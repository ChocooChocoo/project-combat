import { useEffect, useState } from 'react'
import { useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { BookingActionDialog, BookingEmpty, BookingError, BookingLoading, StaffBookingList, type BookingAction } from '@/components/schedule/booking-parts'
import { api, ApiError } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import type { StaffBooking } from '@/lib/bookings'
import { approvedGym, can } from '@/lib/gym-access'

/** R-49: requests are visible to training viewers; decisions require approval access. */
export default function GymBookings() {
  const { gymId } = useParams()
  const { user } = useAuth()
  const gym = approvedGym(user, Number(gymId))
  const canView = Boolean(gym && can(gym, 'training.bookings.view'))
  const [bookings, setBookings] = useState<StaffBooking[] | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [target, setTarget] = useState<BookingAction | null>(null)
  useEffect(() => {
    if (!canView) return
    let live = true
    setBookings(null)
    setError('')
    api<{ bookings: StaffBooking[] }>(`/gyms/${gymId}/bookings?status=requested`)
      .then((result) => live && setBookings(result.bookings))
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'Booking requests could not be loaded.'))
    return () => { live = false }
  }, [gymId, canView, version])
  if (!gym) return null
  if (!canView) return <BookingEmpty title="Bookings are not available" description="Your role does not include training access for this gym." />
  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Bookings" description="Requested bookings reserve places until someone with approval access decides." />
      {error ? (
        <BookingError message={error} retry={() => setVersion((v) => v + 1)} />
      ) : !bookings ? (
        <BookingLoading />
      ) : bookings.length === 0 ? (
        <BookingEmpty title="No bookings waiting for approval" description="New requests appear here when approval is required. Open a session in Schedule for its full roster." />
      ) : (
        <StaffBookingList
          bookings={bookings}
          canApprove={can(gym, 'training.bookings.approve')}
          canManage={can(gym, 'training.bookings.manage')}
          onAction={setTarget}
        />
      )}
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
