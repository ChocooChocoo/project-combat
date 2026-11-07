# Packet 8G: Integration and close-out

[Overview](00-overview.md) | [Previous](phase-7.md) | Next: complete (independent validation in `.orchestration/phase-8-decision-support/07-validation.md`)

Status: ✅ Done (7 November 2026; independently validated 14/14 frozen rows met). Dependencies: all earlier packets. Requirements: all Phase 8.

## Goal and scope

Create Feature/DecisionSupport{Tenancy,ReadOnly}Test. Update access/route/Node tests and only task-relevant defects inside already listed application boundaries. Maintain orchestration implementation/verification reports; parent alone writes validation. Update source Phase8/roadmap/start-here/screens and narrowly append genuinely new CLAUDE.md conventions, preserving user edits.

## Locked decisions and tasks

- [x] All13 routes exact access matrix: owner/decision-only; no source gates; payroll-only deny; HR/finance cannot unmask; disabled catalog/roles and expired grants; unauth/member/admin/foreign gym; every date/basis422 case.
- [x] Two-gyms exact-value12-view matrix; distinctive facts and corrupt cross-gym links cannot leak names/counts/money/events/payroll. Multi-gym users stay isolated; optional null buckets preserved.
- [x] Domain/jobs/notification snapshots before/after every GET unchanged. SQL listener sees no writes; auth infrastructure excluded from domain snapshot claim.
- [x] Full SQLite suite; new isolated MySQL schema name assertion/fresh seed/sync/stock count; real all12-view MySQL aggregate execution, Sunday0/hour0/ONLY_FULL_GROUP_BY. Never fresh development database.
- [x] Frontend Node/lint/build and complete browser role/entry/view/state/network matrix, no inactive fetch/poll/stale display/old print. Owner/sole-decision/plain, five-role closeout, pure payroll-only and front-desk denials pass.
- [x] Twelve views at360/390/768/1440, no document overflow; keyboard visible focus/date-basis labels/charts value tables; light and dark.
- [x] Every active view prints alone; long attendance/athlete/sustainability3+ pages have first/middle/last rows, repeated headers, no clipping/blank pages/controls. Existing owner/own-payslip print unchanged after the shared CSS fix.
- [x] Live source reconciliation and exact screen/print R-91 current snapshot/R-87 inferred-coach labels; accepted limitations dated7Nov documented.
- [x] Source Phase8 item statuses only after frozen checks; roadmap totals honest, Q-15 resolved12/3/R²/short-history, start-here provenance accurate, S-36 scope/permission/pay mask/date/print notes.
- [x] git diff --check/status/stat/exclusion; preserve CLAUDE.md/P04.md, no commit/push. Independent validation complete: `07-validation.md` records Phase 8 complete, 14/14 rows met.

## Commands and done when

Backend: `php artisan test --filter=DecisionSupport`; `php artisan test --filter='SqlTest|RouteAccessTest|FinanceReportTest|ReceivableTest|IncomeTest|FinanceTenancyTest|BudgetTest'`; `php artisan test` (timeout at least600 seconds). Isolated MySQL exact safe sequence from frozen plan, never development fresh.

Frontend: `node --test`; `pnpm lint`; `pnpm build`. Root: `git diff --check`; `git status --short`; `git diff --stat`; orchestration `git check-ignore -v`.

Done when every frozen executable acceptance check has concrete evidence, unexplained failed/unrun gates absent, accepted snapshot exception explicit and docs match actual completion. Historical receivables reconstruction is not required; all other frozen checks remain required.

## Dated evidence

7 November 2026, latest boundary: 8C prerequisite now PASS, merged and marked Done. 8G remains In progress, not phase-complete. Added `DecisionSupportTenancyTest` (exact indicators for all twelve views, same viewer in two gyms, foreign booking/event/payment/payroll links cannot change first-gym payload) and `DecisionSupportReadOnlyTest` (all thirteen GETs, all-table snapshots excluding auth infrastructure, SQL write listener). Access tests extend unauthenticated/foreign/admin/disabled/expired/inactive denial across all thirteen routes; existing member/payroll/HR-finance and owner/decision/date tests remain.

