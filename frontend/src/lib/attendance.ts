export type Attendance = 'present' | 'late' | 'absent' | 'no_show'
export const ATTENDANCE_LABELS = { present: 'Present', late: 'Late', absent: 'Absent', no_show: 'No-show', gym_visit: 'Gym visit' }
export type AttendanceRow = {
  id: string
  booking_id: number | null
  member: { id: number; name: string }
  session: { id: number; name: string } | null
  date: string
  check_in_at: string | null
  check_out_at: string | null
  attendance: Attendance | 'gym_visit'
  method: string | null
  by: string | null
}
export type ProgressNote = {
  id: number
  user_id: number | null
  body: string
  date: string
  session: { id: number; name: string }
  author: string
  created_at: string
}
