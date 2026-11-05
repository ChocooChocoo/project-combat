# Permission catalog verification

Verified 5 November 2026 on branch `feat/rbac-permission-catalog`.

## Automated checks

| Check | Result |
|---|---|
| Initial backend baseline | 244 tests, 21,845 assertions passed |
| Final `php artisan test` | 282 tests, 22,871 assertions passed; 63.773 seconds |
| `pnpm build` | Passed TypeScript and Vite production build |
| `pnpm lint` | Exit 0; 70 warnings, no errors |
| Old backend access helpers and route middleware | Removed; exact permission checks replace them |
| Frontend old module-level `can` calls and template endpoint | Removed |
| `git diff --check` | Passed at handoff; index remains empty |

Build warnings concern the existing Vite `__dirname` configuration and a bundle larger than 500 kB. Lint warnings concern existing component exports, effects, refs, and editor patterns. The newly introduced date-render purity warning was fixed with lazy state initialization. No dependency was added.

Failing-first runs were recorded for the catalog, missing storage shape, Access test trait, route middleware, inline resource checks, lifecycle endpoints/seeding, admin endpoints, `/me` payload, case-insensitive role names, dotted unknown module expansion, and owner assignment through a legacy duplicate employee membership. Additional migration and safety regression cases were then run with the full suite.

New coverage is in:

- `backend/tests/Feature/PermissionCatalogTest.php`: 51 unique keys, all modules, exact names, unknown levels/modules including dotted paths, and custom-action skipping during legacy expansion.
- `PermissionStorageTest.php`: independent permissions, gym isolation, disabled role/permission and expired grants, forced assignment partial-failure rollback, and unknown sync keys preserving existing links.
- `PermissionMigrationTest.php`: legacy access and assignment preservation, safe round-trip, malformed-map rejection before schema changes, and refusal of lossy, disabled, or dated-grant downgrade.
- `RoleSafetyTest.php`: explicit staff `role_ids` guard, guarded offboarding, expired self-held edit refusal, owner-role assignment rejection even with a legacy employee membership, case-insensitive names, disabled-key cleanup, rejected dormant additions, and unchanged dormant-grant preservation/removal.
- `RouteAccessTest.php`: exact route-to-resource mapping, not only catalog membership or check count.
- `ExactPermissionTest.php`: roles-only read cannot read staff or schedules; pay/leave helpers use exact keys; expired grants do not receive notifications.
- `RoleLifecycleTest.php`: on/off behavior, disabled cleanup without reactivation, self-toggle refusal, end-of-day dates, expired history, hard-delete guard, renamed starter idempotence, case-insensitive collision skip, and forced starter-seed transaction rollback.
- `AdminPermissionTest.php`: admin-only CRUD, schema-length/module validation, cross-gym usage count and delete guard with no current gym, global disable including owners, and status-preserving sync.
- `RbacSeederTest.php`: seeding twice with fake storage preserves custom catalog rows and disabled status and leaves no orphaned permission pivots.

Existing tests were adapted through `Concerns/GivesGymRoles.php`. `AccessTest` covers the new payload and role CRUD. `GymReviewTest` verifies starter roles on approval. The user-owned, untracked `DatabaseSeederTest.php` was run unchanged.

## Browser smoke

Playwright used a newly created SQLite file under the approved temporary directory. It did not use the configured MySQL database or existing dev servers. Temporary servers used ports 8017 and 5177 and were stopped after testing. Fixtures used test-only identities and credentials, not real accounts.

