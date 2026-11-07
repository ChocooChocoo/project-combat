[← Previous: Packet 7A Announcements](./phase-2.md) · [Next: Packet 7C Scheduled reminders →](./phase-4.md)

**Status:** ✅ Finished

# Packet 7B: Platform notices (R-81)

**Goal:** The platform administrator writes a notice and chooses who receives it: owners of approved gyms, or every verified platform user. Recipients see it in their inbox. The notice carries no gym's private information.

Q-23 is answered: S-04 shows an audience choice. The default is owners of approved gyms.

## Dependencies

Packet 7.0. R-07 (platform administrator) and R-10 (inbox) are already built.

## Decisions

- One `platform_notices` row is the sent record. No drafts, editing or deleting.
- Audience values: `owners` and `all_users`.
- `owners` means one owner per approved gym, deduplicated by user id. Staff and members are excluded. Owners of pending or rejected gyms are excluded.
- `all_users` means every user with a verified email address. Unverified accounts cannot read the inbox, so they are excluded.
- The administrator who sends the notice is excluded from the recipients.
- Delivery is synchronous, in chunks of 200. No queue.
- The notification has no `gym_id` and no gym fields. The notice is gym-less.

## Schema

`backend/database/migrations/2026_11_18_110000_create_platform_notices_table.php`:

| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| title | string(120) | |
| body | text | max 2000 validated |
| audience | string(20) | `owners` or `all_users` |
| recipients_count | unsignedInteger | default 0 |
| created_by | FK users nullable | null on delete |
| timestamps | | `created_at` is the send time |

Index: `(created_at)`.

## Enums and models

- `App\Enums\PlatformNoticeAudience`: `owners`, `all_users`.
- `App\Models\PlatformNotice`: fillable, enum cast, `createdBy()` relation. No gym scope and no `gym_id`.

## Recipient resolution

In `App\Support\PlatformNotices\PlatformNoticeAudience`:

- `owners`: users with a `gym_user` pivot row whose role is `owner`, on a gym whose status is `approved`. Exclude the sender. Unique by user id.
- `all_users`: users with `email_verified_at` not null. Exclude the sender.
- Return a collection of users. An empty audience is allowed but the UI should warn; do not fail the send (the platform may have no other users yet).

## Backend tasks

- [x] Create the migration, enum and model.
- [x] Create `App\Support\PlatformNotices\PlatformNoticeAudience`.
- [x] Create `App\Http\Controllers\Admin\PlatformNoticeController`:
  - `index`: 20 per page, newest first, return `{ notices, meta }`.
  - `store`: validate title (required, max 120), body (required, max 2000), audience (required enum). Resolve recipients, create the row, send `PlatformNoticeSent` in chunks of 200, store `recipients_count`, return 201.
- [x] Create `App\Notifications\PlatformNoticeSent`:
  - database channel only.
  - payload: `title`, `message`, `notice_id`. No `gym_id`, no gym name, no gym address.
- [x] Add routes inside the existing `prefix('admin')->middleware('admin')` group:
  - `GET notices`
  - `POST notices`
- [x] Seeder:
  - add `platform_notices` to `resetData()`.
  - create one demo platform notice for owners.
  - extend `DatabaseSeederTest` with a basic assertion.
- [x] Documentation:
  - mark Q-23 answered in `00 - START HERE.md`.
  - note the answer in the Phase 7 build record.

## Frontend tasks

- [x] Create `frontend/src/lib/platform-notices.ts` with types, audience labels and date helpers.
- [x] Create `frontend/src/pages/admin/AdminNotices.tsx`:
  - list sent notices newest first with pagination.
  - compose dialog: title, body, audience select with “Owners of approved gyms” and “All verified users”.
  - helper text explains who receives each choice.
  - show `recipients_count` and sent date in the list.
  - server field errors through `ApiError.errors`; non-field errors to a toast.
  - after send: toast, close, reload page 1.
- [x] Add the route `/admin/notices` under the `AdminOnly` group in `frontend/src/App.tsx`.
- [x] Add “Platform notices” to the Platform nav group in `frontend/src/components/app-shell.tsx`.
- [x] Keep the same table/card, empty, loading and error patterns as `AdminPermissions`.

## Tests

Create `backend/tests/Feature/PlatformNoticeTest.php`.

- [x] `test_only_the_platform_administrator_can_list_or_send_notices` — owner and employee get 403; admin succeeds.
- [x] `test_the_owners_audience_reaches_one_owner_per_approved_gym` — approved gym owners receive; staff, members and pending-gym owners do not; duplicate owner rows produce one notice.
- [x] `test_the_all_users_audience_reaches_only_verified_users` — verified owner, employee and member receive; unverified user does not.
- [x] `test_the_sender_is_not_notified`.
- [x] `test_the_notification_carries_no_gym_data` — assert `gym_id` and gym fields are absent.
- [x] `test_recipient_count_is_stored`.
- [x] `test_title_body_and_audience_are_required` — 422.
- [x] `test_the_history_is_paginated_newest_first`.

