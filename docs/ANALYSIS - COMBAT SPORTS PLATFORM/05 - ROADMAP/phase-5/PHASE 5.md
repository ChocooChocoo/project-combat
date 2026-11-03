# PHASE 5

**Sessions are scheduled without clashes, booked fairly, and attended**

[00 - START HERE](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · [05 - ROADMAP](../05%20-%20ROADMAP.md) · Previous: [PHASE 4](../phase-4/PHASE%204.md) · Next: [PHASE 6](../phase-6/PHASE%206.md)

**Status:** ⭕ Not started

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
| R-21, R-23, R-24, R-25, R-27 in [PHASE 3](../phase-3/PHASE%203.md) | Sessions need rooms and coaches; availability needs schedules, leave and holidays | ⭕ |
| R-33, R-36 in [PHASE 4](../phase-4/PHASE%204.md) | Only active, eligible members book | ⭕ |
| R-34 in [PHASE 4](../phase-4/PHASE%204.md) | Add-on fees are recorded as payments | ⭕ |
| Q-11, Q-12, Q-14, Q-18, Q-20, Q-24 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) | QR method, waiting-list offers, lateness, approval policy, refunds and self-cancelling | ⭕ |

## What it unlocks

- Payroll (R-59) uses coach sessions.
- Income (R-63) uses session payments.
- Session reminders (R-82) need bookings.
- The attendance, service-use and performance dashboards (R-85, R-87, R-88) need this phase's records.

## Where this phase stands

| Status | How many |
|---|---|
| ✅ Finished | 0 |
| 🟨 Being worked on | 0 |
| ⭕ Not started | 14 |
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
| R-42 | Create every kind of session | R-21, R-23 | ⭕ | Classes, group, private, sparring and open-mat sessions can be created with all their details |
| R-43 | Refuse clashes | R-42 | ⭕ | A session that clashes on coach, room or time cannot be saved |
| R-44 | Coach availability calendar | R-24, R-25, R-27, R-42 | ⭕ | Each coach's calendar shows hours, days off, leave and sessions |
| R-45 | Reschedule and cancel, with notices | R-42, R-10 | ⭕ | Changing or cancelling a session tells everyone booked and the coach |
| R-46 | Coach becomes unavailable | R-44, R-45 | ⭕ | Affected bookings are flagged for staff to reassign or move |

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

- [ ] The owner and permitted staff can create each session type.
- [ ] Each session has a coach, room, time, capacity, eligibility, content and fee.
- [ ] A repeating session creates every occurrence, and each occurrence can be changed on its own.
- [ ] Sessions link to the matching service in the gym's list (R-28).

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

- [ ] A session that overlaps the same coach's other session is refused.
- [ ] A session that overlaps another session in the same room is refused.
- [ ] A session outside the coach's working hours, on their approved leave or on a holiday is refused or clearly warned against.
- [ ] Two staff saving clashing sessions at the same moment cannot both succeed.

**Still open:** nothing.

**Old system:** `TrainerSessionController.php`, lines 659 to 669.

### R-44: Coach availability calendar

**In plain words:** Each coach has a calendar showing their working hours, days off, approved leave and the sessions already assigned. Bookings and scheduling use it to show only open slots.

**Why it is needed:** The spec, page 13, PF-5: check the trainer's availability before booking. The old system had none.

**Where the need came from:** D-30, D-31 and D-52.

**Depends on:** R-24, R-25, R-27 and R-42.

**Blockers:** none known.

**Done when:**

- [ ] A coach and permitted staff can see the coach's calendar.
- [ ] Approved leave and holidays appear on it automatically.
- [ ] When booking private coaching, the athlete sees only the coach's open slots.

**Still open:** nothing.

**Old system:** none.

### R-45: Reschedule and cancel, with notices

**In plain words:** Permitted staff can move or cancel a session. Everyone booked, and the coach, get a notice straight away.

**Why it is needed:** The spec, page 13 and the notice table on page 18. The old system notified members of cancellations but refunded nothing (`TrainerSessionController.php`, lines 455 to 517).

**Where the need came from:** D-28 and D-72.

**Depends on:** R-42 and R-10.

**Blockers:** Q-20 (refund rules when the gym cancels a paid session).

