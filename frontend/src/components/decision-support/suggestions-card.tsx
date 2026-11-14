import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router'
import { AlertDialog, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogPopup, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Card, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { toastManager } from '@/components/ui/toast'
import { api } from '@/lib/api'
import type { GymAccess } from '@/lib/auth'
import { can } from '@/lib/gym-access'
import type { Suggestion } from '@/lib/decision-support'

type RequestAction = Extract<Suggestion['action'], { type: 'request' }>

/** What the owner is about to send, in plain words. */
function bodySummary(action: RequestAction): string {
  const { body } = action
  if (typeof body.title === 'string') return `"${body.title}": ${String(body.body ?? '')}`
  const lines = Array.isArray(body.lines) ? body.lines.length : 0
  return `${lines} ${lines === 1 ? 'item' : 'items'} on the request.`
}

/** (R-141) Nothing runs until the owner confirms; the target endpoint checks permission again. */
export function SuggestionsCard({ gym }: { gym: GymAccess }) {
  const [items, setItems] = useState<Suggestion[] | null>(null)
  const [error, setError] = useState('')
  const [pending, setPending] = useState<Suggestion | null>(null)
  const [busy, setBusy] = useState(false)

  const load = useCallback(() => {
    api<{ items: Suggestion[] }>(`/gyms/${gym.id}/decision-support/suggestions`)
      .then((data) => { setItems(data.items); setError('') })
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : 'Could not load suggestions.'))
  }, [gym.id])
  useEffect(load, [load])

  async function confirm() {
    if (!pending || pending.action.type !== 'request') return
    setBusy(true)
    try {
      await api(`/gyms/${gym.id}/${pending.action.path}`, { method: pending.action.method, body: pending.action.body })
      toastManager.add({ title: 'Done.', type: 'success' })
      setPending(null)
      load()
    } catch (reason) {
      toastManager.add({ title: reason instanceof Error ? reason.message : 'Could not do this.', type: 'error' })
    } finally { setBusy(false) }
  }

  const action = pending?.action.type === 'request' ? pending.action : null

  return <Card className="print:hidden">
    <CardHeader><CardTitle>Suggestions</CardTitle></CardHeader>
    <CardPanel className="flex flex-col gap-4">
      {error ? <p className="text-destructive-foreground text-sm">{error}</p>
        : items === null ? <p className="text-muted-foreground text-sm">Loading suggestions...</p>
        : items.length === 0 ? <p className="text-muted-foreground text-sm">No suggestions right now.</p>
        : items.map((item) => <div key={item.code + item.title} className="flex flex-col gap-2 border-b pb-4 last:border-b-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
          <div className="min-w-0">
            <p className="font-medium">{item.title}</p>
            <p className="text-muted-foreground text-sm">{item.reason}</p>
          </div>
          {item.action.type === 'link'
            ? <Button size="sm" variant="outline" render={<Link to={item.action.to} />}>{item.action.label}</Button>
            : can(gym, item.action.permission as `${string}.${string}.${string}`)
              ? <Button size="sm" onClick={() => setPending(item)}>{item.action.label}</Button>
              : <p className="text-muted-foreground text-sm">Ask someone with access to do this.</p>}
        </div>)}
    </CardPanel>
    <AlertDialog open={pending !== null} onOpenChange={(open) => { if (!open && !busy) setPending(null) }}>
      <AlertDialogPopup>
        <AlertDialogHeader>
          <AlertDialogTitle className="break-words">{pending?.title}</AlertDialogTitle>
          <AlertDialogDescription>{action?.confirm} {action && bodySummary(action)}</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <Button variant="outline" disabled={busy} onClick={() => setPending(null)}>Cancel</Button>
          <Button loading={busy} onClick={confirm}>Confirm</Button>
        </AlertDialogFooter>
      </AlertDialogPopup>
    </AlertDialog>
  </Card>
}
