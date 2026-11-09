import { Link, useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { BarList, ChartCard, ChartEmpty, ColumnChart, DashboardGrid, DashboardPage, DashboardState, DonutChart, HourHeatmap, RateRing, RingRow, SegmentBar, StatList, StatStrip, type Slice, type Tone } from '@/components/dashboard/widgets'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { api } from '@/lib/api'
import { addDays, monthKey, monthLabel, compactPeso, useDashboardData } from '@/lib/dashboard'
import { decisionUrl, rateLabel, readOptions, type AttendancePayload, type MembershipPayload, type MoneyPayload, type ProblemPayload, type TrendPayload } from '@/lib/decision-support'
import { incomeTypeLabel } from '@/lib/finance'
import { money } from '@/lib/membership'

const PROBLEM_LABELS = { falling_attendance: 'Falling attendance', low_use_slot: 'Low-use slot', high_no_show: 'High no-show' }

/**
 * P07: the gym's at-a-glance dashboard. Reads the same decision-support aggregates as the full
 * reports (decision_support.general.view), fixed to the last 30 days and the last six months.
 */
export default function GymDashboard() {
  const gymId = Number(useParams().gymId)
  const { data, error, retry } = useDashboardData(async () => {
    const options = readOptions(await api<unknown>(`/gyms/${gymId}/decision-support/options`))
    const to = options.today
    const from = addDays(to, -29)
    const [memberships, cash, attendance, problems, trends] = await Promise.all([
      api<MembershipPayload>(decisionUrl(gymId, 'memberships', from, to)),
      api<MoneyPayload>(decisionUrl(gymId, 'money', from, to)),
      api<AttendancePayload>(decisionUrl(gymId, 'attendance', from, to)),
      api<ProblemPayload>(decisionUrl(gymId, 'problems', from, to)),
      api<TrendPayload>(decisionUrl(gymId, 'trends', `${monthKey(to, -5)}-01`, to)),
    ])
    return { memberships, cash, attendance, problems, trends }
  }, [gymId])
  const header = <PageHeader title="Gym dashboard" description="Members, money and training over the last 30 days." actions={<Button variant="outline" render={<Link to={`/gyms/${gymId}/manage/decision-support`} />}>Full reports</Button>} />

  if (!data) return <DashboardPage header={header}><DashboardState error={error} retry={retry} /></DashboardPage>

  const { memberships: m, cash, attendance: a, problems, trends } = data
  const totals = cash.income_expense.totals
  // Trends always returns its configured window; the dashboard shows the latest six months.
  const months = trends.buckets.slice(-6)
  // Income by type, largest first: top three keep their own slice, the rest fold into Other.
  const income = cash.income_types.filter((row) => Number(row.amount) > 0).sort((x, y) => Number(y.amount) - Number(x.amount))
  const incomeTones: Tone[] = ['chart1', 'chart2', 'chart3']
  const incomeSlices: Slice[] = income.slice(0, 3).map((row, i) => ({ label: incomeTypeLabel(row.type), value: Number(row.amount), text: money(row.amount), tone: incomeTones[i] }))
  const otherIncome = income.slice(3).reduce((sum, row) => sum + Number(row.amount), 0)
  if (otherIncome > 0) incomeSlices.push({ label: 'Other', value: otherIncome, text: money(otherIncome), tone: 'chart4' })
  const peakHours = a.peaks.hours.map((hour) => a.hours.find((h) => h.hour === hour)?.label).filter(Boolean).join(', ')

  return <DashboardPage header={header}>
    <StatStrip items={[
      { label: 'Active members', value: m.summary.active_at_end, hint: `${m.summary.new} new, ${m.summary.renewed} renewed`, trend: months.map((b) => b.active_at_end), trendLabel: 'Active members at each month end' },
      { label: 'Member retention', value: rateLabel(m.summary.retention_rate), hint: `${m.summary.retained_from_start} of ${m.summary.active_at_start} kept from 30 days ago` },
      { label: 'Net income', value: money(totals.net), hint: `${money(totals.income)} in, ${money(totals.expenses)} out` },
      { label: 'Class attendance', value: rateLabel(a.summary.attendance_rate), hint: `${a.summary.attended} of ${a.summary.marked} marked bookings`, trend: months.map((b) => b.attendance_rate ?? 0), trendLabel: 'Attendance rate per month' },
    ]} />

    <DashboardGrid>
      <ChartCard title="Revenue" description="Payments recorded per month. The current month is still in progress.">
        <ColumnChart caption="Revenue per month" points={months.map((b) => ({ label: monthLabel(b.period), value: Number(b.revenue), text: money(b.revenue), short: compactPeso(b.revenue) }))} />
      </ChartCard>
      <ChartCard title="Attended sessions" description="Present or late bookings per month.">
        <ColumnChart caption="Attended sessions per month" points={months.map((b) => ({ label: monthLabel(b.period), value: b.attended, text: String(b.attended) }))} />
      </ChartCard>
    </DashboardGrid>

    <DashboardGrid>
      <ChartCard title="Gym health" description="Last 30 days. Each ring is a rate out of 100.">
        <div className="flex flex-col gap-6">
          <RingRow>
            <RateRing label="Retention" value={m.summary.retention_rate} hint="Members kept" />
            <RateRing label="Renewal" value={m.summary.renewal_rate} hint="Renewed vs. expired" />
            <RateRing label="Attendance" value={a.summary.attendance_rate} hint="Marked bookings" />
          </RingRow>
          <div className="flex flex-col gap-3 border-t pt-5">
            <h4 className="font-medium text-muted-foreground text-xs">Booking outcomes</h4>
            <SegmentBar caption="Booking outcomes" slices={[
              { label: 'Present', value: a.summary.present, tone: 'success' },
              { label: 'Late', value: a.summary.late, tone: 'warning' },
              { label: 'Absent', value: a.summary.absent, tone: 'destructive' },
              { label: 'No-show', value: a.summary.no_show, tone: 'muted' },
            ]} />
          </div>
        </div>
      </ChartCard>
      <ChartCard title="Membership activity" description="Membership changes in the last 30 days.">
        <DonutChart caption="Membership activity" centerLabel="changes" slices={[
          { label: 'New', value: m.summary.new, tone: 'chart1' },
          { label: 'Renewed', value: m.summary.renewed, tone: 'chart2' },
          { label: 'Expired', value: m.summary.expired, tone: 'chart3' },
          { label: 'Cancelled', value: m.summary.cancelled, tone: 'chart4' },
        ]} />
      </ChartCard>
    </DashboardGrid>

    <ChartCard title="When the gym is busiest" description={`Class attendances and gym visits by hour, last 30 days, Manila time.${peakHours ? ` Peak: ${peakHours}.` : ''}`}>
      <HourHeatmap caption="Traffic per hour of day" points={a.hours.map((h) => ({ label: h.label, value: h.total, text: `${h.total} (${h.class_attended} in class, ${h.gym_visits} visits)` }))} />
    </ChartCard>

    <DashboardGrid>
      <ChartCard title="Busiest days" description="Class attendances and gym visits by weekday, last 30 days.">
        <BarList caption="Traffic per weekday" points={a.weekdays.map((w) => ({ label: w.label, value: w.total, text: String(w.total) }))} />
      </ChartCard>
      <ChartCard title="Income by type" description="Where the last 30 days of income came from.">
        <DonutChart caption="Income by type" centerLabel="income" centerValue={compactPeso(incomeSlices.reduce((sum, s) => sum + s.value, 0))} slices={incomeSlices} />
      </ChartCard>
    </DashboardGrid>

    <ChartCard title="Needs attention" description="Rule-based flags from the last 30 days. Full reports list the records behind each one.">
      <div className="flex flex-col gap-4">
        {problems.items.length === 0
          ? <ChartEmpty>Nothing flagged in the last 30 days.</ChartEmpty>
          : <ul className="flex flex-col divide-y">
            {problems.items.slice(0, 5).map((item) => <li key={`${item.code}:${item.key}`} className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0 text-sm">
              <span className="min-w-0 truncate">{item.name}</span>
              <Badge variant="warning">{PROBLEM_LABELS[item.code]}</Badge>
            </li>)}
          </ul>}
        {problems.items.length > 5 && <p className="text-muted-foreground text-xs">{problems.items.length - 5} more in full reports.</p>}
        {cash.receivables.debtor_count > 0 && <StatList rows={[{ label: 'Members with a balance due', hint: `${cash.receivables.debtor_count} members, as of today`, value: money(cash.receivables.outstanding) }]} />}
      </div>
    </ChartCard>
  </DashboardPage>
}
