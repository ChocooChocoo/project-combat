[← Previous: Packet 7B Platform notices](./phase-3.md) · [Next: Packet 7D Live delivery →](./phase-5.md)

**Status:** ✅ Complete

# Packet 7C: Scheduled reminders (R-82)

**Goal:** On its own timetable, the system reminds booked athletes before a session, reminds members before their membership expires, and reminds the assigned staff member when a customer follow-up is due. Nobody gets the same reminder twice.

## Dependencies

Packet 7.0. R-36 (memberships), R-48 (bookings) and R-69 (follow-ups) are already built.

## Decisions

- Session reminders run every 5 minutes. Lead time is the gym's `session_reminder_minutes` (30, 60, 120 or 1440; default 60).
- Membership expiry reminders run daily at 08:00. Thresholds are 7, 3 and 1 days before `ends_on`, from `config/communication.php`.
- Follow-up reminders run every 15 minutes. A follow-up is due when `due_at <= now()`. Only the last 24 hours are considered, so the first run cannot flood old follow-ups.
- Frozen memberships and session-count plans with no `ends_on` are skipped.
- Reassignment or a due-date change clears the follow-up marker. Rescheduling a session clears the session marker on its bookings.
- All three commands use `withoutOverlapping()`.
- All reminders are database notifications only. No email, SMS or queue.

## Schema

`backend/database/migrations/2026_11_19_100000_add_reminders.php`:

1. Add `session_bookings.reminder_sent_at` — nullable timestamp, after `check_in_at` or at the end of the table.
2. Create `membership_reminders`:

| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| membership_id | FK memberships | cascade delete |
| ends_on | date | the membership end date this reminder belongs to |
| days_before | unsignedSmallInteger | 7, 3 or 1 |
| timestamps | | |

Unique: `(membership_id, ends_on, days_before)`. Index: `(membership_id, ends_on)`.

## Config

Create `backend/config/communication.php`:

```php
return [
    'membership_expiry_reminder_days' => [7, 3, 1],
    'follow_up_reminder_lookback_hours' => 24,
];
```

## Support classes

### `App\Support\Reminders\SessionReminders`

- Iterate approved gyms; set `CurrentGym` per gym.
- Lead = `$gym->session_reminder_minutes`.
- Query `SessionBooking` in the current gym:
  - `status = booked`
  - `reminder_sent_at` null
  - related session `status = scheduled`
  - `starts_at > now()`
  - `starts_at <= now()->addMinutes($lead)`
- Send `SessionReminder` to the booking's user, then set `reminder_sent_at = now()`.
- Return the number sent.

### `App\Support\Reminders\MembershipExpiryReminders`

- Iterate approved gyms; set `CurrentGym` per gym.
- Consider `Membership` rows with status `active`, `ends_on` not null.
- Skip `frozen` (not active) and any row whose plan is session-based with no `ends_on`.
- `daysLeft = (int) today()->startOfDay()->diffInDays($membership->ends_on->startOfDay(), false)`.
- Pick the smallest configured threshold `T` where `daysLeft <= T`.
- Try to insert `(membership_id, ends_on, T)` into `membership_reminders`. Only send when the insert is new.
- Send `MembershipExpiryReminder` with the actual `daysLeft`, not the threshold.
- Return the number sent.

This gives catch-up: a missed 7-day run still sends once on the next run, and the 3-day and 1-day reminders still follow.

### `App\Support\Reminders\FollowUpReminders`

- Iterate approved gyms; set `CurrentGym` per gym.
- Query `CustomerFollowUp`:
  - `status = open`
  - `reminded_at` null
  - `due_at <= now()`
  - `due_at >= now()->subHours(config('communication.follow_up_reminder_lookback_hours'))`
- Every follow-up already has an assignee: `customer_follow_ups.assigned_to` is NOT NULL. No unassigned case exists.
- Send `FollowUpDueReminder` to the assignee, then set `reminded_at = now()`.
- Return the number sent.

## Notifications

All database channel only. All carry `url`.

