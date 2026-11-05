# 05 - ROADMAP

[00 - START HERE](../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · Previous: [04 - DIAGRAMS](../04%20-%20DIAGRAMS/04%20-%20DIAGRAMS.md) · Next: [PHASE 1](phase-1/PHASE%201.md)

## What this covers

This is the plan for rebuilding the platform from scratch. It covers all seven modules of the spec. It also includes the old-system features you chose to keep because they fit inside a spec module. It is split into eight phases and 98 capabilities, and every item is ⭕ Not started, because nothing from the old system is being reused (said in conversation, 3 November 2026). Each item names what the old system did, as reference only. The plan covers building work only: no budget, staffing or dates. It says **what** the system must be able to do, not **how** to build it.

## Where the plan came from

The plan draws on these sources:

- **The spec's requirements:** D-01 to D-81 in [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md).
- **The old system's faults and contradictions:** C-01 to C-23, also in `02`.
- **The proposed arrangement** in [03 - SYSTEM ARCHITECTURE](../03%20-%20SYSTEM%20ARCHITECTURE/03%20-%20SYSTEM%20ARCHITECTURE.md).
- **Your answers, given in conversation on 3 November 2026:**
  - rebuild everything;
  - the spec wins over the old code;
  - keep the old extras that fit inside a spec module;
  - use the spec's permission model for staff.

## The phases

| Phase | What is true when it is done | Waits for | Items | Status | Note |
|---|---|---|---|---|---|
| 1 | People can sign up, prove who they are, and be recognised safely | — | R-01 to R-10 | ✅ | [PHASE 1](phase-1/PHASE%201.md) |
| 2 | Gyms can apply, be checked, and go live | Phase 1 | R-11 to R-19 | ✅ | [PHASE 2](phase-2/PHASE%202.md) |
| 3 | Each approved gym runs its own workspace, staff and offer | Phase 2 | R-20 to R-30, R-97, R-98 | ✅ Finished | [PHASE 3](phase-3/PHASE%203.md) |
| 4 | Athletes can join a gym and have a complete record | Phase 3 | R-31 to R-41 | ✅ Finished | [PHASE 4](phase-4/PHASE%204.md) |
| 5 | Sessions are scheduled without clashes, booked fairly, and attended | Phases 3 and 4 | R-42 to R-55 | ⭕ | [PHASE 5](phase-5/PHASE%205.md) |
| 6 | The business side runs in one place, fed by training | Phases 3, 4 and 5 | R-56 to R-79 | ⭕ | [PHASE 6](phase-6/PHASE%206.md) |
| 7 | Everyone stays informed, on their phone too | Phase 4; some items also wait for Phases 5 and 6 | R-80 to R-84 | ⭕ | [PHASE 7](phase-7/PHASE%207.md) |
| 8 | Owners decide with data | Phases 4, 5 and 6 | R-85 to R-96 | ⭕ | [PHASE 8](phase-8/PHASE%208.md) |

A phase is ✅ only when every item in it is ✅, 🔵 or ⬜. It is 🟨 once any item is being worked on, ❌ if it cannot start or continue, and ⭕ otherwise. The order is drawn as diagram 10 in [04 - DIAGRAMS](../04%20-%20DIAGRAMS/04%20-%20DIAGRAMS.md).

## Coverage check

Every module of the spec, and the old-system extras, are accounted for.

| Module | Phase | Coverage |
|---|---|---|
| 1. Accounts, sign-in, profiles (spec 1.1, 1.2) | [PHASE 1](phase-1/PHASE%201.md) | Complete: R-01 to R-06 |
| 1. Roles, owner-made roles ("dynamic RBAC"), gym separation (spec 1.4; client request) | [PHASE 1](phase-1/PHASE%201.md), [PHASE 3](phase-3/PHASE%203.md) | Complete: R-07 to R-09; role screens in R-22 |
| 1. Onboarding and gym discovery (spec 1.3) | [PHASE 1](phase-1/PHASE%201.md), [PHASE 3](phase-3/PHASE%203.md) | Complete: R-02 (lands on home page), R-30 |
| 2. Owner application and tiers (spec 2.1, 2.2) | [PHASE 2](phase-2/PHASE%202.md) | Complete: R-11 to R-15 |
| 2. Administrator review (spec 2.3) | [PHASE 2](phase-2/PHASE%202.md) | Complete: R-16 to R-18 |
| 2. Workspace, services, plans (spec 2.4 to 2.6) | [PHASE 3](phase-3/PHASE%203.md) | Complete: R-20, R-21, R-28, R-29 |
| 3. Sessions and scheduling (spec 3.1 to 3.3) | [PHASE 5](phase-5/PHASE%205.md) | Complete: R-42 to R-46, R-55 |
| 3. Session details and booking (spec 3.4, 3.5) | [PHASE 5](phase-5/PHASE%205.md) | Complete: R-47 to R-51 |
| 3. Attendance and check-in (spec 3.6) | [PHASE 5](phase-5/PHASE%205.md) | Complete: R-52 to R-54 |
| 4. Membership application and management (spec 4.1, 4.2) | [PHASE 4](phase-4/PHASE%204.md) | Complete: R-31 to R-36 |
| 4. Athlete records (spec 4.3) | [PHASE 4](phase-4/PHASE%204.md) | Complete: R-37 to R-41 |
| 5A. HR and employee management: staff, roles, schedules, leave (spec 5.1) | [PHASE 3](phase-3/PHASE%203.md) | Complete: R-22 to R-27 |
| 5A. HR and employee management: attendance, overtime, performance (spec 5.1) | [PHASE 6](phase-6/PHASE%206.md) | Complete: R-56 to R-58 |
| 5B. Payroll (spec 5.1) | [PHASE 6](phase-6/PHASE%206.md) | Complete: R-59 to R-62 |
| 5C. Payments, including PayMongo for every online fee (spec 5.2; client request) | [PHASE 4](phase-4/PHASE%204.md) | Complete: R-34, R-35 |
| 5C. Finance (spec 5.2) | [PHASE 6](phase-6/PHASE%206.md) | Complete: R-63 to R-68 |
| 5D. Customer relationships, CRM (spec 5.3) | [PHASE 6](phase-6/PHASE%206.md) | Complete: R-69 to R-71 |
| 5E. Inventory (spec 5.4) | [PHASE 6](phase-6/PHASE%206.md) | Complete: R-72 to R-74 |
| 5F. Procurement (spec 5.5) | [PHASE 6](phase-6/PHASE%206.md) | Complete: R-75 to R-79 |
| 6. Notification inbox (base for spec 6.2) | [PHASE 1](phase-1/PHASE%201.md) | Complete: R-10. The nine automatic notices of spec 6.2 sit in the items that cause them (R-17, R-33, R-45, R-46, R-49, R-50, R-74, R-79, R-82). |
| 6. Announcements, reminders, live updates, phone app (spec 6.1 to 6.3) | [PHASE 7](phase-7/PHASE%207.md) | Complete: R-80 to R-84 |
| 7. Nearest gyms and best matches for athletes (client request) | [PHASE 3](phase-3/PHASE%203.md) | Complete: R-97, R-98 |
| 7. Platform-level monitoring (spec 7.4) | [PHASE 2](phase-2/PHASE%202.md) | Complete: R-19 |
| 7. Dashboards, trends, forecasts, sustainability (spec 7.1 to 7.3) | [PHASE 8](phase-8/PHASE%208.md) | Complete: R-85 to R-96. Employee access "if permitted" is covered by R-08. |
| Old-system extras kept | Phases 1, 3, 4, 6 | R-06, R-26, R-27, R-32, R-38 (divisions), R-57, R-60. PayMongo (R-35) is now a core requirement. |
| Old-system extras not decided | — | Left out until answered (see below) |

## Where everything stands

| Phase | ✅ | 🟨 | ⭕ | ❌ | 🔵 | ⬜ | ❓ | Total |
|---|---|---|---|---|---|---|---|---|
| [PHASE 1](phase-1/PHASE%201.md) | 10 | 0 | 0 | 0 | 0 | 0 | 0 | 10 |
| [PHASE 2](phase-2/PHASE%202.md) | 9 | 0 | 0 | 0 | 0 | 0 | 0 | 9 |
| [PHASE 3](phase-3/PHASE%203.md) | 13 | 0 | 0 | 0 | 0 | 0 | 0 | 13 |
| [PHASE 4](phase-4/PHASE%204.md) | 11 | 0 | 0 | 0 | 0 | 0 | 0 | 11 |
| [PHASE 5](phase-5/PHASE%205.md) | 0 | 0 | 14 | 0 | 0 | 0 | 0 | 14 |
| [PHASE 6](phase-6/PHASE%206.md) | 0 | 0 | 24 | 0 | 0 | 0 | 0 | 24 |
| [PHASE 7](phase-7/PHASE%207.md) | 0 | 0 | 5 | 0 | 0 | 0 | 0 | 5 |
| [PHASE 8](phase-8/PHASE%208.md) | 0 | 0 | 12 | 0 | 0 | 0 | 0 | 12 |
| **All phases** | **43** | **0** | **55** | **0** | **0** | **0** | **0** | **98** |

Phases 1, 2, 3 and 4 are finished: 43 items are done. Phase 1 includes owner-made roles (R-08) as your client asked. Phase 2 was built without tier upgrades or document expiry while Q-16 and Q-17 stay open. Phase 3 was built with the old Philippine leave list, Monday to Friday working days by default, and pay kept as a monthly rate, a per-session rate, or both. Phase 4 was built without a member ID check (Q-04) or a health risk score (Q-05). The other 55 items are waiting their turn.

## Deliberately left out

| What | Why it is not here |
|---|---|
| Leave-to-cash conversion | Old-system extra outside the spec. Waiting on Q-01. |
| Absence explanations | Old-system extra outside the spec. Waiting on Q-02. |
| GPS-fenced staff check-in | Old-system extra outside the spec. Waiting on Q-03. Plain staff attendance is in R-56. |
| Platform administrator checking every member's ID | Old-system extra that contradicts the spec (C-01). Waiting on Q-04. |
| Health risk score from the questionnaire | Old-system extra outside the spec. Waiting on Q-05. The questionnaire itself is in R-32. |
| Member reviews of gyms | Old-system extra outside the spec. Waiting on Q-06. |
| Newsletter and public contact form | Old-system extras outside the spec. Waiting on Q-07. |
| Moving old stored information into the new system | Not decided. Waiting on Q-08. |
| Job postings and recruitment | Disabled placeholders in the old system (`backend/app/Http/Controllers/API/JobPostingController.php`); not in the spec. |
| The old system's duplicate HR tables, loose test scripts and look-changing scripts | Not features: leftovers of the old build (see [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md)). |
| Two-step sign-in | A non-working shell in the old system (`backend/app/Http/Controllers/API/SettingsController.php`, lines 349 to 351); not in the spec. |
| Body-sensor analysis and wearables | Excluded by the spec, page 4. |
| Full accounting, tax and statutory financial reports | Excluded by the spec, pages 4 and 16. |
| A separately built native phone app | Excluded by the spec, page 4. R-84 covers the Median.co shell. |
| Setting up the backend, store and screens (installing, configuring, creating the empty store) | Not a capability: it is groundwork. It belongs at the start of the implementation plan for Phase 1 (see *Implementation handoff* below). |
| Changing the secrets exposed in the old files | Not building work. It is an urgent clean-up task; see the security warning in [00 - START HERE](../00%20-%20START%20HERE/00%20-%20START%20HERE.md). |

## Implementation handoff

Implementation begins with the next eligible `R-` item: the earliest one that is ⭕ Not started and whose dependencies and blockers are clear. To prepare it:

1. Open the phase note it sits in and read its detail there.
2. Read the supporting findings in [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md).
3. Read the affected parts in [03 - SYSTEM ARCHITECTURE](../03%20-%20SYSTEM%20ARCHITECTURE/03%20-%20SYSTEM%20ARCHITECTURE.md).
4. Look at the relevant diagrams in [04 - DIAGRAMS](../04%20-%20DIAGRAMS/04%20-%20DIAGRAMS.md).
5. Check any unresolved questions in the [open questions](../00%20-%20START%20HERE/00%20-%20START%20HERE.md) of `00`.

**Before the first item: setting up.** The implementation plan for Phase 1 must start by setting up the three parts the rebuild uses, before any `R-` item is built. These are the Laravel backend, a new MySQL store built from scratch rather than imported from the old copy, and the coss UI screen app installed with its official method (the stack was said in conversation, 3 November 2026). The setup also covers the parts the proposed arrangement relies on: private file storage, the background sending line, the scheduled job runner, and secrets kept out of shared folders (see [03 - SYSTEM ARCHITECTURE](../03%20-%20SYSTEM%20ARCHITECTURE/03%20-%20SYSTEM%20ARCHITECTURE.md)). Setup is groundwork, not a capability, so it carries no `R-` number (added in conversation, 3 November 2026).

**The next eligible item right now:** R-42, sessions, in [PHASE 5](phase-5/PHASE%205.md). Phases 1 to 4 are finished. The Phase 4 checks that need later work were moved to R-51, R-54, R-55 (Phase 5), R-63 (Phase 6) and R-82 (Phase 7) on 5 November 2026.

This roadmap decides **what** gets built, **which module it belongs to**, and **in what order**. It deliberately does not decide **how**. A separate implementation-planning workflow breaks the chosen item into tasks, sets technical checks, and hands those tasks to whoever builds them.

## Status legend

| Emoji | Means |
|---|---|
| ✅ | Finished: built, checked, working |
| 🟨 | Being worked on |
| ⭕ | Not started: waiting its turn |
| ❌ | Blocked: something is stopping it |
| 🔵 | Already there: found in the supplied material, built before this plan |
| ⬜ | Dropped: decided against, kept for the record |
| ❓ | Unclear: the material does not say |
