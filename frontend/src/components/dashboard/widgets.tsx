import type { ReactElement, ReactNode } from 'react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Meter, MeterIndicator, MeterLabel, MeterTrack, MeterValue } from '@/components/ui/meter'
import { Skeleton } from '@/components/ui/skeleton'
import { Tooltip, TooltipPopup, TooltipTrigger } from '@/components/ui/tooltip'
import { addDays, dayLabel, mondayOf } from '@/lib/dashboard'
import { cn } from '@/lib/utils'

/*
 * P07 dashboard widgets. Built from coss UI parts and Tailwind theme tokens only.
 * Charts use one series colour (chart-1); status colours (success, warning, destructive)
 * are kept for attendance states. Every chart has a hover tooltip and a screen-reader table.
 */

export function DashboardPage({ header, children }: { header: ReactNode; children: ReactNode }) {
  return <div className="flex min-w-0 flex-col gap-6">{header}{children}</div>
}

export function DashboardState({ error, retry }: { error: string; retry: () => void }) {
  if (error) {
    return <Alert variant="error"><AlertTitle>Could not load this dashboard</AlertTitle><AlertDescription>{error}<div className="mt-3"><Button variant="outline" onClick={retry}>Retry</Button></div></AlertDescription></Alert>
  }
  return <div aria-busy="true" aria-label="Loading dashboard" className="flex flex-col gap-6">
    <Skeleton className="h-28 w-full rounded-2xl" />
    <Skeleton className="h-72 w-full rounded-2xl" />
    <div className="grid gap-6 lg:grid-cols-2"><Skeleton className="h-64 rounded-2xl" /><Skeleton className="h-64 rounded-2xl" /></div>
  </div>
}

/** trend: optional recent values of the same measure, oldest first, drawn as a sparkline. */
export type Stat = { label: string; value: ReactNode; hint?: ReactNode; trend?: number[]; trendLabel?: string }

/** Row one: the few numbers that answer "how are things right now", in one card. */
export function StatStrip({ items }: { items: Stat[] }) {
  return <Card>
    <dl className="grid sm:grid-cols-2 lg:grid-cols-4">
      {items.map((item, i) => <div
        key={item.label}
        className={cn('flex min-w-0 flex-col gap-1 p-5', i > 0 && 'border-t', i === 1 && 'sm:border-t-0 sm:border-l', i === 3 && 'sm:border-l', i > 1 && 'lg:border-t-0', i === 2 && 'lg:border-l')}
      >
        <dt className="text-muted-foreground text-xs">{item.label}</dt>
        <dd className="flex items-end justify-between gap-3">
          <span className="font-semibold text-2xl tabular-nums tracking-tight">{item.value}</span>
          {item.trend && item.trend.length > 1 && item.trend.some(Boolean) && <Sparkline values={item.trend} label={item.trendLabel ?? `${item.label} trend`} />}
        </dd>
        {item.hint && <dd className="text-muted-foreground text-xs leading-relaxed">{item.hint}</dd>}
      </div>)}
    </dl>
  </Card>
}

/** Small trend line with a soft area and an end dot. Exact values are in the label. */
export function Sparkline({ values, label }: { values: number[]; label: string }) {
  const max = Math.max(...values)
  const min = Math.min(...values, 0)
  const span = max - min || 1
  const x = (i: number) => (i / (values.length - 1)) * 96 + 2
  const y = (v: number) => 26 - ((v - min) / span) * 22
  const line = values.map((v, i) => `${x(i)},${y(v)}`).join(' ')
  return <svg viewBox="0 0 100 30" className="h-8 w-24 shrink-0 overflow-visible" role="img" aria-label={`${label}: ${values.join(', ')}`}>
    <polygon points={`2,28 ${line} 98,28`} className="fill-chart-1/12" />
    <polyline points={line} fill="none" strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" vectorEffect="non-scaling-stroke" className="stroke-chart-1" />
    <circle cx={x(values.length - 1)} cy={y(values.at(-1) ?? 0)} r="2.5" className="fill-chart-1" />
  </svg>
}

