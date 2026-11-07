import { AggregateTable, MetricsList } from '@/components/decision-support/dashboard-parts'
import { TrendChart } from '@/components/decision-support/trend-chart'
import { fitLabel, forecastMethodLabel, type ForecastPayload } from '@/lib/decision-support'
import { money } from '@/lib/membership'

export function ForecastView({ data, mode }: { data: ForecastPayload; mode: 'screen' | 'print' }) {
  return <div className="space-y-6" data-report-mode={mode}>
    <p className="font-medium">Method: {forecastMethodLabel(data)}</p>
    <p className="text-sm">Basis: {data.basis === 'cash_flow' ? 'Cash flow (cash in / cash out)' : 'Income against expenses (accrual)'}. History: {data.window.from ?? 'No source history'} to {data.window.to}, {data.window.months} observed calendar months.</p>
    <p className="text-sm text-muted-foreground">{data.window.excluded_partial_month ? `Unfinished month excluded: ${data.window.excluded_partial_month}.` : 'History ends at a completed calendar month.'} Selected start does not shorten this history window.</p>
    <section className="space-y-3"><h3 className="font-semibold">R² confidence (fit)</h3>
      <MetricsList items={[{ label: `${data.series_labels.income} R²`, value: fitLabel(data.fit.income_r_squared) }, { label: `${data.series_labels.expenses} R²`, value: fitLabel(data.fit.expense_r_squared) }]} />
      <p className="text-sm text-muted-foreground">Fit is on a 0..1 scale, not a probability, certainty or a prediction interval. Short-history averages have no R² fit.</p>
    </section>
    {data.method === 'no_history' ? <p role="status">No completed source history is available. No projections have been estimated.</p> : <>
      <MetricsList items={[
        { label: `Projected ${data.series_labels.income}`, value: money(data.totals.income) },
        { label: `Projected ${data.series_labels.expenses}`, value: money(data.totals.expenses) },
        { label: `Projected ${data.series_labels.net}`, value: money(data.totals.net) },
      ]} />
      {mode === 'screen' && <TrendChart title={`${data.series_labels.net}: recorded and projected`} description="Completed-month history followed by three projected months. Philippine pesos." forecastStart={data.history.length} points={[...data.history, ...data.buckets].map((row) => ({ label: row.period, value: Number(row.net), text: money(row.net) }))} />}
      <AggregateTable caption="Completed-month source history" columns={['Month', data.series_labels.income, data.series_labels.expenses, data.series_labels.net]} rows={data.history.map((row) => ({ key: row.period, cells: [row.period, money(row.income), money(row.expenses), money(row.net)] }))} />
      <AggregateTable caption="Forecast by month" columns={['Month', data.series_labels.income, data.series_labels.expenses, data.series_labels.net]} rows={data.buckets.map((row) => ({ key: row.period, cells: [row.period, money(row.income), money(row.expenses), money(row.net)] }))} />
    </>}
  </div>
}
