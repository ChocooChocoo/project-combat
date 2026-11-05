import { useEffect, useState } from 'react'
import { useParams } from 'react-router'
import { HealthSummary } from '@/components/membership/athlete-record-view'
import { MembershipStatusBadge } from '@/components/membership/membership-parts'
import { PageHeader } from '@/components/page-header'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, apiFile, ApiError, formValues, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import { dateLabel, money, type Membership, type MembershipForm } from '@/lib/membership'

type Filter = 'applied' | 'rejected' | 'all'
const FILTERS: { value: Filter; label: string }[] = [
  { value: 'applied', label: 'Waiting for decision' },
  { value: 'rejected', label: 'Not approved' },
  { value: 'all', label: 'All applications' },
]

/** S-21, R-33: membership applications waiting for a decision. */
export default function GymApplications() {
  const { gymId: gymIdParam } = useParams()
  const gymId = Number(gymIdParam)
  const { user } = useAuth()
  const gym = approvedGym(user, gymId)
  const canDecide = gym ? can(gym, 'members.memberships.approve') : false

  const [filter, setFilter] = useState<Filter>('applied')
  const [rows, setRows] = useState<Membership[] | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [form, setForm] = useState<MembershipForm | null>(null)

  const [open, setOpen] = useState<Membership | null>(null)
  const [action, setAction] = useState<'approve' | 'reject' | null>(null)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let live = true
    setRows(null)
    setError('')
    const status = filter === 'all' ? '' : `&status=${filter}`
    api<{ memberships: Membership[] }>(`/gyms/${gymId}/memberships?view=applications${status}`)
      .then((result) => live && setRows(result.memberships))
      .catch((failure) => {
        if (!live) return
        setRows([])
        setError(failure instanceof ApiError ? failure.message : 'Could not load applications.')
      })
    return () => {
      live = false
    }
  }, [gymId, filter, version])

  useEffect(() => {
    api<MembershipForm>('/membership-form').then(setForm).catch(() => undefined)
  }, [])

  if (!gym) return null

  async function review(row: Membership) {
    setAction(null)
    setErrors({})
    setOpen(row)
    try {
      // The full application, with health details only if this person may see them.
      const result = await api<{ membership: Membership }>(`/gyms/${gymId}/memberships/${row.id}`)
      setOpen(result.membership)
    } catch {
      // The list row is enough to decide; health details just stay hidden.
    }
  }

  async function decide(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!open || !action) return
    const reason = formValues(event).reason?.trim() ?? ''
    if (action === 'reject' && !reason) {
      setErrors({ reason: ['Give the applicant a reason.'] })
      return
    }
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/memberships/${open.id}/decision`, { body: { decision: action, reason: reason || null } })
      toastManager.add({ title: action === 'approve' ? 'Application approved. The member can now pay.' : 'Application rejected.', type: 'success' })
      setOpen(null)
      setVersion((v) => v + 1)
    } catch (failure) {
      const fieldErrors = failure instanceof ApiError ? failure.errors : {}
      setErrors(fieldErrors)
      if (Object.keys(fieldErrors).length === 0) {
        toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Could not save the decision.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  async function openFile(row: Membership) {
    try {
      await apiFile(`/gyms/${gymId}/memberships/${row.id}/medical-file`)
    } catch (failure) {
      toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Could not open the file.', type: 'error' })
    }
  }

  const reviewButton = (row: Membership) => (
    <Button size="xs" variant="outline" onClick={() => review(row)}>
      {row.status === 'applied' && canDecide && row.member.id !== user?.id ? 'Review' : 'View'}
    </Button>
  )

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Membership applications"
        description="Approve or reject people who applied to join. Approval creates their athlete profile; their membership starts once the fee is paid."
        meta={canDecide ? <Badge variant="outline">Can decide</Badge> : null}
      />

      <Card>
        <CardHeader>
          <CardTitle render={<h2 />}>Applications</CardTitle>
          <CardDescription>{canDecide ? 'You cannot decide your own application.' : 'You can view applications. Deciding needs membership approval access.'}</CardDescription>
        </CardHeader>
        <CardPanel className="flex flex-col gap-4">
          <div className="flex flex-wrap items-center gap-3">
            {rows === null ? (
              <Skeleton className="mr-auto h-4 w-24" />
            ) : (
              <p className="mr-auto text-muted-foreground text-sm">
                {rows.length} {rows.length === 1 ? 'application' : 'applications'}
              </p>
            )}
            <Select items={FILTERS} value={filter} onValueChange={(next) => setFilter((next as Filter) ?? 'applied')}>
              <SelectTrigger size="sm" className="w-48" aria-label="Filter applications">
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

          {error ? (
            <Alert variant="error">
              <AlertTitle>Applications could not be loaded</AlertTitle>
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
                <EmptyTitle>{filter === 'applied' ? 'No applications waiting' : 'No applications here'}</EmptyTitle>
                <EmptyDescription>People apply from the gym page once a plan is published. New applications also arrive in your notifications.</EmptyDescription>
              </EmptyHeader>
            </Empty>
          ) : (
            <>
              <ul className="flex flex-col gap-3 md:hidden">
                {rows.map((row) => (
                  <li key={row.id} className="flex flex-col gap-2 rounded-xl border p-4">
                    <div className="flex items-start gap-2">
                      <span className="flex min-w-0 flex-1 flex-col">
                        <span className="font-medium">{row.member.name}</span>
                        <span className="text-muted-foreground text-sm">{row.plan?.name}</span>
                      </span>
                      <MembershipStatusBadge status={row.status} />
                    </div>
                    <span className="text-muted-foreground text-sm">Applied {dateLabel(row.created_at)}</span>
                    <div>{reviewButton(row)}</div>
                  </li>
                ))}
              </ul>
              <div className="hidden md:block">
                <Table variant="card">
                  <TableHeader>
                    <TableRow>
                      <TableHead>Applicant</TableHead>
                      <TableHead>Plan</TableHead>
                      <TableHead>Applied</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>
                        <span className="sr-only">Actions</span>
                      </TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {rows.map((row) => (
                      <TableRow key={row.id}>
                        <TableCell>
                          <div className="flex flex-col">
                            <span className="font-medium">{row.member.name}</span>
                            <span className="text-muted-foreground text-xs">{row.member.email}</span>
                          </div>
                        </TableCell>
                        <TableCell>
                          {row.plan?.name}
                          <span className="block text-muted-foreground text-xs tabular-nums">{money(row.plan?.total_fee)}</span>
                        </TableCell>
                        <TableCell>{dateLabel(row.created_at)}</TableCell>
                        <TableCell>
                          <MembershipStatusBadge status={row.status} />
                        </TableCell>
                        <TableCell>
                          <div className="flex justify-end">{reviewButton(row)}</div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            </>
          )}
        </CardPanel>
      </Card>

      <Dialog open={open !== null} onOpenChange={(next) => !next && setOpen(null)}>
        <DialogPopup className="sm:max-w-2xl">
          {open && (
            <Form errors={errors} onSubmit={decide} className="flex min-h-0 flex-col">
              <DialogHeader>
                <DialogTitle>{open.member.name}</DialogTitle>
                <DialogDescription>
                  {open.plan?.name} · {money(open.plan?.total_fee)} · applied {dateLabel(open.created_at)}
                </DialogDescription>
              </DialogHeader>
              <DialogPanel className="flex flex-col gap-4">
                {open.status === 'rejected' && open.decision_reason && (
                  <Alert variant="error">
                    <AlertTitle>Not approved</AlertTitle>
                    <AlertDescription>{open.decision_reason}</AlertDescription>
                  </Alert>
                )}
                <dl className="grid gap-3 rounded-xl border p-3 text-sm sm:grid-cols-2">
                  <div>
                    <dt className="text-muted-foreground">Background</dt>
                    <dd className="whitespace-pre-line">{open.background || 'Not given'}</dd>
                  </div>
                  <div>
                    <dt className="text-muted-foreground">Current rank</dt>
                    <dd>{open.current_rank || 'Not given'}</dd>
                  </div>
                  <div className="sm:col-span-2">
                    <dt className="text-muted-foreground">Training history</dt>
                    <dd className="whitespace-pre-line">{open.training_history || 'Not given'}</dd>
                  </div>
                  <div className="sm:col-span-2">
                    <dt className="text-muted-foreground">Waivers</dt>
                    <dd>
                      Accepted and signed by “{open.signature_name}” on {dateLabel(open.signed_on)}
                    </dd>
                  </div>
                </dl>
                {open.health ? (
                  <section className="rounded-xl border p-3">
                    <HealthSummary health={open.health} form={form} onOpenFile={() => openFile(open)} />
                  </section>
                ) : (
                  <p className="text-muted-foreground text-sm">Health answers and emergency contacts are hidden. They need health details access.</p>
                )}

                {action === 'reject' && (
                  <Field name="reason" className="w-full">
                    <FieldLabel>Reason the applicant will see</FieldLabel>
                    <Textarea name="reason" rows={3} maxLength={2000} required autoFocus placeholder="For example: the beginner class is full until next month." />
                    <FieldError />
                  </Field>
                )}
                {action === 'approve' && (
                  <Alert variant="info">
                    <AlertDescription>
                      Approving adds {open.member.name} as a member and asks them to pay {money(open.plan?.total_fee)}. Taking the payment is a separate step.
                    </AlertDescription>
                  </Alert>
                )}
              </DialogPanel>
              <DialogFooter>
                {open.status === 'applied' && canDecide && open.member.id !== user?.id ? (
                  action === null ? (
                    <>
                      <Button key="reject" type="button" variant="destructive-outline" onClick={() => setAction('reject')}>
                        Reject
                      </Button>
                      <Button key="approve" type="button" onClick={() => setAction('approve')}>
                        Approve
                      </Button>
                    </>
                  ) : (
                    <>
                      <Button key="back" type="button" variant="outline" onClick={() => setAction(null)}>
                        Back
                      </Button>
                      <Button key="confirm" type="submit" variant={action === 'reject' ? 'destructive' : 'default'} loading={busy}>
                        {action === 'approve' ? 'Confirm approval' : 'Reject application'}
                      </Button>
                    </>
                  )
                ) : (
                  <Button type="button" variant="outline" onClick={() => setOpen(null)}>
                    Close
                  </Button>
                )}
              </DialogFooter>
            </Form>
          )}
        </DialogPopup>
      </Dialog>
    </div>
  )
}
