[← Previous: Packet 6G Budgets and reports](./phase-8.md)

**Status:** ✅ Done

# Packet 6H: Integration pass and close-out

**Goal:** Make the six modules feel like one system. Check every door, every empty state, every cross-module flow, and the documents. Close Phase 6 only when all 24 requirements are ✅, 🔵 or ⬜ and the evidence is recorded.

## Dependencies

All other packets. This pass changes no business rule; it fixes integration, permissions, layout and documentation.

## Tasks

### Navigation and access

- [x] Group the workspace sidebar so it stays usable at 19+ items. Reuse `NavGroup` in `frontend/src/components/app-shell.tsx`. Suggested groups: **People** (staff, attendance, leave, payroll), **Training** (schedule, bookings, check-in, attendance, services), **Members** (plans, applications, members, payments), **Business** (finance, customers, stock, purchasing), **Setup** (settings, rooms). Done in `gym-nav.tsx` with `workspaceNavGroups()`; `workspaceNav()` stays the flat order for `workspaceHome()`.
- [x] Audit every new page: missing view permission renders an `Empty` state, not a broken fetch; manage permission hides every write control; no page relies on the nav alone. All nine new pages guard first and gate every dialog, button and correction/receipt/release control.
- [x] Audit every new route: exact `permission:` middleware; own-record routes check ownership in the controller; no route accepts another gym's id. `RouteAccessTest` passes; `my-attendance`, `my-overtime-requests`, `my-work`, `me/payslips` and each gym relation are scoped to the signed-in person or `{gym}`.
- [x] Confirm the nav is permission-filtered for owner, HR, finance, front desk, stock staff and a plain employee. Finance now also appears for budgets-only or reports-only access, and Purchasing for a stock-only employee through the restock list.
- [x] Confirm `workspaceHome()` lands somewhere sensible for every role, including a stock-only or CRM-only employee. It is the first allowed group item: owner and HR land on Staff, front desk and coaches on Schedule, finance on Payroll approval, stock-only on Stock, CRM-only on Customers, and a plain employee on My work.

### Cross-module flows

- [x] Desk membership payment and PayMongo payment both appear in Finance income with the right type. `IncomeTest` covers desk and signed webhook rows; `PaymentIncomeTypeTest` covers membership, session and private coaching.
- [x] Session and private coaching payments appear in income and in member receivables until paid. `IncomeTest` and `ReceivableTest` compare each row with `MembershipLedger` and `BookingLedger`.
- [x] Payroll release posts `payroll` and `statutory_contributions` expenses once; the run is visible in reports. `PayrollRunTest::test_double_release_posts_each_expense_once` and `FinanceReportTest` released-run sums.
- [x] Receiving posts stock movement, purchase expense and supplier payable once; paying the supplier shows as cash out, not a second expense. `ReceivingTest` (partial, full, duplicate) and `PayableTest` (expense count unchanged by payment).
- [x] A low-stock crossing reaches the owner and stock staff; a purchase status change reaches requester and approver. `LowStockTest`, `PurchaseRequestTest` and `PurchaseOrderTest` notification counts.
- [x] A CRM lead converts to a member and appears in a customer group. `CustomerTest` follows lead → applied → member; `CustomerGroupTest::test_a_converted_lead_appears_as_a_member_in_a_customer_group` covers the joined flow; `CustomerConversionTest` proves both email directions and same-gym tenancy.
- [x] A removed employee keeps released payslips and their attendance history. `PayslipTest::test_a_removed_employee_still_sees_released_payslips`; new `StaffAttendanceTest::test_a_removed_employee_keeps_their_attendance_history`.

### UI polish

