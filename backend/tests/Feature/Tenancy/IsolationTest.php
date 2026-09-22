<?php

namespace Tests\Feature\Tenancy;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class IsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        Route::middleware(['auth:sanctum', 'tenant', SubstituteBindings::class])->group(function (): void {
            Route::get('/_test/clients', function () {
                Gate::authorize('viewAny', Client::class);

                return Client::all();
            });
            Route::get('/_test/clients/{client}', function (Client $client) {
                Gate::authorize('view', $client);

                return $client;
            });
        });

        Route::middleware(['auth:sanctum', 'super_admin'])
            ->get('/_test/admin/ping', fn () => ['ok' => true]);
    }

    public function test_member_lists_only_own_account_clients(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();

        Client::factory()->count(2)->create(['account_id' => $accountA->getKey()]);
        Client::factory()->create(['account_id' => $accountB->getKey()]);

        $member = $this->memberOf($accountA, 'admin');

        $response = $this->actingAs($member, 'sanctum')->getJson('/_test/clients');

        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonFragment(['account_id' => $accountA->getKey()]);
        $response->assertJsonMissing(['account_id' => $accountB->getKey()]);
    }

    public function test_direct_access_to_other_account_resource_returns_404(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();

        $foreign = Client::factory()->create(['account_id' => $accountB->getKey()]);

        $member = $this->memberOf($accountA, 'admin');

        $this->actingAs($member, 'sanctum')
            ->getJson("/_test/clients/{$foreign->getKey()}")
            ->assertNotFound();
    }

    public function test_suspended_account_blocks_tenant_access(): void
    {
        $account = Account::factory()->create();
        Client::factory()->create(['account_id' => $account->getKey()]);

        $member = $this->memberOf($account, 'admin');

        $account->update(['status' => 'suspended']);

        $this->actingAs($member, 'sanctum')
            ->getJson('/_test/clients')
            ->assertForbidden();

        $account->update(['status' => 'active']);

        $this->actingAs($member, 'sanctum')
            ->getJson('/_test/clients')
            ->assertOk();
    }

    public function test_regular_user_switching_to_foreign_account_returns_403_and_keeps_current(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();

        $member = $this->memberOf($accountA, 'user');

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/account/switch', ['account_id' => $accountB->getKey()])
            ->assertForbidden();

        $this->assertSame($accountA->getKey(), $member->refresh()->current_account_id);
    }

    public function test_regular_user_switching_between_own_accounts_succeeds(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();

        $member = $this->memberOf($accountA, 'user');
        AccountUser::create([
            'account_id' => $accountB->getKey(),
            'user_id' => $member->getKey(),
            'role' => 'user',
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/account/switch', ['account_id' => $accountB->getKey()])
            ->assertOk();

        $this->assertSame($accountB->getKey(), $member->refresh()->current_account_id);
    }

    public function test_account_admin_cannot_access_global_routes(): void
    {
        $account = Account::factory()->create();
        $admin = $this->memberOf($account, 'admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/_test/admin/ping')
            ->assertForbidden();

        $superAdmin = User::factory()->create();
        $superAdmin->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/_test/admin/ping')
            ->assertOk();
    }

    private function memberOf(Account $account, string $role): User
    {
        $user = User::factory()->create();

        AccountUser::create([
            'account_id' => $account->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
        ]);

        $user->forceFill(['current_account_id' => $account->getKey()])->save();

        return $user->refresh();
    }
}
