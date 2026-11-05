import { useEffect, useState } from 'react'
import { useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { AttendanceList } from '@/components/schedule/attendance-list'
import { BookingEmpty, BookingError, BookingLoading } from '@/components/schedule/booking-parts'
import { TextField } from '@/components/text-field'
import { api, ApiError } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import type { AttendanceRow } from '@/lib/attendance'
import { approvedGym, can } from '@/lib/gym-access'
import { dateKey } from '@/lib/sessions'
import { shiftDay } from '@/lib/bookings'
import { gymDate } from '@/lib/membership'

type Choice = { id: number; name: string; starts_at?: string }
type History = { attendance: AttendanceRow[]; sessions: Choice[]; classes: Choice[]; members: Choice[] }

/** R-54: gym history, with session, class and athlete filters. */
export default function GymAttendance() {
  const { gymId } = useParams()
  const { user } = useAuth()
  const gym = approvedGym(user, Number(gymId))
  const allowed = Boolean(gym && can(gym, 'training.attendance.view'))
  const [filters, setFilters] = useState(() => ({ from: shiftDay(dateKey(new Date()), -30), to: dateKey(new Date()), session_id: '', gym_service_id: '', user_id: '' }))
  const [data, setData] = useState<History | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  useEffect(() => {
    if (!allowed) return
    let live = true
    const query = new URLSearchParams(Object.entries(filters).filter(([, value]) => value))
    api<History>(`/gyms/${gymId}/attendance?${query}`)
      .then((result) => { if (live) { setError(''); setData(result) } })
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'Attendance could not be loaded.'))
    return () => { live = false }
  }, [gymId, allowed, filters, version])
  if (!allowed) return <BookingEmpty title="Attendance is private" description="Your role does not include training access." />
  return <div className="flex min-w-0 flex-col gap-6">
    <PageHeader title="Attendance" description="Session attendance and gym visits, including recorded absences and no-shows." />
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <TextField name="from" label="From" type="date" value={filters.from} onChange={(e) => setFilters({ ...filters, from: e.target.value })} />
      <TextField name="to" label="To" type="date" min={filters.from} value={filters.to} onChange={(e) => setFilters({ ...filters, to: e.target.value })} />
      {(['session_id', 'gym_service_id', 'user_id'] as const).map((key) => {
        const label = key === 'session_id' ? 'Session' : key === 'gym_service_id' ? 'Class / service' : 'Member'
        const choices = key === 'session_id' ? data?.sessions : key === 'gym_service_id' ? data?.classes : data?.members
        return <label key={key} className="flex min-w-0 flex-col gap-2 text-sm font-medium">{label}
          <select className="h-11 w-full min-w-0 rounded-lg border bg-background px-3 focus-visible:outline-2 focus-visible:outline-ring" value={filters[key]} onChange={(e) => setFilters({ ...filters, [key]: e.target.value })}>
            <option value="">All</option>
            {choices?.map((choice) => <option key={choice.id} value={choice.id}>{choice.name}{choice.starts_at ? ` · ${gymDate(choice.starts_at).toLocaleString('en-PH', { timeZone: 'Asia/Manila', weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })}` : ''}</option>)}
          </select>
        </label>
      })}
    </div>
    {error ? <BookingError message={error} retry={() => setVersion((v) => v + 1)} /> : !data ? <BookingLoading /> : <AttendanceList rows={data.attendance} />}
  </div>
}