## Done when

- [x] The administrator can write a notice, choose an audience and send it.
- [x] Recipients see it in their inbox.
- [x] The notice carries no gym's private information.
- [x] Q-23 is recorded as answered and S-04 is reachable in the admin nav.
- [x] All backend tests pass; `pnpm lint` and `pnpm build` pass.
- [x] Browser check: admin sends to each audience; an owner and a member see the right notice; a non-admin cannot open `/admin/notices`.

## Status notes

- 6 November 2026 — packet created; not started.
- 6 November 2026: Packet 7B started. Read objectives, findings, implementation plan and conventions. Existing Packet 7.0/7A work and `docs/prompts/P02.md` will be preserved. UI follows the existing AdminPermissions components and states.
- 6 November 2026: Added `PlatformNoticeTest.php` first. Initial `php artisan test --filter=PlatformNoticeTest` failed (missing routes; corrected fixture from nonexistent `pending` to `submitted`). Added migration, enum, gym-less model, audience resolver, controller, presenter, notification and admin routes. Rerun passed: 8 tests, 83 assertions. Tests include 201 synchronous recipients, zero jobs, empty audience, deduplication and inbox retrieval. Added admin middleware coverage to `RouteAccessTest.php`; full-suite verification pending.
- 6 November 2026: Extended `DatabaseSeederTest.php` first: `php artisan test --filter=DatabaseSeederTest` failed all 3 dates because the demo notice was absent. Updated `DatabaseSeeder.php` reset and demo owner delivery; rerun passed 3 tests, 20,406 assertions, including `jobs = 0`. Recorded Q-23's answer in start-here and the Phase 7 build record.
- 6 November 2026: Added `frontend/src/lib/platform-notices.ts` and `frontend/src/pages/admin/AdminNotices.tsx`; wired the admin route in `App.tsx` and Platform navigation in `app-shell.tsx`. Reused coss components, existing typography/color/spacing and responsive table/cards to keep the admin screens consistent. No new dependency or visual asset.
- 6 November 2026: Required checks: backend `php artisan test --filter=PlatformNoticeTest` passed 8 tests / 83 assertions. First full `php artisan test` exceeded the 120-second tool timeout; rerun completed in 218.791 seconds and passed 500 tests / 26,334 assertions. Frontend `pnpm lint` exited 0 with warnings (including the existing-style effect reset in AdminNotices); `pnpm build` passed, with existing Vite config/chunk-size warnings. `git diff --check` passed with line-ending notices only.
- 6 November 2026: Local MySQL `php artisan migrate:status` inspected; applied only this packet's migration using `php artisan migrate --path=database/migrations/2026_11_18_110000_create_platform_notices_table.php --force` (passed). Existing development data was not reset. Browser sends created two verification notices in the local development database, delivering to 5 approved-gym owners and 118 verified users respectively.
- 6 November 2026: Playwright browser checks passed against localhost:5173 / localhost:8000: admin navigation opens S-04; empty history renders; default audience is owners; both audience sends close the dialog and reload history with counts/dates; Ramon (owner) sees both notices; Sofia (member) sees only the all-users notice; both non-admins redirect from `/admin/notices` to `/`. At 390px the history and composer have no horizontal overflow. Empty title triggers native validation; Escape and Cancel close the composer; keyboard Enter opens it. Injected 503 exercises error + Try again recovery; injected 422 renders the server title field error; browser-only paginated fixtures verify Next/Previous and zero-recipient warning, then real history is restored. Console showed transient `ERR_NETWORK_CHANGED` resource failures and deliberately injected 503/422 responses, not JavaScript application exceptions. Early browser-script timeouts were corrected by using visible text rather than a heading for the empty state and accounting for the hidden mobile sidebar.
- 6 November 2026: Additional MySQL schema/seeder gate passed via `php C:\Users\marka\AppData\Local\Temp\opencode\packet7b-mysql-check.php` from `backend/`. Temporary helper bootstrapped Laravel, created a uniquely named isolated MySQL database, invoked Artisan `migrate:fresh --seed --force`, asserted the owner demo notice has 5 recipients and `jobs = 0`, then dropped only that isolated database. Development database was not reset. No Packet 7B task remains unticked. No commit or push.
- 7 November 2026 — Final Phase 7 close-out recorded in `phase-7.md`: the administrator sent owners (5) and all-users (117) notices, the owner saw both, the member saw only the all-users notice, a non-admin was denied, and stored payloads had only `title`, `message` and `notice_id`. Full backend, frontend and MySQL gates passed. No Packet 7B source changed in this pass.