**Done when:**

- [ ] Moving a session runs the clash check again.
- [ ] Booked athletes and the coach get a notice when a session is moved or cancelled.
- [ ] Paid add-on bookings for a cancelled session follow the agreed refund rule.

**Still open:** Q-20.

**Old system:** as above.

### R-46: Coach becomes unavailable

**In plain words:** If a coach goes on leave or becomes unavailable, every affected booking is flagged. Staff then reassign a coach or move the session, and the athletes are told.

**Why it is needed:** The spec, page 13, section 3.3, and page 18 (coach reassignment notice).

**Where the need came from:** D-32 and D-72.

**Depends on:** R-44 and R-45.

**Blockers:** none known.

**Done when:**

- [ ] Approving a coach's leave flags every session of theirs in that period.
- [ ] Staff see a list of flagged sessions and can reassign a free coach or move the session.
- [ ] Athletes and the new coach get a notice of the change.

**Still open:** nothing.

**Old system:** none.

---

## Module: Booking

**Purpose:** let athletes book fairly, with full information and correct checks.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-47 | Session details page | R-42 | ⭕ | Every session has a details page with all the spec's information |
| R-48 | Book with checks | R-47, R-44, R-36 | ⭕ | A booking goes through only if places, coach and eligibility all check out |
| R-49 | Booking approval when the gym requires it | R-48, R-10 | ⭕ | If the gym needs confirmation, staff approve or reject, and the athlete is told |
| R-50 | Waiting list | R-48 | ⭕ | A full session puts the athlete on a waiting list, and a freed place goes to the next in line |
| R-51 | Add-on fees owed or paid, honestly | R-48, R-34 | ⭕ | A booking with a fee is marked "owed" until a real payment is recorded |

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

- [ ] Every session has a details page showing all the items above.
- [ ] Places left are always up to date.
- [ ] The page offers "Book" or "Join waiting list" as appropriate.

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

- [ ] A member without an active membership, or at the wrong level, is refused with the reason.
- [ ] A booking for a time when the coach is unavailable cannot be submitted.
- [ ] Two members booking the last place at the same moment cannot both get it.
- [ ] The member sees their bookings in one list.

**Still open:** Q-24 (can athletes cancel their own bookings, and until when?).

**Old system:** `backend/app/Http/Controllers/API/MemberTrainerSessionController.php`, lines 133 to 200; `backend/app/Http/Controllers/API/TrainerSessionRequestController.php` for private and sparring requests.

### R-49: Booking approval when the gym requires it

**In plain words:** Each gym can say whether bookings need staff confirmation, for all sessions or only some. When they do, a booking stays "requested" until a permitted staff member approves or rejects it. The athlete gets a notice either way.

**Why it is needed:** The spec, page 14 and page 18.

**Where the need came from:** D-36 and D-72.

**Depends on:** R-48 and R-10.

