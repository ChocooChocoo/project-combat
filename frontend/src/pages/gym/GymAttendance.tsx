import { useEffect, useState } from 'react'
import { useParams } from 'react-router'
import { AttendanceList } from '@/components/schedule/attendance-list'
import { BookingEmpty, BookingError, BookingLoading } from '@/components/schedule/booking-parts'
import { TextField } from '@/components/text-field'
import { Button } from '@/components/ui/button'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { api, ApiError } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import type { AttendanceRow } from '@/lib/attendance'
import { approvedGym, can } from '@/lib/gym-access'
import { dateKey } from '@/lib/sessions'
import { shiftDay } from '@/lib/bookings'
import { gymDate } from '@/lib/membership'

type Choice = { id: number; name: string; starts_at?: string }
type History = { attendance: AttendanceRow[]; sessions: Choice[]; classes: Choice[]; members: Choice[] }

const makeFilters = () => ({ from: shiftDay(dateKey(new Date()), -30), to: dateKey(new Date()), session_id: '', gym_service_id: '', user_id: '' })

/** R-54: gym history, with session, class and athlete filters. */
export default function GymAttendance() {
  const { gymId } = useParams()
  const { user } = useAuth()
  const gym = approvedGym(user, Number(gymId))
  const allowed = Boolean(gym && can(gym, 'training.attendance.view'))
  const [filters, setFilters] = useState(makeFilters)
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
    <header className="flex flex-wrap items-end justify-between gap-4 border-b pb-6">
      <div>
        <h1 className="font-semibold text-3xl tracking-tight sm:text-4xl">Attendance</h1>
        <p className="mt-3 max-w-prose text-muted-foreground text-sm leading-relaxed">Session attendance and gym visits, including recorded absences and no-shows.</p>
      </div>
      {data && (
        <div>
          <p className="text-muted-foreground text-xs">Records</p>
          <p className="mt-1 font-semibold text-2xl tabular-nums">{data.attendance.length}</p>
        </div>
      )}
    </header>
    <section className="flex flex-col gap-4 rounded-xl border bg-card p-5" aria-labelledby="attendance-filters">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 id="attendance-filters" className="font-medium text-sm">Filters</h2>
          <p className="mt-1 text-muted-foreground text-xs">Last 30 days by default. Every filter is optional.</p>
        </div>
        <Button size="sm" variant="ghost" onClick={() => setFilters(makeFilters())}>Reset filters</Button>
      </div>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <TextField name="from" label="From" type="date" value={filters.from} onChange={(e) => setFilters({ ...filters, from: e.target.value })} />
      <TextField name="to" label="To" type="date" min={filters.from} value={filters.to} onChange={(e) => setFilters({ ...filters, to: e.target.value })} />
      {(['session_id', 'gym_service_id', 'user_id'] as const).map((key) => {
        const label = key === 'session_id' ? 'Session' : key === 'gym_service_id' ? 'Class / service' : 'Member'
        const choices = key === 'session_id' ? data?.sessions : key === 'gym_service_id' ? data?.classes : data?.members
        const options = [
          { value: 'all', label: `All ${label.toLowerCase()}` },
          ...(choices ?? []).map((choice) => ({
            value: String(choice.id),
            label: `${choice.name}${choice.starts_at ? ` · ${gymDate(choice.starts_at).toLocaleString('en-PH', { timeZone: 'Asia/Manila', weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })}` : ''}`,
          })),
        ]
        return (
          <div key={key} className="flex min-w-0 flex-col gap-2">
            <span className="font-medium text-base/4.5 sm:text-sm/4">{label}</span>
            <Select
              items={options}
              value={filters[key] || 'all'}
              onValueChange={(next) => setFilters({ ...filters, [key]: next === 'all' ? '' : String(next) })}
            >
              <SelectTrigger aria-label={`Filter by ${label.toLowerCase()}`}>
                <SelectValue />
              </SelectTrigger>
              <SelectPopup>
                {options.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectPopup>
            </Select>
          </div>
        )
      })}
      </div>
    </section>
    {error ? <BookingError message={error} retry={() => setVersion((v) => v + 1)} /> : !data ? <BookingLoading /> : <AttendanceList rows={data.attendance} />}
  </div>
}
