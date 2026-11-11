# Training Management gaps: D-35, D-32, D-24

## Context
A gap check of Module 3 (D-24 to D-39) found that three requirements are only partly built, even though the Phase 5 roadmap marks them finished:
- **D-35:** booking never checks the athlete's level. Athletes have no structured level; `current_rank` is free text.
- **D-32:** only an approved leave marks ("flags") a coach's sessions. Changes to work hours or days off, new gym holidays and staff removal do not. Athletes are not told when a session is flagged. Staff cannot clear a flag without changing the time, room or coach.
- **D-24:** there is no "group session" type, and a gym cannot add its own session types.

Decisions the user made:
- **Level rule:** "at or above". A session marked "All levels" is open to everyone. An athlete with no level set counts as Beginner.
- **Session types:** add "Group session" as a built-in type, and let each gym add its own extra types.

The smaller limits (staff-only check-in, automatic waitlist promotion, no editing of notes, no bulk move of a series) are out of scope.

## 1. D-35: level check on booking
- **Migration** `backend/database/migrations/2026_11_10_100000_add_training_gap_columns.php`:
  - `athlete_profiles.level`: nullable string(20).
  - `gyms.custom_activity_types`: nullable json (used in part 3).
- **`AthleteProfile`:** add `level` to the fillable list.
- **New route** `PUT gyms/{gym}/athletes/{user}/level` with `permission:training.athletes.manage`, handled by a new `AthleteRecordController::updateLevel`:
  - Accepts `beginner`, `intermediate`, `advanced` or null.
  - Uses the existing `profile()` lookup.
  - Blocks editing your own level, as `storeEntry` already does.
- **`AthleteRecordController::record()`** returns `level` in the record.
- **`BookingDesk::eligibility()`** gets the new rule:
  - The order is beginner (1), intermediate (2), advanced (3).
  - An athlete with no level counts as beginner (1).
  - A session marked `all` always passes.
  - Otherwise, if the athlete's level is below the session's level, booking is refused with "This session is for {Level} athletes and above."
  - `promote()` and `decide()` already call `eligibility()`, so waitlist promotion and approval get the check too.
- **Frontend** `frontend/src/components/membership/athlete-record-view.tsx`:
  - Show the athlete's level.
  - Add a level select for staff with `can('training.athletes.manage')`.
  - Add the type and API call in `frontend/src/lib/` next to the existing athlete record types.
- **Seeder:** give demo athletes levels so the seeded intermediate and advanced sessions can still be booked.

## 2. D-32: flag sessions whenever a coach becomes unavailable
- **New class `backend/app/Support/SessionFlagger.php`**, the single place that flags sessions:
  - `flag(Gym $gym, Collection $sessions, callable|string $reason)`. It takes upcoming scheduled sessions that are not yet flagged and sets `flagged_at` and `flag_reason`.
  - It sends a `SessionChanged` notice of type `flagged` to athletes with a live booking or waitlist place.
  - It sends one `SessionsFlagged` summary to staff with `training.sessions.manage`.
  - It returns how many sessions it flagged.
  - The `$reason` callable is given a session. It returns that session's reason, or null to skip it, which is how a work-hours change is checked one session at a time.
- **Change `SessionsFlagged`** to take a reason text instead of a coach name. The message becomes "N sessions need a new coach or time: {reason}."
- **Callers.** Each one runs inside its existing transaction, with the gym row locked, as leave approval already does:
  - **`LeaveController` decide:** replace the inline `update()` with the flagger and keep the same reason text.
  - **`ScheduleController::update`:** after the hours are saved, check the coach's upcoming sessions with `SessionScheduler::problems()` and flag any with a `starts_at` problem, using the first message as the reason.
  - **`HolidayController::store`:** flag every session on that date with the reason "Holiday: {name}".
  - **`StaffController::destroy`:** flag the removed person's upcoming sessions with the reason "Coach {name} is no longer on staff".
