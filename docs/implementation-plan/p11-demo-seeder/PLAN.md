# P11 prompt: realistic seeder for P09, P10 and P11 data

## Context

`docs/prompts/P11.md` asks for a realistic demo seeder for the data added or changed by plans P09, P10 and P11. It should replace the ad-hoc test data, cover every status, and use the real files in the repo-root `assets/` folder.

User answers:
- The "AI-generated sample document image" means the `assets/images/*-scan.png` files.
- The new seeder is registered in `DatabaseSeeder`, so `php artisan migrate:fresh --seed` seeds everything.

## How the work runs (opus-plan-sonnet-build skill)

1. After approval, save the plan below word for word to `docs/implementation-plan/p11-demo-seeder/PLAN.md`.
2. Five Sonnet builder agents (`model: sonnet`, `effort: medium`) run one after another, one per phase. Each one ticks its checkboxes in `docs/implementation-plan/p11-demo-seeder/PLAN.md`.
3. The orchestrator runs the acceptance commands: `php artisan test --filter=DatabaseSeederTest`, the full `php artisan test`, `php artisan migrate:fresh --seed` on MySQL and the tinker spot checks.
4. Report. No commits.

---

# Plan: seed realistic demo data for P09, P10 and P11

## 1. Summary

The plan adds one new trait, `P10P11Scenarios`, that `DatabaseSeeder` uses the same way it uses `P09Scenarios`. It also makes small, targeted edits to `P09Scenarios`, `DemoScenarios` and `DatabaseSeeder`. The existing `DatabaseSeederTest` is extended, not replaced.

**Key design decisions:**

- **Fill gaps only.** I checked the diffs of commits ef12820, 2179bc9 and 2decbe8 against the current seeder. Almost all P10 and P11 data has no seed rows today: government IDs, clock-in location, session thumbnails, user identity and guardian fields, gym photos and policy files, medical clearance, walk-ins, job postings and applications, sparring fields, and document review fields (`admin_viewed_at`, `ocr_flags`).
- **P09 already has a seeder** (`P09Scenarios`). It is missing some statuses: an `archived` offer, an `approved` coach application and a `dropped` goal.
- **Two P09 seed bugs to fix.**
  - Equipment checks store `result = 'ok'`, but the real set is `pass | needs_repair | unsafe` (`EquipmentSafetyController.php:51`).
  - The purchase proof is a 1×1 PNG made in code.
