# PHASE 5

**Sessions are scheduled without clashes, booked fairly, and attended**

[00 - START HERE](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · [05 - ROADMAP](../05%20-%20ROADMAP.md) · Previous: [PHASE 4](../phase-4/PHASE%204.md) · Next: [PHASE 6](../phase-6/PHASE%206.md)

**Status:** ✅ Finished

> **5 November 2026:** Phase finished. The one check that needs a later phase (unpaid add-ons in the money-owed list) was moved to R-65 in PHASE 6. Building started the same day. Open questions answered in conversation, 5 November 2026:
>
> - **Q-11:** the member shows a QR code from the app that changes every minute; staff scan it or type its short code.
> - **Q-12:** a freed place goes automatically to the first athlete on the waiting list, who is notified.
> - **Q-14:** an athlete is late when they check in after the start time plus the gym's grace period (set by the owner, default 10 minutes).
> - **Q-18:** one gym-wide booking-approval switch, which each session can override.
> - **Q-20:** when the gym cancels a paid session, a full refund entry is recorded against the payment and staff hand the money back.
> - **Q-24:** athletes can cancel their own booking until a cut-off set by the owner (default 2 hours before the start); after that only staff can.
>
> Assumption: membership plans have no level, so "who may join" is a level label plus an optional list of allowed plans (empty means any active member).

Citation forms are the same as in [02 - FINDINGS](../../02%20-%20FINDINGS/02%20-%20FINDINGS.md).

## The goal

Staff, and the owner, create every kind of session. The system refuses clashes of coach, room or time. Each coach has an availability calendar. Athletes read a session's full details, then book. Bookings check places, the coach's availability and the athlete's eligibility, and need staff approval if the gym says so. Full sessions get a waiting list. Athletes check in by account or QR code, staff can enter attendance by hand, and coaches record progress notes.

## Why it comes here

Sessions need:

- rooms (R-21), coaches (R-22, R-23), and coach schedules, leave and holidays (R-24 to R-27) from [PHASE 3](../phase-3/PHASE%203.md);
- active members (R-33, R-36) from [PHASE 4](../phase-4/PHASE%204.md);
- the notification inbox (R-10) from [PHASE 1](../phase-1/PHASE%201.md) for booking notices.

## What it waits for

| Waits for | Why | Status |
|---|---|---|
| R-21, R-23, R-24, R-25, R-27 in [PHASE 3](../phase-3/PHASE%203.md) | Sessions need rooms and coaches; availability needs schedules, leave and holidays | ✅ |
| R-33, R-36 in [PHASE 4](../phase-4/PHASE%204.md) | Only active, eligible members book | ✅ |
| R-34 in [PHASE 4](../phase-4/PHASE%204.md) | Add-on fees are recorded as payments | ✅ |
| Q-11, Q-12, Q-14, Q-18, Q-20, Q-24 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) | QR method, waiting-list offers, lateness, approval policy, refunds and self-cancelling | ✅ Answered 5 November 2026 (see note above) |

## What it unlocks

- Payroll (R-59) uses coach sessions.
- Income (R-63) uses session payments.
- Session reminders (R-82) need bookings.
- The attendance, service-use and performance dashboards (R-85, R-87, R-88) need this phase's records.

## Where this phase stands

| Status | How many |
|---|---|
| ✅ Finished | 14 |
| 🟨 Being worked on | 0 |
| ⭕ Not started | 0 |
| ❌ Blocked | 0 |
| 🔵 Already there | 0 |
| ⬜ Dropped | 0 |
| ❓ Unclear | 0 |
| **Total** | **14** |

---

## Module: Scheduling

**Purpose:** plan sessions so that coaches, rooms and times never clash.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-42 | Create every kind of session | R-21, R-23 | ✅ | Classes, group, private, sparring and open-mat sessions can be created with all their details |
| R-43 | Refuse clashes | R-42 | ✅ | A session that clashes on coach, room or time cannot be saved |
| R-44 | Coach availability calendar | R-24, R-25, R-27, R-42 | ✅ | Each coach's calendar shows hours, days off, leave and sessions |
| R-45 | Reschedule and cancel, with notices | R-42, R-10 | ✅ | Changing or cancelling a session tells everyone booked and the coach |
| R-46 | Coach becomes unavailable | R-44, R-45 | ✅ | Affected bookings are flagged for staff to reassign or move |

