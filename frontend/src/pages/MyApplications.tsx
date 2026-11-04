import { FilePlus2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import { StatusBadge } from '@/components/application-summary'
import { PageHeader } from '@/components/page-header'
import { Button } from '@/components/ui/button'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { api } from '@/lib/api'
import { type ApplicationStatus, editable } from '@/lib/gyms'

type Row = { id: number; name: string; status: ApplicationStatus; tier: number | null; submitted_at: string | null; created_at: string }

const NEXT: Record<ApplicationStatus, string> = {
  draft: 'Finish and send it',
  submitted: 'Waiting for the administrator',
  under_review: 'Being checked now',
  returned: 'Fix the listed items and send again',
  approved: 'Live on the platform',
  rejected: 'See the reason',
}

/** R-14: every gym application this person has made. One person may run several gyms (Q-09). */
export default function MyApplications() {
  const [rows, setRows] = useState<Row[] | null>(null)

  useEffect(() => {
    api<{ applications: Row[] }>('/gym-applications').then((r) => setRows(r.applications)).catch(() => setRows([]))
  }, [])

  return (
    <div className="flex flex-col gap-8">
      <PageHeader
        title="My gym applications"
        description="Each gym you run needs its own approved application."
        actions={
          rows &&
          rows.length > 0 && (
            <Button render={<Link to="/apply" />}>
              <FilePlus2 aria-hidden /> New application
            </Button>
          )
        }
      />

      {!rows ? (
        <div className="flex flex-col gap-2" aria-busy="true">
          <Skeleton className="h-11" />
          <Skeleton className="h-11" />
        </div>
      ) : rows.length === 0 ? (
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>You have not applied yet</EmptyTitle>
            <EmptyDescription>
              Describe your gym, choose a verification tier and upload its documents. The platform administrator checks them, and the gym goes live once
              approved.
            </EmptyDescription>
          </EmptyHeader>
          <EmptyContent>
            <Button render={<Link to="/apply" />}>
              <FilePlus2 aria-hidden /> Start an application
            </Button>
          </EmptyContent>
        </Empty>
      ) : (
        <div className="overflow-x-auto">
          <Table variant="card">
            <TableHeader>
              <TableRow>
                <TableHead>Gym</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Next</TableHead>
                <TableHead>Tier</TableHead>
                <TableHead>Sent</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((row) => (
                <TableRow key={row.id}>
                  <TableCell>
                    <Link
                      className="font-medium underline-offset-4 hover:underline"
                      to={editable(row.status) ? `/applications/${row.id}/edit` : `/applications/${row.id}`}
                    >
                      {row.name}
                    </Link>
                  </TableCell>
                  <TableCell>
                    <StatusBadge status={row.status} />
                  </TableCell>
                  <TableCell className="text-muted-foreground">{NEXT[row.status]}</TableCell>
                  <TableCell className="tabular-nums">{row.tier ? `Tier ${row.tier}` : <span className="text-muted-foreground">Not chosen</span>}</TableCell>
                  <TableCell className="text-muted-foreground tabular-nums">
                    {row.submitted_at ? new Date(row.submitted_at).toLocaleDateString() : 'Not sent'}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}
    </div>
  )
}
