import { ChartPanel, type ChartPoint } from './visuals'

const compact = new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 })

export function TrendChart({ title, points, forecastStart, description }: { title: string; points: ChartPoint[]; forecastStart?: number; description?: string }) {
  const values = points.flatMap((p) => p.value === null ? [] : [p.value])
  const min = Math.min(0, ...values)
  const max = Math.max(0, ...values)
  const span = max - min || 1
  const x = (i: number) => 58 + i / Math.max(1, points.length - 1) * 620
  const y = (n: number) => 206 - (n - min) / span * 180
  return <ChartPanel title={title} description={description}>
    {values.length === 0 ? <p className="ds-chart-empty">No observed values available.</p> : <div className="ds-line-scroll">
      <svg role="img" aria-label={`${title}. Exact values in chart data below.`} viewBox="0 0 710 252" className="ds-line-chart">
        {forecastStart !== undefined && forecastStart < points.length && <rect x={x(forecastStart) - 12} y="16" width={690 - x(forecastStart)} height="200" className="ds-projection-area" />}
        {[0, 0.5, 1].map((fraction) => { const value = min + span * fraction; return <g key={fraction}><line x1="58" x2="678" y1={y(value)} y2={y(value)} className="ds-grid-line" /><text x="48" y={y(value) + 4} textAnchor="end">{compact.format(value)}</text></g> })}
        {points.map((p, i) => {
          const previous = points[i - 1]
          return <g key={`${p.label}:${i}`}>
            {p.value !== null && previous?.value != null && <line x1={x(i - 1)} y1={y(previous.value)} x2={x(i)} y2={y(p.value)} className="ds-series-line" strokeDasharray={forecastStart !== undefined && i >= forecastStart ? '6 5' : undefined} />}
            {p.value !== null && <circle cx={x(i)} cy={y(p.value)} r="4" className="ds-series-point"><title>{p.label}: {p.text ?? p.value}</title></circle>}
            {(points.length <= 7 || i % Math.ceil(points.length / 6) === 0 || i === points.length - 1) && <text x={x(i)} y="239" textAnchor="middle">{p.label}</text>}
          </g>
        })}
      </svg>
    </div>}
    {forecastStart !== undefined && <p className="ds-chart-footnote">Solid line: recorded net. Dashed line and shaded area: projected net.</p>}
    <details className="ds-chart-data"><summary>Chart data <span>{points.length} points</span></summary><dl>{points.map((p, i) => <div key={`${p.label}:${i}`}><dt>{p.label}{forecastStart !== undefined && i >= forecastStart ? ' (projected)' : ''}</dt><dd>{p.text ?? (p.value === null ? 'Not available' : p.value.toLocaleString())}</dd></div>)}</dl></details>
  </ChartPanel>
}
