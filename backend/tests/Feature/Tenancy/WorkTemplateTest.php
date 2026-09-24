<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TaskStatus;
use App\Models\ProcessTemplate;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkTemplateTest extends TestCase
{
    use RefreshDatabase;

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
}
