import type { ReactNode } from 'react'
import { ChartCard, ChartEmpty, DataTable, HourHeatmap } from '@/components/dashboard/widgets'
import { cn } from '@/lib/utils'

export type ChartPoint = { label: string; value: number | null; text?: string }

export function ChartPanel({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
  return <ChartCard title={title} description={description}>{children}</ChartCard>
}

function pointText(point: ChartPoint): string {
  return point.text ?? (point.value === null ? 'Not available' : point.value.toLocaleString())
}

/** Horizontal bars that handle signed values: negatives grow left of the zero line in the destructive colour. */
export function BarChart({ title, description, points }: { title: string; description?: string; points: ChartPoint[] }) {
  const min = Math.min(0, ...points.map((p) => p.value ?? 0))
  const max = Math.max(0, ...points.map((p) => p.value ?? 0))
  const span = max - min || 1
  const zero = (-min / span) * 100
  return <ChartPanel title={title} description={description}>
    {points.length === 0 ? <ChartEmpty /> : <figure className="min-w-0">
      <ul className="flex max-h-96 flex-col gap-4 overflow-y-auto pr-1" aria-hidden="true">
        {points.map((point, i) => <li key={`${point.label}:${i}`} className="flex flex-col gap-1.5">
          <div className="flex items-baseline justify-between gap-4 text-sm">
            <span className="min-w-0 [overflow-wrap:anywhere] first-letter:uppercase">{point.label}</span>
            <span className="shrink-0 font-medium tabular-nums">{pointText(point)}</span>
          </div>
          <div className="relative h-2 rounded-full bg-muted">
            {min < 0 && <span className="absolute -inset-y-1 w-px bg-border" style={{ left: `${zero}%` }} />}
            {point.value !== null && <span
              className={cn('absolute h-full rounded-full', point.value < 0 ? 'bg-destructive' : 'bg-chart-1')}
              style={{ left: `${Math.min(zero, ((point.value - min) / span) * 100)}%`, width: `${(Math.abs(point.value) / span) * 100}%` }}
            />}
          </div>
        </li>)}
      </ul>
      <DataTable caption={title} rows={points.map((p) => ({ label: p.label, text: pointText(p) }))} />
    </figure>}
  </ChartPanel>
}

export function TrafficHeatmap({ points }: { points: ChartPoint[] }) {
  return <ChartPanel title="When the gym is busiest" description="Hourly attendances and visits. Manila time; not unique people.">
    <HourHeatmap caption="Traffic per hour" points={points.map((p) => ({ label: p.label, value: p.value ?? 0, text: pointText(p) }))} />
  </ChartPanel>
}
