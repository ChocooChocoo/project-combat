import { useCallback, useEffect, useRef, useState } from 'react'
import { useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { AttendanceList } from '@/components/schedule/attendance-list'
import { BookingEmpty, BookingError, BookingLoading } from '@/components/schedule/booking-parts'
import { CameraScanner } from '@/components/schedule/camera-scanner'
import { TextField } from '@/components/text-field'
import { Button } from '@/components/ui/button'
import { api, ApiError } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { ATTENDANCE_LABELS, type AttendanceRow } from '@/lib/attendance'
import { approvedGym, can } from '@/lib/gym-access'

/** R-52: one field accepts typed short codes and keyboard/USB QR scanners. */
export default function GymCheckIn() {
  const { gymId } = useParams()
  const { user } = useAuth()
  const gym = approvedGym(user, Number(gymId))
  const allowed = Boolean(gym && can(gym, 'training.check_ins.manage'))
  const field = useRef<HTMLInputElement>(null)
  const inFlight = useRef(false)
  const [code, setCode] = useState('')
  const [busy, setBusy] = useState(false)
  const [camera, setCamera] = useState(false)
  const [message, setMessage] = useState('')
  const [search, setSearch] = useState('')
  const [searchResult, setSearchResult] = useState<{ search: string; members: { id: number; name: string }[]; error: string } | null>(null)
  const members = searchResult?.search === search ? searchResult.members : null
  const searchError = searchResult?.search === search ? searchResult.error : ''
  const [rows, setRows] = useState<AttendanceRow[] | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  useEffect(() => {
    if (!allowed) return
    let live = true
    api<{ check_ins: AttendanceRow[] }>(`/gyms/${gymId}/check-ins`)
      .then((result) => { if (live) { setError(''); setRows(result.check_ins) } })
      .catch(() => live && setError("Today's check-ins could not be loaded."))
    return () => { live = false }
  }, [gymId, allowed, version])
  useEffect(() => {
    if (!allowed || !search.trim()) return
    let live = true
    const timer = setTimeout(() => {
      api<{ members: { id: number; name: string }[] }>(`/gyms/${gymId}/check-ins/members?search=${encodeURIComponent(search)}`)
        .then((result) => live && setSearchResult({ search, members: result.members, error: '' }))
        .catch(() => live && setSearchResult({ search, members: [], error: 'Member search failed. Try changing the name.' }))
    }, 250)
    return () => { live = false; clearTimeout(timer) }
  }, [gymId, allowed, search])
  const submit = useCallback(async (body: { code: string } | { user_id: number }) => {
    if (inFlight.current) return
    inFlight.current = true
    setBusy(true)
    setCamera(false)
    setMessage('')
    try {
      const { check_in: result } = await api<{ check_in: AttendanceRow }>(`/gyms/${gymId}/check-ins`, { method: 'POST', body })
      setMessage(`${result.member.name}: ${result.session ? `${result.session.name}, ${ATTENDANCE_LABELS[result.attendance]}` : 'Gym visit'}.`)
      setCode('')
      setSearch('')
      setVersion((v) => v + 1)
    } catch (failure) {
      setMessage(failure instanceof ApiError ? Object.values(failure.errors).flat().join(' ') || failure.message : 'Check-in failed. Try again.')
    } finally {
      inFlight.current = false
      setBusy(false)
      field.current?.focus()
    }
  }, [gymId])
  const scanned = useCallback((value: string) => { setCode(value); void submit({ code: value }) }, [submit])
  if (!allowed) return <BookingEmpty title="Check-in is staff-only" description="Your role needs training manage access." />
  return <div className="flex min-w-0 flex-col gap-6">
    <PageHeader title="Check-in" description="Scan a member's QR code, type their short code, or find their account." />
    <form className="flex flex-col gap-3 sm:max-w-xl" onSubmit={(e) => { e.preventDefault(); void submit({ code: code.trim() }) }}>
      <TextField ref={field} name="code" label="Check-in code" description="USB scanners type into this field. Press Enter to submit." value={code} onChange={(e) => setCode(e.target.value)} autoFocus autoComplete="off" maxLength={200} required />
      <div className="flex flex-wrap gap-3"><Button type="submit" disabled={busy || !code.trim()} loading={busy}>Check in</Button>
        {'BarcodeDetector' in window && <Button type="button" variant="outline" disabled={busy} onClick={() => setCamera(true)}>Scan with camera</Button>}
      </div>
      {!('BarcodeDetector' in window) && <p className="text-muted-foreground text-sm">Camera scanning is unavailable in this browser. Typing the code works.</p>}
    </form>
    {camera && <CameraScanner onRead={scanned} onClose={() => { setCamera(false); field.current?.focus() }} />}
    <p role="status" aria-live="polite" aria-atomic="true" className="text-sm font-medium">{message}</p>
    <section className="flex flex-col gap-3 sm:max-w-xl" aria-label="Account check-in">
      <TextField name="member_search" label="Find member by name" value={search} onChange={(e) => setSearch(e.target.value)} autoComplete="off" maxLength={150} />
      {search.trim() && (searchError ? <p role="alert">{searchError}</p> : members === null ? <p role="status">Searching…</p> : members.length === 0 ? <p className="text-muted-foreground text-sm">No active members match that name.</p> : <ul className="flex flex-col gap-2">{members.map((member) => <li key={member.id} className="flex min-w-0 flex-wrap items-center justify-between gap-2 rounded-lg border p-3 text-sm"><span className="break-words">{member.name}</span><Button variant="outline" disabled={busy} onClick={() => void submit({ user_id: member.id })}>Check in {member.name}</Button></li>)}</ul>)}
    </section>
    <section className="flex min-w-0 flex-col gap-3"><h2 className="text-xl font-semibold">Today's check-ins</h2>
      {error ? <BookingError message={error} retry={() => setVersion((v) => v + 1)} /> : !rows ? <BookingLoading /> : <AttendanceList rows={rows} />}
    </section>
  </div>
}
