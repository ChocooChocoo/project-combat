# Packet 8A: Training dashboards

[Overview](00-overview.md) | [Previous](phase-1.md) | [Next](phase-3.md)

Status: Done (7 November 2026; phase-wide acceptance closed in 8G). Dependencies: 8.0. Requirements: R-85, R-87, R-88.

## Goal and scope

Create TrainingDashboards; Feature/DecisionSupport{Attendance,Athlete,Service}Test; frontend {attendance,athlete,service}-view. Edit shared controller/routes/route map/access fixtures/types/shell and shared parts only as needed.

## Locked decisions and tasks

- [x] Attendance fixture present2/late1/absent1/no_show1 gives A3/M5,60%/20%; pending/unmarked excluded. Cancel marked booking/session without losing markers. Unknown markers and visits separate.
- [x] Tenant-filtered per-session booking aggregate left-joined to sessions and optional gym dimensions; no multiplying raw joins or per-athlete/session ledger loop.
- [x] Zero-fill 7 weekdays/24 hours; every tied nonzero peak; starts_at for class attendance and checked_in_at for visits. Capacity counted once per session.
- [x] Athlete fixture three distinct attendees/two progress gives66.67%; all four entry types, stored competition results/promotion titles, date-only bounds, entry-only athlete.
- [x] One coach winner per athlete by count/recency/lowest ID; recorder never coach; unassigned preserved and all splits reconcile.
- [x] Service requested/booked held counts, confirmed separate, other statuses excluded; zero service/session/null bucket retained; private separate; 2/4+3/12 pooled31.25%; exact coach/room minutes and delivered occurrences; cancelled offers excluded.
- [x] Most/least includes every tied service. Notes disclose current occupancy/classification and noncausal “coach based on attendance”.
- [x] Three lazy endpoints/views with responsive data and full print notes; no health/note bodies or source-module actions.
- [x] Browser owner/decision-only, populated/empty, injected503+Retry, stale range switch,360/390px, keyboard tabs and print attribution/denominator notes.

## Commands and done when

Backend: `php artisan test --filter='DecisionSupportAttendanceTest|DecisionSupportAthleteTest|DecisionSupportServiceTest|DecisionSupportAccessTest|RouteAccessTest|AttendanceTest|AthleteRecordTest|BookingTest'`.

Frontend: `node --test`; `pnpm lint`; `pnpm build`.

Done when stored markers reconcile, one-coach partition/effectiveness and pooled-fill fixtures pass, zero/private cases visible and all displayed metrics backend-computed. Every feature file also covers no records, one-day/final-day/next-day boundaries and exact numeric types.

## Dated evidence

7 November 2026: tests written before endpoint implementation; red8training cases (404 routes; one visit fixture lacked required method, corrected). Final packet backend command exit0,74tests/2250assertions/17880ms. Added exact AccessTest date/decision-only/payroll-only matrix for three views, source AttendanceLedger reconciliation, unknown/cancelled markers, final microsecond and visit/peak cases, progress coach tie partition, weighted/current/private use. Node red on missing training-reader branch, then18passed. pnpm lint exit0 (existing warnings, none in new files), pnpm build exit0/3150modules/1.62s; git diff --check exit0 with CRLF conversion warnings only.

Live existing MySQL owner: all three actual endpoints rendered, no pageerrors in recorded page check, four widths had equal document scroll/client widths. Owner artifacts: `.orchestration/phase-8-decision-support/8A-{attendance,athletes,services}.{png,pdf}`. Real decision-only employee fixture used a newly created temp SQLite file (no seed/reset of development DB) and dedicated PHP8008, with browser requests forwarded to that real API, not fabricated responses. Actual /me permission list exactly `[decision_support.general.view]`; sign-in stayed `/`, switcher entered decision support. All three rendered at390px without overflow; active print reports preserve notes, static portal, hidden other roots and visible table overflow. Restricted-role artifacts: `8A-decision-only-*-390.png` and `8A-decision-only-*.pdf` in same excluded directory.

Injected503/Retry recovered; real2000-01-01 attendance empty state shown. Delayed October request while switching to November left print disabled/root0 during pending, then correct November report after old completion. Keyboard End reached Sustainability inside390px viewport with visible2px solid outline; Home restored Attendance. New print CSS removes nested table-container overflow in print only. Full3+page pagination/payslip/theme/all-role checks remain8G, not marked passed here. Test browser closed, forwarding removed, isolated PHP process stopped; original localhost8000/5173 servers untouched. Next: packet8B.
