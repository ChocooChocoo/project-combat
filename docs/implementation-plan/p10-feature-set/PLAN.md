# P10: Rest of Section A (gov IDs, clock-in location, pagination, session sport and thumbnail, QR fallback, pay after approval)

Status: built 13 Nov 2026. Phases 1 to 10 done. Checked: backend 699 tests passed; frontend `node --test` 31 passed; `pnpm build` ok; `pnpm lint` 0 errors (warnings only). NOT checked: browser checks for all phases; real phone GPS; QR scan in Firefox and Chrome; manual paging with more than 25 rows; MySQL migrations not yet applied (2026_11_26_090000, _100000, _110000). Source: the feature change list, section A items 3 to 9. P09 already built items 1, 2 and 10 to 21. All of section B is out of scope. Plan saved as `docs/implementation-plan/p10-feature-set/PLAN.md`. Browser run 13 Nov 2026 (Playwright, localhost, seeded Iron Will Boxing gym, MySQL migrations applied): gov IDs save encrypted and reload; clock-in refused 1855 m away and accepted at 25 m, distance shown on staff attendance; sport dropdown; thumbnail upload and schedule row; payments paging next, previous and range reset; approve opens the payment dialog with the voucher discount and the membership turns active; 390 px no overflow on 14 pages. One fix made: clock-in location now reads a fresh position (`maximumAge: 0`), a cached one judged staff by where they were a minute ago. Second browser run 13 Nov 2026 also passed: masked gov IDs for a view-only role (****7890 etc., read-only); thumbnail on member timetable and session details, and "apply to series" across 4 sessions; all 9 paged screens send page=1 and return 200; Payments and Offers page 2, Previous and range reset; jsQR decodes the real member check-in payload in Chromium. Not verified: the QR camera loop with a real camera (a fake camera rig gave only 2x2 frames), real phone GPS, the step-back-a-page rule (archived offers stay listed), and iOS Safari or Firefox.

## Context
I read the code. This is what is there today:
- **A#3 gov IDs.** SSS, PhilHealth and Pag-IBIG are only computed as payroll contributions (`app/Support/Payroll/Calculator.php:316-353`). The ID numbers are not stored anywhere. `EmployeeProfile` (`app/Models/EmployeeProfile.php`) holds the job and coach fields. `EmployeeProfileController::update` only lets `hr.staff.manage` edit employment fields. `PresentsHr::profilePayload(?EmployeeProfile, bool $canViewCompensation)` already leaves pay out for viewers who may not see it. It is called from `employeePayload` (used by `StaffController` index/store/show/update and `EmployeeProfileController`) and from `WorkController` (the employee's own My Work page). `User::canViewCompensation` = `hr.staff.manage | payroll.runs.view | finance.payments.view`. Frontend: `pages/gym/EmployeeRecord.tsx` `EmploymentSection` (line 241).
- **A#4 clock-in location.** `MyAttendanceController::clockIn` calls `StaffAttendanceBook::clockIn(Gym, User)` (`app/Support/StaffAttendanceBook.php:43`) with no location. Gyms already have `latitude`/`longitude`, set in `GymSetupController` and drawn with `LocationPicker` in `GymSettings.tsx:320`. The distance formula is copied twice, as private `distanceKm()` in `DiscoveryController.php:164` and `PublicGymController.php:293`. The clock button lives in `components/hr/my-attendance.tsx` (`clock()`, line 64). The `my-attendance` index returns `settings.grace_minutes`. The browser geolocation pattern with a 15 s guard is in `components/location-picker.tsx:40-70`.
- **A#5 pagination.** The backend already paginates customers, follow-ups, expenses, income, payroll runs, purchase requests and orders, suppliers, staff attendance, stock movements, announcements and notifications. These return the full list with no `page`: `PaymentController::index` (`limit(500)`), `MembershipController::index` (members and applications views), `BookingController::index`, `StaffController::index`, `LeaveController::index`, `OvertimeController::index` (`limit(200)`), `OfferController::index` and `CoachApplicationController::index` (which works out schedule conflicts for every row). Many other screens call the same endpoints without paging and need every row: `GymPayments` loads `memberships?view=members` for the payment dialog, `GymLeave` loads `leave-requests?status=approved` for the calendar, and `GymBookings` loads `bookings?status=booked` for the owing list. The frontend page control is `PageControls`, copied in `GymCustomers.tsx:701`, `GymPurchasing.tsx:1506`, `GymStaffAttendance.tsx:475` and `GymFinance.tsx:608`, plus a different one in `GymPayroll.tsx:361`. `lib/pagination.ts` has `pageMetaFrom`. `components/ui/pagination.tsx` is not used anywhere.
- **A#6 session sport.** `training_sessions.discipline` is free text: `'nullable','string','max:40'` in `SessionController::validated` (line 247) and a `TextField` in `components/schedule/session-editor.tsx:190`. The choices are in `config('verification.disciplines')` (10 keys). `ServiceController::rules` uses `Rule::in(array_keys(...))`. Seeders, factories and tests already use keys such as `boxing`. `PresentsBookings::sessionSummary` already falls back to the raw text when a value is not a known key.
- **A#7 session thumbnail.** Copy the service cover pattern: `ServiceController::cover` (line 116), `public` disk, `gym-services/{gym}`, and `config('offer.service_photos.max_kb')` = 4096. `SessionController::payload` spreads `attributesToArray()`. Member screens use `PresentsBookings::sessionSummary`.
- **A#8 QR.** `components/schedule/camera-scanner.tsx` only uses the browser's built-in `BarcodeDetector`. `GymCheckIn.tsx:92-94` hides the camera button when that is missing (Firefox, Safari, desktop Chrome on Windows). `qrcode` is already a dependency. Node tests import `.ts` files directly (Node 24) and cannot use the `@/` alias.
- **A#9 pay after approval.** `GymApplications.tsx:122` posts the decision, shows a toast and closes. The decision response already has `membership` with `balance`, `amount_charged` (already after the discount, see `OfferApplier`), `discount_amount` and `offer_name`. `RecordPaymentDialog` (`components/membership/payment-dialogs.tsx:16`) takes `memberships={[one]}`. Recording a payment needs `finance.payments.manage`. This item is frontend only; no backend gap found.
- The highest requirement id is R-120. New ids: R-121 to R-127.

