import { ChevronRight } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import { StatusBadge } from '@/components/application-summary'
import { PageHeader } from '@/components/page-header'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsList, TabsTab } from '@/components/ui/tabs'
import { api } from '@/lib/api'
import { type ApplicationStatus } from '@/lib/gyms'

type Stats = {
  gyms_registered: number
  gyms_verified: number
  verified_by_tier: Record<string, number>
  applications_pending: number
  applications_returned: number
  applications_rejected: number
}

type Row = {
  id: number
  name: string
  city: string | null
  status: ApplicationStatus
  tier: number | null
  submitted_at: string | null
  applicant: { first_name: string; last_name: string; email: string } | null
}

type Filter = 'open' | 'returned' | 'approved' | 'rejected'

const EMPTY: Record<Filter, { title: string; text: string }> = {
  open: { title: 'Nothing waiting for review', text: 'New applications appear here as soon as an applicant sends one.' },
  returned: { title: 'Nothing returned', text: 'Applications you send back for correction stay here until the applicant sends them again.' },
  approved: { title: 'No approved gyms yet', text: 'Approved gyms go live on the platform with their tier badge.' },
  rejected: { title: 'No rejected applications', text: 'Rejected applications and their reasons are kept here.' },
}

const waited = (iso: string) => {
  const days = Math.floor((Date.now() - new Date(iso).getTime()) / 86_400_000)
  return days === 0 ? 'Today' : days === 1 ? '1 day' : `${days} days`
}

/** S-01, R-16 and R-19: the review queue first, with platform numbers that never touch a gym's private records. */
export default function AdminHome() {
  const [stats, setStats] = useState<Stats | null>(null)
  const [filter, setFilter] = useState<Filter>('open')
  const [rows, setRows] = useState<Row[] | null>(null)

  useEffect(() => {
    api<Stats>('/admin/stats').then(setStats).catch(() => undefined)
  }, [])

  useEffect(() => {
    api<{ applications: Row[] }>(`/admin/applications${filter === 'open' ? '' : `?status=${filter}`}`)
      .then((r) => setRows(r.applications))
      .catch(() => setRows([]))
  }, [filter])

  const count = (value: number | undefined) => (value === undefined ? '' : ` ${value}`)

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Gym applications"
        description={
          stats ? (
            <span className="tabular-nums">
              {stats.gyms_verified} {stats.gyms_verified === 1 ? 'gym' : 'gyms'} live
              {Object.entries(stats.verified_by_tier).map(([tier, n]) => ` · Tier ${tier}: ${n}`)} · {stats.gyms_registered} applications sent in total
            </span>
          ) : (
            'Check each application against its tier checklist, then decide.'
          )
        }
      />

      <Tabs
        value={filter}
        onValueChange={(value) => {
          setRows(null)
          setFilter(value as Filter)
        }}
      >
        <TabsList variant="underline" aria-label="Show applications" className="max-w-full overflow-x-auto overflow-y-hidden [scrollbar-width:none]">
          <TabsTab value="open">Waiting{count(stats?.applications_pending)}</TabsTab>
          <TabsTab value="returned">Returned{count(stats?.applications_returned)}</TabsTab>
          <TabsTab value="approved">Approved{count(stats?.gyms_verified)}</TabsTab>
          <TabsTab value="rejected">Rejected{count(stats?.applications_rejected)}</TabsTab>
        </TabsList>
      </Tabs>

      <div>
        {!rows ? (
          <div className="flex flex-col gap-2" aria-busy="true">
            <Skeleton className="h-12" />
            <Skeleton className="h-12" />
            <Skeleton className="h-12" />
          </div>
        ) : rows.length === 0 ? (
          <Empty className="rounded-xl border">
            <EmptyHeader>
              <EmptyTitle>{EMPTY[filter].title}</EmptyTitle>
              <EmptyDescription>{EMPTY[filter].text}</EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <div className="overflow-x-auto">
            <Table variant="card">
              <TableHeader>
                <TableRow>
                  <TableHead>Gym</TableHead>
                  <TableHead>Applicant</TableHead>
                  <TableHead>Tier</TableHead>
                  <TableHead>{filter === 'open' ? 'Waiting' : 'Sent'}</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>
                    <span className="sr-only">Open</span>
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((row) => (
                  <TableRow key={row.id}>
                    <TableCell>
                      <Link className="font-medium underline-offset-4 hover:underline" to={`/admin/applications/${row.id}`}>
                        {row.name}
                      </Link>
                      {row.city && <span className="block pt-1 text-muted-foreground text-xs">{row.city}</span>}
                    </TableCell>
                    <TableCell>
                      {row.applicant ? (
                        <>
                          {row.applicant.first_name} {row.applicant.last_name}
                          <span className="block pt-1 text-muted-foreground text-xs">{row.applicant.email}</span>
                        </>
                      ) : (
                        <span className="text-muted-foreground">Unknown</span>
                      )}
                    </TableCell>
                    <TableCell className="tabular-nums">{row.tier ? `Tier ${row.tier}` : <span className="text-muted-foreground">Not chosen</span>}</TableCell>
                    <TableCell className="tabular-nums">
                      {row.submitted_at ? (filter === 'open' ? waited(row.submitted_at) : new Date(row.submitted_at).toLocaleDateString()) : 'Not sent'}
                    </TableCell>
                    <TableCell>
                      <StatusBadge status={row.status} />
                    </TableCell>
                    <TableCell className="text-right">
                      <Link
                        to={`/admin/applications/${row.id}`}
                        aria-label={`${filter === 'open' ? 'Review' : 'Open'} ${row.name}`}
                        className="inline-flex items-center gap-1 text-sm underline-offset-4 hover:underline"
                      >
                        {filter === 'open' ? 'Review' : 'Open'} <ChevronRight className="size-4" aria-hidden />
                      </Link>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
      </div>
    </div>
  )
}
