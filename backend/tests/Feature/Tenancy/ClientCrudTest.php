<?php

namespace Tests\Feature\Tenancy;

use App\Models\Account;
use App\Models\Client;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_tax_id_is_unique_inside_account(): void
    {
        $first = Account::factory()->create();

        Client::factory()->company()->create([
            'account_id' => $first->getKey(),
            'tax_id' => '27865757000102',
        ]);

        $this->expectException(QueryException::class);
        Client::factory()->company()->create([
            'account_id' => $first->getKey(),
            'tax_id' => '27865757000102',
        ]);
    }

    public function test_same_tax_id_is_allowed_in_another_account(): void
    {
        $first = Account::factory()->create();
        $second = Account::factory()->create();

        Client::factory()->company()->create([
            'account_id' => $first->getKey(),
            'tax_id' => '27865757000102',
        ]);

        $client = Client::factory()->company()->create([
            'account_id' => $second->getKey(),
            'tax_id' => '27865757000102',
        ]);

        $this->assertSame($second->getKey(), $client->account_id);
    }

    public function test_soft_deleted_client_is_hidden_and_does_not_count_toward_plan_limit(): void
    {
        $account = Account::factory()->create();
        $client = Client::factory()->company()->create(['account_id' => $account->getKey()]);

        $client->delete();

        $this->assertSame(0, $account->clients()->count());
        $this->assertSame(1, Client::withoutGlobalScopes()->withTrashed()->count());
    }
}
