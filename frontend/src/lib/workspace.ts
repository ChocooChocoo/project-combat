import type { AccessLevel } from '@/lib/gym-access'
import type { Day } from '@/lib/gyms'

/** Shared types for the Phase 3 gym workspace responses. */

export type PayBasis = 'monthly' | 'per_session' | 'both'
export type LeaveRequestStatus = 'pending' | 'approved' | 'rejected' | 'cancelled'
export type PlanBilling = 'time' | 'sessions'

export type WorkingHours = Partial<Record<Day, { open: string; close: string }>>

export type GymSetup = {
  id: number
  name: string
  tier: number | null
  badge: string | null
  address_line: string | null
  barangay: string | null
  city: string | null
  province: string | null
  latitude: number | null
  longitude: number | null
  contact_phone: string | null
  contact_email: string | null
  operating_hours: WorkingHours | null
  disciplines: string[] | null
  services: string[] | null
  facilities: string[] | null
  activity_types: string[] | null
  working_days: Day[] | null
  policies: string | null
}

export type LeaveTypeOption = { label: string; days: number; paid: boolean }

export type GymOptions = {
  weekdays: Record<Day, string>
  leave_types: Record<string, LeaveTypeOption>
  pay_bases: Record<PayBasis, string>
  activity_types: Record<string, string>
  levels: Record<string, string>
  frequencies: Record<string, string>
  plan_billing: Record<PlanBilling, string>
  attachment: { mimes: string[]; max_kb: number }
  service_photos: { max: number; max_kb: number }
}

export type Room = {
  id: number
  name: string
  is_active: boolean
}

export type EmployeeProfile = {
  job_title: string | null
  started_on: string | null
  pay_basis: PayBasis | null
  monthly_rate?: string | null
  per_session_rate?: string | null
  coach_photo_url: string | null
  coach_experience: string | null
  coach_rank: string | null
  coach_specialisation: string | null
  coach_bio: string | null
  coach_public: boolean
}

export type WorkSchedule = {
  id: number
  user_id: number
  weekday: Day
  start_time: string | null
  end_time: string | null
}

export type Employee = {
  id: number
  first_name: string
  last_name: string
  email: string
  phone: string | null
  photo_url: string | null
  roles: string[]
  profile: EmployeeProfile | null
  schedule: WorkSchedule[]
}

export type EmployeeSchedule = {
  schedule: WorkSchedule[]
  working_days: Day[]
}

export type LeaveRequest = {
  id: number
  user_id: number
  user: { id: number; first_name: string; last_name: string; photo_url: string | null } | null
  type: string
  starts_on: string
  ends_on: string
  working_days: number
  reason: string | null
  status: LeaveRequestStatus
  attachment_name: string | null
  attachment_mime: string | null
  attachment_size: number | null
  decided_at: string | null
  decision_note: string | null
  created_at: string
}

export type LeaveBalance = {
  id: number
  user_id: number
  user: { id: number; first_name: string; last_name: string } | null
  type: string
  year: number
  allowance: number
  used?: number
  remaining?: number
}

export type Holiday = {
  id: number
  date: string
  name: string
}

export type GymServicePhoto = {
  id: number
  url: string
  sort: number
}

export type GymService = {
  id: number
  name: string
  discipline: string
  activity_type: string
  overview: string | null
  level: string
  duration_minutes: number
  frequency: string
  included: string[] | null
  bring: string[] | null
  price: string | null
  price_included: boolean
  cover_photo_url: string | null
  published_at: string | null
  photos: GymServicePhoto[]
}

export type MembershipPlanAddon = {
  id: number
  gym_service_id: number
  price: string
  service: GymService | null
}

export type MembershipPlan = {
  id: number
  name: string
  billing_type: PlanBilling
  duration_months: number | null
  session_count: number | null
  total_fee: string
  registration_included: boolean
  inclusions: string[] | null
  policy_validity: string | null
  policy_freezing: string | null
  policy_renewal: string | null
  policy_cancellation: string | null
  max_freeze_days: number | null
  refundable: boolean
  published_at: string | null
  addons: MembershipPlanAddon[]
}

/** The setup response groups the address fields, so the form does not guess them apart. */
export type GymSetupProfile = {
  address_line: string | null
  barangay: string | null
  city: string | null
  province: string | null
  latitude: number | null
  longitude: number | null
  contact_phone: string | null
  contact_email: string | null
}

/** The option lists the workspace screens fill their selects and checkboxes from. */
export type WorkspaceOptions = {
  disciplines: Record<string, string>
  activity_types: Record<string, string>
  weekdays: Record<Day, string>
  working_days: Day[]
  default_working_days: Day[]
  pay_bases: Record<PayBasis, string>
  leave_types: Record<string, LeaveTypeOption>
  levels: Record<string, string>
  frequencies: Record<string, string>
  plan_billing: Record<PlanBilling, string>
  service_photos: { max: number; max_kb: number }
}

export type WorkspaceSetup = {
  id: number
  name: string
  status: string
  tier: number | null
  approved_tier: number | null
  badge: string | null
  profile: GymSetupProfile
  disciplines: string[]
  activity_types: string[]
  operating_hours: WorkingHours
  working_days: Day[]
  policies: string | null
  booking_approval: boolean
  late_grace_minutes: number
  cancel_cutoff_hours: number
  services: string[]
  facilities: string[]
  options?: WorkspaceOptions
}

/** A staff row may carry extra HR fields the employee record returns with it. */
export type StaffMember = Employee & {
  role_ids?: number[]
  coach?: boolean
  is_coach?: boolean
  balances?: EmployeeLeaveBalance[]
  approved_leave_dates?: string[]
}

/** The owner is shown beside employees but is not one: no employment record, no removal. */
export type StaffOwner = {
  id: number
  first_name: string
  last_name: string
  email: string
  phone: string | null
  photo_url: string | null
}

export type StaffList = {
  staff: StaffMember[]
  owner: StaffOwner | null
}

/** One leave type with what is left for the year, as the employee record carries it. */
export type EmployeeLeaveBalance = {
  type: string
  label: string
  allowance: number
  used: number
  remaining: number
  paid: boolean
}

/** The schedule endpoint sends all seven days, with explicit days off. */
export type ScheduleDay = {
  weekday: Day
  label: string
  start_time: string | null
  end_time: string | null
  is_day_off: boolean
}

export type ScheduleWeek = {
  schedule: ScheduleDay[]
}

/** A role the owner made for this gym (R-08). */
export type GymRole = {
  id: number
  name: string
  permissions: Record<string, AccessLevel>
  users_count?: number
}

export type AccessTemplates = {
  modules: string[]
  levels: AccessLevel[]
  templates: Record<string, Record<string, AccessLevel>>
}

export const MODULE_LABELS: Record<string, string> = {
  hr: 'HR and employees',
  gym_setup: 'Gym setup',
  training: 'Training',
  members: 'Members',
  health_records: 'Health records',
  payroll: 'Payroll',
  finance: 'Finance',
  customers: 'Customers',
  inventory: 'Inventory',
  procurement: 'Procurement',
  announcements: 'Announcements',
  decision_support: 'Decision support',
}

export const LEVEL_LABELS: Record<string, string> = {
  view: 'View',
  manage: 'Manage',
  approve: 'Approve',
}

export const moduleLabel = (module: string) => MODULE_LABELS[module] ?? module.replaceAll('_', ' ')

/** "front_desk" -> "Front desk", for the role template names the backend sends. */
export const templateLabel = (key: string) => {
  const words = key.replaceAll('_', ' ')
  return words.charAt(0).toUpperCase() + words.slice(1)
}
