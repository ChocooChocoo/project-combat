import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { CheckInCodes } from '@/components/schedule/check-in-code'
import { BookingActionDialog, BookingBadge, BookingEmpty, BookingError, BookingLoading, type BookingAction } from '@/components/schedule/booking-parts'
import { Button } from '@/components/ui/button'
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/components/ui/tabs'
import { api, ApiError } from '@/lib/api'
import { bookingPaymentLabel, dayLabel, liveBooking, type MyBooking } from '@/lib/bookings'
import { sessionTime } from '@/lib/sessions'
import { toastManager } from '@/components/ui/toast'

/** R-48: one personal list across all gyms, including cancelled and rejected bookings. */
export default function MyBookings() {
  const [data, setData] = useState<{ upcoming: MyBooking[]; past: MyBooking[] } | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [target, setTarget] = useState<BookingAction | null>(null)
  const [search, setSearch] = useSearchParams()
  const [paying, setPaying] = useState<number | null>(null)
  useEffect(() => {
    let live = true
    setData(null)
    setError('')
    async function load() {
      const returned = search.get('checkout')
      const booking = search.get('booking')
      let confirmed: boolean | null = null
      if (returned === 'done' && booking && /^\d+$/.test(booking)) {
        const result = await api<{ paid: boolean }>(`/my-bookings/${booking}/checkout/confirm`, { method: 'POST' })
        confirmed = result.paid
      }
      const result = await api<{ upcoming: MyBooking[]; past: MyBooking[] }>('/my-bookings')
      if (!live) return
      setData(result)
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
    }
    load()
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'Your bookings could not be loaded.'))
    return () => { live = false }
  }, [version, search, setSearch])
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
    <div className="flex flex-col gap-6">
      <PageHeader title="My bookings" description="Your sessions and waiting-list places in every gym." />
      <CheckInCodes />
      {error ? (
        <BookingError message={error} retry={() => setVersion((v) => v + 1)} />
      ) : !data ? (
        <BookingLoading />
      ) : (
        <Tabs defaultValue="upcoming">
          <TabsList variant="underline" aria-label="Booking period">
            <TabsTab value="upcoming">Upcoming ({data.upcoming.length})</TabsTab>
            <TabsTab value="past">Past ({data.past.length})</TabsTab>
          </TabsList>
          {(['upcoming', 'past'] as const).map((period) => (
            <TabsPanel key={period} value={period} className="pt-4">
              {data[period].length === 0 ? (
                <BookingEmpty title={`No ${period} bookings`} description="Open a gym's Sessions page to find your next session." />
              ) : (
                <ul className="flex flex-col gap-3">
                  {data[period].map((booking) => (
                    <li key={booking.id} className="flex min-w-0 flex-col gap-4 rounded-xl border p-4 sm:flex-row sm:items-start">
                      <div className="flex min-w-0 flex-1 flex-col gap-2">
                        <p className="break-words text-muted-foreground text-sm">{booking.gym.name}</p>
                        <Link className="break-words font-semibold underline underline-offset-4" to={`/gyms/${booking.gym_id}/sessions/${booking.training_session_id}`}>
                          {booking.session.name}
                        </Link>
                        <p className="text-sm">
                          {dayLabel(booking.session.starts_at)}, {sessionTime(booking.session.starts_at)}–{sessionTime(booking.session.ends_at)}
                        </p>
                        <p className="break-words text-muted-foreground text-sm">
                          {booking.session.coach.name} · {booking.session.room.name}
                        </p>
                        <p className="text-sm">{bookingPaymentLabel(booking)}</p>
                        <div><BookingBadge status={booking.status} /></div>
                        {booking.decision_reason && <p className="break-words text-sm">{booking.decision_reason}</p>}
                        {liveBooking(booking) && !booking.can_cancel && <p className="text-muted-foreground text-sm">{booking.cancel_reason}</p>}
                      </div>
                      <div className="flex flex-wrap gap-2 sm:flex-col">
                      {booking.status === 'booked' && booking.session.status === 'scheduled' && Number(booking.balance) > 0 && <Button disabled={paying !== null} loading={paying === booking.id} onClick={() => payOnline(booking.id)}>Pay online</Button>}
                      {liveBooking(booking) && (
                        <Button variant="outline" disabled={!booking.can_cancel} onClick={() => setTarget({ booking, action: 'cancel', name: booking.session.name })}>
                          Cancel booking
                        </Button>
                      )}
                      </div>
                    </li>
                  ))}
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
