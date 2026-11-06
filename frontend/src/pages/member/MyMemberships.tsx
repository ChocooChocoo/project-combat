import { ArrowLeft, CreditCard } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router'
import { HistoryList, MembershipStatusBadge, PaymentsList } from '@/components/membership/membership-parts'
import { PageHeader } from '@/components/page-header'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError } from '@/lib/api'
import { balanceSourceLabel, myBalanceFrom, type MyBalance } from '@/lib/finance'
import { dateLabel, money, periodLabel, type Membership } from '@/lib/membership'

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
    <div className="flex flex-col gap-6">
      <PageHeader title="My memberships" description="Your applications and memberships in every gym, what you owe, and your receipts." />

      <Card>
        <CardHeader>
          <CardTitle render={<h2 />}>Balance across gyms</CardTitle>
          <CardDescription>Membership balances, unpaid session add-ons and private coaching fees in one place.</CardDescription>
        </CardHeader>
        <CardPanel>
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
          ) : (
            <div className="flex flex-col gap-3" aria-live="polite">
              <p className="text-sm">
                <span className="text-muted-foreground">Combined balance</span>{' '}
                <strong className="font-semibold text-xl tabular-nums">{money(balance.balance)}</strong>
              </p>
              {balance.sources.length === 0 ? (
                <p className="text-muted-foreground text-sm">Nothing is outstanding. Paid-up memberships and sessions are not listed here.</p>
              ) : (
                <ul className="flex flex-col divide-y rounded-xl border">
                  {balance.sources.map((source, index) => (
                    <li
                      key={`${source.kind ?? 'source'}-${source.id ?? 'none'}-${index}`}
                      className="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3 text-sm"
                    >
                      <span className="min-w-0 break-words">{balanceSourceLabel(source)}</span>
                      <span className="font-medium tabular-nums">{money(source.amount)}</span>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}
        </CardPanel>
      </Card>

      {error ? (
        <LoadError message={error} retry={() => setVersion((v) => v + 1)} />
      ) : memberships === null ? (
        <div className="flex flex-col gap-3" aria-busy="true">
          <Skeleton className="h-20" />
          <Skeleton className="h-20" />
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
        <ul className="flex flex-col gap-3">
          {memberships.map((membership) => (
            <li key={membership.id}>
              <Link
                to={`/memberships/${membership.id}`}
                className="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border p-4 outline-none transition-colors hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-ring"
              >
                <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                  <span className="font-medium">{membership.gym_name}</span>
                  <span className="text-muted-foreground text-sm">
                    {membership.plan?.name}
                    {periodLabel(membership) ? ` · ${periodLabel(membership)}` : ''}
                  </span>
                </span>
                {Number(membership.balance) > 0 && membership.status !== 'cancelled' && (
                  <span className="text-sm tabular-nums">{money(membership.balance)} due</span>
                )}
                <MembershipStatusBadge status={membership.status} />
              </Link>
            </li>
          ))}
        </ul>
      )}
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
      <PageHeader
        title={membership.gym_name}
        meta={<MembershipStatusBadge status={membership.status} />}
        description={plan ? `${plan.name} · applied ${dateLabel(membership.created_at)}` : undefined}
        actions={
          <>
            <Button size="sm" variant="outline" render={<Link to="/memberships" />}>
              <ArrowLeft aria-hidden /> All memberships
            </Button>
            {['active', 'frozen', 'awaiting_payment', 'expired'].includes(membership.status) && (
              <Button size="sm" variant="outline" render={<Link to={`/gyms/${membership.gym_id}/my-record`} />}>
                My record
              </Button>
            )}
          </>
        }
      />

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

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div className="flex min-w-0 flex-col gap-6">
          <Card>
            <CardHeader>
              <CardTitle render={<h2 />}>Payments and receipts</CardTitle>
              <CardDescription>Every payment you made, with who received it.</CardDescription>
            </CardHeader>
            <CardPanel>
              <PaymentsList payments={membership.payments ?? []} />
            </CardPanel>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle render={<h2 />}>History</CardTitle>
              <CardDescription>Every change to this membership.</CardDescription>
            </CardHeader>
            <CardPanel>
              <HistoryList events={membership.history ?? []} />
            </CardPanel>
          </Card>
        </div>

        <div className="flex flex-col gap-6">
          {!['applied', 'rejected'].includes(membership.status) && (
          <Card>
            <CardHeader>
              <CardTitle render={<h2 />}>Balance</CardTitle>
            </CardHeader>
            <CardPanel className="flex flex-col gap-4">
              <dl className="grid grid-cols-2 gap-3 text-sm">
                <dt className="text-muted-foreground">Charged</dt>
                <dd className="text-right tabular-nums">{money(membership.amount_charged)}</dd>
                <dt className="text-muted-foreground">Paid</dt>
                <dd className="text-right tabular-nums">{money(membership.amount_paid)}</dd>
                <dt className="font-medium">{balance < 0 ? 'Paid in advance' : 'Still owed'}</dt>
                <dd className="text-right font-semibold tabular-nums">{money(Math.abs(balance))}</dd>
              </dl>
              {canPay && (
                <Button onClick={payOnline} loading={busy}>
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
              {canPay && <p className="text-muted-foreground text-xs">GCash, Maya or card through PayMongo. It counts once PayMongo confirms it.</p>}
            </CardPanel>
          </Card>
          )}

          {plan && (
            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>{plan.name}</CardTitle>
                <CardDescription>{periodLabel(membership) ?? 'Starts once paid'}</CardDescription>
              </CardHeader>
              <CardPanel className="flex flex-col gap-3 text-sm">
                {membership.status === 'frozen' && membership.frozen_until && <p>Frozen until {dateLabel(membership.frozen_until)}.</p>}
                {plan.inclusions.length > 0 && (
                  <ul className="list-inside list-disc text-muted-foreground">
                    {plan.inclusions.map((item) => (
                      <li key={item}>{item}</li>
                    ))}
                  </ul>
                )}
                {plan.max_freeze_days !== null && (
                  <p className="text-muted-foreground">
                    Freeze: {membership.freeze_days_used} of {plan.max_freeze_days} days used this period.
                  </p>
                )}
              </CardPanel>
            </Card>
          )}
        </div>
      </div>
    </div>
  )
}
