# PHASE 3

**Each approved gym runs its own workspace, staff and offer**

[00 - START HERE](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · [05 - ROADMAP](../05%20-%20ROADMAP.md) · Previous: [PHASE 2](../phase-2/PHASE%202.md) · Next: [PHASE 4](../phase-4/PHASE%204.md)

**Status:** ✅ Finished

Citation forms are the same as in [02 - FINDINGS](../../02%20-%20FINDINGS/02%20-%20FINDINGS.md).

## The goal

An approved owner sets up the gym's workspace:

- disciplines, rooms, hours and policies;
- employees, each with the permissions they need;
- employee work schedules, leave and holidays;
- a services list with photos and full descriptions;
- membership plans with one all-inclusive price.

People can then find the gym by searching and filtering, and see the nearest gyms to them first.

## Why it comes here

A workspace exists only for an approved gym (R-18 in [PHASE 2](../phase-2/PHASE%202.md)). Employees need the permission model (R-08 in [PHASE 1](../phase-1/PHASE%201.md)). Plans must exist before anyone can apply for membership in [PHASE 4](../phase-4/PHASE%204.md). Coach schedules and leave must exist before coach availability in [PHASE 5](../phase-5/PHASE%205.md).

## What it waits for

| Waits for | Why | Status |
|---|---|---|
| R-18 in [PHASE 2](../phase-2/PHASE%202.md) | Only an approved gym gets a workspace | ✅ |
| R-08, R-09 in [PHASE 1](../phase-1/PHASE%201.md) | Employees get module permissions inside one gym | ✅ |
| R-06 in [PHASE 1](../phase-1/PHASE%201.md) | New employees must set their own password | ✅ |
| Q-19 and Q-21 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) | Leave types and working days shape leave and schedules | ✅ |

## What it unlocks

- Membership applications (R-31) need plans (R-29).
- Sessions (R-42) need rooms (R-21) and coaches (R-22, R-23).
- Coach availability (R-44) needs schedules, leave and holidays (R-24 to R-27).
- Payroll (R-59) needs employee records and leave.

## Where this phase stands

| Status | How many |
|---|---|
| ✅ Finished | 13 |
| 🟨 Being worked on | 0 |
| ⭕ Not started | 0 |
| ❌ Blocked | 0 |
| 🔵 Already there | 0 |
| ⬜ Dropped | 0 |
| ❓ Unclear | 0 |
| **Total** | **13** |

---

## Module: Workspace setup

**Purpose:** give each approved gym its own private space and the basic settings everything else uses.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-20 | Workspace opens on approval, with core settings | R-18 | ✅ | The owner can set disciplines, operating hours and policies |
| R-21 | Facilities and rooms | R-20 | ✅ | The owner keeps a list of training areas that sessions can use |

### R-20: Workspace opens on approval, with core settings

**In plain words:** When a gym is approved, it gets its own workspace. The owner sets:

- the combat sports it teaches;
- its kinds of activity: classes, programs, private coaching, sparring and open mat;
- its operating hours and working days;
- its general policies.

**Why it is needed:** The spec, page 11, section 2.4. In the old system the only thing an owner could change after approval was the map location (`backend/app/Http/Controllers/API/TrainingCenterController.php`, lines 262 to 312).

**Where the need came from:** D-20.

**Depends on:** R-18.

**Blockers:** none. Q-21 answered.

**Done when:**

- [x] An approved owner lands in their own workspace.
- [x] The owner can edit the gym's profile, disciplines, activity types, hours, working days and policies.
- [x] Changes show on the gym's public page.

**Still open:** nothing. Q-21 answered: Monday to Friday by default, and the owner can change it.

**Old system:** as above.

### R-21: Facilities and rooms

**In plain words:** The owner keeps a list of the gym's training areas, such as the main mat, the ring or the weights room. Sessions are then assigned to one of them.

**Why it is needed:** The clash check (R-43) and the session details page (R-47) both need to know which room a session uses (the spec, pages 13 and 14). The old system had no rooms at all, so it could not check room clashes (C-10).

**Where the need came from:** D-20 and D-25; *drawn from* D-27.

