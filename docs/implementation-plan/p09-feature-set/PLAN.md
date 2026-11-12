# P09 — Next feature set (sidebar, memberships, offers, filters, purchasing, monitoring, coaches, goals, safety)

Status: built 12 Nov 2026 (phases 1-10). Browser verification run 12 Nov 2026: not accepted yet, coach application form crashed and approval preview omitted the calculated offer discount. Both fixed in code 12 Nov 2026 (stray `FieldDescription` removed from `CoachApplications.tsx`; new `GET memberships/{membership}/price-preview` feeds the approve dialog). Browser retest of both screens passed 13 Nov 2026 (Playwright, localhost, seeded Iron Will Boxing gym). Same run exercised phases 1-9 in the browser: all passed (date filters, prepay and price lock incl. expired re-lock, offers and voucher approval, purchase edit and receipt proofs, monitoring notices, coach conflict and approval, goals, injuries and restricted flag, equipment safety, mobile 390 px and light mode). `php artisan test` 668 passed, `pnpm build` ok, `pnpm lint` 0 errors, `node --test` 30 passed. Follow-up run 13 Nov 2026 also passed: injury recovery log and "Cleared to train" notice, Finance Officer and Front Desk sidebars (no API errors), 390 px overflow check on all owner pages. Two small fixes made: edit-request submit button now reads "Save changes"; proof delete now asks first. All seven P09 migrations were already applied to local MySQL when checked. The earlier report file `anti-slop/audit-001-2026-11-12.md` is missing from the repo. Source: `docs/prompts/P09.md` (from `gavins lists of changes.md`, items 1–8; item 9 tournaments is out of scope).

## Context
P09 asks for nine enhancements across the gym workspace plus a docs/artifact refresh. Code reading showed:
- Sidebar groups are People, Training, Members, Business, Setup (`frontend/src/components/gym-nav.tsx:12-37`). The gym comes only
  from the URL; after login `homePath()` (`lib/gym-access.ts:17`) sends non-owners to `/`. Logout removes only `token`.
- `membership_plans.total_fee` is the price. Approve sets `amount_charged = total_fee`; **renew re-reads the current plan
  `total_fee`** (`Gym/MembershipController.php:99,129`). That is the price-lock bug. Owed = `amount_charged − payments`.
- No promo/voucher model. `discount` exists only on desk sales (`Support/Finance/SaleRecorder.php`).
- Purchase requests already take many lines in API and UI (`GymPurchasing.tsx:1727 NewRequestDialog`). Gaps: no edit of a
  pending request, approvers not notified on submit. No receipt proof files.
- Expiry reminders already exist (member, 7/3/1 days from `config/communication.php`). Staff absences and no-shows send no
  notice. No inactivity check; `gym_visits` + `session_bookings.attendance` can drive it.
- No coach application flow. Staff are added by email (`StaffController::store`). Coach = `EmployeeProfile::isCoach()`
  (coach_* fields filled). `work_schedules` = weekday + start/end per gym.
- No goals, milestones, injuries or equipment checks. `stock_items.category` has `equipment` and `protective_gear`.
- Highest requirement id is R-107. New ids: R-108 to R-120.

## User decisions
- B: advance = prepay N terms of a time plan in one charge, with optional prepay discount % on the plan.
- B: price lock covers the current term and all renewals while the membership stays active/frozen. A lapsed (expired)
  membership renews at the plan's current price and locks that.
- C: promo = automatic discount on chosen plans for a date window. Voucher = code with % or fixed off, optional usage
  limit. Apply to membership plans only. CRM staff create → owner approves. Owner-created offers are approved at once.
- F: per-gym settings (expiry reminder days default 7,3,1; inactivity days default 14).
- G: member applies with weekly availability; conflicts with their work schedules at other gyms **block**; owner (or staff
  with the new approve permission) approves; approval adds employee link + Coach starter role. Account stays `member`.
- I: staff and athlete record injuries; details need `health_records.records.view`; coaches see only a restricted flag.
  Equipment safety = inspection log on stock items of category equipment / protective_gear.
- J: docs and artifacts once at the end; show summary and ask before publishing the public process guide.
- Plan saved as this one file at `docs/implementation-plan/p09-feature-set/PLAN.md`, phases inside.

## Shared rules (every phase)
- Add each new permission to `backend/config/permissions.php` first, run `php artisan app:sync-permissions`, add it to the
  right starter templates in `config/access.php` when a starter role should get it, and mirror the check with `can()`.