## User decisions (defaults chosen; the user can change them before building)
- **Scope (confirmed with user).** Section A only. QR: add the jsQR fallback.
- **R-121 gov IDs.** New columns `sss_number`, `philhealth_number`, `pagibig_number` on `employee_profiles`. They are stored encrypted (Laravel `encrypted` cast) and saved as digits only: SSS 10 digits, PhilHealth 12, Pag-IBIG 12. Dashes and spaces typed by the user are removed before checking. Only `hr.staff.manage` can edit them.
  - Full numbers are shown to `hr.staff.manage`, to `payroll.runs.view`, and to the employee for their own record.
  - Anyone else who can see the staff record gets a masked value: `****` plus the last 4 digits.
  - `finance.payments.view` does **not** unmask them. This is stricter than pay.
  - TIN is not included.
- **R-122 clock-in location.** New setting `gyms.attendance_radius_m` (whole metres, 50 to 5000). Empty means no check, which is how every gym works today.
  - The check runs only when the radius is set **and** the gym has a map pin. If it runs and no location is sent, or the person is outside the radius, clock-in is refused with 422 on the field `location`.
  - If the gym has no pin or no radius, clock-in is allowed and any location sent is still saved.
  - Only clock-in is checked. Clock-out and desk entries made by HR are not checked.
  - Saved on the attendance row: `clock_in_latitude`, `clock_in_longitude`, `clock_in_distance_m`. Only the distance is sent back to the screen.
  - The setting is edited in Gym settings next to the map pin (`GymSetupController`, permission `gym_setup.settings.manage`), not in payroll settings, so it never depends on the gym's plan.
  - Known limit: the browser reports the location, so a determined person can fake it.
- **R-123 pagination.** It is opt-in: an endpoint pages only when the request includes `?page=N` (25 rows per page, with a `meta` block like `CustomerController`). Without `page`, the response is exactly what it is today, so every other screen keeps working. Pages covered: Payments, Members, Membership applications, Bookings (requested), Staff (employees tab), Leave requests, Overtime tab, Offers, Coach applications. Changing a filter or date range jumps back to page 1.
- **R-124 session sport.** A dropdown fed from `config('verification.disciplines')`, with the gym's own `gyms.disciplines` listed first, then the rest of the platform list. It stays optional ("Not specified" is allowed). No migration.
  - An old free-text value is accepted on update only if it is unchanged. The editor shows it as "<text> (old entry)".
  - A new free-text value gets 422.
- **R-125 session thumbnail.** Column `training_sessions.thumbnail_path`. Uploading and removing happen on their own endpoints after the session is saved (same as service covers). An optional "Use for upcoming sessions in this series" box applies the image to future scheduled sessions in the same series. A file is deleted only when no session still uses it. There is no fallback to the service cover photo.
- **R-126 QR fallback.** Add the `jsqr` package. It is loaded only when the browser has no `BarcodeDetector`, or has one that cannot read `qr_code`. The camera button shows whenever the browser can open the camera.
- **R-127 pay after approval.** After a successful approval:
  - If the viewer has `can(gym, 'finance.payments.manage')` and the membership balance is above 0, `RecordPaymentDialog` opens for that membership.
  - Otherwise a toast says "Approved. Ask the front desk to record the payment."
  - The dialog shows the voucher or promo discount when there is one.

