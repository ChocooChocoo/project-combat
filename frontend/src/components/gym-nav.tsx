import { CalendarDays, ClipboardList, CreditCard, DoorOpen, Dumbbell, Inbox, Receipt, Settings2, UserCheck, Users, type LucideIcon } from 'lucide-react'
import type { GymAccess } from '@/lib/auth'
import { can, type AccessLevel, type GymModule } from '@/lib/gym-access'

export type GymNavItem = { to: string; label: string; icon: LucideIcon }

const ITEMS: { segment: string; label: string; icon: LucideIcon; module: GymModule; level?: AccessLevel }[] = [
  { segment: 'settings', label: 'Setup', icon: Settings2, module: 'gym_setup' },
  { segment: 'rooms', label: 'Rooms', icon: DoorOpen, module: 'gym_setup' },
  { segment: 'staff', label: 'Staff', icon: Users, module: 'hr' },
  { segment: 'leave', label: 'Leave', icon: CalendarDays, module: 'hr' },
  { segment: 'schedule', label: 'Schedule', icon: CalendarDays, module: 'training' },
  { segment: 'bookings', label: 'Bookings', icon: ClipboardList, module: 'training' },
  { segment: 'check-in', label: 'Check-in', icon: UserCheck, module: 'training', level: 'manage' },
  { segment: 'attendance', label: 'Attendance', icon: ClipboardList, module: 'training' },
  { segment: 'services', label: 'Services', icon: Dumbbell, module: 'gym_setup' },
  { segment: 'plans', label: 'Plans', icon: CreditCard, module: 'members' },
  { segment: 'applications', label: 'Applications', icon: Inbox, module: 'members' },
  { segment: 'members', label: 'Members', icon: UserCheck, module: 'members' },
  { segment: 'payments', label: 'Payments', icon: Receipt, module: 'finance' },
]

/** Workspace links this person may open, in the order the sidebar and sub-navigation show them. */
export function workspaceNav(gym: GymAccess): GymNavItem[] {
  return ITEMS.filter((item) => can(gym, item.module, item.level ?? 'view')).map(({ segment, label, icon }) => ({
    to: `/gyms/${gym.id}/manage/${segment}`,
    label,
    icon,
  }))
}

/** Every gym employee can see their own schedule, leave and profile. */
export function myWorkNav(gymId: number): GymNavItem {
  return { to: `/gyms/${gymId}/manage/my-work`, label: 'My work', icon: ClipboardList }
}

/** First workspace section this person may open, or the public page when none is allowed. */
export function workspaceHome(gym: GymAccess): string {
  const first = workspaceNav(gym)[0] ?? (gym.roles.includes('employee') ? myWorkNav(gym.id) : undefined)
  return first?.to ?? `/gyms/${gym.id}`
}
