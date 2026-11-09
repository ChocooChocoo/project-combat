import { useCallback, useState } from 'react'
import { Link, useParams } from 'react-router'
import { Ban, ChevronLeft } from 'lucide-react'
import { BookingActionDialog, BookingBadge, BookingError, BookingLoading, SessionFacts, type BookingAction } from '@/components/schedule/booking-parts'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError } from '@/lib/api'
import { usePolling } from '@/hooks/use-polling'
import { useAuth } from '@/lib/auth'
import { bookingPaymentLabel, dayLabel, liveBooking, type SessionDetails as Details } from '@/lib/bookings'
import { sessionFee, sessionTime } from '@/lib/sessions'

/** R-47 to R-50: full details before booking, then the current booking and cancellation cut-off. */
export default function SessionDetails() {
  const { gymId, sessionId } = useParams()
  const { user } = useAuth()
  const [session, setSession] = useState<Details | null>(null)
  const [error, setError] = useState('')
  const [actionError, setActionError] = useState('')
  const [version, setVersion] = useState(0)
  const [busy, setBusy] = useState(false)
  const [target, setTarget] = useState<BookingAction | null>(null)
  const load = useCallback(async (silent: boolean, isCurrent: () => boolean) => {
    if (!silent) setError('')
    try {
      const result = await api<{ session: Details }>(`/gyms/${gymId}/timetable/${sessionId}`)
      if (isCurrent()) { setSession(result.session); setError('') }
    } catch (failure) {
      if (isCurrent() && !silent) setError(failure instanceof ApiError ? failure.message : 'The session could not be loaded.')
    }
  }, [gymId, sessionId])
  usePolling(load, 15_000, Boolean(user && gymId && sessionId), version)
  async function book() {
    setBusy(true)
    setActionError('')
    try {
      await api(`/gyms/${gymId}/timetable/${sessionId}/book`, { method: 'POST' })
      toastManager.add({ title: 'Booking saved. Check your status below.', type: 'success' })
    } catch (failure) {
      setActionError(failure instanceof ApiError ? Object.values(failure.errors).flat().join(' ') || failure.message : 'The booking could not be made.')
    } finally {
      setBusy(false)
      setVersion((v) => v + 1)
    }
  }
  const mine = session?.my_booking ?? null
  const active = liveBooking(mine)
  const cancelled = session?.status === 'cancelled'
  const availability = session
    ? cancelled
      ? 'Session cancelled'
      : session.places_left > 0
        ? `${session.places_left} ${session.places_left === 1 ? 'place' : 'places'} left of ${session.capacity}`
        : session.waiting_list_open
          ? 'Full, waiting list open'
          : session.type === 'private_coaching'
            ? 'Booked'
            : 'Not available'
    : ''
  return (
    <div className="flex min-w-0 flex-col gap-6">
      <Button className="-ms-3 min-h-11 self-start" size="sm" variant="ghost" render={<Link to={`/gyms/${gymId}/sessions`} />}>
        <ChevronLeft aria-hidden /> Back to sessions
      </Button>
      {error ? (
        <BookingError message={error} retry={() => setVersion((v) => v + 1)} />
      ) : !session ? (
        <BookingLoading />
      ) : (
        <>
          <header className="flex min-w-0 flex-col gap-4 border-b pb-6">
            <div className="flex flex-wrap items-center gap-2">
              {cancelled && <Badge variant="error">Cancelled</Badge>}
              <Badge variant="secondary">{session.type_label}</Badge>
              <Badge variant="outline">{session.level_label}</Badge>
              {mine && <BookingBadge status={mine.status} />}
            </div>
            <h1 className="font-semibold text-3xl tracking-tight wrap-anywhere sm:text-4xl">{session.name}</h1>
            <p className="text-muted-foreground text-sm tabular-nums">
              {dayLabel(session.starts_at)} · {sessionTime(session.starts_at)}–{sessionTime(session.ends_at)}
              <span className="text-muted-foreground"> · {session.duration_minutes} minutes</span>
            </p>
          </header>

          {cancelled && (
            <section className="flex items-start gap-4 rounded-xl border bg-muted/30 p-5" aria-labelledby="session-cancelled">
              <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-destructive/8" aria-hidden>
                <Ban className="size-4 text-destructive-foreground" />
              </span>
              <div className="min-w-0">
                <h2 id="session-cancelled" className="font-semibold text-base">This session was cancelled</h2>
                {session.cancel_reason && (
                  <p className="mt-2 whitespace-pre-line text-sm leading-7 wrap-anywhere">{session.cancel_reason}</p>
                )}
                <p className="mt-2 text-muted-foreground text-sm leading-relaxed">
                  Booking is closed for this session. Check the timetable for another session.
                </p>
              </div>
            </section>
          )}
          {actionError && (
            <Alert variant="error">
              <AlertTitle>Booking could not be made</AlertTitle>
              <AlertDescription>{actionError}</AlertDescription>
            </Alert>
          )}
          {active && mine && !mine.can_cancel && (
            <Alert variant="info">
              <AlertTitle>Ask staff to cancel</AlertTitle>
              <AlertDescription>{mine.cancel_reason}</AlertDescription>
            </Alert>
          )}
          {!active && !cancelled && session.eligibility_reasons.length > 0 && (
            <Alert variant="warning">
              <AlertTitle>Booking is not available</AlertTitle>
              <AlertDescription>
                <ul className="list-disc pl-4">{session.eligibility_reasons.map((reason) => <li key={reason}>{reason}</li>)}</ul>
              </AlertDescription>
            </Alert>
          )}
          {mine?.decision_reason && (
            <Alert variant="info">
              <AlertTitle>Staff decision</AlertTitle>
              <AlertDescription>{mine.decision_reason}</AlertDescription>
            </Alert>
          )}

          <div className="grid min-w-0 items-start gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div className="order-2 min-w-0 lg:order-1">
              <SessionFacts session={session} omit={['Date', 'Time', 'Fee', 'Availability', 'Capacity']} />
            </div>
            <aside className="order-1 min-w-0 overflow-hidden rounded-xl border bg-card lg:order-2 lg:sticky lg:top-6" aria-labelledby="session-booking">
              <div className="border-b bg-muted/30 px-5 py-4 sm:px-6">
                <h2 id="session-booking" className="font-semibold text-lg tracking-tight">Book your place</h2>
              </div>
              <div className="flex flex-col gap-5 p-5 sm:p-6">
                <div className="min-w-0">
                  <p className="text-muted-foreground text-xs">Session fee</p>
                  <p className="mt-1 font-semibold text-3xl tracking-tight tabular-nums wrap-anywhere">
                    {Number(session.fee) > 0 ? sessionFee(session.fee) : 'Included in membership'}
                  </p>
                  <p className="mt-2 text-muted-foreground text-sm">{availability}</p>
                </div>
                <dl className="grid grid-cols-2 gap-4 border-t pt-5 text-sm">
                  <div className="min-w-0">
                    <dt className="text-muted-foreground text-xs">Capacity</dt>
                    <dd className="mt-1 font-medium tabular-nums">{session.capacity} {session.capacity === 1 ? 'place' : 'places'}</dd>
                  </div>
                  <div className="min-w-0">
                    <dt className="text-muted-foreground text-xs">Room</dt>
                    <dd className="mt-1 font-medium wrap-anywhere">{session.room.name}</dd>
                  </div>
                </dl>

                {active && mine ? (
                  <Button className="min-h-11 h-auto w-full whitespace-normal py-2" variant="outline" disabled={!mine.can_cancel} onClick={() => setTarget({ booking: mine, action: 'cancel', name: session.name })}>
                    Cancel booking
                  </Button>
                ) : cancelled ? (
                  <p className="rounded-lg border bg-muted/40 px-4 py-3 text-muted-foreground text-sm leading-relaxed">
                    This session was cancelled, so booking is closed.
                  </p>
                ) : (
                  <Button className="min-h-11 h-auto w-full whitespace-normal py-2" loading={busy} disabled={session.eligibility_reasons.length > 0 || (session.places_left === 0 && !session.waiting_list_open)} onClick={book}>
                    {session.places_left === 0 ? session.waiting_list_open ? 'Join waiting list' : 'Not available' : session.needs_approval ? 'Request booking' : 'Book this session'}
                  </Button>
                )}

                {mine && (
                  <p className="text-sm leading-relaxed">
                    {bookingPaymentLabel(mine)}
                    {mine.status === 'booked' && Number(mine.balance) > 0 && <>. <Link className="underline underline-offset-4" to="/bookings">Pay online in My bookings</Link></>}
                  </p>
                )}

                {!cancelled && (
                <p className="border-t pt-4 text-muted-foreground text-xs leading-relaxed">
                  Cancel up to {session.cancel_cutoff_hours} hours before the start. After that, contact gym staff. Times use the gym's local clock.
                </p>
                )}
              </div>
            </aside>
          </div>
        </>
      )}
      {target && (
        <BookingActionDialog
          key={`${target.booking.id}-${target.action}`}
          target={target}
          onClose={() => setTarget(null)}
          onSaved={() => {
            setTarget(null)
            setActionError('')
            setVersion((v) => v + 1)
          }}
        />
      )}
    </div>
  )
}
