# Permission Catalog RBAC Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the combat project's "one level per module" roles with the photo project's model: a catalog of named permissions, roles that hold any set of them, and grants that can be turned off or expire.

**Architecture:** A `permissions` table holds one row per `module.resource.action` string (for example `training.sessions.manage`). Gym roles link to permissions through a pivot table, and employees link to roles through a pivot that can carry an end date. Every check asks for an exact permission string through `User::hasPermission()` or the `permission:` route middleware. Gym separation, the "no access above your own" rule and the "no changing your own roles" rule stay as they are today.

**Tech Stack:** Laravel 13 (PHP 8.3), PHPUnit feature tests on SQLite, React 19 + TypeScript + Vite, Base UI components in `frontend/src/components/ui`.

**Spec:** No separate spec. The source design is the photo project at `D:\Personal Files\Projects\CapsApp\project-00-photo`:
- `app/Models/StudioOwner/RoleModel.php`, `PermissionModel.php`
- `app/Models/UserModel.php` (`activeRoles`, `getAllPermissions`, `hasPermission`)
- `app/Http/Middleware/CheckPermissionMiddleware.php`
- `app/Http/Controllers/StudioOwner/RoleController.php`, `PermissionController.php`
- migrations `create_tbl_roles_table`, `create_tbl_permissions_table`, `create_tbl_role_permissions_table`, `create_tbl_user_roles_table`, `add_is_system_to_tbl_roles_table`, `update_tbl_permissions_for_resource_action_protocol`

## Execution record (5 November 2026)

**Status:** implementation completed; final verification recorded in `rbac-verification.md`. Changes remain uncommitted because the supplied seeder contains overlapping user work. Commit checkboxes below are intentionally deferred. User-owned `backend/tests/Feature/DatabaseSeederTest.php` was run, not edited.

**Approved corrections override illustrative snippets below:** preserve or explicitly remove disabled keys and dormant grants; never add or reactivate access the actor lacks. Block all self-assignment and expiry changes and edits/toggles of any self-held role, including expired grants. Staff creation and offboarding cannot bypass those guards. Rollback must refuse lossy conversions, disabled state, and dated grants; see `rbac-operations.md` for backup recovery. Code checks remain config-authoritative. Read permissions are independent of Manage and Approve. Editor lower ticks are only a user convenience. Existing UI direction was preserved with antislop during, without installation or redesign.

**Implementation differences:** migration uses unique timestamp `2026_11_05_110000`; its mapping is frozen, not read from future configuration. Stable `template_key` prevents renamed starter duplicates. Global catalog usage bypasses the role gym scope only for catalog relations. Role names are case-insensitively unique; custom modules and segment lengths match schema. Existing expired grants are preserved by omitting unchanged dates. Browser smoke uses a disposable SQLite database, never configured MySQL. No broad staging or configured database reset was performed.

| Task | Status | Backend verification after task |
|---|---|---|
| 1 Catalog | Complete | 250 tests passed, including 6 catalog tests |
| 2 Storage and guards | Complete | 262 passed, including storage, migration, and safety cases |
| 3 Route middleware | Complete | 263 passed; exact route-to-resource map asserted |
| 4 Inline checks | Complete | 266 passed; independent permissions and recipients tested |
| 5 Lifecycle | Complete | 271 passed; expiry, self-toggle, seed rename/collision covered |
| 6 Admin catalog | Complete | 275 passed; global usage/delete and validation covered |
| 7 API and frontend checks | Complete | 276 passed; frontend build/lint passed after coupled Task 8 edits |
| 8 Role UI | Complete | 276 passed; browser role/assignment/reader smoke passed |
| 9 Admin UI | Complete | 276 passed; browser catalog CRUD/status smoke passed |
| 10 Docs and final checks | Complete | 282 tests and 22,871 assertions passed; build/lint and browser evidence recorded |

The implementation added migration, seeder-repeat, and safety coverage beyond the original snippets. Initial baseline was 244 tests, not 234. Original snippets remain reference material, not safe copy-and-run deployment or rollback instructions.

## Global Constraints

- Run backend commands from `backend/`, frontend commands from `frontend/`.
- Backend tests: `php artisan test` (SQLite, `RefreshDatabase`). Verified baseline: 244 passing tests. The suite must pass after every task.
- Frontend checks: `pnpm build` (runs `tsc -b`) and `pnpm lint` (oxlint). The frontend has no unit test runner.
- Permission strings are lowercase `module.resource.action`. Module names match `App\Enums\Module` values. Matching is exact; there is no fuzzy matching.
- Gym roles stay scoped to one gym through `App\Models\Concerns\BelongsToGym`.
- Owners hold every **active** permission because they own the gym. They are never given a role.
- New or reactivated access must be within the actor's exact active permissions. Existing disabled keys and dormant grants may be preserved or explicitly removed under the approved cleanup rules. Active access being handled remains guarded. Nobody may change their own assignments or expiry, or edit/toggle a role they hold.
- Write code comments and UI copy in plain English, matching the existing files.
- Commit only the files each task names. The working tree has other uncommitted work, so never use `git add -A` or `git add .`.

## What is copied from the photo project, and what is not

**Copied:**

| Photo project | Combat project after this plan |
|---|---|
| `tbl_permissions` (portal, resource, action, permission_string, name, description, status) | `permissions` (module, resource, action, permission_string, name, description, status, is_system) |
| `tbl_roles` (name, description, status, is_system) | `gym_roles` gains description, status, is_system |
| `tbl_role_permissions` pivot | `gym_role_permission` pivot |
| `tbl_user_roles` with `studio_id` and `expires_at` | `gym_role_user` gains `expires_at` and `granted_by` (the gym is already on the role) |
| `permission:a,b` middleware, any one is enough | `permission:a,b` middleware, any one is enough |
| `hasPermission()` that skips inactive roles, inactive permissions and expired grants | `User::hasPermission()` with the same rules |
| Permission CRUD screen with an on/off switch | Platform admin permission catalog screen with an on/off switch |
| Role CRUD with an on/off switch; system roles cannot be deleted | Same, per gym. Each new gym gets the four template roles as system roles. |

**Changed on purpose (each one closes a gap found in the photo project):**

1. **Roles belong to one gym.** In the photo project `tbl_roles` has no studio column, so one studio owner can edit or delete roles another studio uses.
2. **Exact matching.** The photo project's `identifierMatches()` accepts a missing portal prefix and reordered words, which can grant more than intended.
3. **No-escalation and no-self-change rules stay.** The photo project has neither.
4. **Only the platform admin edits the catalog.** The catalog is shared by every gym, so a gym owner editing it would change other gyms.
5. **No portal column.** The combat project is one app, not separate portals.
6. **No booking-scoped grants.** The photo project uses these for freelancers, and the combat project has none. End dates are kept.
7. **No soft deletes.** A role can only be deleted when nobody holds it, and a permission only when no role uses it, so hard deletes lose nothing.
8. **No static permission cache.** The current system has none, and a static cache goes stale across requests in tests.

**Limits of a permission catalog:** a permission the admin creates in the UI has no effect until code checks it. The route test in Task 3 makes sure every permission the code uses exists in `config/permissions.php`. The admin screen tells admins about this limit.

## Permission map

Every permission action equals the level the code checks today. A role saved as `training => manage` therefore becomes "every training permission whose action is view or manage". The current behavior survives the move unchanged.

| Module | Resource | Actions | Used by |
|---|---|---|---|
| hr | staff | view, manage | staff list and edit, employee profile, coach calendar |
| hr | roles | view, manage | roles screen and role assignment |
| hr | schedules | view, manage | work schedules |
| hr | leave | view, manage | see all leave, decide leave |
| hr | holidays | manage | add and remove holidays |
| gym_setup | settings | view, manage | setup screen |
| gym_setup | rooms | view, manage | rooms |
| gym_setup | services | view, manage | services |
| training | sessions | view, manage | timetable admin, roster, session notes |
| training | bookings | view, manage, approve | booking desk |
| training | attendance | view, manage | attendance and check-out |
| training | check_ins | manage | check-in desk |
| training | athletes | view, manage | athlete record view and entries |
| members | plans | view, manage | membership plans |
| members | memberships | view, manage, approve | members, applications, renew, freeze, cancel |
| health_records | records | view | medical files and health details |
| finance | payments | view, manage | payments desk |
| payroll, customers, inventory, procurement, announcements, decision_support | general | view, manage, approve | modules not built yet; payroll view is used for seeing pay |

The catalog has 51 permissions in total.

## File map

**Create (backend):**
- `config/permissions.php`: the system catalog.
- `app/Support/PermissionCatalog.php`: lists, checks and syncs catalog strings, and expands the old level shorthand.
- `app/Support/RoleGuard.php`: the "not above your own access" check.
- `app/Support/SystemRoles.php`: creates the template roles for a gym.
- `app/Models/Permission.php`
- `app/Http/Middleware/RequirePermission.php`
- `app/Http/Controllers/Admin/AdminPermissionController.php`
- `database/migrations/2026_11_05_100000_move_gym_roles_to_permission_catalog.php`
- `tests/Feature/Concerns/GivesGymRoles.php`
- `tests/Feature/PermissionCatalogTest.php`, `RouteAccessTest.php`, `RoleLifecycleTest.php`, `AdminPermissionTest.php`

**Modify (backend):** `app/Models/GymRole.php`, `app/Models/User.php`, `app/Models/Gym.php`, `app/Support/GymRoleAssigner.php`, `app/Support/BookingDesk.php`, `app/Http/Controllers/Gym/GymRoleController.php`, `StaffController.php`, `LeaveController.php`, `MembershipController.php`, `AthleteRecordController.php`, `CoachCalendarController.php`, `EmployeeProfileController.php`, `ScheduleController.php`, `ServiceController.php`, `SessionNoteController.php`, `Concerns/PresentsHr.php`, `app/Http/Controllers/MembershipApplicationController.php`, `app/Http/Controllers/Admin/GymReviewController.php`, `app/Http/Resources/UserResource.php`, `bootstrap/app.php`, `routes/api.php`, `routes/console.php`, `config/access.php`, `database/seeders/DatabaseSeeder.php`, and the test files listed in Task 2.

**Delete (backend):** `app/Http/Middleware/RequireModuleAccess.php`

**Create (frontend):** `src/pages/admin/AdminPermissions.tsx`

**Modify (frontend):** `src/lib/auth.tsx`, `src/lib/gym-access.ts`, `src/lib/workspace.ts`, `src/components/gym-nav.tsx`, `src/components/gym/role-dialog.tsx`, `src/components/gym/staff-dialogs.tsx`, `src/pages/gym/GymStaff.tsx`, every page listed in Task 7, `src/App.tsx`, `src/pages/admin/AdminHome.tsx`

**Known gap between tasks:** after Task 2 the backend sends role permissions as strings, and the catalog moves to `/api/permissions/catalog`. The Staff page's roles tab stays broken until Task 8. Run the tasks in one stretch.

---

### Task 1: Permission catalog

**Files:**
- Create: `backend/config/permissions.php`
- Create: `backend/app/Support/PermissionCatalog.php`
- Test: `backend/tests/Feature/PermissionCatalogTest.php`

**Interfaces:**
- Produces:
  - `PermissionCatalog::all(): list<string>`
  - `PermissionCatalog::exists(string $permission): bool`
  - `PermissionCatalog::assertKnown(string ...$permissions): void`, which throws `InvalidArgumentException`
  - `PermissionCatalog::expandLevels(array<string, ?string> $levels): list<string>`
  - `PermissionCatalog::label(string $resource, string $action): string`
  - `PermissionCatalog::sync(): void`, which needs the `permissions` table from Task 2 and is used from Task 2 on

- [x] **Step 1: Write the failing test**

`backend/tests/Feature/PermissionCatalogTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\Module;
use App\Support\PermissionCatalog;
use InvalidArgumentException;
use Tests\TestCase;

/** The permission catalog that every access check reads from. */
class PermissionCatalogTest extends TestCase
{
    public function test_catalog_covers_every_module_and_nothing_else(): void
    {
        $this->assertEqualsCanonicalizing(
            array_column(Module::cases(), 'value'),
            array_keys(config('permissions.catalog')),
        );
    }

    public function test_catalog_lists_51_unique_lowercase_strings(): void
    {
        $all = PermissionCatalog::all();

        $this->assertCount(51, $all);
        $this->assertSame($all, array_values(array_unique($all)));
        foreach ($all as $permission) {
            $this->assertMatchesRegularExpression('/^[a-z_]+\.[a-z_]+\.[a-z_]+$/', $permission);
        }
    }

    public function test_levels_expand_to_every_action_up_to_that_level(): void
    {
        $expanded = PermissionCatalog::expandLevels(['training' => 'manage', 'members' => null]);

        $this->assertContains('training.sessions.view', $expanded);
        $this->assertContains('training.sessions.manage', $expanded);
        $this->assertContains('training.check_ins.manage', $expanded);
        $this->assertNotContains('training.bookings.approve', $expanded);
        $this->assertEmpty(array_filter($expanded, fn (string $p) => str_starts_with($p, 'members.')));
    }

    public function test_unknown_modules_and_levels_expand_to_nothing(): void
    {
        $this->assertSame([], PermissionCatalog::expandLevels(['nope' => 'view', 'training' => 'boss']));
    }

    public function test_unknown_permission_is_refused(): void
    {
        PermissionCatalog::assertKnown('finance.payments.view');

        $this->expectException(InvalidArgumentException::class);
        PermissionCatalog::assertKnown('finance.payments.refund');
    }

    public function test_label_reads_as_plain_words(): void
    {
        $this->assertSame('Manage check ins', PermissionCatalog::label('check_ins', 'manage'));
    }
}
```

- [x] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter PermissionCatalogTest`
Expected: FAIL with `Class "App\Support\PermissionCatalog" not found`.

- [x] **Step 3: Write the catalog config**

`backend/config/permissions.php`:

```php
<?php

// The permission catalog: module => resource => actions. Code checks the
// strings "module.resource.action" (for example "training.sessions.manage").
// `php artisan app:sync-permissions` writes them to the permissions table.
// A check in code for a string that is not listed here throws an error.
$later = ['general' => ['view', 'manage', 'approve']];

