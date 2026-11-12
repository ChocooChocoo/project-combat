import { useState } from 'react'
import { Ban, Check, CreditCard, MoreVertical, X } from 'lucide-react'
import { Link } from 'react-router'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Menu, MenuItem, MenuPopup, MenuTrigger } from '@/components/ui/menu'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { money } from '@/lib/membership'
import { BOOKING_STATUS_LABELS, BOOKING_STATUS_VARIANTS, bookingPaymentLabel, dayLabel, type Booking, type BookingStatus, type SessionDetails, type StaffBooking } from '@/lib/bookings'
import { sessionFee, sessionTime } from '@/lib/sessions'

export function BookingBadge({ status }: { status: BookingStatus }) {
  return <Badge variant={BOOKING_STATUS_VARIANTS[status]}>{BOOKING_STATUS_LABELS[status]}</Badge>
}

export function BookingError({ message, retry }: { message: string; retry: () => void }) {
  return (
    <Alert variant="error">
      <AlertTitle>Could not load this view</AlertTitle>
      <AlertDescription>
        <p>{message}</p>
        <Button variant="outline" onClick={retry}>Try again</Button>
      </AlertDescription>
    </Alert>
  )
}

export function BookingLoading() {
  return (
    <div className="flex flex-col gap-3" aria-busy="true" aria-label="Loading bookings">
      <Skeleton className="h-12" />
      <Skeleton className="h-28" />
      <Skeleton className="h-28" />
    </div>
  )
}

export function BookingEmpty({ title, description }: { title: string; description: string }) {
  return (
    <Empty className="rounded-xl border">
      <EmptyHeader>
        <EmptyTitle>{title}</EmptyTitle>
        <EmptyDescription>{description}</EmptyDescription>
      </EmptyHeader>
    </Empty>
  )
}

/** R-47: one full set of session facts for athlete and staff views. */
export function SessionFacts({ session, omit = [] }: { session: SessionDetails; omit?: string[] }) {
  const coach = session.coach
  const facts = ([
    ['Type', session.type_label], ['Discipline', session.discipline_label || 'Not specified'],
    ['Level', session.level_label], ['Date', dayLabel(session.starts_at)],
    ['Time', `${sessionTime(session.starts_at)}–${sessionTime(session.ends_at)} (${session.duration_minutes} minutes)`],
    ['Repeat', session.repeat_note || 'One session'], ['Room', session.room.name],
    ['Capacity', `${session.capacity} ${session.capacity === 1 ? 'place' : 'places'}`],
    ['Availability', session.places_left > 0 ? `${session.places_left} ${session.places_left === 1 ? 'place' : 'places'} left` : session.waiting_list_open ? 'Full, waiting list open' : session.type === 'private_coaching' ? 'Booked' : 'Not available'],
    ...(session.type === 'private_coaching' ? [] : [['Waiting list', `${session.waitlist_count} waiting`]]),
    ['Fee', Number(session.fee) > 0 ? sessionFee(session.fee) : 'Included in membership'],
    ['Approval', session.needs_approval ? 'Staff approval required' : 'No approval required'],
  ] as [string, string][]).filter(([label]) => !omit.includes(label))
  return (
    <div className="flex min-w-0 flex-col gap-6">
      <section className="min-w-0 overflow-hidden rounded-xl border bg-card" aria-labelledby="session-key-facts">
        <div className="border-b bg-muted/30 px-5 py-4 sm:px-6">
          <h2 id="session-key-facts" className="font-semibold text-lg tracking-tight">Session details</h2>
        </div>
        <dl className="grid gap-x-6 gap-y-5 p-5 sm:grid-cols-2 sm:p-6">
          {facts.map(([label, value]) => (
            <div key={label} className="min-w-0">
              <dt className="text-muted-foreground text-xs">{label}</dt>
              <dd className="mt-1 font-medium text-sm leading-relaxed wrap-anywhere">{value}</dd>
            </div>
          ))}
        </dl>
        <div className="divide-y border-t">
          {[['Training content', session.content], ['Requirements', session.requirements], ['What to bring', session.bring.join(', ')]].map(([label, value]) => (
            <section key={label} className="flex min-w-0 flex-col gap-2 px-5 py-5 sm:px-6">
              <h3 className="font-medium text-sm">{label}</h3>
              <p className="whitespace-pre-wrap text-muted-foreground text-sm leading-7 wrap-anywhere">{value || 'Not specified by the gym.'}</p>
            </section>
          ))}
        </div>
      </section>
      <section className="min-w-0 rounded-xl border bg-card p-5 sm:p-6" aria-label="Coach profile">
        <div className="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-start sm:gap-5">
          <Avatar className="size-16 shrink-0">
            <AvatarImage src={coach.photo_url ?? undefined} alt="" />
            <AvatarFallback>{coach.name.split(' ').map((name) => name[0]).slice(0, 2).join('')}</AvatarFallback>
          </Avatar>
          <div className="flex min-w-0 flex-1 flex-col gap-1">
            <p className="text-muted-foreground text-xs">Coach</p>
            <h2 className="font-semibold text-xl tracking-tight wrap-anywhere">{coach.name}</h2>
            {(coach.rank || coach.specialisation) && (
              <p className="mt-1 text-sm leading-relaxed wrap-anywhere">
                {[coach.rank, coach.specialisation].filter(Boolean).join(' · ')}
              </p>
            )}
          </div>
        </div>
        {(coach.experience || coach.bio) && (
          <div className="mt-5 flex flex-col gap-3 border-t pt-5 text-muted-foreground text-sm leading-7">
            {coach.experience && <p className="whitespace-pre-wrap wrap-anywhere">{coach.experience}</p>}
            <p className="whitespace-pre-wrap wrap-anywhere">{coach.bio || 'No public profile provided.'}</p>
          </div>
        )}
      </section>
    </div>
  )
}

