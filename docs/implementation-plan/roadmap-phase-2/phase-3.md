[← Previous: Phase 2](./phase-2.md) | [Next: Phase 4 →](./phase-4.md)

**Status:** ✅ Done

# Phase 3: Applicant screens

**Goal:** A confirmed user can fill in the gym application on screen, set the location on a map, pick a tier, upload the right documents, send it, and follow its status. Covers the screens for R-11 to R-14.

## Dependencies

- Phase 1 complete (`gym-applications` endpoints and `requirements` endpoint).
- Phase 2 only needed to test the "returned, fix and send again" path end to end.

## Decisions and assumptions

- Map: Leaflet with OpenStreetMap tiles (`leaflet`, `react-leaflet`), chosen 4 November 2026. Reused later by nearest gyms (R-97).
- Only coss UI parts already in `frontend/src/components/ui/` (Field, Input, RadioGroup, CheckboxGroup, Card, Badge, Alert, Table, AlertDialog). No custom CSS; Leaflet's own CSS file is imported as is.
- Document and tier lists come from `GET /api/gym-applications/requirements`, never hard-coded in the screen.

## Tasks

- [x] In `frontend/`: `pnpm add leaflet react-leaflet` and `pnpm add -D @types/leaflet`; import `leaflet/dist/leaflet.css` in `frontend/src/main.tsx`.
- [x] Add `apiFile(path)` to `frontend/src/lib/api.ts`: fetch with the bearer pass, return a blob, open it with an object URL.
- [x] `frontend/src/components/location-picker.tsx`: map, click or drag the pin to set latitude and longitude, "Use my location" button (`navigator.geolocation`).
- [x] `frontend/src/pages/GymApply.tsx` (new and edit draft or returned): gym name, address, map location, contacts, operating hours per weekday, disciplines (checkboxes from config), services, facilities.
- [x] Tier picker in `GymApply.tsx`: Tier 1 or Tier 2, showing each document, what it proves and who sends it.
- [x] Document upload section in `GymApply.tsx`: one upload per document type, file list with delete (only while draft or returned), photo counter `n / 10`, rules text (clear, colour, not expired, names and addresses match), file `accept` attribute from config, server 422 messages shown next to the field. Files from the other tier are shown as "not needed" and kept unless the applicant deletes them.
- [x] "Send application" button: calls `submit` and shows the missing items list from a 422.
- [x] `frontend/src/pages/MyApplications.tsx`: list of the user's applications with status badge.
- [x] `frontend/src/pages/ApplicationDetail.tsx`: status, profile, every file openable with `apiFile()`, rejection reason or items to fix, "Fix and send again" link for returned ones.
- [x] Routes in `frontend/src/App.tsx` (inside the signed-in guard); "My gym applications" link in `frontend/src/components/app-shell.tsx`; "Apply to run a gym" card on `frontend/src/pages/Home.tsx`.

## Status notes

- 2026-11-04: Built. Files: `frontend/src/lib/gyms.ts` (types, `useRequirements`), `frontend/src/lib/api.ts` (`apiFile`), `frontend/src/components/location-picker.tsx`, `frontend/src/components/application-summary.tsx` (profile and document summaries, `StatusBadge`), `frontend/src/pages/GymApply.tsx`, `frontend/src/pages/MyApplications.tsx`, `frontend/src/pages/ApplicationDetail.tsx`, routes in `App.tsx`, links in `app-shell.tsx` and `Home.tsx`. Added `leaflet`, `react-leaflet`, `@types/leaflet`.
- Checked in a real browser (Playwright, `php artisan serve` + `pnpm dev`): full Tier 2 application with 10 photos and a video sent; wrong file type ("This file type is not accepted. Use: mp4.") and missing items listed; pin saved and shown after reload; tier switch kept 14 files under "Not needed for this tier"; returned application fixed and sent again; no sideways scrolling at 375 px; tier choice works with arrow keys.
- Opening files: images open in a new tab. PDFs could not be checked in the test browser, which turns PDF responses into downloads; check once in a normal browser. A signed-out request for a file URL gets 401.
- Fixed during the check: stale "not complete" list after uploading; short names for tier radios and Open/Delete buttons (screen readers); "DTI or SEC" summary hides the one not sent; the application step list pushed the page sideways at 375 px (missing `min-w-0` on the step navigation).
- Re-checked 2026-11-04: wrong file type refused with a clear message; 9 of 10 photos blocks sending and names the missing photo; switching tier keeps 14 files; the map pin survives a reload; tier choice works with arrow keys; a signed-out file request gets 401; non-admin calls to every `admin/*` route get 403; approve without a full checklist, reject without a reason and return without items each get 422; stats return only gym counts. `pnpm build` passes.
- The test used `php -d upload_max_filesize=200M -d post_max_size=210M artisan serve`. php.ini itself is unchanged.
- Data from the browser check was removed afterwards.

## Done when

- [x] `pnpm build` passes.
- [x] Manual run (`php artisan dev` and `pnpm dev`): a full Tier 2 application with 10 photos and a video is sent.
- [x] Manual run: a wrong file type and a missing document each show a clear message, and the application is not sent.
- [x] Manual run: the pin placed on the map is saved and shows again after reload.
- [x] Manual run: the detail page opens each uploaded file; a copied file URL opened in a new signed-out tab gives nothing.
- [x] Manual run: switching tier keeps uploaded files.
- [x] Manual run (after Phase 2): a returned application shows the items to fix and can be sent again.
- [x] Screens work at phone width (375 px) and with keyboard only (labels, focus visible).

---

[← Previous: Phase 2](./phase-2.md) | [Next: Phase 4 →](./phase-4.md)
