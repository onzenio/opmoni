<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Process;
use App\Models\ProcessTemplate;
use App\Models\ProcessTemplateTask;
use App\Tenant\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessGenerationService
{
    /**
     * @return Collection<int, Process>
     */
    public function generate(ProcessTemplate $template, Carbon $month): Collection
    {
        $reference = $month->copy()->startOfMonth()->startOfDay();
        $referenceDate = $reference->toDateString();
        $clients = $this->eligibleClients($template);

        return $clients->map(fn (Client $client): Process => DB::transaction(function () use ($template, $client, $reference, $referenceDate): Process {
            $process = Process::withoutGlobalScope('account')->where([
                'account_id' => $template->account_id,
                'template_id' => $template->getKey(),
                'client_id' => $client->getKey(),
            ])->whereDate('reference_month', $referenceDate)->lockForUpdate()->first();

            if ($process instanceof Process) {
                return $process;
            }

            $process = Process::query()->create([
                'account_id' => $template->account_id,
                'name' => $template->name.' '.$reference->format('m/Y'),
                'template_id' => $template->getKey(),
                'client_id' => $client->getKey(),
                'reference_month' => $reference->toDateString(),
                'status' => 'open',
                'due_on' => $this->resolveDueDate($reference, (int) $template->due_day),
            ]);

            foreach ($template->steps()->orderBy('order')->get() as $step) {
                $process->tasks()->create([
                    'account_id' => $template->account_id,
                    'title' => $step->title,
                    'department' => $step->department,
                    'description' => $step->description,
                    'status' => TaskStatus::Todo->value,
                    'due_on' => $this->resolveDueDate($reference, (int) $step->due_day),
                    'priority' => $step->priority,
                    'assignee_member_id' => $this->resolveAssignee($template->account_id, $step),
                    'order' => $step->order,
                ]);
            }

            return $process;
        }));
    }

    /**
     * @return Collection<int, array{client: Client, reason: string}>
     */
    public function preview(ProcessTemplate $template): Collection
    {
        return $this->eligibleClients($template)->map(fn (Client $client): array => [
            'client' => $client,
            'reason' => $this->matchReason($template, $client),
        ]);
    }

    /** @return Collection<int, Client> */
    public function eligibleClients(ProcessTemplate $template): Collection
    {
        resolve(CurrentTenant::class)->accountId = $template->account_id;

        $regimes = $template->regimes ?? [];
        $tagIds = $template->tags()->pluck('tags.id')->all();
        $removed = $template->exceptions()->where('kind', 'removed')->pluck('client_id')->all();
        $added = $template->exceptions()->where('kind', 'added')->pluck('client_id')->all();

        $base = Client::query()
            ->where('status', 'active')
            ->when($regimes !== [], fn ($query) => $query->whereIn('tax_regime', $regimes))
            ->when($tagIds !== [], fn ($query) => $query->whereHas('tags', fn ($tags) => $tags->whereKey($tagIds)))
            ->when($removed !== [], fn ($query) => $query->whereNotIn('clients.id', $removed))
            ->orderBy('name')->get();

        if ($added === []) {
            return $base;
        }

        $extra = Client::query()->whereKey($added)->where('status', 'active')->orderBy('name')->get();

        return $base->merge($extra)->unique('id')->values();
    }

    private function matchReason(ProcessTemplate $template, Client $client): string
    {
        $added = $template->exceptions()->where('kind', 'added')->pluck('client_id')->all();

        return in_array($client->getKey(), $added, true) ? 'added' : 'rule';
    }

    private function resolveDueDate(Carbon $reference, int $day): string
    {
        $capped = min(max($day, 1), $reference->daysInMonth);

        return $reference->copy()->day($capped)->toDateString();
    }

    /**
     * A geração agendada não pode falhar o mês inteiro por um responsável
     * fora do departamento (ex.: saiu do time após a escrita do modelo):
     * gera a task com responsável nulo e registra auditoria em log.
     */
    private function resolveAssignee(int $accountId, ProcessTemplateTask $step): ?int
    {
        $assignee = $step->default_assignee_member_id;

        if ($assignee === null) {
            return null;
        }

        $departmentId = DepartmentMembership::findId($accountId, $step->department);

        if ($departmentId !== null && ! DepartmentMembership::memberBelongs($accountId, $departmentId, (int) $assignee)) {
            Log::warning('work.generation.assignee_outside_department', [
                'account_id' => $accountId,
                'template_id' => $step->template_id,
                'step_id' => $step->getKey(),
                'department' => $step->department,
                'assignee_member_id' => (int) $assignee,
            ]);

            return null;
        }

        return (int) $assignee;
    }
}
