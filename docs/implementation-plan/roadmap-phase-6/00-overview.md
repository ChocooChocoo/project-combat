[Next: Packet 6.0 Foundations →](./phase-1.md)

**Status:** ✅ Done

# Roadmap Phase 6: The business side, fed by training (R-56 to R-79)

**Goal:** The owner and permitted staff run the whole business side from the same system: staff attendance and overtime, payroll for everyone including coaches, income and expenses, money owed in both directions, budgets, customer leads and follow-ups, stock, and purchasing. Training feeds the business side directly.

This folder holds the build plan for all 24 Phase 6 requirements. Every packet file is self-contained: goal, dependencies, decisions, schema, tasks, invariants, tests, and a done-when list.

## Packets

| File | Packet | Requirements | Waits for | Status |
|---|---|---|---|---|
| [phase-1.md](./phase-1.md) | 6.0 Foundations | enabler only | — | ✅ Done |
| [phase-2.md](./phase-2.md) | 6A HR extras | R-56, R-57, R-58 | 6.0 | ✅ Done |
| [phase-3.md](./phase-3.md) | 6B Inventory | R-72, R-73, R-74 | 6.0 | ✅ Done |
| [phase-4.md](./phase-4.md) | 6C CRM | R-69, R-70, R-71 | 6.0 | ✅ Done |
| [phase-5.md](./phase-5.md) | 6D Finance core | R-63, R-64, R-65 | 6.0 (merchandise part also 6B) | ✅ Done |
| [phase-6.md](./phase-6.md) | 6E Procurement | R-75, R-76, R-77, R-78, R-79, R-66 | 6B, 6D | ✅ Done |
| [phase-7.md](./phase-7.md) | 6F Payroll | R-59, R-60, R-61, R-62 | 6A, 6D | ✅ Done |
| [phase-8.md](./phase-8.md) | 6G Budgets and reports | R-67, R-68 | 6D, 6E, 6F | ✅ Done |
| [phase-9.md](./phase-9.md) | 6H Integration pass | all screens, nav, docs | all | ✅ Done |

## Locked decisions

Settled with the user on 5 November 2026.

| Decision | Value |
|---|---|
| Execution shape | Sequenced packets. Each packet ends with tests, build and lint, a browser check, and a review stop. |
| Payroll approval chain | Per gym, 1 to 3 steps from `hr_review`, `finance_review`, `owner`. Owner is always the last step. Default is `["owner"]`. The owner may release a run they prepared only when the chain has one step; the run records `self_released = true`. |
| Staff lateness (Q-14) | Late when clock-in is later than the scheduled start plus the gym's grace (default 10 minutes). Late minutes count from the scheduled start, not from the end of the grace. Undertime is deducted. |
| Pay defaults | Regular holiday worked: +100% of the daily rate. Special holiday worked: +30%. Overtime: 1.25 times the minute rate. Contractors get no SSS, PhilHealth, Pag-IBIG or withholding tax. |
| Old-system extras | Leave-to-cash (Q-01) dropped. GPS-fenced check-in (Q-03) dropped. Absence explanations (Q-02) kept as a plain text field on the employee's own attendance row, with no approval step and no effect on pay. |
| RBAC prerequisite | The uncommitted permission catalog refactor gets its own commit before packet 6.0 starts. |
| Pay frequency | Gym setting, default semi-monthly (1-15 and 16-end of month). Monthly is an option. |
| Purchase expense timing | A received purchase becomes an expense at receipt. A supplier payment is cash out and settles the payable; it is never added to expense totals a second time. |
| Performance notes | Visible only to holders of `hr.performance.view`, never to the employee being reviewed. |
| Stock location | One storage place per item, stored as text. Per-location quantities are out of scope. |
| Owner purchasing | Nobody approves their own request, owners included. If no other approver exists, the owner may raise a purchase order directly from an approved request, recorded as an owner direct purchase. |
| Activity supply needs | Entered by hand and linked to a training session or a date. Automatic per-service usage rules are out of scope. |
| Payslips | In-app, print-friendly page. No PDF package. |
| Statutory rates | Effective-dated rows seeded from verified circulars. Old periods keep the rates that were effective then. |

## Dependency order

```
6.0 Foundations
 ├── 6A HR extras ──────────────┐
 ├── 6B Inventory ──┐           │
 ├── 6C CRM         │           │
 └── 6D Finance core┴── 6E Procurement
                        │         │
                        └── 6F Payroll
                              │   │
                              └── 6G Budgets and reports
                                        │
                                    6H Integration
```

6A, 6B, 6C and 6D may be built in parallel once 6.0 lands. 6E needs 6B and 6D. 6F needs 6A and 6D. 6G reads every money source. 6H is the final pass.

**Close-out state (6 November 2026):** every packet is built and the gates are green: 476 backend tests, frontend lint and build, an isolated MySQL fresh migrate and seed, and the browser matrix with one printed payslip. Phase 6 is done.

## Architecture in one page

**Money in.** The existing immutable `payments` table stays the only money-in ledger. It gains `income_type`, `discount` and `payer_name`. `income_type` is derived when a payment is created: membership payments are `membership`; session payments are `session`, or `private_coaching` when the session type says so; desk sales pass `walk_in`, `merchandise` or `other` explicitly. Corrections copy the original's type. PayMongo, desk payments, refunds and corrections all pass through the same two ledger classes, so nothing needs retyping.