- `App\Notifications\SessionReminder`:
  title “Session reminder”; message includes session name, start time and room; payload `gym_id`, `session_id`, `booking_id`, `starts_at`, `url` (`/gyms/{gym}/sessions/{session}`).
- `App\Notifications\MembershipExpiryReminder`:
  title “Membership expires soon”; message includes plan name, end date and days left; payload `gym_id`, `membership_id`, `ends_on`, `days_left`, `url` (`/memberships/{membership}`).
- `App\Notifications\FollowUpDueReminder`:
  title “Follow-up due”; message includes customer name and a short note; payload `gym_id`, `follow_up_id`, `customer_id`, `due_at`, `url` (`/gyms/{gym}/manage/customers/{customer}`).

## Marker resets

- [x] In `SessionController@update`, when `starts_at` or `ends_at` changes, clear `reminder_sent_at` on that session's bookings.
- [x] In `FollowUpController@update`, when `assigned_to` or `due_at` changes, set `reminded_at = null` in the same transaction.
- [x] Cancelled sessions and non-open follow-ups are excluded by the queries; no extra reset is needed.

## Backend tasks

- [x] Create the migration and `App\Models\MembershipReminder`.
- [x] Add `reminder_sent_at` cast to `SessionBooking` and the column to `$fillable` if the model uses it (the model uses `forceFill` for system fields).
- [x] Create the three support classes.
- [x] Create the three notifications.
- [x] Add three closure commands in `backend/routes/console.php`:
  - `app:send-session-reminders`
  - `app:send-membership-reminders`
  - `app:send-follow-up-reminders`
  Each prints the number sent.
- [x] Register the schedules:
  - session: `everyFiveMinutes()->withoutOverlapping()`
  - membership: `dailyAt('08:00')->withoutOverlapping()`
  - follow-up: `everyFifteenMinutes()->withoutOverlapping()`
- [x] Add `membership_reminders` to `DatabaseSeeder::resetData()`.
- [x] Do not seed reminder rows that would make the seeder send notices. The seeder may seed demo data, but reminders live in tests.

## Tests

Create three feature files.

`backend/tests/Feature/SessionReminderTest.php`:

- [x] `test_a_booked_athlete_is_reminded_inside_the_lead_window_once`.
- [x] `test_a_booking_outside_the_window_is_not_reminded`.
- [x] `test_requested_waitlisted_and_cancelled_bookings_are_not_reminded`.
- [x] `test_a_cancelled_session_is_not_reminded`.
- [x] `test_rescheduling_a_session_clears_the_reminder_marker`.
- [x] `test_the_gym_lead_time_is_respected`.

`backend/tests/Feature/MembershipExpiryReminderTest.php`:

- [x] `test_an_active_membership_gets_one_reminder_at_each_threshold`.
- [x] `test_a_missed_threshold_catches_up_once`.
- [x] `test_a_second_run_on_the_same_day_sends_nothing`.
- [x] `test_frozen_memberships_and_session_plans_without_an_end_date_are_skipped`.
- [x] `test_a_renewed_end_date_starts_a_new_reminder_cycle`.

`backend/tests/Feature/FollowUpReminderTest.php`:

- [x] `test_the_assignee_is_reminded_when_a_follow_up_is_due_once`.
- [x] `test_follow_ups_older_than_the_lookback_window_are_skipped`.
- [x] `test_reassignment_clears_the_marker_for_the_new_assignee`.
- [x] `test_a_new_due_date_clears_the_marker`.
- [x] `test_done_and_cancelled_follow_ups_are_skipped`.

Add one schedule test, in `FollowUpReminderTest` or a small `ReminderScheduleTest`:

- [x] `test_the_three_reminder_commands_are_registered` using `Schedule::events()`.

## Done when

- [x] Booked athletes get a reminder inside the lead time, even if nobody opens the app.
- [x] Members get reminders 7, 3 and 1 days before expiry, with catch-up and no duplicates.
- [x] The assigned staff member is reminded when a follow-up is due.
- [x] Nobody gets the same reminder twice.
- [x] All backend tests pass.
- [x] No frontend change is needed in this packet.

## Status notes

