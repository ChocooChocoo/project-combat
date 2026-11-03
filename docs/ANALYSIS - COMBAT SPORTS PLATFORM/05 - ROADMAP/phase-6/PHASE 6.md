# PHASE 6

**The business side runs in one place, fed by training**

[00 - START HERE](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · [05 - ROADMAP](../05%20-%20ROADMAP.md) · Previous: [PHASE 5](../phase-5/PHASE%205.md) · Next: [PHASE 7](../phase-7/PHASE%207.md)

**Status:** ⭕ Not started

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
| R-23, R-24, R-25, R-26, R-27 in [PHASE 3](../phase-3/PHASE%203.md) | Pay rates, schedules, leave and holidays feed payroll | ⭕ |
| R-34 in [PHASE 4](../phase-4/PHASE%204.md) | Membership payments are income | ⭕ |
| R-42, R-51 in [PHASE 5](../phase-5/PHASE%205.md) | Coach sessions feed payroll; session fees are income | ⭕ |
| Q-13, Q-14, Q-21 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) | Coach pay, lateness and working days shape payroll | ⭕ |

## What it unlocks

[PHASE 8](../phase-8/PHASE%208.md) reports on everything here. Follow-up reminders (R-82) need customer records (R-69).

## Where this phase stands

| Status | How many |
|---|---|
| ✅ Finished | 0 |
| 🟨 Being worked on | 0 |
| ⭕ Not started | 24 |
| ❌ Blocked | 0 |
| 🔵 Already there | 0 |
| ⬜ Dropped | 0 |
| ❓ Unclear | 0 |
| **Total** | **24** |

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
| R-56 | Staff attendance | R-24 | ⭕ | Employees' work times, lateness and absences are recorded |
| R-57 | Overtime requests | R-56 | ⭕ | Extra hours become a request that a permitted person approves |
| R-58 | Performance notes | R-23 | ⭕ | Permitted staff can record notes on an employee's performance |

### R-56: Staff attendance

**In plain words:** Employees record when they start and finish work. The system marks lateness and absences against their schedule, leave and holidays.

**Why it is needed:** The spec, page 16: employee "attendance" feeds payroll.

**Where the need came from:** D-48 and D-49.

**Depends on:** R-24.

**Blockers:** Q-14 (lateness) and Q-21 (working days).

**Done when:**

- [ ] An employee can record their start and finish at the gym.
- [ ] Days with no attendance, outside approved leave and holidays, are marked absent.
- [ ] Lateness is marked by a set rule.
- [ ] Permitted staff can correct an entry, and the correction records who made it.

**Still open:** Q-03 (whether to keep GPS-fenced check-in) and Q-02 (whether to keep absence explanations).

**Old system:** `backend/app/Http/Controllers/API/EmployeeAttendanceController.php`; the nightly absence job, `backend/app/Console/Commands/MarkAbsentEmployees.php`. That job ignored national holidays and disagreed with payroll on Saturdays (C-22).

### R-57: Overtime requests

*From the old system, not in the spec. Kept as a fitting extra (said in conversation, 3 November 2026).*

**In plain words:** Time worked beyond the set hours becomes an overtime request. A permitted person approves or rejects it, and approved overtime is paid in payroll.

**Why it is needed:** It ties extra hours to pay honestly (the spec, page 16, "authorized compensation, attendance").

**Where the need came from:** the old system, `EmployeeAttendanceController.php`, lines 243 to 277 and 369 to 423.

**Depends on:** R-56.

**Blockers:** none known.

**Done when:**

- [ ] Extra time beyond the schedule creates a pending overtime request.
- [ ] Only approved overtime is paid.

**Still open:** nothing.

**Old system:** as above. The old overtime rate was 1.25 times (`backend/app/Http/Controllers/API/PayrollController.php`).

### R-58: Performance notes

**In plain words:** Permitted staff can write dated notes on an employee's performance.

**Why it is needed:** The spec, page 16, section 5.1. The old system had a disabled placeholder.

**Where the need came from:** D-49.

**Depends on:** R-23.

**Blockers:** none known.

**Done when:**