## Shared rules (every phase)
- Keep the `(R-xxx)` comments. Put new ones on new code: one short comment per feature.
- No new permission strings. If a phase turns out to need one, follow the P09 rule: add it to `config/permissions.php` first, then run `php artisan app:sync-permissions`.
- Migrations are named `2026_11_26_HH0000_*.php` and must have a working `down()`. Run `php artisan migrate` locally on MySQL after the tests pass.
- Business rules go in `app/Support`. JSON shapes come from the existing `Presents*` traits or the controller `payload()`. Controllers stay thin.
- Tests go in `backend/tests/Feature/` and reuse setup traits (`Concerns/AttendanceSetup`, `GivesGymRoles`, `BookingSetup`, `MembershipSetup`). Frontend checks are `frontend/tests/*.test.mjs` with `node:test`, importing `../src/lib/*.ts` files that have no `@/` imports.
- Every phase ends with `php artisan test --filter=<NewTest>`, then the full `php artisan test`, then `pnpm build`, `pnpm lint` and `node --test` (from `frontend/`).
- Frontend: reuse coss UI from `src/components/ui/`. For visible new UI, load the `impeccable` and `antislop-ui` skills. Plain, short labels.
- Tick the boxes in this file as work lands. Do not commit.

---

## Phase 1: Government ID numbers on the employee record (A#3, R-121)
Goal: HR can store an employee's SSS, PhilHealth and Pag-IBIG numbers safely. Other staff viewers only see them masked.
- [x] Migration `2026_11_26_090000_add_government_ids_to_employee_profiles.php`: `text('sss_number')->nullable()`, `text('philhealth_number')->nullable()`, `text('pagibig_number')->nullable()`. Use `text` because encrypted values are long. `down()` drops all three.
- [x] `EmployeeProfile`: add the three fields to `#[Fillable]`. Add the casts `'sss_number' => 'encrypted'`, `'philhealth_number' => 'encrypted'`, `'pagibig_number' => 'encrypted'`.
- [x] `User`: add `canViewGovernmentIds(Gym|int $gym): bool` = `hasPermission($gym, 'hr.staff.manage', 'payroll.runs.view')`, next to `canViewCompensation`.
- [x] `PresentsHr`:
  - `profilePayload(?EmployeeProfile $profile, bool $canViewCompensation, bool $canViewGovIds = false)` adds `sss_number`, `philhealth_number`, `pagibig_number`. Each is the full value when `$canViewGovIds`, else `null` or `'****'.substr($value, -4)`. Write a private `maskedId(?string $value, bool $full): ?string`.
  - `employeePayload(Gym, User, bool $canViewCompensation, bool $canViewGovIds = false)` passes the flag on.
- [x] Callers pass the flag:
  - `StaffController` (index, store, show, update): `$viewer->canViewGovernmentIds($gym)`. In `show` and `update`, also allow it when `$viewer->is($user)`. In `index` there is one flag for all rows.
  - `EmployeeProfileController::update`: `$viewer->canViewGovernmentIds($gym) || $isSelf`.
  - `WorkController`: `true`, because it is always the employee's own record.
- [x] `EmployeeProfileController::update`, inside the `if ($hrManage)` block:
  - Before `validate`, for each of the three keys that is filled, `$request->merge([$key => preg_replace('/\D/', '', (string) $request->input($key))])`.
  - Add the rules `'sss_number' => ['nullable', 'digits:10']`, `'philhealth_number' => ['nullable', 'digits:12']`, `'pagibig_number' => ['nullable', 'digits:12']`.
  - Add messages such as "Enter the 10-digit SSS number." by passing a messages array to `validate`.
  - Self-edits without HR manage do not list these keys, so `validate()` drops them. No extra code is needed.
- [x] `frontend/src/lib/hr.ts`: add `sss_number?: string | null; philhealth_number?: string | null; pagibig_number?: string | null` to the `EmployeeProfile` type.
- [x] `EmployeeRecord.tsx` `EmploymentSection`:
  - Add a "Government IDs" group with three `TextField`s (`inputMode="numeric"`, hint "Numbers only; dashes are fine").
  - Editable only when `canEdit` (HR manage). When a value starts with `****`, show it as read-only text.
  - Send the three fields in the existing `save()` body, empty string as `null`.
  - Load the `impeccable` and `antislop-ui` skills before this UI change.
- [x] Tests, new `tests/Feature/EmployeeGovernmentIdTest.php`:
  - HR manager saves `12-3456789-0`, gets `1234567890` back, and the raw DB value (`DB::table('employee_profiles')->value('sss_number')`) is not the plain digits.
  - Wrong length gives 422.
  - A role with only `hr.staff.view` sees `****7890` in `GET staff/{user}`.
  - A role with `payroll.runs.view` sees the full value.
  - The employee sees their own full value in `GET my-work` (use the real route path from `routes/api.php`).
  - An employee posting their own IDs without HR manage does not change them.
  - Other gym gives 404.

Acceptance: `php artisan test --filter=EmployeeGovernmentIdTest` passes. The full `php artisan test`, `pnpm build`, `pnpm lint` and `node --test` pass. On screen, the owner enters the IDs, and a staff role with only view sees the masked values.

