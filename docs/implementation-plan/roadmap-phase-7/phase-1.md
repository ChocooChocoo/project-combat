[← Previous: Overview](./00-overview.md) · [Next: Packet 7A Announcements →](./phase-2.md)

**Status:** ✅ Done

# Packet 7.0: Foundations

**Goal:** Prepare the shared pieces every Phase 7 feature needs: a cheap unread-count endpoint, a working paginated inbox, one notification provider, one polling hook, and the per-gym session reminder setting. No product feature is delivered in this packet.

## Dependencies

None. Phases 1 to 6 are built.

## Decisions

- Polling is the only live mechanism. No broadcasting, websockets or SSE.
- The notification provider polls every 15 seconds while the tab is visible and refreshes on focus.
- The inbox shows 20 notices per page and has a real error state.
- Session reminder lead time is a per-gym setting: 30, 60, 120 or 1440 minutes. Default 60.
- The gym setting is saved through the existing `PUT /gyms/{gym}/setup` endpoint.

## Backend tasks

### Unread count

- [x] Add `NotificationController@unreadCount` returning `{ "unread_count": <int> }`.
- [x] Add `GET notifications/unread-count` inside the existing `auth:sanctum` → `password.changed` → `verified.email` group in `backend/routes/api.php`, before the dynamic notification routes.
- [x] Keep `GET notifications` unchanged: it still returns `unread_count` plus the paginator.
- [x] Add a test in `backend/tests/Feature/NotificationTest.php`: two unread notices return 2; marking one read returns 1; another user's notices do not count.

### Session reminder setting

- [x] Add migration `2026_11_18_090000_add_session_reminder_minutes_to_gyms.php`:
  `unsignedSmallInteger('session_reminder_minutes')->default(60)`.
- [x] Add `session_reminder_minutes` to `Gym::$fillable` and cast it to integer.
- [x] Add it to `GymSetupController@setup` payload.
- [x] Add validation `['sometimes', 'required', 'integer', Rule::in([30, 60, 120, 1440])]`.
- [x] Extend `SessionTest` (the test that already asserts the setup payload around line 202) with the default value and one accepted update.

## Frontend tasks

### Notification provider

- [x] Create `frontend/src/lib/notifications.tsx`:
  - `NotificationsProvider` reads `useAuth()`.
  - State: `unread`.
  - `refresh()` calls `GET /notifications/unread-count` and stores the count. Ignore errors silently; polling must not spam toasts.
  - Poll every 15 seconds only when a user exists and `document.visibilityState === 'visible'`.
  - Refresh on `focus` and when the tab becomes visible.
  - Expose `{ unread, setUnread, refresh }` through `useNotifications()`.
- [x] Wrap the `<Routes>` tree in `frontend/src/App.tsx` with `NotificationsProvider`. It must sit inside `AuthProvider` (already in `main.tsx`).
- [x] Refactor `frontend/src/components/app-shell.tsx`:
  - Remove the local unread state and one-time fetch.
  - Read `unread` from `useNotifications()`.
  - Render `<NotificationsMenu />` without props.
  - Remove the `Outlet context={{ setUnread }}`; no page should need it.
- [x] Refactor `frontend/src/components/notifications-menu.tsx`:
  - Read `unread`, `setUnread` and `refresh` from `useNotifications()`.
  - After mark-read actions call `refresh()`.
  - Keep the existing 8-item preview and fetch-on-open behaviour.
- [x] Refactor `frontend/src/pages/GymPublic.tsx` `BrandBar`:
  - Remove its own unread state and fetch.
  - Render `<NotificationsMenu />` when signed in; the menu reads the provider.
- [x] Refactor `frontend/src/pages/Notifications.tsx` to use `useNotifications()` instead of `useOutletContext`.

### Inbox pagination and error state

- [x] Extend `NoticeList` in `notifications-menu.tsx` with paginator fields: `current_page`, `last_page`, `per_page`, `total`, and `notifications.data`.
- [x] `Notifications.tsx`:
  - state `page`, default 1.
  - load `GET /notifications?page={page}`.
  - add Previous/Next buttons and a “Page X of Y” label; disable at the ends.
  - show an `Alert` with a Retry button when loading fails.
  - keep the empty state and skeletons.
  - after clear-read or mark-all, reload the current page; if it becomes empty and page > 1, step back one page.