- **Clear a flag:** new route `POST gyms/{gym}/sessions/{session}/clear-flag` with `permission:training.sessions.manage`, handled by `SessionController::clearFlag`:
  - Lock the session.
  - Refuse with 422 if the coach is no longer in `coaches($gym)`, or if `problems()` still shows a `starts_at` or `coach_id` problem.
  - Otherwise clear the flag, send athletes a `SessionChanged` notice of type `flag_cleared` ("goes ahead as planned"), then call `BookingDesk::promote()`. Promotion skips flagged sessions, so the waitlist can move once the flag is cleared.
- **`SessionChanged`:** add titles for `flagged` ("Session may change") and `flag_cleared` ("Session confirmed").
- **Frontend** `frontend/src/components/schedule/session-actions.tsx`: add a "Clear flag" button on flagged sessions that shows the 422 message if clearing is refused. Add the API call in `frontend/src/lib/sessions.ts`.

## 3. D-24: group sessions and the gym's own types
- **`config/offer.php`:** add `'group_session' => 'Group session'`.
- **New `Gym::activityTypeOptions(): array`:** returns the config types plus the gym's own types, keyed `custom_<slug>`. Use it wherever a gym's types are listed or checked:
  - `SessionController` options and the `type` rule.
  - `ServiceController` `activity_type` rule and its publish check (line 235).
  - `GymSetupController` options.
  - `PresentsBookings` `type_label`.
- **`GymSetupController`:** accept `custom_activity_types`, a list of up to 20 distinct names of up to 40 characters each, and return it in the setup payload.
- **Kept to built-in types only:** the public "what we offer" picker (`gyms.activity_types`) and the discovery filters, so gym search stays consistent across gyms.
- **Removed types:** labels for a removed custom type fall back to a readable form of the key. Add a `ponytail:` comment noting this; blocking removal of types still in use is the upgrade.
- **Frontend:**
  - `frontend/src/pages/gym/GymSettings.tsx`: an "Extra session types" list (add and remove a name) using the existing coss UI `Input` and `Button`.
  - Widen `SessionType` in `frontend/src/lib/sessions.ts` to `string`.
  - The session editor and service editor already read `options.activity_types`, so no change is needed there.

## Tests (backend, `php artisan test`)
- **`BookingTest`:**
  - A beginner (or an athlete with no level) is refused an intermediate session.
  - An advanced athlete can book a beginner session.
  - "All levels" is open to everyone.
  - The level endpoint needs `training.athletes.manage`.
- **`SessionChangeTest`:**
  - A work-hours change flags an affected session and leaves a still-valid one alone.
  - A new gym holiday flags sessions on that date.
  - Removing a staff member flags their sessions.
  - Athletes get a `flagged` notice.
  - `clear-flag` is refused while the problem remains, and works after it is fixed, sending a notice and promoting the waitlist.
  - Existing leave-flag tests still pass.
- **`SessionTest` / `ServiceTest` / `WorkspaceSetupTest`:**
  - Saving custom types.
  - A session or service using a custom type is accepted.
  - An unknown type is still refused.
  - `group_session` is accepted.

## Docs
- Copy this plan word for word to `docs/implementation-plan/training-gaps/plan.md`.
- In `docs/ANALYSIS - COMBAT SPORTS PLATFORM/05 - ROADMAP/phase-5/PHASE 5.md`, add short notes to R-42, R-46 and R-48 saying the gaps are now closed. Keep the requirement IDs in code comments, e.g. `(R-46, D-32)`.

## Verification
- `php artisan test` (full suite) from `backend/`.
- `pnpm build` and `pnpm lint` from `frontend/`.
- `php artisan migrate` on the local MySQL database.
- Manual check, if time allows, at `php artisan dev` + `pnpm dev`:
  - Set an athlete's level, then try to book an advanced session as them.
  - Change a coach's hours, check the session shows a flag and the athlete got a notice, then clear the flag.
  - Add a custom type and create a session with it.
- No browser is available here for automatic screenshots. I'll say so if I can't check the UI by hand.
