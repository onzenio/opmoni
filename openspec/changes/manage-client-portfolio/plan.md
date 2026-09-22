# Client Portfolio Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar a carteira tenant de clientes CPF/CNPJ em `/customers`, com CRUD real, consulta CNPJ.ws, regime tributário híbrido, certificado A1 criptografado e controle da procuração e-CAC.

**Architecture:** O `Client` permanece o aggregate root tenant identificado por `(account_id, tax_id)`; certificado e procuração têm tabelas e ciclos de vida próprios. Controllers finos usam Form Requests, API Resources e services; o Nuxt consome somente a API Laravel por um composable tipado e divide tabela, formulário e overlays por responsabilidade.

**Tech Stack:** PHP 8.4 / Laravel 13.32.0 / Sanctum 4.3.3 / OpenSSL / PHPUnit 12.5.35 / Pint 1.32.1; Nuxt 4.5.2 / Vue 3.5.43 / @nuxt/ui 4.11.1 / Zod 4.6.5 / TypeScript 6.0.3 / pnpm 12.5.1.

**Spec:** `openspec/changes/manage-client-portfolio/` — `proposal.md`, `specs/tenant/{client-portfolio,cnpj-lookup,client-fiscal-access}/spec.md`, `design.md` e `tasks.md`.

## Global Constraints

- Ler `backend/AGENTS.md`, `AGENTS.md` e qualquer `.ai/rules` aplicável antes de editar; regras locais prevalecem sobre este plano.
- Preservar todas as mudanças não commitadas e não editar páginas administrativas para “limpar” falhas não relacionadas.
- Backend usa Laravel 13.32.0 e PHP 8.4; criar classes/migrations/tests com `php artisan make:* --no-interaction`.
- Backend roda em `backend/`: teste focado com `php artisan test --compact --filter=Nome`, suíte com `composer test`, estilo com `vendor/bin/pint --dirty --format agent`.
- Frontend roda em `frontend/` e usa somente `pnpm`; verificar com `pnpm lint`, `pnpm typecheck` e `pnpm build`.
- Não adicionar dependências Composer ou pnpm; usar `Http`, `Cache`, `RateLimiter`, `Crypt`, `Storage` e OpenSSL já disponíveis.
- CPF/CNPJ é único dentro do Account e pode se repetir em Accounts distintos; toda rota de recurso permanece sob `auth:sanctum` + `tenant`.
- `admin` e `operador` escrevem; `user` somente lê; super_admin em suporte mantém a auditoria existente.
- Nunca persistir/logar senha do PFX/P12, conteúdo em claro, material criptográfico, caminho interno ou CPF completo em auditoria.
- A consulta pública CNPJ.ws aceita no máximo três chamadas por minuto; cache normalizado dura 24 horas e chamadas reais não são feitas em testes.
- Campos de identidade adicionados a clientes existentes são nullable para migração; toda nova criação exige identidade válida na aplicação.
- Textos da interface são em português e usam cores semânticas do Nuxt UI, sem classes de paleta crua.
- Cada commit deve conter somente arquivos da task; antes do commit executar `git diff --cached --check` e revisar `git diff --cached`.

---

## File Structure

### Backend — criar

- `app/Enums/ClientPersonType.php` — `Company|Individual`.
- `app/Enums/ClientStatus.php` — `Active|Inactive`.
- `app/Enums/TaxRegime.php` — `Mei|SimpleNational|PresumedProfit|ActualProfit|Other|NotApplicable`.
- `app/Enums/DeadlineStatus.php` — `Missing|Valid|Expiring|Expired`.
- `app/Rules/ValidCpf.php`, `app/Rules/ValidCnpj.php` — validação de dígitos verificadores.
- `app/Models/ClientCertificate.php`, `app/Models/ClientEcacPowerOfAttorney.php` — metadados tenant dos acessos fiscais.
- `app/Services/BrazilianTaxId.php` — normalização e algoritmos CPF/CNPJ.
- `app/Services/CnpjLookupException.php`, `app/Services/CnpjWsLookup.php` — contrato normalizado e erros estáveis do provedor.
- `app/Services/ClientManager.php` — criação/restauração, atualização, refresh oficial e exclusão segura.
- `app/Services/DeadlineState.php` — cálculo único dos quatro estados de validade.
- `app/Services/ClientCertificateVault.php` — parse, criptografia, armazenamento, substituição e remoção.
- `app/Http/Requests/Tenant/{IndexClientRequest,StoreClientRequest,UpdateClientRequest,LookupClientCnpjRequest,StoreClientCertificateRequest,UpsertClientEcacPowerOfAttorneyRequest}.php`.
- `app/Http/Resources/{ClientResource,ClientCertificateResource,ClientEcacPowerOfAttorneyResource}.php`.
- `app/Http/Controllers/Tenant/{ClientCnpjLookupController,ClientCnpjRefreshController,ClientCertificateController,ClientEcacPowerOfAttorneyController}.php`.
- `database/migrations/2026_09_22_100001_add_portfolio_fields_to_clients_table.php`.
- `database/migrations/2026_09_22_100002_create_client_certificates_table.php`.
- `database/migrations/2026_09_22_100003_create_client_ecac_powers_of_attorney_table.php`.
- `database/factories/{ClientCertificateFactory,ClientEcacPowerOfAttorneyFactory}.php`.
- `tests/Feature/Tenancy/{ClientCrudTest,ClientCnpjLookupTest,ClientCertificateTest,ClientEcacPowerOfAttorneyTest}.php`.
- `tests/Unit/{BrazilianTaxIdTest,DeadlineStateTest}.php`.

### Backend — modificar

- `app/Models/Client.php` — casts, soft delete, relações e scopes.
- `app/Models/Account.php` — relações dos novos recursos quando necessárias.
- `database/factories/ClientFactory.php` — estados `company()` e `individual()`.
- `app/Http/Controllers/Tenant/ClientController.php` — requests/resources e delegação.
- `routes/api.php` — endpoints de consulta, refresh, certificado e procuração.
- `config/filesystems.php` — disco privado `certificates` sem serving.
- `tests/Feature/Tenancy/{RolesTest,SubscriptionsTest,SupportAccessTest}.php` — payload completo de cliente.

### Frontend — criar

- `app/types/client.ts` — contratos da API e enums union.
- `app/composables/useClients.ts` — todas as operações da carteira.
- `app/components/customers/ClientFormSlideover.vue` — cadastro/edição por etapas.
- `app/components/customers/ClientDetailsSlideover.vue` — leitura cadastral/fiscal.
- `app/components/customers/CertificateModal.vue` — upload/substituição/remoção A1.
- `app/components/customers/EcacPowerOfAttorneyModal.vue` — vigência da procuração.
- `app/components/customers/ClientDeleteModal.vue` — confirmação da exclusão lógica.

### Frontend — modificar/remover

- `app/pages/customers.vue` — tabela real, filtros, paginação e ações.
- `app/composables/useAuth.ts` — `currentRole` e `canManageClients`.
- `app/utils/index.ts` — formatação de CPF/CNPJ e datas.
- Remover `app/components/customers/AddModal.vue`, `app/components/customers/DeleteModal.vue` e `server/api/customers.ts` somente após a API real estar conectada.

### Interfaces compartilhadas

```php
// CnpjWsLookup
public function lookup(string $cnpj): array;

// ClientManager
public function create(Account $account, array $data): Client;
public function update(Client $client, array $data): Client;
public function refreshCompany(Client $client): Client;
public function delete(Client $client): void;

// ClientCertificateVault
public function replace(Client $client, UploadedFile $file, string $password): ClientCertificate;
public function remove(Client $client): void;

// DeadlineState
public function for(?CarbonInterface $expiresAt): DeadlineStatus;
```

```ts
// useClients
list(params: ClientListParams): Promise<PaginatedResponse<Client>>
lookupCnpj(cnpj: string): Promise<CnpjPreview>
create(payload: ClientWritePayload): Promise<Client>
update(id: number, payload: ClientUpdatePayload): Promise<Client>
refreshPreview(id: number): Promise<CnpjRefreshPreview>
refreshCnpj(id: number): Promise<Client>
remove(id: number): Promise<void>
uploadCertificate(id: number, file: File, password: string): Promise<Client>
removeCertificate(id: number): Promise<void>
upsertPowerOfAttorney(id: number, payload: PowerOfAttorneyPayload): Promise<Client>
removePowerOfAttorney(id: number): Promise<void>
```

---

### Task 1: Baseline, enums and Brazilian document validation

**Files:**
- Create: `backend/app/Enums/{ClientPersonType,ClientStatus,TaxRegime,DeadlineStatus}.php`
- Create: `backend/app/Services/BrazilianTaxId.php`
- Create: `backend/app/Rules/{ValidCpf,ValidCnpj}.php`
- Test: `backend/tests/Unit/BrazilianTaxIdTest.php`

**Interfaces:**
- Consumes: somente PHP/Laravel instalado.
- Produces: `BrazilianTaxId::normalize(string): string`, `isValidCpf(string): bool`, `isValidCnpj(string): bool`; enums usados por requests, models e frontend.

- [ ] **Step 1: Capturar o baseline sem alterar arquivos existentes**

Run na raiz:

```bash
git status --short
git diff -- frontend/app/pages/customers.vue backend/app/Models/Client.php backend/app/Http/Controllers/Tenant/ClientController.php
```

Run em `backend/`:

```bash
php artisan test --compact --filter='(RolesTest|SubscriptionsTest|SupportAccessTest)'
```

Run em `frontend/`:

```bash
pnpm exec eslint app/pages/customers.vue app/components/customers app/composables/useAuth.ts
pnpm typecheck
```

Expected: registrar os resultados atuais; não corrigir arquivos fora do escopo nesta task.

- [ ] **Step 2: Gerar enums, rules, service e teste**

Run em `backend/`:

```bash
php artisan make:enum ClientPersonType --no-interaction
php artisan make:enum ClientStatus --no-interaction
php artisan make:enum TaxRegime --no-interaction
php artisan make:enum DeadlineStatus --no-interaction
php artisan make:class Services/BrazilianTaxId --no-interaction
php artisan make:rule ValidCpf --no-interaction
php artisan make:rule ValidCnpj --no-interaction
php artisan make:test --phpunit --unit BrazilianTaxIdTest --no-interaction
```

- [ ] **Step 3: Escrever testes RED para normalização e dígitos verificadores**

