/** Shapes and display helpers for the public landing page. */

export type LandingStats = {
  gyms: number
  cities: number
  disciplines: number
  services: number
  plans: number
  coaches: number
  upcoming_sessions: number
  plan_fee_min: string | null
  plan_fee_max: string | null
}

export type LandingGym = {
  id: number
  name: string
  city: string | null
  province: string | null
  tier: number | null
  badge: string | null
  disciplines: string[]
  services: number
  plans: number
  plan_from: string | null
}

export type LandingDiscipline = {
  key: string
  label: string
  gyms: number
  services: number
  from: string | null
}

export type LandingPlan = {
  id: number
  gym_id: number
  gym_name: string
  name: string
  billing_type: 'time' | 'sessions' | null
  duration_months: number | null
  session_count: number | null
  total_fee: string
  inclusions: string[]
}

export type LandingCoach = {
  id: number
  name: string
  rank: string | null
  specialisation: string | null
  gym_id: number
  gym_name: string
}

export type LandingSession = {
  id: number
  gym_name: string
  name: string
  discipline: string | null
  starts_at: string
  ends_at: string
  capacity: number
}

export type LandingPayload = {
  stats: LandingStats
  gyms: LandingGym[]
  disciplines: LandingDiscipline[]
  plans: LandingPlan[]
  plan_inclusions: string[]
  coaches: LandingCoach[]
  sessions: LandingSession[]
}

export const DISCIPLINE_LABELS: Record<string, string> = {
  boxing: 'Boxing',
  muay_thai: 'Muay Thai',
  kickboxing: 'Kickboxing',
  mma: 'Mixed martial arts',
  bjj: 'Brazilian jiu-jitsu',
  wrestling: 'Wrestling',
  judo: 'Judo',
  karate: 'Karate',
  taekwondo: 'Taekwondo',
  arnis: 'Arnis',
}

export function disciplineLabel(key: string): string {
  return DISCIPLINE_LABELS[key] ?? key.replaceAll('_', ' ')
}

/** ₱1,500 for whole fees, ₱1,500.50 when a plan carries centavos. */
export function peso(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return ''
  const amount = Number(value)
  if (!Number.isFinite(amount)) return ''

  const whole = Number.isInteger(amount)
  return `₱${amount.toLocaleString('en-PH', {
    minimumFractionDigits: whole ? 0 : 2,
    maximumFractionDigits: 2,
  })}`
}

export function feeRange(stats: LandingStats | null | undefined): string {
  if (!stats?.plan_fee_min || !stats.plan_fee_max) return ''
  return `${peso(stats.plan_fee_min)}-${peso(stats.plan_fee_max)}`
}

export function planLength(plan: LandingPlan): string {
  if (plan.billing_type === 'sessions') {
    return `${plan.session_count ?? 0} ${plan.session_count === 1 ? 'session' : 'sessions'}`
  }
  return `${plan.duration_months ?? 0} ${plan.duration_months === 1 ? 'month' : 'months'}`
}

const DATE_TIME = new Intl.DateTimeFormat('en-PH', {
  timeZone: 'Asia/Manila',
  weekday: 'short',
  month: 'short',
  day: 'numeric',
  hour: 'numeric',
  minute: '2-digit',
})

export function sessionTime(value: string): string {
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : DATE_TIME.format(date)
}

/** Pexels serves the same photo at any size; keep the request small and the crop fixed. */
export function photoUrl(id: number, width: number, height?: number): string {
  const size = height ? `&h=${height}&fit=crop` : ''
  return `https://images.pexels.com/photos/${id}/pexels-photo-${id}.jpeg?auto=compress&cs=tinysrgb&w=${width}${size}`
}

export const PHOTOS = {
  hero: {
    id: 35045523,
    alt: 'A boxer training on a heavy bag under warm gym light',
    width: 1000,
    height: 1250,
  },
  muayThai: {
    id: 39969614,
    alt: 'Two athletes sparring Muay Thai in a smoky gym',
    width: 900,
    height: 620,
  },
  bjj: {
    id: 38678590,
    alt: 'Two athletes grappling in gi on a training mat',
    width: 640,
    height: 620,
  },
  karate: {
    id: 7045571,
    alt: 'An instructor and a young student practising karate in a dojo',
    width: 480,
    height: 620,
  },
  coach: {
    id: 38510139,
    alt: 'Black and white photograph of a boxer in a gym',
    width: 760,
    height: 950,
  },
  closing: {
    id: 8611244,
    alt: 'An empty boxing gym with a ring and heavy bags',
    width: 1800,
    height: 520,
  },
} as const

/** Pexels asks for no attribution, but the page still names where the photography came from. */
export const PHOTO_CREDITS = [
  { id: PHOTOS.hero.id, label: 'Boxing training' },
  { id: PHOTOS.muayThai.id, label: 'Muay Thai sparring' },
  { id: PHOTOS.bjj.id, label: 'Brazilian jiu-jitsu' },
  { id: PHOTOS.karate.id, label: 'Karate practice' },
  { id: PHOTOS.coach.id, label: 'Boxing gym portrait' },
  { id: PHOTOS.closing.id, label: 'Boxing gym interior' },
]
