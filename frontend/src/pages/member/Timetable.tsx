import { useCallback, useState } from 'react'
import { Link, useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { CheckInCodes } from '@/components/schedule/check-in-code'
import { BookingBadge, BookingEmpty, BookingError, BookingLoading } from '@/components/schedule/booking-parts'
import { TextField } from '@/components/text-field'
import { Button } from '@/components/ui/button'
import { api, ApiError } from '@/lib/api'
import { usePolling } from '@/hooks/use-polling'
import { useAuth } from '@/lib/auth'
import { dayLabel, shiftDay, type Timetable as TimetableData } from '@/lib/bookings'
import { dateKey, sessionFee, sessionTime } from '@/lib/sessions'

/** R-47, R-48: upcoming sessions, grouped by the gym's local date. */
export default function Timetable() {
  const { gymId } = useParams()
  const { user } = useAuth()
  const [from, setFrom] = useState(() => dateKey(new Date()))
  const [to, setTo] = useState(() => shiftDay(dateKey(new Date()), 13))
  const [data, setData] = useState<TimetableData | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const load = useCallback(async (silent: boolean, isCurrent: () => boolean) => {
    if (!silent) setError('')
    if (!from || !to) {
      if (!silent) setError('Choose both dates.')
      return
    }
    try {
      const result = await api<TimetableData>(`/gyms/${gymId}/timetable?from=${from}&to=${to}`)
      if (isCurrent()) { setData(result); setError('') }
    } catch (failure) {
      if (isCurrent() && !silent) setError(failure instanceof ApiError ? failure.message : 'The timetable could not be loaded.')
    }
  }, [gymId, from, to])
  usePolling(load, 15_000, Boolean(user && gymId), version)
  const days = [...new Set((data?.sessions ?? []).map((session) => session.starts_at.slice(0, 10)))]
  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Sessions"
        description="Find a session, read the details and reserve your place."
        actions={data && data.private_services.length > 0 && (
          <Button render={<Link to={`/gyms/${gymId}/private-coaching`} />}>Book private coaching</Button>
        )}
      />
      <CheckInCodes gymId={Number(gymId)} />
      <div className="grid gap-4 sm:max-w-lg sm:grid-cols-2">
        <TextField name="from" label="From" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
        <TextField name="to" label="To" type="date" min={from} value={to} onChange={(e) => setTo(e.target.value)} />
      </div>
      {error ? (
        <BookingError message={error} retry={() => setVersion((v) => v + 1)} />
      ) : !data ? (
        <BookingLoading />
      ) : data.sessions.length === 0 ? (
        <BookingEmpty title="No upcoming sessions" description="Try another date range, or check back when the gym adds sessions." />
      ) : days.map((day) => (
        <section key={day} className="flex min-w-0 flex-col gap-3" aria-label={dayLabel(day)}>
          <h2 className="font-semibold">{dayLabel(day)}</h2>
          <ul className="flex flex-col gap-3">
            {data.sessions.filter((session) => session.starts_at.startsWith(day)).map((session) => (
              <li key={session.id}>
                <Link
                  to={`/gyms/${gymId}/sessions/${session.id}`}
                  className="flex min-h-11 min-w-0 flex-col gap-3 rounded-xl border p-4 hover:bg-accent/40 focus-visible:outline-2 focus-visible:outline-ring sm:flex-row sm:items-start sm:gap-5"
                >
                  <p className="shrink-0 text-sm tabular-nums sm:w-40">{sessionTime(session.starts_at)}–{sessionTime(session.ends_at)}</p>
                  <div className="flex min-w-0 flex-1 flex-col gap-1">
                    <h3 className="break-words font-semibold">{session.name}</h3>
                    <p className="text-muted-foreground text-sm">{session.type_label} · {session.coach.name} · {session.room.name}</p>
                    <p className="text-sm">
                      {session.places_left > 0 ? `${session.places_left} ${session.places_left === 1 ? 'place' : 'places'} left` : session.waiting_list_open ? 'Full, waiting list open' : 'Not available'} · {sessionFee(session.fee)}
                    </p>
                  </div>
                  {session.my_booking && <BookingBadge status={session.my_booking.status} />}
                </Link>
              </li>
            ))}
          </ul>
        </section>
      ))}
    </div>
  )
}
