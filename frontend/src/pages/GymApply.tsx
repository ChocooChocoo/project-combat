import { ArrowLeft, Check, Upload } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router'
import { DocumentSummary, FileTile, ProfileSummary, StatusBadge } from '@/components/application-summary'
import { CaviteLocationFields } from '@/components/cavite-location-fields'
import { LocationPicker } from '@/components/location-picker'
import { PageHeader } from '@/components/page-header'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button, buttonVariants } from '@/components/ui/button'
import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { CheckboxGroup } from '@/components/ui/checkbox-group'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Fieldset, FieldsetLegend } from '@/components/ui/fieldset'
import { Form } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Radio, RadioGroup } from '@/components/ui/radio-group'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import {
  type Application,
  DAYS,
  type Day,
  documentsMissing,
  documentUrl,
  editable,
  type Requirements,
  typesFor,
  useRequirements,
} from '@/lib/gyms'
import { fileProblem, phoneRule } from '@/lib/validation'

type Hours = Record<Day, { on: boolean; open: string; close: string }>

type FormState = {
  name: string
  tier: number | null
  address_line: string
  barangay: string
  city: string
  province: string
  location: { lat: number; lng: number } | null
  contact_phone: string
  contact_email: string
  hours: Hours
  disciplines: string[]
  services: string
  facilities: string
}

type StepKey = 'details' | 'offer' | 'tier' | 'documents' | 'review'

const STEPS: { key: StepKey; title: string; hint: string }[] = [
  { key: 'details', title: 'Gym details', hint: 'Name, address, map and contacts' },
  { key: 'offer', title: 'Hours and offer', hint: 'Opening hours, sports, services' },
  { key: 'tier', title: 'Verification tier', hint: 'What the gym will prove' },
  { key: 'documents', title: 'Documents', hint: "The tier's required files" },
  { key: 'review', title: 'Review and send', hint: 'Check everything, then send' },
]

const STEP_DESCRIPTIONS: Record<StepKey, string> = {
  details: 'How members and the administrator will find and reach the gym.',
  offer: 'When the gym is open and what it offers.',
  tier: 'Tier 1 proves the business is registered. Tier 2 adds proof of the facility and the owner, and earns a stronger badge.',
  documents: '',
  review: '',
}

const lines = (text: string) => text.split('\n').map((line) => line.trim()).filter(Boolean)

function fromApplication(app: Application | null): FormState {
  const hours = Object.fromEntries(
    DAYS.map(({ key }) => {
      const day = app?.operating_hours?.[key]
      return [key, { on: Boolean(day), open: day?.open ?? '08:00', close: day?.close ?? '20:00' }]
    }),
  ) as Hours

  return {
    name: app?.name ?? '',
    tier: app?.tier ?? null,
    address_line: app?.address_line ?? '',
    barangay: app?.barangay ?? '',
    city: app?.city ?? '',
    province: app?.province ?? '',
    location: app?.latitude != null && app.longitude != null ? { lat: app.latitude, lng: app.longitude } : null,
    contact_phone: app?.contact_phone ?? '',
    contact_email: app?.contact_email ?? '',
    hours,
    disciplines: app?.disciplines ?? [],
    services: (app?.services ?? []).join('\n'),
    facilities: (app?.facilities ?? []).join('\n'),
  }
}

function toBody(form: FormState) {
  return {
    name: form.name,
    tier: form.tier,
    address_line: form.address_line || null,
    barangay: form.barangay || null,
    city: form.city || null,
    province: 'Cavite',
    latitude: form.location?.lat ?? null,
    longitude: form.location?.lng ?? null,
    contact_phone: form.contact_phone || null,
    contact_email: form.contact_email || null,
    operating_hours: Object.fromEntries(
      DAYS.filter(({ key }) => form.hours[key].on).map(({ key }) => [key, { open: form.hours[key].open, close: form.hours[key].close }]),
    ),
    disciplines: form.disciplines,
    services: lines(form.services),
    facilities: lines(form.facilities),
  }
}