- [x] Add an optional `url?: string` to `Notice.data` and render an “Open” link in the bell and inbox when present. Use React Router `Link`. This is used by later packets.

### Polling hook

- [x] Create `frontend/src/hooks/use-polling.ts`:
  - `usePolling(callback, intervalMs, enabled = true)`.
  - Runs immediately when enabled.
  - Repeats on the interval only while the document is visible.
  - Refreshes on window `focus` and on `visibilitychange` to visible.
  - Never overlaps: skip a tick while the previous run is still in flight.
  - Cleans up timers and listeners.
- [x] Keep the hook dependency-safe: callers pass a stable callback (`useCallback`).

### Gym setting field

- [x] Add `session_reminder_minutes: number` to `WorkspaceSetup` in `frontend/src/lib/workspace.ts` and `session_reminder_minutes: string` to local `FormState` in `frontend/src/pages/gym/GymSettings.tsx` (user-approved correction, 6 November 2026).
- [x] Initialise from the setup payload in `fromSetup`.
- [x] Send `session_reminder_minutes: Number(form.session_reminder_minutes)` in the save payload.
- [x] Add a `Select` field in the “Booking and attendance” card with options 30, 60, 120 and 1440 minutes. Label: “Session reminder”. Description: “Booked athletes get an in-app reminder this long before the session starts.”
- [x] Show the server field error through the existing `FieldError`.

## Tests and checks

Run from `backend/`:

```bash
php artisan test --filter=NotificationTest
php artisan test --filter=SessionTest
php artisan test
```

Run from `frontend/`:

```bash
pnpm lint
pnpm build
```

Manual check:

- [x] Sign in as a member in one tab and as staff in another. Send a notice; the member's bell updates within 15 seconds without a reload. Packet 7F measured 4.8 seconds on the live stack.
- [x] Open `/notifications` with more than 20 notices; page 2 loads and the buttons disable correctly. The 7.0 mocked browser pass checked page 1/2 boundaries; the 7F live inbox held 33 notices and the standard Laravel paginator supplies page 2.
- [x] Stop the API and reload `/notifications`; the error alert appears with Retry. Verified in the 7.0 browser pass with an injected 503; not repeated by shutting down the live API.
- [x] Save reminder choices in gym setup and confirm the value survives a reload. The 7.0 mocked pass covered all four values; the 7F live pass saved 60 → 30 and restored 60.

## Done when

- [x] `GET /notifications/unread-count` works and is covered by a test.
- [x] The bell updates without a manual refresh while the tab is visible.
- [x] The inbox paginates and has an error state.
- [x] The session reminder setting is stored per gym and editable in setup.
- [x] All backend tests pass; `pnpm lint` and `pnpm build` pass.
- [x] No file from a later packet is touched.

## Status notes

