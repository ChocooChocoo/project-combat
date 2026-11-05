import { useEffect, useState } from 'react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { AlertDialog, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogPopup, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError } from '@/lib/api'
import type { SessionChoice, TrainingSession } from '@/lib/sessions'

/** R-45, Q-20: make the manual money hand-back explicit before cancelling. */
export function CancelSessionDialog({ gymId, session, onClose, onSaved }: {
  gymId: number; session: Pick<TrainingSession, 'id' | 'name'>; onClose: () => void; onSaved: () => void
}) {
  const [returnFocus] = useState(() => document.activeElement instanceof HTMLElement ? document.activeElement : null)
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    setError('')
    try {
      await api(`/gyms/${gymId}/sessions/${session.id}/cancel`, { body: { reason } })
      toastManager.add({ title: 'Session cancelled. Refund entries recorded; staff must hand back any money paid.', type: 'success' })
      onSaved()
    } catch (failure) {
      setError(failure instanceof ApiError ? Object.values(failure.errors).flat().join(' ') || failure.message : 'Could not cancel the session.')
    } finally { setBusy(false) }
  }
  return (
    <AlertDialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <AlertDialogPopup finalFocus={() => returnFocus?.isConnected ? returnFocus : false}>
        <Form onSubmit={save}>
          <AlertDialogHeader>
            <AlertDialogTitle>Cancel {session.name}?</AlertDialogTitle>
            <AlertDialogDescription>All bookings and waiting-list places will be cancelled. Athletes and the coach will be notified. Full refund entries will be recorded for payments; staff must hand the money back. PayMongo will not refund automatically.</AlertDialogDescription>
          </AlertDialogHeader>
          <div className="flex flex-col gap-4 overflow-y-auto px-6 pb-6">
            {error && <Alert variant="error"><AlertTitle>Cancellation failed</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}
            <Field name="reason"><FieldLabel>Reason for cancellation</FieldLabel><Textarea name="reason" value={reason} onChange={(e) => setReason(e.target.value)} required maxLength={2000} disabled={busy} /><FieldError /></Field>
          </div>
          <AlertDialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>Keep session</Button>
            <Button type="submit" variant="destructive" loading={busy}>Cancel session</Button>
          </AlertDialogFooter>
        </Form>
      </AlertDialogPopup>
    </AlertDialog>
  )
}

/** R-46: only coaches passing the same save-time clash check are offered. */
export function ReassignCoachDialog({ gymId, session, onClose, onSaved }: {
  gymId: number; session: TrainingSession; onClose: () => void; onSaved: () => void
}) {
  const [coaches, setCoaches] = useState<SessionChoice[] | null>(null)
  const [coachId, setCoachId] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  useEffect(() => {
    let live = true
    api<{ coaches: SessionChoice[] }>(`/gyms/${gymId}/sessions/${session.id}/free-coaches`)
      .then((result) => live && setCoaches(result.coaches.filter((coach) => coach.id !== session.coach_id)))
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'Could not load free coaches.'))
    return () => { live = false }
  }, [gymId, session.id, session.coach_id, version])
  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!coachId) return
    setBusy(true)
    setError('')
    try {
      await api(`/gyms/${gymId}/sessions/${session.id}`, { method: 'PUT', body: { ...session, coach_id: Number(coachId) } })
      toastManager.add({ title: 'Coach reassigned. Athletes and coaches notified.', type: 'success' })
      onSaved()
    } catch (failure) {
      setError(failure instanceof ApiError ? Object.values(failure.errors).flat().join(' ') || failure.message : 'Could not reassign the coach.')
    } finally { setBusy(false) }
  }
  const items = (coaches ?? []).map((coach) => ({ value: String(coach.id), label: coach.name }))
  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup><Form onSubmit={save}>
        <DialogHeader><DialogTitle>Reassign coach</DialogTitle><DialogDescription>{session.name}. Booked athletes and the coach will be notified.</DialogDescription></DialogHeader>
        <DialogPanel className="flex flex-col gap-4">
          {error && <Alert variant="error"><AlertTitle>Coach could not be assigned</AlertTitle><AlertDescription>{error} <Button type="button" variant="outline" onClick={() => { setCoaches(null); setError(''); setVersion((v) => v + 1) }}>Reload coaches</Button></AlertDescription></Alert>}
          {!coaches && !error ? <p role="status">Checking coach availability…</p> : coaches?.length === 0 ? <p>No other coaches are free at this time. Move or cancel the session instead.</p> : coaches && <Field name="coach_id"><FieldLabel>Free coach</FieldLabel><Select items={items} value={coachId} onValueChange={(next) => setCoachId(String(next ?? ''))}><SelectTrigger><SelectValue placeholder="Choose a coach" /></SelectTrigger><SelectPopup>{items.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}</SelectPopup></Select><FieldError /></Field>}
        </DialogPanel>
        <DialogFooter><Button type="button" variant="outline" disabled={busy} onClick={onClose}>Go back</Button><Button type="submit" loading={busy} disabled={!coachId || !coaches?.length}>Reassign coach</Button></DialogFooter>
      </Form></DialogPopup>
    </Dialog>
  )
}
