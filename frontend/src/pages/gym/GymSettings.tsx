import { ExternalLink, X } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { CaviteLocationFields } from '@/components/cavite-location-fields'
import { LocationPicker, type LatLng } from '@/components/location-picker'
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
import { Input } from '@/components/ui/input'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import { DAYS, type Day } from '@/lib/gyms'
import type { WorkspaceOptions, WorkspaceSetup } from '@/lib/workspace'
import { cn } from '@/lib/utils'
import { phoneRule } from '@/lib/validation'

type DayHours = Record<Day, { open: boolean; start: string; end: string }>

type FormState = {
  name: string
  address_line: string
  barangay: string
  city: string
  province: string
  contact_phone: string
  contact_email: string
  location: LatLng | null
  disciplines: string[]
  activity_types: string[]
  custom_activity_types: string[]
  hours: DayHours
  working_days: Day[]
  policies: string
  booking_approval: boolean
  late_grace_minutes: string
  cancel_cutoff_hours: string
  session_reminder_minutes: string
  membership_reminder_days: string
  inactivity_days: string
  attendance_radius_m: string
}

function fromSetup(setup: WorkspaceSetup): FormState {
  const hours = Object.fromEntries(
    DAYS.map(({ key }) => {
      const day = setup.operating_hours?.[key]
      return [key, { open: Boolean(day), start: day?.open ?? '08:00', end: day?.close ?? '20:00' }]
    }),
  ) as DayHours

  return {
    name: setup.name,
    address_line: setup.profile.address_line ?? '',
    barangay: setup.profile.barangay ?? '',
    city: setup.profile.city ?? '',
    province: setup.profile.province ?? '',
    contact_phone: setup.profile.contact_phone ?? '',
    contact_email: setup.profile.contact_email ?? '',
    location:
      setup.profile.latitude != null && setup.profile.longitude != null
        ? { lat: setup.profile.latitude, lng: setup.profile.longitude }
        : null,
    disciplines: setup.disciplines ?? [],
    activity_types: setup.activity_types ?? [],
    custom_activity_types: setup.custom_activity_types ?? [],
    hours,
    working_days: setup.working_days ?? [],
    policies: setup.policies ?? '',
    booking_approval: setup.booking_approval,
    late_grace_minutes: String(setup.late_grace_minutes),
    cancel_cutoff_hours: String(setup.cancel_cutoff_hours),
    session_reminder_minutes: String(setup.session_reminder_minutes),
    membership_reminder_days: (setup.membership_reminder_days ?? []).join(', '),
    inactivity_days: String(setup.inactivity_days ?? 14),
    attendance_radius_m: setup.attendance_radius_m == null ? '' : String(setup.attendance_radius_m),
  }
}

/** D-24: the gym's own session types, used by services and the schedule. Saved with the rest of the form. */
function CustomTypes({ value, onChange, disabled }: { value: string[]; onChange: (value: string[]) => void; disabled: boolean }) {
  const [draft, setDraft] = useState('')
  const name = draft.trim()
  const duplicate = value.some((type) => type.toLowerCase() === name.toLowerCase())
  const add = () => {
    if (!name || duplicate || value.length >= 20) return
    onChange([...value, name])
    setDraft('')
  }
  return (
    <Field name="custom_activity_types" className="w-full">
      <FieldLabel>Extra session types</FieldLabel>
      <FieldDescription>Add types the built-in list does not cover, such as Kids class. They show up only in your services and schedule, not in public gym search.</FieldDescription>
      {value.length > 0 && (
        <ul className="flex flex-wrap gap-2">
          {value.map((type) => (
            <li key={type} className="flex items-center gap-1 rounded-md border py-1 ps-3 pe-1 text-sm">
              {type}
              <Button type="button" size="icon-sm" variant="ghost" aria-label={`Remove ${type}`} disabled={disabled}
                onClick={() => onChange(value.filter((item) => item !== type))}>
                <X aria-hidden />
              </Button>
            </li>
          ))}
        </ul>
      )}
      <div className="flex w-full gap-2">
        <Input
          aria-label="New session type"
          placeholder="Type name"
          maxLength={40}
          value={draft}
          onChange={(event) => setDraft(event.target.value)}
          onKeyDown={(event) => { if (event.key === 'Enter') { event.preventDefault(); add() } }}
        />
        <Button type="button" variant="outline" disabled={disabled || !name || duplicate || value.length >= 20} onClick={add}>Add type</Button>
      </div>
      {duplicate && <p className="text-muted-foreground text-sm">That type is already in the list.</p>}
      <FieldError />
    </Field>
  )
}

