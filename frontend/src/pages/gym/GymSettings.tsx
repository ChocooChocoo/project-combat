import { ExternalLink } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { LocationPicker, type LatLng } from '@/components/location-picker'
import { PageHeader } from '@/components/page-header'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { CheckboxGroup } from '@/components/ui/checkbox-group'
import { Field, FieldDescription, FieldError } from '@/components/ui/field'
import { Fieldset, FieldsetLegend } from '@/components/ui/fieldset'
import { Form } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import { DAYS, type Day } from '@/lib/gyms'
import type { WorkspaceOptions, WorkspaceSetup } from '@/lib/workspace'
import { cn } from '@/lib/utils'

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
  hours: DayHours
  working_days: Day[]
  policies: string
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
    hours,
    working_days: setup.working_days ?? [],
    policies: setup.policies ?? '',
  }
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

  const canManage = can(gym, 'gym_setup', 'manage')

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
          province: form.province || null,
          latitude: form.location?.lat ?? null,
          longitude: form.location?.lng ?? null,
          contact_phone: form.contact_phone || null,
          contact_email: form.contact_email || null,
          disciplines: form.disciplines,
          activity_types: form.activity_types,
          operating_hours: Object.fromEntries(
            DAYS.filter(({ key }) => form.hours[key].open).map(({ key }) => [
              key,
              { open: form.hours[key].start, close: form.hours[key].end },
            ]),
          ),
          working_days: form.working_days,
          policies: form.policies || null,
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
                <TextField name="name" label="Gym name" value={form.name} onChange={(e) => set('name', e.target.value)} required />

                <Fieldset className="flex flex-col gap-4">
                  <FieldsetLegend className="font-medium text-sm">Address</FieldsetLegend>
                  <TextField
                    name="address_line"
                    label="Street address"
                    value={form.address_line}
                    onChange={(e) => set('address_line', e.target.value)}
                  />
                  <div className="grid gap-4 sm:grid-cols-3">
                    <TextField
                      name="barangay"
                      label="Barangay"
                      value={form.barangay}
                      onChange={(e) => set('barangay', e.target.value)}
                    />
                    <TextField
                      name="city"
                      label="City or municipality"
                      value={form.city}
                      onChange={(e) => set('city', e.target.value)}
                    />
                    <TextField
                      name="province"
                      label="Province"
                      value={form.province}
                      onChange={(e) => set('province', e.target.value)}
                    />
                  </div>
                </Fieldset>

                <Fieldset className="flex flex-col gap-3">
                  <FieldsetLegend className="font-medium text-sm">Map location</FieldsetLegend>
                  <div className={cn(!canManage && 'pointer-events-none opacity-64')}>
                    <LocationPicker value={form.location} onChange={(value) => set('location', value)} />
                  </div>
                </Fieldset>

                <div className="grid gap-4 sm:grid-cols-2">
                  <TextField
                    name="contact_phone"
                    label="Contact number"
                    type="tel"
                    autoComplete="tel"
                    value={form.contact_phone}
                    onChange={(e) => set('contact_phone', e.target.value)}
                  />
                  <TextField
                    name="contact_email"
                    label="Contact email"
                    type="email"
                    autoComplete="email"
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
                      className="grid gap-x-4 gap-y-3 sm:grid-cols-3"
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
                      className="grid gap-x-4 gap-y-3 sm:grid-cols-3"
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
              </CardPanel>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Opening hours and working days</CardTitle>
                <CardDescription>When the gym is open, and which days count as working days for leave.</CardDescription>
              </CardHeader>
              <CardPanel className="flex flex-col gap-6">
                <Fieldset className="flex flex-col gap-1">
                  <FieldsetLegend className="mb-2 font-medium text-sm">Operating hours</FieldsetLegend>
                  <div className="divide-y rounded-lg border">
                    {DAYS.map(({ key, label }) => (
                      <div key={key} className="flex min-h-12 flex-wrap items-center gap-3 px-3 py-2">
                        <label className="flex w-32 items-center gap-2 text-sm">
                          <Checkbox checked={form.hours[key].open} onCheckedChange={(open) => setDay(key, { open })} />
                          {label}
                        </label>
                        {form.hours[key].open ? (
                          <div className="flex flex-wrap items-start gap-2">
                            <Field name={`operating_hours.${key}.open`} className="w-36">
                              <Input
                                type="time"
                                aria-label={`${label} opens`}
                                value={form.hours[key].start}
                                onChange={(e) => setDay(key, { start: e.target.value })}
                              />
                              <FieldError />
                            </Field>
                            <span className="pt-1.5 text-muted-foreground text-sm">to</span>
                            <Field name={`operating_hours.${key}.close`} className="w-36">
                              <Input
                                type="time"
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
                      className="grid gap-x-4 gap-y-3 sm:grid-cols-4"
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