- [ ] A permitted person can add and view performance notes for an employee.
- [ ] Notes are not visible to other employees.

**Still open:** nothing.

**Old system:** `backend/app/Http/Controllers/API/PerformanceReviewController.php`, which returns "temporarily disabled".

---

## Module: Payroll (Module 5B)

**Purpose:** pay everyone correctly, through a proper approval chain.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-59 | Work out payroll for every employee, coaches included | R-56, R-26, R-27, R-42 | ⭕ | Pay is worked out per period for every employee |
| R-60 | Philippine statutory deductions | R-59 | ⭕ | SSS, PhilHealth, Pag-IBIG and withholding tax are deducted at the current rates |
| R-61 | Payroll approval and release | R-59 | ⭕ | Payroll goes through each approval step and cannot skip one |
| R-62 | Payslips and payroll history | R-61 | ⭕ | Employees see their payslips; history is kept |

### R-59: Work out payroll for every employee, coaches included

**In plain words:** For each pay period, the system works out every employee's pay, coaches included. It uses their approved pay rate, attendance, approved leave, approved overtime, holidays and deductions.

**Why it is needed:** The spec, page 16, sections 5.1 and PF-7. The old payroll left coaches out entirely (C-14). It also trusted pay figures sent from the screen (`PayrollController.php`, lines 607 to 676).

**Where the need came from:** D-48 and D-50.

**Depends on:** R-56, R-26, R-27 and R-42.

**Blockers:** Q-13 (how coaches are paid) and Q-21.

**Done when:**

- [ ] Running payroll for a period produces a pay line for every active employee, coaches included.
- [ ] Each line shows how it was worked out: days, leave, overtime, holidays and deductions.
- [ ] Pay is worked out by the system from stored records, never from figures typed in on the screen.

**Still open:** Q-13 and Q-01.

**Old system:** `PayrollController.php`, lines 183 to 592 and 1084 to 1095 (holiday rates).

### R-60: Philippine statutory deductions

*From the old system, not in the spec. Kept as a fitting extra (said in conversation, 3 November 2026). It fits the spec's "deductions" on page 16.*

**In plain words:** Payroll deducts SSS, PhilHealth, Pag-IBIG and withholding tax using rates kept in a table with start dates, so they can be updated when the law changes.

**Why it is needed:** Payslips in the Philippines need these lines. The spec excludes statutory *financial reporting* (page 4), not payroll deductions.

**Where the need came from:** the old system, `PayrollController.php`, lines 1339 to 1535, and `combat_sports_db.sql`, line 2965. The stored rates were effective from 1 January 2025 (line 2993).

**Depends on:** R-59.

**Blockers:** none known.

**Done when:**

- [ ] Each payslip shows SSS, PhilHealth, Pag-IBIG and tax.
- [ ] A permitted person can add new rates with a start date, and old periods keep their old rates.

**Still open:** nothing.

**Old system:** as above.

### R-61: Payroll approval and release

**In plain words:** Worked-out payroll goes through approval steps, for example HR, then finance, then the owner, before it is released. No step can be skipped, and one person cannot approve two steps of the same payroll.

**Why it is needed:** The spec asks for "authorized compensation" (page 16). The old system let a plain edit set payroll straight to "released" (`PayrollController.php`, lines 133 to 145).

**Where the need came from:** D-50 and D-51; *drawn from* the safety faults in [02 - FINDINGS](../../02%20-%20FINDINGS/02%20-%20FINDINGS.md).

**Depends on:** R-59.

**Blockers:** none known.

**Done when:**

- [ ] Payroll moves through each step in order and is released only at the end.
- [ ] A rejected payroll goes back with a reason.
- [ ] There is no way to mark payroll released without every approval.
- [ ] Released payroll appears as a pay-out expense (R-64).

**Still open:** which steps a gym needs, since small gyms may have no separate HR or finance person. It should be decided during implementation planning.

**Old system:** `PayrollController.php`, lines 594 to 894.

### R-62: Payslips and payroll history

**In plain words:** Each employee sees their own payslips: gross pay, each deduction and net pay. Permitted staff see the full payroll history.

