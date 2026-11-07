# Packet 8B: Members and customers

[Overview](00-overview.md) | [Previous](phase-2.md) | [Next](phase-4.md)

Status: Done (7 November 2026; real-role/browser matrix closed in 8G). Dependencies: 8.0. Requirements: R-86, R-89.

## Goal and scope

Create MembershipMetrics, CustomerMetrics; Feature/DecisionSupport{Membership,Customer}Test; frontend {membership,customer}-view. Edit shared controller/routes/map/access fixture/types/shell only.

## Locked decisions and tasks

- [x] Opening cohort4/churn2/retained2/50%; frozen and null-end session plan retained. Cover new-then-cancel, never-active cancel, expired-unpaid renewal, paid reactivation, duplicate churn, timestamp/ID ordering.
- [x] Renewal actions3/expiry2 gives60%; unpaid renewal does not activate; repeated activation not new; exactly-S activation new, not opening; mutate current dates/status without changing historical result.
- [x] Implement exact tenant-scoped EXISTS/NOT EXISTS material-event ordering. Share activeIdsAt/firstActivations. Missing activation evidence yields quality count, never fallback. Zero-fill selected months.
- [x] Four created leads/two linked activated gives50%; old lead only separate gained count; old/duplicate activation, late link and current-stage independence proved.
- [x] Opening concern backlog, resolution after E, prior concern resolved inside period, future exclusion and feedback occurrence timestamps. No interaction/resolution body leakage.
- [x] Customer EXISTS cohort, distinct gained users and source breakdown; clear renewal-action/retention/cohort/backlog labels.
- [x] Wire null-rate, quality, opening-stock/backlog and print states using backend has_data rather than guessing array lengths.
- [x] OpenCode browse owner lifecycle-boundary/frozen-retention/current-stage/print checks; backend decision-only200 without source grants. New real restricted-role browser checks delegated to parent for8G, not claimed passed here.

## Commands and done when

Backend: `php artisan test --filter='DecisionSupportMembershipTest|DecisionSupportCustomerTest|DecisionSupportAccessTest|RouteAccessTest|MembershipLifecycleTest|CustomerConversionTest|CustomerInteractionTest'`.

Frontend: `node --test`; `pnpm lint`; `pnpm build`.

Done when exact event/churn ordering and raw renewal definitions pass, conversion is cohort-bounded without a clamp, concern windows and printed notes match. Each feature includes no-record/day/final-day/next-day/type checks.

## Dated evidence

7 November 2026: initial six backend tests failed404 before endpoint implementation; Node reader test failed “Dashboard contract not implemented: memberships” before strict reader implementation. Added seventh backend opening-stock-without-events case. Exact packet backend command exit0,33tests/1944assertions/6786ms. Frontend node --test19passed; pnpm lint exit0 with existing warnings only (none new); pnpm build exit0,3152modules/1.88s. git diff --check exit0, CRLF conversion warnings only.

OpenCode browse session phase8-browse, existing owner/live MySQL: defaultNov01..07 membership opening16/churn2/retained14/87.5%,renewal14/(14+1)=93.33%; extending start toOct01 yields new15/opening1/churn1/retention0%, end stock14. Frozen-retention/renewal-action/missing-history notes visible. Customers Oct01..Nov07: leads5/converted2/40%,gained2,feedback2,raised1/open0/resolved1; defaultNov restored: leads1/converted0/0%,gained0,raised1/resolved1. Both view print media roots static, nonreport roots hidden, report notes visible. Desktop scrollWidth=clientWidth (membership1384/1384; customer equalitytrue). Date range restoredNov01..07; parent owner session retained for follow-up. No Playwright MCP used. Responsive/full-role/long-report/theme matrix remains8G; real restricted-role new-view browser checks explicitly delegated to parent.
