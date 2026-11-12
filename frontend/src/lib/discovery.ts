/** Shared types for gym discovery, search and athlete recommendations. */
import type { Day } from '@/lib/gyms'

export type PreferredTime = 'morning' | 'afternoon' | 'evening'

export type PublicGym = {
  id: number
  name: string
  city: string | null
  province: string | null
  disciplines: string[] | null
  tier: number
  badge: string
  distance_km?: number | null
  latitude?: number | null
  longitude?: number | null
  service_names?: string[] | null
  services?: (string | { name?: string | null })[] | null
  min_price?: string | number | null
}

export type SearchResult = {
  gyms: PublicGym[]
  total: number
}

export type Recommendation = {
  gym: PublicGym
  score: number
  reasons: string[]
  distance_km?: number | null
}

export type GymPreferences = {
  discipline: string | null
  min_price: string | null
  max_price: string | null
  preferred_times: PreferredTime[] | null
}

export type GeoResult = {
  label: string
  lat: number
  lng: number
}

export type DiscoveryTier = {
  value: string
  label: string
  badge?: string
}

/** The filter lists the discovery screen shows. The API sends either maps or lists for tiers and times. */
export type DiscoveryOptions = {
  disciplines?: Record<string, string> | null
  activity_types?: Record<string, string> | null
  tiers?: Record<string, { label?: string; badge?: string }> | DiscoveryTier[] | null
  weekdays?: Record<string, string> | null
  preferred_times?: Record<string, string> | string[] | null
}

/** Tier choices in one shape, whichever shape the API used. */
export function tierOptions(options: DiscoveryOptions | null): DiscoveryTier[] {
  const tiers = options?.tiers
  if (!tiers) return []
  if (Array.isArray(tiers)) {
    return tiers.map((tier) => ({ value: String(tier.value), label: tier.label, badge: tier.badge }))
  }
  return Object.entries(tiers).map(([value, tier]) => ({
    value,
    label: tier.label ?? `Tier ${value}`,
    badge: tier.badge,
  }))
}

/** Preferred-time choices in one shape, with a readable fallback. */
export function preferredTimeOptions(options: DiscoveryOptions | null): { value: string; label: string }[] {
  const times = options?.preferred_times
  if (Array.isArray(times)) return times.map((value) => ({ value, label: labelKey(value) }))
  if (times) return Object.entries(times).map(([value, label]) => ({ value, label }))
  return [
    { value: 'morning', label: 'Morning' },
    { value: 'afternoon', label: 'Afternoon' },
    { value: 'evening', label: 'Evening' },
  ]
}

/** "muay_thai" -> "Muay thai" when the API does not send a label. */
export function labelKey(key: string): string {
  const words = key.replaceAll('_', ' ')
  return words.charAt(0).toUpperCase() + words.slice(1)
}

export type PublicServicePhoto = {
  id: number
  url: string
  sort?: number
}

/** One service on the public page or in the workspace offer editor (R-28). */
export type PublicService = {
  id: number
  name: string
  discipline?: string | null
  activity_type?: string | null
  activity_type_label?: string | null
  overview?: string | null
  level?: string | null
  duration_minutes?: number | null
  frequency?: string | null
  included?: string[] | null
  bring?: string[] | null
  price?: string | number | null
  price_included?: boolean
  cover_photo_path?: string | null
  cover_photo_url?: string | null
  photos?: PublicServicePhoto[] | null
  photo_urls?: string[] | null
  published_at?: string | null
}

export type PublicPlanAddon = {
  id?: number
  service_id?: number | null
  service_name?: string | null
  service?: { name?: string | null } | null
  price: string | number
  included?: boolean
}

/** One membership plan on the public page or in the workspace offer editor (R-29). */
export type PublicPlan = {
  id: number
  name: string
  billing_type: string
  duration_months?: number | null
  session_count?: number | null
  total_fee: string | number
  max_prepay_terms?: number
  prepay_discount_percent?: string | number
  registration_included?: boolean
  inclusions?: string[] | null
  policy_validity?: string | null
  policy_freezing?: string | null
  policy_renewal?: string | null
  policy_cancellation?: string | null
  max_freeze_days?: number | null
  refundable?: boolean
  addons?: PublicPlanAddon[] | null
  published_at?: string | null
}

