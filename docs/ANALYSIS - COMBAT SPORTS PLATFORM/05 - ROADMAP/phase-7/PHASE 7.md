# PHASE 7

**Everyone stays informed, on their phone too**

[00 - START HERE](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · [05 - ROADMAP](../05%20-%20ROADMAP.md) · Previous: [PHASE 6](../phase-6/PHASE%206.md) · Next: [PHASE 8](../phase-8/PHASE%208.md)

**Status:** ✅ Finished

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
| R-10 in [PHASE 1](../phase-1/PHASE%201.md) | Every notice lands in the inbox | ✅ |
| R-33, R-36 in [PHASE 4](../phase-4/PHASE%204.md) | Members to announce to; memberships that expire | ✅ |
| R-42, R-48 in [PHASE 5](../phase-5/PHASE%205.md) | Classes to target; bookings to remind about | ✅ |
| R-69, R-71 in [PHASE 6](../phase-6/PHASE%206.md) | Follow-ups to remind about; customer groups to target | ✅ |
| Q-23 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) | What platform notices are for | ✅ Answered 6 November 2026 |

## What it unlocks

Nothing else waits on this phase. It completes the spec's communication module (Module 6).

## Where this phase stands

| Status | How many |
|---|---|
| ✅ Finished | 5 |
| 🟨 Being worked on | 0 |
| ⭕ Not started | 0 |
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
| R-80 | Targeted announcements and events | R-33, R-10 | ✅ | A gym can announce to all members, one class or a customer group |
| R-81 | Platform notices | R-07, R-10 | ✅ | The administrator can send a notice to platform users |
| R-82 | Scheduled reminders | R-36, R-48, R-69 | ✅ | Session, expiry and follow-up reminders go out on time, on their own |

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

- [x] A permitted person can post an announcement to all members.
- [x] The audience can be narrowed to one class or one customer group.
- [x] Events can be announced with a date and place.
- [x] Only the chosen audience receives it.

**Still open:** nothing.

**Status note (7 November 2026):** Built as S-35 with one `announcements` table and a scoped audience resolver. Packet 7A tests cover all three audiences, empty audiences, cross-gym ids and separate view/manage permissions. Packet 7F browser checks: owner sent all-members (14 recipients), one class (session 25, 1 booked recipient) and a customer-group event (3 linked recipients, 1 unlinked customer counted as skipped). The expired athlete did not receive the all-members notice; the waitlisted athlete did not receive the class notice; both received the platform-wide all-users notice. The class, group and all-members notices appeared in an active member's inbox with the event date and place.

**Old system:** `backend/app/Http/Controllers/AnnouncementController.php`. It found the gym through links that do not exist, so owners got "no training center found" (lines 130 to 132).

### R-81: Platform notices

**In plain words:** The platform administrator can send a notice to platform users, for example about planned maintenance.

**Why it is needed:** The access table gives the administrator "platform notices" in the communication module (the spec, page 7).

**Where the need came from:** D-74.

**Depends on:** R-07 and R-10.

**Blockers:** none. Q-23 answered 6 November 2026: platform-wide updates, sent to owners of approved gyms (default) or all verified users. Exclude the sender and carry no gym data.

**Done when:**

- [x] The administrator can write a notice and send it.
- [x] Recipients see it in their inbox.
- [x] The notice carries no gym's private information.

**Still open:** nothing for R-81.

**Status note (7 November 2026):** Built as S-04 with a gym-less `platform_notices` table and the audience picker answered under Q-23. Packet 7B tests cover admin-only access, owner deduplication, verified-user filtering, sender exclusion, empty audiences, pagination and a gym-free payload. Packet 7F browser checks: the administrator sent an owners notice (5 recipients) and an all-users notice (117 recipients). An owner received both; a member received only the all-users notice; a non-admin was redirected from `/admin/notices` and the API returned 403. Stored notification payloads contain only `title`, `message` and `notice_id` — no `gym_id`, gym name or address.

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

- [x] Booked athletes get a reminder a set time before each session, even if nobody opens the app.
- [x] Members get a reminder a set number of days before their membership expires. *(Moved from R-36 in [PHASE 4](../phase-4/PHASE%204.md) on 5 November 2026; the expiry itself and its notice are already built there.)*
- [x] The assigned staff member is reminded when a follow-up is due.
- [x] Nobody gets the same reminder twice.

**Still open:** nothing. Planning settled the timings: session reminders default to 60 minutes, with a per-gym setting of 30, 60, 120 or 1440; expiry reminders go at 7, 3 and 1 days; follow-ups are due at `due_at` with a 24-hour lookback.

**Status note (7 November 2026):** Three scheduled commands run with `withoutOverlapping()`: sessions every 5 minutes, membership expiry daily at 08:00, follow-ups every 15 minutes. Packet 7C tests cover timing, one-send idempotency, catch-up, the per-gym lead time, frozen and session-plan skips, and the reassignment and due-date resets. Packet 7F command checks: a temporary session in the lead window sent 1 reminder, then 0; rescheduling cleared the marker and the next run sent 1, then 0. A temporary membership sent at 7, 3 and 1 days and stayed silent on a repeat run; a second membership with no ledger caught up once at 2 days left. A due follow-up sent once to the assignee, stayed silent on repeat, re-armed on reassignment and on a due-date change, and sent again to the new assignee. `schedule:list` shows `*/5`, `0 8` and `*/15`.