### R-42: Create every kind of session

**In plain words:** A permitted staff member, or the owner, creates a session: a class, group session, private coaching, sparring, open mat, or another gym service. Each session gets:

- a coach and a room;
- a date and start and end time;
- a capacity;
- who may join (level and membership);
- the training content and the fee.

Sessions can repeat on a pattern.

**Why it is needed:** The spec, page 13, section 3.1. In the old system owners could not create sessions (C-12), sessions had no room, and there were only two types, class and coaching (`combat_sports_db.sql`, line 4030).

**Where the need came from:** D-24 and D-25.

**Depends on:** R-21 and R-23.

**Blockers:** none known.

**Done when:**

- [x] The owner and permitted staff can create each session type.
- [x] Each session has a coach, room, time, capacity, eligibility, content and fee.
- [x] A repeating session creates every occurrence, and each occurrence can be changed on its own.
- [x] Sessions link to the matching service in the gym's list (R-28).

**Status note (5 November 2026):** Built. `training_sessions` table, `TrainingSession` model, `Gym\SessionController` (routes `gyms/{gym}/sessions`; view needs `training,view`, changes need `training,manage`), Schedule screen (`GymSchedule.tsx`, `session-editor.tsx`). A repeat creates every occurrence with a shared series id; each occurrence is edited on its own. Gym settings added: booking approval, late grace minutes, cancel cut-off hours. Checked: `SessionTest` (19 tests), full suite 172 tests passed; `pnpm build` and `pnpm lint` passed; screens checked in a browser with mocked data.

**Still open:** nothing.

**Old system:** `backend/app/Http/Controllers/API/TrainerSessionController.php`, lines 118 to 284 and 579 to 657.

### R-43: Refuse clashes

**In plain words:** Before a session is saved, the system checks three things:

- the coach is not already busy at that time;
- the room is not already in use;
- the coach is available: inside their working hours, not on leave, and not on a holiday.

If anything clashes, it says what, and does not save.

**Why it is needed:** The spec, page 3 and page 13, section 3.2. The old check looked only at the same trainer's overlapping sessions (C-10).

**Where the need came from:** D-27.

**Depends on:** R-42.

**Blockers:** none known.

**Done when:**

- [x] A session that overlaps the same coach's other session is refused.
- [x] A session that overlaps another session in the same room is refused.
- [x] A session outside the coach's working hours, on their approved leave or on a holiday is refused or clearly warned against.
- [x] Two staff saving clashing sessions at the same moment cannot both succeed.

**Status note (5 November 2026):** Built in `App\Support\SessionScheduler`. It refuses a coach clash, a room clash, times outside working hours, approved leave, and gym or national holidays, with a message naming the clash. Saves lock the gym row inside a transaction, so two clashing saves run one after the other. Not proven by tests: SQLite (used by the tests) ignores row locks; MySQL honours them.

**Still open:** nothing.

**Old system:** `TrainerSessionController.php`, lines 659 to 669.

### R-44: Coach availability calendar

**In plain words:** Each coach has a calendar showing their working hours, days off, approved leave and the sessions already assigned. Bookings and scheduling use it to show only open slots.

**Why it is needed:** The spec, page 13, PF-5: check the trainer's availability before booking. The old system had none.

**Where the need came from:** D-30, D-31 and D-52.

**Depends on:** R-24, R-25, R-27 and R-42.

**Blockers:** none known.

**Done when:**

- [x] A coach and permitted staff can see the coach's calendar.
- [x] Approved leave and holidays appear on it automatically.
- [x] When booking private coaching, the athlete sees only the coach's open slots.

**Status note (5 November 2026):** Built. `Gym\CoachCalendarController`: `coaches/{user}/calendar` (self, `training,view` or `hr,view`) shows working hours, days off, approved leave, gym and national holidays and sessions; shown on the employee record and in My work. `coaches/{user}/open-slots` lists only free slots for private coaching. Checked in `BookingTest`.

**Still open:** nothing.

**Old system:** none.

### R-45: Reschedule and cancel, with notices

