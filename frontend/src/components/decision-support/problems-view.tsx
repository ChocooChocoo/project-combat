import { AggregateTable, MetricsList } from '@/components/decision-support/dashboard-parts'
import { rateLabel, type ProblemItem, type ProblemPayload } from '@/lib/decision-support'
import { BarChart } from './visuals'

function Evidence({ item }: { item: ProblemItem }) {
  if (item.code === 'falling_attendance') {
    const { previous, current, drop_points, session_ids } = item.evidence
    return <>
      <AggregateTable caption="Attendance comparison" columns={['Window', 'Dates', 'Attended', 'Marked outcomes', 'Attendance']} rows={[
        { key: 'previous', cells: ['Previous', `${previous.from} to ${previous.to}`, previous.attended, previous.marked, rateLabel(previous.rate)] },
        { key: 'current', cells: ['Current', `${current.from} to ${current.to}`, current.attended, current.marked, rateLabel(current.rate)] },
      ]} />
      <p className="text-sm">Drop: {drop_points} percentage points. Session IDs: {session_ids.join(', ')}.</p>
    </>
  }
  if (item.code === 'low_use_slot') {
    const row = item.evidence
    return <>
      <MetricsList items={[{ label: 'Weekday (Sunday = 0) / start hour', value: `${row.weekday} / ${row.hour}` }, { label: 'Completed sessions', value: row.sessions }, { label: 'Held seats / capacity', value: `${row.held_seats} / ${row.capacity}` }, { label: 'Mean session fill (not pooled)', value: rateLabel(row.average_fill) }]} />
      <p className="text-sm">Session IDs: {row.session_ids.join(', ')}.</p>
    </>
  }
  const row = item.evidence
  return <>
    <p className="text-sm">Selected window: {row.from} to {row.to}.</p>
    <MetricsList items={[{ label: 'No-shows / marked outcomes', value: `${row.no_show} / ${row.marked}` }, { label: 'No-show rate', value: rateLabel(row.rate) }, { label: 'Awaiting attendance (excluded)', value: row.awaiting_attendance }]} />
  </>
}

export function ProblemsView({ data, mode }: { data: ProblemPayload; mode: 'screen' | 'print' }) {
  const { falling_attendance: falling, low_use_slot: slot, high_no_show: missed } = data.thresholds
  const labels = { falling_attendance: 'Falling class attendance', low_use_slot: 'Low-use time slot', high_no_show: 'High no-show rate' }
  return <div className="space-y-6" data-report-mode={mode}>
    <MetricsList items={[{ label: 'Triggered rules', value: data.summary.total }, { label: labels.falling_attendance, value: data.summary.falling_attendance }, { label: labels.low_use_slot, value: data.summary.low_use_slot }, { label: labels.high_no_show, value: data.summary.high_no_show }]} />
    {mode === 'screen' && <BarChart title="Where attention is needed" description="Triggered rules, not a business score or a count of unique classes." points={[{ label: labels.falling_attendance, value: data.summary.falling_attendance }, { label: labels.low_use_slot, value: data.summary.low_use_slot }, { label: labels.high_no_show, value: data.summary.high_no_show }]} />}
    <details className="ds-detail" open={mode === 'print'}><summary>Configured thresholds</summary><ul className="list-disc space-y-1 p-5 pl-9 text-sm">
      <li>Falling attendance: latest {falling.window_sessions} completed occurrences against previous {falling.window_sessions}; drop at least {falling.drop_points} percentage points and current rate below {falling.current_below}%.</li>
      <li>Low-use slot: at least {slot.minimum_sessions} valid-capacity sessions, arithmetic mean fill below {slot.fill_below}%.</li>
      <li>High no-show: at least {missed.minimum_expected} marked outcomes, no-show rate at least {missed.rate_at_least}%.</li>
    </ul></details>
    {data.items.length === 0 && <p>No rules triggered for this range. This is not a grade for the whole business.</p>}
    {data.items.map((item) => <section key={`${item.code}:${item.key}`} className="ds-problem space-y-3"><h3 className="break-words font-semibold">{labels[item.code]}: {item.name}</h3><Evidence item={item} /></section>)}
  </div>
}
