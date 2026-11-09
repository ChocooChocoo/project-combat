import { useEffect, useState } from 'react'
import { Plus } from 'lucide-react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { TextField } from '@/components/text-field'
import { isoDay } from '@/components/leave-calendar'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import {
  ATTENDANCE_STATUS,
  OVERTIME_KIND,
  OVERTIME_STATUS,
  clockLabel,
  longDayLabel,
  minutesLabel,
  workDayLabel,
  type MyAttendanceData,
  type OvertimeRequest,
  type StaffAttendance,
} from '@/lib/hr'

/** Today's regular hours from the employee's weekly schedule, used before there is a clock-in. */
export type TodayShift = { start: string | null; end: string | null; dayOff: boolean }

/** R-56 and R-57 on My work: clock in and out, my records and my overtime requests. */
export function MyAttendance({ gymId, shift = null }: { gymId: number; shift?: TodayShift | null }) {
  const [data, setData] = useState<MyAttendanceData | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [busy, setBusy] = useState('')
  const [explaining, setExplaining] = useState<StaffAttendance | null>(null)
  const [requesting, setRequesting] = useState(false)

  useEffect(() => {
    let live = true
    setError('')
    api<MyAttendanceData>(`/gyms/${gymId}/my-attendance`)
      .then((result) => {
        if (live) setData(result)
      })
      .catch((reason) => {
        if (live) setError(reason instanceof ApiError ? reason.message : 'Your attendance could not be loaded.')
      })
    return () => {
      live = false
    }
  }, [gymId, version])

  function refresh() {
    setVersion((current) => current + 1)
  }

  async function clock(action: 'clock-in' | 'clock-out') {
    setBusy(action)
    try {
      await api(`/gyms/${gymId}/my-attendance/${action}`, { method: 'POST' })
      refresh()
      toastManager.add({ title: action === 'clock-in' ? 'Clocked in.' : 'Clocked out.', type: 'success' })
    } catch (reason) {
      toastManager.add({ title: reason instanceof ApiError ? reason.message : 'The clock could not be recorded.', type: 'error' })
    } finally {
      setBusy('')
    }
  }

  if (error) {
    return (
      <Alert variant="error">
        <AlertTitle>Your attendance could not be loaded</AlertTitle>
        <AlertDescription>{error}</AlertDescription>
        <div className="pt-2">
          <Button size="sm" variant="outline" onClick={refresh}>
            Try again
          </Button>
        </div>
      </Alert>
    )
  }

  if (data === null) {
    return (
      <div className="flex flex-col gap-4" aria-busy="true">
        <Skeleton className="h-44 rounded-2xl" />
        <div className="grid gap-4 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
          <Skeleton className="h-64 rounded-2xl" />
          <Skeleton className="h-64 rounded-2xl" />
        </div>
      </div>
    )
  }

  const today = data.today
  const overtime = [...data.overtime.filter((row) => row.status === 'pending'), ...data.overtime.filter((row) => row.status !== 'pending')].slice(0, 6)
  const pending = overtime.filter((row) => row.status === 'pending')
  const decided = overtime.filter((row) => row.status !== 'pending')

  return (
    <div className="flex flex-col gap-4">
      <ClockHero
        today={today}
        shift={shift}
        graceMinutes={data.settings.grace_minutes}
        busy={busy}
        onClockIn={() => clock('clock-in')}
        onClockOut={() => clock('clock-out')}
      />

      <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <Card>
          <CardHeader>
            <CardTitle render={<h2 />}>My attendance</CardTitle>
            <CardDescription>The last 31 days. You can explain an absence or a late day.</CardDescription>
          </CardHeader>
          <CardPanel>
            {data.attendance.length === 0 ? (
              <Empty className="py-8 md:py-10">
                <EmptyHeader>
                  <EmptyTitle>No attendance yet</EmptyTitle>
                  <EmptyDescription>Clock in on a working day to start your record.</EmptyDescription>
                </EmptyHeader>
              </Empty>
            ) : (
              <ol className="-my-3 flex flex-col divide-y">
                {data.attendance.map((row) => (
                  <HistoryRow key={row.id} row={row} onExplain={() => setExplaining(row)} />
                ))}
              </ol>
            )}
          </CardPanel>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle render={<h2 />}>My overtime</CardTitle>
            <CardDescription>Extra time needs approval before it is paid.</CardDescription>
            <CardAction>
              <Button size="sm" variant="outline" onClick={() => setRequesting(true)}>
                <Plus aria-hidden />
                Request
                <span className="sr-only"> overtime</span>
              </Button>
            </CardAction>
          </CardHeader>
          <CardPanel>
            {data.overtime.length === 0 ? (
              <Empty className="py-8 md:py-10">
                <EmptyHeader>
                  <EmptyTitle>No overtime requests</EmptyTitle>
                  <EmptyDescription>Requests appear here after a late clock-out or when you file one.</EmptyDescription>
                </EmptyHeader>
              </Empty>
            ) : (
              <div className="flex flex-col gap-5">
                {pending.length > 0 && (
                  <section aria-labelledby="my-overtime-pending" className="flex flex-col gap-1">
                    <h3 id="my-overtime-pending" className="font-medium text-muted-foreground text-xs">
                      Waiting for a decision
                    </h3>
                    <ul className="flex flex-col divide-y">
                      {pending.map((row) => (
                        <OvertimeRow key={row.id} row={row} />
                      ))}
                    </ul>
                  </section>
                )}
                {decided.length > 0 && (
                  <section aria-labelledby="my-overtime-decided" className="flex flex-col gap-1">
                    <h3 id="my-overtime-decided" className="font-medium text-muted-foreground text-xs">
                      Decided
                    </h3>
                    <ul className="flex flex-col divide-y">
                      {decided.map((row) => (
                        <OvertimeRow key={row.id} row={row} />
                      ))}
                    </ul>
                  </section>
                )}
              </div>
            )}
          </CardPanel>
        </Card>
      </div>

      {explaining && (
        <ExplanationDialog
          gymId={gymId}
          row={explaining}
          onClose={() => setExplaining(null)}
          onSaved={() => {
            setExplaining(null)
            refresh()
          }}
        />
      )}

      {requesting && (
        <OvertimeDialog
          gymId={gymId}
          onClose={() => setRequesting(false)}
          onSaved={() => {
            setRequesting(false)
            refresh()
          }}
        />
      )}
    </div>
  )
}