export type BookingAction = { booking: Booking; action: 'approve' | 'reject' | 'cancel'; name: string }

/** R-49: rejection always collects a reason; server repeats the permission check. */
export function BookingActionDialog({ target, onClose, onSaved }: { target: BookingAction; onClose: () => void; onSaved: () => void }) {
  const [returnFocus] = useState(() => document.activeElement instanceof HTMLElement ? document.activeElement : null)
  const [reason, setReason] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [message, setMessage] = useState('')
  const [busy, setBusy] = useState(false)
  const verb = target.action === 'approve' ? 'Approve' : target.action === 'reject' ? 'Reject' : 'Cancel'
  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    setMessage('')
    try {
      const result = await api<{ booking?: { balance?: string } }>(`/gyms/${target.booking.gym_id}/bookings/${target.booking.id}/${target.action === 'cancel' ? 'cancel' : 'decision'}`, {
        method: 'POST', ...(target.action !== 'cancel' ? { body: { decision: target.action, reason: reason || null } } : {}),
      })
      const owed = target.action === 'approve' ? Number(result.booking?.balance ?? 0) : 0
      toastManager.add({
        title: `Booking ${target.action === 'approve' ? 'approved' : target.action === 'reject' ? 'rejected' : 'cancelled'}.`,
        ...(owed > 0 ? { description: `${money(owed)} is owed. Find it under "Approved, payment owed".` } : {}),
        type: 'success',
      })
      onSaved()
    } catch (failure) {
      setErrors(failure instanceof ApiError ? failure.errors : {})
      setMessage(failure instanceof ApiError ? Object.values(failure.errors).flat().join(' ') || failure.message : 'The booking could not be changed.')
    } finally {
      setBusy(false)
    }
  }
  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogContent finalFocus={() => returnFocus?.isConnected ? returnFocus : false}>
        <DialogHeader>
          <DialogTitle>{verb} booking?</DialogTitle>
          <DialogDescription>
            {target.name}. {target.action === 'cancel'
              ? 'A freed place goes to the first athlete on the waiting list.'
              : 'The athlete will be notified of this decision.'}
          </DialogDescription>
        </DialogHeader>
        <Form errors={errors} onSubmit={submit}>
          <DialogPanel className="flex flex-col gap-4">
            {message && (
              <Alert variant="error">
                <AlertTitle>Booking could not be changed</AlertTitle>
                <AlertDescription>{message}</AlertDescription>
              </Alert>
            )}
            {target.action === 'reject' && (
              <Field name="reason">
                <FieldLabel>Reason for rejection</FieldLabel>
                <Textarea name="reason" value={reason} onChange={(e) => setReason(e.target.value)} maxLength={2000} required autoFocus />
                <FieldError />
              </Field>
            )}
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>Go back</Button>
            <Button type="submit" variant={target.action === 'approve' ? 'default' : 'destructive'} loading={busy}>{verb} booking</Button>
          </DialogFooter>
        </Form>
      </DialogContent>
    </Dialog>
  )
}

