# 02 - FINDINGS

[00 - START HERE](../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · Previous: [01 - OVERVIEW](../01%20-%20OVERVIEW/01%20-%20OVERVIEW.md) · Next: [03 - SYSTEM ARCHITECTURE](../03%20-%20SYSTEM%20ARCHITECTURE/03%20-%20SYSTEM%20ARCHITECTURE.md)

**How citations work here**

- "The spec, page N" means `docs/documentation/Combat_Sports_Platform_Features_and_Modules.pdf`.
- "`combat_sports_db.sql`, line N" means `docs/existing-database/combat_sports_db.sql`.
- Paths that start with `backend/` or `frontend/` sit inside `docs/existing-source-code/laravel_app/`.
- Everything was read on 3 November 2026. Line numbers refer to the files as they were that day.

## What was read

| Document or file | Kind | How much was read |
|---|---|---|
| `Combat_Sports_Platform_Features_and_Modules.pdf` | PDF, 20 pages | All of it |
| `combat_sports_db.sql` | A full copy of the old stored information, 5,332 lines | All of it. Personal details were not copied into these notes. |
| `backend/` | Source code: the part doing the work behind the screens | Every address list, job file, permission check, data shape, settings file, setup step and loose script. Skipped: ready-made parts (`vendor/`, `node_modules/`) and uploaded personal files (`storage/`, `public/storage/`). |
| `frontend/` | Source code: the screens people see | All screens, parts and helpers. Skipped: ready-made parts (`node_modules/`) and the generated copy (`dist/`). The generated copy was only checked to see that it matches. |
| `laravel_app.rar` | Packed archive | Not opened, so what it holds was not checked. Its name matches the unpacked folder beside it. |

**Security warning.** `backend/.env` holds a real app secret key, a Gmail email password and PayMongo test secret keys. The loose scripts in `backend/` (for example `check_login.php`, `fix_password.php` and `verify_system.php`) hold real personal email addresses, plain-text passwords and store login details. None of these values is repeated in these notes. Treat them as exposed: change the email password and the keys before anything goes live.

## What the material says the system must do

All of these come from the spec. The numbers are used by the roadmap.

### Module 1: User management and access control

| # | What it must do | Where it says so |
|---|---|---|
| D-01 | Let a new person create an account with personal and sign-in details | The spec, page 9, section 1.1 |
| D-02 | Let people sign in securely | Page 9, section 1.1 |
| D-03 | Let people manage their own profile | Page 9, section 1.1 |
| D-04 | Let people recover a forgotten password | Page 9, section 1.1 |
| D-05 | Email a one-time code after sign-up; the account works only after the code is entered | Page 9, section 1.2 |
| D-06 | Send people to the platform home page once verified | Page 9, section 1.3 |
| D-07 | Let people search and filter gyms by location, discipline, services, schedules and checking status | Page 9, section 1.3 |
| D-08 | Support five roles: platform administrator, platform user, owner, employee (coaches included) and athlete/member | Page 7; page 9, section 1.4 |
| D-09 | Let owners grant each employee permissions module by module | Page 9, section 1.4; page 7 |
| D-10 | Keep each gym's records invisible to other gyms | Page 7; page 8; page 9, section 1.4 |

### Module 2: Training center management

| # | What it must do | Where it says so |
|---|---|---|
| D-11 | Let a verified user apply to be an owner with the gym's profile, address, map location, contacts, hours, disciplines, services and facilities | Page 10, section 2.1 |
| D-12 | Let the applicant choose a checking level ("tier"), upload documents, see the application's status and review what they sent | Page 10, section 2.2 |
| D-13 | Tier 1 documents: a DTI certificate or an SEC certificate, plus a current Mayor's permit and a barangay clearance | Page 10 |
| D-14 | Tier 2 documents: everything in Tier 1, plus at least 10 photos, a video tour, the owner's ID, an owner selfie and a proof-of-address bill | Page 11 |
| D-15 | Upload rules: PDF, JPG or PNG; MP4 for video. Clear, in colour and not expired. Names and addresses must match. | Page 11 |
| D-16 | Send applications with missing or unreadable items back for correction | Page 10 |
| D-17 | Give the administrator a review checklist | Page 11, section 2.3 |
| D-18 | Offer three decisions: approve, reject with a reason, or return for correction and resubmission | Page 11, section 2.3 |
| D-19 | Make an approved gym visible, with a badge for its tier | Page 11, section 2.3 |
| D-20 | Create a workspace where the owner sets up disciplines, classes, programs, facilities, plans, policies, employees, and the customer, money, payroll, stock and purchasing settings | Page 11, section 2.4 |
| D-21 | Require a cover photo for every service and allow a gallery of up to 5 more | Page 11, section 2.5 |
| D-22 | Refuse to publish a service until name, discipline, type, overview, level, duration and frequency, inclusions, requirements and price are filled in | Pages 11 and 12 |
| D-23 | Membership plans show one all-inclusive fee with registration included, an itemised list of what it covers, add-ons with their own prices, and policies for validity, freezing, renewal, cancellation and refunds | Page 12, section 2.6 |

### Module 3: Training management

| # | What it must do | Where it says so |
|---|---|---|
| D-24 | Create classes, group sessions, private coaching, sparring, open mat and other gym-specific services | Page 13, section 3.1 |
| D-25 | Give each session a coach, facility, date, time, capacity and eligibility | Page 13, section 3.1 |
| D-26 | Record training progress notes per session and per participant | Page 13, section 3.1 |
| D-27 | Check for clashing coaches, facilities and overlapping sessions before saving | Page 13, section 3.2 |
| D-28 | Allow rescheduling and cancelling, with affected athletes told automatically | Page 13, section 3.2 |
| D-29 | Keep a waiting list for full sessions and offer a freed place to the next in line | Page 13, section 3.2 |
| D-30 | Give each coach an availability calendar: working hours, days off, approved leave and assigned sessions | Page 13, section 3.3 |
| D-31 | Show only open slots when booking, and refuse a booking that clashes with the coach | Page 13, section 3.3 |
| D-32 | Flag bookings when a coach becomes unavailable, so staff can reassign or reschedule, and tell the athletes | Page 13, section 3.3 |
| D-33 | Show a session details page before booking: name and type, discipline and level, coach profile, time, venue, places left, content, requirements and fee | Pages 13 and 14, section 3.4 |
| D-34 | Let members browse and book classes, services and private sessions | Page 14, section 3.5 |
| D-35 | Check places left, coach availability and eligibility (active membership, level) | Page 14, section 3.5 |
| D-36 | Let staff approve or reject bookings when the gym's policy needs confirmation | Page 14, section 3.5 |
| D-37 | Let members check in with their account or a membership QR code | Page 14, section 3.6 |
| D-38 | Let permitted staff confirm or enter attendance by hand | Page 14, section 3.6 |
| D-39 | Record attendance history: check-in and check-out times, late arrivals, absences and no-shows | Page 14, section 3.6 |

### Module 4: Athlete monitoring and membership

| # | What it must do | Where it says so |
|---|---|---|
| D-40 | Let a platform user apply for membership at a chosen gym | Page 15, section 4.1 |
| D-41 | Let authorised staff approve or reject the application | Page 15, section 4.1 |
| D-42 | On approval, create the membership and athlete profile: plan, eligibility, emergency contacts, health declarations, background, rank and history | Page 15, section 4.1 |
| D-43 | Handle activation, renewal, expiry, suspension and cancellation | Page 15, section 4.2 |
| D-44 | Manage plans, packages and member status | Page 15, section 4.2 |
| D-45 | Send renewal reminders before a membership expires | Page 15, section 4.2 |
| D-46 | Keep athlete records: attendance, progress notes, rank and promotions, skill assessments, competitions, achievements, health and safety | Page 15, section 4.3 |
| D-47 | Let athletes see their own records; staff see them by permission; health details only for authorised staff | Page 15, section 4.3 |

### Module 5: Integrated business management

| # | What it must do | Where it says so |
|---|---|---|
| D-48 | Share information with training: coach schedules and attendance feed payroll; bookings and memberships feed income; session activity drives supply needs | Page 16, PF-7 box |
| D-49 | Keep employee and coach records: employment details, roles, assignments, schedules, attendance, leave and performance notes | Page 16, section 5.1 |
| D-50 | Work out pay from approved pay rates, attendance, leave, deductions and pay periods | Page 16, section 5.1 |
| D-51 | Keep payroll history, net pay and payslips | Page 16, section 5.1 |
| D-52 | Feed coach schedules and leave into the coach availability check | Page 16, section 5.1 |
| D-53 | Record income: membership and registration fees, session and walk-in payments, private coaching, merchandise and discounts | Page 16, section 5.2 |
| D-54 | Record expenses: running costs, payroll pay-outs, supplies and purchasing costs | Page 16, section 5.2 |
| D-55 | Record cash, card and e-wallet payments with their details and history | Page 16, section 5.2 |
| D-56 | Track money members owe the gym and money the gym owes suppliers | Page 16, section 5.2 |
| D-57 | Set budgets and compare budget with actual | Page 16, section 5.2 |
| D-58 | Produce sustainability reports: cash flow, income against expenses, payroll summaries and pay-out history | Page 16, section 5.2 |
| D-59 | Keep customer records: prospects, inquiries, membership leads and follow-ups | Page 16, section 5.3 |
| D-60 | Track customer interactions, preferences, feedback, concerns and engagement | Page 16, section 5.3 |
| D-61 | Support turning leads into members, targeted announcements and resolving concerns | Page 16, section 5.3 |
| D-62 | Keep stock records: equipment, protective gear, supplies and merchandise | Page 17, section 5.4 |
| D-63 | Record quantities, storage places, items issued and returned, adjustments, and damaged or lost items | Page 17, section 5.4 |
| D-64 | Set minimum stock levels and keep a movement history | Page 17, section 5.4 |
| D-65 | Work out what needs restocking from minimum levels, scheduled activities and approved needs | Page 17, section 5.5 |
| D-66 | Create purchase requests and send them for approval | Page 17, section 5.5 |
| D-67 | Keep supplier details and prepare purchase orders | Page 17, section 5.5 |
| D-68 | Record received items and update stock automatically | Page 17, section 5.5 |
| D-69 | Track purchases as pending, partly received, completed or cancelled | Page 17, section 5.5 |

### Module 6: Communication, notifications and mobile access

| # | What it must do | Where it says so |
|---|---|---|
| D-70 | Send announcements to all members, one class, or a customer group | Page 18, section 6.1 |
| D-71 | Announce gym activities and events | Page 18, section 6.1 |
| D-72 | Send automatic notices for nine set events (the table on page 18) | Page 18, section 6.2 |
| D-73 | Run inside a Median.co phone shell, where athletes apply, see schedules, book, check in and get live updates | Page 3; page 18, section 6.3 |
| D-74 | Let the platform administrator send platform notices | Page 7, access table |

### Module 7: Decision support system

| # | What it must do | Where it says so |
|---|---|---|
| D-75 | Show dashboards and reports for attendance, membership, athlete performance, service use, customer engagement, employees, money, and stock and purchasing | Page 19, section 7.1 |
| D-76 | Show trends in attendance, membership and revenue | Page 19, section 7.2 |
| D-77 | Forecast money, to help with planning | Page 19, section 7.2 |
| D-78 | Point out problems, such as falling attendance or empty time slots | Page 19, section 7.2 |
| D-79 | Show sustainability measures: monthly income against expenses, revenue mix, retention, use of classes and rooms, and budget against actual with a cash-flow forecast | Page 19, section 7.3 |
| D-80 | Show the administrator platform-wide counts only, never a gym's private records | Page 19, section 7.4 |
| D-81 | Let employees use decision support only if permitted | Page 7, access table |

### What your client added

These are not in the spec. Your client asked for them, and you settled how far each goes (asked by your client, relayed in conversation, 3 November 2026; answers said in conversation, 3 November 2026).

| # | What it must do | Where it says so |
|---|---|---|
| D-82 | Let each owner create, name, edit and delete their gym's own roles, choose a level per module for each, and give roles to employees ("dynamic RBAC") | Client request; you chose owner-made roles |
| D-83 | Treat HR and employee management, payroll, finance, customer relationships (CRM), inventory and procurement as six separate modules, each with its own screens and permission | Client request; you chose to split them |
| D-84 | Take online payments through PayMongo (GCash, Maya, card) for every fee: memberships, registration, session add-ons, private coaching and other fees | Client request; you made it core for all fees |
| D-85 | Show a person the nearest gyms first, and best-match recommendations that weigh distance with sport, price and schedule | Client request ("the DSS is the nearest combat sports training center"); you chose to add it beside the owner dashboards |

### What your client added later

These came through `docs/prompts/P05.md` (relayed in conversation, 9 November 2026). Your answers that day settle how far each goes.

| # | What it must do | Where it says so |
|---|---|---|
| D-86 | Let staff check in and check out against the schedules the gym owner sets, with a grace period of at least 10 minutes for late clock-ins | `P05.md`, *Constraints* and *Tasks: Staff*; you chose owner-set with a 10-minute minimum |
| D-87 | Show each staff member's attendance (present, late, absent) in payroll | `P05.md`, *Tasks: HR / Operations*; you chose to keep the current pay rules |
| D-88 | Show each user only the sidebar modules their role allows | `P05.md`, *Tasks: Technical* |
| D-89 | Let the platform administrator create and edit subscription plans and what each includes | `P05.md`, *Tasks: Admin*; you chose the names Starter, Pro and Elite |
| D-90 | Let the administrator choose which features each plan can and cannot use | `P05.md`, *Tasks: Admin* |
| D-91 | Put each newly registered gym on a free trial | `P05.md`, *Context*; you chose an administrator-set length, 30 days to start |
| D-92 | Require owners to subscribe once the trial ends, and limit owner-side features for gyms that do not | `P05.md`, *Constraints* and *Success Criteria*; you chose "keep only the free plan's modules" |
| D-93 | Take subscription payments through PayMongo | Your answer, said in conversation, 9 November 2026 |

**What the rebuild already does about these** (rebuild code in the project folder, read 9 November 2026; *confirmed*):

- Staff clock in and out, and lateness and absences are worked out from the owner's schedules (`backend/app/Support/StaffAttendanceBook.php`). Each gym has a staff grace setting starting at 10 minutes, but it accepts 0 (`backend/app/Http/Controllers/Gym/GymSetupController.php`, line 146; `PayrollSettingsController.php`, line 29). D-86 is mostly built; the minimum is missing.
- Payroll counts days worked and days absent and deducts late minutes (`backend/app/Support/Payroll/Calculator.php`, lines 127 to 243). It does not count late days. D-87 is partly built.
- The workspace sidebar lists a module only if the person holds one of its permissions (`frontend/src/components/gym-nav.tsx`, lines 12 to 56). D-88 is built.
- There are no subscription plans, trials or gym subscription records anywhere in the rebuild. D-89 to D-93 are not built. The gym "tiers" (D-12 to D-14) are verification levels and are unrelated.


## Rules it has to follow

- **Who sees what.** The access table on page 7 says what each role may do in each module. Where it says "by role", the owner decides, module by module. Each gym's records are kept apart.
- **Document rules.** Exact documents per tier, accepted file types, clear and unexpired, and names and addresses that match (pages 10 and 11). An application with missing items goes back for correction (page 10).
- **Service rule.** A service cannot be published until every required field and the cover photo are present (page 11).
- **Plan rule.** One all-inclusive price, everything it covers listed, and nothing hidden (page 12).
- **Booking rules.** No booking may clash with a coach's schedule (page 13). Bookings need a place left, coach availability and eligibility (page 14).
- **Health details.** Only authorised staff may see them (page 15).
- **Money limits.** No full accounting, tax or statutory reporting (pages 4 and 16).
- **Administrator limit.** Platform-wide numbers only (page 19).

## What the code actually does

### The pieces

- **The screens** are built with Vue 3 and styled with Tailwind (`frontend/package.json`, lines 11 to 28). The interface calls itself "CombatPro" (for example `frontend/src/pages/LandingPage.vue`, line 34).
- **The work behind the screens** is built with Laravel 12 on PHP 8.2 (`backend/composer.json`, lines 9 and 10). Sign-in passes come from Laravel Sanctum, and roles come from Spatie Permission.
- **The store** is a MariaDB 10.4 database called `combat_sports_db` (`combat_sports_db.sql`, lines 7 and 21). It has 50 tables (counted from the dump), and 29 of them hold information. The information is a small test snapshot: 2 gyms and 9 users.
- **Almost all features live in three very large screen files:**
  - `frontend/src/pages/TrainingDashboard.vue`, for owners (6,702 lines)
  - `frontend/src/pages/DynamicRoleDashboard.vue`, for all staff (8,442 lines)
  - `frontend/src/pages/MemberDashboard.vue`, for members (4,060 lines)

### Journey 1: Signing up and signing in

1. **Owner sign-up.** An owner signs up on `frontend/src/pages/Register.vue`. In the same step, the backend creates the owner's account and a "pending" gym (`backend/app/Http/Controllers/API/AuthController.php`, lines 50 to 125).
2. **Member sign-up.** A member signs up on a separate page, `frontend/src/pages/RegisterMember.vue`. There is an age check of 18 or over (`backend/app/Http/Controllers/API/MemberController.php`, lines 49 to 56).
3. **The emailed code.** Both get a 6-digit code by email. It lasts 10 minutes and is stored as plain text (`AuthController.php`, lines 80, 81 and 130 to 224). There is no limit on attempts, and no address is protected against rapid repeated tries. Routes 49 to 65 of `backend/routes/api.php` are public, and no rate limit was found anywhere.
4. **Members wait for an ID check.** A new member must upload a government ID. The platform administrator then approves or rejects that member before the member can use the platform (`MemberController.php`, lines 198 to 280; `backend/app/Http/Controllers/API/AdminController.php`, lines 181 to 246; `frontend/src/utils/roleRouting.js`, lines 179 to 242).
5. **The sign-in pass.** Signing in hands out a pass that never expires (`backend/config/sanctum.php`, line 50). Four of the screens' sign-out buttons only forget the pass on the device and do not cancel it on the server. One example is `frontend/src/pages/AdminDashboard.vue`, line 1565.
6. **Forgotten passwords.** Recovery reuses the same code column. It tells a stranger whether an email address has an account (`AuthController.php`, lines 1004 to 1119).
7. **First sign-in for staff.** Staff accounts made by an owner must change their password at first sign-in. Only the screen enforces this (`frontend/src/App.vue`, lines 34 to 77); the backend does not block other requests (`AuthController.php`, lines 275 to 277).
8. **Two-step sign-in is a shell.** Confirming it does not check the code, and sign-in never asks for it (`backend/app/Http/Controllers/API/SettingsController.php`, lines 349 to 351).

### Journey 2: A gym applies and is checked

1. **Choosing a tier.** The owner, still pending, sees a tier picker on `frontend/src/pages/Dashboard.vue` (lines 201 to 320).
2. **Uploading documents.** They upload through `frontend/src/components/Tier1Upload.vue` or `Tier2Upload.vue`. The chosen tier is never sent to the backend.
3. **The tier is decided automatically.** On submit, the backend picks the tier itself, and Tier 1 wins if its documents exist (`backend/app/Models/TrainingCenter.php`, lines 248 to 259). It then **deletes the other tier's files** (`backend/app/Http/Controllers/API/DocumentController.php`, lines 246 to 277).
4. **Where documents are kept.** They are saved in a publicly reachable folder (`DocumentController.php`, line 66). The photo-delete action deletes **any** file in that folder that it is given (`DocumentController.php`, line 187).
5. **The administrator's choice.** The administrator can only approve or reject (`backend/routes/api.php`, lines 568 to 584). There is no "return for correction". Approving does not check that any documents were actually sent (`AdminController.php`, lines 301 to 342).
6. **What the gym record holds.** Status is pending, approved or rejected. Tier is Tier 1 or Tier 2. The badge is "verified business", "legit gym" or "pending review" (`combat_sports_db.sql`, lines 4190 to 4194).

### Journey 3: Services and plans

- **Services.** Services are a title and a description, limited to 15 sport names (`backend/app/Http/Controllers/API/TrainingCenterOfferController.php`, lines 13 to 29). There is no photo field. The owner screen always sends a price of 0 (`frontend/src/pages/TrainingDashboard.vue`, lines 3940 to 3945).
- **Plans.** Membership plans are a type, a number of months, a price and a description (`combat_sports_db.sql`, line 2106). There is no list of inclusions, no add-ons and no policies.
- **Plan management is broken for owners.** It looks up the gym through a link that does not exist, so it fails (`backend/app/Http/Controllers/MembershipFeeController.php`, lines 20 to 22).

### Journey 4: Joining a gym and paying

1. **Finding a gym.** Members find gyms only inside their own dashboard, by service text and price range (`frontend/src/pages/MemberDashboard.vue`, lines 293 to 302). A gym appears only if it is approved, has a location, has active services with a price above 0, and has a trainer (`backend/app/Models/TrainingCenter.php`, lines 174 to 204).
2. **The application.** It asks for address, emergency contact, plan, weight, a health questionnaire, a medical clearance file, waivers and a typed signature (`frontend/src/pages/MemberDashboard.vue`, lines 1335 to 1500).
3. **What the backend adds.** It works out a health "risk score" and a boxing weight class, and saves the medical file in the public folder (`backend/app/Http/Controllers/API/MembershipApplicationController.php`, lines 148 to 159).
4. **Paying.** The member pays cash at the desk or online through PayMongo (`MembershipApplicationController.php`, lines 201 to 461).
5. **The cash chain.** The front desk receives the cash, and this **immediately approves the membership** (`backend/app/Http/Controllers/API/MembershipPaymentController.php`, lines 78 to 85). The front desk then passes the money to finance, and finance confirms it (lines 93 to 211).
6. **Expiry.** Expiry is only worked out when someone looks: the approval date plus the plan's months (`backend/app/Models/MembershipApplication.php`, lines 82 to 127). There is no renewal, freezing or cancelling.

### Journey 5: Sessions, booking and attendance

- **Who creates sessions.** Only employees with a training permission can create them. Owners cannot (`backend/app/Http/Controllers/API/TrainerSessionController.php`, lines 20 to 38).
- **Repeats.** Sessions can repeat up to 365 times (lines 579 to 657).
- **The clash check** looks only at overlapping times for the same trainer (lines 659 to 669). It does not check rooms, and there is no coach availability calendar.
- **Booking.** It confirms at once and marks the booking "paid" without taking any payment (`backend/app/Http/Controllers/API/MemberTrainerSessionController.php`, lines 185 to 199). It counts places without guarding against two people booking the last place at the same moment (lines 178 to 183). There is no waiting list and no approval step.
- **Private and sparring sessions** are requested by the member and approved by the trainer. Approval creates the session and a "paid" booking (`backend/app/Http/Controllers/API/TrainerSessionRequestController.php`, lines 236 to 361).
- **Member check-in** is a button the member presses from 30 minutes before the start until the end, on trust (`MemberTrainerSessionController.php`, lines 281 to 369). There is no QR code. No QR tool exists in `frontend/package.json`.
- **When a trainer checks out,** every booking is marked "attended" (`TrainerSessionController.php`, lines 416 to 423).
- **When a session is cancelled,** members are told, but nothing is refunded (`TrainerSessionController.php`, lines 455 to 517).

### Journey 6: Staff, attendance, leave and payroll

This is the most complete part of the old system. Most of it goes beyond what the spec asks for.

- **Staff check-in.** Staff check in and out by GPS, within 150 metres of the gym. The phone sends its own position, so it can be faked (`backend/app/Http/Controllers/API/EmployeeAttendanceController.php`, line 18 and lines 816 to 850).
- **Overtime.** Work past 8 hours and after 16:00 creates an overtime request (lines 243 to 277).
- **Absences.** A nightly job marks absent staff (`backend/routes/console.php`, line 13). Staff can send an explanation with an attachment, which the owner or manager reviews (`backend/app/Http/Controllers/API/AbsenceExplanationController.php`).
- **Leave.** Philippine leave types, with balances and some types limited by sex (`backend/app/Models/HrLeaveBalance.php`, lines 49 to 64 and 137 to 171). Unused leave can be converted to cash at the monthly salary divided by 26 (`backend/app/Http/Controllers/API/LeaveConversionController.php`, lines 175 to 178).
- **Holidays.** Philippine holidays are worked out automatically, and each gym can add its own (`backend/app/Http/Controllers/API/HolidayController.php`).
- **Payroll** (`backend/app/Http/Controllers/API/PayrollController.php`):
  - *Pay calculation:* minutes worked between 08:00 and 16:00, holiday rates, overtime, late deductions, and the SSS, PhilHealth, Pag-IBIG and TRAIN tax deductions (lines 183 to 592 and 1339 to 1535).
  - *Approval chain:* HR, then finance, then owner (lines 594 to 894).
  - *Trainers and coaches are left out entirely.* The code says a "dedicated flow" would come later (lines 389 to 392).
  - *The chain can be skipped:* a plain edit can set a payroll straight to "released" (lines 133 to 145).

### Journey 7: Announcements, notifications and reports

- **Announcements** go only to staff with certain roles and to the owner, never to members (`backend/app/Http/Controllers/AnnouncementController.php`, lines 180 to 193).
- **Notifications** are an in-app list. They load once per page; there is no live channel (`frontend/src/pages/DynamicRoleDashboard.vue`, lines 4139 to 4141). Some "automatic" notices are only created when someone opens a dashboard (`backend/app/Http/Controllers/DashboardController.php`, lines 597 to 790).
- **The administrator's activity feed** is re-fetched every 5 seconds (`frontend/src/pages/AdminDashboard.vue`, line 1385).
- **The "decision support"** is charts and simple rules worked out on the owner's screen. The month-end forecast is 0.6 times the last three months' average plus 0.4 times last month (`frontend/src/pages/TrainingDashboard.vue`, around lines 2293 and 3000 to 3003).
- **The revenue and statistics page** is open to any signed-in user linked to a gym, with no permission check (`DashboardController.php`, lines 135 to 353; `backend/routes/api.php`, lines 38 to 40).

### Where things are kept

Tables that exist in `combat_sports_db.sql`, grouped by spec module:

| Module | Tables | Missing entirely |
|---|---|---|
| 1. Accounts and access | `users` (line 4320), `roles` (3322), `permissions` (3018), the link tables for roles and permissions, `personal_access_tokens` (3106), `password_reset_tokens`, `active_sessions`, `user_settings` (4376), `training_center_role_profiles` (4292). The one-time code is two columns on `users`. | A table for platform notices |
| 2. Gym setup | `training_centers` (4177), `training_center_documents` (4214), `training_center_offers` for services (4242), `membership_fees` for plans (2106), `training_center_reviews` (4273) | Service photos, plan inclusions, add-ons and policies, facilities or rooms, operating hours |
| 3. Training | `trainer_sessions` (4026), `trainer_session_bookings` (4095), `trainer_session_requests` (4140), `trainer_session_attendance` for coach time (4065), `holidays` (208) | Coach availability, waiting list, QR codes, facilities |
| 4. Members | `membership_applications` (2054), `membership_payments` (2134), `member_documents` (2178) | Rank or belt, competitions, achievements, a health record of its own, membership changes over time |
| 5. Business | `hr_employees` (1794), `hr_attendance` (1776), `hr_leave_requests` (1897), `hr_leave_balances` (1826), `hr_leave_conversions` (1873), `hr_overtime_requests` (1921), `hr_payroll` (1941), `payroll_statutory_rates` (2965), `absence_explanations` (30), `job_postings` (2012) | Expenses, receivables, payables, budgets, customer follow-up, stock, purchasing, suppliers |
| 6. Communication | `announcements` (111), `notifications` (2883), `subscribers` (4004) | Scheduled reminders |
| 7. Decision support | Only `activities`, a simple log (83) | Everything else |

Problems in the stored information (all from `combat_sports_db.sql`):

- **Duplicate HR tables.** Four old HR tables sit beside their newer `hr_` replacements and are never used: `employees` (line 172), `attendances` (128), `leaves` (2031) and `payrolls` (2936).
- **Three ways of storing roles, which disagree:**
  - a free-text `role` column on `users` (line 4345);
  - Spatie roles: 28 of them, including per-gym ones such as `tc_12_coach` (lines 3335 to 3362);
  - a third layer of per-gym role profiles (line 4292).
- **No link between staff tables.** Staff records in `hr_employees` are tied to user accounts only by matching email. There is no link column. The email must be unique across all gyms, so one person cannot work at two gyms (line 4515).
- **Missing links between tables.** Several are missing, and this has left many orphaned rows. For example, 103 of the 111 role-assignment rows point at users who no longer exist (counted from line 2764).
- **Deletes that spread.** Deleting an owner deletes their gym, and deleting a gym deletes its staff accounts (lines 5286 and 5320).
- **Money is stored as exact decimals everywhere.** This is correct.

### Things that go outside the system

- **PayMongo** takes online payments for memberships only (`backend/app/Services/PayMongo/PayMongoService.php`). Its check that incoming payment messages are genuine is probably wrong (lines 173 to 184). In practice, the member's screen confirms the payment by asking PayMongo directly.
- **Gmail** sends the emails: codes, password resets, staff welcome emails with the password in plain text, and the contact form (`backend/.env.example`, lines 53 to 61; `backend/app/Notifications/StaffWelcomeNotification.php`, lines 40 and 41). Every email holds up the request until it is sent.
- **OpenStreetMap and Nominatim** draw maps and look up addresses, called straight from the screens (`frontend/src/pages/DynamicRoleDashboard.vue`, lines 7908 and 8058 to 8090).
- **Median.co, text messages and phone push notices:** none found.

### Serious safety faults

These matter for the rebuild because each one is a rule the new system must get right.

1. **An owner can make anyone a platform administrator.** The role-assign action accepts any existing role name, `admin` included (`backend/app/Http/Controllers/API/RolePermissionController.php`, lines 165 to 182). Administrator pages accept that role (`backend/routes/api.php`, line 568).
2. **Every staff member counts as HR.** Every staff account is given attendance permissions automatically (`backend/app/Support/RbacPermissionCatalog.php`, lines 52 to 59). Anyone with any attendance permission counts as HR (`backend/app/Models/User.php`, lines 166 to 180). The role check also lets people through on permissions alone (`backend/app/Http/Middleware/CheckRole.php`, lines 51 to 53 and 82 to 102).
3. **Owners skip every permission check** (`backend/app/Http/Middleware/CheckPermission.php`, lines 32 to 34).
4. **Private files are public.** Member IDs, medical clearances, owner IDs and selfies, and leave attachments are all saved in a publicly reachable folder.
5. **Any public file can be deleted** through the photo-delete action (`DocumentController.php`, line 187).
6. **Gym separation is done by hand, one action at a time, with gaps.** For example: records can be created for another gym's staff (`backend/app/Http/Controllers/Api/AttendanceController.php`, lines 51 to 61; `PayrollController.php`, lines 100 to 116), and payroll searches are not limited to one gym when the user has none (`PayrollController.php`, lines 1621 to 1623). Gym grouping in the role tool is switched off (`backend/config/permission.php`, line 134).
7. **Weak sign-in protection.** No rate limits, plain-text codes, passes that never expire, a fake two-step sign-in, and new staff passwords returned on screen (`backend/app/Http/Controllers/API/EmployeeController.php`, lines 217 and 218).
8. **Reading a page can change stored information.** For example, holidays are added when someone simply views the calendar (`HolidayController.php`, lines 52 to 58).
9. **Almost no automatic tests.** Two placeholder tests and one small real test. Nothing covers sign-in, permissions, gym separation, payments or payroll (`backend/tests/`).

### Unfinished, switched-off or unused parts

- **Broken actions:**
  - The "clear all documents" address points at an action that does not exist (`backend/routes/api.php`, line 316).
  - Updating staff permissions always fails (`backend/app/Http/Controllers/API/UserManagementController.php`, line 198).
  - Clearing read notifications is caught by the wrong address (`backend/routes/api.php`, lines 495 and 496).
- **Dead code:**
  - Performance reviews and job postings return "temporarily disabled" and have no addresses.
  - Four "create account" actions in `AuthController.php` have no address.
  - One staff-creation screen part, `frontend/src/components/CreateUserWithPermissions.vue`, is not used anywhere.
- **Leftover setup files:**
  - The setup file that should create starter data fails (`backend/database/seeders/DatabaseSeeder.php`, lines 21 to 24).
  - The administrator seed creates the account without the administrator role.
- **About 30 loose test-and-fix scripts in `backend/`** show the bugs the developers kept fighting:
  - first-sign-in password tracking;
  - the main role and the Spatie roles disagreeing;
  - staff records drifting apart from accounts;
  - gyms not showing up for members.
- **Two scripts in `frontend/`, `transform.cjs` and `combatpro-transform.cjs`,** were one-off jobs that switched the screens from a dark look to a light one.

## Promised and built, side by side

| Module | Built in the old system | Partly built | Not built at all |
|---|---|---|---|
| 1. Accounts | Sign-up, emailed code, sign-in, password recovery, own profile | Roles (three systems that disagree), permissions (unsafe), gym separation (gaps), gym search (members only, 2 filters) | One shared sign-up; a "platform user" role; a coach role |
| 2. Gym setup | Owner application, document upload, approve or reject, badge | Tier choice (decided automatically), services (no photos, no price, no required fields), plans (no inclusions or policies), workspace (location only) | Return for correction, a review checklist, upload rules |
| 3. Training | Session creation with repeats, private and sparring requests | Clash check (same trainer only), booking (no checks, fake "paid"), attendance (honour button) | Coach availability, room clashes, session details page, waiting list, booking approval, QR check-in, progress notes, coach reassignment |
| 4. Members | Membership application, cash and online payment | Approval (done by the cash desk), expiry (worked out when viewed) | Renewal, freezing, cancelling, rank, competitions, achievements, athlete record views |
| 5. Business | Staff records, staff attendance, leave, overtime, payroll with Philippine deductions | Income (membership payments only) | Coach payroll, expenses, receivables and payables, budgets, sustainability reports, customer follow-up, stock, purchasing |
| 6. Communication | In-app notification list | Announcements (staff only), automatic notices (only when a dashboard loads) | Announcements to members or classes, scheduled reminders, live updates, Median.co |
| 7. Decision support | Charts on the owner's screen | A forecast (one simple formula) | Reports, trend analysis, issue spotting, sustainability measures, the administrator's platform view |

*Drawn from* the journeys above and the table of stores in *Where things are kept*.

## Confirmed and derived

| Kind | What it means | Written as |
|---|---|---|
| **Confirmed** | The material states it outright | The spec, page 4. Or `backend/...`, lines 10 to 20. |
| **Derived** | Worked out from the material, not stated outright | *Drawn from* the files named |

Twelve of the most serious code claims were checked line by line against the files on 3 November 2026, after the first read. These are faults 1 to 3 and 5, the fake "paid" booking, the payroll skip, coaches being left out of payroll, the clash check, the approve-or-reject-only decision, the automatic tier, who receives announcements, and the role list in the stored information.

## Where the documents disagree, or the code contradicts them

You settled how all of these are handled: **the spec wins** (said in conversation, 3 November 2026). They are kept here so the rebuild does not repeat them.

| # | One says | The other says | Where |
|---|---|---|---|
| C-01 | Spec: after the emailed code, the user goes to the home page | Old system: a member must also upload an ID and wait for the platform administrator's approval | The spec, page 9, versus `MemberController.php`, lines 198 to 280, and `AdminController.php`, lines 181 to 246 |
| C-02 | Spec: a verified user applies to become an owner | Old system: owner sign-up creates the account and a pending gym in one step, so no ordinary user can apply later | The spec, page 10, versus `AuthController.php`, lines 50 to 125 |
| C-03 | Spec: approve, reject with a reason, or return for correction | Old system: approve or reject only | The spec, page 11, versus `backend/routes/api.php`, lines 568 to 584, and `combat_sports_db.sql`, line 4190 |
| C-04 | Spec: the applicant chooses the tier | Old system: the tier is picked automatically and the other tier's files are deleted | The spec, page 10, versus `TrainingCenter.php`, lines 248 to 259, and `DocumentController.php`, lines 246 to 277 |
| C-05 | Spec: a DTI certificate *or* an SEC certificate | Old screen: DTI required, SEC optional | The spec, page 10, versus `frontend/src/components/Tier1Upload.vue`, lines 356 to 359 |
| C-06 | Spec: a cover photo, a gallery and nine required fields, including price | Old system: title and description only; price always 0 | The spec, pages 11 and 12, versus `combat_sports_db.sql`, line 4242, and `TrainingDashboard.vue`, lines 3940 to 3945 |
| C-07 | Spec: an all-inclusive fee, inclusions, add-ons and policies | Old system: type, months, price and description | The spec, page 12, versus `combat_sports_db.sql`, line 2106 |
| C-08 | Spec: five roles, with employee permissions set by the owner per module | Old system: 13 fixed roles plus per-gym roles, stored three ways | The spec, pages 7 and 9, versus `combat_sports_db.sql`, lines 3335 to 3362, 4345 and 4292 |
| C-09 | Spec: a booking checks places, coach and eligibility, and needs approval if the gym requires it | Old system: books at once and marks "paid" without payment | The spec, page 14, versus `MemberTrainerSessionController.php`, lines 133 to 200 |
| C-10 | Spec: check coach, facility and overlap | Old system: same-trainer overlap only | The spec, page 13, versus `TrainerSessionController.php`, lines 659 to 669 |
| C-11 | Spec: check in by account or QR code; staff may enter attendance by hand | Old system: the member presses a button, on trust | The spec, page 14, versus `MemberTrainerSessionController.php`, lines 281 to 369 |
| C-12 | Spec: the owner has full access to training | Old system: owners cannot create sessions | The spec, page 7, versus `TrainerSessionController.php`, lines 20 to 38 |
| C-13 | Spec: authorised staff approve or reject membership | Old system: receiving cash approves the membership automatically, and the staff desk screen has no approve button | The spec, page 15, versus `MembershipPaymentController.php`, lines 78 to 85, and `DynamicRoleDashboard.vue`, line 1790 |
| C-14 | Spec: payroll for employees, coaches included | Old system: coaches are left out | The spec, page 16, versus `PayrollController.php`, lines 389 to 392 |
| C-15 | Spec: announcements to members, classes and customer groups | Old system: staff and the owner only | The spec, page 18, versus `AnnouncementController.php`, lines 180 to 193 |
| C-16 | Spec: the administrator sees platform numbers only | Old system: an owner can create an administrator, and some payroll searches are not limited to one gym | The spec, pages 7 and 19, versus `RolePermissionController.php`, lines 165 to 182, and `PayrollController.php`, lines 1621 to 1623 |
| C-17 | Spec: any verified user searches gyms by location, discipline, services, schedules and tier | Old system: only approved members, by service text and price | The spec, page 9, versus `MemberDashboard.vue`, lines 293 to 302 |
| C-18 | Spec: each gym's records are kept apart | Old system: separation is done by hand and has gaps | The spec, page 9, versus *Serious safety faults* above, item 6 |
| C-19 | Spec: a Median.co phone shell | Old system: none | The spec, page 18, versus a search of `frontend/` that found nothing |
| C-20 | Spec: live updates | Old system: notifications load once per page | The spec, pages 3 and 18, versus `DynamicRoleDashboard.vue`, lines 4139 to 4141 |
| C-21 | `PAYMONGO_SETUP.md`: a different event name and a different status after payment | The PayMongo code | `backend/PAYMONGO_SETUP.md` versus `backend/app/Http/Controllers/API/PayMongoWebhookController.php`, lines 13 to 85 |
| C-22 | The absence job: Saturday and Sunday are days off; only gym-specific holidays count | Payroll: Saturday is a working day | `backend/ATTENDANCE_ABSENCE_MARKING.md` and `backend/app/Console/Commands/MarkAbsentEmployees.php`, lines 42 and 84, versus `PayrollController.php`, line 1029 |
| C-23 | Member sign-up uses the member code addresses | The sign-in screen sends unverified members to the owner code addresses | `frontend/src/pages/RegisterMember.vue`, lines 1348 and 1379, versus `frontend/src/pages/Login.vue`, lines 797 and 849 |

Two more differences come from your client rather than the material:

| # | One says | The other says | Where |
|---|---|---|---|
| C-24 | Spec: decision support means owner dashboards, trends and forecasts | Your client: decision support is "the nearest combat sports training center" | The spec, page 19, versus asked by your client, relayed in conversation, 3 November 2026. Settled: both are built (D-85). |
| C-25 | Spec: no payment company is named; e-wallet payments are only recorded | Your client: PayMongo integration is required | The spec, page 16, versus asked by your client, relayed in conversation, 3 November 2026. Settled: PayMongo is core for every fee (D-84). |

## What the material leaves unsaid

These gaps matter for the rebuild. Each is a question in the [open questions](../00%20-%20START%20HERE/00%20-%20START%20HERE.md) of `00`.

- **Old features the spec does not mention.** You chose to keep the ones that fit inside a spec module (said in conversation, 3 November 2026). The rest are questions: leave-to-cash conversion, absence explanations, GPS staff check-in, the administrator's ID check of members, the health risk score, gym reviews, the newsletter and the contact form (Q-01 to Q-07).
- **Carrying over stored information.** Whether any old information is carried into the new build (Q-08).
- **Several gyms.** Whether one person can own, train at or work at more than one gym (Q-09 and Q-10).
- **QR check-in.** Who scans whom (Q-11).
- **Waiting lists.** How a freed place is offered (Q-12).
- **Coach pay.** How coaches are paid (Q-13).
- **Lateness.** What counts as late (Q-14).
- **Forecasting.** Which method to use (Q-15).
- **Tiers.** Whether a gym can move from Tier 1 to Tier 2 (Q-16), and whether documents expire (Q-17).
- **The gym's "policy" for booking approval.** How it is set (Q-18).
- **Leave.** Which leave types and entitlements apply (Q-19).
- **Refunds.** Rules when the gym cancels a paid session (Q-20).
- **Working days.** A gym's working days for absence and payroll (Q-21).
- **Online payment.** Whether it covers session add-ons or only memberships (Q-22, now answered: every fee).
- **Platform notices.** What they are for, and who gets them (Q-23).
- **Cancelling a booking.** Whether, and until when, an athlete can cancel their own booking (Q-24).
- **Free trial.** What a gym gets during the trial (Q-25), and whether it starts at application or approval (Q-26). Both answered 9 November 2026.
- **Paid periods.** What happens when a paid period ends unpaid (Q-27), and which billing periods are offered (Q-28). Both answered 9 November 2026.

## Your client's P09 requests (12 November 2026)

Source: `docs/prompts/P09.md`, drawn from `gavins lists of changes.md` items 1 to 8 (item 9, tournaments, is out of scope). What the code showed before building, and the item each request became:

| Request | What the rebuild had | Became |
|---|---|---|
| Sidebar groups; reopen the last gym after sign-in | Groups People/Training/Members/Business/Setup; non-owners landed on Home | R-108 |
| Advance (prepaid) plans | One term per charge only | R-109 |
| Keep the price a member paid | Renewal re-read the plan's current fee | R-110 |
| Promos and vouchers | Discounts existed only on desk sales | R-111 |
| Date filters on history pages | Only check-in, schedule, attendance and finance had dates | R-112 |
| Several items per purchase request; receipt photos | Several items already worked; no edit, no approver notice, no proof files | R-113, R-114 |
| Expiry reminders; absence and inactivity notices | Fixed 7/3/1 reminders; no absence or inactivity notices | R-115, R-116 |
| Coach applications | Staff were added by email only | R-117 |
| Training goals and milestones | None | R-118 |
| Injury and equipment safety | None | R-119, R-120 |

Status and checks are in [PHASE 11](../05%20-%20ROADMAP/phase-11/PHASE%2011.md).