/** Two columns on wide screens, one on phones. */
export function DashboardGrid({ children }: { children: ReactNode }) {
  return <div className="grid min-w-0 gap-6 lg:grid-cols-2">{children}</div>
}

export function ChartCard({ title, description, children, className }: { title: string; description?: string; children: ReactNode; className?: string }) {
  return <Card className={cn('min-w-0', className)}>
    <CardHeader>
      <CardTitle className="text-base">{title}</CardTitle>
      {description && <CardDescription className="text-xs leading-relaxed">{description}</CardDescription>}
    </CardHeader>
    <CardPanel className="min-w-0">{children}</CardPanel>
  </Card>
}

export function ChartEmpty({ children = 'No records in this window yet.' }: { children?: ReactNode }) {
  return <Empty className="py-6 md:py-8"><EmptyHeader><EmptyTitle className="text-sm">Nothing to show</EmptyTitle><EmptyDescription className="text-xs">{children}</EmptyDescription></EmptyHeader></Empty>
}

/** text is the exact value (tooltip, table); short is an optional compact label drawn on the chart. */
export type Point = { label: string; value: number; text: string; short?: string; hint?: string }

/** Hidden table that carries every exact value for screen readers. */
export function DataTable({ caption, rows }: { caption: string; rows: { label: string; text: string }[] }) {
  return <table className="sr-only"><caption>{caption}</caption><tbody>{rows.map((row, i) => <tr key={`${row.label}:${i}`}><th scope="row">{row.label}</th><td>{row.text}</td></tr>)}</tbody></table>
}

function Tip({ children, content }: { children: ReactElement; content: ReactNode }) {
  return <Tooltip><TooltipTrigger render={children} /><TooltipPopup>{content}</TooltipPopup></Tooltip>
}

/** Vertical bars for a short series over time. Values sit above bars when there are few of them. */
export function ColumnChart({ caption, points }: { caption: string; points: Point[] }) {
  if (!points.some((p) => p.value !== 0)) return <ChartEmpty />
  const max = Math.max(...points.map((p) => p.value), 0) || 1
  const labelled = points.length <= 8
  return <figure className="min-w-0">
    <div className="flex h-44 items-end gap-1.5 sm:gap-2" aria-hidden="true">
      {points.map((p, i) => <Tip key={`${p.label}:${i}`} content={<span className="tabular-nums">{p.label}: {p.text}</span>}>
        <div className="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1.5">
          {labelled && <span className="max-w-full truncate text-muted-foreground text-xs tabular-nums">{p.value ? p.short ?? p.text : ''}</span>}
          <div className="w-full max-w-12 rounded-t-sm bg-chart-1 transition-opacity hover:opacity-80" style={{ height: `${Math.max(p.value > 0 ? 2 : 0, (p.value / max) * 100)}%` }} />
        </div>
      </Tip>)}
    </div>
    <div className="mt-2 flex gap-1.5 border-t pt-2 sm:gap-2" aria-hidden="true">
      {points.map((p, i) => <span key={`${p.label}:${i}`} className="min-w-0 flex-1 truncate text-center text-muted-foreground text-xs">{p.label}</span>)}
    </div>
    <DataTable caption={caption} rows={points} />
  </figure>
}

/** Horizontal bars for comparing a few named groups, largest first if the caller sorts them. */
export function BarList({ caption, points }: { caption: string; points: Point[] }) {
  if (!points.some((p) => p.value !== 0)) return <ChartEmpty />
  const max = Math.max(...points.map((p) => p.value), 0) || 1
  return <figure className="min-w-0">
    <ul className="flex flex-col gap-4" aria-hidden="true">
      {points.map((p, i) => <li key={`${p.label}:${i}`} className="flex flex-col gap-1.5">
        <div className="flex items-baseline justify-between gap-4 text-sm">
          <span className="min-w-0 truncate">{p.label}{p.hint && <span className="ml-2 text-muted-foreground text-xs">{p.hint}</span>}</span>
          <span className="shrink-0 font-medium tabular-nums">{p.text}</span>
        </div>
        <div className="h-2 overflow-hidden rounded-full bg-muted"><div className="h-full rounded-full bg-chart-1" style={{ width: `${Math.max(0, (p.value / max) * 100)}%` }} /></div>
      </li>)}
    </ul>
    <DataTable caption={caption} rows={points} />
  </figure>
}

