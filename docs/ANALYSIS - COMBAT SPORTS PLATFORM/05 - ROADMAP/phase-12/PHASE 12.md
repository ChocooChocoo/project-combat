# PHASE 12

**Staff IDs, clock-in location, paging, session sport and picture, QR fallback and pay after approval (feature change list, section A)**

[00 - START HERE](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · [05 - ROADMAP](../05%20-%20ROADMAP.md) · Previous: [PHASE 11](../phase-11/PHASE%2011.md) · Next: [06 - SCREENS BY ROLE](../../06%20-%20SCREENS%20BY%20ROLE/06%20-%20SCREENS%20BY%20ROLE.md)

**Status:** 🟨 Built 13 November 2026; waiting for a browser check

Paths starting `backend/` or `frontend/` point at the **rebuild** in the project folder, read 13 November 2026. The build plan with every task ticked is `docs/implementation-plan/p10-feature-set/PLAN.md`.

## The goal

Your client's feature change list, section A items 3 to 9, asked for staff government ID numbers, a location check on staff clock-in, paging on long lists, a sport list and a picture for sessions, QR check-in in more browsers, and a faster path from approving an application to taking the payment. Items 1, 2 and 10 to 21 were built in [PHASE 11](../phase-11/PHASE%2011.md). All of section B is out of scope.

## Items

| ID | What is true when it is done | Where in the rebuild | Status |
|---|---|---|---|
| R-121 | An employee record holds SSS (10 digits), PhilHealth (12) and Pag-IBIG (12) numbers, stored encrypted and saved as digits only (dashes and spaces are dropped). Only `hr.staff.manage` can edit them. `hr.staff.manage`, `payroll.runs.view` and the employee on their own record see full numbers; other staff who can view the record see `****` and the last 4 digits. `finance.payments.view` does not unmask them. | `employee_profiles.*_number`, `EmployeeProfile` casts, `User::canViewGovernmentIds`, `PresentsHr::maskedId`, `StaffController::update`, `EmployeeProfileController::update`, `EmployeeProfile` type in `frontend/src/lib/workspace.ts`, *Employee record → Employment* | 🟨 |
| R-122 | A gym can set a clock-in distance (50 to 5000 m). When it is set and the gym has a map pin, staff must share their location and be within that distance to clock in, or clock-in is refused. With no distance or no pin, clock-in works as before and any location sent is kept. Only clock-in is checked. The distance is shown beside the clock-in time. | `gyms.attendance_radius_m`, `staff_attendance.clock_in_*`, `App\Support\Geo`, `StaffAttendanceBook::clockIn`, `MyAttendanceController`, `frontend/src/lib/geolocation.ts`, Gym settings | 🟨 |
| R-123 | Payments, members, membership applications, requested bookings, staff, leave, overtime, offers and coach applications show 25 rows a page when the request has `?page=N`. Without `page`, the full list comes back unchanged, so other screens keep working. A filter or date change goes back to page 1; an emptied page steps back one. | `App\Support\Paging`, `frontend/src/components/page-controls.tsx`, the nine index controllers | 🟨 |
| R-124 | A session's sport is chosen from a list: the gym's own sports first, then the rest of the platform list, or *Not specified*. An old typed value is kept only while unchanged and shows as "(old entry)"; a new typed value is refused. | `SessionController::options` and `validated`, `session-editor.tsx` | 🟨 |
| R-125 | A session can have a thumbnail (image, up to the service photo size limit). It can also be applied to later scheduled sessions in the same series. A file is deleted only when no session still uses it. It shows in the schedule, the session page and the member timetable and details. | `training_sessions.thumbnail_path`, `SessionController::thumbnail` and `destroyThumbnail`, `TrainingSession::thumbnailUrl` | 🟨 |
| R-126 | Camera check-in works in browsers without a built-in QR reader. The `jsqr` reader loads only when needed, and the camera button shows whenever the browser can open the camera. | `frontend/src/lib/qr-scan.ts`, `camera-scanner.tsx`, `GymCheckIn.tsx` | 🟨 |
| R-127 | After approving an application, staff with `finance.payments.manage` get the record-payment dialog for that membership when money is owed, with the discount shown. Others see a toast to ask the front desk. | `GymApplications.tsx`, `RecordPaymentDialog` | 🟨 |

## Differences from the plan

- `StaffController::update` also validates the government IDs, as well as `EmployeeProfileController::update`, because the staff screen saves through it.
- The `EmployeeProfile` type is in `frontend/src/lib/workspace.ts`, not `hr.ts`.
- The coach applications controller is `backend/app/Http/Controllers/CoachApplicationController.php`.
- The Applications and Bookings screens also step back a page when an action empties the current page.
- The Overtime tab badge counts only pending rows on the current page.

## Decisions you made

Recorded in the plan's "User decisions" list: section A only; the `jsqr` fallback for QR; masked IDs for other viewers, with payroll viewers but not finance viewers seeing full numbers; opt-in paging at 25 rows; no fallback from a session picture to the service cover.

## Checked

- Backend: 699 tests pass, including new tests for government IDs, attendance location, paging, session sport and thumbnails.
- Frontend: `pnpm build` passes; `pnpm lint` has 0 errors (warnings only); 31 node tests pass, including a QR decode check.

## Not checked yet

- Browser render of every changed screen, including phone width and light mode.
- A real phone's GPS for the clock-in check.
- QR scanning with the camera in Firefox and in Chrome.
- Paging by hand with more than 25 rows on each screen.
- The three new migrations (`2026_11_26_090000`, `2026_11_26_100000`, `2026_11_26_110000`) on the real MySQL database; tests run on SQLite.
