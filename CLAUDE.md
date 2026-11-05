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

# Frontend (from frontend/)
pnpm dev                       # app at http://localhost:5173
pnpm build                     # type-check and build
pnpm lint                      # oxlint
```

Emails go out through Gmail SMTP (settings in `backend/.env`). The email worker must be running for codes to be sent.
The frontend calls the API at `VITE_API_URL` (`frontend/.env`).

## Architecture

- **Requirement IDs.** Comments like `(R-09)` point to requirements in the analysis docs. Keep them when editing.
  Build work follows phase plans in `docs/implementation-plan/` (status and checkboxes kept up to date).
- **Auth.** Sanctum bearer tokens, stored in `localStorage` by `frontend/src/lib/api.ts`. Route groups in
  `backend/routes/api.php` stack middleware in order: `auth:sanctum` → `password.changed` → `verified.email`.
  Platform admin screens use `admin` middleware; `is_platform_admin` is set only by `app:make-admin`.
- **Gym separation (multi-tenancy).** Every route under `gyms/{gym}` runs `gym` middleware (`EnsureGymAccess`),
  which checks membership and sets the request's gym in `App\Support\CurrentGym`. Models using the
  `BelongsToGym` trait are scoped to that gym by a global scope, fill `gym_id` on create, and return nothing
  when no gym is set. Cross-gym queries must call `withoutGlobalScope('gym')` on purpose.
- **Permissions.** A person's link to a gym is `owner`, `employee`, or `member` (`GymPersonType`). Owners
  create job roles (`GymRole`) that grant an `AccessLevel` (view < manage < approve) per `Module`.
  Routes check with `module:<module>,<level>`; owners always get approve. Some routes skip the middleware and
  check inside the controller (own staff record, own leave). The frontend mirrors this in
  `frontend/src/lib/gym-access.ts` (`can()`) only to hide UI; the backend is the real check.
- **Frontend.** `src/App.tsx` holds all routes and guards (guest, signed-in, first password, admin, gym member).
  `src/components/ui/` is coss UI (Base UI + Tailwind v4); reuse it before writing new components. `@/` maps to `src/`.

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