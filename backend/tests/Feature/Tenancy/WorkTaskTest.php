<?php

namespace Tests\Feature\Tenancy;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Client;
use App\Models\Department;
use App\Models\Process;
use App\Models\ProcessTemplate;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkTaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_task_lifecycle_requires_reason_and_cascade_blocks_sequence(): void
    {
        $account = Account::factory()->create();
        $member = $this->memberOf($account, 'admin');
        $this->actingAs($member, 'sanctum');

        $template = ProcessTemplate::factory()->create([
            'account_id' => $account->getKey(),
            'cascade' => true,
        ]);
        $process = Process::factory()->create([
            'account_id' => $account->getKey(),
            'template_id' => $template->getKey(),
        ]);
        $task1 = Task::factory()->create([
            'account_id' => $account->getKey(),
            'process_id' => $process->getKey(),
            'order' => 1,
            'title' => 'Etapa 1',
        ]);
        $task2 = Task::factory()->create([
            'account_id' => $account->getKey(),
            'process_id' => $process->getKey(),
            'order' => 2,
            'title' => 'Etapa 2',
        ]);

        $this->patchJson("/api/tasks/{$task2->getKey()}", ['status' => 'doing'])->assertStatus(422);

        $this->patchJson("/api/tasks/{$task1->getKey()}", ['status' => 'dismissed'])->assertStatus(422);

        $this->patchJson("/api/tasks/{$task1->getKey()}", [
            'status' => 'dismissed',
            'dismissal_reason' => 'Sem movimento no mês',
        ])->assertOk()->assertJsonPath('data.status', 'dismissed');

        $this->patchJson("/api/tasks/{$task2->getKey()}", ['status' => 'doing'])
            ->assertOk()->assertJsonPath('data.status', 'doing');
    }

    public function test_dismiss_without_reason_is_rejected_and_user_is_forbidden(): void
    {
        $account = Account::factory()->create();
        $admin = $this->memberOf($account, 'admin');
        $user = $this->memberOf($account, 'user');

        $process = Process::factory()->create([
            'account_id' => $account->getKey(),
            'name' => 'Avulso',
        ]);
        $task = Task::factory()->create([
            'account_id' => $account->getKey(),
            'process_id' => $process->getKey(),
            'title' => 'Etapa única',
        ]);

        $this->actingAs($admin, 'sanctum');
        $this->patchJson("/api/tasks/{$task->getKey()}", ['status' => 'dismissed'])->assertStatus(422);

        $this->actingAs($user, 'sanctum');
        $this->patchJson("/api/tasks/{$task->getKey()}", ['status' => 'doing'])->assertForbidden();
        $this->getJson('/api/tasks')->assertOk();
    }

    public function test_calendar_omits_undated_and_grouped_nests_client_process_task(): void
    {
        $account = Account::factory()->create();
        $member = $this->memberOf($account, 'admin');
        $this->actingAs($member, 'sanctum');

        $client = Client::factory()->company()->create([
            'account_id' => $account->getKey(),
            'status' => 'active',
        ]);
        $process = Process::factory()->create([
            'account_id' => $account->getKey(),
            'name' => 'Março',
            'client_id' => $client->getKey(),
            'reference_month' => '2026-03-01',
            'status' => 'open',
        ]);
        Task::factory()->create([
            'account_id' => $account->getKey(),
            'process_id' => $process->getKey(),
            'title' => 'Sem data',
            'due_on' => null,
            'order' => 1,
        ]);
        Task::factory()->create([
            'account_id' => $account->getKey(),
            'process_id' => $process->getKey(),
            'title' => 'Com data',
            'due_on' => '2026-03-10',
            'order' => 2,
        ]);

        $this->getJson('/api/tasks?status=todo')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/work/calendar?from=2026-03-01&to=2026-03-31')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Com data');

        $this->getJson('/api/work/grouped?reference_month=2026-03')
            ->assertOk()
            ->assertJsonPath('data.0.client.id', $client->getKey())
            ->assertJsonPath('data.0.processes.0.tasks.0.title', 'Com data');
    }

    public function test_reassign_without_status_change_skips_reason_and_cascade_guards(): void
    {
        $account = Account::factory()->create();
        $admin = $this->memberOf($account, 'admin');
        $assignee = $this->memberOf($account, 'operador');
        $this->actingAs($admin, 'sanctum');

        $template = ProcessTemplate::factory()->create([
            'account_id' => $account->getKey(),
            'cascade' => true,
        ]);
        $process = Process::factory()->create([
            'account_id' => $account->getKey(),
            'template_id' => $template->getKey(),
        ]);
        Task::factory()->create([
            'account_id' => $account->getKey(),
            'process_id' => $process->getKey(),
            'order' => 1,
            'title' => 'Etapa 1',
        ]);
        $blockedDoing = Task::factory()->create([
            'account_id' => $account->getKey(),
            'process_id' => $process->getKey(),
            'order' => 2,
            'title' => 'Etapa 2',
            'status' => 'doing',
        ]);
        $dismissed = Task::factory()->create([
            'account_id' => $account->getKey(),
            'process_id' => $process->getKey(),
            'order' => 3,
            'title' => 'Etapa 3',
            'status' => 'dismissed',
            'dismissal_reason' => 'Sem movimento',
        ]);

        $this->patchJson("/api/tasks/{$dismissed->getKey()}", ['assignee_member_id' => $assignee->getKey()])
            ->assertOk()
            ->assertJsonPath('data.status', 'dismissed')
            ->assertJsonPath('data.assignee_member_id', $assignee->getKey());

        $this->patchJson("/api/tasks/{$blockedDoing->getKey()}", ['assignee_member_id' => $assignee->getKey()])
            ->assertOk()
            ->assertJsonPath('data.status', 'doing')
            ->assertJsonPath('data.assignee_member_id', $assignee->getKey());
    }

    public function test_task_department_filter_rejects_unknown_department(): void
    {
        $account = Account::factory()->create();
        $member = $this->memberOf($account, 'admin');
        $this->actingAs($member, 'sanctum');

        $this->getJson('/api/tasks?department=Inexistente')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'department' => 'O departamento informado não está cadastrado nesta conta.',
            ]);

        $this->getJson('/api/work/calendar?from=2026-03-01&to=2026-03-31&department=Inexistente')
            ->assertUnprocessable();
    }

    public function test_task_department_filter_accepts_registered_department(): void
    {
        $account = Account::factory()->create();
        $member = $this->memberOf($account, 'admin');
        $this->actingAs($member, 'sanctum');

        Department::factory()->create(['account_id' => $account->getKey(), 'name' => 'Fiscal']);

        $process = Process::factory()->create(['account_id' => $account->getKey()]);
        Task::factory()->create([
            'account_id' => $account->getKey(),
            'process_id' => $process->getKey(),
            'title' => 'Etapa fiscal',
            'department' => 'Fiscal',
        ]);

        $this->getJson('/api/tasks?department=fISCAL')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_task_reassign_rejects_assignee_outside_task_department(): void
    {
        $account = Account::factory()->create();
        $admin = $this->memberOf($account, 'admin');
        $this->actingAs($admin, 'sanctum');

        $outsider = $this->memberOf($account, 'operador');
        $insider = $this->memberOf($account, 'operador');
        $department = Department::factory()->create(['account_id' => $account->getKey(), 'name' => 'Fiscal']);
        $department->members()->attach($insider->getKey(), ['account_id' => $account->getKey()]);

        $process = Process::factory()->create(['account_id' => $account->getKey()]);
        $task = Task::factory()->create([
            'account_id' => $account->getKey(),
            'process_id' => $process->getKey(),
            'department' => 'Fiscal',
        ]);

        $this->patchJson("/api/tasks/{$task->getKey()}", ['assignee_member_id' => $outsider->getKey()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'assignee_member_id' => 'O responsável precisa pertencer ao departamento da tarefa.',
            ]);

        $this->patchJson("/api/tasks/{$task->getKey()}", ['assignee_member_id' => $insider->getKey()])
            ->assertOk()
            ->assertJsonPath('data.assignee_member_id', $insider->getKey());

        $this->patchJson("/api/tasks/{$task->getKey()}", ['assignee_member_id' => null])->assertOk();
    }

    private function memberOf(Account $account, string $role): User
    {
        $user = User::factory()->create();
        AccountUser::create(['account_id' => $account->getKey(), 'user_id' => $user->getKey(), 'role' => $role]);
        $user->forceFill(['current_account_id' => $account->getKey()])->save();

        return $user->refresh();
    }
}
