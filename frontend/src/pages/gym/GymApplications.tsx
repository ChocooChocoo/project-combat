import { useEffect, useState } from 'react'
import { useParams } from 'react-router'
import { HealthSummary } from '@/components/membership/athlete-record-view'
import { MembershipStatusBadge } from '@/components/membership/membership-parts'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
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

function initials(name: string): string {
  return name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()
}

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
    <Button size="sm" variant="outline" onClick={() => review(row)}>
      {row.status === 'applied' && canDecide && row.member.id !== user?.id ? 'Review' : 'View'}
    </Button>
  )

  return (
    <div className="flex min-w-0 flex-col gap-6">
      <header className="flex flex-wrap items-end justify-between gap-4 border-b pb-6">
        <div>
          <h1 className="font-semibold text-3xl tracking-tight sm:text-4xl">Membership applications</h1>
          <p className="mt-3 max-w-prose text-muted-foreground text-sm leading-relaxed">Approve or reject people who applied to join. Approval creates their athlete profile; their membership starts once the fee is paid.</p>
        </div>
        <div className="flex items-center gap-4">
          {canDecide && <Badge variant="outline">Can decide</Badge>}
          {rows && (
            <div>
              <p className="text-muted-foreground text-xs">In view</p>
              <p className="mt-1 font-semibold text-2xl tabular-nums">{rows.length}</p>
            </div>
          )}
        </div>
      </header>

      <section className="min-w-0 overflow-hidden rounded-xl border bg-card" aria-labelledby="applications-list">
        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-b bg-muted/30 px-5 py-4">
          <div>
            <h2 id="applications-list" className="font-medium text-sm">Applications</h2>
            <p className="mt-1 max-w-prose text-muted-foreground text-xs leading-relaxed">{canDecide ? 'You cannot decide your own application.' : 'You can view applications. Deciding needs membership approval access.'}</p>
          </div>
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
        <div className="p-5">
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
                  <li key={row.id} className="flex flex-col gap-3 rounded-xl border p-4">
                    <div className="flex items-start gap-3">
                      <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted font-medium text-xs" aria-hidden>{initials(row.member.name)}</span>
                      <div className="flex min-w-0 flex-1 flex-col">
                        <span className="font-medium wrap-anywhere">{row.member.name}</span>
                        <span className="text-muted-foreground text-sm wrap-anywhere">{row.plan?.name}</span>
                      </div>
                      <MembershipStatusBadge status={row.status} />
                    </div>
                    <p className="text-muted-foreground text-xs">Applied {dateLabel(row.created_at)}</p>
                    <div className="flex justify-end">{reviewButton(row)}</div>
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
                        <TableCell className="max-w-56 whitespace-normal">
                          <div className="flex items-center gap-3">
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted font-medium text-xs" aria-hidden>{initials(row.member.name)}</span>
                            <div className="flex min-w-0 flex-col">
                              <span className="font-medium wrap-anywhere">{row.member.name}</span>
                              <span className="text-muted-foreground text-xs wrap-anywhere">{row.member.email}</span>
                            </div>
                          </div>
                        </TableCell>
                        <TableCell className="max-w-48 whitespace-normal">
                          <p className="wrap-anywhere">{row.plan?.name}</p>
                          <p className="mt-0.5 text-muted-foreground text-xs tabular-nums">{money(row.plan?.total_fee)}</p>
                        </TableCell>
                        <TableCell><span className="whitespace-nowrap">{dateLabel(row.created_at)}</span></TableCell>
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
        </div>
      </section>

      <Dialog open={open !== null} onOpenChange={(next) => !next && setOpen(null)}>
        <DialogPopup className="sm:max-w-2xl">
          {open && (
            <Form errors={errors} onSubmit={decide} className="flex min-h-0 flex-col">
              <DialogHeader>
                <div className="flex items-start gap-3">
                  <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-muted font-medium text-sm" aria-hidden>{initials(open.member.name)}</span>
                  <div className="flex min-w-0 flex-1 flex-col">
                    <DialogTitle className="wrap-anywhere">{open.member.name}</DialogTitle>
                    <DialogDescription className="mt-1">
                      <span className="wrap-anywhere">{open.plan?.name ?? 'No plan listed'}</span> · {money(open.plan?.total_fee)}
                    </DialogDescription>
                    <p className="mt-1 text-muted-foreground text-xs">Applied {dateLabel(open.created_at)}</p>
                  </div>
                  <MembershipStatusBadge status={open.status} />
                </div>
              </DialogHeader>
              <DialogPanel className="flex flex-col gap-5">
                {open.status === 'rejected' && open.decision_reason && (
                  <Alert variant="error">
                    <AlertTitle>Not approved</AlertTitle>
                    <AlertDescription>{open.decision_reason}</AlertDescription>
                  </Alert>
                )}
                <section className="overflow-hidden rounded-xl border">
                  <h3 className="border-b bg-muted/30 px-4 py-2.5 font-medium text-sm">Application details</h3>
                  <dl className="grid gap-5 p-4 text-sm sm:grid-cols-2">
                    <div className="min-w-0">
                      <dt className="text-muted-foreground text-xs">Background</dt>
                      <dd className="mt-1 whitespace-pre-line leading-relaxed wrap-anywhere">{open.background || 'Not given'}</dd>
                    </div>
                    <div className="min-w-0">
                      <dt className="text-muted-foreground text-xs">Current rank</dt>
                      <dd className="mt-1 leading-relaxed wrap-anywhere">{open.current_rank || 'Not given'}</dd>
                    </div>
                    <div className="min-w-0 sm:col-span-2">
                      <dt className="text-muted-foreground text-xs">Training history</dt>
                      <dd className="mt-1 whitespace-pre-line leading-relaxed wrap-anywhere">{open.training_history || 'Not given'}</dd>
                    </div>
                    <div className="min-w-0 border-t pt-4 sm:col-span-2">
                      <dt className="text-muted-foreground text-xs">Waivers</dt>
                      <dd className="mt-1 leading-relaxed wrap-anywhere">
                        Accepted and signed by “{open.signature_name}” on {dateLabel(open.signed_on)}
                      </dd>
                    </div>
                  </dl>
                </section>
                <section className="overflow-hidden rounded-xl border">
                  <h3 className="border-b bg-muted/30 px-4 py-2.5 font-medium text-sm">Health and emergency</h3>
                  <div className="p-4">
                    {open.health ? (
                      <HealthSummary health={open.health} form={form} onOpenFile={() => openFile(open)} />
                    ) : (
                      <p className="text-muted-foreground text-sm leading-relaxed">Health answers and emergency contacts are hidden. They need health details access.</p>
                    )}
                  </div>
                </section>

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
