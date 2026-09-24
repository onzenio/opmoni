# Multi-tenant Chatwoot-style Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar tenancy por `account_id` (auth Sanctum SPA, contas, roles, planos, suporte auditado, painel `/admin`) sobre o esqueleto atual.

**Architecture:** Single-DB com `account_id` em todos os recursos + global scope via tenant resolvido em middleware; policies por nível; rotas globais sob gate `super_admin`. Frontend consome a API por `$fetch` com sessão/CSRF, sem token no client.

**Tech Stack:** Laravel 13 / PHP ^8.3 / Sanctum (SPA session) / PHPUnit / Pint; Nuxt 4 / Vue 3 / @nuxt/ui 4.11.1 / zod 4 / pnpm 12.5.1.

**Spec:** `openspec/changes/multi-tenant-accounts/` — `proposal.md`, `specs/tenant/*/spec.md` (6), `design.md`, `tasks.md`. O plano argumenta a partir da spec; executores leem ambos.

## Global Constraints

- Backend comandos rodam em `backend/`: `php artisan test --compact`, `vendor/bin/pint --dirty --format agent`.
- Frontend comandos rodam em `frontend/`: `pnpm lint`, `pnpm typecheck`. Frontend usa `pnpm` (nunca `npm`); backend usa `npm` para Vite.
- Testes backend usam sqlite `:memory:` via `phpunit.xml` — sem docker para testar.
- Novos arquivos Laravel via `php artisan make:* --no-interaction`.
- Estilo PHP: chaves sempre, promotion no construtor, tipos explícitos, `TitleCase` em enums.
- Não criar docs além das pedidas; seguir convenções dos arquivos irmãos.
- Limites: `basico{users:5,clients:50,monitorings:100}`, `profissional{20,500,1000}`, `empresarial{}` (ausente = ilimitado).
- Sem hard delete de conta; `support_access_logs` append-only (sem update/delete).

---

## File Structure

**Backend — criar:**
- `routes/api.php` — rotas auth, tenant e admin
- `app/Models/Account.php`, `AccountUser.php`, `Plan.php`, `Subscription.php`, `SupportAccessLog.php`, `Client.php`, `SerproMonitoring.php`, `Document.php`, `Process.php`
- `app/Tenant/CurrentTenant.php` — singleton com `?int $accountId`
- `app/Concerns/BelongsToAccount.php` — global scope por `CurrentTenant`
- `app/Http/Middleware/ResolveTenant.php` (alias `tenant`), `EnsureSuperAdmin.php` (alias `super_admin`)
- `app/Observers/AccountObserver.php` — cria subscription Básico no `created`
- `app/Services/PlanLimits.php` — `assertCanCreate(Account $account, string $key): void`
- `app/Http/Controllers/AuthController.php`, `SupportAccessController.php`, `Admin/{AccountController,PlanController,SubscriptionController,UserController,SupportLogController}.php`, `Tenant/{ClientController,SerproMonitoringController,DocumentController,ProcessController,AccountMemberController}.php`
- `app/Policies/AccountPolicy.php`, `ClientPolicy.php`, `SerproMonitoringPolicy.php`, `DocumentPolicy.php`, `ProcessPolicy.php`
- `database/migrations/20*_add_tenant_columns_to_users_table.php`, `..._create_accounts_table.php`, `..._create_account_user_table.php`, `..._create_plans_table.php`, `..._create_subscriptions_table.php`, `..._create_support_access_logs_table.php`, `..._create_clients_table.php`, `..._create_serpro_monitorings_table.php`, `..._create_documents_table.php`, `..._create_processes_table.php`
- `database/seeders/PlanSeeder.php`
- `tests/Feature/Tenancy/AuthTest.php`, `IsolationTest.php`, `RolesTest.php`, `SubscriptionsTest.php`, `SupportAccessTest.php`

**Backend — modificar:**
- `bootstrap/app.php` — registrar `api.php`, `statefulApi()`, aliases `tenant`/`super_admin`
- `app/Models/User.php` — `HasApiTokens`, `is_super_admin`, `current_account_id`, helpers `isSuperAdmin()`, `accountRole()`, `currentAccount()`
- `app/Providers/AppServiceProvider.php` — bind `CurrentTenant`, registrar observer e policies
- `.env.example` — `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`; `config/cors.php` publicado com `supports_credentials=true`

