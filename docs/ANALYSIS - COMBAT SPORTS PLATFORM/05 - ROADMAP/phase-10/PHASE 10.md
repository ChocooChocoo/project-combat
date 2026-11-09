# PHASE 10

**Gyms subscribe to the platform**

[00 - START HERE](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · [05 - ROADMAP](../05%20-%20ROADMAP.md) · Previous: [PHASE 9](../phase-9/PHASE%209.md) · Next: [06 - SCREENS BY ROLE](../../06%20-%20SCREENS%20BY%20ROLE/06%20-%20SCREENS%20BY%20ROLE.md)

**Status:** ✅ Finished (9 November 2026)

Citation forms are the same as in [02 - FINDINGS](../../02%20-%20FINDINGS/02%20-%20FINDINGS.md). Paths starting `backend/` or `frontend/` in this note point at the **rebuild** in the project folder, read 9 November 2026.

## The goal

The platform administrator sets up subscription plans for gyms. For each plan they write what it includes and switch each owner-side module on or off. A newly registered gym gets a free trial. When the trial ends, the owner subscribes and pays through PayMongo. A gym that does not subscribe moves to the free plan and keeps only the modules that plan allows.

These requests came from your client through `docs/prompts/P05.md` (relayed in conversation, 9 November 2026). The rebuild has no subscription, plan-tier or trial records today (searched 9 November 2026). The gym "tiers" of Phase 2 are verification levels, not subscription plans, and are not changed here.

## Suggested plan names

You chose these names (said in conversation, 9 November 2026). The administrator can rename them.

| Name | Meant for |
|---|---|
| **Starter** | The permanent free plan. Every gym lands here when its trial ends without a subscription. |
| **Pro** | A paid plan for a typical gym. |
| **Elite** | The top paid plan, with every module. |

Which modules each plan includes, and the prices, are set by the administrator (R-103), not fixed here.

## Why it comes here

- A trial starts for a gym that exists, so it needs gym applications and approval (R-11 to R-18 in [PHASE 2](../phase-2/PHASE%202.md)).
- Owners pay through the PayMongo payment flow already built (R-35 in [PHASE 4](../phase-4/PHASE%204.md)).
- Locking a module reuses the permission check and the role-filtered sidebar (R-08 in [PHASE 1](../phase-1/PHASE%201.md), R-101 in [PHASE 9](../phase-9/PHASE%209.md)).
- The owner-side modules a plan can switch on or off are the ones built in Phases 3 to 8.

## What it waits for

| Waits for | Why | Status |
|---|---|---|
| R-11 to R-18 in [PHASE 2](../phase-2/PHASE%202.md) | A gym must exist before its trial starts | ✅ |
| R-35 in [PHASE 4](../phase-4/PHASE%204.md) | Owners pay for plans through PayMongo | ✅ |
| R-08 in [PHASE 1](../phase-1/PHASE%201.md), R-101 in [PHASE 9](../phase-9/PHASE%209.md) | Module checks and sidebar hiding | ✅ / 🔵 |
| Q-25 to Q-28 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) | Trial contents and start, what happens when a paid period ends, billing periods | ✅ Answered 9 November 2026 |

## What it unlocks

Nothing; this is the last phase so far.

## Where this phase stands

| Status | How many |
|---|---|
| ✅ Finished | 6 |
| 🟨 Being worked on | 0 |
| ⭕ Not started | 0 |
| ❌ Blocked | 0 |
| 🔵 Already there | 0 |
| ⬜ Dropped | 0 |
| ❓ Unclear | 0 |
| **Total** | **6** |

Q-25 to Q-28 were answered on 9 November 2026: the trial opens every module, starts at approval, a lapsed month drops to Starter at once, and plans are monthly only.

---

## Module: Platform administration (Module 7)

**Purpose:** let the administrator decide what each plan costs and includes.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-102 | Subscription plans | — | ✅ | The administrator can create, edit and retire plans |
| R-103 | Which modules each plan can use | R-102 | ✅ | The administrator switches each owner-side module on or off per plan |
| R-104 | Free trial for new gyms | R-102, R-18 | ✅ | Each new gym starts on a trial whose length the administrator sets |

### R-102: Subscription plans

**In plain words:** The platform administrator has a *Subscription plans* screen. They create a plan with a name, a price, a billing period and a written list of what it includes. They can edit a plan or retire it so no new gym can choose it. Exactly one plan is marked as the free plan (Starter by default).

