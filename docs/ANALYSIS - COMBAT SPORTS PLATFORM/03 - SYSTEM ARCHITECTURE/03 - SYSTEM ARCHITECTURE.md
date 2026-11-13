# 03 - SYSTEM ARCHITECTURE

[00 - START HERE](../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · Previous: [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md) · Next: [04 - DIAGRAMS](../04%20-%20DIAGRAMS/04%20-%20DIAGRAMS.md)

## How to read this note

The first half describes the old system as it stands. Every part there is **existing**. The second half describes the arrangement proposed for the rebuild. Every part there is **proposed**. Citation forms are the same as in [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md).

## The arrangement today

### The parts

| Part | What it is for | Who or what it serves |
|---|---|---|
| Public pages, existing | Landing page, sign-in, two sign-up pages, password recovery, contact form (`frontend/src/router/index.js`, lines 44 to 149) | Visitors |
| Owner screens, existing | The tier upload page before approval, then one large dashboard after it (`frontend/src/pages/Dashboard.vue`, `frontend/src/pages/TrainingDashboard.vue`) | Owners |
| Staff screens, existing | One large dashboard whose tabs appear according to permission names (`frontend/src/pages/DynamicRoleDashboard.vue`, lines 3712 to 3810) | HR, finance, front desk, trainers, managers |
| Member screens, existing | An ID upload page, then a member dashboard (`frontend/src/pages/MemberVerification.vue`, `frontend/src/pages/MemberDashboard.vue`) | Members |
| Administrator screen, existing | One dashboard listing gyms and members to approve (`frontend/src/pages/AdminDashboard.vue`) | Platform administrator |
| The work behind the screens, existing | Laravel; roughly 300 addresses, all under `/api` (`backend/routes/api.php`) | Every screen |
| Role and permission checks, existing | Three gatekeeping steps: `role`, `permission` and `admin`. The `admin` one is never used (`backend/bootstrap/app.php`, lines 15 to 19). | The backend |
| The store, existing | MariaDB, 50 tables (`combat_sports_db.sql`) | The backend |
| File folder, existing | One publicly reachable folder for every upload (for example `backend/app/Http/Controllers/API/DocumentController.php`, line 66) | The backend and anyone with a link |
| Nightly job, existing | Marks absent staff at 23:59 (`backend/routes/console.php`, line 13) | HR |

### How work passes between them

1. **Routing a person.** Every screen change first asks the backend "who is this?" (`frontend/src/router/index.js`, lines 155 to 231). The screen then sends the person to the dashboard for their role (`frontend/src/utils/roleRouting.js`, lines 179 to 242).
2. **Each request.** The dashboard sends requests carrying the person's sign-in pass. The backend checks the role or permission named on that address. Then the job itself works out which gym the person belongs to, by hand, in each separate action (for example `backend/app/Http/Controllers/API/MembershipPaymentController.php`, lines 264 to 271).
3. **Keeping gyms apart.** No single place stops one gym's request from reaching another gym's records. Most actions remember to filter, some do not (see [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md), *Serious safety faults*, item 6).
4. **Emails.** Emails are sent while the person waits, because the waiting line for slow jobs is switched to "do it now" (`backend/.env.example`, line 38), and no notification or mail job is marked to wait in line (a search of `backend/app/Notifications` and `backend/app/Mail` found none).
5. **Notifications.** They are written into a table. Screens read them once per page load.

### Where things are kept

- **The store** holds everything, grouped by module in *Where things are kept* in [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md).
- **Uploaded files** sit in one public folder, split by gym number or user number:
  - gym documents
  - member IDs
  - medical clearances
  - profile photos
  - leave attachments
- **The screens keep things on the person's device:**
  - the sign-in pass;
  - for members, a full copy of their profile, which is never cleared (`frontend/src/pages/RegisterMember.vue`, lines 1350 and 1351).

### Where it touches the outside world

| Outside service | What it is for | What happens when it is unavailable |
|---|---|---|
| Gmail | Codes, password resets, staff welcome emails, the contact form | The request waits, then fails. The material does not say what the person sees. |
| PayMongo | Online membership payment | The member can still pay cash at the desk (`backend/app/Http/Controllers/API/MembershipApplicationController.php`, lines 201 to 253) |
| OpenStreetMap and Nominatim | Map tiles and address lookups, called from the screens | The material does not say |

### What holds the arrangement together

These are choices that shape everything else. Each is cited, and none of them is explained in the material.

1. **Roles are stored in three places that disagree.** A text column, Spatie roles and per-gym role profiles (`combat_sports_db.sql`, lines 4345, 3335 to 3362 and 4292). Every permission check has to reconcile all three. The developers' loose scripts show they struggled with this.
2. **Gym separation is done by hand in each job.** Spatie's gym grouping is switched off (`backend/config/permission.php`, line 134).
3. **There are three giant screen files, one per kind of user,** instead of one screen per task. This is why owners cannot reach staff features (C-12 and C-13 in [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md)).
4. **Owner sign-up and gym creation are one step** (`backend/app/Http/Controllers/API/AuthController.php`, lines 50 to 125).

## The arrangement being proposed

**Stack for the rebuild.** You chose Laravel for the work behind the screens, MySQL for the store, and coss UI for the screens (said in conversation, 3 November 2026). The business side is arranged as six separate modules (HR and employee management, payroll, finance, CRM, inventory, procurement), each with its own permission (asked by your client, relayed in conversation, 3 November 2026). Beyond these choices, this half describes **what** each part must do. **How** to build it belongs to implementation planning.

### What changes and why

| Part | What it is for | Why it is being added or changed |
|---|---|---|
| One screen app for everyone, proposed | Screens built from coss UI parts that resize from phone to desktop. Each person sees only the screens their role and permissions allow. | Replaces the three giant files. It must also work inside the phone shell (the spec, page 18). |
| Median.co phone shell, proposed | Wraps the same screens as a phone app | Required by the spec, pages 3 and 18. The old system has none (C-19). |
| One front door for every request, proposed | The Laravel backend. Every request is checked for who is asking and what they may do before any work starts. | The old system checks unevenly (*Serious safety faults*, items 1 to 3, in [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md)) |
| One permission model, proposed | The five roles. Inside each gym, the owner creates their own named roles, each with a level per module, and gives them to employees ("dynamic RBAC"). The coach, front desk, finance and HR sets are only starting templates. Only the platform can create a platform administrator. | The spec, pages 7 and 9; your client asked for dynamic RBAC (asked by your client, relayed in conversation, 3 November 2026). It fixes C-08 and C-16. |
| Gym separation in one place, proposed | Every gym-owned record carries its gym. One shared rule limits every lookup to the person's own gym. | The spec, page 9. It fixes C-18. |
| The store, proposed | MySQL. A clean shape built from the spec, with every link between records enforced. No legacy duplicates. One staff record linked to one account. | Fixes the problems in *Where things are kept* in [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md) |
| Private file store, proposed | Documents, IDs, selfies, medical files and attachments. Readable only by people allowed to see them, through short-lived links. | The old public folder leaks private files (*Serious safety faults*, items 4 and 5) |
| Scheduled job runner, proposed | Runs timed jobs: session reminders, expiring memberships, follow-ups due, absence marking, low-stock checks | The spec, page 18, needs timed notices. The old system makes them only when a dashboard is opened. |
| Background sending line, proposed | Sends emails and notices without holding up the person | Old emails block every request |
| Live update channel, proposed | Pushes new notifications and booking changes to open screens straight away | The spec, pages 3 and 18, asks for live updates (C-20) |
| Payment connector, proposed | Records cash, card and e-wallet payments at the desk. Takes online payments for every fee through PayMongo (GCash, Maya, card), with every incoming payment message checked as genuine. | The spec, page 16. PayMongo is core at your client's request (asked by your client, relayed in conversation, 3 November 2026). Fixes the fake "paid" bookings (C-09). |
| Email service, proposed | Codes, resets and notices | Same job as before; secrets kept out of shared folders |
| Subscription plans and gym subscriptions, proposed | The administrator's plans, each with a price, inclusions and a list of modules switched on or off; each gym's trial end date, current plan and paid-until date; and a check, beside the permission check, that refuses a module the gym's plan does not include | Your client's request, `docs/prompts/P05.md` (relayed in conversation, 9 November 2026). Built in [PHASE 10](../05%20-%20ROADMAP/phase-10/PHASE%2010.md). Payments reuse the PayMongo connector. |
| Decision support, proposed | Two parts, both worked out by the backend from stored records. For athletes: nearest gyms and best matches. For owners: reports, trends and forecasts. | The spec, page 19, and your client's definition (asked by your client, relayed in conversation, 3 November 2026). The old version was screen-side only. |

### How work would pass between them

1. **Arriving.** A person opens the app, in a browser or inside the phone shell.
2. **Signing in.** They sign in and get a pass that expires.
3. **Every request.** It goes through the front door. The pass is checked first. Then the person's role and permission for that module are checked. Once Phase 10 is built, the gym's plan is also checked: a module the plan does not include is refused even for the owner. Then the gym rule limits what they can reach.
4. **The work.** The job does its work and writes to the store.
5. **Follow-on effects.** If the job affects other people (a booking approved, a schedule changed), it hands a notice to the background line. The line delivers the notice to the in-app list, sends an email where needed, and pushes it live to anyone with the app open.
6. **Timed notices.** The scheduled runner wakes on its own timetable and creates reminders the same way.
7. **Files.** They go to the private store. Screens get a short-lived link only when the person is allowed to see the file.

The proposed diagram in [04 - DIAGRAMS](../04%20-%20DIAGRAMS/04%20-%20DIAGRAMS.md) shows the same thing.

### What it would take to get there

1. **Foundations first.** Sign-in, the permission model and the gym rule come first, because every later part leans on them ([PHASE 1](../05%20-%20ROADMAP/phase-1/PHASE%201.md)). Adding gym separation afterwards is how the old system ended up with gaps.
2. **The private file store** must exist before gym applications open, because applications carry IDs and permits ([PHASE 2](../05%20-%20ROADMAP/phase-2/PHASE%202.md)).
3. **The notification line** is needed early. Many modules send notices; the announcement and reminder features themselves can come later ([PHASE 7](../05%20-%20ROADMAP/phase-7/PHASE%207.md)).
4. **What cannot be undone once started:** the shape of the store and the permission model. Changing either after real gyms have records means moving stored information into a new shape.

### What is being given up

- **The old system's code and screens.** Only reference value is kept. The rebuild starts from scratch (said in conversation, 3 November 2026).
- **The old stored information.** It is not carried over unless you decide otherwise (Q-08 in the [open questions](../00%20-%20START%20HERE/00%20-%20START%20HERE.md)).
- **Old features outside the spec.** Each is waiting on its own question (Q-01 to Q-07). Until answered, they are not built.
- **A choice of screen technology.** Moving from Vue to coss UI means nothing from the old screens can be reused directly. *Drawn from* the stack choice above and `frontend/package.json`.

## Added after P09 (12 November 2026)

**New records** (all per gym unless noted): `offers` and `offer_plan` (R-111); `purchase_order_proofs`, files on the private disk under `purchase-proofs/{gym}` (R-114); `inactivity_notices`, one row per quiet spell so each is told once (R-116); `coach_applications` (R-117); `training_goals` and `goal_milestones` (R-118); `injuries` and `injury_updates` (R-119); `equipment_checks` (R-120). New columns: `membership_plans.max_prepay_terms`, `prepay_discount_percent`; `memberships.locked_fee`, `prepaid_terms`, `voucher_code`, `offer_id`, `discount_amount`; `gyms.membership_reminder_days`, `inactivity_days`; `stock_items.safety_status`, `check_interval_days`, `next_check_on`, `check_reminded_for`.

**New rules in one place:** `App\Support\DateRange` (history filters), `MembershipPricing` (prepay math), `OfferApplier` (picks and applies the offer inside the approval transaction, with a row lock on the offer), `CoachScheduleConflicts` (reads every gym on purpose), `Reminders\InactivityMonitor`, `Reminders\EquipmentCheckReminders`. In-app notices for these features use one plain class, `App\Notifications\GymNotice`.

**New scheduled jobs** (in `backend/routes/console.php`): `app:monitor-inactivity` daily 08:20 and `app:remind-equipment-checks` daily 08:30. The daily `app:mark-staff-absences` run now also sends absence notices; backfills with dates stay quiet.

**New permissions** (catalog now 75 keys): `customers.offers.view|manage|approve`, `hr.coach_applications.view|approve`, `health_records.injuries.manage`, `inventory.safety.view|manage`. The HR starter role gets `hr.coach_applications.view` only; approving coach applications and offers stays with the owner unless granted.

Details per item: [PHASE 11](../05%20-%20ROADMAP/phase-11/PHASE%2011.md).

## Added after the feature change list, section A (13 November 2026)

**New columns:** `employee_profiles.sss_number`, `philhealth_number`, `pagibig_number` (R-121; stored encrypted with the app key and saved as digits only); `gyms.attendance_radius_m` (R-122; empty means no check); `staff_attendance.clock_in_latitude`, `clock_in_longitude`, `clock_in_distance_m` (R-122; only the distance is sent to screens); `training_sessions.thumbnail_path` (R-125). No new tables. Migrations are `2026_11_26_090000`, `2026_11_26_100000` and `2026_11_26_110000`.

**New helpers in `App\Support`:** `Geo::distanceKm` (the one map-distance formula, now used by gym search, recommendations and the clock-in check) and `Paging` (`rules()` and `rows()`; R-123).

**Opt-in paging.** An endpoint pages only when the request includes `?page=N`: 25 rows and a `meta` block (`current_page`, `last_page`, `per_page`, `total`). Without `page`, the answer is the same full list as before, so screens that need every row (the payment dialog's member list, the leave calendar, the owing-bookings list) keep working. Counts on paged screens use `meta.total`.

**Who sees full government IDs.** Anyone with `hr.staff.manage` or `payroll.runs.view`, and the employee on their own record. `finance.payments.view` does not. Others see `****` plus the last 4 digits. Only `hr.staff.manage` can change them. Both the staff update and the employee-record update validate the three numbers.

**Thumbnail files.** Session pictures sit on the public disk under `training-sessions/{gym}`. A file is deleted only when no session still uses it. They use their own upload and remove routes, like service covers.

**New dependency:** `jsqr` (frontend). It is loaded only when the browser has no QR reader of its own.

**No new permissions and no new scheduled jobs.** Keep the app key: the three ID columns cannot be read if it changes (see `docs/SETUPS/DEPLOY-HOSTINGER.md`).

Details per item: [PHASE 12](../05%20-%20ROADMAP/phase-12/PHASE%2012.md).
