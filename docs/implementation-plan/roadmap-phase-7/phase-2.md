[← Previous: Packet 7.0 Foundations](./phase-1.md) · [Next: Packet 7B Platform notices →](./phase-3.md)

**Status:** ✅ Done

# Packet 7A: Announcements and events (R-80)

**Goal:** A permitted staff member writes an announcement or an event and chooses who receives it: all members, one class occurrence, or one customer group. Recipients get it in their inbox.

## Dependencies

Packet 7.0. Customer groups (R-71) and sessions/bookings (R-42, R-48) are already built.

## Decisions

- One `announcements` row is the sent record. No drafts, no editing, no deleting.
- “All members” means people with an active or frozen membership in this gym. Expired and cancelled memberships are excluded. Staff-only accounts are excluded because they have no membership row.
- “One class” means one session occurrence, `booked` bookings only. Requested, waitlisted and cancelled bookings are excluded. The session must be scheduled and not yet ended.
- “Customer group” means group customers with a linked user account. Customers without an account are skipped and counted.
- The existing permissions `announcements.general.view` and `announcements.general.manage` are used. `approve` stays unused.
- Sending is synchronous, in chunks of 200. No queue.
- New notices carry an optional `url`; the inbox renders “Open” when present.

## Schema

`backend/database/migrations/2026_11_18_100000_create_announcements_table.php`:

| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| gym_id | FK gyms | cascade delete |
| title | string(120) | |
| body | text | max 2000 validated |
| kind | string(20) | `announcement` or `event` |
| event_starts_at | dateTime nullable | required for events |
| event_place | string(180) nullable | required for events |
| audience_type | string(20) | `all_members`, `session`, `customer_group` |
| training_session_id | FK training_sessions nullable | null on delete |
| customer_group_id | FK customer_groups nullable | null on delete |
| recipients_count | unsignedInteger | default 0 |
| skipped_count | unsignedInteger | default 0 |
| created_by | FK users nullable | null on delete |
| timestamps | | `created_at` is the send time |

Indexes: `(gym_id, created_at)`, `(gym_id, audience_type)`.

No `published_at`: `created_at` is the send time.

## Enums and models

- `App\Enums\AnnouncementKind`: `announcement`, `event`.
- `App\Enums\AnnouncementAudience`: `all_members`, `session`, `customer_group`.
- `App\Models\Announcement`: `BelongsToGym`, fillable fields, enum casts, `session()`, `group()`, `createdBy()` relations.

## Audience resolver

`App\Support\Announcements\AnnouncementAudience`:

```php
/** @return array{users: \Illuminate\Support\Collection<int, \App\Models\User>, skipped: int} */
public function resolve(Gym $gym, AnnouncementAudience $type, ?int $sessionId, ?int $groupId): array
```

- `all_members`: `Membership` rows with status `active` or `frozen`, `user_id` not null, loaded through the current gym scope. Return unique users. Skipped 0.
- `session`: find the session in this gym or 404. Require `status = scheduled` and `ends_at >= now()`. Return users on `booked` bookings, unique. Skipped 0.
- `customer_group`: find the group in this gym or 404. Load its customers. Return users for customers with `user_id`; skipped equals the count with no account.
- Always unique by user id and never include a null user.
- An empty audience is rejected by the controller with 422 “This audience has no one to notify.”

## Backend tasks

- [x] Create the migration, enums, model and resolver.
- [x] Create `App\Http\Controllers\Gym\Concerns\PresentsAnnouncements` with the payload shape:
  `id`, `title`, `body`, `kind`, `audience_type`, `training_session_id`, `session_name`, `customer_group_id`, `group_name`, `event_starts_at`, `event_place`, `recipients_count`, `skipped_count`, `created_by`, `created_by_name`, `created_at`.
