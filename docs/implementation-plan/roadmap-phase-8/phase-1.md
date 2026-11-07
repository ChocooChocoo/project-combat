# Packet 8.0: Foundations

[Overview](00-overview.md) | Previous: none | [Next](phase-2.md)

Status: Done (7 November 2026; active-payload integration verified in 8A). Dependencies: none.

## Goal and scope

Permission-gated options endpoint, route/nav/shell, Manila date range, strict transport, shared states/charts and print infrastructure. No unfinished dashboard supplies estimated metrics.

Create backend config, DecisionSupportController, DecisionSupport/{Period,Metrics}, tests Feature/Concerns/DecisionSupportSetup, Feature/DecisionSupportAccessTest and Unit/{DecisionSupportPeriodTest,SqlTest}. Create frontend GymDecisionSupport, lib/decision-support, hooks/use-decision-support, components/decision-support/{dashboard-parts,range-picker,trend-chart,print-sheet}, tests/decision-support.test.mjs. Create this overview and eight packet files. Edit only backend Sql/routes/RouteAccessTest and frontend App/gym-nav/index.css.

## Decisions and tasks

- [x] Record baseline status, CLAUDE.md diff and orchestration exclusion without reverting/stashing.
- [x] Write owner/decision-only 200; plain/payroll-only/member 403; foreign/admin 404; unauthenticated 401; disabled/expired role denial tests first.
- [x] Write Period boundary/copy/Manila validation and Sql driver/unsafe identifier tests first.
- [x] Implement options/config/Period/Metrics/Sql only; add only implemented routes and exact route-map entry.
- [x] Append nav item at end of ITEMS under Business; keep homePath and existing landing order unchanged.
- [x] Build permission-denied/options loading/error/retry/date-range shell with explicitly unfinished panels.
- [x] Add lazy fetch/stale suppression/abort and strict response helpers, never malformed-to-zero fallback. Disable print without successful active payload.
- [x] Add direct-body print portal and scoped static-flow print CSS, preserving payslip rules.
- [x] Node tests: all keys/URL, real ordered dates, null versus zero rates, signed money, malformed/no fallback totals, forecast labels.
- [x] Browser: live owner/nav/unchanged first landing; decision-only client fixture gym-switcher entry (real backend role separately covered in AccessTest).
- [x] Browser: invalid range/options retry; 8A closes stale-response suppression, immediate print disable/root removal on range change, one successful active print root.

## Commands

Backend: `php artisan test --filter='DecisionSupportAccessTest|DecisionSupportPeriodTest|SqlTest|RouteAccessTest|FinanceReportTest'`.

Frontend, separately: `node --test`, `pnpm lint`, `pnpm build`.

## Done when

Exact options gate, date boundaries and Sql regression pass; permitted nav and gym-switcher entry verified; malformed payload fails explicitly; print root singular; no R-85 completion claim.

## Dated evidence

7 November 2026: backend red observed before implementation (missing route/classes/helpers); corrected test fixtures to actual permission/role status columns before green. Final backend gate passed24tests/1711assertions/2980ms; frontend Node16passed, pnpm lint (and quiet rerun) exit0, build exit0/3144modules/1.61s. git diff --check exit0 (existing CRLF conversion warnings). Browser live owner still lands at /manage/staff; one options API request after fixing StrictMode duplicate; date clamp/invalid state, injected503+Retry and four widths pass. Decision-only UI used explicitly mocked /me permissions with live owner-backed options, not a real restricted-role browser login. Active view/stale/print integration remains unchecked until 8A; no full requirement claimed.

7 November 2026, 8A addendum: remaining foundation integration exercised. A real isolated employee whose /me response contains exactly decision_support.general.view signs in at /, then gym switcher->manage->decision-support. Slow old October response cannot replace November payload; pending request disables Print and removes print root; successful root exactly1. Keyboard Home/End reaches all tabs, selected tab scrolls into phone viewport, explicit2px focus outline observed. Packet done; full8G matrix remains separate.
