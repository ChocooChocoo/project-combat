import { AggregateTable, MetricsList } from '@/components/decision-support/dashboard-parts'
import { rateLabel, type AthletePayload } from '@/lib/decision-support'
import { BarChart } from './visuals'
import { TrendChart } from './trend-chart'

export function AthleteView({ data, mode }: { data: AthletePayload; mode: 'screen' | 'print' }) {
  return <div className="space-y-6" data-report-mode={mode}>
    <p className="text-sm font-medium">coach based on attendance</p>
    <p className="text-sm text-muted-foreground">Coach context is inferred from attendance, not a causal claim or the progress recorder. Athletes without attendance remain unassigned.</p>
    <MetricsList items={[
      { label: 'Promotions', value: data.summary.promotion }, { label: 'Assessments', value: data.summary.assessment },
      { label: 'Competition entries', value: data.summary.competition }, { label: 'Achievements', value: data.summary.achievement },
      { label: 'Attending athletes', value: data.summary.attending_athletes }, { label: 'Attendees with progress', value: data.summary.athletes_with_progress },
      { label: 'Training effectiveness', value: rateLabel(data.summary.effectiveness_rate) },
    ]} />
    {mode === 'screen' && <div className="grid gap-6 lg:grid-cols-2"><BarChart title="Recorded progress" description="Recorded events by type in the selected window." points={[{ label: 'Promotions', value: data.summary.promotion }, { label: 'Assessments', value: data.summary.assessment }, { label: 'Competitions', value: data.summary.competition }, { label: 'Achievements', value: data.summary.achievement }]} /><TrendChart title="Assessment activity" points={data.buckets.map((row) => ({ label: row.period, value: row.assessment }))} /></div>}
    <AggregateTable caption="Progress by month" columns={['Month', 'Promotions', 'Assessments', 'Competitions', 'Achievements']} rows={data.buckets.map((row) => ({ key: row.period, cells: [row.period, row.promotion, row.assessment, row.competition, row.achievement] }))} />
    <AggregateTable caption="Progress by discipline" columns={['Discipline', 'Promotions', 'Assessments', 'Competitions', 'Achievements']} rows={data.disciplines.map((row) => ({ key: row.discipline ?? 'unassigned', cells: [row.discipline ?? 'Unassigned', row.promotion, row.assessment, row.competition, row.achievement] }))} />
    <AggregateTable caption="Progress context: coach based on attendance" columns={['Coach', 'Promotions', 'Assessments', 'Competitions', 'Achievements', 'Attending athletes', 'With progress', 'Effectiveness']} rows={data.coaches.map((row) => ({ key: String(row.coach_id), cells: [row.name, row.promotion, row.assessment, row.competition, row.achievement, row.attending_athletes, row.athletes_with_progress, rateLabel(row.effectiveness_rate)] }))} />
    <AggregateTable caption="Recorded progress and competition results" columns={['Month', 'Coach context', 'Type', 'Discipline', 'Title', 'Division', 'Recorded result', 'Entries']} rows={data.results.map((row, index) => ({ key: String(index), cells: [row.period, row.coach_name, row.type, row.discipline ?? 'Unassigned', row.title, row.division ?? 'Not recorded', row.result ?? 'Not recorded', row.count] }))} />
  </div>
}