```php
// tests/Unit/BrazilianTaxIdTest.php
public function test_normalizes_punctuation(): void
{
    $service = new BrazilianTaxId;

    $this->assertSame('27865757000102', $service->normalize('27.865.757/0001-02'));
    $this->assertSame('52998224725', $service->normalize('529.982.247-25'));
}

public function test_validates_cpf_and_rejects_repeated_digits(): void
{
    $service = new BrazilianTaxId;

    $this->assertTrue($service->isValidCpf('52998224725'));
    $this->assertFalse($service->isValidCpf('52998224724'));
    $this->assertFalse($service->isValidCpf('11111111111'));
}

public function test_validates_cnpj_and_rejects_repeated_digits(): void
{
    $service = new BrazilianTaxId;

    $this->assertTrue($service->isValidCnpj('27865757000102'));
    $this->assertFalse($service->isValidCnpj('27865757000103'));
    $this->assertFalse($service->isValidCnpj('00000000000000'));
}
```

- [ ] **Step 4: Rodar os testes e confirmar RED**

Run:

```bash
php artisan test --compact tests/Unit/BrazilianTaxIdTest.php
```

Expected: FAIL porque os métodos ainda não existem.

- [ ] **Step 5: Implementar enums e algoritmo único de módulo 11**

```php
// app/Enums/ClientPersonType.php
enum ClientPersonType: string
{
    case Company = 'company';
    case Individual = 'individual';
}

// app/Enums/ClientStatus.php
enum ClientStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

// app/Enums/TaxRegime.php
enum TaxRegime: string
{
    case Mei = 'mei';
    case SimpleNational = 'simple_national';
    case PresumedProfit = 'presumed_profit';
    case ActualProfit = 'actual_profit';
    case Other = 'other';
    case NotApplicable = 'not_applicable';
}

// app/Enums/DeadlineStatus.php
enum DeadlineStatus: string
{
    case Missing = 'missing';
    case Valid = 'valid';
    case Expiring = 'expiring';
    case Expired = 'expired';
}
```

```php
// app/Services/BrazilianTaxId.php
final class BrazilianTaxId
{
    public function normalize(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    public function isValidCpf(string $value): bool
    {
        $digits = $this->normalize($value);

        return strlen($digits) === 11
            && ! preg_match('/^(\d)\1+$/', $digits)
            && $this->digit($digits, 9, range(10, 2)) === (int) $digits[9]
            && $this->digit($digits, 10, range(11, 2)) === (int) $digits[10];
    }

    public function isValidCnpj(string $value): bool
    {
        $digits = $this->normalize($value);

        return strlen($digits) === 14
            && ! preg_match('/^(\d)\1+$/', $digits)
            && $this->digit($digits, 12, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]) === (int) $digits[12]
            && $this->digit($digits, 13, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]) === (int) $digits[13];
    }

    /** @param list<int> $weights */
    private function digit(string $digits, int $length, array $weights): int
    {
        $sum = 0;

        for ($index = 0; $index < $length; $index++) {
            $sum += (int) $digits[$index] * $weights[$index];
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
```

As Rules não normalizam silenciosamente — os Form Requests fazem isso em `prepareForValidation()`:

```php
final class ValidCpf implements ValidationRule
{
    public function __construct(private readonly BrazilianTaxId $taxId = new BrazilianTaxId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->taxId->isValidCpf($value)) {
            $fail('O CPF informado é inválido.');
        }
    }
}

final class ValidCnpj implements ValidationRule
{
    public function __construct(private readonly BrazilianTaxId $taxId = new BrazilianTaxId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->taxId->isValidCnpj($value)) {
            $fail('O CNPJ informado é inválido.');
        }
    }
}
```

- [ ] **Step 6: Verificar GREEN e formatar**

Run:

```bash
php artisan test --compact tests/Unit/BrazilianTaxIdTest.php
vendor/bin/pint --dirty --format agent
```

Expected: 3 testes PASS e Pint sem erro.

- [ ] **Step 7: Commit isolado**

```bash
git add app/Enums app/Services/BrazilianTaxId.php app/Rules tests/Unit/BrazilianTaxIdTest.php
git diff --cached --check
git commit -m "feat(backend): validate Brazilian client documents"
```

### Task 2: Client portfolio schema and model

**Files:**
- Create: `backend/database/migrations/2026_09_22_100001_add_portfolio_fields_to_clients_table.php`
- Modify: `backend/app/Models/Client.php`
- Modify: `backend/database/factories/ClientFactory.php`
- Test: `backend/tests/Feature/Tenancy/ClientCrudTest.php`

**Interfaces:**
- Consumes: enums e `BrazilianTaxId` da Task 1.
- Produces: Client com soft delete, casts, scopes e estados de factory; schema usado por toda API.

- [ ] **Step 1: Gerar migration e teste**

```bash
php artisan make:migration add_portfolio_fields_to_clients_table --table=clients --no-interaction
mv database/migrations/*_add_portfolio_fields_to_clients_table.php database/migrations/2026_09_22_100001_add_portfolio_fields_to_clients_table.php
php artisan make:test --phpunit ClientCrudTest --no-interaction
```

- [ ] **Step 2: Escrever os primeiros testes RED de identidade tenant**

```php
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
```

- [ ] **Step 3: Confirmar RED**

```bash
php artisan test --compact tests/Feature/Tenancy/ClientCrudTest.php
```

Expected: FAIL por colunas/estados/SoftDeletes ausentes.

- [ ] **Step 4: Implementar migration reversível**

```php
Schema::table('clients', function (Blueprint $table): void {
    $table->string('person_type')->nullable()->after('account_id');
    $table->string('tax_id', 14)->nullable()->after('person_type');
    $table->string('trade_name')->nullable()->after('name');
    $table->string('status')->default('active')->after('trade_name');
    $table->string('tax_regime')->nullable()->after('status');
    $table->string('registration_status')->nullable();
    $table->date('registration_status_date')->nullable();
    $table->date('opened_at')->nullable();
    $table->string('company_size')->nullable();
    $table->string('legal_nature')->nullable();
    $table->string('primary_activity_code', 16)->nullable();
    $table->text('primary_activity_description')->nullable();
    $table->string('street_type', 40)->nullable();
    $table->string('street')->nullable();
    $table->string('address_number', 30)->nullable();
    $table->string('address_complement')->nullable();
    $table->string('district')->nullable();
    $table->string('postal_code', 8)->nullable();
    $table->string('city')->nullable();
    $table->string('state', 2)->nullable();
    $table->string('email')->nullable();
    $table->string('phone', 20)->nullable();
    $table->timestamp('source_updated_at')->nullable();
    $table->timestamp('looked_up_at')->nullable();
    $table->softDeletes();
    $table->unique(['account_id', 'tax_id']);
    $table->index(['account_id', 'status']);
    $table->index(['account_id', 'tax_regime']);
});
```

O `down()` remove índices pelo nome e todas as colunas adicionadas. Não preencher CPF/CNPJ fictício em registros legados.

- [ ] **Step 5: Atualizar model e factory**

```php
// Client.php
use SoftDeletes;

protected function casts(): array
{
    return [
        'person_type' => ClientPersonType::class,
        'status' => ClientStatus::class,
        'tax_regime' => TaxRegime::class,
        'registration_status_date' => 'date',
        'opened_at' => 'date',
        'source_updated_at' => 'datetime',
        'looked_up_at' => 'datetime',
    ];
}
```

`#[Fillable]` lista somente os campos da migration e `account_id`. Os scopes agrupam a busca para não furar o escopo tenant:

```php
public function scopeSearch(Builder $query, ?string $term): Builder
{
    return $query->when($term, fn (Builder $query, string $term): Builder => $query->where(
        fn (Builder $query): Builder => $query
            ->where('name', 'like', "%{$term}%")
            ->orWhere('trade_name', 'like', "%{$term}%")
            ->orWhere('tax_id', 'like', '%'.preg_replace('/\D+/', '', $term).'%')
    ));
}

public function scopeWithStatus(Builder $query, ?string $status): Builder
{
    return $query->when($status, fn (Builder $query, string $status): Builder => $query->where('status', $status));
}

public function scopeWithTaxRegime(Builder $query, ?string $regime): Builder
{
    return $query->when($regime, fn (Builder $query, string $regime): Builder => $query->where('tax_regime', $regime));
}
```

```php
// ClientFactory.php
public function company(): static
{
    return $this->state(fn (): array => [
        'person_type' => ClientPersonType::Company,
        'tax_id' => fake()->unique()->numerify('##############'),
        'name' => fake()->company(),
        'status' => ClientStatus::Active,
        'tax_regime' => TaxRegime::PresumedProfit,
    ]);
}

public function individual(): static
{
    return $this->state(fn (): array => [
        'person_type' => ClientPersonType::Individual,
        'tax_id' => fake()->unique()->numerify('###########'),
        'name' => fake()->name(),
        'status' => ClientStatus::Active,
        'tax_regime' => TaxRegime::NotApplicable,
    ]);
}
```

Factories podem usar documentos numericamente únicos sem dígito válido porque persistem direto; testes HTTP sempre usam documentos válidos conhecidos.

- [ ] **Step 6: Verificar migration e testes**

```bash
php artisan migrate --force
php artisan test --compact tests/Feature/Tenancy/ClientCrudTest.php
vendor/bin/pint --dirty --format agent
```

Expected: migration e testes PASS em SQLite; desenvolvimento migra sem perder clientes atuais.

- [ ] **Step 7: Commit isolado**

```bash
git add app/Models/Client.php database/factories/ClientFactory.php database/migrations/2026_09_22_100001_add_portfolio_fields_to_clients_table.php tests/Feature/Tenancy/ClientCrudTest.php
git diff --cached --check
git commit -m "feat(backend): model client portfolio data"
```

### Task 3: CNPJ.ws lookup service

**Files:**
- Create: `backend/app/Services/{CnpjLookupException,CnpjWsLookup}.php`
- Test: `backend/tests/Feature/Tenancy/ClientCnpjLookupTest.php`

**Interfaces:**
- Consumes: `BrazilianTaxId::isValidCnpj()`.
- Produces: `CnpjWsLookup::lookup(string): array` com shape normalizado e sem sócios.

- [ ] **Step 1: Gerar classes e teste**

```bash
php artisan make:class Services/CnpjLookupException --no-interaction
php artisan make:class Services/CnpjWsLookup --no-interaction
php artisan make:test --phpunit ClientCnpjLookupTest --no-interaction
```

- [ ] **Step 2: Escrever testes RED do adapter**

O fixture do `Http::fake()` inclui `socios`, mas a asserção abaixo exige somente o allowlist:

