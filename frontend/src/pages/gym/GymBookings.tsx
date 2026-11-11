import { useCallback, useState } from 'react'
import { useParams } from 'react-router'
import { BookingActionDialog, BookingEmpty, BookingError, BookingLoading, StaffBookingList, type BookingAction } from '@/components/schedule/booking-parts'
import { api, ApiError } from '@/lib/api'
import { usePolling } from '@/hooks/use-polling'
import { useAuth } from '@/lib/auth'
import type { StaffBooking } from '@/lib/bookings'
import { approvedGym, can } from '@/lib/gym-access'
import { RecordPaymentDialog } from '@/components/membership/payment-dialogs'

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
  const [owing, setOwing] = useState<StaffBooking[]>([])
  const [paying, setPaying] = useState<StaffBooking | null>(null)
  const canPay = Boolean(gym && can(gym, 'finance.payments.manage'))
  const load = useCallback(async (silent: boolean, isCurrent: () => boolean) => {
    if (!silent) setError('')
    try {
      const result = await api<{ bookings: StaffBooking[] }>(`/gyms/${gymId}/bookings?status=requested`)
      const booked = await api<{ bookings: StaffBooking[] }>(`/gyms/${gymId}/bookings?status=booked`)
      if (isCurrent()) { setBookings(result.bookings); setOwing(booked.bookings.filter((b) => Number(b.balance) > 0)); setError('') }
    } catch (failure) {
      if (isCurrent() && !silent) setError(failure instanceof ApiError ? failure.message : 'Booking requests could not be loaded.')
    }
  }, [gymId])
  usePolling(load, 15_000, Boolean(user && canView), version)
  if (!gym) return null
  if (!canView) return <BookingEmpty title="Bookings are not available" description="Your role does not include training access for this gym." />
  return (
    <div className="flex min-w-0 flex-col gap-6">
      <header className="flex flex-wrap items-end justify-between gap-4 border-b pb-6">
        <div>
          <h1 className="font-semibold text-3xl tracking-tight sm:text-4xl">Bookings</h1>
          <p className="mt-3 max-w-prose text-muted-foreground text-sm leading-relaxed">Requested bookings reserve places until someone with approval access decides.</p>
        </div>
        {bookings && bookings.length > 0 && (
          <div>
            <p className="text-muted-foreground text-xs">Waiting for a decision</p>
            <p className="mt-1 font-semibold text-2xl tabular-nums">{bookings.length}</p>
          </div>
        )}
      </header>
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
      {owing.length > 0 && gym && (
        <section className="flex flex-col gap-3">
          <h2 className="font-medium text-lg">Approved, payment owed</h2>
          {!canPay && <p className="text-muted-foreground text-sm">Ask the front desk to record these payments in Payments.</p>}
          <StaffBookingList bookings={owing} canApprove={false} canManage={can(gym, 'training.bookings.manage')} onAction={setTarget} onPayment={canPay ? setPaying : undefined} />
        </section>
      )}
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
