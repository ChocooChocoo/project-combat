# PHASE 9

**Staff time and access rules are tightened**

[00 - START HERE](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · [05 - ROADMAP](../05%20-%20ROADMAP.md) · Previous: [PHASE 8](../phase-8/PHASE%208.md) · Next: [PHASE 10](../phase-10/PHASE%2010.md)

**Status:** ✅ Finished (9 November 2026)

Citation forms are the same as in [02 - FINDINGS](../../02%20-%20FINDINGS/02%20-%20FINDINGS.md). Paths starting `backend/` or `frontend/` in this note point at the **rebuild** in the project folder, read 9 November 2026, not the old code.

## The goal

Staff check in and out against the schedules their gym owner sets, and a late arrival always gets at least 10 minutes of grace. Payroll shows, for every employee, how many days they were present, late and absent. Each person's sidebar shows only the modules their role allows.

These requests came from your client through `docs/prompts/P05.md` (relayed in conversation, 9 November 2026).

## What is already there

Most of this request is already built. It is listed here so nothing is built twice:

- **Check-in and check-out against owner schedules** is R-56 in [PHASE 6](../phase-6/PHASE%206.md) (✅). Employees clock in and out; lateness and absences are worked out from their schedule, approved leave and holidays (`backend/app/Support/StaffAttendanceBook.php`).
- **A grace period** already exists. Each gym has its own staff grace setting, starting at 10 minutes. But the setting can be lowered to 0 (`backend/app/Http/Controllers/Gym/GymSetupController.php`, line 146; `backend/app/Http/Controllers/Gym/PayrollSettingsController.php`, line 29). R-99 closes that gap.
- **Attendance in payroll** already exists. Payroll counts days worked and days absent, and deducts late minutes (`backend/app/Support/Payroll/Calculator.php`, lines 127 to 243). It does not count *late days*. R-100 closes that gap.
- **A sidebar filtered by role** already exists (`frontend/src/components/gym-nav.tsx`, lines 39 to 56). R-101 records it.

## Why it comes here

Every item adjusts something built in Phase 6. It comes after Phase 8 only because the request arrived after Phase 8 was finished. Its items do not depend on Phase 7 or 8.

## What it waits for

| Waits for | Why | Status |
|---|---|---|
| R-56 in [PHASE 6](../phase-6/PHASE%206.md) | Staff attendance is what R-99 tightens | ✅ |
| R-59, R-62 in [PHASE 6](../phase-6/PHASE%206.md) | Payroll lines and payslips are where R-100 adds the counts | ✅ |
| R-08 in [PHASE 1](../phase-1/PHASE%201.md), R-22 in [PHASE 3](../phase-3/PHASE%203.md) | Owner-made roles and permissions decide what the sidebar shows | ✅ |

## What it unlocks

Nothing directly. [PHASE 10](../phase-10/PHASE%2010.md) builds on the same sidebar rule (R-101) when it hides modules a gym's plan does not include.

## Where this phase stands

| Status | How many |
|---|---|
| ✅ Finished | 2 |
| 🟨 Being worked on | 0 |
| ⭕ Not started | 0 |
| ❌ Blocked | 0 |
| 🔵 Already there | 1 |
| ⬜ Dropped | 0 |
| ❓ Unclear | 0 |
| **Total** | **3** |

---

## Module: HR and employee management (Module 5A)

**Purpose:** make sure lateness is judged fairly.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-99 | Staff grace period of at least 10 minutes | R-56 | ✅ | No gym can set a staff grace period below 10 minutes |

### R-99: Staff grace period of at least 10 minutes

**In plain words:** Each gym owner still sets their own grace period for staff, but it can never be less than 10 minutes. A new gym starts at 10 minutes. A staff member who clocks in within the grace period is on time. A staff member who clocks in after it can still clock in; they are marked late, and their late minutes are counted from the scheduled start. That last rule is unchanged from Q-14.

**Why it is needed:** Your client asked for a grace period of at least 10 minutes (`docs/prompts/P05.md`, *Constraints*). The rebuild currently lets an owner set it to 0.

**Where the need came from:** D-86.

**Depends on:** R-56.

**Blockers:** none. You chose "owner sets it, minimum 10" (said in conversation, 9 November 2026).

**Done when:**

- [x] An owner can set the staff grace period to 10 minutes or more, and is refused below 10, on every screen that sets it.
- [x] A new gym starts with 10 minutes.
- [x] Any gym already stored with less than 10 minutes is raised to 10.
- [x] A staff member who clocks in 1 minute after the grace ends is marked late, with late minutes counted from the scheduled start.
- [x] A staff member who clocks in within the grace period is marked on time.

**Still open:** nothing.

**Status note (9 November 2026):** Built. Both grace settings (Setup and Payroll settings) refuse less than 10 minutes; a migration raises stored values below 10. `StaffGraceAndLateDaysTest` passes. Browser-checked 9 November 2026.

---

## Module: Payroll (Module 5B)

**Purpose:** let whoever runs payroll see attendance behind each pay line.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-100 | Present, late and absent days on payroll | R-59, R-62, R-99 | ✅ | Every pay line and payslip shows days present, days late and days absent |

### R-100: Present, late and absent days on payroll

**In plain words:** Each employee's pay line shows three counts for the pay period: days present on time, days late, and days absent. The payslip shows the same counts. How pay is worked out does not change: late minutes are still deducted at the minute rate, and absent days are still unpaid.

**Why it is needed:** Your client asked that payroll include each staff member's attendance: present, late and absent (`docs/prompts/P05.md`, *Success Criteria*). Today payroll shows days worked, days absent and late *minutes*, but not how many days were late.

**Where the need came from:** D-87.

**Depends on:** R-59, R-62 and R-99.

**Blockers:** none. You chose to keep the current pay rules (said in conversation, 9 November 2026).

**Done when:**

- [x] Each pay line shows days present, days late and days absent for the period.
- [x] Present plus late equals the days worked already shown.
- [x] The employee's payslip shows the same three counts.
- [x] The counts come from stored attendance, never from figures typed on the screen.
- [x] Pay amounts for an existing test period do not change.

**Still open:** nothing.

**Status note (9 November 2026):** Built. Pay lines store `days_late`; the payroll run screen and payslip show present, late and absent days, and "Late" is now labelled "Late minutes". `StaffGraceAndLateDaysTest` passes. Browser-checked 9 November 2026.

**Who runs payroll:** whoever the owner gives the payroll permissions to, plus the owner. No fixed HR or operations role is assumed (`backend/config/permissions.php`; R-08).

---

## Module: Roles and access (Module 1)

**Purpose:** show people only what they may use.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-101 | Sidebar shows only the modules a role allows | R-08, R-22 | 🔵 | A person never sees a sidebar link to a module their role does not grant |

### R-101: Sidebar shows only the modules a role allows

**In plain words:** The workspace sidebar shows a module only if the person's role grants at least one permission for it. Owners see every module. A person whose role grants nothing sees only their own work. Hiding a link is a convenience; the server still refuses every request the role does not allow.

**Why it is needed:** Your client asked that users only see the sidebar modules their role allows (`docs/prompts/P05.md`, *Tasks: Technical*).

**Where the need came from:** D-88.

**Depends on:** R-08 and R-22.

**Blockers:** none. You said there is no further issue with roles to fix (said in conversation, 9 November 2026).

**Done when:**

- [x] Each sidebar module is shown only when the person holds one of its permissions (`frontend/src/components/gym-nav.tsx`, lines 12 to 56).
- [x] The server refuses a module's requests for a person without its permission (R-08).
- [x] A check confirms every sidebar link's permissions match the permissions its screen's requests need, so no one sees a link that only leads to a refusal.

**Still open:** nothing.

**Status note (9 November 2026):** Checked by reading: each sidebar module's permissions match the permission on the main request its screen makes (for example Staff and `hr.staff.view`, Finance and `finance.payments.view`). Modules locked by the gym's plan (R-106) are now removed from the same list, so they disappear from the sidebar too.

---

## Phase blockers

None.

## Phase is finished when

Every item above is ✅, 🔵 or ⬜. No gym can give staff less than 10 minutes of grace, every payslip shows days present, late and absent, and every sidebar matches what the person may use.
