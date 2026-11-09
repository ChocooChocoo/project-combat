import { Link } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { CalendarHeatmap, ChartCard, ChartEmpty, ColumnChart, DashboardGrid, DashboardPage, DashboardState, DonutChart, RateRing, RingRow, StatList, StatStrip, UsageMeter } from '@/components/dashboard/widgets'
import { Button } from '@/components/ui/button'
import { api } from '@/lib/api'
import { addDays, compactPeso, manilaToday, mondayOf, monthLabel, percent, useDashboardData, type MemberDashboardData } from '@/lib/dashboard'
import { dateLabel, money } from '@/lib/membership'

const DAY_MS = 86_400_000
const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

/** P07: a member's own training dashboard across every gym they train at. Separate from Home (gym discovery). */
export default function MemberDashboard() {
  const { data, error, retry } = useDashboardData(() => api<MemberDashboardData>('/me/dashboard'), [])
  const header = <PageHeader title="My dashboard" description="Your visits, sessions and memberships across every gym you train at." actions={<Button variant="outline" render={<Link to="/bookings" />}>My bookings</Button>} />

  if (!data) return <DashboardPage header={header}><DashboardState error={error} retry={retry} /></DashboardPage>

  const today = manilaToday()
  const thisMonth = today.slice(0, 7)
  const visitsThisMonth = data.visits.filter((v) => v.date.startsWith(thisMonth)).reduce((sum, v) => sum + v.count, 0)
  const totalVisits = data.visits.reduce((sum, v) => sum + v.count, 0)
  // Check-ins per week for the last 12 weeks, oldest first.
  const weekStarts = Array.from({ length: 12 }, (_, i) => addDays(mondayOf(today), (i - 11) * 7))
  const weekly = weekStarts.map((start) => data.visits.filter((v) => v.date >= start && v.date < addDays(start, 7)).reduce((sum, v) => sum + v.count, 0))
  // Check-ins by weekday across the whole 26-week window.
  const byWeekday = WEEKDAYS.map((_, i) => data.visits.filter((v) => (new Date(`${v.date}T00:00:00Z`).getUTCDay() + 6) % 7 === i).reduce((sum, v) => sum + v.count, 0))
  const { attended, late, no_show: missed, upcoming } = data.sessions
  const showRate = percent(attended, attended + missed)
  const onTimeRate = percent(attended - late, attended)
  const soonest = data.memberships.find((m) => m.days_left !== null)
  const owes = Number(data.balance) > 0

  return <DashboardPage header={header}>
    <StatStrip items={[
      { label: 'Gym visits this month', value: visitsThisMonth, hint: `${totalVisits} in the last 26 weeks`, trend: weekly, trendLabel: 'Check-ins per week, last 12 weeks' },
      { label: 'Sessions attended', value: attended, hint: showRate === null ? 'No marked sessions yet' : `${showRate}% of marked sessions`, trend: data.monthly.map((m) => m.attended), trendLabel: 'Sessions attended per month, last 6 months' },
      { label: 'Membership days left', value: soonest?.days_left ?? 'None', hint: soonest ? `${soonest.plan ?? 'Membership'}, until ${dateLabel(soonest.ends_on)}` : 'No active time-based membership' },
      { label: 'Balance due', value: money(data.balance), hint: owes ? <Link className="underline underline-offset-2" to="/memberships">Pay from My memberships</Link> : 'Nothing owed' },
    ]} />

    <ChartCard title="Gym visits" description="Check-ins per day over the last 26 weeks, all gyms.">
      <CalendarHeatmap
        caption="Gym check-ins per day"
        from={data.visits_since}
        to={today}
        days={data.visits.map((v) => ({ date: v.date, value: v.count, text: `${v.count} ${v.count === 1 ? 'check-in' : 'check-ins'}` }))}
      />
    </ChartCard>

    <DashboardGrid>
      <ChartCard title="Session outcomes" description="Every booked session that has been marked.">
        <DonutChart caption="Session outcomes" centerLabel="marked" slices={[
          { label: 'On time', value: attended - late, tone: 'success' },
          { label: 'Late', value: late, tone: 'warning' },
          { label: 'Missed', value: missed, tone: 'destructive' },
        ]} />
      </ChartCard>
      <ChartCard title="Your training days" description="Check-ins by weekday, last 26 weeks.">
        <ColumnChart caption="Check-ins by weekday" points={WEEKDAYS.map((label, i) => ({ label, value: byWeekday[i], text: String(byWeekday[i]) }))} />
      </ChartCard>
    </DashboardGrid>

    <DashboardGrid>
      <ChartCard title="Sessions attended" description="Present or late, by session month.">
        <ColumnChart caption="Sessions attended per month" points={data.monthly.map((m) => ({ label: monthLabel(m.period), value: m.attended, text: String(m.attended) }))} />
      </ChartCard>
      <ChartCard title="Payments" description="Amount paid per month, all gyms.">
        <ColumnChart caption="Amount paid per month" points={data.monthly.map((m) => ({ label: monthLabel(m.period), value: Number(m.paid), text: money(m.paid), short: compactPeso(m.paid) }))} />
      </ChartCard>
    </DashboardGrid>

    <DashboardGrid>
      <ChartCard title="Consistency" description="How often you show up, and show up on time.">
        <div className="flex flex-col gap-6">
          <RingRow>
            <RateRing label="Show-up rate" value={showRate} hint={`${attended} of ${attended + missed} marked`} />
            <RateRing label="On-time rate" value={onTimeRate} hint={`${attended - late} of ${attended} attended`} />
          </RingRow>
          <StatList rows={[{ label: 'Upcoming bookings', hint: 'From today on', value: upcoming }]} />
        </div>
      </ChartCard>
      <ChartCard title="Active memberships" description="Days left in each membership period.">
        {data.memberships.length === 0
          ? <ChartEmpty>No active memberships. <Link className="underline underline-offset-2" to="/">Find a gym</Link></ChartEmpty>
          : <div className="flex flex-col gap-5">{data.memberships.map((m) => {
            const length = m.starts_on && m.ends_on ? Math.max(1, Math.round((Date.parse(m.ends_on) - Date.parse(m.starts_on)) / DAY_MS)) : 0
            const label = `${m.plan ?? 'Membership'} · ${m.gym ?? ''}`
            return m.days_left === null || length === 0
              ? <StatList key={m.id} rows={[{ label, value: m.status === 'frozen' ? 'Frozen' : 'Session-based' }]} />
              : <UsageMeter key={m.id} label={label} value={m.days_left} max={length} text={`${m.days_left} of ${length} days left`} />
          })}</div>}
      </ChartCard>
    </DashboardGrid>
  </DashboardPage>
}