Full backend final: exit 0, 595 tests / 27,884 assertions / 275,878 ms. Focused combined decision-support/SQL/routes/finance/receivables/income/tenancy/budget: exit 0, 90 tests / 3,017 assertions / 14,594 ms. Initial corrupt fixture violated booking uniqueness; changed only one foreign booking link and reran green, without changing production behavior or expected values.

MySQL throwaway `combat_phase8_8g_20261107_01`: CREATE must succeed without IF NOT EXISTS; no config cache; process-only database/driver/empty DB_URL, array mail and sync queue. Runtime driver/name asserted before `php artisan migrate:fresh --seed --force` (exit 0), then `app:sync-permissions`, `app:check-stock-counts`, all twelve real controller methods (200), stored payment/report 96400.00 equality, Sunday/hour zero and ONLY_FULL_GROUP_BY verified. Initial smoke used wrong seeded gym name, corrected to actual seed name; rerun exit 0. Dropped only this newly created schema, exit 0. No development reset or `.env` changes.

Frontend final: `node --test` 23 passed / 0 failed, exit 0; lint exit 0 / 101 preexisting warnings / 0 decision-support mentions (`phase8-8G-lint.log` in approved temp directory); build exit 0 / 3159 modules / 1.88s, existing Vite warnings. Shared print selector specificity fixed after actual PDF exposed blank pages; new PDFs and computed text visibility prove fix. No dependency added.

Browser evidence: owner all twelve views at 360/390/768/1440 with equal document scroll/client widths; real historical and empty attendance range, restored November dates. Separate new SQLite fixture at `C:/Users/marka/AppData/Local/Temp/opencode/phase8-8g-browser-20261107.db`, seeded via process-only env and extended once with 100 stored class/results, two real employees. Sole-decision `/me` confirms exactly one permission and all twelve views render at 390px. Plain employee API 403, no decision nav and direct-URL denial. Delegated 8C payroll mask PASS cited, not rerun. Light/dark existing CSS class checks all twelve, no overflow; keyboard ArrowRight traverses all tabs in viewport with visible 2px outline after keyboard input. Populated low-use problem: 100 sessions, held/capacity100/800, mean12.5%, IDs shown.

Print artifacts in excluded orchestration folder: twelve `8G-owner-*.pdf`; long attendance/athlete/sustainability9/9/12pages, all100records/no blank pages checked by `8G-check-print.py`, exit0. First/middle/last rendered PDF pages inspected; repeated headers, notes and no clipping. `8G-light.png`, `8G-dark.png`; range-only redacted HAR `8G-active-range.har`. Browser native print activation not passed by the attempted spy; page reloaded and native `window.print` function confirmed restored.

**Final close-out, 7 November 2026.** The previously open rows are closed:

- Own-payslip and owner-managed payslip print pass through the existing dialog and mounted print sheet; values 1,000/100/900 and 22,500/3,606.80/18,893.20 match. `09-8G-browser-closeout.md`.
- Five-role matrix, native Print/Retry keyboard activation, invalid/empty/stale/basis states and both payslip regressions pass. `09`.
- Pure payroll-only and real front-desk roles have no Decision support nav, a direct-URL denial and API 403 for options and a representative metric. `10-8G-remaining-roles.md`.
- Independent validation updated `07-validation.md` to **Phase 8 complete, 14/14 frozen rows met**; quick confirmation 60 tests / 1,027 assertions, no application-diff change.
- Source phase note, roadmap, start-here and screens note now record all twelve items ✅ and **98 finished / 0 open**.

Earlier "pending", "blocked" or "not run" statements in this packet are historical boundary notes, superseded by this close-out. New 8G sessions were reset and owned 8009/5176 processes stopped; real 8000/5173 remain running. Temp 8G SQLite files are retained as reproducible verification artifacts; never rerun their insertion fixtures.

7 November 2026 continuation handoff:8E/8Fimplemented and packet commands green.8Cdelegated reportNOTCLOSED prevents phase completion;8Gnotstarted at this clean packet boundary. Remaining all tasks above, plus8Epopulated/boundary browser and8Frestricted-role/long-pagination. Full backend suite and isolatedMySQLall12have not been run by continuation. Source analysis docs andCLAUDEremain unchanged, statuses must not claim phase complete.
