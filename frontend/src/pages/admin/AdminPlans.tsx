import { Plus } from 'lucide-react'
import { useEffect, useState } from 'react'
import { PageHeader } from '@/components/page-header'
import { PLAN_GRID, PlanCard } from '@/components/subscription/plan-card'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import type { AdminPlansResponse, SubscriptionModule, SubscriptionPlan } from '@/lib/subscriptions'

/** R-102 to R-104, S-45: platform plans, which modules each may use, and the free-trial length. */
export default function AdminPlans() {
  const [data, setData] = useState<AdminPlansResponse | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [editing, setEditing] = useState<SubscriptionPlan | 'new' | null>(null)

  useEffect(() => {
    let live = true
    setError('')
    api<AdminPlansResponse>('/admin/subscription-plans')
      .then((result) => live && setData(result))
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'Plans could not be loaded.'))
    return () => { live = false }
  }, [version])

  const reload = () => setVersion((value) => value + 1)

  async function toggleRetired(plan: SubscriptionPlan) {
    try {
      await api(`/admin/subscription-plans/${plan.id}/toggle-retired`, { method: 'POST' })
      toastManager.add({ title: plan.retired ? `${plan.name} can be chosen again.` : `${plan.name} retired. Gyms on it keep it until their month ends.`, type: 'success' })
      reload()
    } catch (failure) {
      toastManager.add({ title: failure instanceof ApiError ? failure.message : 'The plan could not be changed.', type: 'error' })
    }
  }

  return <div className="flex min-w-0 flex-col gap-6">
    <PageHeader title="Subscription plans" description="What gyms pay for the platform, and which owner-side modules each plan includes. Gym setup and the owner's subscription screen are always open."
      actions={<Button onClick={() => setEditing('new')}><Plus aria-hidden />New plan</Button>} />
    {error ? <Alert variant="error"><AlertTitle>Plans could not be loaded</AlertTitle><AlertDescription>
      <p>{error}</p><Button variant="outline" onClick={reload}>Try again</Button>
    </AlertDescription></Alert>
      : !data ? <div className="flex flex-col gap-2" aria-busy="true" aria-label="Loading plans"><Skeleton className="h-24" /><Skeleton className="h-24" /></div>
        : <>
          <TrialSetting days={data.trial_days} onSaved={reload} />
          <div className={PLAN_GRID}>{data.plans.map((plan) => <PlanCard key={plan.id} plan={plan} modules={data.modules}
            badge={<>
              {plan.is_free && <Badge variant="info">Free plan</Badge>}
              {plan.retired && <Badge variant="warning">Retired</Badge>}
            </>}
            footer={<div className="flex flex-wrap items-center gap-2 border-t pt-4">
              <p className="me-auto text-muted-foreground text-sm">{plan.paying_gyms ?? 0} {plan.paying_gyms === 1 ? 'gym' : 'gyms'} paying now</p>
              <Button size="sm" variant="outline" onClick={() => setEditing(plan)}>Edit</Button>
              {!plan.is_free && <Button size="sm" variant="ghost" onClick={() => toggleRetired(plan)}>{plan.retired ? 'Bring back' : 'Retire'}</Button>}
            </div>} />)}</div>
        </>}
    {editing && data && <PlanDialog plan={editing === 'new' ? null : editing} modules={data.modules} onClose={() => setEditing(null)} onSaved={() => { setEditing(null); reload() }} />}
  </div>
}

function TrialSetting({ days, onSaved }: { days: number; onSaved: () => void }) {
  const [value, setValue] = useState(String(days))
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    setErrors({})
    try {
      await api('/admin/subscription-settings', { method: 'PUT', body: { trial_days: Number(value) } })
      toastManager.add({ title: 'Trial length saved. It applies to gyms approved from now on.', type: 'success' })
      onSaved()
    } catch (failure) {
      setErrors(failure instanceof ApiError ? failure.errors : {})
    } finally { setBusy(false) }
  }

  return <Form errors={errors} onSubmit={save} className="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-5">
    <div className="w-40"><TextField name="trial_days" label="Free trial (days)" type="number" inputMode="numeric" min={1} max={365} step={1} required
      value={value} onChange={(event) => setValue(event.target.value)} /></div>
    <Button type="submit" variant="outline" loading={busy}>Save</Button>
    <p className="basis-full text-muted-foreground text-sm">Starts when a gym is approved. During the trial every module is open.</p>
  </Form>
}

