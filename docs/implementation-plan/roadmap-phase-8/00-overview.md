# Phase 8: Owners decide with data

Status: ✅ Done. Started 7 November 2026; finished and independently accepted 7 November 2026.

## Goal

Deliver R-85 through R-96 through eight dashboards and four analysis views. Read existing records, calculate figures on the server, and expose no write endpoint. No new tables, migrations, indexes, dependencies or fabricated history.

## Packets and coverage

| Plan | Packet | Requirements | Dependencies | Status |
|---|---|---|---|---|
| [1](phase-1.md) | 8.0 Foundations | Enabler | None | Done |
| [2](phase-2.md) | 8A Training | R-85, R-87, R-88 | 8.0 | Done |
| [3](phase-3.md) | 8B Members/customers | R-86, R-89 | 8.0 | Done |
| [4](phase-4.md) | 8C Business | R-90, R-91, R-92 | 8.0 | Done |
| [5](phase-5.md) | 8D Trends/forecast | R-93, R-94 | 8A, 8B, 8C | Done |
| [6](phase-6.md) | 8E Problems | R-95 | 8A | Done |
| [7](phase-7.md) | 8F Sustainability | R-96 | 8A, 8B, 8C, 8D | Done |
| [8](phase-8.md) | 8G Integration | All | All earlier packets | Done |

Build serially in this order. Each packet records tests, commands, results and outstanding evidence before the next starts. No commits or pushes.

## Locked decisions

- Exact `decision_support.general.view` permission opens every view. Only `payroll.runs.view` reveals individual payroll rows. Source-module permission grants are not required.
- Every aggregate source, subquery and optional gym-owned dimension is explicitly tenant-filtered. The area is read-only.
- Manila day ranges use half-open boundaries. Counts are integers, percentages numeric or null for missing denominators, money two-decimal strings. SQL date expressions use `App\Support\Sql`.
- Known attendance markers survive current booking/session cancellation. Operational offerings exclude cancelled sessions. Current held seats are requested plus booked, not reconstructed historical occupancy.
- Retention uses opening event-state cohort minus distinct churn from that cohort. Renewal actions are separately divided by renewal actions plus expirations.
- Training effectiveness is the share of attendees with progress. Coach partition is one attendance-based winner, never recorder or causal attribution.
- Forecast fits at most twelve completed months, predicts three, shows method and R² fit. Fewer than three observed months uses an explicitly labelled average; no history produces no projections.
- Problem thresholds come from server config and compare unrounded ratios. Falling attendance: four against four, drop at least 20 points, current below 60%. Low-use: at least three sessions, mean fill below 25%. No-show: at least five marked outcomes, rate at least 30%.
- Finance formulas reuse Reports, BudgetActual, Receivables and IncomeQuery. Budget uses the whole end month; cash-flow forecast remains cash-based. No frontend business arithmetic.
- Print reuses the successful active payload through one direct-body static-flow portal. No export package.

## User-accepted limitations, 7 November 2026

R-91: user chose “Accept current snapshot (Recommended)”. Revenue and income/expense use selected dates. Outstanding balances remain current, labelled exactly **“Outstanding balances as of today; not a historical balance”**, with `basis=current_snapshot`, server-today `as_of`, and `historical_available=false`. No historical charge ledger or reconstruction is required.

R-87: user chose “Accept attendance-based coach (Recommended)”. Show **“coach based on attendance”** and a visible note that attendance context is inferred and not causal. Preserve unassigned entries.

7 November 2026, 8E boundary: eleven views implemented. Backend packet23tests/2027assertions, frontend22Node/lint/build pass. Live owner no-flags/empty/configured thresholds,390px and print structure checked. Populated boundary browser fixture remains8G, not claimed passed. Next8F;8Cstill awaits separate gate report.

7 November 2026, 8F boundary: all twelve views implemented; final8Fbackend43tests/2423assertions,frontend23Node/lint/buildpass. Five combined measures reconcile with live standalone endpoints; all4widths and5heading/9table/staticroot print structure checked, not long pagination. Read08-8C-payroll-browser-gate.md: verdict NOT CLOSED (browse runtime/forwarding/login blocked).8CremainsInprogress;8Gnotstarted pending earlier gate. The prior hidden-directory glob missed the report; direct file read supersedes earlier absence statement. Full backend/MySQL/tenancy/read-only/browser/source-doc gates remain unrun, phase incomplete.

Final regression follow-up: exact20-point repeating fractions initially exposed float-subtraction threshold miss; count cross-products now preserve equality without rounding. Latest8Epacket24tests/2064assertions, entireDecisionSupport58tests/865assertions pass. Still no full backend-suite or isolatedMySQLgate claim.

## Global gates

