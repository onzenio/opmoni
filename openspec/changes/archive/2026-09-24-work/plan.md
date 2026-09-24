# Work (Rotinas Fiscais por Cliente) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar o subsistema Work com modelos de rotinas mensais, geração de um processo por cliente por mês, tasks com cascata e 5 visões (calendário, clientes, processos, tarefas, modelos).

**Architecture:** `ProcessTemplate` é o blueprint com regra dinâmica (regimes + Tags + exceções); `ProcessGenerationService::generate(template, mês)` resolve elegíveis em query única e faz find-or-create transacional por (template, cliente, mês) clonando snapshot nas `Task`; controllers tenant finos com Form Requests + Resources + policies espelho `ProcessPolicy`; Nuxt consome só a API Laravel via `useWork.ts` com 5 páginas sob `work.vue`.

**Tech Stack:** PHP ^8.3 / Laravel ^13.17 / Sanctum ^4.0 / PHPUnit ^12.5.12 / Pint ^1.27; Nuxt ^4.5.2 / Vue ^3.5.43 / @nuxt/ui ^4.11.1 / @tanstack/table-core ^8.21.3 / TypeScript ^6.0.3 / pnpm@12.5.1.

**Spec:** `openspec/changes/work/` — `proposal.md`, `specs/tenant/{work-templates,work-processes,work-tasks}/spec.md`, `design.md` e `tasks.md`.

## Global Constraints

- Ler `backend/AGENTS.md`, `AGENTS.md` e qualquer `.ai/rules` aplicável antes de editar; regras locais prevalecem sobre este plano.
- Trabalhar em `feat/work` sobre o main `6807f1e`; preservar mudanças não commitadas alheias; não editar páginas admin para limpar falhas não relacionadas.
- Backend usa Laravel 13 e PHP ^8.3; criar classes/migrations/tests com `php artisan make:* --no-interaction`.
- Backend roda em `backend/`: teste focado com `php artisan test --compact --filter=Nome`, suíte com `composer test`, estilo com `vendor/bin/pint --dirty --format agent`.
- Frontend roda em `frontend/` e usa somente `pnpm`; verificar com `pnpm lint`, `pnpm typecheck` e `pnpm build`.
- Não adicionar dependências Composer ou pnpm.
- Toda query de modelo Work usa `BelongsToAccount` (escopo + preenchimento `account_id` + binding 404 cross-account).
- `admin` e `operador` escrevem; `user` somente lê; escrita em suporte registra via `SupportAudit::logWrite`.
- Toda rota de recurso permanece sob `auth:sanctum` + `tenant`.
- Textos da interface em português (A fazer, Em progresso, Concluída, Dispensada); badges semânticos, sem paleta crua.
- Ciclo da task: `todo|doing|done|dismissed`; inicial `todo`; `done` carimba `completed_at`; `dismissed` exige `dismissal_reason` e carimba; sair de `done|dismissed` limpa carimbo e motivo.
- Cascata: template com `cascade` ligado recusa transição além de `todo` enquanto houver etapa de `order` menor nem `done` nem `dismissed`; `dismissed` libera a seguinte; cascata desligada libera qualquer ordem.
- `due_day` 1–31 vira `due_on` real limitado ao último dia do mês (31 em fev/2026 → 28).
- Congelamento: regra/blueprint/cadastro mudados após geração só valem para próximos meses; mês gerado mantém snapshot.
- Unicidade gerada: `UNIQUE(account_id, template_id, client_id, reference_month)` full-column (NULLs distintos, sem partial index).
- Tasks não têm `client_id` próprio; cliente herdado via `process.client`; ordenação `due_on` nulls-last, depois `order`.
- Visão cliente usa `getGroupedRowModel` com `grouping ['client_id','process_id']`; calendário só lista tasks com `due_on`; kanban tem exatamente 4 colunas sem drag-and-drop na v1, com botões avançar/retornar/dispensar via API.
- Cada commit contém somente arquivos da task; antes do commit executar `git diff --cached --check` e revisar `git diff --cached`.

---

## File Structure

### Backend — criar

- `app/Enums/TaskStatus.php` — `Todo|Doing|Done|Dismissed` com values `todo|doing|done|dismissed` e `label()` pt-BR.
- `app/Enums/TaskPriority.php` — `Low|Medium|High|Urgent` com values `low|medium|high|urgent` e `label()` pt-BR.
- `database/migrations/2026_09_24_000001_create_process_templates_table.php` — `process_templates` + `regimes` JSON.
- `database/migrations/2026_09_24_000002_create_process_template_tasks_table.php` — `process_template_tasks` com `department`, `due_day`, `priority`, `order`, `default_assignee_member_id`.
- `database/migrations/2026_09_24_000003_create_template_tag_table.php` — pivot `template_tag`.
- `database/migrations/2026_09_24_000004_create_template_client_exceptions_table.php` — `template_client_exceptions` com `kind added|removed`.
- `database/migrations/2026_09_24_000005_create_tasks_table.php` — `tasks` sem `client_id`, com `dismissal_reason`.
- `database/migrations/2026_09_24_000006_evolve_processes_for_work_table.php` — altera `processes` (nullable `client_id`, `template_id`, `reference_month`, `status`, `due_on` + unique tripla).
- `app/Models/ProcessTemplate.php` — tenant, casts, relações `steps()`, `tags()`, `exceptions()`, `processes()`.
- `app/Models/ProcessTemplateTask.php` — etapa do blueprint (pertence ao template, carrega `account_id` do template).
- `app/Models/TemplateClientException.php` — exceção `added|removed` por cliente.
- `app/Models/Task.php` — tenant, casts `TaskStatus|TaskPriority`, scopes `withStatus/withAssignee/withDepartment/withPriority/withDueRange`, ordenação `due_on nulls-last + order`.
- `app/Services/ProcessGenerationService.php` — `generate(ProcessTemplate, Carbon $month): Collection` + `eligibleClients()` + `preview()`.
- `app/Console/Commands/GenerateRecurringProcesses.php` — assinatura `work:generate-recurrences`.
- `app/Http/Requests/Tenant/StoreProcessTemplateRequest.php`, `UpdateProcessTemplateRequest.php` — authz Gate + regras template + regimes + tags + exceções + blueprint aninhado.
- `app/Http/Requests/Tenant/UpdateProcessRequest.php` — nome/status/vencimento manual.
- `app/Http/Requests/Tenant/UpdateTaskRequest.php` — `status`, `dismissal_reason`, `assignee_member_id`.
- `app/Http/Resources/ProcessTemplateResource.php`, `ProcessTemplateTaskResource.php`, `ProcessResource.php`, `TaskResource.php`.
- `app/Http/Controllers/Tenant/ProcessTemplateController.php` — CRUD + `preview` + `generate`.
- `app/Http/Controllers/Tenant/TaskController.php` — index/show/update + `calendar` + `grouped`.
- `app/Policies/ProcessTemplatePolicy.php`, `app/Policies/TaskPolicy.php` — espelho `ProcessPolicy`.
- `database/factories/ProcessTemplateFactory.php`, `ProcessTemplateTaskFactory.php`, `TaskFactory.php`.
- `tests/Feature/Tenancy/WorkTemplateTest.php`, `WorkProcessTest.php`, `WorkTaskTest.php`, `WorkGenerationTest.php`.

### Backend — modificar

