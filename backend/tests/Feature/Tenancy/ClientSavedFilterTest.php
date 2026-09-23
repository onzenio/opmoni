<?php

namespace Tests\Feature\Tenancy;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientSavedFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_filters_belong_to_the_user_inside_the_account(): void
    {
        $account = Account::factory()->create();
        $owner = $this->memberOf($account, 'user');
        $peer = $this->memberOf($account, 'operador');
        $tag = Tag::factory()->create(['account_id' => $account->getKey(), 'name' => 'Prioridade']);

        $this->actingAs($owner, 'sanctum');

        $id = $this->postJson('/api/clients/saved-filters', [
            'name' => '  Simples  ',
            'q' => '  amaral  ',
            'filters' => [[
                'columnId' => 'regime',
                'operator' => 'is',
                'values' => ['simple_national'],
            ], [
                'columnId' => 'tag',
                'operator' => 'is any of',
                'values' => [(string) $tag->getKey()],
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Simples')
            ->assertJsonPath('data.q', 'amaral')
            ->json('data.id');

        $this->getJson('/api/clients/saved-filters')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Simples');

        $this->actingAs($peer, 'sanctum')
            ->getJson('/api/clients/saved-filters')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($peer, 'sanctum')
            ->deleteJson('/api/clients/saved-filters/'.$id)
            ->assertNotFound();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/clients/saved-filters', [
                'name' => 'Simples',
                'filters' => [[
                    'columnId' => 'status',
                    'operator' => 'is not',
                    'values' => ['inactive'],
                ]],
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->deleteJson('/api/clients/saved-filters/'.$id)->assertNoContent();
        $this->getJson('/api/clients/saved-filters')->assertJsonCount(0, 'data');
    }

    public function test_saved_filter_rejects_unknown_values(): void
    {
        $account = Account::factory()->create();
        $this->actingAs($this->memberOf($account), 'sanctum');

        $this->postJson('/api/clients/saved-filters', [
            'name' => 'Ruim',
            'filters' => [[
                'columnId' => 'certificate',
                'operator' => 'is',
                'values' => ['nope'],
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('filters.0.values.0');
    }

    private function memberOf(Account $account, string $role = 'operador'): User
    {
        $user = User::factory()->create();
        AccountUser::create(['account_id' => $account->getKey(), 'user_id' => $user->getKey(), 'role' => $role]);
        $user->forceFill(['current_account_id' => $account->getKey()])->save();

        return $user->refresh();
    }
}