- [x] Create `App\Http\Controllers\Gym\AnnouncementController`:
  - `index`: 20 per page, newest first, optional `audience_type` filter, return `{ announcements, meta }`.
  - `options`: compact picker data for the composer, gated by `announcements.general.manage`. Return `member_count`, `sessions` (upcoming `scheduled` sessions from now to +90 days: id, name, starts_at, ends_at, room_name, booked_count) and `groups` (id, name, members_count). This exists so an announcements manager does not also need `training.sessions.view` or `customers.records.view`.
  - `store`: validate title (required, max 120), body (required, max 2000), kind (required enum), event fields (required when kind is event, date must be in the future), audience type (required enum), session id (required when audience is session), group id (required when audience is customer group).
  - Clear the irrelevant audience foreign key before saving.
  - Resolve the audience, reject an empty one with 422, create the row, send `AnnouncementPublished` in chunks of 200, store `recipients_count` and `skipped_count`, return 201.
- [x] Create `App\Notifications\AnnouncementPublished`:
  - database channel only.
  - payload: `title`, `message`, `gym_id`, `announcement_id`, `kind`, `event_starts_at`, `event_place`, `url`.
  - message includes the event date and place for events.
  - url: session audience → `/gyms/{gym}/sessions/{session}`; otherwise `/gyms/{gym}`.
- [x] Add routes inside the gym group in `backend/routes/api.php`:
  - `GET announcements` with `permission:announcements.general.view`.
  - `GET announcements/options` with `permission:announcements.general.manage`.
  - `POST announcements` with `permission:announcements.general.manage`.
- [x] Add all three routes to the `RouteAccessTest` expected map.
- [x] Seeder:
  - add `announcements` to `resetData()` before the parent tables.
  - create one all-members announcement and one event aimed at an existing demo customer group per approved gym.
  - keep the seeder from queuing work; notifications stay synchronous.
- [x] Extend `DatabaseSeederTest` with a basic assertion that seeded announcements exist and counts are non-negative.

## Frontend tasks

- [x] Create `frontend/src/lib/announcements.ts` with types, kind labels, audience labels and date helpers.
- [x] Create `frontend/src/pages/gym/GymAnnouncements.tsx`:
  - gate on `announcements.general.view`; show an `Empty` state without it.
  - list announcements newest first with pagination.
  - desktop table and mobile cards.
  - compose dialog only when `can(gym, 'announcements.general.manage')`.
  - compose fields: title, body, kind, event date/time and place when kind is event, audience type.
  - load picker data from `GET /gyms/{gymId}/announcements/options` once when the compose dialog opens.
  - audience all members: show the `member_count`.
  - audience session: show the returned sessions with name, start time and booked count.
  - audience customer group: show the returned groups with name and member count.
  - show server field errors through `ApiError.errors`; non-field errors go to a toast.
  - after send: success toast, close dialog, reload page 1.
  - show recipients and skipped counts in the list; explain “skipped” as customers without an account.
- [x] Add the route `announcements` to the gym workspace in `frontend/src/App.tsx`.
- [x] Add the nav item under **Members**: label “Announcements”, icon `Megaphone`, permission `announcements.general.view`.
- [x] Keep the empty, loading and error states consistent with the other workspace pages.

## Tests

Create `backend/tests/Feature/AnnouncementTest.php` with a shared setup (owner, employee, members, plan, sessions, bookings, customer group).

- [x] `test_an_announcement_to_all_members_reaches_only_active_and_frozen_memberships` — active and frozen receive; expired, cancelled, applied, awaiting-payment and staff-only do not.
- [x] `test_a_class_announcement_reaches_only_booked_athletes` — booked receives; requested, waitlisted, cancelled and the coach do not.
- [x] `test_a_customer_group_announcement_skips_customers_without_accounts` — linked users receive; `skipped_count` counts the rest.
- [x] `test_an_event_requires_a_date_and_a_place`.
- [x] `test_a_session_or_group_from_another_gym_is_rejected` — 404.
- [x] `test_an_empty_audience_is_rejected` — 422.
- [x] `test_list_and_send_permissions_are_separate` — view can list but not send; no view gets 403.
- [x] `test_picker_options_need_manage_and_return_upcoming_sessions_and_groups` — options 403 without manage; sessions are scheduled and future; groups include member counts.
- [x] `test_recipient_counts_are_stored_on_the_announcement`.
- [x] `test_the_notification_carries_a_link_to_the_target` — session link or gym link.

