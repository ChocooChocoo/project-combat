[← Previous: Packet 6F Payroll](./phase-7.md) | [Next: Packet 6H Integration pass →](./phase-9.md)

**Status:** ✅ Done

# Packet 6G: Budgets and sustainability reports

**Goal:** The owner sets a budget per category per month and sees actual income and expenses against it. The owner views cash flow, income against expenses, payroll summaries and pay-out history for any date range, and the figures match the underlying records. Covers R-67 and R-68.

## Requirements and done-when

| # | Requirement | Done when |
|---|---|---|
| R-67 | Budgets and budget against actual | The owner sets a budget per category per month; a view shows budget, actual and the difference |
| R-68 | Sustainability reports | Cash flow, income against expenses, payroll and pay-out summaries can be viewed for any date range, and match the source records |

## Dependencies

- Packet 6D: income and expenses.
- Packet 6E: supplier payments and purchase expenses.
- Packet 6F: released payroll runs.
- Packet 6.0: `finance.budgets.*`, `finance.reports.view`.

## Decisions

- One budget row per gym, kind, category and month. Month is stored as the first day of the month.
- Income budgets use the income types as categories; expense budgets use the expense categories. Both live in the same table with a `kind` discriminator.
- Actual income is grouped by `payments.income_type`. Actual expenses are grouped by `expenses.category`. The difference is `budget − actual`.
- Refunds and corrections are negative rows, so the sums net out naturally.
- Reports take `from` and `to` dates and refuse a reversed range. A range over 62 days is bucketed by month; shorter ranges by day.
- Cash flow counts money actually moving:
  - In: `payments` by `paid_at`.
  - Out: `expenses` where `source_type` is null or `payroll_run`, plus `supplier_payments` by `paid_on`.
  - A `goods_receipt` expense is excluded because the money has not moved; the later supplier payment is counted instead.
- Income against expenses is the accrual view: all expenses, including received purchases, against all income.
- Payroll summary reads released runs only, and cannot disagree with `payroll_lines` because totals are recomputed from the lines, not read from the cached totals alone.
- Pay-out history combines released payroll net pay and supplier payments, by date.
- Every aggregation filters `gym_id` explicitly, even inside a scoped model, because reports use raw queries.
- Month bucketing uses one helper that works on SQLite and MySQL.

## Schema

Migration `backend/database/migrations/2026_11_17_100000_create_budget_table.php`.

**`budgets`**

| Column | Type | Notes |
|---|---|---|
| `gym_id` | FK cascade | |
| `kind` | string(20) | `income`, `expense` |
| `category` | string(40) | income type or expense category |
| `month` | date | first day of the month |
| `amount` | decimal(12,2) | |
| `updated_by` | FK users | |

Unique `(gym_id, kind, category, month)`. Index `(gym_id, month)`.

## Backend tasks

- [x] `App\Support\Sql::month(string $column): string` — `strftime('%Y-%m', ...)` on SQLite, `DATE_FORMAT(..., '%Y-%m')` on MySQL.
- [x] `App\Support\Finance\BudgetActual` — bulk upsert budgets for a month; return per kind and category: budget, actual, difference, percentage.
- [x] `App\Support\Finance\Reports`:
  - `cashFlow(Gym, Carbon $from, Carbon $to): array`
  - `incomeAgainstExpenses(...)`
  - `payrollSummary(...)`
  - `payouts(...)`
  - Each returns `buckets: [{period, ...}]` and `totals: {...}`.
- [x] `BudgetController@index` and `@update` (bulk upsert).
- [x] `FinanceReportController@cashFlow`, `@incomeExpense`, `@payroll`, `@payouts`.
- [x] Routes:

| Method and path | Controller | Permission |
|---|---|---|
| `GET finance/budgets` | BudgetController@index | `finance.budgets.view` |
| `PUT finance/budgets` | @update | `finance.budgets.manage` |
| `GET finance/reports/cash-flow` | FinanceReportController@cashFlow | `finance.reports.view` |
| `GET finance/reports/income-expense` | @incomeExpense | `finance.reports.view` |
| `GET finance/reports/payroll` | @payroll | `finance.reports.view` |
| `GET finance/reports/payouts` | @payouts | `finance.reports.view` |

- [x] Budget access is owner-first: `finance.budgets.manage` is granted to the owner and only to roles the owner chooses. The settings endpoint in 6F already lets the owner decide who holds it.
- [x] Add `budgets` to `DatabaseSeeder::resetData()` and seed a few months for demo gyms.

## Frontend tasks

- [x] `GymFinance.tsx` gains **Budgets** and **Reports** tabs.
  - Budgets: month picker, rows by category, editable amount, actual and difference, income and expense groups, save all.
  - Reports: four cards with a shared date range; bar or table view of buckets; totals highlighted; a "figures match records" footnote is unnecessary, the tests prove it.
- [x] Keep the chart layer minimal: use the existing `Table` and simple CSS bars with `tabular-nums`. No chart library unless one already exists.
- [x] Format money with `money()` and dates with the existing helpers.

## Tests

| File | Must prove |
|---|---|
| `BudgetTest` | Upsert per month; unique per category; income and expense kinds separate; actual equals the source sums; difference sign |
| `FinanceReportTest` | Cash flow in equals payment sum; out excludes `goods_receipt` expenses and includes supplier payments; income against expenses includes all expenses; payroll summary equals line sums per released run; payout history equals released net plus supplier payments; date bucketing switches at 62 days; month helper works on SQLite and MySQL; reversed range refused |
| `ReportTenancyTest` | A second gym's income, expenses, supplier payments and payroll never appear |

## Checks

`php artisan test`, `php artisan migrate:fresh --seed` on MySQL, `pnpm lint`, `pnpm build`, browser pass with a range under and over 62 days.

## Done when

- [x] The owner sets a budget per category per month.
- [x] The view shows budget, actual and the difference.
- [x] Each of the four reports works for any date range.
- [x] Cash flow counts supplier payments once and excludes unpaid purchases.
- [x] Report totals equal the underlying income, expense and payroll records in tests.
- [x] All tests, lint and build pass.

## Status notes

- **6 November 2026 — automated build complete; browser pass pending.** Built `2026_11_17_100000_create_budget_table`, `Budget`, `Support\Sql`, `Finance\BudgetActual`, `Finance\Reports`, `BudgetController`, `FinanceReportController`, and the Budgets and Reports tabs in `GymFinance.tsx` with `lib/reports.ts`. Reports recompute payroll from released lines, exclude `goods_receipt` from cash out and count supplier payments once. Tests: `BudgetTest`, `FinanceReportTest`, `ReportTenancyTest` (all green). `DatabaseSeederTest` proves three budget months and every report source for each demo gym. Browser pass for a short and a long range still to run.
- **6 November 2026 — browser pass done.** Owner saved November budgets (Membership ₱50,000, Rent ₱25,000, Payroll ₱10,000, Purchases ₱5,000) and saw actual, difference and used percentages update. Reports for 1 Oct–6 Nov used daily buckets and matched the records: cash in ₱97,850, cash out ₱11,915.56 (payroll and statutory only on 31 Oct; goods receipts excluded; supplier payment on 6 Nov), payroll summary ₱8,171.28 gross / ₱7,364.50 net / ₱2,744.28 employer, 6 staff, and payouts ₱7,364.50 + ₱1,000. A 1 Aug–6 Nov range switched to monthly buckets; a reversed range showed the client warning and refused the fetch. Phone width had no overflow.

---

[← Previous: Packet 6F Payroll](./phase-7.md) | [Next: Packet 6H Integration pass →](./phase-9.md)