- 6 November 2026: Packet verified and complete. Files: `backend/database/migrations/2026_11_19_100000_add_reminders.php`, `backend/config/communication.php`, `backend/app/Models/{MembershipReminder,SessionBooking}.php`, `backend/app/Support/Reminders/{SessionReminders,MembershipExpiryReminders,FollowUpReminders}.php`, `backend/app/Notifications/{SessionReminder,MembershipExpiryReminder,FollowUpDueReminder}.php`, `backend/routes/console.php`, `backend/app/Http/Controllers/Gym/{SessionController,FollowUpController}.php`, `backend/database/seeders/DatabaseSeeder.php`, `backend/tests/Feature/{SessionReminderTest,MembershipExpiryReminderTest,FollowUpReminderTest}.php`, and this packet record. Database notices and markers/ledger share transactions; sends are synchronous and chunked. Assignee schema unchanged. No frontend or Packet 7D+ changes; no commit.
- 6 November 2026: From `backend/`, `php artisan test --filter=SessionReminderTest`: 7 passed, 76 assertions; `php artisan test --filter=MembershipExpiryReminderTest`: 6 passed, 50 assertions; `php artisan test --filter=FollowUpReminderTest`: 7 passed, 53 assertions. Initial separate runs each had one fixture failure: gym status is system-owned, so replaced ignored mass assignment with `forceFill` and valid `submitted` status; reruns above passed. `php artisan test`: first attempt timed out at 120 seconds; rerun with 600-second limit passed all 520 tests, 26,513 assertions, 183.575 seconds (including DatabaseSeederTest and jobs = 0). `php artisan migrate:fresh --seed`: passed on configured MySQL, including new migration. `php artisan app:sync-permissions`: passed. `php artisan schedule:list`: passed, shows session `*/5 * * * *`, membership `0 8 * * *`, follow-up `*/15 * * * *`; test verifies all three use `withoutOverlapping()`.
- 6 November 2026: From `frontend/`, `pnpm lint`: exit 0 with existing React warnings; `pnpm build`: exit 0 with Vite native-config and large-chunk warnings. No frontend source changed. From repository root, `git diff --check`: exit 0, only line-ending warnings on existing unrelated changes. No remaining unticked tasks.
- 6 November 2026: Blocker resolved by approved correction: removed unassigned-follow-up test; keep NOT NULL assignee schema and omit redundant query condition. Added reminder migration, config, model, three support classes and notifications, closure commands/schedules, controller marker resets and seeder reset entry. Verification pending; no boxes ticked yet.
- 6 November 2026: BLOCKED during test-first verification. Added `backend/tests/Feature/SessionReminderTest.php`, `MembershipExpiryReminderTest.php` and `FollowUpReminderTest.php`. Ran `php artisan test --filter='SessionReminderTest|MembershipExpiryReminderTest|FollowUpReminderTest'`: 21 tests, 0 passed, 1 failure (missing schedules), 20 errors (19 missing commands; 1 schema conflict). Required `test_unassigned_follow_ups_are_skipped` cannot create its fixture: `2026_11_13_100000_create_customer_tables.php:53` defines `customer_follow_ups.assigned_to` as NOT NULL with cascade delete. Packet schema changes only session bookings and membership reminders, so supporting unassigned follow-ups requires an explicit plan/schema decision. Stopped before production changes, per packet guardrail. No tasks ticked; remaining packet checks not run while blocked.
- 6 November 2026: Packet 7C started. Read objectives, findings, packet plan and repository conventions. Test-first implementation; preserve Packets 7.0/7A/7B and unrelated working-tree changes.
- 7 November 2026 — Final Phase 7 close-out recorded in `phase-7.md`: temporary session, membership and follow-up fixtures sent once, stayed silent on repeat, and re-armed after reschedule, reassignment or a due-date change; membership reminders sent at 7, 3 and 1 days and caught up once at 2 days left. `schedule:list` confirmed `*/5`, `0 8` and `*/15`. Full backend, frontend and MySQL gates passed. No Packet 7C source changed in this pass.
- 6 November 2026 — packet created; not started.