| Interaction | Observed result |
|---|---|
| Owner signs in, opens Staff and Roles | Correct staff list, starter roles, status and grant counts |
| New role, search training bookings, select Approve | Lower actions automatically ticked |
| Untick View and Manage, save, reopen | Approve-only selection persisted; no inferred server hierarchy |
| Apply Coach template and toggle own-permission filter | Template selected 11 permissions; filter toggled |
| Cancel or Escape from editor | Dialog closed |
| Toggle custom role off and back on | Role status updated both ways |
| Open existing expired assignment and save unchanged | Historical expiry remained; no past-date validation trap |
| Assign role until 1 December 2026, reopen | End date persisted |
| Remove assignment, delete now-unused role | Both actions succeeded through their dialogs |
| Submit duplicate role name | Validation message displayed; no duplicate created |
| Roles-only employee opens workspace | Redirected to roles tab; Employees tab and New role button absent |
| Admin follows Permission catalog link | Initial catalog contained 51 rows |
| Admin creates and renames custom permission | Added status and renamed text appeared |
| Admin turns payments View off | Owner's Payments link disappeared after fresh sign-in |
| Owner edits role with disabled payment key | Existing dormant key remained visibly selected; explicit removal saved |
| Owner searches newly added custom key | Custom action appeared in role editor |
| Admin turns payment permission back on | Active state restored |
| Admin deletes unused custom key | Confirmation completed; search showed empty state |
| Inject catalog HTTP 503, then Retry | Error state displayed and retry recovered |
| Role editor at 390 x 844, light/dark CSS | Search empty state worked; no document horizontal overflow; Tab and Escape worked |
| Admin list and dialog at 390 x 844 | No document horizontal overflow |

Console review found no uncaught JavaScript errors. Expected failed-resource messages were one intentional HTTP 422 validation response and two injected HTTP 503 responses from development effect replay. No unexpected console warning was reported.

Browser evidence was moved out of the repository to `C:\Users\marka\AppData\Local\Temp\opencode\rbac-browser-evidence-20261105`. The disposable database and fixture script also remain in that temporary directory for inspection, not deployment.

## UI delivery checks

- PASS, direction preserved: existing workspace components, palette, typography, spacing, and layout were reused. No visual assets or fabricated data were added to production screens.
- PASS, functional paths: editor, assignment, role lifecycle, admin catalog, empty/error/retry, and dialog close interactions have the browser evidence above.
- PASS, responsive smoke: changed role editor and admin list/dialog were exercised at 390 px without document overflow.
- PASS, exact access presentation: roles-only navigation, independent action ticks, disabled-key cleanup, and global permission removal matched server behavior.
- PASS, build verification: TypeScript/Vite and lint completed; warnings are reported, not hidden.

This is focused RBAC verification, not a full accessibility certification. Existing color tokens were retained; no automated contrast audit or exhaustive keyboard/screen-reader audit was performed. Dark CSS was exercised by setting the existing root class; the application does not provide a theme toggle.

## Limits and handoff

- SQLite migration and rollback behavior is covered. Migration has not been run against MySQL in this session, and production backup restoration was documented, not executed.
- Browser checks cover the RBAC flows listed above, not every unrelated membership, finance, scheduling, or leave interaction. Their backend regression tests passed.
- No frontend unit-test framework exists. Build, lint, source checks, and browser smoke provide frontend evidence.
- The configured database was never reset. Seeder verification used in-memory SQLite and fake storage; browser verification used a separate temporary SQLite file.
- No files were staged or committed. The user-supplied seeder changes remain in place; only RBAC imports, catalog sync, pivot reset ordering, role creation, and starter seeding were changed there. User-owned `DatabaseSeederTest.php` was not edited or staged.
- The supplied plan's implementation and verification checkboxes are updated. Its ten commit steps remain explicitly deferred. Approved policy corrections override the original illustrative snippets.

Deployment and guarded backup recovery instructions are in [rbac-operations.md](rbac-operations.md).

## UI-only recheck, 5 November 2026, 11:29–11:32 UTC

**Result: blocked. This run does not establish comprehensive end-to-end success.**

The earlier disposable-database smoke run above included fixture setup, injected HTTP failures, and a root-class theme change. Those observations are not proof of the later, stricter UI-only acceptance condition. Saved earlier artifacts exist under the temporary evidence path above, but this recheck did not reconstruct or independently verify every earlier action.