/** Gym-local time updates each second without interrupting screen readers. */
function LiveClock() {
  const [now, setNow] = useState(() => new Date())
  useEffect(() => {
    const id = window.setInterval(() => setNow(new Date()), 1_000)
    return () => window.clearInterval(id)
  }, [])
  const manilaDay = now.toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' })
  return (
    <div className="flex flex-col gap-1 text-center" aria-live="off">
      <p className="text-muted-foreground text-xs">{longDayLabel(manilaDay)}</p>
      <time dateTime={now.toISOString()} className="font-semibold text-3xl tabular-nums tracking-tight">
        {now.toLocaleTimeString('en-PH', { timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true })}
      </time>
      <p className="text-muted-foreground text-xs">Philippine time (Asia/Manila)</p>
    </div>
  )
}

type HeroState = { title: string; detail: string; action: 'clock-in' | 'clock-out' | null }

function heroState(today: StaffAttendance | null, shift: TodayShift | null): HeroState {
  if (!today) {
    const detail = shift?.dayOff
      ? 'Today is a day off on your schedule. You can still clock in if you are working.'
      : shift?.start
        ? `Your shift is ${clockLabel(shift.start)} to ${clockLabel(shift.end) || 'not set'}. Clock in when you arrive.`
        : 'Clock in when you arrive.'
    return { title: 'You have not clocked in', detail, action: 'clock-in' }
  }
  if (today.clock_out_time) {
    const short = today.undertime_minutes > 0 ? ` You left ${minutesLabel(today.undertime_minutes)} before your shift ended.` : ''
    return { title: 'Done for today', detail: `You worked ${minutesLabel(today.worked_minutes)}.${short}`, action: null }
  }
  if (today.clock_in_time) {
    const late = today.late_minutes > 0 ? `${minutesLabel(today.late_minutes)} after your shift started.` : 'On time.'
    return { title: `Clocked in at ${clockLabel(today.clock_in_time)}`, detail: `${late} Clock out when you leave.`, action: 'clock-out' }
  }
  return {
    title: today.status === 'absent' ? 'Marked absent today' : 'No clock-in yet',
    detail: 'If this is wrong, explain it in your attendance below or talk to your manager.',
    action: null,
  }
}

