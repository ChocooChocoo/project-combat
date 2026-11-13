import { useCallback, useState } from 'react'
import { ChevronRight, MapPin, UserRound } from 'lucide-react'
import { Link, useParams } from 'react-router'
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
    <div className="flex min-w-0 flex-col gap-8">
      <header className="flex flex-wrap items-end justify-between gap-4 border-b pb-6">
        <div>
          <h1 className="font-semibold text-3xl tracking-tight sm:text-4xl">Sessions</h1>
          <p className="mt-3 text-muted-foreground text-sm leading-relaxed">Find a session, read the details and reserve your place.</p>
        </div>
        {data && data.private_services.length > 0 && (
          <Button className="min-h-11" variant="outline" render={<Link to={`/gyms/${gymId}/private-coaching`} />}>Book private coaching</Button>
        )}
      </header>
      <div className="grid min-w-0 items-start gap-8 xl:grid-cols-[19rem_minmax(0,1fr)]">
      <aside className="order-2 min-w-0 xl:sticky xl:top-6 xl:order-1" aria-label="Check-in">
        <CheckInCodes gymId={Number(gymId)} columns={1} />
      </aside>
      <div className="order-1 flex min-w-0 flex-col gap-8 xl:order-2">
      <section className="flex flex-col gap-4 rounded-xl border bg-card p-5 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between" aria-labelledby="session-date-range">
        <div>
          <h2 id="session-date-range" className="font-semibold text-base">Find your next session</h2>
          <p className="mt-1 text-muted-foreground text-sm">Choose the dates you want to train.</p>
        </div>
        <div className="grid min-w-0 gap-3 sm:grid-cols-2">
          <TextField name="from" label="From" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
          <TextField name="to" label="To" type="date" min={from} value={to} onChange={(e) => setTo(e.target.value)} />
        </div>
      </section>
      <div className="flex min-w-0 flex-col gap-8">
      {error ? (
        <BookingError message={error} retry={() => setVersion((v) => v + 1)} />
      ) : !data ? (
        <BookingLoading />
      ) : data.sessions.length === 0 ? (
        <BookingEmpty title="No upcoming sessions" description="Try another date range, or check back when the gym adds sessions." />
      ) : days.map((day) => (
        <section key={day} className="flex min-w-0 flex-col gap-4" aria-label={dayLabel(day)}>
          <h2 className="font-semibold text-lg tracking-tight"><time dateTime={day}>{dayLabel(day)}</time></h2>
          <ul className="divide-y overflow-hidden rounded-xl border bg-card">
            {data.sessions.filter((session) => session.starts_at.startsWith(day)).map((session) => (
              <li key={session.id}>
                <Link
                  to={`/gyms/${gymId}/sessions/${session.id}`}
                  className="group flex min-h-11 min-w-0 flex-col gap-4 p-5 transition-colors hover:bg-accent/40 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none sm:flex-row sm:items-start sm:gap-6"
                >
                  <div className="flex shrink-0 items-baseline gap-2 tabular-nums sm:w-28 sm:flex-col sm:gap-1">
                    <p className="font-semibold text-lg tracking-tight">{sessionTime(session.starts_at)}</p>
                    <p className="text-muted-foreground text-xs">to {sessionTime(session.ends_at)}</p>
                  </div>
                  <div className="flex min-w-0 flex-1 flex-col gap-2">
                    {session.thumbnail_url && <img src={session.thumbnail_url} alt="" loading="lazy" className="aspect-video w-28 max-w-full rounded-md border object-cover" />}
                    <h3 className="font-semibold text-lg tracking-tight wrap-anywhere">{session.name}</h3>
                    <p className="text-muted-foreground text-xs">{session.type_label}</p>
                    <div className="mt-1 flex flex-wrap gap-x-5 gap-y-2 text-muted-foreground text-sm">
                      <span className="flex min-w-0 items-start gap-2"><UserRound className="mt-0.5 size-4 shrink-0" aria-hidden /><span className="wrap-anywhere">{session.coach.name}</span></span>
                      <span className="flex min-w-0 items-start gap-2"><MapPin className="mt-0.5 size-4 shrink-0" aria-hidden /><span className="wrap-anywhere">{session.room.name}</span></span>
                    </div>
                  </div>
                  <div className="flex min-w-0 items-center justify-between gap-3 border-t pt-3 sm:max-w-48 sm:border-0 sm:pt-0">
                    <div className="flex min-w-0 flex-col items-start gap-2 sm:items-end sm:text-right">
                      <p className="font-semibold text-sm tabular-nums wrap-anywhere">{sessionFee(session.fee)}</p>
                      <p className="text-muted-foreground text-xs leading-relaxed">
                        {session.places_left > 0 ? `${session.places_left} ${session.places_left === 1 ? 'place' : 'places'} left` : session.waiting_list_open ? 'Full, waiting list open' : 'Not available'}
                      </p>
                      {session.my_booking && <BookingBadge status={session.my_booking.status} />}
                    </div>
                    <ChevronRight className="size-4 shrink-0 text-muted-foreground group-hover:text-foreground" aria-hidden />
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        </section>
      ))}
      </div>
      </div>
      </div>
    </div>
  )
}