/** "Used of total" figure with the coss Meter. */
export function UsageMeter({ label, value, max, text }: { label: string; value: number; max: number; text: string }) {
  return <Meter value={Math.min(value, max)} max={Math.max(1, max)}>
    <div className="flex items-baseline justify-between gap-4">
      <MeterLabel className="min-w-0 truncate font-normal">{label}</MeterLabel>
      <MeterValue className="shrink-0 font-medium">{() => text}</MeterValue>
    </div>
    <MeterTrack className="rounded-full"><MeterIndicator className="rounded-full bg-chart-1" /></MeterTrack>
  </Meter>
}

/** One label and one number per line. */
export function StatList({ rows }: { rows: { label: string; hint?: string; value: ReactNode }[] }) {
  return <dl className="flex flex-col divide-y">
    {rows.map((row) => <div key={row.label} className="flex items-baseline justify-between gap-4 py-3 first:pt-0 last:pb-0">
      <dt className="min-w-0 text-sm">{row.label}{row.hint && <span className="block text-muted-foreground text-xs">{row.hint}</span>}</dt>
      <dd className="shrink-0 font-medium text-sm tabular-nums">{row.value}</dd>
    </div>)}
  </dl>
}

/** Sequential shading on the series colour: none, then four steps. */
const LEVELS = ['bg-muted', 'bg-chart-1/30', 'bg-chart-1/55', 'bg-chart-1/80', 'bg-chart-1']

/** Cell text per step, picked for 4.5:1 contrast on chart-1 in both themes. */
const TEXT_ON_LEVEL = ['text-muted-foreground', 'text-foreground', 'text-foreground', 'text-black dark:text-white', 'text-black']

function level(value: number, max: number): number {
  return value <= 0 ? 0 : Math.min(4, Math.ceil((value / max) * 4))
}

function Legend({ items }: { items: { label: string; className: string }[] }) {
  return <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-muted-foreground text-xs" aria-hidden="true">
    {items.map((item) => <span key={item.label} className="inline-flex items-center gap-1.5"><span className={cn('size-3 rounded-[3px]', item.className)} />{item.label}</span>)}
  </div>
}

function ScaleLegend() {
  return <div className="flex items-center gap-1 text-muted-foreground text-xs" aria-hidden="true">
    <span className="mr-1">Less</span>{LEVELS.map((tone) => <span key={tone} className={cn('size-3 rounded-[3px]', tone)} />)}<span className="ml-1">More</span>
  </div>
}

export type HeatDay = { date: string; value: number; text: string; tone?: string }

const WEEKDAYS = ['Mon', '', 'Wed', '', 'Fri', '', 'Sun']

/**
 * Calendar heatmap: one column per week, Monday on top. Cells shade by value, or take a
 * status tone when given (with a legend). Hover shows the exact day value.
 */
export function CalendarHeatmap({ caption, from, to, days, legend }: {
  caption: string; from: string; to: string; days: HeatDay[]; legend?: { label: string; className: string }[]
}) {
  const byDate = new Map(days.map((day) => [day.date, day]))
  const max = Math.max(1, ...days.map((day) => day.value))
  const dates: string[] = []
  for (let day = mondayOf(from); day <= addDays(mondayOf(to), 6); day = addDays(day, 1)) dates.push(day)
  const recorded = days.filter((day) => day.tone || day.value > 0)
  // Week columns share the full width; cells stay square until they reach max-h-9, then widen.
  const cell = 'aspect-square max-h-9 w-full min-w-2.5'

  return <figure className="flex min-w-0 flex-col gap-4">
    <div className="overflow-x-auto pb-1" aria-hidden="true">
      <div className="grid grid-flow-col grid-rows-7 gap-1" style={{ gridTemplateColumns: `auto repeat(${dates.length / 7}, minmax(0, 1fr))` }}>
        {WEEKDAYS.map((label, i) => <span key={`w${i}`} className="flex items-center pr-2 text-[0.625rem] text-muted-foreground">{label}</span>)}
        {dates.map((date) => {
          if (date < from || date > to) return <span key={date} className={cell} />
          const day = byDate.get(date)
          return <Tip key={date} content={<span className="tabular-nums">{dayLabel(date)}: {day?.text ?? 'No records'}</span>}>
            <span className={cn(cell, 'rounded-[3px]', day?.tone ?? LEVELS[level(day?.value ?? 0, max)])} />
          </Tip>
        })}
      </div>
    </div>
    {legend ? <Legend items={legend} /> : <ScaleLegend />}
    <DataTable caption={caption} rows={recorded.map((day) => ({ label: dayLabel(day.date), text: day.text }))} />
  </figure>
}