return [
    'catalog' => [
        'hr' => [
            'staff' => ['view', 'manage'],
            'roles' => ['view', 'manage'],
            'schedules' => ['view', 'manage'],
            'leave' => ['view', 'manage'],
            'holidays' => ['manage'],
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
        'health_records' => [
            'records' => ['view'],
        ],
        'finance' => [
            'payments' => ['view', 'manage'],
        ],
        // Modules not built yet keep one general resource until their screens exist.
        'payroll' => $later,
        'customers' => $later,
        'inventory' => $later,
        'procurement' => $later,
        'announcements' => $later,
        'decision_support' => $later,
    ],
];
```

- [x] **Step 4: Write `PermissionCatalog`**

`backend/app/Support/PermissionCatalog.php`:

```php
<?php

namespace App\Support;

use App\Enums\AccessLevel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Reads the system permission catalog in config/permissions.php. */
final class PermissionCatalog
{
    /** @return list<string> */
    public static function all(): array
    {
        $all = [];

        foreach (config('permissions.catalog') as $module => $resources) {
            foreach ($resources as $resource => $actions) {
                foreach ($actions as $action) {
                    $all[] = "{$module}.{$resource}.{$action}";
                }
            }
        }

        return $all;
    }

    public static function exists(string $permission): bool
    {
        return in_array($permission, self::all(), true);
    }

    /** Fails loudly when code asks for a permission the catalog does not have (a typo). */
    public static function assertKnown(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            if (! self::exists($permission)) {
                throw new InvalidArgumentException("Unknown permission: {$permission}");
            }
        }
    }

    /**
     * Turns the old "module => level" shorthand into permission strings: every
     * action in that module up to the level. ['training' => 'manage'] gives every
     * training view and manage permission. Used by the data migration, the
     * role templates, the seeder and the tests.
     *
     * @param  array<string, string|null>  $levels
     * @return list<string>
     */
    public static function expandLevels(array $levels): array
    {
        $expanded = [];

        foreach ($levels as $module => $level) {
            $limit = AccessLevel::tryFrom((string) $level);

            if (! $limit) {
                continue;
            }

            foreach (config("permissions.catalog.{$module}", []) as $resource => $actions) {
                foreach ($actions as $action) {
                    if ($limit->covers(AccessLevel::from($action))) {
                        $expanded[] = "{$module}.{$resource}.{$action}";
                    }
                }
            }
        }

        return $expanded;
    }

    /** "check_ins", "manage" -> "Manage check ins". */
    public static function label(string $resource, string $action): string
    {
        return ucfirst($action).' '.str_replace('_', ' ', $resource);
    }

    /**
     * Adds missing catalog permissions to the table and marks catalog rows as
     * system rows. Never changes status, so a permission the admin turned off stays off.
     */
    public static function sync(): void
    {
        $now = now();

        foreach (self::all() as $permission) {
            [$module, $resource, $action] = explode('.', $permission);

            $updated = DB::table('permissions')
                ->where('permission_string', $permission)
                ->update(['is_system' => true]);

            if ($updated === 0 && ! DB::table('permissions')->where('permission_string', $permission)->exists()) {
                DB::table('permissions')->insert([
                    'module' => $module,
                    'resource' => $resource,
                    'action' => $action,
                    'permission_string' => $permission,
                    'name' => self::label($resource, $action),
                    'description' => null,
                    'status' => 'active',
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
```

- [x] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter PermissionCatalogTest`
Expected: PASS (6 tests).

- [ ] **Step 6: Commit**

```bash
git add backend/config/permissions.php backend/app/Support/PermissionCatalog.php backend/tests/Feature/PermissionCatalogTest.php
git commit -m "Add the permission catalog"
```

---

### Task 2: Move gym roles onto the catalog

This task changes how roles are stored. The old checks (`accessLevel`, `canInGym`, `levelFor`, the `module:` middleware) keep working through thin replacements until Tasks 3, 4 and 7 remove them.

**Files:**
- Create: `backend/database/migrations/2026_11_05_100000_move_gym_roles_to_permission_catalog.php`
- Create: `backend/app/Models/Permission.php`
- Create: `backend/app/Support/RoleGuard.php`
- Create: `backend/tests/Feature/Concerns/GivesGymRoles.php`
- Modify: `backend/app/Models/GymRole.php` (whole file)
- Modify: `backend/app/Models/User.php:75-105`
- Modify: `backend/app/Support/GymRoleAssigner.php` (whole file)
- Modify: `backend/app/Http/Controllers/Gym/GymRoleController.php` (whole file)
- Modify: `backend/routes/api.php:69`
- Modify: `backend/database/seeders/DatabaseSeeder.php:258-264`
- Modify tests: `AccessTest.php`, `Concerns/MembershipSetup.php`, `LeaveTest.php`, `PlanTest.php`, `RoomTest.php`, `ServiceTest.php`, `StaffTest.php`, `WorkspaceFoundationTest.php`, `WorkspaceSetupTest.php`

**Interfaces:**
- Consumes: everything Task 1 produces.
- Produces:
  - `Permission` model with the `active()` scope and a `roles()` relation
  - `GymRole::permissions(): BelongsToMany`
  - `GymRole::permissionStrings(): list<string>`
  - `GymRole::syncPermissionStrings(list<string>): void`
  - `GymRole::active()` scope
  - `GymRole::users()`, which now loads the `expires_at` and `granted_by` pivot columns
  - `User::permissionsIn(Gym|int): Collection<int, string>`
  - `User::hasPermission(Gym|int, string ...$anyOf): bool`
  - `RoleGuard::ensureWithin(Collection<int,string> $own, list<string> $permissions): void`, which aborts with 403
  - Test trait `GivesGymRoles::makeRole(array $access, string $name = 'Role', array $attributes = []): GymRole`. `$access` accepts permission strings, `'module' => 'level'` shorthand, or both.
  - Endpoint `GET /api/permissions/catalog` returns `{ permissions: [{id, module, resource, action, permission_string, name, description}], templates: { key: { name, permissions: string[] } } }`
  - Role JSON returns `{ id, name, description, status, is_system, permissions: string[], users_count }`

- [x] **Step 1: Rewrite the role tests in `AccessTest` for the new shape (they fail first)**

In `backend/tests/Feature/AccessTest.php`:

1. Add `use App\Support\PermissionCatalog;` and `use Tests\Feature\Concerns\GivesGymRoles;` to the imports.
2. Change `use RefreshDatabase;` to `use GivesGymRoles, RefreshDatabase;`.
3. Replace the `role()` helper (lines 42-50) with:

```php
    /** Creates a role in a gym directly, the way the owner would. */
    private function role(array $permissions, ?Gym $gym = null, string $name = 'Role'): GymRole
    {
        app(CurrentGym::class)->set($gym ?? $this->gym);
        $role = $this->makeRole($permissions, $name);
        $this->app->forgetScopedInstances();

        return $role;
    }
```

4. Replace `test_owner_creates_a_role_from_a_template_edits_it_and_deletes_it` (lines 80-99) with:

```php
    public function test_owner_creates_edits_and_deletes_a_role(): void
    {
        $response = $this->actingAs($this->owner)->postJson($this->url('roles'), [
            'name' => 'Head Coach',
            'description' => 'Runs classes.',
            'permissions' => ['training.sessions.view', 'training.sessions.manage'],
        ])->assertCreated()
            ->assertJsonPath('role.description', 'Runs classes.')
            ->assertJsonPath('role.status', 'active')
            ->assertJsonPath('role.is_system', false);

        $this->assertEqualsCanonicalizing(
            ['training.sessions.view', 'training.sessions.manage'],
            $response->json('role.permissions'),
        );
        $id = $response->json('role.id');

        $this->actingAs($this->owner)->putJson($this->url("roles/{$id}"), [
            'name' => 'Senior Coach', 'permissions' => ['training.bookings.approve'],
        ])->assertOk()
            ->assertJsonPath('role.name', 'Senior Coach')
            ->assertJsonPath('role.permissions', ['training.bookings.approve']);

        $this->actingAs($this->owner)->postJson($this->url('roles'), ['name' => 'Senior Coach', 'permissions' => []])
            ->assertJsonValidationErrors('name');

        $this->actingAs($this->owner)->postJson($this->url('roles'), ['name' => 'Typo', 'permissions' => ['training.nope.view']])
            ->assertJsonValidationErrors('permissions.0');

        $this->actingAs($this->owner)->deleteJson($this->url("roles/{$id}"))->assertOk();
        $this->assertSame(0, GymRole::withoutGlobalScope('gym')->count());
    }

    public function test_catalog_lists_active_permissions_and_expanded_templates(): void
    {
        $response = $this->actingAs($this->owner)->getJson('/api/permissions/catalog')->assertOk();

        $this->assertCount(count(PermissionCatalog::all()), $response->json('permissions'));
        $this->assertSame('Coach', $response->json('templates.coach.name'));
        $this->assertContains('training.sessions.manage', $response->json('templates.coach.permissions'));
    }
```

5. Replace `test_access_comes_from_roles_and_follows_role_changes` (lines 101-126) with:

```php
    public function test_access_comes_from_roles_and_follows_role_changes(): void
    {
        $cashier = $this->role(['finance.payments.view'], name: 'Cashier');
        $desk = $this->role(['finance.payments.view', 'finance.payments.manage', 'members.memberships.view'], name: 'Desk');

        $this->actingAs($this->owner)->putJson($this->url("staff/{$this->employee->id}/roles"), [
            'role_ids' => [$cashier->id, $desk->id],
        ])->assertOk()->assertJsonCount(2, 'roles');

        $this->assertTrue($this->employee->hasPermission($this->gym, 'finance.payments.manage'));

        // Editing a role changes access for everyone holding it.
        $this->actingAs($this->owner)->putJson($this->url("roles/{$desk->id}"), [
            'name' => 'Desk', 'permissions' => ['members.memberships.view'],
        ])->assertOk();
        $this->assertFalse($this->employee->hasPermission($this->gym, 'finance.payments.manage'));
        $this->assertTrue($this->employee->hasPermission($this->gym, 'finance.payments.view'));

        // Taking a role away removes its access; the emptied role can then be deleted.
        $this->actingAs($this->owner)->putJson($this->url("staff/{$this->employee->id}/roles"), [
            'role_ids' => [$desk->id],
        ])->assertOk();
        $this->assertFalse($this->employee->hasPermission($this->gym, 'finance.payments.view'));

        $this->actingAs($this->owner)->deleteJson($this->url("roles/{$cashier->id}"))->assertOk();
    }
```

6. Replace `test_nobody_handles_a_role_above_their_own_access_or_changes_their_own_roles` (lines 150-166) with:

```php
    public function test_nobody_handles_a_role_above_their_own_access_or_changes_their_own_roles(): void
    {
        $this->give($this->employee, $this->role(['hr' => 'manage', 'finance.payments.view'], name: 'HR Officer'));
        $colleague = User::factory()->create();
        $this->gym->addPerson($colleague, GymPersonType::Employee);
        $big = $this->role(['finance.payments.view', 'finance.payments.manage'], name: 'Finance Head');
        $small = $this->role(['finance.payments.view'], name: 'Cashier');

        $hr = $this->actingAs($this->employee);
        $hr->postJson($this->url('roles'), ['name' => 'Too much', 'permissions' => ['payroll.general.view']])->assertForbidden();
        $hr->postJson($this->url('roles'), ['name' => 'Ok', 'permissions' => ['finance.payments.view']])->assertCreated();
        $hr->putJson($this->url("roles/{$big->id}"), ['name' => 'Finance Head', 'permissions' => ['finance.payments.view']])->assertForbidden();
        $hr->deleteJson($this->url("roles/{$big->id}"))->assertForbidden();
        $hr->putJson($this->url("staff/{$colleague->id}/roles"), ['role_ids' => [$big->id]])->assertForbidden();
        $hr->putJson($this->url("staff/{$colleague->id}/roles"), ['role_ids' => [$small->id]])->assertOk();
        $hr->putJson($this->url("staff/{$this->employee->id}/roles"), ['role_ids' => []])->assertForbidden();
    }
```

7. In `test_the_shared_gym_rule_fails_closed_and_blocks_cross_gym_writes`, change `new GymRole(['name' => 'Sneaky', 'permissions' => []])` to `new GymRole(['name' => 'Sneaky'])`.
8. Remove the now-unused imports `App\Enums\AccessLevel` and `App\Enums\Module`, if nothing else in the file uses them.

- [x] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter AccessTest`
Expected: FAIL. `GivesGymRoles` is not found.

- [x] **Step 3: Write the test trait**

`backend/tests/Feature/Concerns/GivesGymRoles.php`:

```php
<?php

namespace Tests\Feature\Concerns;

use App\Models\GymRole;
use App\Support\PermissionCatalog;

/** Makes gym roles in tests. The current gym must already be set. */
trait GivesGymRoles
{
    /**
     * $access takes permission strings ('training.sessions.manage'), the old
     * module => level shorthand ('training' => 'manage'), or both.
     *
     * @param  array<int|string, string|null>  $access
     */
    protected function makeRole(array $access, string $name = 'Role', array $attributes = []): GymRole
    {
        $role = GymRole::create(['name' => $name, ...$attributes]);
        $role->syncPermissionStrings(self::accessToPermissions($access));

        return $role;
    }

    /**
     * @param  array<int|string, string|null>  $access
     * @return list<string>
     */
    protected static function accessToPermissions(array $access): array
    {
        $strings = [];
        $levels = [];

        foreach ($access as $key => $value) {
            is_int($key) ? $strings[] = $value : $levels[$key] = $value;
        }

        return array_values(array_unique([...$strings, ...PermissionCatalog::expandLevels($levels)]));
    }
}
```

- [x] **Step 4: Write the migration**

`backend/database/migrations/2026_11_05_100000_move_gym_roles_to_permission_catalog.php`:

```php
<?php

use App\Enums\AccessLevel;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R-08: roles now hold named permissions from a shared catalog instead of one
 * level per module. Existing roles keep exactly the access they had: a level
 * becomes every permission in that module up to the level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module', 40)->index();
            $table->string('resource', 60);
            $table->string('action', 30);
            $table->string('permission_string', 150)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('status', 10)->default('active'); // active or inactive
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('gym_role_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['gym_role_id', 'permission_id']);
        });

        Schema::table('gym_roles', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('status', 10)->default('active')->after('description');
            $table->boolean('is_system')->default(false)->after('status');
        });

        Schema::table('gym_role_user', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('user_id');
            $table->foreignId('granted_by')->nullable()->after('expires_at')->constrained('users')->nullOnDelete();
        });

        PermissionCatalog::sync();

        $ids = DB::table('permissions')->pluck('id', 'permission_string');
        $now = now();

        foreach (DB::table('gym_roles')->get(['id', 'permissions']) as $role) {
            $levels = json_decode($role->permissions ?? '[]', true) ?: [];

            foreach (PermissionCatalog::expandLevels($levels) as $permission) {
                DB::table('gym_role_permission')->insert([
                    'gym_role_id' => $role->id,
                    'permission_id' => $ids[$permission],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Schema::table('gym_roles', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('gym_roles', function (Blueprint $table) {
            $table->json('permissions')->nullable();
        });

        // Back to one level per module: the highest action the role holds there.
        foreach (DB::table('gym_roles')->pluck('id') as $roleId) {
            $levels = [];
            $rows = DB::table('gym_role_permission')
                ->join('permissions', 'permissions.id', '=', 'gym_role_permission.permission_id')
                ->where('gym_role_id', $roleId)
                ->get(['permissions.module', 'permissions.action']);

            foreach ($rows as $row) {
                $level = AccessLevel::tryFrom($row->action);
                $held = isset($levels[$row->module]) ? AccessLevel::from($levels[$row->module]) : null;

                if ($level && (! $held || $level->rank() > $held->rank())) {
                    $levels[$row->module] = $level->value;
                }
            }

            DB::table('gym_roles')->where('id', $roleId)->update(['permissions' => json_encode($levels)]);
        }

        Schema::table('gym_role_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('granted_by');
            $table->dropColumn('expires_at');
        });

        Schema::table('gym_roles', function (Blueprint $table) {
            $table->dropColumn(['description', 'status', 'is_system']);
        });

        Schema::dropIfExists('gym_role_permission');
        Schema::dropIfExists('permissions');
    }
};
```

- [x] **Step 5: Write the `Permission` model**

`backend/app/Models/Permission.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** One entry in the shared permission catalog, written module.resource.action. */
#[Fillable(['module', 'resource', 'action', 'permission_string', 'name', 'description', 'status', 'is_system'])]
class Permission extends Model
{
    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(GymRole::class)->withTimestamps();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('permissions.status', 'active');
    }
}
```

- [x] **Step 6: Rewrite `GymRole`**

Replace the whole of `backend/app/Models/GymRole.php` with:

```php
<?php

namespace App\Models;

use App\Enums\AccessLevel;
use App\Enums\Module;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A role an owner creates for their gym, holding any set of catalog permissions (R-08). */
#[Fillable(['name', 'description', 'status'])]
class GymRole extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['expires_at', 'granted_by'])->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('gym_roles.status', 'active');
    }

    /** @return list<string> */
    public function permissionStrings(): array
    {
        return $this->permissions()->pluck('permission_string')->all();
    }

    /** @param list<string> $permissions */
    public function syncPermissionStrings(array $permissions): void
    {
        $this->permissions()->sync(Permission::whereIn('permission_string', $permissions)->pluck('id'));
    }

    /** Old check kept until Task 4: the highest view/manage/approve action this role holds in one module. */
    public function levelFor(Module $module): ?AccessLevel
    {
        return $this->permissions()->where('module', $module->value)->pluck('action')
            ->map(fn (string $action) => AccessLevel::tryFrom($action))
            ->filter()
            ->sortByDesc(fn (AccessLevel $level) => $level->rank())
            ->first();
    }
}
```

`is_system` is left out of `Fillable` on purpose, so requests can never set it. `SystemRoles` (Task 5) sets it with `forceFill`.

- [x] **Step 7: Add the permission checks to `User`**

In `backend/app/Models/User.php`:

1. Add imports: `use App\Support\PermissionCatalog;`, `use Illuminate\Database\Eloquent\Builder;`, `use Illuminate\Support\Collection;`, `use Illuminate\Support\Str;`.
2. Replace `accessLevel()` (lines 75-92) and `canInGym()` (lines 102-105) with the code below. Leave `gymRoles()`, `canSeePay()` and `canApproveLeave()` as they are for now.

```php
    /**
     * Active permission strings this person holds in one gym. Owners hold every
     * active permission. An employee holds what their active, unexpired roles give.
     *
     * @return Collection<int, string>
     */
    public function permissionsIn(Gym|int $gym): Collection
    {
        $gymId = $gym instanceof Gym ? $gym->id : $gym;

        if ($this->hasGymRole($gymId, GymPersonType::Owner)) {
            return Permission::active()->pluck('permission_string');
        }

        if (! $this->hasGymRole($gymId, GymPersonType::Employee)) {
            return collect();
        }

        return Permission::active()
            ->whereHas('roles', fn (Builder $role) => $role->withoutGlobalScope('gym')
                ->where('gym_roles.gym_id', $gymId)
                ->where('gym_roles.status', 'active')
                ->whereHas('users', fn (Builder $user) => $user->whereKey($this->id)
                    ->where(fn (Builder $grant) => $grant->whereNull('gym_role_user.expires_at')
                        ->orWhere('gym_role_user.expires_at', '>', now()))))
            ->pluck('permission_string');
    }

    /** True when this person holds at least one of the given permissions in the gym. */
    public function hasPermission(Gym|int $gym, string ...$anyOf): bool
    {
        PermissionCatalog::assertKnown(...$anyOf);

        return $this->permissionsIn($gym)->intersect($anyOf)->isNotEmpty();
    }

    /** Old check kept until Task 7: the highest view/manage/approve action held in one module. */
    public function accessLevel(Gym|int $gym, Module $module): ?AccessLevel
    {
        if ($this->hasGymRole($gym, GymPersonType::Owner)) {
            return AccessLevel::Approve;
        }

        return $this->permissionsIn($gym)
            ->filter(fn (string $permission) => str_starts_with($permission, $module->value.'.'))
            ->map(fn (string $permission) => AccessLevel::tryFrom(Str::afterLast($permission, '.')))
            ->filter()
            ->sortByDesc(fn (AccessLevel $level) => $level->rank())
            ->first();
    }

    /** Old check kept until Task 4. */
    public function canInGym(Gym|int $gym, Module $module, AccessLevel $needed): bool
    {
        return (bool) $this->accessLevel($gym, $module)?->covers($needed);
    }
```

- [x] **Step 8: Write `RoleGuard`**

`backend/app/Support/RoleGuard.php`:

```php
<?php

namespace App\Support;

use Illuminate\Support\Collection;

/** Nobody may handle a role holding a permission they do not hold themselves (R-08). */
final class RoleGuard
{
    /**
     * @param  Collection<int, string>  $own
     * @param  list<string>  $permissions
     */
    public static function ensureWithin(Collection $own, array $permissions): void
    {
        $above = array_values(array_diff($permissions, $own->all()));

        abort_if($above !== [], 403, 'You cannot handle a role with access you do not have: '.implode(', ', $above).'.');
    }
}
```

- [x] **Step 9: Rewrite `GymRoleAssigner`**

Replace the whole of `backend/app/Support/GymRoleAssigner.php` with:

```php
<?php

namespace App\Support;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Replaces an employee's roles in one gym. Both the roles given and the roles
 * taken away must be within the editor's own access. Owners cannot be assigned
 * here: ownership is a gym membership, not an owner-made role (R-08).
 */
class GymRoleAssigner
{
    /**
     * @param  array<int, int>  $roleIds
     * @param  array<int|string, string|null>  $expiresAt  role id => last day the role counts (optional)
     */
    public function replace(User $actor, Gym $gym, User $employee, array $roleIds, array $expiresAt = []): void
    {
        abort_unless($employee->hasGymRole($gym, GymPersonType::Employee), 404);
        abort_if($actor->is($employee), 403, 'You cannot change your own roles.');

        $wanted = GymRole::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->whereKey($roleIds)
            ->get();
        abort_if($wanted->count() !== count($roleIds), 422, 'One or more roles do not exist in this gym.');

        $current = $employee->gymRoles($gym)->get();
        $own = $actor->permissionsIn($gym);

        foreach ($wanted->merge($current)->unique('id') as $role) {
            RoleGuard::ensureWithin($own, $role->permissionStrings());
        }

        $current->each(fn (GymRole $role) => $role->users()->detach($employee->id));
        $wanted->each(fn (GymRole $role) => $role->users()->attach($employee->id, [
            'expires_at' => isset($expiresAt[$role->id]) ? Carbon::parse($expiresAt[$role->id])->endOfDay() : null,
            'granted_by' => $actor->id,
        ]));
    }
}
```

- [x] **Step 10: Rewrite `GymRoleController`**

Replace the whole of `backend/app/Http/Controllers/Gym/GymRoleController.php` with:

```php
<?php

namespace App\Http\Controllers\Gym;

use App\Enums\GymPersonType;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\Permission;
use App\Models\User;
use App\Support\GymRoleAssigner;
use App\Support\PermissionCatalog;
use App\Support\RoleGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * R-08 "dynamic RBAC": each owner creates, edits and deletes their gym's own roles,
 * picks any catalog permissions for them and gives them to employees. Nobody may
 * create, change, delete or give a role holding access they do not hold. Roles
 * are found through the gym rule (R-09), so another gym's role ids do not exist here.
 */
class GymRoleController extends Controller
{
    /** The permissions a role can hold, and the starter templates. Open to any signed-in person. */
    public function catalog(): JsonResponse
    {
        return response()->json([
            'permissions' => Permission::active()
                ->orderBy('id')
                ->get(['id', 'module', 'resource', 'action', 'permission_string', 'name', 'description']),
            'templates' => collect(config('access.templates'))->map(fn (array $levels, string $key) => [
                'name' => config("access.names.{$key}", Str::headline($key)),
                'permissions' => PermissionCatalog::expandLevels($levels),
            ]),
        ]);
    }

    public function index(): JsonResponse
    {
        $roles = GymRole::withCount('users')->with('permissions:id,permission_string')->orderBy('name')->get();

        return response()->json(['roles' => $roles->map(fn (GymRole $role) => $this->payload($role))]);
    }

    public function store(Request $request, Gym $gym): JsonResponse
    {
        $data = $this->validated($request, $gym);
        RoleGuard::ensureWithin($request->user()->permissionsIn($gym), $data['permissions']);

        $role = DB::transaction(function () use ($data) {
            $role = GymRole::create(['name' => $data['name'], 'description' => $data['description']]);
            $role->syncPermissionStrings($data['permissions']);

            return $role;
        });

        return response()->json(['role' => $this->payload($role->fresh())], 201);
    }

    public function update(Request $request, Gym $gym, int $role): JsonResponse
    {
        $role = GymRole::findOrFail($role);
        $data = $this->validated($request, $gym, $role);
        $own = $request->user()->permissionsIn($gym);
        RoleGuard::ensureWithin($own, $role->permissionStrings());
        RoleGuard::ensureWithin($own, $data['permissions']);

        DB::transaction(function () use ($role, $data) {
            $role->update(['name' => $data['name'], 'description' => $data['description']]);
            $role->syncPermissionStrings($data['permissions']);
        });

        return response()->json(['role' => $this->payload($role->fresh())]);
    }

    public function destroy(Request $request, Gym $gym, int $role): JsonResponse
    {
        $role = GymRole::findOrFail($role);
        RoleGuard::ensureWithin($request->user()->permissionsIn($gym), $role->permissionStrings());
        abort_if($role->users()->exists(), 422, 'This role is still given to employees. Take it away from them before deleting it.');
        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }

    public function staffRoles(Gym $gym, User $user): JsonResponse
    {
        $this->ensureEmployee($gym, $user);

        return response()->json(['roles' => $this->rolesOf($gym, $user)]);
    }

    /** Replaces the employee's roles with the ones sent. */
    public function assign(Request $request, Gym $gym, User $user): JsonResponse
    {
        $data = $request->validate([
            'role_ids' => ['present', 'array'],
            'role_ids.*' => ['integer', 'distinct'],
        ]);

        app(GymRoleAssigner::class)->replace($request->user(), $gym, $user, $data['role_ids']);

        return response()->json(['roles' => $this->rolesOf($gym, $user)]);
    }

    /** @return array<int, array<string, mixed>> */
    private function rolesOf(Gym $gym, User $user): array
    {
        return $user->gymRoles($gym)->orderBy('name')->get()
            ->map(fn (GymRole $role) => [
                ...$this->payload($role),
                'expires_at' => $role->users()->whereKey($user->id)->first()?->pivot->expires_at,
            ])->all();
    }

    /** @return array<string, mixed> */
    private function payload(GymRole $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'status' => $role->status,
            'is_system' => $role->is_system,
            'permissions' => $role->permissions->pluck('permission_string')->values(),
            'users_count' => $role->users_count ?? $role->users()->count(),
        ];
    }

    /** @return array{name: string, description: ?string, permissions: list<string>} */
    private function validated(Request $request, Gym $gym, ?GymRole $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60',
                Rule::unique('gym_roles')->where('gym_id', $gym->id)->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct',
                Rule::exists('permissions', 'permission_string')->where('status', 'active')],
        ]);

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => array_values($data['permissions']),
        ];
    }

    private function ensureEmployee(Gym $gym, User $user): void
    {
        abort_unless($user->hasGymRole($gym, GymPersonType::Employee), 404);
    }
}
```

- [x] **Step 11: Point the route at the new catalog endpoint, and name the templates**

In `backend/routes/api.php` line 69, replace:

```php
            Route::get('access/templates', [GymRoleController::class, 'templates']);