**In plain words:** Permitted staff can move or cancel a session. Everyone booked, and the coach, get a notice straight away.

**Why it is needed:** The spec, page 13 and the notice table on page 18. The old system notified members of cancellations but refunded nothing (`TrainerSessionController.php`, lines 455 to 517).

**Where the need came from:** D-28 and D-72.

**Depends on:** R-42 and R-10.

**Blockers:** Q-20 (refund rules when the gym cancels a paid session).

**Done when:**

- [x] Moving a session runs the clash check again.
- [x] Booked athletes and the coach get a notice when a session is moved or cancelled.
- [x] Paid add-on bookings for a cancelled session follow the agreed refund rule.

**Status note (5 November 2026):** Built. Moving a session or changing its room or coach reruns the clash check and notifies every requested, booked and waitlisted athlete and the coach (old and new) with `SessionChanged`. Raising capacity gives places to the waiting list; capacity cannot drop below places taken. `sessions/{session}/cancel` (`training,manage`, reason required) cancels all bookings and notifies everyone. Q-20 answered: each payment on a cancelled booking gets a full refund entry (same method, linked to the original) and staff hand the money back; no automatic PayMongo refund. Checked: `SessionChangeTest` (8 tests).

**Still open:** nothing.

**Old system:** as above.

### R-46: Coach becomes unavailable

**In plain words:** If a coach goes on leave or becomes unavailable, every affected booking is flagged. Staff then reassign a coach or move the session, and the athletes are told.

**Why it is needed:** The spec, page 13, section 3.3, and page 18 (coach reassignment notice).

**Where the need came from:** D-32 and D-72.

**Depends on:** R-44 and R-45.

**Blockers:** none known.

**Done when:**

- [x] Approving a coach's leave flags every session of theirs in that period.
- [x] Staff see a list of flagged sessions and can reassign a free coach or move the session.
- [x] Athletes and the new coach get a notice of the change.

**Status note (5 November 2026):** Built. Approving leave in `LeaveController` flags that coach's scheduled sessions in the leave dates and sends training managers one notice. The Schedule screen lists flagged sessions; staff reassign a free coach (`sessions/{session}/free-coaches`), move or cancel the session. A flagged session cannot be booked. Athletes and the new coach are notified. Checked in `SessionChangeTest`.

**Still open:** nothing.

**Old system:** none.

---

## Module: Booking

**Purpose:** let athletes book fairly, with full information and correct checks.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-47 | Session details page | R-42 | ✅ | Every session has a details page with all the spec's information |
| R-48 | Book with checks | R-47, R-44, R-36 | ✅ | A booking goes through only if places, coach and eligibility all check out |
| R-49 | Booking approval when the gym requires it | R-48, R-10 | ✅ | If the gym needs confirmation, staff approve or reject, and the athlete is told |
| R-50 | Waiting list | R-48 | ✅ | A full session puts the athlete on a waiting list, and a freed place goes to the next in line |
| R-51 | Add-on fees owed or paid, honestly | R-48, R-34 | ✅ | A booking with a fee is marked "owed" until a real payment is recorded |

### R-47: Session details page

**In plain words:** Before booking, an athlete sees a page with:

- the session's name and type, discipline and level;
- the coach's name, photo and short profile;
- the date, time, length and any repeat pattern;
- the room;
- the capacity and places left, or that a waiting list is open;
- the training content, requirements and what to bring;
- the fee, or "included in membership".

**Why it is needed:** The spec, pages 13 and 14, PF-6. The old system had no such page.

**Where the need came from:** D-33.

**Depends on:** R-42.

**Blockers:** none known.

**Done when:**

- [x] Every session has a details page showing all the items above.
- [x] Places left are always up to date.
- [x] The page offers "Book" or "Join waiting list" as appropriate.

**Status note (5 November 2026):** Built. `timetable/{session}` and the athlete screen `SessionDetails.tsx` show every item above, including the coach's public profile (hidden profiles stay private), places left and the right action (Book, Request booking, Join waiting list, Cancel). Places left are read fresh on each load and after each action.

**Still open:** nothing.

**Old system:** none.

### R-48: Book with checks

