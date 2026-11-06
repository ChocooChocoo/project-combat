import { Paperclip, Printer, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { useParams } from 'react-router'
import { LeaveCalendar, formatDateRange, formatDay, isoDay } from '@/components/leave-calendar'
import { MyAttendance } from '@/components/hr/my-attendance'
import { PageHeader } from '@/components/page-header'
import { PayslipSheet } from '@/components/payroll/payslip-sheet'
import { CoachCalendar } from '@/components/schedule/coach-calendar'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button, buttonVariants } from '@/components/ui/button'
import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Meter, MeterIndicator, MeterTrack } from '@/components/ui/meter'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, apiFile, ApiError, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym } from '@/lib/gym-access'
import { dateLabel, deductionsOf, money, periodLabel, readPayslipDetail, readPayslips, type Payslip } from '@/lib/payroll'
import { cn } from '@/lib/utils'
import type { GymOptions, LeaveRequestStatus } from '@/lib/workspace'
import { fileProblem, notBefore } from '@/lib/validation'

type WorkDay = {
  weekday: string
  label: string
  start_time: string | null
  end_time: string | null
  is_day_off: boolean
}

type WorkBalance = {
  type: string
  label: string
  allowance: number
  used: number
  remaining: number
  paid: boolean
}

type WorkRequest = {
  id: number
  type: string
  type_label?: string
  starts_on: string
  ends_on: string
  working_days: number
  reason: string | null
  status: LeaveRequestStatus
  decided_by_name: string | null
  decision_note: string | null
  attachment_name: string | null
  attachment_size: number | null
  over_balance: boolean
}

type WorkHoliday = {
  id?: number
  date: string
  name: string
  national: boolean
}

type WorkData = {
  is_coach: boolean
  schedule: WorkDay[]
  balances: WorkBalance[]
  requests: WorkRequest[]
  approved_leave_dates: string[]
  holidays: WorkHoliday[]
}

type SentSummary = {
  starts_on: string
  ends_on: string
  working_days?: number
}

const CURRENT_YEAR = new Date().getFullYear()
const TODAY_KEY = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'][new Date().getDay()]
const TODAY_ISO = isoDay(new Date())
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

async function openAttachment(gymId: number, requestId: number) {
  try {
    await apiFile(`/gyms/${gymId}/leave-requests/${requestId}/attachment`)
  } catch (error) {
    toastManager.add({ title: error instanceof ApiError ? error.message : 'Could not open the attachment.', type: 'error' })
  }
}

