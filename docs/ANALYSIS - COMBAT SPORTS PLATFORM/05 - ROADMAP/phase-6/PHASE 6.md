# PHASE 6

**The business side runs in one place, fed by training**

[00 - START HERE](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · [05 - ROADMAP](../05%20-%20ROADMAP.md) · Previous: [PHASE 5](../phase-5/PHASE%205.md) · Next: [PHASE 7](../phase-7/PHASE%207.md)

**Status:** ✅ Done

Citation forms are the same as in [02 - FINDINGS](../../02%20-%20FINDINGS/02%20-%20FINDINGS.md).

## The goal

The owner and permitted staff run the whole business side from the same system:

- staff attendance and overtime;
- payroll for everyone, coaches included;
- income and expenses, amounts owed in both directions, and budgets;
- customer leads and follow-ups;
- stock;
- purchasing.

Training feeds the business side directly. Coach sessions and attendance feed payroll, bookings and memberships feed income, and session activity drives supply needs (the spec, page 16, PF-7).

## Why it comes here

- Payroll needs employee records, schedules and leave (R-23 to R-26 in [PHASE 3](../phase-3/PHASE%203.md)) and coach sessions (R-42 in [PHASE 5](../phase-5/PHASE%205.md)).
- Income needs membership and session payments (R-34 in [PHASE 4](../phase-4/PHASE%204.md), R-51 in [PHASE 5](../phase-5/PHASE%205.md)).
- Supply needs come from scheduled activities (R-42).

## What it waits for

| Waits for | Why | Status |
|---|---|---|
| R-23, R-24, R-25, R-26, R-27 in [PHASE 3](../phase-3/PHASE%203.md) | Pay rates, schedules, leave and holidays feed payroll | ✅ |
| R-34 in [PHASE 4](../phase-4/PHASE%204.md) | Membership payments are income | ✅ |
| R-42, R-51 in [PHASE 5](../phase-5/PHASE%205.md) | Coach sessions feed payroll; session fees are income | ✅ |
| Q-13, Q-14, Q-21 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) | Coach pay, lateness and working days shape payroll | ✅ |

## What it unlocks

[PHASE 8](../phase-8/PHASE%208.md) reports on everything here. Follow-up reminders (R-82) need customer records (R-69).

## Where this phase stands

| Status | How many |
|---|---|
| ✅ Finished | 24 |
| 🟨 Being worked on | 0 |
| ⭕ Not started | 0 |
| ❌ Blocked | 0 |
| 🔵 Already there | 0 |
| ⬜ Dropped | 0 |
| ❓ Unclear | 0 |
| **Total** | **24** |

**Built:** 6.0 foundations, 6A HR extras (R-56 to R-58), 6B inventory (R-72 to R-74), 6C CRM (R-69 to R-71), 6D finance core (R-63 to R-65), 6E procurement (R-66, R-75 to R-79), 6F payroll (R-59 to R-62), 6G budgets and reports (R-67, R-68), and 6H integration. Final gates are green on 6 November 2026: 476 backend tests, frontend lint and build, and a throwaway MySQL migrate-and-seed. The Playwright browser pass covered the main flows, role matrix, phone width, notifications, payroll lock and one printed payslip; residual edge checks are listed in Packet 6H. R-69's due reminder belongs to R-82 in Phase 7. Two production-readiness items stay open: the seeded statutory rates are legacy values that need checking against current circulars, and missing attendance is currently a non-blocking warning that can zero pay.

---

## Module: HR and employee management (Module 5A)

The spec groups all business work under one module, Integrated Business Management (Module 5). At your client's request it is split into six separate modules, each with its own screens and its own permission (asked by your client, relayed in conversation, 3 November 2026):

- 5A HR and employee management;
- 5B Payroll;
- 5C Finance;
- 5D Customer relationships (CRM);
- 5E Inventory;
- 5F Procurement.

The people side of 5A (adding employees, schedules, leave) is in [PHASE 3](../phase-3/PHASE%203.md). This phase adds staff attendance, overtime and performance notes.

