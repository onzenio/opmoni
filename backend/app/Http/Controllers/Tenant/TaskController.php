<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Process;
use App\Models\Task;
use App\Services\SupportAudit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Task::class);

        $filters = $request->validate([
            'process_id' => ['sometimes', 'integer'],
            'client_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', 'string'],
            'assignee_member_id' => ['sometimes', 'integer'],
            'department' => ['sometimes', 'string'],
            'priority' => ['sometimes', 'string'],
            'due_from' => ['sometimes', 'date'],
            'due_to' => ['sometimes', 'date'],
        ]);

        $tasks = Task::query()
            ->with(['process.client'])
            ->when(isset($filters['process_id']), fn (Builder $query) => $query->where('process_id', $filters['process_id']))
            ->when(isset($filters['client_id']), fn (Builder $query) => $query->whereHas('process', fn (Builder $processes) => $processes->where('client_id', $filters['client_id'])))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['assignee_member_id']), fn (Builder $query) => $query->where('assignee_member_id', $filters['assignee_member_id']))
            ->when(isset($filters['department']), fn (Builder $query) => $query->where('department', $filters['department']))
            ->when(isset($filters['priority']), fn (Builder $query) => $query->where('priority', $filters['priority']))
            ->when(isset($filters['due_from']) || isset($filters['due_to']), fn (Builder $query) => $query
                ->when(isset($filters['due_from']), fn (Builder $dates) => $dates->whereDate('due_on', '>=', $filters['due_from']))
                ->when(isset($filters['due_to']), fn (Builder $dates) => $dates->whereDate('due_on', '<=', $filters['due_to'])))
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    public function show(Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return new TaskResource($task->load('process.client'));
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $data = $request->validated();

        $from = $task->status instanceof TaskStatus ? $task->status->value : (string) $task->status;
        $to = $data['status'] ?? $from;
        $statusChanging = array_key_exists('status', $data) && $data['status'] !== $from;

        if ($statusChanging && $to === TaskStatus::Dismissed->value && empty($data['dismissal_reason'])) {
            abort(response()->json([
                'message' => 'Motivo obrigatório ao dispensar.',
                'errors' => ['dismissal_reason' => ['Motivo obrigatório.']],
            ], 422));
        }

        if ($statusChanging && $to !== TaskStatus::Todo->value && $task->process->template?->cascade) {
            $blocked = $task->process->tasks()
                ->where('order', '<', $task->order)
                ->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Dismissed->value])
                ->exists();

            if ($blocked) {
                abort(response()->json([
                    'message' => 'Etapa anterior pendente bloqueia o avanço (cascata).',
                ], 422));
            }
        }

        $task->status = $to;

        if ($statusChanging) {
            if (in_array($to, [TaskStatus::Done->value, TaskStatus::Dismissed->value], true)) {
                $task->completed_at ??= now();
                $task->dismissal_reason = $to === TaskStatus::Dismissed->value ? $data['dismissal_reason'] : null;
            } else {
                $task->completed_at = null;
                $task->dismissal_reason = null;
            }
        }

        if (array_key_exists('assignee_member_id', $data)) {
            $task->assignee_member_id = $data['assignee_member_id'];
        }

        $task->save();

        SupportAudit::logWrite($request, 'tasks', 'update', $task->getKey(), ['status' => $to]);

        return new TaskResource($task->load('process.client'));
    }

    public function calendar(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Task::class);

        $filters = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'process_id' => ['sometimes', 'integer'],
            'client_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', 'string'],
            'assignee_member_id' => ['sometimes', 'integer'],
            'department' => ['sometimes', 'string'],
            'priority' => ['sometimes', 'string'],
        ]);

        return TaskResource::collection($this->filteredQuery($filters)->get());
    }

    public function grouped(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Task::class);

        $filters = $request->validate([
            'reference_month' => ['required', 'date_format:Y-m'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $filters['reference_month'])->startOfMonth();

        $processes = Process::query()
            ->with(['client', 'tasks' => fn ($query) => $query->ordered()])
            ->whereDate('reference_month', $month->toDateString())
            ->orderBy('name')
            ->get();

        $clients = [];

        foreach ($processes as $process) {
            $clientId = $process->client?->getKey() ?? 0;
            $clientName = $process->client?->name ?? 'Sem cliente';

            if (! isset($clients[$clientId])) {
                $clients[$clientId] = [
                    'client' => ['id' => $clientId, 'name' => $clientName],
                    'totals' => ['processes' => 0, 'tasks' => 0],
                    'processes' => [],
                ];
            }

            $total = $process->tasks->count();
            $done = $process->tasks->filter(fn ($task): bool => $this->taskStatus($task) === TaskStatus::Done->value)->count();

            $processEntry = [
                'process' => ['id' => $process->getKey(), 'name' => $process->name],
                'totals' => ['tasks' => $total, 'done' => $done],
                'ratio' => $total > 0 ? round($done / $total, 2) : 0.0,
                'tasks' => TaskResource::collection($process->tasks)->resolve(),
            ];

            $clients[$clientId]['processes'][] = $processEntry;
            $clients[$clientId]['totals']['processes']++;
            $clients[$clientId]['totals']['tasks'] += $total;
        }

        return response()->json(['data' => array_values($clients)]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        return Task::query()
            ->with(['process.client'])
            ->whereNotNull('due_on')
            ->whereDate('due_on', '>=', $filters['from'])
            ->whereDate('due_on', '<=', $filters['to'])
            ->when(isset($filters['process_id']), fn (Builder $query) => $query->where('process_id', $filters['process_id']))
            ->when(isset($filters['client_id']), fn (Builder $query) => $query->whereHas('process', fn (Builder $processes) => $processes->where('client_id', $filters['client_id'])))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['assignee_member_id']), fn (Builder $query) => $query->where('assignee_member_id', $filters['assignee_member_id']))
            ->when(isset($filters['department']), fn (Builder $query) => $query->where('department', $filters['department']))
            ->when(isset($filters['priority']), fn (Builder $query) => $query->where('priority', $filters['priority']))
            ->ordered();
    }

    private function taskStatus(Task $task): string
    {
        return $task->status instanceof TaskStatus ? $task->status->value : (string) $task->status;
    }
}
