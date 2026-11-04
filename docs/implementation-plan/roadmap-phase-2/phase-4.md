[← Previous: Phase 3](./phase-3.md)

**Status:** ✅ Done

# Phase 4: Administrator screens and approved gym list

**Goal:** The platform administrator sees platform numbers and the application queue, reviews each application with its documents side by side, ticks the checklist, and records a decision. Approved gyms show on the home page with their tier badge. Then the roadmap notes are updated. Covers the screens for R-16 to R-19.

## Dependencies

- Phase 2 complete (`admin/*` endpoints, `GET /api/gyms`, notices).
- Phase 3 complete (`apiFile()` helper, application screens for the applicant side of the check).
- An administrator account: `php artisan app:make-admin you@example.com`.

## Decisions and assumptions

- Admin screens show only when `user.is_platform_admin` is true; the backend still refuses non-admins (403).
- The home page shows a plain list of approved gyms with badges. Search and filters stay in roadmap Phase 3 (R-30).

## Tasks

- [x] Admin link in `frontend/src/components/app-shell.tsx` when `user.is_platform_admin`; admin guard in `frontend/src/App.tsx` that redirects others to `/`.
- [x] `frontend/src/pages/admin/AdminHome.tsx`: stat cards (registered, approved by tier, pending, returned, rejected) and the application queue table with a status filter.
- [x] `frontend/src/pages/admin/ReviewApplication.tsx`: profile and documents side by side; open each file with `apiFile()`.
- [x] Checklist in `ReviewApplication.tsx`: pass/fail per item, Tier 2 extras shown only for Tier 2, saved on change.
- [x] Decision buttons in `ReviewApplication.tsx`: Approve (disabled until every item passed), Reject (`AlertDialog`, reason required), Return for correction (`AlertDialog`, pick items to fix).
- [x] Approved gyms list with tier badge on `frontend/src/pages/Home.tsx`, from `GET /api/gyms`.
- [x] After every "Done when" below passes: update `docs/ANALYSIS - COMBAT SPORTS PLATFORM/05 - ROADMAP/phase-2/PHASE 2.md` (R-11 to R-19 status and checkboxes, counts table), `05 - ROADMAP.md` (phase table, totals, next eligible item), and the change log in `00 - START HERE.md` (note the Q-16 and Q-17 assumption).

## Status notes

- 2026-11-04: Built. Files: `frontend/src/pages/admin/AdminHome.tsx`, `frontend/src/pages/admin/ReviewApplication.tsx`, `AdminOnly` guard and routes in `frontend/src/App.tsx`, "Administration" link in `frontend/src/components/app-shell.tsx`, approved gym list on `frontend/src/pages/Home.tsx`.
- Checked in a real browser: administrator returned one application, the applicant fixed and resent it, then it was approved after all 5 Tier 2 checks passed; a second (Tier 1, 3 checks) was rejected, and the reject dialog would not close without a reason. The applicant's inbox showed the "needs corrections" and "is approved" notices (the "was not approved" notice is covered by the automatic test). Home showed "E2E Fight Club, Tier 2 verified" and not the rejected gym. The applicant saw the gym under "Your gyms" as owner. A non-admin opening `/admin` was sent to `/`. Stats matched (1 registered, 1 Tier 2 verified). No sideways scrolling at 375 px.
- `php artisan serve` handles one request at a time on Windows, which made screens slow to fill. `PHP_CLI_SERVER_WORKERS=4` fixed that for the check.
- Roadmap notes updated: `PHASE 2.md` (all items ✅, *Where it was built*), `05 - ROADMAP.md` (totals 19 ✅ / 79 ⭕, next item R-20), the change log in `00 - START HERE.md`, and the R-18 row in `PHASE 3.md`.
- Data from the browser check was removed afterwards.

## Done when

- [x] `pnpm build` passes.
- [x] Manual run: the administrator approves one application, rejects one with a reason, and returns one with items to fix.
- [x] Manual run: the applicant sees a notice in the inbox for each of those decisions.
- [x] Manual run: the approved gym appears on the home page with its tier badge; pending, returned and rejected gyms do not.
- [x] Manual run: the approved applicant now sees the gym under "Your gyms" with the owner role.
- [x] Manual run: a non-admin opening `/admin` is sent to `/`, and the admin API answers 403.
- [x] The admin screens show no member, money or staff data.
- [x] `php artisan test` still passes.

---

[← Previous: Phase 3](./phase-3.md)
