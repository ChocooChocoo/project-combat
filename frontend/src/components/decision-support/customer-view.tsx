import { AggregateTable, MetricsList } from '@/components/decision-support/dashboard-parts'
import { rateLabel, type CustomerPayload } from '@/lib/decision-support'
import { BarChart } from './visuals'

export function CustomerView({ data, mode }: { data: CustomerPayload; mode: 'screen' | 'print' }) {
  const summary = data.summary
  return <div className="space-y-6" data-report-mode={mode}>
    <MetricsList items={[
      { label: 'Leads created in range', value: summary.leads }, { label: 'Converted leads from this cohort', value: summary.converted_leads },
      { label: 'Lead cohort conversion', value: rateLabel(summary.conversion_rate) }, { label: 'Member users gained from CRM', value: summary.members_gained },
      { label: 'Feedback occurring in range', value: summary.feedback }, { label: 'Concerns raised in range', value: summary.concerns_raised },
      { label: 'Concerns open at period end (including backlog)', value: summary.concerns_open_at_end },
      { label: 'Concerns resolved in range', value: summary.concerns_resolved },
    ]} />
    {mode === 'screen' && <div className="grid gap-6 lg:grid-cols-2"><BarChart title="Lead conversion by source" description="Conversion rate for leads created in the selected window." points={data.sources.map((row) => ({ label: row.source.replaceAll('_', ' '), value: row.conversion_rate, text: rateLabel(row.conversion_rate) }))} /><BarChart title="Customer follow-through" description="Open concerns include backlog; resolutions use their resolution date." points={[{ label: 'Feedback received', value: summary.feedback }, { label: 'Concerns raised', value: summary.concerns_raised }, { label: 'Resolved in range', value: summary.concerns_resolved }, { label: 'Open at period end', value: summary.concerns_open_at_end }]} /></div>}
    <p className="text-sm text-muted-foreground">Conversion follows this range’s lead-creation cohort and linked activation evidence, not current CRM stage. Member users gained can include older leads. Open concerns include earlier backlog; resolutions follow their own resolution date.</p>
    <AggregateTable caption="Lead creation cohort by source" columns={['Source', 'Leads', 'Converted leads', 'Conversion']} rows={data.sources.map((row) => ({ key: row.source, cells: [row.source.replaceAll('_', ' '), row.leads, row.converted_leads, rateLabel(row.conversion_rate)] }))} />
  </div>
}
