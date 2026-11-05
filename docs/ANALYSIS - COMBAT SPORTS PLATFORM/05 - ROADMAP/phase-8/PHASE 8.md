# PHASE 8

**Owners decide with data**

[00 - START HERE](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · [05 - ROADMAP](../05%20-%20ROADMAP.md) · Previous: [PHASE 7](../phase-7/PHASE%207.md) · Next: [06 - SCREENS BY ROLE](../../06%20-%20SCREENS%20BY%20ROLE/06%20-%20SCREENS%20BY%20ROLE.md)

**Status:** ⭕ Not started

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
| R-54, R-55, R-48 in [PHASE 5](../phase-5/PHASE%205.md) | Attendance, progress and bookings to report on | ⭕ |
| R-36, R-37, R-38, R-39 in [PHASE 4](../phase-4/PHASE%204.md) | Membership and athlete records to report on | ✅ |
| R-56 to R-79 in [PHASE 6](../phase-6/PHASE%206.md) | Staff, money, customer, stock and purchasing records | ⭕ |
| Q-15 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) | The forecasting method | ⭕ |

## What it unlocks

Nothing; this is the last phase. It answers the spec's research questions 3 and 4 (the spec, page 4).

## Where this phase stands

| Status | How many |
|---|---|
| ✅ Finished | 0 |
| 🟨 Being worked on | 0 |
| ⭕ Not started | 12 |
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
| R-85 | Attendance dashboard | R-54 | ⭕ | Shows attendance rate per class, peak hours and days, and no-show rate |
| R-86 | Membership activity dashboard | R-36 | ⭕ | Shows new, renewed, expired and cancelled memberships, and retention |
| R-87 | Athlete performance dashboard | R-37, R-38, R-55 | ⭕ | Shows progress, promotions, competition results and training effectiveness |
| R-88 | Service use dashboard | R-48 | ⭕ | Shows most and least booked services, class fill rate and coach use |
| R-89 | Customer engagement dashboard | R-69, R-70, R-71 | ⭕ | Shows leads, conversion rate, feedback and concerns |
| R-90 | Employee dashboard | R-56, R-62 | ⭕ | Shows employee attendance, workload and payroll summary |
| R-91 | Money and revenue dashboard | R-63, R-64, R-65 | ⭕ | Shows revenue by service, income against expenses, and outstanding balances |
| R-92 | Stock and purchasing dashboard | R-74, R-79 | ⭕ | Shows stock levels, items below minimum and purchase status |

### R-85: Attendance dashboard

**In plain words:** It shows the attendance rate for each class, the busiest hours and days, and the no-show rate.

**Why it is needed:** The spec, page 19, section 7.1.

**Where the need came from:** D-75.

**Depends on:** R-54.

**Blockers:** none known.

**Done when:**

- [ ] The three indicators show for any date range.
- [ ] Numbers match the attendance records.

**Still open:** nothing.

**Old system:** a 7-day member check-in chart (`backend/app/Http/Controllers/DashboardController.php`, lines 135 to 353).

### R-86: Membership activity dashboard

**In plain words:** It shows how many memberships were new, renewed, expired and cancelled in a period, and the retention rate.

**Why it is needed:** The spec, page 19.

**Where the need came from:** D-75.

**Depends on:** R-36.

**Blockers:** none known.

**Done when:**

- [ ] The four counts and the retention rate show for any date range.

**Still open:** how "retention rate" is worked out is not defined. The old landing page used approved applications divided by all decided ones, which is not retention (`backend/app/Http/Controllers/API/PublicStatsController.php`, lines 41 to 45). It should be decided during implementation planning.

**Old system:** members per plan (`DashboardController.php`).

### R-87: Athlete performance dashboard

**In plain words:** It shows athletes' progress and rank promotions, competition results, and how effective the training is.

**Why it is needed:** The spec, page 19, and the spec's research question 3 (page 4).

**Where the need came from:** D-75.

**Depends on:** R-37, R-38 and R-55.

**Blockers:** none known.

**Done when:**

- [ ] Promotions and competition results show per period and per class or coach.

**Still open:** "training effectiveness" is not defined in the spec. It should be decided during implementation planning.

**Old system:** none.

### R-88: Service use dashboard

**In plain words:** It shows the most and least booked services, how full classes are, and how busy each coach is.

**Why it is needed:** The spec, page 19.

**Where the need came from:** D-75.

**Depends on:** R-48.

**Blockers:** none known.

**Done when:**

- [ ] All three indicators show for any date range.

**Still open:** nothing.