- `app/Models/Process.php` — fillable + casts + relações `template()`, `client()`, `tasks()`, scope mês/status.
- `app/Models/Account.php` — `processTemplates()`, `tasks()` hasMany.
- `app/Models/Client.php` — `processes()` hasMany.
- `app/Http/Controllers/Tenant/ProcessController.php` — filtros template/mês/cliente/status, ordem mês desc, detalhe com tasks + progresso.
- `app/Providers/AppServiceProvider.php` — registra `Gate::policy(ProcessTemplate::class)`, `Gate::policy(Task::class)`.
- `routes/api.php` — `apiResource process-templates`, `tasks`, rotas `preview/generate`, `work/calendar`, `work/grouped`.
- `routes/console.php` — agenda `work:generate-recurrences` diário.
- `database/factories/ClientFactory.php` — garantir estados com `tax_regime` explícito nos testes (reuso, sem mudar factory se já cobre).

### Frontend — criar

- `app/types/work.ts` — `WorkTemplate`, `WorkTemplateStep`, `WorkProcess`, `WorkTask`, `WorkEligibilityPreview`, `WorkGroupedClient`, params e enums `todo|doing|done|dismissed`.
- `app/composables/useWork.ts` — `listTemplates/showTemplate/createTemplate/updateTemplate/destroyTemplate/previewTemplate/generateTemplate`, `listProcesses/showProcess`, `listTasks/updateTask/calendar/grouped`, com `queryOf` igual ao `useClients.ts`.
- `app/utils/workNav.ts` — 5 visões com label/ícone/to + `workListPath`, padrão `monitoringNav.ts`.
- `app/pages/work.vue` — shell `UDashboardPanel/Navbar/Toolbar` com tabs, padrão `customers.vue`/`monitoring.vue`.
- `app/pages/work/calendario.vue`, `clientes.vue`, `processos.vue`, `tarefas.vue`, `modelos.vue`.
- `app/pages/work/processos/[id].vue` — detalhe com Stepper/Timeline + tasks expansíveis.
- `app/pages/work/modelos/[id].vue` — editor em abas (Associação, Clientes e Exceções, Prazo, Tarefas, Recorrência) + preview + gerar mês.

### Frontend — modificar

- `app/layouts/default.vue` — seção Work no sidebar (trigger + 5 filhos), sem remover Monitoramento/Clientes/Settings.
- `app/composables/useAuth.ts` — reuso de `canManageClients` (sem mudar, só consumir).

---

### Task 1: Baseline verde em `feat/work`

**Files:**
- Modify: nenhum produto; só verificação.
- Test: suite existente.

**Interfaces:**
- Consumes: main `6807f1e`.
- Produces: baseline registrado (versões + contagem de testes).

- [ ] **Step 1: Confirmar branch e status limpo**

```bash
git status --short --branch
git log --oneline -3
```

Expected: `## feat/work`, nada pendente além deste plano; base `6807f1e`.

- [ ] **Step 2: Registrar versões instaladas**

```bash
cd backend && php -v | head -1 && php artisan --version
cd ../frontend && pnpm --version && pnpm exec nuxt --version
```

Expected: PHP 8.3/8.4, Laravel 13.x, Nuxt 4.5.x, pnpm 12.5.1. Anotar no commit da Task 2 se divergir.

- [ ] **Step 3: Rodar suite backend e guardar baseline**

```bash
cd backend && composer test 2>&1 | tail -5
```

Expected: PASS com a contagem atual (carteira ~146). Se falhar, parar e corrigir ambiente antes de qualquer código Work.

- [ ] **Step 4: Rodar lint e typecheck frontend**

```bash
cd frontend && pnpm lint 2>&1 | tail -3 && pnpm typecheck 2>&1 | tail -3
```

Expected: ambos verdes. Registrar qualquer falha alheia arquivo:linha para não atribuir ao Work depois.

---

### Task 2: Enums + migrations do domínio Work

**Files:**
- Create: `backend/app/Enums/TaskStatus.php`, `backend/app/Enums/TaskPriority.php`
- Create: `backend/database/migrations/2026_09_24_00000{1,2,3,4,5,6}_*.php`
- Test: `backend/tests/Feature/Tenancy/WorkTemplateTest.php` (só o teste de migração desta task)

**Interfaces:**
- Consumes: `TaxRegime` enum, tabela `processes`, `clients`, `tags`, `users`.
- Produces: `TaskStatus::values()` → `['todo','doing','done','dismissed']`; `TaskPriority::values()`; tabelas migradas.

- [ ] **Step 1: Escrever o teste failing de schema**

```php
// backend/tests/Feature/Tenancy/WorkTemplateTest.php
namespace Tests\Feature\Tenancy;

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
        $this->assertSame(['todo', 'doing', 'done', 'dismissed'], \App\Enums\TaskStatus::values());
    }
}
```

Criar com `php artisan make:test --phpunit WorkTemplateTest` e substituir o conteúdo pelo acima.

- [ ] **Step 2: Rodar para confirmar falha**

```bash
cd backend && php artisan test --compact --filter=test_work_tables_exist_with_expected_columns
```

Expected: FAIL (tabelas `process_templates`/`tasks` inexistentes).

- [ ] **Step 3: Criar enums mínimos**

```php
// backend/app/Enums/TaskStatus.php
namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case Doing = 'doing';
    case Done = 'done';
    case Dismissed = 'dismissed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s): string => $s->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'A fazer',
            self::Doing => 'Em progresso',
            self::Done => 'Concluída',
            self::Dismissed => 'Dispensada',
        };
    }
}
```

```php
// backend/app/Enums/TaskPriority.php
namespace App\Enums;

enum TaskPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $p): string => $p->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Baixa',
            self::Medium => 'Média',
            self::High => 'Alta',
            self::Urgent => 'Urgente',
        };
    }
}
```

Usar `php artisan make:enum --no-interaction TaskStatus` se disponível; senão criar os arquivos.

- [ ] **Step 4: Criar migrations via artisan e preencher `up()`**

```bash
cd backend
php artisan make:migration --no-interaction create_process_templates_table
php artisan make:migration --no-interaction create_process_template_tasks_table
php artisan make:migration --no-interaction create_template_tag_table
php artisan make:migration --no-interaction create_template_client_exceptions_table
php artisan make:migration --no-interaction create_tasks_table
php artisan make:migration --no-interaction evolve_processes_for_work_table
```

Renomear os arquivos gerados para `2026_09_24_000001_*` … `2026_09_24_000006_*` e preencher:

```php
// 000001 process_templates
Schema::create('process_templates', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('account_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->boolean('cascade')->default(false);
    $table->unsignedTinyInteger('generate_day')->default(1);
    $table->unsignedTinyInteger('due_day')->default(20);
    $table->boolean('is_active')->default(true);
    $table->json('regimes')->nullable();
    $table->timestamps();
});
```

```php
// 000002 process_template_tasks
Schema::create('process_template_tasks', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('account_id')->constrained()->cascadeOnDelete();
    $table->foreignId('template_id')->constrained('process_templates')->cascadeOnDelete();
    $table->string('title');
    $table->string('department')->default('Fiscal');
    $table->text('description')->nullable();
    $table->unsignedTinyInteger('due_day')->default(20);
    $table->string('priority')->default('medium');
    $table->unsignedInteger('order')->default(1);
    $table->foreignId('default_assignee_member_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->index(['template_id', 'order']);
});
```

```php
// 000003 template_tag
Schema::create('template_tag', function (Blueprint $table): void {
    $table->foreignId('account_id')->constrained()->cascadeOnDelete();
    $table->foreignId('template_id')->constrained('process_templates')->cascadeOnDelete();
    $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
    $table->unique(['template_id', 'tag_id']);
});
```