**Why it is needed:** Your client asked for a subscription plan module where the admin creates plans, for example Free, Pro and Ultra (`docs/prompts/P05.md`, *Tasks: Admin*).

**Where the need came from:** D-89.

**Depends on:** nothing new; admin screens already exist (R-16, R-19).

**Blockers:** none. Q-28 answered: monthly only.

**Done when:**

- [x] The administrator can create a plan with a name, price, billing period and inclusions.
- [x] The administrator can edit any of those, and the change shows to owners at once.
- [x] A retired plan can no longer be chosen, but gyms already on it keep it until their period ends.
- [x] Exactly one plan is the free plan, and it cannot be retired.
- [x] Only the platform administrator can open the screen or change plans.

**Still open:** nothing. The starting prices (Pro PHP 1,499, Elite PHP 2,999) are placeholders for the administrator to change.

### R-103: Which modules each plan can use

**In plain words:** For each plan, the administrator sees the list of owner-side modules (for example staff, payroll, finance, customers, stock, purchasing, decision support) and switches each one on or off. That list is what a gym on that plan may use.

**Why it is needed:** Your client asked that the admin choose which features each plan can and cannot access (`docs/prompts/P05.md`, *Tasks: Admin*).

**Where the need came from:** D-90.

**Depends on:** R-102.

**Blockers:** none known.

**Done when:**

