[← Previous: Packet 6.0 Foundations](./phase-1.md) | [Next: Packet 6B Inventory →](./phase-3.md)

**Status:** ✅ Done

# Packet 6A: HR extras — attendance, overtime, performance notes

**Goal:** Employees record when they start and finish work. The system marks lateness and absences against the schedule, leave and holidays. Extra hours become an approval request. Permitted staff keep dated performance notes. Covers R-56, R-57 and R-58.

## Requirements and done-when

| # | Requirement | Done when |
|---|---|---|
| R-56 | Staff attendance | Start and finish recorded; absences marked outside leave and holidays; lateness by a set rule; corrections name the corrector |
| R-57 | Overtime requests | Extra time creates a pending request; only approved overtime is paid |
| R-58 | Performance notes | Permitted staff add and view notes; other employees cannot see them |

## Dependencies

- Packet 6.0: `WorkingDays::kind()`, `staff_grace_minutes`, `overtime_min_minutes`, `employee_profiles.ended_on`, permission strings `hr.attendance.*`, `hr.overtime.approve`, `hr.performance.*`.
- Phase 3: work schedules, leave requests and balances, holidays, employee profiles.
- Phase 5: `training_sessions` only for reference; no staff attendance exists yet.

## Decisions

- One attendance row per employee per calendar day. Split shifts are out of scope; the second clock-in on the same day is refused.
- Self clock-in is a signed-in employee action. There is no QR, GPS or kiosk check-in (Q-03 dropped). Desk entry and corrections exist for mistakes and for employees without a phone.
- Lateness uses `scheduled_start + staff_grace_minutes`. Once past grace, late minutes count from `scheduled_start`. An untimed schedule produces no lateness and no undertime.
- A missing clock-out stays open until corrected or until the absence job marks it `missing_clock_out`. Payroll will surface it as a warning; it is not silently accepted.
- Overtime is paid only from `approved_minutes`. Approval may be lower than the request.
- Overtime requests are created automatically at clock-out when work passes the scheduled end by at least `overtime_min_minutes`, and for a rest-day clock-in for the whole worked time. Staff may also file a manual request.
- Nobody decides their own overtime request, owner included. An employee with `hr.overtime.approve` cannot approve themselves.
- Absence explanations (Q-02) are a plain text field on the employee's own row. No attachment, no review step, no pay effect.
- Performance notes are never visible to the employee they describe. Notes are append-only; a correction is a new note.
- Attendance, overtime decisions and holiday changes are blocked inside a submitted or released payroll period. The block is a no-op until 6F adds payroll; the hook ships here as `PayrollLock` with an "open" implementation.

## Schema

Migration `backend/database/migrations/2026_11_11_100000_create_staff_attendance_tables.php`.

**`staff_attendance`**

| Column | Type | Notes |
|---|---|---|
| `gym_id` | FK cascade | tenant |
| `user_id` | FK cascade | employee |
| `work_date` | date | Asia/Manila calendar day |
| `scheduled_start`, `scheduled_end` | time, nullable | snapshot at clock-in |
| `clock_in_at`, `clock_out_at` | dateTime, nullable | |
| `status` | string(20) | `present`, `late`, `absent` |
| `late_minutes`, `undertime_minutes`, `worked_minutes` | unsigned small int, default 0 | derived |
| `missing_clock_out` | boolean, default false | |
| `source` | string(20) | `self`, `desk`, `system` |
| `explanation` | text, nullable | employee's own note |
| `recorded_by` | FK users, nullOnDelete | who created a desk row |

Unique `(gym_id, user_id, work_date)`. Index `(gym_id, work_date)` and `(gym_id, user_id, work_date)`.

**`staff_attendance_changes`** (append-only)

`attendance_id` FK cascade, `changed_by` FK users, `before` json, `after` json, `reason` string(255), timestamps.

**`overtime_requests`**

| Column | Type | Notes |
|---|---|---|
| `gym_id`, `user_id` | FK cascade | |
| `work_date` | date | |
| `attendance_id` | FK nullable, nullOnDelete | |
| `kind` | string(20) | `after_shift`, `rest_day`, `manual` |
| `requested_minutes` | unsigned small int | |
| `approved_minutes` | unsigned small int, nullable | |
| `reason` | text | |
| `status` | string(20) | `pending`, `approved`, `rejected` |
| `decided_by`, `decided_at`, `decision_note` | | |

Indexes `(gym_id, status)`, `(gym_id, user_id, work_date)`.

**`performance_notes`** (append-only)

`gym_id`, `user_id` (subject), `author_id`, `noted_on` date, `kind` (`commendation`, `concern`, `review`, `general`), `body` text, timestamps. Index `(gym_id, user_id, noted_on)`.

