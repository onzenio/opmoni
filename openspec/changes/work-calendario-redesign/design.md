## Context

See `proposal.md` for motivation. Current `frontend/app/pages/work/calendario.vue` renders a `UCalendar` with up-to-4 status dots per day plus a side day-list; `from/to` live only in refs with no shareable URL, and process/client filters are hand-typed numeric IDs. `WorkTask` carries only `due_on: string | null` (date, no time) — see `frontend/app/types/work.ts`. Board actions (`advance`/`moveBack`/`assign`/`dismissWithReason`) already exist in `frontend/app/pages/work/tarefas.vue` and are reused, not reinvented. Backend `PATCH /tasks/{id}` (`backend/app/Http/Controllers/Tenant/TaskController.php::update` + `UpdateTaskRequest`) accepts only `status`, `dismissal_reason`, `assignee_member_id` — drag-to-reschedule is blocked until `due_on` is accepted. The `nuxt-ui-templates/calendar` reference (`/tmp/calendar-template`) contributes the shell pattern (sidebar with mini-calendar + visibility toggles, header with title + view switcher + prev/Today/next, chips with `+N more` popover, `[view]/[date]` routing, popover-per-event) but NOT its heavy machinery: ±5-year virtualized infinite scroll, timed/all-day layout, 15-minute drag snapping, offline queue, or view transitions. Existing delta specs: `tenant/work-tasks` (lifecycle, cascade guard, calendar feed of dated tasks only, kanban with explicit buttons and no status drag-and-drop in v1). New capability spec `tenant/work-calendar` plus the `due_on` addition to `tenant/work-tasks` define observable behavior; this doc covers how.

## Goals / Non-Goals

**Goals:**
- Month grid with readable `dot + title` chips, `+N more` overflow, today highlight, skeletons/empty/error states.
- Week (7 columns) and day (single column) as vertical task lists — no hourly grid, since tasks have no time.
- In-page sidebar (mini-calendar, status toggles default-on, compact process/client/assignee/department/priority controls reusing existing endpoints) plus template-style header (title, Day/Week/Month, prev/next, Today, `t`/arrows shortcuts).
- Shareable position via `?view=&date=` with fallback to current month.
- Task popover reusing the board's API calls with `canManageWork` gating and process link.
- Drag-across-days rescheduling only `due_on`, optimistic with rollback; dismissed chips not draggable; status changes stay button-only (v1 kanban rule preserved).

**Non-Goals:**
- Task creation, deletion, or inline title/date editing from the calendar — tasks are generated from templates.
- Timed events, hour grid, resize-to-change-duration, all-day bars, multi-day spans.
- Offline queue, infinite scroll virtualization, adjacent-range prefetch warming, view-transition sliding, external calendar sync, notifications.
- Touching the global `default.vue` dashboard sidebar — the calendar sidebar is a column inside the page.
- Changing cascade semantics: date moves never trigger the cascade guard.

## Decisions

### 1. Fixed 6×7 month grid, no virtualizer

Render the visible month as a static 6-week × 7-day grid (leading/trailing days fill edge weeks), each cell showing day number + up to `MAX_VISIBLE = 3` chips, remainder behind `+N more` in a `UPopover`. Week view reuses the same day-cell data as 7 vertical columns; day view as one column; both sorted by status weight then title.

Alternative considered: port `MonthView.vue` virtualizer with chunked `loadRange` streaming. Rejected — it pays off for ±5 years of scrolling; we fetch one `GET /work/calendar?from&to` range per visible period, which the existing `useWork().calendar()` already supports.

### 2. Chips colored by status, overflow in popover

Chip = status dot + truncated title (`todo` info, `doing` warning, `done` success, `dismissed` neutral — same mapping as `statusPresentation` in `tarefas.vue`/`calendario.vue`). Priority stays a badge inside the popover/row, not a second color legend. Overflow rows reuse the `CalendarEventChip`-style button opening the same task popover.

Alternative considered: color by priority or department. Rejected — status is the only legend already taught by the board; a second color axis would need a new legend and clash with board badges.

### 3. In-page sidebar + compact top bar (hybrid filters)

