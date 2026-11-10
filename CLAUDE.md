# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Rebuild of the Combat Sports Training Center Platform. Analysis and roadmap: `docs/ANALYSIS - COMBAT SPORTS PLATFORM/`.
`backend/` is the Laravel 13 API with MySQL `combat_platform` (Laragon). `frontend/` is React + Vite + coss UI.

## Commands

```bash
# Backend (from backend/)
php artisan dev                # API at http://localhost:8000/api + email worker, together
php artisan test               # backend tests (SQLite in memory, mail not sent, queue runs inline)
php artisan test --filter=LeaveTest            # one test file or test method
php artisan migrate            # apply database changes
php artisan app:make-admin you@example.com   # the only way to make a platform admin
php artisan app:sync-permissions             # sync config/permissions.php catalog into the DB
php artisan app:seed-system-roles            # add starter roles (config/access.php) to gyms
php artisan app:check-stock-counts           # report stock items whose quantity differs from movements
php artisan app:mark-staff-absences --from=2026-11-01 --to=2026-11-06   # backfill absences (default: yesterday)

# Frontend (from frontend/)
pnpm dev                       # app at http://localhost:5173
pnpm build                     # type-check and build
pnpm lint                      # oxlint
node --test                    # node:test checks in frontend/tests/*.test.mjs (Node 24; not `node --test tests/`)
```

Scheduled jobs (membership expiry, session reminders, membership-expiry reminders, subscription reminders, follow-up reminders, no-shows, staff absences, token pruning) are defined in
`backend/routes/console.php`. Use `Schedule::call(fn () => Artisan::call('...'))`, never `Schedule::command(...)`:
Hostinger disables `proc_open`. Deploy steps: `docs/SETUPS/DEPLOY-HOSTINGER.md`.

Emails go out through Gmail SMTP (settings in `backend/.env`). The email worker must be running for codes to be sent.
Online payments use PayMongo (`App\Support\PayMongo`, keys `PAYMONGO_*` in `backend/.env`). PayMongo confirms payments
at `POST /api/webhooks/paymongo` (no sign-in; the signature is checked in `PayMongoWebhookController`).
The frontend calls the API at `VITE_API_URL` (`frontend/.env`).

## Architecture

- **Requirement IDs.** Comments like `(R-09)` point to requirements in the analysis docs. Keep them when editing.
  Build work follows phase plans in `docs/implementation-plan/<plan>/` (e.g. `roadmap-phase-6/`,
  `rbac-redesign/`; status and checkboxes kept up to date).
- **Auth.** Sanctum bearer tokens, stored in `localStorage` by `frontend/src/lib/api.ts`. Route groups in
  `backend/routes/api.php` stack middleware in order: `auth:sanctum` → `password.changed` → `verified.email`.
  Platform admin screens use `admin` middleware; `is_platform_admin` is set only by `app:make-admin`.
- **Account types.** Each user has a fixed `account_type` (`AccountType`: `member` or `owner`), chosen at
  sign-up and never changed. Routes check it with `account:owner` / `account:member` (`EnsureAccountType`):
  only owners apply for gyms; only members join gyms, set preferences or get best matches. The frontend mirrors
  this with `AccountOnly` in `App.tsx`. Staff and platform admins are not affected by account type.
- **Gym separation (multi-tenancy).** Every route under `gyms/{gym}` runs `gym` middleware (`EnsureGymAccess`),
  which checks membership and sets the request's gym in `App\Support\CurrentGym`. Models using the
  `BelongsToGym` trait are scoped to that gym by a global scope, fill `gym_id` on create, and return nothing
  when no gym is set. Cross-gym queries must call `withoutGlobalScope('gym')` on purpose.
- **Permissions.** A person's link to a gym is `owner`, `employee`, or `member` (`GymPersonType`). Owners
  create job roles (`GymRole`) that hold exact permission strings `module.resource.action` from the catalog
  in `config/permissions.php` (`PermissionCatalog`). Routes check with `permission:<string>[,<string>...]`
  (`RequirePermission`, any-of, via `User::hasPermission`); owners hold every active permission. Checking a
  string not in the catalog fails loudly, so add new ones to the catalog first. Starter roles come from
  `config/access.php` templates (`SystemRoles`), where a level like `manage` expands to all catalog actions it
  covers. Some routes skip the middleware and check inside the controller (own staff record, own leave).
  The frontend mirrors this in `frontend/src/lib/gym-access.ts` (`can()`) only to hide UI; the backend is
  the real check.