```php
// 000004 template_client_exceptions
Schema::create('template_client_exceptions', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('account_id')->constrained()->cascadeOnDelete();
    $table->foreignId('template_id')->constrained('process_templates')->cascadeOnDelete();
    $table->foreignId('client_id')->constrained()->cascadeOnDelete();
    $table->string('kind'); // added|removed (check na Request, sem enum nativo p/ SQLite)
    $table->timestamps();
    $table->unique(['template_id', 'client_id']);
});
```

```php
// 000005 tasks
Schema::create('tasks', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('account_id')->constrained()->cascadeOnDelete();
    $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
    $table->string('title');
    $table->string('department')->default('Fiscal');
    $table->text('description')->nullable();
    $table->string('status')->default('todo');
    $table->date('due_on')->nullable();
    $table->string('priority')->default('medium');
    $table->foreignId('assignee_member_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('completed_at')->nullable();
    $table->text('dismissal_reason')->nullable();
    $table->unsignedInteger('order')->default(1);
    $table->timestamps();
    $table->index(['process_id', 'order']);
    $table->index(['account_id', 'status']);
});
```

```php
// 000006 evolve processes
Schema::table('processes', function (Blueprint $table): void {
    $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('template_id')->nullable()->constrained('process_templates')->nullOnDelete();
    $table->date('reference_month')->nullable();
    $table->string('status')->default('open');
    $table->date('due_on')->nullable();
    $table->unique(['account_id', 'template_id', 'client_id', 'reference_month'], 'processes_template_client_month_unique');
});
```

- [ ] **Step 5: Rodar teste até passar**

```bash
cd backend && php artisan test --compact --filter=test_work_tables_exist_with_expected_columns
```

Expected: PASS.

- [ ] **Step 6: Formatar e commitar isolado**

```bash
cd backend && vendor/bin/pint --dirty --format agent
git add app/Enums/TaskStatus.php app/Enums/TaskPriority.php database/migrations/2026_09_24_00000*_*.php tests/Feature/Tenancy/WorkTemplateTest.php
git diff --cached --check
git commit -m "feat(work): add task enums and work migrations"
```

---

### Task 3: Models, factories e relações

**Files:**
- Create: `backend/app/Models/ProcessTemplate.php`, `ProcessTemplateTask.php`, `Task.php`
- Create: `backend/database/factories/ProcessTemplateFactory.php`, `ProcessTemplateTaskFactory.php`, `ProcessFactory.php`, `TaskFactory.php`
- Modify: `backend/app/Models/Process.php`, `backend/app/Models/Account.php`, `backend/app/Models/Client.php`
- Test: `backend/tests/Feature/Tenancy/WorkProcessTest.php`

**Interfaces:**
- Consumes: `TaskStatus`, `TaskPriority`, `BelongsToAccount`.
- Produces: `ProcessTemplate::steps|tags|exceptions|processes`, `Process::template|client|tasks`, `Task::process`, scopes `withStatus/withDueRange`.

- [ ] **Step 1: Escrever teste failing de ownership e ordenação**

```php
// backend/tests/Feature/Tenancy/WorkProcessTest.php
namespace Tests\Feature\Tenancy;

use App\Models\Account;
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
        $process = \App\Models\Process::factory()->create([
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
```

- [ ] **Step 2: Rodar para confirmar falha**

```bash
cd backend && php artisan test --compact --filter=test_tasks_order_due_nulls_last_then_order
```

Expected: FAIL (`ProcessTemplate::factory` inexistente).

- [ ] **Step 3: Implementar models mínimos**

```php
// backend/app/Models/ProcessTemplate.php
namespace App\Models;

use App\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['account_id', 'name', 'description', 'cascade', 'generate_day', 'due_day', 'is_active', 'regimes'])]
class ProcessTemplate extends Model
{
    /** @use HasFactory<ProcessTemplate> */
    use BelongsToAccount, HasFactory;

    protected function casts(): array
    {
        return [
            'cascade' => 'boolean',
            'is_active' => 'boolean',
            'regimes' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProcessTemplateTask::class, 'template_id')->orderBy('order');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'template_tag')->withPivot('account_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(TemplateClientException::class, 'template_id');
    }

    public function processes(): HasMany
    {
        return $this->hasMany(Process::class, 'template_id');
    }
}
```

`ProcessTemplateTask` e `Task` seguem o mesmo molde (`BelongsToAccount + HasFactory`); `Task` adiciona:

```php
protected function casts(): array
{
    return [
        'status' => \App\Enums\TaskStatus::class,
        'priority' => \App\Enums\TaskPriority::class,
        'due_on' => 'date',
        'completed_at' => 'datetime',
    ];
}

public function scopeOrdered(Builder $query): Builder
{
    return $query->orderByRaw('due_on IS NULL')->orderBy('due_on')->orderBy('order');
}

public function scopeWithStatus(Builder $query, string|array|null $status): Builder
{
    $values = is_array($status) ? $status : ($status ? [$status] : []);
    return $values === [] ? $query : $query->whereIn('status', $values);
}
```

Exceção como model simples `TemplateClientException` (`Fillable [account_id, template_id, client_id, kind]`, `BelongsToAccount`).

- [ ] **Step 4: Estender `Process`, `Account`, `Client` e criar factories**

```php
// em Process.php: adicionar ao Fillable + casts + relações
#[Fillable(['account_id', 'name', 'client_id', 'template_id', 'reference_month', 'status', 'due_on'])]
// casts: reference_month => date, due_on => date
public function template(): BelongsTo { return $this->belongsTo(ProcessTemplate::class, 'template_id'); }
public function client(): BelongsTo { return $this->belongsTo(Client::class); }
public function tasks(): HasMany { return $this->hasMany(Task::class); }
```

```php
// Account.php: adicionar
public function processTemplates(): HasMany { return $this->hasMany(ProcessTemplate::class); }
public function tasks(): HasMany { return $this->hasMany(Task::class); }
```

```php
// Client.php: adicionar
public function processes(): HasMany { return $this->hasMany(Process::class); }
```

Factories via `php artisan make:factory --no-interaction` com `definition()` espelhando `ClientFactory`/`TagFactory` (account via `Account::factory()`, títulos com `fake()->words(3, true)`, `due_day` 1–28 para não quebrar em fevereiro nos testes simples). Criar também `ProcessFactory` (`account_id` via `Account::factory()`, `name` via `fake()->words(2, true)`), pois o `Process` atual não possui factory e os testes das Tasks 3–4 a exigem.

- [ ] **Step 5: Rodar teste até passar**

```bash
cd backend && php artisan test --compact --filter=test_tasks_order_due_nulls_last_then_order
```

Expected: PASS.

- [ ] **Step 6: Formatar e commitar**

```bash
cd backend && vendor/bin/pint --dirty --format agent
git add app/Models backend/database/factories tests/Feature/Tenancy/WorkProcessTest.php
git diff --cached --check
git commit -m "feat(work): add work models, relations and factories"
```

---

### Task 4: `ProcessGenerationService` + comando agendado

**Files:**
- Create: `backend/app/Services/ProcessGenerationService.php`
- Create: `backend/app/Console/Commands/GenerateRecurringProcesses.php`
- Modify: `backend/routes/console.php`
- Test: `backend/tests/Feature/Tenancy/WorkGenerationTest.php`