**Depends on:** R-20.

**Blockers:** none known.

**Done when:**

- [x] The owner can add, rename and retire rooms.
- [x] A retired room can no longer be chosen for new sessions.

**Still open:** nothing.

**Old system:** none.

---

## Module: HR and employee management (Module 5A)

**Purpose:** let the owner bring employees, coaches included, into the gym, give them roles, and keep their work details. This is the people side of the spec's Module 5, split into its own module (asked by your client, relayed in conversation, 3 November 2026).

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-22 | Owner adds employees and gives them roles | R-08, R-20 | ✅ | The owner can add an employee, give them roles on screen, and the employee can sign in |
| R-23 | Employee and coach profiles | R-22 | ✅ | Each employee has employment details; coaches also have a public profile |
| R-24 | Work schedules | R-22 | ✅ | Each employee has set working hours and days off |
| R-25 | Leave requests with approval | R-22, R-10 | ✅ | An employee can ask for leave and a permitted person decides |
| R-26 | Leave balances | R-25 | ✅ | Each employee sees how many days of each leave type remain |
| R-27 | Holiday calendar | R-20 | ✅ | The gym's calendar shows national and gym-specific holidays |

### R-22: Owner adds employees and gives them roles

**In plain words:** The owner adds an employee by email and gives them one or more of the gym's own roles (R-08). A roles screen lets the owner create, edit and delete those roles, starting from a template if they like. The employee gets an account, or the role is added to their existing one, and must set their own password on first sign-in (R-06).

**Why it is needed:** The spec, page 9 and page 20, step 8. The old system had four different ways to create staff, and one of them returned the new password on screen (`backend/app/Http/Controllers/API/EmployeeController.php`, lines 217 and 218).

**Where the need came from:** D-09 and D-49.

**Depends on:** R-08 and R-20.

**Blockers:** none. Q-10 answered.

**Done when:**