## Phase 2: Location check on staff clock-in (A#4, R-122)
Goal: when a gym sets a distance limit, staff can only clock in near the gym's map pin.
- [x] New `app/Support/Geo.php` with `public static function distanceKm(float $lat, float $lng, float $toLat, float $toLng): float`, using the exact formula from `DiscoveryController::distanceKm`. Replace the two private copies in `DiscoveryController` and `PublicGymController` with `Geo::distanceKm(...)` and delete the private methods. `GeoTest`, `GymSearchTest`, `RecommendationTest` and `PublicGymTest` must still pass.
- [x] Migration `2026_11_26_100000_add_attendance_location.php`:
  - `gyms.attendance_radius_m` `unsignedSmallInteger()->nullable()`.
  - `staff_attendance.clock_in_latitude` and `clock_in_longitude` as `decimal(10,7)->nullable()`.
  - `staff_attendance.clock_in_distance_m` as `unsignedInteger()->nullable()`.
- [x] `Gym` model: add `attendance_radius_m` to the fillable list and an `'integer'` cast. `StaffAttendance`: add the three new columns to `#[Fillable]`.
- [x] `GymSetupController`:
  - `setup()` returns `'attendance_radius_m' => $gym->attendance_radius_m`.
  - `rules()` adds `'attendance_radius_m' => ['sometimes', 'nullable', 'integer', 'between:50,5000']`.
  - Check that `update` saves through the same `$gym->update($data)` path as `inactivity_days`.
- [x] `StaffAttendanceBook::clockIn(Gym $gym, User $employee, ?float $lat = null, ?float $lng = null)`:
  - Check before `assertNoRow`.
  - If `$gym->attendance_radius_m` is set and the gym has a pin: refuse when coordinates are missing with `ValidationException::withMessages(['location' => 'Share your location to clock in. This gym checks that you are on site.'])`. Compute `$distance = (int) round(Geo::distanceKm(...) * 1000)` and refuse when it is above the radius with "You are about {d} m from the gym. Clock in within {r} m."
  - Otherwise compute the distance only when both the coordinates and the pin exist.
  - Save the coordinates and distance on `create`.
  - Add a `// ponytail:` comment saying the location comes from the browser and can be faked.
- [x] `MyAttendanceController::clockIn`:
  - Validate `latitude` as `['nullable', 'numeric', 'between:-90,90', 'required_with:longitude']` and `longitude` as `['nullable', 'numeric', 'between:-180,180', 'required_with:latitude']`.
  - Pass them to the book.
  - `index` `settings` adds `'location_check' => $gym->attendance_radius_m !== null && $gym->latitude !== null && $gym->longitude !== null` and `'radius_m' => $gym->attendance_radius_m`.
- [x] `PresentsAttendance::attendancePayload` adds `'clock_in_distance_m' => $row->clock_in_distance_m`. Coordinates are not sent.
- [x] Frontend, new `frontend/src/lib/geolocation.ts` with `currentPosition(): Promise<{ lat: number; lng: number }>`. Copy the 15 s guard and the error wording from `location-picker.tsx`, rejecting with an `Error` whose message is user-ready (blocked, no answer, not supported). Do not refactor `location-picker.tsx`.
- [x] `components/hr/my-attendance.tsx`:
  - In `clock('clock-in')`, when `data.settings.location_check` is true, call `currentPosition()` first and send `{ latitude, longitude }`.
  - If the location call fails, show its message in an error toast and do not post.
  - Show a short line under the button: "Clock-in checks you are within {radius_m} m of the gym."
  - Add `location_check` and `radius_m` to the type in `lib/attendance.ts`.
- [x] `GymSettings.tsx`, next to the `LocationPicker`:
  - `TextField name="attendance_radius_m"`, label "Staff clock-in distance (metres)", `type="number"`, min 50, max 5000, blank allowed. Description: "Leave empty to let staff clock in from anywhere."
  - When it is filled and there is no pin, also show "Set the map pin above, or the check is skipped."
  - Save `null` when blank.
- [x] `GymStaffAttendance.tsx` records list: when `clock_in_distance_m` is not null, show "{n} m from gym" in muted text next to the clock-in time. Add the field to the type.
- [x] Tests, new `tests/Feature/AttendanceLocationTest.php` using `AttendanceSetup`:
  - No radius: clock-in without coordinates gives 201.
  - Radius 100 with a pin and no coordinates: 422 on `location`, and no row is created.
  - Inside: about 30 m away gives 201, `clock_in_distance_m` is between 20 and 40, and the coordinates are saved.
  - Outside: about 500 m away gives 422, with no row.
  - Radius set but no pin: 201 without coordinates.
  - `PUT setup` accepts 100 and `null`, and rejects 10 and 6000 with 422.
  - `my-attendance` `settings.location_check` is true or false to match.

Acceptance: `php artisan test --filter=AttendanceLocationTest` passes, and so do `--filter=GeoTest`, `--filter=GymSearchTest` and `--filter=RecommendationTest`. The full suite, build, lint and `node --test` pass. Manually: set a radius, then clock in from the browser and allow location (localhost counts as a secure page). Blocking location shows the message.