**Frontend — criar:**
- `app/composables/useAuth.ts` — estado compartilhado (user, contas, conta atual, login/logout/switch/enter/exit)
- `app/plugins/api.ts` — `$fetch` com `baseURL` do runtime config + `credentials: 'include'` + init CSRF
- `app/middleware/auth.ts`, `app/middleware/super-admin.ts`
- `app/layouts/admin.vue`
- `app/pages/admin/index.vue`, `contas.vue`, `planos.vue`, `assinaturas.vue`, `usuarios.vue`, `suporte.vue`

**Frontend — modificar:**
- `app/pages/login.vue`, `app/pages/onboarding.vue` — chamar a API real
- `nuxt.config.ts` — `runtimeConfig.public.apiUrl`

Cada arquivo tem uma responsabilidade; controllers finos delegam regra a policies/services.

---

### Task 1: Sanctum + base da API

**Files:**
- Modify: `backend/bootstrap/app.php:8-16`
- Create: `backend/routes/api.php`, `backend/config/cors.php` (via publish)
- Modify: `backend/.env.example`

**Interfaces:**
- Consumes: nada (primeira task)
- Produces: guard `auth:sanctum` funcional; `CurrentTenant` ainda não existe (Task 3)

- [ ] **Step 1: Instalar Sanctum e expor api.php**

```bash
php artisan install:api --no-interaction
php artisan config:publish cors --no-interaction
```

- [ ] **Step 2: Registrar rotas e middleware stateful**

```php
// bootstrap/app.php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
->withMiddleware(function (Middleware $middleware): void {
    $middleware->statefulApi();
})
```

```php
// config/cors.php
'supports_credentials' => true,
```

```ini
# .env.example (acrescentar)
SANCTUM_STATEFUL_DOMAINS=localhost:3000
SESSION_DOMAIN=localhost
```

- [ ] **Step 3: Verificar**

Run: `php artisan route:list --path=api`
Expected: lista inclui `sanctum/csrf-cookie`

- [ ] **Step 4: Commit**

```bash
git add bootstrap/app.php routes/api.php config/cors.php .env.example
git commit -m "feat(backend): sanctum SPA base e rotas api"
```

### Task 2: Colunas de tenant em `users`

**Files:**
- Create: `backend/database/migrations/2026_01_01_000001_add_tenant_columns_to_users_table.php`
- Modify: `backend/app/Models/User.php:1-40`
- Test: `backend/tests/Feature/Tenancy/AuthTest.php` (criado aqui, expandido na Task 4)

**Interfaces:**
- Consumes: nada
- Produces: `User::isSuperAdmin(): bool`, `User::accountRole(Account|int): ?string`, `User::currentAccount(): ?Account`

- [ ] **Step 1: Escrever migration + teste falhando**

```php
Schema::table('users', function (Blueprint $table): void {
    $table->boolean('is_super_admin')->default(false);
    $table->foreignId('current_account_id')->nullable()->constrained('accounts')->nullOnDelete();
});
```

```php
// tests/Feature/Tenancy/AuthTest.php
public function test_first_registered_user_becomes_super_admin(): void
{
    $response = $this->postJson('/api/register', [
        'name' => 'Chefona', 'email' => 'chef@opmoni.dev', 'password' => 'password123',
        'company' => 'HQ', 'size' => 'Só eu',
    ]);
    $response->assertCreated();
    $this->assertTrue(User::firstWhere('email', 'chef@opmoni.dev')->isSuperAdmin());
}
```

- [ ] **Step 2: Rodar para ver falhar**

Run: `php artisan test --compact --filter=test_first_registered_user_becomes_super_admin`
Expected: FAIL (rota/model ainda não existem)

- [ ] **Step 3: Implementar colunas + helpers mínimos no User**

```php
use Laravel\Sanctum\HasApiTokens;

use HasApiTokens, HasFactory, Notifiable;

public function isSuperAdmin(): bool
{
    return $this->is_super_admin === true;
}

public function accountRole(Account|int $account): ?string
{
    $id = $account instanceof Account ? $account->getKey() : $account;
    return $this->accountLinks()->where('account_id', $id)->value('role');
}

public function accountLinks(): HasMany
{
    return $this->hasMany(AccountUser::class);
}

public function currentAccount(): BelongsTo
{
    return $this->belongsTo(Account::class, 'current_account_id');
}
```

(Este passo só faz o teste compilar; a rota `/register` vem na Task 4.)

- [ ] **Step 4: Migrar e formatar**

