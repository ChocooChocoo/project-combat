import { ArrowLeft, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { PageHeader } from '@/components/page-header'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { labelKey, type PublicPlan, type PublicService } from '@/lib/discovery'
import type { WorkspaceOptions } from '@/lib/workspace'

type AddonForm = {
  service_id: string
  price: string
}

type PlanForm = {
  name: string
  billing_type: string
  duration_months: string
  session_count: string
  total_fee: string
  registration_included: boolean
  inclusions: string
  policy_validity: string
  policy_freezing: string
  policy_renewal: string
  policy_cancellation: string
  max_freeze_days: string
  refundable: boolean
  addons: AddonForm[]
}

type Busy = 'save' | 'publish' | 'unpublish' | null

function lines(value: string): string[] {
  return value
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean)
}

function fromPlan(plan: PublicPlan | null): PlanForm {
  return {
    name: plan?.name ?? '',
    billing_type: plan?.billing_type ?? 'time',
    duration_months: plan?.duration_months != null ? String(plan.duration_months) : '',
    session_count: plan?.session_count != null ? String(plan.session_count) : '',
    total_fee: plan?.total_fee != null ? String(plan.total_fee) : '',
    registration_included: plan?.registration_included ?? false,
    inclusions: (plan?.inclusions ?? []).join('\n'),
    policy_validity: plan?.policy_validity ?? '',
    policy_freezing: plan?.policy_freezing ?? '',
    policy_renewal: plan?.policy_renewal ?? '',
    policy_cancellation: plan?.policy_cancellation ?? '',
    max_freeze_days: plan?.max_freeze_days != null ? String(plan.max_freeze_days) : '',
    refundable: plan?.refundable ?? false,
    addons: (plan?.addons ?? []).map((addon) => ({
      service_id: addon.service_id != null ? String(addon.service_id) : '',
      price: addon.price != null ? String(addon.price) : '',
    })),
  }
}

function missingForPublish(form: PlanForm): string[] {
  const missing: string[] = []
  if (!form.total_fee.trim()) missing.push('Total fee')
  if (lines(form.inclusions).length === 0) missing.push('At least one inclusion')
  if (!form.policy_validity.trim()) missing.push('Validity policy')
  if (!form.policy_freezing.trim()) missing.push('Freezing policy')
  if (!form.policy_renewal.trim()) missing.push('Renewal policy')
  if (!form.policy_cancellation.trim()) missing.push('Cancellation and refunds policy')
  return missing
}

function lengthLabel(plan: PublicPlan): string {
  if (plan.billing_type === 'sessions') {
    return `${plan.session_count ?? 0} ${plan.session_count === 1 ? 'session' : 'sessions'}`
  }
  return `${plan.duration_months ?? 0} ${plan.duration_months === 1 ? 'month' : 'months'}`
}

