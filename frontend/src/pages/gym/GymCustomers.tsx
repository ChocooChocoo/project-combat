import { useEffect, useState } from 'react'
import { RangePicker } from '@/components/decision-support/range-picker'
import { NO_RANGE, rangeQuery } from '@/lib/decision-support'
import { Link, useParams } from 'react-router'
import { ChevronLeft, ChevronRight, Plus, Search } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { TextField } from '@/components/text-field'
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import {
  CUSTOMER_SOURCE_LABELS,
  CUSTOMER_STAGE_LABELS,
  followUpStatusLabel,
  followUpStatusVariant,
  linkedMembershipLabel,
  loadStaffChoices,
  stageBadge,
  type Customer,
  type CustomerFollowUp,
  type CustomerSource,
  type StaffChoice,
} from '@/lib/customers'
import { dateTimeLabel, gymDate } from '@/lib/membership'
import { pageMetaFrom, type PageMeta } from '@/lib/pagination'
import { phoneRule } from '@/lib/validation'
import { cn } from '@/lib/utils'

type StageFilter = 'all' | keyof typeof CUSTOMER_STAGE_LABELS

const STAGE_FILTERS: { value: StageFilter; label: string }[] = [
  { value: 'all', label: 'All stages' },
  ...(Object.entries(CUSTOMER_STAGE_LABELS) as [keyof typeof CUSTOMER_STAGE_LABELS, string][]).map(([value, label]) => ({
    value,
    label,
  })),
]

const SOURCE_ITEMS = (Object.entries(CUSTOMER_SOURCE_LABELS) as [CustomerSource, string][]).map(([value, label]) => ({
  value,
  label,
}))

function message(reason: unknown, fallback: string): string {
  return reason instanceof ApiError ? reason.message : fallback
}

function formErrors(reason: unknown, fallback: string, known: string[]): FieldErrors {
  const errors = reason instanceof ApiError ? reason.errors : {}
  const keys = Object.keys(errors)
  if (!(reason instanceof ApiError) || keys.length === 0 || keys.some((key) => !known.includes(key))) {
    toastManager.add({ title: message(reason, fallback), type: 'error' })
  }
  return errors
}

function followUpOrder(a: CustomerFollowUp, b: CustomerFollowUp): number {
  const rank = (status: string) => (status === 'open' ? 0 : status === 'done' ? 1 : 2)
  const byStatus = rank(a.status) - rank(b.status)
  if (byStatus !== 0) return byStatus
  const dueA = gymDate(a.due_at).getTime()
  const dueB = gymDate(b.due_at).getTime()
  return (Number.isNaN(dueA) ? Number.MAX_SAFE_INTEGER : dueA) - (Number.isNaN(dueB) ? Number.MAX_SAFE_INTEGER : dueB)
}

function isOverdue(followUp: CustomerFollowUp): boolean {
  if (followUp.status !== 'open') return false
  const due = gymDate(followUp.due_at).getTime()
  return !Number.isNaN(due) && due < Date.now()
}

function mergeStaff(me: StaffChoice | null, staff: StaffChoice[]): StaffChoice[] {
  const seen = new Set<number>()
  return [...(me ? [me] : []), ...staff].filter((person) => {
    if (seen.has(person.id)) return false
    seen.add(person.id)
    return true
  })
}

