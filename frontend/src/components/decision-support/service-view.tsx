import { AggregateTable, MetricsList } from '@/components/decision-support/dashboard-parts'
import { rateLabel, type ServicePayload, type UseCounts } from '@/lib/decision-support'
import { BarChart } from './visuals'

function UseTable({ title, rows }: { title: string; rows: (UseCounts & { key: string; name: string })[] }) {
  return <AggregateTable caption={title} columns={['Name', 'Offered sessions', 'Delivered sessions', 'Offered minutes', 'Delivered minutes', 'Held seats', 'Confirmed', 'Valid capacity', 'Fill', 'Invalid capacity sessions']} rows={rows.map((row) => ({ key: row.key, cells: [row.name, row.sessions, row.delivered_sessions, row.scheduled_minutes, row.delivered_minutes, row.held_seats, row.confirmed_bookings, row.capacity, rateLabel(row.fill_rate), row.invalid_capacity_sessions] }))} />
}

export function ServiceView({ data, mode }: { data: ServicePayload; mode: 'screen' | 'print' }) {
  return <div className="space-y-6" data-report-mode={mode}>
    <MetricsList items={[
      { label: 'Ordinary class fill (pooled)', value: rateLabel(data.summary.fill_rate) },
      { label: 'Ordinary held seats / valid capacity', value: `${data.summary.held_seats} / ${data.summary.capacity}` },
      { label: 'Ordinary offered / delivered sessions', value: `${data.summary.sessions} / ${data.summary.delivered_sessions}` },
      { label: 'Ordinary offered / delivered minutes', value: `${data.summary.scheduled_minutes} / ${data.summary.delivered_minutes}` },
      { label: 'Confirmed ordinary bookings', value: data.summary.confirmed_bookings },
      { label: 'Invalid-capacity ordinary sessions', value: data.summary.invalid_capacity_sessions },
      { label: 'Private offered / booked occurrences', value: `${data.private_coaching.offered_sessions} / ${data.private_coaching.booked_sessions}` },
      { label: 'Private booking rate', value: rateLabel(data.private_coaching.booking_rate) },
    ]} />
    {mode === 'screen' && <div className="ds-chart-pair"><BarChart title="Service demand" description="Held seats, ranked highest first. Includes private coaching." points={[...data.services].sort((a, b) => b.held_seats - a.held_seats).map((row) => ({ label: row.name, value: row.held_seats }))} /><BarChart title="Room fill" description="Pooled offered-session fill, not availability-hours utilization." points={data.rooms.map((row) => ({ label: row.name, value: row.fill_rate, text: rateLabel(row.fill_rate) }))} /></div>}
    <AggregateTable caption="Most-booked services (all ties)" columns={['Service', 'Held seats']} rows={data.most_booked.map((row) => ({ key: String(row.service_id), cells: [row.name, row.held_seats] }))} />
    <AggregateTable caption="Least-booked services (all ties, including zero demand)" columns={['Service', 'Held seats']} rows={data.least_booked.map((row) => ({ key: String(row.service_id), cells: [row.name, row.held_seats] }))} />
    <UseTable title="Services (including private coaching)" rows={data.services.map((row) => ({ ...row, key: String(row.service_id) }))} />
    <UseTable title="Ordinary classes (excluding private coaching)" rows={data.classes.map((row) => ({ ...row, key: row.class_key }))} />
    <UseTable title="Coach offered-session use (not payroll eligibility)" rows={data.coaches.map((row) => ({ ...row, key: String(row.coach_id) }))} />
    <UseTable title="Room offered-session use (not availability-hours utilization)" rows={data.rooms.map((row) => ({ ...row, key: String(row.room_id) }))} />
  </div>
}
