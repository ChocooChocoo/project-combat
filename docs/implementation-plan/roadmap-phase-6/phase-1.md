[← Previous: Overview](./00-overview.md) | [Next: Packet 6A HR extras →](./phase-2.md)

**Status:** ✅ Done

# Packet 6.0: Foundations

**Goal:** Prepare everything the six business modules share: the real permission catalog, income typing on payments, employment end dates, gym payroll and attendance settings, and one day-classification rule used by attendance and payroll. No business screen ships in this packet.

## Dependencies

- The RBAC permission catalog refactor in the working tree is committed as its own change first. This packet extends it.
- Roadmap Phases 3, 4 and 5 are done and committed.

## Decisions

- The catalog grows from 51 to 70 exact strings. The four `general` placeholders for `payroll`, `customers`, `inventory` and `procurement` are replaced, not kept alongside.
- Existing role grants on a placeholder are mapped level for level. A role with `payroll.general.manage` gets every new payroll string whose action is `view` or `manage`, but not `approve`.
- The new `hr.*` strings are not granted automatically to existing roles. Owners gain them, because owners hold every active permission. Other roles get them when the owner edits the role.
- `PermissionCatalog::sync()` becomes authoritative for system rows: it deletes `is_system = true` rows that are no longer in the config. Custom admin-created rows (`is_system = false`) are never deleted.
- Templates accept either a level string or a list of exact permission strings. Exact lists keep the front desk template from gaining budgets, expenses or supplier payments.
- `payments.user_id` becomes nullable so a walk-in sale needs no account. Deleting a user sets the column to null instead of deleting the payment.
- `payments.income_type` is derived in a model `creating` hook, never typed on screen for membership, session or online payments.
- `employee_profiles.ended_on` marks the last day of employment. Removing an employee still detaches gym access immediately and leaves history intact.
- Day classification is one method used by the nightly absence job and payroll. This closes the old C-22 disagreement about Saturdays.

## Schema changes

### `payments`

| Column | Type | Notes |
|---|---|---|
| `user_id` | nullable FK, `nullOnDelete` | walk-in sales have no account |
| `income_type` | string(20), default `other` | backfilled, then always set explicitly or derived |
| `discount` | decimal(12,2), default 0 | desk sales only |
| `payer_name` | string(150), nullable | walk-in buyer name |

New indexes: `(gym_id, paid_at)` and `(gym_id, income_type, paid_at)`.

### `employee_profiles`

| Column | Type | Notes |
|---|---|---|
| `ended_on` | date, nullable | last day of employment |
| `employment_type` | string(20), default `employee` | `employee` or `contractor` |

### `gyms`

| Column | Type | Default |
|---|---|---|
| `payroll_frequency` | string(20) | `semi_monthly` |
| `payroll_steps` | json, nullable | `null` means `["owner"]` |
| `staff_grace_minutes` | unsigned small int | 10 |
| `overtime_min_minutes` | unsigned small int | 30 |

## Tasks

- [x] **Catalog.** Replace `backend/config/permissions.php` with the 70-entry catalog:

```php
<?php

return [
    'catalog' => [
        'hr' => [
            'staff' => ['view', 'manage'],
            'roles' => ['view', 'manage'],
            'schedules' => ['view', 'manage'],
            'leave' => ['view', 'manage'],
            'holidays' => ['manage'],
            'attendance' => ['view', 'manage'],
            'overtime' => ['approve'],
            'performance' => ['view', 'manage'],
        ],
        'gym_setup' => [
            'settings' => ['view', 'manage'],
            'rooms' => ['view', 'manage'],
            'services' => ['view', 'manage'],
        ],
        'training' => [
            'sessions' => ['view', 'manage'],
            'bookings' => ['view', 'manage', 'approve'],
            'attendance' => ['view', 'manage'],
            'check_ins' => ['manage'],
            'athletes' => ['view', 'manage'],
        ],
        'members' => [
            'plans' => ['view', 'manage'],
            'memberships' => ['view', 'manage', 'approve'],
        ],
        'health_records' => ['records' => ['view']],
        'finance' => [
            'payments' => ['view', 'manage'],
            'expenses' => ['view', 'manage'],
            'receivables' => ['view'],
            'payables' => ['view', 'manage'],
            'budgets' => ['view', 'manage'],
            'reports' => ['view'],
        ],
        'payroll' => [
            'runs' => ['view', 'manage', 'approve'],
            'rates' => ['manage'],
        ],
        'customers' => [
            'records' => ['view', 'manage'],
            'groups' => ['manage'],
        ],
        'inventory' => [
            'items' => ['view', 'manage'],
            'movements' => ['manage'],
        ],
        'procurement' => [
            'requests' => ['view', 'manage', 'approve'],
            'suppliers' => ['view', 'manage'],
            'orders' => ['view', 'manage'],
            'receipts' => ['manage'],
        ],
        'announcements' => ['general' => ['view', 'manage', 'approve']],
        'decision_support' => ['general' => ['view', 'manage', 'approve']],
    ],
];
```

