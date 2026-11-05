import { useEffect, useState } from 'react'
import { BookingEmpty, BookingError, BookingLoading } from '@/components/schedule/booking-parts'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { api, ApiError } from '@/lib/api'
import { dayLabel, shiftDay, weekStart, type CoachDay } from '@/lib/bookings'
import { sessionTime } from '@/lib/sessions'

/** R-44: readable week list, with leave and holidays alongside assigned sessions. */
export function CoachCalendar({ gymId, userId, refreshKey = 0 }: { gymId: number; userId: number; refreshKey?: number }) {
  const [week, setWeek] = useState(weekStart)
  const [days, setDays] = useState<CoachDay[] | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  useEffect(() => {
    let live = true
    setDays(null)
    setError('')
    api<{ days: CoachDay[] }>(`/gyms/${gymId}/coaches/${userId}/calendar?from=${week}&to=${shiftDay(week, 6)}`)
      .then((result) => live && setDays(result.days))
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'The coach calendar could not be loaded.'))
    return () => { live = false }
  }, [gymId, userId, week, version, refreshKey])
  return (
    <section className="flex min-w-0 flex-col gap-4" aria-label="Coach calendar">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="font-semibold text-xl">Calendar</h2>
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" onClick={() => setWeek(shiftDay(week, -7))}>Previous week</Button>
          <Button variant="outline" onClick={() => setWeek(weekStart())}>This week</Button>
          <Button variant="outline" onClick={() => setWeek(shiftDay(week, 7))}>Next week</Button>
        </div>
      </div>
      <p className="text-muted-foreground text-sm" aria-live="polite">
        {dayLabel(week)} to {dayLabel(shiftDay(week, 6))}. Gym-local times.
      </p>
      {error ? (
        <BookingError message={error} retry={() => setVersion((v) => v + 1)} />
      ) : !days ? (
        <BookingLoading />
      ) : days.length === 0 ? (
        <BookingEmpty title="No calendar days" description="Choose another week." />
      ) : (
        <ul className="divide-y rounded-xl border">
          {days.map((day) => (
            <li key={day.date} className="flex min-w-0 flex-col gap-3 p-4 sm:flex-row sm:gap-6">
              <div className="shrink-0 sm:w-52">
                <h3 className="font-semibold text-sm">{dayLabel(day.date)}</h3>
                <p className="mt-1 text-muted-foreground text-sm">
                  {day.is_day_off ? 'Day off' : `${sessionTime(`${day.date} ${day.start_time}`)}–${sessionTime(`${day.date} ${day.end_time}`)}`}
                </p>
              </div>
              <div className="flex min-w-0 flex-1 flex-col gap-2">
                {day.approved_leave && <div><Badge variant="warning">Approved leave</Badge></div>}
                {day.holidays.map((holiday) => (
                  <p key={`${holiday.name}-${holiday.national}`} className="break-words text-sm">
                    {holiday.name} ({holiday.national ? 'national holiday' : 'gym holiday'})
                  </p>
                ))}
                {day.sessions.length === 0 ? (
                  <p className="text-muted-foreground text-sm">No sessions assigned.</p>
                ) : (
                  <ul className="flex flex-col gap-2">
                    {day.sessions.map((session) => (
                      <li key={session.id} className="break-words text-sm">
                        <span className="font-medium">{sessionTime(session.starts_at)}–{sessionTime(session.ends_at)}</span>
                        {' '}· {session.name} · {session.room.name}
                        {session.status === 'cancelled' && <span> (cancelled)</span>}
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