/** S-38, R-24 to R-27: the employee's own schedule, leave, balances and holidays. */
export default function MyWork() {
  const { gymId: gymIdParam } = useParams()
  const gymId = Number(gymIdParam)
  const { user } = useAuth()
  const gym = approvedGym(user, gymId)

  const [data, setData] = useState<WorkData | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [tab, setTab] = useState('work')
  const [options, setOptions] = useState<GymOptions | null>(null)
  const [optionsError, setOptionsError] = useState('')
  const [optionsVersion, setOptionsVersion] = useState(0)
  const [month, setMonth] = useState(() => new Date(CURRENT_YEAR, new Date().getMonth(), 1))

  const [type, setType] = useState('')
  const [start, setStart] = useState('')
  const [end, setEnd] = useState('')
  const [reason, setReason] = useState('')
  const [attachment, setAttachment] = useState<File | null>(null)
  const attachmentInput = useRef<HTMLInputElement>(null)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [sent, setSent] = useState<SentSummary | null>(null)
  const [busy, setBusy] = useState('')
  const [cancelling, setCancelling] = useState<WorkRequest | null>(null)

  useEffect(() => {
    let live = true
    setData(null)
    setError('')
    api<{ work: WorkData }>(`/gyms/${gymId}/my-work`)
      .then((result) => {
        if (live) setData(result.work)
      })
      .catch((reason) => {
        if (!live) return
        setError(reason instanceof ApiError ? reason.message : 'Could not load your work details.')
      })
    return () => {
      live = false
    }
  }, [gymId, version])

  useEffect(() => {
    let live = true
    setOptions(null)
    setOptionsError('')
    api<{ options: GymOptions }>(`/gyms/${gymId}/options`)
      .then((result) => {
        if (live) setOptions(result.options)
      })
      .catch((reason) => {
        if (!live) return
        setOptionsError(reason instanceof ApiError ? reason.message : 'Leave types could not be loaded.')
      })
    return () => {
      live = false
    }
  }, [gymId, optionsVersion])

  if (!gym) return null

  const schedule = data?.schedule ?? []
  const todaySchedule = schedule.find((day) => day.weekday === TODAY_KEY)
  const todayShift = todaySchedule ? { start: todaySchedule.start_time, end: todaySchedule.end_time, dayOff: todaySchedule.is_day_off } : null
  const balances = data?.balances ?? []
  const requests = data?.requests ?? []
  const holidays = data?.holidays ?? []
  const approvedDates = data?.approved_leave_dates ?? []
  const typeItems = options ? Object.entries(options.leave_types).map(([value, info]) => ({ value, label: info.label })) : []
  const selectedType = type ? options?.leave_types[type] : undefined
  const today = TODAY_ISO
  const upcomingHolidays = holidays.filter((holiday) => holiday.date >= today).sort((a, b) => a.date.localeCompare(b.date)).slice(0, 5)
  const accept = options?.attachment.mimes.map((mime) => `.${mime}`).join(',')
  const maxMb = options ? Math.round(options.attachment.max_kb / 1024) : 5

  function refresh() {
    setVersion((current) => current + 1)
  }

  function clearAttachment() {
    setAttachment(null)
    if (attachmentInput.current) attachmentInput.current.value = ''
  }

  async function requestLeave(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = event.currentTarget
    setSent(null)

    const local: FieldErrors = {}
    if (!type) local.type = ['Choose a leave type.']
    if (!start) local.starts_on = ['Choose the first day.']
    if (!end) local.ends_on = ['Choose the last day.']
    if (start && end && end < start) local.ends_on = ['The last day must be on or after the first day.']
    if (attachment && attachment.size > maxMb * 1024 * 1024) local.attachment = [`The largest file is ${maxMb} MB.`]
    if (Object.keys(local).length > 0) {
      setErrors(local)
      return
    }

    const body = new FormData()
    body.append('type', type)
    body.append('starts_on', start)
    body.append('ends_on', end)
    body.append('reason', reason)
    if (attachment) body.append('attachment', attachment)

    setBusy('request')
    try {
      const result = await api<{ request: WorkRequest }>(`/gyms/${gymId}/leave-requests`, { body })
      const saved = result.request
      setSent({
        starts_on: saved?.starts_on ?? start,
        ends_on: saved?.ends_on ?? end,
        working_days: saved?.working_days,
      })
      setErrors({})
      form.reset()
      setType('')
      setStart('')
      setEnd('')
      setReason('')
      clearAttachment()
      refresh()
      toastManager.add({
        title: typeof saved?.working_days === 'number' ? `Leave requested for ${saved.working_days} working ${saved.working_days === 1 ? 'day' : 'days'}.` : 'Leave requested.',
        type: 'success',
      })
    } catch (error) {
      const fieldErrors = error instanceof ApiError ? error.errors : {}
      setErrors(fieldErrors)
      if (!(error instanceof ApiError) || Object.keys(fieldErrors).length === 0) {
        toastManager.add({ title: error instanceof ApiError ? error.message : 'Could not send the leave request.', type: 'error' })
      }
    } finally {
      setBusy('')
    }
  }

  async function cancelRequest() {
    if (!cancelling) return
    setBusy('cancel')
    try {
      await api(`/gyms/${gymId}/leave-requests/${cancelling.id}/cancel`, { method: 'POST' })
      setCancelling(null)
      refresh()
      toastManager.add({ title: 'Leave request cancelled.', type: 'success' })
    } catch (error) {
      toastManager.add({ title: error instanceof ApiError ? error.message : 'Could not cancel the request.', type: 'error' })
    } finally {
      setBusy('')
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="My work"
        description={`${gym.name}: your schedule, leave, the holidays that affect them and your released payslips.`}
        meta={<Badge variant="outline">Employee</Badge>}
      />

      <Tabs value={tab} onValueChange={(value) => setTab(String(value))}>
        <TabsList variant="underline" aria-label="My work sections" className="max-w-full overflow-x-auto overflow-y-hidden [scrollbar-width:none]">
          <TabsTab value="work">My work</TabsTab>
          <TabsTab value="payslips">Payslips</TabsTab>
        </TabsList>

        <TabsPanel value="work" keepMounted className="pt-4">
          {error ? (
            <Alert variant="error">
              <AlertTitle>Your work details could not be loaded</AlertTitle>
              <AlertDescription>{error}</AlertDescription>
              <div className="pt-2">
                <Button size="sm" variant="outline" onClick={refresh}>
                  Try again
                </Button>
              </div>
            </Alert>
          ) : data === null ? (
            <div className="grid gap-6 lg:grid-cols-2" aria-busy="true">
              <Skeleton className="h-72" />
              <Skeleton className="h-72" />
              <Skeleton className="h-96" />
              <Skeleton className="h-96" />
            </div>
          ) : (
        <>
          {gym.roles.includes('employee') && <MyAttendance gymId={gymId} shift={todayShift} />}
          {data.is_coach && user && <CoachCalendar gymId={gymId} userId={user.id} refreshKey={version} />}
          <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Your week</CardTitle>
                <CardDescription>Your regular working hours. Days not listed are days off.</CardDescription>
              </CardHeader>
              <CardPanel>
                <ul className="flex flex-col divide-y rounded-xl border">
                  {schedule.map((day) => {
                    const isToday = day.weekday === TODAY_KEY
                    return (
                      <li key={day.weekday} className={cn('flex min-h-11 flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2', isToday && 'bg-muted/50')}>
                        <span className="w-24 font-medium text-sm">{day.label}</span>
                        <span className={day.is_day_off ? 'text-muted-foreground text-sm' : 'text-sm tabular-nums'}>
                          {day.is_day_off ? 'Day off' : `${day.start_time ?? 'Not set'} – ${day.end_time ?? 'Not set'}`}
                        </span>
                        {isToday && (
                          <Badge size="sm" variant="secondary" className="ms-auto">
                            Today
                          </Badge>
                        )}
                      </li>
                    )
                  })}
                </ul>
              </CardPanel>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Leave balances</CardTitle>
                <CardDescription>Your {CURRENT_YEAR} allowance. Approved leave uses working days.</CardDescription>
              </CardHeader>
              <CardPanel>
                {balances.length === 0 ? (
                  <Empty>
                    <EmptyHeader>
                      <EmptyTitle>No leave balance yet</EmptyTitle>
                      <EmptyDescription>Your gym has not set leave allowances for this year.</EmptyDescription>
                    </EmptyHeader>
                  </Empty>
                ) : (
                  <ul className="flex flex-col gap-5">
                    {balances.map((balance) => (
                      <li key={balance.type} className="flex flex-col gap-2">
                        <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                          <span className="font-medium text-sm">{balance.label}</span>
                          <Badge size="sm" variant="outline">
                            {balance.paid ? 'Paid' : 'Unpaid'}
                          </Badge>
                          <span className="ms-auto text-muted-foreground text-sm tabular-nums">
                            {balance.remaining} of {balance.allowance} {balance.allowance === 1 ? 'day' : 'days'} left
                          </span>
                        </div>
                        <Meter
                          value={Math.min(balance.used, Math.max(balance.allowance, 1))}
                          max={Math.max(balance.allowance, 1)}
                          aria-label={`${balance.label}: ${balance.used} of ${balance.allowance} days used`}
                        >
                          <MeterTrack className="h-1.5 rounded-full">
                            <MeterIndicator
                              className={
                                balance.remaining <= 0 ? 'rounded-full bg-destructive' : balance.remaining <= 2 ? 'rounded-full bg-warning' : 'rounded-full bg-primary'
                              }
                            />
                          </MeterTrack>
                        </Meter>
                        <span className="text-muted-foreground text-xs tabular-nums">{balance.used} used</span>
                      </li>
                    ))}
                  </ul>
                )}
              </CardPanel>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Request leave</CardTitle>
                <CardDescription>Pick your dates and say why. Your manager decides, and approved leave reduces your balance.</CardDescription>
              </CardHeader>
              <CardPanel className="flex flex-col gap-4">
                {optionsError && (
                  <Alert variant="error">
                    <AlertTitle>Leave types could not be loaded</AlertTitle>
                    <AlertDescription>{optionsError}</AlertDescription>
                    <div className="pt-2">
                      <Button size="xs" variant="outline" onClick={() => setOptionsVersion((current) => current + 1)}>
                        Try again
                      </Button>
                    </div>
                  </Alert>
                )}

                {sent && (
                  <Alert variant="success">
                    <AlertTitle>Request sent</AlertTitle>
                    <AlertDescription>
                      {formatDateRange(sent.starts_on, sent.ends_on)}.
                      {typeof sent.working_days === 'number' ? ` ${sent.working_days} working ${sent.working_days === 1 ? 'day' : 'days'}.` : ''} Waiting for a
                      decision.
                    </AlertDescription>
                  </Alert>
                )}

                <Form errors={errors} onSubmit={requestLeave} className="flex flex-col gap-4">
                  <Field name="type">
                    <FieldLabel>Leave type</FieldLabel>
                    {!options && <Skeleton className="h-9 w-full sm:h-8" aria-label="Loading leave types" />}
                    <Select
                      items={typeItems}
                      name="type"
                      value={type || null}
                      onValueChange={(next) => {
                        setSent(null)
                        setType(next ?? '')
                      }}
                      required
                      disabled={!options}
                    >
                      <SelectTrigger className={options ? undefined : 'hidden'} aria-label="Leave type" aria-invalid={errors.type ? true : undefined}>
                        <SelectValue placeholder="Choose a leave type" />
                      </SelectTrigger>
                      <SelectPopup>
                        {typeItems.map((item) => (
                          <SelectItem key={item.value} value={item.value}>
                            {item.label}
                          </SelectItem>
                        ))}
                      </SelectPopup>
                    </Select>
                    {selectedType ? (
                      <FieldDescription>
                        {selectedType.days} days a year. {selectedType.paid ? 'Paid' : 'Unpaid'}.
                      </FieldDescription>
                    ) : null}
                    <FieldError />
                  </Field>

                  <div className="grid gap-4 sm:grid-cols-2">
                    <TextField
                      name="starts_on"
                      label="First day"
                      type="date"
                      value={start}
                      onChange={(event) => {
                        setSent(null)
                        setStart(event.target.value)
                      }}
                      required
                    />
                    <TextField
                      name="ends_on"
                      label="Last day"
                      type="date"
                      min={start || undefined}
                      validate={notBefore('starts_on', 'The last day must be on or after the first day.')}
                      value={end}
                      onChange={(event) => {
                        setSent(null)
                        setEnd(event.target.value)
                      }}
                      required
                    />
                  </div>
                  {start && end && end >= start && <p className="text-muted-foreground text-sm">{formatDateRange(start, end)}</p>}

                  <Field name="reason">
                    <FieldLabel>Reason</FieldLabel>
                    <Textarea
                      name="reason"
                      rows={3}
                      maxLength={2000}
                      value={reason}
                      onChange={(event) => {
                        setSent(null)
                        setReason(event.target.value)
                      }}
                      placeholder="For example: a family commitment out of town."
                      required
                    />
                    <FieldDescription>Your manager sees this with the dates.</FieldDescription>
                    <FieldError />
                  </Field>

                  <Field name="attachment">
                    <FieldLabel>
                      Attachment <span className="font-normal text-muted-foreground">(optional)</span>
                    </FieldLabel>
                    <div className="flex flex-wrap items-center gap-2">
                      <label
                        className={`${buttonVariants({ variant: 'outline', size: 'sm' })} cursor-pointer has-[input:focus-visible]:ring-2 has-[input:focus-visible]:ring-ring`}
                      >
                        <Paperclip aria-hidden />
                        Choose file
                        <input
                          ref={attachmentInput}
                          type="file"
                          className="sr-only"
                          accept={accept}
                          aria-label="Attach a supporting file"
                          onChange={(event) => {
                            setSent(null)
                            const file = event.target.files?.[0] ?? null
                            const problem = file && fileProblem(file, accept, options?.attachment.max_kb)
                            if (problem) {
                              toastManager.add({ title: problem, type: 'error' })
                              event.target.value = ''
                              return
                            }
                            setAttachment(file)
                          }}
                        />
                      </label>
                      {attachment ? (
                        <>
                          <span className="min-w-0 max-w-full truncate text-sm">{attachment.name}</span>
                          <Button type="button" size="xs" variant="ghost" onClick={clearAttachment}>
                            <X aria-hidden />
                            Remove
                          </Button>
                        </>
                      ) : (
                        <span className="text-muted-foreground text-sm">PDF, JPG or PNG up to {maxMb} MB</span>
                      )}
                    </div>
                    <FieldError />
                  </Field>

                  <div className="flex flex-wrap items-center gap-2">
                    <Button type="submit" loading={busy === 'request'} disabled={!options}>
                      Send request
                    </Button>
                    <span className="text-muted-foreground text-xs">Working days are counted by the gym's calendar when you send.</span>
                  </div>
                </Form>
              </CardPanel>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Leave calendar</CardTitle>
                <CardDescription>Approved leave and holidays for {CURRENT_YEAR}. Use the arrows to change the month.</CardDescription>
              </CardHeader>
              <CardPanel className="flex flex-col gap-4">
                <LeaveCalendar
                  holidays={holidays}
                  leaveDates={approvedDates}
                  month={month}
                  onMonthChange={setMonth}
                  label="Your leave and holiday calendar"
                />
                <div className="flex flex-col gap-2 border-t pt-4">
                  <h3 className="font-medium text-sm">Upcoming holidays</h3>
                  {upcomingHolidays.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No more holidays this year.</p>
                  ) : (
                    <ul className="flex flex-col gap-2 text-sm">
                      {upcomingHolidays.map((holiday) => (
                        <li key={`${holiday.date}-${holiday.name}`} className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                          <span className="min-w-0 flex-1">{holiday.name}</span>
                          <span className="text-muted-foreground">{formatDay(holiday.date)}</span>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              </CardPanel>
            </Card>
          </div>

          <Card>
            <CardHeader>
              <CardTitle render={<h2 />}>My leave requests</CardTitle>
              <CardDescription>Your requests for this gym. You can cancel one while it is pending.</CardDescription>
            </CardHeader>
            <CardPanel>
              {requests.length === 0 ? (
                <Empty>
                  <EmptyHeader>
                    <EmptyTitle>No leave requests</EmptyTitle>
                    <EmptyDescription>Use the request form above when you need time away.</EmptyDescription>
                  </EmptyHeader>
                </Empty>
              ) : (
                <ul className="flex flex-col divide-y rounded-xl border">
                  {requests.map((request) => (
                    <li key={request.id} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-start">
                      <div className="flex min-w-0 flex-1 flex-col gap-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="font-medium">{request.type_label ?? typeLabel(request.type, options)}</span>
                          <StatusBadge status={request.status} />
                          {request.over_balance && <Badge variant="warning">Over balance</Badge>}
                        </div>
                        <span className="text-sm">{formatDateRange(request.starts_on, request.ends_on)}</span>
                        <span className="text-muted-foreground text-sm">
                          {request.working_days} working {request.working_days === 1 ? 'day' : 'days'}
                          {request.reason ? ` · ${request.reason}` : ''}
                        </span>
                        {request.decision_note && (
                          <span className="text-muted-foreground text-sm">
                            Note from {request.decided_by_name ?? 'the manager'}: {request.decision_note}
                          </span>
                        )}
                      </div>
                      <div className="flex flex-wrap items-center gap-2">
                        {request.attachment_name && (
                          <Button
                            size="xs"
                            variant="ghost"
                            title={request.attachment_name}
                            onClick={() => openAttachment(gymId, request.id)}
                          >
                            <Paperclip aria-hidden />
                            Attachment
                          </Button>
                        )}
                        {request.status === 'pending' && (
                          <Button size="xs" variant="outline" onClick={() => setCancelling(request)}>
                            Cancel request
                          </Button>
                        )}
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </CardPanel>
          </Card>
        </>
      )}
        </TabsPanel>

        <TabsPanel value="payslips" className="pt-4">
          <PayslipsPanel gymName={gym.name} />
        </TabsPanel>
      </Tabs>

      <Dialog open={cancelling !== null} onOpenChange={(open) => !open && setCancelling(null)}>
        <DialogPopup>
          {cancelling && (
            <>
              <DialogHeader>
                <DialogTitle>Cancel this leave request?</DialogTitle>
                <DialogDescription>
                  {typeLabel(cancelling.type, options)} from {formatDateRange(cancelling.starts_on, cancelling.ends_on)} will be withdrawn. This cannot be undone.
                </DialogDescription>
              </DialogHeader>
              <DialogFooter>
                <Button variant="outline" onClick={() => setCancelling(null)}>
                  Keep request
                </Button>
                <Button variant="destructive" loading={busy === 'cancel'} onClick={cancelRequest}>
                  Cancel request
                </Button>
              </DialogFooter>
            </>
          )}
        </DialogPopup>
      </Dialog>
    </div>
  )
}

function typeLabel(type: string, options: GymOptions | null): string {
  return options?.leave_types?.[type]?.label ?? type.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

/** R-62: released payslips only, with the print-friendly sheet behind one button. */
function PayslipsPanel({ gymName }: { gymName: string }) {
  const [payslips, setPayslips] = useState<Payslip[] | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [viewing, setViewing] = useState<Payslip | null>(null)
  const [detail, setDetail] = useState<Payslip | null>(null)
  const [detailBusy, setDetailBusy] = useState(false)
  const [detailError, setDetailError] = useState('')

  useEffect(() => {
    let live = true
    setPayslips(null)
    setError('')
    api<Record<string, unknown>>('/me/payslips')
      .then((payload) => {
        if (live) setPayslips(readPayslips(payload))
      })
      .catch((reason) => {
        if (!live) return
        setPayslips([])
        setError(reason instanceof ApiError ? reason.message : 'Your payslips could not be loaded.')
      })
    return () => {
      live = false
    }
  }, [version])

  useEffect(() => {
    if (!viewing) return
    if (!viewing.line_id) {
      setDetail(null)
      setDetailError('')
      setDetailBusy(false)
      return
    }
    let live = true
    setDetail(null)
    setDetailError('')
    setDetailBusy(true)
    api<Record<string, unknown>>(`/me/payslips/${viewing.line_id}`)
      .then((payload) => {
        if (live) setDetail(readPayslipDetail(payload))
      })
      .catch((reason) => {
        if (live) setDetailError(reason instanceof ApiError ? reason.message : 'The full payslip could not be loaded.')
      })
      .finally(() => {
        if (live) setDetailBusy(false)
      })
    return () => {
      live = false
    }
  }, [viewing])

  function openPayslip(payslip: Payslip) {
    setDetail(null)
    setDetailError('')
    setViewing(payslip)
  }

  const shown = (payslips ?? [])
    .filter((payslip) => !payslip.status || payslip.status === 'released')
    .sort((a, b) => (b.starts_on ?? '').localeCompare(a.starts_on ?? ''))
  const payslip = detail ?? viewing

  return (
    <div className="flex flex-col gap-4">
      {error ? (
        <Alert variant="error">
          <AlertTitle>Your payslips could not be loaded</AlertTitle>
          <AlertDescription>{error}</AlertDescription>
          <div className="pt-2">
            <Button size="sm" variant="outline" onClick={() => setVersion((value) => value + 1)}>
              Try again
            </Button>
          </div>
        </Alert>
      ) : payslips === null ? (
        <div className="flex flex-col gap-2" aria-busy="true" aria-label="Loading payslips">
          <Skeleton className="h-24" />
          <Skeleton className="h-24" />
        </div>
      ) : shown.length === 0 ? (
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>No payslips yet</EmptyTitle>
            <EmptyDescription>
              A payslip appears here after the owner releases a payroll run that includes you. Draft and in-review runs stay private.
            </EmptyDescription>
          </EmptyHeader>
        </Empty>
      ) : (
        <>
          <p className="text-muted-foreground text-sm" aria-live="polite">
            {shown.length} released {shown.length === 1 ? 'payslip' : 'payslips'}, newest first
          </p>
          <ul className="flex flex-col divide-y rounded-xl border bg-card">
            {shown.map((row) => (
              <li key={`${row.line_id}-${row.run_id ?? 'run'}`} className="flex min-w-0 flex-col gap-3 p-4">
                <div className="flex items-start justify-between gap-3">
                  <div className="flex min-w-0 flex-col gap-1">
                    <span className="font-medium">{periodLabel(row.starts_on, row.ends_on)}</span>
                    <span className="text-muted-foreground text-sm">{row.gym_name || gymName}</span>
                  </div>
                  <Badge variant="success">Released</Badge>
                </div>
                <dl className="grid grid-cols-3 gap-2 text-sm">
                  <div className="flex flex-col gap-0.5">
                    <dt className="text-muted-foreground text-xs">Gross</dt>
                    <dd className="tabular-nums">{money(row.gross_pay)}</dd>
                  </div>
                  <div className="flex flex-col gap-0.5">
                    <dt className="text-muted-foreground text-xs">Deductions</dt>
                    <dd className="tabular-nums">{money(deductionsOf(row))}</dd>
                  </div>
                  <div className="flex flex-col gap-0.5">
                    <dt className="text-muted-foreground text-xs">Net</dt>
                    <dd className="font-semibold tabular-nums">{money(row.net_pay)}</dd>
                  </div>
                </dl>
                {row.released_at && <span className="text-muted-foreground text-xs">Released {dateLabel(row.released_at)}</span>}
                <Button size="sm" variant="outline" className="self-start" onClick={() => openPayslip(row)}>
                  <Printer aria-hidden />
                  View and print
                </Button>
              </li>
            ))}
          </ul>
        </>
      )}

      <Dialog
        open={viewing !== null}
        onOpenChange={(open) => {
          if (!open) {
            setViewing(null)
            setDetail(null)
            setDetailError('')
          }
        }}
      >
        <DialogPopup className="sm:max-w-2xl">
          {payslip && (
            <>
              <DialogHeader>
                <DialogTitle>Payslip</DialogTitle>
                <DialogDescription>
                  {periodLabel(payslip.starts_on, payslip.ends_on)} · {payslip.gym_name || gymName}
                </DialogDescription>
              </DialogHeader>
              <div className="max-h-[70vh] overflow-y-auto px-2 print:hidden" aria-busy={detailBusy || undefined}>
                {detailError && (
                  <Alert variant="warning" className="m-4">
                    <AlertTitle>Full breakdown unavailable</AlertTitle>
                    <AlertDescription>{detailError} You can still print the summary.</AlertDescription>
                  </Alert>
                )}
                <PayslipSheet gymName={payslip.gym_name || gymName} payslip={payslip} />
              </div>
              <DialogFooter>
                <Button variant="outline" onClick={() => setViewing(null)}>
                  Close
                </Button>
                <Button onClick={() => window.print()}>
                  <Printer aria-hidden />
                  Print payslip
                </Button>
              </DialogFooter>
            </>
          )}
        </DialogPopup>
      </Dialog>

      {payslip && (
        <div className="hidden print:block">
          <PayslipSheet gymName={payslip.gym_name || gymName} payslip={payslip} />
        </div>
      )}
    </div>
  )
}