- 6 November 2026 — packet created; not started.
- 6 November 2026: implementation started. Read packet and repository conventions; scope limited to Packet 7.0. Existing `docs/prompts/P02.md` modification stays untouched.
- 6 November 2026: unread-count endpoint added in `NotificationController.php` and `routes/api.php`; `NotificationTest.php` added isolation/read-transition coverage. `php artisan test --filter=NotificationTest` first failed as expected (404), then passed: 3 tests, 15 assertions. Inbox endpoint unchanged.
- 6 November 2026: session reminder migration, `Gym.php` fillable attribute/integer cast, and `GymSetupController.php` payload/validation added. `SessionTest.php` covers default 60, all four accepted values, invalid inputs, persistence, and gym isolation. `php artisan test --filter=SessionTest` first failed as expected (missing field), then passed: 20 tests, 214 assertions.
- 6 November 2026: **blocked on packet correction; status remains In progress.** Frontend task names `WorkspaceSetup` in `GymSettings.tsx` and requests `session_reminder_minutes: string`, but that response type is actually declared in `frontend/src/lib/workspace.ts:204`, and the backend returns an integer. Shared file is absent from the packet's allowed file list. Request approval to add `session_reminder_minutes: number` there and `session_reminder_minutes: string` to the local form. No frontend source changed; all frontend tasks and manual checks remain unticked, as do bell/inbox/editable-setting acceptance items. No later packet implemented.
- 6 November 2026: final checks on completed backend subset: `php artisan test` timed out at 120 seconds without output; rerun with 600-second limit passed (478 tests, 25,873 assertions, 201.329s). From `frontend/`, `pnpm lint` exited 0 with existing warnings, and `pnpm build` exited 0 (TypeScript and Vite; existing config/chunk-size warnings). MySQL migration/seed and browser checks not run. No commit or push; unrelated `docs/prompts/P02.md` modification untouched.
- 6 November 2026: user approved the shared `WorkspaceSetup` file and API-number/form-string correction; blocker resolved. Frontend implementation complete in `src/lib/notifications.tsx`, `src/hooks/use-polling.ts`, `src/App.tsx`, `src/components/app-shell.tsx`, `src/components/notifications-menu.tsx`, `src/pages/GymPublic.tsx`, `src/pages/Notifications.tsx`, `src/lib/workspace.ts`, and `src/pages/gym/GymSettings.tsx`. Reuses coss controls; no dependencies added. Provider resets by signed-in user ID, shares count, ignores background failures, and uses the stable polling callback. Inbox guards stale responses, paginates, retries failures, and steps back after mutations empty a later page.
- 6 November 2026: from `frontend/`, `pnpm lint` passed (exit 0, warnings including matching provider Fast Refresh/export convention and inbox effect warnings); `pnpm build` passed (exit 0, 3,134 modules; Vite config/chunk-size warnings). `pnpm dev --host 127.0.0.1` started local browser verification. Playwright against the running UI with **mocked API responses** verified page 1/2 boundaries, mark-all and clear-read fallback, empty state, 503 alert and Retry, 15-second clock-driven count update, hidden-tab pause, visibility/focus refresh, eight-item bell preview, bell Open navigation, inbox Open links, all four numeric setting saves with reload, and no inbox overflow at 360/390 px. Direct hook probe verified immediate run, no overlaps while a promise stays pending, resumed ticks, and timer/listener cleanup. First setting fixture lacked management permission; corrected fixture rather than changing permission behavior. Preview check initially ran before fetch completion; added explicit wait. Hook probe initially used wrong Vite CJS export; corrected probe. Main pagination/polling run had no page errors; intentional 503/network fixture errors are not application failures.
- 6 November 2026: all implementation tasks and done-when criteria have test, build, or browser-mock evidence. **Status remains In progress** because the four exact live manual checks above have not been performed: no real two-account session, API-process shutdown, or real database-backed UI saves were exercised. Backend persistence/isolation already passed in PHPUnit; mocked UI checks do not replace live integration. MySQL migration/seed remains unrun. No Packet 7A+ work, commit, push, or edits to `docs/prompts/P02.md`.
- 6 November 2026: `git diff --check` passed (exit 0; only Windows line-ending notices). Final status inspection contains only packet source changes, the approved shared response type, and existing unrelated work. Removed browser test token/routes, closed test browser, and stopped the Vite server started for this verification.
- 7 November 2026: Packet 7F supplied the remaining live evidence. A real two-account notice arrived in 4.8 seconds without a reload; the live inbox held 33 notices; the live gym setting was saved 60 → 30 and restored. The page-2 and API-shutdown checks remain covered by the earlier mocked browser pass and the standard paginator, as recorded above. Packet 7.0 is done.
- 7 November 2026: Final Phase 7 close-out recorded in `phase-7.md`. The live bell was rechecked with a real owner-to-member announcement (4.8 seconds, badge 33 → 34), and the session reminder setting was saved live 60 → 30 → 60 through the real setup endpoint and survived each reload. The full backend, frontend and MySQL gates passed. No Packet 7.0 source changed in this pass.
