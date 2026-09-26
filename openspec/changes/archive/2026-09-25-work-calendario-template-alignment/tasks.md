## 1. Test seam and shell

- [x] 1.1 Add failing Node tests for calendar filter-count and accessible date-label behavior, then implement the minimal utility and verify the focused test passes.
- [x] 1.2 Add the calendar-only Work shell variant and verify other Work routes keep their navbar/tabs while `/work/calendario` renders the clean composition.

## 2. Calendar composition

- [x] 2.1 Refine the contextual sidebar with the mini-calendar/status hierarchy, collapsed operational filters, active count, clear action, and responsive behavior; verify all existing filter events and query navigation still work.
- [x] 2.2 Refine the month grid and week/day lists to match the reference rhythm, preserve date-only task semantics, and expose accessible grid/date/task labels; verify month, week, day, overflow, empty, loading, error, and today states.
- [x] 2.3 Replace the broad task modal with a responsive anchored task popover while preserving read-only and management-permission actions, process link, dismissal confirmation, and drag behavior.

## 3. Verification

- [x] 3.1 Run the focused frontend tests, `pnpm lint`, and `pnpm typecheck` successfully.
- [x] 3.2 Perform browser smoke checks at desktop and mobile widths for navigation, filters, task popover, keyboard shortcuts, and accessible calendar structure; inspect the final diff and working tree.