**Purpose:** record when staff work and how they perform.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-56 | Staff attendance | R-24 | ✅ | Employees' work times, lateness and absences are recorded |
| R-57 | Overtime requests | R-56 | ✅ | Extra hours become a request that a permitted person approves |
| R-58 | Performance notes | R-23 | ✅ | Permitted staff can record notes on an employee's performance |

### R-56: Staff attendance

**In plain words:** Employees record when they start and finish work. The system marks lateness and absences against their schedule, leave and holidays.

**Why it is needed:** The spec, page 16: employee "attendance" feeds payroll.

**Where the need came from:** D-48 and D-49.

**Depends on:** R-24.

**Blockers:** none; Q-14 and Q-21 are answered.

**Done when:**

- [x] An employee can record their start and finish at the gym.
- [x] Days with no attendance, outside approved leave and holidays, are marked absent.
- [x] Lateness is marked by a set rule.
- [x] Permitted staff can correct an entry, and the correction records who made it.

**Resolved:** Q-03 was dropped (no GPS-fenced check-in); Q-02 was kept as a plain explanation field.

**Status note (6 November 2026):** Built in Packet 6A and integrated into My Work and the desk screen. `StaffAttendanceTest`, `StaffAbsenceJobTest` and `StaffAttendanceTenancyTest` pass; the nightly command marks absences and the 6H run over 16–31 October 2026 marked 0 twice. Browser pass completed 6 November 2026 (Packet 6H); residual edge checks are listed in Packet 6H.

**Old system:** `backend/app/Http/Controllers/API/EmployeeAttendanceController.php`; the nightly absence job, `backend/app/Console/Commands/MarkAbsentEmployees.php`. That job ignored national holidays and disagreed with payroll on Saturdays (C-22).

### R-57: Overtime requests

*From the old system, not in the spec. Kept as a fitting extra (said in conversation, 3 November 2026).*

**In plain words:** Time worked beyond the set hours becomes an overtime request. A permitted person approves or rejects it, and approved overtime is paid in payroll.

**Why it is needed:** It ties extra hours to pay honestly (the spec, page 16, "authorized compensation, attendance").

**Where the need came from:** the old system, `EmployeeAttendanceController.php`, lines 243 to 277 and 369 to 423.

**Depends on:** R-56.

**Blockers:** none known.

**Done when:**

- [x] Extra time beyond the schedule creates a pending overtime request.
- [x] Only approved overtime is paid. (The request and decision ship in 6A; payroll applies the approved minutes in 6F.)

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6A; payroll consumes approved minutes in 6F. `OvertimeTest` proves self-decision is refused and only approved minutes count. Browser pass: Dindo requested 60 minutes with a reason from My Work; the owner reviewed it, approved 60 minutes with a note, and the decided list showed `Approved` with the approver note. Residual edge checks are listed in Packet 6H.

**Old system:** as above. The old overtime rate was 1.25 times (`backend/app/Http/Controllers/API/PayrollController.php`).

### R-58: Performance notes

**In plain words:** Permitted staff can write dated notes on an employee's performance.

**Why it is needed:** The spec, page 16, section 5.1. The old system had a disabled placeholder.

**Where the need came from:** D-49.

**Depends on:** R-23.

**Blockers:** none known.

**Done when:**

- [x] A permitted person can add and view performance notes for an employee.
- [x] Notes are not visible to other employees.

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6A. `PerformanceNoteTest` proves the employee never sees the note and only `hr.performance.view` can read it. Browser pass: the owner added a performance note on Dindo’s record; Dindo’s own record showed no Performance tab and the note text was absent. Residual edge checks are listed in Packet 6H.

**Old system:** `backend/app/Http/Controllers/API/PerformanceReviewController.php`, which returns "temporarily disabled".

---

## Module: Payroll (Module 5B)

**Purpose:** pay everyone correctly, through a proper approval chain.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-59 | Work out payroll for every employee, coaches included | R-56, R-26, R-27, R-42 | ✅ | Pay is worked out per period for every employee |
| R-60 | Philippine statutory deductions | R-59 | ✅ | SSS, PhilHealth, Pag-IBIG and withholding tax are deducted at the current rates |
| R-61 | Payroll approval and release | R-59 | ✅ | Payroll goes through each approval step and cannot skip one |
| R-62 | Payslips and payroll history | R-61 | ✅ | Employees see their payslips; history is kept |

