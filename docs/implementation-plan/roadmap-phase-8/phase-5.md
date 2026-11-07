# Packet 8D: Trends and forecast

[Overview](00-overview.md) | [Previous](phase-4.md) | [Next](phase-6.md)

Status: Done (7 November 2026; full 8G matrix closed). Dependencies: 8A, 8B, 8C. Requirements: R-93, R-94.

## Goal and scope

Create Trends, Forecast; Feature/DecisionSupport{Trend,Forecast}Test and Unit/DecisionSupportForecastMathTest; frontend {trends,forecast}-view. Edit BusinessDashboards monthly adapter and shared controller/routes/map/access/types/shell/chart/Node tests.

## Locked decisions and tasks

- [x] Pure project test100/200/300->400/500/600 and50/100/150->200/250/300, R²1; constant1/noisy0; one/two-month mean; no-history no projections; independent zero clamps/negative net/half-up centavos/next-month keys.
- [x] Twelve ordered year-crossing trend keys; gaps; A/M; first activation; closing retained stock from events older than window; freeze/terminal/sparse-source/future/partial-month cases.
- [x] Earliest known-marker attendance, material membership event and payment dates no later than anchor; null if absent. No fabricated prehistory.
- [x] monthlyFinance folds existing Reports daily buckets into clipped monthly centavo totals on backend.
- [x] Trends own12-month window; Forecast at most12 completed months bounded by actual first-source age, real zero gaps/trailing months retained. Both accrual/cash-flow bases reconcile with Reports.
- [x] Partial final month excluded; completed historical month end included; today at month end excluded; future to capped; refunds retained; supplier payment versus receipt cash distinction.
- [x] Lazy views/basis selector/signed-zero chart/table/print show method/history/excluded month. “R² confidence (fit)” is fit, not probability; short history label exactly “average, not enough history”.
- [x] OpenCode browse real sparse demo: actual4-month default history and2-month historical-range average; no-history range has no forecast table. No seeder or fabricated history. Pure geometry Node test separately proves negative/null/zero-baseline coordinates.

## Commands and done when

Backend: `php artisan test --filter='DecisionSupportTrendTest|DecisionSupportForecastTest|DecisionSupportForecastMathTest|DecisionSupportMembershipTest|FinanceReportTest|DecisionSupportAccessTest|RouteAccessTest'`.

Frontend: `node --test`; `pnpm lint`; `pnpm build`.

Done when source windows/keys/math/bases exact, backend supplies all rates/projections and screen/print method/fit notes visible. Feature tests include empty/day/final-day/next-day/type checks.

## Dated evidence

7 November 2026: backend initial10red for missing routes/Forecast/monthlyFinance; final exact packet filter32passed/2159assertions/4564ms. Unit3mathtests cover n0/1/2/3/12, linear/constant/noisyfit, independent clamp, negative net and half-up centavos. Feature6forecast/2trend tests cover source age/history/bases/boundaries/refunds/old opening state/first source dates and monthly adapter. Node initialred missingfitLabel then21passed. pnpm lint exit0,101preexisting unrelated warnings/0decision-support mentions (temp phase8-8D-lint.log); buildexit0/3157modules/1.55s; diffcheckexit0/CRLFwarningsonly.

OpenCode browse owner/live MySQL: trends2025Dec..2026Nov07,12rows,actual first attendanceOct17 and membership/revenueJul31;81/96=84.38%,closing14,revenue96400. Default forecast actualJul..Oct4months,least-squaresfit0.56/0.6; accrualNovnet-59430 vs cashNovnet-58005, appropriate basis labels. HistoricalAug31 cutoff gives2months/average1400incoming per futuremonth/R²unavailable. Year2000nohistory displays no projections/table. Printmedia retains method/fit/effective history/notes, staticroot; trend print12rows and actual source dates. OriginalNov01..07range and income_expensebasis restored; no Playwright MCP. Full responsive/role/long-report/artifact matrix remains8G(parent). Next8E.
