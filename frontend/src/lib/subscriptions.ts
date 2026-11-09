/** R-102 to R-107: platform plans a gym pays for, and the gym's current plan state. */

export type SubscriptionModule = { key: string; label: string }

export type SubscriptionPlan = {
  id: number
  name: string
  monthly_price: string
  inclusions: string | null
  modules: string[]
  is_free: boolean
  retired: boolean
  paying_gyms?: number
}

export type SubscriptionStatus = 'unmanaged' | 'trial' | 'paid' | 'free'

/** The short version shipped with each gym on the signed-in user. */
export type GymSubscriptionSummary = {
  status: SubscriptionStatus
  plan_name: string | null
  locked_modules: string[]
  period_ends_at: string | null
}

export type GymSubscription = {
  status: SubscriptionStatus
  plan: SubscriptionPlan | null
  modules: string[]
  locked_modules: string[]
  trial_ends_at: string | null
  paid_until: string | null
  paid_plan_name: string | null
  period_ends_at: string | null
}

export type SubscriptionPayment = {
  id: number
  plan_name: string
  amount: string
  period_starts_at: string
  period_ends_at: string
  paid_at: string
}

export type GymSubscriptionResponse = {
  subscription: GymSubscription
  plans: SubscriptionPlan[]
  modules: SubscriptionModule[]
  payments: SubscriptionPayment[]
}

export type AdminPlansResponse = { plans: SubscriptionPlan[]; modules: SubscriptionModule[]; trial_days: number }

export const statusLabels: Record<SubscriptionStatus, string> = {
  unmanaged: 'No plan needed',
  trial: 'Free trial',
  paid: 'Paid plan',
  free: 'Free plan',
}

/** Whole days from now until the given time, never below 0. */
export function daysLeft(value: string | null, now = Date.now()): number | null {
  if (!value) return null
  const end = new Date(value).getTime()
  if (Number.isNaN(end)) return null
  return Math.max(0, Math.ceil((end - now) / 86_400_000))
}

/** "₱1,499" for whole pesos, "₱1,499.50" otherwise, "₱0" for free. */
export function planPrice(value: string | number): string {
  const amount = Number(value)
  if (!Number.isFinite(amount)) return ''
  return `₱${amount.toLocaleString('en-PH', { minimumFractionDigits: Number.isInteger(amount) ? 0 : 2, maximumFractionDigits: 2 })}`
}
