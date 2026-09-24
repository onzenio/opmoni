<?php

namespace Tests\Feature\Tenancy;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_operador_and_user_list_directory_without_email(): void
    {
        $account = Account::factory()->create();
        $this->memberOf($account, 'operador', ['name' => 'Beto', 'email' => 'beto@opmoni.dev']);
        $this->memberOf($account, 'user', ['name' => 'Ana', 'email' => 'ana@opmoni.dev']);

        foreach (['operador', 'user'] as $role) {
            $member = User::firstWhere('email', $role === 'operador' ? 'beto@opmoni.dev' : 'ana@opmoni.dev');
            $response = $this->actingAs($member, 'sanctum')->getJson('/api/account/members/directory');
            $response->assertOk();
            $response->assertJsonPath('data.0.name', 'Ana');
            $response->assertJsonMissing(['email' => 'ana@opmoni.dev']);
        }
    }

    public function test_user_reads_directory_shape_used_by_work_assignment(): void
    {
        $account = Account::factory()->create();
        $this->memberOf($account, 'operador', ['name' => 'Beto', 'email' => 'beto@opmoni.dev']);
        $user = $this->memberOf($account, 'user', ['name' => 'Ana', 'email' => 'ana@opmoni.dev']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/account/members/directory');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'name', 'role', 'departments']]]);
        $response->assertJsonPath('data.0.name', 'Ana');
        $response->assertJsonMissing(['email' => 'ana@opmoni.dev']);
        $response->assertJsonMissing(['email' => 'beto@opmoni.dev']);
    }

    public function test_directory_never_leaks_other_account(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();
        $this->memberOf($accountB, 'admin', ['name' => 'Forasteiro']);
        $member = $this->memberOf($accountA, 'operador');

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/account/members/directory');

        $response->assertOk();
        $response->assertJsonMissing(['name' => 'Forasteiro']);
    }

    public function test_operador_still_cannot_invite_members(): void
    {
        $account = Account::factory()->create();
        $operador = $this->memberOf($account, 'operador');

        $this->actingAs($operador, 'sanctum')->postJson('/api/account/members', [
            'name' => 'Convidado', 'email' => 'convidado@opmoni.dev',
            'password' => 'password123', 'role' => 'user',
        ])->assertForbidden();
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
}