```php
Http::fake([
    'publica.cnpj.ws/cnpj/27865757000102' => Http::response([
        'razao_social' => 'GLOBO COMUNICACAO E PARTICIPACOES S/A',
        'porte' => ['descricao' => 'Demais'],
        'natureza_juridica' => ['descricao' => 'Sociedade Anônima Fechada'],
        'socios' => [['cpf_cnpj_socio' => '07561099738', 'nome' => 'Dado excluído']],
        'simples' => ['mei' => 'Não', 'simples' => 'Não'],
        'estabelecimento' => [
            'cnpj' => '27865757000102',
            'nome_fantasia' => 'GLOBOPLAY',
            'situacao_cadastral' => 'Ativa',
            'data_situacao_cadastral' => '2005-11-03',
            'data_inicio_atividade' => '1986-01-31',
            'tipo_logradouro' => 'RUA',
            'logradouro' => 'LOPES QUINTAS',
            'numero' => '303',
            'complemento' => null,
            'bairro' => 'JARDIM BOTANICO',
            'cep' => '22460901',
            'ddd1' => '21',
            'telefone1' => '21554551',
            'email' => 'fiscal@example.com',
            'atualizado_em' => '2026-09-12T03:00:00.000Z',
            'atividade_principal' => ['id' => '6021700', 'descricao' => 'Atividades de televisão aberta'],
            'estado' => ['sigla' => 'RJ'],
            'cidade' => ['nome' => 'Rio de Janeiro'],
        ],
    ]),
]);

$result = resolve(CnpjWsLookup::class)->lookup('27.865.757/0001-02');

$this->assertSame('27865757000102', $result['tax_id']);
$this->assertSame('GLOBO COMUNICACAO E PARTICIPACOES S/A', $result['name']);
$this->assertArrayNotHasKey('socios', $result);
Http::assertSentCount(1);
```

Adicionar métodos separados para cache (segunda chamada mantém `assertSentCount(1)`), 404→404, 429→429, timeout/5xx→503 e quarta consulta distinta no minuto→429.

- [ ] **Step 3: Confirmar RED**

```bash
php artisan test --compact tests/Feature/Tenancy/ClientCnpjLookupTest.php
```

Expected: FAIL porque adapter e exceção ainda não têm comportamento.

- [ ] **Step 4: Implementar contrato de erro e lookup**

```php
final class CnpjLookupException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status)
    {
        parent::__construct($message);
    }
}
```

```php
final class CnpjWsLookup
{
    private const CACHE_TTL_SECONDS = 86400;
    private const RATE_KEY = 'cnpj-ws:public';

    public function __construct(private BrazilianTaxId $taxId) {}

    /** @return array<string, mixed> */
    public function lookup(string $cnpj): array
    {
        $normalized = $this->taxId->normalize($cnpj);

        if (! $this->taxId->isValidCnpj($normalized)) {
            throw new CnpjLookupException('CNPJ inválido.', 422);
        }

        return Cache::remember("cnpj-ws:{$normalized}", self::CACHE_TTL_SECONDS, function () use ($normalized): array {
            $result = null;
            $allowed = RateLimiter::attempt(self::RATE_KEY, 3, function () use ($normalized, &$result): bool {
                $result = $this->request($normalized);
                return true;
            }, 60);

            if (! $allowed || ! is_array($result)) {
                throw new CnpjLookupException('Limite temporário de consultas atingido. Tente novamente em um minuto.', 429);
            }

            return $result;
        });
    }
}
```

`request()` não usa `retry()` para não consumir quota adicional:

```php
/** @return array<string, mixed> */
private function request(string $cnpj): array
{
    try {
        $response = Http::acceptJson()->timeout(8)->get("https://publica.cnpj.ws/cnpj/{$cnpj}");
    } catch (ConnectionException) {
        throw new CnpjLookupException('O serviço de consulta de CNPJ está indisponível.', 503);
    }

    if ($response->status() === 404) {
        throw new CnpjLookupException('CNPJ não encontrado.', 404);
    }

    if ($response->status() === 429) {
        throw new CnpjLookupException('O provedor limitou temporariamente as consultas.', 429);
    }

    if ($response->serverError()) {
        throw new CnpjLookupException('O serviço de consulta de CNPJ está indisponível.', 503);
    }

    $response->throw();
    $payload = $response->json();
    $establishment = data_get($payload, 'estabelecimento', []);

    return [
        'tax_id' => (string) data_get($establishment, 'cnpj'),
        'name' => (string) data_get($payload, 'razao_social'),
        'trade_name' => data_get($establishment, 'nome_fantasia'),
        'registration_status' => data_get($establishment, 'situacao_cadastral'),
        'registration_status_date' => data_get($establishment, 'data_situacao_cadastral'),
        'opened_at' => data_get($establishment, 'data_inicio_atividade'),
        'company_size' => data_get($payload, 'porte.descricao'),
        'legal_nature' => data_get($payload, 'natureza_juridica.descricao'),
        'primary_activity_code' => data_get($establishment, 'atividade_principal.id'),
        'primary_activity_description' => data_get($establishment, 'atividade_principal.descricao'),
        'street_type' => data_get($establishment, 'tipo_logradouro'),
        'street' => data_get($establishment, 'logradouro'),
        'address_number' => data_get($establishment, 'numero'),
        'address_complement' => data_get($establishment, 'complemento'),
        'district' => data_get($establishment, 'bairro'),
        'postal_code' => data_get($establishment, 'cep'),
        'city' => data_get($establishment, 'cidade.nome'),
        'state' => data_get($establishment, 'estado.sigla'),
        'email' => data_get($establishment, 'email'),
        'phone' => trim((string) data_get($establishment, 'ddd1').(string) data_get($establishment, 'telefone1')) ?: null,
        'mei' => data_get($payload, 'simples.mei') === 'Sim',
        'simple_national' => data_get($payload, 'simples.simples') === 'Sim',
        'source_updated_at' => data_get($establishment, 'atualizado_em'),
        'looked_up_at' => now()->toISOString(),
    ];
}
```

- [ ] **Step 5: Verificar GREEN e ausência de rede real**

```bash
php artisan test --compact tests/Feature/Tenancy/ClientCnpjLookupTest.php
vendor/bin/pint --dirty --format agent
```

Expected: todos os casos PASS e cada teste usa `Http::preventStrayRequests()`.

- [ ] **Step 6: Commit isolado**

```bash
git add app/Services/CnpjLookupException.php app/Services/CnpjWsLookup.php tests/Feature/Tenancy/ClientCnpjLookupTest.php
git diff --cached --check
git commit -m "feat(backend): integrate public CNPJ lookup"
```

### Task 4: Tenant client API, Resources and confirmed CNPJ refresh

**Files:**
- Create: `backend/app/Services/ClientManager.php`
- Create: `backend/app/Http/Requests/Tenant/{IndexClientRequest,StoreClientRequest,UpdateClientRequest,LookupClientCnpjRequest}.php`
- Create: `backend/app/Http/Resources/ClientResource.php`
- Create: `backend/app/Http/Controllers/Tenant/{ClientCnpjLookupController,ClientCnpjRefreshController}.php`
- Modify: `backend/app/Http/Controllers/Tenant/ClientController.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Tenancy/{ClientCrudTest,ClientCnpjLookupTest}.php`
- Modify tests: `backend/tests/Feature/Tenancy/{RolesTest,SubscriptionsTest,SupportAccessTest}.php`

**Interfaces:**
- Consumes: Client schema, document rules e `CnpjWsLookup`.
- Produces: CRUD paginado real, lookup preview e refresh confirmado; JSON estável para o Nuxt.

- [ ] **Step 1: Gerar requests, resource, controllers e service**

```bash
php artisan make:class Services/ClientManager --no-interaction
php artisan make:request Tenant/IndexClientRequest --no-interaction
php artisan make:request Tenant/StoreClientRequest --no-interaction
php artisan make:request Tenant/UpdateClientRequest --no-interaction
php artisan make:request Tenant/LookupClientCnpjRequest --no-interaction
php artisan make:resource ClientResource --no-interaction
php artisan make:controller Tenant/ClientCnpjLookupController --invokable --no-interaction
php artisan make:controller Tenant/ClientCnpjRefreshController --no-interaction
```

- [ ] **Step 2: Expandir os testes RED do contrato HTTP**

Adicionar casos separados para:

```php
public function test_operador_creates_company_from_server_lookup(): void
{
    Http::fake(['publica.cnpj.ws/*' => Http::response($this->companyFixture())]);
    $account = Account::factory()->create();
    $user = $this->memberOf($account, 'operador');

    $this->actingAs($user, 'sanctum')->postJson('/api/clients', [
        'person_type' => 'company',
        'tax_id' => '27.865.757/0001-02',
        'status' => 'active',
        'tax_regime' => 'actual_profit',
        'email' => 'contato@example.com',
        'phone' => '2121554551',
    ])->assertCreated()
        ->assertJsonPath('data.tax_id', '27865757000102')
        ->assertJsonPath('data.name', 'GLOBO COMUNICACAO E PARTICIPACOES S/A');
}

public function test_user_can_read_but_cannot_create_update_or_delete(): void
{
    $account = Account::factory()->create();
    $user = $this->memberOf($account, 'user');
    $client = Client::factory()->company()->create(['account_id' => $account->getKey()]);

    $this->actingAs($user, 'sanctum')->getJson('/api/clients')->assertOk();
    $this->actingAs($user, 'sanctum')->postJson('/api/clients', [])->assertForbidden();
    $this->actingAs($user, 'sanctum')->patchJson("/api/clients/{$client->getKey()}", ['status' => 'inactive'])->assertForbidden();
    $this->actingAs($user, 'sanctum')->deleteJson("/api/clients/{$client->getKey()}")->assertForbidden();
}
```

Também cobrir: CPF manual válido, CPF/CNPJ inválido 422, documento duplicado 422, mesmo documento em outro Account, paginação, busca, filtros, sort allowlist, Account alheio 404, limite do plano, soft delete, restauração e refresh preview sem mutação.

- [ ] **Step 3: Confirmar RED focado**

```bash
php artisan test --compact tests/Feature/Tenancy/ClientCrudTest.php tests/Feature/Tenancy/ClientCnpjLookupTest.php
```

Expected: FAIL por requests/routes/resources ausentes.

- [ ] **Step 4: Implementar normalização e validação condicional nos Form Requests**

`StoreClientRequest::prepareForValidation()` substitui `tax_id`, `postal_code` e `phone` por dígitos. `authorize()` usa `Gate::allows('create', Client::class)`.