**Interfaces:**
- Consumes: `ProcessTemplate`, `Client::withTaxRegime/withTag`, `template_client_exceptions`.
- Produces: `ProcessGenerationService::generate(ProcessTemplate $t, Carbon $month): Collection` e `::preview(ProcessTemplate $t): Collection<array{client, reason}>`; comando `work:generate-recurrences`.

- [ ] **Step 1: Escrever testes failing de geração**

```php
// backend/tests/Feature/Tenancy/WorkGenerationTest.php
namespace Tests\Feature\Tenancy;

use App\Enums\TaxRegime;
use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Client;
use App\Models\ProcessTemplate;
use App\Models\Tag;
use App\Models\User;
use App\Services\ProcessGenerationService;
use Carbon\Carbon;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_generate_creates_one_process_per_eligible_and_is_idempotent(): void
    {
        $account = Account::factory()->create();
        $member = $this->memberOf($account, 'admin');
        $this->actingAs($member, 'sanctum');

        $tag = Tag::factory()->create(['account_id' => $account->getKey()]);
        $template = ProcessTemplate::factory()->create([
            'account_id' => $account->getKey(), 'name' => 'PGDAS',
            'regimes' => [TaxRegime::SimpleNational->value], 'cascade' => true,
        ]);
        $template->tags()->attach($tag->getKey(), ['account_id' => $account->getKey()]);
        $template->steps()->create(['account_id' => $account->getKey(), 'title' => 'Apurar', 'department' => 'Fiscal', 'due_day' => 3, 'priority' => 'medium', 'order' => 1]);
        $template->steps()->create(['account_id' => $account->getKey(), 'title' => 'Transmitir', 'department' => 'Fiscal', 'due_day' => 31, 'priority' => 'high', 'order' => 2]);

        $ok = Client::factory()->company()->create(['account_id' => $account->getKey(), 'tax_regime' => TaxRegime::SimpleNational, 'status' => 'active']);
        $ok->tags()->attach($tag->getKey(), ['account_id' => $account->getKey()]);
        $out = Client::factory()->company()->create(['account_id' => $account->getKey(), 'tax_regime' => TaxRegime::PresumedProfit, 'status' => 'active']);

        $month = Carbon::create(2026, 2, 1)->startOfDay();
        $first = app(ProcessGenerationService::class)->generate($template, $month);
        $second = app(ProcessGenerationService::class)->generate($template, $month);

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertSame($first->first()->getKey(), $second->first()->getKey());
        $this->assertDatabaseCount('processes', 1);
        // due_day 31 em fevereiro limita ao dia 28
        $this->assertDatabaseHas('tasks', ['process_id' => $first->first()->getKey(), 'due_on' => '2026-02-28', 'status' => 'todo']);
        $this->assertDatabaseMissing('processes', ['client_id' => $out->getKey()]);
    }

    private function memberOf(Account $account, string $role): User
    {
        $user = User::factory()->create();
        AccountUser::create(['account_id' => $account->getKey(), 'user_id' => $user->getKey(), 'role' => $role]);
        $user->forceFill(['current_account_id' => $account->getKey()])->save();
        return $user->refresh();
    }
}
```

- [ ] **Step 2: Rodar para confirmar falha**

```bash
cd backend && php artisan test --compact --filter=test_generate_creates_one_process_per_eligible_and_is_idempotent
```

Expected: FAIL (`ProcessGenerationService` inexistente).

- [ ] **Step 3: Implementar serviço mínimo**

```php
// backend/app/Services/ProcessGenerationService.php
namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Process;
use App\Models\ProcessTemplate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProcessGenerationService
{
    /**
     * @return Collection<int, Process>
     */
    public function generate(ProcessTemplate $template, Carbon $month): Collection
    {
        $reference = $month->copy()->startOfMonth()->startOfDay();
        $clients = $this->eligibleClients($template);

        return $clients->map(fn (Client $client): Process => DB::transaction(function () use ($template, $client, $reference): Process {
            $process = Process::query()->where([
                'template_id' => $template->getKey(),
                'client_id' => $client->getKey(),
                'reference_month' => $reference->toDateString(),
            ])->lockForUpdate()->first();

            if ($process instanceof Process) {
                return $process;
            }

            $process = Process::query()->create([
                'name' => $template->name.' '.$reference->format('m/Y'),
                'template_id' => $template->getKey(),
                'client_id' => $client->getKey(),
                'reference_month' => $reference->toDateString(),
                'status' => 'open',
                'due_on' => $this->resolveDueDate($reference, $template->due_day),
            ]);

            foreach ($template->steps()->orderBy('order')->get() as $step) {
                $process->tasks()->create([
                    'title' => $step->title,
                    'department' => $step->department,
                    'description' => $step->description,
                    'status' => TaskStatus::Todo->value,
                    'due_on' => $this->resolveDueDate($reference, (int) $step->due_day),
                    'priority' => $step->priority,
                    'assignee_member_id' => $step->default_assignee_member_id,
                    'order' => $step->order,
                ]);
            }

            return $process;
        }));
    }

    /**
     * @return Collection<int, array{client: Client, reason: string}>
     */
    public function preview(ProcessTemplate $template): Collection
    {
        return $this->eligibleClients($template)->map(fn (Client $c): array => [
            'client' => $c,
            'reason' => $this->matchReason($template, $c),
        ]);
    }

    /** @return Collection<int, Client> */
    public function eligibleClients(ProcessTemplate $template): Collection
    {
        $regimes = $template->regimes ?? [];
        $tagIds = $template->tags()->pluck('tags.id')->all();
        $removed = $template->exceptions()->where('kind', 'removed')->pluck('client_id')->all();
        $added = $template->exceptions()->where('kind', 'added')->pluck('client_id')->all();

        $base = Client::query()
            ->where('status', 'active')
            ->when($regimes !== [], fn ($q) => $q->whereIn('tax_regime', $regimes))
            ->when($tagIds !== [], fn ($q) => $q->whereHas('tags', fn ($t) => $t->whereKey($tagIds)))
            ->when($removed !== [], fn ($q) => $q->whereNotIn('clients.id', $removed))
            ->orderBy('name')->get();

        if ($added === []) {
            return $base;
        }

        $extra = Client::query()->whereKey($added)->where('status', 'active')->orderBy('name')->get();

        return $base->merge($extra)->unique('id')->values();
    }

    private function matchReason(ProcessTemplate $template, Client $client): string
    {
        $added = $template->exceptions()->where('kind', 'added')->pluck('client_id')->all();
        return in_array($client->getKey(), $added, true) ? 'added' : 'rule';
    }

    private function resolveDueDate(Carbon $reference, int $day): string
    {
        $capped = min(max($day, 1), $reference->daysInMonth);
        return $reference->copy()->day($capped)->toDateString();
    }
}
```

Criar comando com `php artisan make:command --no-interaction GenerateRecurringProcesses`, assinatura `work:generate-recurrences`, `handle()` iterando templates ativos com `generate_day` vencido no mês corrente e chamando o serviço; registrar em `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;
Schedule::command('work:generate-recurrences')->daily();
```

- [ ] **Step 4: Rodar teste até passar**

```bash
cd backend && php artisan test --compact --filter=test_generate_creates_one_process_per_eligible_and_is_idempotent
```

Expected: PASS.

- [ ] **Step 5: Formatar e commitar**

```bash
cd backend && vendor/bin/pint --dirty --format agent
git add app/Services/ProcessGenerationService.php app/Console/Commands/GenerateRecurringProcesses.php routes/console.php tests/Feature/Tenancy/WorkGenerationTest.php
git diff --cached --check
git commit -m "feat(work): add per-client generation service and scheduler"
```

