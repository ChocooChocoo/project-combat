import { Lock } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { useParams, useSearchParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { PLAN_GRID, PlanCard } from '@/components/subscription/plan-card'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { dateLabel, money } from '@/lib/membership'
import { daysLeft, statusLabels, type GymSubscriptionResponse, type SubscriptionPlan } from '@/lib/subscriptions'

/** R-105, R-107, S-46: the owner's plan, trial days left, paying through PayMongo, and past payments. */
export default function GymSubscription() {
  const { gymId } = useParams()
  const { refresh } = useAuth()
  const [search, setSearch] = useSearchParams()
  const [data, setData] = useState<GymSubscriptionResponse | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [paying, setPaying] = useState<number | null>(null)
  const confirming = useRef(false)

  useEffect(() => {
    let live = true
    setError('')
    const returned = search.get('checkout') === 'done' && !confirming.current
    if (returned) confirming.current = true

    // Back from PayMongo: the backend asks PayMongo whether it was really paid.
    const load = returned
      ? api<GymSubscriptionResponse & { paid: boolean }>(`/gyms/${gymId}/subscription/confirm`, { method: 'POST' }).then((result) => {
          toastManager.add(result.paid
            ? { title: 'Payment received. Your plan is active.', type: 'success' }
            : { title: 'PayMongo has not confirmed this payment yet. Check again in a minute.', type: 'warning' })
          setSearch({}, { replace: true })
          if (result.paid) void refresh()
          return result
        })
      : api<GymSubscriptionResponse>(`/gyms/${gymId}/subscription`)

    load
      .then((result) => live && setData(result))
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'Try again in a moment.'))
    return () => { live = false }
  }, [gymId, version, search, setSearch, refresh])

  useEffect(() => {
    if (search.get('checkout') === 'cancelled') {
      toastManager.add({ title: 'Payment cancelled. Nothing was charged.', type: 'info' })
      setSearch({}, { replace: true })
    }
  }, [search, setSearch])

  async function pay(plan: SubscriptionPlan) {
    setPaying(plan.id)
    try {
      const result = await api<{ checkout_url: string }>(`/gyms/${gymId}/subscription/checkout`, { body: { plan_id: plan.id } })
      window.location.href = result.checkout_url
    } catch (failure) {
      toastManager.add({ title: failure instanceof ApiError && failure.status < 500 ? failure.message : 'Online payment is unavailable right now. Try again in a moment.', type: 'error' })
      setPaying(null)
    }
  }

  const header = <PageHeader title="My subscription" description="Your gym's platform plan decides which modules you and your staff can use." />

  if (error) {
    return <div className="flex flex-col gap-6">{header}<Alert variant="error"><AlertTitle>Subscription could not be loaded</AlertTitle><AlertDescription>
      <p>{error}</p><Button variant="outline" onClick={() => setVersion((value) => value + 1)}>Try again</Button>
    </AlertDescription></Alert></div>
  }
  if (!data) {
    return <div className="flex flex-col gap-6" aria-busy="true" aria-label="Loading subscription">{header}<Skeleton className="h-32" /><Skeleton className="h-64" /></div>
  }

  const { subscription: sub, plans, modules, payments } = data
  const label = (key: string) => modules.find((module) => module.key === key)?.label ?? key
  const left = daysLeft(sub.period_ends_at)

  return <div className="flex min-w-0 flex-col gap-6">
    {header}

    <section aria-labelledby="current-plan" className="rounded-xl border bg-card p-5">
      <div className="flex flex-wrap items-center gap-3">
        <h2 id="current-plan" className="font-semibold text-lg">{sub.status === 'trial' ? 'Free trial' : sub.plan?.name ?? statusLabels[sub.status]}</h2>
        <Badge variant={sub.status === 'free' ? 'warning' : sub.status === 'trial' ? 'info' : 'success'}>{statusLabels[sub.status]}</Badge>
      </div>
      <p className="mt-2 text-muted-foreground text-sm">
        {sub.status === 'trial' && <>Every module is open until {dateLabel(sub.trial_ends_at)} ({left} {left === 1 ? 'day' : 'days'} left).{sub.paid_plan_name && <> Your paid {sub.paid_plan_name} month starts after the trial.</>}</>}
        {sub.status === 'paid' && <>Paid until {dateLabel(sub.paid_until)} ({left} {left === 1 ? 'day' : 'days'} left). Pay again before then to keep it.</>}
        {sub.status === 'free' && <>Your gym is on the free plan. Choose a paid plan below to unlock more modules. Your records in locked modules are kept.</>}
        {sub.status === 'unmanaged' && <>This gym is not on a platform plan, so nothing is limited.</>}
      </p>
      {sub.locked_modules.length > 0 && <div className="mt-4">
        <p className="font-medium text-sm">Locked on this plan</p>
        <ul className="mt-2 flex flex-wrap gap-2">{sub.locked_modules.map((key) => <li key={key}>
          <Badge variant="outline"><Lock aria-hidden className="size-3" />{label(key)}</Badge>
        </li>)}</ul>
      </div>}
    </section>

    <section aria-labelledby="plans" className="flex flex-col gap-3">
      <h2 id="plans" className="font-semibold">Plans</h2>
      <div className={PLAN_GRID}>{plans.map((plan) => {
        const current = (sub.status === 'paid' && sub.plan?.id === plan.id) || (sub.status === 'free' && plan.is_free)
        return <PlanCard key={plan.id} plan={plan} modules={modules} current={current}
          badge={current ? <Badge variant="info">Current plan</Badge> : plan.is_free ? <Badge variant="outline">Free</Badge> : null}
          action={plan.is_free
            ? <Button className="w-full" variant="outline" disabled>{current ? 'You are on this plan' : 'Used when nothing is paid'}</Button>
            : <Button className="w-full" variant={current ? 'outline' : 'default'} loading={paying === plan.id} disabled={paying !== null} onClick={() => pay(plan)}>
              {current ? 'Pay for another month' : `Get ${plan.name}`}
            </Button>} />
      })}</div>
      <p className="text-muted-foreground text-sm">Pay with GCash, Maya or card. A new month starts after any time you already have.</p>
    </section>

    <section aria-labelledby="payments" className="flex flex-col gap-3">
      <h2 id="payments" className="font-semibold">Payments</h2>
      {payments.length === 0
        ? <p className="text-muted-foreground text-sm">No payments yet.</p>
        : <Table variant="card">
          <TableHeader><TableRow><TableHead>Paid</TableHead><TableHead>Plan</TableHead><TableHead>Covers</TableHead><TableHead className="text-end">Amount</TableHead></TableRow></TableHeader>
          <TableBody>{payments.map((payment) => <TableRow key={payment.id}>
            <TableCell>{dateLabel(payment.paid_at)}</TableCell>
            <TableCell>{payment.plan_name}</TableCell>
            <TableCell className="whitespace-normal">{dateLabel(payment.period_starts_at)} to {dateLabel(payment.period_ends_at)}</TableCell>
            <TableCell className="text-end tabular-nums">{money(payment.amount)}</TableCell>
          </TableRow>)}</TableBody>
        </Table>}
    </section>
  </div>
}
