# P07 — Data-driven dashboards for every user

## Context
`docs/prompts/P07.md` asks for a dedicated dashboard per user kind, separate from home pages, showing real data
(KPI cards, charts, heatmaps) with clear hierarchy. Today no user has a dashboard: admin home is an applications
table, owners land on the staff list, members land on gym discovery, staff on My work. No chart library is
installed; decision support already has hand-built SVG/CSS charts (`components/decision-support/visuals.tsx`,
`trend-chart.tsx`) and theme tokens `--chart-1..5` (`index.css`).

User decisions: **all four dashboards** (admin, gym, member, staff); **new sidebar link, sign-in landing unchanged**.

Skills to use while building: `dataviz` (read before chart code), `impeccable` + `antislop-ui` (hierarchy, layout,
anti-slop), `antislop-human` (contrast, keyboard). No new skills to install — installed set covers this.
No new npm libraries.

## Shared frontend pieces — `frontend/src/components/dashboard/`
One file `widgets.tsx`:
- `KpiCard({ label, value, hint, tone? })` — built on coss `card.tsx`. One primary number, small label, one-line context.
- `CalendarHeatmap({ days: {date, value}[], weeks })` — week-column × weekday-row grid of CSS cells, opacity
  scale on `--chart-1` (same method as `TrafficHeatmap`), `title`/`aria-label` per cell with exact count, legend.
- `DashboardShell` layout: `PageHeader` + KPI row (4 cards max) + 2-col chart grid that stacks on mobile.
Reuse as-is: `BarChart`, `TrafficHeatmap`, `ChartPanel` (visuals.tsx), `TrendChart` (trend-chart.tsx),
`DashboardLoading/Error/Empty` (dashboard-parts.tsx), coss `meter`/`progress`/`badge`/`skeleton`.

Hierarchy rule for all four: row 1 = 3–4 KPIs (answer "how am I doing now"), row 2 = main trend + heatmap
(answer "when / which direction"), row 3 = breakdowns/lists (answer "why"). Every empty widget shows a real
empty state, never fake numbers.

## 1. Gym dashboard (owner + staff with `decision_support.general.view`)
- Route `/gyms/:gymId/manage/dashboard` in `App.tsx` (inside `GymWorkspaceLayout`); page
  `src/pages/gym/GymDashboard.tsx`; nav item in `components/gym-nav.tsx` `ITEMS`, Business group, permission
  `decision_support.general.view`, placed **not first** so `workspaceHome()` landing is unchanged.
- **No backend change.** Calls existing decision-support routes via `api()` / `lib/decision-support.ts` URL builder,
  last 30 days from `/decision-support/options` `today`:
  - `memberships` → KPIs active members (`active_at_end`), retention rate, new this period.
  - `money` → KPI net income (income_expense totals); income-type breakdown bar.
  - `attendance` → KPI attendance rate; `TrafficHeatmap` from `hours`; weekday bar from `weekdays`.
  - `trends` (6 months) → `TrendChart` revenue + attended per month.
  - `problems` → short "needs attention" list (top items).
- Plan-locked decision support (403) → show existing plan-lock message.

## 2. Member dashboard
- Route `/dashboard` (signed-in, shown when `isMemberDashboard` in `app-shell.tsx`); page `src/pages/MemberDashboard.tsx`.
- **New endpoint** `GET me/dashboard` → `app/Http/Controllers/MemberDashboardController.php` (invokable),
  route next to `my-bookings` in `routes/api.php`. Only the signed-in user's rows; cross-gym on purpose with
  `withoutGlobalScope('gym')`, filtered by `user_id`. Returns:
  - `visits[]` `{date, count}` — `gym_visits.checked_in_at` grouped by day, last 26 weeks (`Sql` helpers for date).
  - `sessions` `{attended, late, no_show, upcoming}` + `monthly[]` `{period, attended}` last 6 months (session_bookings).
  - `memberships[]` active: gym name, plan, `ends_on`, days left.
  - `balance` from `Finance\Receivables::forUser`.
  - `payments_monthly[]` last 6 months sum of `payments.amount - discount` paid by user.
- UI: KPIs (visits this month, sessions attended, days left on soonest membership, balance due) →
  `CalendarHeatmap` of visits + attended-per-month `BarChart` → membership `meter` list + upcoming count.

## 3. Staff dashboard (gym employees)
- Route `/gyms/:gymId/manage/my-dashboard`; page `src/pages/gym/MyDashboard.tsx`; link next to "My work" in
  `app-shell.tsx` (same condition: employee and HR not plan-locked).
- **No backend change.** Combines existing `GET gyms/{gym}/my-attendance` (last 31 records), `GET gyms/{gym}/my-work`
  (balances, schedule), `GET me/payslips` (filter by gym).
- UI: KPIs (days present this month, on-time rate, hours worked, leave days left) → `CalendarHeatmap` of last
  31 days coloured by status + net pay per payslip `BarChart` → leave balances `meter` list, pending overtime count.

## 4. Admin dashboard
- Route `/admin/dashboard` (AdminOnly); page `src/pages/admin/AdminDashboard.tsx`; first link in admin nav group.
- **Extend** `Admin/PlatformStatsController` (keeps R-19: gyms table only) with:
  - `monthly[]` last 12 months `{period, submitted, approved}` from `gyms.submitted_at` / `reviewed_at` + status
    (`Sql::month`).
  - `subscriptions` `{trial, paid, free}` from approved gyms using `App\Support\Subscription` (or the
    `trial_ends_at` / `paid_until` columns directly if cheaper).
  AdminHome ignores the new keys.
- UI: KPIs (live gyms, pending applications, approval rate, gyms on paid plans) → `TrendChart` submitted vs
  approved per month → tier `BarChart` + subscription mix bar + status breakdown.

## Files
New: `components/dashboard/widgets.tsx`, `pages/gym/GymDashboard.tsx`, `pages/gym/MyDashboard.tsx`,
`pages/MemberDashboard.tsx`, `pages/admin/AdminDashboard.tsx`, `lib/dashboard.ts` (response types + small helpers),
`backend/app/Http/Controllers/MemberDashboardController.php`, `backend/tests/Feature/MemberDashboardTest.php`.
Edit: `App.tsx`, `components/app-shell.tsx`, `components/gym-nav.tsx`, `routes/api.php`,
`Admin/PlatformStatsController.php`, existing platform stats test (add monthly/subscriptions asserts).

## Verification
- `php artisan test --filter=MemberDashboardTest` — own data only, other user's visits excluded, other gym rows
  included for same user, empty user returns zeros.
- `php artisan test --filter=PlatformStats` (and full `php artisan test`).
- `pnpm build`, `pnpm lint`, `node --test`.
- Run app (`php artisan dev`, `pnpm dev`) and open each dashboard as admin, owner, member, employee with Playwright
  MCP: screenshots light + dark, mobile width; compare numbers against existing decision-support / My work screens.
- Hook warned render gates have no browser; if Playwright browser is missing, say contrast/responsive checks are
  unmeasured.
