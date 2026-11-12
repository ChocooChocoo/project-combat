import { Plus, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { coachStatus, slotLabel, WEEKDAYS, type CoachApplication, type Slot } from '@/lib/coach'
import { dateLabel } from '@/lib/membership'

const DAY_ITEMS = Object.entries(WEEKDAYS).map(([value, label]) => ({ value, label }))

/** R-117: a member applies to coach at a gym, with the weekly hours they can give. */
export default function CoachApply() {
  const { gymId } = useParams()
  const navigate = useNavigate()
  const [slots, setSlots] = useState<Slot[]>([{ weekday: 'mon', start: '17:00', end: '20:00' }])
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  function setSlot(index: number, patch: Partial<Slot>) {
    setSlots((current) => current.map((slot, at) => (at === index ? { ...slot, ...patch } : slot)))
  }

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const values = Object.fromEntries(new FormData(event.currentTarget)) as Record<string, string>
    setBusy(true)
    setErrors({})
    try {
      await api(`/gyms/${gymId}/coach-applications`, { body: {
        experience: values.experience, rank: values.rank || null, specialisation: values.specialisation || null, bio: values.bio || null, availability: slots,
      } })
      toastManager.add({ title: 'Application sent. The gym will tell you its decision.', type: 'success' })
      navigate('/coach-applications')
    } catch (failure) {
      setErrors(failure instanceof ApiError ? failure.errors : {})
      if (!(failure instanceof ApiError) || Object.keys(failure.errors).length === 0) {
        toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Application could not be sent.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  const conflicts = errors.availability ?? []

  return (
    <div className="flex min-w-0 flex-col gap-6">
      <PageHeader title="Apply as coach" description="Tell the gym about your coaching and the weekly hours you can give. Hours that overlap your shifts at another gym cannot be offered." />
      <Form errors={errors} onSubmit={submit} className="flex max-w-3xl flex-col gap-6">
        <Card>
          <CardHeader><CardTitle render={<h2 />}>Your coaching</CardTitle></CardHeader>
          <CardPanel className="flex flex-col gap-4">
            <Field name="experience"><FieldLabel>Experience</FieldLabel><Textarea name="experience" rows={4} maxLength={5000} required /><FieldError /></Field>
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="rank" label="Rank or belt (optional)" maxLength={100} />
              <TextField name="specialisation" label="Specialisation (optional)" maxLength={150} />
            </div>
            <Field name="bio"><FieldLabel>Short bio (optional)</FieldLabel><Textarea name="bio" rows={3} maxLength={5000} /><FieldError /></Field>
          </CardPanel>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle render={<h2 />}>Weekly availability</CardTitle>
            <CardDescription>One time range per day. Approval turns these into your work schedule here.</CardDescription>
          </CardHeader>
          <CardPanel className="flex flex-col gap-3">
            {slots.map((slot, index) => (
              <div key={index} className="flex flex-wrap items-end gap-3">
                <Field className="w-40"><FieldLabel>Day</FieldLabel>
                  <Select items={DAY_ITEMS} value={slot.weekday} onValueChange={(value) => setSlot(index, { weekday: String(value) })}>
                    <SelectTrigger aria-label={`Day ${index + 1}`}><SelectValue /></SelectTrigger>
                    <SelectPopup>{DAY_ITEMS.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}</SelectPopup>
                  </Select>
                </Field>
                <Field className="w-32"><FieldLabel>From</FieldLabel><Input type="time" value={slot.start} onChange={(event) => setSlot(index, { start: event.target.value })} required /></Field>
                <Field className="w-32"><FieldLabel>To</FieldLabel><Input type="time" value={slot.end} onChange={(event) => setSlot(index, { end: event.target.value })} required /></Field>
                {slots.length > 1 && (
                  <Button type="button" size="icon" variant="ghost" aria-label={`Remove day ${index + 1}`} onClick={() => setSlots((current) => current.filter((_, at) => at !== index))}><Trash2 aria-hidden /></Button>
                )}
              </div>
            ))}
            {slots.length < 7 && (
              <Button type="button" variant="outline" className="self-start" onClick={() => setSlots((current) => [...current, { weekday: Object.keys(WEEKDAYS).find((day) => !current.some((slot) => slot.weekday === day)) ?? 'mon', start: '17:00', end: '20:00' }])}>
                <Plus aria-hidden /> Add a day
              </Button>
            )}
            {conflicts.length > 0 && (
              <Alert variant="error"><AlertTitle>These hours clash with your other job</AlertTitle><AlertDescription>
                <ul className="list-disc ps-5">{conflicts.map((line) => <li key={line}>{line}</li>)}</ul>
              </AlertDescription></Alert>
            )}
            <p className="text-muted-foreground text-xs">Times are Philippine time.</p>
          </CardPanel>
        </Card>
        <div className="flex gap-3"><Button type="submit" loading={busy}>Send application</Button><Button type="button" variant="outline" render={<Link to={`/gyms/${gymId}`} />}>Back to gym</Button></div>
      </Form>
    </div>
  )
}

/** R-117: the member's coach applications and their decisions. */
export function MyCoachApplications() {
  const [rows, setRows] = useState<CoachApplication[] | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)

  useEffect(() => {
    let live = true
    api<{ applications: CoachApplication[] }>('/me/coach-applications')
      .then((result) => live && setRows(result.applications))
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'Your applications could not be loaded.'))
    return () => { live = false }
  }, [version])

  async function withdraw(row: CoachApplication) {
    try {
      await api(`/me/coach-applications/${row.id}/withdraw`, { method: 'POST' })
      toastManager.add({ title: 'Application withdrawn.', type: 'success' })
      setVersion((value) => value + 1)
    } catch (failure) {
      toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Could not withdraw.', type: 'error' })
    }
  }

  return (
    <div className="flex min-w-0 flex-col gap-6">
      <PageHeader title="My coach applications" description="Apply from a gym's page. Approval makes you a coach there; your account stays a member account." />
      {error ? <Alert variant="error"><AlertTitle>Could not load</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>
        : !rows ? <Skeleton className="h-24" />
        : rows.length === 0 ? <Empty className="rounded-xl border"><EmptyHeader><EmptyTitle>No coach applications</EmptyTitle><EmptyDescription>Open a gym's page and choose Apply as coach.</EmptyDescription></EmptyHeader></Empty>
        : (
          <ul className="flex flex-col gap-3">
            {rows.map((row) => (
              <li key={row.id} className="flex flex-col gap-2 rounded-xl border p-4 text-sm sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 break-words">
                  <div className="flex flex-wrap items-center gap-2"><h2 className="font-medium text-base">{row.gym?.name}</h2><Badge variant={coachStatus[row.status].variant}>{coachStatus[row.status].label}</Badge></div>
                  <p className="mt-1 text-muted-foreground">{row.availability.map(slotLabel).join(' · ')}</p>
                  <p className="mt-1 text-muted-foreground text-xs">Sent {dateLabel(row.created_at)}{row.decision_reason && ` · ${row.decision_reason}`}</p>
                </div>
                {row.status === 'pending' && <Button size="sm" variant="outline" onClick={() => withdraw(row)}>Withdraw</Button>}
              </li>
            ))}
          </ul>
        )}
    </div>
  )
}
