export type AttendanceStatus = 'present' | 'late' | 'absent'
export type AttendanceSource = 'self' | 'desk' | 'system'
export type OvertimeStatus = 'pending' | 'approved' | 'rejected'
export type OvertimeKind = 'after_shift' | 'rest_day' | 'manual'
export type PerformanceKind = 'commendation' | 'concern' | 'review' | 'general'

export type StaffAttendance = {
  id: number
  user_id: number
  user_name: string | null
  work_date: string
  scheduled_start: string | null
  scheduled_end: string | null
  clock_in_time: string | null
  clock_out_time: string | null
  clock_in_at: string | null
  clock_out_at: string | null
  status: AttendanceStatus
  late_minutes: number
  undertime_minutes: number
  worked_minutes: number
  missing_clock_out: boolean
  source: AttendanceSource
  explanation: string | null
  recorded_by_name: string | null
  changes_count?: number | null
}

export type AttendanceChange = {
  id: number
  changed_by_name: string | null
  reason: string
  before: Record<string, unknown>
  after: Record<string, unknown>
  created_at: string | null
}

export type OvertimeRequest = {
  id: number
  user_id: number
  user_name: string | null
  work_date: string
  kind: OvertimeKind
  requested_minutes: number
  approved_minutes: number | null
  reason: string
  status: OvertimeStatus
  decided_by_name: string | null
  decided_at: string | null
  decision_note: string | null
}

export type PerformanceNote = {
  id: number
  user_id: number
  author_id: number | null
  author_name: string | null
  noted_on: string
  kind: PerformanceKind
  body: string
  created_at: string | null
}

export type MyAttendanceData = {
  attendance: StaffAttendance[]
  today: StaffAttendance | null
  overtime: OvertimeRequest[]
  settings: { grace_minutes: number; overtime_min_minutes: number }
}

export const ATTENDANCE_STATUS: Record<AttendanceStatus, { label: string; variant: 'success' | 'warning' | 'error' }> = {
  present: { label: 'Present', variant: 'success' },
  late: { label: 'Late', variant: 'warning' },
  absent: { label: 'Absent', variant: 'error' },
}

export const OVERTIME_STATUS: Record<OvertimeStatus, { label: string; variant: 'warning' | 'success' | 'error' }> = {
  pending: { label: 'Pending', variant: 'warning' },
  approved: { label: 'Approved', variant: 'success' },
  rejected: { label: 'Rejected', variant: 'error' },
}

export const OVERTIME_KIND: Record<OvertimeKind, string> = {
  after_shift: 'After shift',
  rest_day: 'Rest day',
  manual: 'Manual request',
}

export const PERFORMANCE_KIND: Record<PerformanceKind, string> = {
  commendation: 'Commendation',
  concern: 'Concern',
  review: 'Review',
  general: 'General',
}

export function minutesLabel(minutes: number): string {
  const safe = Math.max(0, Math.round(minutes))
  const hours = Math.floor(safe / 60)
  const rest = safe % 60
  if (hours === 0) return `${rest} min`
  return rest === 0 ? `${hours} hr` : `${hours} hr ${rest} min`
}

export function timeRange(from: string | null, to: string | null): string {
  return `${from ?? 'Not set'} – ${to ?? 'Not set'}`
}

/** Dot colour that sits next to a status word. The word always carries the meaning. */
export const ATTENDANCE_DOT: Record<AttendanceStatus, string> = {
  present: 'bg-success',
  late: 'bg-warning',
  absent: 'bg-destructive',
}

function parseIsoDay(value: string): Date | null {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value)
  if (!match) return null
  const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
  return Number.isNaN(date.getTime()) ? null : date
}

/** "Thu, 5 Nov", with the year added when it is not this year. */
export function workDayLabel(value: string): string {
  const date = parseIsoDay(value)
  if (!date) return value
  const sameYear = date.getFullYear() === new Date().getFullYear()
  return date.toLocaleDateString('en-PH', { weekday: 'short', day: 'numeric', month: 'short', ...(sameYear ? {} : { year: 'numeric' }) })
}

/** "Thursday, 5 November 2026". */
export function longDayLabel(value: string): string {
  const date = parseIsoDay(value)
  return date ? date.toLocaleDateString('en-PH', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) : value
}

/** "08:05" from the API becomes "8:05 AM". Anything unexpected is shown as it came. */
export function clockLabel(value: string | null | undefined): string {
  if (!value) return ''
  const match = /^(\d{1,2}):(\d{2})/.exec(value)
  if (!match) return value
  const hours = Number(match[1])
  return `${hours % 12 || 12}:${match[2]} ${hours < 12 ? 'AM' : 'PM'}`
}

/** "AC" for "Ana Cruz". */
export function initialsOf(name: string | null | undefined): string {
  const parts = (name ?? '').trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return '?'
  return `${parts[0][0] ?? ''}${parts.length > 1 ? (parts[parts.length - 1][0] ?? '') : ''}`.toUpperCase()
}