- [x] **Template expansion.** Add `PermissionCatalog::expandTemplate(array $template): list<string>` to `backend/app/Support/PermissionCatalog.php`. A value that is an array is a list of exact permission strings, validated with `assertKnown()`; a value that is a string is expanded with `expandLevels()`. Replace the template expansion in `backend/app/Support/SystemRoles.php:29`, `backend/app/Http/Controllers/Gym/GymRoleController.php:31`, `backend/database/seeders/DatabaseSeeder.php:279`, and the test helper that reads `config('access.templates.front_desk')` in `backend/tests/Feature/PhaseFiveRegressionTest.php:39`.

- [x] **Templates.** Update `backend/config/access.php`:
  - `front_desk.finance` becomes `['finance.payments.view', 'finance.payments.manage', 'finance.receivables.view']`.
  - `front_desk` gains `'inventory' => ['inventory.items.view', 'inventory.movements.manage']`.
  - `hr.hr` becomes `'approve'` so the HR starter role also gets `hr.overtime.approve`.
  - `finance` and `coach` stay level-based; verify the expanded sets are intended.

- [x] **Sync cleanup.** In `PermissionCatalog::sync()`, after inserting missing rows, delete `permissions` rows where `is_system = true` and `permission_string` is not in `all()`. The pivot cascades, so a retired permission loses its grants. Never delete `is_system = false` rows.

- [x] **Placeholder mapping helper.** Add `PermissionCatalog::replacementFor(string $permission): list<string>` that maps one of the twelve `{payroll|customers|inventory|procurement}.general.{view|manage|approve}` strings to the new strings in that module whose action is covered by the old level. Unknown strings return `[]`. This keeps the migration logic testable.

- [x] **Migration `backend/database/migrations/2026_11_10_100000_replace_business_placeholder_permissions.php`.** Order matters:
  1. Read every role's current permission strings into memory, grouped by role id.
  2. Call `PermissionCatalog::sync()` (this adds the 70 rows and deletes the 12 placeholder rows; the pivot cascades).
  3. For each role, compute the replacement strings from its captured placeholder grants and attach them.
  4. Never attach an `approve` string from a `manage` grant.
  `down()` recreates the twelve placeholder system rows and leaves new grants in place. It must not delete permissions that roles now hold. Document that rollback is not lossless.

- [x] **`canSeePay`.** Change `backend/app/Models/User.php:113` from `payroll.general.view` to `payroll.runs.view`. Move the same string in `backend/tests/Feature/AccessTest.php:178`. This must ship with the catalog change, because `hasPermission()` throws on unknown strings.

- [x] **Payment columns and model.** Migration `2026_11_10_100001_add_income_to_payments.php`:
  - Drop the `user_id` foreign key, make the column nullable, re-add the foreign key with `nullOnDelete`.
  - Add `income_type` (string 20, default `other`), `discount` (decimal 12,2, default 0), `payer_name` (string 150, nullable).
  - Add the two indexes.
  - Backfill with `DB::table('payments')`, not the model: `membership_id` not null gives `membership`; `session_booking_id` not null joins the booking and its session, giving `private_coaching` for that session type and `session` otherwise; anything else stays `other`.
  - Confirm the `dropForeign` + `change()` path on SQLite and MySQL. Laravel 13 supports both through table alteration.