/** S-24, R-69 to R-71: leads and customers, plus the follow-ups assigned to the signed-in person. */
export default function GymCustomers() {
  const { gymId: gymIdParam } = useParams()
  const gymId = Number(gymIdParam)
  const { user } = useAuth()
  const gym = approvedGym(user, gymId)
  const canView = gym ? can(gym, 'customers.records.view') : false
  const canManage = gym ? can(gym, 'customers.records.manage') : false

  const [tab, setTab] = useState('customers')
  const [search, setSearch] = useState('')
  const [stage, setStage] = useState<StageFilter>('all')
  const [customers, setCustomers] = useState<Customer[] | null>(null)
  const [customerMeta, setCustomerMeta] = useState<PageMeta | null>(null)
  const [customerPage, setCustomerPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)

  const [followUps, setFollowUps] = useState<CustomerFollowUp[] | null>(null)
  const [followUpMeta, setFollowUpMeta] = useState<PageMeta | null>(null)
  const [followUpPage, setFollowUpPage] = useState(1)
  const [followUpRange, setFollowUpRange] = useState(NO_RANGE)
  const [followUpError, setFollowUpError] = useState('')
  const [staff, setStaff] = useState<StaffChoice[]>([])

  const [addOpen, setAddOpen] = useState(false)
  const [doneTarget, setDoneTarget] = useState<CustomerFollowUp | null>(null)
  const [cancelTarget, setCancelTarget] = useState<CustomerFollowUp | null>(null)
  const [cancelBusy, setCancelBusy] = useState(false)

  useEffect(() => {
    if (!gymId || !canView) return
    let live = true
    setError('')
    setLoading(true)
    const query = new URLSearchParams()
    if (search.trim()) query.set('search', search.trim())
    if (stage !== 'all') query.set('stage', stage)
    query.set('page', String(customerPage))
    const timer = setTimeout(() => {
      api<Record<string, unknown>>(`/gyms/${gymId}/customers?${query.toString()}`)
        .then((result) => {
          if (!live) return
          const rows = Array.isArray(result.customers) ? (result.customers as Customer[]) : []
          setCustomers(rows)
          setCustomerMeta(pageMetaFrom(result, rows.length))
        })
        .catch((reason) => {
          if (live) {
            setCustomers([])
            setCustomerMeta(null)
            setError(message(reason, 'Customers could not be loaded.'))
          }
        })
        .finally(() => {
          if (live) setLoading(false)
        })
    }, search.trim() ? 250 : 0)
    return () => {
      live = false
      clearTimeout(timer)
    }
  }, [gymId, canView, search, stage, customerPage, version])

  const viewerId = user?.id
  useEffect(() => {
    if (!gymId || !canView || !viewerId) return
    let live = true
    setFollowUpError('')
    api<Record<string, unknown>>(`/gyms/${gymId}/follow-ups?assigned_to=${viewerId}&page=${followUpPage}${rangeQuery(followUpRange)}`)
      .then((result) => {
        if (!live) return
        const rows = Array.isArray(result.followUps) ? (result.followUps as CustomerFollowUp[]) : []
        setFollowUps(rows)
        setFollowUpMeta(pageMetaFrom(result, rows.length))
      })
      .catch((reason) => {
        if (live) {
          setFollowUps([])
          setFollowUpMeta(null)
          setFollowUpError(message(reason, 'Follow-ups could not be loaded.'))
        }
      })
    return () => {
      live = false
    }
  }, [gymId, canView, viewerId, followUpPage, version, followUpRange])

  useEffect(() => {
    if (!gymId || !canManage) return
    let live = true
    loadStaffChoices(gymId)
      .then((people) => {
        if (live) setStaff(people)
      })
      .catch(() => {
        if (live) setStaff([])
      })
    return () => {
      live = false
    }
  }, [gymId, canManage])

  if (!gym) return null

  if (!canView) {
    return (
      <div className="flex flex-col gap-6">
        <PageHeader title="Customers" />
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>Customers are not available</EmptyTitle>
            <EmptyDescription>Ask the owner for the customer records permission.</EmptyDescription>
          </EmptyHeader>
        </Empty>
      </div>
    )
  }

  const me =
    user && canManage
      ? ({ id: user.id, name: `${user.first_name} ${user.last_name}`.trim() } satisfies StaffChoice)
      : null
  const assignees = mergeStaff(me, staff)
  const myFollowUps = (followUps ?? [])
    .filter((followUp) => user && String(followUp.assigned_to) === String(user.id))
    .sort(followUpOrder)
  const openMine = myFollowUps.filter((followUp) => followUp.status === 'open').length
  const filtered = search.trim() !== '' || stage !== 'all'
  const canOpenMembership = can(gym, 'members.memberships.view')

  function refresh() {
    setVersion((current) => current + 1)
  }

  function customerName(followUp: CustomerFollowUp): string {
    const sameCustomer = (customer: Customer) => String(customer.id) === String(followUp.customer_id)
    return followUp.customer_name ?? customers?.find(sameCustomer)?.name ?? `Customer #${followUp.customer_id}`
  }

  function clearFilters() {
    setSearch('')
    setStage('all')
    setCustomerPage(1)
  }

  function openDone(followUp: CustomerFollowUp) {
    if (canManage) setDoneTarget(followUp)
  }

  async function cancelFollowUp() {
    if (!canManage || !cancelTarget) return
    setCancelBusy(true)
    try {
      await api(`/gyms/${gymId}/follow-ups/${cancelTarget.id}`, { method: 'PUT', body: { status: 'cancelled' } })
      toastManager.add({ title: 'Follow-up cancelled.', type: 'success' })
      setCancelTarget(null)
      refresh()
    } catch (reason) {
      toastManager.add({ title: message(reason, 'The follow-up could not be cancelled.'), type: 'error' })
    } finally {
      setCancelBusy(false)
    }
  }

  const addButton = canManage ? (
    <Button size="sm" onClick={() => setAddOpen(true)}>
      <Plus aria-hidden />
      Add customer
    </Button>
  ) : undefined

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Customers"
        description={`${gym.name}: leads, customers, and what happened with each one.`}
        actions={addButton}
      />

      <Tabs value={tab} onValueChange={(value) => setTab(String(value))}>
        <TabsList
          variant="underline"
          aria-label="Customer sections"
          className="max-w-full overflow-x-auto overflow-y-hidden [scrollbar-width:none]"
        >
          <TabsTab value="customers">Customers</TabsTab>
          <TabsTab value="followups">
            My follow-ups
            {openMine > 0 && (
              <Badge variant="warning" aria-label={`${openMine} open follow-ups assigned to you`}>
                {openMine}
              </Badge>
            )}
          </TabsTab>
        </TabsList>

        <TabsPanel value="customers" className="flex flex-col gap-4 pt-4">
          <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem]">
            <Field name="search" className="min-w-0">
              <FieldLabel className="sr-only">Search customers</FieldLabel>
              <InputGroup>
                <InputGroupAddon>
                  <Search aria-hidden />
                </InputGroupAddon>
                <InputGroupInput
                  type="search"
                  name="search"
                  value={search}
                  onChange={(event) => {
                    setSearch(event.target.value)
                    setCustomerPage(1)
                  }}
                  maxLength={150}
                  placeholder="Search name, email or phone"
                  aria-label="Search customers"
                />
              </InputGroup>
            </Field>
            <Field name="stage">
              <FieldLabel className="sr-only">Stage</FieldLabel>
              <Select
                items={STAGE_FILTERS}
                value={stage}
                onValueChange={(next) => {
                  setStage((next as StageFilter) ?? 'all')
                  setCustomerPage(1)
                }}
              >
                <SelectTrigger aria-label="Filter customers by stage">
                  <SelectValue />
                </SelectTrigger>
                <SelectPopup>
                  {STAGE_FILTERS.map((item) => (
                    <SelectItem key={item.value} value={item.value}>
                      {item.label}
                    </SelectItem>
                  ))}
                </SelectPopup>
              </Select>
            </Field>
          </div>

          {error ? (
            <Alert variant="error">
              <AlertTitle>Customers could not be loaded</AlertTitle>
              <AlertDescription>{error}</AlertDescription>
              <AlertAction>
                <Button size="xs" variant="outline" onClick={refresh}>
                  Try again
                </Button>
              </AlertAction>
            </Alert>
          ) : customers === null ? (
            <div className="flex flex-col gap-2" aria-busy="true" aria-label="Loading customers">
              <Skeleton className="h-12" />
              <Skeleton className="h-12" />
              <Skeleton className="h-12" />
            </div>
          ) : (
            <>
              <p className="text-muted-foreground text-sm" aria-live="polite">
                {customerMeta?.total ?? customers.length}{' '}
                {(customerMeta?.total ?? customers.length) === 1 ? 'customer' : 'customers'}
                {loading ? ' · updating' : ''}
              </p>
              {customers.length === 0 ? (
                <Empty className="rounded-xl border">
                  <EmptyHeader>
                    <EmptyTitle>{filtered ? 'No customers match these filters' : 'No customers yet'}</EmptyTitle>
                    <EmptyDescription>
                      {filtered
                        ? 'Change the search or stage to see other people.'
                        : 'Add a walk-in, a referral or someone who asked about joining. A lead becomes a member when their membership is linked.'}
                    </EmptyDescription>
                  </EmptyHeader>
                  <EmptyContent>
                    {filtered ? (
                      <Button variant="outline" onClick={clearFilters}>
                        Clear filters
                      </Button>
                    ) : (
                      addButton ?? (
                        <span className="text-muted-foreground text-sm">Someone with customer records access can add the first one.</span>
                      )
                    )}
                  </EmptyContent>
                </Empty>
              ) : (
                <>
                  <ul className="flex flex-col gap-3 md:hidden">
                    {customers.map((customer) => {
                      const badge = stageBadge(customer.stage, customer.status)
                      const membership = linkedMembershipLabel(customer)
                      return (
                        <li key={customer.id}>
                          <Link
                            to={`/gyms/${gymId}/manage/customers/${customer.id}`}
                            className="flex flex-col gap-2 rounded-xl border bg-card p-4 outline-none hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-ring"
                          >
                            <span className="flex items-start justify-between gap-3">
                              <span className="min-w-0 break-words font-medium">{customer.name}</span>
                              <Badge variant={badge.variant}>{badge.label}</Badge>
                            </span>
                            <span className="break-words text-muted-foreground text-sm">
                              {[customer.email, customer.phone].filter(Boolean).join(' · ') || 'No contact details'}
                            </span>
                            <span className="text-muted-foreground text-sm">
                              Assignee: {customer.assigned_to_name ?? 'Unassigned'}
                            </span>
                            <span className="text-muted-foreground text-sm">{membership ?? 'No linked membership'}</span>
                          </Link>
                        </li>
                      )
                    })}
                  </ul>

                  <div className="hidden md:block">
                    <Table variant="card" className="min-w-[48rem]">
                      <TableHeader>
                        <TableRow>
                          <TableHead>Name</TableHead>
                          <TableHead>Contact</TableHead>
                          <TableHead>Stage</TableHead>
                          <TableHead>Assignee</TableHead>
                          <TableHead>Linked membership</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {customers.map((customer) => {
                          const badge = stageBadge(customer.stage, customer.status)
                          return (
                            <TableRow key={customer.id}>
                              <TableCell>
                                <Link
                                  to={`/gyms/${gymId}/manage/customers/${customer.id}`}
                                  className="font-medium underline-offset-4 hover:underline"
                                >
                                  {customer.name}
                                </Link>
                              </TableCell>
                              <TableCell className="max-w-64 whitespace-normal">
                                <span className="block break-words text-sm">{customer.email ?? 'No email'}</span>
                                <span className="block break-words text-muted-foreground text-xs">
                                  {customer.phone ?? 'No phone'}
                                </span>
                              </TableCell>
                              <TableCell>
                                <Badge variant={badge.variant}>{badge.label}</Badge>
                              </TableCell>
                              <TableCell className="max-w-40 whitespace-normal break-words">
                                {customer.assigned_to_name ?? <span className="text-muted-foreground">Unassigned</span>}
                              </TableCell>
                              <TableCell className="max-w-56 whitespace-normal">
                                {customer.membership_id ? (
                                  canOpenMembership ? (
                                    <Link
                                      to={`/gyms/${gymId}/manage/members/${customer.membership_id}`}
                                      className="break-words underline-offset-4 hover:underline"
                                    >
                                      {linkedMembershipLabel(customer)}
                                    </Link>
                                  ) : (
                                    <span className="break-words">{linkedMembershipLabel(customer)}</span>
                                  )
                                ) : (
                                  <span className="text-muted-foreground">Not linked</span>
                                )}
                              </TableCell>
                            </TableRow>
                          )
                        })}
                      </TableBody>
                    </Table>
                  </div>

                  <PageControls meta={customerMeta} label="Customers" onPage={setCustomerPage} />
                </>
              )}
            </>
          )}
        </TabsPanel>

        <TabsPanel value="followups" className="flex flex-col gap-4 pt-4">
          <RangePicker optional name="follow-ups" value={followUpRange} onChange={(next) => { setFollowUpRange(next); setFollowUpPage(1) }} />
          {followUpError ? (
            <Alert variant="error">
              <AlertTitle>Follow-ups could not be loaded</AlertTitle>
              <AlertDescription>{followUpError}</AlertDescription>
              <AlertAction>
                <Button size="xs" variant="outline" onClick={refresh}>
                  Try again
                </Button>
              </AlertAction>
            </Alert>
          ) : followUps === null ? (
            <div className="flex flex-col gap-2" aria-busy="true" aria-label="Loading follow-ups">
              <Skeleton className="h-16" />
              <Skeleton className="h-16" />
              <Skeleton className="h-16" />
            </div>
          ) : myFollowUps.length === 0 ? (
            <Empty className="rounded-xl border">
              <EmptyHeader>
                <EmptyTitle>No follow-ups assigned to you</EmptyTitle>
                <EmptyDescription>
                  Open a customer record to add one. New follow-ups appear here with their due date and note.
                </EmptyDescription>
              </EmptyHeader>
            </Empty>
          ) : (
            <>
              <p className="text-muted-foreground text-sm" aria-live="polite">
                {myFollowUps.length} {myFollowUps.length === 1 ? 'follow-up' : 'follow-ups'} · {openMine} open
              </p>

              <ul className="flex flex-col gap-3 md:hidden">
                {myFollowUps.map((followUp) => (
                  <li key={followUp.id} className="flex flex-col gap-3 rounded-xl border bg-card p-4">
                    <div className="flex items-start justify-between gap-3">
                      <div className="flex min-w-0 flex-col gap-0.5">
                        <Link
                          to={`/gyms/${gymId}/manage/customers/${followUp.customer_id}`}
                          className="break-words font-medium underline-offset-4 hover:underline"
                        >
                          {customerName(followUp)}
                        </Link>
                        <span className="flex flex-wrap items-center gap-2 text-sm">
                          <time
                            className={cn(isOverdue(followUp) && 'font-medium text-destructive-foreground')}
                            dateTime={followUp.due_at}
                          >
                            Due {dateTimeLabel(followUp.due_at)}
                          </time>
                          {isOverdue(followUp) && <Badge variant="error">Overdue</Badge>}
                        </span>
                      </div>
                      <Badge variant={followUpStatusVariant(followUp.status)}>
                        {followUpStatusLabel(followUp.status)}
                      </Badge>
                    </div>
                    <p className="whitespace-pre-line break-words text-sm">{followUp.note}</p>
                    {followUp.outcome && (
                      <p className="break-words text-muted-foreground text-sm">Outcome: {followUp.outcome}</p>
                    )}
                    {followUp.status !== 'open' && (
                      <p className="text-muted-foreground text-xs">
                        {followUp.status === 'done'
                          ? `Marked done${followUp.done_by_name ? ` by ${followUp.done_by_name}` : ''}${
                              followUp.done_at ? ` on ${dateTimeLabel(followUp.done_at)}` : ''
                            }.`
                          : 'Cancelled.'}
                      </p>
                    )}
                    {canManage && followUp.status === 'open' && (
                      <div className="flex flex-wrap gap-2">
                        <Button size="sm" onClick={() => openDone(followUp)}>
                          Mark done
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => setCancelTarget(followUp)}>
                          Cancel
                        </Button>
                      </div>
                    )}
                  </li>
                ))}
              </ul>

              <div className="hidden md:block">
                <Table variant="card" className="min-w-[52rem]">
                  <TableHeader>
                    <TableRow>
                      <TableHead>Due</TableHead>
                      <TableHead>Customer</TableHead>
                      <TableHead>Note</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>
                        <span className="sr-only">Actions</span>
                      </TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {myFollowUps.map((followUp) => (
                      <TableRow key={followUp.id}>
                        <TableCell>
                          <div className="flex flex-col gap-1">
                            <time
                              className={cn('tabular-nums', isOverdue(followUp) && 'font-medium text-destructive-foreground')}
                              dateTime={followUp.due_at}
                            >
                              {dateTimeLabel(followUp.due_at)}
                            </time>
                            {isOverdue(followUp) && <Badge variant="error">Overdue</Badge>}
                          </div>
                        </TableCell>
                        <TableCell>
                          <Link
                            to={`/gyms/${gymId}/manage/customers/${followUp.customer_id}`}
                            className="font-medium underline-offset-4 hover:underline"
                          >
                            {customerName(followUp)}
                          </Link>
                        </TableCell>
                        <TableCell className="max-w-72 whitespace-normal">
                          <span className="line-clamp-3 break-words">{followUp.note}</span>
                        </TableCell>
                        <TableCell className="max-w-56 whitespace-normal">
                          <div className="flex flex-col items-start gap-1">
                            <Badge variant={followUpStatusVariant(followUp.status)}>
                              {followUpStatusLabel(followUp.status)}
                            </Badge>
                            {followUp.outcome && (
                              <span className="line-clamp-2 text-muted-foreground text-xs">{followUp.outcome}</span>
                            )}
                            {followUp.status === 'done' && (followUp.done_by_name || followUp.done_at) && (
                              <span className="text-muted-foreground text-xs">
                                {followUp.done_by_name ? `${followUp.done_by_name}` : 'Done'}
                                {followUp.done_at ? ` · ${dateTimeLabel(followUp.done_at)}` : ''}
                              </span>
                            )}
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex justify-end gap-1.5">
                            {canManage && followUp.status === 'open' && (
                              <>
                                <Button size="xs" onClick={() => openDone(followUp)}>
                                  Mark done
                                </Button>
                                <Button size="xs" variant="outline" onClick={() => setCancelTarget(followUp)}>
                                  Cancel
                                </Button>
                              </>
                            )}
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>

              <PageControls meta={followUpMeta} label="Follow-ups" onPage={setFollowUpPage} />
            </>
          )}
        </TabsPanel>
      </Tabs>

      {canManage && addOpen && (
        <AddCustomerDialog
          gymId={gymId}
          assignees={assignees}
          onClose={() => setAddOpen(false)}
          onSaved={() => {
            setAddOpen(false)
            refresh()
          }}
        />
      )}

      {canManage && doneTarget && (
        <DoneFollowUpDialog
          gymId={gymId}
          followUp={doneTarget}
          customerName={customerName(doneTarget)}
          canManage={canManage}
          onClose={() => setDoneTarget(null)}
          onSaved={() => {
            setDoneTarget(null)
            refresh()
          }}
        />
      )}

      <AlertDialog open={cancelTarget !== null} onOpenChange={(open) => !open && !cancelBusy && setCancelTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Cancel this follow-up?</AlertDialogTitle>
            <AlertDialogDescription>
              {cancelTarget ? `${customerName(cancelTarget)} · ${cancelTarget.note}` : ''} It stays in the history as
              cancelled and leaves your open list.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <Button variant="outline" disabled={cancelBusy} onClick={() => setCancelTarget(null)}>
              Keep it open
            </Button>
            <Button variant="destructive" loading={cancelBusy} onClick={cancelFollowUp}>
              Cancel follow-up
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}

function PageControls({
  meta,
  label,
  onPage,
}: {
  meta: PageMeta | null
  label: string
  onPage: (page: number) => void
}) {
  if (!meta || meta.last_page <= 1) return null
  return (
    <nav aria-label={`${label} pages`} className="flex flex-wrap items-center justify-between gap-3">
      <p className="text-muted-foreground text-sm" aria-live="polite">
        Page {meta.current_page} of {meta.last_page} · {meta.total} {meta.total === 1 ? 'entry' : 'entries'}
      </p>
      <div className="flex gap-2">
        <Button size="sm" variant="outline" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>
          <ChevronLeft aria-hidden />
          Previous
        </Button>
        <Button
          size="sm"
          variant="outline"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPage(meta.current_page + 1)}
        >
          Next
          <ChevronRight aria-hidden />
        </Button>
      </div>
    </nav>
  )
}

function AddCustomerDialog({
  gymId,
  assignees,
  onClose,
  onSaved,
}: {
  gymId: number
  assignees: StaffChoice[]
  onClose: () => void
  onSaved: () => void
}) {
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [phone, setPhone] = useState('')
  const [source, setSource] = useState<CustomerSource>('walk_in')
  const [inquiry, setInquiry] = useState('')
  const [assignedTo, setAssignedTo] = useState('none')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setErrors({})
    setBusy(true)
    try {
      const result = await api<{ customer: Customer }>(`/gyms/${gymId}/customers`, {
        body: {
          name: name.trim(),
          email: email.trim() || null,
          phone: phone.trim() || null,
          source,
          inquiry: inquiry.trim() || null,
          assigned_to: assignedTo === 'none' ? null : Number(assignedTo),
        },
      })
      toastManager.add({
        title: result.customer.membership_id
          ? 'Customer added and linked to their application.'
          : 'Customer added.',
        type: 'success',
      })
      onSaved()
    } catch (reason) {
      setErrors(formErrors(reason, 'The customer could not be added.', ['name', 'email', 'phone', 'source', 'inquiry', 'assigned_to']))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Add customer</DialogTitle>
            <DialogDescription>
              A walk-in, referral or someone who asked online. A matching email links their waiting application
              automatically; otherwise link it from the customer record.
            </DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            <TextField
              name="name"
              label="Name"
              value={name}
              onChange={(event) => setName(event.target.value)}
              maxLength={120}
              autoComplete="name"
              required
              autoFocus
            />
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField
                name="email"
                label="Email (optional)"
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                maxLength={150}
                autoComplete="email"
              />
              <TextField
                name="phone"
                label="Phone (optional)"
                type="tel"
                value={phone}
                onChange={(event) => setPhone(event.target.value)}
                maxLength={40}
                autoComplete="tel"
                validate={phoneRule}
                placeholder="0917 123 4567"
              />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field name="source">
                <FieldLabel>Where they came from</FieldLabel>
                <Select
                  items={SOURCE_ITEMS}
                  value={source}
                  onValueChange={(next) => setSource((next as CustomerSource) ?? 'walk_in')}
                >
                  <SelectTrigger aria-label="Where they came from">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectPopup>
                    {SOURCE_ITEMS.map((item) => (
                      <SelectItem key={item.value} value={item.value}>
                        {item.label}
                      </SelectItem>
                    ))}
                  </SelectPopup>
                </Select>
                <FieldError />
              </Field>
              <Field name="assigned_to">
                <FieldLabel>Follow-up owner</FieldLabel>
                <Select
                  items={[
                    { value: 'none', label: 'No one yet' },
                    ...assignees.map((person) => ({ value: String(person.id), label: person.name })),
                  ]}
                  value={assignedTo}
                  onValueChange={(next) => setAssignedTo(next ?? 'none')}
                >
                  <SelectTrigger aria-label="Follow-up owner">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectPopup className="w-(--anchor-width) max-w-[calc(100vw-2rem)]">
                    <SelectItem value="none">No one yet</SelectItem>
                    {assignees.map((person) => (
                      <SelectItem key={person.id} value={String(person.id)}>
                        {person.name}
                      </SelectItem>
                    ))}
                  </SelectPopup>
                </Select>
                <FieldDescription>
                  {assignees.length > 1
                    ? 'This person is notified when a follow-up is assigned.'
                    : 'Only you are listed here. Staff choices come from the staff attendance list.'}
                </FieldDescription>
                <FieldError />
              </Field>
            </div>
            <Field name="inquiry">
              <FieldLabel>
                What they asked about <span className="font-normal text-muted-foreground">(optional)</span>
              </FieldLabel>
              <Textarea
                name="inquiry"
                rows={3}
                maxLength={5000}
                value={inquiry}
                onChange={(event) => setInquiry(event.target.value)}
                placeholder="For example: evening BJJ classes for a beginner."
              />
              <FieldError />
            </Field>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={!name.trim()}>
              Add customer
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function DoneFollowUpDialog({
  gymId,
  followUp,
  customerName,
  canManage,
  onClose,
  onSaved,
}: {
  gymId: number
  followUp: CustomerFollowUp
  customerName: string
  canManage: boolean
  onClose: () => void
  onSaved: () => void
}) {
  const [outcome, setOutcome] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!canManage) return
    setErrors({})
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/follow-ups/${followUp.id}`, {
        method: 'PUT',
        body: { status: 'done', outcome: outcome.trim() },
      })
      toastManager.add({ title: 'Follow-up marked done.', type: 'success' })
      onSaved()
    } catch (reason) {
      setErrors(formErrors(reason, 'The follow-up could not be updated.', ['outcome']))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Mark follow-up done</DialogTitle>
            <DialogDescription>
              {customerName}. The outcome is kept with your name and the time.
            </DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            <div className="rounded-xl border bg-muted/40 p-3 text-sm">
              <p className="font-medium">Due {dateTimeLabel(followUp.due_at)}</p>
              <p className="mt-1 whitespace-pre-line break-words text-muted-foreground">{followUp.note}</p>
            </div>
            <Field name="outcome">
              <FieldLabel>What happened?</FieldLabel>
              <Textarea
                name="outcome"
                rows={3}
                maxLength={5000}
                value={outcome}
                onChange={(event) => setOutcome(event.target.value)}
                required
                autoFocus
                placeholder="For example: booked a trial class for Thursday."
              />
              <FieldDescription>The outcome stays in this customer's follow-up history.</FieldDescription>
              <FieldError />
            </Field>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={!outcome.trim()}>
              Mark done
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}