```

with:

```php
            Route::get('permissions/catalog', [GymRoleController::class, 'catalog']);
```

In `backend/config/access.php`, keep `templates` as it is and add a `names` key after it:

```php
    // Display names for the templates and for the system roles made from them.
    'names' => [
        'coach' => 'Coach',
        'front_desk' => 'Front desk',
        'finance' => 'Finance',
        'hr' => 'HR',
    ],
```

- [x] **Step 12: Switch every other test helper to `makeRole`**

In each file below, add `use Tests\Feature\Concerns\GivesGymRoles;` and add `GivesGymRoles` to the class's `use` list (in `MembershipSetup` add `use GivesGymRoles;` inside the trait). Then make the replacement shown:

| File | Old | New |
|---|---|---|
| `tests/Feature/Concerns/MembershipSetup.php:50` | `GymRole::create(['name' => 'Role '.$user->id, 'permissions' => $permissions])->users()->attach($user->id);` | `$this->makeRole($permissions, 'Role '.$user->id)->users()->attach($user->id);` |
| `tests/Feature/LeaveTest.php:51` | `GymRole::create(['name' => 'HR Manager', 'permissions' => ['hr' => 'manage']])` | `$this->makeRole(['hr' => 'manage'], 'HR Manager')` |
| `tests/Feature/PlanTest.php:48` | `$role = GymRole::create(['name' => $name, 'permissions' => $permissions]);` | `$role = $this->makeRole($permissions, $name);` |
| `tests/Feature/RoomTest.php:46` | same as PlanTest | same as PlanTest |
| `tests/Feature/ServiceTest.php:54` | same as PlanTest | same as PlanTest |
| `tests/Feature/WorkspaceSetupTest.php:46` | same as PlanTest | same as PlanTest |
| `tests/Feature/StaffTest.php:46` | `return GymRole::create(['name' => $name, 'permissions' => $permissions]);` | `return $this->makeRole($permissions, $name);` |
| `tests/Feature/WorkspaceFoundationTest.php:109` | `$hrRole = GymRole::create(['name' => 'HR Officer', 'permissions' => ['hr' => 'manage', 'finance' => 'view']]);` | `$hrRole = $this->makeRole(['hr' => 'manage', 'finance' => 'view'], 'HR Officer');` |
| `tests/Feature/WorkspaceFoundationTest.php:112` | `$big = GymRole::create(['name' => 'Finance Head', 'permissions' => ['finance' => 'approve']]);` | `$big = $this->makeRole(['finance' => 'approve'], 'Finance Head');` |
| `tests/Feature/WorkspaceFoundationTest.php:113` | `$small = GymRole::create(['name' => 'Cashier', 'permissions' => ['finance' => 'view']]);` | `$small = $this->makeRole(['finance' => 'view'], 'Cashier');` |

Remove `use App\Models\GymRole;` from any of these files that no longer use it. Keep it in files that still use the type, such as `StaffTest`'s return type.

Then confirm nothing is left. Run: `rg "'permissions' =>" backend/tests`
Expected: matches only inside the API payloads in `AccessTest.php`.

- [x] **Step 13: Update the seeder**

In `backend/database/seeders/DatabaseSeeder.php`, add `use App\Support\PermissionCatalog;`. Then replace lines 259-264:

```php
            foreach (self::ROLES as $name => $permissions) {
                $roles[$name] = GymRole::create([
                    'name' => $name,
                    'permissions' => $permissions,
                ]);
            }