Left column: mini `UCalendar` (navigates main view), status toggles styled like the template's "Calendars" checkbox list (client-side chip filtering, no refetch), compact selects for process (from `listProcesses` of the visible month), client (from client-portfolio listing), assignee (from `/account/members/directory`), department + priority. Top header keeps only title + switcher + prev/Today/next + refresh; the old six-field filter card is removed.

Alternative considered: all filters in top card (current) or all in sidebar. Rejected — top card wastes vertical space and typed IDs are hostile; full-sidebar hides nothing only if selects have option sources, which these endpoints provide.

### 4. Query-string position, not nested routes

Keep `/work/calendario` and encode `?view=month|week|day&date=YYYY-MM-DD`; invalid/missing values fall back to the current month. Every navigation (prev/next, Today, switcher, mini) replaces the query so links reproduce the view.

Alternative considered: `[view]/[date].vue` nested routes like the template. Rejected — deeper routes complicate the existing `workNav` + dashboard shell and `?month`-style compat; query carries the same shareability with a smaller diff.

### 5. Popover reuses board logic verbatim

New `WorkCalendarTaskPopover` calls the same `useWork().updateTask()` paths as `tarefas.vue` (advance/return with 422 cascade toast + lock hint, assign via `USelectMenu`, dismiss modal with required reason), gated by `canManageWork`, plus a `NuxtLink` to `/work/processos/{id}`. No separate calendar mutation API.

Alternative considered: template-style inline edit form (title/date/notes with debounced autosave). Rejected — tasks are frozen snapshots from generation; title/department/priority are not editable anywhere, so the calendar must not invent an editor.

### 6. Optimistic date-only drag, plain `refresh()` convergence

Drag (pointer-based, desktop-first; HTML5 also acceptable) moves the chip immediately, `PATCH`es `{ due_on }`, and on failure rolls the chip back plus error toast. No overlay store like `useCalendarEvents().mutate()` — the feed is a single `useAsyncData` range, so `refresh()` after settle reconverges; concurrent drags serialize per-chip via a `busyId`-style guard. Drag never sends `status`; `dismissed` chips render non-draggable; `done` chips remain draggable (rescheduling a finished task is harmless and stays audit-logged).

Alternative considered: full overlay + offline queue port from `useCalendarEvents.ts`. Rejected — our API is authoritative per-request and ranges are small; the overlay only pays off with serverless divergence and offline editing, both non-goals.

### 7. Backend: optional `due_on`, status path untouched

`UpdateTaskRequest` gains `'due_on' => ['sometimes', 'nullable', 'date']`; `TaskController::update` assigns `$task->due_on` when the key is present, leaves status/cascade/completion logic exactly as-is (cascade guard only fires on status transitions beyond `todo`, so pure date moves always pass it), keeps `TaskPolicy` write check (`admin|operador`) and `SupportAudit::logWrite`. Null clears the date (task leaves the feed); malformed dates 422 via Form Request.

Alternative considered: dedicated `POST /tasks/{id}/reschedule`. Rejected — `PATCH` with an optional field matches the existing resource shape and needs no new policy/route/audit path.

## Risks / Trade-offs

- [Drag discoverability on touch] → Chips remain clickable (popover) everywhere; drag is an enhancement, pointer-only, with keyboard-equivalent via popover date display (no hidden-only action).
- [Month-boundary drops] → Dropping on a trailing/leading day navigates the feed range to that day's month after persist; rollback still targets the origin key.
- [Process/client select cardinality] → Process select scoped to visible month; client select reuses paginated portfolio listing with search; both degrade to the current text/ID inputs if endpoints fail.
- [Department free-text 422] → Keep department as text with the existing "not registered" 422 toast until a departments directory select is available; never block rendering on it.
- [Backend old vs new frontend] → `due_on` is optional; old frontends ignore it, new frontend rolls back gracefully on 422/403 from old backends.

## Migration Plan

- No database migration (`tasks.due_on` nullable date already exists).
- Land backend first (request + controller + tests), then frontend; either order is deploy-safe due to the optional field + rollback behavior.
- Rollback: revert frontend alone (backend field unused) or backend alone (frontend drag toasts and rolls back); no data repair needed since failed moves never persist.