export function StaffBookingList({ bookings, canApprove, canManage, onAction, onPayment, participant }: { bookings: StaffBooking[]; canApprove: boolean; canManage: boolean; onAction: (target: BookingAction) => void; onPayment?: (booking: StaffBooking) => void; participant?: (booking: StaffBooking) => React.ReactNode }) {
  const canRecordPayment = (booking: StaffBooking) => Boolean(onPayment && booking.status === 'booked' && Number(booking.balance) > 0)
  const canDecide = (booking: StaffBooking) => Boolean(canApprove && booking.status === 'requested')
  const canCancelBooking = (booking: StaffBooking) => Boolean(canManage && booking.can_cancel)
  const actions = (booking: StaffBooking) => {
    const hasActions = canRecordPayment(booking) || canDecide(booking) || canCancelBooking(booking)
    if (!hasActions) return <span className="text-muted-foreground" aria-hidden>—</span>
    return (
      <Menu>
        <MenuTrigger
          render={<Button size="icon" variant="ghost" aria-label={`Actions for ${booking.athlete.name}`} />}
        >
          <MoreVertical aria-hidden />
        </MenuTrigger>
        <MenuPopup align="end" className="min-w-48">
          {canRecordPayment(booking) && (
            <MenuItem onClick={() => onPayment?.(booking)}>
              <CreditCard aria-hidden /> Record payment
            </MenuItem>
          )}
          {canDecide(booking) && (
            <>
              <MenuItem onClick={() => onAction({ booking, action: 'approve', name: booking.athlete.name })}>
                <Check aria-hidden /> Approve
              </MenuItem>
              <MenuItem variant="destructive" onClick={() => onAction({ booking, action: 'reject', name: booking.athlete.name })}>
                <X aria-hidden /> Reject
              </MenuItem>
            </>
          )}
          {canCancelBooking(booking) && (
            <MenuItem variant="destructive" onClick={() => onAction({ booking, action: 'cancel', name: booking.athlete.name })}>
              <Ban aria-hidden /> Cancel booking
            </MenuItem>
          )}
        </MenuPopup>
      </Menu>
    )
  }
  const sessionLink = (booking: StaffBooking) => (
    <Link className="font-medium underline-offset-4 wrap-anywhere transition-colors hover:text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" to={`/gyms/${booking.gym_id}/manage/schedule/${booking.training_session_id}`}>
      {booking.session.name}
    </Link>
  )
  const athlete = (booking: StaffBooking) => (
    <div className="flex min-w-0 items-center gap-3">
      <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted font-medium text-xs" aria-hidden>
        {booking.athlete.name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()}
      </span>
      <span className="min-w-0 font-medium wrap-anywhere">{booking.athlete.name}</span>
      {booking.restricted && <Badge variant="warning" title="Has an open injury. Ask staff with health access for details.">Restricted</Badge>}
    </div>
  )
  const fee = (booking: StaffBooking) => {
    if (booking.payment_state === 'owed') {
      return (
        <div className="min-w-0">
          <p className="font-medium text-sm tabular-nums wrap-anywhere">{bookingPaymentLabel(booking)}</p>
          <p className="mt-0.5 text-muted-foreground text-xs">Payment outstanding</p>
        </div>
      )
    }
    return <p className="text-muted-foreground text-sm wrap-anywhere">{bookingPaymentLabel(booking)}</p>
  }
  if (bookings.length === 0) return <BookingEmpty title="No bookings here" description="Bookings will appear here when athletes join a session." />
  return (
    <>
      <div className="hidden overflow-x-auto lg:block">
        <Table variant="card">
          <TableHeader>
            <TableRow>
              <TableHead>Athlete</TableHead>
              <TableHead>Session</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Fee</TableHead>
              {participant && <TableHead>Attendance and notes</TableHead>}
              <TableHead><span className="sr-only">Actions</span></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {bookings.map((booking) => (
              <TableRow key={booking.id}>
                <TableCell>{athlete(booking)}</TableCell>
                <TableCell className="max-w-64 whitespace-normal break-words min-w-48">
                  {sessionLink(booking)}
                  <span className="mt-1 block text-muted-foreground text-xs">
                    {dayLabel(booking.session.starts_at)}, {sessionTime(booking.session.starts_at)}
                  </span>
                </TableCell>
                <TableCell>
                  <BookingBadge status={booking.status} />
                  {booking.waitlist_position && <span className="mt-1 block text-muted-foreground text-xs tabular-nums">Position {booking.waitlist_position}</span>}
                  {booking.decision_reason && <p className="mt-1 max-w-64 whitespace-normal text-sm">{booking.decision_reason}</p>}
                </TableCell>
                <TableCell>{fee(booking)}</TableCell>
                {participant && <TableCell className="min-w-72 max-w-sm whitespace-normal">{participant(booking)}</TableCell>}
                <TableCell className="w-12 text-right">{actions(booking)}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
      <ul className="flex flex-col gap-3 lg:hidden">
        {bookings.map((booking) => (
          <li key={booking.id} className="flex min-w-0 flex-col gap-3 rounded-xl border p-4">
            {athlete(booking)}
            <div className="min-w-0">
              {sessionLink(booking)}
              <p className="mt-1 text-muted-foreground text-xs">{dayLabel(booking.session.starts_at)}, {sessionTime(booking.session.starts_at)}</p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <BookingBadge status={booking.status} />
              {booking.waitlist_position && <span className="text-muted-foreground text-xs tabular-nums">Position {booking.waitlist_position}</span>}
            </div>
            {fee(booking)}
            {booking.decision_reason && <p className="break-words text-sm">{booking.decision_reason}</p>}
            {participant?.(booking)}
            <div className="flex justify-end">{actions(booking)}</div>
          </li>        ))}
      </ul>
    </>
  )
}