```

with:

```php
            foreach (self::ROLES as $name => $levels) {
                $roles[$name] = GymRole::create(['name' => $name]);
                $roles[$name]->syncPermissionStrings(PermissionCatalog::expandLevels($levels));
            }
```

`self::ROLES` keeps its module => level shorthand.

- [x] **Step 14: Run the full suite**

Run: `php artisan test`
Expected: PASS. The count grows by one, from the new catalog test in `AccessTest`.

Then check the seeder. Run: `php artisan migrate:fresh --seed`
Expected: finishes with no errors. Use a throwaway local database only, because this command wipes the configured one.

- [ ] **Step 15: Commit**

```bash
git add backend/database/migrations/2026_11_05_100000_move_gym_roles_to_permission_catalog.php backend/app/Models/Permission.php backend/app/Models/GymRole.php backend/app/Models/User.php backend/app/Support/RoleGuard.php backend/app/Support/GymRoleAssigner.php backend/app/Http/Controllers/Gym/GymRoleController.php backend/routes/api.php backend/config/access.php backend/database/seeders/DatabaseSeeder.php backend/tests/Feature
git commit -m "Store gym role access as catalog permissions"
```

---

### Task 3: `permission:` route middleware

**Files:**
- Create: `backend/app/Http/Middleware/RequirePermission.php`
- Modify: `backend/bootstrap/app.php:19`
- Modify: `backend/routes/api.php:115-237`
- Test: `backend/tests/Feature/RouteAccessTest.php`

**Interfaces:**
- Consumes: `User::hasPermission()`, `PermissionCatalog::exists()`.
- Produces: the route middleware alias `permission`. Usage is `->middleware('permission:training.sessions.manage')`, and several comma-separated names mean any one of them is enough.

- [x] **Step 1: Write the failing test**

`backend/tests/Feature/RouteAccessTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Every gym route asks for catalog permissions, never the old module levels. */
class RouteAccessTest extends TestCase
{
    public function test_every_route_permission_is_in_the_catalog(): void
    {
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware)) {
                    continue;
                }

                $this->assertStringStartsNotWith('module:', $middleware, "{$route->uri()} still uses the old module check.");

                if (str_starts_with($middleware, 'permission:')) {
                    foreach (explode(',', substr($middleware, strlen('permission:'))) as $permission) {
                        $this->assertTrue(PermissionCatalog::exists($permission), "{$route->uri()} asks for unknown {$permission}.");
                        $checked++;
                    }
                }
            }
        }

        // About 61 checks today; the floor catches a route file that lost its checks.
        $this->assertGreaterThan(50, $checked);
    }
}
```

- [x] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter RouteAccessTest`
Expected: FAIL with `api/gyms/{gym}/roles still uses the old module check.`

- [x] **Step 3: Write the middleware**

`backend/app/Http/Middleware/RequirePermission.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Support\CurrentGym;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('permission:finance.payments.manage'). Several names mean
 * any one of them is enough. Runs after the gym middleware (R-08, R-09).
 */
class RequirePermission
{
    public function __construct(private CurrentGym $currentGym) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $gym = $this->currentGym->get();

        if (! $gym || ! $request->user()->hasPermission($gym, ...$permissions)) {
            abort(403, 'You do not have access to this part of the gym.');
        }

        return $next($request);
    }
}
```

In `backend/bootstrap/app.php`, add the alias under the existing `'module'` line (the `module` alias is removed in Task 4):

```php
            'permission' => \App\Http\Middleware\RequirePermission::class,
```

- [x] **Step 4: Convert every route**

In `backend/routes/api.php`, replace each `module:` value as listed. Keep everything else on the line as it is (`whereNumber`, `throttle`).

| Line(s) | Route | Old | New |
|---|---|---|---|
| 117 | group: `roles`, `staff/{user}/roles` (GET) | `module:hr,view` | `permission:hr.roles.view` |
| 121 | group: `roles` POST/PUT/DELETE, `staff/{user}/roles` PUT | `module:hr,manage` | `permission:hr.roles.manage` |
| 129 | GET `setup` | `module:gym_setup,view` | `permission:gym_setup.settings.view` |
| 130 | PUT `setup` | `module:gym_setup,manage` | `permission:gym_setup.settings.manage` |
| 134 | GET `rooms` | `module:gym_setup,view` | `permission:gym_setup.rooms.view` |
| 135-137 | POST/PUT/DELETE `rooms` | `module:gym_setup,manage` | `permission:gym_setup.rooms.manage` |
| 144-146 | `check-ins/members`, GET/POST `check-ins` | `module:training,manage` | `permission:training.check_ins.manage` |
| 147 | GET `attendance` | `module:training,view` | `permission:training.attendance.view` |
| 148-149 | `bookings/{booking}/attendance`, `bookings/{booking}/check-out` | `module:training,manage` | `permission:training.attendance.manage` |
| 156 | GET `bookings` | `module:training,view` | `permission:training.bookings.view` |
| 158 | POST `bookings/{booking}/decision` | `module:training,approve` | `permission:training.bookings.approve` |
| 159 | GET `sessions/{session}/roster` | `module:training,view` | `permission:training.sessions.view` |
| 160-162, 166 | `sessions/options`, `sessions`, `sessions/{session}`, `free-coaches` | `module:training,view` | `permission:training.sessions.view` |
| 163-165 | POST/PUT `sessions`, POST `sessions/{session}/cancel` | `module:training,manage` | `permission:training.sessions.manage` |
| 169 | GET `staff` | `module:hr,view` | `permission:hr.staff.view` |
| 170, 172, 173 | POST/PUT/DELETE `staff` | `module:hr,manage` | `permission:hr.staff.manage` |
| 176 | PUT `staff/{user}/schedule` | `module:hr,manage` | `permission:hr.schedules.manage` |
| 184 | POST `leave-requests/{leave}/decision` | `module:hr,manage` | `permission:hr.leave.manage` |
| 189-190 | POST/DELETE `holidays` | `module:hr,manage` | `permission:hr.holidays.manage` |
| 198-205 | every `services` write | `module:gym_setup,manage` | `permission:gym_setup.services.manage` |
| 208 | GET `plans` | `module:members,view` | `permission:members.plans.view` |
| 209-213 | every `plans` write | `module:members,manage` | `permission:members.plans.manage` |
| 218 | GET `memberships/{membership}` | `module:members,view` | `permission:members.memberships.view` |
| 220 | POST `memberships/{membership}/decision` | `module:members,approve` | `permission:members.memberships.approve` |
| 221 | group: renew, freeze, unfreeze, cancel | `module:members,manage` | `permission:members.memberships.manage` |
| 229-230 | GET `payments`, `payments/owed-bookings` | `module:finance,view` | `permission:finance.payments.view` |
| 231-232 | POST `payments`, `payments/{payment}/correct` | `module:finance,manage` | `permission:finance.payments.manage` |
| 237 | POST `athletes/{user}/entries` | `module:training,manage` | `permission:training.athletes.manage` |

Also update the comment on line 116 to: `// Owner-made roles (R-08). Viewing needs hr.roles.view; changing needs hr.roles.manage.`

Then confirm nothing is left. Run: `rg "module:" backend/routes`
Expected: no output.

- [x] **Step 5: Run the tests**

Run: `php artisan test`
Expected: PASS, including `RouteAccessTest`.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Middleware/RequirePermission.php backend/bootstrap/app.php backend/routes/api.php backend/tests/Feature/RouteAccessTest.php
git commit -m "Check routes with catalog permissions"
```

---

### Task 4: Convert the checks inside controllers and services

**Files:**
- Modify: `backend/app/Models/Gym.php` (add one method)
- Modify: `backend/app/Models/User.php` (`canSeePay`, `canApproveLeave`; delete `canInGym`)
- Modify: `backend/app/Models/GymRole.php` (delete `levelFor` and its imports)
- Modify: the controllers and services in the table in Step 3
- Delete: `backend/app/Http/Middleware/RequireModuleAccess.php`
- Modify: `backend/bootstrap/app.php` (remove the `module` alias)
- Test: the existing suite. Its booking, leave, membership, athlete record, service, staff and session tests cover every line changed here.

**Interfaces:**
- Consumes: `User::hasPermission()`.
- Produces: `Gym::peopleWithPermission(string $permission): Collection<int, User>`, which returns owners plus employees holding the permission.

- [x] **Step 1: Add the lookup for notifications**

In `backend/app/Models/Gym.php`, add `use Illuminate\Support\Collection;` if it is missing, then add this method:

```php
    /** Owners and employees who hold one permission here, for notifications. */
    public function peopleWithPermission(string $permission): Collection
    {
        return $this->people()->get()->unique('id')
            ->filter(fn (User $person) => $person->hasPermission($this, $permission))
            ->values();
    }
```

- [x] **Step 2: Rewrite the two helpers on `User`**

In `backend/app/Models/User.php`, delete `canInGym()` and replace `canSeePay()` and `canApproveLeave()` with:

```php
    /** Pay stays hidden unless the viewer runs HR staff, or sees payroll or payments (R-23). Owners hold all three. */
    public function canSeePay(Gym|int $gym): bool
    {
        return $this->hasPermission($gym, 'hr.staff.manage', 'payroll.general.view', 'finance.payments.view');
    }

    public function canApproveLeave(Gym|int $gym): bool
    {
        return $this->hasPermission($gym, 'hr.leave.manage');
    }
