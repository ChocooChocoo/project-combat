import { CalendarPlus, Paperclip, Plus, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { RangePicker } from '@/components/decision-support/range-picker'
import { NO_RANGE, rangeQuery } from '@/lib/decision-support'
import { useParams } from 'react-router'
import { LeaveCalendar, formatDateRange, formatDay, isoDay } from '@/components/leave-calendar'
import { PageControls } from '@/components/page-controls'
import { PageHeader } from '@/components/page-header'
import { pageMetaFrom, type PageMeta } from '@/lib/pagination'
import { TextField } from '@/components/text-field'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Meter, MeterIndicator, MeterTrack } from '@/components/ui/meter'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, apiFile, ApiError, formValues, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import type { GymOptions, LeaveRequestStatus } from '@/lib/workspace'

type RequestRow = {
  id: number
  user_id: number
  user_name: string | null
  user: { id: number; first_name: string; last_name: string; photo_url: string | null } | null
  type: string
  type_label?: string
  starts_on: string
  ends_on: string
  working_days: number
  reason: string | null
  status: LeaveRequestStatus
  decided_by_name: string | null
  decided_at: string | null
  decision_note: string | null
  attachment_name: string | null
  attachment_mime: string | null
  attachment_size: number | null
  over_balance: boolean
}

type BalanceType = {
  type: string
  label: string
  allowance: number
  used: number
  remaining: number
  paid: boolean
}

type EmployeeBalance = {
  user_id: number
  user_name: string
  year: number
  types: BalanceType[]
}

type HolidayRow = {
  id?: number
  date: string
  name: string
  national: boolean
}

type StatusFilter = 'all' | LeaveRequestStatus

const CURRENT_YEAR = new Date().getFullYear()
const TODAY_ISO = isoDay(new Date())
const YEARS = Array.from({ length: 5 }, (_, index) => CURRENT_YEAR - 2 + index)
const STATUS_ITEMS: { value: StatusFilter; label: string }[] = [
  { value: 'all', label: 'All statuses' },
  { value: 'pending', label: 'Pending' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'cancelled', label: 'Cancelled' },
]
const STATUS_LABELS: Record<LeaveRequestStatus, string> = {
  pending: 'Pending',
  approved: 'Approved',
  rejected: 'Rejected',
  cancelled: 'Cancelled',
}
const STATUS_VARIANTS: Record<LeaveRequestStatus, 'warning' | 'success' | 'error' | 'outline'> = {
  pending: 'warning',
  approved: 'success',
  rejected: 'error',
  cancelled: 'outline',
}

function StatusBadge({ status }: { status: LeaveRequestStatus }) {
  return <Badge variant={STATUS_VARIANTS[status] ?? 'outline'}>{STATUS_LABELS[status] ?? status}</Badge>
}

