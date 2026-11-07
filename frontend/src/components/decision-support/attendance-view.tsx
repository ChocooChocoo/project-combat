import { AggregateTable, MetricsList } from '@/components/decision-support/dashboard-parts'
import { TrendChart } from '@/components/decision-support/trend-chart'
import { TrafficHeatmap } from './visuals'
import { rateLabel, type AttendancePayload } from '@/lib/decision-support'

export function AttendanceView({ data, mode }: { data: AttendancePayload; mode: 'screen' | 'print' }) {
  const counts = data.summary
  return <div className="space-y-6">
    <MetricsList items={[
      { label: 'Attendance rate (attended / marked)', value: rateLabel(counts.attendance_rate) },
      { label: 'No-show rate (no-show / marked)', value: rateLabel(counts.no_show_rate) },
      { label: 'Attended / marked', value: `${counts.attended} / ${counts.marked}` },
      { label: 'Awaiting attendance', value: counts.awaiting_attendance },
      { label: 'Unknown markers', value: counts.unknown_markers },
      { label: 'Markers on cancelled sessions', value: counts.cancelled_session_markers },
    ]} />
    {mode === 'screen' && <div className="ds-chart-pair"><TrendChart title="Traffic by weekday" description="Attendances and gym visits, not unique people." points={data.weekdays.map((row) => ({ label: row.label, value: row.total, text: String(row.total) }))} /><TrafficHeatmap points={data.hours.map((row) => ({ label: row.label, value: row.total }))} /></div>}
    <AggregateTable caption="Class attendance" columns={['Class', 'Sessions', 'Present', 'Late', 'Absent', 'No-show', 'Attended / marked', 'Attendance', 'No-show rate', 'Awaiting', 'Unknown', 'Cancelled-session markers']} rows={data.classes.map((row) => ({ key: row.class_key, cells: [row.name, row.sessions, row.present, row.late, row.absent, row.no_show, `${row.attended} / ${row.marked}`, rateLabel(row.attendance_rate), rateLabel(row.no_show_rate), row.awaiting_attendance, row.unknown_markers, row.cancelled_session_markers] }))} />
    <p className="text-sm">Peak days: {data.peaks.weekdays.length ? data.peaks.weekdays.map((day) => data.weekdays[day].label).join(', ') : 'No recorded traffic'}. Peak hours: {data.peaks.hours.length ? data.peaks.hours.map((hour) => data.hours[hour].label).join(', ') : 'No recorded traffic'}.</p>
    <AggregateTable caption="Weekday traffic (attendances and visits, not unique people)" columns={['Day', 'Class attended', 'Gym visits', 'Total']} rows={data.weekdays.map((row) => ({ key: String(row.weekday), cells: [row.label, row.class_attended, row.gym_visits, row.total] }))} />
    <AggregateTable caption="Hourly traffic (Manila session start / visit check-in)" columns={['Hour', 'Class attended', 'Gym visits', 'Total']} rows={data.hours.map((row) => ({ key: String(row.hour), cells: [row.label, row.class_attended, row.gym_visits, row.total] }))} />
  </div>
}
