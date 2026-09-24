<?php

namespace Tests\Feature\Tenancy;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Process;
use App\Models\ProcessTemplate;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_cross_account_work_ids_return_404(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();
        $memberA = $this->memberOf($accountA, 'admin');

        $foreignTemplate = ProcessTemplate::factory()->create(['account_id' => $accountB->getKey()]);
        $foreignProcess = Process::factory()->create([
            'account_id' => $accountB->getKey(),
            'template_id' => $foreignTemplate->getKey(),
        ]);
        $foreignTask = Task::factory()->create([
            'account_id' => $accountB->getKey(),
            'process_id' => $foreignProcess->getKey(),
        ]);

        $this->actingAs($memberA, 'sanctum');

        $this->getJson("/api/process-templates/{$foreignTemplate->getKey()}")->assertNotFound();
        $this->getJson("/api/process-templates/{$foreignTemplate->getKey()}/preview")->assertNotFound();
        $this->getJson("/api/processes/{$foreignProcess->getKey()}")->assertNotFound();
        $this->getJson("/api/tasks/{$foreignTask->getKey()}")->assertNotFound();
        $this->patchJson("/api/tasks/{$foreignTask->getKey()}", ['status' => 'doing'])->assertNotFound();
        $this->deleteJson("/api/process-templates/{$foreignTemplate->getKey()}")->assertNotFound();
        $this->deleteJson("/api/processes/{$foreignProcess->getKey()}")->assertNotFound();
    }

    public function test_member_lists_only_own_account_work(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();

        ProcessTemplate::factory()->create(['account_id' => $accountA->getKey(), 'name' => 'Local']);
        ProcessTemplate::factory()->create(['account_id' => $accountB->getKey(), 'name' => 'Estrangeiro']);

        $ownProcess = Process::factory()->create(['account_id' => $accountA->getKey(), 'name' => 'Local']);
        Process::factory()->create(['account_id' => $accountB->getKey(), 'name' => 'Estrangeiro']);
        Task::factory()->create(['account_id' => $accountA->getKey(), 'process_id' => $ownProcess->getKey(), 'title' => 'Local']);
        Task::factory()->create(['account_id' => $accountB->getKey(), 'process_id' => Process::factory()->create(['account_id' => $accountB->getKey()])->getKey(), 'title' => 'Estrangeiro']);

        $this->actingAs($this->memberOf($accountA, 'admin'), 'sanctum');

        $this->getJson('/api/process-templates')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Local');

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Local');
    }

    public function test_work_role_matrix_admin_operador_write_and_user_read_only(): void
    {
        $account = Account::factory()->create();
        $admin = $this->memberOf($account, 'admin');
        $operador = $this->memberOf($account, 'operador');
        $user = $this->memberOf($account, 'user');

        $process = Process::factory()->create(['account_id' => $account->getKey(), 'name' => 'Avulso']);
        $task = Task::factory()->create([
            'account_id' => $account->getKey(),
            'process_id' => $process->getKey(),
            'title' => 'Etapa',
        ]);

        // Leitura: os três níveis listam e abrem.
        foreach ([$admin, $operador, $user] as $member) {
            $this->actingAs($member, 'sanctum');
            $this->getJson('/api/process-templates')->assertOk();
            $this->getJson('/api/processes')->assertOk();
            $this->getJson('/api/tasks')->assertOk();
            $this->getJson("/api/processes/{$process->getKey()}")->assertOk();
            $this->getJson("/api/tasks/{$task->getKey()}")->assertOk();
        }

        // Escrita em templates: admin e operador criam/atualizam/excluem; user é barrado.
        $payload = ['name' => 'Escrita'];
        $this->actingAs($admin, 'sanctum');
        $adminTemplateId = $this->postJson('/api/process-templates', $payload)->assertCreated()->json('data.id');

        $this->actingAs($operador, 'sanctum');
        $operadorTemplateId = $this->postJson('/api/process-templates', $payload)->assertCreated()->json('data.id');
        $this->patchJson("/api/process-templates/{$operadorTemplateId}", ['description' => 'Por operador'])->assertOk();

        $this->actingAs($user, 'sanctum');
        $this->postJson('/api/process-templates', $payload)->assertForbidden();
        $this->patchJson("/api/process-templates/{$operadorTemplateId}", ['description' => 'Por user'])->assertForbidden();
        $this->deleteJson("/api/process-templates/{$operadorTemplateId}")->assertForbidden();

        // Escrita em processos e tasks: operador escreve, user só lê.
        $this->actingAs($operador, 'sanctum');
        $this->patchJson("/api/processes/{$process->getKey()}", ['name' => 'Renomeado'])->assertOk();
        $this->patchJson("/api/tasks/{$task->getKey()}", ['status' => 'doing'])->assertOk();

        $this->actingAs($user, 'sanctum');
        $this->patchJson("/api/processes/{$process->getKey()}", ['name' => 'Por user'])->assertForbidden();
        $this->deleteJson("/api/processes/{$process->getKey()}")->assertForbidden();
        $this->patchJson("/api/tasks/{$task->getKey()}", ['status' => 'doing'])->assertForbidden();

        // Limpeza pelos níveis com escrita.
        $this->actingAs($admin, 'sanctum');
        $this->deleteJson("/api/processes/{$process->getKey()}")->assertNoContent();
        $this->deleteJson("/api/process-templates/{$adminTemplateId}")->assertNoContent();
    }

    private function memberOf(Account $account, string $role): User
    {
        $user = User::factory()->create();
        AccountUser::create(['account_id' => $account->getKey(), 'user_id' => $user->getKey(), 'role' => $role]);
        $user->forceFill(['current_account_id' => $account->getKey()])->save();

        return $user->refresh();
    }
}
