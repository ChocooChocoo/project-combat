import { useState } from 'react'
import { ProgressNotes } from '@/components/schedule/attendance-list'
import { TextField } from '@/components/text-field'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldLabel } from '@/components/ui/field'
import { Textarea } from '@/components/ui/textarea'
import { api, ApiError } from '@/lib/api'
import { ATTENDANCE_LABELS, type Attendance, type ProgressNote } from '@/lib/attendance'
import type { StaffBooking } from '@/lib/bookings'
import { dateKey, sessionTime } from '@/lib/sessions'

/** R-53: staff corrections keep their attribution and let staff supply a wall-clock time. */
export function SessionAttendance({ booking, canManage, notes, onNote, onSaved }: {
  booking: StaffBooking; canManage: boolean; notes: ProgressNote[]; onNote?: () => void; onSaved: () => void
}) {
  const [time, setTime] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [today] = useState(() => dateKey(new Date()))
  const open = booking.status === 'booked' && booking.session.starts_at.slice(0, 10) <= today
  async function mark(attendance?: Attendance) {
    setBusy(true)
    setError('')
    try {
      await api(`/gyms/${booking.gym_id}/bookings/${booking.id}/${attendance ? 'attendance' : 'check-out'}`, {
        method: attendance ? 'PUT' : 'POST',
        ...(attendance ? { body: { attendance, ...(time ? { check_in_at: time } : {}) } } : {}),
      })
      onSaved()
    } catch (failure) {
      setError(failure instanceof ApiError ? Object.values(failure.errors).flat().join(' ') || failure.message : 'Attendance could not be saved.')
    } finally { setBusy(false) }
  }
  return <div className="flex min-w-0 flex-col gap-3 text-sm">
    <p>{booking.attendance ? `${booking.attendance_method === 'manual' ? 'Marked ' : ''}${ATTENDANCE_LABELS[booking.attendance].toLowerCase()}${booking.attendance_by ? ` by ${booking.attendance_by}` : ' automatically'}` : 'No attendance yet'}</p>
    {(booking.check_in_at || booking.check_out_at) && <p>In: {booking.check_in_at ? sessionTime(booking.check_in_at) : 'Not recorded'} · Out: {booking.check_out_at ? sessionTime(booking.check_out_at) : 'Not recorded'}</p>}
    {canManage && open && <>
      <TextField name={`time-${booking.id}`} label="Check-in time (optional)" type="time" value={time} onChange={(e) => setTime(e.target.value)} />
      <div className="flex flex-wrap gap-2">{(['present', 'late', 'absent'] as const).map((status) => <Button key={status} variant="outline" disabled={busy} aria-label={`Mark ${booking.athlete.name} ${status}`} onClick={() => void mark(status)}>{ATTENDANCE_LABELS[status]}</Button>)}
        {booking.check_in_at && !booking.check_out_at && <Button variant="outline" disabled={busy} onClick={() => void mark()}>Check out</Button>}
      </div>
    </>}
    {error && <p role="alert">{error}</p>}
    {notes.length > 0 && <ProgressNotes notes={notes} />}
    {booking.status === 'booked' && onNote && <Button variant="outline" className="self-start" onClick={onNote}>Add note for {booking.athlete.name}</Button>}
  </div>
}

export function SessionNoteDialog({ gymId, sessionId, target, onClose, onSaved }: {
  gymId: number; sessionId: number; target: { userId: number | null; name: string }; onClose: () => void; onSaved: () => void
}) {
  const [returnFocus] = useState(() => document.activeElement instanceof HTMLElement ? document.activeElement : null)
  const [body, setBody] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    setError('')
    try {
      await api(`/gyms/${gymId}/sessions/${sessionId}/notes`, { body: { body, user_id: target.userId } })
      onSaved()
    } catch (failure) {
      setError(failure instanceof ApiError ? Object.values(failure.errors).flat().join(' ') || failure.message : 'Note could not be saved.')
    } finally { setBusy(false) }
  }
  return <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
    <DialogContent finalFocus={() => returnFocus?.isConnected ? returnFocus : false}>
      <DialogHeader><DialogTitle>Add progress note</DialogTitle><DialogDescription>{target.name}. {target.userId ? 'This note also appears in the athlete’s own record.' : 'Whole-session notes are visible to staff only.'}</DialogDescription></DialogHeader>
      <form onSubmit={submit}>
        <DialogPanel className="flex flex-col gap-3">
          {error && <p role="alert" className="text-sm">{error}</p>}
          <Field name="body"><FieldLabel>Note</FieldLabel><Textarea value={body} onChange={(e) => setBody(e.target.value)} required maxLength={10000} autoFocus /></Field>
        </DialogPanel>
        <DialogFooter><Button type="button" variant="outline" disabled={busy} onClick={onClose}>Cancel</Button><Button type="submit" loading={busy} disabled={!body.trim()}>Save note</Button></DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
}
