## Context

See `proposal.md`. The current implementation already owns the calendar data flow, filters, query state, drag-to-reschedule, permissions, and task actions. The redesign must therefore stay inside the existing Nuxt UI surface and avoid copying the reference's timed-event model.

## Goals / Non-Goals

**Goals:**

- Keep the global account sidebar as the only app-level navigation and remove the redundant Work header on the calendar route.
- Give the calendar a single continuous surface: compact header, contextual sidebar, and lightly ruled calendar grid.
- Preserve date-only tasks, Portuguese copy, status colors, query navigation, task actions, loading/error/empty states, and drag rollback.
- Improve keyboard and assistive-technology semantics without changing the task API.

**Non-Goals:**

- Timed events, hourly grids, multi-day event bars, event creation, offline queues, or route migration.
- Reworking unrelated Work pages or the global dashboard visual system.

## Decisions

### 1. Calendar-only Work shell variant

Render the existing Work navbar and navigation toolbar for other Work pages, but suppress them for `/work/calendario`. The global dashboard sidebar remains available, while the calendar owns its contextual mini-calendar and filters. This removes the duplicate hierarchy without changing other Work routes.

### 2. Sidebar hierarchy

Keep the mini-calendar and status visibility list immediately available. Put the five operational filters in a collapsed section with an active-filter count and clear action. On narrow screens, expose the contextual controls as a compact stack/drawer so the month surface remains usable.

### 3. Shared calendar surface

Remove the outer calendar card treatment and use hairline cell rules, consistent padding, muted trailing dates, and status-colored title chips. Month remains a fixed 6×7 grid; week and day remain vertical date-only task lists. Add explicit grid/cell labels and date buttons where the current markup only uses generic divs.

### 4. Task details presentation

Use an anchored task popover on larger screens, with the existing full detail/actions and a responsive modal fallback on small screens. The existing dismissal confirmation remains a separate modal. No task mutation path changes.

### 5. Test seam

Add a small dependency-free calendar UI utility for active-filter counts and accessible date labels, covered with Node's built-in test runner. Visual behavior is verified through Nuxt lint/typecheck and browser smoke checks because the frontend has no component-test runner configured.

## Risks / Trade-offs

- [Removing the Work header could reduce context] → Keep the calendar title and view switcher prominent, and leave Work available in the global sidebar.
- [A dense filter set can still make the sidebar tall] → Collapse filters by default and make the sidebar independently scrollable on desktop.
- [Anchored popovers can be awkward on mobile] → Use a modal fallback below the responsive breakpoint.
- [Reference interaction semantics differ from date-only tasks] → Reuse the reference's hierarchy and accessibility, not its time/event data model.

## Migration Plan

No migration or API rollout is required. The change is frontend-only and can be reverted as one commit if the visual smoke checks expose a regression.
