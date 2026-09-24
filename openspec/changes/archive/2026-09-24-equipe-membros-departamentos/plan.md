# Equipe (membros + departamentos) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Nota:** skill `superpowers:writing-plans` indisponível — plano escrito manualmente a partir de `tasks.md` + `design.md`, seguindo o template e as regras do schema.

**Goal:** Entregar o diretório de membros legível por qualquer membro e o cadastro de departamentos com vínculo N:N, mais a seção Equipe no frontend, como pré-requisito do Work.

**Architecture:** `Department` espelha `Tag` (`BelongsToAccount`, `unique(account_id,name)`, pivot `department_user` com `account_id`); `AccountPolicy::viewMembers` abre só leitura do diretório (`id,name,role,departments[]`, sem email); `DepartmentPolicy` espelha `TagPolicy`; controllers tenant finos com Form Requests + Resources + `SupportAudit::logWrite`; Nuxt consome via `useMembers.ts`/`useDepartments.ts` em 2 páginas sob `equipe.vue`.

**Tech Stack:** PHP ^8.3 / Laravel ^13.17 / Sanctum ^4.0 / PHPUnit ^12.5.12 / Pint ^1.27; Nuxt ^4.5.2 / Vue ^3.5.43 / @nuxt/ui ^4.11.1 / TypeScript ^6.0.3 / pnpm@12.5.1.

**Spec:** `openspec/changes/equipe-membros-departamentos/` — `proposal.md`, `specs/tenant/{team-departments,member-directory}/spec.md`, `design.md` e `tasks.md`.

## Global Constraints

- Ler `backend/AGENTS.md`, `AGENTS.md` e qualquer `.ai/rules` aplicável antes de editar; regras locais prevalecem sobre este plano.
- Trabalhar em `feat/team-departments` sobre o main; preservar mudanças não commitadas alheias; não editar páginas admin para limpar falhas não relacionadas.
- Backend usa Laravel 13 e PHP ^8.3; criar classes/migrations/tests com `php artisan make:* --no-interaction`.
- Backend roda em `backend/`: teste focado com `php artisan test --compact --filter=Nome`, suíte com `composer test`, estilo com `vendor/bin/pint --dirty --format agent`.
- Frontend roda em `frontend/` e usa somente `pnpm`; verificar com `pnpm lint`, `pnpm typecheck` e `pnpm build`.
- Não adicionar dependências Composer ou pnpm.
- Toda query de modelo usa `BelongsToAccount` (escopo + preenchimento `account_id` + binding 404 cross-account).
- `admin` e `operador` escrevem departamentos; `user` somente lê; leitura do diretório liberada para qualquer membro; escrita em suporte registra via `SupportAudit::logWrite` (leitura não audita).
- Toda rota de recurso permanece sob `auth:sanctum` + `tenant`.
- Textos da interface em português (Equipe, Membros, Departamentos); badges semânticos, sem paleta crua; cor de departamento só do enum de tags.
- Cada commit contém somente arquivos da task; antes do commit executar `git diff --cached --check` e revisar `git diff --cached`.

---

## File Structure

### Backend — criar

- `database/migrations/2026_09_24_000001_create_departments_table.php` — `departments` + pivot `department_user`.
- `app/Models/Department.php` — tenant, `members() BelongsToMany User`, `clients()` não; `steps()` futuro (Work) — não criar agora.
- `app/Policies/DepartmentPolicy.php` — espelho `TagPolicy` (viewAny qualquer membro; escrita admin|operador + mesmo account).
- `app/Http/Requests/Tenant/StoreDepartmentRequest.php`, `UpdateDepartmentRequest.php` — authz Gate + `name` trim/unique por account + `color` enum + `member_ids` com `after` checando `account_user`.
- `app/Http/Resources/DepartmentResource.php`, `MemberDirectoryResource.php` (ou presentador no controller espelhando `presentMember`).
- `app/Http/Controllers/Tenant/DepartmentController.php` — CRUD + sync `member_ids` + `SupportAudit::logWrite`.
- `database/factories/DepartmentFactory.php`.
- `tests/Feature/Tenancy/DepartmentTest.php`, `MemberDirectoryTest.php`.