**In plain words:** A member books a class, service or private session. The system checks:

- a place is left;
- the coach is available;
- the member has an active membership at the right level.

Only then does the booking go through. A booking that clashes with the coach's schedule cannot be submitted.

**Why it is needed:** The spec, pages 13 and 14. The old booking checked only duplicates and capacity, without guarding against two people taking the last place at once (C-09).

**Where the need came from:** D-31, D-34 and D-35.

**Depends on:** R-47, R-44 and R-36.

**Blockers:** none known.

**Done when:**

- [x] A member without an active membership, or at the wrong level, is refused with the reason.
- [x] A booking for a time when the coach is unavailable cannot be submitted.
- [x] Two members booking the last place at the same moment cannot both get it.
- [x] The member sees their bookings in one list.

**Status note (5 November 2026):** Built in `App\Support\BookingDesk`: checks active membership, allowed plans, sessions left on a session pack, and coach availability, and gives the reason when it refuses. Booking locks the session row so two people cannot take the last place (not proven by tests: SQLite ignores row locks). Members see all their bookings at `/bookings`. Q-24 answered: athletes cancel their own booking until the gym's cut-off (default 2 hours); after that only staff. Checked: `BookingTest` (26 tests), full suite 198 tests passed.

**Still open:** Q-24 (can athletes cancel their own bookings, and until when?).

**Old system:** `backend/app/Http/Controllers/API/MemberTrainerSessionController.php`, lines 133 to 200; `backend/app/Http/Controllers/API/TrainerSessionRequestController.php` for private and sparring requests.

### R-49: Booking approval when the gym requires it

**In plain words:** Each gym can say whether bookings need staff confirmation, for all sessions or only some. When they do, a booking stays "requested" until a permitted staff member approves or rejects it. The athlete gets a notice either way.

**Why it is needed:** The spec, page 14 and page 18.

**Where the need came from:** D-36 and D-72.

**Depends on:** R-48 and R-10.