## Phase 3: Combat sport dropdown in session details (A#6, R-124)
Goal: a session's sport is picked from the platform list, with the gym's own sports first. Old typed values keep working.
- [x] `SessionController::options` adds `'disciplines' => collect(array_unique([...array_intersect($gym->disciplines ?? [], array_keys($all)), ...array_keys($all)]))->map(fn ($key) => ['value' => $key, 'label' => $all[$key]])->values()`, where `$all = config('verification.disciplines')`. It is a list so the order stays fixed.
- [x] `SessionController::validated(Request $request, Gym $gym, bool $updating = false, ?string $currentDiscipline = null)`: the discipline rule becomes `['nullable', 'string', Rule::in([...array_keys(config('verification.disciplines')), ...array_filter([$currentDiscipline])])]`. `update` calls `$this->validated($request, $gym, true, $model->discipline)`. `store` stays as it is.
- [x] `frontend/src/lib/sessions.ts`: add `disciplines: { value: string; label: string }[]` to `SessionOptions`.
- [x] `session-editor.tsx`:
  - Replace the discipline `TextField` (line 190) with the existing `ChoiceField`, `name="discipline"`, `label="Combat sport"`.
  - Items: `{ value: '', label: 'Not specified' }`, then the old entry `{ value: legacy, label: \`${legacy} (old entry)\` }` when `session?.discipline` is set and not in `options.disciplines`, then `...options.disciplines`.
  - Save sends `form.discipline || null` (remove `.trim()`).
  - `chooseService` stays the same, because service disciplines are always keys.
- [x] Tests, add to `tests/Feature/SessionTest.php`:
  - `'Boxing class'` gives 422 on `discipline`.
  - `'judo'` gives 201.
  - `null` gives 201.
  - `options` lists the gym's disciplines first: set `gyms.disciplines = ['bjj']` and expect `disciplines.0.value` to be `bjj`, with all 10 present.
  - A session made with `TrainingSession::create([... 'discipline' => 'Old text'])` can be updated keeping `'Old text'`, but changing it to `'New text'` gives 422.

Acceptance: `php artisan test --filter=SessionTest` passes. The full suite, build and lint pass. In the editor, the gym's sports appear first in the dropdown.

## Phase 4: Session thumbnail (A#7, R-125)
Goal: staff can add a picture to a session. Staff and members see it in the session lists and details.
- [x] Migration `2026_11_26_110000_add_thumbnail_to_training_sessions.php`: `string('thumbnail_path')->nullable()->after('discipline')`. Do not add it to `#[Fillable]`; set it directly.
- [x] `TrainingSession::thumbnailUrl(): ?string` returns `$this->thumbnail_path ? Storage::disk('public')->url($this->thumbnail_path) : null`.
- [x] Routes in `routes/api.php`, next to the other session routes, both `->whereNumber('session')->middleware('permission:training.sessions.manage')`:
  - `POST sessions/{session}/thumbnail` → `SessionController::thumbnail`
  - `DELETE sessions/{session}/thumbnail` → `SessionController::destroyThumbnail`
