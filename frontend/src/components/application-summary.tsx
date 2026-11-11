import { Check, Circle, FileText, Film, MapPin, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { toastManager } from '@/components/ui/toast'
import { apiBlobUrl, apiFile } from '@/lib/api'
import {
  type Application,
  type ApplicationStatus,
  DAYS,
  documentUrl,
  type GymDocument,
  type Requirements,
  typesFor,
} from '@/lib/gyms'

const STATUS: Record<ApplicationStatus, { label: string; variant: 'secondary' | 'info' | 'warning' | 'success' | 'error' }> = {
  draft: { label: 'Draft', variant: 'secondary' },
  submitted: { label: 'Sent', variant: 'info' },
  under_review: { label: 'Under review', variant: 'info' },
  returned: { label: 'Returned for correction', variant: 'warning' },
  approved: { label: 'Approved', variant: 'success' },
  rejected: { label: 'Rejected', variant: 'error' },
}

export function StatusBadge({ status }: { status: ApplicationStatus }) {
  return <Badge variant={STATUS[status].variant}>{STATUS[status].label}</Badge>
}

/** Where an application is in its life: draft, sent, under review, decided. */
export function StatusTrack({ status }: { status: ApplicationStatus }) {
  const decided = status === 'approved' || status === 'rejected' || status === 'returned'
  const steps = [
    { label: 'Draft', done: status !== 'draft', current: status === 'draft' },
    { label: 'Sent', done: status !== 'draft' && status !== 'submitted', current: status === 'submitted' },
    { label: 'Under review', done: decided, current: status === 'under_review' },
    { label: decided ? STATUS[status].label : 'Decision', done: status === 'approved', current: decided },
  ]

  return (
    <ol className="flex flex-wrap items-center gap-x-2 gap-y-2 text-sm" aria-label="Application progress">
      {steps.map((step, index) => (
        <li key={step.label} className="flex items-center gap-2" aria-current={step.current ? 'step' : undefined}>
          {index > 0 && <span aria-hidden className="h-px w-6 bg-border" />}
          <span
            className={`flex items-center gap-1.5 ${step.current ? 'font-medium text-foreground' : step.done ? 'text-foreground' : 'text-muted-foreground'}`}
          >
            {step.done ? <Check className="size-4 text-success-foreground" aria-hidden /> : <Circle className={`size-3.5 ${step.current ? 'fill-current' : ''}`} aria-hidden />}
            {step.label}
          </span>
        </li>
      ))}
    </ol>
  )
}

const row = (label: string, value: React.ReactNode) => (
  <div className="grid gap-1 py-3 @md:grid-cols-[10rem_1fr] @md:gap-4">
    <dt className="text-muted-foreground text-sm">{label}</dt>
    <dd className="min-w-0 text-sm wrap-anywhere">{value || <span className="text-muted-foreground">Not given</span>}</dd>
  </div>
)

/** Read-only gym profile, for the applicant (R-14) and the administrator (R-16). */
export function ProfileSummary({ app, requirements }: { app: Application; requirements: Requirements }) {
  const hours = DAYS.filter(({ key }) => app.operating_hours?.[key]).map(({ key, label }) => ({
    label,
    time: app.operating_hours![key]!.open === '00:00' && app.operating_hours![key]!.close === '00:00' ? 'Open 24 hours' : `${app.operating_hours![key]!.open}–${app.operating_hours![key]!.close}`,
  }))

  return (
    <dl className="@container divide-y">
      {row('Gym name', app.name)}
      {row('Address', [app.address_line, app.barangay, app.city, app.province, app.zip_code].filter(Boolean).join(', '))}
      {row(
        'Map location',
        app.latitude != null && app.longitude != null && (
          <a
            className="inline-flex items-center gap-1.5 underline underline-offset-4"
            href={`https://www.openstreetmap.org/?mlat=${app.latitude}&mlon=${app.longitude}#map=17/${app.latitude}/${app.longitude}`}
            target="_blank"
            rel="noreferrer"
          >
            <MapPin className="size-4" aria-hidden />
            <span className="tabular-nums">{app.latitude.toFixed(5)}, {app.longitude.toFixed(5)}</span>
          </a>
        ),
      )}
      {row('Contact', [app.contact_phone, app.contact_email].filter(Boolean).join(' · '))}
      {row(
        'Operating hours',
        hours.length > 0 && (
          <ul className="grid gap-0.5">
            {hours.map((h) => (
              <li key={h.label} className="flex gap-3">
                <span className="w-24">{h.label}</span>
                <span className="tabular-nums">{h.time}</span>
              </li>
            ))}
          </ul>
        ),
      )}
      {row(
        'Combat sports',
        (app.disciplines ?? []).length > 0 && (
          <span className="flex flex-wrap gap-1.5">
            {(app.disciplines ?? []).map((d) => <Badge key={d} variant="outline">{requirements.disciplines[d] ?? d}</Badge>)}
          </span>
        ),
      )}
      {row('Services', (app.services ?? []).join(', '))}
      {row('Facilities', (app.facilities ?? []).join(', '))}
      {row('Tier', app.tier && requirements.tiers[app.tier].label)}
    </dl>
  )
}

const sizeText = (bytes: number) => (bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`)

/** One uploaded file. Images show a preview, loaded through the private route (R-15). */
export function FileTile({ gymId, file, onRemove }: { gymId: number; file: GymDocument; onRemove?: (id: number) => void }) {
  const isImage = file.mime.startsWith('image/')
  const [preview, setPreview] = useState<string | null>(null)

  useEffect(() => {
    if (!isImage) return
    let url = ''
    let live = true
    apiBlobUrl(documentUrl(gymId, file.id))
      .then((u) => {
        url = u
        if (live) setPreview(u)
        else URL.revokeObjectURL(u)
      })
      .catch(() => undefined)
    return () => {
      live = false
      if (url) URL.revokeObjectURL(url)
    }
  }, [gymId, file.id, isImage])

  const open = () =>
    apiFile(documentUrl(gymId, file.id)).catch(() => toastManager.add({ title: 'The file could not be opened.', type: 'error' }))
  const Icon = file.mime.startsWith('video/') ? Film : FileText

  return (
    <li className="group flex items-center gap-3 rounded-lg border bg-background p-2">
      <button
        type="button"
        onClick={open}
        aria-label={`Open ${file.original_name}`}
        className="flex min-w-0 flex-1 items-center gap-3 rounded-md text-left outline-none focus-visible:ring-2 focus-visible:ring-ring"
      >
        <span className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted">
          {preview ? <img src={preview} alt="" className="size-full object-cover" /> : <Icon className="size-5 text-muted-foreground" aria-hidden />}
        </span>
        <span className="grid min-w-0">
          <span className="truncate font-medium text-sm group-hover:underline">{file.original_name}</span>
          <span className="text-muted-foreground text-xs">{sizeText(file.size)}</span>
        </span>
      </button>
      {onRemove && (
        <Button type="button" variant="ghost" size="icon-sm" aria-label={`Delete ${file.original_name}`} onClick={() => onRemove(file.id)}>
          <Trash2 aria-hidden />
        </Button>
      )}
    </li>
  )
}

/** Every uploaded file, grouped by document, each openable through the private route (R-15). */
export function DocumentSummary({ app, requirements }: { app: Application; requirements: Requirements }) {
  const needed = typesFor(requirements, app.tier)
  const types = [...needed, ...new Set(app.documents.map((d) => d.type).filter((t) => !needed.includes(t)))]
  // For "DTI or SEC", hide the one not sent once the other is there.
  const otherChoiceSent = (type: string) =>
    (app.tier ? requirements.tiers[app.tier].requirements : []).some(
      (r) => r.types.includes(type) && r.types.some((t) => t !== type && app.documents.some((d) => d.type === t)),
    )

  return (
    <div className="flex flex-col divide-y">
      {types.map((type) => {
        const files = app.documents.filter((d) => d.type === type)
        if (files.length === 0 && (!needed.includes(type) || otherChoiceSent(type))) return null
        return (
          <section key={type} className="flex flex-col gap-2 py-4 first:pt-0 last:pb-0">
            <h3 className="flex flex-wrap items-center gap-2 font-medium text-sm">
              {requirements.documents[type]?.label ?? type}
              {files.length > 1 && <span className="font-normal text-muted-foreground tabular-nums">{files.length} files</span>}
              {!needed.includes(type) && <Badge variant="outline">Not needed for this tier</Badge>}
            </h3>
            {files.length === 0 ? (
              <p className="text-muted-foreground text-sm">Not uploaded</p>
            ) : (
              <ul className={files.length > 2 ? 'grid gap-2 sm:grid-cols-2' : 'grid gap-2'}>
                {files.map((file) => <FileTile key={file.id} gymId={app.id} file={file} />)}
              </ul>
            )}
          </section>
        )
      })}
    </div>
  )
}
