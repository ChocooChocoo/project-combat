[← Previous: Packet 7E Mobile and Median.co](./phase-6.md)

**Status:** ✅ Done

# Packet 7F: Integration and close-out

**Goal:** Prove the five Phase 7 requirements work together, close the phase, and leave the documents matching the code. This packet changes no product rule; it fixes integration gaps and records evidence.

## Dependencies

All other packets.

## Tasks

### Cross-feature flows

- [x] A front-desk employee sends an all-members announcement; an active member sees it live; an expired member does not.
- [x] A staff member announces to one class; only booked athletes receive it; a waitlisted athlete does not.
- [x] A staff member announces to a customer group; linked customers receive it; the skipped count matches customers without accounts.
- [x] A permitted staff member announces an event with a date and place; the inbox shows both.
- [x] The platform administrator sends a notice to owners and to all verified users; each audience sees the right notice.
- [x] Session reminders fire inside each gym's lead time; rescheduling a session sends a fresh reminder.
- [x] Membership reminders fire at 7, 3 and 1 days; a missed run catches up; a second run sends nothing.
- [x] Follow-up reminders reach the assignee; reassignment and due-date changes re-arm the reminder.
- [x] A notice and a places-left change appear on an open screen within 15 seconds without a reload.
- [x] The Median.co setup document matches the shipped head metadata, safe areas and permissions.

### Access and tenancy sweep

- [x] Every new route has its exact permission middleware or admin middleware.
- [x] `RouteAccessTest` covers the new gym routes.
- [x] A gym-2 owner cannot read or send gym-1 announcements; cross-gym session or group ids are rejected.
- [x] A non-admin cannot open any `/admin/notices` route or screen.
- [x] `announcements.general.view` can list but not send; `announcements.general.manage` can send.
- [x] A coach without the permission does not see the Announcements nav item.
- [x] A notification for one user cannot be marked read by another user.
- [x] Platform notices contain no `gym_id`, gym name or gym address.

### Data and seeder

- [x] `DatabaseSeeder::resetData()` clears `announcements`, `platform_notices` and `membership_reminders`.
- [x] The seeder creates demo announcements and one platform notice.
- [x] `DatabaseSeederTest` covers the new tables.
- [x] The seeder leaves `jobs` empty.
- [x] Every new migration is reversible with `migrate:rollback` on a throwaway database.

### Documentation

- [x] `docs/ANALYSIS - COMBAT SPORTS PLATFORM/05 - ROADMAP/phase-7/PHASE 7.md`:
  - set each of R-80 to R-84 to ✅;
  - tick every done-when box;
  - add a dated status note per requirement with evidence;
  - update the counts table to 5 finished;
  - set the phase to ✅.
- [x] `docs/ANALYSIS - COMBAT SPORTS PLATFORM/05 - ROADMAP/05 - ROADMAP.md`:
  - Phase 7 row is ✅;
  - the phase counts table shows 5 finished and 0 open;
  - the total shows 86 finished and 12 open.
- [x] `docs/ANALYSIS - COMBAT SPORTS PLATFORM/00 - START HERE/00 - START HERE.md`:
  - Q-23 is answered with the audience choice;
  - add a dated change-log entry.
- [x] `docs/ANALYSIS - COMBAT SPORTS PLATFORM/06 - SCREENS BY ROLE/06 - SCREENS BY ROLE.md`:
  - mark S-04, S-15 and S-35 built;
  - remove the Q-23 note on S-04.
- [x] `CLAUDE.md`: add a short note only if the polling, reminder or announcement convention must be known by future agents.
- [x] Update this packet and every earlier packet with the final command results and browser evidence.

## Final verification

Run from `backend/`:

```bash
php artisan test
php artisan migrate:fresh --seed          # MySQL
php artisan app:sync-permissions
php artisan schedule:list
```

Run from `frontend/`:

```bash
pnpm lint
pnpm build
node --test
```

Browser matrix, at desktop and 360/390 px:

| Person | Must be able to | Must not see |
|---|---|---|
| Owner | send announcements to all three audiences; edit the session reminder setting; see the live inbox | another gym's announcements |
| Front desk | send announcements (starter role has `announcements.general.manage`) | admin notices |
| Coach | see announcements only if granted | the composer without manage |
| Member | receive a live notice; see places left change; open the notice link | the announcements composer |
| Platform admin | send a notice to owners or all verified users; see history | gym announcements |
| Expired member | nothing from the all-members audience | new gym announcements |