This recheck used Playwright MCP against the already-running frontend at `http://localhost:5173` and backend at `http://localhost:8000`. All application actions used visible links, buttons, and form inputs. Network responses and accessibility snapshots were inspected read-only. No SQL, migration, seed, API shortcut, storage edit, clock change, network mock, or injected application data was used. No application code was changed.

| Actual interaction | Observed result |
|---|---|
| Open app, type documented seeded owner credentials, click Sign in | `POST /api/auth/login` returned HTTP 500; owner remained on sign-in screen |
| Inspect failed sign-in response | MySQL reported missing `combat_platform.permissions` table; failure arose while serializing owner permissions |
| Observe sign-in screen after response | No visible error alert or notification; inputs and sign-in button remained |
| Type documented seeded admin email and click Sign in | Admin authenticated successfully and reached Home |
| Click Gym applications, then Permission catalog | Navigation worked; catalog request returned HTTP 500 and displayed an error alert |
| Click Try again | Another real catalog request failed; error state remained |
| Click New permission, type resource/action/name, click Cancel | Dialog opened, accepted typing, and closed without saving |

### Findings

1. **Blocking environment/deployment gap:** running MySQL schema does not contain the RBAC `permissions` table. Owner authentication and catalog loading fail. This is not evidence that the migration itself fails; the running environment has not been brought to the required schema.
2. **Sign-in error feedback defect:** the observed owner HTTP 500 produced no visible failure message. Reproduce with the owner sign-in steps above while the table is missing.
3. **Error-detail exposure:** catalog error alert displays raw SQL, database name, host/port, and query structure. The login response also contains a PHP exception trace and local source paths. Treat deployment debug/error handling separately from RBAC correctness; detailed internal errors should not be shown to production users.

### Coverage not established in this run

Role creation/edit/delete, templates, assignment/expiry, role toggles, self-change prevention, no-escalation, gym isolation, starter-role protection, narrow-permission navigation, catalog CRUD/status effects, and permission-driven workflows remain **blocked or untested**, not passed. Admin navigation, retry interaction, and unsaved dialog editing were observed, but successful catalog persistence was not tested.

Playwright snapshots: `.playwright-mcp/rbac-ui-only-login-blocker-20261105.yml` and `.playwright-mcp/rbac-ui-only-admin-blocker-20261105.yml`. Browser console log: `.playwright-mcp/console-2026-11-05T11-29-32-207Z.log`. Failed owner login was network request 255 in this browser session. Initial unauthenticated `/api/me` HTTP 401 responses were expected, not RBAC failures.

Next prerequisite: have the environment owner deploy the migration, or provide an already-migrated test environment and test accounts. Database repair was deliberately not performed because the verification request prohibits non-UI application-state manipulation. Resume the full UI-only matrix after that prerequisite, without treating earlier fixture-assisted checks as replacement evidence.

## Requirements comparison: resumed UI-only run, 11:33–11:57 UTC

**Overall result: partial verification, not full acceptance.** On the next Playwright snapshot the same catalog page displayed data. Owner sign-in also subsequently succeeded. Something outside this verification brought the running environment to a usable state; this run did not execute migrations or attribute that recovery to a code fix. The earlier HTTP 500 observations remain historical evidence.

All application changes below used ordinary Playwright MCP clicks, typing, form submissions, and navigation. Isolated browser contexts separated admin, owner, and employee sessions. At the user's request, JavaScript `alert()` reported observed successes; evaluation was used only for these notifications, never for data setup, DOM changes, validation bypasses, or outcome fabrication. These notifications are test-run messages, not newly implemented application features.

### Expected versus actual

PASS applies only to the stated observation, not the entire security invariant. FURTHER VERIFICATION means insufficient UI-only evidence, not an inferred failure.