## Backend tasks

- [x] Models `StaffAttendance`, `StaffAttendanceChange`, `OvertimeRequest`, `PerformanceNote`, all with `BelongsToGym` except the change table (reached through its parent).
- [x] `App\Support\StaffAttendanceBook`:
  - `clockIn(User $employee, Gym $gym): StaffAttendance` — refuses if a row exists for today; snapshots the schedule; sets `present` or `late`; sets `late_minutes`.
  - `clockOut(User $employee, Gym $gym): StaffAttendance` — refuses without an open row; computes `worked_minutes`, `undertime_minutes`; creates the overtime request per the rule.
  - `deskEntry(Gym $gym, User $employee, array $data, User $actor): StaffAttendance` — creates a complete row with `source = desk`.
  - `correct(Gym $gym, StaffAttendance $entry, array $data, User $actor, string $reason): StaffAttendance` — writes `staff_attendance_changes` before/after in the same transaction; recomputes derived fields; resets a pending or unpaid overtime request to pending with new minutes.
  - `explain(User $employee, StaffAttendance $entry, string $text): StaffAttendance` — only the owner of the row, only `absent` or `late`.
- [x] `App\Console\Commands\MarkStaffAbsences` (`app:mark-staff-absences {--date=} {--from=} {--to=}`):
  - For each approved gym, each employee with an active or just-ended profile: skip if `kind()` is not `Working`; skip approved leave; skip an existing row.
  - Insert `absent` rows with `source = system`.
  - For rows with a clock-in and no clock-out when the date is before today, set `missing_clock_out = true`.
  - Idempotent. Uses `withoutGlobalScope('gym')` with explicit `gym_id`, chunks employees, caches holidays per gym-year. Schedule it daily at 00:20 for yesterday in `backend/routes/console.php`.
- [x] Controllers `StaffAttendanceController`, `MyAttendanceController`, `OvertimeController`, `PerformanceNoteController`.
- [x] Routes inside `gyms/{gym}` with `gym` middleware:

| Method and path | Controller | Permission |
|---|---|---|
| `GET staff-attendance` | StaffAttendanceController@index | `hr.attendance.view` |
| `POST staff-attendance` | @store (desk) | `hr.attendance.manage` |
| `PUT staff-attendance/{entry}` | @update (correction) | `hr.attendance.manage` |
| `GET staff-attendance/{entry}/changes` | @changes | `hr.attendance.view` |
| `GET my-attendance` | MyAttendanceController@index | self |
| `POST my-attendance/clock-in` | @clockIn | self |
| `POST my-attendance/clock-out` | @clockOut | self |
| `PUT my-attendance/{entry}/explanation` | @explain | self |
| `GET overtime-requests` | OvertimeController@index | `hr.attendance.view` or `hr.overtime.approve` |
| `POST my-overtime-requests` | @store | self |
| `POST overtime-requests/{request}/decision` | @decide | `hr.overtime.approve` |
| `GET staff/{user}/performance-notes` | PerformanceNoteController@index | `hr.performance.view` |
| `POST staff/{user}/performance-notes` | @store | `hr.performance.manage` |

- [x] Notifications `OvertimeRequested` (to `peopleWithPermission('hr.overtime.approve')`, skipping the requester) and `OvertimeDecided` (to the requester).
- [x] `App\Support\PayrollLock` with `assertOpen(Gym $gym, Carbon $date): void` and `assertOpenRange()`. Packet 6A ships the class returning early; 6F fills it in.
- [x] Server-side checks: an employee without `hr.attendance.view` sees only their own rows; the performance note subject never sees notes; a note author cannot be the subject.

## Frontend tasks

- [x] `frontend/src/pages/gym/GymStaffAttendance.tsx` at route `staff-attendance`:
  - Tabs: **Today** (present, late, absent, still clocked in), **Records** (date range filter, correction dialog with a required reason, change-history dialog), **Overtime** (pending and decided, decide dialog with approved minutes).
  - Read-only without `hr.attendance.manage`; no access without `hr.attendance.view`.
- [x] `EmployeeRecord.tsx` gains a **Performance** tab, shown only with `hr.performance.view`. Add-note dialog requires `hr.performance.manage`.
- [x] `MyWork.tsx` gains a clock card and two sections: my attendance with the explanation field, and my overtime requests with a manual request dialog.
- [x] Nav entry "Staff attendance" in `gym-nav.tsx` with `[hr.attendance.view, hr.overtime.approve]`.
- [x] Types in `frontend/src/lib/hr.ts` (new): `StaffAttendance`, `OvertimeRequest`, `PerformanceNote`.

## Tests

