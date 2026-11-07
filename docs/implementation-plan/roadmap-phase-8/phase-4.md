# Packet 8C: Business dashboards

[Overview](00-overview.md) | [Previous](phase-3.md) | [Next](phase-5.md)

Status: Done (7 November 2026; delegated browser gate PASS merged and phase-wide gates closed in 8G). Dependencies: 8.0. Requirements: R-90, R-91, R-92.

## Goal and scope

Create BusinessDashboards; Feature/DecisionSupport{Employee,Money,Stock}Test; frontend {employee,money,stock}-view. Narrowly extend IncomeQuery and Finance/Receivables; edit shared controller/routes/map/access/types/shell. Do not alter Reports/BudgetActual formulas.

## Locked decisions and tasks

- [x] Staff present2/late1/absent1 gives75% attendance/33.33% lateness; exact stored minutes/missing clock-out; no automatic missing-row absences; workload operational, not payroll eligibility.
- [x] Released lines15000/13500/1290 override false run caches; draft/in-review/out-of-range excluded. Exact decision/payroll/HR/finance/owner mask matrix. Without payroll.runs.view omit whole individual-pay key recursively.
- [x] Signed income1000+500-100+200=1600/service400; null/private/cancelled paid service; dual-FK anomaly excluded only from allocation; existing Reports equality.
- [x] IncomeQuery serviceTotals and equivalent half-open date filters; tenant-safe null-retaining joins; old income regression expectations unchanged.
- [x] Receivables summary delegates existing positive-source formula; tenant-safe payment/dimension filters; forUser remains multi-gym. Test corrections/overpayment/inactive debt/requested booking/corrupt links.
- [x] Current snapshot basis/as_of/historical_available false; date range affects revenue, not snapshot. Exact screen/print label: “Outstanding balances as of today; not a historical balance”. User accepted 7 November 2026, no historical reconstruction required.
- [x] Stock at-min low/null minimum/out0/archive/mixed units; selected creation cohorts with stored partly-received/completed-short/cancelled statuses; independent receipt date/total; no status refresh on GET.
- [x] Employee/money/stock screen/print implementations, signed chart, exact snapshot/purchasing basis, no unauthorized action links; owner live screens and money/stock print media checked.
- [x] Restricted-role browser pay JSON/screen/print mask: parent report `08-8C-payroll-browser-gate.md`, PASS, 7 November 2026. Real decision-only and decision+payroll users, released-line totals, sentinel omission and plain-employee nav/direct URL/403 verified. Isolated servers stopped; fixture not rerun.

## Commands and done when

Backend: `php artisan test --filter='DecisionSupportEmployeeTest|DecisionSupportMoneyTest|DecisionSupportStockTest|DecisionSupportAccessTest|RouteAccessTest|FinanceReportTest|ReceivableTest|IncomeTest|FinanceTenancyTest|PayrollRunTest|PayslipTest|StockTest|PurchaseOrderTest'`.

Frontend: `node --test`; `pnpm lint`; `pnpm build`.

Done when pay mask holds at response boundary, finance/current receivables match helpers, prior finance behavior unchanged, date bases explicit and accepted R-91 label verified. Each feature covers empty/day/end/next-day and numeric/money types.

## Dated evidence

7 November 2026, 8G merge: current delegated report supersedes the earlier NOT CLOSED entry below. Verdict PASS; full backend `php artisan test` also passes: exit 0, 593 tests, 27,722 assertions, 199,395 ms. Packet 8C Done; full phase completion remains subject to 8G and independent validation.

7 November 2026 continuation: read `.orchestration/phase-8-decision-support/08-8C-payroll-browser-gate.md`. Verdict NOT CLOSED: fixture setup succeeded but browse forwarding/login/runtime failed before authenticated JSON/screen/print proof. No R-90/browser pass inferred;8CstaysInprogress. Reuse populated isolated fixture, never rerun insertion, and recover responsive browse execution before retrying remaining gate.

7 November 2026: test-first backend red10tests (missing routes; existing corrupt-cross-gym payment bug; stock fixture lacked required location, corrected). Node business-reader red on missing employees contract before implementation. Final packet backend command exit0,86tests/2702assertions/46041ms. Frontend node --test20passed; pnpm lint exit0,101existing unrelated warnings/0decision-support mentions (full log C:\Users\marka\AppData\Local\Temp\opencode\phase8-8C-lint.log); pnpm build exit0,3155modules/1.85s, existing Vite warnings. git diff --check exit0, CRLFconversionwarnings only. Existing finance/payroll/stock/procurement expected formulas unchanged.

OpenCode browse phase8-browse owner/live MySQL: all3business views render. Money selectedNov01..07 income41600/expenses25000/net16600,cashnet16030; manual existing Finance report GETs200 return identical totals (cashin41600/out25570/net16030). Current balances3800/debtors2 stay identical after selecting2020-01-01; server date remainsNov07. Exact required label visible on screen and actual print media, print rootstatic/otherrootshidden/as-ofnotevisible. Stock active9/low1/out0 unchanged for historical range; Oct01..Nov07 purchasing shows approvedrequest1/partlyreceivedorder1, defaultNov purchasing empty. Employee recorded attendance empty and payroll0 truthfully show unavailable rates while operational coach workloads render; owner individual-pay section permitted and empty. Desktop document overflow equalitytrue for all3. New restricted-role network/print privacy matrix and full responsive/theme/long-report checks delegated to parent; no Playwright MCP used. OriginalNov01..07 range and owner browse session retained. Code tree buildable; next implementation packet8D after parent closes/accepts the explicit remaining browser gate. monthlyFinance adapter still belongs to8D, not a placeholder here.