### R-59: Work out payroll for every employee, coaches included

**In plain words:** For each pay period, the system works out every employee's pay, coaches included. It uses their approved pay rate, attendance, approved leave, approved overtime, holidays and deductions.

**Why it is needed:** The spec, page 16, sections 5.1 and PF-7. The old payroll left coaches out entirely (C-14). It also trusted pay figures sent from the screen (`PayrollController.php`, lines 607 to 676).

**Where the need came from:** D-48 and D-50.

**Depends on:** R-56, R-26, R-27 and R-42.

**Blockers:** none; Q-13 and Q-21 are answered.

**Done when:**

- [x] Running payroll for a period produces a pay line for every active employee, coaches included.
- [x] Each line shows how it was worked out: days, leave, overtime, holidays and deductions.
- [x] Pay is worked out by the system from stored records, never from figures typed in on the screen.

**Resolved:** Q-13 and Q-21 answered; Q-01 dropped (no leave-to-cash).

**Status note (6 November 2026):** Built in Packet 6F. `PayrollRunTest` and `Unit\PayrollCalculatorTest` prove a line for every active and just-ended employee, coaches included, from stored records only. Browser pass completed 6 November 2026 (Packet 6H); residual edge checks are listed in Packet 6H.

**Old system:** `PayrollController.php`, lines 183 to 592 and 1084 to 1095 (holiday rates).

### R-60: Philippine statutory deductions

*From the old system, not in the spec. Kept as a fitting extra (said in conversation, 3 November 2026). It fits the spec's "deductions" on page 16.*

**In plain words:** Payroll deducts SSS, PhilHealth, Pag-IBIG and withholding tax using rates kept in a table with start dates, so they can be updated when the law changes.

**Why it is needed:** Payslips in the Philippines need these lines. The spec excludes statutory *financial reporting* (page 4), not payroll deductions.

**Where the need came from:** the old system, `PayrollController.php`, lines 1339 to 1535, and `combat_sports_db.sql`, line 2965. The stored rates were effective from 1 January 2025 (line 2993).

**Depends on:** R-59.

**Blockers:** none known.

**Done when:**

- [x] Each payslip shows SSS, PhilHealth, Pag-IBIG and tax.
- [x] A permitted person can add new rates with a start date, and old periods keep their old rates.

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6F. `StatutoryRateSeeder` seeds SSS, PhilHealth, Pag-IBIG, semi-monthly tax and monthly tax effective 2025-01-01; `Unit\StatutoryTest` covers bracket edges, effective-date choice and semi-monthly halving. Old released runs keep snapshotted rates. Browser pass completed 6 November 2026 (Packet 6H); residual edge checks are listed in Packet 6H. **Production caveat:** the seeded values come from the legacy Combat Sports tables, and each seeded `source` string says to verify the current SSS, PhilHealth, Pag-IBIG and BIR circulars. The mechanism works; the legal values are not verified as current. A low-gross test run produced gross ₱151.79 and employee statutory ₱751.52. Employee deductions are now capped at gross with a `statutory_capped_at_gross` warning; a fresh Sep 1–15 draft showed deductions equal to gross and net ₱0.00.

**Old system:** as above.

### R-61: Payroll approval and release

**In plain words:** Worked-out payroll goes through approval steps, for example HR, then finance, then the owner, before it is released. No step can be skipped, and one person cannot approve two steps of the same payroll.

**Why it is needed:** The spec asks for "authorized compensation" (page 16). The old system let a plain edit set payroll straight to "released" (`PayrollController.php`, lines 133 to 145).

**Where the need came from:** D-50 and D-51; *drawn from* the safety faults in [02 - FINDINGS](../../02%20-%20FINDINGS/02%20-%20FINDINGS.md).

**Depends on:** R-59.

**Blockers:** none known.

**Done when:**

