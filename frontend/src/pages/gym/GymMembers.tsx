import { Badge } from '@/components/ui/badge'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { MembershipStatusBadge } from '@/components/membership/membership-parts'
import { PageControls } from '@/components/page-controls'
import { PageHeader } from '@/components/page-header'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { api, ApiError } from '@/lib/api'
import { pageMetaFrom, type PageMeta } from '@/lib/pagination'
import { money, periodLabel, STATUS_LABELS, type Membership, type MembershipStatus } from '@/lib/membership'

type Filter = 'all' | Exclude<MembershipStatus, 'applied' | 'rejected'>
const FILTERS: { value: Filter; label: string }[] = [
  { value: 'all', label: 'All members' },
  ...(['active', 'awaiting_payment', 'frozen', 'expired', 'cancelled'] as const).map((value) => ({ value, label: STATUS_LABELS[value] })),
]

/** S-22, R-36 to R-41: everyone approved to join, with their membership state. */
export default function GymMembers() {
  const { gymId: gymIdParam } = useParams()
  const gymId = Number(gymIdParam)
  const [filter, setFilter] = useState<Filter>('all')
  const [rows, setRows] = useState<Membership[] | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [page, setPage] = useState(1) // (R-123)
  const [meta, setMeta] = useState<PageMeta | null>(null)

  useEffect(() => {
    let live = true
    setRows(null)
    setError('')
    const status = filter === 'all' ? '' : `&status=${filter}`
    api<{ memberships: Membership[] }>(`/gyms/${gymId}/memberships?view=members&page=${page}${status}`)
      .then((result) => {
        if (!live) return
        setRows(result.memberships)
        setMeta(pageMetaFrom(result, result.memberships.length))
      })
      .catch((failure) => {
        if (!live) return
        setRows([])
        setError(failure instanceof ApiError ? failure.message : 'Could not load members.')
      })
    return () => {
      live = false
    }
  }, [gymId, filter, version, page])

  const link = (row: Membership) => `/gyms/${gymId}/manage/members/${row.id}`

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Members" description="Each member's plan, status and balance. Open a member to renew, freeze or cancel, take payments and keep their athlete record." />
      <Card>
        <CardHeader>
          <CardTitle render={<h2 />} className="sr-only">
            Member list
          </CardTitle>
          <div className="flex flex-wrap items-center gap-3">
            {rows === null ? (
              <Skeleton className="mr-auto h-4 w-20" />
            ) : (
              <p className="mr-auto text-muted-foreground text-sm">
                {meta?.total ?? rows.length} {(meta?.total ?? rows.length) === 1 ? 'member' : 'members'}
              </p>
            )}
            <Select items={FILTERS} value={filter} onValueChange={(next) => {
              setFilter((next as Filter) ?? 'all')
              setPage(1)
            }}>
              <SelectTrigger size="sm" className="w-48" aria-label="Filter members by status">
                <SelectValue />
              </SelectTrigger>
              <SelectPopup>
                {FILTERS.map((item) => (
                  <SelectItem key={item.value} value={item.value}>
                    {item.label}
                  </SelectItem>
                ))}
              </SelectPopup>
            </Select>
          </div>
        </CardHeader>
        <CardPanel>
          {error ? (
            <Alert variant="error">
              <AlertTitle>Members could not be loaded</AlertTitle>
              <AlertDescription>{error}</AlertDescription>
              <AlertAction>
                <Button size="xs" variant="outline" onClick={() => setVersion((v) => v + 1)}>
                  Try again
                </Button>
              </AlertAction>
            </Alert>
          ) : rows === null ? (
            <div className="flex flex-col gap-2" aria-busy="true">
              <Skeleton className="h-12" />
              <Skeleton className="h-12" />
            </div>
          ) : rows.length === 0 ? (
            <Empty>
              <EmptyHeader>
                <EmptyTitle>No members {filter === 'all' ? 'yet' : 'with this status'}</EmptyTitle>
                <EmptyDescription>People become members once their application is approved under Applications.</EmptyDescription>
              </EmptyHeader>
            </Empty>
          ) : (
            <>
              <ul className="flex flex-col gap-3 md:hidden">
                {rows.map((row) => (
                  <li key={row.id}>
                    <Link to={link(row)} className="flex flex-col gap-1.5 rounded-xl border p-4 outline-none hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-ring">
                      <span className="flex items-start gap-2">
                        <span className="min-w-0 flex-1 font-medium">{row.member.name}</span>
                        {row.restricted && <Badge variant="warning">Restricted</Badge>}
                        <MembershipStatusBadge status={row.status} />
                      </span>
                      <span className="text-muted-foreground text-sm">
                        {row.plan?.name}
                        {periodLabel(row) ? ` · ${periodLabel(row)}` : ''}
                      </span>
                      {Number(row.balance) > 0 && <span className="text-sm tabular-nums">{money(row.balance)} owed</span>}
                    </Link>
                  </li>
                ))}
              </ul>
              <div className="hidden md:block">
                <Table variant="card">
                  <TableHeader>
                    <TableRow>
                      <TableHead>Member</TableHead>
                      <TableHead>Plan</TableHead>
                      <TableHead>Valid</TableHead>
                      <TableHead className="text-right">Owed</TableHead>
                      <TableHead>Status</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {rows.map((row) => (
                      <TableRow key={row.id}>
                        <TableCell>
                          <Link to={link(row)} className="font-medium underline-offset-4 hover:underline">
                            {row.member.name}
                          </Link>
                          {row.restricted && <Badge variant="warning" className="ms-2">Restricted</Badge>}
                          <span className="block text-muted-foreground text-xs">{row.member.email}</span>
                        </TableCell>
                        <TableCell>{row.plan?.name}</TableCell>
                        <TableCell>{periodLabel(row) ?? '—'}</TableCell>
                        <TableCell className="text-right tabular-nums">{Number(row.balance) > 0 ? money(row.balance) : '—'}</TableCell>
                        <TableCell>
                          <MembershipStatusBadge status={row.status} />
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            </>
          )}
        </CardPanel>
        <PageControls meta={meta} label="Members" onPage={setPage} />
      </Card>
    </div>
  )
}
