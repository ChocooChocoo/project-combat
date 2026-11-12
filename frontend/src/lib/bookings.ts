import type { SessionChoice, SessionLevel, SessionPhase, SessionStatus, SessionType } from '@/lib/sessions'
import { dateKey } from '@/lib/sessions'
import { money, type Payment } from '@/lib/membership'
import type { Attendance } from '@/lib/attendance'

export type BookingStatus = 'requested' | 'booked' | 'waitlisted' | 'rejected' | 'cancelled'

export const BOOKING_STATUS_LABELS: Record<BookingStatus, string> = {
  requested: 'Requested', booked: 'Booked', waitlisted: 'Waiting list', rejected: 'Rejected', cancelled: 'Cancelled',
}
export const BOOKING_STATUS_VARIANTS: Record<BookingStatus, 'warning' | 'success' | 'info' | 'error' | 'outline'> = {
  requested: 'warning', booked: 'success', waitlisted: 'info', rejected: 'error', cancelled: 'outline',
}

export type SessionSummary = {
  id: number
  name: string
  type: SessionType
  type_label: string
  discipline: string | null
  discipline_label: string | null
  level: SessionLevel
  level_label: string
  starts_at: string
  ends_at: string
  status: SessionStatus
  phase: SessionPhase
  capacity: number
  fee: string | null
  flagged_at: string | null
  flag_reason: string | null
  cancel_reason: string | null
  coach: SessionChoice
  room: SessionChoice
}

export type Booking = {
  id: number
  gym_id: number
  training_session_id: number
  user_id: number
  status: BookingStatus
  fee: string
  paid: string
  balance: string
  payment_state: 'none' | 'included' | 'owed' | 'paid' | 'refunded'
  payments: Payment[]
  waitlisted_at: string | null
  decided_at: string | null
  decided_by: number | null
  decision_reason: string | null
  cancelled_at: string | null
  cancelled_by: number | null
  can_cancel: boolean
  cancel_reason: string | null
  check_in_at: string | null
  check_out_at: string | null
  attendance: Attendance | null
  attendance_method: string | null
  attendance_by: string | null
}

export type StaffBooking = Booking & {
  athlete: SessionChoice
  session: SessionSummary
  waitlist_position?: number | null
  /** R-119: open injury, details hidden. Sent on session rosters. */
  restricted?: boolean
}
export type MyBooking = Booking & { gym: SessionChoice; session: SessionSummary }
export type OwedBooking = { id: number; balance: string; athlete: SessionChoice; session: { name: string; starts_at: string } }

export function bookingPaymentLabel(booking: Booking): string {
  if (booking.payment_state === 'none') return 'No payment owed'
  if (booking.payment_state === 'included') return 'Included in membership'
  if (booking.payment_state === 'refunded') return 'Refund recorded, staff return the money'
  if (booking.payment_state === 'paid') return `Paid ${money(booking.paid)}`
  return `Owed ${money(booking.balance)}${Number(booking.paid) > 0 ? ` (paid ${money(booking.paid)})` : ''}`
}

export type TimetableSession = SessionSummary & {
  places_left: number
  waitlist_count: number
  waiting_list_open: boolean
  needs_approval: boolean
  my_booking: Booking | null
}

export type SessionDetails = TimetableSession & {
  coach: SessionChoice & {
    photo_url: string | null
    rank: string | null
    specialisation: string | null
    experience: string | null
    bio: string | null
  }
  duration_minutes: number
  repeat_note: string | null
  content: string | null
  requirements: string | null
  bring: string[]
  allowed_plan_ids: number[]
  eligibility_reasons: string[]
  cancel_cutoff_hours: number
}

export type PrivateService = SessionChoice & { duration_minutes: number | null; price: string | null; price_included: boolean }
export type Timetable = { sessions: TimetableSession[]; coaches: SessionChoice[]; private_services: PrivateService[] }
export type OpenSlot = { starts_at: string; ends_at: string }
export type CoachDay = {
  date: string
  start_time: string | null
  end_time: string | null
  is_day_off: boolean
  approved_leave: boolean
  holidays: { name: string; national: boolean }[]
  sessions: SessionSummary[]
}

export const liveBooking = (booking: Booking | null) => Boolean(booking && ['booked', 'requested', 'waitlisted'].includes(booking.status))

export function dayLabel(date: string): string {
  return new Date(`${date.slice(0, 10)}T12:00:00`).toLocaleDateString('en-PH', { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' })
}

export function shiftDay(date: string, days: number): string {
  const next = new Date(`${date}T12:00:00`)
  next.setDate(next.getDate() + days)
  return dateKey(next)
}

export function weekStart(): string {
  const date = new Date()
  date.setDate(date.getDate() - (date.getDay() + 6) % 7)
  return dateKey(date)
}