/** Hour-of-day heatmap: two rows of twelve in wide cards, four rows of six in narrow ones. */
export function HourHeatmap({ caption, points }: { caption: string; points: Point[] }) {
  if (!points.some((p) => p.value > 0)) return <ChartEmpty />
  const max = Math.max(1, ...points.map((p) => p.value))
  return <figure className="@container flex min-w-0 flex-col gap-4">
    <div className="grid grid-cols-6 gap-1 @2xl:grid-cols-12" aria-hidden="true">
      {points.map((p) => {
        const step = level(p.value, max)
        return <Tip key={p.label} content={<span className="tabular-nums">{p.label}: {p.text}</span>}>
          <div className={cn('flex flex-col items-center gap-0.5 rounded-md py-2', LEVELS[step], TEXT_ON_LEVEL[step])}>
            <span className="text-[0.625rem] opacity-80">{p.label}</span>
            <span className="font-medium text-xs tabular-nums">{p.value}</span>
          </div>
        </Tip>
      })}
    </div>
    <ScaleLegend />
    <DataTable caption={caption} rows={points} />
  </figure>
}

/** Fixed colour slots. Categories take chart-1..4 in order; outcomes use the status tokens. */
const TONES = {
  chart1: { stroke: 'stroke-chart-1', bg: 'bg-chart-1' },
  chart2: { stroke: 'stroke-chart-2', bg: 'bg-chart-2' },
  chart3: { stroke: 'stroke-chart-3', bg: 'bg-chart-3' },
  chart4: { stroke: 'stroke-chart-4', bg: 'bg-chart-4' },
  success: { stroke: 'stroke-success', bg: 'bg-success' },
  warning: { stroke: 'stroke-warning', bg: 'bg-warning' },
  destructive: { stroke: 'stroke-destructive', bg: 'bg-destructive' },
  muted: { stroke: 'stroke-muted-foreground/40', bg: 'bg-muted-foreground/40' },
} as const

export type Tone = keyof typeof TONES
export type Slice = { label: string; value: number; text?: string; tone: Tone }

function share(value: number, total: number): string {
  return total > 0 ? `${Math.round((value / total) * 100)}%` : '0%'
}

function sliceRows(slices: Slice[], total: number) {
  return slices.map((s) => ({ label: s.label, text: `${s.text ?? s.value} (${share(s.value, total)})` }))
}

/** Legend that doubles as the direct label: colour, name, value and share for every slice. */
function SliceLegend({ slices, total }: { slices: Slice[]; total: number }) {
  return <ul className="flex w-full min-w-0 flex-1 flex-col gap-2.5" aria-hidden="true">
    {slices.map((slice) => <li key={slice.label} className="flex items-center gap-2 text-sm">
      <span className={cn('size-2.5 shrink-0 rounded-full', TONES[slice.tone].bg)} />
      <span className="min-w-0 flex-1 truncate">{slice.label}</span>
      <span className="font-medium tabular-nums">{slice.text ?? slice.value}</span>
      <span className="w-10 text-right text-muted-foreground text-xs tabular-nums">{share(slice.value, total)}</span>
    </li>)}
  </ul>
}