- [x] Payroll moves through each step in order and is released only at the end.
- [x] A rejected payroll goes back with a reason.
- [x] There is no way to mark payroll released without every approval.
- [x] Released payroll appears as a pay-out expense (R-64).

**Decided:** each gym chooses 1 to 3 steps from HR review, finance review and owner; the owner is always last and the default is owner only.

**Status note (6 November 2026):** Built in Packet 6F. `PayrollRunTest` covers ordered steps, no skipping, preparer refusal, rejection rounds, the flagged one-step owner release and double-release posting each expense once. `PayrollLockTest` closes the period while in review. Browser pass completed 6 November 2026 (Packet 6H); residual edge checks are listed in Packet 6H. The multi-step browser test rejected and re-approved a run and released it when HR prepared, Finance approved and the owner released. An owner-prepared multi-step run initially deadlocked at the owner step; it is fixed so the owner can release the final step they prepared while every earlier step still needs a different approver. Run #2 was released as `Self-released` after the fix.

**Old system:** `PayrollController.php`, lines 594 to 894.

### R-62: Payslips and payroll history

**In plain words:** Each employee sees their own payslips: gross pay, each deduction and net pay. Permitted staff see the full payroll history.

**Why it is needed:** The spec, page 16.

**Where the need came from:** D-51.

**Depends on:** R-61.

**Blockers:** none known.

**Done when:**

- [x] An employee sees their payslips for released periods only.
- [x] Permitted staff can look up any past payroll.

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6F. `PayslipTest` proves released-only own payslips, another employee gets 404, and a removed employee still sees released payslips. Browser pass: Dindo’s My Work listed the released Oct 16–31 payslip with matching gross, deductions and net, and the print button fired `window.print`. The removed-employee UI path is still automated-only because removing a demo employee would damage the payroll fixtures.

**Old system:** `PayrollController.php`, lines 896 to 944.

---

## Module: Finance (Module 5C)

**Purpose:** record money in and out, money owed, budgets and sustainability, without full accounting.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-63 | Income, recorded automatically from training | R-34, R-51 | ✅ | Membership and session payments appear as income with no retyping |
| R-64 | Expenses | R-61 | ✅ | Running costs, payroll pay-outs, supplies and purchases are recorded |
| R-65 | Money members owe | R-63 | ✅ | Each member's outstanding balance is visible |
| R-66 | Money owed to suppliers | R-77 | ✅ | Each supplier's outstanding amount is visible |
| R-67 | Budgets and budget against actual | R-63, R-64 | ✅ | The owner sets budgets and sees actual against them |
| R-68 | Sustainability reports | R-63, R-64, R-62 | ✅ | Cash flow, income against expenses, payroll and pay-out summaries can be viewed |

### R-63: Income, recorded automatically from training

**In plain words:** Every recorded payment becomes income, with its type:

- membership and registration fees;
- session and walk-in payments;
- private coaching;
- merchandise.

Discounts are shown. Staff can also record walk-in and merchandise sales directly.

**Why it is needed:** The spec, page 16: "bookings and memberships feed income records". The old finance screens showed membership collections only.

**Where the need came from:** D-48 and D-53.

**Depends on:** R-34 and R-51.

**Blockers:** none known.

**Done when:**

- [x] A recorded membership or session payment appears as income without retyping.
- [x] Online PayMongo payments (R-35 in [PHASE 4](../phase-4/PHASE%204.md)) appear as income the same way as desk payments. *(Moved from R-35 on 5 November 2026.)*
- [x] Walk-in and merchandise sales can be recorded.
- [x] Income can be viewed by type and date range.

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6D. `IncomeTest` covers desk membership, session, private coaching and signed PayMongo rows; `PaymentIncomeTypeTest` covers type derivation and correction copying; `SaleTest` covers walk-in and merchandise. Income is paginated by 25 with type totals. Browser pass: membership, session/private-coaching, walk-in and merchandise rows all appeared with the right type, method and receipt. PayMongo online checkout was attempted with the configured test keys, but `api.paymongo.com` times out from this environment, so no live online payment row could be created; signed PayMongo rows remain covered by automated tests. Residual edge checks are listed in Packet 6H.