function PlanDialog({ plan, modules, onClose, onSaved }: { plan: SubscriptionPlan | null; modules: SubscriptionModule[]; onClose: () => void; onSaved: () => void }) {
  const [name, setName] = useState(plan?.name ?? '')
  const [price, setPrice] = useState(plan?.monthly_price ?? '')
  const [inclusions, setInclusions] = useState(plan?.inclusions ?? '')
  const [chosen, setChosen] = useState<string[]>(plan?.modules ?? [])
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (busy) return
    setBusy(true)
    setErrors({})
    const body = { name, monthly_price: plan?.is_free ? 0 : Number(price), inclusions, modules: chosen }
    try {
      await api(plan ? `/admin/subscription-plans/${plan.id}` : '/admin/subscription-plans', { method: plan ? 'PUT' : 'POST', body })
      toastManager.add({ title: plan ? 'Plan saved.' : 'Plan created.', type: 'success' })
      onSaved()
    } catch (failure) {
      setErrors(failure instanceof ApiError ? failure.errors : {})
      if (!(failure instanceof ApiError) || Object.keys(failure.errors).length === 0) {
        toastManager.add({ title: failure instanceof ApiError ? failure.message : 'The plan could not be saved.', type: 'error' })
      }
    } finally { setBusy(false) }
  }

  const toggle = (key: string, on: boolean) => setChosen((current) => on ? [...current, key] : current.filter((item) => item !== key))

  return <Dialog open onOpenChange={(open) => !open && !busy && onClose()}><DialogContent>
    <DialogHeader><DialogTitle>{plan ? `Edit ${plan.name}` : 'New plan'}</DialogTitle>
      <DialogDescription>Changes apply to every gym on this plan straight away.</DialogDescription></DialogHeader>
    <Form errors={errors} onSubmit={save}><DialogPanel className="flex flex-col gap-4">
      <fieldset disabled={busy} className="flex min-w-0 flex-col gap-4">
        <TextField name="name" label="Name" value={name} onChange={(event) => setName(event.target.value)} required maxLength={60} autoFocus />
        {plan?.is_free
          ? <p className="text-muted-foreground text-sm">This is the free plan gyms fall back to, so it has no price.</p>
          : <TextField name="monthly_price" label="Price per month (PHP)" type="number" inputMode="decimal" min={20} step="0.01" required
            value={price} onChange={(event) => setPrice(event.target.value)} description="PayMongo's smallest payment is PHP 20." />}
        <Field name="inclusions"><FieldLabel>What it includes</FieldLabel>
          <Textarea name="inclusions" value={inclusions} onChange={(event) => setInclusions(event.target.value)} maxLength={2000} rows={4} />
          <FieldDescription>Shown to owners. One item per line.</FieldDescription><FieldError /></Field>
        <Field name="modules" render={<fieldset />}><legend className="font-medium text-sm">Modules this plan can use</legend>
          <div className="grid gap-2 sm:grid-cols-2">{modules.map((module) => <label key={module.key} className="flex items-center gap-2 text-sm">
            <Checkbox aria-label={module.label} checked={chosen.includes(module.key)} onCheckedChange={(on) => toggle(module.key, on === true)} />{module.label}
          </label>)}</div>
          <FieldDescription>Unticked modules are hidden and refused for gyms on this plan.</FieldDescription><FieldError /></Field>
      </fieldset>
    </DialogPanel><DialogFooter><Button type="button" variant="outline" disabled={busy} onClick={onClose}>Cancel</Button><Button type="submit" loading={busy}>Save plan</Button></DialogFooter></Form>
  </DialogContent></Dialog>
}
