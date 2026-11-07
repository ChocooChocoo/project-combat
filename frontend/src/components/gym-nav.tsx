import { Banknote, CalendarDays, ClipboardList, Clock, CreditCard, DoorOpen, Dumbbell, Inbox, Megaphone, Package, Receipt, Settings2, ShoppingCart, UserCheck, Users, UsersRound, Wallet, type LucideIcon } from 'lucide-react'
import type { GymAccess } from '@/lib/auth'
import { can, type Permission } from '@/lib/gym-access'

export type GymNavItem = { to: string; label: string; icon: LucideIcon }
export type GymNavGroup = { label: string; items: GymNavItem[] }

type GroupName = 'People' | 'Training' | 'Members' | 'Business' | 'Setup'

type ItemDefinition = Omit<GymNavItem, 'to'> & { segment: string; permissions: Permission[]; group: GroupName }

const ITEMS: ItemDefinition[] = [
  { segment: 'staff', label: 'Staff', icon: Users, permissions: ['hr.staff.view', 'hr.roles.view'], group: 'People' },
  { segment: 'staff-attendance', label: 'Staff attendance', icon: Clock, permissions: ['hr.attendance.view'], group: 'People' },
  { segment: 'leave', label: 'Leave', icon: CalendarDays, permissions: ['hr.leave.view', 'hr.leave.manage', 'hr.holidays.manage'], group: 'People' },
  { segment: 'payroll', label: 'Payroll', icon: Banknote, permissions: ['payroll.runs.view'], group: 'People' },
  { segment: 'schedule', label: 'Schedule', icon: CalendarDays, permissions: ['training.sessions.view'], group: 'Training' },
  { segment: 'bookings', label: 'Bookings', icon: ClipboardList, permissions: ['training.bookings.view'], group: 'Training' },
  { segment: 'check-in', label: 'Check-in', icon: UserCheck, permissions: ['training.check_ins.manage'], group: 'Training' },
  { segment: 'attendance', label: 'Attendance', icon: ClipboardList, permissions: ['training.attendance.view'], group: 'Training' },
  { segment: 'services', label: 'Services', icon: Dumbbell, permissions: ['gym_setup.services.view'], group: 'Training' },
  { segment: 'plans', label: 'Plans', icon: CreditCard, permissions: ['members.plans.view'], group: 'Members' },
  { segment: 'applications', label: 'Applications', icon: Inbox, permissions: ['members.memberships.view'], group: 'Members' },
  { segment: 'members', label: 'Members', icon: UserCheck, permissions: ['members.memberships.view'], group: 'Members' },
  { segment: 'announcements', label: 'Announcements', icon: Megaphone, permissions: ['announcements.general.view'], group: 'Members' },
  { segment: 'payments', label: 'Payments', icon: Receipt, permissions: ['finance.payments.view'], group: 'Members' },
  { segment: 'finance', label: 'Finance', icon: Wallet, permissions: ['finance.payments.view', 'finance.expenses.view', 'finance.receivables.view', 'finance.payables.view', 'finance.budgets.view', 'finance.reports.view'], group: 'Business' },
  { segment: 'customers', label: 'Customers', icon: UsersRound, permissions: ['customers.records.view'], group: 'Business' },
  { segment: 'stock', label: 'Stock', icon: Package, permissions: ['inventory.items.view'], group: 'Business' },
  { segment: 'purchasing', label: 'Purchasing', icon: ShoppingCart, permissions: ['procurement.requests.view', 'procurement.orders.view', 'procurement.suppliers.view', 'inventory.items.view'], group: 'Business' },
  { segment: 'settings', label: 'Setup', icon: Settings2, permissions: ['gym_setup.settings.view'], group: 'Setup' },
  { segment: 'rooms', label: 'Rooms', icon: DoorOpen, permissions: ['gym_setup.rooms.view'], group: 'Setup' },
]

const GROUP_ORDER: GroupName[] = ['People', 'Training', 'Members', 'Business', 'Setup']

/** Workspace links this person may open, in the order the sidebar and sub-navigation show them. */
export function workspaceNav(gym: GymAccess): GymNavItem[] {
  return ITEMS.filter((item) => can(gym, ...item.permissions)).map(({ segment, label, icon }) => ({
    to: `/gyms/${gym.id}/manage/${segment}`,
    label,
    icon,
  }))
}

/** The same links split into short labelled groups, so 19 items stay scannable on a phone. */
export function workspaceNavGroups(gym: GymAccess): GymNavGroup[] {
  return GROUP_ORDER.flatMap((label) => {
    const items = ITEMS.filter((item) => item.group === label && can(gym, ...item.permissions)).map(
      ({ segment, label: text, icon }) => ({ to: `/gyms/${gym.id}/manage/${segment}`, label: text, icon }),
    )

    return items.length > 0 ? [{ label, items }] : []
  })
}

/** Every gym employee can see their own schedule, leave and profile. */
export function myWorkNav(gymId: number): GymNavItem {
  return { to: `/gyms/${gymId}/manage/my-work`, label: 'My work', icon: ClipboardList }
}

/**
 * First workspace section this person may open, or their own work view when they
 * hold no module permission. A stock-only or CRM-only account lands on that module.
 */
export function workspaceHome(gym: GymAccess): string {
  const first = workspaceNav(gym)[0] ?? (gym.roles.includes('employee') ? myWorkNav(gym.id) : undefined)
  return first?.to ?? `/gyms/${gym.id}`
}
