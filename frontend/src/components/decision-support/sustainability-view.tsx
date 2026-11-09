import { AggregateTable, MetricsList } from '@/components/decision-support/dashboard-parts'
import { ForecastView } from '@/components/decision-support/forecast-view'
import { TrendChart } from '@/components/decision-support/trend-chart'
import { rateLabel, type SustainabilityPayload } from '@/lib/decision-support'
import { money } from '@/lib/membership'
import { BarChart } from './visuals'

export function SustainabilityView({ data, mode }: { data: SustainabilityPayload; mode: 'screen' | 'print' }) {
  const membership = data.membership
  return <div className="space-y-8" data-report-mode={mode}>
    <section className="space-y-4"><h3 className="text-lg font-semibold">Income, expenses and net</h3>
      <p className="text-sm">Selected dates: {data.meta.from} to {data.meta.to}. First and last monthly buckets are clipped to these dates.</p>
      <MetricsList items={[{ label: 'Income', value: money(data.financial.totals.income) }, { label: 'Accrual expenses', value: money(data.financial.totals.expenses) }, { label: 'Net', value: money(data.financial.totals.net) }]} />
      {mode === 'screen' && <TrendChart title="Monthly income less expenses (signed net)" points={data.financial.buckets.map((row) => ({ label: row.period, value: Number(row.net), text: money(row.net) }))} />}
      <AggregateTable caption="Monthly selected-range financial actuals" columns={['Month', 'Income', 'Expenses', 'Net']} rows={data.financial.buckets.map((row) => ({ key: row.period, cells: [row.period, money(row.income), money(row.expenses), money(row.net)] }))} />
    </section>
    <section className="space-y-4 border-t pt-8 print:pt-2.5"><h3 className="text-lg font-semibold">Revenue mix</h3>
      {mode === 'screen' && <BarChart title="Income by source" description="Signed net revenue in Philippine pesos." points={data.revenue_mix.income_types.map((row) => ({ label: row.type.replaceAll('_', ' '), value: Number(row.amount), text: money(row.amount) }))} />}
      <p className="text-sm">Selected-range signed net revenue. Shares are not clamped; nonpositive total income has no defined share.</p>
      <AggregateTable caption="Income-type net revenue mix" columns={['Income type', 'Net revenue', 'Net share']} rows={data.revenue_mix.income_types.map((row) => ({ key: row.type, cells: [row.type.replaceAll('_', ' '), money(row.amount), rateLabel(row.share)] }))} />
      <AggregateTable caption="Session revenue by current service" columns={['Service', 'Net revenue']} rows={data.revenue_mix.session_services.map((row) => ({ key: String(row.service_id), cells: [row.name, money(row.amount)] }))} />
      <p className="text-sm">Service allocation anomalies: {data.revenue_mix.service_allocation_anomalies}. Membership income is not allocated to services.</p>
    </section>
    <section className="space-y-4 border-t pt-8 print:pt-2.5"><h3 className="text-lg font-semibold">Retention and renewal</h3>
      {mode === 'screen' && <BarChart title="Opening membership cohort" points={[{ label: 'Retained, including frozen', value: membership.retained_from_start }, { label: 'Churned', value: membership.churned_from_start }]} />}
      <MetricsList items={[
        { label: 'Opening active/frozen memberships', value: membership.active_at_start }, { label: 'Churned from opening cohort', value: membership.churned_from_start }, { label: 'Retained from opening cohort', value: membership.retained_from_start }, { label: 'Retention', value: rateLabel(membership.retention_rate) },
        { label: 'New memberships', value: membership.new }, { label: 'Renewal actions', value: membership.renewed }, { label: 'Expiration events', value: membership.expired }, { label: 'Cancellation events', value: membership.cancelled },
        { label: 'Renewal action rate', value: rateLabel(membership.renewal_rate) }, { label: 'Closing active/frozen memberships', value: membership.active_at_end }, { label: 'Current records missing activation history', value: membership.missing_activation_history },
      ]} />
      <p className="text-sm">Selected-range event-state retention includes frozen memberships. Reactivation does not erase churn. Renewal actions include unpaid expired renewals; renewal rate is actions divided by actions plus expirations.</p>
    </section>
    <section className="space-y-4 border-t pt-8 print:pt-2.5"><h3 className="text-lg font-semibold">Class and room use</h3>
      {mode === 'screen' && <BarChart title="Room capacity in use" description="Pooled fill of offered sessions." points={data.utilization.rooms.map((row) => ({ label: row.name, value: row.fill_rate, text: rateLabel(row.fill_rate) }))} />}
      <p className="text-sm">Selected-range scheduled offerings, using current requested/booked seats. Pooled fill excludes invalid capacity, not availability-hours utilization or room profit. Ordinary classes exclude private coaching; rooms include it.</p>
      <AggregateTable caption="Ordinary class offered-session use" columns={['Class', 'Offered / delivered sessions', 'Offered / delivered minutes', 'Held seats', 'Confirmed', 'Valid capacity', 'Pooled fill', 'Invalid-capacity sessions']} rows={data.utilization.classes.map((row) => ({ key: row.class_key, cells: [row.name, `${row.sessions} / ${row.delivered_sessions}`, `${row.scheduled_minutes} / ${row.delivered_minutes}`, row.held_seats, row.confirmed_bookings, row.capacity, rateLabel(row.fill_rate), row.invalid_capacity_sessions] }))} />
      <AggregateTable caption="Room offered-session use" columns={['Room', 'Offered / delivered sessions', 'Offered / delivered minutes', 'Held seats', 'Confirmed', 'Valid capacity', 'Pooled fill', 'Invalid-capacity sessions']} rows={data.utilization.rooms.map((row) => ({ key: String(row.room_id), cells: [row.name, `${row.sessions} / ${row.delivered_sessions}`, `${row.scheduled_minutes} / ${row.delivered_minutes}`, row.held_seats, row.confirmed_bookings, row.capacity, rateLabel(row.fill_rate), row.invalid_capacity_sessions] }))} />
    </section>
    <section className="space-y-4 border-t pt-8 print:pt-2.5"><h3 className="text-lg font-semibold">Budget and cash-flow outlook</h3>
      <p className="font-medium">Whole calendar month: {data.budget.month}, the month containing selected end. Actuals include days outside selected dates. No daily proration.</p>
      <AggregateTable caption="Whole-month budget totals" columns={['Kind', 'Budget', 'Actual', 'Budget minus actual', 'Actual / budget']} rows={data.budget.totals.map((row) => ({ key: row.kind, cells: [row.kind, money(row.budget), money(row.actual), money(row.difference), rateLabel(row.percentage)] }))} />
      <AggregateTable caption="Whole-month budget by category" columns={['Kind', 'Category', 'Budget', 'Actual', 'Budget minus actual', 'Actual / budget']} rows={data.budget.rows.map((row) => ({ key: `${row.kind}:${row.category}`, cells: [row.kind, row.category.replaceAll('_', ' '), money(row.budget), money(row.actual), money(row.difference), rateLabel(row.percentage)] }))} />
      <h4 className="font-semibold">Cash-flow forecast (separate completed-month window)</h4>
      <ForecastView data={data.cash_flow_forecast} mode={mode} />
    </section>
  </div>
}
