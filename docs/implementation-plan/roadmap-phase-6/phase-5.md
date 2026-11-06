[← Previous: Packet 6C CRM](./phase-4.md) | [Next: Packet 6E Procurement →](./phase-6.md)

**Status:** ✅ Done

# Packet 6D: Finance core — income, expenses and member balances

**Goal:** Every recorded payment appears as income with a type and a date. Staff record walk-in and merchandise sales. Expenses are recorded with a category, and released payroll or received purchases will post to the same table later. Permitted staff see every member's outstanding balance, and a member sees their own. Covers R-63, R-64 (manual part) and R-65.

## Requirements and done-when

| # | Requirement | Done when |
|---|---|---|
| R-63 | Income from training | Membership and session payments appear with no retyping; online PayMongo payments appear the same way; walk-in and merchandise sales can be recorded; income can be viewed by type and date range |
| R-64 | Expenses | Staff record an expense with category, amount, date and note. Automatic payroll and purchase postings arrive with 6E and 6F. |
| R-65 | Money members owe | Each member's balance is visible to permitted staff and to the member; a payment reduces it; unpaid session add-ons and private coaching fees appear |

## Dependencies

- Packet 6.0: `payments.income_type`, `discount`, `payer_name`, nullable `user_id`; `finance.expenses.*`, `finance.receivables.view`, `finance.reports.view`.
- Packet 6B for merchandise sales: `StockLedger::move()` with type `sale`.
- Roadmap Phase 4/5: `MembershipLedger`, `BookingLedger`, `PaymentController`, PayMongo webhook.

## Decisions

- `payments` remains the only income ledger. No copy, no second income table.
- Income types: `membership`, `session`, `private_coaching`, `walk_in`, `merchandise`, `other`. The type is set at creation and never edited. Refunds and corrections carry the same type with a negative amount.
- A desk sale writes one `payment` row. Merchandise additionally writes one `sale` stock movement per item, carrying `payment_id`. A walk-in sale writes no booking and no membership.
- Desk sales accept cash, card and e-wallet only. Online checkout is not used for walk-in or merchandise.
- Discounts are a desk-sale field only. Membership and booking pricing has no discount concept today; do not invent one here.
- `expenses` is append-only, like `payments`. A correction is a negative row pointing at the original.
- Automatic expense rows use a unique `(source_type, source_id, category)` key so a retry cannot post twice. Manual rows have null source keys, and MySQL allows many nulls.
- Expenses and supplier payments are different things. An expense is a cost. A supplier payment is cash out that settles a payable. Reports count each once.
- Receivables are derived with the same formulas the ledgers already use. They are not stored.
- Plan add-ons stay inside the plan's `total_fee`; this packet does not change membership billing. The receivable list covers session add-ons and private coaching fees, which is what R-65's done-when names.

## Schema

Migration `backend/database/migrations/2026_11_14_100000_create_expense_table.php`.

**`expenses`**

| Column | Type | Notes |
|---|---|---|
| `gym_id` | FK cascade | |
| `category` | string(40) | from `config('finance.expense_categories')` |
| `amount` | decimal(12,2) | positive; corrections negative |
| `incurred_on` | date | |
| `method` | string(20), nullable | cash, card, ewallet, bank |
| `note` | string(255), nullable | |
| `source_type` | string(30), nullable | `payroll_run`, `goods_receipt` |
| `source_id` | unsigned big int, nullable | |
| `corrects_id` | FK expenses, nullable | |
| `recorded_by` | FK users, nullOnDelete | |

Unique `(source_type, source_id, category)`. Indexes `(gym_id, incurred_on)`, `(gym_id, category, incurred_on)`.

No new income table.

## Backend tasks

- [x] Model `Expense` with an append-only guard identical in spirit to `Payment`: updates throw except nothing, deletes throw. Relations `recordedBy`, `corrects`.
- [x] `App\Support\IncomeQuery` — one query layer for income, used by the income list and later by reports:
  - `forGym(Gym $gym)` with optional `from`, `to`, `type`.
  - Joins only what is needed to show member and booking context; always filters `gym_id` explicitly.
  - Returns type, amount, method, paid date, member or payer name, receipt, note, corrects id.
- [x] `App\Support\Finance\SaleRecorder::record(Gym $gym, User $actor, array $data): Payment`:
  - Validates method, amount, discount, payer name or member, income type.
  - For merchandise, validates each stock item is `merchandise`, active, and has enough quantity; locks items; computes the total; refuses a discount larger than the total.
  - Creates the payment with `received_by = actor`, `paid_at = now`, receipt number in the existing `OR-{gym}-{000000-id}` pattern.
  - Writes one `sale` movement per merchandise line with `payment_id`.
  - One transaction.
- [x] `App\Support\Finance\Receivables`:
  - Membership part: `amount_charged − paid`, with a grouped payments subquery, only positive.
  - Booking part: status `requested` or `booked`, `fee > 0`, `fee − paid > 0`; includes private coaching.
  - Merge per user, with the sources kept for drill-down.
  - A test compares every row with `MembershipLedger::balance()` and `BookingLedger::balance()`.
- [x] `App\Support\Finance\Expenses` — `post(Gym, string $category, string $amount, Carbon $date, ?string $sourceType, ?int $sourceId, ?User $actor)` used by 6E and 6F. Refuses a duplicate source key.
- [x] Controllers `IncomeController`, `SaleController`, `ExpenseController`, `ReceivableController`.
- [x] Routes:

| Method and path | Controller | Permission |
|---|---|---|
| `GET finance/income` | IncomeController@index | `finance.payments.view` |
| `POST finance/sales` | SaleController@store | `finance.payments.manage` |
| `GET finance/expenses` | ExpenseController@index | `finance.expenses.view` |
| `POST finance/expenses` | @store | `finance.expenses.manage` |
| `POST finance/expenses/{expense}/correct` | @correct | `finance.expenses.manage` |
| `GET finance/receivables` | ReceivableController@index | `finance.receivables.view` |
| `GET my-balance` | ReceivableController@mine | self (member) |

- [x] Paginate income by 25 with `from`, `to`, `type`; paginate expenses by 25 with `from`, `to`, `category`.
- [x] Add `expenses` to `DatabaseSeeder::resetData()` and seed a few expenses for demo gyms.
- [x] Do not touch `MembershipLedger` or `BookingLedger`. `income_type` is derived by the `Payment` hook from 6.0.

## Frontend tasks

- [x] `frontend/src/pages/gym/GymFinance.tsx` at route `finance`:
  - Tabs: **Income**, **Expenses**, **Receivables**. Budgets and Reports are added by 6G.
  - Income: date range, type filter, table, totals by type, link to the payments desk.
  - Record sale dialog: type (walk-in, merchandise, other), buyer name or member, method, amount, discount, note; merchandise line picker with stock and price.
  - Expenses: table, add dialog, correction dialog, category filter.
  - Receivables: member, source, amount, age; member drill-down.
- [x] `MyMemberships.tsx` or the member dashboard shows the combined balance from `GET my-balance`.
- [x] Nav entry "Finance" with `[finance.payments.view, finance.expenses.view, finance.receivables.view]`.
- [x] Types in `frontend/src/lib/finance.ts`; reuse `money()` from `frontend/src/lib/membership.ts`.

## Tests

| File | Must prove |
|---|---|
| `IncomeTest` | Membership payment is `membership`; session is `session`; private coaching is `private_coaching`; PayMongo webhook path gets the same types; correction copies the type; filters by type and date; totals match the payment sum |
| `SaleTest` | Walk-in records income with no user; merchandise lowers stock and records one movement per line; insufficient stock refused; discount cannot exceed total; desk method cannot be PayMongo; receipt number assigned |
| `ExpenseTest` | Add, filter, correct; append-only guard; correction is negative and linked; automatic source key is unique |
| `ReceivableTest` | Every membership and booking row equals the ledger balance; a payment reduces it; requested bookings included; private coaching included; member sees only own; staff need the permission |
| `FinanceTenancyTest` | Another gym's income, expense, sale and receivable return 404 or are absent |

## Checks

`php artisan test`, `php artisan migrate:fresh --seed` on MySQL, `pnpm lint`, `pnpm build`, browser pass as owner, finance and front desk.

## Done when

- [x] A membership payment, a session payment and an online PayMongo payment each appear as income with the right type, without retyping.
- [x] Walk-in and merchandise sales can be recorded; a merchandise sale lowers stock.
- [x] Income can be filtered by type and date range.
- [x] An expense records category, amount, date and note, and can be corrected.
- [x] Each member's balance is visible to permitted staff and to that member, and matches the ledger.
- [x] Unpaid session add-ons and private coaching fees appear in the receivable list.
- [x] All tests, lint and build pass.

## Status notes

- **6 November 2026 — automated build complete; browser pass pending.** Built `2026_11_14_100000_create_expense_table`, `Expense`, `IncomeQuery`, `Finance\SaleRecorder`, `Finance\Receivables`, `Finance\Expenses`, the four finance controllers, `PresentsFinance`, `GymFinance.tsx` (Income, Expenses, Receivables, Payables, Budgets, Reports) and `lib/finance.ts`. Membership, session and online PayMongo rows carry their derived `income_type`; desk sales write one payment and one stock movement per merchandise line. Tests: `IncomeTest`, `SaleTest`, `ExpenseTest`, `ReceivableTest`, `FinanceTenancyTest`, `PaymentIncomeTypeTest` (all green). `DatabaseSeederTest` proves demo expenses, a correction and every stage of income. 6H integration: shared `PageMeta` reader; Finance remains visible to budgets-only and reports-only roles. Browser pass still to run.
- **6 November 2026 — browser pass done.** Owner recorded a walk-in sale (₱500 less ₱50 discount = ₱450 net) and a merchandise sale (2 × ₱500), saw both appear in income with the right type and receipt, and verified stock fell from 10 to 8. Added a ₱25,000 rent expense, corrected it with a reason (negative linked row, total back to zero), and saw the purchase receipts post as two ₱500 expenses. Receivables showed Mika ₱1,400 and Sofia ₱1,200 with source drill-down; Sofia's member account showed the combined ₱2,700 balance and its two private-coaching sources. Income type filtering returned only merchandise. The dev Finance Officer role was stale and initially showed only Income; the owner repaired it through the role editor and all six finance tabs then appeared. Phone width had no overflow.

---

[← Previous: Packet 6C CRM](./phase-4.md) | [Next: Packet 6E Procurement →](./phase-6.md)