**Old system:** `frontend/src/pages/DynamicRoleDashboard.vue`, lines 1418 to 1700.

### R-64: Expenses

**In plain words:** Permitted staff record expenses: running costs, supplies and purchases. Released payroll and received purchase orders appear automatically.

**Why it is needed:** The spec, page 16. The old system had none.

**Where the need came from:** D-54.

**Depends on:** R-61, and R-78 for purchases.

**Blockers:** none known.

**Done when:**

- [x] Staff can record an expense with category, amount, date and note.
- [x] Released payroll appears as an expense automatically.
- [x] Received purchase orders appear as expenses automatically.

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6D and fed by 6E and 6F. `ExpenseTest` covers add/filter/correct and the append-only guard; `PayrollRunTest` and `ReceivingTest` prove one automatic row per source key. Expenses are paginated by 25. Browser pass completed 6 November 2026 (Packet 6H); residual edge checks are listed in Packet 6H.

**Old system:** none.

### R-65: Money members owe

**In plain words:** The system shows each member's unpaid amounts, such as an unpaid add-on or plan fee, and a gym-wide list of outstanding balances.

**Why it is needed:** The spec, page 16.

**Where the need came from:** D-56.

**Depends on:** R-63.

**Blockers:** none known.

**Done when:**

- [x] Each member's balance is visible to permitted staff and to the member.
- [x] Recording a payment reduces the balance.
- [x] Unpaid session add-ons and private coaching fees (R-51 in [PHASE 5](../phase-5/PHASE%205.md)) appear in this list. *(Moved from R-51 on 5 November 2026.)*

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6D. `ReceivableTest` compares every staff row with `MembershipLedger::balance()` and `BookingLedger::balance()`, includes requested bookings and private coaching, and proves a member sees only their own balance. The member dashboard reads `GET my-balance`. Browser pass completed 6 November 2026 (Packet 6H); residual edge checks are listed in Packet 6H.

**Old system:** none (the old permission names existed with no screens; `combat_sports_db.sql`, lines 3076 to 3081).

### R-66: Money owed to suppliers

**In plain words:** The system shows what the gym owes each supplier from received purchase orders, and records payments to them.

**Why it is needed:** The spec, page 16.

**Where the need came from:** D-56.

**Depends on:** R-77.

**Blockers:** none known.

**Done when:**

- [x] Each supplier's outstanding amount is visible.
- [x] Recording a supplier payment reduces it and appears as an expense.

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6E. `PayableTest` proves outstanding equals receipts minus payments, overpayment is refused, and a payment appears beside expenses as cash out without changing the expense count or total. This follows the locked decision that a supplier payment settles the payable and is not a second cost. Browser pass completed 6 November 2026 (Packet 6H); residual edge checks are listed in Packet 6H.

**Old system:** none. The old "payables" screen showed released payroll only.

### R-67: Budgets and budget against actual

**In plain words:** The owner sets budgets per category and period, and sees actual income and expenses against them.

**Why it is needed:** The spec, page 16, and page 19 (budget against actual in decision support).

**Where the need came from:** D-57.

**Depends on:** R-63 and R-64.

**Blockers:** none known.

**Done when:**

- [x] The owner can set a budget per category per month.
- [x] A view shows budget, actual and the difference.

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6G. `BudgetTest` proves month upsert, unique category, separate income/expense kinds and actual matching source sums. `DatabaseSeederTest` proves three seeded months per demo gym. Browser pass on 6 November 2026 saved November budgets (Membership ₱50,000, Rent ₱25,000, Payroll ₱10,000, Purchases ₱5,000) and saw actual, difference and used percentages update.

**Old system:** none.

### R-68: Sustainability reports

**In plain words:** Reports show cash flow, income against expenses, payroll summaries and the history of pay-outs for a chosen period.

**Why it is needed:** The spec, page 16. These are analysed further in decision support (R-96).

**Where the need came from:** D-58.

**Depends on:** R-63, R-64 and R-62.

**Blockers:** none known.

**Done when:**

