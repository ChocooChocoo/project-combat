import { ArrowLeft, Check, ChevronRight, CreditCard } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router'
import { HistoryList, MembershipStatusBadge, PaymentsList } from '@/components/membership/membership-parts'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError } from '@/lib/api'
import { balanceSourceLabel, myBalanceFrom, type MyBalance } from '@/lib/finance'
import { dateLabel, money, periodLabel, type Membership } from '@/lib/membership'
import { cn } from '@/lib/utils'

function LoadError({ message, retry }: { message: string; retry: () => void }) {
  return (
    <Alert variant="error">
      <AlertTitle>Your memberships could not be loaded</AlertTitle>
      <AlertDescription>{message}</AlertDescription>
      <AlertAction>
        <Button size="xs" variant="outline" onClick={retry}>
          Try again
        </Button>
      </AlertAction>
    </Alert>
  )
}

/** S-39: every membership and application this person has, in any gym. */
function gymInitials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase()
}

export function MyMembershipList() {
  const [memberships, setMemberships] = useState<Membership[] | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [balance, setBalance] = useState<MyBalance | null>(null)
  const [balanceError, setBalanceError] = useState('')
  const [balanceVersion, setBalanceVersion] = useState(0)

  useEffect(() => {
    let live = true
    setMemberships(null)
    setError('')
    api<{ memberships: Membership[] }>('/my-memberships')
      .then((result) => live && setMemberships(result.memberships))
      .catch((failure) => {
        if (!live) return
        setMemberships([])
        setError(failure instanceof ApiError ? failure.message : 'Try again in a moment.')
      })
    return () => {
      live = false
    }
  }, [version])

  useEffect(() => {
    let live = true
    setBalance(null)
    setBalanceError('')
    api<Record<string, unknown>>('/my-balance')
      .then((payload) => live && setBalance(myBalanceFrom(payload)))
      .catch((failure) => {
        if (!live) return
        setBalance(null)
        setBalanceError(failure instanceof ApiError ? failure.message : 'Try again in a moment.')
      })
    return () => {
      live = false
    }
  }, [balanceVersion])

  return (
    <div className="flex min-w-0 flex-col gap-8">
      <header className="flex flex-col gap-3 border-b pb-6">
        <h1 className="font-semibold text-3xl tracking-tight sm:text-4xl">My memberships</h1>
        <p className="max-w-prose text-muted-foreground text-sm leading-relaxed">Your applications and memberships in every gym, what you owe, and your receipts.</p>
      </header>

      <Card className="min-w-0 gap-0 overflow-hidden p-0">
        <div className="flex flex-wrap items-end justify-between gap-6 border-b bg-muted/30 p-5 sm:p-6">
          <div className="min-w-0">
            <h2 className="font-semibold text-base">Balance across gyms</h2>
            <p className="mt-2 max-w-prose text-muted-foreground text-xs leading-relaxed">Membership balances, unpaid session add-ons and private coaching fees in one place.</p>
          </div>
          {balance !== null && balanceError === '' && (
            <div aria-live="polite">
              <p className="text-muted-foreground text-xs">Total due</p>
              <p className="mt-1 font-semibold text-4xl tracking-tight tabular-nums wrap-anywhere">{money(balance.balance)}</p>
            </div>
          )}
        </div>
        <div className="p-5 sm:p-6" aria-live="polite">
          {balanceError ? (
            <Alert variant="error">
              <AlertTitle>Your balance could not be loaded</AlertTitle>
              <AlertDescription>{balanceError}</AlertDescription>
              <AlertAction>
                <Button size="xs" variant="outline" onClick={() => setBalanceVersion((value) => value + 1)}>
                  Try again
                </Button>
              </AlertAction>
            </Alert>
          ) : balance === null ? (
            <div className="flex flex-col gap-2" aria-busy="true" aria-label="Loading your balance">
              <Skeleton className="h-9 w-40" />
              <Skeleton className="h-14" />
            </div>
          ) : balance.sources.length === 0 ? (
            <p className="text-muted-foreground text-sm leading-relaxed">Nothing is outstanding. Paid-up memberships and sessions are not listed here.</p>
          ) : (
            <ul className="divide-y">
              {balance.sources.map((source, index) => (
                <li
                  key={`${source.kind ?? 'source'}-${source.id ?? 'none'}-${index}`}
                  className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3 first:pt-0 last:pb-0"
                >
                  <span className="min-w-0 text-sm leading-relaxed wrap-anywhere">{balanceSourceLabel(source)}</span>
                  <span className="ms-auto font-medium text-sm tabular-nums">{money(source.amount)}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
      </Card>

      <section className="flex min-w-0 flex-col gap-4" aria-labelledby="membership-list">
        <div className="flex flex-wrap items-baseline gap-2">
          <h2 id="membership-list" className="font-semibold text-xl tracking-tight">Your memberships</h2>
          {memberships !== null && (
            <span className="text-muted-foreground text-sm tabular-nums">
              {memberships.length} {memberships.length === 1 ? 'gym' : 'gyms'}
            </span>
          )}
        </div>

      {error ? (
        <LoadError message={error} retry={() => setVersion((v) => v + 1)} />
      ) : memberships === null ? (
        <div className="flex flex-col gap-3" aria-busy="true">
          <Skeleton className="h-24" />
          <Skeleton className="h-24" />
        </div>
      ) : memberships.length === 0 ? (
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>No memberships yet</EmptyTitle>
            <EmptyDescription>Find a gym, open its page and apply on one of its plans.</EmptyDescription>
          </EmptyHeader>
          <Button render={<Link to="/" />}>Find a gym</Button>
        </Empty>
      ) : (
        <ul className="flex min-w-0 flex-col gap-4">
          {memberships.map((membership) => {
            const due = Number(membership.balance) > 0 && membership.status !== 'cancelled'
            return (
            <li key={membership.id} className="min-w-0">
              <Link
                to={`/memberships/${membership.id}`}
                className="group flex min-w-0 flex-col gap-4 rounded-xl border bg-card p-5 outline-none transition-colors hover:bg-accent/40 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none sm:flex-row sm:items-center sm:gap-6"
              >
                <div className="flex min-w-0 flex-1 items-start gap-4">
                  <span className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-muted font-semibold text-sm" aria-hidden>
                    {gymInitials(membership.gym_name)}
                  </span>
                  <div className="flex min-w-0 flex-col gap-1">
                    <p className="font-semibold text-lg tracking-tight wrap-anywhere">{membership.gym_name}</p>
                    <p className="text-muted-foreground text-sm wrap-anywhere">
                      {membership.plan?.name ?? 'No plan listed'}
                      {periodLabel(membership) ? ` · ${periodLabel(membership)}` : ''}
                    </p>
                  </div>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-4 border-t pt-4 sm:justify-end sm:border-0 sm:pt-0">
                  <div className="flex flex-col items-start gap-0.5 sm:items-end">
                    <p className={cn('font-semibold text-sm tabular-nums wrap-anywhere', !due && 'text-muted-foreground')}>
                      {due ? `${money(membership.balance)} due` : 'Nothing due'}
                    </p>
                    <p className="text-muted-foreground text-xs">Membership</p>
                  </div>
                  <MembershipStatusBadge status={membership.status} />
                  <ChevronRight className="size-4 shrink-0 text-muted-foreground group-hover:text-foreground" aria-hidden />
                </div>
              </Link>
            </li>
            )
          })}
        </ul>
      )}
      </section>
    </div>
  )
}

/** S-39: one membership with its plan, balance, online payment, receipts and history (R-34 to R-36). */
export default function MyMembership() {
  const { id } = useParams()
  const [search, setSearch] = useSearchParams()
  const [membership, setMembership] = useState<Membership | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [busy, setBusy] = useState(false)
  const [payError, setPayError] = useState('')
  const confirming = useRef(false)

  useEffect(() => {
    let live = true
    setError('')
    const returned = search.get('checkout') === 'done' && !confirming.current
    if (returned) confirming.current = true

    // Back from PayMongo: the backend asks PayMongo whether it was really paid.
    const load = returned
      ? api<{ paid: boolean; membership: Membership }>(`/my-memberships/${id}/checkout/confirm`, { method: 'POST' }).then((result) => {
          toastManager.add(
            result.paid
              ? { title: 'Payment received. Thank you.', type: 'success' }
              : { title: 'PayMongo has not confirmed this payment yet. Check again in a minute.', type: 'warning' },
          )
          setSearch({}, { replace: true })
          return result
        })
      : api<{ membership: Membership }>(`/my-memberships/${id}`)

    load
      .then((result) => live && setMembership(result.membership))
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'Try again in a moment.'))
    return () => {
      live = false
    }
  }, [id, version, search, setSearch])

  useEffect(() => {
    if (search.get('checkout') === 'cancelled') {
      toastManager.add({ title: 'Payment cancelled. Nothing was charged.', type: 'info' })
      setSearch({}, { replace: true })
    }
  }, [search, setSearch])

  async function payOnline() {
    setBusy(true)
    setPayError('')
    try {
      const result = await api<{ checkout_url: string }>(`/my-memberships/${id}/checkout`, { method: 'POST' })
      window.location.href = result.checkout_url
    } catch (failure) {
      const message =
        failure instanceof ApiError && failure.status < 500
          ? failure.message
          : 'Online payment is unavailable right now. Please try again or pay at the front desk.'
      setPayError(message)
      toastManager.add({ title: message, type: 'error' })
      setBusy(false)
    }
  }

  if (error) return <LoadError message={error} retry={() => setVersion((v) => v + 1)} />
  if (!membership) {
    return (
      <div className="flex flex-col gap-6" aria-busy="true" aria-label="Loading">
        <Skeleton className="h-16 w-72" />
        <Skeleton className="h-40" />
        <Skeleton className="h-56" />
      </div>
    )
  }

  const balance = Number(membership.balance)
  const canPay = balance > 0 && ['awaiting_payment', 'active', 'frozen'].includes(membership.status)
  const plan = membership.plan

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
            <Button className="-ms-3 min-h-11" size="sm" variant="ghost" render={<Link to="/memberships" />}>
              <ArrowLeft aria-hidden /> All memberships
            </Button>
            {['active', 'frozen', 'awaiting_payment', 'expired'].includes(membership.status) && (
              <Button className="min-h-11" size="sm" variant="outline" render={<Link to={`/gyms/${membership.gym_id}/my-record`} />}>
                My record
              </Button>
            )}
      </div>
      <header className="flex flex-col items-start gap-3 border-b pb-6">
        <div className="flex flex-wrap items-center gap-x-4 gap-y-3">
          <h1 className="text-balance font-semibold text-3xl tracking-tight sm:text-4xl">{membership.gym_name}</h1>
          <MembershipStatusBadge status={membership.status} />
        </div>
        <p className="text-muted-foreground text-sm">Applied {dateLabel(membership.created_at)}</p>
      </header>

      {membership.status === 'applied' && (
        <Alert variant="info">
          <AlertTitle>Waiting for the gym</AlertTitle>
          <AlertDescription>Staff will review your application. You get a notice when they decide.</AlertDescription>
        </Alert>
      )}
      {membership.status === 'rejected' && (
        <Alert variant="error">
          <AlertTitle>The gym did not approve this application</AlertTitle>
          <AlertDescription>{membership.decision_reason ?? 'No reason was given.'}</AlertDescription>
        </Alert>
      )}
      {membership.status === 'awaiting_payment' && (
        <Alert variant="warning">
          <AlertTitle>Approved. Pay to start your membership.</AlertTitle>
          <AlertDescription>Pay {money(membership.balance)} online below, or at the front desk. Your membership starts the day it is paid in full.</AlertDescription>
        </Alert>
      )}

      <div className="grid gap-5 lg:grid-cols-2">
          {!['applied', 'rejected'].includes(membership.status) && (
          <Card className="min-w-0 gap-0 overflow-hidden p-0">
            <div className="p-6">
              <h2 className="font-medium text-sm">{balance < 0 ? 'Paid in advance' : balance === 0 ? 'Balance' : 'Balance due'}</h2>
              <p className="mt-3 font-semibold text-4xl tracking-tight tabular-nums wrap-anywhere">{money(Math.abs(balance))}</p>
              {balance === 0 && <p className="mt-2 text-success-foreground text-sm">Nothing owed on this membership.</p>}
              <dl className="mt-6 grid grid-cols-2 gap-x-4 gap-y-3 border-t pt-4 text-sm">
                <dt className="text-muted-foreground">Charged</dt>
                <dd className="text-right tabular-nums">{money(membership.amount_charged)}</dd>
                <dt className="text-muted-foreground">Paid</dt>
                <dd className="text-right tabular-nums">{money(membership.amount_paid)}</dd>
              </dl>
            </div>
            {(canPay || payError) && (
            <div className="mt-auto flex flex-col gap-3 border-t bg-muted/30 p-6">
              {canPay && (
                <Button className="min-h-11 h-auto w-full whitespace-normal py-2" onClick={payOnline} loading={busy}>
                  <CreditCard aria-hidden />
                  Pay {money(membership.balance)} online
                </Button>
              )}
              {payError && (
                <Alert variant="error">
                  <AlertTitle>Online payment could not start</AlertTitle>
                  <AlertDescription>{payError}</AlertDescription>
                </Alert>
              )}
              {canPay && <p className="text-muted-foreground text-xs leading-relaxed">GCash, Maya or card through PayMongo. It counts once PayMongo confirms it.</p>}
            </div>
            )}
          </Card>
          )}

          {plan && (
            <Card className="min-w-0 gap-0 overflow-hidden p-0">
              <div className="border-b p-6">
                <h2 className="font-semibold text-xl tracking-tight wrap-anywhere">{plan.name}</h2>
                <p className="mt-2 text-muted-foreground text-sm">{periodLabel(membership) ?? 'Starts once paid'}</p>
              </div>
              <div className="flex flex-1 flex-col gap-4 p-6 text-sm">
                {membership.status === 'frozen' && membership.frozen_until && <p className="font-medium">Frozen until {dateLabel(membership.frozen_until)}.</p>}
                {plan.inclusions.length > 0 && (
                  <ul className="flex flex-col gap-3">
                    {plan.inclusions.map((item, index) => (
                      <li className="flex items-start gap-3 leading-6" key={`${index}-${item}`}>
                        <Check className="mt-1 size-4 shrink-0 text-success-foreground" aria-hidden />
                        <span className="min-w-0 wrap-anywhere">{item}</span>
                      </li>
                    ))}
                  </ul>
                )}
                {plan.max_freeze_days !== null && (
                  <p className="mt-auto border-t pt-4 text-muted-foreground text-xs leading-relaxed">
                    Freeze: {membership.freeze_days_used} of {plan.max_freeze_days} days used this period.
                  </p>
                )}
              </div>
            </Card>
          )}
      </div>
      <section className="min-w-0 pt-2" aria-labelledby="membership-payments">
        <h2 id="membership-payments" className="font-semibold text-xl tracking-tight">Payments and receipts</h2>
        <p className="mt-1 text-muted-foreground text-sm">Every payment you made, with who received it.</p>
        <div className="mt-5">
          <PaymentsList payments={membership.payments ?? []} />
        </div>
      </section>
      <section className="min-w-0 border-t pt-6" aria-labelledby="membership-history">
        <h2 id="membership-history" className="font-semibold text-lg tracking-tight">Membership history</h2>
        <p className="mt-1 text-muted-foreground text-sm">Application, payment and status changes.</p>
        <div className="mt-5">
          <HistoryList events={membership.history ?? []} />
        </div>
      </section>
    </div>
  )
}
