import type { ReactNode } from 'react'

export type ChartPoint = { label: string; value: number | null; text?: string }

export function ChartPanel({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
  return <figure className="ds-chart"><figcaption><h3>{title}</h3>{description && <p>{description}</p>}</figcaption>{children}</figure>
}

export function BarChart({ title, description, points }: { title: string; description?: string; points: ChartPoint[] }) {
  const min = Math.min(0, ...points.map((p) => p.value ?? 0))
  const max = Math.max(0, ...points.map((p) => p.value ?? 0))
  const span = max - min || 1
  const zero = -min / span * 100
  return <ChartPanel title={title} description={description}>
    {points.length === 0 ? <p className="ds-chart-empty">No records in this window.</p> : <div className="ds-bars">{points.map((point, i) => <div className="ds-bar-row" key={`${point.label}:${i}`}>
      <div className="ds-bar-label"><span>{point.label}</span><strong>{point.text ?? (point.value === null ? 'Not available' : point.value.toLocaleString())}</strong></div>
      <div className="ds-bar-track" aria-hidden="true"><span className="ds-bar-zero" style={{ left: `${zero}%` }} />{point.value !== null && <span className={`ds-bar-fill${point.value < 0 ? ' ds-negative' : ''}`} style={{ left: `${Math.min(zero, ((point.value - min) / span) * 100)}%`, width: `${Math.abs(point.value) / span * 100}%` }} />}</div>
    </div>)}</div>}
  </ChartPanel>
}

export function TrafficHeatmap({ points }: { points: ChartPoint[] }) {
  const max = Math.max(1, ...points.map((p) => p.value ?? 0))
  return <ChartPanel title="When the gym is busiest" description="Hourly attendances and visits. Manila time; not unique people.">
    <div className="ds-heatmap">{points.map((p) => <div key={p.label} className="ds-heat-cell">
      <span className="ds-heat-color" aria-hidden="true" style={{ opacity: p.value ? 0.2 + p.value / max * 0.8 : 0.04 }} />
      <span>{p.label}</span><strong>{p.value ?? 'N/A'}</strong>
    </div>)}</div><p className="ds-chart-footnote">Darker cells indicate more traffic. Each cell shows its exact count.</p>
  </ChartPanel>
}
