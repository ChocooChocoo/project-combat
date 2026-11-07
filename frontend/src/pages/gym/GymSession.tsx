import { useCallback, useState } from 'react'
import { Link, useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { BookingActionDialog, BookingEmpty, BookingError, BookingLoading, SessionFacts, StaffBookingList, type BookingAction } from '@/components/schedule/booking-parts'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { CancelSessionDialog } from '@/components/schedule/session-actions'
import { RecordPaymentDialog } from '@/components/membership/payment-dialogs'
import { SessionAttendance, SessionNoteDialog } from '@/components/schedule/session-attendance'
import { ProgressNotes } from '@/components/schedule/attendance-list'
import type { ProgressNote } from '@/lib/attendance'
import { api, ApiError } from '@/lib/api'
import { usePolling } from '@/hooks/use-polling'
import { useAuth } from '@/lib/auth'
import type { SessionDetails, StaffBooking } from '@/lib/bookings'
import { approvedGym, can } from '@/lib/gym-access'
import { flagReasonLabel } from '@/lib/sessions'

/** R-47, R-49, R-50: session panel and ordered roster for the training desk. */
export default function GymSession() {
  const { gymId, sessionId } = useParams()
  const { user } = useAuth()
  const gym = approvedGym(user, Number(gymId))
  const canView = Boolean(gym && can(gym, 'training.sessions.view'))
  const [data, setData] = useState<{ session: SessionDetails; bookings: StaffBooking[]; notes: ProgressNote[] } | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [target, setTarget] = useState<BookingAction | null>(null)
  const [cancelling, setCancelling] = useState(false)
  const [paying, setPaying] = useState<StaffBooking | null>(null)
  const [noteTarget, setNoteTarget] = useState<{ userId: number | null; name: string } | null>(null)
  const load = useCallback(async (silent: boolean, isCurrent: () => boolean) => {
    if (!silent) setError('')
    try {
      const results = await Promise.allSettled([
        api<{ session: SessionDetails }>(`/gyms/${gymId}/timetable/${sessionId}`),
        api<{ bookings: StaffBooking[]; notes: ProgressNote[] }>(`/gyms/${gymId}/sessions/${sessionId}/roster`),
      ])
      const [detailsResult, rosterResult] = results
      if (detailsResult.status === 'rejected') throw detailsResult.reason
      if (rosterResult.status === 'rejected') throw rosterResult.reason
      const details = detailsResult.value
      const roster = rosterResult.value
      if (isCurrent()) { setData({ session: details.session, bookings: roster.bookings, notes: roster.notes }); setError('') }
    } catch (failure) {
      if (isCurrent() && !silent) setError(failure instanceof ApiError ? failure.message : 'The session could not be loaded.')
    }
  }, [gymId, sessionId])
  usePolling(load, 15_000, Boolean(user && canView), version)
  if (!gym) return null
  if (!canView) return <BookingEmpty title="Session roster is private" description="Your role does not include training access for this gym." />
  return (
    <div className="flex flex-col gap-6">
      <Button className="self-start" variant="outline" render={<Link to={`/gyms/${gymId}/manage/schedule`} />}>Back to schedule</Button>
      {error ? (
        <BookingError message={error} retry={() => setVersion((v) => v + 1)} />
      ) : !data ? (
        <BookingLoading />
      ) : (
        <>
          <PageHeader title={data.session.name} meta={<Badge variant="outline">{data.session.status === 'cancelled' ? 'Cancelled' : 'Scheduled'}</Badge>} actions={can(gym, 'training.sessions.manage') && data.session.status === 'scheduled' && <Button variant="destructive-outline" onClick={() => setCancelling(true)}>Cancel session</Button>} />
          {data.session.flagged_at && <Alert variant="warning"><AlertTitle>Needs attention</AlertTitle><AlertDescription>{flagReasonLabel(data.session.flag_reason)}. Use Schedule to reassign, move or cancel.</AlertDescription></Alert>}
          {data.session.cancel_reason && <p className="text-sm">Cancellation reason: {data.session.cancel_reason}</p>}
          <SessionFacts session={data.session} />
          <section className="flex min-w-0 flex-col gap-3">
            <h2 className="font-semibold text-xl">Session notes</h2>
            <ProgressNotes notes={data.notes.filter((note) => note.user_id === null)} />
            {(can(gym, 'training.sessions.manage') || data.session.coach.id === user?.id) && <Button variant="outline" className="self-start" onClick={() => setNoteTarget({ userId: null, name: data.session.name })}>Add session note</Button>}
          </section>
          <section className="flex min-w-0 flex-col gap-4">
            <h2 className="font-semibold text-xl">Roster</h2>
            <p className="text-muted-foreground text-sm">Booked athletes, requests, waiting-list positions, then cancelled or rejected bookings.</p>
            <StaffBookingList
              bookings={data.bookings}
              canApprove={can(gym, 'training.bookings.approve')}
              canManage={can(gym, 'training.bookings.manage')}
              onAction={setTarget}
              onPayment={can(gym, 'finance.payments.manage') ? setPaying : undefined}
              participant={(booking) => <SessionAttendance booking={booking} canManage={can(gym, 'training.attendance.manage')} notes={data.notes.filter((note) => note.user_id === booking.user_id)} onSaved={() => setVersion((v) => v + 1)} onNote={can(gym, 'training.sessions.manage') || data.session.coach.id === user?.id ? () => setNoteTarget({ userId: booking.user_id, name: booking.athlete.name }) : undefined} />}
            />
          </section>
        </>
      )}
      {cancelling && data && <CancelSessionDialog gymId={Number(gymId)} session={data.session} onClose={() => setCancelling(false)} onSaved={() => { setCancelling(false); setVersion((v) => v + 1) }} />}
      {noteTarget && <SessionNoteDialog gymId={Number(gymId)} sessionId={Number(sessionId)} target={noteTarget} onClose={() => setNoteTarget(null)} onSaved={() => { setNoteTarget(null); setVersion((v) => v + 1) }} />}
      {paying && <RecordPaymentDialog gymId={Number(gymId)} open memberships={[]} bookings={[paying]} onClose={() => setPaying(null)} onSaved={() => { setPaying(null); setVersion((v) => v + 1) }} />}
      {target && (
        <BookingActionDialog
          key={`${target.booking.id}-${target.action}`}
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
