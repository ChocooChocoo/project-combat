import { useEffect, useRef, useState } from 'react'
import { ChartEmpty, DataTable } from '@/components/dashboard/widgets'
import { ChartPanel, type ChartPoint } from './visuals'

const compact = new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 })
const HEIGHT = 240

/** Tracks an element's width so the SVG draws at real pixel size and its text stays legible. */
function useWidth<T extends HTMLElement>(fallback: number) {
  const ref = useRef<T>(null)
  const [width, setWidth] = useState(fallback)
  useEffect(() => {
    const element = ref.current
    if (!element) return
    const observer = new ResizeObserver(([entry]) => setWidth(Math.max(320, Math.round(entry.contentRect.width))))
    observer.observe(element)
    return () => observer.disconnect()
  }, [])
  return [ref, width] as const
}

/** Line chart for a monthly series. Handles negatives, gaps (null) and an optional projected tail. */
export function TrendChart({ title, points, forecastStart, description }: { title: string; points: ChartPoint[]; forecastStart?: number; description?: string }) {
  const [ref, width] = useWidth<HTMLDivElement>(640)
  const values = points.flatMap((p) => p.value === null ? [] : [p.value])
  const min = Math.min(0, ...values)
  const max = Math.max(0, ...values)
  const span = max - min || 1
  const left = 64
  const right = width - 28
  const x = (i: number) => left + i / Math.max(1, points.length - 1) * (right - left)
  const y = (n: number) => HEIGHT - 40 - (n - min) / span * (HEIGHT - 64)
  const projected = forecastStart !== undefined && forecastStart < points.length
  // Leave room for the longest label (about 7px a character at 12px) plus a gap.
  const labelWidth = Math.max(...points.map((p) => p.label.length)) * 7 + 16
  const step = Math.max(1, Math.ceil(labelWidth / ((right - left) / Math.max(1, points.length - 1))))
  const text = (p: ChartPoint) => p.text ?? (p.value === null ? 'Not available' : p.value.toLocaleString())
  return <ChartPanel title={title} description={description}>
    {values.length === 0 ? <ChartEmpty>No observed values available.</ChartEmpty> : <figure className="flex min-w-0 flex-col gap-3">
      <div ref={ref} className="min-w-0">
        <svg aria-hidden="true" width={width} height={HEIGHT} viewBox={`0 0 ${width} ${HEIGHT}`} className="block max-w-full">
          {projected && <rect x={x(forecastStart) - 8} y="12" width={right - x(forecastStart) + 8} height={HEIGHT - 44} rx="6" className="fill-chart-1/8" />}
          {[0, 0.5, 1].map((fraction) => {
            const value = min + span * fraction
            return <g key={fraction}>
              <line x1={left} x2={right} y1={y(value)} y2={y(value)} strokeDasharray="3 4" className="stroke-border" />
              <text x={left - 8} y={y(value) + 4} textAnchor="end" className="fill-muted-foreground text-xs tabular-nums">{compact.format(value)}</text>
            </g>
          })}
          {min < 0 && <line x1={left} x2={right} y1={y(0)} y2={y(0)} className="stroke-muted-foreground/40" />}
          {points.map((p, i) => {
            const previous = points[i - 1]
            const dashed = forecastStart !== undefined && i >= forecastStart
            return <g key={`${p.label}:${i}`}>
              {p.value !== null && previous?.value != null && <line x1={x(i - 1)} y1={y(previous.value)} x2={x(i)} y2={y(p.value)} strokeWidth="2" strokeLinecap="round" strokeDasharray={dashed ? '6 5' : undefined} className="stroke-chart-1" />}
              {p.value !== null && <circle cx={x(i)} cy={y(p.value)} r="4" strokeWidth="2" className="fill-card stroke-chart-1"><title>{`${p.label}: ${text(p)}`}</title></circle>}
              {i % step === 0 && <text x={x(i)} y={HEIGHT - 12} textAnchor="middle" className="fill-muted-foreground text-xs tabular-nums">{p.label}</text>}
            </g>
          })}
        </svg>
      </div>
      {projected && <p className="text-muted-foreground text-xs">Solid line: recorded net. Dashed line and shaded area: projected net.</p>}
      <DataTable caption={title} rows={points.map((p, i) => ({ label: `${p.label}${forecastStart !== undefined && i >= forecastStart ? ' (projected)' : ''}`, text: text(p) }))} />
    </figure>}
  </ChartPanel>
}