**Money out.** New append-only `expenses` table with a category, a signed amount, a date and an optional source key. Payroll release writes a `payroll` row and a `statutory_contributions` row. Goods receipt writes a `purchases` row. Manual expenses are entered by staff. `supplier_payments` is a separate append-only table.

**Balances.** Receivables and payables are derived, never stored. Member balances equal the existing membership and booking ledger formulas. Supplier payables equal received totals minus supplier payments.

**Payroll.** A pure calculator works in integer centavos from stored records. Rates, day counts, line items and statutory bracket ids are snapshotted onto the line at compute. Once submitted, lines are frozen. The approval chain is a small state machine; there is no endpoint that sets released directly.

**Stock.** Quantity is a cache of the movement sum. It can only change through `StockLedger::move()`, which writes `balance_after` and a history row. Direct quantity edits throw.

**Procurement.** Request, approve, order, receive. Receiving locks the purchase order, adds stock movements, increases received quantities, recomputes status, and posts the expense in one transaction.

**Permissions.** The catalog grows from 51 to 70 exact strings. The four `general` placeholder modules (`payroll`, `customers`, `inventory`, `procurement`) are replaced with real resources and a migration maps old grants level for level. Owners gain everything automatically; employee roles gain only what the mapping gives them.

**Notifications.** Reuse the existing database notification pipeline and `Gym::peopleWithPermission()`. New notices: overtime requested/decided, payroll submitted/decided/released, low stock, purchase request decided, purchase status changed, follow-up assigned.

## Requirement coverage

| Requirement | What it means | Packet |
|---|---|---|
| R-56 | Staff attendance, lateness and absences | 6A |
| R-57 | Overtime requests and approval | 6A |
| R-58 | Performance notes | 6A |
| R-59 | Payroll for every employee, coaches included | 6F |
| R-60 | Philippine statutory deductions | 6F |
| R-61 | Payroll approval and release | 6F |
| R-62 | Payslips and payroll history | 6F |
| R-63 | Income recorded automatically from training | 6D |
| R-64 | Expenses, including payroll and purchases | 6D, 6E, 6F |
| R-65 | Money members owe | 6D |
| R-66 | Money owed to suppliers | 6E |
| R-67 | Budgets and budget against actual | 6G |
| R-68 | Sustainability reports | 6G |
| R-69 | Leads, customers, inquiries and follow-ups | 6C |
| R-70 | Interaction and feedback history | 6C |
| R-71 | Turn a lead into a member; customer groups | 6C |
| R-72 | Stock items with quantity and place | 6B |
| R-73 | Stock movements and history | 6B |
| R-74 | Minimum levels and low-stock alerts | 6B |
| R-75 | Restock list from minimums and activities | 6E |
| R-76 | Purchase requests with approval | 6E |
| R-77 | Suppliers and purchase orders | 6E |
| R-78 | Receiving updates stock, payable and expense | 6E |
| R-79 | Purchase status tracking and notices | 6E |

## Execution protocol

1. Read the packet file fully. Read the files it names.
2. Set the packet status to **In progress** and add a dated note.
3. Build test-first. Write the failing test, make the smallest change, run the test.
4. Run the packet's checks: `php artisan test`, then `pnpm lint` and `pnpm build` when the frontend is touched.
5. Update the packet file after each task: tick a box only when the task is verified, and record files changed and command results in the status notes.
6. Stop at the packet boundary. The next packet starts only after review.
7. Never commit unless the user asks. When asked, commit only the files the packet names.

Each packet brief handed to an implementer must carry that packet file plus the decisions above. No implementer should have to re-derive a product rule.

## Global checks

Run from `backend/`:

```bash
php artisan test
php artisan migrate:fresh --seed   # MySQL, once per packet that changes schema
php artisan app:sync-permissions
```

Run from `frontend/`:

```bash
pnpm lint
pnpm build
```

Browser check at the end of any packet with screens: owner, a permitted employee, a plain employee and a member where relevant. Check the empty state, the loading state, the error state, a phone-width layout, and that nav visibility matches permissions.

## Out of scope for Phase 6

- 13th-month pay, loans, cash advances, leave conversion to cash.
- Full double-entry accounting, tax filing, government reporting.
- Supplier returns and debit notes.
- GPS-fenced staff check-in.
- Automatic per-service supply usage rules.
- PDF payslips and emailed payslips.
- Follow-up due reminders (R-82, later phase). Phase 6 stores the data the reminder needs.
- Announcement delivery (R-80, later phase). Phase 6 stores customer groups.

## Documentation to keep in step

- `docs/ANALYSIS - COMBAT SPORTS PLATFORM/05 - ROADMAP/phase-6/PHASE 6.md`: statuses, per-requirement notes, build record.
- `docs/ANALYSIS - COMBAT SPORTS PLATFORM/05 - ROADMAP/05 - ROADMAP.md`: Phase 6 counts, and correct the stale Phase 5 row.
- `docs/ANALYSIS - COMBAT SPORTS PLATFORM/00 - START HERE/00 - START HERE.md`: change log.
- `docs/ANALYSIS - COMBAT SPORTS PLATFORM/06 - SCREENS BY ROLE/06 - SCREENS BY ROLE.md`: mark S-28, S-29, S-31, S-32, S-33, S-34 built.

Known doc drift to fix in packet 6.0: the Phase 6 "What it waits for" table still shows R-23 to R-27, R-42 and R-51 as open, and the roadmap still shows Phase 5 as not started. Both are stale.

---

[Next: Packet 6.0 Foundations →](./phase-1.md)