Run: `php artisan migrate --force && vendor/bin/pint --dirty --format agent`
Expected: migrate OK (tabela `accounts` ainda não existe — a FK falha; por isso esta migration roda DEPOIS da de accounts: renomeie o arquivo para timestamp posterior ao da Task 3, ex. `2026_01_01_000010_...`)

- [ ] **Step 5: Commit**

```bash
git add database/migrations/ app/Models/User.php tests/Feature/Tenancy/AuthTest.php
git commit -m "feat(backend): colunas de tenant em users"
```

### Task 3: Tabelas do domínio + seed + observer

**Files:**
- Create: 9 migrations (accounts, account_user, plans, subscriptions, support_access_logs, clients, serpro_monitorings, documents, processes), 8 models, `app/Observers/AccountObserver.php`, `database/seeders/PlanSeeder.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Interfaces:**
- Consumes: Task 2 (`current_account_id` referencia `accounts`)
- Produces: `Account::created` → subscription Básico; `Plan::bySlug('basico')`

- [ ] **Step 1: Migrations (essência)**

```php
// accounts
$table->id(); $table->string('name'); $table->string('status')->default('active');
$table->json('settings')->nullable(); $table->timestamps();

// account_user
$table->id(); $table->foreignId('account_id')->constrained()->cascadeOnDelete();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('role'); $table->foreignId('inviter_id')->nullable()->constrained('users');
$table->unique(['account_id', 'user_id']); $table->timestamps();

// plans
$table->id(); $table->string('slug')->unique(); $table->string('name');
$table->json('limits'); $table->timestamps();

// subscriptions
$table->id(); $table->foreignId('account_id')->unique()->constrained()->cascadeOnDelete();
$table->foreignId('plan_id')->constrained()->restrictOnDelete();
$table->string('status')->default('active'); $table->timestamps();

// support_access_logs
$table->id(); $table->foreignId('super_admin_user_id')->constrained('users');
$table->foreignId('account_id')->constrained()->cascadeOnDelete();
$table->string('action'); $table->json('metadata')->nullable(); $table->string('ip', 45)->nullable();
$table->timestamp('created_at')->useCurrent(); // sem updated_at: append-only

// clients / serpro_monitorings / documents / processes (padrão)
$table->id(); $table->foreignId('account_id')->constrained()->cascadeOnDelete();
$table->string('name'); $table->timestamps();
// (+ campos próprios mínimos; evoluem em changes futuras)
```

- [ ] **Step 2: Models + observer + seeder**

```php
// AccountObserver
public function created(Account $account): void
{
    $account->subscription()->create(['plan_id' => Plan::bySlug('basico')->getKey()]);
}
```

```php
// PlanSeeder
Plan::upsert([
    ['slug' => 'basico', 'name' => 'Básico', 'limits' => ['users' => 5, 'clients' => 50, 'monitorings' => 100]],
    ['slug' => 'profissional', 'name' => 'Profissional', 'limits' => ['users' => 20, 'clients' => 500, 'monitorings' => 1000]],
    ['slug' => 'empresarial', 'name' => 'Empresarial', 'limits' => []],
], 'slug');
```

Registrar observer no `AppServiceProvider::boot()` + `PlanSeeder` no `DatabaseSeeder`.

- [ ] **Step 3: Verificar**

Run: `php artisan migrate:fresh --seed --force && php artisan tinker --execute 'echo App\Models\Plan::count();'`
Expected: `3`

- [ ] **Step 4: Commit**

```bash
git add database/ app/Models/ app/Observers/ app/Providers/AppServiceProvider.php
git commit -m "feat(backend): dominio tenant, seed de planos e subscription automatica"
```

### Task 4: Auth endpoints + `/me`

**Files:**
- Create: `backend/app/Http/Controllers/AuthController.php`
- Modify: `backend/routes/api.php`, `backend/tests/Feature/Tenancy/AuthTest.php`

**Interfaces:**
- Consumes: Tasks 2–3
- Produces: `POST /api/register|login|logout`, `GET /api/me`

- [ ] **Step 1: Completar testes (falhando)**

Acrescentar a `AuthTest.php`: login válido autentica; credencial inválida → 422 sem sessão; `/me` sem sessão → 401; `/me` com sessão retorna `is_super_admin`, `accounts`, `current_account`; registro com base populada (usuários ou accounts existentes) → 403 sem criar nada.

- [ ] **Step 2: Rodar para ver falhar**

Run: `php artisan test --compact --filter=AuthTest`
Expected: FAIL (rotas não existem)

- [ ] **Step 3: Implementar controller + rotas**

```php
// AuthController@register — só no startup inicial (base sem usuários ou sem accounts)
if (User::exists() || Account::exists()) {
    abort(403, 'Registro inicial indisponível.');
}
$data = $request->validate([
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'email', 'unique:users'],
    'password' => ['required', 'string', 'min:8'],
    'company' => ['required', 'string', 'max:255'],
    'size' => ['required', 'string', 'max:50'],
]);
$user = DB::transaction(function () use ($data) {
    $user = User::create([...$data, 'password' => $data['password'],
        'is_super_admin' => true]); // gate acima garante base vazia: sempre o primeiro
    $account = Account::create(['name' => $data['company']]);
    $account->members()->attach($user, ['role' => 'admin']);
    $user->update(['current_account_id' => $account->getKey()]);
    return $user;
});
Auth::login($user);
return response()->json($user->load('currentAccount'), 201);
```

`login`: `Auth::attempt` + `session()->regenerate()`; `logout`: `Auth::guard('web')->logout()` + invalidate; `me`: retorna user + `accounts` (via vínculos) + `current_account`.

```php
// routes/api.php
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
```

- [ ] **Step 4: Rodar e formatar**

Run: `php artisan test --compact --filter=AuthTest && vendor/bin/pint --dirty --format agent`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AuthController.php routes/api.php tests/Feature/Tenancy/AuthTest.php
git commit -m "feat(backend): auth SPA register login logout me"
```