---

### Task 5: API de templates (CRUD + regra + preview + generate)

**Files:**
- Create: `backend/app/Http/Requests/Tenant/StoreProcessTemplateRequest.php`, `UpdateProcessTemplateRequest.php`
- Create: `backend/app/Http/Resources/ProcessTemplateResource.php`, `ProcessTemplateTaskResource.php`
- Create: `backend/app/Http/Controllers/Tenant/ProcessTemplateController.php`
- Create: `backend/app/Policies/ProcessTemplatePolicy.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`, `backend/routes/api.php`
- Test: estender `backend/tests/Feature/Tenancy/WorkTemplateTest.php`

**Interfaces:**
- Consumes: `ProcessGenerationService::generate/preview`.
- Produces: `GET|POST /process-templates`, `GET|PATCH|DELETE /process-templates/{id}`, `GET .../preview`, `POST .../generate`.

- [ ] **Step 1: Escrever testes failing de CRUD + 403 + preview**

```php
public function test_operador_creates_template_with_blueprint_and_user_is_forbidden(): void
{
    $account = Account::factory()->create();
    $operador = $this->memberOf($account, 'operador');
    $user = $this->memberOf($account, 'user');

    $payload = [
        'name' => 'PGDAS', 'cascade' => true, 'generate_day' => 1, 'due_day' => 20,
        'regimes' => ['simple_national'], 'tag_ids' => [], 'exceptions' => [],
        'steps' => [
            ['title' => 'Apurar', 'department' => 'Fiscal', 'due_day' => 3, 'priority' => 'medium', 'order' => 1],
            ['title' => 'Transmitir', 'department' => 'Fiscal', 'due_day' => 5, 'priority' => 'high', 'order' => 2],
        ],
    ];

    $this->actingAs($operador, 'sanctum')->postJson('/api/process-templates', $payload)->assertCreated();
    $this->actingAs($user, 'sanctum')->postJson('/api/process-templates', $payload)->assertForbidden();
}

public function test_preview_returns_eligible_without_creating(): void
{
    // template Simple + cliente Simple elegível; preview 200 com reason rule e processes continua 0
}
```

Helper `memberOf` igual ao `IsolationTest` (copiar o método, sem referenciar outro arquivo).

- [ ] **Step 2: Rodar para confirmar falha**

```bash
cd backend && php artisan test --compact --filter=test_operador_creates_template_with_blueprint_and_user_is_forbidden
```

Expected: FAIL (rota 404).

- [ ] **Step 3: Implementar Requests + Resources + Policy espelho `ProcessPolicy`**

`StoreProcessTemplateRequest::authorize()` → `Gate::allows('create', ProcessTemplate::class)`; `rules()`:

```php
return [
    'name' => ['required', 'string', 'max:255'],
    'description' => ['nullable', 'string'],
    'cascade' => ['sometimes', 'boolean'],
    'generate_day' => ['sometimes', 'integer', 'min:1', 'max:31'],
    'due_day' => ['sometimes', 'integer', 'min:1', 'max:31'],
    'is_active' => ['sometimes', 'boolean'],
    'regimes' => ['sometimes', 'array'],
    'regimes.*' => [Rule::enum(TaxRegime::class)],
    'tag_ids' => ['sometimes', 'array'],
    'tag_ids.*' => ['integer', Rule::exists('tags', 'id')->where(fn ($q) => $q->where('account_id', resolve(CurrentTenant::class)->accountId))],
    'exceptions' => ['sometimes', 'array'],
    'exceptions.*.client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where(fn ($q) => $q->where('account_id', resolve(CurrentTenant::class)->accountId))],
    'exceptions.*.kind' => ['required', Rule::in(['added', 'removed'])],
    'steps' => ['sometimes', 'array'],
    'steps.*.title' => ['required', 'string', 'max:255'],
    'steps.*.department' => ['required', 'string', 'max:120'],
    'steps.*.due_day' => ['required', 'integer', 'min:1', 'max:31'],
    'steps.*.priority' => ['required', Rule::in(TaskPriority::values())],
    'steps.*.order' => ['required', 'integer', 'min:1'],
    'steps.*.description' => ['nullable', 'string'],
    'steps.*.default_assignee_member_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
];
```

Validar assignee membro do account com `Validator::after` checando `account_user`. `UpdateProcessTemplateRequest` igual com `sometimes`.

Policy copia `ProcessPolicy` trocando o model (viewAny qualquer membro; escrita admin|operador + mesmo account).

- [ ] **Step 4: Implementar controller fino**

CRUD com `Gate::authorize`, sync de `steps` (delete ausentes + upsert por id), sync de `tags` com `account_id` no pivot, sync de `exceptions`, `SupportAudit::logWrite` em store/update/destroy/generate, `preview` delegando ao serviço sem criar, `generate` validando `reference_month` (`date_format:Y-m`) e chamando o serviço. Rotas:

```php
Route::apiResource('process-templates', ProcessTemplateController::class);
Route::get('process-templates/{process_template}/preview', [ProcessTemplateController::class, 'preview']);
Route::post('process-templates/{process_template}/generate', [ProcessTemplateController::class, 'generate']);
```

Registrar policy no `AppServiceProvider`.

- [ ] **Step 5: Rodar testes até passar**

```bash
cd backend && php artisan test --compact --filter=WorkTemplateTest
```

Expected: PASS.

- [ ] **Step 6: Formatar e commitar**

```bash
cd backend && vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Tenant/*ProcessTemplate* app/Http/Resources/ProcessTemplate* app/Http/Controllers/Tenant/ProcessTemplateController.php app/Policies/ProcessTemplatePolicy.php app/Providers/AppServiceProvider.php routes/api.php tests/Feature/Tenancy/WorkTemplateTest.php
git diff --cached --check
git commit -m "feat(work): add process template api with rule and preview"
```

---

### Task 6: Evoluir API de processos (filtros + detalhe + progresso)

**Files:**
- Modify: `backend/app/Http/Controllers/Tenant/ProcessController.php`
- Create: `backend/app/Http/Requests/Tenant/UpdateProcessRequest.php`, `backend/app/Http/Resources/ProcessResource.php`
- Test: estender `backend/tests/Feature/Tenancy/WorkProcessTest.php`

**Interfaces:**
- Consumes: `Process::template|client|tasks`, `TaskStatus`.
- Produces: `GET /processes?template_id&reference_month&client_id&status`, `GET /processes/{id}` com `progress {total, done, dismissed, open, ratio}`.

- [ ] **Step 1: Escrever teste failing de filtro + progresso**

```php
public function test_processes_filter_by_month_and_expose_progress(): void
{
    // gera 1 processo com 4 tasks (1 done, 1 dismissed, 2 todo) via serviço;
    // GET /api/processes?template_id=X&reference_month=2026-03 retorna 1;
    // GET /api/processes/{id} retorna progress total 4, done 1, dismissed 1, open 2, ratio 0.25
}
```

- [ ] **Step 2: Rodar para confirmar falha**

```bash
cd backend && php artisan test --compact --filter=test_processes_filter_by_month_and_expose_progress
```

Expected: FAIL (resposta sem `progress`).

- [ ] **Step 3: Implementar `ProcessResource` + controller evoluído**

```php
// ProcessResource::toArray
return [
    'id' => $this->getKey(),
    'name' => $this->name,
    'status' => $this->status,
    'due_on' => $this->due_on?->toDateString(),
    'reference_month' => $this->reference_month?->format('Y-m'),
    'template' => $this->whenLoaded('template', fn () => ['id' => $this->template->getKey(), 'name' => $this->template->name]),
    'client' => $this->whenLoaded('client', fn () => ['id' => $this->client->getKey(), 'name' => $this->client->name]),
    'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
    'progress' => $this->when(isset($this->progress), $this->progress),
];
```