/** What each step still needs, so the step list can show progress. The server check stays the authority. */
function missingFor(step: StepKey, form: FormState, app: Application | null, requirements: Requirements): string[] {
  const need = (ok: unknown, message: string) => (ok ? [] : [message])
  switch (step) {
    case 'details':
      return [
        ...need(form.name.trim(), 'Add the gym name.'),
        ...need(form.address_line.trim() && form.barangay.trim() && form.city.trim(), 'Complete the address.'),
        ...need(form.location, 'Place the gym on the map.'),
        ...need(form.contact_phone.trim() && form.contact_email.trim(), 'Add a contact number and email.'),
      ]
    case 'offer':
      return [
        ...need(DAYS.some(({ key }) => form.hours[key].on), 'Set the days the gym is open.'),
        ...need(form.disciplines.length, 'Choose at least one combat sport.'),
        ...need(lines(form.services).length, 'List at least one service.'),
        ...need(lines(form.facilities).length, 'List at least one facility.'),
      ]
    case 'tier':
      return need(form.tier, 'Choose a tier.')
    case 'documents':
      return app ? documentsMissing(app, requirements) : ['Save the application first.']
    default:
      return []
  }
}

/** Backend: at most 30 lines of up to 100 characters each. */
function listProblem(text: string): string | null {
  const items = lines(text)
  if (items.length > 30) return 'List at most 30 items.'
  if (items.some((item) => item.length > 100)) return 'Keep each line under 100 characters.'
  return null
}