- [x] Each plan shows every owner-side module with an on or off switch.
- [x] A module switched off for a plan is unavailable to every gym on that plan (enforced by R-106).
- [x] The core needed to run a gym at all (signing in, the gym profile, the owner's own subscription screen) can never be switched off.

**Still open:** which modules count as "core" is drawn from the request, not stated in it. The list above is the minimum needed for an owner to reach the subscribe screen.

### R-104: Free trial for new gyms

**In plain words:** A new gym starts on a free trial. The administrator sets the trial length once for the whole platform; it starts at 30 days. Each gym's trial end date is shown to its owner and to the administrator.

**Why it is needed:** Your client said gym owners register for free on a trial, for example one month (`docs/prompts/P05.md`, *Context*). You chose an administrator-set length with a 30-day default (said in conversation, 9 November 2026).

**Where the need came from:** D-91.

**Depends on:** R-102 and R-18.

**Blockers:** none. Q-25: the trial opens every module. Q-26: it starts at approval.

**Done when:**

- [x] The administrator can change the trial length; it starts at 30 days.
- [x] Every new gym gets a trial end date worked out from that length.
- [x] Changing the length affects only gyms whose trial has not started yet.
- [x] A gym gets one trial only.

**Still open:** nothing. Gyms approved before this change got a 30-day trial from the day the change was installed.

---

## Module: Gym subscription (owner side)

**Purpose:** let owners pay for a plan, and limit what an unsubscribed gym can use.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-105 | Subscribe and pay through PayMongo | R-102, R-35 | ✅ | An owner chooses a plan, pays online, and the plan starts when payment is confirmed |
| R-106 | Limit modules when there is no subscription | R-103, R-104, R-105, R-101 | ✅ | A gym whose trial or paid period ended without a subscription can use only the free plan's modules |
| R-107 | Owner sees their plan and dates | R-104, R-105 | ✅ | The owner sees the current plan, trial days left and next renewal date |

### R-105: Subscribe and pay through PayMongo

**In plain words:** The owner opens *My subscription*, picks a paid plan and pays through PayMongo, the same way members pay fees today. The plan starts only when PayMongo confirms the payment. Each payment is kept in the gym's payment history.

**Why it is needed:** Your client said gym owners must subscribe once the trial ends (`docs/prompts/P05.md`, *Constraints*). You chose PayMongo (said in conversation, 9 November 2026).

**Where the need came from:** D-92 and D-93.

**Depends on:** R-102 and R-35.

**Blockers:** none. Q-28: one month at a time.

**Done when:**

- [x] An owner can choose any active paid plan and pay through PayMongo.
- [x] The plan becomes active only after PayMongo confirms the payment, never because the screen says so.
- [x] A failed or abandoned payment leaves the gym on its current plan or trial.
- [x] Each payment shows on the owner's subscription history (plan, months covered, amount). PayMongo sends its own receipt.
- [x] Only the gym's owner can subscribe or pay for it.

**Still open:** prorating. Paying for a different plan makes it current straight away, and any time left carries over to it; there is no refund or price adjustment.

### R-106: Limit modules when there is no subscription

**In plain words:** When a gym's trial ends, or its paid period ends, and it has not subscribed, the gym moves to the free plan (Starter). Any module that plan does not include is locked. The server refuses its requests, the sidebar hides it, and the owner sees a short message saying why with a link to subscribe. Stored records are kept, not deleted, and come back when the gym subscribes. Members' own screens (memberships, bookings, record) are not affected.

**Why it is needed:** Your client asked that gym owners who have not subscribed after the trial lose access to some or all owner-side features (`docs/prompts/P05.md`, *Success Criteria*). You chose "per the free plan's feature list" (said in conversation, 9 November 2026).

**Where the need came from:** D-92.

**Depends on:** R-103, R-104, R-105 and R-101.

**Blockers:** none. Q-27: the gym drops to Starter the moment its paid month ends.

**Done when:**

- [x] On the day after the trial ends without a subscription, the gym is on the free plan.
- [x] A locked module is refused by the server for the owner and every employee, whatever their role.
- [x] A locked module is hidden from the sidebar.
- [x] The owner sees why it is locked and a link to subscribe.
- [x] Records in a locked module are kept and reappear after subscribing.
- [x] Members can still view their memberships, bookings and records at that gym.

**Still open:** nothing.

### R-107: Owner sees their plan and dates

**In plain words:** The owner's *My subscription* screen shows the gym's current plan and what it includes, how many trial days are left, the next renewal date, and past payments. A notice reminds the owner before the trial or paid period ends.

**Why it is needed:** An owner cannot subscribe in time without knowing when the trial ends. *Drawn from* D-91 and D-92.

**Where the need came from:** D-91 and D-92.

**Depends on:** R-104 and R-105.

**Blockers:** none known.

**Done when:**

- [x] The owner sees the current plan, its inclusions and its modules.
- [x] The owner sees trial days left, or the next renewal date.
- [x] The owner gets a notice before the trial or paid period ends (uses the inbox, R-10).

**Still open:** nothing. The reminder goes out once, 3 days before the end.

---

## Phase blockers

None.

## Build record, 9 November 2026

- **Plans and settings (R-102 to R-104):** new tables for plans, platform settings and subscription payments; gyms gained a trial end date, current plan and paid-until date. Starter (free), Pro and Elite are seeded. Admin screen: *Subscription plans* (`/admin/plans`). Approval sets the trial end date from the admin's trial length.
- **One rule, one place (R-106):** `backend/app/Support/Subscription.php` works out the gym's state on every check (trial, paid, free or not managed), so nothing has to run when a period ends. `User::permissionsIn` drops the permissions of locked modules, so the server, the sidebar and every screen agree. A refused request says the module is not in the gym's plan. Routes that check the person inside the controller instead of through `permission:` (staff self-service: clock in and out, leave, overtime, My work; and the owner-only payroll settings) carry a `plan:<module>` check too, added 9 November 2026 after a direct API probe found them open on the free plan. Gyms with no trial date (older test fixtures) are not limited.
- **Paying (R-105):** checkout, return-page confirm and the PayMongo webhook share one recorder; the unique checkout id stops a payment being counted twice.
- **Owner screen (R-107):** *My subscription* in the Setup group, owner only, plus a workspace notice when on the free plan or within 7 days of the end. Daily reminder `app:send-subscription-reminders` at 08:10.
- **Checks:** `SubscriptionTest` (6 tests) and the full backend suite pass (603 tests, 27,997 assertions). Frontend `pnpm build`, `pnpm lint` (no errors) and `node --test` pass. Migration applied to the development MySQL database on 9 November 2026 (backup taken first). Browser-checked 9 November 2026 on a throwaway copy of the database: trial banner, My subscription, a real PayMongo GCash test payment for Pro (recorded once, month starts after the trial), free-plan lock (sidebar hides locked modules, Finance refused, banner shows), admin plan edit, and present/late/absent days on a computed pay line (1 / 2 / 9).

## Phase is finished when

Every item above is ✅, 🔵 or ⬜. The administrator manages plans and what each one includes, new gyms get a trial, owners pay through PayMongo, and an unsubscribed gym uses only what the free plan allows.