`index()`: `Gate::authorize('viewAny')`, query com `when()` para os 4 filtros, `orderByDesc('reference_month')->orderBy('name')`, `paginate` padrão 25. `show()`: carrega `template,client,tasks` ordenadas e calcula `progress` (total, done, dismissed, open, ratio done/total com `dismissed` contada como concluída só no total de concluídas, sem inflar `ratio`). `update()` só nome/status/due_on via `UpdateProcessRequest` + audit. `store()` mantém contrato legado (`name` só) e aceita opcionalmente `client_id/template_id/reference_month`.

- [ ] **Step 4: Rodar teste até passar**

```bash
cd backend && php artisan test --compact --filter=WorkProcessTest
```

Expected: PASS.

- [ ] **Step 5: Formatar e commitar**

```bash
cd backend && vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Tenant/ProcessController.php app/Http/Requests/Tenant/UpdateProcessRequest.php app/Http/Resources/ProcessResource.php tests/Feature/Tenancy/WorkProcessTest.php
git diff --cached --check
git commit -m "feat(work): evolve process api with filters and progress"
```

---

### Task 7: API de tasks (ciclo + cascata + calendário + agrupado)

**Files:**
- Create: `backend/app/Http/Controllers/Tenant/TaskController.php`, `backend/app/Http/Requests/Tenant/UpdateTaskRequest.php`, `backend/app/Http/Resources/TaskResource.php`, `backend/app/Policies/TaskPolicy.php`
- Modify: `backend/routes/api.php`, `backend/app/Providers/AppServiceProvider.php`
- Test: `backend/tests/Feature/Tenancy/WorkTaskTest.php`

**Interfaces:**
- Consumes: `Task`, `Process::tasks`, `ProcessTemplate::cascade`.
- Produces: `GET /tasks` (7 filtros), `PATCH /tasks/{id}`, `GET /work/calendar?from&to`, `GET /work/grouped?reference_month`.

- [ ] **Step 1: Escrever testes failing de ciclo, cascata, calendário e agrupado**

```php
public function test_task_lifecycle_requires_reason_and_cascade_blocks_sequence(): void
{
    // template cascade com 2 etapas; task2 PATCH doing com task1 todo → 422; dispensa task1 com motivo → 200; task2 avança → 200
}

public function test_dismiss_without_reason_is_rejected_and_user_is_forbidden(): void
{
    // PATCH dismissed sem reason → 422; user PATCH → 403
}

public function test_calendar_omits_undated_and_grouped_nests_client_process_task(): void
{
    // 1 task sem due_on + 1 com due_on; GET /api/work/calendar?from=2026-03-01&to=2026-03-31 só traz a datada;
    // GET /api/work/grouped?reference_month=2026-03 traz clients[0].processes[0].tasks
}
```

- [ ] **Step 2: Rodar para confirmar falha**

```bash
cd backend && php artisan test --compact --filter=WorkTaskTest
```

Expected: FAIL (rotas 404).

- [ ] **Step 3: Implementar transição com guarda**

```php
// TaskController::update
Gate::authorize('update', $task);
$data = $request->validated();
$from = $task->status instanceof TaskStatus ? $task->status->value : (string) $task->status;
$to = $data['status'] ?? $from;

if ($to === TaskStatus::Dismissed->value && empty($data['dismissal_reason'])) {
    abort(response()->json(['message' => 'Motivo obrigatório ao dispensar.', 'errors' => ['dismissal_reason' => ['Motivo obrigatório.']]], 422));
}

if ($to !== TaskStatus::Todo->value && $task->process->template?->cascade) {
    $blocked = $task->process->tasks()->where('order', '<', $task->order)
        ->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Dismissed->value])->exists();
    if ($blocked) {
        abort(response()->json(['message' => 'Etapa anterior pendente bloqueia o avanço (cascata).'], 422));
    }
}

$task->status = $to;
if (in_array($to, [TaskStatus::Done->value, TaskStatus::Dismissed->value], true)) {
    $task->completed_at ??= now();
    $task->dismissal_reason = $to === TaskStatus::Dismissed->value ? $data['dismissal_reason'] : null;
} else {
    $task->completed_at = null;
    $task->dismissal_reason = null;
}
if (array_key_exists('assignee_member_id', $data)) {
    $task->assignee_member_id = $data['assignee_member_id'];
}
$task->save();
SupportAudit::logWrite($request, 'tasks', 'update', $task->getKey(), ['status' => $to]);
return new TaskResource($task->load('process.client'));
```

`UpdateTaskRequest` valida `status in todo,doing,done,dismissed`, `dismissal_reason required_if:status,dismissed`, `assignee_member_id` exists users + membro do account via `after`.

`index()` aplica 7 filtros (`process_id`, `client_id` via `whereHas process`, `status`, `assignee_member_id`, `department`, `priority`, `due_from/due_to`) + `ordered()`. `calendar()` exige `from/to` e retorna só `whereNotNull('due_on')` no intervalo. `grouped()` monta `clients[] → processes[] → tasks[]` com totais e `ratio` por processo.

- [ ] **Step 4: Registrar rotas + policy e rodar testes**

```php
Route::apiResource('tasks', TaskController::class)->only(['index', 'show', 'update']);
Route::get('work/calendar', [TaskController::class, 'calendar']);
Route::get('work/grouped', [TaskController::class, 'grouped']);
```

```bash
cd backend && php artisan test --compact --filter=WorkTaskTest
```

Expected: PASS.

- [ ] **Step 5: Formatar e commitar**

```bash
cd backend && vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Tenant/TaskController.php app/Http/Requests/Tenant/UpdateTaskRequest.php app/Http/Resources/TaskResource.php app/Policies/TaskPolicy.php app/Providers/AppServiceProvider.php routes/api.php tests/Feature/Tenancy/WorkTaskTest.php
git diff --cached --check
git commit -m "feat(work): add task api with cascade guard calendar and grouped"
```

---

### Task 8: Frontend base Work (shell + nav + composable + tipos)

**Files:**
- Create: `frontend/app/types/work.ts`, `frontend/app/composables/useWork.ts`, `frontend/app/utils/workNav.ts`, `frontend/app/pages/work.vue`
- Modify: `frontend/app/layouts/default.vue`
- Test: `frontend` typecheck + navegação manual.

**Interfaces:**
- Consumes: endpoints da Task 5–7.
- Produces: `useWork()` com `listTemplates|previewTemplate|generateTemplate|listProcesses|showProcess|listTasks|updateTask|calendar|grouped`; `workNav` com 5 rotas.

- [ ] **Step 1: Criar tipos espelhando Resources**

```ts
// frontend/app/types/work.ts
export type WorkTaskStatus = 'todo' | 'doing' | 'done' | 'dismissed'
export type WorkTaskPriority = 'low' | 'medium' | 'high' | 'urgent'

export interface WorkTemplateStep { id: number, title: string, department: string, description: string | null, due_day: number, priority: WorkTaskPriority, order: number, default_assignee_member_id: number | null }
export interface WorkTemplate { id: number, name: string, description: string | null, cascade: boolean, generate_day: number, due_day: number, is_active: boolean, regimes: string[], steps?: WorkTemplateStep[] }
export interface WorkProcess { id: number, name: string, status: string, due_on: string | null, reference_month: string | null, template?: { id: number, name: string }, client?: { id: number, name: string }, progress?: { total: number, done: number, dismissed: number, open: number, ratio: number } }
export interface WorkTask { id: number, title: string, department: string, description: string | null, status: WorkTaskStatus, due_on: string | null, priority: WorkTaskPriority, assignee_member_id: number | null, order: number, process?: { id: number, name: string, client?: { id: number, name: string } } }
export interface WorkGroupedClient { client: { id: number, name: string }, totals: { processes: number, tasks: number }, processes: { process: { id: number, name: string }, ratio: number, tasks: WorkTask[] }[] }
```

