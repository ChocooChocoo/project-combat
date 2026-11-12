import { ArrowLeft, Check, FileUp } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import { ContactsEditor, HealthQuestions } from '@/components/membership/membership-parts'
import { PageHeader } from '@/components/page-header'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Radio, RadioGroup } from '@/components/ui/radio-group'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { type GymDetailPayload, planLength, type PublicGymDetail, readGymDetail } from '@/lib/discovery'
import { appendForm, money, prepayCharge, type EmergencyContact, type Membership, type MembershipForm } from '@/lib/membership'
import { fileProblem } from '@/lib/validation'

/** S-13, R-31 and R-32: apply to join a gym on a chosen plan, with health declaration and waivers. */
export default function MembershipApply() {
  const { gymId } = useParams()
  const id = Number(gymId)
  const [search] = useSearchParams()
  const navigate = useNavigate()
  const { user } = useAuth()

  const [gym, setGym] = useState<PublicGymDetail | null>(null)
  const [form, setForm] = useState<MembershipForm | null>(null)
  const [loadError, setLoadError] = useState('')
  const [version, setVersion] = useState(0)

  const [planId, setPlanId] = useState(search.get('plan') ?? '')
  const [terms, setTerms] = useState('1')
  const [contacts, setContacts] = useState<EmergencyContact[]>([{ name: '', relationship: '', phone: '' }])
  const [answers, setAnswers] = useState<Record<string, boolean | undefined>>({})
  const [waivers, setWaivers] = useState<Record<string, boolean>>({})
  const [file, setFile] = useState<File | null>(null)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [tried, setTried] = useState(false)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let live = true
    setLoadError('')
    Promise.all([api<{ gym: GymDetailPayload }>(`/gyms/${id}`), api<MembershipForm>('/membership-form')])
      .then(([gymResult, formResult]) => {
        if (!live) return
        setGym(readGymDetail(gymResult.gym))
        setForm(formResult)
      })
      .catch((error) => live && setLoadError(error instanceof ApiError ? error.message : 'The application form could not be loaded.'))
    return () => {
      live = false
    }
  }, [id, version])

  const fullName = user ? `${user.first_name} ${user.last_name}` : ''

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!form) return
    setTried(true)

    const values = Object.fromEntries(new FormData(event.currentTarget)) as Record<string, string>
    const local: FieldErrors = {}
    if (!planId) local.plan_id = ['Choose a plan.']
    if (Object.keys(form.health_questions).some((key) => answers[key] === undefined)) local.health_answers = ['Answer every health question.']
    for (const key of Object.keys(form.waivers)) if (!waivers[key]) local[`waivers.${key}`] = ['Accept this waiver to continue.']
    if (!values.signature_name?.trim()) local.signature_name = ['Type your full name to sign.']
    if (file) {
      const problem = fileProblem(file, form.medical_file.mimes.map((ext) => `.${ext}`).join(','), form.medical_file.max_kb)
      if (problem) local.medical_file = [problem]
    }
    if (Object.keys(local).length > 0) {
      setErrors(local)
      toastManager.add({ title: 'Some answers are missing. Check the marked fields.', type: 'error' })
      return
    }

    const body = new FormData()
    appendForm(body, 'plan_id', planId)
    if (Number(terms) > 1) appendForm(body, 'prepaid_terms', terms)
    if (values.voucher_code?.trim()) appendForm(body, 'voucher_code', values.voucher_code.trim())
    appendForm(body, 'emergency_contacts', contacts)
    appendForm(body, 'background', values.background)
    appendForm(body, 'current_rank', values.current_rank)
    appendForm(body, 'training_history', values.training_history)
    appendForm(body, 'health_answers', answers)
    appendForm(body, 'health_notes', values.health_notes)
    appendForm(body, 'waivers', waivers)
    appendForm(body, 'signature_name', values.signature_name.trim())
    if (file) body.append('medical_file', file)

    setBusy(true)
    try {
      const result = await api<{ membership: Membership }>(`/gyms/${id}/membership-applications`, { body })
      toastManager.add({ title: 'Application sent. The gym will review it.', type: 'success' })
      navigate(`/memberships/${result.membership.id}`)
    } catch (error) {
      const fieldErrors = error instanceof ApiError ? error.errors : {}
      setErrors(fieldErrors)
      toastManager.add({
        title: Object.keys(fieldErrors).length > 0 ? 'Some answers need fixing.' : error instanceof ApiError ? error.message : 'The application could not be sent.',
        type: 'error',
      })
    } finally {
      setBusy(false)
    }
  }

  if (loadError) {
    return (
      <Alert variant="error">
        <AlertTitle>The application form could not be loaded</AlertTitle>
        <AlertDescription>{loadError}</AlertDescription>
        <AlertAction>
          <Button size="xs" variant="outline" onClick={() => setVersion((v) => v + 1)}>
            Try again
          </Button>
        </AlertAction>
      </Alert>
    )
  }

  if (!gym || !form) {
    return (
      <div className="flex flex-col gap-6" aria-busy="true" aria-label="Loading">
        <Skeleton className="h-16 w-80" />
        <Skeleton className="h-48" />
        <Skeleton className="h-64" />
      </div>
    )
  }

  if (gym.plans.length === 0) {
    return (
      <Empty className="rounded-xl border">
        <EmptyHeader>
          <EmptyTitle>{gym.name} has no plans to join yet</EmptyTitle>
          <EmptyDescription>Applications open once the gym publishes a membership plan.</EmptyDescription>
        </EmptyHeader>
        <Button variant="outline" render={<Link to={`/gyms/${id}`} />}>
          Back to the gym page
        </Button>
      </Empty>
    )
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={`Join ${gym.name}`}
        description="Choose a plan, tell the gym who to call in an emergency, declare your health and sign the waivers. Staff review every application."
        actions={
          <Button size="sm" variant="outline" render={<Link to={`/gyms/${id}`} />}>
            <ArrowLeft aria-hidden /> Gym page
          </Button>
        }
      />

      <Form errors={errors} onSubmit={submit} className="flex flex-col gap-6">
        <Card>
          <CardHeader>
            <CardTitle render={<h2 />}>Plan</CardTitle>
            <CardDescription>The fee is the full amount. You pay after the gym approves your application.</CardDescription>
          </CardHeader>
          <CardPanel>
            <Field name="plan_id" className="w-full">
              <RadioGroup aria-label="Membership plan" value={planId} onValueChange={(value) => { setPlanId(String(value)); setTerms('1') }} className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                {gym.plans.map((plan) => {
                  const inclusions = [...(plan.registration_included ? ['Registration included'] : []), ...(plan.inclusions ?? [])]
                  const addons = plan.addons ?? []
                  return (
                    <label
                      key={plan.id}
                      className="flex min-w-0 cursor-pointer flex-col overflow-hidden rounded-xl border bg-card transition-shadow has-focus-visible:ring-2 has-focus-visible:ring-ring has-data-checked:border-primary has-data-checked:ring-1 has-data-checked:ring-primary"
                    >
                      <span className="flex flex-col gap-4 p-5">
                        <span className="flex items-start justify-between gap-3">
                          <span className="font-semibold text-lg tracking-tight wrap-anywhere">{plan.name}</span>
                          <Radio value={String(plan.id)} aria-label={`Choose ${plan.name}`} className="mt-1" />
                        </span>
                        <span className="-mt-2 text-muted-foreground text-sm">
                          {plan.billing_type === 'sessions' ? 'Priced by sessions' : 'Priced by time'} · {planLength(plan)}
                        </span>
                        <span>
                          <span className="block font-semibold text-3xl tracking-tight tabular-nums wrap-anywhere">{money(plan.total_fee) || 'Not set'}</span>
                          <span className="mt-1 block text-muted-foreground text-sm">Total for {planLength(plan)}</span>
                        </span>
                      </span>
                      <span className="flex flex-1 flex-col gap-3 border-t p-5">
                        {inclusions.length > 0 ? (
                          <span className="flex flex-col gap-2">
                            {inclusions.slice(0, 4).map((item, index) => (
                              <span key={`${index}-${item}`} className="flex items-start gap-3 text-sm leading-6">
                                <Check className="mt-1 size-4 shrink-0 text-success-foreground" aria-hidden />
                                <span className="min-w-0 wrap-anywhere">{item}</span>
                              </span>
                            ))}
                            {inclusions.length > 4 && <span className="ps-7 text-muted-foreground text-xs">+{inclusions.length - 4} more included</span>}
                          </span>
                        ) : (
                          <span className="text-muted-foreground text-sm">No inclusions listed.</span>
                        )}
                        {addons.length > 0 && (
                          <span className="mt-auto border-t pt-3 text-muted-foreground text-xs">
                            {addons.length} optional {addons.length === 1 ? 'add-on' : 'add-ons'}, priced separately
                          </span>
                        )}
                      </span>
                    </label>
                  )
                })}
              </RadioGroup>
              <FieldError />
            </Field>
            {(() => {
              // R-109: a time plan may be prepaid for several terms in one payment.
              const chosen = gym.plans.find((plan) => String(plan.id) === planId)
              const max = chosen?.billing_type === 'time' ? Number(chosen.max_prepay_terms ?? 1) : 1
              if (!chosen || max <= 1) return null
              const count = Math.min(Math.max(Number(terms) || 1, 1), max)
              const discount = Number(chosen.prepay_discount_percent ?? 0)
              return (
                <Field name="prepaid_terms" className="mt-5 w-full max-w-xs">
                  <FieldLabel>Terms to prepay</FieldLabel>
                  <Input type="number" min="1" max={String(max)} step="1" inputMode="numeric" value={terms} onChange={(event) => setTerms(event.target.value)} />
                  <FieldDescription>
                    Up to {max} terms.{discount > 0 && ` ${discount}% off for 2 or more.`} You pay {money(prepayCharge(chosen.total_fee, count, discount))} after approval.
                  </FieldDescription>
                  <FieldError />
                </Field>
              )
            })()}
            <Field name="voucher_code" className="mt-5 w-full max-w-xs">
              <FieldLabel>Voucher code (optional)</FieldLabel>
              <Input name="voucher_code" maxLength={40} autoComplete="off" />
              <FieldDescription>Checked when the gym approves. Promos apply by themselves.</FieldDescription>
              <FieldError />
            </Field>
          </CardPanel>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle render={<h2 />}>Emergency contacts</CardTitle>
            <CardDescription>At least one person the gym can call if you get hurt. Only staff allowed to see health details can see them.</CardDescription>
          </CardHeader>
          <CardPanel>
            <ContactsEditor contacts={contacts} max={form.emergency_contacts_max} onChange={setContacts} />
          </CardPanel>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle render={<h2 />}>Your training</CardTitle>
            <CardDescription>Helps coaches place you in the right classes. All optional.</CardDescription>
          </CardHeader>
          <CardPanel className="grid gap-4 md:grid-cols-2">
            <Field name="background" className="w-full">
              <FieldLabel>Combat sports background</FieldLabel>
              <Textarea name="background" rows={3} maxLength={2000} placeholder="Sports you have trained and for how long" />
              <FieldError />
            </Field>
            <Field name="training_history" className="w-full">
              <FieldLabel>Training history</FieldLabel>
              <Textarea name="training_history" rows={3} maxLength={2000} placeholder="Gyms, coaches, fights or competitions" />
              <FieldError />
            </Field>
            <Field name="current_rank" className="w-full">
              <FieldLabel>Current rank, if any</FieldLabel>
              <Input name="current_rank" maxLength={150} placeholder="For example: BJJ blue belt" />
              <FieldError />
            </Field>
          </CardPanel>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle render={<h2 />}>Health declaration</CardTitle>
            <CardDescription>Combat sports carry injury risk. Your answers are seen only by staff allowed to see health details.</CardDescription>
          </CardHeader>
          <CardPanel className="flex flex-col gap-4">
            <HealthQuestions questions={form.health_questions} answers={answers} onChange={setAnswers} missing={tried} />
            {errors.health_answers && <p role="alert" className="text-destructive-foreground text-sm">{errors.health_answers[0]}</p>}
            <Field name="health_notes" className="w-full">
              <FieldLabel>Details</FieldLabel>
              <Textarea name="health_notes" rows={3} maxLength={2000} placeholder="If you answered yes, say what and when" />
              <FieldError />
            </Field>
            <Field name="medical_file" className="w-full">
              <FieldLabel>Medical clearance (optional)</FieldLabel>
              <Input
                type="file"
                accept={form.medical_file.mimes.map((ext) => `.${ext}`).join(',')}
                onChange={(event) => setFile(event.target.files?.[0] ?? null)}
              />
              <FieldDescription>
                <FileUp className="inline size-3.5" aria-hidden /> {form.medical_file.mimes.join(', ').toUpperCase()}, up to {Math.round(form.medical_file.max_kb / 1024)} MB.
                Kept privately.
              </FieldDescription>
              <FieldError />
            </Field>
          </CardPanel>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle render={<h2 />}>Waivers and signature</CardTitle>
            <CardDescription>Accept each waiver and type your full name. Today's date is recorded with your signature.</CardDescription>
          </CardHeader>
          <CardPanel className="flex flex-col gap-4">
            {gym.policies && (
              <div className="rounded-lg border bg-muted/40 px-3 py-2.5 text-sm">
                <p className="font-medium">{gym.name} policies</p>
                <p className="mt-1 whitespace-pre-line text-muted-foreground">{gym.policies}</p>
              </div>
            )}
            {Object.entries(form.waivers).map(([key, text]) => (
              <Field key={key} name={`waivers.${key}`} className="w-full">
                <label className="flex items-start gap-3 text-sm">
                  <Checkbox
                    className="mt-0.5"
                    checked={waivers[key] ?? false}
                    onCheckedChange={(checked) => setWaivers((current) => ({ ...current, [key]: checked }))}
                    aria-invalid={(tried && !waivers[key]) || undefined}
                  />
                  {text}
                </label>
                <FieldError />
              </Field>
            ))}
            <Field name="signature_name" className="w-full sm:max-w-sm">
              <FieldLabel>Signature</FieldLabel>
              <Input name="signature_name" maxLength={150} required placeholder={fullName || 'Your full name'} autoComplete="name" />
              <FieldDescription>Type your full name as your signature.</FieldDescription>
              <FieldError />
            </Field>
          </CardPanel>
        </Card>

        <div className="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="outline" render={<Link to={`/gyms/${id}`} />}>
            Cancel
          </Button>
          <Button type="submit" loading={busy}>
            Send application
          </Button>
        </div>
      </Form>
    </div>
  )
}