### Backend — modificar

- `app/Policies/AccountPolicy.php` — adiciona `viewMembers` (mesmo tenant + `tenantRole != null`).
- `app/Http/Controllers/Tenant/AccountMemberController.php` — adiciona `directory()` (ordenado por `name`, sem email, com departments).
- `app/Models/Account.php` — `departments() HasMany`; `app/Models/User.php` — `departments() BelongsToMany`.
- `app/Providers/AppServiceProvider.php` — registra `Gate::policy(Department::class, DepartmentPolicy::class)`.
- `routes/api.php` — `GET account/members/directory` antes do `apiResource('account/members')` + `apiResource('departments')` (sem `show` se seguir Tags — ver decisão na Task 5).

### Frontend — criar

- `app/types/team.ts` — `Department {id,name,color,members_count,members?}`, `MemberDirectoryEntry {id,name,role,departments: {id,name,color}[]}`.
- `app/composables/useMembers.ts` — `listDirectory()` via `$api` com `queryOf` padrão `useClients.ts`.
- `app/composables/useDepartments.ts` — `list/create/update/remove` via `$api`.
- `app/utils/equipeNav.ts` — 2 itens (Membros `/equipe`, Departamentos `/equipe/departamentos`).
- `app/pages/equipe.vue` — shell `UDashboardPanel/Navbar/Toolbar`, padrão `customers.vue`/`monitoring.vue`.
- `app/pages/equipe/index.vue` — diretório + filtro por departamento.
- `app/pages/equipe/departamentos.vue` — lista A-Z + modal criar/editar + `USelectMenu` múltiplo de membros.

### Frontend — modificar

- `app/layouts/default.vue` — trigger Equipe após Clientes, sem tocar nos demais itens.

---

### Task 1: Baseline em `feat/team-departments`

**Files:**
- Modify: nenhum produto; só verificação.

**Interfaces:**
- Consumes: main atual.
- Produces: baseline registrado (branch + contagem de testes).

- [ ] **Step 1: Confirmar branch e status**

```bash
git status --short --branch
git checkout -b feat/team-departments
```

Expected: branch `feat/team-departments`; pendências alheias preservadas, não commitadas aqui.

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

Expected: PASS com a contagem atual. Se falhar, parar e corrigir ambiente antes de qualquer código.

- [ ] **Step 4: Rodar lint e typecheck frontend**

```bash
cd frontend && pnpm lint 2>&1 | tail -3 && pnpm typecheck 2>&1 | tail -3
```

Expected: ambos verdes. Registrar qualquer falha alheia arquivo:linha.

---

### Task 2: `MemberDirectoryTest` failing (RED)

**Files:**
- Create: `backend/tests/Feature/Tenancy/MemberDirectoryTest.php`
- Test: só este arquivo.

**Interfaces:**
- Consumes: `Account`, `AccountUser`, `User`, `Department` (ainda inexistente — teste de directory puro não precisa dele; teste com departments entra na Task 4).
- Produces: contrato do `GET /api/account/members/directory`.

- [ ] **Step 1: Escrever o teste failing**