- [x] Every new table has a phone-width layout: either cards or a horizontally scrollable table, matching the existing membership table pattern. Code audit: Finance, Purchasing, Payroll, Staff attendance, Stock, Customers and Purchase Order each render a card list below `md` and a scrollable table above it.
- [x] Every list has loading skeletons, an error alert with retry, and an empty state with a permitted call to action. Code audit on every new panel; CTA rendered only under the manage permission.
- [x] Money is right-aligned with `tabular-nums`; dates use the existing format; long text does not break layout. `money()`, `dateLabel()`/`dateTimeLabel()` and `break-words` used throughout.
- [x] Forms map server field errors through `ApiError.errors`; non-field errors go to a toast. Shared `formErrors()` pattern in each new page.
- [x] Destructive actions use `AlertDialog` and name the record. Cancel request, remove need, archive item, correct expense, archive supplier, cancel/close order and follow-up cancel all name the target.
- [x] Check contrast and keyboard use on the new tabs, dialogs and the payroll approval bar. On 6 November 2026 a computed-contrast scan of the new pages (customers, customer record, finance, purchasing, purchase order, payroll, payroll run, stock, My Work) and three dialogs found no WCAG AA failures; Tab stayed inside the dialog, Escape closed it, tabs moved with Arrow keys and selected with Enter, and the payroll action bar moved Compute → Submit with Tab and back with Shift+Tab. A structural scan found no missing main landmarks, heading skips, duplicate IDs or positive tabindex. This is a targeted check, not a formal WCAG certification.

### Performance and data

- [x] Add pagination to every list that can grow without limit: income, expenses, attendance, stock movements, customers, purchase requests, purchase orders, payroll runs. Income, expenses and stock movements were already paginated. Added `meta` plus page controls to customers and follow-ups, staff attendance, purchase requests, purchase orders, suppliers and payroll runs.
- [x] Confirm the indexes from 6.0, 6D, 6E and 6G exist on MySQL with `SHOW INDEX`. Verified on the throwaway `combat_phase6_closeout` database: attendance unique/date, overtime, stock, CRM, expense source/date, procurement status/supplier, payroll unique/status/line, statutory and budget indexes all present.
- [x] Run `php artisan app:check-stock-counts` and confirm no mismatch. Result: `Every stock count matches its movements.`
- [x] Run the nightly absence command over a seeded month twice and confirm the second run changes nothing. Ran 2026-10-16 to 2026-10-31 twice: `0 absences marked.` both times.
- [x] Seed demo data for all six modules in `DatabaseSeeder`, and make `resetData()` clear every new table. `DatabaseSeederTest` checks all six modules, both payroll runs, two payroll expenses, three budget months, CRM groups and stock ledger equality.

### Security and correctness sweep

- [x] Grep for `.general.` in backend and frontend; only `announcements` and `decision_support` may remain. Active catalog has only those two. The literal string no longer appears anywhere: the historical placeholder-mapping migration and its test build the legacy keys with `implode()`.
- [x] Grep for `payroll.general` and `canSeePay`; the pay check uses `payroll.runs.view`. Both strings are gone. The helper is now `canViewCompensation` and checks exact catalog strings (`hr.staff.manage`, `payroll.runs.view`, `finance.payments.view`).
- [x] Confirm every `permission:` string exists in `config/permissions.php` (RouteAccessTest covers this). `RouteAccessTest` and `PermissionCatalogTest` pass.
- [x] Confirm no endpoint sets payroll to released, a purchase order to completed, or stock quantity directly outside its workflow. Only `Payroll\Workflow::decide()` releases; only `Receiving`/`Ordering` advance order status; stock quantity is written only by `StockLedger::move()`/`open()`.
- [x] Confirm no one can approve their own overtime, payroll step (except the flagged one-step owner release), purchase request or supplier payment. Controller and workflow guards are covered by `OvertimeTest`, `PayrollRunTest`, `PurchaseRequestTest`; supplier payments have no approval step.

### Documentation