| Requirement / expected behavior | Actual browser observation | Result |
|---|---|---|
| Owner can sign in and reach workspace | Owner signed in, reached Setup, clicked Staff, and loaded employees/roles | PASS, current run; previous missing-schema failure retained |
| Admin can navigate to catalog | Admin clicked Gym applications, then Permission catalog; catalog rendered | PASS |
| Non-admin cannot open admin page | Employee navigated to `/admin/permissions`; redirected Home with no catalog | PASS, navigation guard only |
| Initial catalog has exactly 51 unique system entries | Catalog rendered many exact keys, but this resumed run did not independently count/reconcile all initial rows | FURTHER VERIFICATION |
| Catalog uses exact `module.resource.action` keys | Created `inventory.evidence_check.adjust`; exact key appeared in table and owner editor | PASS for this key, not exhaustive matching semantics |
| Empty catalog form rejects missing resource/action | Clicked Save on empty form; both fields invalid with “Please fill out this field.” | PASS |
| Duplicate catalog key rejected | Submitted existing custom key again; form stayed open, Action invalid, “This permission already exists.” | PASS; expected validation rejection, not feature failure |
| Custom creation uses readable default name and Added marker | Saved resource `evidence_check`, action `adjust`, blank name; table showed “Adjust evidence check”, Added, On | PASS |
| Renaming persists without changing identifier | Renamed to “Browser evidence adjustment”; fresh page load retained that name and original key | PASS |
| Global usage count reflects role linkage | Custom key count observed 0 before use, 1 after attachment to test role, 0 after explicit removal | PASS for controlled role linkage; all other counts unaudited |
| Used custom permission not deletable | At count 1, Delete control absent; at count 0 it appeared | PASS, UI protection only; forced server deletion not attempted |
| Global permission can be disabled/re-enabled with confirmation | Confirmed custom key off: Off status, count 1 retained; later confirmed on: On status | PASS for catalog status and retained linkage |
| Disabling a checked system key removes it from owners too | No system permission was disabled in this resumed run | FURTHER VERIFICATION |
| Unused custom deletion succeeds and removes search result | Confirmed deletion after cleanup; exact-key search showed “No matching permissions” | PASS |
| Custom permissions require code/config registration before enforcement | Catalog displayed explicit warning mentioning config, check, and sync; new key appeared in editor | PASS for explanatory UI; runtime registration behavior unverified |
| Role create/edit persists name, description, and selected permissions | Created “UI Evidence Role 1136”; reopen retained its name, description, HR roles View/Manage, payments View, and custom key | PASS |
| Coach template selects expected starting set | Selected Coach; editor showed 11 selected permissions | PASS for count; individual template keys not fully reconciled |
| Approve conveniently selects lower actions | Clicked bookings Approve; View/Manage/Approve all visibly checked | PASS |
| Permissions remain independently selectable and persist | Unticked bookings View and Manage separately; saved and reopened: Approve checked, both lower actions unchecked | PASS for stored selection; approval-only booking workflow not exercised |
| Role assignments replace selected roles and retain expiry date | Replaced Jomar's Coach role with test role until `2026-11-08`; reopen showed only test role checked and exact date | PASS |
| Past expiry rejected | Changed date to `2026-11-04`; submission blocked with “Value must be 11/05/2026 or later.” | PASS, browser validation; server-only rejection not separately proven |
| Grants cease at expiry boundary | No clock manipulation or waiting until expiry performed | FURTHER VERIFICATION |
| Role-off state preserves assignment but removes access | Test role Off retained 1 person/4 permissions. Fresh employee sign-in had no workspace link; normal navigation to Payments displayed access denied and no records | PASS for tested payment access |
| Re-enabling restores permission access | Role status returned On; employee reloaded Payments and payment-history table loaded | PASS |
| Payments view does not grant payment writes | Employee payment page loaded without payment-recording action | PASS, UI restriction only; payment values and write endpoints unaudited |
| Roles-only access works without staff-view permission | Employee opened workspace through visible link; Roles tab present, Employees tab absent, Staff/Payments links present, Schedule/Bookings links absent | PASS for tested combination |
| Self-held roles cannot be edited/toggled | Employee's test-role card said another authorized person must edit it and showed no mutation controls | PASS, UI restriction only; assignment/expiry self-write rejection not exercised |
| Cannot grant access not held | Employee editor showed `health_records.records.view` disabled; higher-access existing role cards lacked mutation controls | PASS, UI restriction only; crafted requests not attempted |
| Disabled grants remain visible and can be preserved | Owner editor displayed separate checked dormant key; save/reopen retained it without lockout | PASS |
| Disabled grant can be explicitly removed without regrant | Unticked dormant key, observed unchecked/disabled, saved, reopened: key absent and count fell from 5 to 4; admin usage count became 0 | PASS after individually observed steps; timing ambiguity below retained |
| Role held by employee cannot be deleted | Test role had no Delete action while assigned | PASS, UI protection only |
| Unused non-system role can be deleted | After restoring employee, Delete appeared; confirmation removed test role and its card disappeared | PASS |
| Cross-gym navigation stays isolated | Gym-1 employee opened `/gyms/2/manage/staff`; redirected Home, no gym-2 staff shown | PASS, navigation isolation only |
| Starter roles marked, nondeletable, created on approval | Existing gym showed legacy role names, not an observed new approval/starter-seeding workflow | FURTHER VERIFICATION; do not infer starter status from missing Delete alone |
| Role duplicate names / schema-length / lowercase validation | Not exercised in this resumed run | FURTHER VERIFICATION |
| Staff create/offboard cannot bypass role guards | No employee creation or offboarding performed | FURTHER VERIFICATION |
| Schedule/holiday/health/pay/leave/booking/membership resource gates | Only payment read/off-on behavior and role controls exercised | FURTHER VERIFICATION for other resource workflows |
| Loading, empty, error, retry states | Empty search observed after deletion; real catalog error/retry observed in earlier UI-only run | PASS for those observations; no injected failures or recovery guarantee |
| Mobile, keyboard, contrast, screen reader | Not comprehensively exercised under this stricter run | FURTHER VERIFICATION |
| Migration, sync command, rollback safety, seeder idempotence | Cannot establish through ordinary browser UI; earlier automated evidence is separate | FURTHER VERIFICATION under browser-only criterion |

