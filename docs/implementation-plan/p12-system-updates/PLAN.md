# P12 — System updates: registration, emergency contacts, wording, chat, coach documents, injury proof

Status: built 17 Nov 2026, then **browser-tested 17 Nov 2026 (Playwright, localhost, seeded data)**. Phases 1 to 6 done. Checked: backend **777 tests passed** (32,868 assertions); `pnpm build` ok; `pnpm lint` **0 errors** (112 warnings, all pre-existing); `node --test` **45 passed** (41 existing + 4 new regression tests); all three `2026_11_28_*` migrations applied to local MySQL and `migrate:fresh --seed` ran clean.

**The browser run found two real bugs the API tests could not see, and both are fixed** (detail under Phase 1 below):
1. Sign-up sent `suffix: "none"` and the API refused every account made with the default "None" suffix. This predates P12 — it is very likely what the client meant by "make the suffix field optional".
2. Choosing a value in the emergency-contact relationship dropdown silently saved an **empty** relationship, because the Select's `items` list was rebuilt on every render.

**Not verified:** the coach-application and injury **PDF** downloads do not work in the Playwright Chromium session, and neither do the pre-existing job-application PDF downloads. The server is correct: `curl` returns `200 application/pdf` with the exact byte size for every one of those routes. The same browser fetches `image/png` and JSON normally, and the PDF download **did** work once earlier in the same run (`200`, `application/pdf`, 201,059 bytes), so this is a session/transport limitation of the test browser, not a defect in this work. iOS Safari and Firefox were not used.

Source: the client's system-update request (account registration, emergency contact, terminology, chat, coach applications, injury reports). Requirement IDs **R-142 to R-147** continue after P11's R-141.

## Context
Five of the six requests touch code that already exists in a different form; one is genuinely new.

- **Suffix (R-142).** `users.suffix` is already nullable, and both `AuthController::register` and `ProfileController::update` validate it as `['nullable', Rule::in(User::SUFFIXES)]`. `SignUp.tsx` already defaults to `None` and only sends the field when it is set. The only real gap was the label: it read "Suffix" next to fields that say "(optional)", so people could not tell they may skip it.
- **Emergency contact relationship (R-143).** The relationship was a free-text `Input` in `ContactsEditor`, validated as `required|string|max:60` in `MembershipApplicationController::rules()` and `AthleteRecordController::updateMyHealth()`. The form metadata endpoint `/api/membership-form` already ships server-owned lists to the browser.
- **"Athlete" wording (R-144).** About 40 visible strings say "athlete" while the rest of the product says "member". The identifiers — `athlete_profiles`, `training.athletes.*` permissions, `/athletes/{user}/…` routes, `record.athlete`, `attending_athletes` — are internal and stay, exactly as P11 renamed the "Decision support" label to "Analytics" without touching permission strings or routes.
- **Chat (R-145).** Nothing existed. Notices are database-only and polled every 15 s; there are no websockets, and that stays true.
- **Coach application documents (R-146).** `coach_applications` stored no files. `job_applications` (R-138) is the two-file example, and `purchase_order_proofs` (R-114) is the multi-file example; both use the private `local` disk with a permission-checked download route.
- **Injury proof (R-147).** Injuries had no attachments. The request says "a medical certificate **or other supporting documents**", so a child table was used rather than four nullable columns, following `purchase_order_proofs`.