/** The focal point of My work: where the employee stands today and the one thing to press. */
function ClockHero({
  today,
  shift,
  graceMinutes,
  busy,
  onClockIn,
  onClockOut,
}: {
  today: StaffAttendance | null
  shift: TodayShift | null
  graceMinutes: number
  busy: string
  onClockIn: () => void
  onClockOut: () => void
}) {
  const state = heroState(today, shift)
  const status = today ? ATTENDANCE_STATUS[today.status] : null
  const planStart = today ? today.scheduled_start : shift && !shift.dayOff ? shift.start : null
  const planEnd = today ? today.scheduled_end : shift && !shift.dayOff ? shift.end : null
  const outText = today?.clock_out_time ? clockLabel(today.clock_out_time) : today?.clock_in_time ? (today.missing_clock_out ? 'Missing' : 'Still in') : 'Not yet'

  return (
    <section aria-labelledby="clock-heading" className="flex flex-col gap-5 rounded-2xl border bg-card p-4 shadow-xs/5 sm:p-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
        <div className="flex min-w-0 flex-col gap-2">
          <h2 id="clock-heading" className="text-balance font-heading font-semibold text-2xl leading-tight">
            {state.title}
          </h2>
          <div className="flex flex-wrap items-center gap-2">
            {status ? <Badge variant={status.variant}>{status.label}</Badge> : <Badge variant="outline">Not started</Badge>}
            {today?.missing_clock_out && <Badge variant="error">Missing clock-out</Badge>}
          </div>
          <p className="text-muted-foreground text-sm" aria-live="polite">
            {state.detail}
          </p>
        </div>

        <div className="flex shrink-0 flex-col gap-3 sm:min-w-52 sm:items-stretch">
          <LiveClock />
          {state.action && (
            <>
              {state.action === 'clock-in' ? (
                <Button size="xl" className="w-full" onClick={onClockIn} loading={busy === 'clock-in'}>
                  Clock in
                </Button>
              ) : (
                <Button size="xl" className="w-full" variant={today?.clock_in_time ? 'default' : 'outline'} onClick={onClockOut} loading={busy === 'clock-out'}>
                  Clock out
                </Button>
              )}
              <p className="text-muted-foreground text-xs sm:text-center">Grace is {graceMinutes} minutes.</p>
            </>
          )}
        </div>
      </div>

      {(planStart || planEnd || today) && (
        <table className="w-full max-w-md text-sm">
          <caption className="sr-only">Your shift and your clock times today</caption>
          <thead>
            <tr className="text-muted-foreground text-xs">
              <th scope="col" className="w-20 pb-1 text-left font-normal">
                <span className="sr-only">Row</span>
              </th>
              <th scope="col" className="pb-1 text-left font-normal">
                Start
              </th>
              <th scope="col" className="pb-1 text-left font-normal">
                End
              </th>
            </tr>
          </thead>
          <tbody className="tabular-nums">
            <tr className="border-t">
              <th scope="row" className="py-2 text-left font-normal text-muted-foreground">
                Shift
              </th>
              <td className="py-2">{clockLabel(planStart) || 'Not set'}</td>
              <td className="py-2">{clockLabel(planEnd) || 'Not set'}</td>
            </tr>
            <tr className="border-t">
              <th scope="row" className="py-2 text-left font-normal text-muted-foreground">
                You
              </th>
              <td className="py-2 align-top">
                <span className="font-medium">{today?.clock_in_time ? clockLabel(today.clock_in_time) : 'Not yet'}</span>
                {today && today.late_minutes > 0 && <span className="block text-warning-foreground text-xs">{minutesLabel(today.late_minutes)} late</span>}
              </td>
              <td className="py-2 align-top">
                <span className={today?.clock_out_time ? 'font-medium' : 'text-muted-foreground'}>{outText}</span>
                {today && today.undertime_minutes > 0 && <span className="block text-warning-foreground text-xs">{minutesLabel(today.undertime_minutes)} short</span>}
              </td>
            </tr>
          </tbody>
        </table>
      )}
    </section>
  )
}

function HistoryRow({ row, onExplain }: { row: StaffAttendance; onExplain: () => void }) {
  const badge = ATTENDANCE_STATUS[row.status]
  const explainable = row.status === 'absent' || row.status === 'late'
  const times = row.clock_in_time ? `${clockLabel(row.clock_in_time)} to ${row.clock_out_time ? clockLabel(row.clock_out_time) : 'no clock-out'}` : 'No clock-in'
  const extra = [
    row.late_minutes > 0 ? `${minutesLabel(row.late_minutes)} late` : null,
    row.undertime_minutes > 0 ? `${minutesLabel(row.undertime_minutes)} short` : null,
  ].filter(Boolean)

  return (
    <li className="grid grid-cols-[5.5rem_minmax(0,1fr)] gap-x-3 gap-y-2 py-3 sm:grid-cols-[6.5rem_minmax(0,1fr)_auto] sm:items-start">
      <time dateTime={row.work_date} className="pt-0.5 font-medium text-sm">
        {workDayLabel(row.work_date)}
      </time>
      <div className="flex min-w-0 flex-col gap-1">
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
          <Badge variant={badge.variant}>{badge.label}</Badge>
          {row.missing_clock_out && <Badge variant="error">Missing clock-out</Badge>}
          <span className="text-sm tabular-nums">{times}</span>
          {row.worked_minutes > 0 && <span className="text-muted-foreground text-sm tabular-nums">{minutesLabel(row.worked_minutes)}</span>}
        </div>
        {extra.length > 0 && <span className="text-muted-foreground text-xs">{extra.join(' · ')}</span>}
        {row.explanation && <span className="break-words text-muted-foreground text-sm">Your note: {row.explanation}</span>}
      </div>
      {explainable && (
        <Button size="sm" variant="ghost" className="col-start-2 justify-self-start sm:col-start-auto sm:justify-self-end" onClick={onExplain}>
          {row.explanation ? 'Change explanation' : 'Explain'}
        </Button>
      )}
    </li>
  )
}