**Old system:** weekly class counts only.

### R-89: Customer engagement dashboard

**In plain words:** It shows the number of leads, how many became members, and feedback and concerns.

**Why it is needed:** The spec, page 19.

**Where the need came from:** D-75.

**Depends on:** R-69, R-70 and R-71.

**Blockers:** none known.

**Done when:**

- [ ] Leads, conversion rate, and open and resolved concerns show for any date range.

**Still open:** nothing.

**Old system:** none.

### R-90: Employee dashboard

**In plain words:** It shows employee attendance, workload (for example, sessions per coach) and a payroll summary.

**Why it is needed:** The spec, page 19.

**Where the need came from:** D-75.

**Depends on:** R-56 and R-62.

**Blockers:** none known.

**Done when:**

- [ ] All three indicators show for any date range.
- [ ] Individual pay is visible only to people with payroll permission.

**Still open:** nothing.

**Old system:** none.

### R-91: Money and revenue dashboard

**In plain words:** It shows revenue by service, income against expenses, and what members still owe.

**Why it is needed:** The spec, page 19.

**Where the need came from:** D-75.

**Depends on:** R-63, R-64 and R-65.

**Blockers:** none known.

**Done when:**

- [ ] All three indicators show for any date range, matching the finance records.

**Still open:** nothing.

**Old system:** a 6-month revenue trend from membership payments only (`DashboardController.php`).

### R-92: Stock and purchasing dashboard

**In plain words:** It shows stock levels, items below their minimum, and where each purchase stands.

**Why it is needed:** The spec, page 19.

**Where the need came from:** D-75.

**Depends on:** R-74 and R-79.

**Blockers:** none known.

**Done when:**

- [ ] All three indicators show.

**Still open:** nothing.

**Old system:** none.

---

## Module: Analysis and forecasting

**Purpose:** go beyond today's numbers: show where things are heading and what needs attention.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-93 | Trend analysis | R-85, R-86, R-91 | ⭕ | Attendance, membership and revenue trends show over months |
| R-94 | Money forecast | R-91 | ⭕ | A forecast of income and expenses for coming months is shown with its method |
| R-95 | Problem spotting | R-85, R-88 | ⭕ | The system points out problems such as falling class attendance or empty time slots |
| R-96 | Income and sustainability measures | R-67, R-68, R-86, R-88 | ⭕ | The owner sees the five sustainability measures in one view |

### R-93: Trend analysis

**In plain words:** Charts show how attendance, membership and revenue change month by month.

**Why it is needed:** The spec, page 19, section 7.2.

**Where the need came from:** D-76.

**Depends on:** R-85, R-86 and R-91.

**Blockers:** none known.

**Done when:**

- [ ] Each of the three trends shows over at least the last 12 months, or as far back as records go.

**Still open:** nothing.

**Old system:** charts worked out on the owner's screen (`frontend/src/pages/TrainingDashboard.vue`).

### R-94: Money forecast

**In plain words:** The system forecasts income and expenses for the coming months to help with planning. It shows how sure the forecast is and which method was used.

**Why it is needed:** The spec, page 19, section 7.2. The old forecast was one fixed formula on the owner's screen (`TrainingDashboard.vue`, around lines 3000 to 3003).

**Where the need came from:** D-77.

**Depends on:** R-91.

**Blockers:** Q-15 (forecasting method).

**Done when:**

- [ ] A forecast for the next few months is shown, with the method named on screen.
- [ ] The forecast is worked out by the system from stored records.

**Still open:** Q-15.

**Old system:** as above.

### R-95: Problem spotting

**In plain words:** The system points out operational problems, for example a class whose attendance keeps falling, or time slots that are rarely used.

**Why it is needed:** The spec, page 19, section 7.2.

**Where the need came from:** D-78.

**Depends on:** R-85 and R-88.

**Blockers:** none known.

**Done when:**

- [ ] A list of flagged problems shows, each with the numbers behind it.
- [ ] At least falling class attendance and low-use time slots are detected.

**Still open:** the exact thresholds for "falling" and "low use" are not stated. They should be decided during implementation planning.

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

**Blockers:** Q-15 for the forecast part.

**Done when:**

- [ ] All five measures show in one view for any period.
- [ ] Each figure matches the finance, membership and training records.

**Still open:** Q-15.

**Old system:** none.

---

## Phase blockers

Q-15 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md).

## Phase is finished when

Every item above is ✅, 🔵 or ⬜. The owner can see every area of the gym, its trends, a forecast, flagged problems and its sustainability in one place, all worked out from real records.