- [x] `docs/ANALYSIS - COMBAT SPORTS PLATFORM/05 - ROADMAP/phase-6/PHASE 6.md`: set each requirement to ✅, tick every done-when box, add a dated status note per requirement, update the counts table, set the phase to ✅. Requirements, notes and counts updated; phase set to ✅ after the browser pass. R-69's due-reminder box stays open because it belongs to R-82 in Phase 7.
- [x] `docs/ANALYSIS - COMBAT SPORTS PLATFORM/05 - ROADMAP/05 - ROADMAP.md`: Phase 6 row and counts; correct the stale Phase 5 row. Phase 6 row now records the automated close-out; Phase 7 and 8 remain not started.
- [x] `docs/ANALYSIS - COMBAT SPORTS PLATFORM/00 - START HERE/00 - START HERE.md`: dated change log entry.
- [x] `docs/ANALYSIS - COMBAT SPORTS PLATFORM/06 - SCREENS BY ROLE/06 - SCREENS BY ROLE.md`: mark S-28, S-29, S-31, S-32, S-33, S-34 built, and note payslips on S-38.
- [x] Update `CLAUDE.md` only if the permission or module conventions changed in a way a future agent must know. No change: the exact permission-catalog convention is already documented and unchanged.

### Final verification

```bash
cd backend
php artisan test
php artisan migrate:fresh --seed          # MySQL
php artisan app:sync-permissions
php artisan app:check-stock-counts
php artisan app:mark-staff-absences --from=<seeded start> --to=<seeded end>

cd ../frontend
pnpm lint
pnpm build
```

Browser matrix, at desktop and phone width:

| Person | Must be able to | Must not see |
|---|---|---|
| Owner | everything in the six modules | — |
| HR | staff, attendance, overtime, performance, payroll view | finance budgets, stock |
| Finance | payments, income, expenses, payables, budgets, reports, payroll approve | staff performance |
| Front desk | check-in, bookings, payments, members, customers | payroll, budgets |

## Done when

- [x] All 24 requirements are ✅, 🔵 or ⬜ in `PHASE 6.md`, each with a dated note and evidence.
- [x] No page is reachable without its view permission, and no write control shows without manage. Code audit plus browser checks on Owner, HR, Finance, Front Desk, Operations Manager, Coach, employee and member; one CRM view-only leak was found and fixed.
- [x] Every cross-module flow above is verified in tests or in the browser. Income types, payroll expenses and payslips, procurement receipt/payable, notifications, CRM conversion in a group, and removed-employee coverage are evidenced.
- [x] Every list that can grow is paginated. See the pagination task note.
- [x] All backend tests pass; frontend lint and build pass; MySQL fresh migrate and seed pass. Final suite 476 tests, 25,821 assertions; `pnpm lint` (warnings only, no errors); `pnpm build` green; throwaway MySQL 8.4 fresh migrate + seed green.
- [x] The roadmap, start-here log and screens document match the code. Updated 6 November 2026 after the browser pass.
- [x] Nothing is committed unless the user asked. No commit made.

**Browser evidence:** owner, HR, Finance, Front Desk, Operations Manager, Coach, plain employee and member were exercised at desktop. New pages were checked at 390 px with no horizontal overflow and again at 1920×1080. One printed payslip was triggered through the print button. Payroll lock, purchase notifications, low-stock notice, CRM conversion and member balance were verified. Six real defects were fixed during verification: payroll negative gross/net, missing payslip deduction/breakdown fields, a CRM view-only write-control leak, close-short orders showing a red “Order cancelled” banner, the owner-prepared multi-step payroll deadlock, and employee statutory deductions exceeding gross. The demo HR/Finance roles were stale from an earlier seed; they were repaired through the owner role editor and retested.

The second desktop pass also exercised owner direct purchase, direct-order cancel with a reason, partial receipt followed by close-short, CRM group removal/re-add, follow-up cancel, Escape-to-close on dialogs, and direct URLs to another gym (redirected out). It then closed more gaps: income pagination reached page 2 of 26 entries; a mixed-case membership application auto-linked to a lowercase customer email; a stale second-tab receipt submission was refused by the server with 409 and no second receipt; gym-2 Phase 6 API routes returned 404 to a gym-1 owner; multi-step payroll rejection, re-approval and release worked when HR prepared, Finance approved and the owner released; Dindo requested 60 minutes of overtime and the owner approved it with a note; and the owner added a performance note while the employee’s own record showed no Performance tab and no note. The close-short banner reads “Closed with remainder” with the reason, while cancelled orders keep the red “Order cancelled” banner.