## Decisions
- **Suffix** stays optional exactly as the API already allows; only the label changes, plus a test that pins the behaviour.
- **Relationship** is a fixed list stored as the shown label: `Parent, Spouse, Sibling, Child, Relative, Friend, Other`, held in `config('membership.emergency_relationships')` and shipped through `/api/membership-form`. An older free-text value shows as `<value> (old entry)` (the R-124 pattern) so an edit never silently drops it; the server refuses anything outside the list, so free text cannot come back.
- **Wording**: "Athlete" becomes "Member" in everything a person reads — screens, dialogs, toasts, server messages, demo record names. Internal identifiers, JSON keys, routes, tables and permission strings do not change.
- **Chat**: one 1:1 thread per pair per gym. Members may message staff (coaches are staff) and the owner; the owner may message staff and members; **no member↔member**. No group threads, no help-desk inbox, no per-message notice (an unread badge is the signal), polled at 15 s on an open thread and 60 s for the badge. No new permission strings, so the catalogue stays at 79.
- **Coach documents**: resume, valid ID and medical clearance **required**; certifications **optional**. PDF/JPG/PNG up to 5 MB, same as job applications.
- **Injury proof**: up to **3** files, PDF/JPG/PNG up to 5 MB, reusing `config('membership.medical_file')`. Reachable by the member themself or staff with `health_records.records.view` — a plain coach still sees only the "restricted" flag.

## Shared rules followed
- Migrations are `2026_11_28_*`, each with a working `down()`.
- New private files use the `local` disk with `path`, `original_name`, `mime`, `size`, served by an authenticated route that checks permission (the R-15 pattern).
- `GymNotice` / database notifications only; no websockets.
- No new permission strings; the catalogue in `config/permissions.php` is unchanged.
- Business rules in `app/Support` (`Chat`), JSON shapes in `Presents*` traits (`PresentsChat`).
- Frontend reuses `src/components/ui/*`; pure helpers in `src/lib/*.ts` keep no `@/` imports so `node --test` can load them.

---

## Phase 1 — Suffix and emergency-contact relationship (R-142, R-143)
- [x] `frontend/src/pages/SignUp.tsx`: label `Suffix (optional)`.
- [x] `frontend/src/pages/Profile.tsx`: same label.
- [x] `backend/tests/Feature/RegistrationIdentityTest.php`: new test that an account can be created without a suffix and `full_name` has no stray space.
- [x] `backend/config/membership.php`: `emergency_relationships` list.
- [x] `MembershipApplicationController::form()` returns `emergency_relationships`; `rules()` uses `Rule::in(...)` with the message "Choose the relationship from the list."
- [x] `AthleteRecordController::updateMyHealth()` uses the same rule and message.
- [x] `frontend/src/lib/membership.ts`: `MembershipForm.emergency_relationships`.
- [x] `ContactsEditor` takes the list and renders a `Select`; an out-of-list saved value shows as "(old entry)".
- [x] `MembershipApply.tsx` and `MyRecord.tsx` pass `form.emergency_relationships`.
- [x] Fixtures updated (`MembershipSetup` Mother→Parent, `AthleteRecordTest` Father→Parent); `AccountTypeTest` pins the new form key.
- [x] New tests: `MembershipApplicationTest::test_the_emergency_contact_relationship_must_be_one_of_the_choices`, and the refusal added to `AthleteRecordTest::test_the_athlete_updates_their_own_health_and_emergency_contacts`.

### Browser run: two bugs found and fixed
- [x] **Bug 1 — sign-up always sent the "None" placeholder.** `formValues()` reads the whole form, and the suffix `Select` writes its placeholder into the hidden input, so the request carried `suffix: "none"` and the API answered 422 "The selected suffix is invalid." Every account created with the default suffix failed. Fixed by `chosenSuffix()` in `lib/validation.ts` (leave the field out rather than send the placeholder). `Profile.tsx` was already correct because it overrides the value with `''`. Covered by `frontend/tests/emergency-contact.test.mjs`.
- [x] **Bug 2 — the relationship dropdown saved nothing.** Selecting a value cleared the hidden input and reset the trigger to "Choose one": the `items` array was rebuilt on every render inside the contacts `map`, so Base UI could not match the selected value. Fixed by extracting `ContactRow` and memoizing the list with `relationshipChoices()`; an older value is captured once at mount so it stays selectable as "(old entry)". Covered by `frontend/tests/emergency-contact.test.mjs`.