/** Donut for parts of one whole (four slices at most). The total sits in the middle. */
export function DonutChart({ caption, slices, centerLabel, centerValue }: { caption: string; slices: Slice[]; centerLabel: string; centerValue?: ReactNode }) {
  const total = slices.reduce((sum, s) => sum + s.value, 0)
  if (total <= 0) return <ChartEmpty />
  const r = 40
  const c = 2 * Math.PI * r
  const gap = slices.filter((s) => s.value > 0).length > 1 ? 1.5 : 0
  const arcs = slices.reduce<{ slice: Slice; start: number; length: number }[]>((list, slice) => {
    const start = list.length ? list[list.length - 1].start + list[list.length - 1].length : 0
    return [...list, { slice, start, length: (slice.value / total) * c }]
  }, [])
  return <figure className="flex min-w-0 flex-col items-center gap-6 sm:flex-row">
    <div className="relative size-36 shrink-0">
      <svg viewBox="0 0 100 100" className="size-full -rotate-90" aria-hidden="true">
        <circle cx="50" cy="50" r={r} fill="none" strokeWidth="10" className="stroke-muted" />
        {arcs.filter((arc) => arc.slice.value > 0).map(({ slice, start, length }) => {
          const dash = Math.max(0.5, length - gap)
          return <circle key={slice.label} cx="50" cy="50" r={r} fill="none" strokeWidth="10" strokeDasharray={`${dash} ${c - dash}`} strokeDashoffset={-start} className={TONES[slice.tone].stroke}><title>{`${slice.label}: ${slice.text ?? slice.value}`}</title></circle>
        })}
      </svg>
      <div className="absolute inset-0 flex flex-col items-center justify-center">
        <span className="font-semibold text-xl tabular-nums tracking-tight">{centerValue ?? total}</span>
        <span className="text-muted-foreground text-xs">{centerLabel}</span>
      </div>
    </div>
    <SliceLegend slices={slices} total={total} />
    <DataTable caption={caption} rows={sliceRows(slices, total)} />
  </figure>
}

/** One stacked bar for outcome shares, with the legend underneath. */
export function SegmentBar({ caption, slices }: { caption: string; slices: Slice[] }) {
  const total = slices.reduce((sum, s) => sum + s.value, 0)
  if (total <= 0) return <ChartEmpty />
  return <figure className="flex min-w-0 flex-col gap-4">
    <div className="flex h-3 gap-0.5 overflow-hidden rounded-full" aria-hidden="true">
      {slices.filter((s) => s.value > 0).map((slice) => <Tip key={slice.label} content={<span className="tabular-nums">{slice.label}: {slice.text ?? slice.value} ({share(slice.value, total)})</span>}>
        <div className={cn('h-full', TONES[slice.tone].bg)} style={{ width: `${(slice.value / total) * 100}%` }} />
      </Tip>)}
    </div>
    <SliceLegend slices={slices} total={total} />
    <DataTable caption={caption} rows={sliceRows(slices, total)} />
  </figure>
}

/** Circular gauge for one rate out of 100. Null shows an empty track. */
export function RateRing({ label, value, hint }: { label: string; value: number | null; hint?: string }) {
  const r = 40
  const c = 2 * Math.PI * r
  const filled = value === null ? 0 : (Math.max(0, Math.min(100, value)) / 100) * c
  return <figure className="flex min-w-0 flex-col items-center gap-2 text-center">
    <div className="relative size-24">
      <svg viewBox="0 0 100 100" className="size-full -rotate-90" aria-hidden="true">
        <circle cx="50" cy="50" r={r} fill="none" strokeWidth="9" className="stroke-muted" />
        {filled > 0 && <circle cx="50" cy="50" r={r} fill="none" strokeWidth="9" strokeLinecap="round" strokeDasharray={`${filled} ${c}`} className="stroke-chart-1" />}
      </svg>
      <span className="absolute inset-0 flex items-center justify-center font-semibold text-lg tabular-nums tracking-tight">{value === null ? 'N/A' : `${Math.round(value)}%`}</span>
    </div>
    <figcaption className="flex flex-col gap-0.5">
      <span className="font-medium text-sm">{label}</span>
      {hint && <span className="text-muted-foreground text-xs leading-relaxed">{hint}</span>}
    </figcaption>
  </figure>
}

/** Two to four rate rings side by side. */
export function RingRow({ children }: { children: ReactNode }) {
  return <div className="grid grid-cols-2 gap-6 sm:grid-cols-[repeat(auto-fit,minmax(8rem,1fr))]">{children}</div>
}