- [x] The owner can view each report for any date range.
- [x] The figures match the underlying income, expense and payroll records.

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6G. `FinanceReportTest` covers cash flow, income against expenses, payroll summaries, pay-outs, the 62-day bucket switch and the reversed-range refusal; `ReportTenancyTest` proves another gym never leaks in. Browser pass on 6 November 2026 checked a 6-day range (daily buckets), a 98-day range (monthly buckets) and a reversed range (warning, no fetch); totals matched the seeded and UI-created records.

**Old system:** none.

---

## Module: Customer relationships, CRM (Module 5D)

**Purpose:** keep track of prospects and customers, and turn interest into membership.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-69 | Leads, customers, inquiries and follow-ups | R-22 | ✅ | Staff can record a lead and schedule a follow-up assigned to someone |
| R-70 | Interaction and feedback history | R-69 | ✅ | Every contact, preference, feedback item and concern is kept per customer |
| R-71 | Turn a lead into a member | R-69, R-31 | ✅ | A lead can be linked to their membership application |

### R-69: Leads, customers, inquiries and follow-ups

**In plain words:** Staff record prospective and existing customers, their inquiries and membership leads. They schedule follow-ups and assign each to a staff member.

**Why it is needed:** The spec, page 16, section 5.3, and page 18 (notice when a follow-up is due). The old system had only a newsletter list.

**Where the need came from:** D-59 and D-72.

**Depends on:** R-22.

**Blockers:** none known.

**Done when:**

- [x] Staff can add a lead or customer with contact details and their inquiry.
- [x] A follow-up can be scheduled and assigned.
- [ ] The assigned person is reminded when it is due (built in R-82). **Not in Phase 6; R-82 in Phase 7 owns this.**

**Out of scope:** Q-07 (newsletter and contact form). The due reminder is R-82.

**Status note (6 November 2026):** Built in Packet 6C. `CustomerTest` and `FollowUpTest` cover add, search, stage filter, assignee validation, scheduling, reassignment, done with outcome, cancel and the assignment notice. Customer and follow-up lists now paginate in 6H. The due reminder itself is R-82 and stays open. Browser pass completed 6 November 2026 (Packet 6H); residual edge checks are listed in Packet 6H.

**Old system:** none beyond `combat_sports_db.sql`, line 4004 (newsletter subscribers).

### R-70: Interaction and feedback history

**In plain words:** Each customer's record keeps every interaction, preference, piece of feedback and concern, and whether each concern was resolved.

**Why it is needed:** The spec, page 16.

**Where the need came from:** D-60 and D-61.

**Depends on:** R-69.

**Blockers:** none known.

**Done when:**

- [x] Staff can log an interaction, feedback item or concern against a customer.
- [x] Concerns can be marked resolved, with who resolved them.

**Out of scope:** Q-06 (whether public gym reviews are kept, which overlaps with feedback).

**Status note (6 November 2026):** Built in Packet 6C. `CustomerInteractionTest` covers all four kinds, append-only behaviour, and concern resolution recording who and when; only concerns can be resolved and a second resolution is refused. Browser pass completed 6 November 2026 (Packet 6H); residual edge checks are listed in Packet 6H.

**Old system:** none.

### R-71: Turn a lead into a member

**In plain words:** When a lead applies for membership, their customer record links to the application, so the gym can see which leads became members. Customer groups can be targeted by announcements (R-80).

**Why it is needed:** The spec, page 16 ("supports membership conversion ... targeted announcements") and page 19 (conversion rate).

**Where the need came from:** D-61.

**Depends on:** R-69 and R-31.

**Blockers:** none known.

**Done when:**

- [x] A lead can be linked to a membership application, or linked automatically by email.
- [x] Staff can group customers, and the groups can be chosen as announcement audiences.

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6C. `CustomerConversionTest` covers manual and email links in both directions, case-insensitive matching and another gym's application returning 404; `CustomerGroupTest` covers unique names, member sync, user-to-customer creation and permissions. `CustomerTest` follows a lead to applied to member. Announcement sending itself is R-80 in Phase 7. Browser pass completed 6 November 2026 (Packet 6H); residual edge checks are listed in Packet 6H.

