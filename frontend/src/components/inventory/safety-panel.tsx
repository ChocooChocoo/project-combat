import { ShieldAlert } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Input } from '@/components/ui/input'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { manilaToday } from '@/lib/dashboard'
import { api, ApiError } from '@/lib/api'
import { dateLabel } from '@/lib/membership'

type Check = { id: number; result: 'pass' | 'needs_repair' | 'unsafe'; note: string | null; checked_on: string | null; by: string | null }
type SafetyItem = {
  id: number
  name: string
  category: string
  location: string
  quantity: number
  safety_status: 'ok' | 'needs_repair' | 'unsafe'
  check_interval_days: number | null
  next_check_on: string | null
  checks: Check[]
}

const STATUS = { ok: { label: 'OK', variant: 'success' }, needs_repair: { label: 'Needs repair', variant: 'warning' }, unsafe: { label: 'Unsafe', variant: 'error' } } as const
const RESULT_ITEMS = [{ value: 'pass', label: 'Pass' }, { value: 'needs_repair', label: 'Needs repair' }, { value: 'unsafe', label: 'Unsafe' }]

/** R-120: inspection log for equipment and protective gear. Notice only: unsafe items stay usable in stock flows. */
export function SafetyPanel({ gymId, canManage }: { gymId: number; canManage: boolean }) {
  const [items, setItems] = useState<SafetyItem[] | null>(null)
  const [error, setError] = useState('')
  const [open, setOpen] = useState<number | null>(null)
  const [result, setResult] = useState('pass')
  const [note, setNote] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let live = true
    api<{ items: SafetyItem[] }>(`/gyms/${gymId}/equipment-safety`)
      .then((data) => live && setItems(data.items))
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'Safety checks could not be loaded.'))
    return () => { live = false }
  }, [gymId])

  function replace(item: SafetyItem) {
    setItems((current) => current?.map((row) => (row.id === item.id ? { ...item, checks: item.checks.length > 0 ? item.checks : row.checks } : row)) ?? null)
  }

  async function save(path: string, body: unknown, onDone?: () => void) {
    setBusy(true)
    try {
      const data = await api<{ item: SafetyItem }>(path, { method: path.endsWith('/checks') ? 'POST' : 'PUT', body })
      replace(data.item)
      onDone?.()
    } catch (failure) {
      toastManager.add({ title: failure instanceof ApiError ? Object.values(failure.errors).flat()[0] ?? failure.message : 'Could not save.', type: 'error' })
    } finally {
      setBusy(false)
    }
  }

  if (error) return <p className="text-destructive text-sm">{error}</p>
  if (!items) return <Skeleton className="h-24" />
  if (items.length === 0) return <Empty className="rounded-xl border"><EmptyHeader><EmptyTitle>No equipment to check</EmptyTitle><EmptyDescription>Items in the Equipment and Protective gear categories appear here.</EmptyDescription></EmptyHeader></Empty>

  const today = manilaToday()

  return (
    <ul className="flex flex-col gap-3">
      {items.map((item) => {
        const status = STATUS[item.safety_status]
        const due = item.next_check_on !== null && item.next_check_on <= today
        return (
          <li key={item.id} className="flex flex-col gap-3 rounded-xl border p-4 text-sm">
            <div className="flex flex-wrap items-start justify-between gap-2">
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  {item.safety_status === 'unsafe' && <ShieldAlert className="size-4 text-destructive" aria-hidden />}
                  <h3 className="break-words font-medium">{item.name}</h3>
                  <Badge variant={status.variant}>{status.label}</Badge>
                  {due && <Badge variant="warning">Check due</Badge>}
                </div>
                <p className="text-muted-foreground text-xs">
                  {item.location} · {item.next_check_on ? `next check ${dateLabel(item.next_check_on)}` : 'no check schedule'}
                </p>
              </div>
              {canManage && (
                <div className="flex flex-wrap items-center gap-2">
                  <label className="flex items-center gap-2 text-xs">Every
                    <Input className="w-20" type="number" min="1" max="365" defaultValue={item.check_interval_days ?? ''} aria-label={`Check ${item.name} every how many days`}
                      onBlur={(event) => {
                        const value = event.target.value === '' ? null : Number(event.target.value)
                        if (value !== item.check_interval_days) save(`/gyms/${gymId}/equipment-safety/${item.id}`, { check_interval_days: value })
                      }} />
                    days</label>
                  <Button size="sm" variant="outline" onClick={() => { setOpen(open === item.id ? null : item.id); setResult('pass'); setNote('') }}>Record check</Button>
                </div>
              )}
            </div>
            {open === item.id && (
              <form className="flex flex-col gap-2 rounded-lg bg-muted/40 p-3 sm:flex-row sm:items-end" onSubmit={(event) => {
                event.preventDefault()
                save(`/gyms/${gymId}/equipment-safety/${item.id}/checks`, { result, note: note.trim() || null }, () => setOpen(null))
              }}>
                <Select items={RESULT_ITEMS} value={result} onValueChange={(value) => setResult(String(value))}>
                  <SelectTrigger className="sm:w-40" aria-label="Check result"><SelectValue /></SelectTrigger>
                  <SelectPopup>{RESULT_ITEMS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}</SelectPopup>
                </Select>
                <Textarea className="sm:flex-1" rows={1} maxLength={2000} placeholder={result === 'pass' ? 'Note (optional)' : 'What is wrong?'} aria-label="Check note" value={note} onChange={(event) => setNote(event.target.value)} required={result !== 'pass'} />
                <Button type="submit" loading={busy}>Save check</Button>
              </form>
            )}
            {item.checks.length > 0 && (
              <ol className="flex flex-col gap-1 text-xs text-muted-foreground">
                {item.checks.map((check) => (
                  <li key={check.id}>{dateLabel(check.checked_on)} · {RESULT_ITEMS.find((option) => option.value === check.result)?.label}{check.note && ` · ${check.note}`}{check.by && ` · ${check.by}`}</li>
                ))}
              </ol>
            )}
          </li>
        )
      })}
    </ul>
  )
}
