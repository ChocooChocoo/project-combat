[Next: Phase 2 →](./phase-2.md)

**Status:** ✅ Done

# Phase 1: Gym application and private documents (backend)

**Goal:** A confirmed user can create a gym application, pick a tier, upload exactly that tier's documents into private storage, follow its status, and send it only when complete. Covers roadmap items R-11 to R-15 on the backend.

## Dependencies

- Roadmap Phase 1 complete: `verified.email` middleware, `gyms` table with `status`, `Gym::addPerson()`, `GymPersonType::Owner`, notification inbox (commit 0903d19).
- PHP upload limits raised in `C:\Users\marka\AppData\Local\Programs\PHP\8.4.27\php.ini` (now `upload_max_filesize=2M`, `post_max_size=8M`). Suggested: `upload_max_filesize=200M`, `post_max_size=210M`. Without this the video tour cannot upload.

## Decisions and assumptions

- The gym row is the application. One user may have several (Q-09 answered yes).
- The applicant is **not** added to `gym_user` until approval (Phase 2 of this plan).
- `gym_documents` does **not** use the `BelongsToGym` trait: applicant and administrator work outside a gym workspace, so there is no current gym. Access is checked by `GymApplicationPolicy` instead.
- Files go on the existing private `local` disk (`storage/app/private`) and are only streamed through an authorized API route. No public or signed links, so a copied link opens nothing.
- Q-16 (tier upgrade) and Q-17 (document expiry) are unanswered. Assumption: no upgrade flow, no expiry. `tier` (chosen) and `approved_tier` (badge) are separate columns so either can be added later.
- Upload rules from the spec page 11 ("proposed") are treated as settled.

## Tasks

- [x] Migration `backend/database/migrations/2026_11_04_100000_add_application_to_gyms.php`: add to `gyms` `applicant_id` (FK `users`), `tier`, `approved_tier` (nullable), `address_line`, `barangay`, `city`, `province`, `latitude`, `longitude`, `contact_phone`, `contact_email`, `operating_hours` (json), `disciplines` (json), `services` (json), `facilities` (json), `submitted_at`, `decision_reason`, `return_items` (json), `checklist` (json), `reviewed_by` (FK `users`, nullable), `reviewed_at`; change `status` default to `draft`. Create `gym_documents` (`gym_id` FK cascade, `type`, `path`, `original_name`, `mime`, `size`, timestamps).
- [x] `backend/config/verification.php`: per tier the document types with label, what it proves, who sends it, allowed file types, minimum count. Tier 1: `dti` *or* `sec`, `mayors_permit`, `barangay_clearance`. Tier 2: all of Tier 1 plus `facility_photo` (min 10), `video_tour` (mp4), `owner_id`, `owner_selfie`, `utility_bill`. Also: checklist items (3 base, 2 extra for Tier 2), discipline list, size limits (documents and photos 10 MB, video 150 MB).
- [x] `backend/app/Enums/GymStatus.php`: `draft`, `submitted`, `under_review`, `returned`, `approved`, `rejected` (same style as `AccessLevel` and `Module`).
- [x] Update `backend/app/Models/Gym.php` (fillable, casts, `applicant()`, `documents()`), add `backend/app/Models/GymDocument.php`, add `draft()`/`approved()` states to `backend/database/factories/GymFactory.php`.
- [x] `backend/app/Policies/GymApplicationPolicy.php`: view = applicant or platform admin; update, upload, delete = applicant and status `draft` or `returned`.
- [x] `backend/app/Http/Controllers/Gym/GymApplicationController.php`: `requirements` (config out), `index` (my applications), `store`, `show` (profile, documents, reason, items to fix), `update` (only `draft` or `returned`; changing tier never deletes files), `submit` (every required field, every required document for the chosen tier, at least 10 photos for Tier 2; 422 lists what is missing; sets `submitted`, `submitted_at`).
- [x] `backend/app/Http/Controllers/Gym/GymDocumentController.php`: `store` (type must belong to the chosen tier; file type and size from config; saved under `gym-applications/{gym}/` on the `local` disk), `show` (policy, then `Storage::disk('local')->response()`), `destroy` (own file, before sending, deletes the stored file too).
- [x] Routes in `backend/routes/api.php` inside the existing `verified.email` group: `GET gym-applications/requirements`, `GET|POST gym-applications`, `GET|PUT gym-applications/{gym}`, `POST gym-applications/{gym}/submit`, `POST gym-applications/{gym}/documents`, `GET|DELETE gym-applications/{gym}/documents/{document}` (document scoped to the gym).
- [x] `backend/tests/Feature/GymApplicationTest.php` (pattern of `AccessTest.php`; `Storage::fake('local')`, `UploadedFile::fake()`).

## Status notes

- 2026-11-04: Built. Files: migration `2026_11_04_100000_add_application_to_gyms.php`, `config/verification.php`, `app/Enums/GymStatus.php`, `app/Support/Verification.php`, `app/Models/Gym.php`, `app/Models/GymDocument.php`, `app/Policies/GymApplicationPolicy.php` (registered with `#[UsePolicy]`), `app/Http/Controllers/Gym/GymApplicationController.php`, `app/Http/Controllers/Gym/GymDocumentController.php`, routes, `tests/Feature/GymApplicationTest.php`. `php artisan test`: 38 passed. Migration applied, rolled back and re-applied on MySQL.
- Each single document type holds one file (delete to replace); facility photos up to 30.
- Test fake images use `create(..., 'image/jpeg')` because the GD extension is not installed.
- php.ini upload limits are still 2M/8M: not changed (outside the project). Needed before a real video upload.

## Done when

- [x] A test shows an unconfirmed user gets refused on `POST /api/gym-applications`.
- [x] A test shows an application with a missing profile field or missing required document cannot be submitted (422 names the missing items).
- [x] A test shows Tier 1 accepts DTI *or* SEC, and needs both permits.
- [x] A test shows a wrong file type (for example `.docx`, or a `.pdf` as video) is refused with a clear message.
- [x] A test shows a Tier 2 application with 9 photos cannot be submitted, and with 10 it can.
- [x] A test shows another user gets 404 and a signed-out request gets 401 on a document URL.
- [x] A test shows deleting a file after submitting is refused.
- [x] A test shows changing tier keeps already uploaded files.
- [x] A test shows the applicant has no `gym_user` row after submitting.
- [x] `php artisan test` passes (old tests included).

---

[Next: Phase 2 →](./phase-2.md)
