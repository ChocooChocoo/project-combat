import { ArrowLeft, ImagePlus, Trash2 } from 'lucide-react'
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
import { Skeleton } from '@/components/ui/skeleton'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { type PublicService, type PublicServicePhoto } from '@/lib/discovery'
import type { WorkspaceOptions } from '@/lib/workspace'

type ServiceForm = {
  name: string
  discipline: string
  activity_type: string
  overview: string
  level: string
  duration_minutes: string
  frequency: string
  included: string
  bring: string
  price: string
  price_included: boolean
}

type Busy = 'save' | 'publish' | 'unpublish' | null

function lines(value: string): string[] {
  return value
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean)
}

function fromService(service: PublicService | null): ServiceForm {
  return {
    name: service?.name ?? '',
    discipline: service?.discipline ?? '',
    activity_type: service?.activity_type ?? '',
    overview: service?.overview ?? '',
    level: service?.level ?? '',
    duration_minutes: service?.duration_minutes != null ? String(service.duration_minutes) : '',
    frequency: service?.frequency ?? '',
    included: (service?.included ?? []).join('\n'),
    bring: (service?.bring ?? []).join('\n'),
    price: service?.price != null ? String(service.price) : '',
    price_included: service?.price_included ?? false,
  }
}

function sentenceCount(text: string): number {
  return (text.match(/[.!?]/g) ?? []).length
}

function missingForPublish(form: ServiceForm, coverUrl: string | null): string[] {
  const missing: string[] = []
  const overview = form.overview.trim()
  const duration = Number(form.duration_minutes)

  if (!form.name.trim()) missing.push('Service name')
  if (!form.discipline) missing.push('Combat sport')
  if (!form.activity_type) missing.push('Activity type')
  if (overview.length < 40 || sentenceCount(overview) < 2 || sentenceCount(overview) > 4) {
    missing.push('Overview of 2 to 4 sentences, at least 40 characters')
  }
  if (!form.level) missing.push('Level')
  if (!form.duration_minutes.trim() || !Number.isFinite(duration) || duration < 1 || duration > 600) {
    missing.push('Duration between 1 and 600 minutes')
  }
  if (!form.frequency) missing.push('Frequency')
  if (lines(form.included).length === 0) missing.push('At least one inclusion')
  if (lines(form.bring).length === 0) missing.push('At least one item to bring')
  if (!coverUrl) missing.push('Cover photo')
  if (!form.price_included && form.price.trim() === '') missing.push('Price, or mark it included')

  return missing
}