## Phase 2 — "Athlete" becomes "Member" in everything a person reads (R-144)
- [x] Gym screens: `MemberRecord`, `GymMembers`, `GymSession`, `GymSettings`, `GymApplications`, `GymAnnouncements`, `EmployeeRecord`.
- [x] Shared parts: `athlete-record-view` ("Member record"), `booking-parts` (dialog text and table heading), `session-actions`, `session-attendance`, `session-editor`, `goals-card`, `injuries-card`, `service-editor`, `decision-support/athlete-view` (labels and note), `lib/decision-support.ts` view label.
- [x] Member screens and marketing copy: `MyRecord`, `Landing`, `lib/landing.ts` alt text, `lib/membership.ts` and `lib/discovery.ts` header comments.
- [x] Backend messages: `AthleteRecordController`, `AttendanceController`, `BookingDesk`, `ServiceController`, `DecisionSupportController` meta note, `DecisionSupport/Sparring` notes.
- [x] Seeder record names a person sees: "Annual Member Pass", "Recreational member".
- [x] Copy assertions updated: `SparringTest` (2), `TrainingGapsTest` (1).
- [x] Deliberately unchanged: models, tables, columns, routes, permission strings, JSON keys (`record.athlete`, `attending_athletes`), TypeScript type names, and general prose about the sport ("combat athletes", coach bios).

## Phase 3 — Chat (R-145)
- [x] Migration `2026_11_28_090200_create_chat_tables.php` — `chat_conversations` (unique pair per gym, `last_message_at`) and `chat_messages` (`sender_id`, `body`, `read_at`).
- [x] `ChatConversation` and `ChatMessage` models, both `BelongsToGym`.
- [x] `app/Support/Chat.php` — `canChat`, `start`, `send`, `markRead`, `unreadFor`, `partnersFor`.
- [x] `PresentsChat` concern; `Gym/ChatController` with seven actions, each guarding that the viewer is in the thread (404 otherwise).
- [x] Routes inside the existing `gyms/{gym}` + `gym` group.
- [x] `frontend/src/lib/chat.ts` — types and pure helpers.
- [x] `frontend/src/pages/gym/Messages.tsx` — thread list, new-message picker, open thread with "Load older messages" paging, 15 s polling.
- [x] `frontend/src/App.tsx` — `/gyms/:gymId/messages` and `/gyms/:gymId/messages/:conversationId` under the `GymMember` guard.
- [x] `frontend/src/components/app-shell.tsx` — "Messages" sidebar item with an unread badge for the gym in the URL.
- [x] Tests: `backend/tests/Feature/ChatTest.php` (9 tests), `frontend/tests/chat.test.mjs` (4 groups).

## Phase 4 — Coach application documents (R-146)
- [x] Migration `2026_11_28_090000_add_documents_to_coach_applications.php` — eight nullable columns (four paths, four names).
- [x] `CoachApplication` hides the paths; the names reach the response.
- [x] `CoachApplicationController::store` validates and stores the files on the private disk; `payload()` adds the four names; new `file()` streams one document.
- [x] Route `GET gyms/{gym}/coach-applications/{application}/files/{kind}` behind `hr.coach_applications.view|approve`.
- [x] `lib/coach.ts` — the four names plus the accept/size constants and `attachedFiles`.
- [x] `CoachApplications.tsx` — the apply form sends `FormData` with four file fields and checks each file in the browser; the member's list shows what was attached.
- [x] `GymCoachApplications.tsx` — "View" buttons for each attached document.
- [x] Tests: `CoachApplicationTest` (9 tests) covers required files, optional certifications, downloads by the owner and HR, and the refusal for an unrelated member.

## Phase 5 — Injury proof documents (R-147)
- [x] Migration `2026_11_28_090100_create_injury_files.php` — `injury_files` mirroring `purchase_order_proofs`.
- [x] `InjuryFile` model and `Injury::files()`.
- [x] `InjuryController::store` accepts up to 3 files and rolls back written files on failure; `injuries()` returns `files`; new `file()` streams one proof.
- [x] Route `GET gyms/{gym}/injuries/{injury}/files/{file}` — member themself or `health_records.records.view`.
- [x] `injuries-card.tsx` — optional multi-file input on the report form, the attached file names with "View" buttons.
- [x] Tests: `SafetyTest` (2 new tests) covers the files being stored and listed, the 3-file limit, the wrong type, and who may open a proof.