```php
public function rules(): array
{
    $company = $this->input('person_type') === ClientPersonType::Company->value;

    return [
        'person_type' => ['required', Rule::enum(ClientPersonType::class)],
        'tax_id' => ['required', $company ? 'size:14' : 'size:11', $company ? new ValidCnpj : new ValidCpf],
        'name' => [Rule::requiredIf(! $company), 'nullable', 'string', 'max:255'],
        'status' => ['required', Rule::enum(ClientStatus::class)],
        'tax_regime' => ['required', Rule::enum(TaxRegime::class)],
        'email' => ['nullable', 'email', 'max:255'],
        'phone' => ['nullable', 'string', 'max:20'],
        'street_type' => [Rule::prohibitedIf($company), 'nullable', 'string', 'max:40'],
        'street' => [Rule::prohibitedIf($company), 'nullable', 'string', 'max:255'],
        'address_number' => [Rule::prohibitedIf($company), 'nullable', 'string', 'max:30'],
        'address_complement' => [Rule::prohibitedIf($company), 'nullable', 'string', 'max:255'],
        'district' => [Rule::prohibitedIf($company), 'nullable', 'string', 'max:255'],
        'postal_code' => [Rule::prohibitedIf($company), 'nullable', 'digits:8'],
        'city' => [Rule::prohibitedIf($company), 'nullable', 'string', 'max:255'],
        'state' => [Rule::prohibitedIf($company), 'nullable', 'string', 'size:2'],
    ];
}
```

Adicionar `after()` somente para impor que CPF aceita `not_applicable`. `ClientManager::companyAttributes()` confronta o regime com o lookup: MEI/Simples sobrescreve o valor recebido; fora deles aceita somente `presumed_profit|actual_profit|other` e lança `ValidationException` nos demais casos. `UpdateClientRequest` proíbe alterar `person_type` e `tax_id`; para CNPJ permite apenas status, regime manual, email e telefone. `IndexClientRequest` aceita `q`, `status`, `tax_regime`, `deadline_status`, `sort`, `direction`, `page`, `per_page` com `per_page` entre 1 e 100.

- [ ] **Step 5: Implementar `ClientManager` com transações e restauração**

```php
public function create(Account $account, array $data): Client
{
    PlanLimits::assertCanCreate($account, 'clients');

    return DB::transaction(function () use ($account, $data): Client {
        $existing = Client::withoutGlobalScopes()
            ->withTrashed()
            ->where('account_id', $account->getKey())
            ->where('tax_id', $data['tax_id'])
            ->lockForUpdate()
            ->first();

        $attributes = $data['person_type'] === ClientPersonType::Company->value
            ? $this->companyAttributes($data)
            : $this->individualAttributes($data);

        if ($existing !== null && ! $existing->trashed()) {
            throw ValidationException::withMessages(['tax_id' => 'Este CPF/CNPJ já está cadastrado nesta carteira.']);
        }

        if ($existing !== null) {
            $existing->restore();
            $existing->fill($attributes)->save();
            return $existing->refresh();
        }

        return $account->clients()->create($attributes);
    });
}
```

`companyAttributes()` chama `CnpjWsLookup::lookup()`, copia somente campos oficiais normalizados e mantém email/phone enviados como contatos locais quando presentes. `update()` atualiza apenas o validated allowlist. `refreshCompany()` rejeita CPF, consulta o CNPJ salvo e atualiza somente campos oficiais/source timestamps. `delete()` será completado na Task 7 para limpar certificado antes do soft delete.

- [ ] **Step 6: Implementar Resource e controllers finos**

```php
// ClientResource::toArray()
return [
    'id' => $this->getKey(),
    'person_type' => $this->person_type?->value,
    'tax_id' => $this->tax_id,
    'name' => $this->name,
    'trade_name' => $this->trade_name,
    'status' => $this->status?->value,
    'tax_regime' => $this->tax_regime?->value,
    'registration_status' => $this->registration_status,
    'registration_status_date' => $this->registration_status_date?->toDateString(),
    'opened_at' => $this->opened_at?->toDateString(),
    'company_size' => $this->company_size,
    'legal_nature' => $this->legal_nature,
    'primary_activity' => [
        'code' => $this->primary_activity_code,
        'description' => $this->primary_activity_description,
    ],
    'address' => [
        'street_type' => $this->street_type,
        'street' => $this->street,
        'number' => $this->address_number,
        'complement' => $this->address_complement,
        'district' => $this->district,
        'postal_code' => $this->postal_code,
        'city' => $this->city,
        'state' => $this->state,
    ],
    'email' => $this->email,
    'phone' => $this->phone,
    'source_updated_at' => $this->source_updated_at?->toISOString(),
    'looked_up_at' => $this->looked_up_at?->toISOString(),
    'created_at' => $this->created_at?->toISOString(),
    'updated_at' => $this->updated_at?->toISOString(),
];
```

O index usa sort allowlist `name|tax_id|status|tax_regime|created_at` e, após as Tasks 5–7, mantém as duas relações fiscais no eager load:

```php
$data = $request->validated();
$sort = $data['sort'] ?? 'name';
$direction = $data['direction'] ?? 'asc';
$perPage = $data['per_page'] ?? 15;

$clients = Client::query()
    ->with(['currentCertificate', 'ecacPowerOfAttorney'])
    ->search($data['q'] ?? null)
    ->withStatus($data['status'] ?? null)
    ->withTaxRegime($data['tax_regime'] ?? null)
    ->orderBy($sort, $direction)
    ->paginate($perPage)
    ->withQueryString();

return ClientResource::collection($clients);
```

- [ ] **Step 7: Registrar rotas sem conflito com `{client}`**

```php
Route::post('clients/cnpj-lookup', ClientCnpjLookupController::class);
Route::post('clients/{client}/cnpj-refresh-preview', [ClientCnpjRefreshController::class, 'preview']);
Route::post('clients/{client}/cnpj-refresh', [ClientCnpjRefreshController::class, 'update']);
Route::apiResource('clients', ClientController::class);
```

Lookup captura `CnpjLookupException` e responde `response()->json(['message' => $exception->getMessage()], $exception->status)`. Preview compara somente campos oficiais e retorna `{ data: { current, incoming, changes } }`; update exige policy `update` e chama `refreshCompany()`.

- [ ] **Step 8: Atualizar testes existentes para o novo payload**

Adicionar aos testes existentes um helper de payload manual e substituir cada criação name-only; não afrouxar a validação:

```php
/** @return array<string, string> */
private function individualClientPayload(array $overrides = []): array
{
    return array_replace([
        'person_type' => 'individual',
        'tax_id' => '52998224725',
        'name' => 'Cliente Teste',
        'status' => 'active',
        'tax_regime' => 'not_applicable',
    ], $overrides);
}
```

- [ ] **Step 9: Verificar GREEN e regressões tenant**

```bash
php artisan test --compact tests/Feature/Tenancy/ClientCrudTest.php tests/Feature/Tenancy/ClientCnpjLookupTest.php tests/Feature/Tenancy/RolesTest.php tests/Feature/Tenancy/SubscriptionsTest.php tests/Feature/Tenancy/SupportAccessTest.php
vendor/bin/pint --dirty --format agent
php artisan route:list --path=api/clients
```

Expected: testes PASS; lookup aparece antes do binding genérico; nenhuma resposta contém dados de sócios.

- [ ] **Step 10: Commit isolado**

```bash
git add app/Http/Controllers/Tenant app/Http/Requests/Tenant app/Http/Resources/ClientResource.php app/Services/ClientManager.php routes/api.php tests/Feature/Tenancy
git diff --cached --check
git commit -m "feat(backend): expose tenant client portfolio API"
```

### Task 5: Fiscal-access schema and deadline calculation

**Files:**
- Create: `backend/database/migrations/2026_09_22_100002_create_client_certificates_table.php`
- Create: `backend/database/migrations/2026_09_22_100003_create_client_ecac_powers_of_attorney_table.php`
- Create: `backend/app/Models/{ClientCertificate,ClientEcacPowerOfAttorney}.php`
- Create: `backend/database/factories/{ClientCertificateFactory,ClientEcacPowerOfAttorneyFactory}.php`
- Create: `backend/app/Services/DeadlineState.php`
- Modify: `backend/app/Models/Client.php`
- Test: `backend/tests/Unit/DeadlineStateTest.php`

**Interfaces:**
- Consumes: `DeadlineStatus` e Client tenant.
- Produces: relações `certificateHistory()`, `currentCertificate()`, `ecacPowerOfAttorney()` e cálculo consistente de prazo.

- [ ] **Step 1: Gerar arquivos**

```bash
php artisan make:model ClientCertificate --factory --no-interaction
php artisan make:model ClientEcacPowerOfAttorney --factory --no-interaction
php artisan make:migration create_client_certificates_table --create=client_certificates --no-interaction
mv database/migrations/*_create_client_certificates_table.php database/migrations/2026_09_22_100002_create_client_certificates_table.php
php artisan make:migration create_client_ecac_powers_of_attorney_table --create=client_ecac_powers_of_attorney --no-interaction
mv database/migrations/*_create_client_ecac_powers_of_attorney_table.php database/migrations/2026_09_22_100003_create_client_ecac_powers_of_attorney_table.php
php artisan make:class Services/DeadlineState --no-interaction
php artisan make:test --phpunit --unit DeadlineStateTest --no-interaction
```

- [ ] **Step 2: Escrever teste RED das fronteiras de 30 dias**

```php
public function test_derives_deadline_states_at_boundaries(): void
{
    CarbonImmutable::setTestNow('2026-09-22 12:00:00');
    $service = new DeadlineState;

    $this->assertSame(DeadlineStatus::Missing, $service->for(null));
    $this->assertSame(DeadlineStatus::Expired, $service->for(CarbonImmutable::parse('2026-09-21')));
    $this->assertSame(DeadlineStatus::Expiring, $service->for(CarbonImmutable::parse('2026-09-22')));
    $this->assertSame(DeadlineStatus::Expiring, $service->for(CarbonImmutable::parse('2026-10-22')));
    $this->assertSame(DeadlineStatus::Valid, $service->for(CarbonImmutable::parse('2026-10-23')));

    CarbonImmutable::setTestNow();
}
```

- [ ] **Step 3: Implementar migrations**

