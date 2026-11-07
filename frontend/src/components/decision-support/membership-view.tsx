import { AggregateTable, MetricsList } from '@/components/decision-support/dashboard-parts'
import { rateLabel, type MembershipPayload } from '@/lib/decision-support'
import { BarChart } from './visuals'
import { TrendChart } from './trend-chart'

export function MembershipView({ data, mode }: { data: MembershipPayload; mode: 'screen' | 'print' }) {
  const summary = data.summary
  return <div className="space-y-6" data-report-mode={mode}>
    <MetricsList items={[
      { label: 'New (first activation)', value: summary.new }, { label: 'Renewal actions', value: summary.renewed },
      { label: 'Expiration events', value: summary.expired }, { label: 'Cancellation events', value: summary.cancelled },
      { label: 'Active/frozen at period start', value: summary.active_at_start }, { label: 'Opening cohort churned', value: summary.churned_from_start },
      { label: 'Opening cohort retained', value: summary.retained_from_start }, { label: 'Active/frozen at period end', value: summary.active_at_end },
      { label: 'Retention rate', value: rateLabel(summary.retention_rate) }, { label: 'Renewal action rate', value: rateLabel(summary.renewal_rate) },
      { label: 'Current records missing activation history', value: summary.missing_activation_history },
    ]} />
    {mode === 'screen' && <div className="ds-chart-pair"><TrendChart title="New memberships by month" description="First activations, not renewal actions." points={data.buckets.map((row) => ({ label: row.period, value: row.new }))} /><BarChart title="Opening cohort outcomes" description="Retained includes frozen memberships." points={[{ label: 'Retained', value: summary.retained_from_start }, { label: 'Churned', value: summary.churned_from_start }]} /></div>}
    <p className="text-sm text-muted-foreground">Frozen memberships remain retained. Renewal actions include expired renewals awaiting payment; renewal alone does not activate a membership. Counts describe memberships and events, not unique member users.</p>
    {summary.missing_activation_history > 0 && <p role="status" className="text-sm">Some current active/frozen memberships lack activation evidence. Historical stock excludes unsupported records rather than guessing from current dates.</p>}
    <AggregateTable caption="Membership activity by month" columns={['Month', 'First activations', 'Renewal actions', 'Expirations', 'Cancellations']} rows={data.buckets.map((row) => ({ key: row.period, cells: [row.period, row.new, row.renewed, row.expired, row.cancelled] }))} />
  </div>
}
