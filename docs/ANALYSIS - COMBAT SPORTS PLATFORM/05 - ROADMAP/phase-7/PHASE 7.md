# PHASE 7

**Everyone stays informed, on their phone too**

[00 - START HERE](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · [05 - ROADMAP](../05%20-%20ROADMAP.md) · Previous: [PHASE 6](../phase-6/PHASE%206.md) · Next: [PHASE 8](../phase-8/PHASE%208.md)

**Status:** ⭕ Not started

Citation forms are the same as in [02 - FINDINGS](../../02%20-%20FINDINGS/02%20-%20FINDINGS.md).

## The goal

Gyms can send announcements to all members, to one class, or to a customer group. The platform administrator can send platform notices. Timed reminders go out on their own: session reminders, expiring memberships and follow-ups due. Notices arrive live, without refreshing. Athletes can use the whole platform from a phone app wrapped by Median.co.

## Why it comes here

The phase can start once [PHASE 4](../phase-4/PHASE%204.md) is done, alongside Phases 5 and 6, because announcements to members need members (R-33). Each item still waits for the specific thing it announces or reminds about:

- class announcements need sessions (R-42);
- customer-group announcements need customer groups (R-71);
- follow-up reminders need follow-ups (R-69).

## What it waits for

| Waits for | Why | Status |
|---|---|---|
| R-10 in [PHASE 1](../phase-1/PHASE%201.md) | Every notice lands in the inbox | ⭕ |
| R-33, R-36 in [PHASE 4](../phase-4/PHASE%204.md) | Members to announce to; memberships that expire | ✅ |
| R-42, R-48 in [PHASE 5](../phase-5/PHASE%205.md) | Classes to target; bookings to remind about | ⭕ |
| R-69, R-71 in [PHASE 6](../phase-6/PHASE%206.md) | Follow-ups to remind about; customer groups to target | ⭕ |
| Q-23 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) | What platform notices are for | ⭕ |

## What it unlocks

Nothing else waits on this phase. It completes the spec's communication module (Module 6).

## Where this phase stands

| Status | How many |
|---|---|
| ✅ Finished | 0 |
| 🟨 Being worked on | 0 |
| ⭕ Not started | 5 |
| ❌ Blocked | 0 |
| 🔵 Already there | 0 |
| ⬜ Dropped | 0 |
| ❓ Unclear | 0 |
| **Total** | **5** |

---

## Module: Announcements and reminders

**Purpose:** let gyms and the platform reach the right people, and send reminders on time without anyone pressing a button.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-80 | Targeted announcements and events | R-33, R-10 | ⭕ | A gym can announce to all members, one class or a customer group |
| R-81 | Platform notices | R-07, R-10 | ⭕ | The administrator can send a notice to platform users |
| R-82 | Scheduled reminders | R-36, R-48, R-69 | ⭕ | Session, expiry and follow-up reminders go out on time, on their own |

### R-80: Targeted announcements and events

**In plain words:** A permitted staff member writes an announcement, or an event such as a fight night or seminar, and chooses who receives it:

- all members;
- the members booked on one class;
- a customer group from the customer records.

Recipients get it in their inbox.

**Why it is needed:** The spec, page 18, section 6.1. The old system sent announcements only to certain staff roles and the owner, never to members (C-15).

**Where the need came from:** D-70 and D-71.

**Depends on:** R-33 and R-10. Class targeting also needs R-42; group targeting needs R-71.

**Blockers:** none known.

**Done when:**

- [ ] A permitted person can post an announcement to all members.
- [ ] The audience can be narrowed to one class or one customer group.
- [ ] Events can be announced with a date and place.
- [ ] Only the chosen audience receives it.

**Still open:** nothing.

**Old system:** `backend/app/Http/Controllers/AnnouncementController.php`. It found the gym through links that do not exist, so owners got "no training center found" (lines 130 to 132).

### R-81: Platform notices