- [x] `SessionController::thumbnail(Request, Gym, int $session)`:
  - Validate `photo` as `['required', 'image', 'max:'.config('offer.service_photos.max_kb')]` (reuse `ServiceController`'s message wording) and `apply_to_series` as `['nullable', 'boolean']`.
  - Return 409 if the session is cancelled.
  - Store to `training-sessions/{gym}` on `public`.
  - The sessions to change are this one, plus, when `apply_to_series` and `series_id` are set, sessions with the same `series_id`, `status = scheduled` and `starts_at >= now()`.
  - Collect their old paths, update them, then delete each old path that `TrainingSession::where('thumbnail_path', $old)->doesntExist()`.
  - Return `['session' => $this->payload($model->fresh())]`.
  - `destroyThumbnail` does the same with no new file, and also takes `apply_to_series`.
- [x] Payloads: `SessionController::payload` and `PresentsBookings::sessionSummary` add `'thumbnail_url' => $session->thumbnailUrl()`. `options` adds `'thumbnail_max_kb' => config('offer.service_photos.max_kb')`.
- [x] Types: `TrainingSession.thumbnail_url: string | null` in `lib/sessions.ts`, `SessionSummary.thumbnail_url: string | null` in `lib/bookings.ts`, and `SessionOptions.thumbnail_max_kb: number`.
- [x] `session-editor.tsx`, new "Thumbnail" card after "Session details":
  - New session: text "Save the session first, then add a thumbnail."
  - Existing session: preview `<img className="aspect-video w-full max-w-sm rounded-lg border object-cover" alt="">`, a file input `accept="image/*"` with a size check on the browser side using `thumbnail_max_kb`, a checkbox "Use for upcoming sessions in this series" only when `session.series_id`, and a "Remove" button.
  - Upload uses `FormData` exactly like `service-editor.tsx:242-251`. Keep the URL in local state.
- [x] Show it when present (`loading="lazy"`, `alt=""` because the name sits next to it):
  - Session rows in `GymSchedule.tsx` (`daySessions.map`, line 207): small 16:9 image at the start of the row, about `w-28`.
  - The `GymSession.tsx` header area.
  - `member/Timetable.tsx` rows (line 75).
  - The `member/SessionDetails.tsx` header (above the `h1`, line 80).
  - Do not add it to calendar blocks.
- [x] Tests, add to `SessionTest.php` with `Storage::fake('public')`:
  - Upload gives 200 and `session.thumbnail_url` is not null; the file exists.
  - Upload again: the old file is deleted.
  - `apply_to_series` sets the same path on future scheduled sessions in the series but not on past or cancelled ones.
  - Removing from one session keeps a file that another session still uses.
  - A non-image gives 422.
  - No `training.sessions.manage` gives 403.
  - A cancelled session gives 409.
  - The timetable/session details endpoint shows `thumbnail_url`.

Acceptance: `php artisan test --filter=SessionTest` passes. The full suite, build and lint pass. The image shows in the schedule and in the member timetable. The public storage link must exist (`php artisan storage:link`, already set up for services).

## Phase 5: QR scanning fallback (A#8, R-126)
Goal: camera QR check-in works in browsers without `BarcodeDetector`.
- [x] `pnpm add jsqr` (from `frontend/`). It ships its own types.
- [x] New `frontend/src/lib/qr-scan.ts`. It imports only `jsqr`, with no `@/` imports, so Node can test it. Export `decodeQr(data: Uint8ClampedArray, width: number, height: number): string | null`, which returns `jsQR(data, width, height, { inversionAttempts: 'dontInvert' })?.data ?? null`.
  - Before writing the import, check the interop in `frontend/`: `node -e "import('jsqr').then(m => console.log(typeof m.default, typeof m.default?.default))"`.
  - If the default import is the function, use `import jsQR from 'jsqr'`. If it is an object holding `.default`, unwrap it in one line and comment why.
- [x] `camera-scanner.tsx`:
  - Pick the reader inside `start()`. Native if `'BarcodeDetector' in window && (await BarcodeDetector.getSupportedFormats()).includes('qr_code')`.
  - Otherwise `const { decodeQr } = await import('@/lib/qr-scan')`, so the package is loaded only when needed.
  - Fallback loop every 250 ms: draw `video` onto one reused `canvas`, scaled so the width is at most 640, with `getContext('2d', { willReadFrequently: true })`. Call `getImageData` and `decodeQr`; on a hit, `onRead(code)`.
  - Keep the existing stop and cleanup code and the error messages.
  - Update the doc comment: R-52 native scanning, with the jsQR fallback for R-126.
- [x] `GymCheckIn.tsx:92-94`: show "Scan with camera" when `!!navigator.mediaDevices?.getUserMedia` instead of `'BarcodeDetector' in window`. Change the "unavailable" note to show only when there is no `getUserMedia` (no camera, or the page is not secure).
- [x] Test, new `frontend/tests/qr-scan.test.mjs`:
  - Make a QR with `QRCode.create('CHK-123456', { errorCorrectionLevel: 'M' })` from `qrcode`.
  - Turn `modules` into an RGBA `Uint8ClampedArray`: 4 px per module, a 4-module white border, black = 0, white = 255, alpha 255.
  - Assert that `decodeQr(...)` returns `'CHK-123456'`, and that a blank white image returns `null`.
  - Use a code that matches the real format in `app/Support/CheckInCode.php`.

Acceptance: `node --test` passes, including `qr-scan.test.mjs`. `pnpm build` passes and puts jsqr in its own chunk (check the build output). `pnpm lint` passes. Manually: scan a member QR in Firefox and in Chrome.

## Phase 6: Record payment right after approval (A#9, R-127)
Goal: front desk staff who approve an application go straight to recording the member's payment.
- [x] `GymApplications.tsx`:
  - Add state `const [paying, setPaying] = useState<Membership | null>(null)` and `const canPay = gym ? can(gym, 'finance.payments.manage') : false`.
  - In `decide()`, read the response: `const result = await api<{ membership: Membership }>(...)`.
  - On approve: if `canPay && Number(result.membership.balance) > 0`, call `setPaying(result.membership)` with the toast "Application approved. Record the payment now."
  - Else the toast is "Application approved. Ask the front desk to record the payment.", or "Application approved." when the balance is 0.
  - Reject stays as it is.
  - Render `{paying && <RecordPaymentDialog gymId={Number(gymId)} open memberships={[paying]} onClose={() => setPaying(null)} onSaved={() => { setPaying(null); setVersion((v) => v + 1) }} />}`. This is the same pattern as `GymBookings.tsx:76`.
- [x] `RecordPaymentDialog`: when there is exactly one membership and `Number(m.discount_amount) > 0`, show one muted line above "Still owed": `Includes {offer_name ?? 'discount'}: −{money(discount_amount)}`. Add `discount_amount` and `offer_name` to the `Membership` type in `lib/membership.ts` only if they are missing.
- [x] No backend change. Check that `MembershipLifecycleTest` and `OfferTest` already assert that the decide response has `balance` and `discount_amount`. If not, add one assert to the approve test in `MembershipLifecycleTest`.

Acceptance: build, lint and the full `php artisan test` pass. On screen:
- The owner approves an application with a voucher. The payment dialog opens with the amount filled in and the discount line shown. Saving records the payment and the member becomes active.
- A role with `members.memberships.approve` but no payments permission sees only the toast.

## Phase 7: Pagination, backend (A#5, R-123)
Goal: the listed endpoints return one page plus `meta` when `?page` is sent, and are unchanged otherwise.
- [x] New `app/Support/Paging.php`:
  - `public static function rules(): array` returns `['page' => ['sometimes', 'integer', 'min:1']]`.
  - `public static function rows(Builder|Relation $query, Request $request, ?int $limit = null): array` returns `[Collection $rows, ?array $meta]`.
  - No `page`: `[$limit ? $query->limit($limit)->get() : $query->get(), null]`.
  - With `page`: `paginate(25)`, giving `collect($page->items())` and `['current_page', 'last_page', 'per_page', 'total']`, the same keys as `CustomerController`.
  - One-line comment: opt-in so callers that need full lists keep working.
- [x] Apply to each index. Add `...Paging::rules()` to its `validate` (add a `Request $request` parameter where missing), swap the final `->get()` for `Paging::rows(...)`, and respond with `[... , ...($meta ? ['meta' => $meta] : [])]`:
  - `PaymentController::index` (`$limit = 500`)
  - `MembershipController::index` (the `restricted` lookup uses the page rows)
  - `BookingController::index`
  - `StaffController::index` (the `owner` key stays)
  - `LeaveController::index`
  - `OvertimeController::index` (`$limit = 200`)
  - `OfferController::index` (`plans` stays)
  - `CoachApplicationController::index` (conflicts are worked out only for the page rows)
  - Leave the existing ordering and the `DateRange` filters exactly as they are; they run before paging.
- [x] Tests, new `tests/Feature/PaginationTest.php`. For each of the 8 endpoints, seed 30 rows (factories or the existing setup traits) and check:
  - `?page=1` gives 25 rows and `meta.total = 30`.
  - `?page=2` gives 5 rows.
  - No `page` gives all 30 and no `meta` key (`assertJsonMissingPath('meta')`).
  - One date-range case: `payments?from=..&to=..&page=1` counts only rows in the range.
  - `page=0` gives 422.
  - Use a data provider if it stays readable. Otherwise write one short test per endpoint.

Acceptance: `php artisan test --filter=PaginationTest` passes, and so do `--filter=DateRangeFilterTest` and the full suite.

## Phase 8: Pagination, shared control and member, finance and training pages (A#5, R-123)
Goal: one shared page control; paging on Payments, Members, Applications and Bookings.
- [x] New `frontend/src/components/page-controls.tsx` exporting `PageControls`. Move it word for word from `GymCustomers.tsx:701`.
  - Replace the local copies in `GymCustomers`, `GymPurchasing`, `GymStaffAttendance` and `GymFinance` **only where the JSX is the same**. Check `GymFinance` first.
  - Leave `GymPayroll` alone; it uses a different meta type.
  - Do not use `components/ui/pagination.tsx`.
- [x] For each page below, add `const [page, setPage] = useState(1)` and `const [meta, setMeta] = useState<PageMeta | null>(null)`. Add `page=${page}` to the list request and `setMeta(pageMetaFrom(result, rows.length))`. Call `setPage(1)` wherever the filter, status or range changes, in the same handler. Put `<PageControls meta={meta} label="..." onPage={setPage} />` under the list. Counts shown to the user use `meta?.total ?? rows.length`.
  - `GymPayments.tsx`: the `payments` call only. The `memberships?view=members` and `owed-bookings` calls stay unpaged.
  - `GymMembers.tsx`: `memberships?view=members...`.
  - `GymApplications.tsx`: `memberships?view=applications...`. The pending count at line 163 uses `meta.total`.
  - `GymBookings.tsx`: the `status=requested` call only. The `status=booked` owing call stays unpaged.
- [x] Load `impeccable` and `antislop-ui` only if the layout changes beyond adding the control.

Acceptance: `pnpm build`, `pnpm lint` and `node --test` pass. Manually, with more than 25 rows (seed or create them): next and previous work, changing the date range goes back to page 1, and the payment dialog still lists every member who owes money.

## Phase 9: Pagination, HR and business pages (A#5, R-123)
Goal: paging on Staff, Leave, Overtime, Offers and Coach applications.
- [x] Same steps as Phase 8, for:
  - `GymStaff.tsx`: the `/staff` call. The Employees tab count and the summary stat use `meta.total`. The owner card stays outside the paging.
  - `GymLeave.tsx`: the main `leave-requests${query}` call (line 185). The `status=approved` calendar call stays unpaged.
  - `GymStaffAttendance.tsx`: the overtime tab request.
  - `GymOffers.tsx`: `offers`. `plans` still come from the same response.
  - `GymCoachApplications.tsx`: `coach-applications`.
- [x] After any approve, reject or archive action, re-fetch the current page (the existing `version` bump). If the page comes back empty and `page > 1`, call `setPage(page - 1)`.

Acceptance: build, lint and `node --test` pass. Manual check as in Phase 8 on each page.

## Phase 10: Docs (all items)
- [x] `docs/ANALYSIS - COMBAT SPORTS PLATFORM/`:
  - Findings and overview: R-121 to R-127.
  - Architecture: new columns `employee_profiles.*_number` (encrypted), `gyms.attendance_radius_m`, `staff_attendance.clock_in_*`, `training_sessions.thumbnail_path`; helpers `Geo`, `Paging`; the `jsqr` dependency; opt-in `?page`.
  - Diagrams: clock-in location check flow.
  - Screens by role: who sees masked IDs, the clock-in note, thumbnails.
  - Word list: "masked ID", "clock-in distance", "thumbnail".
- [x] Roadmap: add P10 as **Phase 12** in `05 - ROADMAP/05 - ROADMAP.md` with a new `05 - ROADMAP/phase-12/PHASE 12.md`, written like phase-11, marked built with the browser check pending. Add a progress row to `00 - START HERE.md` like the 12 Nov row.
- [x] `CLAUDE.md`: no new artisan command, so no change. Edit only if a line has become wrong.
- [x] Add a note in `docs/SETUPS/DEPLOY-HOSTINGER.md`: keep `APP_KEY` (gov IDs are encrypted with it), or set `APP_PREVIOUS_KEYS` when rotating.
- [x] Update this plan's Status line with test counts and what was not checked.

Acceptance: the docs match the code. Links work (use relative paths like the existing rows).

## Order and dependencies
1 → 2 → 3 → 4 (shares `session-editor.tsx` with 3) → 5 → 6 → 7 → 8 (needs 7, and edits `GymApplications.tsx` after 6) → 9 → 10.
Phases 1, 2 and 5 are independent and can run in any order.

## Risks
- **Encrypted IDs.** If `APP_KEY` changes on Hostinger, the values can no longer be read. Add a note in `docs/SETUPS/DEPLOY-HOSTINGER.md` (Phase 10) to keep the key, or set `APP_PREVIOUS_KEYS` when rotating it. Encrypted columns cannot be searched or made unique, which is fine here.
- **Geolocation.** It needs HTTPS or localhost. Indoor GPS can be off by 50 m or more, which is why the radius has a 50 m minimum and is the setting to adjust. The location can be faked, so the check discourages cheating but does not prove presence.
- **Pagination.** Any screen that later sends `page` by accident gets partial data. Only the listed calls send it. Counts must use `meta.total`.
- **jsqr import.** It is an old-style package, so the default import may differ between Node and Vite. Phase 5 has a one-line check before writing the import.
- **Legacy discipline text.** It only stays while it is unchanged. Once someone picks a key, the old text cannot be chosen again (intended).
- **Shared thumbnail files across a series.** They are deleted only when no session still uses them. The test covers this.

## Not in scope (add when asked)
- All of section B (freezing, coach diet and discipline suggestions, tournaments).
- TIN; gov IDs on payslips or remittance reports; employees editing their own IDs; gov IDs on My Work.
- Location check on clock-out or desk entries; a map of clock-in points; checks of how accurate the location reading is.
- Paging on setup lists (rooms, plans, services, roles, holidays, groups, stock items), admin lists and the member's own pages.
- A dropdown for the athlete-record discipline (`MemberRecord.tsx:475`); using the service cover photo when a session has no thumbnail.

## Verification
- Per phase: `php artisan test --filter=<NewTest>`, then `php artisan test` (from `backend/`). Then `pnpm build`, `pnpm lint` and `node --test` (from `frontend/`). Then `php artisan migrate` on local MySQL for phases 1, 2 and 4.
- Manual, with `php artisan dev` and `pnpm dev`:
  - Owner: enter gov IDs. A staff role with only view sees them masked.
  - Set the clock-in radius. Clock in as an employee with location allowed, then blocked.
  - Pick a sport from the dropdown; open an old session that has free text.
  - Upload a thumbnail with "series" ticked and check the member timetable.
  - Scan a QR in Firefox.
  - Approve an application with a voucher and record the payment in the dialog that opens.
  - Page through payments with a date range; check the payment dialog still lists all members who owe.
  - Check at 390 px and in light mode.
- Say clearly what was not checked: for example, a real phone's GPS, iOS Safari, or MySQL if it was not run.

## Execution (opus-plan-sonnet-build)
- After approval: save an exact copy of this plan to `docs/implementation-plan/p10-feature-set/PLAN.md` (one file, no phase split, per saved preference).
- Build with one Sonnet 5.5 (`model: sonnet`, `effort: medium`) general-purpose agent per phase, run in order. Orchestrator runs each phase's acceptance commands. No commits.

### Critical Files for Implementation
- backend/app/Http/Controllers/Gym/Concerns/PresentsHr.php
- backend/app/Support/StaffAttendanceBook.php
- backend/app/Http/Controllers/Gym/SessionController.php
- frontend/src/components/schedule/session-editor.tsx
- frontend/src/pages/gym/GymApplications.tsx
