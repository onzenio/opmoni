## Context

See `proposal.md` for motivation and the three delta specs for observable behavior. The current `clients` table contains only `account_id` and `name`; its controller returns unpaginated models and validates inline. The Nuxt `/customers` page and its server endpoint still contain template data. Tenancy, role policies, plan limits and support-mode auditing already exist and must remain the authority for access.

The public CNPJ.ws endpoint accepts an unformatted CNPJ, returns substantially more data than this feature needs and permits only three requests per minute. Certificate handling introduces secret binary material and a transient password, while the current filesystem has no certificate-specific boundary.

## Goals / Non-Goals

**Goals:**
- Add the client domain without weakening tenant scoping, role checks or plan enforcement.
- Keep the HTTP layer thin through Form Requests, Resources and focused services.
- Make upstream CNPJ data replaceable and testable without coupling the domain to its raw response.
- Ensure only encrypted certificate bytes exist at rest and no secret reaches JSON or logs.
- Deliver the customer page with server-side query controls and reusable focused components.

**Non-Goals:**
- Persisting company partners, state registrations, secondary activities or the raw CNPJ.ws payload.
- Inferring Lucro Real versus Lucro Presumido from public registration data.
- Storing the password of a PFX/P12, exporting certificates, or exposing download URLs.
- Uploading a procuração document, automating e-CAC login, or monitoring revocation lists.
- Adding a frontend test framework or replacing the existing authentication/tenancy architecture.

## Decisions

### 1. Client remains the tenant aggregate root

`clients` will keep `account_id` and the existing `BelongsToAccount` scope. It will gain person type, normalized tax id, names, independent internal and official statuses, tax regime, selected registration fields, contacts, address, source timestamps and soft deletion. A database unique constraint on `(account_id, tax_id)` provides the final concurrency guard; application validation supplies a readable error. The same tax id remains legal in another Account.

Certificate and procuração data use separate tenant-owned tables rather than nullable columns on `clients`. Their lifecycle, security and replacement semantics differ from ordinary registration data, and separate records keep the client model from becoming a secret container.

Alternative considered: one wide client table. Rejected because certificate replacement history and file lifecycle would be difficult to represent safely.

### 2. Deleted documents are restored rather than duplicated

Client deletion uses soft deletion. A later create request for the same Account and tax id will restore and update the historical client after normal authorization and plan-limit checks instead of creating a second row. Active certificate bytes are removed at deletion and are never restored automatically.

Alternative considered: partial unique indexes that ignore deleted rows. Rejected because PostgreSQL and the SQLite test environment express partial uniqueness differently and duplicate historical identities would complicate related data.

### 3. CNPJ.ws is isolated behind a normalized lookup service

The browser calls a tenant-authenticated backend lookup endpoint. A service validates check digits, reads a cache keyed by normalized CNPJ, applies a shared outbound rate limiter, performs a short-timeout HTTP request and maps an explicit allowlist into an internal array. Successful normalized responses are cached for 24 hours. Provider `404`, `429`, timeout and `5xx` conditions map to stable application errors; no automatic retry consumes an additional public quota.

Client creation accepts the document and user-controlled fields, then obtains official fields through the same cached service instead of trusting an official-data payload posted by the browser. Refresh uses a preview followed by a separate confirmed apply request so viewing differences cannot mutate data.

Alternative considered: direct frontend lookup. Rejected because it duplicates provider coupling, cannot enforce a shared limit and makes reliability and tests weaker.

### 4. Tax regime is constrained by person type and lookup result

For pessoa física the only accepted regime is `not_applicable`. For pessoa jurídica, MEI takes precedence over Simples Nacional when the provider marks both; either provider-derived choice is fixed. If neither flag is active, the request must select `presumed_profit`, `actual_profit` or `other`. The system does not claim to derive information absent from CNPJ.ws.

### 5. Certificate bytes use envelope encryption on a dedicated private disk

Upload is limited to PFX/P12 and a small fixed size. The service reads the upload, uses the supplied password only to parse it with OpenSSL, extracts subject, serial and validity, encrypts the bytes using the application encryption key, and writes the ciphertext under an opaque generated name on a non-served disk. The database stores only tenant/client ownership, safe metadata, opaque path and lifecycle timestamps.

Replacement writes and validates the new ciphertext before switching the current record. After the database transition succeeds, the old ciphertext is deleted and its path cleared while safe metadata remains. Failures clean up newly written files. Client deletion follows the same secret cleanup path. API Resources never serialize paths.

Alternative considered: database blob storage. Rejected because it increases database backup size and couples binary streaming to ordinary records without improving key management.

### 6. Deadline states are derived, not persisted

Both certificate and procuração expose `missing`, `valid`, `expiring` and `expired`. `expiring` includes today through 30 calendar days ahead; `expired` is strictly earlier than today. Computing the state from dates avoids a scheduler and stale status columns. Query filters translate the same boundaries into database predicates so the list and detail representations agree.

### 7. API shape uses dedicated requests and resources

The resource controller will provide paginated index, store, show, update and soft delete. Separate controllers handle lookup/refresh, certificate upload/removal and procuração upsert/removal. Form Requests centralize authorization and conditional validation. Client Resources return a stable shape with nested safe certificate and procuração metadata, and support auditing continues to record writes made in support mode without certificate contents, passwords or full CPF values.

### 8. Nuxt UI is split by user task

`/customers` owns URL-level filters, loading and table composition. A typed composable owns all calls to the Laravel API. A wide slideover handles staged CNPJ/CPF creation and editing; focused modals handle certificate, procuração and destructive confirmation. The table uses server pagination and keeps client identity, internal status and deadline alerts visible on narrow screens. The mock server endpoint and template customer types are removed only after the real page is connected.

## Risks / Trade-offs

- [CNPJ.ws public quota is global and very small] → Cache normalized successes for 24 hours, serialize outbound allowance in the shared cache store and expose recoverable 429 feedback.
- [Provider data may be stale or unavailable] → Show source timestamps, allow non-active registrations with a warning, preserve stored data on refresh failure and require confirmation before replacement.
- [Application-key loss makes ciphertext unrecoverable] → Treat key backup and rotation as an operational prerequisite; never invent a fallback plaintext copy.
- [Database and filesystem operations are not one transaction] → Write new ciphertext first, use a database transaction for metadata switch, delete obsolete files afterward and clean the new file on failure.
- [Soft deletion can conflict with uniqueness] → Restore the existing tenant/document row rather than insert another identity.
- [Existing tests and callers send only `name`] → Update all repository callers and fixtures in the same change; this is an intentional API contract change before public stabilization.
- [Existing unrelated frontend lint failures may obscure verification] → Capture the baseline first, avoid modifying admin pages, run targeted checks during work and require the full checks once concurrent edits are stable.

## Migration Plan

1. Capture the dirty working-tree baseline without reverting unrelated work.
2. Add nullable client columns and the two related tables so the migration can run against existing rows.
3. Backfill existing clients with deterministic test-safe placeholder identity only in development/test fixtures; production migration must leave incomplete legacy rows inactive and identifiable for manual completion rather than inventing a CPF/CNPJ.
4. Add application support for both incomplete legacy rows and complete new rows during the transition.
5. Connect and verify the real frontend before removing the mock endpoint and old components.
6. After existing clients are completed, enforce required application validation for all new writes; database nullability can remain for legacy history.

Rollback removes the new UI and routes first, then the related tables and added client columns. Before rolling back, encrypted certificate files created by this feature must be deleted through a maintenance command or deployment procedure so orphaned secrets do not remain.
