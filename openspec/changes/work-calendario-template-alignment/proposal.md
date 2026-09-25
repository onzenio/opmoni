## Why

The functional calendar change is already archived, but the resulting page still diverges from the approved Nuxt Calendar reference: the Work shell adds a second navigation layer, filters dominate the sidebar, and the calendar surface is split into heavy cards. The page needs a visual and interaction pass that preserves Opmoni's task semantics while making the calendar immediately scannable.

## What Changes

- Make the calendar route use a clean template-like composition inside the global account navigation.
- Rework the contextual sidebar around the mini-calendar and status legend, with operational filters collapsed by default.
- Refine month, week, and day layouts, task chips, empty/loading/error states, responsive behavior, and calendar accessibility semantics.
- Present task details in a contextual popover on desktop while preserving the existing task actions and mobile fallback.
- Keep the existing query-string URL, task API, due-date drag behavior, permissions, and green Opmoni visual identity.

## Capabilities

### New Capabilities

None. This is a UI refinement of the existing `tenant/work-calendar` capability.

### Modified Capabilities

None. Existing calendar requirements remain authoritative; this change only brings their presentation and accessibility implementation into alignment.

## Impact

- Frontend Work shell, calendar page, calendar sidebar/grid/day views, task details presentation, and related UI utility tests.
- No backend routes, database schema, dependencies, or public API contracts change.
