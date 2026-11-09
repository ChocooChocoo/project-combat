import { useCallback, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { CheckInCodes } from '@/components/schedule/check-in-code'
import { BookingActionDialog, BookingBadge, BookingEmpty, BookingError, BookingLoading, type BookingAction } from '@/components/schedule/booking-parts'
import { Button } from '@/components/ui/button'
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/components/ui/tabs'
import { api, ApiError } from '@/lib/api'
import { usePolling } from '@/hooks/use-polling'
import { useAuth } from '@/lib/auth'
import { bookingPaymentLabel, dayLabel, liveBooking, type MyBooking } from '@/lib/bookings'
import { sessionTime } from '@/lib/sessions'
import { toastManager } from '@/components/ui/toast'

function bookingDate(value: string) {
  const date = new Date(`${value.slice(0, 10)}T12:00:00`)
  return {
    weekday: date.toLocaleDateString('en-PH', { weekday: 'short' }),
    day: date.toLocaleDateString('en-PH', { day: 'numeric' }),
    month: date.toLocaleDateString('en-PH', { month: 'short' }),
  }
}

/** R-48: one personal list across all gyms, including cancelled and rejected bookings. */
export default function MyBookings() {
  const { user } = useAuth()
  const [data, setData] = useState<{ upcoming: MyBooking[]; past: MyBooking[] } | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [target, setTarget] = useState<BookingAction | null>(null)
  const [search, setSearch] = useSearchParams()
  const [paying, setPaying] = useState<number | null>(null)
  const load = useCallback(async (silent: boolean, isCurrent: () => boolean) => {
    if (!silent) setError('')
    try {
      const returned = silent ? null : search.get('checkout')
      const booking = search.get('booking')
      let confirmed: boolean | null = null
      if (returned === 'done' && booking && /^\d+$/.test(booking)) {
        const result = await api<{ paid: boolean }>(`/my-bookings/${booking}/checkout/confirm`, { method: 'POST' })
        if (!isCurrent()) return
        confirmed = result.paid
      }
      const result = await api<{ upcoming: MyBooking[]; past: MyBooking[] }>('/my-bookings')
      if (!isCurrent()) return
      setData(result)
      setError('')
      if (confirmed !== null) toastManager.add(confirmed
        ? { title: 'Payment confirmed. Your booking has been updated.', type: 'success' }
        : { title: 'PayMongo has not confirmed payment yet. Check again in a minute.', type: 'warning' })
      if (returned === 'cancelled') toastManager.add({ title: 'Checkout closed. Only confirmed payments change your balance.', type: 'info' })
      if (returned) {
        const next = new URLSearchParams(search)
        next.delete('checkout')
        next.delete('booking')
        setSearch(next, { replace: true })
      }
    } catch (failure) {
      if (isCurrent() && !silent) setError(failure instanceof ApiError ? failure.message : 'Your bookings could not be loaded.')
    }
  }, [search, setSearch])
  usePolling(load, 15_000, Boolean(user), version)
  async function payOnline(bookingId: number) {
    setPaying(bookingId)
    try {
      const result = await api<{ checkout_url: string }>(`/my-bookings/${bookingId}/checkout`, { method: 'POST' })
      window.location.assign(result.checkout_url)
    } catch (failure) {
      toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Online payment could not start.', type: 'error' })
      setPaying(null)
    }
  }
  return (
    <div className="flex min-w-0 flex-col gap-8">
      <header className="flex flex-wrap items-end justify-between gap-4 border-b pb-6">
        <div>
          <h1 className="font-semibold text-3xl tracking-tight sm:text-4xl">My bookings</h1>
          <p className="mt-3 text-muted-foreground text-sm leading-relaxed">Your sessions and waiting-list places in every gym.</p>
        </div>
        {data && (
          <dl className="flex gap-8 text-sm">
            <div>
              <dt className="text-muted-foreground text-xs">Upcoming</dt>
              <dd className="mt-1 font-semibold text-2xl tabular-nums">{data.upcoming.length}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground text-xs">Past</dt>
              <dd className="mt-1 font-semibold text-2xl tabular-nums">{data.past.length}</dd>
            </div>
          </dl>
        )}
      </header>
      <CheckInCodes />
      {error ? (
        <BookingError message={error} retry={() => setVersion((v) => v + 1)} />
      ) : !data ? (
        <BookingLoading />
      ) : (
        <Tabs defaultValue="upcoming" className="min-w-0">
          <TabsList variant="underline" aria-label="Booking period" className="h-auto max-w-full flex-wrap justify-start">
            <TabsTab className="min-h-11" value="upcoming">Upcoming ({data.upcoming.length})</TabsTab>
            <TabsTab className="min-h-11" value="past">Past ({data.past.length})</TabsTab>
          </TabsList>
          {(['upcoming', 'past'] as const).map((period) => (
            <TabsPanel key={period} value={period} className="pt-6">
              {data[period].length === 0 ? (
                <BookingEmpty title={`No ${period} bookings`} description="Open a gym's Sessions page to find your next session." />
              ) : (
                <ul className="flex min-w-0 flex-col gap-4">
                  {data[period].map((booking) => {
                    const date = bookingDate(booking.session.starts_at)
                    return (
                    <li key={booking.id} className="min-w-0 overflow-hidden rounded-xl border bg-card">
                      <div className="flex min-w-0 flex-col gap-4 p-5 sm:flex-row sm:gap-6">
                        <div className="flex shrink-0 items-baseline gap-2 rounded-lg bg-muted/40 px-3 py-2 sm:w-24 sm:flex-col sm:items-center sm:justify-center sm:gap-0.5 sm:px-2 sm:py-3">
                          <p className="text-muted-foreground text-xs">{date.weekday}</p>
                          <p className="font-semibold text-2xl leading-none tracking-tight tabular-nums">{date.day}</p>
                          <p className="text-muted-foreground text-xs">{date.month}</p>
                        </div>
                        <div className="flex min-w-0 flex-1 flex-col gap-2">
                          <div className="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                            <p className="min-w-0 font-medium text-muted-foreground text-xs wrap-anywhere">{booking.gym.name}</p>
                            <BookingBadge status={booking.status} />
                          </div>
                          <Link className="font-semibold text-lg tracking-tight wrap-anywhere underline-offset-4 hover:underline" to={`/gyms/${booking.gym_id}/sessions/${booking.training_session_id}`}>
                            {booking.session.name}
                          </Link>
                          <p className="text-sm tabular-nums">
                            {dayLabel(booking.session.starts_at)}
                            <span className="text-muted-foreground"> · {sessionTime(booking.session.starts_at)}–{sessionTime(booking.session.ends_at)}</span>
                          </p>
                          <p className="text-muted-foreground text-sm wrap-anywhere">
                            {booking.session.coach.name} · {booking.session.room.name}
                          </p>
                          <p className="mt-1 text-sm">{bookingPaymentLabel(booking)}</p>
                          {booking.decision_reason && <p className="text-sm wrap-anywhere">{booking.decision_reason}</p>}
                          {liveBooking(booking) && !booking.can_cancel && <p className="text-muted-foreground text-xs leading-relaxed">{booking.cancel_reason}</p>}
                        </div>
                      </div>
                      {(booking.status === 'booked' && booking.session.status === 'scheduled' && Number(booking.balance) > 0) || liveBooking(booking) ? (
                        <div className="flex flex-col gap-3 border-t bg-muted/30 p-4 sm:flex-row sm:items-center sm:justify-end">
                          {liveBooking(booking) && (
                            <Button className="min-h-11" variant="outline" disabled={!booking.can_cancel} onClick={() => setTarget({ booking, action: 'cancel', name: booking.session.name })}>
                              Cancel booking
                            </Button>
                          )}
                          {booking.status === 'booked' && booking.session.status === 'scheduled' && Number(booking.balance) > 0 && (
                            <Button className="min-h-11" disabled={paying !== null} loading={paying === booking.id} onClick={() => payOnline(booking.id)}>
                              Pay online
                            </Button>
                          )}
                        </div>
                      ) : null}
                    </li>
                    )
                  })}
                </ul>
              )}
            </TabsPanel>
          ))}
        </Tabs>
      )}
      {target && (
        <BookingActionDialog
          key={target.booking.id}
          target={target}
          onClose={() => setTarget(null)}
          onSaved={() => {
            setTarget(null)
            setVersion((v) => v + 1)
          }}
        />
      )}
    </div>
  )
}
