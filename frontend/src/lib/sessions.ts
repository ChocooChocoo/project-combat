import type { PublicService } from '@/lib/discovery'
import { dateLabel, gymDate } from '@/lib/membership'

/** Built-in types, group_session, or the gym's own types keyed custom_<slug> (D-24). */
export type SessionType = string
export type SessionLevel = 'beginner' | 'intermediate' | 'advanced' | 'all'
export type SessionStatus = 'scheduled' | 'cancelled'
/** Shown status worked out by the server from the Philippine clock. */
export type SessionPhase = 'scheduled' | 'ongoing' | 'ended' | 'cancelled'
export type SessionChoice = { id: number; name: string }

/** R-42: timestamps are gym-local wall times, not browser-local UTC conversions. */
export type TrainingSession = {
  id: number
  gym_service_id: number | null
  series_id: string | null
  type: SessionType
  name: string
  discipline: string | null
  level: SessionLevel
  coach_id: number
  room_id: number
  starts_at: string
  ends_at: string
  capacity: number
  allowed_plan_ids: number[] | null
  content: string | null
  requirements: string | null
  bring: string[] | null
  fee: string | null
  needs_approval: boolean | null
  status: SessionStatus
  phase: SessionPhase
  cancel_reason: string | null
  flagged_at: string | null
  flag_reason: string | null
  coach: SessionChoice
  room: SessionChoice
  service: SessionChoice | null
}

/** Type and level labels come from the existing offer configuration. */
export type SessionOptions = {
  coaches: SessionChoice[]
  rooms: SessionChoice[]
  services: PublicService[]
  plans: SessionChoice[]
  activity_types: Record<SessionType, string>
  levels: Record<SessionLevel, string>
}

export const SESSION_PHASES: Record<SessionPhase, { label: string; variant: 'outline' | 'success' | 'secondary' }> = {
  scheduled: { label: 'Scheduled', variant: 'outline' },
  ongoing: { label: 'Ongoing', variant: 'success' },
  ended: { label: 'Ended', variant: 'secondary' },
  cancelled: { label: 'Cancelled', variant: 'outline' },
}

export function sessionFee(fee: string | null): string {
  return Number(fee) > 0
    ? `₱${Number(fee).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
    : 'Included'
}

export function dateKey(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

export function sessionTime(value: string): string {
  return gymDate(value).toLocaleTimeString('en-PH', { timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit' })
}

export function flagReasonLabel(reason: string | null): string {
  return (reason ?? '').replace(/\((\d{4}-\d{2}-\d{2}) to (\d{4}-\d{2}-\d{2})\)/, (_, start: string, end: string) =>
    `(${dateLabel(start)}${start === end ? '' : ` to ${dateLabel(end)}`})`)
}
