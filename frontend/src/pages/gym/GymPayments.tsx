import { Plus } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useParams } from 'react-router'
import { PaymentsList } from '@/components/membership/membership-parts'
import { CorrectPaymentDialog, RecordPaymentDialog } from '@/components/membership/payment-dialogs'
import { PageHeader } from '@/components/page-header'
import { RangePicker } from '@/components/decision-support/range-picker'
import { NO_RANGE, rangeQuery } from '@/lib/decision-support'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { api, ApiError } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import { correctable, money, type Membership, type Payment } from '@/lib/membership'
import type { OwedBooking } from '@/lib/bookings'

/** S-30, R-34 and R-35: the payments desk. Desk payments are recorded here; online ones arrive on their own. */
export default function GymPayments() {
  const { gymId: gymIdParam } = useParams()
  const gymId = Number(gymIdParam)
  const { user } = useAuth()
  const gym = approvedGym(user, gymId)
  const canRecord = gym ? can(gym, 'finance.payments.manage') : false

  const [payments, setPayments] = useState<Payment[] | null>(null)
  const [members, setMembers] = useState<Membership[]>([])
  const [bookings, setBookings] = useState<OwedBooking[]>([])
  const [choicesError, setChoicesError] = useState('')
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [recording, setRecording] = useState(false)
  const [correcting, setCorrecting] = useState<Payment | null>(null)
  const [range, setRange] = useState(NO_RANGE)

  useEffect(() => {
    let live = true
    setPayments(null)
    setError('')
    api<{ payments: Payment[] }>(`/gyms/${gymId}/payments${rangeQuery(range, '?')}`)
      .then((result) => live && setPayments(result.payments))
      .catch((failure) => {
        if (!live) return
        setPayments([])
        setError(failure instanceof ApiError ? failure.message : 'Could not load payments.')
      })
    if (canRecord) {
      setChoicesError('')
      api<{ memberships: Membership[] }>(`/gyms/${gymId}/memberships?view=members`)
        .then((result) => live && setMembers(result.memberships.filter((m) => ['awaiting_payment', 'active', 'frozen'].includes(m.status))))
        .catch(() => live && setChoicesError('Could not load membership choices. Try again.'))
      api<{ bookings: OwedBooking[] }>(`/gyms/${gymId}/payments/owed-bookings`)
        .then((result) => live && setBookings(result.bookings))
        .catch(() => live && setChoicesError('Could not load session add-ons owed. Try again.'))
    }
    return () => {
      live = false
    }
  }, [gymId, version, canRecord, range])

  if (!gym) return null
  const refresh = () => setVersion((v) => v + 1)
  const owing = members.filter((m) => Number(m.balance) > 0)

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Payments"
        description="Membership and session add-on payments with receipts. Record cash, card and e-wallet payments here; PayMongo payments appear once confirmed."
        actions={
          canRecord && (
            <Button size="sm" onClick={() => setRecording(true)} disabled={(members.length === 0 && bookings.length === 0) || Boolean(choicesError)}>
              <Plus aria-hidden />
              Record payment
            </Button>
          )
        }
      />
      {choicesError && <Alert variant="error"><AlertTitle>Payment choices unavailable</AlertTitle><AlertDescription>{choicesError} <Button variant="outline" onClick={refresh}>Try again</Button></AlertDescription></Alert>}

      {canRecord && owing.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle render={<h2 />}>Outstanding balances</CardTitle>
            <CardDescription>Approved members who still owe money.</CardDescription>
          </CardHeader>
          <CardPanel>
            <ul className="divide-y">
              {owing.map((m) => (
                <li key={m.id} className="flex flex-wrap items-baseline justify-between gap-2 py-2 text-sm">
                  <span>
                    <span className="font-medium">{m.member.name}</span>
                    <span className="text-muted-foreground"> · {m.plan?.name}</span>
                  </span>
                  <span className="font-medium tabular-nums">{money(m.balance)}</span>
                </li>
              ))}
            </ul>
          </CardPanel>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle render={<h2 />}>Payment history</CardTitle>
          <CardDescription>Payments cannot be edited. A mistake is fixed with a correction entry, so the history stays complete.</CardDescription>
          <RangePicker optional name="payments" value={range} onChange={setRange} />
        </CardHeader>
        <CardPanel>
          {error ? (
            <Alert variant="error">
              <AlertTitle>Payments could not be loaded</AlertTitle>
              <AlertDescription>{error}</AlertDescription>
              <AlertAction>
                <Button size="xs" variant="outline" onClick={refresh}>
                  Try again
                </Button>
              </AlertAction>
            </Alert>
          ) : payments === null ? (
            <div className="flex flex-col gap-2" aria-busy="true">
              <Skeleton className="h-12" />
              <Skeleton className="h-12" />
            </div>
          ) : (
            <PaymentsList
              payments={payments}
              actions={
                canRecord
                  ? (payment) =>
                      correctable(payment, payments) ? (
                        <Button size="xs" variant="ghost" onClick={() => setCorrecting(payment)}>
                          Correct
                        </Button>
                      ) : null
                  : undefined
              }
            />
          )}
        </CardPanel>
      </Card>

      {recording && <RecordPaymentDialog
        gymId={gymId}
        open={recording}
        memberships={members}
        bookings={bookings}
        onClose={() => setRecording(false)}
        onSaved={() => {
          setRecording(false)
          refresh()
        }}
      />}
      <CorrectPaymentDialog
        gymId={gymId}
        payment={correcting}
        onClose={() => setCorrecting(null)}
        onSaved={() => {
          setCorrecting(null)
          refresh()
        }}
      />
    </div>
  )
}