## Done when

- [x] All five requirements are ✅ in `PHASE 7.md`, each with a dated note and evidence.
- [x] All backend tests pass; frontend lint and build pass; the MySQL fresh migrate and seed pass.
- [x] `schedule:list` shows the three reminder commands at the intended frequencies.
- [x] The browser matrix passes, including a live notice and a live places-left change.
- [x] The phone audit has no horizontal scrolling at 360 px or 390 px.
- [x] The roadmap, start-here log, screens document and Median setup doc match the code.
- [x] The install and store checks are recorded as external user steps.
- [x] Nothing is committed unless the user asked.

## Status notes

- 7 November 2026 — **Packet 7F complete; Phase 7 closed.** Gates from `backend/`: `php artisan test` passed 520 tests / 26,513 assertions in 176.6 seconds; `php artisan migrate:fresh --seed --force` passed on MySQL and left 10 demo announcements, 1 platform notice, 5 gyms, 118 users, `membership_reminders = 0` and `jobs = 0` (601 seeded notifications); `php artisan app:sync-permissions` reported the catalog is up to date; `php artisan schedule:list` showed session reminders `*/5 * * * *`, membership reminders `0 8 * * *` and follow-up reminders `*/15 * * * *`, each registered with `withoutOverlapping()`. The four new migrations rolled back and re-applied cleanly on a throwaway MySQL schema, which was dropped afterwards. From `frontend/`: `pnpm lint` exit 0 with existing warnings only; `pnpm build` exit 0 (3,138 modules, 1.76 s); `node --test` 6 passed / 0 failed. Root `git diff --check` exit 0 with line-ending notices only. No commit.
- 7 November 2026 — Browser matrix (Playwright, seeded demo data unless stated). Owner sent an all-members announcement (14 recipients), a one-class announcement to session 25 (1 booked recipient) and a customer-group event with a date and place (3 linked recipients; 1 temporary unlinked customer counted as skipped). The front desk sent an all-members announcement (14 recipients). A coach without the permission saw no Announcements nav item and got the denial state on the direct route; a head coach with view only could list but had no Send button. An expired member received the all-users platform notice but not the all-members gym announcement; a waitlisted athlete received neither the class notice nor the group event. The administrator sent an owners notice (5 recipients) and an all-users notice (117 recipients); the owner received both, the member received only the all-users notice, a non-admin was redirected from `/admin/notices`, and the API returned 403. Stored platform notice payloads had only `title`, `message` and `notice_id`. A gym-2 owner got 200 on its own announcements, 404 on gym-1 list/send, and 404 for gym-1 session and group ids; the platform administrator got 404 on the gym-1 announcements route, and the front desk got 403 on `/admin/notices`. The owner's session reminder setting was changed from 60 to 30, survived a reload, and was restored to 60 through the real setup endpoint.
- 7 November 2026 — Live delivery: a new all-members notice appeared in an open member inbox 4.8 seconds after send without a reload and the badge moved 33 → 34. Cancelling a booked place through a staff screen changed the open session details from 5 to 6 places left in 11.2 seconds without a reload. Reminder commands: temporary session fixture sent 1 then 0; the reschedule endpoint cleared its marker and the next run sent 1 then 0. Temporary membership fixture sent once at 7, 3 and 1 days and 0 on repeat; a second membership with no ledger caught up once at 2 days left. A due follow-up sent once, stayed silent on repeat, re-armed on reassignment and on a due-date change, and sent to the new assignee. `schedule:list` and the feature tests cover the per-gym session lead time.
- 7 November 2026 — Independent Opus 5.5 validation: **PASS**. R-84 is met for repository scope only; the native Median build, install and store steps stay external and unticked. The validator re-ran the backend suite and the frontend gates and confirmed no code regression, no commit and no websocket/SSE/push dependency.
- 7 November 2026 — Phone audit at 360 px and 390 px: home, profile, notifications, public gym, membership application, memberships, membership detail, bookings, timetable, session details, private coaching, my record, applications and owner application all reported `scrollWidth == clientWidth` with no overflowing element. The shipped `index.html` matches `median-co-setup.md` (title, `viewport-fit=cover`, `#0a0a0a` theme, Apple web-app tags). The installed Median build, native permission prompts and store submission remain external user steps and are not claimed as done.
- 6 November 2026 — packet created; not started.
