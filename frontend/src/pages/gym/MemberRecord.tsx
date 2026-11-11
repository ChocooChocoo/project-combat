import { ArrowLeft, Plus } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { AthleteRecordView, HealthSummary } from '@/components/membership/athlete-record-view'
import { HistoryList, MembershipStatusBadge, PaymentsList } from '@/components/membership/membership-parts'
import { CorrectPaymentDialog, RecordPaymentDialog } from '@/components/membership/payment-dialogs'
import { PageHeader } from '@/components/page-header'
import { TextField } from '@/components/text-field'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, apiFile, ApiError, formValues, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { manilaToday } from '@/lib/dashboard'
import { approvedGym, can } from '@/lib/gym-access'
import { correctable, dateLabel, ENTRY_LABELS, money, periodLabel, type AthleteEntry, type AthleteRecord, type Membership, type MembershipForm, type Payment } from '@/lib/membership'

type Action = 'renew' | 'freeze' | 'unfreeze' | 'cancel'
const ENTRY_TYPES = (Object.keys(ENTRY_LABELS) as AthleteEntry['type'][]).map((value) => ({ value, label: ENTRY_LABELS[value] }))

/** S-22: one member's membership, payments and athlete record, each part shown by permission (R-34 to R-41). */
export default function MemberRecord() {
  const { gymId: gymIdParam, membershipId } = useParams()
  const gymId = Number(gymIdParam)
  const { user } = useAuth()
  const gym = approvedGym(user, gymId)
  const canManage = gym ? can(gym, 'members.memberships.manage') : false
  const canPay = gym ? can(gym, 'finance.payments.manage') : false
  const canCoach = gym ? can(gym, 'training.athletes.manage') : false

  const [membership, setMembership] = useState<Membership | null>(null)
  const [record, setRecord] = useState<AthleteRecord | null>(null)
  const [form, setForm] = useState<MembershipForm | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)

  const [action, setAction] = useState<Action | null>(null)
  const [actionErrors, setActionErrors] = useState<FieldErrors>({})
  const [refundMethod, setRefundMethod] = useState('cash')
  const [entryOpen, setEntryOpen] = useState(false)
  const [entryType, setEntryType] = useState<AthleteEntry['type']>('promotion')
  const [entryErrors, setEntryErrors] = useState<FieldErrors>({})
  const [paying, setPaying] = useState(false)
  const [correcting, setCorrecting] = useState<Payment | null>(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let live = true
    setError('')
    // Coaches without membership access still reach the record through the member list.
    api<{ membership: Membership }>(`/gyms/${gymId}/memberships/${membershipId}`)
      .catch(async (failure) => {
        if (!(failure instanceof ApiError) || failure.status !== 403) throw failure
        const list = await api<{ memberships: Membership[] }>(`/gyms/${gymId}/memberships?view=members`)
        const found = list.memberships.find((m) => String(m.id) === membershipId)
        if (!found) throw failure
        return { membership: found }
      })
      .then(async (result) => {
        if (!live) return
        setMembership(result.membership)
        const recordResult = await api<{ record: AthleteRecord }>(`/gyms/${gymId}/athletes/${result.membership.member.id}/record`).catch(() => null)
        if (live) setRecord(recordResult?.record ?? null)
      })
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'Could not load this member.'))
    return () => {
      live = false
    }
  }, [gymId, membershipId, version])

  useEffect(() => {
    api<MembershipForm>('/membership-form').then(setForm).catch(() => undefined)
  }, [])

  if (!gym) return null
  const refresh = () => setVersion((v) => v + 1)

  async function runAction(event: React.FormEvent<HTMLFormElement>) {
    const values = formValues(event)
    if (!membership || !action) return
    const body: Record<string, unknown> = {}
    if (action === 'freeze') Object.assign(body, { days: Number(values.days), reason: values.reason || null })
    if (action === 'cancel') {
      body.reason = values.reason
      if (values.refund_amount) Object.assign(body, { refund_amount: Number(values.refund_amount), refund_method: refundMethod })
    }
    setBusy(true)
    try {
      const result = await api<{ membership: Membership }>(`/gyms/${gymId}/memberships/${membership.id}/${action}`, { body })
      setMembership(result.membership)
      setAction(null)
      setActionErrors({})
      toastManager.add({ title: { renew: 'Membership renewed.', freeze: 'Membership frozen.', unfreeze: 'Membership unfrozen.', cancel: 'Membership cancelled.' }[action], type: 'success' })
    } catch (failure) {
      const fieldErrors = failure instanceof ApiError ? failure.errors : {}
      setActionErrors(fieldErrors)
      if (Object.keys(fieldErrors).length === 0) {
        toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Could not save the change.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  async function addEntry(event: React.FormEvent<HTMLFormElement>) {
    const values = formValues(event)
    if (!membership) return
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/athletes/${membership.member.id}/entries`, {
        body: {
          type: entryType,
          discipline: values.discipline || null,
          title: values.title,
          division: values.division || null,
          result: values.result || null,
          occurred_on: values.occurred_on,
          notes: values.notes || null,
        },
      })
      toastManager.add({ title: `${ENTRY_LABELS[entryType]} added to the record.`, type: 'success' })
      setEntryOpen(false)
      setEntryErrors({})
      refresh()
    } catch (failure) {
      const fieldErrors = failure instanceof ApiError ? failure.errors : {}
      setEntryErrors(fieldErrors)
      if (Object.keys(fieldErrors).length === 0) {
        toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Could not add the entry.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  async function openFile() {
    try {
      await apiFile(`/gyms/${gymId}/athletes/${membership?.member.id}/medical-file`)
    } catch (failure) {
      toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Could not open the file.', type: 'error' })
    }
  }

  if (error) {
    return (
      <Alert variant="error">
        <AlertTitle>This member could not be loaded</AlertTitle>
        <AlertDescription>{error}</AlertDescription>
        <AlertAction>
          <Button size="xs" variant="outline" onClick={refresh}>
            Try again
          </Button>
        </AlertAction>
      </Alert>
    )
  }
  if (!membership) {
    return (
      <div className="flex flex-col gap-6" aria-busy="true" aria-label="Loading">
        <Skeleton className="h-16 w-72" />
        <Skeleton className="h-48" />
        <Skeleton className="h-64" />
      </div>
    )
  }

  const plan = membership.plan
  const status = membership.status
  const freezeLeft = plan?.max_freeze_days != null ? plan.max_freeze_days - membership.freeze_days_used : null
  const payments = membership.payments ?? []

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={membership.member.name}
        meta={<MembershipStatusBadge status={status} />}
        description={`${plan?.name ?? ''}${periodLabel(membership) ? ` · ${periodLabel(membership)}` : ''} · ${membership.member.email}`}
        actions={
          <Button size="sm" variant="outline" render={<Link to={`/gyms/${gymId}/manage/members`} />}>
            <ArrowLeft aria-hidden /> Members
          </Button>
        }
      />

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div className="flex min-w-0 flex-col gap-6">
          {membership.history && (
            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Membership</CardTitle>
                <CardDescription>
                  {status === 'frozen' && membership.frozen_until ? `Frozen until ${dateLabel(membership.frozen_until)}. ` : ''}
                  Every change is kept and the member is told.
                </CardDescription>
                {canManage && (
                  <CardAction className="flex flex-wrap gap-2">
                    {['active', 'frozen', 'expired'].includes(status) && (
                      <Button size="sm" variant="outline" onClick={() => setAction('renew')}>
                        Renew
                      </Button>
                    )}
                    {status === 'active' && plan?.max_freeze_days != null && (
                      <Button size="sm" variant="outline" onClick={() => setAction('freeze')} disabled={(freezeLeft ?? 0) <= 0}>
                        Freeze
                      </Button>
                    )}
                    {status === 'frozen' && (
                      <Button size="sm" variant="outline" onClick={() => setAction('unfreeze')}>
                        Unfreeze
                      </Button>
                    )}
                    {['awaiting_payment', 'active', 'frozen'].includes(status) && (
                      <Button size="sm" variant="destructive-outline" onClick={() => setAction('cancel')}>
                        Cancel
                      </Button>
                    )}
                  </CardAction>
                )}
              </CardHeader>
              <CardPanel>
                <HistoryList events={membership.history} />
              </CardPanel>
            </Card>
          )}

          {membership.payments && (
            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Payments</CardTitle>
                <CardDescription>
                  Charged {money(membership.amount_charged)} · paid {money(membership.amount_paid)} ·{' '}
                  {Number(membership.balance) < 0 ? `paid in advance ${money(-Number(membership.balance))}` : `owed ${money(membership.balance)}`}
                </CardDescription>
                {canPay && ['awaiting_payment', 'active', 'frozen'].includes(status) && (
                  <CardAction>
                    <Button size="sm" onClick={() => setPaying(true)}>
                      <Plus aria-hidden />
                      Record payment
                    </Button>
                  </CardAction>
                )}
              </CardHeader>
              <CardPanel>
                <PaymentsList
                  payments={payments}
                  actions={
                    canPay
                      ? (payment) =>
                          correctable(payment, payments) ? (
                            <Button size="xs" variant="ghost" onClick={() => setCorrecting(payment)}>
                              Correct
                            </Button>
                          ) : null
                      : undefined
                  }
                />
              </CardPanel>
            </Card>
          )}

          {record && (
            <AthleteRecordView
              record={record}
              action={
                canCoach && membership.member.id !== user?.id ? (
                  <Button size="sm" variant="outline" onClick={() => setEntryOpen(true)}>
                    <Plus aria-hidden />
                    Add to record
                  </Button>
                ) : null
              }
            />
          )}
        </div>

        <div className="flex flex-col gap-6">
          {record?.health ? (
            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Health and emergency</CardTitle>
                <CardDescription>Shown because you have health details access.</CardDescription>
              </CardHeader>
              <CardPanel>
                <HealthSummary health={record.health} form={form} onOpenFile={openFile} />
              </CardPanel>
            </Card>
          ) : (
            record && (
              <Card>
                <CardHeader>
                  <CardTitle render={<h2 />}>Health and emergency</CardTitle>
                  <CardDescription>Hidden. Only staff with health details access can see this member's health answers and emergency contacts.</CardDescription>
                </CardHeader>
              </Card>
            )
          )}
          {plan && (
            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Plan rules</CardTitle>
              </CardHeader>
              <CardPanel className="flex flex-col gap-2 text-sm">
                <p>{plan.max_freeze_days != null ? `Freezing: up to ${plan.max_freeze_days} days a period (${freezeLeft} left).` : 'Freezing: not allowed.'}</p>
                <p>{plan.refundable ? 'Refunds on cancelling: allowed.' : 'Refunds on cancelling: not given.'}</p>
                {plan.policy_cancellation && <p className="whitespace-pre-line text-muted-foreground">{plan.policy_cancellation}</p>}
              </CardPanel>
            </Card>
          )}
        </div>
      </div>

      <Dialog open={action !== null} onOpenChange={(next) => !next && setAction(null)}>
        <DialogPopup>
          {action && (
            <Form errors={actionErrors} onSubmit={runAction} className="flex min-h-0 flex-col">
              <DialogHeader>
                <DialogTitle>
                  {{ renew: 'Renew membership?', freeze: 'Freeze membership', unfreeze: 'Unfreeze membership?', cancel: 'Cancel membership' }[action]}
                </DialogTitle>
                <DialogDescription>
                  {action === 'renew' &&
                    (status === 'expired'
                      ? `Adds ${money(plan?.total_fee)} to pay. The new period starts once it is paid.`
                      : `Adds one more ${plan?.billing_type === 'sessions' ? `${plan.session_count} ${plan.session_count === 1 ? 'session' : 'sessions'}` : `${plan?.duration_months} month period`} and ${money(plan?.total_fee)} to pay.`)}
                  {action === 'freeze' && `The end date moves forward by the frozen days. ${freezeLeft} days left on this plan.`}
                  {action === 'unfreeze' && 'The membership becomes active today, and any unused frozen days are taken back off the end date.'}
                  {action === 'cancel' && 'The membership ends now. The member is told, with your reason.'}
                </DialogDescription>
              </DialogHeader>
              {(action === 'freeze' || action === 'cancel') && (
                <DialogPanel className="flex flex-col gap-4">
                  {action === 'freeze' && (
                    <TextField name="days" label="Days to freeze" type="number" min="1" max={String(Math.max(freezeLeft ?? 1, 1))} step="1" required inputMode="numeric" />
                  )}
                  <Field name="reason" className="w-full">
                    <FieldLabel>{action === 'cancel' ? 'Reason' : 'Reason (optional)'}</FieldLabel>
                    <Textarea name="reason" rows={3} maxLength={2000} required={action === 'cancel'} />
                    <FieldError />
                  </Field>
                  {action === 'cancel' && plan?.refundable && Number(membership.amount_paid) > 0 && (
                    <div className="grid gap-4 sm:grid-cols-2">
                      <TextField
                        name="refund_amount"
                        label="Refund (optional)"
                        type="number"
                        min="0.01"
                        max={membership.amount_paid}
                        step="0.01"
                        inputMode="decimal"
                        description={`Up to ${money(membership.amount_paid)}, following the plan's policy.`}
                      />
                      <Field name="refund_method" className="w-full">
                        <FieldLabel>Refund method</FieldLabel>
                        <Select
                          items={[
                            { value: 'cash', label: 'Cash' },
                            { value: 'card', label: 'Card' },
                            { value: 'ewallet', label: 'E-wallet' },
                          ]}
                          value={refundMethod}
                          onValueChange={(next) => setRefundMethod(String(next ?? 'cash'))}
                        >
                          <SelectTrigger aria-label="Refund method">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectPopup>
                            <SelectItem value="cash">Cash</SelectItem>
                            <SelectItem value="card">Card</SelectItem>
                            <SelectItem value="ewallet">E-wallet</SelectItem>
                          </SelectPopup>
                        </Select>
                        <FieldError />
                      </Field>
                    </div>
                  )}
                </DialogPanel>
              )}
              <DialogFooter>
                <Button type="button" variant="outline" onClick={() => setAction(null)}>
                  Back
                </Button>
                <Button type="submit" variant={action === 'cancel' ? 'destructive' : 'default'} loading={busy}>
                  {{ renew: 'Renew', freeze: 'Freeze', unfreeze: 'Unfreeze', cancel: 'Cancel membership' }[action]}
                </Button>
              </DialogFooter>
            </Form>
          )}
        </DialogPopup>
      </Dialog>

      <Dialog open={entryOpen} onOpenChange={setEntryOpen}>
        <DialogPopup>
          <Form errors={entryErrors} onSubmit={addEntry} className="flex min-h-0 flex-col">
            <DialogHeader>
              <DialogTitle>Add to {membership.member.name}'s record</DialogTitle>
              <DialogDescription>Recorded with your name and kept in the athlete's history.</DialogDescription>
            </DialogHeader>
            <DialogPanel className="flex flex-col gap-4">
              <Field name="type" className="w-full">
                <FieldLabel>Kind of entry</FieldLabel>
                <Select items={ENTRY_TYPES} value={entryType} onValueChange={(next) => setEntryType((next as AthleteEntry['type']) ?? 'promotion')}>
                  <SelectTrigger aria-label="Kind of entry">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectPopup>
                    {ENTRY_TYPES.map((item) => (
                      <SelectItem key={item.value} value={item.value}>
                        {item.label}
                      </SelectItem>
                    ))}
                  </SelectPopup>
                </Select>
                <FieldError />
              </Field>
              <TextField
                name="title"
                label={{ promotion: 'New rank', assessment: 'Skill assessed', competition: 'Event name', achievement: 'Achievement' }[entryType]}
                maxLength={150}
                required
                placeholder={{ promotion: 'For example: Blue belt', assessment: 'For example: Guard passing', competition: 'For example: Cavite Open 2026', achievement: 'For example: 100 classes attended' }[entryType]}
              />
              {entryType !== 'achievement' && (
                <TextField name="discipline" label={entryType === 'promotion' ? 'Discipline' : 'Discipline (optional)'} maxLength={60} required={entryType === 'promotion'} placeholder="For example: BJJ" />
              )}
              {entryType === 'competition' && <TextField name="division" label="Division (optional)" maxLength={100} placeholder="For example: Lightweight, 61 kg" />}
              {(entryType === 'competition' || entryType === 'assessment') && (
                <TextField name="result" label={entryType === 'competition' ? 'Result' : 'Score or outcome'} maxLength={150} required placeholder={entryType === 'competition' ? 'For example: Gold' : 'For example: 4 of 5'} />
              )}
              <TextField name="occurred_on" label="Date" type="date" max={manilaToday()} defaultValue={manilaToday()} required />
              <Field name="notes" className="w-full">
                <FieldLabel>Notes (optional)</FieldLabel>
                <Textarea name="notes" rows={3} maxLength={2000} />
                <FieldDescription>The athlete can read these notes.</FieldDescription>
                <FieldError />
              </Field>
            </DialogPanel>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setEntryOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" loading={busy}>
                Add to record
              </Button>
            </DialogFooter>
          </Form>
        </DialogPopup>
      </Dialog>

      <RecordPaymentDialog
        gymId={gymId}
        open={paying}
        memberships={[membership]}
        onClose={() => setPaying(false)}
        onSaved={() => {
          setPaying(false)
          refresh()
        }}
      />
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
