import { dateTimeLabel } from '@/lib/membership'

export type PlatformNoticeAudience = 'owners' | 'all_users'

export const audienceLabels: Record<PlatformNoticeAudience, string> = {
  owners: 'Owners of approved gyms', all_users: 'All verified users',
}

export const audienceDescriptions: Record<PlatformNoticeAudience, string> = {
  owners: 'Each owner of an approved gym receives one notice, even if they own several gyms. Staff, members and owners of pending gyms are not included.',
  all_users: 'Every user with a verified email receives this notice, including owners, staff and members.',
}

export type PlatformNotice = {
  id: number
  title: string
  body: string
  audience: PlatformNoticeAudience
  recipients_count: number
  created_by: number | null
  created_at: string
}

export type PlatformNoticePage = {
  notices: PlatformNotice[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export const noticeDate = dateTimeLabel
