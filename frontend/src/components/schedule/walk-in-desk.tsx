import { useCallback, useState } from 'react'
import { TextField } from '@/components/text-field'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { toastManager } from '@/components/ui/toast'
import { usePolling } from '@/hooks/use-polling'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { dateTimeLabel, money } from '@/lib/membership'

type WalkInRow = { id: number; name: string; phone: string; checked_in_at: string; paid: boolean; amount: string | null; health_flag: boolean }

const METHODS = [
  { value: 'cash', label: 'Cash' },
  { value: 'card', label: 'Card' },
  { value: 'ewallet', label: 'E-wallet' },
]

/** R-137: today's walk-ins from the public kiosk, with desk payment. */
export function WalkInDesk({ gymId, canPay }: { gymId: string | undefined; canPay: boolean }) {
  const [rows, setRows] = useState<WalkInRow[] | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [paying, setPaying] = useState<WalkInRow | null>(null)
  const [amount, setAmount] = useState('')
  const [method, setMethod] = useState('cash')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)
  const load = useCallback(async (_silent: boolean, isCurrent: () => boolean) => {
    try {
      const result = await api<{ walk_ins: WalkInRow[] }>(`/gyms/${gymId}/walk-ins`)
      if (isCurrent()) { setRows(result.walk_ins); setError('') }
    } catch {
      if (isCurrent()) setError("Today's walk-ins could not be loaded.")
    }
  }, [gymId])
  usePolling(load, 15000, true, version)
  const link = `${window.location.origin}/gyms/${gymId}/walk-in`

  async function save(event: React.FormEvent) {
    event.preventDefault()
    if (!paying) return
    setBusy(true)
    setErrors({})
    try {
      await api(`/gyms/${gymId}/walk-ins/${paying.id}/payment`, { body: { amount: Number(amount), method } })
      toastManager.add({ title: 'Payment recorded.', type: 'success' })
      setPaying(null)
      setVersion((v) => v + 1)
    } catch (failure) {
      if (failure instanceof ApiError) setErrors(failure.errors)
      toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Payment could not be recorded.', type: 'error' })
    } finally {
      setBusy(false)
    }
  }

  return <section className="flex min-w-0 flex-col gap-3" aria-label="Today's walk-ins">
    <div className="flex flex-wrap items-center justify-between gap-2">
      <h2 className="text-xl font-semibold">Today's walk-ins</h2>
      <div className="flex flex-wrap gap-2">
        <Button variant="outline" render={<a href={link} target="_blank" rel="noreferrer" />}>Open walk-in kiosk</Button>
        <Button variant="outline" onClick={() => { void navigator.clipboard?.writeText(link).then(() => toastManager.add({ title: 'Link copied.', type: 'success' })) }}>Copy link</Button>
      </div>
    </div>
    {error ? <p role="alert" className="text-sm">{error}</p> : !rows ? <p role="status" className="text-muted-foreground text-sm">Loading…</p> : rows.length === 0 ? <p className="text-muted-foreground text-sm">No walk-ins yet today.</p> : <ul className="flex flex-col gap-2">
      {rows.map((row) => <li key={row.id} className="flex min-w-0 flex-wrap items-center justify-between gap-2 rounded-lg border p-3 text-sm">
        <span className="min-w-0 break-words"><span className="font-medium">{row.name}</span> · {row.phone} · {dateTimeLabel(row.checked_in_at)}</span>
        <span className="flex flex-wrap items-center gap-2">
          {row.health_flag && <Badge variant="warning">Health: check answers</Badge>}
          {row.paid ? <span>Paid {money(row.amount)}</span> : canPay && <Button size="sm" variant="outline" onClick={() => { setAmount(''); setMethod('cash'); setErrors({}); setPaying(row) }}>Record payment</Button>}
        </span>
      </li>)}
    </ul>}
    <Dialog open={!!paying} onOpenChange={(open) => !open && !busy && setPaying(null)}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Record payment</DialogTitle>
            <DialogDescription>{paying?.name}'s walk-in visit.</DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            <TextField name="amount" label="Amount (₱)" type="number" inputMode="decimal" min="0.01" step="0.01" value={amount} onChange={(e) => setAmount(e.target.value)} required autoFocus />
            <Field name="method" className="w-full">
              <FieldLabel>Method</FieldLabel>
              <Select items={METHODS} value={method} onValueChange={(next) => setMethod(String(next ?? 'cash'))}>
                <SelectTrigger aria-label="Method"><SelectValue /></SelectTrigger>
                <SelectPopup>{METHODS.map((m) => <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>)}</SelectPopup>
              </Select>
            </Field>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={() => setPaying(null)}>Cancel</Button>
            <Button type="submit" loading={busy} disabled={busy || !amount}>Save</Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  </section>
}