**Blockers:** Q-18 (how the gym's policy is set).

**Done when:**

- [x] The owner can turn booking confirmation on or off.
- [x] With it on, bookings wait for a staff decision, and staff see a list of them.
- [x] The athlete gets a notice when the booking is approved or rejected.

**Status note (5 November 2026):** Built. Q-18 answered: a gym-wide switch on the Setup screen, which each session can override. Requested bookings hold a place and show on the Bookings screen; `training,approve` approves or rejects (rejecting needs a reason); the athlete is notified either way.

**Still open:** nothing.

**Old system:** only private and sparring requests needed approval (`TrainerSessionRequestController.php`, lines 236 to 431). Reject skipped the permission check that approve has.

### R-50: Waiting list

**In plain words:** If a session is full, the athlete can join its waiting list and gets a "waitlisted" notice. When a place frees up, it goes to the next athlete in line.

**Why it is needed:** The spec, page 13, section 3.2, and page 18 (waitlisted notice).

**Where the need came from:** D-29 and D-72.

**Depends on:** R-48.

**Blockers:** Q-12 (is the place given automatically, or offered with a time limit?).

**Done when:**

- [x] Athletes can join and leave a full session's waiting list.
- [x] The waiting list keeps the order people joined in.
- [x] A freed place goes to the first athlete in line, who is notified.

**Status note (5 November 2026):** Built. Q-12 answered: a freed place goes automatically to the first athlete in line, who is notified. The waiting list is ordered by when each athlete joined; the next athlete is checked for eligibility again before being given the place.

**Still open:** nothing.

**Old system:** none.

### R-51: Add-on fees owed or paid, honestly

**In plain words:** If a session costs extra (it is not included in the membership), the booking shows the fee as owed. It becomes paid only when a real payment is recorded at the desk or online.

**Why it is needed:** The spec, pages 14 and 16: session payments are income and must be recorded with details. The old system marked bookings "paid" with no money taken (C-09).

**Where the need came from:** D-33 (fee), D-53 and D-55.

**Depends on:** R-48 and R-34.

**Blockers:** none known. Online payment for add-ons is part of R-35 (Q-22 answered).

**Done when:**

- [x] A booking for a paid session shows the amount owed.
- [x] It turns "paid" only after a recorded payment.
- [x] Session add-ons and private coaching can be paid online through PayMongo, reusing the payment record and checkout flow from R-35 ([PHASE 4](../phase-4/PHASE%204.md)). *(Moved from R-35 on 5 November 2026.)*
*Moved on 5 November 2026:* unpaid add-ons appearing in the gym's list of money owed is checked in R-65 ([PHASE 6](../phase-6/PHASE%206.md)), which builds that list.

**Status note (5 November 2026):** Built. `payments.session_booking_id` links a payment to a booking; `BookingLedger` records it with a receipt. A booking shows included, owed, paid or refunded from real payment entries only. Desk payments (`finance,manage`) and online PayMongo checkout (`my-bookings/{booking}/checkout`, webhook shared with memberships) both work; membership payments behave as before. Checked: `BookingPaymentTest` (7 tests), full suite 213 tests passed, `pnpm build` and `pnpm lint` passed.

**Still open:** nothing.

**Old system:** `MemberTrainerSessionController.php`, lines 185 to 199.

---

## Module: Attendance and progress

**Purpose:** record who actually came, and how they are progressing.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-52 | Check in by account or QR code | R-48 | ✅ | A member can check in with their account or membership QR code |
| R-53 | Manual attendance by staff | R-48, R-08 | ✅ | Permitted staff can confirm or enter attendance by hand |
| R-54 | Attendance history | R-52, R-53 | ✅ | Check-in and out times, late arrivals, absences and no-shows are kept per member |
| R-55 | Training progress notes | R-42 | ✅ | Coaches can write notes per session and per athlete |

### R-52: Check in by account or QR code

**In plain words:** On arrival, a member checks in using their account or their membership QR code. The check-in is tied to their booking or to the gym visit.

**Why it is needed:** The spec, page 14, section 3.6, and page 18 (check in through the phone shell). The old check-in was a button the member pressed on trust (C-11).

**Where the need came from:** D-37.

**Depends on:** R-48.

**Blockers:** Q-11 (who scans whom).

**Done when:**

- [x] Each member has a membership QR code they can show from the app.
- [x] Checking in with the QR code or account records the time against the right booking.
- [x] A member cannot check in from home or for someone else.

**Status note (5 November 2026):** Built. Q-11 answered: the member shows a QR code with a short code from the app (Sessions and My bookings screens); it changes every minute (`App\Support\CheckInCode`, signed with the app key). Only staff with `training,manage` record check-ins, on the Check-in screen (camera scan where the browser supports it, typed code or USB scanner, or account search), so a member cannot check in from home or for someone else. A check-in is tied to the booking open at that time, or recorded as a gym visit. Session packs lose one session on check-in and get it back if staff mark the booking absent. Checked: `AttendanceTest`. Not checked: a real camera and a real USB scanner.

**Still open:** nothing.

**Old system:** `MemberTrainerSessionController.php`, lines 281 to 369.

### R-53: Manual attendance by staff

**In plain words:** A permitted staff member can confirm a check-in, or enter attendance by hand when needed, for example if the member's phone is dead.

**Why it is needed:** The spec, page 14.

**Where the need came from:** D-38.

**Depends on:** R-48 and R-08.

**Blockers:** none known.

**Done when:**

- [x] Permitted staff can mark a member present, late or absent for a session.
- [x] Each manual entry records who made it.

**Status note (5 November 2026):** Built. `bookings/{booking}/attendance` (`training,manage`) marks present, late or absent; check-out is separate. Each entry stores who made it, shown on the session roster. Checked in `AttendanceTest`.

**Still open:** nothing.

**Old system:** none for members. The old trainer check-out marked every booking "attended" (`backend/app/Http/Controllers/API/TrainerSessionController.php`, lines 416 to 423).

### R-54: Attendance history

**In plain words:** For each gym, class and session, the system keeps every member's check-in and check-out times. It marks late arrivals and absences, and books with no attendance become no-shows.

**Why it is needed:** The spec, page 14, and page 15 (attendance is part of the athlete record).

**Where the need came from:** D-39 and D-46.

**Depends on:** R-52 and R-53.

**Blockers:** Q-14 (what counts as late).

**Done when:**

- [x] Each attendance shows check-in and check-out times.
- [x] Late arrivals are marked by a set rule.
- [x] A booking with no check-in by the end of the session becomes a no-show.
- [x] Staff can view attendance by gym, class, session or member; members see their own.
- [x] An athlete's attendance appears in their record (R-41 in [PHASE 4](../phase-4/PHASE%204.md)). *(Moved from R-41 on 5 November 2026.)*

**Status note (5 November 2026):** Built. Q-14 answered: late means checking in after the start time plus the gym's grace period (Setup screen, default 10 minutes). `app:mark-no-shows` runs every 15 minutes and marks bookings with no check-in after the session ended. The Attendance screen (`training,view`) filters by date, session, class and member; attendance and its counts appear in the athlete record for staff and for the athlete. Checked: `AttendanceTest` (17 tests).

**Still open:** nothing.

**Old system:** `combat_sports_db.sql`, line 4095 (booking check-in and check-out columns).

### R-55: Training progress notes

**In plain words:** Coaches write notes about a session as a whole and about each participant. Participant notes build up into the athlete's progress record.

**Why it is needed:** The spec, page 13, section 3.1, and page 15 (coach notes per session and over time).

**Where the need came from:** D-26 and D-46.

**Depends on:** R-42.

**Blockers:** none known.

**Done when:**

- [x] A coach can add a note to a session and to each participant.
- [x] An athlete's notes appear in their record over time (R-41 in [PHASE 4](../phase-4/PHASE%204.md)). *(Moved from R-41 on 5 November 2026.)*

**Status note (5 November 2026):** Built. `session_notes` table; the session's coach or `training,manage` adds a whole-session note or a note for a booked participant on the session screen. Participant notes appear in the athlete record over time; whole-session notes stay staff-only. Checked: `ProgressNoteTest` (4 tests), full suite 234 tests passed, `pnpm build` and `pnpm lint` passed. Seed data now has 30 sample sessions (6 per approved gym).

**Still open:** nothing.

**Old system:** none.

---

## Phase blockers

Q-11, Q-12, Q-14, Q-18, Q-20 and Q-24 were answered on 5 November 2026 (see the note at the top). R-51's money-owed check was moved to R-65 in [PHASE 6](../phase-6/PHASE%206.md).

## Phase is finished when

Every item above is ✅, 🔵 or ⬜. No session can be saved with a clash. Athletes book only what they are eligible for, with full information, and check in properly. Every attendance is recorded.

## Where it was built

- **Backend:** `SessionController`, `BookingController`, `CoachCalendarController`, `AttendanceController`, `SessionNoteController`, `MyBookingController`; `SessionScheduler`, `BookingDesk`, `BookingLedger`, `AttendanceLedger`, `CheckInCode`; migrations `2026_11_07_100000` to `2026_11_07_130000`; `app:mark-no-shows` in `routes/console.php`.
- **Screens:** staff Schedule, Session (roster, attendance, notes), Bookings, Check-in, Attendance, coach calendar on the employee record and My work; athlete Sessions, Session details, Private coaching, My bookings, check-in code, attendance and notes in My record.
- **Permissions used:** `training` (view: schedules and attendance; manage: sessions, check-in, attendance, notes; approve: booking requests), `finance,manage` for add-on payments.
- **Checks (5 November 2026):** 234 tests, 1,904 assertions passed; `pnpm build` passed; `pnpm lint` no errors; `migrate:fresh --seed` ran on the local database. Screens checked in a browser with mocked data.
- **Live browser test (5 November 2026):** every flow was run against the real backend. Fixed afterwards: the app clock now uses Philippine time (Asia/Manila), so check-in attaches to the right booking and the cancel cut-off works; only staff with a coach profile are listed as coaches; the Front Desk template gets `training,manage` so it can check members in; cancelled bookings no longer show money owed; private coaching has no waiting list; a membership can no longer be overpaid at the desk; several wording fixes. Rechecked live: QR check-in recorded "Present" against the booking at 3:26 PM, and self-cancel inside the cut-off was refused. 241 tests passed.
- **Not checked:** row locks under real MySQL load (tests use SQLite), real PayMongo payments, a real camera or USB scanner, and live end-to-end browser use.
