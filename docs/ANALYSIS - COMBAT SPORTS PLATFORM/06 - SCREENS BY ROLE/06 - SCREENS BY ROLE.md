# 06 - SCREENS BY ROLE

[00 - START HERE](../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · Previous: [PHASE 8](../05%20-%20ROADMAP/phase-8/PHASE%208.md) · Next: [07 - WORD LIST](../07%20-%20WORD%20LIST/07%20-%20WORD%20LIST.md)

## Read this first

This note has two halves, and they are different kinds of thing.

- **What the old system shows** is a finding. It comes from the old screens and cites them.
- **The screens for the rebuild** are a proposal. The spec describes needs, not screens. It names only one screen outright: the session details page (the spec, page 13). Every other screen below is *drawn from* a stated need, given as a D- number from [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md) and an R- number from the roadmap. It is there to be argued with, not taken as settled.

`S-` numbers name screens. They are **not** a build order; the order is in [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md). Screens are named for what a person is doing.

## What the old system shows, by role

Paths sit inside `docs/existing-source-code/laravel_app/`.

| Old role | What they see | Where |
|---|---|---|
| Visitor | Landing page, sign-in, owner sign-up, member sign-up, password recovery, contact form | `frontend/src/router/index.js`, lines 44 to 149 |
| Platform administrator | One dashboard with three sections: an overview, member ID checks, and gym registrations. Approve or reject only. | `frontend/src/pages/AdminDashboard.vue`, lines 60 to 110 |
| Owner, before approval | Status banner and tier upload page | `frontend/src/pages/Dashboard.vue`, lines 109 to 328 |
| Owner, after approval | One dashboard. Tabs: overview with charts, HR (read only), finance, front desk (read only), schedule, members, calendar, staff and roles, location, services, settings. | `frontend/src/pages/TrainingDashboard.vue`, lines 50 to 229 and 428 to 1988 |
| Staff, all kinds | One dashboard whose tabs depend on permission names. Tabs: overview, calendar, attendance, payslips, leave, staff management, payroll, finance, front desk, reports, staff accounts, training management, location, settings. | `frontend/src/pages/DynamicRoleDashboard.vue`, lines 54 to 297 and 3712 to 3810 |
| Member, before ID approval | ID upload page | `frontend/src/pages/MemberVerification.vue` |
| Member, after approval | One dashboard. Tabs: overview with gym search, activity, "my training" (requests and bookings), attendance (a self check-in button), settings. | `frontend/src/pages/MemberDashboard.vue`, lines 55 to 140 |

The old screens get several role rules wrong. These are recorded as contradictions in [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md):

- Owners cannot create sessions or approve memberships (C-12, C-13).
- Members must wait for an ID check before seeing anything (C-01).
- Gym search is hidden from ordinary users (C-17).
- Many staff tabs are reachable by every staff member, because all staff count as HR (*Serious safety faults*, item 2).

## The five levels in the rebuild

| Level | What they come here to do | Where it says so |
|---|---|---|
| Platform administrator | Check gym applications; see platform numbers; send platform notices | The spec, page 7 and page 19 |
| Platform user | Look after their account; find gyms; apply to join one or to run one | The spec, page 7 and page 9 |
| Owner | Run their gym: everything inside its workspace | The spec, page 7 |
| Employee, coaches included | Do the work the owner allowed, module by module | The spec, page 7 and page 9 |
| Athlete or member | Book, check in, and follow their own membership and progress | The spec, page 7 and page 15 |

Each level sees only its own screens. Owners, employees and athletes stay inside their own gym (R-09). Employees need exact active permissions from active, unexpired roles (R-08). Owners hold every active permission, not permissions the administrator has disabled. Everyone is also a platform user, so everyone has the platform-user screens.

## Platform administrator

| # | Screen | What is on it | Where it leads | Where the need came from |
|---|---|---|---|---|
| S-01 | Platform overview | Counts of registered and verified gyms by tier; pending, returned and rejected applications. No gym's private records. | S-02 | D-80, R-19 |
| S-02 | Gym applications | Lists by status, with search | S-03 | *Drawn from* D-17 and D-18 |
| S-03 | Review an application | The gym profile and every document side by side; the checklist; approve, reject with a reason, or return with items to fix | Back to S-02 | D-16 to D-18, R-16 and R-17 |
| S-04 | Send a platform notice | Message and audience | Back to S-01 | D-74, R-81 |

S-04 cannot be finished until Q-23 (what platform notices are for) is answered. An administrator member-ID screen like the old one is not listed; it waits on Q-04.

## Platform user

| # | Screen | What is on it | Where it leads | Where the need came from |
|---|---|---|---|---|
| S-05 | Sign up | Personal and sign-in details | S-06 | D-01, R-01 |
| S-06 | Confirm your email | Code entry and "send a new code" | S-09 | D-05, R-02 |
| S-07 | Sign in | Email and password; link to S-08 | S-09, or the right workspace | D-02, R-03 |
| S-08 | Reset your password | Email, code and new password | S-07 | D-04, R-04 |
| S-09 | Find a gym | The nearest gyms first, by device location or a typed address; best-match suggestions with the reason for each; search with filters for location, discipline, services, schedules and tier; results on a list and a map | S-10 | D-06, D-07 and D-85, R-30, R-97 and R-98 |
| S-10 | Gym page | Profile, badge, services with photos, plans with inclusions, add-ons and policies, and upcoming sessions | S-13, S-11, or S-41 for members | *Drawn from* D-19, D-21 to D-23 |
| S-11 | Apply to run a gym | Gym profile, tier choice, the exact document checklist with uploads, and the upload rules | S-12 | D-11 to D-15, R-11 to R-13 |
| S-12 | My gym application | Status, reason if returned or rejected, and the documents sent | S-11 to correct, or the owner workspace when approved | D-12, R-14 |
| S-13 | Apply for membership | Plan, emergency contacts, background, health questionnaire, waivers and signature | S-39 once approved | D-40 and D-42, R-31 and R-32 |
| S-14 | My profile and settings | Personal details, photo, password | — | D-03, R-05 |
| S-15 | Notifications | The person's notices, unread marked; mark read and clear | Wherever the notice points | *Drawn from* D-72, R-10 and R-83 |

S-11 depends on Q-09 (several gyms per owner), and S-13 on Q-10 (several gyms per athlete).

## Owner

The owner holds every active permission for their own gym. Employees see the same screens only when their exact permissions allow them (R-08). Read, Manage, and Approve are independent. The platform admin manages the shared catalog at `/admin/permissions`; custom keys need a configuration entry and a code check before use.

| # | Screen | What is on it | Where it leads | Where the need came from |
|---|---|---|---|---|
| S-16 | Gym setup | Profile, disciplines, activity types, hours, working days, policies, rooms | — | D-20, R-20 and R-21 |
| S-17 | Staff and roles | Separate staff and role permissions; independent named permission choices; starter templates; on/off switches; optional role end dates; explicit disabled-grant cleanup; delete unused custom roles only; no self changes | S-18 | D-09 and D-82, R-08 and R-22 |
| S-18 | Employee record | Employment details, schedule, leave balance, performance notes; coach public profile | — | D-49, R-23, R-24, R-26 and R-58 |
| S-19 | Services | List and editor with photo upload and required fields; publish | — | D-21 and D-22, R-28 |
| S-20 | Membership plans | List and editor with the fee, inclusions, add-ons and policies | — | D-23, R-29 |
| S-21 | Membership applications | Applications waiting for a decision; approve or reject with a reason | S-22 | D-41, R-33 |
| S-22 | Members and athlete records | Member list; each record shows membership history, payments, attendance, progress, rank, competitions and achievements. Health details only with permission. | — | D-43, D-46 and D-47, R-36 to R-41 |
| S-23 | Schedule | Calendar of sessions; create or edit a session, with clash warnings; reschedule or cancel | S-25, S-26 | D-24 to D-28, R-42, R-43 and R-45 |
| S-24 | Coach availability | Each coach's hours, days off, leave and sessions; flagged sessions to reassign | S-23 | D-30 and D-32, R-44 and R-46 |
| S-25 | Booking requests and waiting lists | Bookings waiting for approval; each session's waiting list | — | D-29 and D-36, R-49 and R-50 |
| S-26 | Session attendance | A session's roster with check-in times; manual entry; progress notes | — | D-26, D-38 and D-39, R-53 to R-55 |
| S-27 | Leave and holidays | Leave requests to decide; the holiday calendar | — | D-49, R-25 and R-27 |
| S-28 | Staff attendance and overtime | Daily staff attendance; overtime requests to decide | — | D-49, R-56 and R-57 |
| S-29 | Payroll | Run a pay period; review each line; approve in turn; release | — | D-50 and D-51, R-59 to R-62 |
| S-30 | Payments desk | Record a cash, card or e-wallet payment at the desk; see online PayMongo payments as they arrive; receipts; outstanding balances | — | D-55, D-56 and D-84, R-34, R-35 and R-65 |
| S-31 | Finance | Income, expenses, amounts owed to suppliers, budgets against actual, and sustainability reports | — | D-53 to D-58, R-63 to R-68 |
| S-32 | Customers | Leads and customers, follow-ups, interaction history, concerns, groups | — | D-59 to D-61, R-69 to R-71 |
| S-33 | Stock | Items, quantities, places, movements, minimum levels | — | D-62 to D-64, R-72 to R-74 |
| S-34 | Purchasing | Restock list, purchase requests and approvals, suppliers, purchase orders, receiving, status | — | D-65 to D-69, R-75 to R-79 |
| S-35 | Announcements | Write an announcement or event; choose all members, a class or a customer group | — | D-70 and D-71, R-80 |
| S-36 | Decision support for owners | The eight dashboards, trends, forecast, flagged problems, sustainability view | — | D-75 to D-79, R-85 to R-96 |

Several screens cannot be finished until open questions are answered:

- S-26 and S-28 depend on Q-14 (lateness).
- S-29 depends on Q-13 (coach pay).
- S-25 depends on Q-12 and Q-18 (waiting lists and approval policy).
- S-36's forecast depends on Q-15.

## Employee, coaches included

An employee sees S-16 to S-36 only for the modules the owner has allowed. On top of that, every employee has these screens:

| # | Screen | What is on it | Where it leads | Where the need came from |
|---|---|---|---|---|
| S-37 | My sessions | For coaches: their upcoming sessions, rosters, check-in confirmation and progress notes | S-26 for a session they run | *Drawn from* D-26 and D-30, R-44 and R-55 |
| S-38 | My work | Their schedule, own attendance, leave requests and balances, and payslips | — | *Drawn from* D-49 and D-51, R-24 to R-26, R-56 and R-62 |

S-38's attendance part depends on Q-03 (GPS check-in) and Q-02 (absence explanations).

## Athlete or member

| # | Screen | What is on it | Where it leads | Where the need came from |
|---|---|---|---|---|
| S-39 | My membership | Plan and what it includes, status and end date, history, payments and receipts, outstanding balance with a "pay online" button, and the membership QR code | S-42 | D-43, D-55, D-56 and D-84, R-34 to R-36 and R-52 |
| S-40 | Gym schedule | Upcoming sessions the member may book, with filters | S-41 | D-34, R-48 |
| S-41 | Session details | Everything on page 13 and 14 of the spec: coach, time, room, level, places left, content, requirements, fee. A Book or Join waiting list button. | S-42 on the day, or S-39 if a fee is owed | D-33, D-35 and D-31, R-47 to R-51 |
| S-42 | Check in | Shows the QR code or confirms check-in for today's booking | S-43 | D-37, R-52 |
| S-43 | My record | Own attendance, progress notes, rank history, competitions and achievements | — | D-47, R-41 |
| S-44 | My bookings | Upcoming and past bookings, waiting-list places, approval status | S-41 | *Drawn from* D-34 and D-36, R-48 to R-50 |

S-42 cannot be finished until Q-11 (who scans whom) is answered, and S-44's cancel button waits on Q-24.

## Where the levels overlap

| Screen | Platform administrator | Platform user | Owner | Employee | Athlete |
|---|---|---|---|---|---|
| S-01 Platform overview | Yes | No | No | No | No |
| S-02 Gym applications | Yes | No | No | No | No |
| S-03 Review an application | Yes | No | No | No | No |
| S-04 Send a platform notice | Yes | No | No | No | No |
| S-05 to S-08 Sign up, confirm, sign in, reset | Yes | Yes | Yes | Yes | Yes |
| S-09 Find a gym | No | Yes | Yes | Yes | Yes |
| S-10 Gym page | No | Yes | Yes | Yes | Yes |
| S-11 Apply to run a gym | No | Yes | Only for another gym, if Q-09 allows | Yes | Yes |
| S-12 My gym application | No | Own only | Own only | Own only | Own only |
| S-13 Apply for membership | No | Yes | Yes, at another gym | Yes, if Q-10 allows | Yes, at another gym, if Q-10 allows |
| S-14 My profile and settings | Own only | Own only | Own only | Own only | Own only |
| S-15 Notifications | Own only | Own only | Own only | Own only | Own only |
| S-16 Gym setup | No | No | Own gym | If permitted | No |
| S-17 Staff and roles | No | No | Own gym | If permitted, never above own access | No |
| S-18 Employee record | No | No | Own gym | If permitted | No |
| S-19 Services | No | No | Own gym | If permitted | No |
| S-20 Membership plans | No | No | Own gym | If permitted | No |
| S-21 Membership applications | No | No | Own gym | If permitted | No |
| S-22 Members and athlete records | No | No | Own gym | If permitted; health only with health permission | No |
| S-23 Schedule | No | No | Own gym | If permitted | No |
| S-24 Coach availability | No | No | Own gym | If permitted; coaches see their own | No |
| S-25 Booking requests and waiting lists | No | No | Own gym | If permitted | No |
| S-26 Session attendance | No | No | Own gym | If permitted; coaches for their sessions | No |
| S-27 Leave and holidays | No | No | Own gym | If permitted | No |
| S-28 Staff attendance and overtime | No | No | Own gym | If permitted | No |
| S-29 Payroll | No | No | Own gym | If permitted, never approving two steps | No |
| S-30 Payments desk | No | No | Own gym | If permitted | No |
| S-31 Finance | No | No | Own gym | If permitted | No |
| S-32 Customers | No | No | Own gym | If permitted | No |
| S-33 Stock | No | No | Own gym | If permitted | No |
| S-34 Purchasing | No | No | Own gym | If permitted, never approving own request | No |
| S-35 Announcements | No | No | Own gym | If permitted | No |
| S-36 Decision support | No | No | Own gym | If permitted | No |
| S-37 My sessions | No | No | No | Coaches, own only | No |
| S-38 My work | No | No | No | Own only | No |
| S-39 My membership | No | No | No | No | Own only |
| S-40 Gym schedule | No | No | No | No | Own gym |
| S-41 Session details | No | Public sessions only | Own gym | Own gym | Own gym |
| S-42 Check in | No | No | No | No | Own only |
| S-43 My record | No | No | No | No | Own only |
| S-44 My bookings | No | No | No | No | Own only |

Some of these cells come from interpretation rather than the spec's words:

- *"Public sessions only"* on S-41 is drawn from the access table's "view public" for platform users in Training Management (the spec, page 7).
- *"No"* for the administrator on S-09 and S-10 is drawn from the spec, page 19: the administrator monitors the platform and does not browse as a customer. Change it if you want administrators to browse.

The old system gets these cells wrong:

- Owners could not reach S-21 or S-23 (C-12, C-13).
- Every staff member effectively reached S-27, S-28 and S-19 (*Serious safety faults*, item 2, in [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md)).
- Platform users could not reach S-09 until approved as members (C-17).

## What is missing

| What is missing | Why it is not above |
|---|---|
| A public landing page for visitors who have not signed up | The spec never mentions one. The old system had one (`frontend/src/pages/LandingPage.vue`). Whether visitors can browse gyms before signing up is not stated (see R-30 in [PHASE 3](../05%20-%20ROADMAP/phase-3/PHASE%203.md)). |
| A contact or help page | Old-system extra; waits on Q-07 |
| Gym reviews | Old-system extra; waits on Q-06 |
| Member ID check for the administrator | Old-system extra that contradicts the spec; waits on Q-04 |
| Leave-to-cash and absence-explanation screens | Old-system extras; wait on Q-01 and Q-02 |
| A screen for the administrator to manage administrator accounts | The roadmap says only the platform creates administrators (R-07, *drawn from* C-16), but neither the spec nor the roadmap says how. It should be decided during implementation planning. |
