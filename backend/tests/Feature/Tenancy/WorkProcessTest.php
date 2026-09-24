<?php

namespace Tests\Feature\Tenancy;

use App\Models\Process;
use App\Models\ProcessTemplate;
use App\Models\Task;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkProcessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_tasks_order_due_nulls_last_then_order(): void
    {
        $template = ProcessTemplate::factory()->create();
        $process = Process::factory()->create([
            'account_id' => $template->account_id,
            'template_id' => $template->getKey(),
        ]);

        Task::factory()->create(['process_id' => $process->getKey(), 'account_id' => $process->account_id, 'due_on' => null, 'order' => 1, 'title' => 'Sem prazo']);
        Task::factory()->create(['process_id' => $process->getKey(), 'account_id' => $process->account_id, 'due_on' => '2026-03-05', 'order' => 2, 'title' => 'Dia 5']);
        Task::factory()->create(['process_id' => $process->getKey(), 'account_id' => $process->account_id, 'due_on' => '2026-03-03', 'order' => 3, 'title' => 'Dia 3']);

        $titles = Task::query()->ordered()->where('process_id', $process->getKey())->pluck('title')->all();

        $this->assertSame(['Dia 3', 'Dia 5', 'Sem prazo'], $titles);
    }
}
