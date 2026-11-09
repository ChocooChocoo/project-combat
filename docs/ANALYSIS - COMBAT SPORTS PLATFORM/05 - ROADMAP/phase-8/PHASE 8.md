# PHASE 8

**Owners decide with data**

[00 - START HERE](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · [05 - ROADMAP](../05%20-%20ROADMAP.md) · Previous: [PHASE 7](../phase-7/PHASE%207.md) · Next: [PHASE 9](../phase-9/PHASE%209.md)

**Status:** ✅ Finished (7 November 2026)

Citation forms are the same as in [02 - FINDINGS](../../02%20-%20FINDINGS/02%20-%20FINDINGS.md).

## The goal

The owner, and employees given permission, open the decision-support area and see dashboards and reports for every part of the gym. They see trends over time, a money forecast, warnings about problems such as a class losing attendance, and a sustainability view of the business. Every figure comes from stored records, worked out by the system.

## Why it comes here

Every dashboard reports on records from earlier phases:

- attendance (R-54);
- memberships (R-36);
- athlete records (R-37 to R-39);
- bookings (R-48);
- customers (R-69 to R-71);
- payroll (R-62);
- money (R-63 to R-68);
- stock and purchasing (R-72 to R-79).

The platform-wide view for the administrator was built earlier, in R-19 in [PHASE 2](../phase-2/PHASE%202.md).

## What it waits for

| Waits for | Why | Status |
|---|---|---|
| R-54, R-55, R-48 in [PHASE 5](../phase-5/PHASE%205.md) | Attendance, progress and bookings to report on | ✅ |
| R-36, R-37, R-38, R-39 in [PHASE 4](../phase-4/PHASE%204.md) | Membership and athlete records to report on | ✅ |
| R-56 to R-79 in [PHASE 6](../phase-6/PHASE%206.md) | Staff, money, customer, stock and purchasing records | ✅ |
| Q-15 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) | The forecasting method | ✅ Answered 7 November 2026 |

## What it unlocks

Nothing in the original plan. It answers the spec's research questions 3 and 4 (the spec, page 4).

## Where this phase stands

| Status | How many |
|---|---|
| ✅ Finished | 12 |
| 🟨 Being worked on | 0 |
| ⭕ Not started | 0 |
| ❌ Blocked | 0 |
| 🔵 Already there | 0 |
| ⬜ Dropped | 0 |
| ❓ Unclear | 0 |
| **Total** | **12** |

Access follows R-08: owners hold every active catalog permission in their gym; employees need the exact named permission through an active, unexpired role. Disabled permissions grant nothing to either group. See [permission operations](../../../implementation-plan/rbac-redesign/rbac-operations.md).

---

## Module: Dashboards and reports

**Purpose:** show the owner how each part of the gym is doing, for any period.

Every item here can be viewed for a chosen date range and opened as a report (the spec, page 19: "dashboards and reports"). The example indicators come from the spec, page 19, section 7.1.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-85 | Attendance dashboard | R-54 | ✅ | Built and packet checks pass |
| R-86 | Membership activity dashboard | R-36 | ✅ | Built and packet checks pass |
| R-87 | Athlete performance dashboard | R-37, R-38, R-55 | ✅ | Built with accepted attendance-based coach context |
| R-88 | Service use dashboard | R-48 | ✅ | Built and packet checks pass |
| R-89 | Customer engagement dashboard | R-69, R-70, R-71 | ✅ | Built and packet checks pass |
| R-90 | Employee dashboard | R-56, R-62 | ✅ | Payroll network/screen/print mask passed |
| R-91 | Money and revenue dashboard | R-63, R-64, R-65 | ✅ | Selected-range finance and accepted current balance snapshot verified |
| R-92 | Stock and purchasing dashboard | R-74, R-79 | ✅ | Built and packet checks pass |

### R-85: Attendance dashboard

**In plain words:** It shows the attendance rate for each class, the busiest hours and days, and the no-show rate.

**Why it is needed:** The spec, page 19, section 7.1.

**Where the need came from:** D-75.

**Depends on:** R-54.

**Blockers:** none known.

**Done when:**

- [x] The three indicators show for any date range.
- [x] Numbers match the attendance records.

**Still open:** nothing.

**Old system:** a 7-day member check-in chart (`backend/app/Http/Controllers/DashboardController.php`, lines 135 to 353).

### R-86: Membership activity dashboard

**In plain words:** It shows how many memberships were new, renewed, expired and cancelled in a period, and the retention rate.

**Why it is needed:** The spec, page 19.

**Where the need came from:** D-75.

**Depends on:** R-36.

**Blockers:** none known.

**Done when:**

- [x] The four counts and the retention rate show for any date range.

**Resolved, 7 November 2026:** retention is opening active/frozen event-state memberships minus distinct opening-cohort expirations/cancellations, divided by opening cohort. Renewal rate is renewal actions divided by renewals plus expirations. No activation-history guess from current status.

