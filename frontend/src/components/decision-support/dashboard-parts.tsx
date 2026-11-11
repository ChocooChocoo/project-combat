import { useContext, type ReactNode } from 'react'
import { ChevronDown } from 'lucide-react'
import { ReportMode } from './report-mode'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Collapsible, CollapsiblePanel, CollapsibleTrigger } from '@/components/ui/collapsible'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { cn } from '@/lib/utils'

export function DashboardLoading() {
  return <div aria-busy="true" aria-label="Loading dashboard" className="flex flex-col gap-6">
    <Skeleton className="h-28 w-full rounded-2xl" />
    <div className="grid gap-6 lg:grid-cols-2"><Skeleton className="h-64 rounded-2xl" /><Skeleton className="h-64 rounded-2xl" /></div>
  </div>
}

export function DashboardError({ message, retry }: { message: string; retry: () => void }) {
  return <Alert variant="error"><AlertTitle>Could not load decision support</AlertTitle><AlertDescription>{message}<div className="mt-3"><Button variant="outline" onClick={retry}>Retry</Button></div></AlertDescription></Alert>
}

export function DashboardEmpty() {
  return <Card><Empty className="py-10 md:py-12"><EmptyHeader><EmptyTitle>No records for this view</EmptyTitle><EmptyDescription>Choose another date range. Snapshot and history windows are stated beside their figures.</EmptyDescription></EmptyHeader></Empty></Card>
}

/** A card that opens to show more. Print mode always renders it open, without the toggle. */
export function Disclosure({ title, meta, defaultOpen, children, className }: { title: ReactNode; meta?: ReactNode; defaultOpen?: boolean; children: ReactNode; className?: string }) {
  const mode = useContext(ReportMode)
  if (mode === 'print') return <section className={cn('min-w-0 space-y-3', className)}><h3 className="font-semibold">{title}</h3>{children}</section>
  return <Card className={cn('min-w-0 overflow-hidden', className)}>
    <Collapsible defaultOpen={defaultOpen}>
      <CollapsibleTrigger className="group flex min-h-13 w-full cursor-pointer items-center gap-3 px-5 py-3 text-left font-medium text-sm outline-none transition-colors hover:bg-accent/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset">
        <span className="min-w-0 flex-1">{title}</span>
        {meta}
        <ChevronDown aria-hidden="true" className="size-4 shrink-0 text-muted-foreground transition-transform group-data-panel-open:rotate-180" />
      </CollapsibleTrigger>
      <CollapsiblePanel className="border-t">{children}</CollapsiblePanel>
    </Collapsible>
  </Card>
}

type Metric = { label: string; value: ReactNode }

function MetricGrid({ items, primary }: { items: Metric[]; primary?: boolean }) {
  // Every cell draws its top and left hairline; the negative margin tucks the outer edges under the card border.
  return <dl className="-mt-px -ml-px grid grid-cols-2 sm:grid-cols-3 print:m-0 print:grid-cols-3">
    {items.map(({ label, value }) => <div key={label} className="flex min-w-0 flex-col gap-1.5 border-t border-l p-4 sm:p-5 print:border-0 print:p-1.5">
      <dt className="max-w-[35ch] text-muted-foreground text-xs leading-relaxed print:text-[10px]">{label}</dt>
      <dd className={cn('font-semibold tabular-nums tracking-tight [overflow-wrap:anywhere] print:text-[13px]', primary ? 'text-2xl' : 'text-lg')}>{value}</dd>
    </div>)}
  </dl>
}

/** Headline figures: the first three stand out, the rest fold into "Supporting figures". */
export function MetricsList({ items }: { items: Metric[] }) {
  const mode = useContext(ReportMode)
  if (mode === 'print') return <MetricGrid items={items} />
  const primary = items.slice(0, 3)
  const rest = items.slice(3)
  return <Card className="overflow-hidden">
    <MetricGrid items={primary} primary />
    {rest.length > 0 && <Collapsible className="border-t">
      <CollapsibleTrigger className="group flex min-h-11 w-full cursor-pointer items-center gap-2 px-5 py-2 text-left text-muted-foreground text-xs outline-none transition-colors hover:bg-accent/50 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset">
        Supporting figures <Badge variant="secondary" size="sm">{rest.length}</Badge>
        <ChevronDown aria-hidden="true" className="ml-auto size-4 transition-transform group-data-panel-open:rotate-180" />
      </CollapsibleTrigger>
      <CollapsiblePanel><MetricGrid items={rest} /></CollapsiblePanel>
    </Collapsible>}
  </Card>
}

/** Detail table that opens on demand. Phones get one card per row instead of a wide table. */
export function AggregateTable({ caption, columns, rows }: { caption: string; columns: string[]; rows: { key: string; cells: ReactNode[] }[] }) {
  const mode = useContext(ReportMode)
  return <Disclosure title={caption} meta={<Badge variant="outline" size="sm">{rows.length} {rows.length === 1 ? 'row' : 'rows'}</Badge>}>
    {rows.length === 0 && <p className="p-5 text-muted-foreground text-sm">No records in this window.</p>}
    {rows.length > 0 && <>
      <div className={cn(mode === 'screen' && 'hidden sm:block')}>
        <Table>
          <TableHeader><TableRow>{columns.map((column) => <TableHead key={column} className="whitespace-nowrap print:whitespace-normal bg-muted/50 text-xs first:pl-5">{column}</TableHead>)}</TableRow></TableHeader>
          <TableBody>{rows.map(({ key, cells }) => <TableRow key={key}>{cells.map((cell, index) => <TableCell key={index} className="whitespace-nowrap print:whitespace-normal print:break-words text-xs tabular-nums first:pl-5">{cell}</TableCell>)}</TableRow>)}</TableBody>
        </Table>
      </div>
      {mode === 'screen' && <div className="flex flex-col gap-3 p-4 sm:hidden">{rows.map(({ key, cells }) => <dl key={key} className="flex flex-col gap-2 rounded-lg border p-3">
        {cells.map((cell, index) => <div key={index} className="grid min-w-0 grid-cols-2 gap-3"><dt className="text-muted-foreground text-sm">{columns[index]}</dt><dd className="min-w-0 break-words text-sm">{cell}</dd></div>)}
      </dl>)}</div>}
    </>}
  </Disclosure>
}

/** Short explanation under a figure or chart. */
export function Note({ children, tone = 'muted' }: { children: ReactNode; tone?: 'muted' | 'plain' }) {
  return <p className={cn('max-w-prose text-sm leading-relaxed', tone === 'muted' && 'text-muted-foreground')}>{children}</p>
}

/** Heading for a block of related figures inside one view. */
export function SectionTitle({ children, description }: { children: ReactNode; description?: ReactNode }) {
  return <header className="flex flex-col gap-1"><h3 className="font-semibold text-base">{children}</h3>{description && <Note>{description}</Note>}</header>
}