**Why it is needed:** The spec, page 16.

**Where the need came from:** D-51.

**Depends on:** R-61.

**Blockers:** none known.

**Done when:**

- [ ] An employee sees their payslips for released periods only.
- [ ] Permitted staff can look up any past payroll.

**Still open:** nothing.

**Old system:** `PayrollController.php`, lines 896 to 944.

---

## Module: Finance (Module 5C)

**Purpose:** record money in and out, money owed, budgets and sustainability, without full accounting.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-63 | Income, recorded automatically from training | R-34, R-51 | ⭕ | Membership and session payments appear as income with no retyping |
| R-64 | Expenses | R-61 | ⭕ | Running costs, payroll pay-outs, supplies and purchases are recorded |
| R-65 | Money members owe | R-63 | ⭕ | Each member's outstanding balance is visible |
| R-66 | Money owed to suppliers | R-77 | ⭕ | Each supplier's outstanding amount is visible |
| R-67 | Budgets and budget against actual | R-63, R-64 | ⭕ | The owner sets budgets and sees actual against them |
| R-68 | Sustainability reports | R-63, R-64, R-62 | ⭕ | Cash flow, income against expenses, payroll and pay-out summaries can be viewed |

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

- [ ] A recorded membership or session payment appears as income without retyping.
- [ ] Walk-in and merchandise sales can be recorded.
- [ ] Income can be viewed by type and date range.

**Still open:** nothing.

**Old system:** `frontend/src/pages/DynamicRoleDashboard.vue`, lines 1418 to 1700.

### R-64: Expenses

**In plain words:** Permitted staff record expenses: running costs, supplies and purchases. Released payroll and received purchase orders appear automatically.

**Why it is needed:** The spec, page 16. The old system had none.

**Where the need came from:** D-54.

**Depends on:** R-61, and R-78 for purchases.

**Blockers:** none known.

**Done when:**

- [ ] Staff can record an expense with category, amount, date and note.
- [ ] Released payroll appears as an expense automatically.
- [ ] Received purchase orders appear as expenses automatically.

**Still open:** nothing.

**Old system:** none.

### R-65: Money members owe

**In plain words:** The system shows each member's unpaid amounts, such as an unpaid add-on or plan fee, and a gym-wide list of outstanding balances.

**Why it is needed:** The spec, page 16.

**Where the need came from:** D-56.

**Depends on:** R-63.

**Blockers:** none known.

**Done when:**

- [ ] Each member's balance is visible to permitted staff and to the member.
- [ ] Recording a payment reduces the balance.

**Still open:** nothing.

**Old system:** none (the old permission names existed with no screens; `combat_sports_db.sql`, lines 3076 to 3081).

### R-66: Money owed to suppliers

**In plain words:** The system shows what the gym owes each supplier from received purchase orders, and records payments to them.

**Why it is needed:** The spec, page 16.

**Where the need came from:** D-56.

**Depends on:** R-77.

**Blockers:** none known.

**Done when:**

- [ ] Each supplier's outstanding amount is visible.
- [ ] Recording a supplier payment reduces it and appears as an expense.

**Still open:** nothing.

**Old system:** none. The old "payables" screen showed released payroll only.

### R-67: Budgets and budget against actual

**In plain words:** The owner sets budgets per category and period, and sees actual income and expenses against them.

**Why it is needed:** The spec, page 16, and page 19 (budget against actual in decision support).

**Where the need came from:** D-57.

**Depends on:** R-63 and R-64.

**Blockers:** none known.

**Done when:**

- [ ] The owner can set a budget per category per month.
- [ ] A view shows budget, actual and the difference.

**Still open:** nothing.

**Old system:** none.

### R-68: Sustainability reports

**In plain words:** Reports show cash flow, income against expenses, payroll summaries and the history of pay-outs for a chosen period.

**Why it is needed:** The spec, page 16. These are analysed further in decision support (R-96).

**Where the need came from:** D-58.

**Depends on:** R-63, R-64 and R-62.

**Blockers:** none known.

**Done when:**

- [ ] The owner can view each report for any date range.
- [ ] The figures match the underlying income, expense and payroll records.

