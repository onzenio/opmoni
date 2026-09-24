<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreDepartmentRequest;
use App\Http\Requests\Tenant\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Models\ProcessTemplateTask;
use App\Models\Task;
use App\Services\SupportAudit;
use App\Tenant\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DepartmentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Department::class);

        return DepartmentResource::collection(
            Department::query()->withCount('members')->orderBy('name')->get()
        );
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $department = Department::query()->create([
            'name' => $validated['name'],
            'color' => $validated['color'],
        ]);

        $this->syncMembers($department, $validated['member_ids'] ?? []);

        SupportAudit::logWrite($request, 'departments', 'create', $department->getKey(), ['name' => $department->name]);

        return (new DepartmentResource($department->loadCount('members')))->response()->setStatusCode(201);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): DepartmentResource
    {
        $validated = $request->validated();

        $department->update(array_intersect_key($validated, ['name' => true, 'color' => true]));

        if (array_key_exists('member_ids', $validated)) {
            $this->syncMembers($department, $validated['member_ids'] ?? []);
        }

        SupportAudit::logWrite($request, 'departments', 'update', $department->getKey(), ['name' => $department->name]);

        return new DepartmentResource($department->loadCount('members'));
    }

    public function destroy(Request $request, Department $department): Response
    {
        Gate::authorize('delete', $department);

        $usage = $this->usageDescription($department);

        if ($usage !== null) {
            abort(response()->json([
                'message' => "Não é possível excluir o departamento \"{$department->name}\": em uso em {$usage}.",
                'errors' => ['department' => ["O departamento está em uso em {$usage}."]],
            ], 422));
        }

        $id = $department->getKey();
        $name = $department->name;
        $department->delete();
        SupportAudit::logWrite($request, 'departments', 'delete', $id, ['name' => $name]);

        return response()->noContent();
    }

    /**
     * Descreve onde o departamento está em uso, ou null se pode excluir.
     *
     * Tasks carregam snapshot congelado do nome: só tarefas abertas
     * bloqueiam; concluídas/dispensadas e processos já gerados não.
     */
    private function usageDescription(Department $department): ?string
    {
        $name = mb_strtolower($department->name);

        $steps = ProcessTemplateTask::withoutGlobalScopes()
            ->where('account_id', $department->account_id)
            ->whereRaw('LOWER(department) = ?', [$name])
            ->count();

        $openTasks = Task::withoutGlobalScopes()
            ->where('account_id', $department->account_id)
            ->whereRaw('LOWER(department) = ?', [$name])
            ->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Dismissed->value])
            ->count();

        $parts = [];

        if ($steps > 0) {
            $parts[] = $steps === 1 ? '1 etapa de modelo' : "{$steps} etapas de modelo";
        }

        if ($openTasks > 0) {
            $parts[] = $openTasks === 1 ? '1 tarefa aberta' : "{$openTasks} tarefas abertas";
        }

        return $parts === [] ? null : implode(' e ', $parts);
    }

    /**
     * @param  array<int>  $memberIds
     */
    private function syncMembers(Department $department, array $memberIds): void
    {
        $tenantId = resolve(CurrentTenant::class)->accountId;

        $department->members()->sync(
            collect($memberIds)->unique()->mapWithKeys(fn ($id) => [$id => ['account_id' => $tenantId]])->all()
        );
    }
}
