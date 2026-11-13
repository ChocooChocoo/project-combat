import { Plus, TicketPercent } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useParams } from 'react-router'
import { PageControls } from '@/components/page-controls'
import { PageHeader } from '@/components/page-header'
import { pageMetaFrom, type PageMeta } from '@/lib/pagination'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { manilaToday } from '@/lib/dashboard'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import { dateLabel } from '@/lib/membership'
import { discountLabel, offerStatus, type Offer } from '@/lib/offers'

type PlanChoice = { id: number; name: string }

const KIND_ITEMS = [{ value: 'promo', label: 'Promo (applies by itself)' }, { value: 'voucher', label: 'Voucher (needs a code)' }]
const TYPE_ITEMS = [{ value: 'percent', label: 'Percent off' }, { value: 'fixed', label: 'Fixed amount off' }]

/** R-111: promos and vouchers on membership plans. Staff offers start only after an approver says yes. */
export default function GymOffers() {
  const { gymId } = useParams()
  const { user } = useAuth()
  const gym = approvedGym(user, Number(gymId))
  const canManage = Boolean(gym && can(gym, 'customers.offers.manage'))
  const canApprove = Boolean(gym && can(gym, 'customers.offers.approve'))
  const [offers, setOffers] = useState<Offer[] | null>(null)
  const [plans, setPlans] = useState<PlanChoice[]>([])
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [page, setPage] = useState(1) // (R-123)
  const [meta, setMeta] = useState<PageMeta | null>(null)
  const [editing, setEditing] = useState<Offer | 'new' | null>(null)
  const [deciding, setDeciding] = useState<Offer | null>(null)

  useEffect(() => {
    let live = true
    setError('')
    api<{ offers: Offer[]; plans: PlanChoice[] }>(`/gyms/${gymId}/offers?page=${page}`)
      .then((result) => {
        if (!live) return
        // An action can empty the last page; step back one.
        if (result.offers.length === 0 && page > 1) {
          setPage(page - 1)
          return
        }
        setOffers(result.offers)
        setPlans(result.plans)
        setMeta(pageMetaFrom(result, result.offers.length))
      })
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'Offers could not be loaded.'))
    return () => { live = false }
  }, [gymId, version, page])

  if (!gym) return null
  const refresh = () => setVersion((value) => value + 1)

  async function archive(offer: Offer) {
    try {
      await api(`/gyms/${gymId}/offers/${offer.id}/archive`, { method: 'POST' })
      toastManager.add({ title: 'Offer archived.', type: 'success' })
      refresh()
    } catch (failure) {
      toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Offer could not be archived.', type: 'error' })
    }
  }

  return (
    <div className="flex min-w-0 flex-col gap-6">
      <PageHeader
        title="Offers"
        description="Promos take money off chosen plans by themselves. Vouchers need a code. Both apply to the first payment only, and staff offers wait for approval."
        actions={canManage && <Button onClick={() => setEditing('new')}><Plus aria-hidden /> New offer</Button>}
      />
      {error ? (
        <Alert variant="error"><AlertTitle>Offers could not be loaded</AlertTitle><AlertDescription>
          <p>{error}</p><Button variant="outline" onClick={refresh}>Try again</Button>
        </AlertDescription></Alert>
      ) : !offers ? (
        <div className="flex flex-col gap-2" aria-busy="true" aria-label="Loading offers"><Skeleton className="h-20" /><Skeleton className="h-20" /></div>
      ) : offers.length === 0 ? (
        <Empty className="rounded-xl border"><EmptyHeader><TicketPercent aria-hidden className="size-6 text-muted-foreground" />
          <EmptyTitle>No offers yet</EmptyTitle><EmptyDescription>Make a promo or voucher for one or more membership plans.</EmptyDescription>
        </EmptyHeader></Empty>
      ) : (
        <>
        <ul className="flex flex-col gap-3">
          {offers.map((offer) => {
            const status = offerStatus[offer.status]
            const mine = offer.created_by_id === user?.id
            return (
              <li key={offer.id} className="flex min-w-0 flex-col gap-3 rounded-xl border p-4 text-sm sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 break-words">
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="font-medium text-base">{offer.name}</h2>
                    <Badge variant={status.variant}>{status.label}</Badge>
                    <Badge variant="outline">{offer.kind === 'voucher' ? `Code ${offer.code}` : 'Promo'}</Badge>
                  </div>
                  <p className="mt-2">{discountLabel(offer)} · {offer.plans.map((plan) => plan.name).join(', ')}</p>
                  <p className="mt-1 text-muted-foreground">
                    {dateLabel(offer.starts_on)} to {offer.ends_on ? dateLabel(offer.ends_on) : 'no end date'} · used {offer.uses}{offer.max_uses !== null ? ` of ${offer.max_uses}` : ''} times
                  </p>
                  <p className="mt-1 text-muted-foreground text-xs">
                    Made by {offer.created_by ?? 'unknown'}{offer.decided_by && ` · ${offer.status === 'rejected' ? 'rejected' : 'approved'} by ${offer.decided_by}`}
                    {offer.decision_reason && ` · ${offer.decision_reason}`}
                  </p>
                </div>
                <div className="flex shrink-0 flex-wrap gap-2">
                  {canApprove && offer.status === 'pending' && !mine && <Button size="sm" onClick={() => setDeciding(offer)}>Decide</Button>}
                  {canManage && offer.status !== 'archived' && <Button size="sm" variant="outline" onClick={() => setEditing(offer)}>Edit</Button>}
                  {canManage && offer.status !== 'archived' && <Button size="sm" variant="ghost" onClick={() => archive(offer)}>Archive</Button>}
                </div>
              </li>
            )
          })}
        </ul>
        <PageControls meta={meta} label="Offers" onPage={setPage} />
        </>
      )}
      {editing && <OfferDialog gymId={gym.id} offer={editing === 'new' ? null : editing} plans={plans} onClose={() => setEditing(null)} onSaved={() => { setEditing(null); refresh() }} />}
      {deciding && <DecideDialog gymId={gym.id} offer={deciding} onClose={() => setDeciding(null)} onDone={() => { setDeciding(null); refresh() }} />}
    </div>
  )
}

