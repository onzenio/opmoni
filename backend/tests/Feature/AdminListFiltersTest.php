<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminListFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_accounts_are_searched_and_filtered_before_pagination(): void
    {
        $superAdmin = $this->superAdminWithOwnAccount();
        $target = Account::factory()->create([
            'name' => 'Conta Agulha Fiscal',
            'status' => 'suspended',
        ]);
        Account::factory()->create(['name' => 'Conta Agulha Ativa', 'status' => 'active']);
        Account::factory()->count(16)->create();

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/accounts?q=agulha&status=suspended')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $target->getKey());
    }

    public function test_subscriptions_are_searched_by_related_data_and_filtered_before_pagination(): void
    {
        $superAdmin = $this->superAdminWithOwnAccount();
        $target = Account::factory()->create(['name' => 'Conta Assinatura Alvo']);
        $target->subscription()->update([
            'plan_id' => Plan::query()->where('slug', 'profissional')->valueOrFail('id'),
            'status' => 'past_due',
        ]);
        $other = Account::factory()->create(['name' => 'Outra Assinatura']);
        $other->subscription()->update([
            'plan_id' => Plan::query()->where('slug', 'profissional')->valueOrFail('id'),
            'status' => 'active',
        ]);
        Account::factory()->count(16)->create();

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/subscriptions?q=profissional&status=past_due')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.account.id', $target->getKey());
    }

    public function test_users_are_searched_and_filtered_before_pagination(): void
    {
        $superAdmin = $this->superAdminWithOwnAccount();
        $target = User::factory()->create([
            'name' => 'Pessoa Agulha',
            'email' => 'agulha@example.test',
        ]);
        $other = User::factory()->create([
            'name' => 'Admin Agulha',
            'email' => 'admin.agulha@example.test',
        ]);
        $other->forceFill(['is_super_admin' => true])->save();
        User::factory()->count(16)->create();

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/users?q=agulha%40example.test&type=user')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $target->getKey());
    }

    public function test_admin_list_filters_reject_unsupported_values(): void
    {
        $superAdmin = $this->superAdminWithOwnAccount();

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/accounts?status=deleted')
            ->assertUnprocessable();

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/subscriptions?status=trial')
            ->assertUnprocessable();

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/users?type=operator')
            ->assertUnprocessable();
    }

    public function test_admin_search_treats_like_wildcards_as_literal_characters(): void
    {
        $superAdmin = $this->superAdminWithOwnAccount();
        Account::factory()->create(['name' => 'Conta Fiscal']);
        User::factory()->create(['name' => 'Pessoa Fiscal', 'email' => 'fiscal@example.test']);
        $accountWithUnderscore = Account::factory()->create(['name' => 'Conta_Literal']);
        $userWithUnderscore = User::factory()->create(['name' => 'Pessoa Literal', 'email' => 'literal_name@example.test']);

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/accounts?q=%25')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/subscriptions?q=%25')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/users?q=%25')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/accounts?q=_')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $accountWithUnderscore->getKey());

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/subscriptions?q=_')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.account.id', $accountWithUnderscore->getKey());

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/admin/users?q=_')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $userWithUnderscore->getKey());
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
