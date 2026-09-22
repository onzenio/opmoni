<?php

namespace Tests\Feature\Tenancy;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Client;
use App\Models\ClientCertificate;
use App\Models\ClientEcacPowerOfAttorney;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientEcacPowerOfAttorneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('certificates');
    }

    public function test_operador_upserts_power_of_attorney_and_receives_deadline_status(): void
    {
        CarbonImmutable::setTestNow('2026-09-22 12:00:00');
        $account = Account::factory()->create();
        $operator = $this->memberOf($account, 'operador');
        $client = Client::factory()->company()->create(['account_id' => $account->getKey()]);

        $this->actingAs($operator, 'sanctum')->putJson(
            "/api/clients/{$client->getKey()}/ecac-power-of-attorney",
            ['starts_at' => '2026-01-01', 'expires_at' => '2026-10-10', 'notes' => 'Todos os serviços']
        )->assertOk()
            ->assertJsonPath('data.ecac_power_of_attorney.status', 'expiring');

        CarbonImmutable::setTestNow();
    }

    public function test_incoherent_dates_return_422_and_preserve_record(): void
    {
        $account = Account::factory()->create();
        $client = Client::factory()->company()->create(['account_id' => $account->getKey()]);
        ClientEcacPowerOfAttorney::factory()->create([
            'account_id' => $account->getKey(), 'client_id' => $client->getKey(),
            'starts_at' => '2026-01-01', 'expires_at' => '2026-12-31',
        ]);
        $this->actingAs($this->memberOf($account, 'operador'), 'sanctum');

        $this->putJson("/api/clients/{$client->getKey()}/ecac-power-of-attorney",
            ['starts_at' => '2026-12-01', 'expires_at' => '2026-01-01']
        )->assertUnprocessable()->assertJsonValidationErrors('expires_at');

        $this->assertSame('2026-12-31', ClientEcacPowerOfAttorney::sole()->expires_at->toDateString());
    }

    public function test_user_cannot_write_power_of_attorney(): void
    {
        $account = Account::factory()->create();
        $client = Client::factory()->company()->create(['account_id' => $account->getKey()]);
        $this->actingAs($this->memberOf($account, 'user'), 'sanctum');

        $this->putJson("/api/clients/{$client->getKey()}/ecac-power-of-attorney",
            ['starts_at' => '2026-01-01', 'expires_at' => '2026-12-31'])->assertForbidden();
        $this->deleteJson("/api/clients/{$client->getKey()}/ecac-power-of-attorney")->assertForbidden();
    }

    public function test_cross_account_power_of_attorney_is_404(): void
    {
        $client = Client::factory()->company()->create();
        $this->actingAs($this->memberOf(Account::factory()->create(), 'operador'), 'sanctum');

        $this->putJson("/api/clients/{$client->getKey()}/ecac-power-of-attorney",
            ['starts_at' => '2026-01-01', 'expires_at' => '2026-12-31'])->assertNotFound();
        $this->deleteJson("/api/clients/{$client->getKey()}/ecac-power-of-attorney")->assertNotFound();
    }

    public function test_destroy_returns_204_and_removes_metadata(): void
    {
        $account = Account::factory()->create();
        $client = Client::factory()->company()->create(['account_id' => $account->getKey()]);
        ClientEcacPowerOfAttorney::factory()->create([
            'account_id' => $account->getKey(), 'client_id' => $client->getKey(),
        ]);
        $this->actingAs($this->memberOf($account, 'operador'), 'sanctum');

        $this->deleteJson("/api/clients/{$client->getKey()}/ecac-power-of-attorney")->assertNoContent();
        $this->assertDatabaseCount('client_ecac_powers_of_attorney', 0);
    }

    public function test_index_supports_deadline_status_filter(): void
    {
        CarbonImmutable::setTestNow('2026-09-22 12:00:00');
        $account = Account::factory()->create();
        $missing = Client::factory()->individual()->create(['account_id' => $account->getKey()]);
        $valid = Client::factory()->individual()->create(['account_id' => $account->getKey()]);
        $expiring = Client::factory()->individual()->create(['account_id' => $account->getKey()]);
        $expired = Client::factory()->individual()->create(['account_id' => $account->getKey()]);
        ClientEcacPowerOfAttorney::factory()->create(['account_id' => $account->getKey(),
            'client_id' => $valid->getKey(), 'starts_at' => '2026-01-01', 'expires_at' => '2027-06-01']);
        ClientEcacPowerOfAttorney::factory()->create(['account_id' => $account->getKey(),
            'client_id' => $expiring->getKey(), 'starts_at' => '2026-01-01', 'expires_at' => '2026-10-10']);
        ClientEcacPowerOfAttorney::factory()->create(['account_id' => $account->getKey(),
            'client_id' => $expired->getKey(), 'starts_at' => '2026-01-01', 'expires_at' => '2026-09-10']);
        foreach (['valid' => '2027-06-01', 'expiring' => '2026-10-10', 'expired' => '2026-09-10'] as $key => $date) {
            $target = ${$key};
            ClientCertificate::factory()->create(['account_id' => $account->getKey(),
                'client_id' => $target->getKey(), 'valid_from' => '2026-01-01', 'valid_until' => $date]);
        }
        $this->actingAs($this->memberOf($account, 'operador'), 'sanctum');

        $this->getJson('/api/clients?deadline_status=missing')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $missing->getKey());
        $this->getJson('/api/clients?deadline_status=valid')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $valid->getKey());
        $this->getJson('/api/clients?deadline_status=expiring')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $expiring->getKey());
        $this->getJson('/api/clients?deadline_status=expired')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $expired->getKey());
        $this->getJson('/api/clients?deadline_status=bogus')->assertUnprocessable()
            ->assertJsonValidationErrors('deadline_status');

        CarbonImmutable::setTestNow();
    }

    public function test_client_responses_include_fiscal_statuses_without_n_plus_one(): void
    {
        $account = Account::factory()->create();
        $clients = Client::factory()->individual()->count(6)->create(['account_id' => $account->getKey()]);
        foreach ($clients as $client) {
            ClientEcacPowerOfAttorney::factory()->create([
                'account_id' => $account->getKey(), 'client_id' => $client->getKey(),
            ]);
        }
        $this->actingAs($this->memberOf($account, 'operador'), 'sanctum');

        $countFor = function (int $perPage): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson("/api/clients?per_page={$perPage}")->assertOk()
                ->assertJsonPath('data.0.certificate_status', 'missing')
                ->assertJsonStructure(['data' => [['certificate_status', 'ecac_power_of_attorney_status']]]);
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $this->assertSame($countFor(3), $countFor(6));
    }

    public function test_client_soft_delete_removes_active_ciphertext(): void
    {
        $account = Account::factory()->create();
        $client = Client::factory()->individual()->create(['account_id' => $account->getKey()]);
        $certificate = ClientCertificate::factory()->create([
            'account_id' => $account->getKey(), 'client_id' => $client->getKey(),
            'storage_path' => 'cipher/active.enc',
        ]);
        Storage::disk('certificates')->put('cipher/active.enc', 'secret-bytes');
        $this->actingAs($this->memberOf($account, 'operador'), 'sanctum');

        $this->deleteJson("/api/clients/{$client->getKey()}")->assertNoContent();

        $this->assertSoftDeleted('clients', ['id' => $client->getKey()]);
        $this->assertFalse(Storage::disk('certificates')->exists('cipher/active.enc'));
        $this->assertNotNull($certificate->refresh()->removed_at);
        $this->assertNull($certificate->refresh()->storage_path);
    }

    private function memberOf(Account $account, string $role = 'operador'): User
    {
        $user = User::factory()->create();
        AccountUser::create(['account_id' => $account->getKey(), 'user_id' => $user->getKey(), 'role' => $role]);
        $user->forceFill(['current_account_id' => $account->getKey()])->save();

        return $user->refresh();
    }
}