/** S-11, R-11 to R-13: the gym application as five steps, saved as a draft along the way. */
export default function GymApply() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [params, setParams] = useSearchParams()
  const requirements = useRequirements()
  const [app, setApp] = useState<Application | null>(null)
  const [form, setForm] = useState<FormState>(() => fromApplication(null))
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState('')

  const step = (STEPS.find((s) => s.key === params.get('step'))?.key ?? 'details') as StepKey
  const stepIndex = STEPS.findIndex((s) => s.key === step)

  useEffect(() => {
    if (!id) return
    // Only the latest load may fill the form; a late, stale answer would wipe what the person has typed since.
    let live = true
    api<{ application: Application }>(`/gym-applications/${id}`)
      .then(({ application }) => {
        if (!live) return
        if (!editable(application.status)) {
          navigate(`/applications/${application.id}`, { replace: true })
          return
        }
        setApp(application)
        setForm(fromApplication(application))
      })
      .catch(() => live && navigate('/applications', { replace: true }))
    return () => {
      live = false
    }
  }, [id, navigate])

  if (!requirements || (id && !app)) {
    return (
      <div className="flex flex-col gap-6" aria-busy="true">
        <Skeleton className="h-9 w-72" />
        <div className="grid gap-8 lg:grid-cols-[15rem_1fr]">
          <Skeleton className="h-64" />
          <Skeleton className="h-96" />
        </div>
      </div>
    )
  }

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) => setForm((current) => ({ ...current, [key]: value }))
  const setDay = (day: Day, change: Partial<Hours[Day]>) =>
    setForm((current) => ({ ...current, hours: { ...current.hours, [day]: { ...current.hours[day], ...change } } }))

  const goTo = (key: StepKey, savedId?: number) => {
    setErrors({})
    if (savedId && !id) navigate(`/applications/${savedId}/edit?step=${key}`, { replace: true })
    else setParams({ step: key })
    window.scrollTo({ top: 0 })
  }

  const reachable = (key: StepKey) => (key === 'documents' || key === 'review' ? Boolean(app?.tier) : true)
  const missingAll = STEPS.slice(0, 4).map((s) => ({ step: s, missing: missingFor(s.key, form, app, requirements) }))

  /** Saves the draft. Returns the saved application, or null when the server refused it. */
  async function save(): Promise<Application | null> {
    try {
      const result = app
        ? await api<{ application: Application }>(`/gym-applications/${app.id}`, { method: 'PUT', body: toBody(form) })
        : await api<{ application: Application }>('/gym-applications', { body: toBody(form) })
      setApp(result.application)
      setErrors({})
      return result.application
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
      if (!(error instanceof ApiError) || Object.keys(error.errors).length === 0) {
        toastManager.add({ title: error instanceof ApiError ? error.message : 'Could not save.', type: 'error' })
      }
      return null
    }
  }

  async function saveAndContinue(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    // Native rules already passed (Base UI blocks submit otherwise); these are the parts without an input.
    const missing = requirements ? missingFor(step, form, app, requirements) : []
    if (missing.length > 0) {
      setErrors({ step: missing })
      window.scrollTo({ top: 0 })
      return
    }
    setBusy('next')
    const saved = await save()
    setBusy('')
    if (saved) goTo(STEPS[stepIndex + 1].key, saved.id)
  }

  async function saveDraft() {
    setBusy('save')
    const saved = await save()
    setBusy('')
    if (!saved) return
    toastManager.add({ title: 'Draft saved.', type: 'success' })
    if (!id) navigate(`/applications/${saved.id}/edit?step=${step}`, { replace: true })
  }

  async function send() {
    if (!app) return
    setBusy('send')
    try {
      await api(`/gym-applications/${app.id}/submit`, { method: 'POST' })
      toastManager.add({ title: 'Application sent. You will get a notice when it is decided.', type: 'success' })
      navigate(`/applications/${app.id}`)
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
    } finally {
      setBusy('')
    }
  }

  const messages = [...new Set(Object.values(errors).flat())]
  const current = STEPS[stepIndex]

  return (
    <div className="flex flex-col gap-8">
      <PageHeader
        title={app?.name || 'Apply to run a gym'}
        meta={app && <StatusBadge status={app.status} />}
        description="Your progress is saved as a draft at each step. You become the gym's owner only after the platform administrator approves it."
      />

      <div className="grid gap-8 lg:grid-cols-[15rem_1fr]">
        <nav aria-label="Application steps" className="min-w-0 lg:sticky lg:top-6 lg:self-start">
          <p className="mb-3 text-muted-foreground text-sm lg:hidden">
            Step {stepIndex + 1} of {STEPS.length}
          </p>
          <ol className="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1 lg:flex-col lg:overflow-visible">
            {STEPS.map((s, index) => {
              const done = s.key !== 'review' && missingFor(s.key, form, app, requirements).length === 0
              const isCurrent = s.key === step
              return (
                <li key={s.key} className="shrink-0">
                  <button
                    type="button"
                    disabled={!reachable(s.key)}
                    aria-current={isCurrent ? 'step' : undefined}
                    onClick={() => goTo(s.key)}
                    className={`flex w-full items-start gap-3 rounded-lg px-3 py-2 text-left outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 ${isCurrent ? 'bg-accent text-accent-foreground' : 'hover:bg-accent/50'}`}
                  >
                    <span
                      className={`mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border font-medium text-xs tabular-nums ${done ? 'border-primary bg-primary text-primary-foreground' : isCurrent ? 'border-foreground' : 'text-muted-foreground'}`}
                    >
                      {done ? <Check className="size-3.5" aria-label="Complete" /> : index + 1}
                    </span>
                    <span className="grid">
                      <span className="whitespace-nowrap font-medium text-sm">{s.title}</span>
                      <span className="hidden text-muted-foreground text-xs lg:block">{s.hint}</span>
                    </span>
                  </button>
                </li>
              )
            })}
          </ol>
        </nav>

        <div className="flex min-w-0 flex-col gap-6">
          {app?.status === 'returned' && app.return_items && (
            <Alert variant="warning">
              <AlertTitle>Returned for correction</AlertTitle>
              <AlertDescription>
                <ul className="list-disc ps-5">{app.return_items.map((item) => <li key={item}>{item}</li>)}</ul>
                {app.decision_reason && <p>{app.decision_reason}</p>}
              </AlertDescription>
            </Alert>
          )}

          {messages.length > 0 && (
            <Alert variant="error">
              <AlertTitle>{step === 'review' ? 'The application cannot be sent yet' : 'Some details need fixing'}</AlertTitle>
              <AlertDescription>
                <ul className="list-disc ps-5">{messages.map((message) => <li key={message}>{message}</li>)}</ul>
              </AlertDescription>
            </Alert>
          )}

          {step === 'documents' && app ? (
            <>
              <Documents app={app} requirements={requirements} onChange={setApp} />
              <StepFooter onBack={() => goTo('tier')} primary={<Button onClick={() => goTo('review')}>Continue to review</Button>} />
            </>
          ) : step === 'review' && app ? (
            <Review
              app={app}
              requirements={requirements}
              missing={missingAll}
              onGoTo={goTo}
              onBack={() => goTo('documents')}
              onSend={send}
              sending={busy === 'send'}
            />
          ) : (
            <Form errors={errors} onSubmit={saveAndContinue} className="flex flex-col gap-6">
              <Card>
                <CardHeader>
                  <CardTitle render={<h2 />}>{current.title}</CardTitle>
                  <CardDescription>{STEP_DESCRIPTIONS[step]}</CardDescription>
                </CardHeader>
                <CardPanel className="flex flex-col gap-6">
                  {step === 'details' && (
                    <>
                      <TextField name="name" label="Gym name" maxLength={120} value={form.name} onChange={(e) => set('name', e.target.value)} required />
                      <Fieldset className="flex flex-col gap-4">
                        <FieldsetLegend className="font-medium text-sm">Address</FieldsetLegend>
                        <TextField name="address_line" label="Street address" maxLength={255} required value={form.address_line} onChange={(e) => set('address_line', e.target.value)} />
                        <CaviteLocationFields city={form.city} barangay={form.barangay} onChange={(place) => setForm((f) => ({ ...f, ...place }))} />
                      </Fieldset>
                      <Fieldset className="flex flex-col gap-3">
                        <FieldsetLegend className="font-medium text-sm">Map location</FieldsetLegend>
                        <LocationPicker value={form.location} onChange={(value) => set('location', value)} />
                      </Fieldset>
                      <div className="grid gap-4 sm:grid-cols-2">
                        <TextField name="contact_phone" label="Contact number" type="tel" autoComplete="tel" maxLength={30} validate={phoneRule} required value={form.contact_phone} onChange={(e) => set('contact_phone', e.target.value)} />
                        <TextField name="contact_email" label="Contact email" type="email" autoComplete="email" maxLength={255} required value={form.contact_email} onChange={(e) => set('contact_email', e.target.value)} />
                      </div>
                    </>
                  )}

                  {step === 'offer' && (
                    <>
                      <Fieldset className="flex min-w-0 flex-col gap-1">
                        <FieldsetLegend className="mb-2 font-medium text-sm">Operating hours</FieldsetLegend>
                        <div className="divide-y rounded-lg border">
                          {DAYS.map(({ key, label }) => (
                            <div key={key} className="flex min-h-12 flex-wrap items-center gap-3 px-3 py-2">
                              <label className="flex w-32 items-center gap-2 text-sm">
                                <Checkbox checked={form.hours[key].on} onCheckedChange={(on) => setDay(key, { on })} />
                                {label}
                              </label>
                              {form.hours[key].on ? (
                                <div className="grid w-full grid-cols-[auto_1fr] items-start gap-x-3 gap-y-2 sm:flex sm:w-auto sm:gap-2">
                                  <span className="pt-2 text-muted-foreground text-sm sm:hidden">Opens</span>
                                  <Field name={`operating_hours.${key}.open`} className="min-w-0 sm:w-36">
                                    <Input type="time" required aria-label={`${label} opens`} value={form.hours[key].open} onChange={(e) => setDay(key, { open: e.target.value })} />
                                    <FieldError />
                                  </Field>
                                  <span className="pt-2 text-muted-foreground text-sm sm:pt-1.5">
                                    <span className="sm:hidden">Closes</span>
                                    <span className="max-sm:hidden">to</span>
                                  </span>
                                  <Field
                                    name={`operating_hours.${key}.close`}
                                    className="min-w-0 sm:w-36"
                                    validate={(value) => (value && String(value) <= form.hours[key].open ? 'Must be after opening time.' : null)}
                                  >
                                    <Input type="time" required aria-label={`${label} closes`} value={form.hours[key].close} onChange={(e) => setDay(key, { close: e.target.value })} />
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
                      <Fieldset>
                        <FieldsetLegend className="mb-3 font-medium text-sm">Combat sports offered</FieldsetLegend>
                        <CheckboxGroup value={form.disciplines} onValueChange={(value) => set('disciplines', value)} className="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3">
                          {Object.entries(requirements.disciplines).map(([key, label]) => (
                            <label key={key} className="flex items-center gap-2 text-sm">
                              <Checkbox value={key} />
                              {label}
                            </label>
                          ))}
                        </CheckboxGroup>
                      </Fieldset>
                      <div className="grid gap-4 sm:grid-cols-2">
                        <Field name="services" validate={() => listProblem(form.services)}>
                          <FieldLabel>Services</FieldLabel>
                          <Textarea value={form.services} onChange={(e) => set('services', e.target.value)} rows={4} />
                          <FieldDescription>One per line, for example "Private coaching".</FieldDescription>
                          <FieldError />
                        </Field>
                        <Field name="facilities" validate={() => listProblem(form.facilities)}>
                          <FieldLabel>Facilities</FieldLabel>
                          <Textarea value={form.facilities} onChange={(e) => set('facilities', e.target.value)} rows={4} />
                          <FieldDescription>One per line, for example "Boxing ring".</FieldDescription>
                          <FieldError />
                        </Field>
                      </div>
                    </>
                  )}

                  {step === 'tier' && (
                    <RadioGroup value={form.tier ? String(form.tier) : ''} onValueChange={(value) => set('tier', Number(value))} className="grid gap-3 md:grid-cols-2">
                      {Object.entries(requirements.tiers).map(([tier, info]) => (
                        <label
                          key={tier}
                          className="flex cursor-pointer gap-3 rounded-xl border p-4 transition-colors hover:bg-accent/40 has-data-checked:border-primary has-data-checked:bg-accent/40"
                        >
                          <Radio value={tier} aria-label={info.label} className="mt-0.5" />
                          <span className="flex flex-col gap-3">
                            <span className="flex flex-wrap items-center gap-2">
                              <span className="font-semibold">{info.label}</span>
                              <Badge variant="outline">{info.badge} badge</Badge>
                            </span>
                            <ul className="flex flex-col gap-1.5 text-muted-foreground text-sm">
                              {info.requirements.map((req) => (
                                <li key={req.types.join()} className="flex gap-2">
                                  <Check className="mt-0.5 size-4 shrink-0" aria-hidden />
                                  <span>
                                    {req.types.map((t) => requirements.documents[t].label).join(' or ')}
                                    {req.min > 1 && ` (at least ${req.min})`}
                                  </span>
                                </li>
                              ))}
                            </ul>
                          </span>
                        </label>
                      ))}
                    </RadioGroup>
                  )}
                </CardPanel>
              </Card>
              <StepFooter
                onBack={stepIndex > 0 ? () => goTo(STEPS[stepIndex - 1].key) : undefined}
                secondary={<Button type="button" variant="ghost" loading={busy === 'save'} onClick={saveDraft}>Save draft</Button>}
                primary={<Button type="submit" loading={busy === 'next'}>Save and continue</Button>}
              />
            </Form>
          )}
        </div>
      </div>
    </div>
  )
}

function StepFooter({ onBack, secondary, primary }: { onBack?: () => void; secondary?: React.ReactNode; primary: React.ReactNode }) {
  return (
    <div className="flex flex-wrap items-center gap-2 border-t pt-4">
      {onBack && (
        <Button type="button" variant="outline" onClick={onBack}>
          <ArrowLeft aria-hidden /> Back
        </Button>
      )}
      <div className="ms-auto flex flex-wrap items-center gap-2">
        {secondary}
        {primary}
      </div>
    </div>
  )
}

/** R-12, R-13: the chosen tier's exact documents, with the upload rules. */
function Documents({ app, requirements, onChange }: { app: Application; requirements: Requirements; onChange: (app: Application) => void }) {
  const [uploadErrors, setUploadErrors] = useState<Record<string, string>>({})
  const [uploading, setUploading] = useState('')

  const reload = async () => onChange((await api<{ application: Application }>(`/gym-applications/${app.id}`)).application)

  async function upload(type: string, group: string[], files: FileList | null) {
    if (!files?.length) return
    setUploading(type)
    const failed: string[] = []
    const maxKb = requirements.kinds[requirements.documents[type].kind ?? 'document'].max_kb
    for (const file of Array.from(files)) {
      const problem = fileProblem(file, accept(type), maxKb)
      if (problem) {
        failed.push(`${file.name}: ${problem}`)
        continue
      }
      const body = new FormData()
      body.append('type', type)
      body.append('file', file)
      try {
        await api(`/gym-applications/${app.id}/documents`, { body })
      } catch (error) {
        const message = error instanceof ApiError ? (error.errors.file?.[0] ?? error.errors.type?.[0] ?? error.message) : 'Upload failed.'
        failed.push(`${file.name}: ${message}`)
      }
    }
    // A new upload replaces older messages for the whole group, so "DTI or SEC" shows one current state.
    setUploadErrors((current) => ({ ...current, ...Object.fromEntries(group.map((t) => [t, ''])), [type]: failed.join(' ') }))
    setUploading('')
    await reload()
  }

  async function remove(documentId: number) {
    try {
      await api(documentUrl(app.id, documentId), { method: 'DELETE' })
      await reload()
      toastManager.add({ title: 'File deleted.', type: 'success' })
    } catch (error) {
      toastManager.add({ title: error instanceof ApiError ? error.message : 'Could not delete.', type: 'error' })
    }
  }

  const tier = app.tier!
  const needed = typesFor(requirements, tier)
  const extra = app.documents.filter((doc) => !needed.includes(doc.type))
  const accept = (type: string) => requirements.kinds[requirements.documents[type].kind ?? 'document'].mimes.map((m) => `.${m}`).join(',')
  const remaining = documentsMissing(app, requirements).length

  return (
    <Card>
      <CardHeader>
        <CardTitle render={<h2 />}>Documents for {requirements.tiers[tier].label}</CardTitle>
        <CardDescription>
          {remaining === 0 ? 'Every required document is uploaded.' : `${remaining} ${remaining === 1 ? 'item' : 'items'} still to upload.`} Documents and
          photos: PDF, JPG or PNG. Video: MP4.
        </CardDescription>
      </CardHeader>
      <CardPanel className="flex flex-col gap-6">
        <Alert variant="info">
          <AlertTitle>Before you upload</AlertTitle>
          <AlertDescription>
            <ul className="list-disc ps-5">{requirements.upload_rules.map((rule) => <li key={rule}>{rule}</li>)}</ul>
          </AlertDescription>
        </Alert>

        <div className="flex flex-col divide-y rounded-xl border">
          {requirements.tiers[tier].requirements.map((req) => {
            const have = app.documents.filter((doc) => req.types.includes(doc.type)).length
            const complete = have >= req.min
            return (
              <section key={req.types.join()} className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center gap-2">
                  <h3 className="mr-auto font-medium">{req.types.map((t) => requirements.documents[t].label).join(' or ')}</h3>
                  <Badge variant={complete ? 'success' : 'outline'}>{req.min > 1 ? `${have} of ${req.min}` : complete ? 'Uploaded' : 'Needed'}</Badge>
                </div>
                {req.types.map((type) => {
                  const info = requirements.documents[type]
                  const files = app.documents.filter((doc) => doc.type === type)
                  const max = info.max ?? 1
                  return (
                    <div key={type} className="flex flex-col gap-3">
                      <div className="flex flex-wrap items-start gap-3">
                        <div className="min-w-0 flex-1">
                          {req.types.length > 1 && <p className="font-medium text-sm">{info.label}</p>}
                          <p className="text-muted-foreground text-sm">
                            {info.proves} Sent by: {info.who}.
                          </p>
                        </div>
                        {files.length < max && !(req.types.length > 1 && files.length === 0 && have > 0) && (
                          <label
                            className={`${buttonVariants({ variant: 'outline', size: 'sm' })} has-[input:focus-visible]:ring-2 has-[input:focus-visible]:ring-ring ${uploading === type ? 'pointer-events-none opacity-64' : ''}`}
                          >
                            <Upload aria-hidden />
                            {uploading === type ? 'Uploading…' : files.length > 0 ? 'Add more' : 'Choose file'}
                            <input
                              type="file"
                              className="sr-only"
                              aria-label={`Upload ${info.label}`}
                              accept={accept(type)}
                              multiple={max > 1}
                              disabled={uploading === type}
                              onChange={(e) => {
                                upload(type, req.types, e.target.files)
                                e.target.value = ''
                              }}
                            />
                          </label>
                        )}
                      </div>
                      {uploadErrors[type] && (
                        <p role="alert" className="text-destructive-foreground text-sm">
                          {uploadErrors[type]}
                        </p>
                      )}
                      {uploading === type && <Skeleton className="h-14" aria-label="Uploading" />}
                      {files.length > 0 && (
                        <ul className={files.length > 2 ? 'grid gap-2 sm:grid-cols-2' : 'grid gap-2'}>
                          {files.map((file) => <FileTile key={file.id} gymId={app.id} file={file} onRemove={remove} />)}
                        </ul>
                      )}
                    </div>
                  )
                })}
              </section>
            )
          })}
        </div>

        {extra.length > 0 && (
          <section className="flex flex-col gap-3 rounded-xl border border-dashed p-4">
            <div>
              <h3 className="font-medium">Not needed for this tier</h3>
              <p className="text-muted-foreground text-sm">These files are kept but not sent for review. Delete them if you no longer need them.</p>
            </div>
            <ul className="grid gap-2 sm:grid-cols-2">
              {extra.map((file) => <FileTile key={file.id} gymId={app.id} file={file} onRemove={remove} />)}
            </ul>
          </section>
        )}
      </CardPanel>
    </Card>
  )
}

/** Last step: everything the administrator will see, and what is still missing. */
function Review({
  app,
  requirements,
  missing,
  onGoTo,
  onBack,
  onSend,
  sending,
}: {
  app: Application
  requirements: Requirements
  missing: { step: (typeof STEPS)[number]; missing: string[] }[]
  onGoTo: (key: StepKey) => void
  onBack: () => void
  onSend: () => void
  sending: boolean
}) {
  const open = missing.filter((m) => m.missing.length > 0)

  return (
    <>
      {open.length > 0 ? (
        <Alert variant="warning">
          <AlertTitle>Finish these before sending</AlertTitle>
          <AlertDescription>
            <ul className="flex flex-col gap-2">
              {open.map(({ step, missing: items }) => (
                <li key={step.key} className="flex flex-wrap items-start gap-x-3 gap-y-1">
                  <span className="min-w-0 flex-1">
                    <span className="font-medium">{step.title}:</span> {items.join(' ')}
                  </span>
                  <Button size="xs" variant="outline" onClick={() => onGoTo(step.key)}>
                    Go to {step.title.toLowerCase()}
                  </Button>
                </li>
              ))}
            </ul>
          </AlertDescription>
        </Alert>
      ) : (
        <Alert variant="success">
          <AlertTitle>Ready to send</AlertTitle>
          <AlertDescription>
            The platform administrator will check every document against the tier's checklist. You cannot change the application while it is being reviewed.
          </AlertDescription>
        </Alert>
      )}

      <div className="flex flex-col gap-6">
        <Card>
          <CardHeader>
            <CardTitle render={<h2 />}>Gym profile</CardTitle>
          </CardHeader>
          <CardPanel>
            <ProfileSummary app={app} requirements={requirements} />
          </CardPanel>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle render={<h2 />}>Documents</CardTitle>
          </CardHeader>
          <CardPanel>
            <DocumentSummary app={app} requirements={requirements} />
          </CardPanel>
        </Card>
      </div>

      <StepFooter
        onBack={onBack}
        primary={
          <Button onClick={onSend} loading={sending} disabled={open.length > 0}>
            Send application
          </Button>
        }
      />
    </>
  )
}