**Old system:** members per plan (`DashboardController.php`).

### R-87: Athlete performance dashboard

**In plain words:** It shows athletes' progress and rank promotions, competition results, and how effective the training is.

**Why it is needed:** The spec, page 19, and the spec's research question 3 (page 4).

**Where the need came from:** D-75.

**Depends on:** R-37, R-38 and R-55.

**Blockers:** none known.

**Done when:**

- [x] Promotions and competition results show per period and per coach context.

**Resolved, 7 November 2026:** effectiveness is distinct attendees with a progress entry divided by distinct attendees. User accepted "coach based on attendance": most-attended coach context, visibly inferred, not a causal claim; unassigned entries retained.

**Old system:** none.

### R-88: Service use dashboard

**In plain words:** It shows the most and least booked services, how full classes are, and how busy each coach is.

**Why it is needed:** The spec, page 19.

**Where the need came from:** D-75.

**Depends on:** R-48.

**Blockers:** none known.

**Done when:**

- [x] All three indicators show for any date range.

**Still open:** nothing.

**Old system:** weekly class counts only.

### R-89: Customer engagement dashboard

**In plain words:** It shows the number of leads, how many became members, and feedback and concerns.

**Why it is needed:** The spec, page 19.

**Where the need came from:** D-75.

**Depends on:** R-69, R-70 and R-71.

**Blockers:** none known.

**Done when:**

- [x] Leads, conversion rate, and open and resolved concerns show for any date range.

**Still open:** nothing.

**Old system:** none.

### R-90: Employee dashboard

**In plain words:** It shows employee attendance, workload (for example, sessions per coach) and a payroll summary.

**Why it is needed:** The spec, page 19.

**Where the need came from:** D-75.

**Depends on:** R-56 and R-62.

**Blockers:** none known.

**Done when:**

- [x] All three indicators show for any date range.
- [x] Individual pay is visible only with exactly `payroll.runs.view` (delegated browser gate PASS, 7 November 2026).

**Still open:** nothing.

**Old system:** none.

### R-91: Money and revenue dashboard

**In plain words:** It shows revenue by service, income against expenses, and what members still owe.

**Why it is needed:** The spec, page 19.

**Where the need came from:** D-75.

**Depends on:** R-63, R-64 and R-65.

**Blockers:** none known.

**Done when:**

- [x] Revenue and income against expenses use selected dates and match finance records. Outstanding balances match current receivables, labelled exactly "Outstanding balances as of today; not a historical balance" on screen and in print.

**Accepted limitation, 7 November 2026:** user chose "Accept current snapshot (Recommended)". Snapshot uses server-today `as_of`, `basis=current_snapshot`, `historical_available=false`; changing selected dates does not reconstruct historical debt. No historical charge ledger or schema change is required.

**Still open:** nothing.

**Old system:** a 6-month revenue trend from membership payments only (`DashboardController.php`).

### R-92: Stock and purchasing dashboard

**In plain words:** It shows stock levels, items below their minimum, and where each purchase stands.

**Why it is needed:** The spec, page 19.

**Where the need came from:** D-75.

**Depends on:** R-74 and R-79.

**Blockers:** none known.

**Done when:**

- [x] All three indicators show. Stock is current and low means at or below minimum; purchasing uses selected creation/receipt dates and current stored status.

**Still open:** nothing.

**Old system:** none.

---

## Module: Analysis and forecasting

**Purpose:** go beyond today's numbers: show where things are heading and what needs attention.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-93 | Trend analysis | R-85, R-86, R-91 | ✅ | Twelve-month window and actual source history verified |
| R-94 | Money forecast | R-91 | ✅ | Forecast math, history and method verified |
| R-95 | Problem spotting | R-85, R-88 | ✅ | Stored boundary tests and populated low-use browser evidence pass |
| R-96 | Income and sustainability measures | R-67, R-68, R-86, R-88 | ✅ | Five measures reconcile and long report checked |

### R-93: Trend analysis

**In plain words:** Charts show how attendance, membership and revenue change month by month.

**Why it is needed:** The spec, page 19, section 7.2.

**Where the need came from:** D-76.

**Depends on:** R-85, R-86 and R-91.

**Blockers:** none known.

**Done when:**

- [x] Each of the three trends shows over at least the last 12 months, with actual first-source dates and zero gaps, not invented history.

**Still open:** nothing.

**Old system:** charts worked out on the owner's screen (`frontend/src/pages/TrainingDashboard.vue`).

### R-94: Money forecast

**In plain words:** The system forecasts income and expenses for the coming months to help with planning. It shows how sure the forecast is and which method was used.

**Why it is needed:** The spec, page 19, section 7.2. The old forecast was one fixed formula on the owner's screen (`TrainingDashboard.vue`, around lines 3000 to 3003).

