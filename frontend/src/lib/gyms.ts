import { useEffect, useState } from 'react'
import { api } from '@/lib/api'
import { todayIso } from '@/lib/document-check'

/** Shared types and helpers for gym applications (R-11 to R-18). */

export type Day = 'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun'
export const DAYS: { key: Day; label: string }[] = [
  { key: 'mon', label: 'Monday' },
  { key: 'tue', label: 'Tuesday' },
  { key: 'wed', label: 'Wednesday' },
  { key: 'thu', label: 'Thursday' },
  { key: 'fri', label: 'Friday' },
  { key: 'sat', label: 'Saturday' },
  { key: 'sun', label: 'Sunday' },
]

export type ApplicationStatus = 'draft' | 'submitted' | 'under_review' | 'returned' | 'approved' | 'rejected'

export type GymDocument = { id: number; type: string; original_name: string; mime: string; size: number; admin_viewed_at?: string | null; expires_on?: string | null; ocr_flags?: string[] | null }

export type Application = {
  id: number
  name: string
  status: ApplicationStatus
  tier: number | null
  approved_tier: number | null
  address_line: string | null
  barangay: string | null
  city: string | null
  province: string | null
  zip_code: string | null
  latitude: number | null
  longitude: number | null
  contact_phone: string | null
  contact_email: string | null
  operating_hours: Partial<Record<Day, { open: string; close: string }>> | null
  disciplines: string[] | null
  services: string[] | null
  facilities: string[] | null
  submitted_at: string | null
  decision_reason: string | null
  return_items: string[] | null
  checklist: Record<string, boolean | null> | null
  documents: GymDocument[]
}

export type Requirement = { types: string[]; min: number }

export type Requirements = {
  documents: Record<string, { label: string; proves: string; who: string; max?: number; kind?: string; must_open?: boolean; expiry?: 'required' | 'optional'; match?: string[] }>
  kinds: Record<string, { mimes: string[]; max_kb: number }>
  tiers: Record<string, { label: string; badge: string; requirements: Requirement[] }>
  checklist: Record<string, { label: string; tiers: number[] }>
  upload_rules: string[]
  disciplines: Record<string, string>
}

/** The tier lists and file rules from the backend, so screens never hard-code them. */
export function useRequirements(): Requirements | null {
  const [requirements, setRequirements] = useState<Requirements | null>(null)
  useEffect(() => {
    api<Requirements>('/gym-applications/requirements').then(setRequirements).catch(() => undefined)
  }, [])
  return requirements
}

export const typesFor = (requirements: Requirements, tier: number | null): string[] =>
  tier ? requirements.tiers[tier].requirements.flatMap((r) => r.types) : []

/** Files the admin must open before the checklist; same rule as Verification::unopened() on the server (R-132). */
export const mustOpen = (app: Application, requirements: Requirements): GymDocument[] =>
  app.documents.filter((d) => typesFor(requirements, app.tier).includes(d.type) && requirements.documents[d.type]?.must_open !== false)

export const documentUrl = (gymId: number, documentId: number) => `/gym-applications/${gymId}/documents/${documentId}`

export const editable = (status: ApplicationStatus) => status === 'draft' || status === 'returned'

/**
 * What is still missing, as the screens see it. Mirrors the server check in
 * GymApplicationController::submit, which stays the authority.
 */
export function documentsMissing(app: Application, requirements: Requirements): string[] {
  if (!app.tier) return ['Choose a tier first.']
  return requirements.tiers[app.tier].requirements.flatMap((req) => {
    const have = app.documents.filter((d) => req.types.includes(d.type)).length
    const labels = req.types.map((t) => requirements.documents[t].label).join(' or ')
    if (have >= req.min) return []
    return [req.min > 1 ? `Upload at least ${req.min} ${labels} (${have} so far).` : `Upload the ${labels}.`]
  }).concat(expiryProblems(app, requirements))
}

/** (R-133) Required expiry dates that are missing or past; the server checks the same on submit. */
function expiryProblems(app: Application, requirements: Requirements): string[] {
  const today = todayIso()
  return app.documents
    .filter((d) => typesFor(requirements, app.tier).includes(d.type) && requirements.documents[d.type]?.expiry === 'required')
    .flatMap((d) => {
      const label = requirements.documents[d.type].label
      if (!d.expires_on) return [`Add the expiry date of the ${label}.`]
      return d.expires_on < today ? [`The ${label} has expired.`] : []
    })
}
