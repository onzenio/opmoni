## Context

See `proposal.md` for motivation (incl. TaskHub reference `.ref/ref-*.png`) and the three delta specs (`tenant/work-templates`, `tenant/work-processes`, `tenant/work-tasks`) for observable behavior. The current `processes` table holds only `account_id` + `name`, returned raw by `ProcessController` under `ProcessPolicy` (read: any tenant member; write: admin|operador) with `BelongsToAccount` scoping and `SupportAudit::logWrite` on writes. Clients (with `TaxRegime` enum + Tags + `ClientPolicy`), members, plan limits and support-mode auditing already exist and stay authoritative. Frontend owns `workNav`-style navigation utilities (`monitoringNav.ts`, `customerRoutes`), a typed API composable per domain (`useClients.ts`), and Nuxt UI dashboard/table/calendar patterns to reuse.

## Goals / Non-Goals

**Goals:**
- Add the Work domain without weakening tenant scoping, role checks or support auditing.
- Generate one process per (template, eligible client, month), idempotently, frozen after generation.
- Resolve eligibility per template from regimes + Tags + explicit exceptions, reusing the existing `TaxRegime` enum and `tags` table (no new category entity).
- Keep the HTTP layer thin through Form Requests, Resources and one focused generation service.
- Deliver the 5 Work views on the existing dashboard shell with the user's chosen grouping (Cliente > Processo > Task) and calendar (only tasks with due dates), mirroring the TaskHub model table and tabbed editor.

**Non-Goals:**
- Anexo de documento na etapa, drag-and-drop on the calendar, dependências arbitrárias entre tasks, SLA/notificações.
- Automação e-CAC, reaproveitar `monitorings`/`documents` como tasks.
- Sincronizar calendários externos ou inferir responsáveis automaticamente.

## Decisions

### 1. Process gains a client; eligibility is rule + exceptions, not a fixed list

`processes` gains nullable `client_id` FK, nullable `template_id` FK, nullable `reference_month` (date, day 01), `status` and nullable `due_on`. Existing name-only rows stay valid as manual processes (NULL template/client/month). Generated rows always carry all three, guarded by `UNIQUE(account_id, template_id, client_id, reference_month)` — full-column unique, no partial index: NULLs stay distinct in both PostgreSQL and SQLite, so manuals never collide (same rationale as the client-portfolio soft-delete decision, which avoided partial-index divergence).

Templates (`process_templates`) hold `cascade` bool, `generate_day`, `due_day`, `is_active` plus a `regimes` JSON array of `TaxRegime` values (empty = all regimes). Tag rule lives in `template_tag` pivot (empty = all tags, otherwise match-any). Exceptions live in `template_client_exceptions` (`template_id, client_id, account_id`, `kind added|removed`, unique per template+client). No `process_client`/`template_client` fixed-list tables — the approved approach is dynamic rule + exceptions.

Alternative considered: fixed client list per template. Rejected because the TaskHub reference and the approved Section 2 require regime+category rules with Add/Remove exception tabs; a fixed list rots whenever regimes or tags change.

### 2. One generation service for manual and scheduled paths, per-client idempotent