- [x] The owner can add an employee and give them roles in one step.
- [x] The employee receives a sign-in invitation, never a password shown on screen.
- [x] The owner can change an employee's roles or remove the employee at any time, and removal ends their access straight away.
- [x] On the staff and roles screen (S-17), the owner creates, edits and deletes roles, choosing a level for each module, and gives roles to employees. *(The screen part of R-08; moved here in conversation, 3 November 2026, and changed to roles at your client's request.)*

**Still open:** nothing. Q-10 answered: a person may work at several gyms.

**Old system:** `backend/app/Http/Controllers/API/UserManagementController.php`, lines 31 to 142 and 311 to 505.

### R-23: Employee and coach profiles

**In plain words:** Each employee has a record of their employment: job title, start date, pay rate, work assignments and contact details. Coaches also have a public profile with a photo, experience, rank and specialisation, which athletes see on session pages.

**Why it is needed:** The spec, page 16, section 5.1, and page 13, section 3.4 (the coach's photo and short profile on the session details page). In the old system, staff records were a separate table linked to accounts only by matching email (`combat_sports_db.sql`, line 1794).

**Where the need came from:** D-49 and D-33.

**Depends on:** R-22.

**Blockers:** none. Q-13 answered.

**Done when:**

- [x] Each employee has one employment record, tied to their account.
- [x] Coaches can fill in a public profile, and athletes can see it.
- [x] Pay details are visible only to people with HR or finance permission.

**Still open:** nothing. Q-13 answered: a record keeps a monthly rate, a per-session rate, or both.

**Old system:** `backend/app/Models/HrEmployee.php`; `user_settings.specialization` (`combat_sports_db.sql`, line 4376).

### R-24: Work schedules

**In plain words:** Each employee has set working hours and days off. For coaches, this is the base of their availability calendar (R-44).

**Why it is needed:** The spec, pages 13 and 16: "coach schedules and leave feed the coach availability check".

**Where the need came from:** D-49 and D-52.

**Depends on:** R-22.

**Blockers:** none. Q-21 answered.

**Done when:**

- [x] A permitted person can set each employee's weekly working hours and days off.
- [x] An employee can see their own schedule.

**Still open:** nothing. Q-21 answered: Monday to Friday by default, and the owner can change it.

**Old system:** none as a schedule. The old system assumed 08:00 to 16:00 for everyone (`backend/app/Http/Controllers/API/PayrollController.php`, lines 183 to 592).

### R-25: Leave requests with approval

**In plain words:** An employee asks for leave for certain dates, with a reason and an optional attachment. A person with leave-approval permission approves or rejects it. Both sides get a notice. Approved leave blocks the coach's availability (R-44).

**Why it is needed:** The spec, page 16 (leave records) and page 13 (approved leave appears on the coach's calendar).

**Where the need came from:** D-49 and D-52.

**Depends on:** R-22 and R-10.

**Blockers:** none. Q-19 answered.

**Done when:**

- [x] An employee can ask for leave and cancel a pending request.
- [x] A permitted person can approve or reject it, but not their own request.
- [x] Attachments are kept privately (as in R-15).
- [x] Approved leave shows on the employee's calendar.

**Still open:** nothing. Q-19 answered with the old Philippine list.

**Old system:** `backend/app/Http/Controllers/API/LeaveRequestController.php`. The old system counted calendar days and had no check against approving your own leave.

### R-26: Leave balances

*From the old system, not in the spec. Kept as a fitting extra (said in conversation, 3 November 2026).*

**In plain words:** Each employee has a yearly allowance per leave type, and the system shows what remains. Approved paid leave reduces the balance.

**Why it is needed:** It keeps leave records accurate for payroll (the spec, page 16, "leave records", and "compute payroll from ... leave").

**Where the need came from:** the old system, `backend/app/Models/HrLeaveBalance.php`, lines 49 to 64. The old list was vacation 15, sick 15, service incentive 5, solo parent 7, bereavement 3, emergency 3, maternity 105, paternity 7, special leave for women 60 and VAWC 10 days (`combat_sports_db.sql`, lines 1843 to 1865).

**Depends on:** R-25.

**Blockers:** none. Q-19 answered.

**Done when:**

- [x] An employee sees their remaining days for each leave type.
- [x] Approving paid leave reduces the right balance by working days, not calendar days.
- [x] A request for more days than remain is flagged before approval.

**Still open:** Q-01 (whether unused leave can be turned into cash). Not needed for this phase. Q-19 answered with the old Philippine list.

**Old system:** as above.

### R-27: Holiday calendar

*From the old system, not in the spec. Kept as a fitting extra (said in conversation, 3 November 2026).*

**In plain words:** The gym's calendar shows Philippine national holidays and any extra holidays the gym adds. Holidays count for coach availability, absence marking and holiday pay.

**Why it is needed:** Availability (R-44), staff attendance (R-56) and payroll (R-59) all need to know which days are holidays. The old absence job ignored national holidays (C-22).

**Where the need came from:** the old system, `backend/app/Http/Controllers/API/HolidayController.php` and `combat_sports_db.sql`, line 208.

**Depends on:** R-20.

**Blockers:** none known.

**Done when:**

- [x] The calendar shows national holidays without anyone adding them.
- [x] A permitted person can add and remove gym-specific holidays.
- [x] Simply viewing the calendar never changes it.

**Still open:** nothing.

**Old system:** as above. In the old system, viewing the calendar added holidays (lines 52 to 58).

---

## Module: The gym's offer

**Purpose:** publish what the gym sells, clearly and with no hidden charges.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-28 | Services list with photos and full descriptions | R-20 | ✅ | No service can be published without a cover photo and all required fields |
| R-29 | All-inclusive membership plans | R-28 | ✅ | Each plan shows one total price, what it covers, add-ons and policies |

### R-28: Services list with photos and full descriptions

**In plain words:** The owner lists each service. Every service needs:

- a cover photo, plus up to 5 more photos;
- a name, discipline and type;
- a 2 to 4 sentence overview;
- the level or eligibility, the duration and how often it runs;
- what is included, what to bring;
- a price, or "included in membership".

A service cannot be published until all of these are filled in.

**Why it is needed:** The spec, pages 11 and 12, PF-2 and PF-4. The old services had only a title and a description, and the price was always sent as 0 (C-06).

**Where the need came from:** D-21 and D-22.

**Depends on:** R-20.

**Blockers:** none known.

**Done when:**

- [x] A service without a cover photo, or with any required field empty, cannot be published.
- [x] Up to 5 gallery photos can be added; a sixth is refused.
- [x] The price can be a fixed amount or "included in membership".
- [x] Published services appear on the gym's public page.

**Still open:** nothing.

**Old system:** `backend/app/Http/Controllers/API/TrainingCenterOfferController.php`; `combat_sports_db.sql`, line 4242.

### R-29: All-inclusive membership plans

**In plain words:** Each plan shows:

- its name and length;
- one total fee, with any registration fee already included;
- an itemised list of what the fee covers;
- add-ons that are *not* included, each with its own price;
- its policies: validity, freezing, renewal, and cancellation and refunds.

**Why it is needed:** The spec, page 12, PF-3: members must know there are no hidden charges. The old plans were a type, a number of months and a price (C-07), and the plan screens failed for owners (`backend/app/Http/Controllers/MembershipFeeController.php`, lines 20 to 22).

**Where the need came from:** D-23 and D-44.

**Depends on:** R-28, because add-ons point at services.

**Blockers:** none known.

**Done when:**

- [x] A plan cannot be published without a total fee, a list of inclusions and its policies.
- [x] Add-ons show their own price and are marked "not included".
- [x] The plan page shows the athlete exactly these details.
- [x] Plans can be priced by time (for example monthly) or by number of sessions (for example a 10-session pass).

**Still open:** nothing.

**Old system:** as above; `combat_sports_db.sql`, line 2106.

---

## Module: Gym discovery

**Purpose:** let people find the right gym.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-30 | Search and filter gyms | R-18, R-28 | ✅ | Anyone signed in can filter gyms by location, discipline, services, schedules and tier |

### R-30: Search and filter gyms

**In plain words:** After confirming their account, a person lands on the platform home page. There they can search approved gyms and filter by location, combat sport, services, schedules and tier badge.

**Why it is needed:** The spec, page 9, section 1.3. In the old system only approved members could search, by service text and price only (C-17).

**Where the need came from:** D-06 and D-07.

**Depends on:** R-18 and R-28. The schedule filter becomes fully useful only once sessions exist (R-42 in [PHASE 5](../phase-5/PHASE%205.md)).

**Blockers:** none known.

**Done when:**

- [x] A newly confirmed user lands on the home page and can search gyms.
- [x] Filters for location, discipline, services and tier all work.
- [x] The schedule filter works once sessions exist.
- [x] Only approved gyms appear.

**Still open:** nothing. Browsing stays for signed-in users only, as the spec's access table gives "browse" to platform users (page 7).

**Old system:** `frontend/src/pages/MemberDashboard.vue`, lines 293 to 302 and 3622 to 3652; `backend/app/Models/TrainingCenter.php`, lines 174 to 204.

---

## Module: Decision support for athletes: nearest gyms

**Purpose:** help a person choose a gym by showing the nearest ones first and the best matches for what they want. Your client defined decision support as finding "the nearest combat sports training center" (asked by your client, relayed in conversation, 3 November 2026). You chose to add this beside the owner dashboards in [PHASE 8](../phase-8/PHASE%208.md) (said in conversation, 3 November 2026).

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-97 | Nearest gyms to the person | R-30 | ✅ | A person sees approved gyms sorted by distance from where they are, on a list and a map |
| R-98 | Best-match gym recommendations | R-97, R-28, R-29 | ✅ | A person gets a ranked list that weighs distance with discipline, price and schedule, with the reason for each match shown |

### R-97: Nearest gyms to the person

**In plain words:** With the person's permission, the system uses their device location, or an address they type. It shows approved gyms in order of distance, with the distance shown, on a list and on a map.

**Why it is needed:** Your client's definition of decision support (asked by your client, relayed in conversation, 3 November 2026). The old member dashboard did something close: it worked out distances in the browser with the person's location and a map lookup (`frontend/src/pages/MemberDashboard.vue`, lines 3622 to 3652).

**Where the need came from:** D-85.

**Depends on:** R-30.

**Blockers:** none known.

**Done when:**

- [x] A person who allows location sharing sees the nearest approved gyms first, each with its distance.
- [x] A person who does not share their location can type an address or town instead.
- [x] The same gyms appear on a map.
- [x] Distances are worked out by the system from stored gym locations, not from figures sent by the screen.

**Still open:** nothing.

**Old system:** as above. Map lookups used OpenStreetMap and Nominatim, called straight from the screens.

### R-98: Best-match gym recommendations

**In plain words:** The person can say what they want: a combat sport, a price range and preferred training times. The system ranks gyms by how well they match, together with distance. Each suggestion says why it was picked, for example "2 km away, teaches Muay Thai, evening classes".

**Why it is needed:** Distance alone is not enough if the nearest gym does not teach the right sport. The old system had a "best matches" score worked out in the browser (`frontend/src/pages/MemberDashboard.vue`, line 399 and lines 3622 to 3652).

**Where the need came from:** D-85; *drawn from* the old system's best-match score.

**Depends on:** R-97, R-28 and R-29. The schedule part only works once sessions exist (R-42 in [PHASE 5](../phase-5/PHASE%205.md)).

**Blockers:** none known.

**Done when:**

- [x] A person can set the sport, price range and preferred times they want, and the system remembers them.
- [x] Gyms are ranked by match and distance, and each shows the reasons it was suggested.
- [x] The schedule part of the match works once sessions exist.
- [x] The ranking is worked out by the system, not on the screen.

**Still open:** nothing. Decided during implementation: discipline 40, price 30 and distance 30. The schedule part waits for Phase 5.

**Old system:** as above.

---

## Phase blockers

None. Q-10, Q-13, Q-19 and Q-21 were answered on 4 November 2026. Q-01 (leave-to-cash) stays open and does not block this phase.

## Phase is finished when

Every item above is ✅, 🔵 or ⬜. An approved owner can set up their gym, add staff with roles they create, publish complete services and plans, and be found in search, with the nearest gyms shown first.

## Where it was built

Built on 4 November 2026. Paths are from the project folder `project-02-combat`.

- **Workspace and rooms (R-20, R-21):** `backend/app/Http/Controllers/Gym/GymSetupController.php`, `RoomController.php`; migrations in `backend/database/migrations/2026_11_05_100000_create_gym_workspace_tables.php`; screens in `frontend/src/pages/gym/GymSettings.tsx` and `GymRooms.tsx`.
- **Employees, roles, profiles, schedules (R-22 to R-24):** `backend/app/Http/Controllers/Gym/StaffController.php`, `EmployeeProfileController.php`, `ScheduleController.php`; `backend/app/Support/GymRoleAssigner.php`, `WorkingDays.php`; invitation flow in `AuthController.php` and `OneTimeCodes.php`; screens in `GymStaff.tsx` and `EmployeeRecord.tsx`.
- **Leave and holidays (R-25 to R-27):** `backend/app/Http/Controllers/Gym/LeaveController.php`, `HolidayController.php`; `backend/config/hr.php` and `holidays.php`; screens in `GymLeave.tsx` and `MyWork.tsx`.
- **Services and plans (R-28, R-29):** `backend/app/Http/Controllers/Gym/ServiceController.php`, `PlanController.php`; screens in `GymServices.tsx`, `GymPlans.tsx` and the public page `frontend/src/pages/GymPublic.tsx`.
- **Search, nearest gyms and best matches (R-30, R-97, R-98):** `backend/app/Http/Controllers/PublicGymController.php`, `DiscoveryController.php`, `GymPreferenceController.php`, `GeoController.php`; `backend/config/discovery.php`; screens in `frontend/src/pages/Home.tsx` and `frontend/src/components/gym-map.tsx`.
- **Checks:** `php artisan test` passed 125 tests and 846 assertions. `pnpm build` passed. `pnpm lint` reported no errors.
- **Browser verification, 4 November 2026:** R-20 to R-30, R-97 and R-98 passed. R-27 add and remove were rechecked after the holiday list was changed to update in place: the calendar and list change without a reload, and both dialogs close. National holidays, duplicate dates and read-only viewing also passed.