## Done when

- [x] A permitted person can post an announcement to all members.
- [x] The audience can be narrowed to one class or one customer group.
- [x] Events can be announced with a date and place.
- [x] Only the chosen audience receives it, and the negative cases above are covered.
- [x] The S-35 screen is reachable from the Members nav group.
- [x] All backend tests pass; `pnpm lint` and `pnpm build` pass.
- [x] Browser check: owner sends to each audience; a member sees the notice; a coach without the permission cannot open the screen.

## Status notes

- 6 November 2026 — **Packet 7A complete.** Playwright against isolated MySQL demo data and production frontend preview: Members → Announcements opened S-35; owner sent all-members (201, 14 recipients), booked-class (201, 4 recipients), and group event (201, 3 recipients, Manila date/place preserved). Member inbox displayed the all-members notice and Open links; member and unpermitted coach both saw the denial state, and coach had no Announcements nav item. Composer made exactly one `announcements/options` call per opening and zero sessions/customer-groups calls. Empty form required-field validation and actual past-event 422 field error worked. Cancel/Escape closed dialog; keyboard Tab reached Message with component focus ring. Real 21-row history exercised Next/Previous; sending from page 2 reloaded page 1 (22 rows). Intercepted responses verified list error/retry, empty state, delayed loading skeleton, and picker failure/retry with send disabled. Final clean reload/open/Escape: zero runtime exceptions and zero console errors. Initial browser setup hit Laravel serve environment passthrough/CORS and Vite dev-resource network issues; switched to isolated PHP server plus production preview with matching runtime origins, without source or `.env` changes.
- 6 November 2026 — Visual evidence moved out of the repository: desktop dark `C:/Users/marka/AppData/Local/Temp/opencode/page-2026-11-06T13-15-12-029Z.png`; phone light `C:/Users/marka/AppData/Local/Temp/opencode/page-2026-11-06T13-16-33-375Z.png`. Only this packet's browser artifacts were moved; the pre-existing browser log was left alone. History and composer fit 360/390px (no document/dialog horizontal overflow). Tested production build with `VITE_API_URL=http://localhost:8001/api`; `pnpm build` exited 0 (3,136 modules, 1.89 seconds). Preview command: `pnpm exec vite preview --host 127.0.0.1 --port 5175`; isolated backend: `DB_DATABASE=combat_packet_7a_20261106_210500 FRONTEND_URL=http://127.0.0.1:5175 php -S 127.0.0.1:8001 -t public` (environment set with PowerShell). Packet-created test servers stopped after verification; other processes left alone. Final `pnpm lint`: exit 0 with warnings. Final `pnpm build` using unchanged project environment: exit 0, 3,136 modules, 2.82 seconds (restores normal API URL in ignored build output). `git diff --check`: exit 0, line-ending warnings only. All packet checkboxes verified; no Packet 7B+ work, permission catalog changes, edits to `docs/prompts/P02.md`, commits or pushes.
- 6 November 2026 — Added frontend announcement types/date helpers, S-35 history (desktop table/mobile cards), permission gates, compose dialog and manage-only options loading, field errors/toasts, retry and pagination in `frontend/src/lib/announcements.ts`, `frontend/src/pages/gym/GymAnnouncements.tsx`, `frontend/src/App.tsx`, `frontend/src/components/gym-nav.tsx`. Initial lint/build found missing table-header opening tags; fixed them. Rerun `pnpm lint`: exit 0 with warnings; `pnpm build`: exit 0, 3,136 modules, existing Vite config/chunk-size warnings. Impeccable detector: `[]`. Screen deliberately reuses existing workspace tokens/components; cards replace the table below `md` for readable phone content, and Megaphone denotes announcements as specified. No training/CRM API calls are made by this page.
- 6 November 2026 — Expanded validation and empty-audience coverage. Required final backend gates: `php artisan test --filter=AnnouncementTest`: 13/13 passed, 133 assertions, 3.797 seconds; `php artisan test`: 491/491 passed, 26,195 assertions, 222.831 seconds. MySQL gate via temporary `C:/Users/marka/AppData/Local/Temp/opencode/packet-7a-mysql-verify.php`: runs `migrate:fresh --seed` only in a newly created isolated schema `combat_packet_7a_20261106_210500`, with isolated storage roots. Exit 0; 10 announcements, jobs 0. Existing `combat_platform` schema/data/storage not reset. Browser verification in progress using isolated data; status remains In progress until that check finishes.
- 6 November 2026 — Seeder test-first proof: `php artisan test --filter=DatabaseSeederTest` failed in all three date fixtures because announcements were absent. Added `seedAnnouncements()` and reset ordering in `backend/database/seeders/DatabaseSeeder.php`; reuses audience resolution and synchronous chunked notifications. `php artisan test --filter="DatabaseSeederTest|RouteAccessTest"`: 4/4 passed, 21,876 assertions, 72.581 seconds, including `jobs = 0` and stored recipient-count/notification-count agreement.
- 6 November 2026 — Backend implementation verified: added announcement migration, two enums, model, scoped audience resolver, presenter, controller (including compact manage-only options), synchronous database notification and three routes. `backend/: php artisan test --filter=AnnouncementTest`: initial run timed out at 120 seconds; retry proved RED (12 tests, 11 failed on missing routes). After implementation: GREEN, 12/12 tests, 108 assertions, 7.607 seconds. Added route-map entries and seeder assertions next; those remain unticked until their checks run.
- 6 November 2026 — User approved a compact, manage-only `announcements/options` endpoint. Previous picker-permission blocker resolved by this explicit amendment; training and CRM endpoints keep their original permissions. Added picker regression test before implementing it.
- 6 November 2026 — Packet 7A started. Read locked decisions and repository conventions; preserving Packet 7.0 and unrelated `docs/prompts/P02.md` work. Sequence: failing tests, backend tasks, seeder, frontend, packet checks.
- 6 November 2026 — Added `backend/tests/Feature/AnnouncementTest.php` test-first coverage for the nine named cases plus session eligibility and scoped/filterable pagination. First `php artisan test --filter=AnnouncementTest` failed on a test-helper name collision with Laravel's `session()`; renamed it to `trainingSession()`. Second run: 11 tests, 1 passed, 10 failed (missing announcement routes return 404), 15 assertions. The cross-gym test's current 404 is not verification of isolation until routes exist; no test checkbox is ticked.
- 6 November 2026 — **Blocked; stopped before production implementation as instructed.** The frontend tasks require loading session and group pickers using existing endpoints, but `backend/routes/api.php:201` requires `training.sessions.view` for `GET sessions` and line 366 requires `customers.records.view` for `GET customer-groups`. A staff member holding `announcements.general.view` and `.manage` alone cannot load either targeted audience. The packet does not authorize broadening those endpoints or adding a minimal announcement-options endpoint. Planner decision needed before continuing; do not silently grant training/CRM access or return full CRM records to announcement-only staff. All packet tasks remain unticked.
- 6 November 2026 — Requested checks at blocked boundary: `backend/: php artisan test` first exceeded the 120-second tool timeout; rerun with 600-second timeout completed in 198.731 seconds: 489 tests, 479 passed, 10 failed, 25,888 assertions. All failures are the new announcement tests hitting missing routes. `frontend/: pnpm lint` exited 0 with existing warnings; `pnpm build` exited 0 (3,134 modules; Vite configuration and large-chunk warnings). No frontend or production files changed, no browser check, migration or seed run, no commit or push.
- 6 November 2026 — packet created; not started.
- 7 November 2026 — Final Phase 7 close-out recorded in `phase-7.md`: 520 backend tests / 26,513 assertions, MySQL fresh seed, lint/build/node tests, and the browser matrix (all-members, class, group and event sends; expired, waitlisted and coach exclusions). No Packet 7A source changed in this pass.
