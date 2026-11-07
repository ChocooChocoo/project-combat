# Packet 8E: Problems

[Overview](00-overview.md) | [Previous](phase-5.md) | [Next](phase-7.md)

Status: Done (7 November 2026; populated/boundary browser evidence closed in 8G). Dependencies: 8A. Requirement: R-95.

## Goal and scope

Create Problems; Feature/DecisionSupportProblemTest; frontend problems-view. Edit shared controller/routes/map/access/types/shell. Reuse TrainingDashboards::sessions, no duplicate attendance query.

## Locked decisions and tasks

- [x] Previous four8/10 and current four5/10 gives80->50/drop30 flag. Exactly20 qualifies; current60/drop19.99/only7/zero-denominator do not. Unmarked occurrences stay in eight; cancelled/future/out-of-range excluded.
- [x] Three slot fills0/20/40 mean20 flags; unequal capacities prove unweighted mean. Exactly25/only2 do not. SQL weekday/hour portability and requested held seats.
- [x] No-show3/10=30 qualifies, below30/M4 do not; requested/unmarked/unknown never become outcomes; show unmarked evidence separately.
- [x] Override threshold config and prove detection/output notes both follow it; no hidden threshold literals.
- [x] Every flag has counts/rates/session IDs/date windows/configured thresholds. Empty means no rule triggered, not business success grade. No write/job/notification or invented advice.
- [x] Evidence table/cards/full print implemented. Live empty/no-flags, configured thresholds, phone width and print structure checked.
- [x] Populated/boundary evidence browser rendering closed in 8G: stored low-use fixture rendered both falling attendance and low-use flags with inspectable evidence; see `09-8G-browser-closeout.md` and `07-validation.md`.

## Commands and done when

Backend: `php artisan test --filter='DecisionSupportProblemTest|DecisionSupportAttendanceTest|DecisionSupportServiceTest|SqlTest|DecisionSupportAccessTest|RouteAccessTest'`.

Frontend: `node --test`; `pnpm lint`; `pnpm build`.

Done when all inclusive/exclusive boundaries pass and both falling attendance/low-use are detected from stored fixtures with inspectable evidence. Include empty/day/final-day/next-day/type checks.

## Dated evidence

7 November 2026: six stored-fixture feature tests cover exact 80/50/drop30,20 equality,60 exclusion,19.99 exclusion and changed19.98 config,7/zero/unmarked windows, slot0/20/40 unweighted20 with capacities100/5/5,25 equality,requested seats,Sunday0/hour0,3/10 no-show30,unmarked/unknown separation,empty/day/end/next-day/config. Initial five cases failed404 before implementation; test helper names colliding with framework methods corrected first. Large precision fixture inserts chunked after SQLite variable-limit failure; final packet command exit0,23tests/2027assertions/4164ms. Node test red on missing problems contract, then22pass; lint exit0 with existing unrelated warnings; build exit0/3158modules/2.72s. Diff check exit0, existing CRLF warnings only.

OpenCode browse owner/live MySQL: Problems loads configured4/20/60,3/25,5/30 thresholds and honest no-rules-triggered text. Historical2000-01-01 response200 and actual No records state verified.390px document375/375; print root1/static/other roots hidden,thresholds and no-flags text present. An injected populated fixture attempt failed in browse runtime before proof; handler removed and live default view reloaded, no populated/clean-console claim. OriginalNov01..07 and1440px restored; owner session retained for follow-up. Full role/theme/long-print and populated browser matrix remains8G.

Final boundary follow-up: added ongoing-session exclusion, explicit2/10no-show non-trigger, and repeating-fraction exact20-point regression (7/15 to4/15). Last test first failed0flags because floating subtraction fell below20; changed threshold comparison to cross-products of original counts, not rounded display rates. Final exact8Epacketcommand exit0,24tests/2064assertions/4551ms. Entire DecisionSupport regression exit0,58tests/865assertions/8528ms. No new browser pass inferred from this arithmetic fix.