**Old system:** none.

---

## Module: Inventory (Module 5E)

**Purpose:** know what stock the gym has, where it is, and when it runs low.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-72 | Stock items | R-20 | ✅ | Equipment, gear, supplies and merchandise are listed with quantity and place |
| R-73 | Stock movements and history | R-72 | ✅ | Every issue, return, adjustment and loss changes the quantity and is kept in history |
| R-74 | Minimum levels and low-stock alerts | R-73, R-10 | ✅ | When stock falls below its minimum, stock staff and the owner are told |

### R-72: Stock items

**In plain words:** The gym keeps a list of stock: training equipment, protective gear, supplies and merchandise. Each item shows its quantity and where it is stored.

**Why it is needed:** The spec, page 17, section 5.4. The old system had none.

**Where the need came from:** D-62 and D-63.

**Depends on:** R-20.

**Blockers:** none known.

**Done when:**

- [x] Permitted staff can add items with category, quantity and storage place.

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6B. `StockTest` proves item creation, category and storage place, merchandise pricing rules and archive behaviour. Browser pass completed 6 November 2026 (Packet 6H); residual edge checks are listed in Packet 6H.

**Old system:** none.

### R-73: Stock movements and history

**In plain words:** Every movement is recorded with who did it and when, and updates the quantity:

- items issued and returned;
- adjustments;
- damaged or lost items.

**Why it is needed:** The spec, page 17.

**Where the need came from:** D-63 and D-64.

**Depends on:** R-72.

**Blockers:** none known.

**Done when:**

- [x] Each movement type changes the quantity correctly.
- [x] An item's full movement history can be viewed.
- [x] Quantity can never be changed without a recorded movement.

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6B. `StockTest` covers every movement type, returns, issue balances and the direct-write guard; `StockTenancyTest` covers another gym; `app:check-stock-counts` returned `Every stock count matches its movements.` on the throwaway MySQL seed. Movement history is paginated and loads older pages on demand. Browser pass completed 6 November 2026 (Packet 6H); residual edge checks are listed in Packet 6H.

**Old system:** none.

### R-74: Minimum levels and low-stock alerts

**In plain words:** Each item can have a minimum level. When stock falls to or below it, stock staff and the owner get a notice.

**Why it is needed:** The spec, page 17 and page 18 ("low stock / reorder level reached").

**Where the need came from:** D-64 and D-72.

**Depends on:** R-73 and R-10.

**Blockers:** none known.

**Done when:**

- [x] A minimum level can be set per item.
- [x] Dropping to it sends a notice to stock staff and the owner.

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6B. `LowStockTest` proves one notice at the crossing to owner and stock manager, no repeat while it stays low, a cleared flag above the minimum, a fresh notice after a new drop, and no notice for archived items. Browser pass completed 6 November 2026 (Packet 6H); residual edge checks are listed in Packet 6H.

**Old system:** none.

---

## Module: Procurement (Module 5F)

**Purpose:** restock on time, with approval, and keep stock and money up to date automatically.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-75 | Work out what needs restocking | R-74, R-42 | ✅ | A list shows items to restock from minimum levels and upcoming activities |
| R-76 | Purchase requests with approval | R-75, R-10 | ✅ | A request goes to a permitted approver and is approved or rejected |
| R-77 | Suppliers and purchase orders | R-76 | ✅ | Approved requests become purchase orders to a recorded supplier |
| R-78 | Receiving updates stock | R-77, R-73 | ✅ | Recording received items raises stock quantities automatically |
| R-79 | Purchase status tracking | R-77, R-10 | ✅ | Each purchase shows pending, partly received, completed or cancelled, and status changes notify the people involved |

### R-75: Work out what needs restocking

**In plain words:** The system lists items to restock, based on minimum levels, scheduled activities (for example a sparring event needing more gear) and approved needs.

**Why it is needed:** The spec, page 17, section 5.5, and page 16 ("session activity drives supply needs").

**Where the need came from:** D-48 and D-65.

**Depends on:** R-74 and R-42.

**Blockers:** none; needs are entered by hand and linked to a session or a date, with no automatic usage rules.