**Still open:** nothing.

**Old system:** none.

---

## Module: Customer relationships, CRM (Module 5D)

**Purpose:** keep track of prospects and customers, and turn interest into membership.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-69 | Leads, customers, inquiries and follow-ups | R-22 | ⭕ | Staff can record a lead and schedule a follow-up assigned to someone |
| R-70 | Interaction and feedback history | R-69 | ⭕ | Every contact, preference, feedback item and concern is kept per customer |
| R-71 | Turn a lead into a member | R-69, R-31 | ⭕ | A lead can be linked to their membership application |

### R-69: Leads, customers, inquiries and follow-ups

**In plain words:** Staff record prospective and existing customers, their inquiries and membership leads. They schedule follow-ups and assign each to a staff member.

**Why it is needed:** The spec, page 16, section 5.3, and page 18 (notice when a follow-up is due). The old system had only a newsletter list.

**Where the need came from:** D-59 and D-72.

**Depends on:** R-22.

**Blockers:** none known.

**Done when:**

- [ ] Staff can add a lead or customer with contact details and their inquiry.
- [ ] A follow-up can be scheduled and assigned.
- [ ] The assigned person is reminded when it is due (built in R-82).

**Still open:** Q-07 (newsletter and contact form).

**Old system:** none beyond `combat_sports_db.sql`, line 4004 (newsletter subscribers).

### R-70: Interaction and feedback history

**In plain words:** Each customer's record keeps every interaction, preference, piece of feedback and concern, and whether each concern was resolved.

**Why it is needed:** The spec, page 16.

**Where the need came from:** D-60 and D-61.

**Depends on:** R-69.

**Blockers:** none known.

**Done when:**

- [ ] Staff can log an interaction, feedback item or concern against a customer.
- [ ] Concerns can be marked resolved, with who resolved them.

**Still open:** Q-06 (whether public gym reviews are kept, which overlaps with feedback).

**Old system:** none.

### R-71: Turn a lead into a member

**In plain words:** When a lead applies for membership, their customer record links to the application, so the gym can see which leads became members. Customer groups can be targeted by announcements (R-80).

**Why it is needed:** The spec, page 16 ("supports membership conversion ... targeted announcements") and page 19 (conversion rate).

**Where the need came from:** D-61.

**Depends on:** R-69 and R-31.

**Blockers:** none known.

**Done when:**

- [ ] A lead can be linked to a membership application, or linked automatically by email.
- [ ] Staff can group customers, and the groups can be chosen as announcement audiences.

**Still open:** nothing.

**Old system:** none.

---

## Module: Inventory (Module 5E)

**Purpose:** know what stock the gym has, where it is, and when it runs low.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-72 | Stock items | R-20 | ⭕ | Equipment, gear, supplies and merchandise are listed with quantity and place |
| R-73 | Stock movements and history | R-72 | ⭕ | Every issue, return, adjustment and loss changes the quantity and is kept in history |
| R-74 | Minimum levels and low-stock alerts | R-73, R-10 | ⭕ | When stock falls below its minimum, stock staff and the owner are told |

### R-72: Stock items

**In plain words:** The gym keeps a list of stock: training equipment, protective gear, supplies and merchandise. Each item shows its quantity and where it is stored.

**Why it is needed:** The spec, page 17, section 5.4. The old system had none.

**Where the need came from:** D-62 and D-63.

**Depends on:** R-20.

**Blockers:** none known.

**Done when:**

- [ ] Permitted staff can add items with category, quantity and storage place.

**Still open:** nothing.

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

- [ ] Each movement type changes the quantity correctly.
- [ ] An item's full movement history can be viewed.
- [ ] Quantity can never be changed without a recorded movement.

**Still open:** nothing.

**Old system:** none.

### R-74: Minimum levels and low-stock alerts

**In plain words:** Each item can have a minimum level. When stock falls to or below it, stock staff and the owner get a notice.

**Why it is needed:** The spec, page 17 and page 18 ("low stock / reorder level reached").

**Where the need came from:** D-64 and D-72.

**Depends on:** R-73 and R-10.

**Blockers:** none known.

