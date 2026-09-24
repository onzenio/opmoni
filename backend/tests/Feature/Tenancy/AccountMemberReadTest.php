<?php

namespace Tests\Feature\Tenancy;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountMemberReadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_operador_lists_members(): void
    {
        $account = Account::factory()->create();
        $operador = $this->memberOf($account, 'operador');

        $this->actingAs($operador, 'sanctum')
            ->getJson('/api/account/members')
            ->assertOk();
    }

    public function test_user_lists_members(): void
    {
        $account = Account::factory()->create();
        $user = $this->memberOf($account, 'user');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/account/members')
            ->assertOk();
    }

    public function test_index_show_and_directory_share_read_ability(): void
    {
        $account = Account::factory()->create();
        $operador = $this->memberOf($account, 'operador');
        $user = $this->memberOf($account, 'user');

        foreach ([$operador, $user] as $member) {
            $this->actingAs($member, 'sanctum');
            $this->getJson('/api/account/members')->assertOk();
            $this->getJson("/api/account/members/{$user->getKey()}")->assertOk();
            $this->getJson('/api/account/members/directory')->assertOk();
        }
    }

    public function test_show_cross_account_returns_404(): void
    {
        $account = Account::factory()->create();
        $other = Account::factory()->create();
        $operador = $this->memberOf($account, 'operador');
        $foreign = $this->memberOf($other, 'admin');

        $this->actingAs($operador, 'sanctum')
            ->getJson("/api/account/members/{$foreign->getKey()}")
            ->assertNotFound();
    }

    public function test_writes_still_require_admin(): void
    {
        $account = Account::factory()->create();
        $operador = $this->memberOf($account, 'operador');

        $this->actingAs($operador, 'sanctum')
            ->postJson('/api/account/members', [
                'name' => 'Convidado',
                'email' => 'convidado@opmoni.dev',
                'password' => 'password123',
                'role' => 'user',
            ])
            ->assertForbidden();
    }

    private function memberOf(Account $account, string $role): User
    {
        $user = User::factory()->create();
        AccountUser::create(['account_id' => $account->getKey(), 'user_id' => $user->getKey(), 'role' => $role]);
        $user->forceFill(['current_account_id' => $account->getKey()])->save();

        return $user->refresh();
    }
}