**Blockers:** Q-18 (how the gym's policy is set).

**Done when:**

- [ ] The owner can turn booking confirmation on or off.
- [ ] With it on, bookings wait for a staff decision, and staff see a list of them.
- [ ] The athlete gets a notice when the booking is approved or rejected.

**Still open:** Q-18.

**Old system:** only private and sparring requests needed approval (`TrainerSessionRequestController.php`, lines 236 to 431). Reject skipped the permission check that approve has.

### R-50: Waiting list

**In plain words:** If a session is full, the athlete can join its waiting list and gets a "waitlisted" notice. When a place frees up, it goes to the next athlete in line.

**Why it is needed:** The spec, page 13, section 3.2, and page 18 (waitlisted notice).

**Where the need came from:** D-29 and D-72.

**Depends on:** R-48.

**Blockers:** Q-12 (is the place given automatically, or offered with a time limit?).

**Done when:**

- [ ] Athletes can join and leave a full session's waiting list.
- [ ] The waiting list keeps the order people joined in.
- [ ] A freed place goes to the first athlete in line, who is notified.

**Still open:** Q-12.

**Old system:** none.

### R-51: Add-on fees owed or paid, honestly

**In plain words:** If a session costs extra (it is not included in the membership), the booking shows the fee as owed. It becomes paid only when a real payment is recorded at the desk or online.

**Why it is needed:** The spec, pages 14 and 16: session payments are income and must be recorded with details. The old system marked bookings "paid" with no money taken (C-09).

**Where the need came from:** D-33 (fee), D-53 and D-55.

**Depends on:** R-48 and R-34.

**Blockers:** none known. Online payment for add-ons is part of R-35 (Q-22 answered).

**Done when:**

- [ ] A booking for a paid session shows the amount owed.
- [ ] It turns "paid" only after a recorded payment.
- [ ] Unpaid add-ons appear in the gym's list of money owed (R-65).

**Still open:** nothing.

**Old system:** `MemberTrainerSessionController.php`, lines 185 to 199.

---

## Module: Attendance and progress

**Purpose:** record who actually came, and how they are progressing.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-52 | Check in by account or QR code | R-48 | ⭕ | A member can check in with their account or membership QR code |
| R-53 | Manual attendance by staff | R-48, R-08 | ⭕ | Permitted staff can confirm or enter attendance by hand |
| R-54 | Attendance history | R-52, R-53 | ⭕ | Check-in and out times, late arrivals, absences and no-shows are kept per member |
| R-55 | Training progress notes | R-42 | ⭕ | Coaches can write notes per session and per athlete |

### R-52: Check in by account or QR code

**In plain words:** On arrival, a member checks in using their account or their membership QR code. The check-in is tied to their booking or to the gym visit.

**Why it is needed:** The spec, page 14, section 3.6, and page 18 (check in through the phone shell). The old check-in was a button the member pressed on trust (C-11).

**Where the need came from:** D-37.

**Depends on:** R-48.

**Blockers:** Q-11 (who scans whom).

**Done when:**

- [ ] Each member has a membership QR code they can show from the app.
- [ ] Checking in with the QR code or account records the time against the right booking.
- [ ] A member cannot check in from home or for someone else.

**Still open:** Q-11.

**Old system:** `MemberTrainerSessionController.php`, lines 281 to 369.

### R-53: Manual attendance by staff

**In plain words:** A permitted staff member can confirm a check-in, or enter attendance by hand when needed, for example if the member's phone is dead.

**Why it is needed:** The spec, page 14.

**Where the need came from:** D-38.

**Depends on:** R-48 and R-08.

**Blockers:** none known.

**Done when:**

- [ ] Permitted staff can mark a member present, late or absent for a session.
- [ ] Each manual entry records who made it.

**Still open:** nothing.

**Old system:** none for members. The old trainer check-out marked every booking "attended" (`backend/app/Http/Controllers/API/TrainerSessionController.php`, lines 416 to 423).

### R-54: Attendance history

**In plain words:** For each gym, class and session, the system keeps every member's check-in and check-out times. It marks late arrivals and absences, and books with no attendance become no-shows.

**Why it is needed:** The spec, page 14, and page 15 (attendance is part of the athlete record).

**Where the need came from:** D-39 and D-46.

**Depends on:** R-52 and R-53.

**Blockers:** Q-14 (what counts as late).

**Done when:**

- [ ] Each attendance shows check-in and check-out times.
- [ ] Late arrivals are marked by a set rule.
- [ ] A booking with no check-in by the end of the session becomes a no-show.
- [ ] Staff can view attendance by gym, class, session or member; members see their own.

**Still open:** Q-14.

**Old system:** `combat_sports_db.sql`, line 4095 (booking check-in and check-out columns).

### R-55: Training progress notes

**In plain words:** Coaches write notes about a session as a whole and about each participant. Participant notes build up into the athlete's progress record.

**Why it is needed:** The spec, page 13, section 3.1, and page 15 (coach notes per session and over time).

**Where the need came from:** D-26 and D-46.

**Depends on:** R-42.

**Blockers:** none known.

**Done when:**

- [ ] A coach can add a note to a session and to each participant.
- [ ] An athlete's notes appear in their record over time (R-41).

**Still open:** nothing.

**Old system:** none.

---

## Phase blockers

Q-11, Q-12, Q-14, Q-18, Q-20 and Q-24 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md).

## Phase is finished when

Every item above is ✅, 🔵 or ⬜. No session can be saved with a clash. Athletes book only what they are eligible for, with full information, and check in properly. Every attendance is recorded.