- [x] Every frozen R-85 through R-96 acceptance row has exact test and browser evidence; independent validation records 14/14 rows met.
- [x] All thirteen endpoints pass access matrix; all twelve views pass exact-value two-gym and read-only tests.
- [x] `php artisan test --filter=DecisionSupport`: exit 0, 60 tests / 1,027 assertions.
- [x] `php artisan test --filter='SqlTest|RouteAccessTest|FinanceReportTest|ReceivableTest|IncomeTest|FinanceTenancyTest|BudgetTest'`: exit 0, 30 tests / 1,990 assertions.
- [x] `php artisan test` with at least 600-second timeout: final exit 0, 595 tests / 27,884 assertions.
- [x] Fresh seed and permission sync only in a newly created throwaway MySQL schema, runtime name assertion and all twelve controller aggregate paths pass. Schema dropped.
- [x] Frontend `node --test` (23 pass), `pnpm lint` (0 errors, 101 existing warnings), `pnpm build` (exit 0).
- [x] Browser roles, entry route, twelve views, loading/empty/error/retry/forbidden/invalid/stale states, active-only requests, no polling. Payroll-only and front-desk denials closed by `10-8G-remaining-roles.md`; five-role matrix and representative states closed by `09-8G-browser-closeout.md`.
- [x] All twelve views at 360/390/768/1440px, keyboard, light/dark themes, exact pay mask, live reconciliation.
- [x] Every active report prints; long attendance/athlete/sustainability reports span 3+ pages without missing rows or blank roots. Existing own/owner payslip print unchanged after the shared CSS fix.
- [x] Source docs updated to the accepted final status: **98 finished / 0 open**, all twelve Phase 8 items ✅.
- [x] `git diff --check` exit 0; diff/stat/status inspected; preexisting `CLAUDE.md` and `docs/prompts/P04.md` preserved; `.orchestration/` excluded.

## Evidence protocol

7 November 2026, current 8G boundary (supersedes historical blocked-8C notes below): delegated 8C gate PASS merged; 8C Done. Added exact two-gym/corrupt-link and read-only tests; expanded endpoint denial matrix. Combined focused gate 90 tests / 3,017 assertions passes. Full suite 595 tests / 27,884 assertions passes. MySQL `combat_phase8_8g_20261107_01` fresh-seed/sync/stock and all twelve controller paths pass under ONLY_FULL_GROUP_BY; stored/report income both 96,400.00, Sunday/hour zero; schema dropped. Frontend 23 tests/lint/build pass.

Owner all twelve views at 360/390/768/1440, live range/empty checks and twelve PDF reports verified. Fresh, separate 8G-only SQLite database and ports 5176/8009 used for real sole-decision all-view access, ordinary-employee denial, populated low-use evidence, existing light/dark CSS classes, keyboard tab traversal and 100-row long reports. No old payroll-gate database/server reused. Shared print visibility specificity defect fixed after six blank pages reproduced; reports now contain text. Long attendance/athlete/sustainability PDFs have 9/9/12 nonblank pages, all 100 records and repeated headers.

**Final close-out, 7 November 2026.** The two remaining browser-role rows were closed by `10-8G-remaining-roles.md`: a real pure payroll-only employee and the real front-desk employee both have no Decision support nav, a direct-URL denial, no metrics and API 403 for options and a representative metric. The independent validation seat updated `07-validation.md` to **Phase 8 complete, 14/14 frozen rows met**, with a quick confirmation gate of 60 tests / 1,027 assertions and no application-diff change. The full evidence chain is `08` (populated payroll mask), `09` (payslip print, five roles, keyboard and states) and `10` (remaining roles). This supersedes every earlier "8G remains In progress" or "phase incomplete" note in this file and in the packet plans; those are historical boundary records, not current status.

Implementation evidence: `.orchestration/phase-8-decision-support/05-implementation-report.md` and `06-verification.md`. Independent validation: `07-validation.md` (Phase 8 complete, 14/14 rows met). Browser evidence: `08`, `09` and `10`. The source Phase 8 note, roadmap, start-here and screens note now record all twelve items ✅ and **98 finished / 0 open**. No commits or pushes were made.

7 November 2026: baseline contains only preexisting `CLAUDE.md` modifications and untracked `docs/prompts/P04.md`; exclusion confirmed. No requirement completion claimed.

7 November 2026, 8A boundary: 8.0 and 8A packet checks pass; next packet is 8B. Latest backend packet gate74passed/2250assertions; frontend18Node tests/lint/build pass. Owner used live existing MySQL records; genuine decision-only employee used isolated SQLite fixtures and the same UI. Packet evidence includes four widths, shared503/retry/stale-range checks, one active print portal and active-report PDFs. Full phase security/read-only/isolated-MySQL-all-views/long-pagination/theme matrix still belongs to8G and is not claimed. Source requirement statuses remain unchanged until close-out.

7 November 2026, 8B boundary: 8.0/8A/8B implemented; next8C. Backend8B33passed/1944assertions, frontend19Node tests/lint/build pass. Browser tooling changed to OpenCode browse extension at user request; no subsequent Playwright MCP use. Live owner membership/customer range/notes/print isolation verified; new restricted-role/full responsive/theme/long-print browser matrix delegated to parent for8G. Five of twelve metric views now implemented. Full phase completion/source statuses not claimed.

7 November 2026, 8C implementation boundary: eight dashboard views implemented; four analysis views8D..8F not started. Latest8Cbackend86passed/2702assertions, frontend20Node tests/lint/build pass. Backend payroll mask matrix passes; restricted-role real browser network/print gate explicitly delegated to parent and keeps8CstatusInprogress. Owner OpenCode browse verified business views, exact current-balance label on screen/print, unchanged3800snapshot across historical dates, stable stock9/low1 with purchasing range changes, and source Finance totals equality. No full phase completion or source status updates claimed. Next code8D once remaining browser gate is closed/accepted by parent.

7 November 2026,8D boundary: parent authorized continuing while8Cpayrollbrowsergate remains delegated.8Dpacketcomplete,10of12viewsimplemented; next8E. Latestbackend32passed/2159assertions; frontend21Node/lint/buildpass. OpenCode live source-backed12monthtrend,4-monthleast-squares,2-monthaverage,distinctcash/accrual,negative net,no-history and printnotes verified. No source-analysis/CLAUDE status changes before8G;8CstillInprogresspendingparentgate.