| File | Must prove |
|---|---|
| `StaffAttendanceTest` | One row per day; grace boundary (10 minutes on time, 11 late); late minutes count from start; undertime; desk correction needs a reason and writes before/after; employee sees only own rows; missing clock-out is flagged |
| `StaffAbsenceJobTest` | Saturday holiday is not absent (old C-22); gym holiday skipped; approved leave skipped; rest day skipped; before `started_on` skipped; rerun is idempotent; ended employment stops after `ended_on` |
| `OvertimeTest` | Automatic request after the threshold; rest-day request for all worked minutes; manual request; cannot decide own; partial approval; correction resets to pending; rejected is not paid (asserted with the 6F calculator when it lands) |
| `PerformanceNoteTest` | Permission required; subject cannot see notes; self-note refused; ordering and kinds |
| `StaffAttendanceTenancyTest` | Another gym's entry, overtime request and employee id all return 404 |

## Checks

`php artisan test`, `php artisan migrate:fresh --seed` on MySQL, `pnpm lint`, `pnpm build`, then a browser pass as owner, HR and a plain employee.

## Done when

- [x] An employee can clock in and out and sees their own record for the day.
- [x] A clock-in after start plus grace is `late` with the right minutes.
- [x] A working day with no row, no approved leave and no holiday becomes `absent` on the next run.
- [x] Extra time past the threshold creates a pending overtime request.
- [x] An overtime request cannot be decided by its own requester.
- [x] A correction records before, after, who and why.
- [x] A permitted person can add and read performance notes; another employee cannot.
- [x] All backend tests pass, frontend lint and build pass.

## Status notes

- 2026-11-05: Built the four tables (`staff_attendance`, `staff_attendance_changes`, `overtime_requests`, `performance_notes`), their enums and models, and the gym relations. Files: migration `2026_11_11_100000_create_staff_attendance_tables.php`, `app/Enums/Attendance*.php`, `Overtime*.php`, `PerformanceNoteKind.php`, `app/Models/StaffAttendance.php`, `StaffAttendanceChange.php`, `OvertimeRequest.php`, `PerformanceNote.php`, `Gym.php`.
- 2026-11-05: `StaffAttendanceBook` owns clock-in, clock-out, desk entry, correction and explanation. Lateness uses the gym grace and counts from the scheduled start; the automatic overtime request uses the gym threshold; corrections write before/after and reset a non-rejected overtime request to pending. Files: `app/Support/StaffAttendanceBook.php`, `app/Support/PayrollLock.php`.
- 2026-11-05: The absence job is `app:mark-staff-absences` in `routes/console.php`, with the logic in `app/Support/StaffAbsenceMarker.php`. This matches the project's existing console-command convention instead of adding a command class. It runs daily at 00:20 and is idempotent.
- 2026-11-05: Requests and notices. Routes cover desk records, own clock, overtime and performance notes. `OvertimeRequested` and `OvertimeDecided` reuse the database notification pipeline. Added `GET staff-attendance/employees` so the desk dialog can list employees without needing `hr.staff.view`; this is a small supporting route beyond the packet's table.
- 2026-11-05: Screens. New `GymStaffAttendance.tsx` (Today, Records, Overtime, desk and correction dialogs, history), `MyAttendance` on My work (clock, records, explanation, manual overtime), and `PerformanceNotes` on the employee record. Nav and route added.
- 2026-11-05: Tests added: `StaffAttendanceTest`, `StaffAbsenceJobTest`, `OvertimeTest`, `PerformanceNoteTest`, `StaffAttendanceTenancyTest`. Packet run: 28 passed, 103 assertions.
- 2026-11-05: `pnpm build` passed; `pnpm lint` exit 0 with the existing warning pattern only.
- 2026-11-05: Browser pass on the running app. Owner opened Staff attendance, switched all three tabs, opened the desk dialog and the correction dialog. Employee Dindo clocked in at 20:38 (late 6 hr 38 min against 14:00–21:00) and out at 20:39 (undertime 21 min); the owner page then showed the row and the still-in count. The employee record showed the Performance tab and its empty state. At 390 px the page had no horizontal overflow. No console errors.
- 2026-11-05: Dev MySQL migrations applied, including the new tables. The browser pass left one demo attendance row for Dindo on 2026-11-05; it was removed during the later design review, so the dev database has no staff attendance rows.
- 2026-11-05: Full backend suite: 323 passed, 23,159 assertions.
- 2026-11-05: Assumption: an automatic overtime notice is sent only when the request row is first created. Corrections reset the request to pending without sending a second notice.

---

[← Previous: Packet 6.0 Foundations](./phase-1.md) | [Next: Packet 6B Inventory →](./phase-3.md)
