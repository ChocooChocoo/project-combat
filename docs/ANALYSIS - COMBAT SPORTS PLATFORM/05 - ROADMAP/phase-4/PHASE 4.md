# PHASE 4

**Athletes can join a gym and have a complete record**

[00 - START HERE](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · [05 - ROADMAP](../05%20-%20ROADMAP.md) · Previous: [PHASE 3](../phase-3/PHASE%203.md) · Next: [PHASE 5](../phase-5/PHASE%205.md)

**Status:** ⭕ Not started

Citation forms are the same as in [02 - FINDINGS](../../02%20-%20FINDINGS/02%20-%20FINDINGS.md).

## The goal

A platform user applies to join a gym on a chosen plan, declares their health and signs the waivers. Staff approve or reject the application. Payments are recorded properly, whether cash, card or e-wallet. The membership then lives a proper life: it can be renewed, frozen or cancelled, and it expires on time. Each athlete has a record of rank, competitions, achievements and health details, and can see their own.

## Why it comes here

Members join on a plan (R-29 in [PHASE 3](../phase-3/PHASE%203.md)). Staff need approval permissions (R-08 in [PHASE 1](../phase-1/PHASE%201.md)). Bookings in [PHASE 5](../phase-5/PHASE%205.md) need active members (R-33, R-36).

## What it waits for

| Waits for | Why | Status |
|---|---|---|
| R-29 in [PHASE 3](../phase-3/PHASE%203.md) | Members choose a plan | ⭕ |
| R-30 in [PHASE 3](../phase-3/PHASE%203.md) | Members find the gym first | ⭕ |
| R-15 in [PHASE 2](../phase-2/PHASE%202.md) | Health files are kept privately | ⭕ |
| Q-10 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) | Whether an athlete can be a member of two gyms | ⭕ |

## What it unlocks

- Booking eligibility (R-48) needs active memberships.
- Income records (R-63) need membership payments (R-34).
- Expiry reminders (R-82) need the membership lifecycle (R-36).
- [PHASE 7](../phase-7/PHASE%207.md) can start once this phase is done.

## Where this phase stands

| Status | How many |
|---|---|
| ✅ Finished | 0 |
| 🟨 Being worked on | 0 |
| ⭕ Not started | 11 |
| ❌ Blocked | 0 |
| 🔵 Already there | 0 |
| ⬜ Dropped | 0 |
| ❓ Unclear | 0 |
| **Total** | **11** |

---

## Module: Membership

**Purpose:** take a person from applicant to active member, and keep their membership and payments in order.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-31 | Apply for membership | R-29 | ⭕ | A platform user can apply to a gym on a chosen plan |
| R-32 | Health questionnaire and waivers | R-31 | ⭕ | The application includes a health declaration and signed waivers |
| R-33 | Approve or reject; athlete profile created | R-31, R-10 | ⭕ | Permitted staff decide, and approval creates the athlete profile |
| R-34 | Record membership payments | R-31 | ⭕ | Cash, card and e-wallet payments are recorded with a receipt |
| R-35 | Pay any fee online through PayMongo | R-34 | ⭕ | Any fee can be paid online, and only genuine payments count |
| R-36 | Membership life: activate, renew, freeze, expire, cancel | R-33 | ⭕ | Each change follows the plan's policy and is kept in history |

### R-31: Apply for membership

**In plain words:** From a gym's page, a platform user applies to join. They pick a plan and give:

- their emergency contacts;
- their combat sports background;
- any current rank;
- their training history.

**Why it is needed:** The spec, page 15, section 4.1.

**Where the need came from:** D-40 and D-42.

**Depends on:** R-29.

**Blockers:** Q-10.

**Done when:**

- [ ] A signed-in user can apply to a gym from its page.
- [ ] The application cannot be sent without a plan and emergency contacts.
- [ ] The applicant can see the application's status.

