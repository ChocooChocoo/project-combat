import { AggregateTable, MetricsList } from '@/components/decision-support/dashboard-parts'
import { TrendChart } from '@/components/decision-support/trend-chart'
import { rateLabel, type TrendPayload } from '@/lib/decision-support'
import { money } from '@/lib/membership'

export function TrendsView({ data, mode }: { data: TrendPayload; mode: 'screen' | 'print' }) {
  return <div className="space-y-6" data-report-mode={mode}>
    <p className="text-sm font-medium">Own trend window: {data.window.from} to {data.window.to} ({data.window.months} months).</p>
    <p className="text-sm text-muted-foreground">Selected start does not shorten this window. {data.window.partial_final_month ? 'Final month is partial, clipped to the stated end day.' : 'Final month ends on its calendar month end.'} Zero-filled buckets do not invent records before the first source date.</p>
    <MetricsList items={[
      { label: 'Recorded attendances / marked outcomes', value: `${data.totals.attended} / ${data.totals.marked}` },
      { label: 'Pooled class attendance', value: rateLabel(data.totals.attendance_rate) },
      { label: 'New / renewal / expired / cancelled actions', value: `${data.totals.new} / ${data.totals.renewed} / ${data.totals.expired} / ${data.totals.cancelled}` },
      { label: 'Closing active/frozen memberships (not summed stock)', value: data.closing_active },
      { label: 'Window net revenue', value: money(data.totals.revenue) },
    ]} />
    <AggregateTable caption="First actual source records (no later than effective end)" columns={['Attendance markers', 'Membership activity', 'Revenue']} rows={[{ key: 'sources', cells: [data.first_records.attendance ?? 'No records', data.first_records.memberships ?? 'No records', data.first_records.revenue ?? 'No records'] }]} />
    {mode === 'screen' && <div className="ds-trends-grid">
      <TrendChart title="Class attendance rate" points={data.buckets.map((row) => ({ label: row.period, value: row.attendance_rate, text: rateLabel(row.attendance_rate) }))} />
      <TrendChart title="Closing active/frozen membership stock" points={data.buckets.map((row) => ({ label: row.period, value: row.active_at_end, text: String(row.active_at_end) }))} />
      <TrendChart title="Signed net revenue" points={data.buckets.map((row) => ({ label: row.period, value: Number(row.revenue), text: money(row.revenue) }))} />
    </div>}
    <AggregateTable caption="Monthly attendance, membership activity and revenue" columns={['Month', 'Attended', 'Marked', 'Attendance', 'New', 'Renewal actions', 'Expired', 'Cancelled', 'Closing active/frozen', 'Net revenue']} rows={data.buckets.map((row) => ({ key: row.period, cells: [row.period, row.attended, row.marked, rateLabel(row.attendance_rate), row.new, row.renewed, row.expired, row.cancelled, row.active_at_end, money(row.revenue)] }))} />
  </div>
}