```

- [x] **Step 3: Replace each inline check**

Make each replacement below. Then remove the `AccessLevel`, `Module` and `GymRole` imports from any file that no longer uses them.

| File:line | Old | New |
|---|---|---|
| `app/Support/BookingDesk.php:110` | `$by->canInGym($booking->gym_id, Module::Training, AccessLevel::Approve)` | `$by->hasPermission($booking->gym_id, 'training.bookings.approve')` |
| `app/Support/BookingDesk.php:142` | `$by->canInGym($booking->gym_id, Module::Training, AccessLevel::Manage)` | `$by->hasPermission($booking->gym_id, 'training.bookings.manage')` |
| `app/Support/BookingDesk.php:157` | `$by->canInGym($booking->gym_id, Module::Training, AccessLevel::Manage)` | `$by->hasPermission($booking->gym_id, 'training.bookings.manage')` |
| `app/Support/BookingDesk.php:211-214` | the `$owners` / `$approvers` lines and `foreach ($owners->merge($approvers)->unique('id') as $approver)` | `foreach ($booking->gym->peopleWithPermission('training.bookings.approve') as $approver)` |
| `app/Http/Controllers/MembershipApplicationController.php:139-148` | body of `deciders()` | `return $gym->peopleWithPermission('members.memberships.approve');` |
| `app/Http/Controllers/Gym/AthleteRecordController.php:35` | `$viewer->canInGym($gym, Module::Members, AccessLevel::View) \|\| $viewer->canInGym($gym, Module::Training, AccessLevel::View)` | `$viewer->hasPermission($gym, 'members.memberships.view', 'training.athletes.view')` |
| `AthleteRecordController.php:40` | `$viewer->canInGym($gym, Module::HealthRecords, AccessLevel::View)` | `$viewer->hasPermission($gym, 'health_records.records.view')` |
| `AthleteRecordController.php:127` | `$viewer->canInGym($gym, Module::HealthRecords, AccessLevel::View)` | `$viewer->hasPermission($gym, 'health_records.records.view')` |
| `app/Http/Controllers/Gym/CoachCalendarController.php:36-37` | `$request->user()->canInGym($gym, Module::Training, AccessLevel::View) \|\| $request->user()->canInGym($gym, Module::Hr, AccessLevel::View)` | `$request->user()->hasPermission($gym, 'training.sessions.view', 'hr.staff.view')` |
| `app/Http/Controllers/Gym/EmployeeProfileController.php:28` | `$viewer->canInGym($gym, Module::Hr, AccessLevel::Manage)` | `$viewer->hasPermission($gym, 'hr.staff.manage')` |
| `app/Http/Controllers/Gym/Concerns/PresentsHr.php:104` | `$roles->contains(fn (GymRole $role) => $role->levelFor(Module::Training) !== null)` | `$roles->contains(fn (GymRole $role) => $role->permissions()->where('module', 'training')->exists())` |
| `PresentsHr.php:340-349` | body of `leaveApprovers()` | `return $gym->peopleWithPermission('hr.leave.manage');` |
| `app/Http/Controllers/Gym/MembershipController.php:34-36` | `collect([Module::Members, Module::Training, Module::Finance])->contains(fn (Module $module) => $viewer->canInGym($gym, $module, AccessLevel::View))` | `$viewer->hasPermission($gym, 'members.memberships.view', 'training.sessions.view', 'finance.payments.view')` |
| `MembershipController.php:289` | `$request->user()->canInGym($gym, Module::HealthRecords, AccessLevel::View)` | `$request->user()->hasPermission($gym, 'health_records.records.view')` |
| `app/Http/Controllers/Gym/LeaveController.php:39` and `:205` | `$viewer->canInGym($gym, Module::Hr, AccessLevel::View) \|\| $viewer->canApproveLeave($gym)` | `$viewer->hasPermission($gym, 'hr.leave.view', 'hr.leave.manage')` |
| `LeaveController.php:121` | `$viewer->canInGym($gym, Module::Hr, AccessLevel::View)` | `$viewer->hasPermission($gym, 'hr.leave.view')` |
| `LeaveController.php:187` | `foreach ($gym->people()->get()->unique('id')->filter(fn ($person) => $person->canInGym($gym, Module::Training, AccessLevel::Manage)) as $manager)` | `foreach ($gym->peopleWithPermission('training.sessions.manage') as $manager)` |
| `app/Http/Controllers/Gym/ScheduleController.php:28` | `$request->user()->canInGym($gym, Module::Hr, AccessLevel::View)` | `$request->user()->hasPermission($gym, 'hr.schedules.view')` |
| `app/Http/Controllers/Gym/ServiceController.php:32-33` | `$request->user()->canInGym($gym, Module::GymSetup, AccessLevel::View) \|\| $request->user()->canInGym($gym, Module::Members, AccessLevel::View)` | `$request->user()->hasPermission($gym, 'gym_setup.services.view', 'members.plans.view')` |
| `app/Http/Controllers/Gym/SessionNoteController.php:22` | `$request->user()->canInGym($gym, Module::Training, AccessLevel::Manage)` | `$request->user()->hasPermission($gym, 'training.sessions.manage')` |
| `app/Http/Controllers/Gym/StaffController.php:192` | `$viewer->canInGym($gym, Module::Hr, AccessLevel::View)` | `$viewer->hasPermission($gym, 'hr.staff.view')` |

Line numbers are from the current files. If an earlier edit moves a line, find it by the "Old" text.

- [x] **Step 4: Delete the old checks**

1. Delete `backend/app/Http/Middleware/RequireModuleAccess.php`.
2. Remove the `'module' => \App\Http\Middleware\RequireModuleAccess::class,` line from `backend/bootstrap/app.php`.
3. Delete `levelFor()` from `backend/app/Models/GymRole.php`, together with its `AccessLevel` and `Module` imports.

Then confirm. Run: `rg "canInGym|levelFor|RequireModuleAccess" backend/app backend/routes backend/bootstrap backend/tests`
Expected: no output.

- [x] **Step 5: Run the tests**

Run: `php artisan test`
Expected: PASS, with the same count as after Task 3.

- [ ] **Step 6: Commit**

```bash
git add backend/app backend/bootstrap/app.php
git commit -m "Use catalog permissions inside controllers and services"
```

---

### Task 5: Role on/off switch, system roles and end dates

**Files:**
- Create: `backend/app/Support/SystemRoles.php`
- Modify: `backend/app/Http/Controllers/Gym/GymRoleController.php` (add `toggleStatus`, the system-role delete guard, and expiry on `assign`)
- Modify: `backend/routes/api.php` (add the toggle route inside the `permission:hr.roles.manage` group)
- Modify: `backend/app/Http/Controllers/Admin/GymReviewController.php:104-107`
- Modify: `backend/routes/console.php` (add `app:seed-system-roles`)
- Modify: `backend/database/seeders/DatabaseSeeder.php` (seed system roles after the custom roles)
- Test: `backend/tests/Feature/RoleLifecycleTest.php`, `backend/tests/Feature/GymReviewTest.php`

**Interfaces:**
- Consumes: `GymRole::active()`, the pivot `expires_at`, `RoleGuard`, `PermissionCatalog::expandLevels`.
- Produces:
  - `SystemRoles::seed(Gym $gym): void`
  - `POST /api/gyms/{gym}/roles/{role}/toggle-status`, which returns `{ role }`
  - `PUT /api/gyms/{gym}/staff/{user}/roles` also accepts `expires_at: { [roleId]: 'YYYY-MM-DD' }`

- [x] **Step 1: Write the failing tests**

`backend/tests/Feature/RoleLifecycleTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\User;
use App\Support\CurrentGym;
use App\Support\PermissionCatalog;
use App\Support\SystemRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\GivesGymRoles;
use Tests\TestCase;

/** Roles that are turned off, system roles, and grants with an end date. */
class RoleLifecycleTest extends TestCase
{
    use GivesGymRoles, RefreshDatabase;

    private Gym $gym;

    private User $owner;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create();
        $this->owner = User::factory()->create();
        $this->employee = User::factory()->create();
        $this->gym->addPerson($this->owner, GymPersonType::Owner);
        $this->gym->addPerson($this->employee, GymPersonType::Employee);
    }

    private function url(string $path): string
    {
        return "/api/gyms/{$this->gym->id}/{$path}";
    }

    private function role(array $access, string $name = 'Role', array $attributes = []): GymRole
    {
        app(CurrentGym::class)->set($this->gym);
        $role = $this->makeRole($access, $name, $attributes);
        $this->app->forgetScopedInstances();

        return $role;
    }

    public function test_a_role_that_is_turned_off_gives_nothing(): void
    {
        $role = $this->role(['finance.payments.view'], 'Cashier');
        $role->users()->attach($this->employee->id);

        $this->actingAs($this->owner)->postJson($this->url("roles/{$role->id}/toggle-status"))
            ->assertOk()->assertJsonPath('role.status', 'inactive');
        $this->assertFalse($this->employee->hasPermission($this->gym, 'finance.payments.view'));

        $this->actingAs($this->owner)->postJson($this->url("roles/{$role->id}/toggle-status"))
            ->assertOk()->assertJsonPath('role.status', 'active');
        $this->assertTrue($this->employee->hasPermission($this->gym, 'finance.payments.view'));
    }

    public function test_turning_a_role_off_needs_its_access(): void
    {
        $hr = $this->role(['hr.roles.view', 'hr.roles.manage'], 'HR');
        $hr->users()->attach($this->employee->id);
        $finance = $this->role(['finance.payments.manage'], 'Finance');

        $this->actingAs($this->employee)->postJson($this->url("roles/{$finance->id}/toggle-status"))->assertForbidden();
    }

    public function test_a_grant_stops_at_its_end_date(): void
    {
        $role = $this->role(['finance.payments.view'], 'Cover cashier');
        $role->users()->attach($this->employee->id, ['expires_at' => now()->subMinute()]);
        $this->assertFalse($this->employee->hasPermission($this->gym, 'finance.payments.view'));

        $role->users()->updateExistingPivot($this->employee->id, ['expires_at' => now()->addDay()]);
        $this->assertTrue($this->employee->hasPermission($this->gym, 'finance.payments.view'));
    }

    public function test_owner_gives_a_role_until_a_date(): void
    {
        $role = $this->role(['finance.payments.view'], 'Cover cashier');
        $until = now()->addDays(3)->toDateString();

        $this->actingAs($this->owner)->putJson($this->url("staff/{$this->employee->id}/roles"), [
            'role_ids' => [$role->id],
            'expires_at' => [$role->id => $until],
        ])->assertOk()->assertJsonPath('roles.0.expires_at', fn ($value) => str_starts_with((string) $value, $until));

        // Pivot columns are not cast, so they come back as plain database values.
        $pivot = $role->users()->whereKey($this->employee->id)->first()->pivot;
        $this->assertEquals($this->owner->id, $pivot->granted_by);
        $this->assertStringEndsWith('23:59:59', (string) $pivot->expires_at);

        $this->actingAs($this->owner)->putJson($this->url("staff/{$this->employee->id}/roles"), [
            'role_ids' => [$role->id],
            'expires_at' => [$role->id => now()->subDay()->toDateString()],
        ])->assertJsonValidationErrors("expires_at.{$role->id}");
    }

    public function test_system_roles_can_be_edited_but_not_deleted(): void
    {
        $role = $this->role(['training' => 'view'], 'Coach');
        $role->forceFill(['is_system' => true])->save();

        $this->actingAs($this->owner)->putJson($this->url("roles/{$role->id}"), [
            'name' => 'Coach', 'permissions' => ['training.sessions.view'],
        ])->assertOk();

        $this->actingAs($this->owner)->deleteJson($this->url("roles/{$role->id}"))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Starter roles cannot be deleted. Turn the role off instead.');
    }

    public function test_system_roles_come_from_the_templates_and_skip_names_in_use(): void
    {
        $this->role(['finance' => 'view'], 'Coach');

        SystemRoles::seed($this->gym);
        SystemRoles::seed($this->gym); // running twice adds nothing

        $roles = GymRole::withoutGlobalScope('gym')->where('gym_id', $this->gym->id)->get()->keyBy('name');
        $this->assertCount(4, $roles); // the existing Coach + Front desk, Finance, HR
        $this->assertFalse($roles['Coach']->is_system);
        $this->assertTrue($roles['HR']->is_system);
        $this->assertEqualsCanonicalizing(
            PermissionCatalog::expandLevels(config('access.templates.hr')),
            $roles['HR']->permissionStrings(),
        );
    }
}
```

In `backend/tests/Feature/GymReviewTest.php`, find the approval test that asserts `$this->assertTrue($this->applicant->hasGymRole($gym, GymPersonType::Owner));` (line 168) and add right after it:

```php
        $this->assertSame(4, \App\Models\GymRole::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)->where('is_system', true)->count());
```

- [x] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter "RoleLifecycleTest|GymReviewTest"`
Expected: FAIL. `SystemRoles` is not found, and the toggle route returns 404.

- [x] **Step 3: Write `SystemRoles`**

`backend/app/Support/SystemRoles.php`:

```php
<?php

namespace App\Support;

use App\Models\Gym;
use App\Models\GymRole;
use Illuminate\Support\Str;

/** The starter roles every gym gets from the templates in config/access.php. They can be changed but not deleted. */
final class SystemRoles
{
    public static function seed(Gym $gym): void
    {
        foreach (config('access.templates') as $key => $levels) {
            $name = config("access.names.{$key}", Str::headline($key));

            $taken = GymRole::withoutGlobalScope('gym')
                ->where('gym_id', $gym->id)
                ->where('name', $name)
                ->exists();

            if ($taken) {
                continue;
            }

            // Made outside a gym request (admin approval, console), so the gym is set
            // by hand and the gym rule's create check is skipped on purpose.
            $role = GymRole::withoutEvents(fn () => GymRole::withoutGlobalScope('gym')->forceCreate([
                'gym_id' => $gym->id,
                'name' => $name,
                'description' => 'Starter role. Change it to fit your gym.',
                'status' => 'active',
                'is_system' => true,
            ]));

            $role->syncPermissionStrings(PermissionCatalog::expandLevels($levels));
        }
    }
}
```

- [x] **Step 4: Add the toggle, the delete guard and expiry to `GymRoleController`**

In `backend/app/Http/Controllers/Gym/GymRoleController.php`:

1. In `destroy()`, add this line right after `$role = GymRole::findOrFail($role);`:

```php
        abort_if($role->is_system, 422, 'Starter roles cannot be deleted. Turn the role off instead.');
```

2. Add this method after `destroy()`:

```php
    /** Turns a role off or on. A role that is off stays assigned but gives nothing. */
    public function toggleStatus(Request $request, Gym $gym, int $role): JsonResponse
    {
        $role = GymRole::findOrFail($role);
        RoleGuard::ensureWithin($request->user()->permissionsIn($gym), $role->permissionStrings());
        $role->update(['status' => $role->status === 'active' ? 'inactive' : 'active']);

        return response()->json(['role' => $this->payload($role->fresh())]);
    }
```

3. Replace the body of `assign()` with:

```php
        $data = $request->validate([
            'role_ids' => ['present', 'array'],
            'role_ids.*' => ['integer', 'distinct'],
            'expires_at' => ['nullable', 'array'],
            'expires_at.*' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        app(GymRoleAssigner::class)->replace($request->user(), $gym, $user, $data['role_ids'], $data['expires_at'] ?? []);

        return response()->json(['roles' => $this->rolesOf($gym, $user)]);
```

In `backend/routes/api.php`, inside the `Route::middleware('permission:hr.roles.manage')` group, add:

```php
                    Route::post('roles/{role}/toggle-status', [GymRoleController::class, 'toggleStatus'])->whereNumber('role');
```

- [x] **Step 5: Seed system roles when a gym goes live, from the console, and in the seeder**

In `backend/app/Http/Controllers/Admin/GymReviewController.php`, add `use App\Support\SystemRoles;`. Then change the approve block (lines 104-107) to:

```php
            if ($data['decision'] === 'approve') {
                $gym->approved_tier = $gym->tier;
                $gym->addPerson($gym->applicant, GymPersonType::Owner);
                SystemRoles::seed($gym);
            }
```

In `backend/routes/console.php`, add the following. Add `use App\Enums\GymStatus;`, `use App\Models\Gym;` and `use App\Support\SystemRoles;` to the imports if they are missing.

```php
// Gives every live gym the starter roles. Safe to run again: names already used are skipped.
Artisan::command('app:seed-system-roles', function () {
    Gym::where('status', GymStatus::Approved)->each(fn (Gym $gym) => SystemRoles::seed($gym));
    $this->info('Starter roles are in place.');
})->purpose('Create the starter roles for every approved gym');
```

In `backend/database/seeders/DatabaseSeeder.php`, add `use App\Support\SystemRoles;`, and right after the `foreach (self::ROLES ...)` loop from Task 2 add:

```php
            SystemRoles::seed($gym);
```

- [x] **Step 6: Run the tests**

Run: `php artisan test`
Expected: PASS, including the 6 new `RoleLifecycleTest` tests.

- [ ] **Step 7: Commit**

```bash
git add backend/app/Support/SystemRoles.php backend/app/Http/Controllers/Gym/GymRoleController.php backend/app/Http/Controllers/Admin/GymReviewController.php backend/routes/api.php backend/routes/console.php backend/database/seeders/DatabaseSeeder.php backend/tests/Feature/RoleLifecycleTest.php backend/tests/Feature/GymReviewTest.php
git commit -m "Add role on/off switch, starter roles and role end dates"
```

---

### Task 6: Platform admin permission catalog

**Files:**
- Create: `backend/app/Http/Controllers/Admin/AdminPermissionController.php`
- Modify: `backend/routes/api.php:106-112` (admin group)
- Modify: `backend/routes/console.php` (add `app:sync-permissions`)
- Test: `backend/tests/Feature/AdminPermissionTest.php`

**Interfaces:**
- Consumes: `Permission`, `PermissionCatalog::sync()`, `PermissionCatalog::label()`.
- Produces, all behind the `admin` middleware:
  - `GET /api/admin/permissions` returns `{ permissions: [{ id, module, resource, action, permission_string, name, description, status, is_system, roles_count }] }`
  - `POST /api/admin/permissions` takes `{ module, resource, action, name?, description? }` and returns 201 `{ permission }`
  - `PUT /api/admin/permissions/{permission}` takes `{ name, description? }` and returns `{ permission }`
  - `POST /api/admin/permissions/{permission}/toggle-status` returns `{ permission }`
  - `DELETE /api/admin/permissions/{permission}` returns `{ message }`

