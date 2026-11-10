import { useEffect, useState } from 'react'
import { api } from '@/lib/api'

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

export type GymDocument = { id: number; type: string; original_name: string; mime: string; size: number }

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
  documents: Record<string, { label: string; proves: string; who: string; max?: number; kind?: string }>
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
  })
}