```php
Schema::create('client_certificates', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('account_id')->constrained()->cascadeOnDelete();
    $table->foreignId('client_id')->constrained()->cascadeOnDelete();
    $table->string('subject');
    $table->string('serial_number');
    $table->timestamp('valid_from');
    $table->timestamp('valid_until');
    $table->string('original_filename');
    $table->string('storage_path')->nullable();
    $table->string('sha256', 64);
    $table->timestamp('replaced_at')->nullable();
    $table->timestamp('removed_at')->nullable();
    $table->timestamps();
    $table->index(['client_id', 'replaced_at', 'removed_at']);
});

Schema::create('client_ecac_powers_of_attorney', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('account_id')->constrained()->cascadeOnDelete();
    $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
    $table->date('starts_at');
    $table->date('expires_at');
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 4: Implementar models, relações e prazo**

Ambos models usam `BelongsToAccount`, `HasFactory`, `#[Fillable]`, casts de data e relações `client()`/`account()`. No Client:

```php
public function certificateHistory(): HasMany
{
    return $this->hasMany(ClientCertificate::class)->latest('id');
}

public function currentCertificate(): HasOne
{
    return $this->hasOne(ClientCertificate::class)
        ->whereNull('replaced_at')
        ->whereNull('removed_at')
        ->latestOfMany();
}

public function ecacPowerOfAttorney(): HasOne
{
    return $this->hasOne(ClientEcacPowerOfAttorney::class);
}
```

```php
final class DeadlineState
{
    public function for(?CarbonInterface $expiresAt): DeadlineStatus
    {
        if ($expiresAt === null) {
            return DeadlineStatus::Missing;
        }

        $today = now()->startOfDay();
        $date = $expiresAt->copy()->startOfDay();

        if ($date->lt($today)) {
            return DeadlineStatus::Expired;
        }

        return $date->lte($today->copy()->addDays(30))
            ? DeadlineStatus::Expiring
            : DeadlineStatus::Valid;
    }
}
```

- [ ] **Step 5: Verificar schema e unit test**

```bash
php artisan migrate --force
php artisan test --compact tests/Unit/DeadlineStateTest.php
vendor/bin/pint --dirty --format agent
```

Expected: migration e fronteiras PASS.

- [ ] **Step 6: Commit isolado**

```bash
git add app/Models app/Services/DeadlineState.php database/factories database/migrations/2026_09_22_100002_create_client_certificates_table.php database/migrations/2026_09_22_100003_create_client_ecac_powers_of_attorney_table.php tests/Unit/DeadlineStateTest.php
git diff --cached --check
git commit -m "feat(backend): model client fiscal access metadata"
```

### Task 6: Encrypted A1 certificate lifecycle

**Files:**
- Create: `backend/app/Services/ClientCertificateVault.php`
- Create: `backend/app/Http/Requests/Tenant/StoreClientCertificateRequest.php`
- Create: `backend/app/Http/Resources/ClientCertificateResource.php`
- Create: `backend/app/Http/Controllers/Tenant/ClientCertificateController.php`
- Modify: `backend/config/filesystems.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Tenancy/ClientCertificateTest.php`

**Interfaces:**
- Consumes: certificate schema, policy do Client e `DeadlineState`.
- Produces: upload/substituição/remoção sem endpoint de download e sem senha persistida.

- [ ] **Step 1: Gerar service, request, resource, controller e teste**

```bash
php artisan make:class Services/ClientCertificateVault --no-interaction
php artisan make:request Tenant/StoreClientCertificateRequest --no-interaction
php artisan make:resource ClientCertificateResource --no-interaction
php artisan make:controller Tenant/ClientCertificateController --no-interaction
php artisan make:test --phpunit ClientCertificateTest --no-interaction
```

- [ ] **Step 2: Escrever helper de certificado efêmero e testes RED**

No teste, gerar chave/certificado/PFX em memória com `openssl_pkey_new`, `openssl_csr_new`, `openssl_csr_sign` e `openssl_pkcs12_export`; envolver bytes em `UploadedFile::fake()->createWithContent('cliente.pfx', $bytes)`.

Cobrir:

```php
$response = $this->actingAs($operador, 'sanctum')->post(
    "/api/clients/{$client->getKey()}/certificate",
    ['certificate' => $file, 'password' => 'secret'],
    ['Accept' => 'application/json']
);

$response->assertOk()
    ->assertJsonMissingPath('data.certificate.storage_path')
    ->assertJsonMissingPath('data.certificate.password');

$record = ClientCertificate::sole();
$stored = Storage::disk('certificates')->get($record->storage_path);
$this->assertNotSame($bytes, $stored);
$this->assertSame(hash('sha256', $bytes), $record->sha256);
```

Adicionar: senha errada 422 sem arquivo/registro; extensão inválida 422; >2 MB 422; user 403; outro Account 404; substituição remove ciphertext anterior; falha de substituição preserva anterior; remoção limpa arquivo.

- [ ] **Step 3: Confirmar RED**

```bash
php artisan test --compact tests/Feature/Tenancy/ClientCertificateTest.php
```

Expected: FAIL por disco/service/endpoints ausentes.

- [ ] **Step 4: Configurar disco privado sem serving**

```php
'certificates' => [
    'driver' => 'local',
    'root' => storage_path('app/private/certificates'),
    'serve' => false,
    'throw' => true,
    'report' => true,
],
```

Não adicionar link público nem rota de download.

- [ ] **Step 5: Implementar validação e vault**

Request:

```php
return [
    'certificate' => ['required', 'file', 'max:2048', 'extensions:pfx,p12'],
    'password' => ['required', 'string', 'max:1024'],
];
```

Vault, dentro de `replace()`:

```php
$contents = $file->get();
$parsed = [];

if (! openssl_pkcs12_read($contents, $parsed, $password) || ! isset($parsed['cert'])) {
    throw ValidationException::withMessages(['password' => 'Não foi possível abrir o certificado com a senha informada.']);
}

$metadata = openssl_x509_parse($parsed['cert']);
if (! is_array($metadata) || ! isset($metadata['validFrom_time_t'], $metadata['validTo_time_t'])) {
    throw ValidationException::withMessages(['certificate' => 'O certificado não contém metadados válidos.']);
}

$path = sprintf('%d/%d/%s.enc', $client->account_id, $client->getKey(), Str::uuid());
$ciphertext = Crypt::encryptString(base64_encode($contents));
Storage::disk('certificates')->put($path, $ciphertext);
```

Usar `DB::transaction()` e `lockForUpdate()` no Client. Criar o novo registro, marcar o anterior com `replaced_at` e `storage_path = null`, confirmar a transação e só então apagar o arquivo antigo. Em qualquer exceção antes da confirmação, apagar o novo path. Limpar `$password`, `$contents`, `$parsed` e `$ciphertext` em `finally` sem logar valores.

- [ ] **Step 6: Implementar endpoint e resource seguro**

```php
// ClientCertificateResource
return [
    'id' => $this->getKey(),
    'subject' => $this->subject,
    'serial_number' => $this->serial_number,
    'valid_from' => $this->valid_from->toISOString(),
    'valid_until' => $this->valid_until->toISOString(),
    'original_filename' => $this->original_filename,
    'status' => resolve(DeadlineState::class)->for($this->valid_until)->value,
];
```

Controller autoriza `update` no Client, chama vault e retorna `new ClientResource($client->fresh(['currentCertificate', 'ecacPowerOfAttorney']))`. Remoção chama `remove()` e retorna 204.

```php
Route::post('clients/{client}/certificate', [ClientCertificateController::class, 'store']);
Route::delete('clients/{client}/certificate', [ClientCertificateController::class, 'destroy']);
```

- [ ] **Step 7: Verificar GREEN e inspecionar vazamento**

```bash
php artisan test --compact tests/Feature/Tenancy/ClientCertificateTest.php
vendor/bin/pint --dirty --format agent
grep -R "password\|storage_path" app/Http/Resources app/Services/SupportAudit.php
```

Expected: testes PASS; `password` não aparece em resource/audit; `storage_path` somente no model/vault, nunca serializado.

- [ ] **Step 8: Commit isolado**

```bash
git add app/Services/ClientCertificateVault.php app/Http/Requests/Tenant/StoreClientCertificateRequest.php app/Http/Resources/ClientCertificateResource.php app/Http/Controllers/Tenant/ClientCertificateController.php config/filesystems.php routes/api.php tests/Feature/Tenancy/ClientCertificateTest.php
git diff --cached --check
git commit -m "feat(backend): secure client A1 certificates"
```

### Task 7: Procuração e-CAC, fiscal statuses and safe deletion

**Files:**
- Create: `backend/app/Http/Requests/Tenant/UpsertClientEcacPowerOfAttorneyRequest.php`
- Create: `backend/app/Http/Resources/ClientEcacPowerOfAttorneyResource.php`
- Create: `backend/app/Http/Controllers/Tenant/ClientEcacPowerOfAttorneyController.php`
- Modify: `backend/app/Http/Resources/ClientResource.php`
- Modify: `backend/app/Services/ClientManager.php`
- Modify: `backend/app/Models/Client.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Tenancy/ClientEcacPowerOfAttorneyTest.php`
- Modify test: `backend/tests/Feature/Tenancy/{ClientCrudTest,ClientCertificateTest}.php`

**Interfaces:**
- Consumes: relações fiscais, `DeadlineState` e vault.
- Produces: procuração CRUD, status aninhados, filtro de pendências e exclusão que elimina segredo ativo.

- [ ] **Step 1: Gerar request, resource, controller e teste**

```bash
php artisan make:request Tenant/UpsertClientEcacPowerOfAttorneyRequest --no-interaction
php artisan make:resource ClientEcacPowerOfAttorneyResource --no-interaction
php artisan make:controller Tenant/ClientEcacPowerOfAttorneyController --no-interaction
php artisan make:test --phpunit ClientEcacPowerOfAttorneyTest --no-interaction
```

- [ ] **Step 2: Escrever testes RED de procuração e agregação fiscal**

```php
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
```

Adicionar: expiration anterior ao início 422 preserva registro; user 403; outro Account 404; DELETE retorna 204; listagem usa no máximo quantidade constante de queries; filtros `missing|valid|expiring|expired`; soft delete do cliente remove ciphertext atual.

- [ ] **Step 3: Confirmar RED**

```bash
php artisan test --compact tests/Feature/Tenancy/ClientEcacPowerOfAttorneyTest.php tests/Feature/Tenancy/ClientCrudTest.php tests/Feature/Tenancy/ClientCertificateTest.php
```

Expected: FAIL por endpoints/resource/filtros ausentes.

- [ ] **Step 4: Implementar request, resource e controller**

