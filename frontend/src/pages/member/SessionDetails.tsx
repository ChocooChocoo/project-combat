import { useCallback, useState } from 'react'
import { Link, useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { BookingActionDialog, BookingBadge, BookingError, BookingLoading, SessionFacts, type BookingAction } from '@/components/schedule/booking-parts'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError } from '@/lib/api'
import { usePolling } from '@/hooks/use-polling'
import { useAuth } from '@/lib/auth'
import { bookingPaymentLabel, liveBooking, type SessionDetails as Details } from '@/lib/bookings'

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
  return (
    <div className="flex flex-col gap-6">
      <Button className="self-start" variant="outline" render={<Link to={`/gyms/${gymId}/sessions`} />}>Back to sessions</Button>
      {error ? (
        <BookingError message={error} retry={() => setVersion((v) => v + 1)} />
      ) : !session ? (
        <BookingLoading />
      ) : (
        <>
          <PageHeader
            title={session.name}
            meta={mine && <BookingBadge status={mine.status} />}
            actions={active && mine ? (
              <Button variant="outline" disabled={!mine.can_cancel} onClick={() => setTarget({ booking: mine, action: 'cancel', name: session.name })}>
                Cancel booking
              </Button>
            ) : (
              <Button loading={busy} disabled={session.eligibility_reasons.length > 0 || (session.places_left === 0 && !session.waiting_list_open)} onClick={book}>
                {session.places_left === 0 ? session.waiting_list_open ? 'Join waiting list' : 'Not available' : session.needs_approval ? 'Request booking' : 'Book'}
              </Button>
            )}
          />
          {mine && <p className="text-sm">{bookingPaymentLabel(mine)}{mine.status === 'booked' && Number(mine.balance) > 0 && <>. <Link className="underline underline-offset-4" to="/bookings">Pay online in My bookings</Link></>}</p>}
          {session.cancel_reason && <p className="text-sm">Cancellation reason: {session.cancel_reason}</p>}
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
          {!active && session.eligibility_reasons.length > 0 && (
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
          <SessionFacts session={session} />
          <p className="text-muted-foreground text-sm">
            You can cancel up to {session.cancel_cutoff_hours} hours before the start. After that, contact gym staff. Times use the gym's local clock.
          </p>
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
