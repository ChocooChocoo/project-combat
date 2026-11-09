import { useEffect, useState } from 'react'
import { Check, ChevronLeft, ChevronRight, History, Plus, X } from 'lucide-react'
import { useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
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
  ATTENDANCE_DOT,
  ATTENDANCE_STATUS,
  OVERTIME_KIND,
  OVERTIME_STATUS,
  clockLabel,
  longDayLabel,
  minutesLabel,
  workDayLabel,
  type AttendanceChange,
  type AttendanceStatus,
  type OvertimeRequest,
  type StaffAttendance,
} from '@/lib/hr'
import { dateTimeLabel } from '@/lib/membership'
import { pageMetaFrom, type PageMeta } from '@/lib/pagination'
import { cn } from '@/lib/utils'
import { isoDay } from '@/components/leave-calendar'

type EmployeeChoice = { id: number; name: string }

const todayIso = () => isoDay(new Date())
const monthStartIso = () => isoDay(new Date(new Date().getFullYear(), new Date().getMonth(), 1))

/** S-28, R-56 and R-57: the desk view of staff attendance and overtime. */
export default function GymStaffAttendance() {
  const { gymId: gymIdParam } = useParams()
  const gymId = Number(gymIdParam)
  const { user } = useAuth()
  const gym = approvedGym(user, gymId)

  const canView = gym ? can(gym, 'hr.attendance.view') : false
  const canManage = gym ? can(gym, 'hr.attendance.manage') : false
  const canApprove = gym ? can(gym, 'hr.overtime.approve') : false

  const [tab, setTab] = useState('today')
  const [from, setFrom] = useState(monthStartIso)
  const [to, setTo] = useState(todayIso)
  const [employeeFilter, setEmployeeFilter] = useState('all')
  const [records, setRecords] = useState<StaffAttendance[] | null>(null)
  const [recordsMeta, setRecordsMeta] = useState<PageMeta | null>(null)
  const [recordsPage, setRecordsPage] = useState(1)
  const [todayList, setTodayList] = useState<StaffAttendance[] | null>(null)
  const [overtime, setOvertime] = useState<OvertimeRequest[] | null>(null)
  const [employees, setEmployees] = useState<EmployeeChoice[]>([])
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)

  const [deskOpen, setDeskOpen] = useState(false)
  const [correcting, setCorrecting] = useState<StaffAttendance | null>(null)
  const [historyOf, setHistoryOf] = useState<StaffAttendance | null>(null)
  const [history, setHistory] = useState<AttendanceChange[] | null>(null)
  const [deciding, setDeciding] = useState<OvertimeRequest | null>(null)

  useEffect(() => {
    if (!gymId || (!canView && !canApprove)) return
    let live = true
    setError('')
    setRecords(null)
    setTodayList(null)
    setOvertime(null)

    // Overtime approvers without attendance access load only the overtime list.
    if (!canView) {
      api<{ requests: OvertimeRequest[] }>(`/gyms/${gymId}/overtime-requests`)
        .then((result) => live && setOvertime(result.requests))
        .catch((reason) => {
          if (live) setError(reason instanceof ApiError ? reason.message : 'Overtime requests could not be loaded.')
        })
      return () => {
        live = false
      }
    }

    const query = new URLSearchParams({ from, to, page: String(recordsPage) })
    if (employeeFilter !== 'all') query.set('user_id', employeeFilter)
    const todayQuery = new URLSearchParams({ from: todayIso(), to: todayIso() })

    Promise.all([
      api<Record<string, unknown>>(`/gyms/${gymId}/staff-attendance?${todayQuery.toString()}`),
      api<Record<string, unknown>>(`/gyms/${gymId}/staff-attendance?${query.toString()}`),
      api<{ requests: OvertimeRequest[] }>(`/gyms/${gymId}/overtime-requests`),
      api<{ employees: EmployeeChoice[] }>(`/gyms/${gymId}/staff-attendance/employees`),
    ])
      .then(([todayResult, attendanceResult, overtimeResult, employeeResult]) => {
        if (!live) return
        const todayRows = Array.isArray(todayResult.attendance) ? (todayResult.attendance as StaffAttendance[]) : []
        const recordRows = Array.isArray(attendanceResult.attendance)
          ? (attendanceResult.attendance as StaffAttendance[])
          : []
        setTodayList(todayRows)
        setRecords(recordRows)
        setRecordsMeta(pageMetaFrom(attendanceResult, recordRows.length))
        setOvertime(overtimeResult.requests)
        setEmployees(employeeResult.employees)
      })
      .catch((reason) => {
        if (live) setError(reason instanceof ApiError ? reason.message : 'Staff attendance could not be loaded.')
      })

    return () => {
      live = false
    }
  }, [gymId, canView, canManage, canApprove, from, to, employeeFilter, recordsPage, version])

  useEffect(() => {
    if (!historyOf) return
    let live = true
    setHistory(null)
    api<{ changes: AttendanceChange[] }>(`/gyms/${gymId}/staff-attendance/${historyOf.id}/changes`)
      .then((result) => {
        if (live) setHistory(result.changes)
      })
      .catch((reason) => {
        if (live) {
          setHistory([])
          toastManager.add({ title: reason instanceof ApiError ? reason.message : 'History could not be loaded.', type: 'error' })
        }
      })
    return () => {
      live = false
    }
  }, [gymId, historyOf])

  if (!gym) return null

  if (!canView && !canApprove) {
    return (
      <div className="flex flex-col gap-6">
        <PageHeader title="Staff attendance" />
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>Staff attendance is not available</EmptyTitle>
            <EmptyDescription>Ask the owner for the staff attendance permission.</EmptyDescription>
          </EmptyHeader>
        </Empty>
      </div>
    )
  }

  const pendingOvertime = (overtime ?? []).filter((row) => row.status === 'pending')

  function refresh() {
    setVersion((current) => current + 1)
  }

  const recordButton = canManage ? (
    <Button onClick={() => setDeskOpen(true)}>
      <Plus aria-hidden />
      Record attendance
    </Button>
  ) : null

  const unavailable = <p className="rounded-xl border px-4 py-3 text-muted-foreground text-sm">Nothing to show until attendance loads. Use Try again above.</p>

  const errorAlert = error ? (
    <Alert variant="error">
      <AlertTitle>Attendance could not be loaded</AlertTitle>
      <AlertDescription>{error}</AlertDescription>
      <div className="pt-2">
        <Button size="sm" variant="outline" onClick={refresh}>
          Try again
        </Button>
      </div>
    </Alert>
  ) : null

  const decisionDialog = deciding ? (
    <DecisionDialog
      gymId={gymId}
      request={deciding}
      onClose={() => setDeciding(null)}
      onSaved={() => {
        setDeciding(null)
        refresh()
      }}
    />
  ) : null

  // hr.overtime.approve alone: show only the overtime decisions.
  if (!canView) {
    return (
      <div className="flex flex-col gap-6">
        <PageHeader title="Staff attendance" description={`${gym.name}: overtime decisions.`} />
        {errorAlert}
        {error ? unavailable : <OvertimePanel rows={overtime ?? []} loading={overtime === null} canApprove={canApprove} onDecide={setDeciding} />}
        {decisionDialog}
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Staff attendance"
        description={`${gym.name}: who is in, corrections, and overtime decisions.`}
        actions={
          canManage ? (
            <Button size="sm" onClick={() => setDeskOpen(true)}>
              <Plus aria-hidden />
              Record attendance
            </Button>
          ) : undefined
        }
      />

      {errorAlert}

      {!error && <TodayStrip rows={todayList ?? []} loading={todayList === null} />}

      <Tabs value={tab} onValueChange={(value) => setTab(String(value))}>
        <TabsList variant="underline" aria-label="Staff attendance sections" className="max-w-full overflow-x-auto overflow-y-hidden [scrollbar-width:none]">
          <TabsTab value="today">Today</TabsTab>
          <TabsTab value="records">Records</TabsTab>
          <TabsTab value="overtime">
            Overtime
            {pendingOvertime.length > 0 && (
              <Badge variant="warning" aria-label={`${pendingOvertime.length} waiting for a decision`}>
                {pendingOvertime.length}
              </Badge>
            )}
          </TabsTab>
        </TabsList>

        <TabsPanel value="today" className="pt-4">
          {error ? unavailable : <AttendanceTable
            rows={todayList ?? []}
            loading={todayList === null}
            showDay={false}
            canManage={canManage}
            onCorrect={setCorrecting}
            onHistory={setHistoryOf}
            emptyTitle="Nobody has clocked in yet today"
            emptyBody={
              canManage
                ? 'Clock-ins show here as employees arrive. If someone could not clock in, record it for them.'
                : 'Clock-ins show here as employees arrive.'
            }
            emptyAction={recordButton}
          />}
        </TabsPanel>

        <TabsPanel value="records" className="flex flex-col gap-4 pt-4">
          <div className="grid gap-3 sm:grid-cols-3">
            <TextField name="from" label="From" type="date" value={from} onChange={(event) => {
              setFrom(event.target.value)
              setRecordsPage(1)
            }} />
            <TextField name="to" label="To" type="date" value={to} onChange={(event) => {
              setTo(event.target.value)
              setRecordsPage(1)
            }} />
            <Field name="employee">
              <FieldLabel>Employee</FieldLabel>
              <Select
                items={[{ value: 'all', label: 'Everyone' }, ...employees.map((person) => ({ value: String(person.id), label: person.name }))]}
                value={employeeFilter}
                onValueChange={(next) => {
                  setEmployeeFilter(next ?? 'all')
                  setRecordsPage(1)
                }}
              >
                <SelectTrigger aria-label="Filter by employee">
                  <SelectValue placeholder="Everyone" />
                </SelectTrigger>
                <SelectPopup>
                  <SelectItem value="all">Everyone</SelectItem>
                  {employees.map((person) => (
                    <SelectItem key={person.id} value={String(person.id)}>
                      {person.name}
                    </SelectItem>
                  ))}
                </SelectPopup>
              </Select>
            </Field>
          </div>
          {records !== null && records.length > 0 && (
            <p className="text-muted-foreground text-sm" aria-live="polite">
              {recordsMeta?.total ?? records.length} {(recordsMeta?.total ?? records.length) === 1 ? 'entry' : 'entries'} between {workDayLabel(from)} and {workDayLabel(to)}
            </p>
          )}
          {error ? unavailable : <AttendanceTable
            rows={records ?? []}
            loading={records === null}
            showDay
            canManage={canManage}
            onCorrect={setCorrecting}
            onHistory={setHistoryOf}
            emptyTitle="No attendance in this range"
            emptyBody={
              canManage
                ? 'Try a wider date range or another employee, or record a day that was missed.'
                : 'Try a wider date range or another employee.'
            }
            emptyAction={recordButton}
          />}
          {!error && <PageControls meta={recordsMeta} label="Attendance records" onPage={setRecordsPage} />}
        </TabsPanel>

        <TabsPanel value="overtime" className="pt-4">
          {error ? unavailable : <OvertimePanel rows={overtime ?? []} loading={overtime === null} canApprove={canApprove} onDecide={setDeciding} />}
        </TabsPanel>
      </Tabs>

      {deskOpen && (
        <DeskEntryDialog
          gymId={gymId}
          employees={employees}
          onClose={() => setDeskOpen(false)}
          onSaved={() => {
            setDeskOpen(false)
            refresh()
          }}
        />
      )}

      {correcting && (
        <CorrectionDialog
          gymId={gymId}
          row={correcting}
          onClose={() => setCorrecting(null)}
          onSaved={() => {
            setCorrecting(null)
            refresh()
          }}
        />
      )}

      <Dialog open={historyOf !== null} onOpenChange={(open) => !open && setHistoryOf(null)}>
        <DialogPopup className="sm:max-w-xl">
          <DialogHeader>
            <DialogTitle>Correction history</DialogTitle>
            <DialogDescription>
              {historyOf?.user_name} on {historyOf ? longDayLabel(historyOf.work_date) : ''}. Every correction keeps who changed it and why.
            </DialogDescription>
          </DialogHeader>
          <DialogPanel>
            {history === null ? (
              <div aria-busy="true" className="flex flex-col gap-2">
                <Skeleton className="h-20" />
                <Skeleton className="h-20" />
              </div>
            ) : history.length === 0 ? (
              <p className="text-muted-foreground text-sm">Nobody has corrected this day.</p>
            ) : (
              <ol className="flex flex-col divide-y rounded-xl border">
                {history.map((change) => (
                  <li key={change.id} className="flex flex-col gap-2 p-3">
                    <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                      <span className="font-medium text-sm">{change.changed_by_name ?? 'Unknown person'}</span>
                      {change.created_at && (
                        <time className="text-muted-foreground text-xs tabular-nums" dateTime={change.created_at}>
                          {dateTimeLabel(change.created_at)}
                        </time>
                      )}
                    </div>
                    <p className="break-words text-sm">{change.reason}</p>
                    <dl className="grid grid-cols-[4rem_1fr] gap-x-3 gap-y-1 text-xs">
                      <dt className="text-muted-foreground">Before</dt>
                      <dd className="tabular-nums">{describeSnapshot(change.before)}</dd>
                      <dt className="text-muted-foreground">After</dt>
                      <dd className="tabular-nums">{describeSnapshot(change.after)}</dd>
                    </dl>
                  </li>
                ))}
              </ol>
            )}
          </DialogPanel>
          <DialogFooter>
            <Button variant="outline" onClick={() => setHistoryOf(null)}>
              Close
            </Button>
          </DialogFooter>
        </DialogPopup>
      </Dialog>

      {decisionDialog}
    </div>
  )
}

