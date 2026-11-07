# P02: Auth page redesign + Gym Member / Gym Owner account types

## Context
Today one account does everything: browse and join gyms, and apply to own a gym (spec R-01/R-07: "one sign-up, roles later"). Nothing blocks a user from being owner of one gym and member of another. P02 wants a clear choice at sign-up and clear limits per type.

Decisions (from user):
- **Strict, fixed type** chosen at sign-up: `member` or `owner`. Backend enforces it.
- **Member**: browse approved gyms, join gyms, bookings, best matches. Cannot apply to own a gym.
- **Owner**: apply, run gym once approved, **browse only** on member side (no membership applications, no gym preferences / best matches).
- **Existing accounts**: inferred. Owns a gym or has any gym application = `owner`; else `member`. Old memberships of owner-type accounts keep working (grandfathered); no new joins.
- Staff (employees) are unaffected: staff link is per gym and made by an owner. Staff accounts created by `StaffController` get the default `member`. Platform admin unaffected (admin routes do not check type).

## Backend
1. `backend/app/Enums/AccountType.php` — `Member = 'member'`, `Owner = 'owner'`.
2. Migration `2026_11_19_090000_add_account_type_to_users.php` — `string('account_type', 20)->default('member')`; backfill with `DB::table('users')->whereExists(...)` for `gyms.applicant_id = users.id` or `gym_user.role = 'owner'` (works on SQLite + MySQL). `down()` drops the column.
3. `User` model: add `account_type` to `#[Fillable]` (register sets it; `ProfileController::update` validates its own fields so it cannot be changed), cast to `AccountType`, helper `isOwnerAccount()`.
4. `UserFactory`: default `member`; `owner()` state.
5. `AuthController::register`: rule `'account_type' => ['required', Rule::enum(AccountType::class)]`.
6. `UserResource`: add `'account_type'`.
7. Middleware `EnsureAccountType` (alias `account` in `bootstrap/app.php`), same shape as `EnsurePlatformAdmin`: `account:owner` / `account:member`, 403 "Only Gym Owner accounts can do this." / "Only Gym Member accounts can do this."
8. `routes/api.php`:
   - `gym-applications` group → `account:owner`.
   - `account:member` on `membership-form`, `POST gyms/{gym}/membership-applications`, `gym-preferences` GET/PUT, `gyms/recommendations`.
   - Leave `gyms`, `gyms/{gym}`, `discovery/options`, `geo/search`, `my-*` open (browse + grandfathered records).
9. `DatabaseSeeder`: owners and pending applicants created with `account_type = owner` (forceFill or factory state). Update `ACCOUNTS.md` with one line on account types.

## Frontend
1. `src/lib/auth.tsx`: `User.account_type: 'member' | 'owner'`.
2. `src/components/auth-card.tsx` redesign (all auth screens benefit): split layout on `lg` — left brand panel (platform name, one-line pitch, 2–3 short points), right form card; on mobile brand line above card. Stronger title size, optional `wide` prop for sign-up. Same props otherwise, so `ForgotPassword`, `VerifyEmail`, `FirstPassword`, `AcceptInvite` keep working.
3. `src/pages/SignUp.tsx`: account-type picker first (coss `radio-group` styled as two option cards with icon, title, "what you can do" list). `?type=owner` preselects. Note under picker: "You can't switch later. Use another email for the other type." Then name / email / password. Hidden-free: radio `name="account_type"` goes through `formValues`. Field error shown for `account_type`.
4. `src/pages/SignIn.tsx`: description line, "Forgot password?" placed by the password field, footer "New here? Create an account". Small hierarchy cleanup only.
5. `src/App.tsx`: small `AccountOnly type="owner|member"` guard (like `AdminOnly`, redirect to `/`): owner on `/apply`, `/applications*`; member on `/gyms/:gymId/apply`.
6. `src/pages/Home.tsx`: "Apply to run a gym" button only for owner type; "Best matches" section only for member type; header description per type. Owner with no gym yet sees a short card pointing to their application.
7. `src/components/app-shell.tsx`: "Run a gym" nav group only for owner type.
8. `src/pages/GymPublic.tsx`: `canApply` also needs `account_type === 'member'`; owner sees muted note "Gym Owner accounts can view gyms but not join them."

During UI work load `impeccable` + `antislop-ui` (CLAUDE.md rule 3); reuse coss UI (`card`, `radio-group`, `field`, `alert`).

## Tests
- Update existing: `GymApplicationTest`, `GymReviewTest`, and any test that applies for a gym → applicant via `User::factory()->owner()`. `AccountTest` register calls → send `account_type`. Any test expecting 422 "You own this gym." on membership → now 403 (owner type).
- New `AccountTypeTest`: register needs valid `account_type`; member gets 403 on `POST gym-applications`; owner gets 403 on membership application and `gym-preferences`; owner can `GET gyms`; `/me` returns `account_type`.

## Docs
- `PHASE 1.md` R-01/R-07: note the 6 Nov 2026 change (type chosen at sign-up, owner/member exclusive, staff unaffected).
- `00 - START HERE.md` change log row.

## Verification
- `php artisan test` (backend), `php artisan migrate` on local MySQL and check backfill: seeded owners/applicants = `owner`.
- `pnpm build`, `pnpm lint`.
- Manual in browser (`pnpm dev` + `php artisan dev`): sign up as each type; member sees no apply entry, owner sees no join/best-matches; direct URL `/apply` as member redirects. Note: browser render gates (contrast/responsive) unmeasured unless Playwright browser is installed.