- New models with gym data use `BelongsToGym`. Cross-gym reads (coach conflict check, scheduled jobs) call
  `withoutGlobalScope('gym')` on purpose.
- Business rules in `app/Support`, inside `DB::transaction` + `lockForUpdate` for multi-step writes. JSON via `Presents*` traits.
- Notices: database notifications only (15 s polling already in `lib/notifications.tsx`). Scheduled jobs:
  `Schedule::call(fn () => Artisan::call('...'))`, closure commands in `routes/console.php` like the existing ones.
- Money: plan/payment decimals stay `decimal(12,2)`. Payroll untouched.
- Private files: `local` disk, path + original name + mime + size on the row, served by an authenticated route that checks
  permission (pattern: `LeaveController::attachment`, frontend `apiBlobUrl`).
- Each phase: feature tests in `backend/tests/Feature/` using existing setup traits (`Concerns/*Setup`, `GivesGymRoles`),
  `php artisan test`, `pnpm build`, `pnpm lint`. Tick boxes here as work lands.
- Frontend work: load `impeccable` + `antislop-ui` skills for new screens; reuse coss UI in `src/components/ui/`.

---

## Phase 1 — Sidebar and workspace (A, R-108)
- [x] `gym-nav.tsx`: rename group "People" to "HR / Employee Management" (Staff, Staff attendance, Leave, later Coach
      applications). Add group "Finance" with Finance, Payments (moved from Members), Payroll (moved from People).
      Business keeps Customers, Offers (phase 4), Stock, Purchasing, Dashboard, Decision support. Order:
      HR, Training, Members, Finance, Business, Setup. Check `workspaceHome()` still lands on the first allowed item.
- [x] Remember workspace: in `app-shell.tsx`, when the URL is `/gyms/:id/...` and the gym is approved for the user, save
      `localStorage['last_gym:<userId>'] = id`. Key is per user so a shared device does not open another user's gym.
      Logout keeps it (only `token.clear()` runs, unchanged).
- [x] `homePath(user)` (`lib/gym-access.ts`): if the saved gym is still in the user's gyms, return `gymEntry(gym)` (owner →
      `/manage`, employee → workspace or my-work, member → my-record); else today's behavior. Used by `SignIn.tsx`,
      `VerifyEmail.tsx` and the guest guard in `App.tsx`, so no extra call sites.
