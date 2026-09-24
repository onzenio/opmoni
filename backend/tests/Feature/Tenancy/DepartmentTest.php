<?php

namespace Tests\Feature\Tenancy;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Department;
use App\Models\ProcessTemplate;
use App\Models\SupportAccessLog;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_operador_creates_department_with_members(): void
    {
        $account = Account::factory()->create();
        $operador = $this->memberOf($account, 'operador');
        $ana = $this->memberOf($account, 'user', ['name' => 'Ana']);

        $response = $this->actingAs($operador, 'sanctum')->postJson('/api/departments', [
            'name' => 'Fiscal',
            'color' => 'success',
            'member_ids' => [$ana->getKey()],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Fiscal');
        $response->assertJsonPath('data.color', 'success');

        $this->assertDatabaseHas('departments', [
            'account_id' => $account->getKey(),
            'name' => 'Fiscal',
        ]);

        $departmentId = $response->json('data.id');

        $this->assertDatabaseHas('department_user', [
            'department_id' => $departmentId,
            'user_id' => $ana->getKey(),
            'account_id' => $account->getKey(),
        ]);

        $this->getJson('/api/account/members/directory')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $ana->getKey(),
                'name' => 'Ana',
            ]);
    }

    public function test_user_is_forbidden_and_foreign_member_rejected(): void
    {
        $account = Account::factory()->create();
        $other = Account::factory()->create();
        $user = $this->memberOf($account, 'user');
        $outsider = $this->memberOf($other, 'user');

        $this->actingAs($user, 'sanctum')->postJson('/api/departments', [
            'name' => 'Fiscal',
            'color' => 'success',
        ])->assertForbidden();

        $this->assertDatabaseCount('departments', 0);

        $admin = $this->memberOf($account, 'admin');

        $this->actingAs($admin, 'sanctum')->postJson('/api/departments', [
            'name' => 'Fiscal',
            'color' => 'success',
            'member_ids' => [$outsider->getKey()],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('departments', 0);
    }

    public function test_duplicate_name_with_different_case_is_rejected(): void
    {
        $account = Account::factory()->create();
        $admin = $this->memberOf($account, 'admin');

        $this->actingAs($admin, 'sanctum')->postJson('/api/departments', [
            'name' => 'Fiscal',
            'color' => 'success',
        ])->assertCreated();

        $this->actingAs($admin, 'sanctum')->postJson('/api/departments', [
            'name' => '  fIScaL  ',
            'color' => 'primary',
        ])->assertUnprocessable();

        $this->assertSame(1, Department::count());
    }

    public function test_departments_are_isolated_between_accounts(): void
    {
        $account = Account::factory()->create();
        $other = Account::factory()->create();
        $admin = $this->memberOf($account, 'admin');
        $foreign = $this->memberOf($other, 'admin');

        $departmentId = $this->actingAs($admin, 'sanctum')->postJson('/api/departments', [
            'name' => 'Fiscal',
            'color' => 'success',
        ])->assertCreated()->json('data.id');

        $this->actingAs($foreign, 'sanctum')->getJson('/api/departments')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonMissing(['name' => 'Fiscal']);

        $this->actingAs($foreign, 'sanctum')->patchJson("/api/departments/{$departmentId}", [
            'name' => 'Roubado',
        ])->assertNotFound();

        $this->actingAs($foreign, 'sanctum')->deleteJson("/api/departments/{$departmentId}")
            ->assertNotFound();

        $this->assertDatabaseHas('departments', ['id' => $departmentId, 'name' => 'Fiscal']);
    }

    public function test_crud_follows_role_and_update_syncs_members(): void
    {
        $account = Account::factory()->create();
        $operador = $this->memberOf($account, 'operador');
        $user = $this->memberOf($account, 'user');
        $ana = $this->memberOf($account, 'user', ['name' => 'Ana']);
        $beto = $this->memberOf($account, 'user', ['name' => 'Beto']);

        $departmentId = $this->actingAs($operador, 'sanctum')->postJson('/api/departments', [
            'name' => 'Fiscal',
            'color' => 'success',
            'member_ids' => [$ana->getKey()],
        ])->assertCreated()->json('data.id');

        $this->actingAs($operador, 'sanctum')->getJson('/api/departments')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Fiscal');

        $this->actingAs($user, 'sanctum')->getJson('/api/departments')->assertOk();

        $this->actingAs($operador, 'sanctum')->patchJson("/api/departments/{$departmentId}", [
            'name' => 'Pessoal',
            'member_ids' => [$beto->getKey()],
        ])->assertOk()->assertJsonPath('data.name', 'Pessoal');

        $this->assertDatabaseMissing('department_user', [
            'department_id' => $departmentId,
            'user_id' => $ana->getKey(),
        ]);
        $this->assertDatabaseHas('department_user', [
            'department_id' => $departmentId,
            'user_id' => $beto->getKey(),
        ]);

        $this->actingAs($user, 'sanctum')->patchJson("/api/departments/{$departmentId}", [
            'name' => 'Bloqueado',
        ])->assertForbidden();

        $this->actingAs($user, 'sanctum')->deleteJson("/api/departments/{$departmentId}")
            ->assertForbidden();

        $this->actingAs($operador, 'sanctum')->deleteJson("/api/departments/{$departmentId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('departments', ['id' => $departmentId]);
        $this->assertDatabaseHas('users', ['id' => $ana->getKey()]);
        $this->assertDatabaseHas('users', ['id' => $beto->getKey()]);
    }

    public function test_support_writes_are_audited(): void
    {
        $superAdmin = $this->superAdminWithOwnAccount();
        $target = Account::factory()->create();

        $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/support/accounts/{$target->getKey()}/enter")
            ->assertOk();

        $departmentId = $this->actingAs($superAdmin, 'sanctum')->postJson('/api/departments', [
            'name' => 'Fiscal',
            'color' => 'success',
        ])->assertCreated()->json('data.id');

        $this->actingAs($superAdmin, 'sanctum')->patchJson("/api/departments/{$departmentId}", [
            'name' => 'Pessoal',
        ])->assertOk();

        $this->actingAs($superAdmin, 'sanctum')->deleteJson("/api/departments/{$departmentId}")
            ->assertNoContent();

        foreach (['create', 'update', 'delete'] as $action) {
            $this->assertDatabaseHas('support_access_logs', [
                'super_admin_user_id' => $superAdmin->getKey(),
                'account_id' => $target->getKey(),
                'action' => $action,
            ]);
        }

        $log = SupportAccessLog::firstWhere(['action' => 'create', 'account_id' => $target->getKey()]);
        $this->assertSame('departments', $log->metadata['resource']);
        $this->assertSame($departmentId, $log->metadata['resource_id']);
    }

    public function test_destroy_is_blocked_when_used_by_template_steps(): void
    {
        $account = Account::factory()->create();
        $other = Account::factory()->create();
        $operador = $this->memberOf($account, 'operador');
        $this->actingAs($operador, 'sanctum');

        $departmentId = $this->postJson('/api/departments', [
            'name' => 'Fiscal',
            'color' => 'success',
        ])->assertCreated()->json('data.id');

        $template = ProcessTemplate::factory()->create(['account_id' => $account->getKey()]);
        $template->steps()->create([
            'account_id' => $account->getKey(), 'title' => 'Apurar', 'department' => 'fiscal',
            'due_day' => 3, 'priority' => 'medium', 'order' => 1,
        ]);

        $foreignTemplate = ProcessTemplate::factory()->create(['account_id' => $other->getKey()]);
        $foreignTemplate->steps()->create([
            'account_id' => $other->getKey(), 'title' => 'Apurar', 'department' => 'Fiscal',
            'due_day' => 3, 'priority' => 'medium', 'order' => 1,
        ]);

        $this->deleteJson("/api/departments/{$departmentId}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['department']);

        $this->assertDatabaseHas('departments', ['id' => $departmentId]);

        $template->steps()->delete();

        $this->deleteJson("/api/departments/{$departmentId}")->assertNoContent();
        $this->assertDatabaseMissing('departments', ['id' => $departmentId]);
    }

    public function test_destroy_is_blocked_by_open_tasks_but_not_closed_ones(): void
    {
        $account = Account::factory()->create();
        $operador = $this->memberOf($account, 'operador');
        $this->actingAs($operador, 'sanctum');

        $departmentId = $this->postJson('/api/departments', [
            'name' => 'Fiscal',
            'color' => 'success',
        ])->assertCreated()->json('data.id');

        $open = Task::factory()->create([
            'account_id' => $account->getKey(), 'department' => 'Fiscal', 'status' => 'doing',
        ]);
        Task::factory()->create([
            'account_id' => $account->getKey(), 'department' => 'Fiscal', 'status' => 'done',
        ]);
        Task::factory()->create([
            'account_id' => $account->getKey(), 'department' => 'Fiscal', 'status' => 'dismissed',
            'dismissal_reason' => 'Sem movimento',
        ]);

        $this->deleteJson("/api/departments/{$departmentId}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['department']);

        $this->assertDatabaseHas('departments', ['id' => $departmentId]);

        $open->forceFill(['status' => 'done'])->save();

        $this->deleteJson("/api/departments/{$departmentId}")->assertNoContent();
        $this->assertDatabaseMissing('departments', ['id' => $departmentId]);
        $this->assertDatabaseCount('tasks', 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function memberOf(Account $account, string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        AccountUser::create(['account_id' => $account->getKey(), 'user_id' => $user->getKey(), 'role' => $role]);
        $user->forceFill(['current_account_id' => $account->getKey()])->save();

        return $user->refresh();
    }

    private function superAdminWithOwnAccount(): User
    {
        $superAdmin = User::factory()->create();
        $superAdmin->forceFill(['is_super_admin' => true])->save();
        $home = Account::factory()->create();

        AccountUser::create([
            'account_id' => $home->getKey(),
            'user_id' => $superAdmin->getKey(),
            'role' => 'admin',
        ]);

        $superAdmin->forceFill(['current_account_id' => $home->getKey()])->save();

        return $superAdmin->refresh();
    }
}