- [x] **Step 1: Write the failing tests**

`backend/tests/Feature/AdminPermissionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\Permission;
use App\Models\User;
use App\Support\CurrentGym;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\GivesGymRoles;
use Tests\TestCase;

/** The shared permission catalog, run by the platform administrator. */
class AdminPermissionTest extends TestCase
{
    use GivesGymRoles, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_platform_admin' => true])->save();
    }

    public function test_only_the_admin_can_open_the_catalog(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/admin/permissions')->assertForbidden();
        $this->actingAs($this->admin)->getJson('/api/admin/permissions')
            ->assertOk()->assertJsonCount(count(PermissionCatalog::all()), 'permissions');
    }

    public function test_admin_adds_renames_and_deletes_a_custom_permission(): void
    {
        $id = $this->actingAs($this->admin)->postJson('/api/admin/permissions', [
            'module' => 'inventory', 'resource' => 'stock', 'action' => 'adjust',
        ])->assertCreated()
            ->assertJsonPath('permission.permission_string', 'inventory.stock.adjust')
            ->assertJsonPath('permission.name', 'Adjust stock')
            ->assertJsonPath('permission.is_system', false)
            ->json('permission.id');

        $this->actingAs($this->admin)->postJson('/api/admin/permissions', [
            'module' => 'inventory', 'resource' => 'stock', 'action' => 'adjust',
        ])->assertJsonValidationErrors('action');

        $this->actingAs($this->admin)->putJson("/api/admin/permissions/{$id}", ['name' => 'Fix stock counts'])
            ->assertOk()->assertJsonPath('permission.name', 'Fix stock counts');

        $this->actingAs($this->admin)->deleteJson("/api/admin/permissions/{$id}")->assertOk();
        $this->assertNull(Permission::find($id));
    }

    public function test_system_permissions_and_used_permissions_cannot_be_deleted(): void
    {
        $system = Permission::where('permission_string', 'finance.payments.view')->first();
        $this->actingAs($this->admin)->deleteJson("/api/admin/permissions/{$system->id}")->assertStatus(422);

        $custom = Permission::create([
            'module' => 'inventory', 'resource' => 'stock', 'action' => 'adjust',
            'permission_string' => 'inventory.stock.adjust', 'name' => 'Adjust stock',
        ]);
        $gym = Gym::factory()->create();
        app(CurrentGym::class)->set($gym);
        $this->makeRole(['inventory.stock.adjust'], 'Store');
        $this->app->forgetScopedInstances();

        $this->actingAs($this->admin)->deleteJson("/api/admin/permissions/{$custom->id}")->assertStatus(422);
    }

    public function test_turning_a_permission_off_takes_it_from_everyone_including_owners(): void
    {
        $gym = Gym::factory()->create();
        $owner = User::factory()->create();
        $gym->addPerson($owner, GymPersonType::Owner);
        $permission = Permission::where('permission_string', 'finance.payments.view')->first();

        $this->actingAs($this->admin)->postJson("/api/admin/permissions/{$permission->id}/toggle-status")
            ->assertOk()->assertJsonPath('permission.status', 'inactive');
        $this->assertFalse($owner->hasPermission($gym, 'finance.payments.view'));
    }

    public function test_sync_restores_missing_system_permissions_and_keeps_status(): void
    {
        Permission::where('permission_string', 'finance.payments.view')->delete();
        Permission::where('permission_string', 'finance.payments.manage')->update(['status' => 'inactive']);

        $this->artisan('app:sync-permissions')->assertSuccessful();

        $this->assertTrue(Permission::where('permission_string', 'finance.payments.view')->exists());
        $this->assertSame('inactive', Permission::where('permission_string', 'finance.payments.manage')->value('status'));
    }
}
```

- [x] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter AdminPermissionTest`
Expected: FAIL. The admin routes return 404 and the command does not exist.

- [x] **Step 3: Write the controller**

`backend/app/Http/Controllers/Admin/AdminPermissionController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The shared permission catalog. Only the platform administrator changes it,
 * because every gym's roles read from it. Catalog permissions (is_system) come
 * from config/permissions.php and cannot be deleted. Added permissions have no
 * effect until code checks them.
 */
class AdminPermissionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'permissions' => Permission::withCount('roles')->orderBy('module')->orderBy('resource')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $segment = ['required', 'string', 'max:40', 'regex:/^[a-z][a-z_]*$/'];
        $data = $request->validate([
            'module' => $segment,
            'resource' => $segment,
            'action' => [...$segment, function (string $attribute, string $value, $fail) use ($request) {
                $string = "{$request->input('module')}.{$request->input('resource')}.{$value}";
                if (Permission::where('permission_string', $string)->exists()) {
                    $fail("{$string} already exists.");
                }
            }],
            'name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $permission = Permission::create([
            ...$data,
            'permission_string' => "{$data['module']}.{$data['resource']}.{$data['action']}",
            'name' => $data['name'] ?? PermissionCatalog::label($data['resource'], $data['action']),
        ]);

        return response()->json(['permission' => $permission->fresh()], 201);
    }

    public function update(Request $request, Permission $permission): JsonResponse
    {
        $permission->update($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]));

        return response()->json(['permission' => $permission]);
    }

    /** A permission that is off gives nothing to anyone, owners included. */
    public function toggleStatus(Permission $permission): JsonResponse
    {
        $permission->update(['status' => $permission->status === 'active' ? 'inactive' : 'active']);

        return response()->json(['permission' => $permission]);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        abort_if($permission->is_system, 422, 'Catalog permissions cannot be deleted. Turn them off instead.');
        abort_if($permission->roles()->exists(), 422, 'Roles still use this permission. Remove it from them first.');
        $permission->delete();

        return response()->json(['message' => 'Permission deleted.']);
    }
}
```

If the existing `admin` middleware answers non-admins with 404 instead of 403, change `assertForbidden()` in the first test to match it.

- [x] **Step 4: Add the routes and the command**

In `backend/routes/api.php`, add `use App\Http\Controllers\Admin\AdminPermissionController;` and add these lines inside the `Route::prefix('admin')->middleware('admin')` group:

```php
                Route::get('permissions', [AdminPermissionController::class, 'index']);
                Route::post('permissions', [AdminPermissionController::class, 'store']);
                Route::put('permissions/{permission}', [AdminPermissionController::class, 'update']);
                Route::post('permissions/{permission}/toggle-status', [AdminPermissionController::class, 'toggleStatus']);
                Route::delete('permissions/{permission}', [AdminPermissionController::class, 'destroy']);
```

In `backend/routes/console.php`, add the following, with `use App\Support\PermissionCatalog;` if it is missing:

```php
// Adds catalog permissions that are missing from the table. Run after every deploy that changes config/permissions.php.
Artisan::command('app:sync-permissions', function () {
    PermissionCatalog::sync();
    $this->info('Permission catalog is up to date.');
})->purpose('Write config/permissions.php to the permissions table');
```

- [x] **Step 5: Run the tests**

Run: `php artisan test`
Expected: PASS, including the 5 new `AdminPermissionTest` tests.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Http/Controllers/Admin/AdminPermissionController.php backend/routes/api.php backend/routes/console.php backend/tests/Feature/AdminPermissionTest.php
git commit -m "Let the platform admin manage the permission catalog"
```

---

### Task 7: Send permission strings to the frontend and check them there

**Files:**
- Modify: `backend/app/Http/Resources/UserResource.php:35-37`
- Modify: `backend/app/Models/User.php` (delete the `accessLevel` shim and its unused imports)
- Modify: `backend/tests/Feature/AccessTest.php` (add one test)
- Modify: `frontend/src/lib/auth.tsx:9`
- Modify: `frontend/src/lib/gym-access.ts:1-31`
- Modify: `frontend/src/components/gym-nav.tsx`
- Modify: every page in the Step 6 table

**Interfaces:**
- Consumes: `User::permissionsIn()`.
- Produces:
  - `/api/me` returns `data.gyms[].permissions` as `string[]`
  - Frontend `can(gym: GymAccess, ...anyOf: Permission[]): boolean`
  - Frontend `type Permission = \`${string}.${string}.${string}\``

- [x] **Step 1: Write the failing backend test**

In `backend/tests/Feature/AccessTest.php`, add:

```php
    public function test_me_lists_permission_strings_per_gym(): void
    {
        $this->give($this->employee, $this->role(['finance' => 'view'], name: 'Cashier'));

        $this->actingAs($this->employee)->getJson('/api/me')->assertOk()
            ->assertJsonPath('data.gyms.0.permissions', ['finance.payments.view']);

        $this->assertCount(
            count(PermissionCatalog::all()),
            $this->actingAs($this->owner)->getJson('/api/me')->json('data.gyms.0.permissions'),
        );
    }
```

- [x] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter test_me_lists_permission_strings_per_gym`
Expected: FAIL. `permissions` is still a `{ finance: "view" }` map.

- [x] **Step 3: Change `UserResource`**

In `backend/app/Http/Resources/UserResource.php`, replace:

```php
                    'permissions' => collect(Module::cases())
                        ->mapWithKeys(fn (Module $m) => [$m->value => $this->resource->accessLevel($gym, $m)?->value])
                        ->filter(),
```

with:

```php
                    'permissions' => $this->resource->permissionsIn($gym)->values(),
```

Remove `use App\Enums\Module;` from that file. Then delete `accessLevel()` from `backend/app/Models/User.php`, along with the `AccessLevel`, `Module` and `Str` imports if nothing else there uses them.

Then confirm. Run: `rg "accessLevel\(" backend/app backend/tests`
Expected: no output.

- [x] **Step 4: Run the backend suite**

Run: `php artisan test`
Expected: PASS.

- [x] **Step 5: Change the frontend access helpers**

In `frontend/src/lib/auth.tsx` line 9, change `permissions: Record<string, string>` to `permissions: string[]`.

In `frontend/src/lib/gym-access.ts`, replace lines 1-31 (from the import through the end of `canManage`) with:

```ts
import type { GymAccess, User } from '@/lib/auth'

/** A permission the server checks, written module.resource.action, for example "training.sessions.manage". */
export type Permission = `${string}.${string}.${string}`

/** The three standard actions. Labels only; the server treats every permission on its own. */
export type AccessLevel = 'view' | 'manage' | 'approve'

/** True when this gym grants any one of the given permissions. Owners arrive with every active permission. */
export function can(gym: GymAccess, ...anyOf: Permission[]): boolean {
  return anyOf.some((permission) => gym.permissions.includes(permission))
}

/** True when this gym grants anything beyond viewing, so the workspace is worth showing. */
export function canManage(gym: GymAccess): boolean {
  return gym.roles.includes('owner') || gym.permissions.some((permission) => !permission.endsWith('.view'))
}
```

Leave `homePath` and `approvedGym` unchanged.

In `frontend/src/components/gym-nav.tsx`, change the import on line 3 to `import { can, type Permission } from '@/lib/gym-access'`, and replace `ITEMS` and the filter in `workspaceNav` with:

```ts
const ITEMS: { segment: string; label: string; icon: LucideIcon; permission: Permission }[] = [
  { segment: 'settings', label: 'Setup', icon: Settings2, permission: 'gym_setup.settings.view' },
  { segment: 'rooms', label: 'Rooms', icon: DoorOpen, permission: 'gym_setup.rooms.view' },
  { segment: 'staff', label: 'Staff', icon: Users, permission: 'hr.staff.view' },
  { segment: 'leave', label: 'Leave', icon: CalendarDays, permission: 'hr.leave.view' },
  { segment: 'schedule', label: 'Schedule', icon: CalendarDays, permission: 'training.sessions.view' },
  { segment: 'bookings', label: 'Bookings', icon: ClipboardList, permission: 'training.bookings.view' },
  { segment: 'check-in', label: 'Check-in', icon: UserCheck, permission: 'training.check_ins.manage' },
  { segment: 'attendance', label: 'Attendance', icon: ClipboardList, permission: 'training.attendance.view' },
  { segment: 'services', label: 'Services', icon: Dumbbell, permission: 'gym_setup.services.view' },
  { segment: 'plans', label: 'Plans', icon: CreditCard, permission: 'members.plans.view' },
  { segment: 'applications', label: 'Applications', icon: Inbox, permission: 'members.memberships.view' },
  { segment: 'members', label: 'Members', icon: UserCheck, permission: 'members.memberships.view' },
  { segment: 'payments', label: 'Payments', icon: Receipt, permission: 'finance.payments.view' },
]