/** The page's focal point: today's live state in one strip. */
function TodayStrip({ rows, loading }: { rows: StaffAttendance[]; loading: boolean }) {
  const stillIn = rows.filter((row) => row.clock_in_time && !row.clock_out_time)
  const count = (status: AttendanceStatus) => rows.filter((row) => row.status === status).length
  const items = [
    { key: 'present', label: 'Present', value: count('present'), dot: ATTENDANCE_DOT.present },
    { key: 'late', label: 'Late', value: count('late'), dot: ATTENDANCE_DOT.late },
    { key: 'absent', label: 'Absent', value: count('absent'), dot: ATTENDANCE_DOT.absent },
    { key: 'in', label: 'Still in', value: stillIn.length, dot: 'bg-info' },
  ]
  const names = stillIn.map((row) => row.user_name ?? 'Unknown employee')
  const shown = names.slice(0, 4)

  return (
    <section aria-labelledby="attendance-today" className="flex flex-col gap-3 rounded-2xl border bg-card p-4 shadow-xs/5 sm:p-5">
      <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 id="attendance-today" className="font-heading font-semibold text-lg">
          Today
        </h2>
        <p className="text-muted-foreground text-sm">{longDayLabel(todayIso())}</p>
      </div>

      <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-xl border bg-border sm:grid-cols-4" aria-busy={loading || undefined}>
        {items.map((item) => (
          <div key={item.key} className="flex min-h-14 items-center justify-between gap-3 bg-card px-3 py-2.5">
            <dt className="flex items-center gap-2 text-sm">
              <span aria-hidden className={cn('size-2 shrink-0 rounded-full', item.dot)} />
              {item.label}
            </dt>
            <dd className="font-semibold text-xl tabular-nums">{loading ? <Skeleton className="h-6 w-6" /> : item.value}</dd>
          </div>
        ))}
      </dl>

      {!loading && (
        <p className="text-muted-foreground text-sm">
          {rows.length === 0
            ? 'Nobody is recorded yet today.'
            : names.length === 0
              ? 'Everyone recorded today has clocked out.'
              : `In now: ${shown.join(', ')}${names.length > shown.length ? ` and ${names.length - shown.length} more` : ''}.`}
        </p>
      )}
    </section>
  )
}