**Old system:** as above.

---

## Module: Live updates and mobile access

**Purpose:** make notices arrive at once, and give athletes a phone app.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-83 | Live delivery of notices | R-10 | ✅ | A new notice appears on an open screen without refreshing |
| R-84 | Median.co phone app | R-48, R-52 | ✅ | Athletes can do the athlete tasks in the phone-ready web app; Median build and install are external |

### R-83: Live delivery of notices

**In plain words:** When a notice is created, anyone who has the app open sees it at once. They do not need to refresh. Booking changes and places left also update live.

**Why it is needed:** The spec, pages 3, 4 and 18: "real-time updates". The old system loaded notices once per page (C-20).

**Where the need came from:** D-73.

**Depends on:** R-10.

**Blockers:** none known.

**Done when:**

- [x] A notice sent to a person appears on their open screen within seconds.
- [x] Places left on a session details page update without refreshing.

**Still open:** nothing. Push notices while the app is closed are deferred; the spec asks for live updates while the screen is open, which visibility-aware polling provides.

**Status note (7 November 2026):** One shared notification provider polls the unread count every 15 seconds while visible and refreshes on focus or visibility. The inbox, timetable, session details, my bookings and the staff booking and session screens use the shared polling hook. Packet 7D covers the screens and hidden-tab behaviour. Packet 7F browser checks: a new all-members announcement appeared in an open member inbox 4.8 seconds after send without a reload, and the badge moved 33 → 34; cancelling a booked place in a staff screen changed the open session page from 5 to 6 places left in 11.2 seconds without a reload.

**Old system:** none.

### R-84: Median.co phone app

**In plain words:** The same website is wrapped as a phone app with Median.co. Through it, athletes can apply for membership, see schedules, book sessions, check in and get live updates.

**Why it is needed:** The spec, page 3, page 4 and page 18, section 6.3. The old system had no phone app (C-19).

**Where the need came from:** D-73.

**Depends on:** R-48 and R-52. All screens must also fit a phone, which is a rule for every phase (*drawn from* the spec, page 18: "container of the responsive web application").

**Blockers:** none known.

**Done when:**

- [x] The setup document gives the start URL and configuration to create the build; build, install and store are external and not done.
- [x] Every athlete task (apply, schedules, booking, QR check-in and live notices) works in the web app and passes the phone-width audit; the installed-app transaction check remains external.
- [ ] The phone app is installed and opens the platform (external, evidence pending).
- [x] Every athlete screen fits a phone screen without sideways scrolling.

**Still open:** nothing for the repository. The Median build, install and store checks need the operator's Median account, public HTTPS URLs, signing credentials and physical devices; they are recorded as external steps in `median-co-setup.md`.

**Status note (7 November 2026):** R-84 is ✅ for repository scope under the locked Median decision; the installed-app check is unverified and the external box stays open. Packet 7E added `viewport-fit=cover`, theme and Apple mobile metadata, safe-area padding and card layouts where tables used to scroll sideways. Packet 7F checked the shipped `index.html` against the Median setup document: title `Combat Platform`, theme `#0a0a0a`, viewport with `viewport-fit=cover`, Apple web-app title and status bar all match. The athlete screen audit at 360 px and 390 px covered home, profile, notifications, public gym, membership application, memberships, membership detail, bookings, timetable, session details, private coaching, my record, applications and owner application; all had zero horizontal overflow. The installed-app and store steps remain external and are not claimed as done.

**Old system:** none.

---

## Phase blockers

No open platform-notice audience question. Q-23 was answered on 6 November 2026.

## Build record

- 7 November 2026: Packet 7F closed Phase 7. All five requirements are ✅ with dated notes above. Full gates: `php artisan test` 520 tests / 26,513 assertions; `php artisan migrate:fresh --seed` on MySQL with 10 demo announcements, 1 platform notice and `jobs = 0`; `php artisan app:sync-permissions`; `php artisan schedule:list` showing the three reminders; `pnpm lint`, `pnpm build` and `node --test` (6 passed). The four new migrations rolled back and re-applied on a throwaway MySQL database. Browser matrix: all-members, class, customer-group and event announcements; front-desk send allowed and coach denied; expired member excluded; platform notices to owners and all users; non-admin denied; live notice 4.8 seconds and live places-left 11.2 seconds; athlete screens at 360 px and 390 px with no horizontal overflow. Median build/install/store checks remain external user steps. No commit.
- 6 November 2026: Packet 7B records Q-23 as answered. Platform notices target deduplicated owners of approved gyms or all verified users, excluding the sender. Delivery uses gym-less database notifications sent synchronously in chunks of 200. Packet checks and browser evidence are tracked in [phase-3.md](../../../implementation-plan/roadmap-phase-7/phase-3.md); phase-wide close-out remains Packet 7F.

## Phase is finished when

Every item above is ✅, 🔵 or ⬜. The right people get the right announcements and reminders on time, notices arrive live, and athletes can do everything they need from the phone app.