Sem `client_id` em `WorkTask`; sem `TBD`.

- [ ] **Step 2: Criar `useWork.ts` com `queryOf` igual ao `useClients.ts`**

```ts
// frontend/app/composables/useWork.ts
import type { WorkGroupedClient, WorkProcess, WorkTask, WorkTemplate } from '~/types/work'

export function useWork() {
  const { $api } = useNuxtApp()
  function queryOf(params: object) {
    return Object.fromEntries(Object.entries(params).flatMap(([key, value]) => {
      if (value === undefined) return []
      return [[Array.isArray(value) ? `${key}[]` : key, value]]
    }))
  }
  async function listTemplates() {
    const res = await $api<{ data: WorkTemplate[] }>('/process-templates')
    return res.data
  }
  async function previewTemplate(id: number) {
    const res = await $api<{ data: { client: { id: number, name: string }, reason: string }[] }>(`/process-templates/${id}/preview`)
    return res.data
  }
  async function generateTemplate(id: number, referenceMonth: string) {
    const res = await $api<{ data: WorkProcess[] }>(`/process-templates/${id}/generate`, { method: 'POST', body: { reference_month: referenceMonth } })
    return res.data
  }
  async function listProcesses(params: { template_id?: number, reference_month?: string, client_id?: number, status?: string } = {}) {
    const res = await $api<{ data: WorkProcess[] }>('/processes', { query: queryOf(params) })
    return res.data
  }
  async function showProcess(id: number) {
    const res = await $api<{ data: WorkProcess }>(`/processes/${id}`)
    return res.data
  }
  async function listTasks(params: { status?: string, assignee_member_id?: number } = {}) {
    const res = await $api<{ data: WorkTask[] }>('/tasks', { query: queryOf(params) })
    return res.data
  }
  async function updateTask(id: number, body: { status?: string, dismissal_reason?: string, assignee_member_id?: number | null }) {
    const res = await $api<{ data: WorkTask }>(`/tasks/${id}`, { method: 'PATCH', body })
    return res.data
  }
  async function calendar(from: string, to: string) {
    const res = await $api<{ data: WorkTask[] }>('/work/calendar', { query: { from, to } })
    return res.data
  }
  async function grouped(referenceMonth: string) {
    const res = await $api<{ data: WorkGroupedClient[] }>('/work/grouped', { query: { reference_month: referenceMonth } })
    return res.data
  }
  return { listTemplates, previewTemplate, generateTemplate, listProcesses, showProcess, listTasks, updateTask, calendar, grouped }
}
```

- [ ] **Step 3: Criar `workNav.ts` + `work.vue` + sidebar**

`workNav.ts` exporta array de 5 itens (Calendário `/work/calendario` `i-lucide-calendar-days`, Clientes `/work/clientes` `i-lucide-users`, Processos `/work/processos` `i-lucide-layers`, Tarefas `/work/tarefas` `i-lucide-kanban-square`, Modelos `/work/modelos` `i-lucide-shapes`). `work.vue` copia o shell de `monitoring.vue` (`UDashboardPanel` + `Navbar` + `Toolbar` com tabs vindas de `workNav`). Em `layouts/default.vue`, adicionar trigger Work após Monitoramento com os 5 filhos e `onSelect: close`, sem tocar nos demais itens.

- [ ] **Step 4: Verificar tipos e lint**

```bash
cd frontend && pnpm exec eslint app/types/work.ts app/composables/useWork.ts app/utils/workNav.ts app/pages/work.vue app/layouts/default.vue
pnpm typecheck
```

Expected: PASS.

- [ ] **Step 5: Commit isolado**

```bash
git add frontend/app/types/work.ts frontend/app/composables/useWork.ts frontend/app/utils/workNav.ts frontend/app/pages/work.vue frontend/app/layouts/default.vue
git diff --cached --check
git commit -m "feat(work): add work shell nav and typed composable"
```

---

### Task 9: Páginas Work com mocks + calendário real

**Files:**
- Create: `frontend/app/pages/work/calendario.vue`, `clientes.vue`, `processos.vue`, `tarefas.vue`, `modelos.vue`
- Test: lint + typecheck + clique nas 5 abas.

**Interfaces:**
- Consumes: `useWork()`.
- Produces: 5 rotas com loading/empty/error pt-BR; calendário com chips por status + lista do dia.

- [ ] **Step 1: Criar as 5 páginas com estados padrão**

Cada página usa `useAsyncData` + `UEmpty`/`USkeleton`/`UAlert` + toast pt-BR. Exemplo do calendário:

```vue
<script setup lang="ts">
import type { WorkTask } from '~/types/work'
definePageMeta({ middleware: 'auth' })
const { calendar } = useWork()
const from = ref('2026-03-01')
const to = ref('2026-03-31')
const { data, status, error, refresh } = await useAsyncData('work-calendar', () => calendar(from.value, to.value))
const tasks = computed<WorkTask[]>(() => data.value ?? [])
const byDay = computed(() => {
  const map = new Map<string, WorkTask[]>()
  for (const task of tasks.value) {
    if (!task.due_on) continue
    const list = map.get(task.due_on) ?? []
    list.push(task)
    map.set(task.due_on, list)
  }
  return map
})
function chipColor(status: WorkTask['status']) {
  return status === 'todo' ? 'info' : status === 'doing' ? 'warning' : status === 'done' ? 'success' : 'neutral'
}
</script>

<template>
  <UCalendar>
    <template #day="{ day }">
      <div class="flex flex-wrap gap-1">
        <UChip v-for="task in (byDay.get(day.toString()) ?? [])" :key="task.id" :color="chipColor(task.status)" :text="task.title" />
      </div>
    </template>
  </UCalendar>
  <UAlert v-if="error" color="error" title="Não foi possível carregar o calendário" :actions="[{ label: 'Tentar novamente', onClick: refresh }]" />
</template>
```

As outras 4 páginas entram com mocks tipados vazios + mesmos estados (integração real nas Tasks 10–12).

- [ ] **Step 2: Verificar lint + tipos**

```bash
cd frontend && pnpm exec eslint app/pages/work && pnpm typecheck
```

Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add frontend/app/pages/work
git diff --cached --check
git commit -m "feat(work): add work pages with calendar feed"
```

---

### Task 10: Visão cliente agrupada + detalhe de processo

**Files:**
- Modify: `frontend/app/pages/work/clientes.vue`, `frontend/app/pages/work/processos.vue`, `frontend/app/pages/work/processos/[id].vue` (criar)
- Test: agrupamento real + progresso.

**Interfaces:**
- Consumes: `grouped()`, `showProcess()`.
- Produces: `UTable` com `grouping ['client_id','process_id']`; detalhe com Stepper/Timeline.

- [ ] **Step 1: Implementar tabela agrupada exatamente no padrão do snippet**

```ts
import { getGroupedRowModel } from '@tanstack/table-core'

