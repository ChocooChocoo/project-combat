import { AggregateTable, MetricsList } from '@/components/decision-support/dashboard-parts'
import { TrendChart } from '@/components/decision-support/trend-chart'
import { rateLabel, type MoneyPayload } from '@/lib/decision-support'
import { money } from '@/lib/membership'
import { BarChart } from './visuals'

export function MoneyView({ data, mode }: { data: MoneyPayload; mode: 'screen' | 'print' }) {
  return <div className="space-y-6" data-report-mode={mode}>
    <MetricsList items={[
      { label: 'Selected-range net revenue', value: money(data.income_expense.totals.income) },
      { label: 'Selected-range accrual expenses', value: money(data.income_expense.totals.expenses) },
      { label: 'Selected-range income less expenses', value: money(data.income_expense.totals.net) },
      { label: 'Selected-range cash-flow net', value: money(data.cash_flow.totals.net) },
    ]} />
    {mode === 'screen' && <div className="grid gap-6 lg:grid-cols-2"><TrendChart title="Income less expenses" description="Selected-range accrual net, including negative values." points={data.income_expense.buckets.map((row) => ({ label: row.period, value: Number(row.net), text: money(row.net) }))} /><BarChart title="Income and expenses" description="Selected-range accrual totals in Philippine pesos." points={[{ label: 'Income', value: Number(data.income_expense.totals.income), text: money(data.income_expense.totals.income) }, { label: 'Expenses', value: Number(data.income_expense.totals.expenses), text: money(data.income_expense.totals.expenses) }]} /></div>}
    {mode === 'screen' && <BarChart title="Where revenue comes from" description="Signed net revenue by income type, in Philippine pesos." points={data.income_types.map((row) => ({ label: row.type.replaceAll('_', ' '), value: Number(row.amount), text: money(row.amount) }))} />}
    <AggregateTable caption="Selected-range income against expenses (accrual)" columns={['Period', 'Income', 'Expenses', 'Net']} rows={data.income_expense.buckets.map((row) => ({ key: row.period, cells: [row.period, money(row.income), money(row.expenses), money(row.net)] }))} />
    <AggregateTable caption="Selected-range cash flow" columns={['Period', 'Cash in', 'Cash out', 'Net']} rows={data.cash_flow.buckets.map((row) => ({ key: row.period, cells: [row.period, money(row.in), money(row.out), money(row.net)] }))} />
    <AggregateTable caption="Net revenue share by income type (signed, not clamped)" columns={['Income type', 'Net revenue', 'Net revenue share']} rows={data.income_types.map((row) => ({ key: row.type, cells: [row.type.replaceAll('_', ' '), money(row.amount), rateLabel(row.share)] }))} />
    <AggregateTable caption="Session and private-coaching net revenue by current service" columns={['Service', 'Net revenue']} rows={data.session_services.map((row) => ({ key: String(row.service_id), cells: [row.name, money(row.amount)] }))} />
    <p className="text-sm">Service allocation anomalies: {data.service_allocation_anomalies}. Dual-linked payments remain in income totals but are not allocated to a service.</p>
    <section className="space-y-4"><h3 className="font-semibold">Outstanding balances as of today; not a historical balance</h3>
      <MetricsList items={[{ label: 'Current outstanding member balances', value: money(data.receivables.outstanding) }, { label: 'Members with positive source debt', value: data.receivables.debtor_count }]} />
      <p className="text-sm text-muted-foreground">As of {data.receivables.as_of}. Current snapshot, independent of selected dates. Historical balances are unavailable; this limitation was accepted on 7 November 2026.</p>
      <AggregateTable caption="Current member receivables" columns={['Member', 'Outstanding']} rows={data.receivables.members.map((row) => ({ key: String(row.user_id), cells: [row.member_name, money(row.total)] }))} />
    </section>
  </div>
}