function OfferDialog({ gymId, offer, plans, onClose, onSaved }: { gymId: number; offer: Offer | null; plans: PlanChoice[]; onClose: () => void; onSaved: () => void }) {
  const [kind, setKind] = useState<string>(offer?.kind ?? 'promo')
  const [type, setType] = useState<string>(offer?.discount_type ?? 'percent')
  const [planIds, setPlanIds] = useState<number[]>(offer?.plans.map((plan) => plan.id) ?? [])
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const values = Object.fromEntries(new FormData(event.currentTarget)) as Record<string, string>
    setBusy(true)
    setErrors({})
    try {
      const body = {
        kind, discount_type: type, plan_ids: planIds, name: values.name, code: kind === 'voucher' ? values.code : null,
        value: Number(values.value), starts_on: values.starts_on, ends_on: values.ends_on || null, max_uses: values.max_uses ? Number(values.max_uses) : null,
      }
      const result = await api<{ offer: Offer }>(offer ? `/gyms/${gymId}/offers/${offer.id}` : `/gyms/${gymId}/offers`, { method: offer ? 'PUT' : 'POST', body })
      toastManager.add({ title: result.offer.status === 'pending' ? 'Offer saved. It starts once approved.' : 'Offer saved and live.', type: 'success' })
      onSaved()
    } catch (failure) {
      setErrors(failure instanceof ApiError ? failure.errors : {})
      if (!(failure instanceof ApiError) || Object.keys(failure.errors).length === 0) {
        toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Offer could not be saved.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  return <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
    <DialogContent>
      <DialogHeader><DialogTitle>{offer ? 'Edit offer' : 'New offer'}</DialogTitle><DialogDescription>Applies to membership plans only, on the first payment.</DialogDescription></DialogHeader>
      <Form errors={errors} onSubmit={save}>
        <DialogPanel>
          <fieldset disabled={busy} className="flex min-w-0 flex-col gap-4">
            <TextField name="name" label="Name" defaultValue={offer?.name} required maxLength={120} autoFocus />
            <Field name="kind"><FieldLabel>Kind</FieldLabel><Select items={KIND_ITEMS} value={kind} onValueChange={(value) => setKind(String(value))}>
              <SelectTrigger aria-label="Kind"><SelectValue /></SelectTrigger><SelectPopup>{KIND_ITEMS.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}</SelectPopup>
            </Select><FieldError /></Field>
            {kind === 'voucher' && <TextField name="code" label="Code" defaultValue={offer?.code ?? ''} required maxLength={40} pattern="[A-Za-z0-9_\-]+" description="Letters, numbers, dashes. Saved in capitals." />}
            <div className="grid gap-4 sm:grid-cols-2">
              <Field name="discount_type"><FieldLabel>Discount</FieldLabel><Select items={TYPE_ITEMS} value={type} onValueChange={(value) => setType(String(value))}>
                <SelectTrigger aria-label="Discount type"><SelectValue /></SelectTrigger><SelectPopup>{TYPE_ITEMS.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}</SelectPopup>
              </Select><FieldError /></Field>
              <TextField name="value" label={type === 'percent' ? 'Percent' : 'Amount (PHP)'} type="number" min="0.01" max={type === 'percent' ? '100' : undefined} step="0.01" inputMode="decimal" defaultValue={offer ? String(Number(offer.value)) : ''} required />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="starts_on" label="Starts" type="date" defaultValue={offer?.starts_on ?? manilaToday()} required />
              <TextField name="ends_on" label="Ends (optional)" type="date" defaultValue={offer?.ends_on ?? ''} />
            </div>
            <TextField name="max_uses" label="Use limit (optional)" type="number" min="1" step="1" inputMode="numeric" defaultValue={offer?.max_uses != null ? String(offer.max_uses) : ''} description="Empty means no limit." />
            <Field name="plan_ids"><FieldLabel>Plans</FieldLabel>
              <div className="flex flex-col gap-2">
                {plans.length === 0 && <p className="text-muted-foreground text-sm">Add a membership plan first.</p>}
                {plans.map((plan) => (
                  <label key={plan.id} className="flex items-center gap-3 text-sm">
                    <Checkbox checked={planIds.includes(plan.id)} onCheckedChange={(on) => setPlanIds((ids) => on === true ? [...ids, plan.id] : ids.filter((id) => id !== plan.id))} />
                    {plan.name}
                  </label>
                ))}
              </div>
              <FieldError />
            </Field>
          </fieldset>
        </DialogPanel>
        <DialogFooter><Button type="button" variant="outline" disabled={busy} onClick={onClose}>Cancel</Button><Button type="submit" loading={busy}>Save offer</Button></DialogFooter>
      </Form>
    </DialogContent>
  </Dialog>
}

function DecideDialog({ gymId, offer, onClose, onDone }: { gymId: number; offer: Offer; onClose: () => void; onDone: () => void }) {
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)
  const [reason, setReason] = useState('')

  async function decide(decision: 'approve' | 'reject') {
    setBusy(true)
    setErrors({})
    try {
      await api(`/gyms/${gymId}/offers/${offer.id}/decision`, { body: { decision, reason: reason || null } })
      toastManager.add({ title: decision === 'approve' ? 'Offer approved.' : 'Offer rejected.', type: 'success' })
      onDone()
    } catch (failure) {
      setErrors(failure instanceof ApiError ? failure.errors : {})
      if (!(failure instanceof ApiError) || Object.keys(failure.errors).length === 0) {
        toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Decision could not be saved.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  return <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
    <DialogContent>
      <DialogHeader><DialogTitle>Decide on {offer.name}</DialogTitle><DialogDescription>{discountLabel(offer)} on {offer.plans.map((plan) => plan.name).join(', ')}. Made by {offer.created_by}.</DialogDescription></DialogHeader>
      <Form errors={errors} onSubmit={(event) => { event.preventDefault(); decide('approve') }}>
        <DialogPanel>
          <Field name="reason"><FieldLabel>Reason (needed to reject)</FieldLabel><Textarea name="reason" value={reason} onChange={(event) => setReason(event.target.value)} rows={3} maxLength={2000} /><FieldError /></Field>
        </DialogPanel>
        <DialogFooter>
          <Button type="button" variant="destructive-outline" disabled={busy} onClick={() => decide('reject')}>Reject</Button>
          <Button type="submit" loading={busy}>Approve</Button>
        </DialogFooter>
      </Form>
    </DialogContent>
  </Dialog>
}
