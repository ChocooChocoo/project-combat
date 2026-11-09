import { Link, useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { CalendarHeatmap, ChartCard, ChartEmpty, ColumnChart, DashboardGrid, DashboardPage, DashboardState, DonutChart, RateRing, RingRow, StatStrip, UsageMeter } from '@/components/dashboard/widgets'
import { Button } from '@/components/ui/button'
import { api } from '@/lib/api'
import { addDays, manilaToday, percent, compactPeso, useDashboardData } from '@/lib/dashboard'
import { minutesLabel, type OvertimeRequest, type StaffAttendance } from '@/lib/hr'
import { dateLabel, money } from '@/lib/membership'

type Balance = { type: string; label: string; allowance: number; used: number; remaining: number; paid: boolean }
type PayslipRow = { id: number; gym_id: number; starts_on: string | null; ends_on: string | null; net_pay: string }

const STATUS = {
  present: { text: 'Present', tone: 'bg-success' },
  late: { text: 'Late', tone: 'bg-warning' },
  absent: { text: 'Absent', tone: 'bg-destructive' },
} as const

/** P07: an employee's own work dashboard in this gym. Built only from their own self-service data. */
export default function MyDashboard() {
  const gymId = Number(useParams().gymId)
  const { data, error, retry } = useDashboardData(() => Promise.all([
    api<{ attendance: StaffAttendance[]; overtime: OvertimeRequest[] }>(`/gyms/${gymId}/my-attendance`),
    api<{ work: { balances: Balance[] } }>(`/gyms/${gymId}/my-work`),
    api<{ payslips: PayslipRow[] }>('/me/payslips'),
  ]), [gymId])
  const header = <PageHeader title="Work dashboard" description="Your attendance, pay and leave at this gym." actions={<Button variant="outline" render={<Link to={`/gyms/${gymId}/manage/my-work`} />}>My work</Button>} />

  if (!data) return <DashboardPage header={header}><DashboardState error={error} retry={retry} /></DashboardPage>

  const [{ attendance, overtime }, { work }, { payslips }] = data
  const today = manilaToday()
  const thisMonth = attendance.filter((row) => row.work_date.startsWith(today.slice(0, 7)))
  const present = thisMonth.filter((row) => row.status === 'present').length
  const late = thisMonth.filter((row) => row.status === 'late').length
  const worked = thisMonth.reduce((sum, row) => sum + row.worked_minutes, 0)
  const onTime = percent(present, present + late)
  const leaveLeft = work.balances.reduce((sum, b) => sum + b.remaining, 0)
  // me/payslips covers every gym the person works at, newest first; keep this gym's last six, oldest first.
  const pay = payslips.filter((p) => p.gym_id === gymId).slice(0, 6).reverse()
  const from = addDays(today, -34)
  const absent = thisMonth.length - present - late
  // Last 31 records: hours worked per day (oldest first) and the share of days not absent.
  const recent = [...attendance].reverse()
  const hoursTrend = recent.map((row) => Math.round((row.worked_minutes / 60) * 10) / 10)
  const attendanceRate = percent(attendance.filter((row) => row.status !== 'absent').length, attendance.length)
  const allowance = work.balances.reduce((sum, b) => sum + b.allowance, 0)
  const used = work.balances.reduce((sum, b) => sum + b.used, 0)
  const overtimeCount = (status: string) => overtime.filter((o) => o.status === status).length

  return <DashboardPage header={header}>
    <StatStrip items={[
      { label: 'Days worked this month', value: present + late, hint: `${absent} absent` },
      { label: 'On-time rate', value: onTime === null ? 'None yet' : `${onTime}%`, hint: `${late} late ${late === 1 ? 'day' : 'days'} this month` },
      { label: 'Hours worked this month', value: minutesLabel(worked), hint: 'From your clock-in and clock-out', trend: hoursTrend, trendLabel: 'Hours worked per day, last 31 records' },
      { label: 'Leave days left', value: leaveLeft, hint: work.balances.length ? `Across ${work.balances.length} leave ${work.balances.length === 1 ? 'type' : 'types'}` : 'No leave balances set' },
    ]} />

    <DashboardGrid>
      <ChartCard title="Attendance" description="Your last five weeks. Blank squares have no attendance record.">
        <CalendarHeatmap
          caption="Attendance per work day"
          from={from}
          to={today}
          days={attendance.filter((row) => row.work_date >= from).map((row) => ({
            date: row.work_date,
            value: 1,
            tone: STATUS[row.status].tone,
            text: [STATUS[row.status].text, row.late_minutes ? `${minutesLabel(row.late_minutes)} late` : '', row.worked_minutes ? `${minutesLabel(row.worked_minutes)} worked` : ''].filter(Boolean).join(', '),
          }))}
          legend={[...Object.values(STATUS).map((s) => ({ label: s.text, className: s.tone })), { label: 'No record', className: 'bg-muted' }]}
        />
      </ChartCard>
      <ChartCard title="Net pay" description="Your last six released payslips at this gym.">
        {pay.length === 0
          ? <ChartEmpty>No released payslips yet.</ChartEmpty>
          : <ColumnChart caption="Net pay per payslip" points={pay.map((p) => ({ label: p.ends_on ? dateLabel(p.ends_on).replace(/,? \d{4}$/, '') : `#${p.id}`, value: Number(p.net_pay), text: money(p.net_pay), short: compactPeso(p.net_pay) }))} />}
      </ChartCard>
    </DashboardGrid>

    <DashboardGrid>
      <ChartCard title="This month" description="Your attendance records so far this month.">
        <DonutChart caption="Attendance this month" centerLabel="days" slices={[
          { label: 'Present', value: present, tone: 'success' },
          { label: 'Late', value: late, tone: 'warning' },
          { label: 'Absent', value: absent, tone: 'destructive' },
        ]} />
      </ChartCard>
      <ChartCard title="Reliability" description="Rates from your last 31 attendance records and this year's leave.">
        <RingRow>
          <RateRing label="On-time rate" value={onTime} hint="This month" />
          <RateRing label="Attendance rate" value={attendanceRate} hint={`${attendance.length} records`} />
          <RateRing label="Leave used" value={percent(used, allowance)} hint={`${used} of ${allowance} days`} />
        </RingRow>
      </ChartCard>
    </DashboardGrid>

    <DashboardGrid>
      <ChartCard title="Leave balances" description="This year. The bar shows days used of each allowance.">
        {work.balances.length === 0
          ? <ChartEmpty>No leave balances set for you yet.</ChartEmpty>
          : <div className="grid gap-x-8 gap-y-5 sm:grid-cols-2">{work.balances.map((b) => <UsageMeter key={b.type} label={`${b.label}${b.paid ? '' : ' (unpaid)'}`} value={b.used} max={b.allowance} text={`${b.remaining} of ${b.allowance} left`} />)}</div>}
      </ChartCard>
      <ChartCard title="Overtime requests" description="Your last 20 requests.">
        <DonutChart caption="Overtime requests by status" centerLabel="requests" slices={[
          { label: 'Waiting', value: overtimeCount('pending'), tone: 'chart1' },
          { label: 'Approved', value: overtimeCount('approved'), tone: 'success' },
          { label: 'Rejected', value: overtimeCount('rejected'), tone: 'destructive' },
        ]} />
      </ChartCard>
    </DashboardGrid>
  </DashboardPage>
}
