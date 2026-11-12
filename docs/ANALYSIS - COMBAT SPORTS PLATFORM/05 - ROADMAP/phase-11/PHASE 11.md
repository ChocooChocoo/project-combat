# PHASE 11

**Gyms get offers, safer training and clearer monitoring (P09)**

[00 - START HERE](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · [05 - ROADMAP](../05%20-%20ROADMAP.md) · Previous: [PHASE 10](../phase-10/PHASE%2010.md) · Next: [06 - SCREENS BY ROLE](../../06%20-%20SCREENS%20BY%20ROLE/06%20-%20SCREENS%20BY%20ROLE.md)

**Status:** 🟨 Built 12 November 2026; waiting for a browser check

Paths starting `backend/` or `frontend/` point at the **rebuild** in the project folder, read 12 November 2026. The build plan with every task ticked is `docs/implementation-plan/p09-feature-set/PLAN.md`.

## The goal

Your client's P09 list (`docs/prompts/P09.md`, items 1 to 8) asked for a clearer sidebar that remembers the last gym, date filters on history pages, prepaid plans with a locked price, promos and vouchers, multi-item purchase requests with receipt photos, monitoring notices, coach applications, training goals, and injury and equipment safety tracking. Item 9 (tournaments) is out of scope.

## Items

| ID | What is true when it is done | Where in the rebuild | Status |
|---|---|---|---|
| R-108 | The sidebar groups are HR / Employee Management, Training, Members, Finance, Business, Setup. After sign-in a person lands in the last gym they used (kept per person on the device). | `frontend/src/components/gym-nav.tsx`, `frontend/src/lib/gym-access.ts` (`homePath`, `rememberGym`) | 🟨 |
| R-109 | A time plan can be prepaid for up to N terms in one charge, with an optional prepay discount. | `backend/app/Support/MembershipPricing.php`, plan editor | 🟨 |
| R-110 | A member keeps the fee they were approved at on every renewal while active or frozen. An expired membership renews at the current price and locks that. | `memberships.locked_fee`, `MembershipController::renew` | 🟨 |
| R-111 | Promos (automatic, date window, chosen plans) and vouchers (code, % or fixed, optional use limit) take money off the first membership charge. Staff offers work only after an approver says yes; the owner's are live at once. | `backend/app/Support/OfferApplier.php`, `OfferController`, *Offers* screen | 🟨 |
| R-112 | History lists can be filtered by a from/to date. Empty dates show the list as before. | `backend/app/Support/DateRange.php`, `RangePicker` with `optional` | 🟨 |
| R-113 | A pending purchase request can be edited by its requester or an approver, and approvers get a notice when one is submitted. | `PurchaseRequestController::update`, `Procurement\Notifier::requestSubmitted` | 🟨 |
| R-114 | A purchase order keeps several receipt photos or PDFs, uploaded on receiving or later. | `purchase_order_proofs`, `PurchaseOrderProofController` | 🟨 |
| R-115 | Each gym sets its membership expiry reminder days (default 7, 3, 1). | `gyms.membership_reminder_days`, *Setup* screen | 🟨 |
| R-116 | Inactive members and staff absences produce notices for the person and a digest for managers. Nothing is blocked. | `app:monitor-inactivity`, `StaffAbsenceMarker` | 🟨 |
| R-117 | A member applies to coach with weekly hours; hours that overlap a shift at another gym block the application and the approval. Approval adds an employee link, coach profile, schedule and the Coach starter role; the account stays a member account. | `CoachApplicationController`, `CoachScheduleConflicts` | 🟨 |
| R-118 | Coach and athlete share training goals with milestones; a goal is achieved when its last milestone is done. | `TrainingGoalController`, Goals card | 🟨 |
| R-119 | Injuries with a recovery log. Details need health access; coaches without it see only a *Restricted* flag on the members list and session rosters. | `InjuryController`, `Injury::restrictedAmong` | 🟨 |
| R-120 | Equipment and protective gear have a safety inspection log, a check schedule, an unsafe alert and a daily due-check digest. Unsafe items stay usable in stock (notice only). | `EquipmentSafetyController`, `app:remind-equipment-checks`, *Stock → Safety* | 🟨 |

## Decisions you made

Recorded in the plan's "User decisions" list: prepay and lock (B), promos/vouchers on plans only with owner approval (C), per-gym reminder and inactivity days (F), coach conflicts block and approval by owner or a granted approver (G), injury details behind health access with a coach-only flag (I).

## Checked

- Backend feature tests for every item (`backend/tests/Feature/DateRangeFilterTest.php`, `MembershipPricingTest`, `OfferTest`, `PurchaseProofTest`, `MonitoringTest`, `StaffAbsenceJobTest`, `CoachApplicationTest`, `TrainingGoalTest`, `SafetyTest`) and the full suite.
- Frontend `pnpm build`, `pnpm lint` (warnings only) and `node --test`.

## Not checked yet

- Browser render of the new screens, and MySQL migration on the real database (tests run on SQLite).
