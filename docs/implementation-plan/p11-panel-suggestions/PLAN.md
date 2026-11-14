# P11: Panel suggestions (names, guardian, location, walk-in kiosk, document checks, gym catalogue, hiring, Analytics, sparring, suggestions)

Status: built 14 Nov 2026. Phases 1 to 9 done. Checked: backend 758 tests passed (`phpunit.xml` now sets `memory_limit=512M`; the suite outgrew PHP's 128 MB default); frontend `node --test` 37 passed; `pnpm build` ok; `pnpm lint` 0 errors (old warnings only); all 7 `2026_11_27_*` migrations applied on local MySQL. Browser run 14 Nov 2026 (Playwright, localhost, seeded data): every phase's acceptance steps passed. One fix from the browser run: sparring candidates without a weight now rank after those with a known weight and show "Weight not recorded". Second browser run 14 Nov 2026 also passed: GymApply map pin filled city, street and zip with the toast (barangay left empty when Nominatim's name has no Cavite match); the 11th walk-in post in a minute showed the 429 message; a second payment for a paid walk-in returned 409; "Open members" suggestion link; "Ask someone with access to do this." for a role without `procurement.requests.manage`. NOT verified: real phone GPS, the "owner name not found" OCR flag on a real photo, the "Review the schedule" link (no low-use slot in demo data), and iOS Safari or Firefox. Old demo roles lack the new `hr.job_*` permissions until reseeded (granted by hand to Iron Will roles 5 and 9). Save as `docs/implementation-plan/p11-panel-suggestions/PLAN.md` (one file). Source: `docs/prompts/P10.md` (the mock-defense panel list). **Build this only after P10 (`docs/implementation-plan/p10-feature-set/PLAN.md`) is finished.** P10 is half-built in the working tree right now: its Phase 1 files are modified or untracked. P11 reuses two things P10 Phase 2 creates, `App\Support\Geo` and `frontend/src/lib/geolocation.ts` (`currentPosition()`). If either file is missing when you start Phase 2 here, create it exactly as P10 Phase 2 describes.

## Context
I read the code. This is what is there today:
- **Names.** `users.first_name` and `last_name` only (create_users migration). `AuthController::register` (`app/Http/Controllers/Auth/AuthController.php:24-38`) validates inline and calls `User::create($data)`. `User` fillable is at `app/Models/User.php:23`. `ProfileController::update` (`app/Http/Controllers/ProfileController.php:23-48`) validates the same names. `UserResource` (`app/Http/Resources/UserResource.php:18-19`). Frontend: `pages/SignUp.tsx:82-83` and `pages/Profile.tsx`. The user type is in `src/lib/auth.tsx:17-18`.
- **Birthdate and guardian.** Nothing anywhere (grep for `guardian` or `birth` finds nothing).
- **Location.** `GeoController::search` (`app/Http/Controllers/GeoController.php`) is a cached Nominatim forward search. Its route is `api.php:136`: signed in, `throttle:geo`. The `geo` limiter is keyed by user id or IP (`AppServiceProvider.php:26`). There is no reverse lookup. `components/location-picker.tsx` (`LocationPicker({ value, onChange })`) and `components/cavite-location-fields.tsx` exist; the second one loads `/locations/cavite` through a private `loadMunicipalities()`. `GymApply.tsx:366-370` draws both.
- **Gym application.** A gym application is a `gyms` row. `gym_documents` holds `type, path, original_name, mime, size` (`2026_11_04_100000_add_application_to_gyms.php:46-56`). Document types and the checklist live in `config/verification.php`. `GymDocumentController::store/show/destroy` uses the private `local` disk. `show` is also how the admin opens files: the `account:owner` middleware lets admins through (`EnsureAccountType.php:16`). `GymApplicationController::submit` (`:77-116`) notifies nobody. `GymReviewController::checklist` (`:54-67`) and `decide` (approve needs every checklist item true).
- **Admin review screen.** `pages/admin/ReviewApplication.tsx`: Documents card `:137-147`, Checklist card `:175-209` (Passed/Failed `ToggleGroup` `:186-202`, `mark()` `:72-83`), Decision card `:211+`. `components/application-summary.tsx` has `FileTile` (`:124-168`, image previews load the same document URL through `apiBlobUrl`) and `DocumentSummary` (`:171-205`). Upload UI: the `Documents` component in `pages/GymApply.tsx:505-651` (`upload()` `:512-540`).
- **Notices.** `App\Notifications\GymNotice(title, message, ?gymId, ?url)` is the generic database notice. The notifications menu and page show an "Open" link for `data.url`.
- **Membership form.** `config/membership.php` holds the health questions, 3 fixed waivers (`:17-21`) and an optional `medical_file` (`:25-29`). `MembershipApplicationController::form/store/rules`. `pages/member/MembershipApply.tsx` (gym policies text `:318-322`, waivers `:324+`, medical file `:296`). `gyms.policies` is a text field edited in `GymSettings.tsx:525-540` and shown in `GymPublic.tsx:394`.
- **Gym photos.** None. Services have `cover_photo_path` plus the `gym_service_photos` table (`ServiceController::cover/storePhoto/destroyPhoto` `:116-170`, `config/offer.php` `service_photos` `:35-38`, routes `api.php:333-335`). `PublicGymController::index/show` builds the cards and the public page. Gym cards are in `Home.tsx:937-980`.
- **Trial notice.** `PlanNotice` in `components/gym-workspace-layout.tsx:38-62` only shows when 7 days or fewer are left, or on the free plan. Owners with an approved gym land in the workspace after sign-in (`homePath`, `lib/gym-access.ts:32-38`).
- **Hiring.** `CoachApplicationController` plus the `coach_applications` table (R-117) is the pattern to copy: member applies outside the gym check, `GymNotice` to approvers, approval in a locked transaction adds an employee and a profile. `GymRoleAssigner::replace(actor, gym, employee, roleIds)` assigns roles safely.
- **Walk-in.** `gym_visits.user_id` is required (`2026_11_07_130000...:18`), so guests cannot go there. `SaleRecorder::record(Gym, User, array)` (`app/Support/Finance/SaleRecorder.php`) records an `income_type = walk_in` sale from `payer_name`. `CustomerSource::WalkIn` exists. `CustomerLinker::customerForEmail(Gym, ?string)`. Check-in screen: `pages/gym/GymCheckIn.tsx`. Polling hook: `src/hooks/use-polling.ts`.
- **Analytics label.** "Decision support" text appears at `components/gym-nav.tsx:36`, `lib/workspace.ts:323`, `GymDecisionSupport.tsx:78,105,142,149`, `components/decision-support/dashboard-parts.tsx:22` and `app/Support/Subscription.php:35`.
- **Athletes.** `athlete_profiles` has `current_rank` and `level` (levels in `config/offer.php:22-26`). There is no weight, height or win/loss. `AthleteRecordController::updateLevel` (`:67-78`) and `record()` (`:147-185`). The level UI is in `MemberRecord.tsx:276-295` through `AthleteRecordView`.
- **Rules engine.** `app/Support/DecisionSupport/Problems.php` (`falling_attendance`, `low_use_slot`, `high_no_show`, thresholds in `config/decision_support.php`). `Procurement\Restock::forGym` gives low-stock items with `suggested_quantity`. `POST purchase-requests` takes `{note, lines:[{stock_item_id, quantity, est_unit_cost}]}`. `POST announcements` takes `{title, body, kind:'announcement', audience_type:'all_members'}`. `DiscoveryController` shows the scoring style (config weights, `available` points, `reasons[]`).
- **Customers label.** "Add customer" appears in `GymCustomers.tsx:272`, `:789` and `:899`, plus the toasts `:772-773`. Customers are CRM leads, not members.
- **Tests and IDs.** Registration tests call `/api/auth/register` in `AccountTest.php:30` and `AccountTypeTest.php`. Membership payloads are built in `tests/Feature/Concerns/MembershipSetup.php:57` (and any other helper with `'signature_name'`). Document fixtures are in `GymApplicationTest.php:32-45`, `GymReviewTest.php:79,124` and `DatabaseSeeder.php:1592`. P10 uses migrations `2026_11_26_*` and R-121 to R-127. **P11 uses `2026_11_27_*` and R-128 to R-141.**

## Assumptions (user decisions and defaults; change before building if needed)
- **Location (R-130).** On sign-up, an optional "Use my location" button fills the city and province text fields through a new public reverse-lookup endpoint. Both values are saved on the user (nullable) and stay editable. On GymApply, dropping the map pin fills the Cavite city and barangay, and the street address only when those fields are empty and the names match the Cavite list.
- **Names (R-128).** Add `middle_name` (optional) and `suffix` (optional, one of `Jr.`, `Sr.`, `II`, `III`, `IV`, `V`). Add `User::fullName()`. Use it only in `UserResource`, the new notices and the new screens. The ~92 other places that join first and last name stay as they are.
- **Age (R-129).** The birthdate is required at sign-up. Under 7: refused. Owners must be 18 or older. Members aged 7 to 17 must give the guardian's name, phone and relationship, and tick consent. The birthdate and guardian cannot be edited later in Profile (out of scope). Existing users keep a null birthdate.
- **Admin notice (R-131).** Every platform admin gets a `GymNotice` when an application is sent, and again when a returned one is sent again.
- **Review (R-132).** The server records `admin_viewed_at` the first time an admin opens a document (not when a thumbnail preview loads). The checklist endpoint returns 422 until every required document is opened. Facility photos are exempt because their thumbnails are already shown. The checklist moves into a dialog behind a "Continue to checklist" button.
- **OCR (R-133).** Tesseract.js runs in the browser, for images only; PDFs skip it. The blur check (Laplacian variance) blocks blurry images before upload. OCR fills `expires_on` and flags `owner_name`, `gym_name` or `no_text` when it cannot find them. The owner can correct the date. The server rejects past dates. An expiry date is required at submit for the DTI certificate, the mayor's permit and the barangay clearance; it is optional for the owner ID, because the national ID has no expiry. Nothing is auto-rejected; the admin decides.
- **Gym catalogue (R-134).** Gym cover photo plus up to 12 gallery photos, managed in Gym settings. Cards with the cover photo in the Home gym finder; the gallery on the public gym page.
- **Policies and waiver (R-135).** The owner uploads up to 5 PDF files ("Policies and waiver") in Gym settings. They show on the public gym page and the apply page. When the gym has policy text or files, the member must tick "I accept {gym}'s policies and waiver" (`waivers.gym_terms`). No separate waiver-text column.
- **Medical clearance (R-136).** Required on the membership application (`config('membership.medical_file.required') = true`). Walk-in health answers are optional.
- **Trial notice (R-139).** The owner sees the banner on every workspace page during the whole trial, with the days left. It turns to the warning style at 7 days or fewer.
- **Walk-in (R-137).** A public kiosk page at `/gyms/{id}/walk-in` (no sign-in, its own minimal layout). The visitor enters name, phone, optional email, optional health answers, the waivers and a typed signature. This creates or reuses a CRM customer (`source = walk_in`) and a `walk_ins` row. The front desk sees today's walk-ins on Check-in and records payment through `SaleRecorder`. The page is throttled. Walk-ins are not counted in attendance analytics (they have no user).
- **Hiring (R-138).** One flow. The owner posts openings (title, optional starter role, description, requirements, open or closed). Signed-in member accounts apply with experience text, a credentials file and a valid ID file. An approver approves (adds them as employee with the posting's title and role) or rejects with a reason. New permissions: `hr.job_postings.view|manage`, `hr.job_applications.view|approve`. The HR template gets `job_postings.view`, `job_postings.manage` and `job_applications.view`; approve stays with the owner, like coach applications. Coach applications stay separate.
- **Analytics.** Rename the UI text only. Permission strings, routes, folders and file names stay `decision_support` / `decision-support`.
- **Sparring (R-140).** New `athlete_profiles` columns: `weight_kg`, `height_cm`, `wins`, `losses`, `draws`, `experience_years`. "Rank or skill" = the existing `level`; `current_rank` stays display-only. The score uses weight, height, level, experience and win rate (config weights, reasons). It is shown on the member record page (one athlete at a time), not as a new Analytics view.
- **Suggestions (R-141).** A read-only endpoint lists suggestions. Each action is either a link, or a prepared call to an **existing** endpoint (purchase request, announcement) that the owner confirms in a dialog. No new write endpoints, nothing automatic.
- **"Add member" label.** Rename the button, dialog title and toasts. The dialog still adds a CRM lead, so its description must say they become a member once their membership is linked (otherwise the label misleads).

## Summary
- Small, separate phases, ordered from cheapest to biggest. Each phase is one Sonnet pass.
- No new backend libraries. One new frontend dependency, `tesseract.js`. It is loaded with `await import('tesseract.js')` only when an image document is uploaded, so the main bundle and the `node --test` imports do not load it.
- Reuse first: `GymNotice` for every new notice, `SaleRecorder` for walk-in payment, `GymRoleAssigner` for hiring roles, the service-photo upload pattern for gym photos, `Problems` and `Restock` for suggestions, `CoachApplicationController` as the template for job applications.
- Business rules go in `app/Support` (`Guardian`, `DecisionSupport\Sparring`, `DecisionSupport\Suggestions`). JSON shapes go in controller `payload()` methods or one new trait, `PresentsGymMedia`.
- Pure frontend helpers (date reading, name check, blur math, age, Cavite matching) live in `src/lib/*.ts` files with **no `@/` imports**, so `node --test` can import them.

## Shared rules (every phase)
- Keep the existing `(R-xxx)` comments. Add one short `(R-1xx)` comment per new feature.
- New permission strings go into `config/permissions.php` first. The migration that needs them calls `PermissionCatalog::sync()`, like `2026_11_25_130000_create_coach_applications.php`. Locally, also run `php artisan app:sync-permissions`.
- Migrations are named `2026_11_27_HH0000_*.php` and must have a working `down()`. Run `php artisan migrate` on local MySQL after tests pass.
- SQL that differs between SQLite and MySQL goes through `App\Support\Sql`. Dates use the Asia/Manila app timezone (`today()`, `now()`).
- Frontend: reuse `src/components/ui/*` (Dialog, AlertDialog, Checkbox, Select, Badge, Card, Alert) and `TextField`. Plain, short labels. For visible new UI, load the `impeccable` and `antislop-ui` skills.
- Each phase ends with `php artisan test --filter=<NewTest>`, the full `php artisan test` (from `backend/`), then `pnpm build`, `pnpm lint` and `node --test` (from `frontend/`). Everything must pass.
- Tick the boxes in this file as work lands. Do not commit.

---

## Phase 1: Labels, trial notice, admin notice (R-131, R-139)
Goal: quick wins. "Analytics" wording, "Add member" wording, an always-on trial banner, and admins told about new applications.
- [x] Rename "Decision support" to "Analytics" in UI text only:
  - `gym-nav.tsx:36` label `'Analytics'`.
  - `lib/workspace.ts:323` `decision_support: 'Analytics'`.
  - `GymDecisionSupport.tsx`: line 78 "You do not have permission to view analytics.", line 105 `title="Analytics"`, lines 142 and 149 aria labels "Analytics view" / "Analytics views".
  - `dashboard-parts.tsx:22` "Could not load analytics".
  - `app/Support/Subscription.php:35` `'decision_support' => 'Analytics'`.
  - Then grep the backend and frontend tests for `Decision support` and update any match.
- [x] `GymCustomers.tsx`:
  - Button (`:272`, `:899`) and `DialogTitle` (`:789`) become "Add member".
  - Toasts (`:772-773`) become "Member added and linked to their application." / "Member added."
  - The dialog description becomes: "Add someone who walked in, was referred or asked online. They are kept as a lead and become a member once their membership is linked. A matching email links a waiting application automatically."
  - Leave the page title "Customers" and the empty-state text as they are.
- [x] `components/gym-workspace-layout.tsx` `PlanNotice`:
  - Show it when `sub.status === 'free'` **or** `sub.status === 'trial'` **or** (`paid` and `left <= 7`).
  - Trial title: `Free trial: ${left} ${left === 1 ? 'day' : 'days'} left`.
  - Trial body: "Every module is open until {gymDate(sub.period_ends_at)}. Choose a plan before then to keep the modules you use." Use `gymDate` from `src/lib/membership.ts`.
  - Variant: `'warning'` when `left <= 7`, else `'info'`. Keep the "See plans" button.
- [x] `GymApplicationController::submit`. After the `forceFill(...)->save()`, keep `$wasReturned = $gym->getOriginal('status') === GymStatus::Returned` (read it before the save). Then:
  ```php
  User::where('is_platform_admin', true)->get()->each(fn (User $admin) => $admin->notify(new GymNotice(
      $wasReturned ? 'Gym application sent again' : 'New gym application',
      "{$gym->name} from {$gym->applicant->first_name} {$gym->applicant->last_name} is waiting for review.",
      null, "/admin/applications/{$gym->id}",
  )));
  ```
  Add `// (R-131)`.
- [x] Tests, new `tests/Feature/AdminApplicationNoticeTest.php`:
  - Submitting a complete Tier 1 application gives every admin (make 2) one notice with `data.url = /admin/applications/{id}`. The applicant and a plain owner get none.
  - A returned application sent again gives a notice titled "Gym application sent again".
  - Copy the upload and submit helpers from `GymApplicationTest`.

Acceptance: `php artisan test --filter=AdminApplicationNoticeTest` passes; full suite, build, lint and `node --test` pass. On screen: the sidebar says Analytics, the owner of a trial gym sees "Free trial: N days left" on every workspace page, and the admin bell shows the new application.

## Phase 2: Registration: names, birthdate, guardian, location (R-128, R-129, R-130)
Goal: sign-up collects the full name, birthdate and (for minors) guardian; location can be filled from the device; the GymApply pin fills the address.
- [x] Migration `2026_11_27_090000_add_identity_fields_to_users.php`:
  - `middle_name` string(100) nullable after `first_name`.
  - `suffix` string(10) nullable after `last_name`.
  - `birthdate` date nullable.
  - `guardian_name` string(150), `guardian_phone` string(30), `guardian_relationship` string(60), all nullable.
  - `guardian_consent_at` dateTime nullable.
  - `city` string(120) and `province` string(120), nullable.
  - `down()` drops all of them.
- [x] `User`:
  - Add `middle_name`, `suffix`, `birthdate`, `city`, `province` to `#[Fillable]`. The guardian columns are saved with `forceFill`.
  - Cast `'birthdate' => 'date'` and `'guardian_consent_at' => 'datetime'`.
  - Constants `public const SUFFIXES = ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'];`, `MIN_AGE = 7`, `ADULT_AGE = 18`.
  - `public function fullName(): string` returns `trim(preg_replace('/\s+/', ' ', "{$this->first_name} {$this->middle_name} {$this->last_name} {$this->suffix}"))`.
- [x] New `app/Support/Guardian.php`:
  - `public static function rules(): array` returns the `birthdate` and guardian rules:
    - `'birthdate' => ['required', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01']`
    - `'guardian_name' => ['nullable', 'string', 'max:150']`
    - `'guardian_phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[\d\s()-]{7,30}$/']`
    - `'guardian_relationship' => ['nullable', 'string', 'max:60']`
    - `'guardian_consent' => ['nullable', 'boolean']`
  - `public static function check(array $data, AccountType $type): array`:
    - `$age = Carbon::parse($data['birthdate'])->age`.
    - Below `MIN_AGE`: throw `ValidationException::withMessages(['birthdate' => 'Children under 7 cannot have an account.'])`.
    - Owner below `ADULT_AGE`: throw `['birthdate' => 'Gym owners must be 18 or older.']`.
    - Member below `ADULT_AGE`: collect errors for each blank guardian field ("Add your parent or guardian's name.", "…phone number.", "…relationship to you.") and `guardian_consent` not true ("Your parent or guardian must agree before you can sign up."). Throw them together.
    - Return the guardian columns to save: the values plus `guardian_consent_at => now()` for minors, all `null` for adults.
  - Add a `(R-129)` comment.
- [x] `AuthController::register`:
  - Add rules `'middle_name' => ['nullable', 'string', 'max:100']`, `'suffix' => ['nullable', Rule::in(User::SUFFIXES)]`, `'city'` and `'province'` (`['nullable', 'string', 'max:120']`), plus `...Guardian::rules()`.
  - After validate: `$guardian = Guardian::check($data, AccountType::from($data['account_type']))`.
  - `User::create(Arr::except($data, ['guardian_name', 'guardian_phone', 'guardian_relationship', 'guardian_consent']))`, then `$user->forceFill($guardian)->save()`, then issue the code as now.
- [x] `ProfileController::update`: add the `middle_name`, `suffix`, `city` and `province` rules (same as above). Birthdate and guardian are not editable here.
- [x] `UserResource`: add `middle_name`, `suffix`, `full_name` (`$this->resource->fullName()`), `birthdate` (`?->toDateString()`), `city`, `province`.
- [x] `AthleteRecordController::record`: in the `athlete` block, add `'is_minor' => $user->birthdate?->age < User::ADULT_AGE` (false when null). When the viewer may see health (`$withHealth`), also add `'guardian' => $user->guardian_name ? ['name' => …, 'phone' => …, 'relationship' => …] : null`.
- [x] `GeoController::reverse(Request $request): JsonResponse`:
  - Validate `lat` (`required|numeric|between:-90,90`) and `lng` (`required|numeric|between:-180,180`).
  - Cache key `'geo-reverse:'.round($lat, 3).','.round($lng, 3)`, kept for one day.
  - Call `https://nominatim.openstreetmap.org/reverse` with `format=jsonv2`, `lat`, `lon`, `zoom=18`, `addressdetails=1`, using the same User-Agent and 5 s timeout as `search`.
  - Map `address` to:
    - `city` = `city ?? town ?? municipality`
    - `province` = `province ?? state`
    - `barangay` = `quarter ?? village ?? suburb ?? neighbourhood`
    - `street` = `trim(house_number.' '.road)`
    - `postcode`
    - `label` = `display_name`
  - Return `['place' => [...]]`. On failure, the same 502 `unavailable()`.
  - Route, public, next to `public/landing`: `Route::get('geo/reverse', [GeoController::class, 'reverse'])->middleware('throttle:geo');`. It must be outside `auth:sanctum` because sign-up is a guest page.
- [x] Frontend `src/lib/validation.ts` (it has no `@/` imports, keep it that way):
  - `export const SUFFIXES = ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V']`.
  - `export function ageOn(birthdate: string, today: string): number` (whole years, string dates `YYYY-MM-DD`).
  - `export const MIN_AGE = 7, ADULT_AGE = 18`.
- [x] New `src/lib/places.ts` (no `@/` imports):
  - `export function matchCavitePlace(place: { city?: string | null; barangay?: string | null }, municipalities: { name: string; barangays: string[] }[]): { city: string; barangay: string }`.
  - Compare in lower case after removing `"city of "` and `" city"` and extra spaces. Return empty strings when nothing matches.
- [x] `components/cavite-location-fields.tsx`: export the existing `loadMunicipalities` (add `export`, no other change).
- [x] `SignUp.tsx`:
  - Name grid: First name, Middle name (optional), Last name, Suffix (`Select` with "None" plus `SUFFIXES`).
  - `TextField name="birthdate" type="date" max={today}` (required).
  - Keep `birthdate` in state. When `account_type` is member and `ageOn(...)` is between 7 and 17, show a "Parent or guardian" fieldset: guardian name, phone (`validate={phoneRule}`), relationship, and a `Checkbox name="guardian_consent"` labelled "My parent or guardian agrees to this account and to my training."
  - When the age is under 7, show an inline error and disable submit.
  - When the age is under 18 and the account type is owner, show "Gym owners must be 18 or older."
  - The account type `RadioGroup` needs `onValueChange` to keep it in state.
  - Optional "Your area" row: City and Province `TextField`s plus a "Use my location" button:
    - Call `currentPosition()` (P10 `src/lib/geolocation.ts`), then `api('/geo/reverse?lat=…&lng=…')`.
    - Fill only empty fields.
    - On error, show the message under the row. Never block sign-up.
  - Send `guardian_consent` as `'1'` or omit it.
- [x] `Profile.tsx`:
  - Add Middle name, Suffix, City and Province to the details form.
  - Show Birthdate read-only when set ("Birthdate: {gymDate}").
  - Add the new fields to the `User` type in `src/lib/auth.tsx`.
- [x] `GymApply.tsx` `LocationPicker onChange`:
  - After `set('location', value)`, call `api<{ place }>('/geo/reverse?lat=&lng=')`.
  - Then `matchCavitePlace(place, await loadMunicipalities())`.
  - Fill `city` and `barangay` only if `form.city` is empty, and `address_line` from `place.street` only if it is empty.
  - Toast "Address filled from the map pin. Check it." when anything was filled. Ignore errors silently.
- [x] Update the test helpers that register users:
  - `AccountTest.php:30` and the register calls in `AccountTypeTest.php` get `'birthdate' => '1995-05-10'`.
  - Grep `auth/register` in `tests/` for any others.
- [x] Tests, new `tests/Feature/RegistrationIdentityTest.php`:
  - Middle name and `Jr.` are saved, and `GET /api/me` returns `full_name` "Ana Maria Reyes Jr.".
  - Suffix `Esq.` gives 422.
  - Missing birthdate gives 422.
  - A 6-year-old gives 422 on `birthdate`.
  - A 17-year-old owner gives 422 on `birthdate`.
  - A 12-year-old member without guardian gives 422 on `guardian_name`, `guardian_phone`, `guardian_relationship` and `guardian_consent`.
  - A 12-year-old member with a guardian gives 201, with `guardian_consent_at` set.
  - An adult member who sends guardian fields gets them saved as null.
  - Exactly 7 today is accepted (build the date with `today()->subYears(7)`).
  - `PUT /api/me` saves middle name, suffix, city and province.
- [x] Tests: add to `tests/Feature/GeoTest.php`:
  - `Http::fake(['nominatim.openstreetmap.org/reverse*' => Http::response(['display_name' => 'x', 'address' => ['town' => 'Silang', 'province' => 'Cavite', 'village' => 'Biga']])])`. A guest `GET /api/geo/reverse?lat=14.2&lng=120.97` returns `place.city = Silang`, `place.province = Cavite`, `place.barangay = Biga`.
  - `lat=200` gives 422.
  - `Http::fake` returning 500 gives 502.
- [x] Node test, new `frontend/tests/registration.test.mjs`:
  - `ageOn('2019-11-13', '2026-11-13') === 7` and `ageOn('2019-11-14', '2026-11-13') === 6`.
  - `matchCavitePlace({ city: 'City of Dasmariñas' }, [{ name: 'Dasmariñas', barangays: [...] }])` matches.
  - An unknown city gives empty strings.

Acceptance: `php artisan test --filter=RegistrationIdentityTest` and `--filter=GeoTest` pass; full suite, build, lint and `node --test` pass. Manually: sign up as a 12-year-old member (the guardian fields appear), as a 17-year-old owner (blocked), and use "Use my location" on localhost.

## Phase 3: Admin review: open every document, checklist in a dialog (R-132)
Goal: Passed/Failed cannot be used until the admin has opened the documents; the checklist lives in a dialog.
- [x] Migration `2026_11_27_100000_add_review_fields_to_gym_documents.php`: `dateTime('admin_viewed_at')->nullable()`. (Phase 4 adds its own columns in its own migration.)
- [x] `config/verification.php`: add `'must_open' => false` to `facility_photo` only, with the comment "Thumbnails are enough; every other file must be opened before the checklist (R-132)."
- [x] `App\Support\Verification::unopened(Gym $gym): Collection` returns the gym's documents whose `type` is in `self::typesFor($gym->tier)`, whose `config("verification.documents.{type}.must_open", true)` is true, and whose `admin_viewed_at` is null.
- [x] `GymDocumentController::show(Request $request, Gym $gym, GymDocument $document)`:
  - After the policy check, mark the document opened on the first real open:
    ```php
    if ($request->user()->is_platform_admin && ! $request->boolean('preview') && $document->admin_viewed_at === null) {
        $document->forceFill(['admin_viewed_at' => now()])->save();
    }
    ```
  - Add the `Request` parameter to the signature.
- [x] `GymReviewController::checklist`: before saving, if `Verification::unopened($gym)->isNotEmpty()`, throw `ValidationException::withMessages(['checklist' => 'Open every document before you mark the checklist.'])`.
- [x] Frontend `lib/gyms.ts`:
  - Add `admin_viewed_at?: string | null` to `GymDocument` and `must_open?: boolean` to the `Requirements.documents` value type.
  - Add `export function mustOpen(app: Application, requirements: Requirements): GymDocument[]`, the same rule as the server.
- [x] `application-summary.tsx`:
  - `FileTile` previews call `apiBlobUrl(documentUrl(...) + '?preview=1')`.
  - New optional props `onOpened?: (id: number) => void` and `opened?: boolean`. Call `onOpened` after `apiFile` succeeds. When `opened` is defined, show a small `Badge variant="success"` "Opened" or `variant="outline"` "Not opened".
  - `DocumentSummary` takes `onOpened` and `openedIds?: Set<number>` and passes them through.
- [x] `ReviewApplication.tsx`:
  - State `opened: Set<number>`, initialised from `app.documents.filter(d => d.admin_viewed_at)`.
  - `const toOpen = mustOpen(app, requirements).filter(d => !opened.has(d.id))`.
  - Replace the Checklist card body with:
    - the progress line "`{passed} of {items.length}` checks passed",
    - "`{n}` documents still to open" when `toOpen.length > 0`,
    - a `Button` "Continue to checklist", disabled while `toOpen.length > 0 || !open`.
  - The button opens a `Dialog` (`components/ui/dialog.tsx`) holding the existing Passed/Failed list and `errors.checklist`, plus a footer with "Done" and, when `allPassed`, the "Approve and go live" button calling `decide({ decision: 'approve' })`.
  - The Decision card stays (Approve still disabled until all passed; Return and Reject unchanged).
  - In the Documents card description: "Open each file. The checklist unlocks when every file has been opened."
  - Decided applications open the dialog read-only (`ToggleGroup disabled={!open}` as now), so the "Continue" button stays enabled when `!open`.
- [x] Tests, add to `tests/Feature/GymReviewTest.php`:
  - An admin `GET .../documents/{id}` sets `admin_viewed_at`. `?preview=1` does not. The applicant opening it does not.
  - With an unopened `dti` document, `PUT checklist` gives 422 on `checklist`. After the admin opens it, 200.
  - An unopened `facility_photo` on Tier 2 does not block the checklist.
  - Existing tests still pass (their gyms have no documents, or the test opens them first: update `test_returned_application_can_be_fixed_and_sent_again` only if it calls the checklist).

Acceptance: `php artisan test --filter=GymReviewTest` passes; full suite, build and lint pass. On screen: the "Continue to checklist" button unlocks after the last file is opened, and the dialog marks the checks and approves.

## Phase 4: Document checks: blur, OCR expiry, name flags (R-133)
Goal: blurry photos are refused before upload; images are read in the browser to fill the expiry date and flag missing names; the server refuses expired dates.
- [x] `cd frontend && pnpm add tesseract.js`. This is the only new dependency. Check the current API with context7 (`createWorker('eng')`, `worker.recognize(file)`, `worker.terminate()`).
- [x] Migration `2026_11_27_110000_add_ocr_fields_to_gym_documents.php`: `date('expires_on')->nullable()` and `json('ocr_flags')->nullable()`.
- [x] `GymDocument`: add `expires_on` and `ocr_flags` to `#[Fillable]`. Casts: `'expires_on' => 'date:Y-m-d'`, `'ocr_flags' => 'array'`, `'admin_viewed_at' => 'datetime'`.
- [x] `config/verification.php` documents:
  - `dti`: `'expiry' => 'required', 'match' => ['owner', 'gym']`
  - `sec`: `'match' => ['gym']`
  - `mayors_permit`: `'expiry' => 'required', 'match' => ['gym']`
  - `barangay_clearance`: `'expiry' => 'required', 'match' => ['gym']`
  - `owner_id`: `'expiry' => 'optional', 'match' => ['owner']`
  - Add one comment line explaining the keys.
- [x] `GymDocumentController::store`:
  - After the file rules, validate `'expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today']` (message "This document has expired. Upload a current one."), `'ocr_flags' => ['nullable', 'array', 'max:3']` and `'ocr_flags.*' => [Rule::in(['owner_name', 'gym_name', 'no_text'])]`.
  - Save `expires_on` only when `config("verification.documents.{$type}.expiry")` is set (else null). Save `ocr_flags` as given or null.
  - Add `// ponytail: flags come from the browser and can be faked; they only guide the admin.`
- [x] New `GymDocumentController::update(Request $request, Gym $gym, GymDocument $document): JsonResponse`:
  - `Gate::authorize('update', $gym)`.
  - Abort 422 when the type has no `expiry`.
  - Validate `expires_on` `['nullable', 'date_format:Y-m-d', 'after_or_equal:today']`, save it, return `['document' => $document]`.
  - Route: `Route::put('{gym}/documents/{document}', [GymDocumentController::class, 'update'])->scopeBindings();` in the `gym-applications` group.
- [x] `GymApplicationController::submit`: after the document counts, for each document of the tier whose `expiry === 'required'`:
  - missing `expires_on` gives `$missing["expiry.{$doc->id}"] = "Add the expiry date of the {label}."`;
  - `expires_on < today()` gives "The {label} has expired. Upload a current one."
- [x] New `frontend/src/lib/document-check.ts` (no `@/` imports):
  - `export const BLUR_MIN = 80`, marked `// ponytail: starting value; tune with real phone photos (raise if blurry ones pass)`.
  - `export function laplacianVariance(gray: ArrayLike<number>, width: number, height: number): number`: the 3×3 Laplacian `[0,1,0,1,-4,1,0,1,0]` over the inner pixels, returning the variance.
  - `export async function blurScore(file: File): Promise<number>`: `createImageBitmap(file)`, draw on a canvas scaled so the longer side is at most 800 px, read luminance `0.299r + 0.587g + 0.114b`, return `laplacianVariance(...)`.
  - `export async function readText(file: File): Promise<string>`: `const { createWorker } = await import('tesseract.js')`, recognize, `terminate()` in `finally`.
  - `export function findExpiryDate(text: string): string | null`:
    - Uppercase the text.
    - Collect dates with index for these forms: `YYYY-MM-DD` (also `/` and `.`), `MM/DD/YYYY` (also `-` and `.`), `Month D, YYYY`, and `D Month YYYY` (3-letter month prefix, optional dot).
    - Drop impossible dates.
    - If `/VALID\s*(UNTIL|THRU|THROUGH|TO)|EXPIR|VALIDITY|UNTIL/` matches, return the first date after that index. Otherwise return the latest date.
    - Return ISO `YYYY-MM-DD`.
  - `export function nameFlags(text: string, checks: string[], names: { owner: string; gym: string }): string[]`:
    - Normalise with `s.toUpperCase().replace(/[^A-Z0-9]+/g, ' ').trim()`.
    - Normalised text shorter than 20 characters gives `['no_text']`.
    - `'owner'` check: the owner's last name must appear as a whole word, else `'owner_name'`.
    - `'gym'` check: at least half of the gym name's words that are 3 or more letters long and not in `GYM FITNESS CENTER CENTRE THE AND INC CORP CLUB` must appear, else `'gym_name'`.
- [x] `GymApply.tsx` `Documents.upload`, inside the per-file loop, after `fileProblem`:
  - If `file.type.startsWith('image/')`:
    - `if (await blurScore(file) < BLUR_MIN)`: push "`{name}`: This photo looks blurry. Take a clearer photo in good light and upload it again." and `continue`.
    - Wrap in try/catch; on error skip the check.
  - If the type has `expiry` or `match` **and** it is an image:
    - Set the uploading label to "Reading the document…".
    - `const text = await readText(file).catch(() => '')`.
    - `expires = findExpiryDate(text)`.
    - If `expires >= today`, `body.append('expires_on', expires)`. If it is in the past, do not send it and add the warning "The date read from {name} ({date}) has passed. Check the expiry date."
    - For each `nameFlags(text, info.match ?? [], { owner: user.last_name, gym: app.name })`, `body.append('ocr_flags[]', flag)`.
  - PDFs skip OCR.
  - Get `user` from `useAuth()`.
  - Add `expiry?: 'required' | 'optional'; match?: string[]` to the `Requirements.documents` type in `lib/gyms.ts`.
- [x] Owner expiry editing, new small component `ExpiryField({ gymId, file, required, onSaved })` in `GymApply.tsx`:
  - An `<Input type="date" min={today}>` under each `FileTile` whose type has `expiry`, labelled "Expiry date" (with "(required)" when required).
  - On change, `api(documentUrl(gymId, file.id), { method: 'PUT', body: { expires_on: value || null } })`, then `onSaved` → `reload()`. Show the server error under the field.
- [x] `lib/gyms.ts`:
  - `GymDocument` gets `expires_on?: string | null; ocr_flags?: string[] | null`.
  - `documentsMissing()` adds "Add the expiry date of the {label}." for required-expiry documents without `expires_on`, and "The {label} has expired." when it is before today.
- [x] `FileTile`, under the size line:
  - "Expires {gymDate(expires_on)}" when set. Add `Badge variant="error"` "Expired" when before today.
  - Flag badges (`variant="warning"`): `owner_name` → "Owner name not found", `gym_name` → "Gym name not found", `no_text` → "Text could not be read".
  - Both the owner and the admin see these.
- [x] Fixtures:
  - `GymApplicationTest::upload` sends `'expires_on' => today()->addYear()->toDateString()` by default.
  - `GymReviewTest.php:124` and `DatabaseSeeder.php:1592` set `expires_on` (one year ahead) on `dti`, `mayors_permit` and `barangay_clearance` documents.
- [x] Tests, new `tests/Feature/GymDocumentCheckTest.php` (`Storage::fake('local')`):
  - Upload `dti` with yesterday's date gives 422 on `expires_on`. With next year's date it is saved and returned.
  - `ocr_flags: ['owner_name']` is saved. `['bad']` gives 422.
  - `sec` with `expires_on` saves null.
  - `PUT .../documents/{id}` changes the date. Past date gives 422. Another applicant's gym gives 404. After sending, 403.
  - Submit with a `mayors_permit` that has no date gives 422 on `expiry.{id}`. With an expired date (set directly in the DB) it also gives 422.
  - A complete one passes.
- [x] Node test, new `frontend/tests/document-check.test.mjs`:
  - `findExpiryDate('VALID UNTIL DECEMBER 31, 2027')` gives `'2027-12-31'`.
  - `'Issued 01/05/2026 Expiry Date: 2027/01/04'` gives `'2027-01-04'`.
  - `'12 MAR 2028'` gives `'2028-03-12'`.
  - Text with no date gives `null`.
  - `nameFlags` finds `REYES` and flags a missing gym name.
  - `laplacianVariance` of a flat 10×10 array is `0`; of a checkerboard it is `> BLUR_MIN`.

Acceptance: `php artisan test --filter=GymDocumentCheckTest`, `--filter=GymApplicationTest` and `--filter=GymReviewTest` pass; full suite, build, lint and `node --test` pass. Manually: a blurry phone photo is refused; a clear permit photo fills the date; a PDF shows an empty date field to type.

## Phase 5: Gym catalogue photos, policy files, gym terms, required medical clearance (R-134, R-135, R-136)
Goal: gyms show photos before members apply; owners upload policy and waiver PDFs that members accept; the medical clearance becomes required.
- [x] Migration `2026_11_27_120000_create_gym_files.php`:
  - `gyms.cover_photo_path` string nullable.
  - `gym_files` table: `id`, `gym_id` FK cascade, `kind` string(20) (`photo` or `policy`), `path`, `original_name`, `size` unsignedBigInteger, `sort` unsignedSmallInteger default 0, timestamps, index `[gym_id, kind]`.
  - `down()` drops both.
- [x] New model `GymFile`: `use BelongsToGym`, `#[Fillable(['kind', 'path', 'original_name', 'size', 'sort'])]`, `#[Hidden(['path'])]`.
- [x] `config/offer.php`: `'gym_photos' => ['max' => 12, 'max_kb' => 4096]` and `'policy_files' => ['max' => 5, 'mimes' => ['pdf'], 'max_kb' => 5120]`.
- [x] New trait `app/Http/Controllers/Concerns/PresentsGymMedia.php` with `gymMedia(Gym $gym): array`:
  - `cover_photo_url`
  - `photos: [{id, url}]`, ordered by sort
  - `policy_files: [{id, name, size, url}]`
  - Query with `GymFile::withoutGlobalScope('gym')->where('gym_id', $gym->id)` so it also works on public pages. URLs come from `Storage::disk('public')->url()`.
- [x] New `app/Http/Controllers/Gym/GymMediaController.php` (copy `ServiceController::cover/storePhoto/destroyPhoto`):
  - `cover(Request, Gym)`: `photo` must be `image` with max `offer.gym_photos.max_kb`. Store in `gyms/{id}` on the `public` disk, delete the old file, `forceFill` the path.
  - `storeFile(Request, Gym)`:
    - `kind` in `photo` or `policy`.
    - `file` rules: photo = `image|max:{kb}`; policy = `file|mimes:pdf|max:{kb}`.
    - Refuse above `max` per kind with "You can add at most N …".
    - Create the row with `sort = max + 1`.
  - `destroyFile(Gym, int $file)`: `GymFile::findOrFail`, delete the stored file and the row.
  - All three return `['media' => $this->gymMedia($gym)]`.
  - Routes (under `gyms/{gym}`, `permission:gym_setup.settings.manage`): `post('media/cover')`, `post('media/files')`, `delete('media/files/{file}')->whereNumber('file')`.
- [x] `GymSetupController::setup` adds `'media' => $this->gymMedia($gym)`. `optionLists` adds `'gym_photos'` and `'policy_files'` from config.
- [x] `PublicGymController`:
  - `index` rows add `'cover_photo_url' => $gym->cover_photo_path ? Storage::disk('public')->url(...) : null`.
  - `show` adds `...$this->gymMedia($gym)` and `'needs_terms' => filled($gym->policies) || policy files exist`.
- [x] `config/membership.php`: `medical_file` gets `'required' => true` (comment `(R-136)`).
- [x] `MembershipApplicationController::rules(Gym $gym)`:
  - `medical_file` first rule becomes `config('membership.medical_file.required') ? 'required' : 'nullable'`, with message "Upload your medical clearance."
  - When the gym has policy text or policy files, add `"waivers.gym_terms" => ['accepted']` (message "Accept the gym's policies and waiver to continue.").
  - In `store`, save `'waivers' => [...array_keys(config('membership.waivers')), ...($needsTerms ? ['gym_terms'] : [])]`.
  - Add a private `needsTerms(Gym $gym): bool` used by both.
  - `form()` already returns `medical_file` (now with `required`).
- [x] Test payload helpers: add `'medical_file' => UploadedFile::fake()->create('clearance.pdf', 50, 'application/pdf')` to `MembershipSetup::applicationPayload` and to every other test helper that builds a membership payload (grep `'signature_name'` in `tests/`). `postJson` pulls `UploadedFile` values out of the data and sends them as files. Make sure those tests call `Storage::fake('local')`.
- [x] Frontend `src/lib/discovery.ts`: the public gym list type gets `cover_photo_url`. The gym detail type gets `cover_photo_url`, `photos`, `policy_files` and `needs_terms`.
- [x] New `src/components/gym/gym-media-card.tsx` `GymMediaCard({ gymId, media, options, onChange })`:
  - A Card titled "Photos and files".
  - Cover photo preview with a "Change cover" file input.
  - A gallery grid with "Add photo" and a delete button per photo.
  - A "Policies and waiver (PDF)" list with "Add file" and delete.
  - Copy the upload, size-check and error-message code from `components/offer/service-editor.tsx:227-305`.
  - Render it in `GymSettings.tsx` next to the Policies card. Rename that card's description to "House rules and waiver members accept before joining. You can also upload PDFs below."
- [x] `Home.tsx` gym cards (`:937-980`):
  - Put the cover image on top of each card (`<img className="aspect-video w-full rounded-lg object-cover" loading="lazy" alt="">`). Without one, use a muted block of the same size with the `Dumbbell` icon.
  - Make the list a 2-column grid from `sm` when the map is hidden. Keep the existing text.
- [x] `GymPublic.tsx`:
  - A cover image at the top.
  - A "Photos" section (grid of thumbnails; each is a link opening the full image in a new tab).
  - In the Policies section, a list of policy file links (`FileText` icon, name, size, `target="_blank"`).
- [x] `MembershipApply.tsx`:
  - Medical clearance: label "Medical clearance", `required` when `form.medical_file.required`; local check "Upload your medical clearance."
  - When `gym.needs_terms`, show the policy file links under the policies text, plus a `Checkbox` "I have read {gym.name}'s policies and waiver and accept them." Send it as `waivers[gym_terms]=1` through the existing `appendForm(body, 'waivers', …)`, by adding `gym_terms` to the waivers state object.
- [x] Tests, new `tests/Feature/GymMediaTest.php` (`Storage::fake('public')`):
  - The owner sets a cover and adds 2 photos and 1 PDF policy. `GET setup` lists them.
  - A 13th photo gives 422. A `.docx` policy gives 422.
  - A role without `gym_setup.settings.manage` gets 403.
  - Deleting removes the file from the disk.
  - Another gym's file id gives 404.
  - Public `GET /api/gyms/{id}` returns `cover_photo_url`, `photos` and `policy_files`. `GET /api/gyms` rows have `cover_photo_url`.
- [x] Tests, add to `MembershipApplicationTest`:
  - Without `medical_file`: 422.
  - A gym with `policies` set and no `waivers.gym_terms`: 422. With it: 201, and the membership `waivers` contains `gym_terms`.
  - A gym with no policies needs no `gym_terms`.

Acceptance: `php artisan test --filter=GymMediaTest` and `--filter=MembershipApplicationTest` pass; full suite, build, lint and `node --test` pass. Manually: upload a cover photo and see it on Home and the public page; apply without a clearance (blocked).

## Phase 6: Walk-in kiosk (R-137)
Goal: walk-in visitors register themselves on a public kiosk page; the front desk sees them on Check-in and records payment.
- [x] Migration `2026_11_27_130000_create_walk_ins.php`:
  - `walk_ins` table: `id`, `gym_id` FK cascade, `customer_id` FK nullable nullOnDelete, `name` string(150), `phone` string(30), `email` string nullable, `health_answers` json nullable, `health_notes` text nullable, `waivers` json, `signed_name` string(150), `payment_id` FK `payments` nullable nullOnDelete, `checked_in_at` dateTime, timestamps, index `[gym_id, checked_in_at]`.
- [x] New model `WalkIn`:
  - `use BelongsToGym`.
  - Fillable: `customer_id, name, phone, email, health_answers, health_notes, waivers, signed_name, checked_in_at`. `payment_id` is set with forceFill.
  - Casts: arrays and datetime.
  - Relations `customer()` and `payment()`.
- [x] `AppServiceProvider`: `RateLimiter::for('walk-in', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));`
- [x] New `app/Http/Controllers/WalkInController.php` (public part, outside the gym check, like `MembershipApplicationController`):
  - `form(int $gym)`:
    - Approved gym or 404.
    - Returns `gym: {id, name, policies, policy_files, needs_terms}`, `health_questions`, `waivers` (config).
    - Reuse `PresentsGymMedia` for `policy_files`.
  - `store(Request, int $gym, CustomerLinker $linker)`:
    - Approved gym or 404. `app(CurrentGym::class)->set($gym)`.
    - Validate:
      - `name` required max 150; `phone` required with the phone regex used in `MembershipApplicationController`; `email` nullable email.
      - `health_answers` nullable array with the question keys; each answer nullable boolean.
      - `health_notes` nullable max 2000.
      - `waivers.{key}` accepted for every config waiver, plus `waivers.gym_terms` when the gym needs terms.
      - `signed_name` required max 150.
    - Customer:
      - `$customer = $linker->customerForEmail($gym, $email)`.
      - `?? Customer::where('phone', $phone)->first()`.
      - `?? Customer::create(['name', 'email', 'phone', 'source' => CustomerSource::WalkIn, 'inquiry' => 'Walk-in visit'])`.
    - Create the `WalkIn` with `checked_in_at => now()`.
    - Return 201 `['message' => 'Thank you. Please pay at the front desk.']`. Return no ids or data.
  - Routes, public, next to `public/landing`:
    - `Route::get('public/gyms/{gym}/walk-in', [WalkInController::class, 'form'])->whereNumber('gym')->middleware('throttle:60,1');`
    - `Route::post('public/gyms/{gym}/walk-ins', [WalkInController::class, 'store'])->whereNumber('gym')->middleware('throttle:walk-in');`
- [x] Staff part, new `app/Http/Controllers/Gym/WalkInDeskController.php`:
  - `today(Request, Gym)`: today's rows (`whereDate('checked_in_at', today())`), newest first. Each row has `id`, `name`, `phone`, `email`, `checked_in_at`, `customer_id`, `paid` (bool) and `amount` (from the payment). Also `health_flag` (any answer true). Include `health_answers` and `health_notes` only when `$request->user()->hasPermission($gym, 'health_records.records.view')`.
  - `pay(Request, Gym, int $walkIn, SaleRecorder $recorder)`:
    - `DB::transaction`: lock the row; 409 "This visit is already paid." when `payment_id` is set.
    - `$payment = $recorder->record($gym, $request->user(), ['income_type' => 'walk_in', 'method' => $request->input('method'), 'amount' => $request->input('amount'), 'discount' => $request->input('discount'), 'payer_name' => $row->name, 'note' => "Walk-in visit #{$row->id}"])`.
    - `forceFill(['payment_id' => $payment->id])`.
    - Return the row payload.
  - Routes under `gyms/{gym}`:
    - `get('walk-ins')` with `permission:training.check_ins.manage`.
    - `post('walk-ins/{walkIn}/payment')->whereNumber('walkIn')` with `permission:finance.payments.manage`.
- [x] Frontend, new `src/pages/WalkIn.tsx`:
  - Route `<Route path="/gyms/:gymId/walk-in" element={<WalkIn />} />` in `App.tsx`, next to `/gyms/:gymId`, outside every `Guard`.
  - Layout: reuse `AuthCard wide` (title "Walk-in at {gym.name}").
  - Fields: name, phone, email (optional); "Health (optional)" with one `Checkbox` "Yes" per question plus notes; the 3 waiver checkboxes; policy links plus the `gym_terms` checkbox when needed; "Type your full name to sign".
  - On 201, show "Thank you, {name}. Please pay at the front desk." and a "Next visitor" button that resets the form.
  - On 429, show "Too many sign-ins from this device. Wait a minute and try again."
- [x] `GymCheckIn.tsx`, new section "Today's walk-ins":
  - Use `usePolling` (15 s) on `GET walk-ins`.
  - Each row: name, phone, time, `Badge` "Health: check answers" when `health_flag`, and "Paid ₱x" or a "Record payment" button (only when `can(gym, 'finance.payments.manage')`).
  - The button opens a small `Dialog`: amount (`type="number"`), method `Select`, Save → `POST walk-ins/{id}/payment`. Find the desk method labels already in `src/lib` (grep `E-wallet`) or hard-code `cash`, `card` and `ewallet` with the same labels.
  - Header action: "Open walk-in kiosk" (`<a href="/gyms/{id}/walk-in" target="_blank">`) plus "Copy link".
- [x] Tests, new `tests/Feature/WalkInTest.php`:
  - A guest `GET public/gyms/{id}/walk-in` returns the questions and waivers. A draft or pending gym gives 404.
  - A guest `POST` creates a `walk_ins` row and a `Customer` with `source = walk_in`.
  - A second visit with the same email reuses that customer.
  - Missing waiver gives 422 on `waivers.liability`.
  - A gym with policies needs `waivers.gym_terms`.
  - The 11th post in a minute gets 429.
  - Front desk `GET gyms/{id}/walk-ins` lists today's rows only, and has no `health_answers` without `health_records.records.view`.
  - `POST walk-ins/{id}/payment` makes a payment with `income_type = walk_in` and `payer_name`. A second time gives 409. Without `finance.payments.manage`: 403. Another gym's id: 404.

Acceptance: `php artisan test --filter=WalkInTest` passes; full suite, build, lint and `node --test` pass. Manually: open `/gyms/1/walk-in` signed out, submit, see the row on Check-in, record payment, and see it in Finance income.

## Phase 7: Job postings and staff applications (R-138)
Goal: owners post jobs; members apply with credentials, a valid ID and experience; the owner approves, which adds them as staff.
- [x] `config/permissions.php`, under `hr`: `'job_postings' => ['view', 'manage']` and `'job_applications' => ['view', 'approve']` (comment `(R-138)`). `config/access.php` HR template: add `'hr.job_postings.view', 'hr.job_postings.manage', 'hr.job_applications.view'` to the exact list.
- [x] Migration `2026_11_27_140000_create_job_postings.php`:
  - `job_postings` table: `id`, `gym_id` FK cascade, `title` string(120), `gym_role_id` FK `gym_roles` nullable nullOnDelete, `description` text, `requirements` text nullable, `status` string(20) default `open`, `created_by` FK users nullable nullOnDelete, timestamps, index `[gym_id, status]`.
  - `job_applications` table: `id`, `gym_id` FK cascade, `job_posting_id` FK cascade, `user_id` FK cascade, `status` string(20) default `pending`, `experience` text, `credentials_path`, `credentials_name`, `valid_id_path`, `valid_id_name`, `decided_by` FK users nullable nullOnDelete, `decided_at` dateTime nullable, `decision_reason` text nullable, timestamps, index `[gym_id, status]`.
  - `up()` ends with `PermissionCatalog::sync();`.
- [x] Models:
  - `JobPosting` (BelongsToGym, fillable `title, gym_role_id, description, requirements, status`, `role()` and `applications()`).
  - `JobApplication` (BelongsToGym, fillable `experience`, hidden `credentials_path` and `valid_id_path`, `user()`, `posting()`, `decidedBy()`, `decided_at` datetime cast).
- [x] New `app/Http/Controllers/Gym/JobPostingController.php`:
  - `index` (each posting with `pending_count`).
  - `store` and `update`. Rules: `title` required max 120; `gym_role_id` nullable integer, exists in `gym_roles` for this gym with status active; `description` required max 5000; `requirements` nullable max 5000; `status` in `open` or `closed`.
  - Routes `get/post/put job-postings` with `permission:hr.job_postings.view` and `.manage`.
- [x] New `app/Http/Controllers/JobApplicationController.php`. Copy the structure of `CoachApplicationController`:
  - `store(Request, int $gym, int $posting)` (member, outside the gym check):
    - Approved gym; posting open and in this gym (set `CurrentGym` first).
    - Refuse platform admins and people already owner or employee there ("You already work at this gym.").
    - Refuse a pending application for the same posting.
    - Validate `experience` required max 5000. `credentials` and `valid_id` each required, `file|mimes:pdf,jpg,jpeg,png|max:5120`.
    - Store both on the `local` disk under `job-applications/{gym}`.
    - Notify `peopleWithPermission('hr.job_applications.approve')` with `GymNotice('New job application', "{fullName} applied for {title}.", $gym->id, "/gyms/{$gym->id}/manage/hiring")`.
  - `index(Gym)`: pending first (same `orderByRaw` as coach applications), each with the posting title and applicant name and email.
  - `file(Gym, int $application, string $kind)`: `kind` in `credentials` or `valid_id`; stream from `local` with the original name.
  - `decide(Request, Gym, int $application)`:
    - Same validation and locking as `CoachApplicationController::decide`. Not your own.
    - On approve:
      - `addPerson(Employee)` when needed.
      - `EmployeeProfile` with `job_title = posting title`, `started_on = today`, `ended_on = null`.
      - If `posting.gym_role_id`, call `app(GymRoleAssigner::class)->replace($actor, $gym, $user, array_unique([...$user->gymRoles($gym)->pluck('id')->all(), $posting->gym_role_id]))`.
    - Notify the applicant ("Job application approved" → `/gyms/{id}/manage/my-work`, or "not approved" with the reason).
  - Routes:
    - Member: `Route::post('gyms/{gym}/job-postings/{posting}/applications', [JobApplicationController::class, 'store'])->whereNumber(['gym', 'posting'])->middleware('account:member');` next to the coach-applications route.
    - Gym: `get('job-applications')` and `get('job-applications/{application}/files/{kind}')->whereIn('kind', ['credentials', 'valid_id'])` with `permission:hr.job_applications.view,hr.job_applications.approve`; `post('job-applications/{application}/decision')` with `permission:hr.job_applications.approve`.
- [x] `PublicGymController::show` adds `'job_postings' => JobPosting::withoutGlobalScope('gym')->where('gym_id', $gym->id)->where('status', 'open')->get(['id', 'title', 'description', 'requirements'])`.
- [x] Frontend:
  - New `src/lib/hiring.ts` with the types.
  - New `src/pages/gym/GymHiring.tsx` at route `manage/hiring`, with two tabs (`components/ui/tabs.tsx`):
    - **Openings:** a list plus a Dialog form (title, starter role `Select` from `GET roles` when `can('hr.roles.view')`, description, requirements, open/closed).
    - **Applications:** each card shows the applicant, posting, experience and two buttons, "View credentials" and "View ID" (`apiFile`), plus Approve and Reject (reason Textarea), shown when `can('hr.job_applications.approve')`.
  - `gym-nav.tsx`: `{ segment: 'hiring', label: 'Hiring', icon: BriefcaseBusiness, permissions: ['hr.job_postings.view', 'hr.job_applications.view', 'hr.job_applications.approve'], group: 'HR / Employee Management' }`.
  - `GymPublic.tsx`: a "Jobs" section when `job_postings.length`. Each shows title, description and requirements. The "Apply" button links to `/gyms/{id}/jobs/{postingId}/apply`, shown to signed-in member accounts; others see "Sign in with a member account to apply."
  - New `src/pages/member/JobApply.tsx` in the `AccountOnly type="member"` block. Experience Textarea, two file inputs (PDF/JPG/PNG up to 5 MB, checked with `fileProblem`), and a submit button that sends `FormData`. On success, toast "Application sent. You will get a notice when the gym decides." and go back to the gym page.
- [x] Tests, new `tests/Feature/JobApplicationTest.php` (`Storage::fake('local')`, `GivesGymRoles`):
  - The owner creates a posting. A role with only `hr.job_postings.view` cannot create one (403).
  - A member applies with both files (201) and the approver gets a notice.
  - Missing `valid_id` gives 422.
  - A closed posting gives 404 or 422.
  - An existing employee is refused.
  - A second pending application gives 422.
  - An owner account gets 403.
  - Approve adds the employee, sets the profile `job_title` to the posting title and assigns the posting role.
  - Reject needs a reason.
  - Deciding twice gives 409.
  - HR (template) can view but not approve (403).
  - File download works for HR and is 403 for a member-only person.
  - The public gym page lists only open postings.
  - Run `PermissionCatalogTest`, `RbacSeederTest`, `ExactPermissionTest` and `AccessTest`; update any hard-coded permission counts or HR lists.

Acceptance: `php artisan test --filter=JobApplicationTest` plus the permission tests pass; full suite, build, lint and `node --test` pass. Manually: post a job, apply as a member, approve, and the person appears under Staff with the role.

## Phase 8: Sparring matchmaking (R-140)
Goal: coaches record weight, height, record and experience; the member record suggests sparring partners with reasons.
- [x] Migration `2026_11_27_150000_add_sparring_fields_to_athlete_profiles.php`: `decimal('weight_kg', 5, 1)->nullable()`, `unsignedSmallInteger('height_cm')->nullable()`, `unsignedSmallInteger('wins')->default(0)`, the same for `losses` and `draws`, and `unsignedTinyInteger('experience_years')->nullable()`.
- [x] `AthleteProfile`: add the six fields to `#[Fillable]`. Casts `weight_kg` `'decimal:1'`, the others `'integer'`.
- [x] New `config/sparring.php`:
  ```php
  return ['weights' => ['weight' => 35, 'level' => 20, 'record' => 20, 'experience' => 15, 'height' => 10],
      'max_weight_gap_kg' => 10, 'height_cap_cm' => 20, 'experience_cap_years' => 5, 'record_cap' => 0.5, 'limit' => 5];
  ```
- [x] New `app/Support/DecisionSupport/Sparring.php` with `public function matchesFor(Gym $gym, User $user): array`:
  - Target = this gym's `AthleteProfile` for the user (`firstOrFail`).
  - Candidates = profiles whose user has an `active` membership in this gym, without the target.
  - For each pair, add a factor only when both values are known. Add its weight to `$available`, as `DiscoveryController` does:
    - weight: `1 - |Δ| / max_gap`. **Exclude** the candidate when `|Δ| > max_gap`. Reason "`{Δ}` kg apart".
    - height: `max(0, 1 - |Δ| / cap)`, reason "`{Δ}` cm apart".
    - level: same level = 1 ("Same level: Intermediate"), next level = 0.5 ("One level apart"), else 0. Order from `array_keys(config('offer.athlete_levels'))`; a null level counts as beginner.
    - experience: `max(0, 1 - |Δ| / cap)`, reason "`{Δ}` years more experience" or "Same experience".
    - record: win rate = `wins / (wins + losses + draws)`, only when both totals are above 0. `max(0, 1 - |Δ| / record_cap)`, reason "Win rates 60% and 55%".
  - `score = $available > 0 ? round(100 * $points / $available) : 0`.
  - Sort by score descending, then name. Take `limit`.
  - Return `['athlete' => sparringPayload(target), 'matches' => [...], 'notes' => $target->weight_kg === null ? ['Add this athlete\'s weight for safer matches.'] : []]`.
  - Each match: `user_id`, `name` (`fullName()`), `score`, `reasons`, `weight_kg`, `height_cm`, `level`, `record` ("W-L-D"), `experience_years`.
  - Add `// ponytail: simple weighted score; add sport and gender filters if coaches ask`.
- [x] `AthleteRecordController`:
  - `updateSparring(Request, Gym, User)`:
    - Same self-check as `updateLevel`.
    - Validate `weight_kg` nullable numeric 20–200, `height_cm` nullable integer 90–230, `wins`/`losses`/`draws` integer 0–500, `experience_years` nullable integer 0–60.
    - Update and return `['sparring' => ...]`.
  - `sparringMatches(Gym, User, Sparring $sparring)` returns `$sparring->matchesFor($gym, $user)`.
  - `record()` adds `'sparring' => $profile->only(['weight_kg', 'height_cm', 'wins', 'losses', 'draws', 'experience_years'])`.
  - Routes next to `athletes/{user}/level`:
    - `put('athletes/{user}/sparring')` with `permission:training.athletes.manage`.
    - `get('athletes/{user}/sparring-matches')` with `permission:training.athletes.view,decision_support.general.view`.
- [x] Frontend, new `src/components/training/sparring-card.tsx` `SparringCard({ gymId, athleteId, sparring, canEdit, canSee })`:
  - Shows the six values ("Not recorded" when null), with an Edit `Dialog` when `canEdit`.
  - Lists matches (name, score `/100`, reasons as small text) from `GET sparring-matches` when `canSee`. Show `notes` in an info `Alert`.
  - Empty state: "No active members to match yet."
  - Render it in `MemberRecord.tsx` after `AthleteRecordView`, with `canEdit = canCoach && member is not me` and `canSee = can(gym, 'training.athletes.view', 'decision_support.general.view')`.
  - Add the `sparring` type to the record type in `src/lib/membership.ts` (or wherever `AthleteRecord` lives).
- [x] Tests, new `tests/Feature/SparringTest.php` (`MembershipSetup`):
  - Three active athletes at 60, 62 and 75 kg. The 60 kg target's matches list the 62 kg athlete first, with a "2 kg apart" reason, and leave out 75 kg (over 10 kg).
  - Same level scores higher than two levels apart.
  - A frozen or expired member is not listed.
  - Missing target weight returns the note.
  - `PUT sparring` validates the ranges (`weight_kg` 300 gives 422) and saves.
  - A coach changing their own record gets 403.
  - A role without `training.athletes.view` or `decision_support.general.view` gets 403.
  - Another gym's athlete gives 404.

Acceptance: `php artisan test --filter=SparringTest` passes; full suite, build, lint and `node --test` pass. Manually: on a member record, enter weights for 3 members and see the ranked partners with reasons.

## Phase 9: Analytics suggestions with one-click actions (R-141)
Goal: Analytics tells the owner what to do next, with an action the owner confirms. Nothing changes without a click and a confirmation.
- [x] New `app/Support/DecisionSupport/Suggestions.php` with `public function forGym(Gym $gym): array`, returning `['items' => [...]]`. Each item is `{code, title, reason, action}`. `action` is either `{type: 'link', label, to}` or `{type: 'request', label, confirm, method: 'POST', path, body, permission}`, where `path` is relative to `/gyms/{id}/`. Rules (reuse, no new SQL where possible):
  1. **low_stock**: `app(Restock::class)->forGym($gym)`. When not empty:
     - title "Restock {n} items".
     - reason: the names of the first 3.
     - request action "Create purchase request" → `purchase-requests`, body `{note: 'Suggested by Analytics', lines: [{stock_item_id, quantity: suggested_quantity, est_unit_cost: unit_cost}]}`, permission `procurement.requests.manage`.
  2. **falling_attendance**: `app(Problems::class)->forPeriod($gym, new Period(today()->subDays(60), today()))` items with this code:
     - title "Attendance is falling in {name}".
     - request action "Send a notice to members" → `announcements`, body `{title: "See you at {name}", body: "We miss you at {name}. Book your next session this week.", kind: 'announcement', audience_type: 'all_members'}`, permission `announcements.general.manage`.
  3. **low_use_slot**: same Problems result:
     - title "{name} is mostly empty".
     - reason "Average fill {x}%".
     - link action "Review the schedule" → `/gyms/{id}/manage/schedule`. (Merging or cancelling sessions stays a manual decision.)
  4. **expiring_memberships**: count of `active` memberships with `ends_on` between today and today + 7:
     - title "{n} memberships end this week".
     - link action "Open members" → `/gyms/{id}/manage/members`.
  - Cap at 10 items.
  - Add `// (R-141) suggestions only; the owner confirms every action and the target endpoint checks permission.`
- [x] `DecisionSupportController::suggestions(Gym $gym, Suggestions $suggestions)` returns `response()->json($suggestions->forGym($gym))`. Route `get('decision-support/suggestions')` with `permission:decision_support.general.view`.
- [x] Frontend, new `src/components/decision-support/suggestions-card.tsx` `SuggestionsCard({ gym })`:
  - Fetches `/gyms/{id}/decision-support/suggestions`.
  - Each item: title, reason, and its action:
    - link → `Button render={<Link to>}`.
    - request → `Button` that opens an `AlertDialog` showing `confirm` text and the body summary (announcement title and text, or item count). "Confirm" calls `api('/gyms/{id}/' + path, { method, body })`, toasts "Done.", then reloads the list.
    - Hide the request button when `!can(gym, action.permission)` and show "Ask someone with access to do this."
  - Empty: "No suggestions right now."
  - Render it in `GymDecisionSupport.tsx` between `PageHeader` and the workspace grid. Not printed: add `print:hidden`.
  - Add the types to `src/lib/decision-support.ts`.
- [x] Tests, new `tests/Feature/DecisionSupportSuggestionTest.php` (reuse `DecisionSupportSetup`, `StockSetup`):
  - A stock item below its minimum produces `low_stock` with a `purchase-requests` body. Posting that body to the path as the owner returns 201.
  - An active membership ending in 3 days produces `expiring_memberships`.
  - A gym with nothing gives `items: []`.
  - A role without `decision_support.general.view` gets 403.
  - Reading suggestions writes nothing: compare `purchase_requests` and `announcements` counts before and after, like `DecisionSupportReadOnlyTest`.
- [x] `CLAUDE.md`, "Decision support" bullet. Add:
  - "Shown as **Analytics** in the UI."
  - "`decision-support/suggestions` is read-only and returns actions that call existing endpoints after the owner confirms."
  - "Sparring matches live at `athletes/{user}/sparring-matches` (`training.athletes.view` or `decision_support.general.view`)."
  - In the Auth bullet: "Public walk-in kiosk: `public/gyms/{gym}/walk-in(s)`, throttled by `walk-in`."

Acceptance: `php artisan test --filter=DecisionSupportSuggestionTest` and `--filter=DecisionSupportReadOnlyTest` pass; full suite, build, lint and `node --test` pass. Manually: lower a stock item below its minimum, open Analytics, confirm "Create purchase request", and see it in Purchasing.

---

## Risks and edge cases
- **Tesseract.js needs the internet.** It downloads its WASM core and `eng` language data from a CDN the first time. When that fails, `readText` throws and the upload goes on without OCR (the owner types the date). The first OCR is slow (several seconds), so show "Reading the document…". Phone photos of permits are often skewed, so OCR misses are normal. That is why nothing is auto-rejected and the date stays editable.
- **The blur threshold is a guess.** `BLUR_MIN` is the calibration knob. Too high refuses good photos; too low lets blurry ones through. Test with a few real photos before the defense.
- **OCR flags can be faked by the client.** They only guide the admin. The server checks only that the expiry date is not past.
- **Required expiry and old drafts.** Drafts and returned applications that already hold permits without `expires_on` cannot be sent until the owner types the dates. Their submit message says exactly what to add.
- **Required medical clearance.** It applies to new applications only; existing memberships are untouched. Every test payload helper must include a fake file, or many tests fail at once.
- **Guardian rules.** Age is computed in Asia/Manila. Turning 7 or 18 "today" counts. Minors who turn 18 keep their guardian data (no clean-up job). Staff accounts made by owners (`StaffController::store`) have no birthdate. That is allowed.
- **Reverse lookup.** Nominatim's address keys differ by place (`town` or `city`, `village` or `quarter`, sometimes no `province`). Filling is best-effort, never required. The public `geo/reverse` route is throttled per IP (`geo`, 30 per minute) and cached, so it stays within Nominatim's usage policy.
- **Public kiosk abuse.** It is throttled to 10 posts per minute per IP and creates only CRM leads. A gym kiosk on one tablet shares one IP, and 10 per minute is plenty for a front desk.
- **Health data on walk-ins.** Answers are shown only to people with `health_records.records.view`. Check-in staff only see a flag.
- **Walk-ins are not in attendance analytics.** `gym_visits` needs a user. Walk-ins live in their own table.
- **Hiring roles.** `GymRoleAssigner::replace` checks that the approver holds every permission in the role. An approver who is not the owner may be refused for strong roles (403). That is correct and safe.
- **New permissions and old gyms.** Existing gyms' HR roles do not get the new strings automatically; templates only apply to newly seeded roles. Owners always have them. Say so in the demo notes.
- **Plan locks.** Hiring routes sit under the `hr` module, so a free-plan gym without HR gets 403 there. Members applying are not affected.
- **"Add member" wording.** It still creates a lead. The dialog text explains this, so staff do not think a membership was made.
- **P10 overlap.** P10 is still being built and touches `GymCheckIn.tsx` (QR fallback), `PublicGymController` (`Geo::distanceKm`) and `User.php`. Build P11 on top of the finished P10 to avoid merge conflicts.

## Out of scope
- Editing the birthdate or guardian after sign-up, and guardian consent for minors applying to gyms beyond what sign-up records.
- Server-side OCR, auto-rejecting documents, and comparing details across documents automatically.
- A separate gym waiver text field (policy text plus PDFs cover it), and e-signature images.
- Walk-in accounts, walk-in QR check-in, and counting walk-ins in attendance analytics.
- A member's "my job applications" page (the decision notice covers it), deleting job postings (closing is enough), and merging coach applications into hiring.
- Sparring filters by sport, gender or age, and an Analytics view for sparring (it lives on the member record).
- Automatic actions without confirmation, and editing suggested announcement text in the dialog (the owner can write a custom one in Announcements).
- Replacing the other ~92 "first name + last name" displays with `fullName()`.

### Critical Files for Implementation
- D:\Personal Files\Projects\CapsApp\project-02-combat\backend\app\Http\Controllers\Auth\AuthController.php
- D:\Personal Files\Projects\CapsApp\project-02-combat\backend\app\Http\Controllers\Gym\GymDocumentController.php
- D:\Personal Files\Projects\CapsApp\project-02-combat\frontend\src\pages\admin\ReviewApplication.tsx
- D:\Personal Files\Projects\CapsApp\project-02-combat\frontend\src\pages\GymApply.tsx
- D:\Personal Files\Projects\CapsApp\project-02-combat\backend\routes\api.php