### Discrepancies and uncertainty retained

- **Historical confirmed failures:** missing-schema owner login/catalog HTTP 500, missing visible owner-login error, and raw internal SQL/trace exposure from the earlier run. Current successful login/catalog loading does not prove their error handling was fixed.
- **Save/read timing ambiguity:** an immediate save/reopen sequence once showed old checkbox values. Individually observed clicks, save, and reopen subsequently persisted the expected values. This is not marked a confirmed application bug or erased as irrelevant. Investigate loading/reload coordination before claiming rapid repeated actions are safe.
- **Browser session interruption:** Playwright unexpectedly lost tabs/isolated contexts and returned `about:blank`; normal sign-in restored access. Cause unconfirmed; not attributed to application and not marked passed.
- **Tool errors:** stale references, invalid selector syntax, a transient toast causing two `dialog` matches, text-selector timeouts on split status text, and expected redirects were distinguished from application failures. Outcomes were checked with fresh snapshots; a timed-out selector alone did not determine feature status.
- No new confirmed product defect was established during the successful resumed workflows. This is not proof of absence of bugs in untested flows.

### Cleanup and evidence

Jomar originally held Coach only, with no end date. Restored exactly that visible assignment through the owner dialog; reopened and verified it. Removed the temporary role after unassignment. Re-enabled, then deleted the unused custom permission through admin confirmation. Search confirmed absence. No existing role content or system permission status was changed. Assignment audit metadata may reflect legitimate test updates; this run does not claim byte-for-byte database restoration.

Evidence is in chronological `.playwright-mcp/page-2026-11-05T11-33*` through `11-57*` snapshots and tool interactions. Focused snapshots include:

- `.playwright-mcp/rbac-role-saved-20261105.yml`
- `.playwright-mcp/rbac-dormant-owner-editor.yml`
- `.playwright-mcp/rbac-dormant-cleanup-final.yml`
- `.playwright-mcp/rbac-jomar-restored.yml`

Snapshots are supporting observations, not executable assertions or a substitute for the recorded action sequence. Previous automated passing counts are deliberately not used to mark unobserved browser requirements passed.