- [x] **Income typing hook.** In `backend/app/Models/Payment.php`:
  - Add `income_type`, `discount`, `payer_name` to `Fillable`.
  - Add a `creating` hook: if `income_type` is empty, derive `membership` from `membership_id`, `session` or `private_coaching` from the booking's session type, and otherwise throw. A correction (`corrects_id`) copies the original's type.
  - Cast `discount` as `decimal:2`.
  - Do not change the immutability guard; `income_type` is written once at creation.

- [x] **Employment end.** Migration `2026_11_10_100002_add_employment_end.php` adds `ended_on` and `employment_type` to `employee_profiles`. Update `EmployeeProfile` fillable and casts, `PresentsHr::employeePayload()`, `StaffController@update` rules (allow `ended_on` and `employment_type` for pay-visible editors), and `StaffController::destroy` to set `ended_on = today` when it is null.

- [x] **Gym settings.** Migration `2026_11_10_100003_add_business_settings_to_gyms.php` adds the four columns. Update `Gym` fillable, casts (`payroll_steps` array, the two integers) and defaults. `GymSetupController@options` exposes the frequency list, payroll step list and the two thresholds; `GymSetupController@update` validates them. The payroll settings screen arrives in 6F; the values exist now.

- [x] **Day classification.** Add `backend/app/Enums/DayKind.php` with `Working`, `Rest`, `HolidayRegular`, `HolidaySpecial`. Add `WorkingDays::kind(Gym $gym, User $employee, Carbon $date): DayKind`:
  1. National holiday: regular or special from config.
  2. Gym holiday: special.
  3. The employee has a timed schedule for that weekday: `Working`.
  4. The gym's working days include the weekday: `Working`.
  5. Otherwise: `Rest`.

  Add `kind` to each entry in `backend/config/holidays.php` (`regular` or `special`) and surface it through `NationalHolidays::forYear()` as a third key. Verify the classification against the current Philippine proclamation; the fixed list is a starting point, not legal advice.

- [x] **Config files.** Add `backend/config/payroll.php`:
  - `frequencies` (semi-monthly, monthly labels)
  - `steps` (`hr_review`, `finance_review`, `owner` labels)
  - `overtime_multiplier` = 1.25
  - `holiday_premiums` = `['regular' => 1.00, 'special' => 0.30]`
  - `default_grace_minutes` = 10
  - `default_overtime_min_minutes` = 30

  Add `backend/config/finance.php`:
  - `income_types` = membership, session, private_coaching, walk_in, merchandise, other, each with a label
  - `expense_categories` = rent, utilities, equipment, supplies, maintenance, marketing, professional_fees, taxes_licenses, payroll, statutory_contributions, purchases, other
  - `purchase_expense_timing` = `receipt`

- [x] **Tests.**
  - `PermissionCatalogTest`: 70 strings, unique, lowercase, template expansion for exact lists and levels, `replacementFor` for each placeholder and level.
  - `AccessTest`: owner holds 70; the pay-visibility string is `payroll.runs.view`.
  - `AdminPermissionTest`: 70 catalog rows.
  - `RouteAccessTest`: the route map still resolves; no Phase 6 routes yet.
  - `PaymentIncomeTypeTest`: membership, session, private coaching, explicit walk-in, correction copies type, unknown type throws, null `user_id` allowed.
  - `StaffTest`: removing an employee sets `ended_on` and leaves the profile.
  - `DayKindTest`: holiday beats schedule, gym holiday is special, timed schedule beats gym week, rest day.
  - `WorkspaceFoundationTest`: new gym and payment columns exist.

- [x] **Docs.** Correct the stale dependency table in `docs/ANALYSIS - COMBAT SPORTS PLATFORM/05 - ROADMAP/phase-6/PHASE 6.md` (R-23 to R-27, R-42, R-51 are done) and the stale Phase 5 row in `05 - ROADMAP.md`. Add a short note to `00 - START HERE.md`.

