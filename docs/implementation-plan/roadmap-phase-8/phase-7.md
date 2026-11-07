# Packet 8F: Sustainability

[Overview](00-overview.md) | [Previous](phase-6.md) | [Next](phase-8.md)

Status: Done (7 November 2026; restricted-role and long-pagination browser checks closed in 8G). Dependencies: 8A, 8B, 8C, 8D. Requirement: R-96.

## Goal and scope

Create Sustainability; Feature/DecisionSupportSustainabilityTest; frontend sustainability-view. Edit shared controller/routes/map/access/types/shell only; no new finance formulas.

## Locked decisions and tasks

- [x] Composition equality against standalone financial/mix/membership/utilization services for identical dates; cross-month partial range preserves clipped actuals.
- [x] Selected15Nov-15Dec budget uses whole December; income650/1000 difference350/65%; expense700/500 difference-200/140%; actual after selected end still counts in month; zero budget null. Old BudgetActual strings unchanged.
- [x] Compose monthly finance/backend budget-kind centavo totals/numeric percentages; real cash_flow forecast, never accrual relabelled.
- [x] Five numbered named sections: financial trend, mix, retention/renewal, class/room use, whole-end-month budget plus separately windowed cash outlook. No composite score. Missing budgets do not hide other measures.
- [x] Screen and print-media structure keep all five headings/notes/exception windows. Long3+page rendered pagination remains8G, not passed here.
- [x] Owner browser standalone reconciliation/class-room/negative net/budget/method-window. Exact decision-only endpoint access passes backend tests without source grants.
- [x] Real sole-decision employee renders combined view in new 8G-only database; 12-page report retains all 100 class rows, five measures and notes. Owner/own-payslip regression remains separately open in 8G.

## Commands and done when

Backend: `php artisan test --filter='DecisionSupportSustainabilityTest|DecisionSupportForecastMathTest|DecisionSupportForecastTest|DecisionSupportMembershipTest|DecisionSupportMoneyTest|DecisionSupportServiceTest|BudgetTest|FinanceReportTest|DecisionSupportAccessTest|RouteAccessTest'`.

Frontend: `node --test`; `pnpm lint`; `pnpm build`.

Done when all five reconcile, whole-month/cash-basis labels explicit, numeric new rates comply without changing old finance output and no frontend totals. Include empty/day/final-day/next-day/type checks.

## Dated evidence

7 November 2026: three feature tests initially404 before implementation; Node initially missing sustainability contract. Composition uses existing BusinessDashboards/MembershipMetrics/TrainingDashboards/BudgetActual/Forecast services, no duplicate finance formula. Exact selected-range300income/250expense/50net across clippedNov/Dec, final microsecond included/nextday excluded, wholeDecember650/1000=65%and700/500=140%,difference350/-200,zero-budgetnull,legacy65.00string unchanged,empty measures independent and cash forecast excludes400goods-receipt accrual proved. JSON integer/float rate equivalence normalized in comparisons, money remains exact decimal strings.

Final exact8Fbackend command exit0,43tests/2423assertions/5431ms. Frontend23Node tests pass; lint exit0,0decision-support mentions (C:/Users/marka/AppData/Local/Temp/opencode/phase8-8F-lint.log); build exit0,3159modules/1.87s,existing Vite warnings. All twelve views now implemented, construction placeholders removed.

OpenCode browse owner/live MySQL: all5headings render. Manual authenticated read-only GETs for combined/money/membership/services/cashforecast all200; financial totals,mix,membership,class/roomuse,cashprojection arrays equal standalone. SelectedNov01..07=41600/25000/16600; wholeNovbudget77500income/124000expense,actual41600/25000,53.68%/20.16%. CashNovprojection50600-108605=-58005and nextmonths negative,correctcashlabels/method/history/fit. Width360/390/768/1440 scroll/client345/345,375/375,753/753,1425/1425. Actual print media has1staticroot,5numberedheadings,9tables,wholemonth/cashwindow/fit/negativevalue/notes,otherbodyrootshidden. No long-pagination/PDF/theme/restricted-role pass claimed. OriginalNov01..07/1440px retained; browser session kept for parent follow-up.

8C report08-8C-payroll-browser-gate.md read: NOT CLOSED, blocked browse login/forwarding/runtime.8CstaysInprogress. Stop at8F implementation boundary;8Gnotstarted while earlier gate blocked. No full backend/isolatedMySQL/source-doc close-out claims.
