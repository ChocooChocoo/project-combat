// Shapes and helpers for memberships, payments and athlete records (R-31 to R-41).

export type MembershipStatus = 'applied' | 'rejected' | 'awaiting_payment' | 'active' | 'frozen' | 'expired' | 'cancelled'

export type EmergencyContact = { name: string; relationship: string; phone: string }

export type HealthDetails = {
  emergency_contacts: EmergencyContact[]
  answers: Record<string, boolean>
  notes: string | null
  medical_file_name: string | null
}

export type Payment = {
  id: number
  membership_id: number | null
  member: { id: number; name: string; email: string } | null
  amount: string
  method: 'cash' | 'card' | 'ewallet' | 'paymongo'
  receipt_number: string | null
  received_by: string | null
  paid_at: string
  note: string | null
  corrects_id: number | null
}

export type HistoryEvent = { id: number; type: string; note: string | null; by: string | null; at: string }

export type Membership = {
  id: number
  gym_id: number
  gym_name: string
  status: MembershipStatus
  member: { id: number; name: string; email: string }
  plan: {
    id: number
    name: string
    billing_type: 'time' | 'sessions'
    duration_months: number | null
    session_count: number | null
    total_fee: string
    inclusions: string[]
    max_freeze_days: number | null
    refundable: boolean
    policy_freezing: string | null
    policy_renewal: string | null
    policy_cancellation: string | null
  } | null
  background: string | null
  current_rank: string | null
  training_history: string | null
  signature_name: string
  signed_on: string
  amount_charged: string
  amount_paid: string
  balance: string
  starts_on: string | null
  ends_on: string | null
  sessions_remaining: number | null
  freeze_days_used: number
  frozen_until: string | null
  decided_by: string | null
  decided_at: string | null
  decision_reason: string | null
  created_at: string
  health?: HealthDetails
  payments?: Payment[]
  history?: HistoryEvent[]
}

export type AthleteEntry = {
  id: number
  type: 'promotion' | 'assessment' | 'competition' | 'achievement'
  discipline: string | null
  title: string
  division: string | null
  result: string | null
  occurred_on: string
  notes: string | null
  recorded_by: string | null
}

export type AthleteRecord = {
  athlete: { id: number; name: string; email: string; phone: string | null }
  background: string | null
  starting_rank: string | null
  training_history: string | null
  ranks: { discipline: string; rank: string; since: string }[]
  entries: AthleteEntry[]
  memberships: Membership[]
  health?: HealthDetails
}

export type MembershipForm = {
  health_questions: Record<string, string>
  waivers: Record<string, string>
  emergency_contacts_max: number
  medical_file: { mimes: string[]; max_kb: number }
}

export const STATUS_LABELS: Record<MembershipStatus, string> = {
  applied: 'Waiting for decision',
  rejected: 'Not approved',
  awaiting_payment: 'Waiting for payment',
  active: 'Active',
  frozen: 'Frozen',
  expired: 'Expired',
  cancelled: 'Cancelled',
}

export const STATUS_VARIANTS: Record<MembershipStatus, 'warning' | 'success' | 'error' | 'outline' | 'info' | 'secondary'> = {
  applied: 'warning',
  rejected: 'error',
  awaiting_payment: 'warning',
  active: 'success',
  frozen: 'info',
  expired: 'outline',
  cancelled: 'outline',
}

/** A membership counts as current while it is waiting, active or frozen. */
export const LIVE_STATUSES: MembershipStatus[] = ['applied', 'awaiting_payment', 'active', 'frozen']

export const METHOD_LABELS: Record<Payment['method'], string> = {
  cash: 'Cash',
  card: 'Card',
  ewallet: 'E-wallet',
  paymongo: 'Online (PayMongo)',
}

export const EVENT_LABELS: Record<string, string> = {
  applied: 'Applied',
  approved: 'Approved',
  rejected: 'Not approved',
  activated: 'Became active',
  payment: 'Payment',
  refund: 'Refund',
  correction: 'Payment corrected',
  renewed: 'Renewed',
  frozen: 'Frozen',
  unfrozen: 'Unfrozen',
  expired: 'Expired',
  cancelled: 'Cancelled',
}

export const ENTRY_LABELS: Record<AthleteEntry['type'], string> = {
  promotion: 'Promotion',
  assessment: 'Skill assessment',
  competition: 'Competition',
  achievement: 'Achievement',
}

/** Only real desk payments can be corrected, once (R-34). */
export function correctable(payment: Payment, all: Payment[]): boolean {
  return Number(payment.amount) > 0 && payment.method !== 'paymongo' && !all.some((other) => other.corrects_id === payment.id)
}

export function money(value: string | number | null | undefined): string {
  const amount = Number(value ?? 0)
  if (!Number.isFinite(amount)) return ''
  const text = Math.abs(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  return amount < 0 ? `−₱${text}` : `₱${text}`
}

/** "Valid until 4 Dec 2026", "8 sessions left", or nothing before activation. */
export function periodLabel(membership: Membership): string | null {
  if (membership.ends_on) return `Valid until ${dateLabel(membership.ends_on)}`
  if (membership.sessions_remaining !== null) return `${membership.sessions_remaining} ${membership.sessions_remaining === 1 ? 'session' : 'sessions'} left`
  return null
}

export function dateLabel(value: string | null | undefined): string {
  if (!value) return ''
  const date = new Date(value.length === 10 ? `${value}T00:00:00` : value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('en-PH', { day: 'numeric', month: 'short', year: 'numeric' })
}

export function dateTimeLabel(value: string | null | undefined): string {
  if (!value) return ''
  const date = new Date(value)
  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleString('en-PH', { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' })
}

/** Appends nested values the way Laravel reads them: contacts[0][name], answers[key]. Booleans become 1 or 0. */
export function appendForm(form: FormData, key: string, value: unknown): void {
  if (value === null || value === undefined) return
  if (value instanceof File) form.append(key, value)
  else if (Array.isArray(value)) value.forEach((item, index) => appendForm(form, `${key}[${index}]`, item))
  else if (typeof value === 'object') Object.entries(value).forEach(([child, item]) => appendForm(form, `${key}[${child}]`, item))
  else if (typeof value === 'boolean') form.append(key, value ? '1' : '0')
  else form.append(key, String(value))
}