```php
// UpsertClientEcacPowerOfAttorneyRequest::rules()
return [
    'starts_at' => ['required', 'date'],
    'expires_at' => ['required', 'date', 'after_or_equal:starts_at'],
    'notes' => ['nullable', 'string', 'max:2000'],
];
```

`authorize()` chama `Gate::allows('update', $this->route('client'))`. Controller faz o upsert abaixo; destroy apaga somente metadata da procuração e retorna 204.

```php
$power = $client->ecacPowerOfAttorney()->updateOrCreate(
    ['client_id' => $client->getKey()],
    array_merge($request->validated(), ['account_id' => $client->account_id])
);
```

```php
// ClientEcacPowerOfAttorneyResource
return [
    'id' => $this->getKey(),
    'starts_at' => $this->starts_at->toDateString(),
    'expires_at' => $this->expires_at->toDateString(),
    'notes' => $this->notes,
    'status' => resolve(DeadlineState::class)->for($this->expires_at)->value,
];
```

```php
Route::put('clients/{client}/ecac-power-of-attorney', [ClientEcacPowerOfAttorneyController::class, 'update']);
Route::delete('clients/{client}/ecac-power-of-attorney', [ClientEcacPowerOfAttorneyController::class, 'destroy']);
```

- [ ] **Step 5: Completar ClientResource e eager loading**

```php
'certificate' => $this->whenLoaded(
    'currentCertificate',
    fn () => $this->currentCertificate === null ? null : new ClientCertificateResource($this->currentCertificate)
),
'certificate_status' => resolve(DeadlineState::class)->for($this->currentCertificate?->valid_until)->value,
'ecac_power_of_attorney' => $this->whenLoaded(
    'ecacPowerOfAttorney',
    fn () => $this->ecacPowerOfAttorney === null ? null : new ClientEcacPowerOfAttorneyResource($this->ecacPowerOfAttorney)
),
'ecac_power_of_attorney_status' => resolve(DeadlineState::class)->for($this->ecacPowerOfAttorney?->expires_at)->value,
```

Todos os endpoints de Client carregam `currentCertificate` e `ecacPowerOfAttorney`. Implementar `scopeWithDeadlineStatus()` com as mesmas fronteiras do `DeadlineState`:

```php
public function scopeWithDeadlineStatus(Builder $query, ?string $status): Builder
{
    if ($status === null) {
        return $query;
    }

    $today = now()->startOfDay();
    $limit = $today->copy()->addDays(30)->endOfDay();
    $column = fn (Builder $relation, string $name): Builder => match ($status) {
        DeadlineStatus::Expired->value => $relation->whereDate($name, '<', $today),
        DeadlineStatus::Expiring->value => $relation->whereBetween($name, [$today, $limit]),
        DeadlineStatus::Valid->value => $relation->where($name, '>', $limit),
        default => $relation,
    };

    if ($status === DeadlineStatus::Missing->value) {
        return $query->where(fn (Builder $query): Builder => $query
            ->whereDoesntHave('currentCertificate')
            ->orWhereDoesntHave('ecacPowerOfAttorney'));
    }

    return $query->where(fn (Builder $query): Builder => $query
        ->whereHas('currentCertificate', fn (Builder $relation): Builder => $column($relation, 'valid_until'))
        ->orWhereHas('ecacPowerOfAttorney', fn (Builder $relation): Builder => $column($relation, 'expires_at')));
}
```

- [ ] **Step 6: Completar exclusão segura no ClientManager**

```php
public function delete(Client $client): void
{
    DB::transaction(function () use ($client): void {
        $locked = Client::query()->whereKey($client->getKey())->lockForUpdate()->firstOrFail();
        $this->certificateVault->remove($locked);
        $locked->delete();
    });
}
```

Controller captura id/nome/últimos quatro dígitos antes da exclusão e chama `SupportAudit::logWrite()` somente com `name` e `tax_id_last4`. Não registrar CPF/CNPJ completo.

- [ ] **Step 7: Verificar GREEN, query count e segredo removido**

```bash
php artisan test --compact tests/Feature/Tenancy/ClientEcacPowerOfAttorneyTest.php tests/Feature/Tenancy/ClientCrudTest.php tests/Feature/Tenancy/ClientCertificateTest.php
vendor/bin/pint --dirty --format agent
```

Expected: testes PASS; filtro e resource concordam nas fronteiras; nenhum N+1; arquivo ativo some na exclusão lógica.

- [ ] **Step 8: Commit isolado**

```bash
git add app/Http/Requests/Tenant/UpsertClientEcacPowerOfAttorneyRequest.php app/Http/Resources app/Http/Controllers/Tenant/ClientEcacPowerOfAttorneyController.php app/Services/ClientManager.php app/Models/Client.php routes/api.php tests/Feature/Tenancy
git diff --cached --check
git commit -m "feat(backend): track e-CAC powers and fiscal deadlines"
```

### Task 8: Frontend contracts, formatting and client API composable

**Files:**
- Create: `frontend/app/types/client.ts`
- Create: `frontend/app/composables/useClients.ts`
- Modify: `frontend/app/composables/useAuth.ts`
- Modify: `frontend/app/utils/index.ts`

**Interfaces:**
- Consumes: JSON dos Resources e endpoints backend.
- Produces: contratos TypeScript, autorização de apresentação e operações usadas pelos componentes.

- [ ] **Step 1: Definir tipos exatamente iguais ao Resource**

```ts
// app/types/client.ts
export type ClientPersonType = 'company' | 'individual'
export type ClientStatus = 'active' | 'inactive'
export type TaxRegime = 'mei' | 'simple_national' | 'presumed_profit' | 'actual_profit' | 'other' | 'not_applicable'
export type DeadlineStatus = 'missing' | 'valid' | 'expiring' | 'expired'

export interface ClientCertificate {
  id: number
  subject: string
  serial_number: string
  valid_from: string
  valid_until: string
  original_filename: string
  status: DeadlineStatus
}

export interface ClientEcacPowerOfAttorney {
  id: number
  starts_at: string
  expires_at: string
  notes: string | null
  status: DeadlineStatus
}

export interface Client {
  id: number
  person_type: ClientPersonType | null
  tax_id: string | null
  name: string
  trade_name: string | null
  status: ClientStatus
  tax_regime: TaxRegime | null
  registration_status: string | null
  registration_status_date: string | null
  opened_at: string | null
  company_size: string | null
  legal_nature: string | null
  primary_activity: { code: string | null, description: string | null }
  address: {
    street_type: string | null
    street: string | null
    number: string | null
    complement: string | null
    district: string | null
    postal_code: string | null
    city: string | null
    state: string | null
  }
  email: string | null
  phone: string | null
  certificate: ClientCertificate | null
  certificate_status: DeadlineStatus
  ecac_power_of_attorney: ClientEcacPowerOfAttorney | null
  ecac_power_of_attorney_status: DeadlineStatus
  source_updated_at: string | null
  looked_up_at: string | null
  created_at: string
  updated_at: string
}

export interface CnpjPreview {
  tax_id: string
  name: string
  trade_name: string | null
  registration_status: string | null
  registration_status_date: string | null
  opened_at: string | null
  company_size: string | null
  legal_nature: string | null
  primary_activity_code: string | null
  primary_activity_description: string | null
  street_type: string | null
  street: string | null
  address_number: string | null
  address_complement: string | null
  district: string | null
  postal_code: string | null
  city: string | null
  state: string | null
  email: string | null
  phone: string | null
  mei: boolean
  simple_national: boolean
  source_updated_at: string | null
  looked_up_at: string
}

export interface CnpjRefreshPreview {
  current: CnpjPreview
  incoming: CnpjPreview
  changes: Record<string, { from: unknown, to: unknown }>
}

export interface ClientListParams {
  page: number
  per_page: number
  q?: string
  status?: ClientStatus
  tax_regime?: TaxRegime
  deadline_status?: DeadlineStatus
  sort: 'name' | 'tax_id' | 'status' | 'tax_regime' | 'created_at'
  direction: 'asc' | 'desc'
}

export interface ClientWritePayload {
  person_type: ClientPersonType
  tax_id: string
  name?: string
  status: ClientStatus
  tax_regime: TaxRegime
  email?: string
  phone?: string
  street_type?: string
  street?: string
  address_number?: string
  address_complement?: string
  district?: string
  postal_code?: string
  city?: string
  state?: string
}

export type ClientUpdatePayload = Partial<Omit<ClientWritePayload, 'person_type' | 'tax_id'>>

export interface PowerOfAttorneyPayload {
  starts_at: string
  expires_at: string
  notes?: string
}

export interface PaginatedResponse<T> {
  data: T[]
  links: Record<string, string | null>
  meta: { current_page: number, last_page: number, per_page: number, total: number }
}
```

- [ ] **Step 2: Adicionar formatadores puros**

```ts
export function formatTaxId(value: string | null): string {
  if (!value) return 'Não informado'
  if (value.length === 11) return value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4')
  return value.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5')
}

export function maskTaxId(value: string | null): string {
  const formatted = formatTaxId(value)
  return value?.length === 11 ? `***.${value.slice(3, 6)}.${value.slice(6, 9)}-**` : formatted
}

export function formatDate(value: string | null): string {
  return value ? new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeZone: 'UTC' }).format(new Date(value)) : 'Não informado'
}
```

- [ ] **Step 3: Expor role atual sem duplicar regra nos componentes**

```ts
// useAuth.ts
const currentRole = computed(() => accounts.value.find(account => account.id === currentAccount.value?.id)?.role ?? null)
const canManageClients = computed(() => isSuperAdmin.value || currentRole.value === 'admin' || currentRole.value === 'operador')
```

Retornar ambos no objeto de `useAuth()`. Em suporte, `isSuperAdmin` mantém ações disponíveis e backend continua autoritativo.

- [ ] **Step 4: Implementar composable sem estado global oculto**