- **All new files come from the repo-root `assets/` folder.** A helper reads them from `base_path('../assets/...')`. If a file is missing, the seeder stops with a `RuntimeException`. A silent fallback would create records that point at files that do not exist.
- **Gym application documents switch to the real assets.** These rows are "data changed by P11" (OCR, expiry and opened fields). They use the `*-scan.png` images where OCR applies (DTI, barangay clearance, owner ID) and the PDFs where it does not (mayor's permit, utility bill).
  - Service artwork, coach photos and profile photos stay as they are. That seeding is older than P09 and these plans did not change it.
- **Business writes use the app's own code wherever it does the work:**
  - clock-in uses `StaffAttendanceBook::clockIn/clockOut`;
  - walk-in payment uses `SaleRecorder::record`;
  - approved applications repeat the same steps as the controllers.

## 2. Files

| File (all under `backend\`) | Action | Purpose |
|---|---|---|
| `database\seeders\P10P11Scenarios.php` | **Create** | New trait. `seedP10P11(Gym, User $owner, array $employees)` runs once per approved gym. `seedP10P11Platform()` runs once at the end. |
| `database\seeders\DemoScenarios.php` | Modify | Add three shared helpers: `demoAsset()`, `putDemoAsset()`, `hireMember()`. |
| `database\seeders\P09Scenarios.php` | Modify | Add the archived offer, approved coach application and dropped goal. Fix equipment `pass`. Use real asset files for purchase proofs. |
| `database\seeders\DatabaseSeeder.php` | Modify | `use P10P11Scenarios;`. Call `seedP10P11` after `seedP09`. Call `seedP10P11Platform()` at the end of `run()`. Delete the `training-sessions` folder on reset. Change `documentFile()` to copy assets. |
| `tests\Feature\DatabaseSeederTest.php` | Modify | Assert every new entity, every status and that each file exists. |

Nothing is deleted. No new libraries. No migrations.

## 3. Data matrix

"Per gym" means each of the 5 approved gyms (ids 1–5, created first). In the existing seeder, the active memberships list (`$active`, ordered by id) is used at these positions:

- indexes 1–4: goals
- indexes 2, 4, 5: injuries
- unique users at indexes 3–6: coach applications
- the last 2: "quiet" members

**Indexes 7 and 8 are free in every gym**, because there are at most 5 existing members and the quiet members are always ≥ 9. This plan uses index 7 for the approved coach and index 8 for the minor.

| Entity (table / model) | Statuses / variants to cover | Rows | Asset(s) | Realistic values |
|---|---|---|---|---|
| `offers` / `Offer` (P09) | pending, approved, rejected, **archived** (new) | +1 per gym (6 total) | – | `['promo','Holy Week promo',null,'percent',15,-230,-222,null,7,'archived',$owner,$owner,null]` |
| `coach_applications` (P09) | pending, rejected, withdrawn, **approved** (new) | +1 per gym | – | Row 5 (applicant = `$applicants[7]`): `'approved'`, "Licensed boxing instructor with eight years of amateur bouts. Coaches a barangay youth program on weekends.", rank "Assistant coach", specialisation "{Discipline} technique", slots mon/wed 17:00–20:00. Decided 3 days ago. The user becomes an employee (see `hireMember`). |
| `training_goals` (P09) | active, achieved, **dropped** (new) | +1 per gym | – | For `$active[4]` (the wrist-strain member): "Fight at the December interclub", "Prepare for a first amateur bout.", target +20 days, `dropped`. Milestones: "Make weight at 63 kg" (−10, done), "Six sparring sessions" (+10, not done). |
| `equipment_checks` / `stock_items.safety_status` (P09) | result **pass**, needs_repair, unsafe; safety ok / needs_repair / unsafe | fix existing rows | – | Change `'ok'` to `'pass'` in the check specs. Item status: `$result === 'pass' ? 'ok' : $result`. |
| `purchase_order_proofs` (P09) | image proof and PDF proof | 2 per gym (replaces the 1×1 PNG) | `images/delivery-note-scan.png`, `documents/purchase-receipt.pdf` | Original names `Delivery-note-{$order->number}.png` and `Sales-invoice-{$order->number}.pdf`. Mime `image/png` / `application/pdf`. Path `purchase-proofs/{gym}/...` |
| `employee_profiles.sss/philhealth/pagibig_number` (P10 R-121, encrypted) | filled; empty for the contractor | spec staff per gym, except staff index 2 (the contractor) and new hires | – | Digits only: `sprintf('34%08d', $h % 1e8)`, `sprintf('19%010d', $h % 1e10)`, `sprintf('1211%08d', intdiv($h,7) % 1e8)`, where `$h = (int) sprintf('%u', crc32($email))`. Save through the Eloquent model so encryption applies. |
| `gyms.attendance_radius_m` (P10 R-122) | set / empty | gym 1 = 150, gym 2 = 200, gyms 3–5 = null | – | |
| `staff_attendance.clock_in_latitude/longitude/distance_m` (P10 R-122) | self clock-ins with a location (desk rows stay without one) | gyms 1–2: every employee with a shift, on each of the last 3 working days | – | Through `StaffAttendanceBook::clockIn($gym, $user, $lat, $lng)` at shift start −6 min. Point 15–124 m north of the pin (`lat + m/111320`). Then `clockOut` at shift end +4 min. |
| `training_sessions.thumbnail_path` (P10 R-125) | with / without a thumbnail | every session of one class service per gym | `images/session-thumbnail.jpg` | `training-sessions/{gym}/session-thumbnail.jpg` on the `public` disk. |
| `users` identity fields (P11 R-128–130) | middle name set / empty, suffix, birthdate, city/province set / empty, **minor with guardian** | all users | – | Birthdate: owners 32–51 years old, everyone else 19–42 (deterministic from crc32). About 3/4 get a middle name from a list of common Filipino surnames. Suffixes: Nestor Cabral **Jr.**, Paolo Reyes **III**, Daniel Villamor **Jr.**. City = the city of the user's first gym, otherwise a Cavite city, with about 1/5 left empty. Province "Cavite". |
| minor member (P11 R-129) | `guardian_*` and `guardian_consent_at` | 1 per gym (`$active[8]->user`) | – | Age 14–16. Guardian "Rosario / Eduardo / Marites / Ramil / Teresita {member's last name}". Relationship Mother or Father to match the name. Phone `mobile('guardian-'.$email)`. Consent time = `created_at` + 3 min. |
| `gyms.cover_photo_path` + `gym_files` kind `photo` (P11 R-134) | cover + gallery | 1 cover + 6 photos per gym | gym 1 cover `images/gym-cover.jpg`; others `images/facility-%02d.jpg`; gallery rotates through `facility-01..10.jpg` | Paths `gyms/{gym}/cover.jpg`, `gyms/{gym}/facility-NN.jpg`, `sort` 1..6. |
| `gym_files` kind `policy` (P11 R-135) | none / 1 / 2 / 3 files | gym 1: 3, gym 2: 2, gym 3: 1, gyms 4–5: 0 | `documents/gym-policy.pdf`, `membership-policy.pdf`, `privacy-policy.pdf` | Original names: "{Gym} - House rules and waiver.pdf", "{Gym} - Membership terms.pdf", "{Gym} - Data privacy notice.pdf". |
| `memberships.medical_file_*` + `athlete_profiles.medical_file_*` (P11 R-136) and `waivers` gets `gym_terms` (R-135) | PDF clearance / scan clearance | every membership of the gym, all statuses; its athlete profile when one exists | `documents/medical-certificate.pdf`, `images/medical-certificate-scan.png` (stored once per gym and shared, the same way the app already shares the path on approval) | Name `Medical-Clearance-{First}-{Last}.pdf/.png`, alternating by row. Append `gym_terms` to `waivers` when the gym has policy text or policy files. |
| `athlete_profiles` sparring fields (P11 R-140) | known / unknown weight; zero / non-zero record | every athlete profile in the gym | – | By index `% 14`. Weights: `[58.5,61.0,63.5,66.0,68.5,null,72.0,75.5,52.0,80.0,64.0,70.5,57.0,90.0]`. Heights: `[165,168,170,172,175,null,176,178,158,180,169,174,162,183]`. W-L-D: `[[3,1,0],[5,2,1],[0,0,0],[8,3,0],[2,2,1],[0,0,0],[6,1,0],[1,3,0],[0,0,0],[10,4,1],[4,4,0],[2,0,0],[0,1,0],[7,2,2]]`. Years: `[2,4,0,6,3,null,5,1,1,8,3,2,0,7]`. |
| `job_postings` (P11 R-138) | **open**, **closed** | 3 per gym | – | (1) "Front desk associate", role Front Desk, open. (2) "{Discipline label} assistant coach", role Coach, open. (3) "Sports massage therapist", role Sports Therapist, closed. Descriptions and requirements in Phase 4. |
| `job_applications` (P11 R-138) | **pending** ×2, **approved**, **rejected** | 4 per gym (20 new member-account applicants) | credentials: `documents/job-credentials.pdf` or `images/job-credentials-scan.png`; valid ID: `documents/job-valid-id.pdf` or `images/job-valid-id-scan.png` | Applicants come from a hard-coded list of 20 names whose first names are not in `personName()`, so emails cannot collide. Approved: posting 3; the person is hired with job_title "Sports massage therapist" and the Sports Therapist role. Rejected reason: "We need someone who can cover the 6:00 AM opening shift on weekdays. Please apply again if your schedule changes." |
| `walk_ins` (P11 R-137) | paid / unpaid; health flag yes / no; today / earlier | 5 per gym: 3 today (1 paid), 1 yesterday (paid), 1 three days ago (paid) | – | Visitors from a 10-name list (e.g. Jericho Manalastas, Abegail Ferrer). Each gets a new `Customer` (`source = walk_in`, inquiry "Walk-in visit"). Payments go through `SaleRecorder` (`income_type = walk_in`), amount `350 + $gym->id * 25`, methods cash / ewallet / card. One row answers `recent_injury = true` with the note "Sprained left wrist three months ago; cleared by a doctor but trains with a brace." |
| `gym_documents` (P11 R-132/133) | opened / not opened; current / **expired**; ocr_flags `owner_name`, `gym_name`, none | existing rows | DTI `images/dti-certificate-scan.png`; mayor's permit `documents/mayors-permit.pdf`; barangay `images/barangay-clearance-scan.png`; facility `images/facility-01..10.jpg`; owner ID `images/owner-id-scan.png`; utility bill `documents/utility-bill.pdf`. Video tour and owner selfie stay generated (the asset pack has no MP4 or selfie). | Approved, rejected, returned and under-review gyms: every document that must be opened is opened. Submitted (Southside): only the DTI is opened. Returned (Tagum): the mayor's permit `expires_on = today()->subMonthsNoOverflow(2)->endOfMonth()`, which matches its `return_items`. Rejected (Mindoro): DTI `ocr_flags = ['gym_name']`, which matches its decision reason. Under review (Visayas): owner ID `ocr_flags = ['owner_name']`, which matches `selfie_matches_id = false`. |

## 4. Phases

Every acceptance command runs from `backend/`.

### Phase 1: asset helpers, gym documents and P09 fixes

**Goal:** real asset files replace generated ones in data P09 and P11 changed, and the P09 status gaps are filled.

**Tasks**

- [x] **`DemoScenarios.php`: add the asset helpers** (and any missing imports: `Storage`):
  ```php
  /** Contents of a repo-root assets/ file (sample images and PDFs, see assets/README.md). Fails loudly when missing. */
  private function demoAsset(string $file): string
  {
      $path = base_path('../assets/'.$file);
      if (! is_file($path)) {
          throw new \RuntimeException("Demo asset missing: assets/{$file}. Restore the repo-root assets folder (see assets/README.md) before seeding.");
      }
      return file_get_contents($path);
  }

  /** Copies an asset onto a disk and returns its size in bytes. */
  private function putDemoAsset(string $disk, string $path, string $file): int
  {
      $contents = $this->demoAsset($file);
      Storage::disk($disk)->put($path, $contents);
      return strlen($contents);
  }
  ```
- [x] **`DemoScenarios.php`: add the hire helper** (imports `GymRole`, `GymPersonType`, `Carbon` if missing). It repeats what `CoachApplicationController::decide` and `JobApplicationController::decide` do on approve:
  ```php
  /** Adds a person as staff the way an approved coach or job application does (R-117, R-138). */
  private function hireMember(Gym $gym, User $user, array $profile, ?GymRole $role, Carbon $on): void
  {
      if (! $user->hasGymRole($gym, GymPersonType::Employee)) {
          $gym->addPerson($user, GymPersonType::Employee);
      }
      $gym->employees()->create(['user_id' => $user->id, 'started_on' => $on->toDateString(), 'ended_on' => null, ...$profile]);
      $role?->users()->syncWithoutDetaching([$user->id]);
  }
  ```
- [x] **`DatabaseSeeder::documentFile()`: copy assets instead of generating files.** Use the mapping in the data matrix. Return `[path, originalName, mime, $this->demoAsset(...)]`.
  - The file extensions change: `dti-certificate.png`, `barangay-clearance.png`, `owner-id.png`, `facility-photo-{i}.jpg` (`sprintf('images/facility-%02d.jpg', $index)`, `image/jpeg`).
  - Keep the `video_tour`, `owner_selfie` and `default` branches unchanged.
  - Delete local variables that become unused. Keep `pdf()` and `imageDocument()`, because the remaining branches still use them.
- [x] **`P09Scenarios::seedOffers`:** add the archived row from the matrix as the 6th entry.
- [x] **`P09Scenarios::seedCoachApplications`: add the approved row.**
  - Add a 5th row `['approved', ...]`; it uses `$applicants[7]`.
  - For `approved`: set `decided_by = $owner`, `decided_at = now()->subDays(3)`, and `created_at` 6 days ago.
  - Call `$this->hireMember($gym, $user, ['job_title' => 'Coach', 'coach_experience' => ..., 'coach_rank' => ..., 'coach_specialisation' => ...], GymRole::where('template_key', 'coach')->where('status', 'active')->first(), today()->subDays(3))`.
  - Create one work schedule row per slot: `$gym->workSchedules()->create(['user_id', 'weekday', 'start_time', 'end_time'])`.
  - Send the notice the controller sends: `GymNotice('Coach application approved', "You are now a coach at {$gym->name}. Open My work to see your schedule.", $gym->id, "/gyms/{$gym->id}/manage/my-work")`.
  - Today, `decided_by` and `decided_at` are set only for `rejected`. Extend that to `in_array($status, ['approved', 'rejected'])`.
- [x] **`P09Scenarios::seedGoals`:** add the 4th spec (dropped) from the matrix.
- [x] **`P09Scenarios::seedEquipmentChecks`:** change spec results from `'ok'` to `'pass'`. Set `safety_status` with `$result === 'pass' ? 'ok' : $result`.
- [x] **`P09Scenarios::seedPurchasingP09`: replace the base64 PNG block.**
  - Loop over the two proofs in the matrix.
  - Each one: `$size = $this->putDemoAsset('local', "purchase-proofs/{$gym->id}/{$file}", $asset)`, then the same `DB::table('purchase_order_proofs')->insert` with the right `mime` and `size`.
  - Update the comment above the block.

**Tests (`DatabaseSeederTest`, inside the approved-gym loop)**

- Change the existing coach status list to `['pending', 'approved', 'rejected', 'withdrawn']`.
- Add `archived` to the offer statuses.
- Add `dropped` to the goal statuses.
- Assert `equipment_checks.result` has `pass`, `needs_repair` and `unsafe`, and never `ok`.
- Assert one `purchase_order_proofs` row with `mime = image/png` and one with `application/pdf`. For each proof path, call `Storage::disk('local')->assertExists($path)`.
- After the loop:
  - assert a `gym_documents` row with `type = dti` and `mime = image/png`;
  - call `Storage::disk('local')->assertExists(...)` on that row's path.

**Acceptance**

- `php artisan test --filter=DatabaseSeederTest` passes (3 data-provider runs).
- `php artisan db:seed` on MySQL succeeds.
- `php artisan tinker --execute="dump(DB::table('coach_applications')->pluck('status')->countBy())"` shows all 4 statuses.

### Phase 2: P10 trait and wiring

**Goal:** government IDs, the clock-in radius and location, and session thumbnails are seeded.

**Tasks**

- [x] **Create `P10P11Scenarios.php`.** Namespace `Database\Seeders`. Doc comment: `/** P10 and P11 demo records: gov IDs, clock-in location, thumbnails, gym media, hiring, walk-ins, sparring, identities. */`. Add:
  ```php
  private function seedP10P11(Gym $gym, User $owner, array $employees): void
  {
      $active = $gym->memberships()->with('user')->where('status', 'active')->orderBy('id')->get();
      $this->seedGovernmentIds($gym, $employees);   // (R-121)
      $this->seedClockInLocations($gym);            // (R-122)
      $this->seedSessionThumbnails($gym);           // (R-125)
      // Phases 3-4 add more calls here.
  }
  ```
- [x] **`seedGovernmentIds(Gym $gym, array $employees)`**
  - `foreach ($employees as $index => $person)`. Skip index 2 with the comment `// contractor: IDs not handed in yet`.
  - Find `$gym->employees()->where('user_id', $person->id)->first()` and call `->update([...])` with the numbers from the matrix. It must be a model update so the `encrypted` cast applies.
- [x] **`seedClockInLocations(Gym $gym)`**
  - Return early unless `$gym->id <= 2`.
  - `$gym->forceFill(['attendance_radius_m' => $gym->id === 1 ? 150 : 200])->save()`.
  - Loop `$gym->employees()->with('user')->orderBy('id')->get()` as `$n => $profile`, then `[3, 2, 1]` as `$daysAgo` with `$day = today()->subDays($daysAgo)`.
  - Skip the day when any of these is true:
    - `started_on > $day`, or `ended_on` is before `$day`;
    - `app(WorkingDays::class)->kind($gym, $profile->user, $day)->value !== 'working'`;
    - `$shift = app(StaffAttendanceBook::class)->scheduleFor(...)` has an empty start or end, or end ≤ start;
    - a row already exists for that user and date;
    - `PayrollLock::assertOpen($gym, $day)` throws (wrap it in `try { } catch (\Throwable) { continue; }`).
  - Then:
    ```php
    $metres = 15 + ($n * 23 + $daysAgo * 11) % 110;
    Carbon::withTestNow($start->copy()->subMinutes(6), fn () => $book->clockIn($gym, $profile->user, (float) $gym->latitude + $metres / 111320, (float) $gym->longitude));
    Carbon::withTestNow($end->copy()->addMinutes(4), fn () => $book->clockOut($gym, $profile->user));
    ```
- [x] **`seedSessionThumbnails(Gym $gym)`**
  - `$serviceId = TrainingSession::where('type', 'classes')->orderBy('starts_at')->value('gym_service_id')`. Return if null.
  - `$path = "training-sessions/{$gym->id}/session-thumbnail.jpg"`, then `putDemoAsset('public', $path, 'images/session-thumbnail.jpg')`.
  - `TrainingSession::where('gym_service_id', $serviceId)->update(['thumbnail_path' => $path])`. This is a query-builder update, because the column is not fillable.
- [x] **`DatabaseSeeder` wiring**
  - Add `use P10P11Scenarios;` after `use P09Scenarios;`.
  - In `seedApprovedGyms`, after `$this->seedP09(...)`, add `$this->seedP10P11($gym, $owner, array_values($employees));`.
  - In `resetData()`, add `Storage::disk('public')->deleteDirectory('training-sessions');`.

**Tests**

- Per gym: assert `training_sessions` has a row with `thumbnail_path` set, and that file exists on `public`.
- Per gym: assert `employee_profiles` with `sss_number` set count > 0, and the raw value matches neither `/^\d{10}$/` nor stays plain. Check it with `DB::table('employee_profiles')->whereNotNull('sss_number')->value('sss_number')`.
- For gym id 1: assert `staff_attendance` with `source = self` and `clock_in_distance_m` between 1 and 150 exists, and that `gyms.attendance_radius_m = 150`.

**Acceptance**

- The filtered test passes.
- `php artisan db:seed` on MySQL succeeds.
- `php artisan tinker --execute="dump(DB::table('staff_attendance')->whereNotNull('clock_in_distance_m')->count())"` is > 0.

### Phase 3: P11 gym media, medical clearance, minor, sparring

**Goal:** the gym catalogue, required clearances, a guardian-backed minor and sparring data are seeded.

**Tasks**

- [x] **Add the calls to `seedP10P11`, in this order** (media must come first, because the gym-terms check reads the policy files):
  ```php
  $this->seedGymMedia($gym);                 // (R-134, R-135)
  $this->seedMedicalClearances($gym);        // (R-136)
  $this->seedMinorMember($active[8]->user ?? null, $gym); // (R-129)
  $this->seedSparringProfiles();             // (R-140)
  ```
- [x] **`seedGymMedia(Gym $gym)`**
  - Cover: `putDemoAsset('public', "gyms/{$gym->id}/cover.jpg", $gym->id === 1 ? 'images/gym-cover.jpg' : sprintf('images/facility-%02d.jpg', (($gym->id - 1) * 2 + 6) % 10 + 1))`, then `forceFill(['cover_photo_path' => ...])->save()`.
  - Gallery: `k = 0..5`, `$n = (($gym->id - 1) * 2 + $k) % 10 + 1`. Call `GymFile::create(['kind' => 'photo', 'path' => "gyms/{$gym->id}/facility-%02d.jpg", 'original_name' => sprintf('facility-%02d.jpg', $n), 'size' => $size, 'sort' => $k + 1])`.
  - Policies: `[1 => ['gym-policy','membership-policy','privacy-policy'], 2 => ['gym-policy','privacy-policy'], 3 => ['membership-policy']][$gym->id] ?? []`. Labels: `['gym-policy' => 'House rules and waiver', 'membership-policy' => 'Membership terms', 'privacy-policy' => 'Data privacy notice']`. Path `gyms/{gym}/{name}.pdf`, `original_name = "{$gym->name} - {$label}.pdf"`.
- [x] **`seedMedicalClearances(Gym $gym)`**
  - Put `medical-files/{gym}/medical-clearance.pdf` (from `documents/medical-certificate.pdf`) and `.png` (from `images/medical-certificate-scan.png`) once each. Keep both sizes.
  - `$needsTerms = filled($gym->policies) || GymFile::where('kind', 'policy')->exists();`
  - `foreach (Membership::with('user')->orderBy('id')->get() as $i => $m)`:
    - pick the PDF when `$i` is even, otherwise the PNG;
    - name `'Medical-Clearance-'.Str::slug($m->user->first_name.' '.$m->user->last_name, '-')`, keeping the case, e.g. `Str::of(...)->replace(' ', '-')`, plus the extension;
    - `forceFill` the four `medical_file_*` columns, plus `waivers` with `gym_terms` added (unique) when `$needsTerms`, then save;
    - then `AthleteProfile::where('user_id', $m->user_id)->first()?->forceFill(the same 4 columns)->save()`.
- [x] **`seedMinorMember(?User $member, Gym $gym)`**
  - Return if null.
  - `$k = ($gym->id - 1) % 5`.
  - `[$first, $relation] = [['Rosario','Mother'],['Eduardo','Father'],['Marites','Mother'],['Ramil','Father'],['Teresita','Mother']][$k]`.
  - `$member->forceFill(['birthdate' => today()->subYears(14 + $k % 3)->subMonths(3), 'guardian_name' => "{$first} {$member->last_name}", 'guardian_phone' => $this->mobile('guardian-'.$member->email), 'guardian_relationship' => $relation, 'guardian_consent_at' => $member->created_at->copy()->addMinutes(3)])->save()`.
- [x] **`seedSparringProfiles()`:** `AthleteProfile::orderBy('id')->get()` (scoped to the gym). For each index, `forceFill` the 6 values from the matrix lists at `$i % 14`, then save.

**Tests (per gym)**

- `gyms.cover_photo_path` is not null, and the file exists on `public`.
- `gym_files` has 6 rows of kind photo.
- For gym 1: 3 policy rows. For gyms 4 and 5: 0 policy rows.
- `memberships` with a null `medical_file_path` count = 0.
- If the gym has policy text or files, every membership's `waivers` contains `gym_terms`. Decode the JSON in PHP; it works on SQLite.
- `athlete_profiles` has rows with `weight_kg` not null AND at least one with `weight_kg` null.
- Exactly one user among the gym's members has `guardian_consent_at` set and `birthdate > today()->subYears(18)`.

**Acceptance**

- The filtered test passes.
- MySQL seed succeeds.
- Owner screen check (manual, optional): on Gym settings, "Photos and files" shows the photos.

### Phase 4: P11 hiring and walk-ins

**Goal:** job postings and applications in every status, plus walk-ins both paid and unpaid.

**Tasks**

- [x] **Add the calls to `seedP10P11`:** `$this->seedJobs($gym, $owner); // (R-138)` and `$this->seedWalkIns($gym, $owner); // (R-137)`.
- [x] **`seedJobs(Gym $gym, User $owner)`: create the postings.**
  - `$label = config('verification.disciplines')[$gym->disciplines[0]] ?? 'Boxing'`.
  - Create 3 `JobPosting` rows with `forceFill` of `gym_id`, `created_by`, `created_at` (−30 / −14 / −45 days) and `status`:
    1. "Front desk associate", `GymRole::where('name','Front Desk')`, open. Description: "Welcome members and walk-in visitors, record payments and check-ins, and keep the front desk running during evening classes." Requirements: "At least six months of customer service or cashier experience. Comfortable with a computer and e-wallet payments. Available on weekday evenings and Saturdays."
    2. "{$label} assistant coach", role Coach, open. Description: "Assist the head coach in beginner and intermediate classes, hold pads, run warm-ups and help keep sparring safe." Requirements: "At least two years of {$label} training with competition or coaching experience. A basic first aid certificate is preferred."
    3. "Sports massage therapist", role Sports Therapist, closed. Description: "Give pre- and post-training massage and recovery sessions, and keep injury notes up to date with the coaches." Requirements: "Licensed massage therapist or physical therapy graduate. Experience with athletes is an advantage."
- [x] **`seedJobs`: create the applicants.**
  - Names, as a private const or local array: `['Kevin Lacson','Princess Magbanua','Ronald Sarmiento','Hazel Quinto','Jerome Abad','Kristine Nepomuceno','Ariel Buenaventura','Shiela Cortez','Ramil Ilagan','Joanna Pacheco','Bryan Salonga','Mylene Dimaculangan','Arnel Bustamante','Czarina Lim','Edgar Macaraeg','Faith Sumulong','Gilbert Tan','Irene Mangahas','Wilfredo Ponce','Lovely Guevarra']`.
  - The applicant for row `r` (0..3) is `$names[($gym->id - 1) * 4 + $r]`, split on the first space.
  - Create each one with `$this->user($first, $last, Str::slug("$first $last", '.').'@demo.combatplatform.ph', $this->mobile('job-'.$first.$last))`.
- [x] **`seedJobs`: create the applications.** Rows: `[posting, status, createdDaysAgo, decidedDaysAgo, experience, credAsset, credName, idAsset, idName, reason]`.
  - **A** — posting 1, pending, 2 days ago. Experience: "Two years as a cashier and customer service crew at a supermarket in {$gym->city}. Handles cash counts and GCash payments daily." Files: `documents/job-credentials.pdf` "Resume-{First}-{Last}.pdf", `images/job-valid-id-scan.png` "UMID-{First}-{Last}.png".
  - **B** — posting 1, rejected, created 9 days ago, decided 6 days ago, with the reason from the matrix. Experience: "Part-time sales associate at a mall sports store for one year." Files: PDF credentials, `documents/job-valid-id.pdf` "Drivers-License-{First}-{Last}.pdf".
  - **C** — posting 2, pending, 1 day ago. Experience: "Five years of amateur {$label} with twelve interclub bouts. Helps run kids' classes at a barangay sports program on weekends." Files: `images/job-credentials-scan.png` "Coaching-Certificate-{First}-{Last}.png", `images/job-valid-id-scan.png` "PhilSys-ID-{First}-{Last}.png".
  - **D** — posting 3, approved, created 20 days ago, decided 14 days ago. Experience: "Licensed massage therapist with three years at a sports clinic in Makati. Worked with a university varsity team during the UAAP season." Files: PDF "PRC-License-and-Resume-...pdf" and the PDF valid ID.
  - Store each file with `putDemoAsset('local', "job-applications/{$gym->id}/{$user->id}-credentials.{ext}", ...)`, and the same pattern for `valid_id`.
  - Build each with `new JobApplication(['experience' => ...])` and `forceFill` of `gym_id`, `job_posting_id`, `user_id`, `status`, the 4 file columns, `decided_by` (owner when decided), `decided_at`, `decision_reason`, `created_at` and `updated_at`.
- [x] **`seedJobs`: side effects.**
  - Pending: `$gym->peopleWithPermission('hr.job_applications.approve')->each(fn ($p) => $p->notify(new GymNotice('New job application', "{$user->fullName()} applied for {$posting->title}.", $gym->id, "/gyms/{$gym->id}/manage/hiring")))`.
  - Approved: `$this->hireMember($gym, $user, ['job_title' => $posting->title], $posting->role, today()->subDays(14))`, plus the controller's approved notice.
  - Rejected: the controller's "Job application not approved" notice with the reason.
- [x] **`seedWalkIns(Gym $gym, User $owner)`: rows.**
  - `$desk = GymRole::where('name','Front Desk')->first()?->users()->first() ?? $owner`.
  - Names: `['Jericho Manalastas','Abegail Ferrer','Ronnie Villareal','Precious Lagman','Dominic Yap','Kimberly Sison','Arvin Talusan','Hannah Robles','Paulo Galang','Trixie Mangubat']`, starting at offset `($gym->id - 1) * 2`.
  - The 5 rows `[checkedIn, email?, health?, payMethod|null]`:
    - `now()->subMinutes(25)->max(today())`, with email, no health issue, unpaid
    - `now()->subMinutes(70)->max(today())`, no email, `recent_injury = true` with the note from the matrix, unpaid
    - `now()->subMinutes(130)->max(today())`, with email, paid cash
    - `today()->subDay()->setTime(18, 30)`, paid ewallet
    - `today()->subDays(3)->setTime(17, 45)`, paid card
  - Email when set: `Str::slug($name, '.').'@demo.combatplatform.ph'`.
  - `health_answers`: every config key set to false, except `recent_injury` on the flagged row.
  - `waivers`: config keys plus `gym_terms` when the gym needs terms. Use the same check as Phase 3; you may factor it into a private `needsTerms(Gym)` in the trait and use it in both places.
- [x] **`seedWalkIns`: records and payment.** Copy the create calls from `WalkInController::store`:
  - `Customer::create(['name','email','phone','source' => CustomerSource::WalkIn,'inquiry' => 'Walk-in visit'])`;
  - `WalkIn::create([... 'signed_name' => $name, 'checked_in_at' => $at])`, then `forceFill` `created_at` to `$at` on both.
  - Phone: `$this->mobile('walk-in-'.$name)`.
  - Paid rows:
    ```php
    $payment = Carbon::withTestNow($at->copy()->addMinutes(4), fn () => app(SaleRecorder::class)->record($gym, $desk, ['income_type' => 'walk_in', 'method' => $method, 'amount' => 350 + $gym->id * 25, 'payer_name' => $name, 'note' => "Walk-in visit #{$row->id}"]));
    $row->forceFill(['payment_id' => $payment->id])->save();
    ```

**Tests (per gym)**

- `job_postings` statuses include `open` and `closed`.
- `job_applications` statuses include `pending`, `approved` and `rejected`.
- The approved applicant's `employee_profiles.job_title` equals the posting title, and that user's `gym_user` role is `employee`.
- `credentials_path` exists on `local` for one row.
- `walk_ins` today count = 3, with at least one where `payment_id` is not null and one where it is null.
- `payments.income_type = walk_in` count ≥ 3 (existing desk sales add more).
- The existing customer stage assertion `['applied','lead','lost','member']` must still pass. Walk-in customers are leads.

**Acceptance**

- The filtered test passes.
- MySQL seed succeeds.
- `php artisan tinker --execute="dump(DB::table('job_applications')->pluck('status')->countBy(), DB::table('walk_ins')->whereNull('payment_id')->count())"`.

### Phase 5: platform pass (identities, document review) and full verification

**Goal:** every user has P11 identity data, and every application shows the document check states.

**Tasks**

- [x] **Add `seedP10P11Platform()`** and call it as the last line of `DatabaseSeeder::run()`, after `seedSubscriptionStates()`:
  ```php
  private function seedP10P11Platform(): void
  {
      $this->seedIdentities();       // (R-128, R-130)
      $this->seedDocumentReview();   // (R-132, R-133)
  }
  ```
- [x] **`seedIdentities()`: loop `User::orderBy('id')->get()`** with `$h = (int) sprintf('%u', crc32($user->email))`.
  - **Birthdate:** `$user->birthdate ?? today()->subYears(($user->account_type === 'owner' ? 32 + $h % 20 : 19 + $h % 24))->subDays($h % 300)`. `account_type` may be an enum cast; compare `->value` if so.
  - **Middle name:** `$h % 4 === 0 ? null : MIDDLE[$h % 16]`, from `['Santos','Reyes','Cruz','Bautista','Ocampo','Garcia','Mendoza','Torres','Castro','Aquino','Lopez','Navarro','Ramos','Gonzales','Mercado','Javier']`. Use null when it equals the last name.
  - **Suffix:** `['Nestor Cabral' => 'Jr.', 'Paolo Reyes' => 'III', 'Daniel Villamor' => 'Jr.']["{$user->first_name} {$user->last_name}"] ?? null`.
  - **City and province:**
    - when `$h % 5 === 0`, set both to null;
    - otherwise city = `DB::table('gym_user')->join('gyms','gyms.id','=','gym_user.gym_id')->where('gym_user.user_id',$user->id)->orderBy('gym_user.id')->value('gyms.city')` ?? `['Dasmariñas','Imus','Bacoor','General Trias','Silang','Tagaytay'][$h % 6]`, and province = `'Cavite'`.
  - Save with `forceFill(...)->save()`.
- [x] **`seedDocumentReview()`: loop `Gym::with('documents')->orderBy('id')->get()`.** Skip gyms with no documents. `$mustOpen = fn ($d) => config("verification.documents.{$d->type}.must_open", true)`.
  - **Submitted:** only the `dti` document gets `admin_viewed_at = now()->subHours(3)`.
  - **Any other status:** `$base = $gym->reviewed_at ?? now()->subDay()`. Each must-open document at index `$i` gets `admin_viewed_at = $base->copy()->subMinutes(60 - $i * 4)`. Facility photos stay null.
  - **Returned:** the `mayors_permit` gets `expires_on = today()->subMonthsNoOverflow(2)->endOfMonth()`.
  - **Rejected:** `dti.ocr_flags = ['gym_name']`.
  - **Under review:** `owner_id.ocr_flags = ['owner_name']`.
  - Use `forceFill(...)->save()`; `GymDocument` has no gym scope.

**Tests (after the loop)**

- `users` with a null `birthdate` count = 0. `middle_name` not null count > 0. A `suffix = 'Jr.'` row exists.
- No owner is younger than 18. Check in PHP: `DB::table('users')->where('account_type','owner')->where('birthdate','>',today()->subYears(18)->toDateString())->count() === 0`.
- Approved gyms: documents not of type `facility_photo` with a null `admin_viewed_at` count = 0.
- The submitted gym has at least 1 unopened document.
- The returned gym's `mayors_permit.expires_on` is before today.
- `ocr_flags` contains `gym_name` on one row and `owner_name` on another. Use `where('ocr_flags', 'like', '%gym_name%')`; it works on both SQLite and MySQL.

**Acceptance (whole plan)**

1. `php artisan test --filter=DatabaseSeederTest` passes.
2. `php artisan test` passes in full. Report the count.
3. `php artisan migrate:fresh --seed` on local MySQL succeeds. `php artisan storage:link` already exists.
4. Tinker spot check:
   ```
   php artisan tinker --execute="dump(DB::table('users')->whereNotNull('guardian_consent_at')->count(), DB::table('gym_files')->pluck('kind')->countBy(), DB::table('gym_documents')->whereNotNull('ocr_flags')->pluck('ocr_flags'))"
   ```
   Expected: 5, `{photo: 30, policy: 6}`, 2 rows.
5. Optional browser check:
   - Home gym cards show covers;
   - Check-in shows "Today's walk-ins";
   - Hiring shows 3 postings;
   - the admin review of Southside shows "documents still to open".
   Say clearly if this was not run.

## 5. Risks and edge cases

- **The assets are untracked in git.** On a fresh clone or in CI, the seeder and `DatabaseSeederTest` will fail with the clear "Demo asset missing" message. That is the intended loud failure. The user should commit `assets/`, excluding `assets/__pycache__`. Tell the user; do not commit.
- **The asset text shows a different gym and owner.** The PDFs and scans read "IRON FIST COMBAT ACADEMY" and "JUAN MIGUEL DELA CRUZ", not the seeded gyms. A real browser OCR run on them would flag names. The seeded `ocr_flags` are chosen by hand to match each application's story. This is a known limit of the fixtures.
- **Test time and disk use go up.** Each seed copies about 30–40 MB into the fake disks, and the test seeds 3 times. That is acceptable. If it gets slow, reduce the gallery to 4 photos.
- **Clock-ins near a payroll boundary.** If the last 3 days fall in a released payroll period (for example, seeding on the 1st or 16th), the `PayrollLock` guard skips those days. Some days may then have no self clock-ins. That is fine.
- **Walk-ins seeded just after midnight.** `->max(today())` keeps today's rows inside today, but the times would read "00:00". This is cosmetic.
- **New hires have no pay set** (`pay_basis` null), exactly like the controllers do. The draft payroll run was computed before the hires, so it does not include them. That is realistic ("HR still to set pay").
- **The approved coach gets a work schedule.** Clock-ins are seeded for the coach's last 3 days, so the absence job finds no gap. Days before the 3-day start are covered because the coach starts 3 days ago.
- **Encrypted government IDs depend on `APP_KEY`.** A database seeded under one key cannot be read under another. Reseed after changing keys.
- **Index assumptions** (`$active[7]`, `$active[8]`): they hold for the current specs (at most 5 existing members per gym). Guard with `?? null` and skip when missing, as the P09 code does.

## 6. Out of scope

- Service cover and gallery SVGs, coach photos, profile photos and leave attachments. That seeding is older than P09 and these plans did not change it. Swapping in `service-cover.jpg`, `service-gallery-*`, `coach-photo.jpg` and `user-profile.jpg` is a small follow-up if the user wants it.
- Filling `memberships.offer_id` and `discount_amount` on past memberships. Changing amounts would break the payment and ledger invariants the seeder test checks. The R-127 discount demo already works: approving the seeded `applied` membership applies the live "Rainy season promo".
- Arranging data so every Analytics suggestion appears (`low_use_slot`, `falling_attendance`, `expiring_memberships`). Suggestions are worked out from data, not stored. `low_stock` already shows from the seeded stock.
- An MP4 video tour and an owner selfie. The asset pack has neither, so the existing generated placeholders stay.
- Free-text "old entry" session sports (R-124) and the QR fallback (R-126). Neither adds stored data that needs seeding.