**Where the need came from:** D-77.

**Depends on:** R-91.

**Blockers:** none.

**Done when:**

- [x] A three-month forecast is shown, with method and R² confidence (fit), not probability.
- [x] Forecast is calculated server-side from up to 12 completed observed months; fewer than three uses "average, not enough history"; no history gives no projections. Incoming/outgoing clamp at zero, net may be negative.

**Still open:** nothing about method; Q-15 answered 7 November 2026.

**Old system:** as above.

### R-95: Problem spotting

**In plain words:** The system points out operational problems, for example a class whose attendance keeps falling, or time slots that are rarely used.

**Why it is needed:** The spec, page 19, section 7.2.

**Where the need came from:** D-78.

**Depends on:** R-85 and R-88.

**Blockers:** none known.

**Done when:**

- [x] A list of flagged problems shows, each with the numbers behind it.
- [x] Falling class attendance and low-use time slots are detected in stored tests. Fresh isolated browser fixture shows 100 sessions, 100/800 held seats and 12.5% mean fill.

**Resolved thresholds:** latest four completed sessions versus previous four, drop at least 20 points and current below 60%; low-use slot at least three sessions with mean fill below 25%; high no-show at least five marked outcomes and rate at least 30%. Server config supplies thresholds and notes.

**Old system:** simple rule-based tips on the owner's screen (`TrainingDashboard.vue`, around line 2293).

### R-96: Income and sustainability measures

**In plain words:** One view shows five measures:

- monthly income against expenses, and the net income trend;
- revenue mix: memberships, sessions, private coaching and merchandise;
- retention and renewal rate;
- which classes and rooms are well used and which are underused;
- budget against actual, with a cash-flow forecast.

**Why it is needed:** The spec, page 19, section 7.3, PF-7.

**Where the need came from:** D-79.

**Depends on:** R-67, R-68, R-86 and R-88. The cash-flow forecast also uses R-94.

**Blockers:** none.

**Done when:**

- [x] All five measures show in one view for any period.
- [x] Figures reconcile to standalone finance, membership and training services. Budget is whole calendar month containing selected end; cash forecast uses cash-flow basis and its own completed-month window.

**Still open:** nothing about method; Q-15 answered 7 November 2026.

**Old system:** none.

---

## Phase blockers

None. Q-15 is answered. Phase-wide acceptance is complete: the frozen 14-row acceptance table is met, including all twelve capabilities and both phase-level criteria. Browser role, state, payslip and print checks passed; independent validation is recorded in `.orchestration/phase-8-decision-support/07-validation.md`. Packet details: [8G](../../../implementation-plan/roadmap-phase-8/phase-8.md).

## Build record, 7 November 2026

All twelve views implemented read-only, with backend calculations and one active payload/report. No new migration, table, index, dependency, scheduled job or stored snapshot. Exact decision-support permission opens all views; individual pay uses the separate exact payroll permission.

- Final backend full suite: `php artisan test`, exit 0, 595 tests, 27,884 assertions, 275,878 ms. Focused combined gate: 90 tests, 3,017 assertions, exit 0; includes two-gym/corrupt-link and all-GET read-only proofs.
- Throwaway MySQL `combat_phase8_8g_20261107_01`: fresh migration/seed, permission sync and stock-count check exit 0; all twelve controller paths return 200 under `ONLY_FULL_GROUP_BY`; stored income equals report 96,400.00; Sunday/hour both zero. Database dropped. Development database and `.env` untouched.
- Owner OpenCode browser: all twelve views, 360/390/768/1440px with no document overflow, date change and empty range; all twelve active reports generated after fixing shared print visibility specificity. Real sole-decision employee renders all twelve views; ordinary employee has no nav, denial state and API 403. Payroll mask cites parent's PASS report.
- Fresh 8G-only SQLite fixture supplies 100 stored classes/results: attendance/athlete/sustainability PDFs have 9/9/12 nonblank pages, repeated headers and first/middle/last rows. Existing light/dark CSS classes tested, not a new theme toggle.
- Final OpenCode browser close-out: pure payroll-only and front-desk roles have no decision nav, a denial state and API 403; owner/own payslip print matched 1,000/100/900 and owner-managed payslip matched 22,500/3,606.80/18,893.20; keyboard Print and Retry, invalid, empty, stale and basis-switch states passed; the five-role matrix passed.
- Independent validation: `.orchestration/phase-8-decision-support/07-validation.md` records Phase 8 complete, 14/14 frozen rows met, with a quick confirmation of 60 tests / 1,027 assertions and no application-diff change.

## Phase is finished when

Every item above is ✅, 🔵 or ⬜. The owner can see every area of the gym, its trends, a forecast, flagged problems and its sustainability in one place, all worked out from real records.