```ts
export function useClients() {
  const { $api } = useNuxtApp()

  async function list(params: ClientListParams) {
    return $api<PaginatedResponse<Client>>('/clients', { query: params })
  }

  async function lookupCnpj(cnpj: string) {
    const response = await $api<{ data: CnpjPreview }>('/clients/cnpj-lookup', {
      method: 'POST', body: { cnpj }
    })
    return response.data
  }

  async function create(payload: ClientWritePayload) {
    const response = await $api<{ data: Client }>('/clients', { method: 'POST', body: payload })
    return response.data
  }

  async function update(id: number, payload: ClientUpdatePayload) {
    const response = await $api<{ data: Client }>(`/clients/${id}`, { method: 'PATCH', body: payload })
    return response.data
  }

  async function refreshPreview(id: number) {
    const response = await $api<{ data: CnpjRefreshPreview }>(`/clients/${id}/cnpj-refresh-preview`, { method: 'POST' })
    return response.data
  }

  async function refreshCnpj(id: number) {
    const response = await $api<{ data: Client }>(`/clients/${id}/cnpj-refresh`, { method: 'POST' })
    return response.data
  }

  async function remove(id: number) {
    await $api(`/clients/${id}`, { method: 'DELETE' })
  }

  async function uploadCertificate(id: number, file: File, password: string) {
    const body = new FormData()
    body.append('certificate', file)
    body.append('password', password)
    const response = await $api<{ data: Client }>(`/clients/${id}/certificate`, { method: 'POST', body })
    return response.data
  }

  async function removeCertificate(id: number) {
    await $api(`/clients/${id}/certificate`, { method: 'DELETE' })
  }

  async function upsertPowerOfAttorney(id: number, payload: PowerOfAttorneyPayload) {
    const response = await $api<{ data: Client }>(`/clients/${id}/ecac-power-of-attorney`, { method: 'PUT', body: payload })
    return response.data
  }

  async function removePowerOfAttorney(id: number) {
    await $api(`/clients/${id}/ecac-power-of-attorney`, { method: 'DELETE' })
  }

  return { list, lookupCnpj, create, update, refreshPreview, refreshCnpj, remove, uploadCertificate, removeCertificate, upsertPowerOfAttorney, removePowerOfAttorney }
}
```

Não armazenar senha em `useState`, cookie, query, log ou toast.

- [ ] **Step 5: Verificar lint e tipos focados**

```bash
pnpm exec eslint app/types/client.ts app/composables/useClients.ts app/composables/useAuth.ts app/utils/index.ts
pnpm typecheck
```

Expected: zero erro nos arquivos alterados; nenhuma incompatibilidade entre payload e Resource.

- [ ] **Step 6: Commit isolado**

```bash
git add app/types/client.ts app/composables/useClients.ts app/composables/useAuth.ts app/utils/index.ts
git diff --cached --check
git commit -m "feat(frontend): add typed client portfolio API"
```

### Task 9: Client form and fiscal-access overlays

**Files:**
- Create: `frontend/app/components/customers/ClientFormSlideover.vue`
- Create: `frontend/app/components/customers/ClientDetailsSlideover.vue`
- Create: `frontend/app/components/customers/CertificateModal.vue`
- Create: `frontend/app/components/customers/EcacPowerOfAttorneyModal.vue`
- Create: `frontend/app/components/customers/ClientDeleteModal.vue`

**Interfaces:**
- Consumes: `useClients`, client types, `canManageClients` passado pela página.
- Produces: overlays controlados com `v-model:open` e evento `saved`/`deleted` para recarregar a tabela.

- [ ] **Step 1: Criar contrato comum dos overlays**

Cada componente usa estado controlado:

```ts
const props = defineProps<{ open: boolean, client?: Client | null }>()
const emit = defineEmits<{
  'update:open': [value: boolean]
  'saved': [client: Client]
}>()

const isOpen = computed({
  get: () => props.open,
  set: value => emit('update:open', value)
})
```

`ClientDeleteModal` emite `deleted: [id: number]`; todos usam `UModal` ou `USlideover` com `title`, `description`, `#body` e `#footer`, não `#content`, para manter close e acessibilidade nativos.

- [ ] **Step 2: Implementar schema discriminado e etapas do ClientFormSlideover**

```ts
const companySchema = z.object({
  person_type: z.literal('company'),
  tax_id: z.string().min(14, 'Informe um CNPJ válido'),
  status: z.enum(['active', 'inactive']),
  tax_regime: z.enum(['mei', 'simple_national', 'presumed_profit', 'actual_profit', 'other']),
  email: z.email('Email inválido').or(z.literal('')).optional(),
  phone: z.string().max(20).optional()
})

const individualSchema = z.object({
  person_type: z.literal('individual'),
  tax_id: z.string().min(11, 'Informe um CPF válido'),
  name: z.string().min(2, 'Informe o nome completo').max(255),
  status: z.enum(['active', 'inactive']),
  tax_regime: z.literal('not_applicable'),
  email: z.email('Email inválido').or(z.literal('')).optional(),
  phone: z.string().max(20).optional(),
  street_type: z.string().max(40).optional(),
  street: z.string().max(255).optional(),
  address_number: z.string().max(30).optional(),
  address_complement: z.string().max(255).optional(),
  district: z.string().max(255).optional(),
  postal_code: z.string().max(9).optional(),
  city: z.string().max(255).optional(),
  state: z.string().length(2).optional()
})

const schema = z.discriminatedUnion('person_type', [companySchema, individualSchema])
```

Etapa 1 escolhe tipo e documento. Para CNPJ, botão “Consultar CNPJ” chama `lookupCnpj`; somente após sucesso habilita etapa 2 e mostra razão social, fantasia, situação Receita, atividade, endereço e timestamp. MEI/Simples ficam bloqueados conforme preview; se ambos forem falsos, o select exige Presumido/Real/Outro. CPF pula consulta e fixa “Não aplicável”.

- [ ] **Step 3: Implementar submit e refresh confirmado**

```ts
function toUpdatePayload(data: ClientWritePayload): ClientUpdatePayload {
  if (data.person_type === 'company') {
    return {
      status: data.status,
      tax_regime: data.tax_regime,
      email: data.email,
      phone: data.phone
    }
  }

  return {
    name: data.name,
    status: data.status,
    tax_regime: 'not_applicable',
    email: data.email,
    phone: data.phone,
    street_type: data.street_type,
    street: data.street,
    address_number: data.address_number,
    address_complement: data.address_complement,
    district: data.district,
    postal_code: data.postal_code,
    city: data.city,
    state: data.state
  }
}

async function onSubmit(event: FormSubmitEvent<ClientWritePayload>) {
  submitting.value = true
  try {
    const saved = props.client
      ? await update(props.client.id, toUpdatePayload(event.data))
      : await create(event.data)
    toast.add({ title: props.client ? 'Cliente atualizado' : 'Cliente cadastrado', color: 'success' })
    emit('saved', saved)
    isOpen.value = false
  } catch {
    toast.add({ title: 'Não foi possível salvar o cliente', color: 'error' })
  } finally {
    submitting.value = false
  }
}
```

Na edição de CNPJ, “Atualizar pela Receita” chama `refreshPreview`, apresenta somente `changes` numa confirmação e chama `refreshCnpj` após aceite. Fechar sem confirmar não faz PATCH/POST de aplicação.

- [ ] **Step 4: Implementar CertificateModal com senha efêmera**

```ts
const file = shallowRef<File | null>(null)
const password = ref('')

async function submitCertificate() {
  if (!props.client || !file.value || !password.value) return
  uploading.value = true
  try {
    const saved = await uploadCertificate(props.client.id, file.value, password.value)
    emit('saved', saved)
    isOpen.value = false
    toast.add({ title: 'Certificado A1 atualizado', color: 'success' })
  } catch {
    toast.add({ title: 'Não foi possível validar o certificado', description: 'Confira o arquivo e a senha.', color: 'error' })
  } finally {
    password.value = ''
    file.value = null
    uploading.value = false
  }
}

watch(isOpen, (open) => {
  if (!open) {
    password.value = ''
    file.value = null
  }
})
```

Usar `UFileUpload` aceitando `.pfx,.p12`, `UInput type="password" autocomplete="off"`; exibir metadados e validade do atual. Remoção exige confirmação secundária e chama `removeCertificate`.

- [ ] **Step 5: Implementar procuração, detalhe e exclusão**

Procuração usa Zod com strings ISO e refinamento `expires_at >= starts_at`, notas limitadas a 2000 e botões salvar/remover. Detalhe organiza cadastro, contato/endereço e acessos fiscais em seções sem expor path/segredo. Exclusão descreve que o cliente sai da carteira e o A1 ativo será eliminado; exige confirmação pelo nome e chama `remove(client.id)`.

Mapas visuais exatos:

```ts
const deadlinePresentation = {
  missing: { label: 'Não cadastrado', color: 'neutral' as const, icon: 'i-lucide-circle-minus' },
  valid: { label: 'Válido', color: 'success' as const, icon: 'i-lucide-circle-check' },
  expiring: { label: 'Vence em breve', color: 'warning' as const, icon: 'i-lucide-clock-alert' },
  expired: { label: 'Vencido', color: 'error' as const, icon: 'i-lucide-circle-alert' }
}
```

- [ ] **Step 6: Verificar componentes isoladamente**

```bash
pnpm exec eslint app/components/customers/ClientFormSlideover.vue app/components/customers/ClientDetailsSlideover.vue app/components/customers/CertificateModal.vue app/components/customers/EcacPowerOfAttorneyModal.vue app/components/customers/ClientDeleteModal.vue
pnpm typecheck
```

Expected: zero erro; inputs têm label via `UFormField`; botões async têm `loading`; fechar certificado zera senha/arquivo.

- [ ] **Step 7: Commit isolado**

```bash
git add app/components/customers/ClientFormSlideover.vue app/components/customers/ClientDetailsSlideover.vue app/components/customers/CertificateModal.vue app/components/customers/EcacPowerOfAttorneyModal.vue app/components/customers/ClientDeleteModal.vue
git diff --cached --check
git commit -m "feat(frontend): add client and fiscal access forms"
```

### Task 10: Real customer table, filters and responsive states

**Files:**
- Modify: `frontend/app/pages/customers.vue`
- Delete: `frontend/app/components/customers/AddModal.vue`
- Delete: `frontend/app/components/customers/DeleteModal.vue`
- Delete: `frontend/server/api/customers.ts`
- Modify: `frontend/app/types/index.d.ts` only to remove customer-template types proven unused.

**Interfaces:**
- Consumes: components e `useClients` das Tasks 8–9.
- Produces: página `/customers` totalmente conectada à API real.

- [ ] **Step 1: Substituir estado TanStack local por query server-side**