function OvertimeRow({ row }: { row: OvertimeRequest }) {
  const badge = OVERTIME_STATUS[row.status]
  const amounts =
    row.approved_minutes !== null
      ? `${minutesLabel(row.approved_minutes)} approved of ${minutesLabel(row.requested_minutes)}`
      : `${minutesLabel(row.requested_minutes)} asked`
  return (
    <li className="flex flex-col gap-1 py-3">
      <div className="flex items-center justify-between gap-2">
        <time dateTime={row.work_date} className="font-medium text-sm">
          {workDayLabel(row.work_date)}
        </time>
        <Badge variant={badge.variant}>{badge.label}</Badge>
      </div>
      <span className="text-muted-foreground text-sm tabular-nums">
        {OVERTIME_KIND[row.kind]} · {amounts}
      </span>
      {row.decision_note && (
        <span className="break-words text-muted-foreground text-sm">
          Note from {row.decided_by_name ?? 'the approver'}: {row.decision_note}
        </span>
      )}
    </li>
  )
}

function ExplanationDialog({
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
  const [text, setText] = useState(row.explanation ?? '')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setErrors({})
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/my-attendance/${row.id}/explanation`, {
        method: 'PUT',
        body: { explanation: text },
      })
      toastManager.add({ title: 'Explanation saved.', type: 'success' })
      onSaved()
    } catch (error) {
      const fieldErrors = error instanceof ApiError ? error.errors : {}
      setErrors(fieldErrors)
      if (!(error instanceof ApiError) || Object.keys(fieldErrors).length === 0) {
        toastManager.add({ title: error instanceof ApiError ? error.message : 'Could not save the explanation.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  const context = row.status === 'late' && row.late_minutes > 0 ? `Marked late by ${minutesLabel(row.late_minutes)}.` : `Marked ${ATTENDANCE_STATUS[row.status].label.toLowerCase()}.`

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Explain {workDayLabel(row.work_date)}</DialogTitle>
            <DialogDescription>
              {context} This note is for your manager. It does not change pay by itself.
            </DialogDescription>
          </DialogHeader>
          <DialogPanel>
            <Field name="explanation">
              <FieldLabel>Explanation</FieldLabel>
              <Textarea name="explanation" rows={3} maxLength={1000} value={text} onChange={(event) => setText(event.target.value)} required />
              <FieldError />
            </Field>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={!text.trim()}>
              Save explanation
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function OvertimeDialog({ gymId, onClose, onSaved }: { gymId: number; onClose: () => void; onSaved: () => void }) {
  const [workDate, setWorkDate] = useState(() => isoDay(new Date()))
  const [minutes, setMinutes] = useState('')
  const [reason, setReason] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setErrors({})
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/my-overtime-requests`, {
        body: { work_date: workDate, minutes: Number(minutes), reason },
      })
      toastManager.add({ title: 'Overtime requested.', type: 'success' })
      onSaved()
    } catch (error) {
      const fieldErrors = error instanceof ApiError ? error.errors : {}
      setErrors(fieldErrors)
      if (!(error instanceof ApiError) || Object.keys(fieldErrors).length === 0) {
        toastManager.add({ title: error instanceof ApiError ? error.message : 'Could not send the request.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  const minutesNumber = Number(minutes)
  const minutesHint = minutes !== '' && Number.isFinite(minutesNumber) && minutesNumber > 0 ? `That is ${minutesLabel(minutesNumber)}.` : 'Whole minutes, for example 90.'

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Request overtime</DialogTitle>
            <DialogDescription>A permitted person decides. Only approved minutes are paid.</DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="work_date" label="Day" type="date" value={workDate} onChange={(event) => setWorkDate(event.target.value)} required />
              <TextField
                name="minutes"
                label="Minutes"
                type="number"
                inputMode="numeric"
                min={1}
                max={1440}
                value={minutes}
                onChange={(event) => setMinutes(event.target.value)}
                description={minutesHint}
                required
              />
            </div>
            <Field name="reason">
              <FieldLabel>Reason</FieldLabel>
              <Textarea name="reason" rows={3} maxLength={1000} value={reason} onChange={(event) => setReason(event.target.value)} required />
              <FieldDescription>Say what kept you past your shift.</FieldDescription>
              <FieldError />
            </Field>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={!minutes || !reason.trim()}>
              Send request
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}
