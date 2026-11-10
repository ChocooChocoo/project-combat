// Shapes and labels for customer records, follow-ups and groups (R-69 to R-71).

import { api } from '@/lib/api'
import type { MembershipStatus } from '@/lib/membership'

export type CustomerStage = 'lead' | 'applied' | 'member' | 'lost'
export type CustomerStatus = 'open' | 'lost'
export type CustomerSource = 'walk_in' | 'referral' | 'social' | 'website' | 'event' | 'other'
export type InteractionKind = 'interaction' | 'feedback' | 'concern' | 'preference'
export type InteractionChannel = 'phone' | 'sms' | 'email' | 'messenger' | 'walk_in' | 'other'
export type FollowUpStatus = 'open' | 'done' | 'cancelled'

export type CustomerMembershipRef = {
  id: number
  status?: MembershipStatus | null
  member?: { id: number; name: string; email?: string | null } | null
  plan?: { id?: number; name: string } | null
}

/** The small application list the CRM link control is allowed to read. */
export type LinkableMembership = {
  id: number
  status: MembershipStatus
  starts_on: string | null
  created_at: string | null
  member_id: number
  member_name: string
  email: string | null
  plan_name: string | null
}

export type Customer = {
  id: number
  name: string
  email: string | null
  phone: string | null
  source: CustomerSource
  inquiry: string | null
  user_id: number | null
  membership_id: number | null
  linked_at: string | null
  status: CustomerStatus
  stage: CustomerStage
  assigned_to: number | null
  assigned_to_name: string | null
  created_at: string
  updated_at: string
  /** Some responses include the linked membership; the list still works without it. */
  membership?: CustomerMembershipRef | null
}

export type CustomerInteraction = {
  id: number
  kind: InteractionKind
  channel: InteractionChannel
  body: string
  occurred_at: string
  recorded_by_name: string | null
  resolved_at: string | null
  resolved_by_name: string | null
  resolution: string | null
}

export type CustomerFollowUp = {
  id: number
  customer_id: number
  assigned_to: number
  assigned_to_name: string | null
  due_at: string
  note: string
  status: FollowUpStatus
  done_at: string | null
  done_by: number | null
  done_by_name?: string | null
  outcome: string | null
  /** Optional when follow-ups are listed outside a customer record. */
  customer_name?: string | null
}

export type CustomerGroupMember = { id: number; customer_id?: number; name?: string | null }

export type CustomerGroup = {
  id: number
  name: string
  description: string | null
  members_count: number
  /** Present on responses that carry the whole audience, so members can be synced safely. */
  members?: Array<number | CustomerGroupMember> | null
  customers?: Array<number | CustomerGroupMember> | null
  customer_ids?: Array<number | string> | null
  member_ids?: Array<number | string> | null
}

export type CustomerDetail = {
  customer: Customer
  interactions: CustomerInteraction[]
  followUps: CustomerFollowUp[]
  groups: CustomerGroup[]
}

export type StaffChoice = { id: number; name: string }

export const CUSTOMER_STAGE_LABELS: Record<CustomerStage, string> = {
  lead: 'Lead',
  applied: 'Applied',
  member: 'Member',
  lost: 'Lost',
}

export const CUSTOMER_STAGE_VARIANTS: Record<CustomerStage, 'info' | 'warning' | 'success' | 'secondary'> = {
  lead: 'info',
  applied: 'warning',
  member: 'success',
  lost: 'secondary',
}

export const CUSTOMER_STATUS_LABELS: Record<CustomerStatus, string> = {
  open: 'Open',
  lost: 'Lost',
}

export const CUSTOMER_SOURCE_LABELS: Record<CustomerSource, string> = {
  walk_in: 'Walk-in',
  referral: 'Referral',
  social: 'Social media',
  website: 'Website',
  event: 'Event',
  other: 'Other',
}

export const INTERACTION_KIND_LABELS: Record<InteractionKind, string> = {
  interaction: 'Interaction',
  feedback: 'Feedback',
  concern: 'Concern',
  preference: 'Preference',
}

export const INTERACTION_KIND_VARIANTS: Record<InteractionKind, 'outline' | 'info' | 'error' | 'secondary'> = {
  interaction: 'outline',
  feedback: 'info',
  concern: 'error',
  preference: 'secondary',
}

export const INTERACTION_CHANNEL_LABELS: Record<InteractionChannel, string> = {
  phone: 'Phone call',
  sms: 'SMS',
  email: 'Email',
  messenger: 'Messenger',
  walk_in: 'In person',
  other: 'Other',
}

export const FOLLOW_UP_STATUS_LABELS: Record<FollowUpStatus, string> = {
  open: 'Open',
  done: 'Done',
  cancelled: 'Cancelled',
}