export type PublicCoachPayload = {
  id: number
  first_name?: string | null
  last_name?: string | null
  name?: string | null
  photo_url?: string | null
  coach_photo_url?: string | null
  coach_rank?: string | null
  coach_experience?: string | null
  coach_specialisation?: string | null
  coach_bio?: string | null
  profile?: {
    coach_photo_url?: string | null
    coach_rank?: string | null
    coach_experience?: string | null
    coach_specialisation?: string | null
    coach_bio?: string | null
  } | null
}

/** A coach as the public gym page renders them. */
export type PublicCoach = {
  id: number
  name: string
  photo_url: string | null
  rank: string | null
  specialisation: string | null
  experience: string | null
  bio: string | null
}

export type GymAddress = {
  address_line: string | null
  barangay: string | null
  city: string | null
  province: string | null
  zip_code: string | null
  latitude: number | null
  longitude: number | null
  contact_phone: string | null
  contact_email: string | null
}

/** The public gym response. Some fields may sit at the top level or inside `profile`. */
export type GymDetailPayload = Partial<GymAddress> & {
  id: number
  name: string
  tier?: number | null
  approved_tier?: number | null
  badge?: string | null
  profile?: Partial<GymAddress> | null
  operating_hours?: Partial<Record<Day, { open: string; close: string }>> | null
  disciplines?: string[] | null
  activity_types?: string[] | null
  facilities?: string[] | null
  policies?: string | null
  services?: PublicService[] | null
  plans?: PublicPlan[] | null
  coaches?: PublicCoachPayload[] | null
  staff?: PublicCoachPayload[] | null
  employees?: PublicCoachPayload[] | null
}

export type PublicGymDetail = GymAddress & {
  id: number
  name: string
  tier: number | null
  badge: string | null
  operating_hours: Partial<Record<Day, { open: string; close: string }>>
  disciplines: string[]
  activity_types: string[]
  facilities: string[]
  policies: string | null
  services: PublicService[]
  plans: PublicPlan[]
  coaches: PublicCoach[]
}

function readCoach(coach: PublicCoachPayload): PublicCoach {
  const profile = coach.profile ?? {}
  const name =
    coach.name?.trim() ||
    [coach.first_name, coach.last_name].filter(Boolean).join(' ').trim() ||
    'Coach'

  return {
    id: coach.id,
    name,
    photo_url: coach.photo_url ?? coach.coach_photo_url ?? profile.coach_photo_url ?? null,
    rank: profile.coach_rank ?? coach.coach_rank ?? null,
    specialisation: profile.coach_specialisation ?? coach.coach_specialisation ?? null,
    experience: profile.coach_experience ?? coach.coach_experience ?? null,
    bio: profile.coach_bio ?? coach.coach_bio ?? null,
  }
}

/** Puts the public gym response in the one shape the page renders. */
export function readGymDetail(payload: GymDetailPayload): PublicGymDetail {
  const profile = payload.profile ?? {}
  const tier = payload.approved_tier ?? payload.tier ?? null
  const coaches = payload.coaches ?? payload.staff ?? payload.employees ?? []

  return {
    id: payload.id,
    name: payload.name,
    tier,
    badge: payload.badge ?? (tier ? `Tier ${tier}` : null),
    address_line: payload.address_line ?? profile.address_line ?? null,
    barangay: payload.barangay ?? profile.barangay ?? null,
    city: payload.city ?? profile.city ?? null,
    province: payload.province ?? profile.province ?? null,
    zip_code: payload.zip_code ?? profile.zip_code ?? null,
    latitude: payload.latitude ?? profile.latitude ?? null,
    longitude: payload.longitude ?? profile.longitude ?? null,
    contact_phone: payload.contact_phone ?? profile.contact_phone ?? null,
    contact_email: payload.contact_email ?? profile.contact_email ?? null,
    operating_hours: payload.operating_hours ?? {},
    disciplines: payload.disciplines ?? [],
    activity_types: payload.activity_types ?? [],
    facilities: payload.facilities ?? [],
    policies: payload.policies ?? null,
    services: payload.services ?? [],
    plans: payload.plans ?? [],
    coaches: coaches.map(readCoach),
  }
}

export function planLength(plan: PublicPlan): string {
  if (plan.billing_type === 'sessions') {
    return `${plan.session_count ?? 0} ${plan.session_count === 1 ? 'session' : 'sessions'}`
  }
  return `${plan.duration_months ?? 0} ${plan.duration_months === 1 ? 'month' : 'months'}`
}
