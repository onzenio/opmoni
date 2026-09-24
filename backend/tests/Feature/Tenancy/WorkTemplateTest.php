<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TaskStatus;
use App\Enums\TaxRegime;
use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Client;
use App\Models\ProcessTemplate;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_work_tables_exist_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('process_templates'));
        $this->assertTrue(Schema::hasColumns('process_templates', ['id', 'account_id', 'name', 'cascade', 'generate_day', 'due_day', 'is_active', 'regimes']));
        $this->assertTrue(Schema::hasTable('process_template_tasks'));
        $this->assertTrue(Schema::hasTable('tasks'));
        $this->assertTrue(Schema::hasColumns('processes', ['client_id', 'template_id', 'reference_month', 'status', 'due_on']));
        $this->assertSame(['todo', 'doing', 'done', 'dismissed'], TaskStatus::values());
    }

    public function test_template_tags_attach_and_retrieve(): void
    {
        $template = ProcessTemplate::factory()->create();
        $tag = Tag::factory()->create(['account_id' => $template->account_id]);

        $template->tags()->attach($tag->getKey(), ['account_id' => $template->account_id]);

        $this->assertTrue($template->tags()->whereKey($tag->getKey())->exists());
        $this->assertSame($template->account_id, $template->tags()->first()->pivot->account_id);
    }

    public function test_operador_creates_template_with_blueprint_and_user_is_forbidden(): void
    {
        $account = Account::factory()->create();
        $operador = $this->memberOf($account, 'operador');
        $user = $this->memberOf($account, 'user');

        $payload = [
            'name' => 'PGDAS',
            'cascade' => true,
            'generate_day' => 1,
            'due_day' => 20,
            'regimes' => ['simple_national'],
            'tag_ids' => [],
            'exceptions' => [],
            'steps' => [
                ['title' => 'Apurar', 'department' => 'Fiscal', 'due_day' => 3, 'priority' => 'medium', 'order' => 1],
                ['title' => 'Transmitir', 'department' => 'Fiscal', 'due_day' => 5, 'priority' => 'high', 'order' => 2],
            ],
        ];

        $id = $this->actingAs($operador, 'sanctum')->postJson('/api/process-templates', $payload)
            ->assertCreated()->json('data.id');

        $this->assertDatabaseHas('process_templates', ['id' => $id, 'account_id' => $account->getKey(), 'name' => 'PGDAS']);
        $this->assertDatabaseCount('process_template_tasks', 2);

        $this->getJson("/api/process-templates/{$id}")->assertOk()->assertJsonPath('data.steps.0.title', 'Apurar');

        $this->actingAs($user, 'sanctum')->postJson('/api/process-templates', $payload)->assertForbidden();
        $this->actingAs($user, 'sanctum')->getJson('/api/process-templates')->assertOk();
    }

    public function test_preview_returns_eligible_without_creating(): void
    {
        $account = Account::factory()->create();
        $operador = $this->memberOf($account, 'operador');
        $this->actingAs($operador, 'sanctum');

        $template = ProcessTemplate::factory()->create([
            'account_id' => $account->getKey(),
            'name' => 'PGDAS',
            'regimes' => [TaxRegime::SimpleNational->value],
        ]);

        Client::factory()->company()->create([
            'account_id' => $account->getKey(),
            'tax_regime' => TaxRegime::SimpleNational,
            'status' => 'active',
            'name' => 'Elegivel SA',
        ]);
        Client::factory()->company()->create([
            'account_id' => $account->getKey(),
            'tax_regime' => TaxRegime::PresumedProfit,
            'status' => 'active',
            'name' => 'Fora SA',
        ]);

        $this->getJson("/api/process-templates/{$template->getKey()}/preview")
            ->assertOk()
            ->assertJsonPath('data.0.reason', 'rule')
            ->assertJsonPath('data.0.client.name', 'Elegivel SA');

        $this->assertDatabaseCount('processes', 0);
    }

    private function memberOf(Account $account, string $role): User
    {
        $user = User::factory()->create();
        AccountUser::create(['account_id' => $account->getKey(), 'user_id' => $user->getKey(), 'role' => $role]);
        $user->forceFill(['current_account_id' => $account->getKey()])->save();

        return $user->refresh();
    }
}