export const FOLLOW_UP_STATUS_VARIANTS: Record<FollowUpStatus, 'warning' | 'success' | 'secondary'> = {
  open: 'warning',
  done: 'success',
  cancelled: 'secondary',
}

/** The API derives stage; status wins so a closed record never looks active. */
export function customerStage(stage: string | null | undefined, status?: string | null): CustomerStage {
  if (status === 'lost') return 'lost'
  return stage && stage in CUSTOMER_STAGE_LABELS ? (stage as CustomerStage) : 'lead'
}

export function stageBadge(stage: string | null | undefined, status?: string | null): {
  label: string
  variant: 'info' | 'warning' | 'success' | 'secondary'
} {
  const value = customerStage(stage, status)
  return { label: CUSTOMER_STAGE_LABELS[value], variant: CUSTOMER_STAGE_VARIANTS[value] }
}

export function sourceLabel(source: string | null | undefined): string {
  return source && source in CUSTOMER_SOURCE_LABELS
    ? CUSTOMER_SOURCE_LABELS[source as CustomerSource]
    : (source ?? 'Other').replaceAll('_', ' ')
}

export function interactionKindLabel(kind: string | null | undefined): string {
  return kind && kind in INTERACTION_KIND_LABELS
    ? INTERACTION_KIND_LABELS[kind as InteractionKind]
    : (kind ?? 'Interaction').replaceAll('_', ' ')
}

export function interactionKindVariant(kind: string | null | undefined): 'outline' | 'info' | 'error' | 'secondary' {
  return kind && kind in INTERACTION_KIND_VARIANTS ? INTERACTION_KIND_VARIANTS[kind as InteractionKind] : 'outline'
}

export function channelLabel(channel: string | null | undefined): string {
  return channel && channel in INTERACTION_CHANNEL_LABELS
    ? INTERACTION_CHANNEL_LABELS[channel as InteractionChannel]
    : (channel ?? 'Other').replaceAll('_', ' ')
}

export function followUpStatusLabel(status: string | null | undefined): string {
  return status && status in FOLLOW_UP_STATUS_LABELS
    ? FOLLOW_UP_STATUS_LABELS[status as FollowUpStatus]
    : (status ?? 'Open').replaceAll('_', ' ')
}

export function followUpStatusVariant(status: string | null | undefined): 'warning' | 'success' | 'secondary' {
  return status && status in FOLLOW_UP_STATUS_VARIANTS ? FOLLOW_UP_STATUS_VARIANTS[status as FollowUpStatus] : 'warning'
}

/** "Membership #12" or, when the response carries it, "Membership #12 · Ana Cruz". */
export function linkedMembershipLabel(customer: Customer): string | null {
  if (!customer.membership_id) return null
  const membership = customer.membership
  const person = membership?.member?.name
  return person ? `Membership #${customer.membership_id} · ${person}` : `Membership #${customer.membership_id}`
}

/** Reads the optional member list in any of the shapes the group endpoints may return. */
export function groupMemberIds(group: CustomerGroup): number[] | null {
  const list = group.members ?? group.customers
  if (Array.isArray(list)) {
    return list
      .map((member) => (typeof member === 'number' ? member : Number(member.id ?? member.customer_id)))
      .filter((id) => Number.isFinite(id))
  }
  if (Array.isArray(group.customer_ids)) {
    return group.customer_ids.map(Number).filter((id) => Number.isFinite(id))
  }
  if (Array.isArray(group.member_ids)) {
    return group.member_ids.map(Number).filter((id) => Number.isFinite(id))
  }
  return null
}

export function groupCountLabel(group: CustomerGroup): string {
  const count = groupMemberIds(group)?.length ?? group.members_count ?? 0
  return `${count} ${count === 1 ? 'member' : 'members'}`
}

/** Local date-time input value ("2026-11-06T14:30"), using the browser's clock. */
export function nowDateTimeInput(): string {
  const date = new Date()
  const pad = (value: number) => String(value).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/** The API accepts Laravel's "Y-m-d H:i:s"; the input omits seconds. */
export function dateTimeForApi(value: string): string | null {
  const trimmed = value.trim()
  if (!trimmed) return null
  const spaced = trimmed.replace('T', ' ')
  return spaced.length === 16 ? `${spaced}:00` : spaced
}

/** Owner and employees a CRM record or follow-up can be assigned to. */
export async function loadStaffChoices(gymId: number): Promise<StaffChoice[]> {
  try {
    const result = await api<{ employees: StaffChoice[] }>(`/gyms/${gymId}/customers/staff-choices`)
    return [...result.employees].sort((a, b) => a.name.localeCompare(b.name))
  } catch {
    return []
  }
}