function typeLabel(type: string, options: GymOptions | null, rowLabel?: string): string {
  return rowLabel ?? options?.leave_types?.[type]?.label ?? type.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function employeeName(request: RequestRow): string {
  return request.user_name ?? (request.user ? `${request.user.first_name} ${request.user.last_name}` : 'Unknown employee')
}

function fileSize(bytes: number | null): string {
  if (!bytes) return ''
  return bytes >= 1024 * 1024 ? `${(bytes / (1024 * 1024)).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`
}

function YearSelect({ value, onChange }: { value: number; onChange: (year: number) => void }) {
  return (
    <Select
      items={YEARS.map((year) => ({ value: String(year), label: String(year) }))}
      value={String(value)}
      onValueChange={(next) => {
        if (next) onChange(Number(next))
      }}
    >
      <SelectTrigger size="sm" className="w-24" aria-label="Year">
        <SelectValue />
      </SelectTrigger>
      <SelectPopup>
        {YEARS.map((year) => (
          <SelectItem key={year} value={String(year)}>
            {year}
          </SelectItem>
        ))}
      </SelectPopup>
    </Select>
  )
}

/** S-27, R-25 to R-27: decide leave, keep the holiday calendar, and review balances. */
export default function GymLeave() {
  const { gymId: gymIdParam } = useParams()
  const gymId = Number(gymIdParam)
  const { user } = useAuth()
  const gym = approvedGym(user, gymId)
  const canDecide = gym ? can(gym, 'hr.leave.manage') : false
  const canManageHolidays = gym ? can(gym, 'hr.holidays.manage') : false

  const [tab, setTab] = useState('requests')
  const [options, setOptions] = useState<GymOptions | null>(null)
  const [filter, setFilter] = useState<StatusFilter>('all')
  const [range, setRange] = useState(NO_RANGE)
  const [requests, setRequests] = useState<RequestRow[] | null>(null)
  const [requestsError, setRequestsError] = useState('')
  const [approved, setApproved] = useState<RequestRow[] | null>(null)
  const [approvedError, setApprovedError] = useState('')
  const [balances, setBalances] = useState<EmployeeBalance[] | null>(null)
  const [balancesError, setBalancesError] = useState('')
  const [holidays, setHolidays] = useState<HolidayRow[] | null>(null)
  const [holidaysError, setHolidaysError] = useState('')
  const [year, setYear] = useState(CURRENT_YEAR)
  const [month, setMonth] = useState(() => new Date(CURRENT_YEAR, new Date().getMonth(), 1))
  const [version, setVersion] = useState(0)
  const [page, setPage] = useState(1) // (R-123)
  const [meta, setMeta] = useState<PageMeta | null>(null)

  const [decision, setDecision] = useState<{ request: RequestRow; action: 'approve' | 'reject' } | null>(null)
  const [decisionErrors, setDecisionErrors] = useState<FieldErrors>({})
  const [holidayOpen, setHolidayOpen] = useState(false)
  const [holidayErrors, setHolidayErrors] = useState<FieldErrors>({})
  const [removing, setRemoving] = useState<HolidayRow | null>(null)
  const [busy, setBusy] = useState('')

  useEffect(() => {
    let live = true
    api<{ options: GymOptions }>(`/gyms/${gymId}/options`)
      .then((result) => live && setOptions(result.options))
      .catch(() => undefined)
    return () => {
      live = false
    }
  }, [gymId])

  useEffect(() => {
    let live = true
    setRequests(null)
    setRequestsError('')
    const query = `?page=${page}${filter === 'all' ? '' : `&status=${filter}`}${rangeQuery(range)}`
    api<{ requests: RequestRow[] }>(`/gyms/${gymId}/leave-requests${query}`)
      .then((result) => {
        if (!live) return
        const rows = result.requests ?? []
        // A decision can empty the last page; step back one.
        if (rows.length === 0 && page > 1) {
          setPage(page - 1)
          return
        }
        setRequests(rows)
        setMeta(pageMetaFrom(result, rows.length))
      })
      .catch((error) => {
        if (!live) return
        setRequests([])
        setRequestsError(error instanceof ApiError ? error.message : 'Could not load leave requests.')
      })
    return () => {
      live = false
    }
  }, [gymId, filter, version, range, page])

  useEffect(() => {
    let live = true
    setApproved(null)
    setApprovedError('')
    api<{ requests: RequestRow[] }>(`/gyms/${gymId}/leave-requests?status=approved`)
      .then((result) => {
        if (live) setApproved(result.requests ?? [])
      })
      .catch((error) => {
        if (!live) return
        setApproved([])
        setApprovedError(error instanceof ApiError ? error.message : 'Could not load approved leave.')
      })
    return () => {
      live = false
    }
  }, [gymId, version])

  useEffect(() => {
    let live = true
    setBalances(null)
    setBalancesError('')
    api<{ balances: EmployeeBalance[] }>(`/gyms/${gymId}/leave-balances?year=${year}`)
      .then((result) => {
        if (live) setBalances(result.balances ?? [])
      })
      .catch((error) => {
        if (!live) return
        setBalances([])
        setBalancesError(error instanceof ApiError ? error.message : 'Could not load leave balances.')
      })
    return () => {
      live = false
    }
  }, [gymId, year, version])

  useEffect(() => {
    let live = true
    setHolidays(null)
    setHolidaysError('')
    api<{ holidays: HolidayRow[] }>(`/gyms/${gymId}/holidays?year=${year}`)
      .then((result) => {
        if (live) setHolidays(result.holidays ?? [])
      })
      .catch((error) => {
        if (!live) return
        setHolidays([])
        setHolidaysError(error instanceof ApiError ? error.message : 'Could not load holidays.')
      })
    return () => {
      live = false
    }
  }, [gymId, year, version])

  if (!gym) return null

  const refresh = () => setVersion((current) => current + 1)

  function chooseYear(next: number) {
    setYear(next)
    setMonth((current) => new Date(next, current.getMonth(), 1))
  }

  function changeMonth(next: Date) {
    setMonth(next)
    if (next.getFullYear() !== year) setYear(next.getFullYear())
  }

  async function openAttachment(request: RequestRow) {
    try {
      await apiFile(`/gyms/${gymId}/leave-requests/${request.id}/attachment`)
    } catch (error) {
      toastManager.add({ title: error instanceof ApiError ? error.message : 'Could not open the attachment.', type: 'error' })
    }
  }

  function openDecision(request: RequestRow, action: 'approve' | 'reject') {
    setDecisionErrors({})
    setDecision({ request, action })
  }

  async function decide(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!decision) return
    const note = formValues(event).note ?? ''
    setBusy('decision')
    try {
      await api(`/gyms/${gymId}/leave-requests/${decision.request.id}/decision`, {
        body: { decision: decision.action, note: note || null },
      })
      toastManager.add({ title: decision.action === 'approve' ? 'Leave approved.' : 'Leave rejected.', type: 'success' })
      setDecision(null)
      refresh()
    } catch (error) {
      const errors = error instanceof ApiError ? error.errors : {}
      setDecisionErrors(errors)
      if (!(error instanceof ApiError) || Object.keys(errors).length === 0) {
        toastManager.add({ title: error instanceof ApiError ? error.message : 'Could not save the decision.', type: 'error' })
      }
    } finally {
      setBusy('')
    }
  }

  async function addHoliday(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = event.currentTarget
    const values = formValues(event)
    setBusy('holiday')
    try {
      const result = await api<{ holiday: HolidayRow }>(`/gyms/${gymId}/holidays`, {
        body: { date: values.date, name: values.name },
      })
      const addedYear = Number(values.date.slice(0, 4))
      setHolidayOpen(false)
      setHolidayErrors({})
      if (addedYear && addedYear !== year) {
        chooseYear(addedYear)
      } else {
        // Keep the calendar and list mounted, and add the row straight away.
        setHolidays((current) => [...(current ?? []), result.holiday])
      }
      toastManager.add({ title: 'Holiday added.', type: 'success' })
      form.reset()
    } catch (error) {
      const errors = error instanceof ApiError ? error.errors : {}
      setHolidayErrors(errors)
      if (!(error instanceof ApiError) || Object.keys(errors).length === 0) {
        toastManager.add({ title: error instanceof ApiError ? error.message : 'Could not add the holiday.', type: 'error' })
      }
    } finally {
      setBusy('')
    }
  }

  async function removeHoliday() {
    if (!removing?.id) return
    setBusy('remove')
    try {
      const removedId = removing.id
      await api(`/gyms/${gymId}/holidays/${removedId}`, { method: 'DELETE' })
      setRemoving(null)
      // Remove the row in place so the calendar and list do not remount.
      setHolidays((current) => (current ?? []).filter((holiday) => holiday.id !== removedId))
      toastManager.add({ title: 'Holiday removed.', type: 'success' })
    } catch (error) {
      toastManager.add({ title: error instanceof ApiError ? error.message : 'Could not remove the holiday.', type: 'error' })
    } finally {
      setBusy('')
    }
  }

  const sortedHolidays = [...(holidays ?? [])].sort((a, b) => a.date.localeCompare(b.date))
  const defaultHolidayDate = year === CURRENT_YEAR ? TODAY_ISO : `${year}-01-01`

  function requestActions(request: RequestRow, own: boolean) {
    return (
      <div className="flex flex-wrap items-center gap-1.5">
        {request.attachment_name && (
          <Button size="xs" variant="ghost" title={request.attachment_name} onClick={() => openAttachment(request)}>
            <Paperclip aria-hidden />
            Attachment
          </Button>
        )}
        {canDecide && request.status === 'pending' && !own && (
          <>
            <Button size="xs" variant="outline" onClick={() => openDecision(request, 'approve')}>
              Approve
            </Button>
            <Button size="xs" variant="destructive-outline" onClick={() => openDecision(request, 'reject')}>
              Reject
            </Button>
          </>
        )}
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Leave"
        description="Decide leave requests, keep the holiday calendar and review each person's yearly balance."
        meta={canDecide ? <Badge variant="outline">HR manager</Badge> : null}
      />

      <Tabs value={tab} onValueChange={setTab}>
        <TabsList variant="underline" aria-label="Leave sections">
          <TabsTab value="requests">Requests</TabsTab>
          <TabsTab value="calendar">Calendar</TabsTab>
          <TabsTab value="balances">Balances</TabsTab>
        </TabsList>

        <TabsPanel value="requests" className="pt-4">
          <Card>
            <CardHeader>
              <CardTitle render={<h2 />}>Leave requests</CardTitle>
              <CardDescription>
                {canDecide
                  ? 'Every request in this gym. You cannot decide your own request.'
                  : 'Your own requests and their decisions.'}
              </CardDescription>
            </CardHeader>
            <CardPanel className="flex flex-col gap-4">
              <div className="flex flex-wrap items-end gap-3">
                {requests === null ? (
                  <Skeleton className="mr-auto h-4 w-20" aria-label="Loading requests" />
                ) : (
                  <p className="mr-auto text-muted-foreground text-sm">
                    {meta?.total ?? requests.length} {(meta?.total ?? requests.length) === 1 ? 'request' : 'requests'}
                  </p>
                )}
                <RangePicker optional name="leave" value={range} onChange={(next) => { setRange(next); setPage(1) }} />
                <Select
                  items={STATUS_ITEMS}
                  value={filter}
                  onValueChange={(next) => { setFilter((next as StatusFilter) ?? 'all'); setPage(1) }}
                >
                  <SelectTrigger size="sm" className="w-40" aria-label="Filter requests by status">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectPopup>
                    {STATUS_ITEMS.map((item) => (
                      <SelectItem key={item.value} value={item.value}>
                        {item.label}
                      </SelectItem>
                    ))}
                  </SelectPopup>
                </Select>
              </div>

              {requestsError ? (
                <Alert variant="error">
                  <AlertTitle>Requests could not be loaded</AlertTitle>
                  <AlertDescription>{requestsError}</AlertDescription>
                  <AlertAction>
                    <Button size="xs" variant="outline" onClick={refresh}>
                      Try again
                    </Button>
                  </AlertAction>
                </Alert>
              ) : requests === null ? (
                <div className="flex flex-col gap-2" aria-busy="true">
                  <Skeleton className="h-12" />
                  <Skeleton className="h-12" />
                  <Skeleton className="h-12" />
                </div>
              ) : requests.length === 0 ? (
                <Empty>
                  <EmptyHeader>
                    <EmptyTitle>No leave requests</EmptyTitle>
                    <EmptyDescription>
                      {filter === 'all' ? 'Requests will show up here as soon as an employee files leave.' : 'No request has this status.'}
                    </EmptyDescription>
                  </EmptyHeader>
                </Empty>
              ) : (
                <>
                  <ul className="flex flex-col gap-3 md:hidden">
                    {requests.map((request) => {
                      const own = request.user_id === user?.id
                      return (
                        <li key={request.id} className="flex flex-col gap-3 rounded-xl border p-4">
                          <div className="flex flex-wrap items-start gap-2">
                            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                              <span className="font-medium">{employeeName(request)}</span>
                              <span className="text-muted-foreground text-sm">{typeLabel(request.type, options, request.type_label)}</span>
                              {own && <span className="text-muted-foreground text-xs">You</span>}
                            </div>
                            <StatusBadge status={request.status} />
                          </div>
                          <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                            <span>{formatDateRange(request.starts_on, request.ends_on)}</span>
                            <span className="text-muted-foreground tabular-nums">
                              {request.working_days} working {request.working_days === 1 ? 'day' : 'days'}
                            </span>
                          </div>
                          {request.over_balance && (
                            <Badge variant="warning" className="self-start">
                              Over balance
                            </Badge>
                          )}
                          {requestActions(request, own)}
                        </li>
                      )
                    })}
                  </ul>

                  <div className="hidden md:block">
                    <Table variant="card" className="min-w-[58rem]">
                      <TableHeader>
                        <TableRow>
                          <TableHead>Employee</TableHead>
                          <TableHead>Type</TableHead>
                          <TableHead>Dates</TableHead>
                          <TableHead>Working days</TableHead>
                          <TableHead>Status</TableHead>
                          <TableHead>
                            <span className="sr-only">Actions</span>
                          </TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {requests.map((request) => {
                          const own = request.user_id === user?.id
                          return (
                            <TableRow key={request.id}>
                              <TableCell>
                                <div className="flex flex-col gap-0.5">
                                  <span className="font-medium">{employeeName(request)}</span>
                                  {own && <span className="text-muted-foreground text-xs">You</span>}
                                </div>
                              </TableCell>
                              <TableCell>{typeLabel(request.type, options, request.type_label)}</TableCell>
                              <TableCell>{formatDateRange(request.starts_on, request.ends_on)}</TableCell>
                              <TableCell className="tabular-nums">{request.working_days}</TableCell>
                              <TableCell>
                                <div className="flex flex-col items-start gap-1">
                                  <StatusBadge status={request.status} />
                                  {request.over_balance && <span className="font-medium text-warning-foreground text-xs">Over balance</span>}
                                </div>
                              </TableCell>
                              <TableCell>
                                <div className="flex justify-end">{requestActions(request, own)}</div>
                              </TableCell>
                            </TableRow>
                          )
                        })}
                      </TableBody>
                    </Table>
                  </div>
                </>
              )}
              <PageControls meta={meta} label="Leave requests" onPage={setPage} />
            </CardPanel>
          </Card>
        </TabsPanel>

        <TabsPanel value="calendar" className="pt-4">
          <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Holiday and leave calendar</CardTitle>
                <CardDescription>National holidays, gym holidays and approved leave for {year}. Viewing never changes the calendar.</CardDescription>
                <CardAction>
                  <YearSelect value={year} onChange={chooseYear} />
                </CardAction>
              </CardHeader>
              <CardPanel>
                {(approvedError || holidaysError) && (
                  <Alert variant="error" className="mb-4">
                    <AlertTitle>Part of the calendar is missing</AlertTitle>
                    <AlertDescription>{approvedError || holidaysError}</AlertDescription>
                    <AlertAction>
                      <Button size="xs" variant="outline" onClick={refresh}>
                        Try again
                      </Button>
                    </AlertAction>
                  </Alert>
                )}
                {approved === null || holidays === null ? (
                  <div className="flex flex-col gap-2" aria-busy="true">
                    <Skeleton className="h-72" />
                    <Skeleton className="h-5 w-60" />
                  </div>
                ) : (
                  <LeaveCalendar
                    holidays={holidays}
                    leaves={approved}
                    month={month}
                    onMonthChange={changeMonth}
                    label={`Leave and holiday calendar for ${month.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })}`}
                  />
                )}
              </CardPanel>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Holidays in {year}</CardTitle>
                <CardDescription>National holidays are fixed by law. Gym holidays are extra closures.</CardDescription>
                {canManageHolidays && (
                  <CardAction>
                    <Button size="sm" onClick={() => setHolidayOpen(true)}>
                      <Plus aria-hidden />
                      Add holiday
                    </Button>
                  </CardAction>
                )}
              </CardHeader>
              <CardPanel>
                {holidays === null ? (
                  <div className="flex flex-col gap-2" aria-busy="true">
                    <Skeleton className="h-10" />
                    <Skeleton className="h-10" />
                    <Skeleton className="h-10" />
                  </div>
                ) : holidaysError ? (
                  <Alert variant="error">
                    <AlertTitle>Holidays could not be loaded</AlertTitle>
                    <AlertDescription>{holidaysError}</AlertDescription>
                  </Alert>
                ) : sortedHolidays.length === 0 ? (
                  <Empty>
                    <EmptyHeader>
                      <EmptyTitle>No holidays in {year}</EmptyTitle>
                      <EmptyDescription>Add a gym holiday when the gym closes for a local event.</EmptyDescription>
                    </EmptyHeader>
                  </Empty>
                ) : (
                  <ul className="flex flex-col divide-y rounded-xl border">
                    {sortedHolidays.map((holiday) => (
                      <li key={`${holiday.date}-${holiday.id ?? 'national'}`} className="flex flex-wrap items-center gap-3 p-3">
                        <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                          <span className="font-medium">{holiday.name}</span>
                          <span className="text-muted-foreground text-sm">{formatDay(holiday.date)}</span>
                        </div>
                        <Badge variant={holiday.national ? 'info' : 'outline'}>{holiday.national ? 'National' : 'Gym'}</Badge>
                        {canManageHolidays && holiday.id != null && (
                          <Button size="xs" variant="ghost" onClick={() => setRemoving(holiday)}>
                            <Trash2 aria-hidden />
                            Remove
                          </Button>
                        )}
                      </li>
                    ))}
                  </ul>
                )}
              </CardPanel>
            </Card>
          </div>
        </TabsPanel>

        <TabsPanel value="balances" className="pt-4">
          <Card>
            <CardHeader>
              <CardTitle render={<h2 />}>Leave balances</CardTitle>
              <CardDescription>Allowance, approved use and remaining days for {year}. Only approved leave reduces a balance.</CardDescription>
              <CardAction>
                <YearSelect value={year} onChange={chooseYear} />
              </CardAction>
            </CardHeader>
            <CardPanel>
              {balancesError ? (
                <Alert variant="error">
                  <AlertTitle>Balances could not be loaded</AlertTitle>
                  <AlertDescription>{balancesError}</AlertDescription>
                  <AlertAction>
                    <Button size="xs" variant="outline" onClick={refresh}>
                      Try again
                    </Button>
                  </AlertAction>
                </Alert>
              ) : balances === null ? (
                <div className="flex flex-col gap-2" aria-busy="true">
                  <Skeleton className="h-12" />
                  <Skeleton className="h-12" />
                  <Skeleton className="h-12" />
                </div>
              ) : balances.length === 0 ? (
                <Empty>
                  <EmptyHeader>
                    <EmptyTitle>No leave balances for {year}</EmptyTitle>
                    <EmptyDescription>Only employees have leave balances. An HR manager can add people to the staff list first.</EmptyDescription>
                  </EmptyHeader>
                </Empty>
              ) : (
                <>
                  <ul className="flex flex-col gap-4 md:hidden">
                    {balances.map((employee) => (
                      <li key={employee.user_id} className="flex flex-col gap-3 rounded-xl border p-4">
                        <span className="font-medium">{employee.user_name}</span>
                        <ul className="flex flex-col gap-4">
                          {employee.types.map((balance) => (
                            <li key={balance.type} className="flex flex-col gap-1.5">
                              <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm">{balance.label}</span>
                                <Badge size="sm" variant="outline">
                                  {balance.paid ? 'Paid' : 'Unpaid'}
                                </Badge>
                                <Badge
                                  className="ms-auto"
                                  variant={balance.remaining > 2 ? 'success' : balance.remaining > 0 ? 'warning' : 'error'}
                                >
                                  {balance.remaining} {balance.remaining === 1 ? 'day' : 'days'} left
                                </Badge>
                              </div>
                              <Meter
                                value={Math.min(balance.used, Math.max(balance.allowance, 1))}
                                max={Math.max(balance.allowance, 1)}
                                aria-label={`${employee.user_name}: ${balance.used} of ${balance.allowance} ${balance.label} days used`}
                              >
                                <MeterTrack className="h-1.5 rounded-full">
                                  <MeterIndicator
                                    className={
                                      balance.remaining <= 0
                                        ? 'rounded-full bg-destructive'
                                        : balance.remaining <= 2
                                          ? 'rounded-full bg-warning'
                                          : 'rounded-full bg-primary'
                                    }
                                  />
                                </MeterTrack>
                              </Meter>
                              <span className="text-muted-foreground text-xs tabular-nums">
                                {balance.used} of {balance.allowance} days used
                              </span>
                            </li>
                          ))}
                        </ul>
                      </li>
                    ))}
                  </ul>

                  <div className="hidden md:block">
                    <Table variant="card" className="min-w-[46rem]">
                      <TableHeader>
                        <TableRow>
                          <TableHead>Employee</TableHead>
                          <TableHead>Leave type</TableHead>
                          <TableHead>Allowance</TableHead>
                          <TableHead>Used</TableHead>
                          <TableHead>Remaining</TableHead>
                          <TableHead>Use</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {balances.map((employee) =>
                          employee.types.map((balance) => (
                            <TableRow key={`${employee.user_id}-${balance.type}`}>
                              <TableCell className="font-medium">{employee.user_name}</TableCell>
                              <TableCell>
                                <div className="flex flex-wrap items-center gap-2">
                                  {balance.label}
                                  <Badge size="sm" variant="outline">
                                    {balance.paid ? 'Paid' : 'Unpaid'}
                                  </Badge>
                                </div>
                              </TableCell>
                              <TableCell className="tabular-nums">{balance.allowance}</TableCell>
                              <TableCell className="tabular-nums">{balance.used}</TableCell>
                              <TableCell>
                                <Badge variant={balance.remaining > 2 ? 'success' : balance.remaining > 0 ? 'warning' : 'error'}>
                                  {balance.remaining} {balance.remaining === 1 ? 'day' : 'days'}
                                </Badge>
                              </TableCell>
                              <TableCell>
                                <Meter
                                  value={Math.min(balance.used, Math.max(balance.allowance, 1))}
                                  max={Math.max(balance.allowance, 1)}
                                  aria-label={`${employee.user_name}: ${balance.used} of ${balance.allowance} ${balance.label} days used`}
                                  className="w-32"
                                >
                                  <MeterTrack className="h-1.5 rounded-full">
                                    <MeterIndicator
                                      className={
                                        balance.remaining <= 0
                                          ? 'rounded-full bg-destructive'
                                          : balance.remaining <= 2
                                            ? 'rounded-full bg-warning'
                                            : 'rounded-full bg-primary'
                                      }
                                    />
                                  </MeterTrack>
                                </Meter>
                              </TableCell>
                            </TableRow>
                          )),
                        )}
                      </TableBody>
                    </Table>
                  </div>
                </>
              )}
            </CardPanel>
          </Card>
        </TabsPanel>
      </Tabs>

      <Dialog open={decision !== null} onOpenChange={(open) => !open && setDecision(null)}>
        <DialogPopup>
          {decision && (
            <Form errors={decisionErrors} onSubmit={decide} className="flex min-h-0 flex-col">
              <DialogHeader>
                <DialogTitle>{decision.action === 'approve' ? 'Approve leave?' : 'Reject leave?'}</DialogTitle>
                <DialogDescription>
                  {employeeName(decision.request)} · {typeLabel(decision.request.type, options, decision.request.type_label)} ·{' '}
                  {formatDateRange(decision.request.starts_on, decision.request.ends_on)}
                </DialogDescription>
              </DialogHeader>
              <DialogPanel className="flex flex-col gap-4">
                {decision.request.over_balance && (
                  <Alert variant="warning">
                    <AlertTitle>More days than remain</AlertTitle>
                    <AlertDescription>
                      This request uses {decision.request.working_days} working days, more than the employee has left. The server still allows the
                      decision; this is only a warning.
                    </AlertDescription>
                  </Alert>
                )}
                <dl className="grid grid-cols-2 gap-3 rounded-xl border p-3 text-sm">
                  <div>
                    <dt className="text-muted-foreground">Working days</dt>
                    <dd className="font-medium tabular-nums">{decision.request.working_days}</dd>
                  </div>
                  <div>
                    <dt className="text-muted-foreground">Status</dt>
                    <dd>
                      <StatusBadge status={decision.request.status} />
                    </dd>
                  </div>
                  <div className="col-span-2">
                    <dt className="text-muted-foreground">Reason</dt>
                    <dd className="break-words">{decision.request.reason ?? 'No reason given.'}</dd>
                  </div>
                </dl>
                {decision.request.attachment_name && (
                  <Button type="button" variant="outline" size="sm" className="self-start" onClick={() => openAttachment(decision.request)}>
                    <Paperclip aria-hidden />
                    {decision.request.attachment_name}
                    {decision.request.attachment_size ? ` (${fileSize(decision.request.attachment_size)})` : ''}
                  </Button>
                )}
                <Field name="note">
                  <FieldLabel>Note {decision.action === 'reject' ? '(tell the employee why)' : '(optional)'}</FieldLabel>
                  <Textarea name="note" rows={3} maxLength={2000} placeholder={decision.action === 'reject' ? 'For example: the team is short-staffed that week.' : ''} />
                  <FieldError />
                </Field>
              </DialogPanel>
              <DialogFooter>
                <Button type="button" variant="outline" onClick={() => setDecision(null)}>
                  Cancel
                </Button>
                <Button
                  type="submit"
                  variant={decision.action === 'reject' ? 'destructive' : 'default'}
                  loading={busy === 'decision'}
                >
                  {decision.action === 'approve' ? 'Approve leave' : 'Reject leave'}
                </Button>
              </DialogFooter>
            </Form>
          )}
        </DialogPopup>
      </Dialog>

      <Dialog
        open={holidayOpen}
        onOpenChange={(open) => {
          setHolidayOpen(open)
          if (!open) setHolidayErrors({})
        }}
      >
        <DialogPopup>
          <Form errors={holidayErrors} onSubmit={addHoliday} className="flex min-h-0 flex-col">
            <DialogHeader>
              <DialogTitle>Add gym holiday</DialogTitle>
              <DialogDescription>This closes the gym for everyone on that date. National holidays already count and cannot be removed.</DialogDescription>
            </DialogHeader>
            <DialogPanel className="flex flex-col gap-4">
              <TextField name="date" label="Date" type="date" defaultValue={defaultHolidayDate} required />
              <TextField name="name" label="Holiday name" placeholder="For example: local fiesta" maxLength={120} required />
            </DialogPanel>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setHolidayOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" loading={busy === 'holiday'}>
                <CalendarPlus aria-hidden />
                Add holiday
              </Button>
            </DialogFooter>
          </Form>
        </DialogPopup>
      </Dialog>

      <Dialog open={removing !== null} onOpenChange={(open) => !open && setRemoving(null)}>
        <DialogPopup>
          {removing && (
            <>
              <DialogHeader>
                <DialogTitle>Remove this holiday?</DialogTitle>
                <DialogDescription>
                  {removing.name} on {formatDay(removing.date)} will no longer count as a holiday. National holidays cannot be removed.
                </DialogDescription>
              </DialogHeader>
              <DialogFooter>
                <Button variant="outline" onClick={() => setRemoving(null)}>
                  Keep holiday
                </Button>
                <Button variant="destructive" loading={busy === 'remove'} onClick={removeHoliday}>
                  <Trash2 aria-hidden />
                  Remove holiday
                </Button>
              </DialogFooter>
            </>
          )}
        </DialogPopup>
      </Dialog>
    </div>
  )
}