/** Workspace links this person may open, in the order the sidebar and sub-navigation show them. */
export function workspaceNav(gym: GymAccess): GymNavItem[] {
  return ITEMS.filter((item) => can(gym, item.permission)).map(({ segment, label, icon }) => ({
    to: `/gyms/${gym.id}/manage/${segment}`,
    label,
    icon,
  }))
}
```

- [x] **Step 6: Replace every `can(gym, module, level)` call**

| File:line | Old | New |
|---|---|---|
| `src/pages/gym/GymApplications.tsx:37` | `can(gym, 'members', 'approve')` | `can(gym, 'members.memberships.approve')` |
| `src/pages/gym/EmployeeRecord.tsx:55` | `can(gym, 'hr', 'view')` | `can(gym, 'hr.staff.view')` |
| `src/pages/gym/EmployeeRecord.tsx:56` | `can(gym, 'hr', 'manage')` | `can(gym, 'hr.staff.manage')` |
| `src/pages/gym/GymBookings.tsx:15` | `can(gym, 'training', 'view')` | `can(gym, 'training.bookings.view')` |
| `src/pages/gym/GymBookings.tsx:44` | `can(gym, 'training', 'approve')` | `can(gym, 'training.bookings.approve')` |
| `src/pages/gym/GymBookings.tsx:45` | `can(gym, 'training', 'manage')` | `can(gym, 'training.bookings.manage')` |
| `src/pages/gym/GymAttendance.tsx:23` | `can(gym, 'training', 'view')` | `can(gym, 'training.attendance.view')` |
| `src/pages/gym/GymLeave.tsx:142` | `can(gym, 'hr', 'manage')` | `can(gym, 'hr.leave.manage')` |
| `src/pages/gym/GymCheckIn.tsx:19` | `can(gym, 'training', 'manage')` | `can(gym, 'training.check_ins.manage')` |
| `src/pages/gym/GymPayments.tsx:23` | `can(gym, 'finance', 'manage')` | `can(gym, 'finance.payments.manage')` |
| `src/pages/gym/GymPlans.tsx:53` | `can(gym, 'members', 'view')` | `can(gym, 'members.plans.view')` |
| `src/pages/gym/GymPlans.tsx:54` | `can(gym, 'members', 'manage')` | `can(gym, 'members.plans.manage')` |
| `src/pages/gym/GymRooms.tsx:69` | `can(gym, 'gym_setup', 'manage')` | `can(gym, 'gym_setup.rooms.manage')` |
| `src/pages/gym/GymSchedule.tsx:39` | `can(gym, 'training', 'view')` | `can(gym, 'training.sessions.view')` |
| `src/pages/gym/GymSchedule.tsx:40` | `can(gym, 'training', 'manage')` | `can(gym, 'training.sessions.manage')` |
| `src/pages/gym/GymServices.tsx:46` | `can(gym, 'gym_setup', 'view')` | `can(gym, 'gym_setup.services.view')` |
| `src/pages/gym/GymServices.tsx:47` | `can(gym, 'gym_setup', 'manage')` | `can(gym, 'gym_setup.services.manage')` |
| `src/pages/gym/GymSession.tsx:23` | `can(gym, 'training', 'view')` | `can(gym, 'training.sessions.view')` |
| `src/pages/gym/GymSession.tsx:55, 62` | `can(gym, 'training', 'manage')` | `can(gym, 'training.sessions.manage')` |
| `src/pages/gym/GymSession.tsx:69` | `can(gym, 'training', 'approve')` | `can(gym, 'training.bookings.approve')` |
| `src/pages/gym/GymSession.tsx:70` | `can(gym, 'training', 'manage')` | `can(gym, 'training.bookings.manage')` |
| `src/pages/gym/GymSession.tsx:72` | `can(gym, 'finance', 'manage')` | `can(gym, 'finance.payments.manage')` |
| `src/pages/gym/GymSession.tsx:73` | `canManage={can(gym, 'training', 'manage')}` | `canManage={can(gym, 'training.attendance.manage')}` |
| `src/pages/gym/GymSession.tsx:73` | `onNote={can(gym, 'training', 'manage') \|\| ...` | `onNote={can(gym, 'training.sessions.manage') \|\| ...` |
| `src/pages/gym/GymSettings.tsx:122` | `can(gym, 'gym_setup', 'manage')` | `can(gym, 'gym_setup.settings.manage')` |
| `src/pages/gym/GymStaff.tsx:54` | `can(gym, 'hr', 'view')` | `can(gym, 'hr.staff.view')` |
| `src/pages/gym/GymStaff.tsx:55` | `can(gym, 'hr', 'manage')` | `can(gym, 'hr.staff.manage')` |
| `src/pages/gym/MemberRecord.tsx:34` | `can(gym, 'members', 'manage')` | `can(gym, 'members.memberships.manage')` |
| `src/pages/gym/MemberRecord.tsx:35` | `can(gym, 'finance', 'manage')` | `can(gym, 'finance.payments.manage')` |
| `src/pages/gym/MemberRecord.tsx:36` | `can(gym, 'training', 'manage')` | `can(gym, 'training.athletes.manage')` |

Then confirm. Run: `rg "can\(gym, '[a-z_]+', '" frontend/src`
Expected: no output.

- [x] **Step 7: Build and lint**

Run: `pnpm build` and then `pnpm lint`
Expected: both finish with no errors. The roles tab code in `GymStaff.tsx` that reads `role.permissions[module]` may raise type errors here. If it does, Task 8 replaces that code, so do Task 8 Step 3 before re-running.

- [ ] **Step 8: Commit**

```bash
git add backend/app/Http/Resources/UserResource.php backend/app/Models/User.php backend/tests/Feature/AccessTest.php frontend/src/lib/auth.tsx frontend/src/lib/gym-access.ts frontend/src/components/gym-nav.tsx frontend/src/pages
git commit -m "Check catalog permissions in the frontend"
```

---

### Task 8: Role editor, roles tab and role end dates (frontend)

**Files:**
- Modify: `frontend/src/lib/workspace.ts:273-314`
- Modify: `frontend/src/components/gym/role-dialog.tsx` (whole file)
- Modify: `frontend/src/pages/gym/GymStaff.tsx` (data load, roles tab, role actions)
- Modify: `frontend/src/components/gym/staff-dialogs.tsx` (`AssignRolesDialog`)

**Interfaces:**
- Consumes: `GET /api/permissions/catalog`, the role JSON from Task 2, `POST roles/{id}/toggle-status`, and `expires_at` on assign (Task 5).
- Produces: the types `CatalogPermission`, `PermissionCatalog` and `GymRole` (new shape), plus `resourceLabel()` and `actionLabel()`.

- [x] **Step 1: Replace the role types**

In `frontend/src/lib/workspace.ts`, replace the `GymRole` and `AccessTemplates` types (lines 273-285) with:

```ts
/** One permission from the shared catalog. */
export type CatalogPermission = {
  id: number
  module: string
  resource: string
  action: string
  permission_string: string
  name: string
  description: string | null
}

/** What a role can hold, plus starter templates. From GET /permissions/catalog. */
export type PermissionCatalog = {
  permissions: CatalogPermission[]
  templates: Record<string, { name: string; permissions: string[] }>
}

/** A role the owner made for this gym (R-08). */
export type GymRole = {
  id: number
  name: string
  description: string | null
  status: 'active' | 'inactive'
  is_system: boolean
  permissions: string[]
  users_count?: number
  expires_at?: string | null
}
```

After `moduleLabel` (line 308), add:

```ts
/** "check_ins" -> "Check ins". */
export const resourceLabel = (resource: string) => {
  const words = resource.replaceAll('_', ' ')
  return words.charAt(0).toUpperCase() + words.slice(1)
}

export const actionLabel = (action: string) => LEVEL_LABELS[action] ?? resourceLabel(action)
```

Remove `templateLabel` if nothing else uses it. Check with `rg templateLabel frontend/src`.

- [x] **Step 2: Rewrite the role dialog**

Replace the whole of `frontend/src/components/gym/role-dialog.tsx` with:

```tsx
import { useMemo, useState } from 'react'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogPanel,
  DialogTitle,
} from '@/components/ui/dialog'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import {
  actionLabel,
  type CatalogPermission,
  type GymRole,
  moduleLabel,
  type PermissionCatalog,
  resourceLabel,
} from '@/lib/workspace'

// Within one resource, a higher action needs the lower ones: manage needs view.
const ORDER = ['view', 'manage', 'approve']

/**
 * R-08: create or change one gym role. A template fills the starting ticks,
 * then each permission can be ticked on its own.
 */
export function RoleDialog({
  gymId,
  role,
  catalog,
  onClose,
  onSaved,
}: {
  gymId: number
  role: GymRole | null
  catalog: PermissionCatalog
  onClose: () => void
  onSaved: () => void
}) {
  const [name, setName] = useState(role?.name ?? '')
  const [description, setDescription] = useState(role?.description ?? '')
  const [selected, setSelected] = useState<Set<string>>(() => new Set(role?.permissions ?? []))
  const [template, setTemplate] = useState('none')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  // module -> resource -> permissions, in catalog order.
  const groups = useMemo(() => {
    const map = new Map<string, Map<string, CatalogPermission[]>>()
    for (const permission of catalog.permissions) {
      const resources = map.get(permission.module) ?? new Map<string, CatalogPermission[]>()
      resources.set(permission.resource, [...(resources.get(permission.resource) ?? []), permission])
      map.set(permission.module, resources)
    }
    return map
  }, [catalog.permissions])

  const templateItems = {
    none: 'Start from scratch',
    ...Object.fromEntries(Object.entries(catalog.templates).map(([key, value]) => [key, value.name])),
  }

  function applyTemplate(key: string) {
    setTemplate(key)
    setSelected(new Set(key === 'none' ? [] : catalog.templates[key]?.permissions ?? []))
  }

  function toggle(permission: CatalogPermission, checked: boolean) {
    setSelected((current) => {
      const next = new Set(current)
      const rank = ORDER.indexOf(permission.action)
      for (const sibling of groups.get(permission.module)?.get(permission.resource) ?? []) {
        const siblingRank = ORDER.indexOf(sibling.action)
        const isSelf = sibling.id === permission.id
        if (checked && (isSelf || (rank > 0 && siblingRank >= 0 && siblingRank < rank))) next.add(sibling.permission_string)
        if (!checked && (isSelf || (rank >= 0 && siblingRank > rank))) next.delete(sibling.permission_string)
      }
      return next
    })
  }

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    const body = { name, description: description.trim() || null, permissions: [...selected] }
    try {
      if (role) {
        await api(`/gyms/${gymId}/roles/${role.id}`, { method: 'PUT', body })
        toastManager.add({ title: 'Role saved.', type: 'success' })
      } else {
        await api(`/gyms/${gymId}/roles`, { body })
        toastManager.add({ title: 'Role created.', type: 'success' })
      }
      setErrors({})
      onSaved()
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
      if (!(error instanceof ApiError) || Object.keys(error.errors).length === 0) {
        toastManager.add({
          title: error instanceof ApiError ? error.message : 'The role could not be saved.',
          type: 'error',
        })
      }
    } finally {
      setBusy(false)
    }
  }

  const messages = [...new Set(Object.values(errors).flat())]

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>{role ? 'Edit role' : 'New role'}</DialogTitle>
          <DialogDescription>
            Tick what this role can do. Changes apply to everyone who has it. Ticking manage or approve also ticks view.
          </DialogDescription>
        </DialogHeader>
        <Form errors={errors} onSubmit={save}>
          <DialogPanel className="flex flex-col gap-5">
            {messages.length > 0 && (
              <p role="alert" className="text-destructive-foreground text-sm">
                {messages.join(' ')}
              </p>
            )}

            <div className="grid gap-4 sm:grid-cols-2">
              <Field name="name" className="w-full">
                <FieldLabel>Role name</FieldLabel>
                <Input name="name" value={name} onChange={(e) => setName(e.target.value)} placeholder="Head coach" maxLength={60} required autoFocus />
                <FieldError />
              </Field>

              <Field name="template" className="w-full">
                <FieldLabel>Start from a template</FieldLabel>
                <Select value={template} onValueChange={(value) => applyTemplate(String(value ?? 'none'))} items={templateItems}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectPopup>
                    {Object.entries(templateItems).map(([key, label]) => (
                      <SelectItem key={key} value={key}>
                        {label}
                      </SelectItem>
                    ))}
                  </SelectPopup>
                </Select>
                <FieldDescription>Picking a template replaces the ticks below.</FieldDescription>
              </Field>
            </div>

            <Field name="description" className="w-full">
              <FieldLabel>Description</FieldLabel>
              <Input name="description" value={description} onChange={(e) => setDescription(e.target.value)} placeholder="What this person does" maxLength={500} />
              <FieldError />
            </Field>

            <div className="flex flex-col gap-3">
              <p className="font-medium text-sm">
                Permissions <span className="text-muted-foreground">({selected.size} ticked)</span>
              </p>
              {[...groups].map(([module, resources]) => (
                <fieldset key={module} className="flex flex-col gap-2 rounded-lg border p-3">
                  <legend className="px-1 font-medium text-sm">{moduleLabel(module)}</legend>
                  {[...resources].map(([resource, permissions]) => (
                    <div key={resource} className="flex flex-wrap items-center gap-x-5 gap-y-2">
                      <span className="w-40 text-muted-foreground text-sm">{resourceLabel(resource)}</span>
                      {permissions.map((permission) => (
                        <label key={permission.id} className="flex items-center gap-2 text-sm" title={permission.description ?? permission.name}>
                          <Checkbox
                            checked={selected.has(permission.permission_string)}
                            onCheckedChange={(checked) => toggle(permission, checked === true)}
                          />
                          {actionLabel(permission.action)}
                        </label>
                      ))}
                    </div>
                  ))}
                </fieldset>
              ))}
            </div>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy}>
              {role ? 'Save role' : 'Create role'}
            </Button>
          </DialogFooter>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
```

- [x] **Step 3: Update the Staff page**

In `frontend/src/pages/gym/GymStaff.tsx`:

1. In the `@/lib/workspace` import, replace `type AccessTemplates` with `type PermissionCatalog` and remove `LEVEL_LABELS`. Keep `moduleLabel`.
2. Under the `canView`/`canManage` lines (54-55), add:

```tsx
  const canViewRoles = gym ? can(gym, 'hr.roles.view') : false
  const canManageRoles = gym ? can(gym, 'hr.roles.manage') : false
```

3. Replace `const [templates, setTemplates] = useState<AccessTemplates | null>(null)` with `const [catalog, setCatalog] = useState<PermissionCatalog | null>(null)`.
4. Replace the `Promise.all([...])` block (lines 75-86) with:

```tsx
    Promise.all([
      api<StaffList>(`/gyms/${gymId}/staff`),
      canViewRoles ? api<{ roles: GymRole[] }>(`/gyms/${gymId}/roles`) : Promise.resolve({ roles: [] as GymRole[] }),
      api<PermissionCatalog>('/permissions/catalog'),
    ])
      .then(([staffResult, rolesResult, catalogResult]) => {
        if (!live) return
        setStaff(staffResult.staff)
        setOwner(staffResult.owner)
        setRoles(rolesResult.roles)
        setCatalog(catalogResult)
      })
```

Add `canViewRoles` to the effect's dependency list.

5. Change `!templates` to `!catalog` on line 147. Show the `Roles` tab trigger (line 160) and its panel only when `canViewRoles` is true.
6. Use `canManageRoles` instead of `canManage` for the "Edit roles" button on each employee row (line 268), for "New role" and "Create the first role", and for the role card actions.
7. Add a toggle function next to `deleteRole()`:

```tsx
  async function toggleRole(role: GymRole) {
    if (!gymId) return
    try {
      await api(`/gyms/${gymId}/roles/${role.id}/toggle-status`, { method: 'POST' })
      toastManager.add({ title: role.status === 'active' ? 'Role turned off.' : 'Role turned on.', type: 'success' })
      reload()
    } catch (error) {
      toastManager.add({ title: error instanceof ApiError ? error.message : 'The role could not be changed.', type: 'error' })
    }
  }