**Still open:** Q-04 (whether the platform administrator should also check the applicant's ID, as the old system did; C-01).

**Old system:** `backend/app/Http/Controllers/API/MembershipApplicationController.php`, lines 55 to 199; `frontend/src/pages/MemberDashboard.vue`, lines 1335 to 1500.

### R-32: Health questionnaire and waivers

*From the old system. It fits the spec's "health declarations" (page 15), so it is kept as a fitting extra (said in conversation, 3 November 2026).*

**In plain words:** The application includes:

- a short health questionnaire;
- an optional medical clearance upload;
- the gym's waivers, with a typed signature and date.

**Why it is needed:** The spec asks for "relevant health declarations" (page 15). Combat sports carry injury risk, and the old system asked for this.

**Where the need came from:** D-42; the old system, `MembershipApplicationController.php`, lines 142 to 159.

**Depends on:** R-31.

**Blockers:** none known.

**Done when:**

- [ ] The application cannot be sent until the waivers are accepted and signed.
- [ ] A medical clearance file, if given, is kept privately (as in R-15).
- [ ] Answers are visible only to staff with permission for health details (R-40).

**Still open:** Q-05 (whether to keep the old automatic "risk score" worked out from the answers).

**Old system:** as above; the risk score is at lines 562 to 597.

### R-33: Approve or reject; athlete profile created

**In plain words:** A staff member with membership permission reviews the application and approves or rejects it. Approval creates the athlete profile: plan, which services they may use, emergency contacts, health declarations, background, rank and history. The applicant gets a notice either way.

**Why it is needed:** The spec, page 15, and the notice on page 18. In the old system, receiving cash approved the membership automatically, and the staff desk had no approve button (C-13).

**Where the need came from:** D-41, D-42 and D-72.

**Depends on:** R-31 and R-10.

**Blockers:** none known.

**Done when:**

- [ ] Only staff with membership-approval permission, or the owner, can approve or reject.
- [ ] Rejection needs a reason, which the applicant sees.
- [ ] Approval creates the athlete profile, filled in from the application.
- [ ] Taking a payment never approves a membership by itself.
- [ ] The applicant gets a notice of the decision.

**Still open:** whether approval must wait until the fee is paid is not stated. It should be decided during implementation planning. *Drawn from* the spec, pages 15 and 16; see diagram 8 in [04 - DIAGRAMS](../../04%20-%20DIAGRAMS/04%20-%20DIAGRAMS.md).

**Old system:** `backend/app/Http/Controllers/API/MembershipPaymentController.php`, lines 78 to 85; `MembershipApplicationController.php`, lines 479 to 560.

### R-34: Record membership payments

**In plain words:** Every membership payment is recorded with:

- the amount and method: cash, card or e-wallet;
- the date;
- who received it;
- a receipt number.

Most in-person payments still happen at the front desk; the system records them. The member sees their own payment history.

**Why it is needed:** The spec, page 4 (front-desk payments are recorded) and page 16 (payments with details and history; athletes see "own payments", page 7).

**Where the need came from:** D-53 and D-55.

**Depends on:** R-31.

**Blockers:** none known.

**Done when:**

- [ ] A permitted staff member can record a payment with its method and get a receipt number.
- [ ] Each payment shows who recorded it and when.
- [ ] A member can see their own payments and receipts.
- [ ] A recorded payment cannot be silently changed; corrections are kept as their own entries.

**Still open:** whether the old two-step "front desk passes money to finance" chain is kept is not stated in the spec. It should be decided during implementation planning.

**Old system:** `MembershipPaymentController.php`, lines 31 to 276; `combat_sports_db.sql`, line 2134.

### R-35: Pay any fee online through PayMongo

*A core requirement. Your client noted PayMongo was missing from the documents (asked by your client, relayed in conversation, 3 November 2026), and you made it core for every online payment (said in conversation, 3 November 2026).*

**In plain words:** A person can pay any fee online through PayMongo, using GCash, Maya or a card:

- membership and registration fees;
- session add-ons and private coaching;
- any other fee the gym charges through the system.

The payment is recorded only when PayMongo confirms it, and the confirmation is checked as genuine. Cash and card payments at the front desk are still recorded through R-34.

**Why it is needed:** It lets people pay without visiting the desk. The spec mentions e-wallet payments (page 16) but not PayMongo. The old system used PayMongo for memberships only, and its check that confirmations were genuine was probably wrong (`backend/app/Services/PayMongo/PayMongoService.php`, lines 173 to 184).

**Where the need came from:** D-55 and D-84; the old system, `MembershipApplicationController.php`, lines 301 to 461.

**Depends on:** R-34. Paying session add-ons online is joined up when R-51 in [PHASE 5](../phase-5/PHASE%205.md) is built.

**Blockers:** none known. PayMongo test keys are in `backend/.env` (copied from the old system with your permission, for testing and the thesis only).

**Done when:**

- [ ] A person can pay a membership fee online and come back to a clear confirmation.
- [ ] Session add-ons and private coaching can be paid online the same way, once Phase 5 exists.
- [ ] A payment is recorded only after PayMongo confirms it, and fake confirmations are rejected.
- [ ] Paying twice for the same fee is prevented or refunded.
- [ ] Every online payment appears in the gym's income records (R-63) like a desk payment.

**Still open:** nothing. Q-22 was answered: online payment covers every fee.

**Old system:** as above; `backend/PAYMONGO_SETUP.md` disagrees with the code (C-21).

### R-36: Membership life: activate, renew, freeze, expire, cancel

**In plain words:** A membership becomes active when approved. It expires at the end of its validity, or when its sessions run out for session passes. It can be renewed, frozen and unfrozen under the plan's rules, or cancelled under the plan's refund policy. Every change is kept in its history. The athlete is told about each change.

**Why it is needed:** The spec, page 15, section 4.2, and the plan policies on page 12. The old system had no renewal, freezing or cancelling, and worked out expiry only when someone looked (`backend/app/Models/MembershipApplication.php`, lines 82 to 127).

**Where the need came from:** D-43 and D-44; D-23 (policies).

**Depends on:** R-33.

**Blockers:** none known.

**Done when:**

- [ ] A membership's end date is stored and shown, and the membership stops being active after it.
- [ ] Permitted staff can renew, freeze, unfreeze and cancel, following the plan's policy.
- [ ] A frozen membership's end date moves by the frozen days.
- [ ] Every change is kept in a history the athlete can see.
- [ ] Expiry reminders are sent (built in R-82, [PHASE 7](../phase-7/PHASE%207.md)).

**Still open:** nothing.

**Old system:** as above.

---

## Module: Athlete record

**Purpose:** keep one complete record of each athlete's development.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-37 | Rank, promotions and skill assessments | R-33 | ⭕ | Coaches record rank changes and assessments over time |
| R-38 | Competition records with divisions | R-33 | ⭕ | Events, divisions and results are kept per athlete |
| R-39 | Achievements | R-33 | ⭕ | Awards, titles and milestones are kept per athlete |
| R-40 | Health and emergency details, restricted | R-33, R-08 | ⭕ | Only authorised staff can see health details |
| R-41 | Athlete sees their own record | R-37, R-38, R-39 | ⭕ | An athlete can view their own attendance, progress, rank, competitions and achievements |

### R-37: Rank, promotions and skill assessments

**In plain words:** Each athlete has a current rank or belt per discipline. Promotions are recorded with a date and who awarded them, and coaches can record skill assessments.

**Why it is needed:** The spec, page 15, section 4.3. The old system had nothing for this.

**Where the need came from:** D-46.

**Depends on:** R-33.

**Blockers:** none known.

**Done when:**

- [ ] A permitted coach can record a promotion and an assessment for an athlete.
- [ ] The athlete's rank history shows every promotion in order.

**Still open:** whether each gym defines its own rank ladders (for example BJJ belts or taekwondo grades) is not stated. It should be decided during implementation planning.

**Old system:** none.

### R-38: Competition records with divisions

*The weight-division part is from the old system and is kept as a fitting extra (said in conversation, 3 November 2026). The rest is in the spec.*

**In plain words:** For each athlete, staff record the events they joined, their division (such as a weight class), and their results.

**Why it is needed:** The spec, page 15: "events joined, division, and results". The old system worked out a boxing weight class at application time only (`backend/app/Support/CombatWeightDivision.php`).

**Where the need came from:** D-46.

**Depends on:** R-33.

**Blockers:** none known.

**Done when:**

- [ ] A permitted staff member can add an event, the athlete's division and the result.
- [ ] The athlete's record lists their competitions with results.

**Still open:** nothing.

**Old system:** as above. The old system treated every non-boxing sport as "open weight" (`frontend/src/utils/weightDivision.js`, lines 29 to 64).

### R-39: Achievements

**In plain words:** Awards, titles and milestones are recorded per athlete.

**Why it is needed:** The spec, page 15.

**Where the need came from:** D-46.

**Depends on:** R-33.

**Blockers:** none known.

**Done when:**

- [ ] A permitted staff member can add an achievement with a date.
- [ ] The athlete's record lists their achievements.

**Still open:** nothing.

**Old system:** none.

### R-40: Health and emergency details, restricted

**In plain words:** Health declarations, medical files and emergency contacts sit in the athlete's record. Only staff with permission for health details can see them.

**Why it is needed:** The spec, page 15: "access limited to authorized staff". The old system kept medical files in a public folder (`MembershipApplicationController.php`, line 159).

**Where the need came from:** D-46 and D-47.

**Depends on:** R-33 and R-08.

**Blockers:** none known.

**Done when:**

- [ ] Staff without health permission cannot see health details on any screen or by any request.
- [ ] Medical files are kept privately.
- [ ] The athlete can update their own emergency contacts and health declaration.

**Still open:** nothing.

**Old system:** as above.

### R-41: Athlete sees their own record

**In plain words:** An athlete can open their own record: attendance, progress notes, rank history, competitions and achievements. Coaches and owners see the records of their gym's athletes according to their permissions.

**Why it is needed:** The spec, page 15, and the access table on page 7 ("own records").

**Where the need came from:** D-47.

**Depends on:** R-37, R-38 and R-39. Attendance and progress notes appear once R-54 and R-55 in [PHASE 5](../phase-5/PHASE%205.md) exist.

**Blockers:** none known.

**Done when:**

- [ ] An athlete sees their own rank, competitions and achievements.
- [ ] Attendance and progress notes appear once Phase 5 is built.
- [ ] An athlete can never see another athlete's record.

**Still open:** nothing.

**Old system:** none beyond the weight class label.

---

## Phase blockers

Q-10 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md).

## Phase is finished when

Every item above is ✅, 🔵 or ⬜. A person can apply, be approved by staff, pay in any accepted way, and have a membership that renews, freezes and expires properly. Their athlete record is kept and visible to them.