```ts
definePageMeta({ middleware: 'auth' })

const page = ref(1)
const perPage = ref(15)
const search = ref('')
const debouncedSearch = refDebounced(search, 350)
const statusFilter = ref<ClientStatus | 'all'>('all')
const regimeFilter = ref<TaxRegime | 'all'>('all')
const deadlineFilter = ref<DeadlineStatus | 'all'>('all')
const sort = ref<'name' | 'tax_id' | 'status' | 'tax_regime' | 'created_at'>('name')
const direction = ref<'asc' | 'desc'>('asc')

const params = computed<ClientListParams>(() => ({
  page: page.value,
  per_page: perPage.value,
  q: debouncedSearch.value || undefined,
  status: statusFilter.value === 'all' ? undefined : statusFilter.value,
  tax_regime: regimeFilter.value === 'all' ? undefined : regimeFilter.value,
  deadline_status: deadlineFilter.value === 'all' ? undefined : deadlineFilter.value,
  sort: sort.value,
  direction: direction.value
}))

const { data, status, error, refresh } = await useAsyncData(
  'client-portfolio',
  () => list(params.value),
  { watch: [params] }
)

watch([debouncedSearch, statusFilter, regimeFilter, deadlineFilter], () => { page.value = 1 })
```

Não usar `/api/customers`; `$api` já aponta para Laravel.

- [ ] **Step 2: Definir as sete colunas aprovadas**

```ts
const columns: TableColumn<Client>[] = [
  { accessorKey: 'name', header: 'Cliente' },
  { accessorKey: 'tax_id', header: 'CPF/CNPJ' },
  { accessorKey: 'tax_regime', header: 'Regime tributário' },
  { accessorKey: 'status', header: 'Situação' },
  { id: 'certificate', header: 'Certificado A1' },
  { id: 'ecac_power_of_attorney', header: 'Procuração e-CAC' },
  { id: 'actions', enableHiding: false }
]
```

Usar slots `#name-cell`, `#tax_id-cell`, `#tax_regime-cell`, `#status-cell`, `#certificate-cell`, `#ecac_power_of_attorney-cell`, `#actions-cell`. Cliente mostra nome e fantasia; CPF usa `maskTaxId`, CNPJ usa `formatTaxId`; datas usam `formatDate`; todos os estados usam badges semânticos.

- [ ] **Step 3: Implementar toolbar e ações conforme role**

Toolbar contém busca, selects Situação/Regime/Pendências e seletor de colunas. “Novo cliente” aparece somente quando `canManageClients`. Menu de linha sempre oferece “Visualizar”; quando gerenciável oferece “Editar”, “Certificado A1”, “Procuração e-CAC”, ativar/inativar e “Excluir”. Cada ação define target e abre o overlay correspondente.

```ts
const target = shallowRef<Client | null>(null)
const detailsOpen = ref(false)
const formOpen = ref(false)
const certificateOpen = ref(false)
const powerOfAttorneyOpen = ref(false)
const deleteOpen = ref(false)

function openDetails(client: Client) { target.value = client; detailsOpen.value = true }
function openEdit(client: Client) { target.value = client; formOpen.value = true }
function openCertificate(client: Client) { target.value = client; certificateOpen.value = true }
function openPowerOfAttorney(client: Client) { target.value = client; powerOfAttorneyOpen.value = true }
function openDelete(client: Client) { target.value = client; deleteOpen.value = true }

async function toggleStatus(client: Client) {
  await update(client.id, { status: client.status === 'active' ? 'inactive' : 'active' })
  await refresh()
}

function rowActions(client: Client) {
  const items = [{ label: 'Visualizar', icon: 'i-lucide-eye', onSelect: () => openDetails(client) }]
  if (!canManageClients.value) return items
  return [
    ...items,
    { label: 'Editar', icon: 'i-lucide-pencil', onSelect: () => openEdit(client) },
    { label: 'Certificado A1', icon: 'i-lucide-key-round', onSelect: () => openCertificate(client) },
    { label: 'Procuração e-CAC', icon: 'i-lucide-file-key-2', onSelect: () => openPowerOfAttorney(client) },
    { type: 'separator' as const },
    { label: client.status === 'active' ? 'Inativar' : 'Ativar', icon: 'i-lucide-power', onSelect: () => toggleStatus(client) },
    { label: 'Excluir', icon: 'i-lucide-trash-2', color: 'error' as const, onSelect: () => openDelete(client) }
  ]
}
```

- [ ] **Step 4: Implementar loading, empty, no-results e error**

- `status === 'pending'`: `UTable :loading="true"` e controles desabilitados.
- `error`: `UAlert` com “Não foi possível carregar a carteira” e botão “Tentar novamente” chamando `refresh()`.
- `meta.total === 0` sem filtros: card vazio com ícone e “Cadastre o primeiro cliente”.
- `meta.total === 0` com filtros: “Nenhum cliente encontrado” e botão para limpar filtros.
- resultado: `UTable` + `UPagination` usando `meta.total`, `meta.per_page` e `page` controlada.

- [ ] **Step 5: Implementar responsividade e acessibilidade**

Em viewport estreito, ocultar visualmente Regime e CPF/CNPJ por classes de coluna, manter Cliente/Situação/A1/Procuração/Ações e disponibilizar tudo no detalhe. A toolbar usa `overflow-x-auto` e controles com largura mínima. Botões icon-only recebem `aria-label`; status nunca depende apenas de cor; foco volta à ação disparadora ao fechar overlay.

- [ ] **Step 6: Conectar eventos e remover mocks somente agora**

Cada `saved`/`deleted` fecha overlay, limpa target e executa `await refresh()`. Remover os dois componentes antigos e `server/api/customers.ts`; pesquisar referências:

```bash
grep -R "CustomersAddModal\|CustomersDeleteModal\|/api/customers" app server
```

Expected: nenhum resultado.

- [ ] **Step 7: Verificar lint, tipos e build**

```bash
pnpm exec eslint app/pages/customers.vue app/components/customers app/composables/useClients.ts app/types/client.ts
pnpm typecheck
pnpm build
```

Expected: todos PASS; se lint global ainda falhar em admin, o mesmo erro deve constar no baseline da Task 1 e nenhum arquivo admin pode ter sido alterado.

- [ ] **Step 8: Commit isolado**

```bash
git add app/pages/customers.vue app/components/customers app/types/index.d.ts server/api/customers.ts
git diff --cached --check
git commit -m "feat(frontend): connect customer portfolio page"
```

### Task 11: End-to-end verification and OpenSpec completion

**Files:**
- Modify: arquivos das Tasks 1–10 somente se uma verificação revelar defeito.
- Modify: `openspec/changes/manage-client-portfolio/tasks.md` — marcar somente tarefas comprovadas.
- Reference: `openspec/changes/manage-client-portfolio/{proposal,design,plan}.md` e `specs/tenant/*/spec.md`.

**Interfaces:**
- Consumes: feature completa.
- Produces: evidência reproduzível de que specs, segurança e UX estão atendidas.

- [ ] **Step 1: Rodar formatter e testes backend focados**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Unit/BrazilianTaxIdTest.php tests/Unit/DeadlineStateTest.php
php artisan test --compact tests/Feature/Tenancy/ClientCrudTest.php tests/Feature/Tenancy/ClientCnpjLookupTest.php tests/Feature/Tenancy/ClientCertificateTest.php tests/Feature/Tenancy/ClientEcacPowerOfAttorneyTest.php
php artisan test --compact tests/Feature/Tenancy/RolesTest.php tests/Feature/Tenancy/SubscriptionsTest.php tests/Feature/Tenancy/SupportAccessTest.php
```

Expected: todos PASS, nenhum request externo real e nenhum warning novo.

- [ ] **Step 2: Rodar suíte backend completa**

```bash
composer test
```

Expected: suíte PASS; registrar separadamente o aviso preexistente de permissão em `.phpunit.result.cache` se continuar ocorrendo.

- [ ] **Step 3: Rodar verificações frontend completas**

```bash
pnpm lint
pnpm typecheck
pnpm build
```

Expected: todos PASS. Não declarar sucesso com erro “preexistente” sem comparar arquivo/linha com o baseline da Task 1.

- [ ] **Step 4: Rodar cenários API autenticados**

Verificar, com testes ou navegador:

1. CNPJ válido preenche os campos e fora do Simples exige Presumido/Real/Outro.
2. CNPJ inválido não chama o provedor; 404/429/503 não cria nem altera cliente.
3. CPF válido é manual e usa Não aplicável.
4. Documento duplicado falha no mesmo Account e funciona em outro.
5. `user` lê, mas não recebe nem executa ações de escrita; operador/admin executam.
6. Limite do plano retorna 422; cliente excluído não conta; recadastro restaura sem certificado.
7. Preview de refresh não altera dados; confirmação altera somente campos oficiais.
8. Senha errada/arquivo inválido preserva A1 atual; substituição remove ciphertext anterior.
9. Procuração cobre missing/valid/expiring/expired e datas inválidas preservam estado.
10. Acesso por id de outro Account retorna 404; suporte registra escrita sem CPF completo, senha ou path.

- [ ] **Step 5: Executar uma passada visual limitada**

Usar `agent-browser` para capturar `/customers` em desktop e mobile, com estado cheio, vazio e erro. Corrigir em um único lote: overflow, contraste, foco, labels, truncamento, datas e menus. Depois executar uma confirmação visual, sem ciclo aberto de polimento.

Run na raiz:

```bash
/home/obsidian/.agents/skills/impeccable/scripts/impeccable detect --json frontend/app/pages/customers.vue frontend/app/components/customers
```

Expected: revisar todos os findings; corrigir violações reais ou registrar justificativa concreta.

- [ ] **Step 6: Revisar segurança e diff final**

```bash
grep -R "password\|storage_path\|socios" backend/app/Http/Resources backend/app/Services/SupportAudit.php frontend/app
git diff --check
git status --short
openspec validate manage-client-portfolio --strict
```

Expected: nenhuma senha/path/sócio serializado, diff sem whitespace error e OpenSpec válido.

- [ ] **Step 7: Atualizar checklist e commit final de verificação**

Marcar em `tasks.md` somente itens sustentados pelos comandos e cenários anteriores. Revisar o staged diff para não incluir mudanças concorrentes.

```bash
git add openspec/changes/manage-client-portfolio/tasks.md
git diff --cached --check
git commit -m "chore: verify client portfolio implementation"
```

---

## Execution Handoff

Plano salvo em `openspec/changes/manage-client-portfolio/plan.md`.

1. **Subagent-Driven (recomendado):** executar uma task por agente fresco, com revisão de conformidade e qualidade entre tasks.
2. **Inline Execution:** executar neste contexto com `executing-plans`, em lotes curtos e checkpoints após backend core, acessos fiscais e frontend.

Em ambos os modos, começar relendo `proposal.md`, `design.md`, as três specs e este plano; não iniciar pela UI antes dos contratos backend das Tasks 1–7 estarem verdes.