**Done when:**

- [ ] A minimum level can be set per item.
- [ ] Dropping to it sends a notice to stock staff and the owner.

**Still open:** nothing.

**Old system:** none.

---

## Module: Procurement (Module 5F)

**Purpose:** restock on time, with approval, and keep stock and money up to date automatically.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-75 | Work out what needs restocking | R-74, R-42 | ⭕ | A list shows items to restock from minimum levels and upcoming activities |
| R-76 | Purchase requests with approval | R-75, R-10 | ⭕ | A request goes to a permitted approver and is approved or rejected |
| R-77 | Suppliers and purchase orders | R-76 | ⭕ | Approved requests become purchase orders to a recorded supplier |
| R-78 | Receiving updates stock | R-77, R-73 | ⭕ | Recording received items raises stock quantities automatically |
| R-79 | Purchase status tracking | R-77, R-10 | ⭕ | Each purchase shows pending, partly received, completed or cancelled, and status changes notify the people involved |

### R-75: Work out what needs restocking

**In plain words:** The system lists items to restock, based on minimum levels, scheduled activities (for example a sparring event needing more gear) and approved needs.

**Why it is needed:** The spec, page 17, section 5.5, and page 16 ("session activity drives supply needs").

**Where the need came from:** D-48 and D-65.

**Depends on:** R-74 and R-42.

**Blockers:** how scheduled activities turn into supply needs is not stated (the spec, page 17). It should be decided during implementation planning.

**Done when:**

- [ ] A restock list shows every item at or below its minimum.
- [ ] Staff can add needs for upcoming scheduled activities.

**Still open:** as in blockers.

**Old system:** none.

### R-76: Purchase requests with approval

**In plain words:** Staff create a purchase request from the restock list or by hand. It goes to a permitted approver, who approves or rejects it.

**Why it is needed:** The spec, page 17.

**Where the need came from:** D-66.

**Depends on:** R-75 and R-10.

**Blockers:** none known.

**Done when:**

- [ ] A request can be created with items and quantities.
- [ ] Only a permitted approver, never the requester, can approve it.

**Still open:** nothing.

**Old system:** none.

### R-77: Suppliers and purchase orders

**In plain words:** The gym keeps supplier details. An approved request becomes a purchase order to a chosen supplier.

**Why it is needed:** The spec, page 17.

**Where the need came from:** D-67.

**Depends on:** R-76.

**Blockers:** none known.

**Done when:**

- [ ] Permitted staff can add and edit suppliers.
- [ ] An approved request can be turned into a purchase order for one supplier.

**Still open:** nothing.

**Old system:** none.

### R-78: Receiving updates stock

**In plain words:** When goods arrive, staff record what was received, possibly only part of the order. Stock quantities rise automatically.

**Why it is needed:** The spec, page 17.

**Where the need came from:** D-68.

**Depends on:** R-77 and R-73.

**Blockers:** none known.

**Done when:**

- [ ] Recording a delivery increases each item's stock by the amount received.
- [ ] A part delivery leaves the rest of the order open.
- [ ] The received amount is recorded as owed to the supplier (R-66) and as an expense (R-64).

**Still open:** nothing.

**Old system:** none.

### R-79: Purchase status tracking

**In plain words:** Each purchase shows its status: pending, partly received, completed or cancelled. The requester and approver get a notice whenever it changes.

**Why it is needed:** The spec, page 17 and page 18 ("procurement status update").

**Where the need came from:** D-69 and D-72.

**Depends on:** R-77 and R-10.

**Blockers:** none known.

**Done when:**

- [ ] Every purchase shows one of the four statuses.
- [ ] Each status change sends a notice to the requester and approver.

**Still open:** nothing.

**Old system:** none.

---

## Phase blockers

Q-13, Q-14 and Q-21 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) for payroll and attendance. Q-01 to Q-03 for the old-system extras around leave and attendance.

## Phase is finished when

Every item above is ✅, 🔵 or ⬜. Everyone is paid correctly through a proper chain. Money in and out is recorded, much of it automatically. Leads are followed up, and stock and purchasing keep themselves up to date.
