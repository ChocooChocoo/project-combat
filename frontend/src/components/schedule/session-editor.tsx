import { ArrowLeft } from 'lucide-react'
import { useState } from 'react'
import { PageHeader } from '@/components/page-header'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { CheckboxGroup } from '@/components/ui/checkbox-group'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Fieldset, FieldsetLegend } from '@/components/ui/fieldset'
import { Form } from '@/components/ui/form'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { DAYS, type Day } from '@/lib/gyms'
import { dateKey, type SessionOptions, type TrainingSession } from '@/lib/sessions'

function endTime(start: string, duration: number): string {
  const [hour, minute] = start.split(':').map(Number)
  const total = hour * 60 + minute + duration
  return `${String(Math.floor(total / 60) % 24).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`
}

/** R-42, R-43: edit one occurrence; the server checks every repeated date before saving. */
export function SessionEditor({
  gymId,
  session,
  options,
  onBack,
  onSaved,
}: {
  gymId: string
  session: TrainingSession | null
  options: SessionOptions
  onBack: () => void
  onSaved: () => void
}) {
  const [form, setForm] = useState(() => ({
    gym_service_id: session?.gym_service_id != null ? String(session.gym_service_id) : '',
    name: session?.name ?? '',
    type: session?.type ?? 'classes',
    discipline: session?.discipline ?? '',
    level: session?.level ?? 'all',
    coach_id: session ? String(session.coach_id) : '',
    room_id: session ? String(session.room_id) : '',
    date: session?.starts_at.slice(0, 10) ?? dateKey(new Date()),
    start: session?.starts_at.slice(11, 16) ?? '09:00',
    end: session?.ends_at.slice(11, 16) ?? '10:00',
    capacity: String(session?.capacity ?? 12),
    allowed_plan_ids: (session?.allowed_plan_ids ?? []).map(String),
    content: session?.content ?? '',
    requirements: session?.requirements ?? '',
    bring: (session?.bring ?? []).join('\n'),
    fee: session?.fee ?? '',
    needs_approval: session?.needs_approval == null ? 'default' : session.needs_approval ? 'always' : 'never',
    repeat: false,
    weekdays: [] as Day[],
    until: '',
  }))
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)
  const set = <K extends keyof typeof form>(key: K, value: typeof form[K]) => {
    // A time or coach change may resolve errors on several scheduling fields.
    setErrors({})
    setForm((current) => ({ ...current, [key]: value }))
  }
  const messages = [...new Set(Object.values(errors).flat())]
  const sendsNotice = session && (form.coach_id !== String(session.coach_id) || form.room_id !== String(session.room_id)
    || `${form.date} ${form.start}` !== session.starts_at.slice(0, 16) || `${form.date} ${form.end}` !== session.ends_at.slice(0, 16))

  function chooseService(id: string) {
    setErrors({})
    const service = options.services.find((item) => String(item.id) === id)
    setForm((current) => service ? {
      ...current,
      gym_service_id: id,
      name: service.name,
      type: (service.activity_type || current.type) as typeof current.type,
      discipline: service.discipline ?? '',
      level: (service.level || current.level) as typeof current.level,
      content: service.overview ?? '',
      bring: (service.bring ?? []).join('\n'),
      fee: service.price_included ? '' : String(service.price ?? ''),
      end: service.duration_minutes ? endTime(current.start, service.duration_minutes) : current.end,
      capacity: service.activity_type === 'private_coaching' ? '1' : current.capacity,
    } : { ...current, gym_service_id: id })
  }

  function changeStart(start: string) {
    setErrors({})
    const service = options.services.find((item) => String(item.id) === form.gym_service_id)
    setForm((current) => ({
      ...current,
      start,
      end: start && service?.duration_minutes ? endTime(start, service.duration_minutes) : current.end,
    }))
  }

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    setErrors({})
    try {
      const result = await api<{ sessions?: TrainingSession[] }>(`/gyms/${gymId}/sessions${session ? `/${session.id}` : ''}`, {
        method: session ? 'PUT' : 'POST',
        body: {
          gym_service_id: form.gym_service_id ? Number(form.gym_service_id) : null,
          name: form.name.trim(),
          type: form.type,
          discipline: form.discipline.trim() || null,
          level: form.level,
          coach_id: Number(form.coach_id),
          room_id: Number(form.room_id),
          starts_at: `${form.date} ${form.start}:00`,
          ends_at: `${form.date} ${form.end}:00`,
          capacity: Number(form.capacity),
          allowed_plan_ids: form.allowed_plan_ids.map(Number),
          content: form.content.trim() || null,
          requirements: form.requirements.trim() || null,
          bring: form.bring.split('\n').map((line) => line.trim()).filter(Boolean),
          fee: form.fee.trim() === '' ? null : Number(form.fee),
          needs_approval: form.needs_approval === 'default' ? null : form.needs_approval === 'always',
          ...(!session && form.repeat ? { repeat: { weekdays: form.weekdays, until: form.until } } : {}),
        },
      })
      toastManager.add({
        title: result.sessions && result.sessions.length > 1 ? `${result.sessions.length} sessions created.` : 'Session saved.',
        type: 'success',
      })
      onSaved()
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
      if (!(error instanceof ApiError) || Object.keys(error.errors).length === 0) {
        toastManager.add({
          title: error instanceof ApiError ? error.message : 'The session could not be saved.',
          type: 'error',
        })
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={session ? 'Edit session' : 'New session'}
        description={session?.series_id ? 'Changes apply to this occurrence only, not the rest of the series.' : 'Choose a coach, room and time. Clashes are checked before anything is saved.'}
        actions={
          <Button variant="outline" disabled={busy} onClick={onBack}>
            <ArrowLeft aria-hidden /> Back to schedule
          </Button>
        }
      />
      <Form errors={errors} onSubmit={save} className="flex flex-col gap-6">
        {sendsNotice && <Alert variant="info"><AlertTitle>Session change</AlertTitle><AlertDescription>Booked athletes and the coach will be notified.</AlertDescription></Alert>}
        {messages.length > 0 && (
          <Alert variant="error">
            <AlertTitle>Check these fields</AlertTitle>
            <AlertDescription>
              <ul className="list-disc ps-5">{messages.map((message) => <li key={message}>{message}</li>)}</ul>
            </AlertDescription>
          </Alert>
        )}
        <fieldset disabled={busy} className="flex min-w-0 flex-col gap-6">
          <Card>
            <CardHeader>
              <CardTitle render={<h2 />}>Session details</CardTitle>
              <CardDescription>Start from a service, or enter the session directly.</CardDescription>
            </CardHeader>
            <CardPanel className="grid gap-4 sm:grid-cols-2">
              <ChoiceField
                name="gym_service_id" label="Service" value={form.gym_service_id} onChange={chooseService}
                items={[{ value: '', label: 'No linked service' }, ...options.services.map((item) => ({ value: String(item.id), label: item.name }))]}
              />
              <TextField
                name="name" label="Session name" required maxLength={120}
                value={form.name} onChange={(e) => set('name', e.target.value)}
              />
              <ChoiceField
                name="type" label="Session type" value={form.type}
                onChange={(value) => {
                  set('type', value as typeof form.type)
                  if (value === 'private_coaching') set('capacity', '1')
                }}
                items={Object.entries(options.activity_types).map(([value, label]) => ({ value, label }))}
              />
              <TextField
                name="discipline" label="Combat sport" maxLength={40}
                value={form.discipline} onChange={(e) => set('discipline', e.target.value)}
              />
              <ChoiceField
                name="coach_id" label="Coach" value={form.coach_id} required onChange={(value) => set('coach_id', value)}
                items={[{ value: '', label: 'Choose a coach' }, ...options.coaches.map((item) => ({ value: String(item.id), label: item.name }))]}
              />
              <ChoiceField
                name="room_id" label="Room" value={form.room_id} required onChange={(value) => set('room_id', value)}
                items={[{ value: '', label: 'Choose an active room' }, ...options.rooms.map((item) => ({ value: String(item.id), label: item.name }))]}
              />
              {(options.coaches.length === 0 || options.rooms.length === 0) && (
                <p className="text-sm text-muted-foreground sm:col-span-2">Add a coach in Staff and an active room in Rooms before scheduling.</p>
              )}
            </CardPanel>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle render={<h2 />}>Time and places</CardTitle>
              <CardDescription>Use the gym's local time. Sessions must start and end on the same day.</CardDescription>
            </CardHeader>
            <CardPanel className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <TextField name="starts_at" label="Date" type="date" required value={form.date} onChange={(e) => set('date', e.target.value)} />
              <TextField name="start_time" label="Start time" type="time" required value={form.start} onChange={(e) => changeStart(e.target.value)} />
              <TextField name="ends_at" label="End time" type="time" required value={form.end} onChange={(e) => set('end', e.target.value)} />
              <TextField name="capacity" label="Capacity" type="number" required min={1} max={500} step={1} value={form.capacity} onChange={(e) => set('capacity', e.target.value)} />
            </CardPanel>
          </Card>
          <Card>
            <CardHeader><CardTitle render={<h2 />}>Who may join and what to expect</CardTitle></CardHeader>
            <CardPanel className="grid gap-5 sm:grid-cols-2">
              <ChoiceField name="level" label="Level" value={form.level} onChange={(value) => set('level', value as typeof form.level)}
                items={Object.entries(options.levels).map(([value, label]) => ({ value, label }))} />
              <Field name="allowed_plan_ids" className="w-full">
                <Fieldset className="w-full"><FieldsetLegend className="font-medium text-sm">Allowed membership plans</FieldsetLegend>
                  <CheckboxGroup value={form.allowed_plan_ids} onValueChange={(value) => set('allowed_plan_ids', value)} className="mt-2 flex flex-col">
                    {options.plans.map((plan) => <label key={plan.id} className="flex min-h-11 items-center gap-2 text-sm"><Checkbox value={String(plan.id)} />{plan.name}</label>)}
                  </CheckboxGroup>
                  <FieldDescription>Leave all unchecked to allow any active member.</FieldDescription>
                </Fieldset><FieldError />
              </Field>
              <Field name="content" className="w-full">
                <FieldLabel>Training content</FieldLabel>
                <Textarea rows={5} maxLength={10000} value={form.content} onChange={(e) => set('content', e.target.value)} />
                <FieldError />
              </Field>
              <Field name="requirements" className="w-full">
                <FieldLabel>Requirements</FieldLabel>
                <Textarea rows={5} maxLength={10000} value={form.requirements} onChange={(e) => set('requirements', e.target.value)} />
                <FieldError />
              </Field>
              <Field name="bring" className="w-full">
                <FieldLabel>What to bring</FieldLabel>
                <Textarea rows={4} value={form.bring} onChange={(e) => set('bring', e.target.value)} />
                <FieldDescription>One item per line.</FieldDescription>
                <FieldError />
              </Field>
            </CardPanel>
          </Card>
          <Card>
            <CardHeader><CardTitle render={<h2 />}>Fee and booking approval</CardTitle></CardHeader>
            <CardPanel className="grid gap-4 sm:grid-cols-2">
              <TextField name="fee" label="Extra fee (₱)" type="number" min={0} max={9999999999.99} step="0.01" value={form.fee} onChange={(e) => set('fee', e.target.value)} description="Leave blank or enter 0 if included in membership." />
              <ChoiceField name="needs_approval" label="Booking approval" value={form.needs_approval} onChange={(value) => set('needs_approval', value)}
                items={[{ value: 'default', label: 'Gym default' }, { value: 'always', label: 'Always' }, { value: 'never', label: 'Never' }]} />
            </CardPanel>
          </Card>
          {!session && (
            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Repeat</CardTitle>
                <CardDescription>The first date is always included. Up to 26 weeks and 100 occurrences, saved together or not at all.</CardDescription>
              </CardHeader>
              <CardPanel className="flex flex-col gap-4">
                <label className="flex min-h-11 items-center gap-2 text-sm">
                  <Checkbox checked={form.repeat} onCheckedChange={(checked) => set('repeat', checked)} />
                  Repeat this session
                </label>
                {form.repeat && (
                  <>
                    <Field name="repeat.weekdays" className="w-full">
                      <Fieldset className="w-full">
                        <FieldsetLegend className="font-medium text-sm">Repeat on</FieldsetLegend>
                        <CheckboxGroup value={form.weekdays} onValueChange={(value) => set('weekdays', value as Day[])} className="grid grid-cols-2 gap-x-4 sm:grid-cols-4">
                          {DAYS.map(({ key, label }) => (
                            <label key={key} className="flex min-h-11 items-center gap-2 text-sm">
                              <Checkbox value={key} />{label}
                            </label>
                          ))}
                        </CheckboxGroup>
                      </Fieldset>
                      <FieldError />
                    </Field>
                    <TextField name="repeat.until" label="Repeat until" type="date" required min={form.date} value={form.until} onChange={(e) => set('until', e.target.value)} />
                  </>
                )}
              </CardPanel>
            </Card>
          )}
        </fieldset>
        <div className="flex flex-wrap justify-end gap-2 border-t pt-5">
          <Button type="button" variant="outline" disabled={busy} onClick={onBack}>Cancel</Button>
          <Button type="submit" loading={busy}>Save session</Button>
        </div>
      </Form>
    </div>
  )
}

function ChoiceField({ name, label, value, items, onChange, required = false }: {
  name: string
  label: string
  value: string
  items: { value: string; label: string }[]
  onChange: (value: string) => void
  required?: boolean
}) {
  return (
    <Field name={name} className="w-full" validate={() => required && !value ? `Choose a ${label.toLowerCase()}.` : null}>
      <FieldLabel>{label}</FieldLabel>
      <Select value={value} items={items} onValueChange={(next) => onChange(String(next ?? ''))}>
        <SelectTrigger><SelectValue /></SelectTrigger>
        <SelectPopup>
          {items.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
        </SelectPopup>
      </Select>
      <FieldError />
    </Field>
  )
}