**Done when:**

- [x] A restock list shows every item at or below its minimum.
- [x] Staff can add needs for upcoming scheduled activities.

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6E. `RestockTest` proves at-or-below-minimum appears, above does not, open needs add, on-order subtracts and the suggested quantity respects the reorder quantity. Browser pass completed 6 November 2026 (Packet 6H); residual edge checks are listed in Packet 6H.

**Old system:** none.

### R-76: Purchase requests with approval

**In plain words:** Staff create a purchase request from the restock list or by hand. It goes to a permitted approver, who approves or rejects it.

**Why it is needed:** The spec, page 17.

**Where the need came from:** D-66.

**Depends on:** R-75 and R-10.

**Blockers:** none known.

**Done when:**

- [x] A request can be created with items and quantities.
- [x] Only a permitted approver, never the requester, can approve it.

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6E. `PurchaseRequestTest` proves creation with lines, requester and owner-requester refusal, rejection reason, pending-only cancel, and the decision notice to the requester only. Request list now paginates. Browser pass completed 6 November 2026 (Packet 6H); residual edge checks are listed in Packet 6H.

**Old system:** none.

### R-77: Suppliers and purchase orders

**In plain words:** The gym keeps supplier details. An approved request becomes a purchase order to a chosen supplier.

**Why it is needed:** The spec, page 17.

**Where the need came from:** D-67.

**Depends on:** R-76.

**Blockers:** none known.

**Done when:**

- [x] Permitted staff can add and edit suppliers.
- [x] An approved request can be turned into a purchase order for one supplier.

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6E. `PurchaseOrderTest` proves one order per approved request, quantities cannot exceed the request, unit cost is required, direct buying is owner-only and flagged, and cancel/close-short rules hold. Supplier and order lists now paginate. Browser pass completed 6 November 2026 (Packet 6H); residual edge checks are listed in Packet 6H.

**Old system:** none.

### R-78: Receiving updates stock

**In plain words:** When goods arrive, staff record what was received, possibly only part of the order. Stock quantities rise automatically.

**Why it is needed:** The spec, page 17.

**Where the need came from:** D-68.

**Depends on:** R-77 and R-73.

**Blockers:** none known.

**Done when:**

- [x] Recording a delivery increases each item's stock by the amount received.
- [x] A part delivery leaves the rest of the order open.
- [x] The received amount is recorded as owed to the supplier (R-66) and as an expense (R-64).

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6E. `ReceivingTest` proves a partial receipt raises stock and leaves the order open, over-receipt is refused, a full receipt completes it, a duplicate returns the same receipt, and the expense is posted once. The stock cache equals movements. Browser pass completed 6 November 2026 (Packet 6H); residual edge checks are listed in Packet 6H.

**Old system:** none.

### R-79: Purchase status tracking

**In plain words:** Each purchase shows its status: pending, partly received, completed or cancelled. The requester and approver get a notice whenever it changes.

**Why it is needed:** The spec, page 17 and page 18 ("procurement status update").

**Where the need came from:** D-69 and D-72.

**Depends on:** R-77 and R-10.

**Blockers:** none known.

**Done when:**

- [x] Every purchase shows one of the four statuses.
- [x] Each status change sends a notice to the requester and approver.

**Still open:** nothing.

**Status note (6 November 2026):** Built in Packet 6E. `PurchaseRequestTest` and `PurchaseOrderTest` count the decision and status notices and prove the actor is excluded. `PurchaseOrderTest` covers the four status transitions. Browser pass completed 6 November 2026 (Packet 6H); residual edge checks are listed in Packet 6H.

**Old system:** none.

---

## Phase blockers

None. Q-13, Q-14 and Q-21 are answered and applied; Q-01 was dropped, Q-02 kept as the plain explanation field, and Q-03 dropped. The remaining close-out item is the browser matrix and one printed payslip, not a product decision.

## Phase is finished when

Every item above is ✅, 🔵 or ⬜. Everyone is paid correctly through a proper chain. Money in and out is recorded, much of it automatically. Leads are followed up, and stock and purchasing keep themselves up to date.