**In plain words:** The platform administrator can send a notice to platform users, for example about planned maintenance.

**Why it is needed:** The access table gives the administrator "platform notices" in the communication module (the spec, page 7).

**Where the need came from:** D-74.

**Depends on:** R-07 and R-10.

**Blockers:** Q-23 (what platform notices are for, and who receives them).

**Done when:**

- [ ] The administrator can write a notice and send it.
- [ ] Recipients see it in their inbox.
- [ ] The notice carries no gym's private information.

**Still open:** Q-23.

**Old system:** none.

### R-82: Scheduled reminders

**In plain words:** On its own timetable, the system sends:

- reminders to athletes booked on an upcoming session;
- reminders to members whose membership is about to expire;
- reminders to staff when a customer follow-up is due.

**Why it is needed:** The spec, page 15 (renewal reminders) and page 18 (session reminders, expiring memberships, follow-up due). The old system created some notices only when someone happened to open a dashboard (`backend/app/Http/Controllers/DashboardController.php`, lines 597 to 790).

**Where the need came from:** D-45 and D-72.

**Depends on:** R-36, R-48 and R-69.

**Blockers:** none known.

**Done when:**

- [ ] Booked athletes get a reminder a set time before each session, even if nobody opens the app.
- [ ] Members get a reminder a set number of days before their membership expires. *(Moved from R-36 in [PHASE 4](../phase-4/PHASE%204.md) on 5 November 2026; the expiry itself and its notice are already built there.)*
- [ ] The assigned staff member is reminded when a follow-up is due.
- [ ] Nobody gets the same reminder twice.

**Still open:** how long before each reminder is sent. The old system warned at 14, 7 and 3 days before expiry (`backend/app/Models/MembershipApplication.php`, lines 82 to 127). It should be decided during implementation planning.

**Old system:** as above.

---

## Module: Live updates and mobile access

**Purpose:** make notices arrive at once, and give athletes a phone app.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-83 | Live delivery of notices | R-10 | ⭕ | A new notice appears on an open screen without refreshing |
| R-84 | Median.co phone app | R-48, R-52 | ⭕ | Athletes can install the phone app and do the athlete tasks in it |

### R-83: Live delivery of notices

**In plain words:** When a notice is created, anyone who has the app open sees it at once. They do not need to refresh. Booking changes and places left also update live.

**Why it is needed:** The spec, pages 3, 4 and 18: "real-time updates". The old system loaded notices once per page (C-20).

**Where the need came from:** D-73.

**Depends on:** R-10.

**Blockers:** none known.

**Done when:**

- [ ] A notice sent to a person appears on their open screen within seconds.
- [ ] Places left on a session details page update without refreshing.

**Still open:** whether phone push notices, shown while the app is closed, are wanted is not stated. The spec says "real-time updates" only. It should be decided during implementation planning.

**Old system:** none.

### R-84: Median.co phone app

**In plain words:** The same website is wrapped as a phone app with Median.co. Through it, athletes can apply for membership, see schedules, book sessions, check in and get live updates.

**Why it is needed:** The spec, page 3, page 4 and page 18, section 6.3. The old system had no phone app (C-19).

**Where the need came from:** D-73.

**Depends on:** R-48 and R-52. All screens must also fit a phone, which is a rule for every phase (*drawn from* the spec, page 18: "container of the responsive web application").

**Blockers:** none known.

**Done when:**

- [ ] The phone app can be installed and opens the platform.
- [ ] An athlete can apply, see schedules, book, check in with their QR code and get live notices, all inside the app.
- [ ] Every athlete screen fits a phone screen without sideways scrolling.

**Still open:** nothing.

**Old system:** none.

---

## Phase blockers

Q-23 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md).

## Phase is finished when

Every item above is ✅, 🔵 or ⬜. The right people get the right announcements and reminders on time, notices arrive live, and athletes can do everything they need from the phone app.