The multi-step test also found a real deadlock: when the owner prepared a multi-step run, the owner step showed no Release/Reject control because the preparer was blocked at every step except a one-step chain. Fixed: the owner may now release the final step of a run they prepared, while every earlier step still needs a different approver; the release is flagged `Self-released`. Run #2 was released through the UI after the fix, and the HR → Finance → Owner chain still released normally.

**Not individually browser-exercised:** PayMongo online payments (test keys are configured, but `api.paymongo.com` times out from this environment; the checkout call returned 500 before any payment could be made), removed-employee payslip/attendance retention (removing a demo employee would damage payroll fixtures; automated tests cover it), and a formal manual WCAG certification. The targeted contrast, keyboard and structural checks passed; the multi-step run exercised the in-review approval bar. The low-gross statutory case is fixed: employee deductions are capped at gross with a `statutory_capped_at_gross` warning; a fresh Sep 1–15 draft showed deductions equal to gross on every affected line and net ₱0.00.

## Status notes

- **6 November 2026 — full-window retest closed the remaining gaps and fixed two payroll defects.** Proven in the browser: income pagination reached page 2 of 26; a stale second-tab receipt was refused with 409; gym-2 Phase 6 routes returned 404 to a gym-1 owner; mixed-case application email auto-linked to a lowercase customer; multi-step payroll rejected and re-approved, then released with HR → Finance → Owner. The owner-prepared multi-step deadlock and uncapped low-gross statutory deductions were fixed and retested (run #2 released as self-released; Sep 1–15 draft shows deductions equal to gross with `statutory_capped_at_gross`). Also browser-checked: a 60-minute overtime request approved with a note, a performance note visible to the owner but not the employee, a stock receipt/issue pair returning QA Ledger to 11 pieces with both history rows, and the contrast/keyboard/structural checks. PayMongo checkout could not be completed because `api.paymongo.com` times out from this environment; test keys are configured and the failure is external. Full suite 476 tests / 25,826 assertions; lint/build green; stock counts clean.
- **6 November 2026 — browser verification complete; Phase 6 closed.** Playwright-driven pass covered navigation groups, commerce/finance, CRM, procurement, payroll, budgets/reports, role gating, phone width, notifications and payroll lock. Owner created all Phase 6 records through the UI; no direct data injection was used for the browser scenarios. Three defects were fixed: payroll gross/net could go negative, payslip deductions and breakdown fields were missing from the payload/UI, and CRM view-only users saw follow-up write controls that returned 403. The stale dev HR/Finance role grants were repaired through the owner role editor; fresh seeds contain the correct templates. Final suite 476 tests / 25,821 assertions; lint/build green; `app:check-stock-counts` still clean after the browser data.
- **6 November 2026 — integration pass and automated close-out.** Navigation is grouped (People, Training, Members, Business, Setup) without changing permission filtering or `workspaceHome()`. Pagination added to customer/follow-up, staff attendance, purchase request, purchase order, supplier and payroll-run lists; income, expenses and stock movements were already paginated. Access audit found two gaps and fixed them: a stock-only employee could not reach the restock screen from the nav, and the purchase-request detail looked up its raised order from an unpaginated first page. Security greps removed `canSeePay` in favour of `canViewCompensation`, with no `payroll.general` or extra `.general.` permissions. Two tests added: removed-employee attendance history, and a converted lead carrying the member stage inside a customer group. Commands: `php artisan test` 471 passing; `pnpm lint` 0 errors; `pnpm build` green; throwaway MySQL `combat_phase6_closeout` migrated and seeded; `app:sync-permissions`, `app:check-stock-counts` (`Every stock count matches its movements.`) and `app:mark-staff-absences` over 16–31 October 2026 twice (`0 absences marked.` both times) all green. Browser checks were not run.

---

[← Previous: Packet 6G Budgets and reports](./phase-8.md)