```php
// backend/tests/Feature/Tenancy/MemberDirectoryTest.php
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
        $operador = $this->memberOf($account, 'operador', ['name' => 'Beto', 'email' => 'beto@opmoni.dev']);
        $this->memberOf($account, 'user', ['name' => 'Ana', 'email' => 'ana@opmoni.dev']);

        foreach (['operador', 'user'] as $role) {
            $member = User::firstWhere('email', $role === 'operador' ? 'beto@opmoni.dev' : 'ana@opmoni.dev');
            $response = $this->actingAs($member, 'sanctum')->getJson('/api/account/members/directory');
            $response->assertOk();
            $response->assertJsonPath('data.0.name', 'Ana');
            $response->assertJsonMissing(['email' => 'ana@opmoni.dev']);
        }
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

    private function memberOf(Account $account, string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        AccountUser::create(['account_id' => $account->getKey(), 'user_id' => $user->getKey(), 'role' => $role]);
        $user->forceFill(['current_account_id' => $account->getKey()])->save();
        return $user->refresh();
    }
}
```

Criar com `php artisan make:test --phpunit MemberDirectoryTest` e substituir o conteúdo pelo acima.

- [ ] **Step 2: Rodar para confirmar falha**

```bash
cd backend && php artisan test --compact --filter=MemberDirectoryTest
```

Expected: FAIL (rota 404 — `directory` ainda não existe; cuidado: se cair no `show {member}`, falha com 404 do binding — mesmo sinal, motivo certo a confirmar no controller da Task 3).

---

### Task 3: Diretório pass (policy + controller + rota)

**Files:**
- Modify: `backend/app/Policies/AccountPolicy.php`, `backend/app/Http/Controllers/Tenant/AccountMemberController.php`, `backend/routes/api.php`
- Test: `backend/tests/Feature/Tenancy/MemberDirectoryTest.php` (verde) + `RolesTest` (sem regressão).

**Interfaces:**
- Consumes: `Account::members()`, `CurrentTenant`, `Gate`.
- Produces: `GET /api/account/members/directory` → `{data: [{id,name,role,departments[]}]}`.

- [ ] **Step 1: Adicionar `viewMembers` à `AccountPolicy`**

```php
public function viewMembers(User $user, Account $account): bool
{
    return $this->tenantId() === $account->getKey() && $this->tenantRole($user) !== null;
}
```

`tenantRole` já trata super_admin como `admin` — suporte em modo suporte passa.

- [ ] **Step 2: Adicionar `directory()` ao `AccountMemberController`**

```php
public function directory(): JsonResponse
{
    $account = $this->tenantAccount();
    Gate::authorize('viewMembers', $account);

    $rows = $account->members()->orderBy('name')->get()->map(fn (User $member): array => [
        'id' => $member->getKey(),
        'name' => $member->name,
        'role' => $member->pivot->role,
        'departments' => [],
    ])->all();

    return response()->json(['data' => $rows]);
}
```

`departments` vazio até a Task 5 ligar o vínculo (evita tocar em `User` antes da migration existir).

- [ ] **Step 3: Registrar a rota antes do resource**

```php
Route::get('account/members/directory', [AccountMemberController::class, 'directory']);
Route::apiResource('account/members', AccountMemberController::class)->parameter('members', 'member');
```

Ordem importa: `directory` antes, senão o router casa `show`.

- [ ] **Step 4: Rodar testes até passar + regressão**

```bash
cd backend && php artisan test --compact --filter=MemberDirectoryTest
cd backend && php artisan test --compact --filter=RolesTest
```

Expected: ambos PASS (`test_operador_cannot_manage_members` continua verde).

- [ ] **Step 5: Formatar e commitar isolado**

```bash
cd backend && vendor/bin/pint --dirty --format agent
git add app/Policies/AccountPolicy.php app/Http/Controllers/Tenant/AccountMemberController.php routes/api.php tests/Feature/Tenancy/MemberDirectoryTest.php
git diff --cached --check
git commit -m "feat(team): add readable member directory"
```

---

### Task 4: `DepartmentTest` failing (RED)

**Files:**
- Create: `backend/tests/Feature/Tenancy/DepartmentTest.php`
- Test: só este arquivo.

**Interfaces:**
- Consumes: `Department` (ainda inexistente), `Tag` como molde de payload.
- Produces: contrato do CRUD `departments`.

