# Permission catalog operations

## Access rules

Roles hold exact `module.resource.action` keys from the shared catalog. `config/permissions.php` defines the keys code may check. Checks never infer View or Manage from Approve. Editor lower-action ticks are only a convenience; each tick can be removed independently.

Owners hold every active permission in their own gym. Employees receive permissions only from active roles with unexpired grants in that gym. Platform administrators manage the shared catalog at `/admin/permissions`; this gives them no gym-record access.

`hr.roles.view` opens the roles tab independently of `hr.staff.view`. Role assignment needs `hr.roles.manage`; assignment screens also need read permissions. Schedules use `hr.schedules.view/manage`, holidays use `hr.holidays.manage`, and leave decisions use `hr.leave.manage`.

No person may change their own role assignments or expiry dates. A person also cannot edit, delete, or toggle any role they hold, even through an expired grant. Another authorized person must make that change. Staff creation with any explicit `role_ids`, including an empty list, requires `hr.roles.manage`. Offboarding cannot bypass assignment guards.

## Disabled permissions and grants

Disabled permissions give no access, even to owners. Existing disabled keys remain on roles until explicitly removed. Existing inactive or expired grants remain assigned until explicitly removed. They still prevent hard deletion of a role.

Authorized editors can preserve or remove existing disabled keys. They cannot add disabled keys to another role, newly assign a role containing keys they lack, or reactivate it without holding every key. Active permissions on roles being handled must remain within the editor's own access. Turning a role off preserves its grants.

Assignment replacement is transactional. Omitted expiry entries preserve existing dates, including expired history. A changed end date must be today or later. Dates end at 23:59:59 in the application's Asia/Manila timezone. Clearing a date explicitly makes the grant indefinite and requires access to every permission in that role.

Starter roles can be renamed, edited, or turned off, but not deleted. Seeding does not overwrite customized roles. A stable template key prevents duplicates after a starter role is renamed. Existing names are checked case-insensitively and skipped.

## Deploy

1. Back up the database and verify that it can be restored. Keep the matching pre-deploy application release.
2. Enter maintenance mode for the backend and deploy backend and frontend together. The `/api/me` permission payload changes from an object to a string array.
3. Run `php artisan migrate`. Migration `2026_11_05_110000_move_gym_roles_to_permission_catalog` validates legacy maps before removing their JSON column and converts them using a frozen legacy mapping.
4. Run `php artisan app:sync-permissions` and `php artisan app:seed-system-roles`. Both can be rerun. Catalog sync preserves custom rows, names, descriptions, and disabled status.
5. Leave maintenance mode. Reload signed-in clients so `/api/me` supplies current menus. The server checks permissions on every request regardless of cached menus.

Creating a custom catalog entry alone adds no protection. A developer must add its exact key to `config/permissions.php`, add the code check, deploy, and sync the catalog. Unknown code keys fail loudly rather than matching similar keys.

Never run `migrate:fresh` or the demo seeder against a database that must be preserved. Demo seeding resets application records and files; it is not a deployment step.

## Downgrade and recovery

The downgrade checks all data before changing schema. It refuses if any role or permission is disabled, any grant has an expiry date, or a role's exact keys cannot be represented by a complete legacy module level. It never converts an arbitrary resource selection into a highest-action module grant.

Do not remove grants, enable permissions, or broaden roles merely to force a rollback. For a refused downgrade, keep maintenance mode on, preserve a backup of the current state, restore the verified pre-migration database backup, and deploy its matching application release. Reconcile any post-deploy business changes separately before reopening. Test this recovery process on a disposable database first.

## Verification

Run backend `php artisan test`, frontend `pnpm build`, and frontend `pnpm lint`. Migration tests use in-memory SQLite. Seeder repeat tests fake storage and preserve the shared catalog. Configured MySQL is not reset by these checks.