```

8. Replace the role card body inside `roles.map(...)` (lines 326-370) with:

```tsx
                {roles.map((role) => {
                  const counts = role.permissions.reduce<Record<string, number>>((acc, permission) => {
                    const module = permission.split('.')[0]
                    acc[module] = (acc[module] ?? 0) + 1
                    return acc
                  }, {})
                  return (
                    <Card key={role.id} className={role.status === 'inactive' ? 'opacity-70' : undefined}>
                      <CardHeader>
                        <CardTitle render={<h2 />}>{role.name}</CardTitle>
                        <CardDescription>
                          {role.users_count ?? 0} {(role.users_count ?? 0) === 1 ? 'person' : 'people'}
                          {role.description ? ` · ${role.description}` : ''}
                        </CardDescription>
                        {canManageRoles && (
                          <CardAction className="flex gap-1">
                            <Button size="sm" variant="ghost" onClick={() => toggleRole(role)}>
                              {role.status === 'active' ? 'Turn off' : 'Turn on'}
                            </Button>
                            <Button size="icon-sm" variant="ghost" aria-label={`Edit ${role.name}`} onClick={() => setRoleDialog({ role })}>
                              <Pencil aria-hidden />
                            </Button>
                            {!role.is_system && (
                              <Button size="icon-sm" variant="ghost" aria-label={`Delete ${role.name}`} onClick={() => setRoleDelete(role)}>
                                <Trash2 aria-hidden />
                              </Button>
                            )}
                          </CardAction>
                        )}
                      </CardHeader>
                      <CardPanel className="flex flex-wrap content-start gap-1.5">
                        {role.status === 'inactive' && <Badge variant="outline">Off: gives no access</Badge>}
                        {role.is_system && <Badge variant="outline">Starter role</Badge>}
                        {Object.keys(counts).length === 0 ? (
                          <span className="text-muted-foreground text-sm">No permissions</span>
                        ) : (
                          Object.entries(counts).map(([module, count]) => (
                            <Badge key={module} variant="outline">
                              {moduleLabel(module)}: {count}
                            </Badge>
                          ))
                        )}
                      </CardPanel>
                    </Card>
                  )
                })}
```

9. Change the copy on line 301 to `Roles decide what a person can see and do. A role that is off stays assigned but gives nothing.` Change line 314 to `Start from a template or tick permissions one by one, then give the role to an employee.`
10. Pass `catalog={catalog}` instead of `templates={templates}` to `<RoleDialog>` (line 416).

- [x] **Step 4: Add end dates to the assign dialog**

In `frontend/src/components/gym/staff-dialogs.tsx`, `AssignRolesDialog`:

1. Add the state `const [expiry, setExpiry] = useState<Record<string, string>>({})` next to `selected`.
2. In the load effect's `.then`, after `setSelected(...)`, add:

```tsx
        if (live) setExpiry(Object.fromEntries(result.roles.filter((role) => role.expires_at).map((role) => [String(role.id), String(role.expires_at).slice(0, 10)])))
```

3. Change the PUT body to:

```tsx
        body: {
          role_ids: (selected ?? []).map(Number),
          expires_at: Object.fromEntries((selected ?? []).filter((id) => expiry[id]).map((id) => [id, expiry[id]])),
        },
```

4. Replace the checkbox list (lines 271-278) with:

```tsx
                    <CheckboxGroup value={selected} onValueChange={setSelected} className="flex w-full flex-col gap-3">
                      {roles.map((role) => {
                        const id = String(role.id)
                        return (
                          <div key={role.id} className="flex flex-wrap items-center justify-between gap-2">
                            <label className="flex items-center gap-2 text-sm">
                              <Checkbox value={id} />
                              {role.name}
                              {role.status === 'inactive' && <span className="text-muted-foreground">(off)</span>}
                            </label>
                            {selected.includes(id) && (
                              <Input
                                type="date"
                                className="w-44"
                                aria-label={`Last day ${role.name} counts`}
                                min={new Date().toISOString().slice(0, 10)}
                                value={expiry[id] ?? ''}
                                onChange={(e) => setExpiry((current) => ({ ...current, [id]: e.target.value }))}
                              />
                            )}
                          </div>
                        )
                      })}
                    </CheckboxGroup>
```

5. Under the list, add `<FieldDescription>Set a last day for cover work. Leave the date empty to keep the role.</FieldDescription>`. Import `Input` from `@/components/ui/input` if the file does not already.

- [x] **Step 5: Build, lint and check by hand**

Run: `pnpm build` and then `pnpm lint`
Expected: both finish with no errors.

Then start the backend (`php artisan serve`) and the frontend (`pnpm dev`), sign in as a seeded owner and check:
1. Staff → Roles shows the starter roles marked "Starter role", with no delete button.
2. Create a role from the Coach template. The ticks match the template, and ticking "Approve" on Bookings also ticks View and Manage.
3. Give the role to an employee with a last day three days away. Reopening the dialog shows the date.
4. Turn the role off. Signed in as that employee, the Schedule and Bookings links are gone after reload.

- [ ] **Step 6: Commit**

```bash
git add frontend/src/lib/workspace.ts frontend/src/components/gym/role-dialog.tsx frontend/src/components/gym/staff-dialogs.tsx frontend/src/pages/gym/GymStaff.tsx
git commit -m "Role editor with permission ticks, on/off switch and end dates"
```

---

### Task 9: Admin permission catalog page (frontend)

**Files:**
- Create: `frontend/src/pages/admin/AdminPermissions.tsx`
- Modify: `frontend/src/App.tsx:156-157`
- Modify: `frontend/src/pages/admin/AdminHome.tsx` (add a link)

**Interfaces:**
- Consumes: the `/api/admin/permissions` endpoints from Task 6.

- [x] **Step 1: Write the page**

`frontend/src/pages/admin/AdminPermissions.tsx`:

```tsx
import { Plus } from 'lucide-react'
import { useEffect, useState } from 'react'
import { PageHeader } from '@/components/page-header'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogPanel,
  DialogTitle,
} from '@/components/ui/dialog'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { moduleLabel, type CatalogPermission } from '@/lib/workspace'

type AdminPermission = CatalogPermission & { status: 'active' | 'inactive'; is_system: boolean; roles_count: number }

/** The shared permission catalog. Changes here reach every gym. */
export default function AdminPermissions() {
  const [permissions, setPermissions] = useState<AdminPermission[] | null>(null)
  const [loadError, setLoadError] = useState('')
  const [version, setVersion] = useState(0)
  const [editing, setEditing] = useState<AdminPermission | 'new' | null>(null)

  useEffect(() => {
    let live = true
    setLoadError('')
    api<{ permissions: AdminPermission[] }>('/admin/permissions')
      .then((result) => live && setPermissions(result.permissions))
      .catch((error) => live && setLoadError(error instanceof ApiError ? error.message : 'The catalog could not be loaded.'))
    return () => {
      live = false
    }
  }, [version])

  const reload = () => setVersion((value) => value + 1)

  async function run(path: string, method: 'POST' | 'DELETE', done: string) {
    try {
      await api(path, { method })
      toastManager.add({ title: done, type: 'success' })
      reload()
    } catch (error) {
      toastManager.add({ title: error instanceof ApiError ? error.message : 'That did not work.', type: 'error' })
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Permission catalog"
        description="Every gym builds its roles from this list. Turning a permission off takes it from everyone, owners included."
        actions={
          <Button onClick={() => setEditing('new')}>
            <Plus aria-hidden /> New permission
          </Button>
        }
      />

      <Alert>
        <AlertTitle>New permissions need code</AlertTitle>
        <AlertDescription>
          A permission you add here can be ticked on roles, but it guards nothing until a developer adds a check for it.
        </AlertDescription>
      </Alert>

      {loadError ? (
        <Alert variant="error">
          <AlertTitle>The catalog could not be loaded</AlertTitle>
          <AlertDescription>
            <p>{loadError}</p>
            <Button size="sm" variant="outline" onClick={reload}>
              Try again
            </Button>
          </AlertDescription>
        </Alert>
      ) : permissions === null ? (
        <div className="flex flex-col gap-2" aria-busy="true">
          <Skeleton className="h-8 w-full" />
          <Skeleton className="h-8 w-full" />
          <Skeleton className="h-8 w-full" />
        </div>
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Module</TableHead>
              <TableHead>Permission</TableHead>
              <TableHead>Key</TableHead>
              <TableHead>Roles</TableHead>
              <TableHead className="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {permissions.map((permission) => (
              <TableRow key={permission.id} className={permission.status === 'inactive' ? 'opacity-60' : undefined}>
                <TableCell>{moduleLabel(permission.module)}</TableCell>
                <TableCell>
                  <div className="flex flex-wrap items-center gap-1.5">
                    {permission.name}
                    {!permission.is_system && <Badge variant="outline">Added</Badge>}
                    {permission.status === 'inactive' && <Badge variant="outline">Off</Badge>}
                  </div>
                </TableCell>
                <TableCell className="font-mono text-xs">{permission.permission_string}</TableCell>
                <TableCell>{permission.roles_count}</TableCell>
                <TableCell className="text-right">
                  <div className="flex justify-end gap-1">
                    <Button size="sm" variant="ghost" onClick={() => setEditing(permission)}>
                      Rename
                    </Button>
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() => run(`/admin/permissions/${permission.id}/toggle-status`, 'POST', permission.status === 'active' ? 'Permission turned off.' : 'Permission turned on.')}
                    >
                      {permission.status === 'active' ? 'Turn off' : 'Turn on'}
                    </Button>
                    {!permission.is_system && (
                      <Button size="sm" variant="ghost" onClick={() => run(`/admin/permissions/${permission.id}`, 'DELETE', 'Permission deleted.')}>
                        Delete
                      </Button>
                    )}
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}

      {editing && (
        <PermissionDialog
          permission={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null)
            reload()
          }}
        />
      )}
    </div>
  )
}

function PermissionDialog({
  permission,
  onClose,
  onSaved,
}: {
  permission: AdminPermission | null
  onClose: () => void
  onSaved: () => void
}) {
  const [values, setValues] = useState({
    module: permission?.module ?? '',
    resource: permission?.resource ?? '',
    action: permission?.action ?? '',
    name: permission?.name ?? '',
    description: permission?.description ?? '',
  })
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)
  const set = (key: keyof typeof values) => (e: React.ChangeEvent<HTMLInputElement>) => setValues((v) => ({ ...v, [key]: e.target.value }))

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    try {
      if (permission) {
        await api(`/admin/permissions/${permission.id}`, { method: 'PUT', body: { name: values.name, description: values.description || null } })
      } else {
        await api('/admin/permissions', { body: { ...values, name: values.name || null, description: values.description || null } })
      }
      toastManager.add({ title: 'Permission saved.', type: 'success' })
      onSaved()
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
      if (!(error instanceof ApiError) || Object.keys(error.errors).length === 0) {
        toastManager.add({ title: error instanceof ApiError ? error.message : 'The permission could not be saved.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{permission ? 'Rename permission' : 'New permission'}</DialogTitle>
          <DialogDescription>
            {permission ? permission.permission_string : 'Use lowercase words joined by underscores, for example stock and adjust.'}
          </DialogDescription>
        </DialogHeader>
        <Form errors={errors} onSubmit={save}>
          <DialogPanel className="flex flex-col gap-4">
            {!permission && (
              <div className="grid gap-4 sm:grid-cols-3">
                {(['module', 'resource', 'action'] as const).map((key) => (
                  <Field key={key} name={key} className="w-full">
                    <FieldLabel>{key.charAt(0).toUpperCase() + key.slice(1)}</FieldLabel>
                    <Input name={key} value={values[key]} onChange={set(key)} required maxLength={40} />
                    <FieldError />
                  </Field>
                ))}
              </div>
            )}
            <Field name="name" className="w-full">
              <FieldLabel>Name</FieldLabel>
              <Input name="name" value={values.name} onChange={set('name')} required={Boolean(permission)} maxLength={100} />
              <FieldError />
            </Field>
            <Field name="description" className="w-full">
              <FieldLabel>Description</FieldLabel>
              <Input name="description" value={values.description} onChange={set('description')} maxLength={500} />
              <FieldError />
            </Field>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy}>
              Save
            </Button>
          </DialogFooter>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
```

If `PageHeader` has no `actions` prop, check `src/components/page-header.tsx`. `GymSession.tsx:55` passes `actions`, so the prop is expected to exist.

- [x] **Step 2: Add the route and the link**

In `frontend/src/App.tsx`, add `import AdminPermissions from '@/pages/admin/AdminPermissions'` and, under the `/admin` route (line 156):

```tsx
            <Route path="/admin/permissions" element={<AdminPermissions />} />
```

In `frontend/src/pages/admin/AdminHome.tsx`, add a link to `/admin/permissions` labelled `Permission catalog` in the page header. Use the same link pattern the file already uses for application links.

- [x] **Step 3: Build, lint and check by hand**

Run: `pnpm build` and then `pnpm lint`
Expected: both finish with no errors.

Signed in as the platform admin, check:
1. `/admin/permissions` lists 51 rows.
2. Adding `inventory` / `stock` / `adjust` shows it marked "Added", and it appears in a gym owner's role editor.
3. Turning `finance.payments.view` off hides the Payments link for the owner after reload. Turn it back on afterwards.

- [ ] **Step 4: Commit**

```bash
git add frontend/src/pages/admin/AdminPermissions.tsx frontend/src/App.tsx frontend/src/pages/admin/AdminHome.tsx
git commit -m "Admin page for the permission catalog"
```

---

### Task 10: Docs and final check

**Files:**
- Modify: the analysis notes that describe R-08. Find them with `rg -l "R-08" docs`.

- [x] **Step 1: Update the docs**

In each note `rg -l "R-08" docs` lists, other than this plan, replace the "one access level per module" description with:

> Roles hold named permissions from a shared catalog (`config/permissions.php`, written `module.resource.action`). Owners build roles per gym and can turn them off or give them until a date. Owners hold every active permission. Nobody can hand out access they do not hold or change their own roles. The platform admin runs the catalog at `/admin/permissions`.

- [x] **Step 2: Run every check**

Backend: `php artisan test`
Expected: PASS. That is the 234 original tests plus the new ones (about 254).

Frontend: `pnpm build` and `pnpm lint`
Expected: no errors.

Leftovers: `rg "canInGym|levelFor|accessLevel\(|module:|access/templates|AccessTemplates" backend/app backend/routes frontend/src`
Expected: no output.

- [x] **Step 3: Deploy notes (for whoever ships this)**

1. Run `php artisan migrate`. It creates the catalog and converts every existing role, and no access changes.
2. Run `php artisan app:seed-system-roles` to give existing gyms the starter roles.
3. After any later change to `config/permissions.php`, run `php artisan app:sync-permissions`.
4. Signed-in users get the new permission list on their next `/api/me` load. Until then, only their menus are out of date; the server checks every request.

- [ ] **Step 4: Commit**

```bash
git add docs
git commit -m "Document the permission catalog RBAC"
```
