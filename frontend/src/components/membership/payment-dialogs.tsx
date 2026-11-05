import { useState } from 'react'
import { TextField } from '@/components/text-field'
import { Button } from '@/components/ui/button'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, formValues, type FieldErrors } from '@/lib/api'
import { METHOD_LABELS, money, type Membership, type Payment } from '@/lib/membership'

const DESK_METHODS = (['cash', 'card', 'ewallet'] as const).map((value) => ({ value, label: METHOD_LABELS[value] }))

/** R-34: record a desk payment. With one membership given, the member choice is fixed. */
export function RecordPaymentDialog({
  gymId,
  open,
  onClose,
  onSaved,
  memberships,
}: {
  gymId: number
  open: boolean
  onClose: () => void
  onSaved: (payment: Payment) => void
  memberships: Membership[]
}) {
  const [membershipId, setMembershipId] = useState('')
  const [method, setMethod] = useState('cash')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)
  const chosen = memberships.length === 1 ? memberships[0] : memberships.find((m) => String(m.id) === membershipId)
  const items = memberships.map((m) => ({ value: String(m.id), label: `${m.member.name} · ${m.plan?.name ?? ''}` }))

  async function save(event: React.FormEvent<HTMLFormElement>) {
    const values = formValues(event)
    if (!chosen) {
      setErrors({ membership_id: ['Choose the member who paid.'] })
      return
    }
    setBusy(true)
    try {
      const result = await api<{ payment: Payment }>(`/gyms/${gymId}/payments`, {
        body: { membership_id: chosen.id, amount: Number(values.amount), method, note: values.note || null },
      })
      toastManager.add({ title: `Payment recorded. Receipt ${result.payment.receipt_number}.`, type: 'success' })
      setErrors({})
      setMembershipId('')
      onSaved(result.payment)
    } catch (failure) {
      const fieldErrors = failure instanceof ApiError ? failure.errors : {}
      setErrors(fieldErrors)
      if (Object.keys(fieldErrors).length === 0) {
        toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Could not record the payment.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Record a payment</DialogTitle>
            <DialogDescription>For money taken at the front desk. Online payments are recorded on their own once PayMongo confirms them.</DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            {memberships.length === 1 ? (
              <p className="text-sm">
                <span className="font-medium">{chosen?.member.name}</span> · {chosen?.plan?.name}
              </p>
            ) : (
              <Field name="membership_id" className="w-full">
                <FieldLabel>Member</FieldLabel>
                <Select items={items} value={membershipId} onValueChange={(next) => setMembershipId(String(next ?? ''))}>
                  <SelectTrigger aria-label="Member">
                    <SelectValue placeholder="Choose a member" />
                  </SelectTrigger>
                  <SelectPopup>
                    {items.map((item) => (
                      <SelectItem key={item.value} value={item.value}>
                        {item.label}
                      </SelectItem>
                    ))}
                  </SelectPopup>
                </Select>
                <FieldError />
              </Field>
            )}
            {chosen && Number(chosen.balance) > 0 && <p className="text-muted-foreground text-sm">Still owed: {money(chosen.balance)}</p>}
            <TextField
              name="amount"
              label="Amount (PHP)"
              type="number"
              min="0.01"
              step="0.01"
              inputMode="decimal"
              required
              key={chosen?.id ?? 'none'}
              defaultValue={chosen && Number(chosen.balance) > 0 ? chosen.balance : ''}
            />
            <Field name="method" className="w-full">
              <FieldLabel>Method</FieldLabel>
              <Select items={DESK_METHODS} value={method} onValueChange={(next) => setMethod(String(next ?? 'cash'))}>
                <SelectTrigger aria-label="Method">
                  <SelectValue />
                </SelectTrigger>
                <SelectPopup>
                  {DESK_METHODS.map((item) => (
                    <SelectItem key={item.value} value={item.value}>
                      {item.label}
                    </SelectItem>
                  ))}
                </SelectPopup>
              </Select>
              <FieldError />
            </Field>
            <TextField name="note" label="Note (optional)" maxLength={500} placeholder="For example: GCash reference number" />
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy}>
              Record payment
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

/** R-34: a wrong entry is reversed by its own correction entry, never edited. */
export function CorrectPaymentDialog({
  gymId,
  payment,
  onClose,
  onSaved,
}: {
  gymId: number
  payment: Payment | null
  onClose: () => void
  onSaved: () => void
}) {
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    const values = formValues(event)
    if (!payment) return
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/payments/${payment.id}/correct`, { body: { reason: values.reason } })
      toastManager.add({ title: 'Correction recorded.', type: 'success' })
      setErrors({})
      onSaved()
    } catch (failure) {
      const fieldErrors = failure instanceof ApiError ? failure.errors : {}
      setErrors(fieldErrors)
      if (Object.keys(fieldErrors).length === 0) {
        toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Could not record the correction.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open={payment !== null} onOpenChange={(next) => !next && onClose()}>
      <DialogPopup>
        {payment && (
          <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
            <DialogHeader>
              <DialogTitle>Correct receipt {payment.receipt_number}?</DialogTitle>
              <DialogDescription>
                This adds a new entry of {money(-Number(payment.amount))} that cancels the original. The original stays on record. Record the right amount
                afterwards as a new payment.
              </DialogDescription>
            </DialogHeader>
            <DialogPanel>
              <TextField name="reason" label="Why is it being corrected?" maxLength={500} required placeholder="For example: recorded for the wrong member" />
            </DialogPanel>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={onClose}>
                Keep it
              </Button>
              <Button type="submit" variant="destructive" loading={busy}>
                Record correction
              </Button>
            </DialogFooter>
          </Form>
        )}
      </DialogPopup>
    </Dialog>
  )
}
