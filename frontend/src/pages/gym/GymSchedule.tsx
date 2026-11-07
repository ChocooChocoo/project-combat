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
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { api, ApiError } from '@/lib/api'
import { usePolling } from '@/hooks/use-polling'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import { dateKey, flagReasonLabel, sessionFee, sessionTime, SESSION_STATUS_LABELS, type SessionOptions, type TrainingSession } from '@/lib/sessions'

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
    <div className="flex flex-wrap gap-2">
      <Button variant="outline" onClick={() => setEditing(session)} aria-label={`Edit ${session.name}`}>Edit</Button>
      <Button variant="destructive-outline" onClick={() => setCancelling(session)}>Cancel session</Button>
    </div>
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
        <section className="flex flex-col gap-3" aria-label="Needs attention">
          <h2 className="font-semibold text-xl">Needs attention ({flagged.length})</h2>
          <ul className="divide-y rounded-xl border px-4">
            {flagged.map((session) => <li key={session.id} className="flex flex-wrap items-center justify-between gap-3 py-4">
              <div className="min-w-0"><Link className="break-words font-medium underline underline-offset-4" to={`/gyms/${gymId}/manage/schedule/${session.id}`}>{session.name}</Link><p className="text-sm">{dayLabel(session.starts_at.slice(0, 10))}, {sessionTime(session.starts_at)}</p><p className="break-words text-muted-foreground text-sm">{flagReasonLabel(session.flag_reason)}</p></div>
              {canManage && <div className="flex flex-wrap gap-2"><Button variant="outline" onClick={() => setReassigning(session)}>Reassign coach</Button><Button variant="outline" onClick={() => setEditing(session)}>Move</Button><Button variant="destructive-outline" onClick={() => setCancelling(session)}>Cancel</Button></div>}
            </li>)}
          </ul>
        </section>
      )}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="font-medium text-sm" aria-live="polite">
          {dayLabel(week)} to {dayLabel(shift(week, 6))}
        </p>
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" aria-label="Previous week" onClick={() => setWeek(shift(week, -7))}>
            <ChevronLeft aria-hidden />
          </Button>
          <Button variant="outline" onClick={() => setWeek(monday())}>This week</Button>
          <Button variant="outline" aria-label="Next week" onClick={() => setWeek(shift(week, 7))}>
            <ChevronRight aria-hidden />
          </Button>
        </div>
      </div>
      {error ? (
        <Alert variant="error">
          <AlertTitle>Schedule could not be loaded</AlertTitle>
          <AlertDescription>
            <p>{error}</p>
            <Button variant="outline" onClick={() => setVersion((value) => value + 1)}>Try again</Button>
          </AlertDescription>
        </Alert>
      ) : !sessions || !options ? (
        <div className="flex flex-col gap-3" aria-busy="true" aria-label="Loading schedule">
          <Skeleton className="h-12" />
          <Skeleton className="h-24" />
          <Skeleton className="h-24" />
        </div>
      ) : sessions.length === 0 ? (
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>No sessions this week</EmptyTitle>
            <EmptyDescription>Choose another week or add a session to this schedule.</EmptyDescription>
          </EmptyHeader>
          {canManage && <EmptyContent>{add}</EmptyContent>}
        </Empty>
      ) : days.map((day) => (
        <section key={day} className="flex min-w-0 flex-col gap-3" aria-label={dayLabel(day)}>
          <h2 className="font-semibold">{dayLabel(day)}</h2>
          <div className="hidden overflow-x-auto lg:block">
            <Table variant="card">
              <TableHeader>
                <TableRow>
                  <TableHead>Time</TableHead>
                  <TableHead>Session</TableHead>
                  <TableHead>Coach</TableHead>
                  <TableHead>Room</TableHead>
                  <TableHead>Capacity</TableHead>
                  <TableHead>Fee</TableHead>
                  {canManage && <TableHead><span className="sr-only">Actions</span></TableHead>}
                </TableRow>
              </TableHeader>
              <TableBody>
                {sessions.filter((session) => session.starts_at.startsWith(day)).map((session) => (
                  <TableRow key={session.id} className={session.status === 'cancelled' ? 'text-muted-foreground' : undefined}>
                    <TableCell className="tabular-nums">{sessionTime(session.starts_at)}–{sessionTime(session.ends_at)}</TableCell>
                    <TableCell className="max-w-64 whitespace-normal">
                      <Link to={`/gyms/${gymId}/manage/schedule/${session.id}`} className="block break-words font-medium underline underline-offset-4">{session.name}</Link>
                      <div className="mt-1 flex flex-wrap gap-1">
                        <Badge variant="secondary">{options.activity_types[session.type]}</Badge>
                        {session.status === 'cancelled' && <Badge variant="outline">{SESSION_STATUS_LABELS[session.status]}</Badge>}
                      </div>
                    </TableCell>
                    <TableCell className="max-w-40 whitespace-normal break-words">{session.coach.name}</TableCell>
                    <TableCell className="max-w-32 whitespace-normal break-words">{session.room.name}</TableCell>
                    <TableCell>{session.capacity}</TableCell>
                    <TableCell>{sessionFee(session.fee)}</TableCell>
                    {canManage && (
                      <TableCell className="text-right">
                        {actions(session)}
                      </TableCell>
                    )}
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
          <ul className="flex flex-col gap-3 lg:hidden">
            {sessions.filter((session) => session.starts_at.startsWith(day)).map((session) => (
              <li key={session.id} className={`flex min-w-0 flex-col gap-3 rounded-xl border p-4 ${session.status === 'cancelled' ? 'text-muted-foreground' : ''}`}>
                <p className="text-sm tabular-nums">{sessionTime(session.starts_at)}–{sessionTime(session.ends_at)}</p>
                <h3 className="break-words font-semibold"><Link to={`/gyms/${gymId}/manage/schedule/${session.id}`} className="underline underline-offset-4">{session.name}</Link></h3>
                <div className="flex flex-wrap gap-2">
                  <Badge variant="secondary">{options.activity_types[session.type]}</Badge>
                  {session.status === 'cancelled' && <Badge variant="outline">{SESSION_STATUS_LABELS[session.status]}</Badge>}
                </div>
                <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 text-sm">
                  <dt className="text-muted-foreground">Coach</dt><dd className="break-words">{session.coach.name}</dd>
                  <dt className="text-muted-foreground">Room</dt><dd className="break-words">{session.room.name}</dd>
                  <dt className="text-muted-foreground">Capacity</dt><dd>{session.capacity}</dd>
                  <dt className="text-muted-foreground">Fee</dt><dd>{sessionFee(session.fee)}</dd>
                </dl>
                {actions(session)}
              </li>
            ))}
          </ul>
        </section>
      ))}
      {cancelling && <CancelSessionDialog key={cancelling.id} gymId={Number(gymId)} session={cancelling} onClose={() => setCancelling(null)} onSaved={refresh} />}
      {reassigning && <ReassignCoachDialog key={reassigning.id} gymId={Number(gymId)} session={reassigning} onClose={() => setReassigning(null)} onSaved={refresh} />}
    </div>
  )
}