## Checks

```bash
cd backend
php artisan test
php artisan migrate:fresh --seed        # MySQL
php artisan app:sync-permissions

cd ../frontend
pnpm lint
pnpm build
```

## Done when

- [x] `PermissionCatalog::all()` returns exactly 70 strings and every module in `Module` has catalog entries.
- [x] A role holding `payroll.general.manage` ends up with every payroll view and manage string after the migration, and no approve string.
- [x] A role holding `customers.general.view` ends up with `customers.records.view` only.
- [x] The twelve placeholder permissions no longer exist, and no role holds a string that is absent from the config.
- [x] Owners hold all 70. `canSeePay()` works for a holder of `payroll.runs.view`.
- [x] A payment created from a membership gets `income_type = membership`; a private coaching booking gets `private_coaching`; a null-linked payment without a type throws.
- [x] A payment may have `user_id = null`; deleting a user nulls it and keeps the row.
- [x] Removing an employee sets `ended_on` and leaves the profile row.
- [x] `WorkingDays::kind()` returns holiday before schedule, and the absence job tests in a later packet can rely on it.
- [x] Backend tests pass; frontend lint and build pass.
- [x] `migrate:fresh --seed` passes on MySQL.

## Status notes

- 2026-11-05: Started. The RBAC refactor was committed separately as `RBAC: replace module levels with an exact permission catalog`.
- 2026-11-05: Catalog expanded from 51 to 70 exact keys. Files: `backend/config/permissions.php`, `backend/app/Support/PermissionCatalog.php` (exact templates, placeholder replacement, retired-key cleanup), `backend/config/access.php`, `backend/app/Support/SystemRoles.php`, `backend/app/Http/Controllers/Gym/GymRoleController.php`, `backend/tests/Feature/Concerns/GivesGymRoles.php`, `backend/database/seeders/DatabaseSeeder.php`.
- 2026-11-05: The legacy RBAC migration now inserts its own frozen 51 keys, so a fresh migration never reads a later catalog. New migration `2026_11_10_100000_replace_business_placeholder_permissions.php` captures grants, syncs, maps them level for level, and retires the 12 placeholders.
- 2026-11-05: `payments` gained `income_type`, `discount`, `payer_name` and a nullable `user_id`, with two indexes. The backfill and the model hook derive the type from the membership, the booking session type, or an explicit desk type. Files: migration `2026_11_10_100001_add_income_to_payments.php`, `backend/app/Models/Payment.php`, `backend/config/finance.php`.
- 2026-11-05: Employment end and type, plus gym payroll and attendance settings. Files: migrations `2026_11_10_100002_add_employment_end.php` and `2026_11_10_100003_add_business_settings_to_gyms.php`, `EmployeeProfile`, `Gym`, `PresentsHr`, `StaffController`, `GymSetupController`, `config/hr.php`, `config/payroll.php`.
- 2026-11-05: One day classification for attendance and payroll. Files: `app/Enums/DayKind.php`, `app/Support/WorkingDays.php`, `app/Support/NationalHolidays.php`, `config/holidays.php`.
- 2026-11-05: Tests added or updated: `PaymentIncomeTypeTest`, `DayKindTest`, `PermissionCatalogTest`, `PermissionMigrationTest`, `StaffTest`, `WorkspaceFoundationTest`, `WorkspaceSetupTest`, `AccessTest`, `AdminPermissionTest`. Each passed in a targeted run.
- 2026-11-05: Checks: `php artisan test` 295 passed; `pnpm lint` exit 0 (existing warnings only); `pnpm build` passed (existing chunk-size warning); MySQL `migrate:fresh --seed` passed on a throwaway `combat_platform_p6check` database, which reported 70 permissions, 0 placeholders and 30 roles holding new keys, then was dropped.
- 2026-11-05: Docs synced: Phase 6 dependency table, roadmap Phase 5 row and next item, open questions Q-01, Q-02, Q-03 and Q-14.

---

[← Previous: Overview](./00-overview.md) | [Next: Packet 6A HR extras →](./phase-2.md)
