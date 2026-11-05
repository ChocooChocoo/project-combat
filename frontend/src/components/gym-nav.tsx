import { CalendarDays, ClipboardList, CreditCard, DoorOpen, Dumbbell, Inbox, Receipt, Settings2, UserCheck, Users, type LucideIcon } from 'lucide-react'
import type { GymAccess } from '@/lib/auth'
import { can, type Permission } from '@/lib/gym-access'

export type GymNavItem = { to: string; label: string; icon: LucideIcon }

const ITEMS: { segment: string; label: string; icon: LucideIcon; permissions: Permission[] }[] = [
  { segment: 'settings', label: 'Setup', icon: Settings2, permissions: ['gym_setup.settings.view'] },
  { segment: 'rooms', label: 'Rooms', icon: DoorOpen, permissions: ['gym_setup.rooms.view'] },
  { segment: 'staff', label: 'Staff', icon: Users, permissions: ['hr.staff.view', 'hr.roles.view'] },
  { segment: 'leave', label: 'Leave', icon: CalendarDays, permissions: ['hr.leave.view', 'hr.leave.manage', 'hr.holidays.manage'] },
  { segment: 'schedule', label: 'Schedule', icon: CalendarDays, permissions: ['training.sessions.view'] },
  { segment: 'bookings', label: 'Bookings', icon: ClipboardList, permissions: ['training.bookings.view'] },
  { segment: 'check-in', label: 'Check-in', icon: UserCheck, permissions: ['training.check_ins.manage'] },
  { segment: 'attendance', label: 'Attendance', icon: ClipboardList, permissions: ['training.attendance.view'] },
  { segment: 'services', label: 'Services', icon: Dumbbell, permissions: ['gym_setup.services.view'] },
  { segment: 'plans', label: 'Plans', icon: CreditCard, permissions: ['members.plans.view'] },
  { segment: 'applications', label: 'Applications', icon: Inbox, permissions: ['members.memberships.view'] },
  { segment: 'members', label: 'Members', icon: UserCheck, permissions: ['members.memberships.view'] },
  { segment: 'payments', label: 'Payments', icon: Receipt, permissions: ['finance.payments.view'] },
]

/** Workspace links this person may open, in the order the sidebar and sub-navigation show them. */
export function workspaceNav(gym: GymAccess): GymNavItem[] {
  return ITEMS.filter((item) => can(gym, ...item.permissions)).map(({ segment, label, icon }) => ({
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