- [x] Tests: `frontend/tests/workspace-memory.test.mjs` (saved id used; stale id ignored; other user's key ignored).
Done when: sign out and in again lands in the last gym used; sidebar shows the new groups for owner and for a Finance role.

## Phase 2 — Date filters on history pages (D, R-112)
- [x] Backend helper `app/Support/DateRange.php`: `rules()` (`from` `date_format:Y-m-d`, `to` `after_or_equal:from`) and
      `apply($query, Request, string $column)` (from = startOfDay, to = endOfDay, Manila time). Use it only in the
      endpoints below; leave the controllers that already filter unchanged.
- [x] Add `from`/`to` to: `payments`, `bookings`, `payroll/runs` (period overlap), `leave-requests` (date overlap),
      `overtime-requests`, `purchase-requests`, `purchase-orders`, `stock-items/{id}/movements`, `follow-ups` (due date),
      `announcements`, `/notifications`, `my-bookings` (past list), `my-attendance`, membership events on the member record (filtered in the browser: the record already loads the full history).
- [x] Frontend: reuse `RangePicker` (`components/decision-support/range-picker.tsx`) and `validRange()` on each matching
      page: `GymPayments`, `GymBookings`, `GymPayroll`, `GymLeave`, overtime tab in `GymStaffAttendance`, `GymPurchasing`
      (both tabs), stock movements in `GymStock`, follow-ups in `GymCustomers`, `GymAnnouncements`, `Notifications`,
      `member/MyBookings`, `hr/my-attendance`, history tabs in `MemberRecord`. Empty range = no filter (current behavior).
- [x] Out of scope: check-in (today only), schedule/timetable/attendance/finance (already filtered).
- [x] Tests: one feature test per endpoint group asserting rows outside the range are hidden and `to < from` is 422.
Done when: every listed page has a working range filter and default view is unchanged.

## Phase 3 — Advance plans and price lock (B, R-109, R-110)
- [x] Migration on `membership_plans`: `max_prepay_terms` unsignedTinyInteger default 1, `prepay_discount_percent`
      decimal(5,2) default 0. Only `billing_type = time` may have `max_prepay_terms > 1` (validated in plan controller).
- [x] Migration on `memberships`: `locked_fee` decimal(12,2) nullable, `prepaid_terms` unsignedTinyInteger default 1.
      Backfill `locked_fee` from the plan's current `total_fee` for active/frozen/awaiting_payment rows.
- [x] `app/Support/MembershipPricing.php`: `charge(plan, lockedFee, terms)` = `lockedFee × terms × (1 − pct/100)`,
      rounded to 2 dp; `extend(membership, terms)` = `duration_months × terms`. One place for the math.
- [x] Apply (`MembershipApplicationController::store`, `MembershipApply.tsx`): optional `prepaid_terms` 1..max.
- [x] Approve (`MembershipController::decide`): `locked_fee = plan.total_fee`, `amount_charged = charge(...)`,
      `ends_on` from `extend`.
- [x] Renew (`MembershipController::renew`): active/frozen use `locked_fee`; expired re-locks to current `total_fee`.
      Optional `prepaid_terms` on renew too.
- [x] Plan price edit never touches memberships (already true) — add a test that proves it.
- [x] UI: `GymPlans.tsx` fields "Months you can prepay" and "Prepay discount %"; `MemberRecord` / `MyMemberships` show
      locked price and prepaid terms; renew dialog shows the locked price.
- [x] Tests: prepay 3 months charge and end date; discount math; sessions plan refuses prepay; renew after price change
      charges old price; renew after expiry charges new price.
Done when: a member who paid keeps their price on renewal after the gym raises the plan fee.

## Phase 4 — Promos and vouchers (C, R-111)
- [x] Permissions: `customers.offers.view`, `customers.offers.manage`, `customers.offers.approve`. Starter template: CRM-type
      roles get view+manage; approve stays owner-only by default.
- [x] Tables: `offers` (gym_id, kind promo|voucher, name, code nullable unique per gym, discount_type percent|fixed,
      value decimal(12,2), starts_on, ends_on nullable, max_uses nullable, uses default 0, status pending|approved|
      rejected|archived, created_by, decided_by, decided_at, decision_reason) and pivot `offer_plan`.
      `memberships`: `offer_id` nullable, `discount_amount` decimal(12,2) default 0.
- [x] Routes under `gyms/{gym}`: `offers` index/store/update (manage), `offers/{offer}/decision` (approve, creator cannot
      decide own — same rule as purchase requests), `offers/{offer}/archive`. Editing an approved offer sets it back to
      pending unless the editor is the owner. Owner-created → `approved` with `decided_by` = owner.
- [x] `app/Support/OfferApplier.php`: picks the offer for a plan at approval: voucher by code (sent on apply or approve),
      else best active approved promo for the plan. Only approved, in date, under `max_uses`. Increments `uses` with
      `lockForUpdate` in the approval transaction. Discount applies to the first charge only (not renewals); never below 0.
      Called from `MembershipController::decide` after `MembershipPricing::charge`.
- [x] Notices (one generic `GymNotice` class, not one class per notice): `OfferSubmitted` to holders of `customers.offers.approve` + owner; `OfferDecided` to creator.
- [x] UI: new page `GymOffers.tsx` (Business group, `customers.offers.view`): list with status, create/edit dialog, approve/
      reject for approvers. Voucher code field on `MembershipApply.tsx` and the approve dialog; charge preview shows discount.
- [x] Tests: staff offer pending and ignored at approval; owner offer live at once; approved voucher discounts; expired,
      used-up, wrong-plan, self-approval refused; uses count under concurrency guard.
Done when: a staff-made voucher only works after owner approval.

## Phase 5 — Purchase requests and receipt proof (E, R-113, R-114)
- [x] Multiple items: API already supports `lines[]`. Add `PUT purchase-requests/{request}` (manage, requester or manager,
      pending only) to edit note, needed_by and lines; add "Edit" in `GymPurchasing.tsx` reusing `NewRequestDialog` with
      pre-filled lines. Notify approvers (`procurement.requests.approve`) on submit via `Procurement\Notifier`.
- [x] Proof table `purchase_order_proofs` (gym_id, purchase_order_id, goods_receipt_id nullable, path, original_name, mime,
      size, uploaded_by). Files on `local` disk `purchase-proofs/{gym}`. Rules in `config/procurement.php` (or existing
      procurement config): `mimes:jpg,jpeg,png,webp,pdf`, max 5120 KB each, max 10 per upload.
- [x] Routes: `POST purchase-orders/{order}/proofs` (`procurement.receipts.manage`, multipart `files[]`, optional
      `goods_receipt_id` of that order); `GET .../proofs/{proof}` file stream and `DELETE` (uploader or receipts.manage) —
      view needs `procurement.orders.view`. Receiving dialog can attach files in the same submit (second request after the
      receipt is saved, so the stock transaction stays unchanged).
- [x] UI: proof gallery on the order detail (thumbnails via `apiBlobUrl`, PDF as file link), multi-file input.
- [x] Tests: `Storage::fake('local')`; upload many, wrong type 422, other gym 404, no permission 403, file served.
Done when: an order shows several receipt photos after receiving.

## Phase 6 — Expiry reminders and absence/inactivity monitoring (F, R-115, R-116) — notification only
- [x] Gym settings migration: `membership_reminder_days` json nullable (null = `config('communication...')` 7,3,1),
      `inactivity_days` unsignedSmallInteger default 14. Edit in `GymSetupController` + `GymSettings.tsx` (same as
      `session_reminder_minutes`).
- [x] `MembershipExpiryReminders` reads the gym's days. Actual expiry was already told to the member: `app:expire-memberships` logs `expired`,
      which sends `MembershipUpdated` (no new class needed).
- [x] Inactivity: `app/Support/Reminders/InactivityMonitor.php` + command `app:monitor-inactivity` daily 08:20. Active
      members whose last gym visit and last attended booking (or `starts_on` if none) are older than `inactivity_days`.
      Member gets `InactivityNotice`; one `InactiveMembersDigest` per gym to owner + `members.memberships.view` holders.
      Dedup table `inactivity_notices` unique (membership_id, last_activity_on). Index on `gym_visits (gym_id, user_id,
      checked_in_at)`.
- [x] Absence: after `StaffAbsenceMarker::mark`, send `StaffMarkedAbsent` to the employee and one `StaffAbsenceDigest` per
      gym/day to owner + `hr.attendance.view` holders. Backfill runs (`--from/--to`) do not notify (only the default run).
- [x] No blocking anywhere: nothing checks these notices.
- [x] Tests: each command with `travelTo`; dedup on second run; thresholds from gym settings; schedule registered
      (`Schedule::events()`).
Done when: owner sees inactive-member and absence notices in the bell.

## Phase 7 — Coach applications (G, R-117)
- [x] Permissions: `hr.coach_applications.view`, `hr.coach_applications.approve` (HR template gets view).
- [x] Table `coach_applications` (gym_id, user_id, status pending|approved|rejected|withdrawn, experience, rank,
      specialisation, bio, availability json `[{weekday,start,end}]`, decided_by, decided_at, decision_reason).
- [x] Member routes (signed-in group, like membership apply, `account:member`): `POST gyms/{gym}/coach-applications`,
      `GET me/coach-applications`, `POST me/coach-applications/{id}/withdraw`. Refuse if already employee there or a
      pending application exists.
- [x] `app/Support/CoachScheduleConflicts.php`: compare availability with the user's `work_schedules` at every other gym
      where they are still an active employee (`withoutGlobalScope('gym')`, `ended_on` null). Same weekday and overlapping
      time = conflict. 422 lists gym, weekday and hours. Run at apply and again at approval.
- [x] Staff routes under `gyms/{gym}`: `coach-applications` index/show (view), `.../decision` (approve). Approve in one
      transaction: `addPerson(Employee)`, `EmployeeProfile` with coach_* fields (so `isCoach()` is true),
      `work_schedules` from availability, attach starter role `template_key = coach` if present.
- [x] Notices: `CoachApplicationSubmitted` to owner + approvers; `CoachApplicationDecided` to applicant.
- [x] UI: "Apply as coach" on the public gym page (members only, `AccountOnly`); `member/MyCoachApplications` status list;
      staff page `GymCoachApplications.tsx` in HR group with availability and conflict result.
- [x] Tests: conflict blocks apply; no conflict at ended job; approval re-check; approval creates employee, profile,
      schedule, role; owner/admin accounts refused.
Done when: a member coaching at gym A cannot apply for overlapping hours at gym B, and approval makes them a coach.

## Phase 8 — Training goals and milestones (H, R-118)
- [x] Reuse `training.athletes.view|manage` (no new permission).
- [x] Tables `training_goals` (gym_id, user_id, title, description, target_on, status active|achieved|dropped,
      achieved_on, created_by) and `goal_milestones` (gym_id, goal_id, title, due_on, done_on, done_by, position).
- [x] Routes: staff `athletes/{user}/goals` CRUD + milestones (manage); the athlete uses the same routes for their own id (the controller allows
      only their own goals), creating, editing and ticking. Goal becomes `achieved` when the last milestone is done (or set by hand).
- [x] UI: "Goals" section in `MemberRecord.tsx` and `member/MyRecord.tsx` with progress (coss `progress`).
- [x] Tests: CRUD, auto-achieve, athlete cannot touch another athlete's goal, gym scope.
Done when: coach and athlete see the same goal progress.

## Phase 9 — Injury and equipment safety monitoring (I, R-119, R-120)
- [x] Permissions: `health_records.injuries.manage`, `inventory.safety.view`, `inventory.safety.manage`.
- [x] Injuries: tables `injuries` (gym_id, user_id, body_part, description, severity minor|moderate|severe, occurred_on,
      status active|recovering|cleared, restrictions, cleared_on, reported_by) and `injury_updates` (injury_id, status,
      note, recorded_by). Staff routes need `health_records.injuries.manage` to write, `health_records.records.view` to
      read. The athlete uses `athletes/{own id}/injuries` to report and read their own. `restricted` flag (any active/recovering injury, no details)
      added to athlete lists and session roster shapes for coaches. Notify managers on self-report, athlete on clear.
- [x] Equipment: `stock_items` add `safety_status` ok|needs_repair|unsafe default ok, `check_interval_days` nullable,
      `next_check_on` nullable. Table `equipment_checks` (dedup column `stock_items.check_reminded_for`) (gym_id, stock_item_id, result pass|needs_repair|unsafe, note,
      checked_by, checked_on). Only `equipment` / `protective_gear` items. Check updates status + next date. Unsafe →
      `EquipmentUnsafe` to owner + `inventory.items.manage`. Command `app:remind-equipment-checks` daily 08:30 →
      `EquipmentChecksDue` digest, dedup on `next_check_on`. Notification only: unsafe items stay usable in stock flows.
- [x] UI: "Injuries" section on `MemberRecord` / `MyRecord`; restricted badge in roster; "Safety" tab in `GymStock.tsx`.
- [x] Tests: permission matrix (coach sees flag only), self-report, recovery log, check updates status, due digest dedup.
Done when: an injured athlete shows as restricted to coaches, and an unsafe bag alerts the owner.

## Phase 10 — Docs and artifacts (J)
- [x] `docs/ANALYSIS - COMBAT SPORTS PLATFORM/`: R-108..R-120 in findings/overview, architecture (new tables, jobs,
      permissions), diagrams (offer approval, coach application, inactivity job), roadmap (P09 added as Phase 11, marked built, browser check pending),
      screens by role, word list (promo, voucher, price lock, prepay, inactivity, restricted).
- [x] Update `CLAUDE.md` scheduled-jobs line with the new commands.
- [x] Artifacts: draft new/changed sections for "Combat Platform Process Guide" (public) and "Combat Gym Testing Artifact"
      (owner only, test steps per phase). Show the summary to the user and get OK before publishing the public guide.
Done when: docs match the built code and both artifacts are republished.

## Order and dependencies
1 → 2 → 3 → 4 (needs 3 pricing) → 5 → 6 → 7 → 8 → 9 → 10. Phases 5–9 are independent of each other.

## Not in scope (add when asked)
- Tournaments/competition (item 9 of the change list).
- Offers on bookings/services; free-text purchase lines; member no-show notices; push/email for new notices.

## Verification
- Per phase: `php artisan test --filter=<NewTest>` then full `php artisan test`; `pnpm build`, `pnpm lint`, `node --test`.
- Manual (`php artisan dev` + `pnpm dev`): owner and a staff role per phase — sidebar groups and last gym after re-login;
  each history filter; prepay + renew after price change; staff voucher pending → owner approve → applied; edit PR + upload
  receipt photos; run `app:monitor-inactivity`, `app:mark-staff-absences`, `app:remind-equipment-checks` and check the bell;
  coach application conflict and approval; goals; injury restricted flag; unsafe equipment notice.
- Browser available and exercised 12 Nov 2026 through the connected Brave browser against localhost. Owner, Finance, Front Desk, coach and member flows were checked; Offers also checked at 390 px in light/dark mode. This was not an exhaustive responsive/accessibility audit. See the [browser test report](../../../anti-slop/audit-001-2026-11-12.md) for failures, evidence, local test-data changes and remaining checks. Public artifacts were not published.
