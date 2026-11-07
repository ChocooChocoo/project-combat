import { dateTimeLabel, gymDate } from '@/lib/membership'

export type AnnouncementKind = 'announcement' | 'event'
export type AnnouncementAudience = 'all_members' | 'session' | 'customer_group'

export const kindLabels: Record<AnnouncementKind, string> = { announcement: 'Announcement', event: 'Event' }
export const audienceLabels: Record<AnnouncementAudience, string> = {
  all_members: 'All members', session: 'One class', customer_group: 'Customer group',
}

export type Announcement = {
  id: number
  title: string
  body: string
  kind: AnnouncementKind
  audience_type: AnnouncementAudience
  training_session_id: number | null
  session_name: string | null
  customer_group_id: number | null
  group_name: string | null
  event_starts_at: string | null
  event_place: string | null
  recipients_count: number
  skipped_count: number
  created_by: number | null
  created_by_name: string | null
  created_at: string
}

export type AnnouncementPage = {
  announcements: Announcement[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export type AnnouncementOptions = {
  member_count: number
  sessions: Array<{ id: number; name: string; starts_at: string; ends_at: string; room_name: string | null; booked_count: number }>
  groups: Array<{ id: number; name: string; members_count: number }>
}

export const announcementDate = dateTimeLabel

/** The composer's datetime-local control always represents Philippine wall time. */
export function eventTimestamp(value: string): string {
  return gymDate(value).toISOString()
}