- **Gym subscriptions (R-102 to R-107).** Each gym is on a platform plan worked out on every check by
  `App\Support\Subscription`: trial (all modules, starts once on approval) → paid plan → free plan. Modules the plan
  leaves out are removed from `User::permissionsIn`, so `permission:` routes return 403 with a "not in your gym's
  plan" message (`grantedPermissionsIn` is the role grant before plan limits). Gym setup is never locked. A gym with no
  `trial_ends_at` is not limited (old rows, test fixtures). Admins manage plans and trial length
  (`SubscriptionPlanController`, `PlatformSetting`); owners pay through `gyms/{gym}/subscription/checkout`.
- **Backend layout.** Controllers stay thin. Business rules and multi-step writes live in `app/Support`
  (`*Ledger`, `Payroll/`, `Finance/`, `Procurement/`), usually inside `DB::transaction` with
  `lockForUpdate`. JSON shapes come from `Presents*` traits in `Http/Controllers/**/Concerns`, not API Resources (only exception: `UserResource`
  for the signed-in user).
  Stock quantity changes only through `StockLedger` movements. `PayrollLock::assertOpen` blocks edits to
  dates inside an in-review or released payroll run (409). Payroll math uses integer centavos
  (`Payroll\Money`); statutory rates live in the `statutory_rates` table, not config.
- **Time and SQL.** App timezone is `Asia/Manila`; frontend formats dates with `gymDate()` in
  `src/lib/membership.ts`. A session's shown status (`phase`: scheduled, ongoing, ended, cancelled) comes from
  `TrainingSession::phase()` on the server clock; it is never stored, and business rules check `status` instead.
  Tests run on SQLite but production is MySQL, so SQL that differs between them goes
  through `App\Support\Sql`.
- **Notices and live updates.** Notices use database notifications only. The shared notification provider and the
  session/booking screens poll every 15 seconds while the tab is visible; there are no websockets, SSE or push.
  Announcements reuse `announcements.general.view|manage`; platform notices are admin-only and carry no gym data.
- **Decision support.** Read-only aggregates live in `app/Support/DecisionSupport`; all thirteen GET routes use
  exactly `decision_support.general.view`, without source-module gates. Only `payroll.runs.view` unmasks individual
  payroll server-side. Backend supplies business totals/rates/forecasts; frontend formats values and chart geometry only.
  Active reports use a direct-body `.print-area.print-report` portal with static print flow. Keep print visibility
  selectors at least as specific as `body:has(.print-area) *`; DOM structure alone does not prove printable text.
- **Frontend.** `src/App.tsx` holds all routes and guards (guest, signed-in, first password, admin, gym member).
  `src/components/ui/` is coss UI (Base UI + Tailwind v4); reuse it before writing new components. `@/` maps to `src/`.
  Each area has `src/lib/<area>.ts` (response types and display helpers, e.g. `payroll.ts`, `inventory.ts`) used by `src/pages/gym/*`.
  Light/dark theme lives in `src/lib/theme.ts` (`dark` class on `<html>`, saved in `localStorage`, dark by default).

## Rules

## 1. Plan before bigger tasks

For any task bigger than a small fix:

1. Understand the goal and read the code it touches.
2. Write a short plan: what changes, which files, which skills or plugins are needed.
3. Check that the plan is not over-engineered.
4. Implement. Keep the code as simple as the plan.

## 2. Keep it simple and focused

- Do only what the user asked. The latest instruction wins.
- Prefer the smallest change that correctly solves the task.
- Reuse existing code and project patterns before writing new code.
- Do not add abstractions, layers, features, or libraries without a clear need.
- Do not change, clean up, or restructure unrelated code.
- Keep existing working behavior unless the task requires a change.

## 3. Skills and plugins

- Use a skill or plugin only when it directly helps the current task. Do not load one just because it is available.
- For general work, use the primary skills: Superpowers, Ponytail, and Caveman.
- For frontend or UI/UX work, install and use design skills such as Impeccable, Anti-Slop, UI/UX Pro Max, Taste Skills, and others that fit the task.

## 4. Git

- Never commit unless the user asks.
- Commit messages contain only the approved message. No Co-Authored-By, no Claude or AI author lines, no other author changes.

## 5. Check work before finishing

- Review the changes for mistakes.
- Run the checks that match the change.
- Say clearly what was not checked.
- Never claim something works without checking it when checking was possible.

## 6. Plain language

- Use simple, clear words. Keep explanations short. Explain any technical term that is needed.