`ProcessGenerationService::generate(template, referenceMonth)` resolves eligible clients in one query (active same-account clients × regime rule × tag rule, plus `added`, minus `removed`), then per eligible client does a transactional find-or-create of the (template, client, month) process with row-level locking, cloning the blueprint snapshot into tasks (`due_on` = step's fixed day-of-month capped at month length, status `todo`). `POST /process-templates/{id}/generate` and the daily `work:generate-recurrences` command call the same service; the second caller for an existing triple returns it without new rows. Scheduler scope: active templates whose `generate_day` has passed for the current reference month with eligible clients still lacking a process. A `GET .../preview` endpoint runs only the resolution step and returns match reasons, creating nothing.

Alternative considered: queued job per (template, client). Rejected for v1 — the daily command is observable and testable; queueing can arrive later without spec change.

### 3. Task is a step of a process; cascade is a transition guard

`tasks` carries `process_id` only (client inherited via the process — NO direct `client_id`), `account_id`, title, `department` snapshot (free text v1), `description` snapshot, `due_on`, `priority` snapshot (`low|medium|high|urgent`, default `medium`), nullable `assignee_member_id` (users FK validated as same-account member, never a new membership table), nullable `completed_at`, nullable `dismissal_reason`, `order`. `done` stamps `completed_at`; `dismissed` stamps `completed_at` with a required reason; leaving either clears it. Ordering: `due_on` nulls-last, then `order`. When the template has `cascade` enabled, any transition beyond `todo` is refused while an earlier-`order` task of the same process is neither `done` nor `dismissed`; cascade off allows any order. Snapshot semantics: generation copies step values, so later blueprint edits affect only future months (the approved freeze).

Alternative considered: tasks with their own `client_id`. Rejected because one-process-per-client makes it redundant and a divergence risk; the grouped view joins through the process.

### 4. API shape mirrors the client-portfolio pattern

`apiResource('process-templates')` with nested blueprint sync, rule sync (regimes array, tag ids, exceptions added/removed) and `GET .../preview`; `apiResource('processes')` evolved (filters template/month/client/status, month-desc ordering, detail with ordered tasks + derived progress); `apiResource('tasks')` with filters (process, client via process, status, assignee, department, priority, due range); `POST .../generate`; `GET /work/calendar?from&to` (dated tasks only, with process/client/assignee presentation); grouped Cliente > Processo > Task payload with totals and ratios. Form Requests centralize authz + validation; Resources serialize stable shapes; writes log via `SupportAudit::logWrite`; policies mirror `ProcessPolicy` (read: any member; write: admin|operador).

### 5. Frontend follows the customers/monitoring shell, TaskHub look for models

New sidebar section Work + `work.vue` layout (UDashboardPanel/Navbar/Toolbar tabs) + `workNav.ts` + `useWork.ts`, mirroring `customers.vue`/`monitoring.vue` and `useClients.ts`. Models table mirrors the TaskHub columns (Título, Regimes, Categorias/Tags, Departamentos, Clientes associados, Recorrência); the model editor uses tabs (Associação, Clientes e Exceções, Prazo, Tarefas, Recorrência) with eligibility preview and a "gerar mês" button. Calendar view composes `UCalendar` day-slot chips (CalendarEventsExample) with a day task list; client view uses `UTable` + `getGroupedRowModel` with `grouping ['client_id','process_id']` exactly as the user-provided snippet; process detail uses Stepper/Timeline + expandable task rows; tasks view is a four-column kanban (A fazer, Em progresso, Concluída, Dispensada) with fiscal cards (process + month, client, due date, assignee, priority, department, cascade lock) and explicit advance/return buttons calling the task API — no drag-and-drop in v1 — sharing the listing filters.

Alternative considered: full Calendar template clone (drag-and-drop, offline). Rejected — v1 needs month/day views with status chips only.

## Risks / Trade-offs

- [Fan-out template × clients × steps in one call] → One service call per (template, month), per-client find-or-create inside; hundreds of clients take seconds — acceptable v1, generation stays out of the request hot path via the scheduler, manual generate shows progress feedback.
- [due_day 29–31 in short months] → Cap at month length (Feb 2026 + day 31 → Feb 28); deterministic and tested at the boundary.
- [Assignee leaves the account] → Tasks keep `assignee_member_id`; listing tolerates missing member ("sem responsável"); reassignment validates current membership.
- [Regime/tag changes mid-month] → By design frozen: generated rows never re-resolve; the rule only affects future months (approved Section 2).
- [NULL template/client uniqueness across DBs] → Full-column unique relies on NULL-distinct behavior shared by PostgreSQL and SQLite; no partial index, no divergence.

## Migration Plan

1. Migrate: alter `processes` (additive nullable `client_id`, `template_id`, `reference_month`, `status`, `due_on` + full unique); create `process_templates` (+`cascade`, `generate_day`, `due_day`, `is_active`, `regimes` JSON), `process_template_tasks` (+`department`, `due_day`, `priority`), `template_tag`, `template_client_exceptions`, `tasks` (process-only link, `dismissal_reason` nullable). Existing rows untouched.
2. Deploy backend, then frontend; no contract break on existing routes.
3. Rollback: drop new tables/columns only if no generated data must be kept; frontend Work section hides behind existing auth middleware.

## Open Questions

- None blocking. Department stays free text in v1 (a department catalog would change specs — deferred). Assignee listing reuses the account members endpoint as-is.