### Task 5: Tenant runtime (CurrentTenant, trait, middlewares, policies, limites)

**Files:**
- Create: `app/Tenant/CurrentTenant.php`, `app/Concerns/BelongsToAccount.php`, middlewares, `app/Services/PlanLimits.php`, 5 policies
- Modify: `bootstrap/app.php` (aliases), 4 models (usar trait), `AuthController` (setar tenant no login)
- Test: `IsolationTest.php`, `RolesTest.php`, `SubscriptionsTest.php`

**Interfaces:**
- Consumes: Tasks 2–4
- Produces: escopo automático; `PlanLimits::assertCanCreate(Account $account, string $key): void` (lança `ValidationException` → 422)

- [ ] **Step 1: Testes de isolamento e roles (falhando)**

`IsolationTest`: membro da conta A lista clients → só os de A; GET recurso de B por id → 404; conta suspensa → 403; comum trocando de conta → 403; `admin` de conta em rota global → 403. `RolesTest`: operador gerenciando membros → 403; admin convidando → 201. `SubscriptionsTest`: conta nova tem 1 subscription ativa no Básico; 51º client no Básico → 422 e count segue 50.

- [ ] **Step 2: Rodar para ver falhar**

Run: `php artisan test --compact --filter="IsolationTest|RolesTest|SubscriptionsTest"`
Expected: FAIL

- [ ] **Step 3: Implementar runtime**

```php
// CurrentTenant
final class CurrentTenant
{
    public ?int $accountId = null;
}
// AppServiceProvider::register: $this->app->singleton(CurrentTenant::class);

// BelongsToAccount
protected static function bootBelongsToAccount(): void
{
    static::addGlobalScope('account', fn (Builder $q) => $q->when(
        resolve(CurrentTenant::class)->accountId,
        fn ($q, $id) => $q->where($q->getModel()->getTable().'.account_id', $id)
    ));
    static::creating(fn ($m) => $m->account_id ??= resolve(CurrentTenant::class)->accountId);
}

// ResolveTenant: da sessão/user; conta suspensa → abort(403); alias 'tenant'
// EnsureSuperAdmin: !$user?->isSuperAdmin() → abort(403); alias 'super_admin'

// PlanLimits
public static function assertCanCreate(Account $account, string $key): void
{
    $limit = $account->subscription->plan->limits[$key] ?? null;
    if ($limit === null) { return; } // ilimitado
    $count = match ($key) {
        'users' => $account->members()->count(),
        'clients' => $account->clients()->count(),
        'monitorings' => $account->monitorings()->count(),
    };
    if ($count >= $limit) {
        throw ValidationException::withMessages(['limit' => ['Limite do plano atingido.']]);
    }
}
```

Policies verificam `$user->accountRole($model->account_id)` + `CurrentTenant`. Registrar em `AuthServiceProvider`-style via `Gate::policy` no `AppServiceProvider::boot`.

- [ ] **Step 4: Rodar e formatar**

