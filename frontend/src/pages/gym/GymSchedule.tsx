import { ChevronLeft, ChevronRight, Plus } from 'lucide-react'
import { useCallback, useState } from 'react'
import { Link, useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { SessionEditor } from '@/components/schedule/session-editor'
import { CancelSessionDialog, ReassignCoachDialog } from '@/components/schedule/session-actions'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { api, ApiError } from '@/lib/api'
import { cn } from '@/lib/utils'
import { usePolling } from '@/hooks/use-polling'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import { dateKey, flagReasonLabel, sessionFee, sessionTime, SESSION_PHASES, type SessionOptions, type TrainingSession } from '@/lib/sessions'

function monday(): string {
  const date = new Date()
  date.setDate(date.getDate() - (date.getDay() + 6) % 7)
  return dateKey(date)
}

function shift(date: string, days: number): string {
  const next = new Date(`${date}T12:00:00`)
  next.setDate(next.getDate() + days)
  return dateKey(next)
}

function dayLabel(date: string): string {
  return new Date(`${date}T12:00:00`).toLocaleDateString('en-PH', { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' })
}

function dayParts(date: string) {
  const value = new Date(`${date}T12:00:00`)
  return {
    weekday: value.toLocaleDateString('en-PH', { weekday: 'long' }),
    date: value.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }),
    today: date === dateKey(new Date()),
  }
}

/** R-42: a week of occurrences, grouped by day, with editing limited by training permissions. */
export default function GymSchedule() {
  const { gymId } = useParams()
  const { user } = useAuth()
  const gym = approvedGym(user, Number(gymId))
  const canView = gym ? can(gym, 'training.sessions.view') : false
  const canManage = gym ? can(gym, 'training.sessions.manage') : false
  const [week, setWeek] = useState(monday)
  const [sessions, setSessions] = useState<TrainingSession[] | null>(null)
  const [options, setOptions] = useState<SessionOptions | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [editing, setEditing] = useState<TrainingSession | 'new' | null>(null)
  const [flagged, setFlagged] = useState<TrainingSession[]>([])
  const [cancelling, setCancelling] = useState<TrainingSession | null>(null)
  const [reassigning, setReassigning] = useState<TrainingSession | null>(null)

  const load = useCallback(async (silent: boolean, isCurrent: () => boolean) => {
    if (!silent) setError('')
    try {
      const [listResult, choicesResult, attentionResult] = await Promise.allSettled([
        api<{ sessions: TrainingSession[] }>(`/gyms/${gymId}/sessions?from=${week}&to=${shift(week, 6)}`),
        api<{ options: SessionOptions }>(`/gyms/${gymId}/sessions/options`),
        api<{ sessions: TrainingSession[] }>(`/gyms/${gymId}/sessions?flagged=1`),
      ])
      if (listResult.status === 'rejected') throw listResult.reason
      if (choicesResult.status === 'rejected') throw choicesResult.reason
      if (attentionResult.status === 'rejected') throw attentionResult.reason
      const list = listResult.value
      const choices = choicesResult.value
      const attention = attentionResult.value
      if (!isCurrent()) return
      setSessions(list.sessions)
      setOptions(choices.options)
      setFlagged(attention.sessions)
      setError('')
    } catch (failure) {
      if (isCurrent() && !silent) setError(failure instanceof ApiError ? failure.message : 'The schedule could not be loaded.')
    }
  }, [gymId, week])
  usePolling(load, 30_000, Boolean(user && gymId && canView), version)

  if (!gym) return null
  if (!canView) {
    return (
      <Empty className="rounded-xl border">
        <EmptyHeader>
          <EmptyTitle>Schedule is not available</EmptyTitle>
          <EmptyDescription>Your role does not include training access for this gym.</EmptyDescription>
        </EmptyHeader>
      </Empty>
    )
  }

  if (editing !== null && options && gymId && canManage) {
    return (
      <SessionEditor
        key={editing === 'new' ? 'new' : editing.id}
        gymId={gymId}
        session={editing === 'new' ? null : editing}
        options={options}
        onBack={() => setEditing(null)}
        onSaved={() => {
          setEditing(null)
          setVersion((value) => value + 1)
        }}
      />
    )
  }

  const days = [...new Set((sessions ?? []).map((session) => session.starts_at.slice(0, 10)))]
  const refresh = () => { setCancelling(null); setReassigning(null); setVersion((v) => v + 1) }
  const actions = (session: TrainingSession) => canManage && session.status !== 'cancelled' && (
    <>
      <Button size="sm" variant="outline" onClick={() => setEditing(session)} aria-label={`Edit ${session.name}`}>Edit</Button>
      <Button size="sm" variant="ghost" className="text-destructive-foreground" onClick={() => setCancelling(session)}>Cancel session</Button>
    </>
  )
  const add = (
    <Button disabled={!options || !sessions || Boolean(error)} onClick={() => setEditing('new')}>
      <Plus aria-hidden /> Add session
    </Button>
  )

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Schedule" description="The gym's sessions, coaches and rooms for the week." actions={canManage && add} />
      {!error && sessions && options && flagged.length > 0 && (
        <section className="min-w-0 overflow-hidden rounded-xl border bg-card" aria-label="Needs attention">
          <div className="flex items-center justify-between gap-3 border-b bg-muted/30 px-5 py-4">
            <h2 className="font-semibold text-lg tracking-tight">Needs attention</h2>
            <span className="text-muted-foreground text-sm tabular-nums">{flagged.length}</span>
          </div>
          <ul className="divide-y">
            {flagged.map((session) => <li key={session.id} className="flex min-w-0 flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:gap-6">
              <div className="min-w-0">
                <Link className="font-medium text-sm underline-offset-4 hover:underline" to={`/gyms/${gymId}/manage/schedule/${session.id}`}>{session.name}</Link>
                <p className="mt-1 text-muted-foreground text-sm tabular-nums">{dayLabel(session.starts_at.slice(0, 10))}, {sessionTime(session.starts_at)}</p>
                <p className="mt-1 text-sm wrap-anywhere">{flagReasonLabel(session.flag_reason)}</p>
              </div>
              {canManage && <div className="flex flex-wrap justify-end gap-2">
                <Button size="sm" variant="outline" onClick={() => setReassigning(session)}>Reassign coach</Button>
                <Button size="sm" variant="outline" onClick={() => setEditing(session)}>Move</Button>
                <Button size="sm" variant="destructive-outline" onClick={() => setCancelling(session)}>Cancel</Button>
              </div>}
            </li>)}
          </ul>
        </section>
      )}
      <section className="flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-card p-4 sm:p-5" aria-label="Week">
        <div className="min-w-0">
          <p className="text-muted-foreground text-xs">Week</p>
          <p className="mt-1 font-semibold text-sm tabular-nums" aria-live="polite">{dayLabel(week)} to {dayLabel(shift(week, 6))}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" aria-label="Previous week" onClick={() => setWeek(shift(week, -7))}>
            <ChevronLeft aria-hidden />
          </Button>
          <Button variant="outline" onClick={() => setWeek(monday())}>This week</Button>
          <Button variant="outline" aria-label="Next week" onClick={() => setWeek(shift(week, 7))}>
            <ChevronRight aria-hidden />
          </Button>
        </div>
      </section>
      {error ? (
        <Alert variant="error">
          <AlertTitle>Schedule could not be loaded</AlertTitle>
          <AlertDescription>
            <p>{error}</p>
            <Button variant="outline" onClick={() => setVersion((value) => value + 1)}>Try again</Button>
          </AlertDescription>
        </Alert>
      ) : !sessions || !options ? (
        <div className="flex flex-col gap-4" aria-busy="true" aria-label="Loading schedule">
          <Skeleton className="h-12 w-48" />
          <Skeleton className="h-40" />
          <Skeleton className="h-40" />
        </div>
      ) : sessions.length === 0 ? (
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>No sessions this week</EmptyTitle>
            <EmptyDescription>Choose another week or add a session to this schedule.</EmptyDescription>
          </EmptyHeader>
          {canManage && <EmptyContent>{add}</EmptyContent>}
        </Empty>
      ) : days.map((day) => {
        const date = dayParts(day)
        const daySessions = sessions.filter((session) => session.starts_at.startsWith(day))
        return (
        <section key={day} className="flex min-w-0 flex-col gap-3" aria-label={dayLabel(day)}>
          <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
            <div className="flex flex-wrap items-baseline gap-x-2.5 gap-y-1">
              <h2 className="font-medium text-sm"><time dateTime={day}>{date.weekday}</time></h2>
              <span className="text-muted-foreground text-sm tabular-nums">{date.date}</span>
              {date.today && <span className="font-medium text-success-foreground text-xs">Today</span>}
            </div>
            <span className="text-muted-foreground text-xs tabular-nums">
              {daySessions.length} {daySessions.length === 1 ? 'session' : 'sessions'}
            </span>
          </div>
          <ul className="divide-y overflow-hidden rounded-xl border bg-card">
            {daySessions.map((session) => (
              <li key={session.id} className={cn('flex min-w-0 flex-col', session.status === 'cancelled' && 'opacity-60')}>
                <div className="grid min-w-0 gap-4 px-5 py-4 sm:grid-cols-[5.5rem_minmax(0,1fr)] sm:items-start sm:gap-5">
                  <div className="flex shrink-0 flex-col gap-0.5 tabular-nums">
                    <time className="font-medium text-sm leading-tight" dateTime={session.starts_at}>{sessionTime(session.starts_at)}</time>
                    <p className="text-muted-foreground text-xs">to {sessionTime(session.ends_at)}</p>
                  </div>

                  <div className="flex min-w-0 flex-col gap-2">
                    <div className="flex flex-wrap items-start justify-between gap-x-4 gap-y-1.5">
                      <Link to={`/gyms/${gymId}/manage/schedule/${session.id}`} className="font-medium text-sm leading-snug wrap-anywhere underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">{session.name}</Link>
                      <div className="flex flex-wrap gap-1.5">
                        <Badge variant="secondary">{options.activity_types[session.type]}</Badge>
                        <Badge variant={SESSION_PHASES[session.phase].variant}>{SESSION_PHASES[session.phase].label}</Badge>
                      </div>
                    </div>
                    <p className="text-muted-foreground text-sm">
                      <span className="wrap-anywhere">{session.coach.name}</span>
                      <span className="mx-1.5">·</span>
                      <span className="wrap-anywhere">{session.room.name}</span>
                      <span className="mx-1.5">·</span>
                      <span className="tabular-nums">{session.capacity} places</span>
                      <span className="mx-1.5">·</span>
                      <span className="tabular-nums">{sessionFee(session.fee)}</span>
                    </p>
                  </div>
                </div>

                {canManage && session.status !== 'cancelled' && (
                  <div className="flex flex-wrap justify-end gap-2 border-t bg-muted/30 px-5 py-2.5">{actions(session)}</div>
                )}
              </li>
            ))}
          </ul>
        </section>
        )
      })}
      {cancelling && <CancelSessionDialog key={cancelling.id} gymId={Number(gymId)} session={cancelling} onClose={() => setCancelling(null)} onSaved={refresh} />}
      {reassigning && <ReassignCoachDialog key={reassigning.id} gymId={Number(gymId)} session={reassigning} onClose={() => setReassigning(null)} onSaved={refresh} />}
    </div>
  )
}