const table = useVueTable({
  get data() { return flatTasks.value },
  columns,
  getCoreRowModel: getCoreRowModel(),
  getGroupedRowModel: getGroupedRowModel(),
  getExpandedRowModel: getExpandedRowModel(),
  state: { grouping: ['client_id', 'process_id'], expanded: true },
  groupedColumnMode: 'remove'
})
```

Coluna `title` com botão expand + nome do cliente/processo + `UBadge` (`todo:info`, `doing:warning`, `done:success`, `dismissed:neutral`). Dados vêm de `grouped(mês)` achatados com `client_id`, `client_name`, `process_id`, `process_name`.

- [ ] **Step 2: Implementar detalhe do processo**

`processos/[id].vue` consome `showProcess(id)`, exibe Stepper/Timeline com `progress.ratio`, lista tasks ordenadas expansíveis com cadeado quando `cascade && ordem bloqueada` (badge + tooltip, sem inventar endpoint novo).

- [ ] **Step 3: Verificar lint + tipos + agrupamento manual**

```bash
cd frontend && pnpm exec eslint app/pages/work/clientes.vue app/pages/work/processos.vue app/pages/work/processos/\[id\].vue && pnpm typecheck
```

Abrir `/work/clientes?reference_month=2026-03` com dados da Task 4 e confirmar Cliente > Processo > Task expande/colapsa.

- [ ] **Step 4: Commit**

```bash
git add frontend/app/pages/work/clientes.vue frontend/app/pages/work/processos.vue frontend/app/pages/work/processos/\[id\].vue
git diff --cached --check
git commit -m "feat(work): add grouped client view and process detail"
```

---

### Task 11: Kanban de tarefas (4 colunas, sem drag-and-drop)

**Files:**
- Modify: `frontend/app/pages/work/tarefas.vue`
- Test: transições + bloqueio + 403 visual.

**Interfaces:**
- Consumes: `listTasks()`, `updateTask()`, mesmos filtros da listagem.
- Produces: board A fazer/Em progresso/Concluída/Dispensada com cards fiscais e botões.

- [ ] **Step 1: Implementar colunas e cards fiscais**

Quatro colunas fixas `todo|doing|done|dismissed` com contadores; card exibe processo+mês, cliente, vencimento, responsável, prioridade, departamento e cadeado quando a API recusou por cascata. Botões: Avançar, Retornar e Dispensar (abre `UModal` com `UTextarea` de motivo obrigatório). Atribuição via `USelectMenu` de membros do account. `user` (`!canManageClients`) vê board sem botões nem select.

```ts
async function advance(task: WorkTask) {
  const next = task.status === 'todo' ? 'doing' : task.status === 'doing' ? 'done' : null
  if (!next) return
  try {
    await updateTask(task.id, { status: next })
    await refresh()
  } catch (error: unknown) {
    toast.add({ title: 'Avanço bloqueado pela cascata', description: apiMessage(error), color: 'warning' })
  }
}

async function dismissWithReason(task: WorkTask, reason: string) {
  if (!reason.trim()) {
    toast.add({ title: 'Informe o motivo da dispensa', color: 'error' })
    return
  }
  await updateTask(task.id, { status: 'dismissed', dismissal_reason: reason.trim() })
  await refresh()
}
```

Sem drag-and-drop; alternância tabela/board reaproveita os mesmos filtros.

- [ ] **Step 2: Verificar lint + tipos + fluxo manual**

```bash
cd frontend && pnpm exec eslint app/pages/work/tarefas.vue && pnpm typecheck
```

Confirmar: avançar step 2 com step 1 aberta mantém o card e mostra toast; dispensar sem motivo não chama a API.

- [ ] **Step 3: Commit**

```bash
git add frontend/app/pages/work/tarefas.vue
git diff --cached --check
git commit -m "feat(work): add task kanban with cascade feedback"
```

---

### Task 12: Modelos estilo TaskHub (tabela + editor em abas + preview + gerar)

**Files:**
- Modify: `frontend/app/pages/work/modelos.vue`
- Create: `frontend/app/pages/work/modelos/[id].vue`
- Test: criação fim-a-fim gerando processos do mês.

**Interfaces:**
- Consumes: `listTemplates|previewTemplate|generateTemplate|createTemplate|updateTemplate`.
- Produces: tabela (Título, Regimes, Categorias/Tags, Departamentos, Clientes, Recorrência) + editor em 5 abas.

- [ ] **Step 1: Implementar tabela + editor**

Tabela com as 6 colunas TaskHub; editor com `UTabs` (Associação, Clientes e Exceções, Prazo, Tarefas, Recorrência); aba Clientes e Exceções consome `previewTemplate(id)` mostrando motivo `rule|added`; botão "Gerar mês" chama `generateTemplate(id, 'YYYY-MM')` com feedback de quantos processos criados.

- [ ] **Step 2: Verificar lint + tipos + fim-a-fim**

```bash
cd frontend && pnpm exec eslint app/pages/work/modelos.vue app/pages/work/modelos/\[id\].vue && pnpm typecheck
```

Criar modelo PGDAS (Simples, dia 1/20, 2 etapas) e gerar `2026-03`; confirmar 1 processo por cliente elegível e snapshot congelado.

- [ ] **Step 3: Commit**

```bash
git add frontend/app/pages/work/modelos.vue frontend/app/pages/work/modelos/\[id\].vue
git diff --cached --check
git commit -m "feat(work): add taskhub-style model table and editor"
```

---

### Task 13: Verificação final e `openspec validate`

**Files:**
- Modify: só correções reveladas pelas verificações + `openspec/changes/work/tasks.md` (marcar o comprovado).
- Test: pint + suite + lint + typecheck + build + cenários manuais.

**Interfaces:**
- Consumes: feature completa.
- Produces: evidência de specs + segurança + UX atendidas.

- [ ] **Step 1: Backend formatter + suite completa**

```bash
cd backend && vendor/bin/pint --dirty --format agent && composer test 2>&1 | tail -5
```

Expected: formatado + todos PASS.

- [ ] **Step 2: Frontend completo**

```bash
cd frontend && pnpm lint && pnpm typecheck && pnpm build
```

Expected: PASS; qualquer falha alheia deve bater arquivo:linha com o baseline da Task 1.

- [ ] **Step 3: Cenários manuais autenticados**

Com `admin` e `user`: modelo PGDAS Simples + exceção, 10 empresas → 10 processos, repetir sem duplicar, scheduler `php artisan work:generate-recurrences`, troca de regime congelando março, cascata bloqueando, isolamento entre accounts, 403 de `user`, auditoria de suporte, calendário sem tasks sem prazo, board 4 colunas, agrupado Cliente > Processo > Task. Registrar HTTP + estado + audit log.

- [ ] **Step 4: Validar change e commitar verificação**

```bash
openspec validate work --strict
git add openspec/changes/work/tasks.md
git diff --cached --check
git commit -m "chore(work): verify work implementation"
```

Expected: `Change 'work' is valid`.

---

## Execution Handoff

Plano salvo em `openspec/changes/work/plan.md`.

1. **Subagent-Driven (recomendado):** executar uma task por agente fresco, com revisão de conformidade e qualidade entre tasks.
2. **Inline Execution:** executar neste contexto com `executing-plans`, em lotes curtos e checkpoints após backend (Tasks 2–7) e frontend (Tasks 8–12).

Em ambos os modos, começar relendo `proposal.md`, `design.md`, as três specs e este plano; não iniciar pela UI antes dos contratos backend das Tasks 2–7 estarem verdes.
