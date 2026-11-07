import { AggregateTable, MetricsList } from '@/components/decision-support/dashboard-parts'
import type { StockPayload } from '@/lib/decision-support'
import { money } from '@/lib/membership'
import { BarChart } from './visuals'

export function StockView({ data, mode }: { data: StockPayload; mode: 'screen' | 'print' }) {
  return <div className="space-y-6" data-report-mode={mode}>
    <h3 className="font-semibold">Stock levels as of today ({data.as_of})</h3>
    <p className="text-sm text-muted-foreground">Current active items, independent of selected dates. Item counts do not combine incompatible units.</p>
    <MetricsList items={[{ label: 'Active stock items', value: data.summary.items }, { label: 'At or below minimum', value: data.summary.low_items }, { label: 'Out-of-stock items', value: data.summary.out_items }]} />
    {mode === 'screen' && <div className="ds-chart-pair"><BarChart title="Purchase requests" description="Created in selected dates; current stored status." points={data.purchasing.requests.map((row) => ({ label: row.status.replaceAll('_', ' '), value: row.count }))} /><BarChart title="Purchase orders" description="Created in selected dates; current stored status." points={data.purchasing.orders.map((row) => ({ label: row.status.replaceAll('_', ' '), value: row.count }))} /></div>}
    <AggregateTable caption="Current stock quantities" columns={['Item', 'Category', 'Quantity', 'Unit', 'Location', 'Minimum', 'At/below minimum']} rows={data.items.map((row) => ({ key: String(row.id), cells: [row.name, row.category.replaceAll('_', ' '), row.quantity, row.unit, row.location ?? 'Not recorded', row.min_level ?? 'Not set', row.low ? 'Yes' : 'No'] }))} />
    <AggregateTable caption="Requests created in selected range (current stored status)" columns={['Current status', 'Requests']} rows={data.purchasing.requests.map((row) => ({ key: row.status, cells: [row.status.replaceAll('_', ' '), row.count] }))} />
    <AggregateTable caption="Orders created in selected range (current stored status)" columns={['Current status', 'Orders']} rows={data.purchasing.orders.map((row) => ({ key: row.status, cells: [row.status.replaceAll('_', ' '), row.count] }))} />
    <section className="space-y-4"><h3 className="font-semibold">Goods received in selected range (receipt date)</h3><MetricsList items={[{ label: 'Receipts', value: data.purchasing.receipts.count }, { label: 'Receipt total', value: money(data.purchasing.receipts.total) }]} /></section>
  </div>
}
