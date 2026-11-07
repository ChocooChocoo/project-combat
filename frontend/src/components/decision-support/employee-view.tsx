import { AggregateTable, MetricsList } from '@/components/decision-support/dashboard-parts'
import { rateLabel, type EmployeePayload } from '@/lib/decision-support'
import { money } from '@/lib/membership'
import { BarChart } from './visuals'

export function EmployeeView({ data, mode }: { data: EmployeePayload; mode: 'screen' | 'print' }) {
  const summary = data.summary
  return <div className="space-y-6" data-report-mode={mode}>
    <MetricsList items={[
      { label: 'Recorded present / late / absent', value: `${summary.present} / ${summary.late} / ${summary.absent}` },
      { label: 'Staff attendance', value: rateLabel(summary.attendance_rate) }, { label: 'Late rate among attendees', value: rateLabel(summary.late_rate) },
      { label: 'Worked minutes', value: summary.worked_minutes }, { label: 'Late minutes', value: summary.late_minutes },
      { label: 'Undertime minutes', value: summary.undertime_minutes }, { label: 'Missing clock-outs', value: summary.missing_clock_out },
    ]} />
    {mode === 'screen' && <div className="ds-chart-pair"><BarChart title="Staff attendance outcomes" description="Recorded outcomes in the selected dates." points={[{ label: 'Present', value: summary.present }, { label: 'Late', value: summary.late }, { label: 'Absent', value: summary.absent }]} /><BarChart title="Delivered coach sessions" description="Operational workload, not payroll eligibility." points={data.workload.map((row) => ({ label: row.name, value: row.delivered_sessions }))} /></div>}
    <AggregateTable caption="Recorded employee attendance" columns={['Employee', 'Present', 'Late', 'Absent', 'Attendance', 'Late rate', 'Worked minutes', 'Late minutes', 'Undertime minutes', 'Missing clock-outs']} rows={data.attendance.map((row) => ({ key: String(row.user_id), cells: [row.name, row.present, row.late, row.absent, rateLabel(row.attendance_rate), rateLabel(row.late_rate), row.worked_minutes, row.late_minutes, row.undertime_minutes, row.missing_clock_out] }))} />
    <AggregateTable caption="Operational coach workload (not payroll eligibility)" columns={['Coach', 'Offered / delivered sessions', 'Offered / delivered minutes', 'Held seats', 'Confirmed', 'Valid capacity', 'Pooled fill', 'Invalid capacity sessions']} rows={data.workload.map((row) => ({ key: String(row.coach_id), cells: [row.name, `${row.sessions} / ${row.delivered_sessions}`, `${row.scheduled_minutes} / ${row.delivered_minutes}`, row.held_seats, row.confirmed_bookings, row.capacity, rateLabel(row.fill_rate), row.invalid_capacity_sessions] }))} />
    <section className="space-y-4"><h3 className="font-semibold">Aggregate payroll (released lines, run end date in range)</h3>
      <MetricsList items={[
        { label: 'Gross payroll', value: money(data.payroll.totals.gross) }, { label: 'Net payroll', value: money(data.payroll.totals.net) },
        { label: 'Employer statutory contributions', value: money(data.payroll.totals.employer_statutory) }, { label: 'Released payroll lines', value: data.payroll.totals.count },
      ]} />
      <AggregateTable caption="Released payroll by period" columns={['Period', 'Gross', 'Net', 'Employer statutory', 'Lines']} rows={data.payroll.buckets.map((row) => ({ key: row.period, cells: [row.period, money(row.gross), money(row.net), money(row.employer_statutory), row.count] }))} />
    </section>
    {data.pay_visible ? <AggregateTable caption="Individual payroll (payroll.runs.view)" columns={['Employee', 'Gross', 'Net', 'Employer statutory', 'Lines']} rows={data.payroll_by_employee!.map((row) => ({ key: String(row.user_id), cells: [row.name, money(row.gross), money(row.net), money(row.employer_statutory), row.line_count] }))} /> : <p className="text-sm text-muted-foreground">Individual payroll is not included in this report. Only payroll.runs.view permits individual pay.</p>}
  </div>
}