function shiftLabel(row: StaffAttendance): string {
  if (!row.scheduled_start && !row.scheduled_end) return 'No shift set'
  return `${clockLabel(row.scheduled_start) || 'Not set'} to ${clockLabel(row.scheduled_end) || 'Not set'}`
}

function outLabel(row: StaffAttendance): string {
  if (row.clock_out_time) return clockLabel(row.clock_out_time)
  if (row.clock_in_time) return row.missing_clock_out ? 'Missing' : 'Still in'
  return 'None'
}

/** Late, undertime and who recorded it, as one quiet line. */
function detailParts(row: StaffAttendance): string[] {
  const parts: string[] = []
  if (row.late_minutes > 0) parts.push(`${minutesLabel(row.late_minutes)} late`)
  if (row.undertime_minutes > 0) parts.push(`${minutesLabel(row.undertime_minutes)} short`)
  if (row.source === 'desk') parts.push(`Recorded by ${row.recorded_by_name ?? 'the desk'}`)
  return parts
}

function PageControls({ meta, label, onPage }: { meta: PageMeta | null; label: string; onPage: (page: number) => void }) {
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

function AttendanceTable({
  rows,
  loading,
  showDay,
  canManage,
  onCorrect,
  onHistory,
  emptyTitle,
  emptyBody,
  emptyAction,
}: {
  rows: StaffAttendance[]
  loading: boolean
  showDay: boolean
  canManage: boolean
  onCorrect: (row: StaffAttendance) => void
  onHistory: (row: StaffAttendance) => void
  emptyTitle: string
  emptyBody: string
  emptyAction: React.ReactNode
}) {
  if (loading) {
    return (
      <div className="flex flex-col gap-2" aria-busy="true">
        <Skeleton className="h-12" />
        <Skeleton className="h-12" />
        <Skeleton className="h-12" />
      </div>
    )
  }

  if (rows.length === 0) {
    return (
      <Empty className="rounded-xl border">
        <EmptyHeader>
          <EmptyTitle>{emptyTitle}</EmptyTitle>
          <EmptyDescription>{emptyBody}</EmptyDescription>
        </EmptyHeader>
        {emptyAction && <EmptyContent>{emptyAction}</EmptyContent>}
      </Empty>
    )
  }

  const historyLabel = (row: StaffAttendance) => (row.changes_count ? `History (${row.changes_count})` : 'History')

  return (
    <>
      <ul className="flex flex-col divide-y rounded-xl border bg-card md:hidden">
        {rows.map((row) => {
          const badge = ATTENDANCE_STATUS[row.status]
          const details = detailParts(row)
          return (
            <li key={row.id} className="flex flex-col gap-3 p-4">
              <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-0.5">
                  <span className="break-words font-medium">{row.user_name ?? 'Unknown employee'}</span>
                  <span className="text-muted-foreground text-sm">{showDay ? workDayLabel(row.work_date) : `Shift ${shiftLabel(row)}`}</span>
                </div>
                <div className="flex shrink-0 flex-col items-end gap-1">
                  <Badge variant={badge.variant}>{badge.label}</Badge>
                  {row.missing_clock_out && <Badge variant="error">Missing clock-out</Badge>}
                </div>
              </div>
              <dl className="grid grid-cols-3 gap-2 text-sm">
                <div className="flex flex-col gap-0.5">
                  <dt className="text-muted-foreground text-xs">In</dt>
                  <dd className="tabular-nums">{row.clock_in_time ? clockLabel(row.clock_in_time) : 'None'}</dd>
                </div>
                <div className="flex flex-col gap-0.5">
                  <dt className="text-muted-foreground text-xs">Out</dt>
                  <dd className="tabular-nums">{outLabel(row)}</dd>
                </div>
                <div className="flex flex-col gap-0.5">
                  <dt className="text-muted-foreground text-xs">Worked</dt>
                  <dd className="tabular-nums">{minutesLabel(row.worked_minutes)}</dd>
                </div>
              </dl>
              {(details.length > 0 || showDay) && (
                <p className="text-muted-foreground text-xs">{[showDay ? `Shift ${shiftLabel(row)}` : null, ...details].filter(Boolean).join(' · ')}</p>
              )}
              {row.explanation && <p className="break-words text-sm">Employee note: {row.explanation}</p>}
              <div className="flex flex-wrap gap-2">
                <Button size="sm" variant="ghost" onClick={() => onHistory(row)}>
                  <History aria-hidden />
                  {historyLabel(row)}
                </Button>
                {canManage && (
                  <Button size="sm" variant="outline" onClick={() => onCorrect(row)}>
                    Correct
                  </Button>
                )}
              </div>
            </li>
          )
        })}
      </ul>

      <div className="hidden md:block">
        <Table variant="card" className={showDay ? 'min-w-[56rem]' : 'min-w-[50rem]'}>
          <TableHeader>
            <TableRow>
              <TableHead>Employee</TableHead>
              {showDay && <TableHead>Day</TableHead>}
              <TableHead>Shift</TableHead>
              <TableHead className="text-right">In</TableHead>
              <TableHead className="text-right">Out</TableHead>
              <TableHead className="text-right">Worked</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>
                <span className="sr-only">Actions</span>
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((row) => {
              const badge = ATTENDANCE_STATUS[row.status]
              const details = detailParts(row)
              return (
                <TableRow key={row.id}>
                  <TableCell>
                    <div className="flex flex-col gap-1">
                      <span className="font-medium">{row.user_name ?? 'Unknown employee'}</span>
                      {row.explanation && (
                        <span className="max-w-64 truncate text-muted-foreground text-xs" title={row.explanation}>
                          Note: {row.explanation}
                        </span>
                      )}
                    </div>
                  </TableCell>
                  {showDay && (
                    <TableCell>
                      <time dateTime={row.work_date}>{workDayLabel(row.work_date)}</time>
                    </TableCell>
                  )}
                  <TableCell className="text-muted-foreground tabular-nums">{shiftLabel(row)}</TableCell>
                  <TableCell className="text-right tabular-nums">
                    {row.clock_in_time ? clockLabel(row.clock_in_time) : <span className="text-muted-foreground">None</span>}
                  </TableCell>
                  <TableCell className={cn('text-right tabular-nums', !row.clock_out_time && 'text-muted-foreground')}>{outLabel(row)}</TableCell>
                  <TableCell className="text-right font-medium tabular-nums">{minutesLabel(row.worked_minutes)}</TableCell>
                  <TableCell>
                    <div className="flex flex-col items-start gap-1">
                      <div className="flex flex-wrap gap-1">
                        <Badge variant={badge.variant}>{badge.label}</Badge>
                        {row.missing_clock_out && <Badge variant="error">Missing clock-out</Badge>}
                      </div>
                      {details.length > 0 && <span className="text-muted-foreground text-xs">{details.join(' · ')}</span>}
                    </div>
                  </TableCell>
                  <TableCell>
                    <div className="flex justify-end gap-1.5">
                      <Button size="xs" variant="ghost" onClick={() => onHistory(row)} aria-label={`${historyLabel(row)} for ${row.user_name ?? 'this employee'}`}>
                        {historyLabel(row)}
                      </Button>
                      {canManage && (
                        <Button size="xs" variant="outline" onClick={() => onCorrect(row)} aria-label={`Correct ${row.user_name ?? 'this employee'}, ${workDayLabel(row.work_date)}`}>
                          Correct
                        </Button>
                      )}
                    </div>
                  </TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      </div>
    </>
  )
}

function approvedCell(row: OvertimeRequest): { text: string; muted: boolean } {
  if (row.approved_minutes !== null) return { text: minutesLabel(row.approved_minutes), muted: false }
  if (row.status === 'rejected') return { text: 'None', muted: true }
  return { text: 'Not yet', muted: true }
}

function OvertimePanel({
  rows,
  loading,
  canApprove,
  onDecide,
}: {
  rows: OvertimeRequest[]
  loading: boolean
  canApprove: boolean
  onDecide: (row: OvertimeRequest) => void
}) {
  if (loading) {
    return (
      <div className="flex flex-col gap-2" aria-busy="true">
        <Skeleton className="h-12" />
        <Skeleton className="h-12" />
      </div>
    )
  }

  if (rows.length === 0) {
    return (
      <Empty className="rounded-xl border">
        <EmptyHeader>
          <EmptyTitle>No overtime requests</EmptyTitle>
          <EmptyDescription>Extra time appears here after a clock-out or a manual request.</EmptyDescription>
        </EmptyHeader>
      </Empty>
    )
  }

  const pending = rows.filter((row) => row.status === 'pending')
  const decided = rows.filter((row) => row.status !== 'pending')

  return (
    <div className="flex flex-col gap-8">
      <p className="text-muted-foreground text-sm">
        Only approved minutes are paid.{canApprove ? '' : ' You can see requests, but someone with overtime approval decides them.'}
      </p>

      <section aria-labelledby="overtime-pending" className="flex flex-col gap-3">
        <h2 id="overtime-pending" className="flex items-center gap-2 font-heading font-semibold text-base">
          Waiting for a decision
          <Badge variant={pending.length > 0 ? 'warning' : 'outline'}>{pending.length}</Badge>
        </h2>
        {pending.length === 0 ? (
          <p className="rounded-xl border px-4 py-3 text-muted-foreground text-sm">Nothing is waiting. New requests show here first.</p>
        ) : (
          <OvertimeTable rows={pending} canApprove={canApprove} onDecide={onDecide} />
        )}
      </section>

      {decided.length > 0 && (
        <section aria-labelledby="overtime-decided" className="flex flex-col gap-3">
          <h2 id="overtime-decided" className="font-heading font-semibold text-base">
            Decided
          </h2>
          <OvertimeTable rows={decided} canApprove={canApprove} onDecide={onDecide} />
        </section>
      )}
    </div>
  )
}

function OvertimeTable({ rows, canApprove, onDecide }: { rows: OvertimeRequest[]; canApprove: boolean; onDecide: (row: OvertimeRequest) => void }) {
  const showAction = canApprove && rows.some((row) => row.status === 'pending')
  const decisionLine = (row: OvertimeRequest) => (row.decision_note ? `Note from ${row.decided_by_name ?? 'the approver'}: ${row.decision_note}` : '')

  return (
    <>
      <ul className="flex flex-col divide-y rounded-xl border bg-card md:hidden">
        {rows.map((row) => {
          const badge = OVERTIME_STATUS[row.status]
          const approved = approvedCell(row)
          return (
            <li key={row.id} className="flex flex-col gap-3 p-4">
              <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-0.5">
                  <span className="break-words font-medium">{row.user_name ?? 'Unknown employee'}</span>
                  <span className="text-muted-foreground text-sm">
                    {workDayLabel(row.work_date)} · {OVERTIME_KIND[row.kind]}
                  </span>
                </div>
                <Badge variant={badge.variant} className="shrink-0">
                  {badge.label}
                </Badge>
              </div>
              <dl className="grid grid-cols-2 gap-2 text-sm">
                <div className="flex flex-col gap-0.5">
                  <dt className="text-muted-foreground text-xs">Asked for</dt>
                  <dd className="tabular-nums">{minutesLabel(row.requested_minutes)}</dd>
                </div>
                <div className="flex flex-col gap-0.5">
                  <dt className="text-muted-foreground text-xs">Approved</dt>
                  <dd className={cn('tabular-nums', approved.muted && 'text-muted-foreground')}>{approved.text}</dd>
                </div>
              </dl>
              {row.reason && <p className="break-words text-sm">{row.reason}</p>}
              {row.decision_note && <p className="break-words text-muted-foreground text-sm">{decisionLine(row)}</p>}
              {canApprove && row.status === 'pending' && (
                <Button size="sm" variant="outline" className="self-start" onClick={() => onDecide(row)}>
                  Review request
                </Button>
              )}
            </li>
          )
        })}
      </ul>

      <div className="hidden md:block">
        <Table variant="card" className="min-w-[52rem]">
          <TableHeader>
            <TableRow>
              <TableHead>Employee</TableHead>
              <TableHead>Day</TableHead>
              <TableHead>Kind</TableHead>
              <TableHead className="text-right">Asked for</TableHead>
              <TableHead className="text-right">Approved</TableHead>
              <TableHead>Status</TableHead>
              {showAction && (
                <TableHead>
                  <span className="sr-only">Actions</span>
                </TableHead>
              )}
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((row) => {
              const badge = OVERTIME_STATUS[row.status]
              const approved = approvedCell(row)
              return (
                <TableRow key={row.id}>
                  <TableCell>
                    <div className="flex flex-col gap-1">
                      <span className="font-medium">{row.user_name ?? 'Unknown employee'}</span>
                      {row.reason && (
                        <span className="max-w-72 truncate text-muted-foreground text-xs" title={row.reason}>
                          {row.reason}
                        </span>
                      )}
                    </div>
                  </TableCell>
                  <TableCell>
                    <time dateTime={row.work_date}>{workDayLabel(row.work_date)}</time>
                  </TableCell>
                  <TableCell>{OVERTIME_KIND[row.kind]}</TableCell>
                  <TableCell className="text-right tabular-nums">{minutesLabel(row.requested_minutes)}</TableCell>
                  <TableCell className={cn('text-right font-medium tabular-nums', approved.muted && 'font-normal text-muted-foreground')}>{approved.text}</TableCell>
                  <TableCell>
                    <div className="flex flex-col items-start gap-1">
                      <Badge variant={badge.variant}>{badge.label}</Badge>
                      {row.decision_note && (
                        <span className="max-w-56 truncate text-muted-foreground text-xs" title={decisionLine(row)}>
                          {decisionLine(row)}
                        </span>
                      )}
                    </div>
                  </TableCell>
                  {showAction && (
                    <TableCell>
                      <div className="flex justify-end">
                        {row.status === 'pending' && (
                          <Button size="xs" variant="outline" onClick={() => onDecide(row)} aria-label={`Review overtime for ${row.user_name ?? 'this employee'}, ${workDayLabel(row.work_date)}`}>
                            Review
                          </Button>
                        )}
                      </div>
                    </TableCell>
                  )}
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      </div>
    </>
  )
}

function DeskEntryDialog({
  gymId,
  employees,
  onClose,
  onSaved,
}: {
  gymId: number
  employees: EmployeeChoice[]
  onClose: () => void
  onSaved: () => void
}) {
  const [employeeId, setEmployeeId] = useState('')
  const [workDate, setWorkDate] = useState(todayIso)
  const [clockIn, setClockIn] = useState('')
  const [clockOut, setClockOut] = useState('')
  const [note, setNote] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setErrors({})
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/staff-attendance`, {
        body: {
          user_id: Number(employeeId),
          work_date: workDate,
          clock_in_time: clockIn || null,
          clock_out_time: clockOut || null,
          note: note || null,
        },
      })
      toastManager.add({ title: 'Attendance recorded.', type: 'success' })
      onSaved()
    } catch (error) {
      const fieldErrors = error instanceof ApiError ? error.errors : {}
      setErrors(fieldErrors)
      if (!(error instanceof ApiError) || Object.keys(fieldErrors).length === 0) {
        toastManager.add({ title: error instanceof ApiError ? error.message : 'Could not record the attendance.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Record attendance</DialogTitle>
            <DialogDescription>Use this when the employee could not clock in themselves.</DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-6">
            <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_11rem]">
              <Field name="user_id">
                <FieldLabel>Employee</FieldLabel>
                <Select
                  items={employees.map((person) => ({ value: String(person.id), label: person.name }))}
                  value={employeeId || null}
                  onValueChange={(next) => setEmployeeId(next ?? '')}
                  disabled={employees.length === 0}
                >
                  <SelectTrigger aria-label="Employee">
                    <SelectValue placeholder="Choose an employee" />
                  </SelectTrigger>
                  <SelectPopup>
                    {employees.map((person) => (
                      <SelectItem key={person.id} value={String(person.id)}>
                        {person.name}
                      </SelectItem>
                    ))}
                  </SelectPopup>
                </Select>
                {employees.length === 0 && <FieldDescription>No employees are listed yet. Add staff first.</FieldDescription>}
                <FieldError />
              </Field>
              <TextField name="work_date" label="Day" type="date" value={workDate} onChange={(event) => setWorkDate(event.target.value)} required />
            </div>

            <fieldset className="flex flex-col gap-3">
              <legend className="mb-3 font-medium text-sm">Clock times</legend>
              <div className="grid grid-cols-2 gap-4">
                <TextField name="clock_in_time" label="Clock in" type="time" value={clockIn} onChange={(event) => setClockIn(event.target.value)} />
                <TextField name="clock_out_time" label="Clock out" type="time" value={clockOut} onChange={(event) => setClockOut(event.target.value)} />
              </div>
              <p className="text-muted-foreground text-sm">
                Leave clock out empty if they are still at work. Late and short time are worked out from their schedule and the gym's staff grace period when you save.
              </p>
            </fieldset>

            <Field name="note">
              <FieldLabel>
                Note <span className="font-normal text-muted-foreground">(optional)</span>
              </FieldLabel>
              <Textarea name="note" rows={2} maxLength={1000} value={note} onChange={(event) => setNote(event.target.value)} placeholder="Why the desk is recording this day." />
              <FieldError />
            </Field>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={!employeeId || !workDate}>
              Record attendance
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function CorrectionDialog({
  gymId,
  row,
  onClose,
  onSaved,
}: {
  gymId: number
  row: StaffAttendance
  onClose: () => void
  onSaved: () => void
}) {
  const [clockIn, setClockIn] = useState(row.clock_in_time ?? '')
  const [clockOut, setClockOut] = useState(row.clock_out_time ?? '')
  const [scheduledStart, setScheduledStart] = useState(row.scheduled_start ?? '')
  const [scheduledEnd, setScheduledEnd] = useState(row.scheduled_end ?? '')
  const [reason, setReason] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)
  const badge = ATTENDANCE_STATUS[row.status]

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setErrors({})
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/staff-attendance/${row.id}`, {
        method: 'PUT',
        body: {
          clock_in_time: clockIn || null,
          clock_out_time: clockOut || null,
          scheduled_start: scheduledStart || null,
          scheduled_end: scheduledEnd || null,
          reason,
        },
      })
      toastManager.add({ title: 'Attendance corrected.', type: 'success' })
      onSaved()
    } catch (error) {
      const fieldErrors = error instanceof ApiError ? error.errors : {}
      setErrors(fieldErrors)
      if (!(error instanceof ApiError) || Object.keys(fieldErrors).length === 0) {
        toastManager.add({ title: error instanceof ApiError ? error.message : 'Could not correct the attendance.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Correct attendance</DialogTitle>
            <DialogDescription>
              {row.user_name} on {longDayLabel(row.work_date)}. The before and after values are kept with your name.
            </DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-6">
            <dl className="grid grid-cols-2 gap-3 rounded-xl border bg-muted/40 p-3 text-sm sm:grid-cols-4">
              <div className="flex flex-col gap-1">
                <dt className="text-muted-foreground text-xs">Now</dt>
                <dd>
                  <Badge variant={badge.variant}>{badge.label}</Badge>
                </dd>
              </div>
              <div className="flex flex-col gap-1">
                <dt className="text-muted-foreground text-xs">In</dt>
                <dd className="tabular-nums">{row.clock_in_time ? clockLabel(row.clock_in_time) : 'None'}</dd>
              </div>
              <div className="flex flex-col gap-1">
                <dt className="text-muted-foreground text-xs">Out</dt>
                <dd className="tabular-nums">{outLabel(row)}</dd>
              </div>
              <div className="flex flex-col gap-1">
                <dt className="text-muted-foreground text-xs">Worked</dt>
                <dd className="tabular-nums">{minutesLabel(row.worked_minutes)}</dd>
              </div>
            </dl>

            <fieldset>
              <legend className="mb-3 font-medium text-sm">Clock times</legend>
              <div className="grid grid-cols-2 gap-4">
                <TextField name="clock_in_time" label="Clock in" type="time" value={clockIn} onChange={(event) => setClockIn(event.target.value)} />
                <TextField name="clock_out_time" label="Clock out" type="time" value={clockOut} onChange={(event) => setClockOut(event.target.value)} />
              </div>
            </fieldset>

            <fieldset className="flex flex-col gap-3">
              <legend className="mb-3 font-medium text-sm">Shift for this day</legend>
              <div className="grid grid-cols-2 gap-4">
                <TextField name="scheduled_start" label="Shift start" type="time" value={scheduledStart} onChange={(event) => setScheduledStart(event.target.value)} />
                <TextField name="scheduled_end" label="Shift end" type="time" value={scheduledEnd} onChange={(event) => setScheduledEnd(event.target.value)} />
              </div>
              <p className="text-muted-foreground text-sm">Change the shift only if this day's schedule was wrong. The status is worked out again from the new times when you save.</p>
            </fieldset>

            <Field name="reason">
              <FieldLabel>Reason</FieldLabel>
              <Textarea name="reason" rows={2} maxLength={255} value={reason} onChange={(event) => setReason(event.target.value)} required placeholder="Why this day needs correcting." />
              <FieldDescription>Shown in the correction history.</FieldDescription>
              <FieldError />
            </Field>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={!reason.trim()}>
              Save correction
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function DecisionDialog({
  gymId,
  request,
  onClose,
  onSaved,
}: {
  gymId: number
  request: OvertimeRequest
  onClose: () => void
  onSaved: () => void
}) {
  const [decision, setDecision] = useState<'approve' | 'reject'>('approve')
  const [minutes, setMinutes] = useState(String(request.requested_minutes))
  const [note, setNote] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setErrors({})
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/overtime-requests/${request.id}/decision`, {
        body: {
          decision,
          minutes: decision === 'approve' ? Number(minutes) : null,
          note: note || null,
        },
      })
      toastManager.add({ title: decision === 'approve' ? 'Overtime approved.' : 'Overtime rejected.', type: 'success' })
      onSaved()
    } catch (error) {
      const fieldErrors = error instanceof ApiError ? error.errors : {}
      setErrors(fieldErrors)
      if (!(error instanceof ApiError) || Object.keys(fieldErrors).length === 0) {
        toastManager.add({ title: error instanceof ApiError ? error.message : 'Could not decide the request.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  const approvedNumber = Number(minutes)
  const approvedHint =
    minutes !== '' && Number.isFinite(approvedNumber) && approvedNumber >= 0
      ? `Up to ${request.requested_minutes} minutes. That is ${minutesLabel(approvedNumber)}.`
      : `Up to ${request.requested_minutes} minutes.`

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Decide overtime</DialogTitle>
            <DialogDescription>
              {request.user_name} asked for {minutesLabel(request.requested_minutes)} on {longDayLabel(request.work_date)}.
            </DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-6">
            <dl className="grid grid-cols-2 gap-3 rounded-xl border bg-muted/40 p-3 text-sm">
              <div className="flex flex-col gap-0.5">
                <dt className="text-muted-foreground text-xs">Kind</dt>
                <dd>{OVERTIME_KIND[request.kind]}</dd>
              </div>
              <div className="flex flex-col gap-0.5">
                <dt className="text-muted-foreground text-xs">Asked for</dt>
                <dd className="font-medium tabular-nums">{minutesLabel(request.requested_minutes)}</dd>
              </div>
              <div className="col-span-2 flex flex-col gap-0.5">
                <dt className="text-muted-foreground text-xs">Reason</dt>
                <dd className="break-words">{request.reason || 'No reason given.'}</dd>
              </div>
            </dl>

            <div className="flex flex-col gap-2">
              <span id="decision-label" className="font-medium text-sm">
                Decision
              </span>
              <div className="grid grid-cols-2 gap-2" role="group" aria-labelledby="decision-label">
                <Button type="button" variant={decision === 'approve' ? 'default' : 'outline'} aria-pressed={decision === 'approve'} onClick={() => setDecision('approve')}>
                  <Check aria-hidden /> Approve
                </Button>
                <Button type="button" variant={decision === 'reject' ? 'destructive' : 'outline'} aria-pressed={decision === 'reject'} onClick={() => setDecision('reject')}>
                  <X aria-hidden /> Reject
                </Button>
              </div>
            </div>

            {decision === 'approve' && (
              <TextField
                name="minutes"
                label="Approved minutes"
                type="number"
                inputMode="numeric"
                min={0}
                max={request.requested_minutes}
                value={minutes}
                onChange={(event) => setMinutes(event.target.value)}
                description={approvedHint}
              />
            )}
            <Field name="note">
              <FieldLabel>
                Note <span className="font-normal text-muted-foreground">{decision === 'reject' ? '(tell them why)' : '(optional)'}</span>
              </FieldLabel>
              <Textarea name="note" rows={2} maxLength={1000} value={note} onChange={(event) => setNote(event.target.value)} placeholder="Optional for approval, useful for a rejection." />
              <FieldDescription>The employee sees this note with the decision.</FieldDescription>
              <FieldError />
            </Field>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" variant={decision === 'reject' ? 'destructive' : 'default'} loading={busy}>
              {decision === 'approve' ? 'Approve overtime' : 'Reject overtime'}
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function describeSnapshot(snapshot: Record<string, unknown>): string {
  const time = (value: unknown) => (typeof value === 'string' && value ? clockLabel(value) : 'not set')
  const status = typeof snapshot.status === 'string' ? (ATTENDANCE_STATUS[snapshot.status as AttendanceStatus]?.label ?? snapshot.status) : 'No status'
  const shift = snapshot.scheduled_start || snapshot.scheduled_end ? `, shift ${time(snapshot.scheduled_start)} to ${time(snapshot.scheduled_end)}` : ''
  return `${status}, in ${time(snapshot.clock_in_time)}, out ${time(snapshot.clock_out_time)}${shift}`
}