- [ ] **Step 1: Escrever o teste failing**

```php
// backend/tests/Feature/Tenancy/DepartmentTest.php (essencial)
public function test_operador_creates_department_with_members(): void
{
    $account = Account::factory()->create();
    $operador = $this->memberOf($account, 'operador');
    $ana = $this->memberOf($account, 'user');

    $response = $this->actingAs($operador, 'sanctum')->postJson('/api/departments', [
        'name' => 'Fiscal', 'color' => 'success', 'member_ids' => [$ana->getKey()],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Fiscal');
}

public function test_user_is_forbidden_and_foreign_member_rejected(): void
{
    $account = Account::factory()->create();
    $other = Account::factory()->create();
    $user = $this->memberOf($account, 'user');
    $outsider = $this->memberOf($other, 'user');

    $this->actingAs($user, 'sanctum')->postJson('/api/departments', [
        'name' => 'Fiscal', 'color' => 'success',
    ])->assertForbidden();

    $admin = $this->memberOf($account, 'admin');
    $this->actingAs($admin, 'sanctum')->postJson('/api/departments', [
        'name' => 'Fiscal', 'color' => 'success', 'member_ids' => [$outsider->getKey()],
    ])->assertUnprocessable();
}
```

Helper `memberOf` igual ao da Task 2. Incluir também: nome duplicado com caixa diferente → 422; isolamento (lista omite outro account, acesso direto 404); escrita em suporte gera `SupportAccessLog` (espelhar `SupportAccessTest`).

- [ ] **Step 2: Rodar para confirmar falha**

```bash
cd backend && php artisan test --compact --filter=DepartmentTest
```

Expected: FAIL (`Department` inexistente / rota 404).

---

### Task 5: Departamentos pass (migration + model + policy + API)

**Files:**
- Create: migration, `app/Models/Department.php`, `app/Policies/DepartmentPolicy.php`, Requests, Resources, `app/Http/Controllers/Tenant/DepartmentController.php`, `database/factories/DepartmentFactory.php`
- Modify: `app/Models/Account.php`, `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, `routes/api.php`, `AccountMemberController::directory` (liga `departments`)
- Test: `DepartmentTest` + `MemberDirectoryTest` verdes.

**Interfaces:**
- Consumes: `account_user` (validação de vínculo), `SupportAudit`.
- Produces: `GET|POST /departments`, `GET|PATCH|DELETE /departments/{id}`.

- [ ] **Step 1: Criar migration e model via artisan**

```bash
cd backend
php artisan make:migration --no-interaction create_departments_table
php artisan make:model --no-interaction Department
php artisan make:factory --no-interaction DepartmentFactory
```

Preencher `up()`:

```php
Schema::create('departments', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('account_id')->constrained()->cascadeOnDelete();
    $table->string('name', 40);
    $table->string('color')->default('neutral');
    $table->timestamps();
    $table->unique(['account_id', 'name']);
});

