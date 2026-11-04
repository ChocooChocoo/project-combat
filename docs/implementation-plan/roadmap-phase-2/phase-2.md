[← Previous: Phase 1](./phase-1.md) | [Next: Phase 3 →](./phase-3.md)

**Status:** ✅ Done

# Phase 2: Administrator review, go live and platform numbers (backend)

**Goal:** The platform administrator reviews each application against a checklist, then approves, rejects with a reason, or returns it for correction. The applicant gets a notice. Approved gyms become visible with their tier badge, and the administrator sees platform-wide counts. Covers R-16 to R-19 on the backend.

## Dependencies

- Phase 1 complete (application columns on `gyms`, `gym_documents`, `GymApplicationPolicy` already letting the administrator view documents, `config/verification.php` checklist items).
- Roadmap Phase 1: `is_platform_admin` (set only by `php artisan app:make-admin`), notification inbox, `Gym::addPerson()`.

## Decisions and assumptions

- The administrator opens documents through the Phase 1 document route; no second file route.
- Notices use the `database` channel with data `{title, message}`, the shape `frontend/src/pages/Notifications.tsx` already shows.
- Platform numbers count only the `gyms` table. No members, money or staff data is read.
- No tier upgrade and no expiry (Q-16, Q-17 assumption from Phase 1).

## Tasks

- [x] `backend/app/Http/Middleware/EnsurePlatformAdmin.php` (403 unless `is_platform_admin`), alias `admin` in `backend/bootstrap/app.php`.
- [x] `backend/app/Http/Controllers/Admin/GymReviewController.php` `index`: applications filtered by status (default: submitted and under review), drafts never listed.
- [x] `GymReviewController@show`: profile plus document list; opening a `submitted` application sets `under_review`.
- [x] `GymReviewController@checklist`: save pass/fail per item from config; Tier 2 applications get the 2 extra items (selfie matches ID, photos and video match facility).
- [x] `GymReviewController@decide`: `approve` only when every checklist item passed (sets `approved`, `approved_tier = tier`, `reviewed_by`, `reviewed_at`, and calls `addPerson($applicant, GymPersonType::Owner)`); `reject` needs a reason; `return` needs at least one item to fix (sets `returned`, `return_items`). Wrap in a DB transaction.
- [x] `backend/app/Notifications/GymApplicationDecided.php` (`database` channel), sent once for each decision.
- [x] `backend/app/Http/Controllers/Admin/PlatformStatsController.php`: counts of registered gyms, approved by tier, submitted/under review, returned, rejected.
- [x] `backend/app/Http/Controllers/PublicGymController.php` `index`: approved gyms only, with name, city, disciplines and badge (`approved_tier`).
- [x] Routes in `backend/routes/api.php`: `admin/*` group with `admin` middleware (inside `verified.email`); `GET gyms` for signed-in users.
- [x] `backend/tests/Feature/GymReviewTest.php`.

## Status notes

- 2026-11-04: Built. Files: `app/Http/Middleware/EnsurePlatformAdmin.php`, `app/Http/Controllers/Admin/GymReviewController.php`, `app/Http/Controllers/Admin/PlatformStatsController.php`, `app/Http/Controllers/PublicGymController.php`, `app/Notifications/GymApplicationDecided.php`, routes, `bootstrap/app.php` alias, `tests/Feature/GymReviewTest.php`. `php artisan test`: 49 passed.
- A decided (approved or rejected) application answers 409 to further checklist changes or decisions.
- "Registered gyms" counts every application that was sent (drafts excluded).

## Done when

- [x] A test shows a non-admin gets 403 on every `admin/*` route.
- [x] A test shows approve is refused while any checklist item is failed or not set, and Tier 2 needs the 2 extra items.
- [x] A test shows reject without a reason returns 422.
- [x] A test shows a returned application can be fixed by the applicant and submitted again.
- [x] A test shows the applicant gets exactly one notice per decision.
- [x] A test shows the approved applicant holds the owner role (`hasGymRole`) and can open `GET /api/gyms/{gym}/roles`.
- [x] A test shows `GET /api/gyms` lists only approved gyms with their badge; draft, submitted, returned and rejected gyms are absent.
- [x] A test shows the stats return correct counts and no member, money or staff fields.
- [x] `php artisan test` passes.

---

[← Previous: Phase 1](./phase-1.md) | [Next: Phase 3 →](./phase-3.md)