## Phase 6 — Demo data and checks
- [x] `DatabaseSeeder::resetData()` clears `chat_messages`, `chat_conversations` (before `users`) and `injury_files` (before `injuries`); the seed file directories are removed too.
- [x] `P09Scenarios`: 15 of 25 coach applications carry a resume, a valid ID and a medical clearance, 5 of them also certifications. Older (rejected/withdrawn) applications keep no files, because that is what an older application looks like.
- [x] `P09Scenarios`: 20 injury proofs from `assets/documents/medical-certificate.pdf` and `assets/images/medical-certificate-scan.png`.
- [x] `P10P11Scenarios`: 10 chat threads with 25 messages and 10 unread, one member↔staff and one member↔owner per gym. A direct check confirmed every seeded pair passes `Chat::canChat` and every message sender is in the thread.
- [x] `DatabaseSeederTest` asserts the new rows and files (3 seeded dates).
- [x] `php artisan migrate` on local MySQL, then `php artisan migrate:fresh --seed`.
- [x] `php artisan test` (777 passed), `pnpm build`, `pnpm lint` (0 errors), `node --test` (45 passed).
- [x] Browser walkthrough of every phase in build order (see the section below). It found and fixed the two Phase 1 bugs above and produced `frontend/tests/emergency-contact.test.mjs`.

## Not verified
- **PDF downloads in the Playwright browser.** `View` on a coach resume, an injury certificate or a job-application credential returns `net::ERR_FAILED` in this test browser, while `image/png` and JSON responses work. `curl` gets `200 application/pdf` with the exact byte size for all three routes, and one PDF download did succeed earlier in the same run, so the server and routes are fine. Left open because it could not be reproduced as a code fault.
- **Mobile layout.** `Messages.tsx` at 360 px (list → thread switch) is type-checked but not visually confirmed.
- **iOS Safari and Firefox** were not used, the same gap P11 reported.

## Browser run 17 Nov 2026 (Playwright, localhost, seeded data)
Walked through in the order the phases were built.
- **Sign-up**: "Suffix (optional)" with "None" default; created an account without a suffix (stored `suffix` NULL, `full_name` "Playwright NoSuffix") and a second one with "III" ("… WithSuffix III").
- **Emergency contacts**: the relationship is a dropdown with the seven configured choices on both the apply form and My record; a legacy "Mother" value showed as "Mother (old entry)" above the list, picking "Parent" saved it, and the record then read Parent. Later changed to Sibling and confirmed in the database.
- **Wording**: "Member record", "Member", "Gym member", "…only that the member is restricted", "Members cannot message other members".
- **Chat**: a member's partner picker listed only Staff and Coach people (no other members). Sent a message, the coach's sidebar showed **1 unread**, he replied, his thread read 1 unread from Sofia, and the database showed her message read and his reply unread.
- **Coach application**: filled the form and attached a real resume PDF, a certificate PNG, an ID PNG and a medical-clearance PDF; the member's list showed all four attached, and the owner's screen listed them with a View button each.
- **Injury report**: reported an injury with a real PDF and a real PNG from `assets/`; both were listed under the injury with View buttons, and the first View returned `200`, `application/pdf`, 201,059 bytes — the same size as the source file.
- Also seen during the run, unrelated to this work: the temporary accounts created by the browser test remain in the local database.

## Not verified
- Real browser walkthrough of the chat screen, the coach apply form with real files, and the injury report with a real PDF. The automated tests cover the server rules and the frontend helpers, but nobody has clicked through these screens in a browser yet.
- Mobile layout of `Messages.tsx` at 360 px.