Schema::create('department_user', function (Blueprint $table): void {
    $table->foreignId('account_id')->constrained()->cascadeOnDelete();
    $table->foreignId('department_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->unique(['department_id', 'user_id']);
    $table->index(['account_id', 'user_id']);
});
```

Model espelha `Tag`:

```php
#[Fillable(['account_id', 'name', 'color'])]
class Department extends Model
{
    use BelongsToAccount, HasFactory;

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_user')->withPivot('account_id');
    }
}
```

`Account::departments()`, `User::departments()` no mesmo molde.

- [ ] **Step 2: Policy + Requests + Resource espelhando Tags**

`DepartmentPolicy` copia `TagPolicy` trocando o model. Requests copiam `StoreTagRequest` + `member_ids`:

```php
'member_ids' => ['sometimes', 'array'],
'member_ids.*' => ['integer'],
// + after(): cada id precisa existir em account_user do tenant
```

Comparar nome com `trim` (duplicata de caixa: normalizar com `LOWER()` na checagem ou validar no `after` — documentar a escolha no commit).

- [ ] **Step 3: Controller fino + rotas + policy**

CRUD com `Gate::authorize`, sync de `member_ids` com `account_id` no pivot, `SupportAudit::logWrite` em store/update/destroy. Decidir `show`: `TagController` exclui `show` do resource (`->except(['show'])`) — seguir igual salvo motivo explícito no commit.

```php
Route::apiResource('departments', DepartmentController::class)->except(['show']);
```

Registrar `Gate::policy(Department::class, DepartmentPolicy::class)`.

- [ ] **Step 4: Ligar `departments` no `directory()`**

Trocar `'departments' => []` por `$member->departments()->orderBy('name')->get(['departments.id','name','color'])` — atenção ao N+1: usar eager `with('departments')` na query do `directory()`.

- [ ] **Step 5: Rodar testes até passar**

```bash
cd backend && php artisan test --compact --filter="DepartmentTest|MemberDirectoryTest"
```

Expected: PASS.

- [ ] **Step 6: Formatar e commitar**

```bash
cd backend && vendor/bin/pint --dirty --format agent
git add app/Models/Department.php app/Models/Account.php app/Models/User.php app/Policies/DepartmentPolicy.php app/Http/Requests/Tenant/*Department* app/Http/Resources/DepartmentResource.php app/Http/Controllers/Tenant/DepartmentController.php app/Providers/AppServiceProvider.php routes/api.php database/migrations/*departments* database/factories/DepartmentFactory.php tests/Feature/Tenancy/DepartmentTest.php
git diff --cached --check
git commit -m "feat(team): add departments with member links"
```

---

### Task 6: Frontend Equipe (tipos + composables + shell + 2 páginas)

**Files:**
- Create: `frontend/app/types/team.ts`, `frontend/app/composables/useMembers.ts`, `frontend/app/composables/useDepartments.ts`, `frontend/app/utils/equipeNav.ts`, `frontend/app/pages/equipe.vue`, `frontend/app/pages/equipe/index.vue`, `frontend/app/pages/equipe/departamentos.vue`
- Modify: `frontend/app/layouts/default.vue`
- Test: `pnpm lint` + `pnpm typecheck` + navegação manual.

**Interfaces:**
- Consumes: `GET /account/members/directory`, CRUD `/departments`.
- Produces: seção Equipe com 2 abas funcionais.

- [ ] **Step 1: Criar tipos espelhando a API**

```ts
// frontend/app/types/team.ts
export interface TeamDepartmentRef { id: number, name: string, color: string }
export interface MemberDirectoryEntry { id: number, name: string, role: string, departments: TeamDepartmentRef[] }
export interface Department { id: number, name: string, color: string, members_count?: number, members?: MemberDirectoryEntry[] }
export type DepartmentColor = 'neutral' | 'primary' | 'success' | 'info' | 'warning' | 'error'
```

Sem `email` em `MemberDirectoryEntry`; sem `TBD`.

- [ ] **Step 2: Criar `useMembers.ts` + `useDepartments.ts` com `queryOf` igual ao `useClients.ts`**

```ts
// frontend/app/composables/useMembers.ts
import type { MemberDirectoryEntry } from '~/types/team'

export function useMembers() {
  const { $api } = useNuxtApp()
  async function listDirectory() {
    const res = await $api<{ data: MemberDirectoryEntry[] }>('/account/members/directory')
    return res.data
  }
  return { listDirectory }
}
```

`useDepartments.ts` com `list/show/create/update/remove` no mesmo molde (`UModal` de Tags como referência de UX para o modal).

- [ ] **Step 3: Criar `equipeNav.ts` + `equipe.vue` + sidebar**

`equipeNav.ts` exporta 2 itens (Membros `/equipe` `i-lucide-users`, Departamentos `/equipe/departamentos` `i-lucide-building-2`). `equipe.vue` copia o shell de `monitoring.vue` (`UDashboardPanel` + `Navbar` + `Toolbar` com tabs). Em `layouts/default.vue`, adicionar trigger Equipe após Clientes com os 2 filhos e `onSelect: close`, sem tocar nos demais itens.

- [ ] **Step 4: Criar as 2 páginas com estados padrão**

`equipe/index.vue`: `useAsyncData` + busca + filtro por departamento (`USelectMenu`) + lista agrupada por departamento (A-Z, padrão TaskHub) + `UEmpty`/`USkeleton`/`UAlert` + toast pt-BR. `equipe/departamentos.vue`: cards/grupos A-Z com contagem de membros + modal criar/editar (nome, cor, `USelectMenu` múltiplo de membros vindo de `listDirectory()`) + `user` (`!canManageClients`) sem botões de escrita.

- [ ] **Step 5: Verificar lint + tipos + navegação**

```bash
cd frontend && pnpm exec eslint app/types/team.ts app/composables/useMembers.ts app/composables/useDepartments.ts app/utils/equipeNav.ts app/pages/equipe.vue app/pages/equipe/index.vue app/pages/equipe/departamentos.vue app/layouts/default.vue
pnpm typecheck
```

Expected: PASS. Abrir `/equipe` e `/equipe/departamentos` autenticado e alternar as abas.

- [ ] **Step 6: Commit isolado**

```bash
git add frontend/app/types/team.ts frontend/app/composables/useMembers.ts frontend/app/composables/useDepartments.ts frontend/app/utils/equipeNav.ts frontend/app/pages/equipe.vue frontend/app/pages/equipe/index.vue frontend/app/pages/equipe/departamentos.vue frontend/app/layouts/default.vue
git diff --cached --check
git commit -m "feat(team): add equipe section with directory and departments"
```

---

### Task 7: Verificação final

**Files:**
- Modify: só correções reveladas pelas verificações + `openspec/changes/equipe-membros-departamentos/tasks.md` (marcar o comprovado).
- Test: pint + suite + lint + typecheck + build + cenários manuais.

**Interfaces:**
- Consumes: feature completa.
- Produces: evidência de specs + segurança + UX atendidas.

- [ ] **Step 1: Backend formatter + suite completa**

```bash
cd backend && vendor/bin/pint --dirty --format agent && composer test 2>&1 | tail -5
```

Expected: formatado + suite verde (contagem nova = baseline + DepartmentTest + MemberDirectoryTest).

- [ ] **Step 2: Frontend lint + typecheck + build**

```bash
cd frontend && pnpm lint 2>&1 | tail -3 && pnpm typecheck 2>&1 | tail -3 && pnpm build 2>&1 | tail -5
```

Expected: verdes; se `build` falhar por causa alheia, documentar arquivo:linha e seguir sem tocar.

- [ ] **Step 3: Cenários manuais autenticados**

Criar Fiscal (success) + Pessoal (warning), vincular Felipe Galvão aos dois; listar diretório como operador e como user (sem email); tentar escrita como user → 403; checar isolamento (outro account não aparece); escrever em suporte e conferir `support_access_logs`. Confirmar HTTP, estado persistido e audit log conformes às specs.

- [ ] **Step 4: `openspec validate` da change**

```bash
openspec validate --change equipe-membros-departamentos 2>&1 | tail -10
```

Expected: válido. Se o comando exato divergir, usar `openspec validate --all --json` e filtrar a change.

---

## Impacto no Work (registrar, não implementar aqui)

- `specs/tenant/work-templates`: `department` texto livre → `department_id` FK + snapshot; atualizar spec + design da change `work` antes da Task 5 dela.
- `specs/tenant/work-tasks`: `assignee` validado contra `account_user`; listagem tolera membro removido ("sem responsável").
- Nenhum código do Work é tocado por esta change.