function priceLabel(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return 'Not set'
  const amount = Number(value)
  if (!Number.isFinite(amount)) return 'Not set'
  return `₱${amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

/** R-29: the full plan editor, with inclusions, add-ons, policies and publishing. */
export function PlanEditor({
  gymId,
  plan,
  options,
  services,
  servicesError,
  onBack,
  onSaved,
}: {
  gymId: string | number
  plan: PublicPlan | null
  options: WorkspaceOptions
  services: PublicService[]
  servicesError: string
  onBack: () => void
  onSaved: (plan: PublicPlan) => void
}) {
  const [current, setCurrent] = useState<PublicPlan | null>(plan)
  const [form, setForm] = useState<PlanForm>(() => fromPlan(plan))
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState<Busy>(null)

  const set = <K extends keyof PlanForm>(key: K, value: PlanForm[K]) =>
    setForm((existing) => ({ ...existing, [key]: value }))

  const usedServiceIds = new Set(form.addons.map((addon) => addon.service_id).filter(Boolean))
  const savedAddons = (current ?? plan)?.addons ?? []
  const existingServiceIds = new Set(savedAddons.map((addon) => addon.service_id))
  const serviceChoices = [
    ...services
      .filter((service) => service.published_at || existingServiceIds.has(service.id))
      .map((service) => ({ id: service.id, name: service.name, published: Boolean(service.published_at) })),
    ...savedAddons
      .filter((addon) => addon.service_id != null && !services.some((service) => service.id === addon.service_id))
      .map((addon) => ({ id: addon.service_id as number, name: addon.service_name ?? 'Service', published: false })),
  ].filter((choice, index, all) => all.findIndex((item) => item.id === choice.id) === index)

  const availableService = serviceChoices.find((choice) => !usedServiceIds.has(String(choice.id)))
  const messages = [...new Set(Object.values(errors).flat())]
  const published = Boolean(current?.published_at)
  const missing = missingForPublish(form)

  async function persist(): Promise<PublicPlan | null> {
    setBusy('save')
    setErrors({})

    const numberOrNull = (value: string) => (value.trim() === '' ? null : Number(value))

    try {
      const body = {
        name: form.name.trim(),
        billing_type: form.billing_type,
        duration_months: form.billing_type === 'time' ? numberOrNull(form.duration_months) : null,
        session_count: form.billing_type === 'sessions' ? numberOrNull(form.session_count) : null,
        total_fee: numberOrNull(form.total_fee),
        registration_included: form.registration_included,
        inclusions: lines(form.inclusions),
        policy_validity: form.policy_validity.trim(),
        policy_freezing: form.policy_freezing.trim(),
        policy_renewal: form.policy_renewal.trim(),
        policy_cancellation: form.policy_cancellation.trim(),
        max_freeze_days: numberOrNull(form.max_freeze_days),
        refundable: form.refundable,
        addons: form.addons.map((addon) => ({
          service_id: addon.service_id === '' ? null : Number(addon.service_id),
          price: numberOrNull(addon.price),
        })),
      }

      const result = await api<{ plan: PublicPlan }>(
        current ? `/gyms/${gymId}/plans/${current.id}` : `/gyms/${gymId}/plans`,
        { method: current ? 'PUT' : 'POST', body },
      )

      setCurrent(result.plan)
      setForm(fromPlan(result.plan))
      onSaved(result.plan)
      return result.plan
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
      if (!(error instanceof ApiError) || Object.keys(error.errors).length === 0) {
        toastManager.add({
          title: error instanceof ApiError ? error.message : 'The plan could not be saved.',
          type: 'error',
        })
      }
      return null
    } finally {
      setBusy(null)
    }
  }

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const saved = await persist()
    if (saved) toastManager.add({ title: 'Plan saved.', type: 'success' })
  }

  async function publish() {
    const saved = await persist()
    if (!saved) return

    setBusy('publish')
    setErrors({})
    try {
      const result = await api<{ plan: PublicPlan }>(`/gyms/${gymId}/plans/${saved.id}/publish`, {
        method: 'POST',
      })
      setCurrent(result.plan)
      setForm(fromPlan(result.plan))
      onSaved(result.plan)
      toastManager.add({ title: 'Plan published.', type: 'success' })
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
      if (!(error instanceof ApiError) || Object.keys(error.errors).length === 0) {
        toastManager.add({
          title: error instanceof ApiError ? error.message : 'The plan could not be published.',
          type: 'error',
        })
      }
    } finally {
      setBusy(null)
    }
  }

  async function unpublish() {
    if (!current) return
    setBusy('unpublish')
    setErrors({})
    try {
      const result = await api<{ plan: PublicPlan }>(`/gyms/${gymId}/plans/${current.id}/unpublish`, {
        method: 'POST',
      })
      setCurrent(result.plan)
      setForm(fromPlan(result.plan))
      onSaved(result.plan)
      toastManager.add({ title: 'Plan unpublished.', type: 'success' })
    } catch (error) {
      toastManager.add({
        title: error instanceof ApiError ? error.message : 'The plan could not be unpublished.',
        type: 'error',
      })
    } finally {
      setBusy(null)
    }
  }

  function addAddon() {
    if (!availableService) {
      toastManager.add({ title: 'No other published service is available.', type: 'error' })
      return
    }
    set('addons', [...form.addons, { service_id: String(availableService.id), price: '' }])
  }

  function updateAddon(index: number, change: Partial<AddonForm>) {
    set(
      'addons',
      form.addons.map((addon, position) => (position === index ? { ...addon, ...change } : addon)),
    )
  }

  function removeAddon(index: number) {
    set('addons', form.addons.filter((_, position) => position !== index))
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={current ? 'Edit plan' : 'New plan'}
        description="One total fee, what it covers, the extras that are not included, and the policies members agree to."
        meta={current && <Badge variant={published ? 'success' : 'secondary'}>{published ? 'Published' : 'Draft'}</Badge>}
        actions={
          <Button size="sm" variant="outline" onClick={onBack}>
            <ArrowLeft aria-hidden /> Back to plans
          </Button>
        }
      />

      <Form errors={errors} onSubmit={save} className="flex flex-col gap-6">
        {messages.length > 0 && (
          <Alert variant="error">
            <AlertTitle>Check these fields</AlertTitle>
            <AlertDescription>
              <ul className="list-disc ps-5">
                {messages.map((message) => (
                  <li key={message}>{message}</li>
                ))}
              </ul>
            </AlertDescription>
          </Alert>
        )}

        {servicesError && (
          <Alert variant="warning">
            <AlertTitle>Service list unavailable</AlertTitle>
            <AlertDescription>
              <p>{servicesError}</p>
              <p>You can still edit the plan, but add-ons cannot be added until the service list loads.</p>
            </AlertDescription>
          </Alert>
        )}

        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
          <div className="flex min-w-0 flex-col gap-6">
            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Plan details</CardTitle>
                <CardDescription>The name, billing type and total fee.</CardDescription>
              </CardHeader>
              <CardPanel className="grid gap-4 sm:grid-cols-2">
                <Field name="name" className="w-full sm:col-span-2">
                  <FieldLabel>Plan name</FieldLabel>
                  <Input
                    required
                    maxLength={120}
                    value={form.name}
                    onChange={(event) => set('name', event.target.value)}
                    placeholder="Monthly unlimited"
                  />
                  <FieldError />
                </Field>

                <Field name="billing_type" className="w-full">
                  <FieldLabel>Billing type</FieldLabel>
                  <Select
                    value={form.billing_type}
                    onValueChange={(value) => set('billing_type', String(value ?? 'time'))}
                    items={Object.entries(options.plan_billing).map(([value, label]) => ({ value, label }))}
                  >
                    <SelectTrigger aria-invalid={errors.billing_type ? true : undefined}>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectPopup>
                      {Object.entries(options.plan_billing).map(([value, label]) => (
                        <SelectItem key={value} value={value}>
                          {label}
                        </SelectItem>
                      ))}
                    </SelectPopup>
                  </Select>
                  <FieldError />
                </Field>

                {form.billing_type === 'sessions' ? (
                  <Field name="session_count" className="w-full">
                    <FieldLabel>Number of sessions</FieldLabel>
                    <Input
                      type="number"
                      min="1"
                      max="365"
                      step="1"
                      required
                      inputMode="numeric"
                      value={form.session_count}
                      onChange={(event) => set('session_count', event.target.value)}
                      placeholder="10"
                    />
                    <FieldError />
                  </Field>
                ) : (
                  <Field name="duration_months" className="w-full">
                    <FieldLabel>Length in months</FieldLabel>
                    <Input
                      type="number"
                      min="1"
                      max="120"
                      step="1"
                      required
                      inputMode="numeric"
                      value={form.duration_months}
                      onChange={(event) => set('duration_months', event.target.value)}
                      placeholder="1"
                    />
                    <FieldError />
                  </Field>
                )}

                <Field name="total_fee" className="w-full">
                  <FieldLabel>Total fee</FieldLabel>
                  <Input
                    type="number"
                    min="0"
                    step="0.01"
                    required
                    inputMode="decimal"
                    value={form.total_fee}
                    onChange={(event) => set('total_fee', event.target.value)}
                    placeholder="1500.00"
                  />
                  <FieldDescription>The one amount members pay for this plan.</FieldDescription>
                  <FieldError />
                </Field>

                <Field name="registration_included" className="w-full">
                  <div className="flex w-full items-center justify-between gap-4 rounded-lg border px-3 py-2.5">
                    <div>
                      <FieldLabel>Registration included</FieldLabel>
                      <p className="text-muted-foreground text-xs">The total fee already covers registration.</p>
                    </div>
                    <Switch
                      checked={form.registration_included}
                      onCheckedChange={(checked) => set('registration_included', checked)}
                    />
                  </div>
                  <FieldError />
                </Field>
              </CardPanel>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>What the fee covers</CardTitle>
                <CardDescription>One inclusion per line. At least one is required.</CardDescription>
              </CardHeader>
              <CardPanel>
                <Field
                  name="inclusions"
                  className="w-full"
                  validate={() =>
                    form.inclusions.split('\n').some((line) => line.trim().length > 255) ? 'Keep each line under 255 characters.' : null
                  }
                >
                  <Textarea
                    rows={5}
                    required
                    value={form.inclusions}
                    onChange={(event) => set('inclusions', event.target.value)}
                    placeholder={'Unlimited classes\nOpen mat access\nLocker room'}
                  />
                  <FieldError />
                </Field>
              </CardPanel>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Add-ons not included</CardTitle>
                <CardDescription>Optional extras priced separately. Only published services can be chosen.</CardDescription>
              </CardHeader>
              <CardPanel className="flex flex-col gap-4">
                {form.addons.length === 0 ? (
                  <p className="text-muted-foreground text-sm">No add-ons yet.</p>
                ) : (
                  <ul className="flex flex-col gap-4">
                    {form.addons.map((addon, index) => {
                      const rowChoices = serviceChoices.filter(
                        (choice) =>
                          String(choice.id) === addon.service_id ||
                          !usedServiceIds.has(String(choice.id)),
                      )
                      const choicesWithCurrent = serviceChoices.some((choice) => String(choice.id) === addon.service_id)
                        ? rowChoices
                        : [
                            ...rowChoices,
                            {
                              id: addon.service_id === '' ? -index - 1 : Number(addon.service_id) || -index - 1,
                              name: 'Service no longer listed',
                              published: false,
                            },
                          ]

                      return (
                        <li key={`${addon.service_id}-${index}`} className="rounded-lg border p-3">
                          <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_9rem_auto] sm:items-end">
                            <Field
                              name={`addons.${index}.service_id`}
                              className="w-full"
                              validate={() => (addon.service_id ? null : 'Choose a service.')}
                            >
                              <FieldLabel>Service</FieldLabel>
                              <Select
                                value={addon.service_id || 'none'}
                                onValueChange={(value) =>
                                  updateAddon(index, { service_id: String(value ?? '') === 'none' ? '' : String(value ?? '') })
                                }
                                items={[
                                  { value: 'none', label: 'Choose a service' },
                                  ...choicesWithCurrent.map((choice) => ({
                                    value: String(choice.id),
                                    label: choice.published ? choice.name : `${choice.name} (not published)`,
                                  })),
                                ]}
                              >
                                <SelectTrigger aria-invalid={errors[`addons.${index}.service_id`] ? true : undefined}>
                                  <SelectValue placeholder="Choose a service" />
                                </SelectTrigger>
                                <SelectPopup>
                                  <SelectItem value="none">Choose a service</SelectItem>
                                  {choicesWithCurrent.map((choice) => (
                                    <SelectItem key={choice.id} value={String(choice.id)}>
                                      {choice.published ? choice.name : `${choice.name} (not published)`}
                                    </SelectItem>
                                  ))}
                                </SelectPopup>
                              </Select>
                              <FieldError />
                            </Field>

                            <Field name={`addons.${index}.price`} className="w-full">
                              <FieldLabel>Price</FieldLabel>
                              <Input
                                type="number"
                                min="0"
                                step="0.01"
                                required
                                inputMode="decimal"
                                value={addon.price}
                                onChange={(event) => updateAddon(index, { price: event.target.value })}
                                placeholder="500.00"
                              />
                              <FieldError />
                            </Field>

                            <Button
                              type="button"
                              size="icon"
                              variant="destructive-outline"
                              onClick={() => removeAddon(index)}
                              aria-label="Remove add-on"
                            >
                              <Trash2 aria-hidden />
                            </Button>
                          </div>
                        </li>
                      )
                    })}
                  </ul>
                )}

                <div className="flex flex-wrap items-center gap-2">
                  <Button type="button" size="sm" variant="outline" onClick={addAddon} disabled={!availableService}>
                    <Plus aria-hidden /> Add add-on
                  </Button>
                  {!availableService && form.addons.length > 0 && (
                    <span className="text-muted-foreground text-sm">Every available service is already added.</span>
                  )}
                </div>
                {serviceChoices.length === 0 && !servicesError && (
                  <p className="text-muted-foreground text-sm">No published services yet. Publish a service first.</p>
                )}
              </CardPanel>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Policies</CardTitle>
                <CardDescription>All four policies are required before the plan can be published.</CardDescription>
              </CardHeader>
              <CardPanel className="grid gap-4 sm:grid-cols-2">
                <Field name="policy_validity" className="w-full">
                  <FieldLabel>Validity</FieldLabel>
                  <Textarea
                    rows={4}
                    required
                    maxLength={5000}
                    value={form.policy_validity}
                    onChange={(event) => set('policy_validity', event.target.value)}
                    placeholder="Valid for 30 days from the first visit."
                  />
                  <FieldError />
                </Field>

                <Field name="policy_freezing" className="w-full">
                  <FieldLabel>Freezing</FieldLabel>
                  <Textarea
                    rows={4}
                    required
                    maxLength={5000}
                    value={form.policy_freezing}
                    onChange={(event) => set('policy_freezing', event.target.value)}
                    placeholder="How and when a membership may be paused."
                  />
                  <FieldError />
                </Field>

                <Field name="policy_renewal" className="w-full">
                  <FieldLabel>Renewal</FieldLabel>
                  <Textarea
                    rows={4}
                    required
                    maxLength={5000}
                    value={form.policy_renewal}
                    onChange={(event) => set('policy_renewal', event.target.value)}
                    placeholder="How the plan renews or ends."
                  />
                  <FieldError />
                </Field>

                <Field name="policy_cancellation" className="w-full">
                  <FieldLabel>Cancellation and refunds</FieldLabel>
                  <Textarea
                    rows={4}
                    required
                    maxLength={5000}
                    value={form.policy_cancellation}
                    onChange={(event) => set('policy_cancellation', event.target.value)}
                    placeholder="How cancellation and refunds work."
                  />
                  <FieldError />
                </Field>

                <Field name="max_freeze_days" className="w-full">
                  <FieldLabel>Most days it can be frozen</FieldLabel>
                  <Input
                    type="number"
                    min="1"
                    max="365"
                    step="1"
                    inputMode="numeric"
                    value={form.max_freeze_days}
                    onChange={(event) => set('max_freeze_days', event.target.value)}
                    placeholder="Leave empty if freezing is not allowed"
                  />
                  <FieldDescription>Per membership period. Staff cannot freeze for longer than this.</FieldDescription>
                  <FieldError />
                </Field>

                <Field name="refundable" className="w-full">
                  <div className="flex w-full items-center justify-between gap-4 rounded-lg border px-3 py-2.5">
                    <div>
                      <FieldLabel>Refund on cancel</FieldLabel>
                      <p className="text-muted-foreground text-xs">Staff may refund part of what was paid when cancelling.</p>
                    </div>
                    <Switch checked={form.refundable} onCheckedChange={(checked) => set('refundable', checked)} />
                  </div>
                  <FieldError />
                </Field>
              </CardPanel>
            </Card>
          </div>

          <div className="flex min-w-0 flex-col gap-6">
            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Publishing</CardTitle>
                <CardDescription>Published plans appear on the public gym page.</CardDescription>
              </CardHeader>
              <CardPanel className="flex flex-col gap-4">
                <div className="flex items-center gap-2">
                  <Badge variant={published ? 'success' : 'secondary'}>{published ? 'Published' : 'Draft'}</Badge>
                  {published && <span className="text-muted-foreground text-sm">Visible to everyone signed in.</span>}
                </div>

                {!published && missing.length > 0 && (
                  <Alert variant="warning">
                    <AlertTitle>Still needed before publishing</AlertTitle>
                    <AlertDescription>
                      <ul className="list-disc ps-5">
                        {missing.map((item) => (
                          <li key={item}>{item}</li>
                        ))}
                      </ul>
                    </AlertDescription>
                  </Alert>
                )}

                {!published && missing.length === 0 && (
                  <Alert variant="success">
                    <AlertTitle>Ready to publish</AlertTitle>
                    <AlertDescription>The fee, inclusions and all four policies are filled in.</AlertDescription>
                  </Alert>
                )}

                {published ? (
                  <Button type="button" variant="outline" loading={busy === 'unpublish'} onClick={unpublish}>
                    Unpublish plan
                  </Button>
                ) : (
                  <Button type="button" loading={busy === 'publish'} disabled={missing.length > 0} onClick={publish}>
                    Publish plan
                  </Button>
                )}
                <p className="text-muted-foreground text-xs">Publishing saves the current form first, then runs the server checks.</p>
              </CardPanel>
            </Card>

            {current && (
              <Card>
                <CardHeader>
                  <CardTitle render={<h2 />}>Summary</CardTitle>
                </CardHeader>
                <CardPanel className="flex flex-col gap-2 text-sm">
                  <div className="flex items-baseline justify-between gap-2">
                    <span className="text-muted-foreground">Total fee</span>
                    <span className="font-medium tabular-nums">{priceLabel(current.total_fee)}</span>
                  </div>
                  <div className="flex items-baseline justify-between gap-2">
                    <span className="text-muted-foreground">Billing</span>
                    <span className="text-right">
                      {(options.plan_billing as Record<string, string>)[current.billing_type] ?? labelKey(current.billing_type)} ·{' '}
                      {lengthLabel(current)}
                    </span>
                  </div>
                  <div className="flex items-baseline justify-between gap-2">
                    <span className="text-muted-foreground">Add-ons</span>
                    <span className="tabular-nums">{current.addons?.length ?? 0}</span>
                  </div>
                </CardPanel>
              </Card>
            )}
          </div>
        </div>

        <div className="flex flex-wrap justify-end gap-2 border-t pt-5">
          <Button type="button" variant="outline" onClick={onBack}>
            Cancel
          </Button>
          <Button type="submit" loading={busy === 'save'}>
            Save plan
          </Button>
        </div>
      </Form>
    </div>
  )
}