Run: `php artisan test --compact && vendor/bin/pint --dirty --format agent`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Tenant app/Concerns app/Http/Middleware app/Services app/Policies bootstrap/app.php tests/Feature/Tenancy/
git commit -m "feat(backend): tenant runtime, policies e limites de plano"
```

### Task 6: Controllers tenant + admin + suporte

**Files:**
- Create: controllers tenant (5), admin (5), `SupportAccessController.php`
- Modify: `routes/api.php`
- Test: `SupportAccessTest.php` + cenários admin em `RolesTest.php`

**Interfaces:**
- Consumes: Task 5
- Produces: API completa usada pelo frontend (Task 7–9)

- [ ] **Step 1: Testes (falhando)** — enter seta conta + log; exit restaura + log; escrita em suporte aplica + loga com identidade do super_admin; comum em `/admin/*` → 403; suspender conta bloqueia tenant; troca de plano aplica limites.

- [ ] **Step 2: Rodar para ver falhar**

Run: `php artisan test --compact --filter=SupportAccessTest`
Expected: FAIL

- [ ] **Step 3: Implementar**

```php
// SupportAccessController@enter
$account = Account::findOrFail($id);
SupportAccessLog::create(['super_admin_user_id' => $user->id,
    'account_id' => $account->id, 'action' => 'enter', 'ip' => $request->ip()]);
$user->update(['current_account_id' => $account->id]);
```

Rotas:

```php
Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::apiResource('clients', ClientController::class);
    // + monitorings, documents, processes, account/members
});
Route::middleware(['auth:sanctum', 'super_admin'])->prefix('admin')->group(function (): void {
    Route::apiResource('accounts', Admin\AccountController::class);
    Route::apiResource('plans', Admin\PlanController::class);
    Route::apiResource('subscriptions', Admin\SubscriptionController::class)->only(['index', 'show', 'update']);
    Route::get('users', [Admin\UserController::class, 'index']);
    Route::get('support/logs', [Admin\SupportLogController::class, 'index']);
});
Route::middleware(['auth:sanctum', 'super_admin'])
    ->post('support/accounts/{account}/enter', [SupportAccessController::class, 'enter']);
Route::middleware(['auth:sanctum', 'super_admin'])
    ->post('support/exit', [SupportAccessController::class, 'exit']);
```

Tenant controllers: `authorize()` via policy + `PlanLimits::assertCanCreate` no `store`.

- [ ] **Step 4: Rodar e formatar**

Run: `php artisan test --compact && vendor/bin/pint --dirty --format agent`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers routes/api.php tests/Feature/Tenancy/
git commit -m "feat(backend): controllers tenant, admin e suporte"
```

### Task 7: Frontend — API client + store auth

**Files:**
- Create: `frontend/app/plugins/api.ts`, `frontend/app/composables/useAuth.ts`
- Modify: `frontend/nuxt.config.ts`

**Interfaces:**
- Consumes: Task 4 (endpoints)
- Produces: `useAuth()` → `{ user, isSuperAdmin, accounts, currentAccount, login, register, logout, switchAccount, enterSupport, exitSupport }`; `$fetch` com sessão/CSRF

- [ ] **Step 1: Plugin + config**

```ts
// nuxt.config.ts (acrescentar)
runtimeConfig: {
  public: { apiUrl: process.env.NUXT_PUBLIC_API_URL || 'http://localhost:8000' }
}
```

```ts
// app/plugins/api.ts
export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  const api = $fetch.create({
    baseURL: `${config.public.apiUrl}/api`,
    credentials: 'include',
    headers: { Accept: 'application/json' },
    async onRequest({ options }) {
      const token = useCookie('XSRF-TOKEN')
      if (token.value) {
        options.headers.set('X-XSRF-TOKEN', decodeURIComponent(token.value))
      }
    }
  })
  return { provide: { api } }
})
```

```ts
// app/composables/useAuth.ts (estado useState + métodos chamando $fetch:
// login: GET csrf-cookie → POST /login; register: POST /register;
// fetchMe: GET /me; switchAccount/enterSupport/exitSupport: POSTs respectivos + fetchMe)
```

- [ ] **Step 2: Verificar manual** — subir backend + frontend, login via UI, `useAuth().user` preenchido; sem sessão, `/me` → 401 no network tab.

- [ ] **Step 3: Commit**

```bash
git add app/plugins/api.ts app/composables/useAuth.ts nuxt.config.ts
git commit -m "feat(frontend): client api com sessao e store auth"
```

### Task 8: Guards + wiring login/onboarding + layout admin

**Files:**
- Create: `frontend/app/middleware/auth.ts`, `super-admin.ts`, `frontend/app/layouts/admin.vue`
- Modify: `frontend/app/pages/login.vue`, `onboarding.vue`

**Interfaces:**
- Consumes: Task 7
- Produces: rotas protegidas; `/admin/**` isolado do layout dashboard

- [ ] **Step 1: Middlewares + layout**

```ts
// middleware/auth.ts
export default defineNuxtRouteMiddleware(async () => {
  const { fetchMe, user } = useAuth()
  if (!user.value) { await fetchMe().catch(() => {}) }
  if (!user.value) { return navigateTo('/login') }
})
// middleware/super-admin.ts: exige useAuth().isSuperAdmin, senão navigateTo('/')
```

`login.vue`/`onboarding.vue`: `definePageMeta({ layout: 'auth', middleware: 'guest' })`? Não há middleware guest — redirecionar logados no `setup` via `if (user.value) navigateTo('/')`. Simplificar: após login/onboarding com sucesso, `navigateTo('/')`; se já logado e abre `/login`, redireciona a `/`.

- [ ] **Step 2: Verificar** — sem sessão, `/` → `/login`; comum em `/admin` → `/`; onboarding primeira execução cria super_admin (confere `is_super_admin` em `/me`).

- [ ] **Step 3: Commit**

```bash
git add app/middleware/ app/layouts/admin.vue app/pages/login.vue app/pages/onboarding.vue
git commit -m "feat(frontend): guards, layout admin e wiring auth"
```

### Task 9: Painel `/admin` + switcher + banner

**Files:**
- Create: 6 páginas em `frontend/app/pages/admin/`
- Modify: header do layout default (switcher condicional)

**Interfaces:**
- Consumes: Tasks 6–8
- Produces: gestão visual completa

- [ ] **Step 1: Páginas** — `index.vue` (resumo), `contas.vue` (tabela + suspender/ativar + criar), `planos.vue` (editar limites), `assinaturas.vue` (trocar plano/status), `usuarios.vue` (visão global), `suporte.vue` (buscar conta → entrar + tabela de logs). Componentes Nuxt UI (UTable, UModal, UForm) com cores semânticas.
- [ ] **Step 2: Switcher + banner** — switcher no header visível só com `isSuperAdmin` (conta própria como item normal); banner fixo em suporte (`currentAccount.id` ∉ minhas contas) com botão Sair → `exitSupport()`.
- [ ] **Step 3: Verificar manual** — fluxo: entrar em conta → banner aparece → editar dado → log em `/admin/suporte` → sair → banner some.
- [ ] **Step 4: Commit**

```bash
git add app/pages/admin/ app/layouts/default.vue app/components/
git commit -m "feat(frontend): painel admin, switcher e banner de suporte"
```

### Task 10: Lint pendente + verificação final

**Files:**
- Modify: `frontend/app/pages/onboarding.vue` (25 erros `vue/singleline-html-element-content-newline` + `max-attributes-per-line`)

**Interfaces:**
- Consumes: todas
- Produces: base verde

- [ ] **Step 1: Autofix + revisão**

Run: `pnpm eslint app/pages/onboarding.vue --fix && pnpm eslint app/pages/login.vue app/pages/onboarding.vue app/layouts/ app/pages/admin/ app/composables/useAuth.ts app/plugins/api.ts`
Expected: zero erros

- [ ] **Step 2: Verificação completa**

Run: `composer test` (em `backend/`), `pnpm typecheck` (em `frontend/`), `docker compose config` (na raiz)
Expected: tudo verde

- [ ] **Step 3: Roteiro manual ponta a ponta** — onboarding → `/admin` cria conta → switch → escrita → log → suspensão → bloqueio. Marcar cada passo.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "chore: lint final e verificacao multi-tenant"
```

---

## Self-Review

- **Cobertura da spec:** auth (Tasks 1,4,7,8) ✓; accounts (3,6) ✓; isolation (5,6) ✓; subscriptions (3,5,6,9) ✓; support-access (6,9) ✓; admin-panel (6,8,9) ✓.
- **Placeholders:** nenhum TBD/TODO de implementação (os TODOs de `/api/*` futuros viraram Non-Goals no design). Códigos acima são reais e consistentes entre tasks (`CurrentTenant`, `PlanLimits::assertCanCreate`, aliases `tenant`/`super_admin`, `useAuth()`).
- **Consistência:** nomes de tabelas/models/rotas idênticos em todas as tasks; limites copiados verbatim das decisões.
