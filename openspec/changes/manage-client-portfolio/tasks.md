## 1. Baseline and safety

- [x] 1.1 Inspect `git status` and the existing diffs for customer, tenant and admin files, record the current test/lint baseline, and verify no unrelated or concurrent work is reverted.
- [x] 1.2 Read the applicable backend rules and current Laravel/Nuxt UI APIs before coding, and verify the implementation uses the installed Laravel 13, Nuxt 4 and Nuxt UI 4 versions without adding dependencies.

## 2. Client domain and persistence

- [x] 2.1 Add failing feature tests for valid CPF/CNPJ creation, invalid check digits, tenant-scoped uniqueness, cross-Account reuse, role permissions, plan limits, soft deletion and restoration; verify the new tests fail for the expected missing behavior.
- [x] 2.2 Create client person-type, internal-status and tax-regime enums plus a migration that expands `clients` with nullable legacy-safe identity, registration, contact, address, source timestamp and soft-delete columns; verify migrations run on both SQLite tests and the configured development database.
- [x] 2.3 Update `Client`, `Account` and `ClientFactory` with casts, relationships, query scopes and company/individual states, and verify model tests cover tenant ownership and normalized tax ids.
- [x] 2.4 Create migrations and tenant-scoped models/factories for certificate history and procuração e-CAC metadata, and verify their foreign keys and cascade behavior with focused database tests.

## 3. CNPJ lookup and tax regime

- [x] 3.1 Add failing tests using `Http::fake()` for CNPJ checksum validation, successful normalization, partner-data exclusion, provider 404/429/timeout/5xx responses, 24-hour cache hits and the shared three-per-minute outbound limit.
- [x] 3.2 Implement the CNPJ.ws lookup service with explicit field allowlisting, cache, shared rate limiting and stable application errors, and verify all lookup service tests pass without real network traffic.
- [x] 3.3 Add authenticated tenant lookup and refresh-preview endpoints with Form Requests and a normalized response contract, and verify unauthorized, wrong-role and cross-tenant requests return the expected 401/403/404 responses.
- [x] 3.4 Implement hybrid regime validation—MEI, Simples Nacional, manual Presumido/Real/Outro, and Não aplicável for CPF—and verify conflicting or missing selections return 422 without persisting data.
- [x] 3.5 Implement confirmed CNPJ refresh so it changes only registration/source fields and preserves internal status, certificate and procuração, and verify preview alone never mutates the client.

## 4. Tenant client API

- [x] 4.1 Add index/store/update requests and a Client API Resource, then implement paginated search, sorting and filters for internal status and tax regime; verify response data and pagination metadata with feature tests.
- [x] 4.2 Refactor `ClientController` to use the requests/resource and focused write services while preserving `ClientPolicy`, `PlanLimits` and tenant route binding; verify admin/operador writes and user read-only behavior through `/api/clients`.
- [x] 4.3 Implement logical deletion, active-plan count exclusion and restoration-on-recreate for the same Account/document, and verify no duplicate row is created and a restored client has no restored certificate secret.
- [x] 4.4 Update existing tenancy, subscription and support tests that submit name-only clients to the new contract, and verify support-mode create/update/delete actions remain append-only audited without CPF or secret certificate data.

## 5. Certificate A1 security

- [x] 5.1 Add certificate tests that generate an ephemeral PFX/P12 and cover correct password, wrong password, invalid file, size limit, metadata extraction, encryption at rest and omission of secrets/paths from JSON; verify they fail before implementation.
- [x] 5.2 Configure a dedicated non-served private certificate disk and implement the certificate vault with OpenSSL parsing, application encryption, opaque tenant/client paths and cleanup on failure; verify stored bytes differ from the uploaded bytes and decrypt to the original only through the vault.
- [x] 5.3 Add tenant-authorized certificate upload and removal endpoints, and verify admin/operador access, user denial and cross-Account 404 behavior.
- [x] 5.4 Implement replacement history and obsolete-file deletion, and verify a failed replacement leaves the prior certificate current while a successful replacement removes the prior ciphertext and retains only safe metadata.
- [x] 5.5 Connect logical client deletion to certificate secret cleanup, and verify deleting a client removes active encrypted bytes and exposes no retrieval endpoint.

## 6. Procuração and deadline states

- [x] 6.1 Add procuração e-CAC upsert/removal requests and endpoints with start/expiration validation, notes and tenant authorization; verify incoherent dates preserve prior data and user/cross-tenant writes are rejected.
- [x] 6.2 Implement one shared deadline calculation for missing, valid, expiring within 30 calendar days and expired, and verify date-boundary tests for today, day 30 and yesterday using a frozen clock.
- [x] 6.3 Add certificate/procuração metadata and deadline states to Client Resource plus server-side deadline filtering, and verify list and detail responses use identical states without N+1 queries.

## 7. Frontend data layer and forms

- [x] 7.1 Add typed client, lookup, certificate, procuração and paginated-response contracts plus a `useClients` composable for every real API operation, and verify Nuxt typecheck succeeds for the new data layer.
- [x] 7.2 Replace the template add modal with a wide staged client slideover for CNPJ lookup/confirmation and manual CPF entry, including conditional regime validation and official-status warning; verify create and edit flows against the Laravel API.
- [x] 7.3 Add focused certificate and procuração dialogs that never retain the certificate password after submission, and verify success, validation, partial-failure and replacement feedback is visible in Portuguese.
- [x] 7.4 Add detail and destructive-confirmation overlays plus activate/inactivate actions, and verify readonly users do not receive write actions while server authorization remains authoritative.

## 8. Customer table and responsive behavior

- [x] 8.1 Rewrite `/customers` with auth middleware, server-side debounced search, filters, sorting, pagination and the approved Cliente/CPF-CNPJ/Regime/Situação/A1/Procuração/Ações columns; verify the table uses only real API data.
- [x] 8.2 Implement loading, empty, error/retry and no-results states, column visibility and accessible labels, and verify keyboard operation and status communication in both color modes.
- [x] 8.3 Adapt the table for narrow viewports so identity, internal status and fiscal alerts remain accessible, and verify desktop and mobile screenshots against the approved template direction.
- [x] 8.4 Remove `frontend/server/api/customers.ts` and obsolete mock customer components/types only after the real flow is connected, and verify no import or request references `/api/customers` mock data.

## 9. Integration and release verification

- [x] 9.1 Run focused client, CNPJ, certificate, procuração, tenancy, subscription and support PHPUnit tests, fix regressions, and verify every relevant test passes with no real external requests.
- [x] 9.2 Run `vendor/bin/pint --dirty --format agent` and the full `composer test`, and verify formatting and the complete backend suite pass apart from any separately documented pre-existing cache-permission warning.
- [x] 9.3 Run `pnpm lint`, `pnpm typecheck` and `pnpm build`, and verify failures are either fixed in changed files or proven to match the captured unrelated baseline.
- [x] 9.4 Run the authenticated manual scenarios for CNPJ, CPF, duplicate document, cross-Account isolation, roles, plan limit, refresh confirmation, certificate replacement/removal, procuração deadlines and logical deletion; verify HTTP responses, persisted state and support audit entries match the specs.
- [x] 9.5 Perform one bounded browser QA pass on desktop and mobile, run the Impeccable detector on changed frontend targets, apply one consolidated defect batch, and verify the final page has no console errors or secret-bearing network responses.
