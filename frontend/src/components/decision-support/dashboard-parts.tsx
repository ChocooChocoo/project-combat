import { useContext, type ReactNode } from 'react'
import { ReportMode } from './report-mode'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'

export function DashboardLoading() {
  return <div aria-busy="true" aria-label="Loading dashboard" className="space-y-4"><Skeleton className="h-24 w-full" /><Skeleton className="h-64 w-full" /></div>
}

export function DashboardError({ message, retry }: { message: string; retry: () => void }) {
  return <Alert variant="error"><AlertTitle>Could not load decision support</AlertTitle><AlertDescription>{message}<div className="mt-3"><Button variant="outline" onClick={retry}>Retry</Button></div></AlertDescription></Alert>
}

export function DashboardEmpty() {
  return <Empty><EmptyHeader><EmptyTitle>No records for this view</EmptyTitle><EmptyDescription>Choose another date range. Snapshot and history windows are stated beside their figures.</EmptyDescription></EmptyHeader></Empty>
}

export function MetricsList({ items }: { items: { label: string; value: ReactNode }[] }) {
  const mode = useContext(ReportMode)
  const render = (metrics: typeof items) => <dl className="ds-metrics">{metrics.map(({ label, value }, index) => <div key={label} className={index < 3 ? 'ds-metric ds-metric-primary' : 'ds-metric'}><dt>{label}</dt><dd>{value}</dd></div>)}</dl>
  if (mode === 'print' || items.length <= 3) return render(items)
  return <div className="ds-summary">{render(items.slice(0, 3))}<details className="ds-supporting"><summary>Supporting figures <span>{items.length - 3}</span></summary>{render(items.slice(3))}</details></div>
}

export function AggregateTable({ caption, columns, rows }: { caption: string; columns: string[]; rows: { key: string; cells: ReactNode[] }[] }) {
  const mode = useContext(ReportMode)
  const content = <>
    {rows.length === 0 && <p className="p-4 text-sm text-muted-foreground">No records in this window.</p>}
    <div className="hidden overflow-x-auto sm:block print:block"><Table><TableHeader><TableRow>{columns.map((column) => <TableHead key={column} className="whitespace-normal">{column}</TableHead>)}</TableRow></TableHeader><TableBody>{rows.map(({ key, cells }) => <TableRow key={key}>{cells.map((cell, index) => <TableCell key={index} className="whitespace-normal break-words">{cell}</TableCell>)}</TableRow>)}</TableBody></Table></div>
    <div className="space-y-3 sm:hidden print:hidden">{rows.map(({ key, cells }) => <dl key={key} className="space-y-2 rounded-lg border p-3">{cells.map((cell, index) => <div key={index} className="grid min-w-0 grid-cols-2 gap-3"><dt className="text-sm text-muted-foreground">{columns[index]}</dt><dd className="min-w-0 break-words text-sm">{cell}</dd></div>)}</dl>)}</div>
  </>
  return mode === 'print' ? <section aria-label={caption} className="min-w-0 space-y-3"><h3 className="font-semibold">{caption}</h3>{content}</section> : <details className="ds-detail"><summary><span>{caption}</span><small>{rows.length} {rows.length === 1 ? 'row' : 'rows'}</small></summary>{content}</details>
}