/** R-20, S-16: everything the owner set about the gym after approval, in one place. */
export default function GymSettings() {
  const { gymId } = useParams()
  const { user } = useAuth()
  const gym = approvedGym(user, Number(gymId))
  const [setup, setSetup] = useState<WorkspaceSetup | null>(null)
  const [options, setOptions] = useState<WorkspaceOptions | null>(null)
  const [form, setForm] = useState<FormState | null>(null)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [loadError, setLoadError] = useState('')
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [version, setVersion] = useState(0)

  useEffect(() => {
    if (!gymId) return
    let live = true
    setLoading(true)
    setLoadError('')
    Promise.all([
      api<{ setup: WorkspaceSetup }>(`/gyms/${gymId}/setup`),
      api<{ options: WorkspaceOptions }>(`/gyms/${gymId}/options`),
    ])
      .then(([setupResult, optionsResult]) => {
        if (!live) return
        setSetup(setupResult.setup)
        setOptions(optionsResult.options)
        setForm(fromSetup(setupResult.setup))
      })
      .catch((error) => {
        if (live) setLoadError(error instanceof ApiError ? error.message : 'The gym settings could not be loaded.')
      })
      .finally(() => {
        if (live) setLoading(false)
      })
    return () => {
      live = false
    }
  }, [gymId, version])

  if (!gym) return null

  const canManage = can(gym, 'gym_setup.settings.manage')

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((current) => (current ? { ...current, [key]: value } : current))

  const setDay = (day: Day, change: Partial<DayHours[Day]>) =>
    setForm((current) =>
      current ? { ...current, hours: { ...current.hours, [day]: { ...current.hours[day], ...change } } } : current,
    )

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!gymId || !form) return
    setBusy(true)
    try {
      const result = await api<{ setup: WorkspaceSetup }>(`/gyms/${gymId}/setup`, {
        method: 'PUT',
        body: {
          name: form.name,
          address_line: form.address_line || null,
          barangay: form.barangay || null,
          city: form.city || null,
          province: 'Cavite',
          latitude: form.location?.lat ?? null,
          longitude: form.location?.lng ?? null,
          contact_phone: form.contact_phone || null,
          contact_email: form.contact_email || null,
          disciplines: form.disciplines,
          activity_types: form.activity_types,
          custom_activity_types: form.custom_activity_types,
          operating_hours: Object.fromEntries(
            DAYS.filter(({ key }) => form.hours[key].open).map(({ key }) => [
              key,
              { open: form.hours[key].start, close: form.hours[key].end },
            ]),
          ),
          working_days: form.working_days,
          policies: form.policies || null,
          booking_approval: form.booking_approval,
          late_grace_minutes: Number(form.late_grace_minutes),
          cancel_cutoff_hours: Number(form.cancel_cutoff_hours),
          session_reminder_minutes: Number(form.session_reminder_minutes),
          // R-115: comma list of days, e.g. "7, 3, 1"; empty uses the default.
          membership_reminder_days: form.membership_reminder_days.split(',').map((day) => day.trim()).filter(Boolean).map(Number),
          inactivity_days: Number(form.inactivity_days),
          attendance_radius_m: form.attendance_radius_m.trim() === '' ? null : Number(form.attendance_radius_m),
        },
      })
      setSetup(result.setup)
      setForm(fromSetup(result.setup))
      setErrors({})
      toastManager.add({ title: 'Gym settings saved.', type: 'success' })
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
      if (!(error instanceof ApiError) || Object.keys(error.errors).length === 0) {
        toastManager.add({
          title: error instanceof ApiError ? error.message : 'The gym settings could not be saved.',
          type: 'error',
        })
      }
    } finally {
      setBusy(false)
    }
  }

  const messages = [...new Set(Object.values(errors).flat())]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Gym setup"
        description="How the gym appears to members: its address, contacts, sports, hours and policies."
        actions={
          <Button variant="outline" render={<Link to={`/gyms/${gymId}`} />}>
            <ExternalLink aria-hidden /> View public page
          </Button>
        }
      />

      {!canManage && (
        <Alert variant="info">
          <AlertTitle>Read only</AlertTitle>
          <AlertDescription>You can see these settings, but only someone with gym setup management can change them.</AlertDescription>
        </Alert>
      )}

      {loadError ? (
        <Alert variant="error">
          <AlertTitle>Gym settings could not be loaded</AlertTitle>
          <AlertDescription>
            <p>{loadError}</p>
            <Button size="sm" variant="outline" onClick={() => setVersion((value) => value + 1)}>
              Try again
            </Button>
          </AlertDescription>
        </Alert>
      ) : loading || !setup || !options || !form ? (
        <div className="flex flex-col gap-6" aria-busy="true">
          <Skeleton className="h-52" />
          <Skeleton className="h-40" />
          <Skeleton className="h-64" />
        </div>
      ) : (
        <Form errors={errors} onSubmit={save} className="flex flex-col gap-6">
          {messages.length > 0 && (
            <Alert variant="error">
              <AlertTitle>Form errors</AlertTitle>
              <AlertDescription>
                <ul className="list-disc ps-5">
                  {messages.map((message) => (
                    <li key={message}>{message}</li>
                  ))}
                </ul>
              </AlertDescription>
            </Alert>
          )}

          <fieldset className="flex min-w-0 flex-col gap-6" disabled={!canManage}>
            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Gym details</CardTitle>
                <CardDescription>The name, address and contacts members see.</CardDescription>
              </CardHeader>
              <CardPanel className="flex flex-col gap-6">
                <TextField name="name" label="Gym name" maxLength={120} value={form.name} onChange={(e) => set('name', e.target.value)} required />

                <Fieldset className="flex flex-col gap-4">
                  <FieldsetLegend className="font-medium text-sm">Address</FieldsetLegend>
                  <TextField
                    name="address_line"
                    label="Street address"
                    maxLength={255}
                    value={form.address_line}
                    onChange={(e) => set('address_line', e.target.value)}
                  />
                  <CaviteLocationFields
                    city={form.city}
                    barangay={form.barangay}
                    onChange={(place) => setForm((f) => (f ? { ...f, ...place } : f))}
                  />
                </Fieldset>

                <Fieldset className="flex flex-col gap-3">
                  <FieldsetLegend className="font-medium text-sm">Map location</FieldsetLegend>
                  <div className={cn(!canManage && 'pointer-events-none opacity-64')}>
                    <LocationPicker value={form.location} onChange={(value) => set('location', value)} />
                  </div>
                </Fieldset>

                {/* R-122: how close staff must be to clock in. */}
                <TextField name="attendance_radius_m" label="Staff clock-in distance (metres)" type="number" min={50} max={5000} step={1}
                  disabled={!canManage}
                  value={form.attendance_radius_m} onChange={(e) => set('attendance_radius_m', e.target.value)}
                  description={form.attendance_radius_m.trim() !== '' && !form.location
                    ? 'Set the map pin above, or the check is skipped.'
                    : 'Leave empty to let staff clock in from anywhere.'} />

                <div className="grid gap-4 sm:grid-cols-2">
                  <TextField
                    name="contact_phone"
                    label="Contact number"
                    type="tel"
                    autoComplete="tel"
                    maxLength={30}
                    validate={phoneRule}
                    value={form.contact_phone}
                    onChange={(e) => set('contact_phone', e.target.value)}
                  />
                  <TextField
                    name="contact_email"
                    label="Contact email"
                    type="email"
                    autoComplete="email"
                    maxLength={255}
                    value={form.contact_email}
                    onChange={(e) => set('contact_email', e.target.value)}
                  />
                </div>
              </CardPanel>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>What the gym offers</CardTitle>
                <CardDescription>Combat sports and activity types used across the workspace.</CardDescription>
              </CardHeader>
              <CardPanel className="flex flex-col gap-6">
                <Field name="disciplines" className="w-full">
                  <Fieldset className="flex w-full flex-col gap-1">
                    <FieldsetLegend className="mb-3 font-medium text-sm">Combat sports</FieldsetLegend>
                    <CheckboxGroup
                      value={form.disciplines}
                      onValueChange={(value) => set('disciplines', value)}
                      className="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3"
                    >
                      {Object.entries(options.disciplines).map(([key, label]) => (
                        <label key={key} className="flex items-center gap-2 text-sm">
                          <Checkbox value={key} />
                          {label}
                        </label>
                      ))}
                    </CheckboxGroup>
                  </Fieldset>
                  <FieldError />
                </Field>

                <Field name="activity_types" className="w-full">
                  <Fieldset className="flex w-full flex-col gap-1">
                    <FieldsetLegend className="mb-3 font-medium text-sm">Activity types</FieldsetLegend>
                    <CheckboxGroup
                      value={form.activity_types}
                      onValueChange={(value) => set('activity_types', value)}
                      className="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3"
                    >
                      {Object.entries(options.activity_types).map(([key, label]) => (
                        <label key={key} className="flex items-center gap-2 text-sm">
                          <Checkbox value={key} />
                          {label}
                        </label>
                      ))}
                    </CheckboxGroup>
                  </Fieldset>
                  <FieldError />
                </Field>

                <CustomTypes
                  value={form.custom_activity_types}
                  onChange={(value) => set('custom_activity_types', value)}
                  disabled={!canManage}
                />
              </CardPanel>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Opening hours and working days</CardTitle>
                <CardDescription>When the gym is open, and which days count as working days for leave.</CardDescription>
              </CardHeader>
              <CardPanel className="flex flex-col gap-6">
                <Fieldset className="flex min-w-0 flex-col gap-1">
                  <FieldsetLegend className="mb-2 font-medium text-sm">Operating hours</FieldsetLegend>
                  <div className="divide-y rounded-lg border">
                    {DAYS.map(({ key, label }) => (
                      <div key={key} className="flex min-h-12 flex-wrap items-center gap-3 px-3 py-2">
                        <label className="flex w-32 items-center gap-2 text-sm">
                          <Checkbox checked={form.hours[key].open} onCheckedChange={(open) => setDay(key, { open })} />
                          {label}
                        </label>
                        {form.hours[key].open ? (
                          <div className="grid w-full grid-cols-[auto_1fr] items-start gap-x-3 gap-y-2 sm:flex sm:w-auto sm:gap-2">
                            <span className="pt-2 text-muted-foreground text-sm sm:hidden">Opens</span>
                            <Field name={`operating_hours.${key}.open`} className="min-w-0 sm:w-36">
                              <Input
                                type="time"
                                required
                                aria-label={`${label} opens`}
                                value={form.hours[key].start}
                                onChange={(e) => setDay(key, { start: e.target.value })}
                              />
                              <FieldError />
                            </Field>
                            <span className="pt-2 text-muted-foreground text-sm sm:pt-1.5">
                              <span className="sm:hidden">Closes</span>
                              <span className="max-sm:hidden">to</span>
                            </span>
                            <Field
                              name={`operating_hours.${key}.close`}
                              className="min-w-0 sm:w-36"
                              validate={(value) => (value && String(value) !== '00:00' && String(value) <= form.hours[key].start ? 'Must be after opening time. Use 12:00 AM for midnight.' : null)}
                            >
                              <Input
                                type="time"
                                required
                                aria-label={`${label} closes`}
                                value={form.hours[key].end}
                                onChange={(e) => setDay(key, { end: e.target.value })}
                              />
                              <FieldError />
                            </Field>
                          </div>
                        ) : (
                          <span className="text-muted-foreground text-sm">Closed</span>
                        )}
                      </div>
                    ))}
                  </div>
                </Fieldset>

                <Field name="working_days" className="w-full">
                  <Fieldset className="flex w-full flex-col gap-1">
                    <FieldsetLegend className="mb-3 font-medium text-sm">Working days</FieldsetLegend>
                    <CheckboxGroup
                      value={form.working_days}
                      onValueChange={(value) => set('working_days', value as Day[])}
                      className="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-4"
                    >
                      {DAYS.map(({ key, label }) => (
                        <label key={key} className="flex items-center gap-2 text-sm">
                          <Checkbox value={key} />
                          {label}
                        </label>
                      ))}
                    </CheckboxGroup>
                    <FieldDescription className="mt-2">
                      Leave is counted in working days. An employee's own schedule overrides this when it has hours.
                    </FieldDescription>
                  </Fieldset>
                  <FieldError />
                </Field>
              </CardPanel>
            </Card>

            {/* R-42: gym-wide booking defaults; sessions may override approval. */}
            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Booking and attendance</CardTitle>
                <CardDescription>Default rules for sessions across this gym.</CardDescription>
              </CardHeader>
              <CardPanel className="grid gap-5 sm:grid-cols-2">
                <Field name="booking_approval" className="w-full sm:col-span-2">
                  <div className="flex w-full items-center justify-between gap-4 rounded-lg border px-3 py-3">
                    <div className="flex flex-col gap-2">
                      <FieldLabel>Bookings need approval</FieldLabel>
                      <FieldDescription>Staff must confirm bookings unless a session sets its own approval rule.</FieldDescription>
                    </div>
                    <Switch checked={form.booking_approval} onCheckedChange={(checked) => set('booking_approval', checked)} />
                  </div>
                  <FieldError />
                </Field>
                <TextField name="late_grace_minutes" label="Late arrival grace (minutes)" type="number" required min={0} max={120} step={1}
                  value={form.late_grace_minutes} onChange={(e) => set('late_grace_minutes', e.target.value)}
                  description="A member is late after the start time plus this many minutes. Default: 10." />
                <TextField name="cancel_cutoff_hours" label="Cancellation cut-off (hours)" type="number" required min={0} max={72} step={1}
                  value={form.cancel_cutoff_hours} onChange={(e) => set('cancel_cutoff_hours', e.target.value)}
                  description="Members can cancel until this many hours before a session. After that, only staff can cancel. Default: 2." />
                <Field name="session_reminder_minutes" className="w-full sm:col-span-2">
                  <FieldLabel>Session reminder</FieldLabel>
                  <Select value={form.session_reminder_minutes} onValueChange={(value) => value && set('session_reminder_minutes', value)}
                    items={[30, 60, 120, 1440].map((minutes) => ({ value: String(minutes), label: `${minutes} minutes` }))}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectPopup>
                      {[30, 60, 120, 1440].map((minutes) => <SelectItem key={minutes} value={String(minutes)}>{minutes} minutes</SelectItem>)}
                    </SelectPopup>
                  </Select>
                  <FieldDescription>Booked athletes get an in-app reminder this long before the session starts.</FieldDescription>
                  <FieldError />
                </Field>
                <TextField name="membership_reminder_days" label="Membership expiry reminders (days before)"
                  value={form.membership_reminder_days} onChange={(e) => set('membership_reminder_days', e.target.value)} inputMode="numeric"
                  description="Comma list, up to 5 days between 1 and 60. Default: 7, 3, 1." />
                <TextField name="inactivity_days" label="Inactive member after (days)" type="number" required min={3} max={180} step={1}
                  value={form.inactivity_days} onChange={(e) => set('inactivity_days', e.target.value)}
                  description="With no visit or attended session for this long, the member and staff get a notice. Default: 14." />
              </CardPanel>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Policies</CardTitle>
                <CardDescription>House rules members should know before they join.</CardDescription>
              </CardHeader>
              <CardPanel>
                <Field name="policies" className="w-full">
                  <Textarea
                    name="policies"
                    rows={6}
                    maxLength={5000}
                    value={form.policies}
                    onChange={(e) => set('policies', e.target.value)}
                    placeholder="For example: Bring your own wraps. Cancel a session at least 12 hours ahead."
                  />
                  <FieldDescription>Shown on the gym's public page when it is set.</FieldDescription>
                  <FieldError />
                </Field>
              </CardPanel>
            </Card>
          </fieldset>

          {canManage && (
            <div className="flex justify-end">
              <Button type="submit" loading={busy}>
                Save changes
              </Button>
            </div>
          )}
        </Form>
      )}
    </div>
  )
}