function priceLabel(value: string): string {
  const amount = Number(value)
  if (!Number.isFinite(amount)) return 'Not priced'
  return `₱${amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

/** Backend: each list line is at most 255 characters. */
function longLine(text: string): string | null {
  return text.split('\n').some((line) => line.trim().length > 255) ? 'Keep each line under 255 characters.' : null
}

/** R-28: the full service editor, including photos, publishing and server errors. */
export function ServiceEditor({
  gymId,
  service,
  options,
  onBack,
  onSaved,
}: {
  gymId: string | number
  service: PublicService | null
  options: WorkspaceOptions
  onBack: () => void
  onSaved: (service: PublicService) => void
}) {
  const [current, setCurrent] = useState<PublicService | null>(service)
  const [form, setForm] = useState<ServiceForm>(() => fromService(service))
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState<Busy>(null)
  const [mediaBusy, setMediaBusy] = useState<'cover' | 'gallery' | null>(null)
  const [deletingPhoto, setDeletingPhoto] = useState<number | null>(null)
  const [mediaError, setMediaError] = useState('')

  const set = <K extends keyof ServiceForm>(key: K, value: ServiceForm[K]) =>
    setForm((existing) => ({ ...existing, [key]: value }))

  const messages = [...new Set(Object.values(errors).flat())]
  const published = Boolean(current?.published_at)
  const missing = missingForPublish(form, current?.cover_photo_url ?? null)
  const maxPhotos = options.service_photos?.max ?? 5
  const maxPhotoKb = options.service_photos?.max_kb ?? 4096
  const photos = current?.photos ?? []

  async function persist(): Promise<PublicService | null> {
    setBusy('save')
    setErrors({})

    try {
      const body = {
        name: form.name.trim(),
        discipline: form.discipline,
        activity_type: form.activity_type,
        overview: form.overview.trim(),
        level: form.level,
        duration_minutes: form.duration_minutes.trim() === '' ? null : Number(form.duration_minutes),
        frequency: form.frequency,
        included: lines(form.included),
        bring: lines(form.bring),
        price_included: form.price_included,
        price: form.price_included || form.price.trim() === '' ? null : Number(form.price),
      }

      const result = await api<{ service: PublicService }>(
        current ? `/gyms/${gymId}/services/${current.id}` : `/gyms/${gymId}/services`,
        { method: current ? 'PUT' : 'POST', body },
      )

      setCurrent(result.service)
      setForm(fromService(result.service))
      onSaved(result.service)
      return result.service
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
      if (!(error instanceof ApiError) || Object.keys(error.errors).length === 0) {
        toastManager.add({
          title: error instanceof ApiError ? error.message : 'The service could not be saved.',
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
    if (saved) toastManager.add({ title: 'Service saved.', type: 'success' })
  }

  async function publish() {
    const saved = await persist()
    if (!saved) return

    setBusy('publish')
    setErrors({})
    try {
      const result = await api<{ service: PublicService }>(`/gyms/${gymId}/services/${saved.id}/publish`, {
        method: 'POST',
      })
      setCurrent(result.service)
      setForm(fromService(result.service))
      onSaved(result.service)
      toastManager.add({ title: 'Service published.', type: 'success' })
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
      if (!(error instanceof ApiError) || Object.keys(error.errors).length === 0) {
        toastManager.add({
          title: error instanceof ApiError ? error.message : 'The service could not be published.',
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
      const result = await api<{ service: PublicService }>(`/gyms/${gymId}/services/${current.id}/unpublish`, {
        method: 'POST',
      })
      setCurrent(result.service)
      setForm(fromService(result.service))
      onSaved(result.service)
      toastManager.add({ title: 'Service unpublished.', type: 'success' })
    } catch (error) {
      toastManager.add({
        title: error instanceof ApiError ? error.message : 'The service could not be unpublished.',
        type: 'error',
      })
    } finally {
      setBusy(null)
    }
  }

  async function uploadCover(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file || !current) return

    setMediaError('')
    if (!file.type.startsWith('image/')) {
      setMediaError('Choose an image file.')
      return
    }
    if (file.size > maxPhotoKb * 1024) {
      setMediaError(`This photo is too big. The largest accepted size is ${Math.round(maxPhotoKb / 1024)} MB.`)
      return
    }

    setMediaBusy('cover')
    try {
      const body = new FormData()
      body.append('photo', file)
      const result = await api<{ service: PublicService }>(`/gyms/${gymId}/services/${current.id}/cover`, { body })
      setCurrent(result.service)
      onSaved(result.service)
      toastManager.add({ title: 'Cover photo updated.', type: 'success' })
    } catch (error) {
      setMediaError(error instanceof ApiError ? (error.errors.photo?.[0] ?? error.message) : 'The cover photo could not be uploaded.')
    } finally {
      setMediaBusy(null)
    }
  }

  async function uploadGalleryPhoto(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file || !current) return

    setMediaError('')
    if (photos.length >= maxPhotos) {
      setMediaError(`You can add at most ${maxPhotos} gallery photos. Delete one first to add another.`)
      return
    }
    if (!file.type.startsWith('image/')) {
      setMediaError('Choose an image file.')
      return
    }
    if (file.size > maxPhotoKb * 1024) {
      setMediaError(`This photo is too big. The largest accepted size is ${Math.round(maxPhotoKb / 1024)} MB.`)
      return
    }

    setMediaBusy('gallery')
    try {
      const body = new FormData()
      body.append('photo', file)
      const result = await api<{ service: PublicService }>(`/gyms/${gymId}/services/${current.id}/photos`, { body })
      setCurrent(result.service)
      onSaved(result.service)
      toastManager.add({ title: 'Gallery photo added.', type: 'success' })
    } catch (error) {
      setMediaError(error instanceof ApiError ? (error.errors.photo?.[0] ?? error.message) : 'The photo could not be uploaded.')
    } finally {
      setMediaBusy(null)
    }
  }

  async function removePhoto(photo: PublicServicePhoto) {
    if (!current) return
    setDeletingPhoto(photo.id)
    setMediaError('')
    try {
      const result = await api<{ service: PublicService }>(
        `/gyms/${gymId}/services/${current.id}/photos/${photo.id}`,
        { method: 'DELETE' },
      )
      setCurrent(result.service)
      onSaved(result.service)
      toastManager.add({ title: 'Gallery photo removed.', type: 'success' })
    } catch (error) {
      setMediaError(error instanceof ApiError ? error.message : 'The photo could not be removed.')
    } finally {
      setDeletingPhoto(null)
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={current ? 'Edit service' : 'New service'}
        description="What the gym sells, what it includes and what an athlete should bring. Only published services appear on the public gym page."
        meta={current && <Badge variant={published ? 'success' : 'secondary'}>{published ? 'Published' : 'Draft'}</Badge>}
        actions={
          <Button size="sm" variant="outline" onClick={onBack}>
            <ArrowLeft aria-hidden /> Back to services
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

        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
          <div className="flex min-w-0 flex-col gap-6">
            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Service details</CardTitle>
                <CardDescription>The name, sport and format members see first.</CardDescription>
              </CardHeader>
              <CardPanel className="grid gap-4 sm:grid-cols-2">
                <Field name="name" className="w-full sm:col-span-2">
                  <FieldLabel>Service name</FieldLabel>
                  <Input
                    required
                    maxLength={120}
                    value={form.name}
                    onChange={(event) => set('name', event.target.value)}
                    placeholder="Boxing fundamentals"
                  />
                  <FieldError />
                </Field>

                <Field name="discipline" className="w-full" validate={() => (form.discipline ? null : 'Choose a combat sport.')}>
                  <FieldLabel>Combat sport</FieldLabel>
                  <Select
                    value={form.discipline || 'none'}
                    onValueChange={(value) => set('discipline', String(value ?? '') === 'none' ? '' : String(value ?? ''))}
                    items={[
                      { value: 'none', label: 'Choose a sport' },
                      ...Object.entries(options.disciplines).map(([value, label]) => ({ value, label })),
                    ]}
                  >
                    <SelectTrigger aria-invalid={errors.discipline ? true : undefined}>
                      <SelectValue placeholder="Choose a sport" />
                    </SelectTrigger>
                    <SelectPopup>
                      <SelectItem value="none">Choose a sport</SelectItem>
                      {Object.entries(options.disciplines).map(([value, label]) => (
                        <SelectItem key={value} value={value}>
                          {label}
                        </SelectItem>
                      ))}
                    </SelectPopup>
                  </Select>
                  <FieldError />
                </Field>

                <Field name="activity_type" className="w-full" validate={() => (form.activity_type ? null : 'Choose an activity type.')}>
                  <FieldLabel>Activity type</FieldLabel>
                  <Select
                    value={form.activity_type || 'none'}
                    onValueChange={(value) => set('activity_type', String(value ?? '') === 'none' ? '' : String(value ?? ''))}
                    items={[
                      { value: 'none', label: 'Choose a type' },
                      ...Object.entries(options.session_types).map(([value, label]) => ({ value, label })),
                    ]}
                  >
                    <SelectTrigger aria-invalid={errors.activity_type ? true : undefined}>
                      <SelectValue placeholder="Choose a type" />
                    </SelectTrigger>
                    <SelectPopup>
                      <SelectItem value="none">Choose a type</SelectItem>
                      {Object.entries(options.session_types).map(([value, label]) => (
                        <SelectItem key={value} value={value}>
                          {label}
                        </SelectItem>
                      ))}
                    </SelectPopup>
                  </Select>
                  <FieldError />
                </Field>

                <Field name="level" className="w-full" validate={() => (form.level ? null : 'Choose a level.')}>
                  <FieldLabel>Level</FieldLabel>
                  <Select
                    value={form.level || 'none'}
                    onValueChange={(value) => set('level', String(value ?? '') === 'none' ? '' : String(value ?? ''))}
                    items={[
                      { value: 'none', label: 'Choose a level' },
                      ...Object.entries(options.levels).map(([value, label]) => ({ value, label })),
                    ]}
                  >
                    <SelectTrigger aria-invalid={errors.level ? true : undefined}>
                      <SelectValue placeholder="Choose a level" />
                    </SelectTrigger>
                    <SelectPopup>
                      <SelectItem value="none">Choose a level</SelectItem>
                      {Object.entries(options.levels).map(([value, label]) => (
                        <SelectItem key={value} value={value}>
                          {label}
                        </SelectItem>
                      ))}
                    </SelectPopup>
                  </Select>
                  <FieldError />
                </Field>

                <Field name="duration_minutes" className="w-full">
                  <FieldLabel>Duration in minutes</FieldLabel>
                  <Input
                    type="number"
                    min="1"
                    max="600"
                    step="1"
                    required
                    inputMode="numeric"
                    value={form.duration_minutes}
                    onChange={(event) => set('duration_minutes', event.target.value)}
                    placeholder="60"
                  />
                  <FieldError />
                </Field>

                <Field name="frequency" className="w-full sm:col-span-2" validate={() => (form.frequency ? null : 'Choose how often it runs.')}>
                  <FieldLabel>How often it runs</FieldLabel>
                  <Select
                    value={form.frequency || 'none'}
                    onValueChange={(value) => set('frequency', String(value ?? '') === 'none' ? '' : String(value ?? ''))}
                    items={[
                      { value: 'none', label: 'Choose a frequency' },
                      ...Object.entries(options.frequencies).map(([value, label]) => ({ value, label })),
                    ]}
                  >
                    <SelectTrigger aria-invalid={errors.frequency ? true : undefined}>
                      <SelectValue placeholder="Choose a frequency" />
                    </SelectTrigger>
                    <SelectPopup>
                      <SelectItem value="none">Choose a frequency</SelectItem>
                      {Object.entries(options.frequencies).map(([value, label]) => (
                        <SelectItem key={value} value={value}>
                          {label}
                        </SelectItem>
                      ))}
                    </SelectPopup>
                  </Select>
                  <FieldError />
                </Field>
              </CardPanel>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Overview</CardTitle>
                <CardDescription>Two to four sentences describing the session and who it is for.</CardDescription>
              </CardHeader>
              <CardPanel>
                <Field name="overview" className="w-full">
                  <FieldLabel>Overview</FieldLabel>
                  <Textarea
                    rows={6}
                    required
                    minLength={40}
                    maxLength={5000}
                    value={form.overview}
                    onChange={(event) => set('overview', event.target.value)}
                    placeholder="Learn the stance, footwork and basic punches in a small group. Every session ends with supervised pad work."
                  />
                  <FieldDescription>
                    {form.overview.trim().length} characters, {sentenceCount(form.overview)} sentences. At least 40 characters and 2 to 4 sentences.
                  </FieldDescription>
                  <FieldError />
                </Field>
              </CardPanel>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>What is included and what to bring</CardTitle>
                <CardDescription>One item per line. Both lists need at least one item before publishing.</CardDescription>
              </CardHeader>
              <CardPanel className="grid gap-5 sm:grid-cols-2">
                <Field name="included" className="w-full" validate={() => longLine(form.included)}>
                  <FieldLabel>Included</FieldLabel>
                  <Textarea
                    rows={5}
                    required
                    value={form.included}
                    onChange={(event) => set('included', event.target.value)}
                    placeholder={'Coach-led warm-up\nGloves and wraps on loan\nPad work'}
                  />
                  <FieldError />
                </Field>

                <Field name="bring" className="w-full" validate={() => longLine(form.bring)}>
                  <FieldLabel>What to bring</FieldLabel>
                  <Textarea
                    rows={5}
                    required
                    value={form.bring}
                    onChange={(event) => set('bring', event.target.value)}
                    placeholder={'Water bottle\nComfortable shoes'}
                  />
                  <FieldError />
                </Field>
              </CardPanel>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Price</CardTitle>
                <CardDescription>Set one price, or mark the service as covered by a membership.</CardDescription>
              </CardHeader>
              <CardPanel className="flex flex-col gap-5">
                <Field name="price_included" className="w-full">
                  <div className="flex w-full items-center justify-between gap-4 rounded-lg border px-3 py-2.5">
                    <div>
                      <FieldLabel>Included in membership</FieldLabel>
                      <p className="text-muted-foreground text-xs">Members pay nothing extra for this service.</p>
                    </div>
                    <Switch checked={form.price_included} onCheckedChange={(checked) => set('price_included', checked)} />
                  </div>
                  <FieldError />
                </Field>

                <Field name="price" className="w-full">
                  <FieldLabel>Price</FieldLabel>
                  <Input
                    type="number"
                    min="0"
                    step="0.01"
                    required={!form.price_included}
                    inputMode="decimal"
                    value={form.price}
                    onChange={(event) => set('price', event.target.value)}
                    disabled={form.price_included}
                    placeholder={form.price_included ? 'Included in membership' : '500.00'}
                  />
                  <FieldDescription>
                    {form.price_included
                      ? 'The price is not charged for this service.'
                      : form.price.trim() === ''
                        ? 'Add a price, or switch included in membership on.'
                        : priceLabel(form.price)}
                  </FieldDescription>
                  <FieldError />
                </Field>
              </CardPanel>
            </Card>
          </div>

          <div className="flex min-w-0 flex-col gap-6">
            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Cover photo</CardTitle>
                <CardDescription>The main image on the service card.</CardDescription>
              </CardHeader>
              <CardPanel className="flex flex-col gap-3">
                {current?.cover_photo_url ? (
                  <img
                    className="aspect-[16/9] w-full rounded-lg border object-cover"
                    src={current.cover_photo_url}
                    alt={`${current.name || 'Service'} cover photo`}
                  />
                ) : (
                  <div className="flex aspect-[16/9] items-center justify-center rounded-lg border bg-muted text-muted-foreground">
                    <ImagePlus className="size-7" aria-hidden />
                  </div>
                )}

                <Field name="cover_photo_path" className="w-full">
                  <FieldLabel>Upload cover</FieldLabel>
                  <Input
                    type="file"
                    accept="image/*"
                    disabled={!current || mediaBusy !== null}
                    onChange={uploadCover}
                    aria-label="Upload cover photo"
                  />
                  <FieldDescription>
                    {current
                      ? `Image file, up to ${Math.round(maxPhotoKb / 1024)} MB.`
                      : 'Save the service details first, then upload a cover.'}
                  </FieldDescription>
                  <FieldError />
                </Field>
                {mediaBusy === 'cover' && <Skeleton className="aspect-video w-full max-w-sm" aria-label="Uploading cover" />}
              </CardPanel>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Gallery</CardTitle>
                <CardDescription>
                  {photos.length} of {maxPhotos} gallery photos.
                </CardDescription>
              </CardHeader>
              <CardPanel className="flex flex-col gap-3">
                {photos.length > 0 ? (
                  <ul className="grid grid-cols-3 gap-2">
                    {photos.map((photo) => (
                      <li key={photo.id} className="relative">
                        <img className="aspect-square w-full rounded-md border object-cover" src={photo.url} alt="" loading="lazy" />
                        <Button
                          type="button"
                          size="icon-xs"
                          variant="destructive"
                          className="absolute end-1 top-1"
                          loading={deletingPhoto === photo.id}
                          onClick={() => removePhoto(photo)}
                          aria-label="Remove gallery photo"
                        >
                          <Trash2 aria-hidden />
                        </Button>
                      </li>
                    ))}
                  </ul>
                ) : (
                  <p className="text-muted-foreground text-sm">No gallery photos yet.</p>
                )}

                <Field name="photo" className="w-full">
                  <FieldLabel>Add a gallery photo</FieldLabel>
                  <Input
                    type="file"
                    accept="image/*"
                    disabled={!current || photos.length >= maxPhotos || mediaBusy !== null}
                    onChange={uploadGalleryPhoto}
                    aria-label="Add a gallery photo"
                  />
                  <FieldDescription>
                    {!current
                      ? 'Save the service details first, then add photos.'
                      : photos.length >= maxPhotos
                        ? 'The gallery is full. Remove a photo before adding another.'
                        : `Image file, up to ${Math.round(maxPhotoKb / 1024)} MB.`}
                  </FieldDescription>
                  <FieldError />
                </Field>
                {mediaBusy === 'gallery' && <Skeleton className="size-24" aria-label="Uploading photo" />}
              </CardPanel>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Publishing</CardTitle>
                <CardDescription>Published services appear on the public gym page.</CardDescription>
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
                    <AlertDescription>The required details and cover photo are in place.</AlertDescription>
                  </Alert>
                )}

                {published ? (
                  <Button type="button" variant="outline" loading={busy === 'unpublish'} onClick={unpublish}>
                    Unpublish service
                  </Button>
                ) : (
                  <Button type="button" loading={busy === 'publish'} disabled={missing.length > 0} onClick={publish}>
                    Publish service
                  </Button>
                )}
                <p className="text-muted-foreground text-xs">Publishing saves the current form first, then runs the server checks.</p>
              </CardPanel>
            </Card>
          </div>
        </div>

        {mediaError && (
          <Alert variant="error">
            <AlertTitle>Photo problem</AlertTitle>
            <AlertDescription>{mediaError}</AlertDescription>
          </Alert>
        )}

        <div className="flex flex-wrap justify-end gap-2 border-t pt-5">
          <Button type="button" variant="outline" onClick={onBack}>
            Cancel
          </Button>
          <Button type="submit" loading={busy === 'save'}>
            Save service
          </Button>
        </div>
      </Form>
    </div>
  )
}
