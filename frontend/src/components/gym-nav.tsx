import { BadgeCheck, BriefcaseBusiness, Banknote, TicketPercent, UserPlus, ChartColumn, CalendarDays, ClipboardList, Clock, CreditCard, DoorOpen, Dumbbell, Inbox, Megaphone, Package, Receipt, Settings2, ShoppingCart, UserCheck, Users, UsersRound, Wallet, type LucideIcon } from 'lucide-react'
import type { GymAccess } from '@/lib/auth'
import { can, type Permission } from '@/lib/gym-access'

export type GymNavItem = { to: string; label: string; icon: LucideIcon }
export type GymNavGroup = { label: string; items: GymNavItem[] }

type GroupName = 'HR / Employee Management' | 'Training' | 'Members' | 'Finance' | 'Business' | 'Setup'

type ItemDefinition = Omit<GymNavItem, 'to'> & { segment: string; permissions: Permission[]; group: GroupName }

const ITEMS: ItemDefinition[] = [
  { segment: 'staff', label: 'Staff', icon: Users, permissions: ['hr.staff.view', 'hr.roles.view'], group: 'HR / Employee Management' },
  { segment: 'staff-attendance', label: 'Staff attendance', icon: Clock, permissions: ['hr.attendance.view', 'hr.overtime.approve'], group: 'HR / Employee Management' },
  { segment: 'coach-applications', label: 'Coach applications', icon: UserPlus, permissions: ['hr.coach_applications.view', 'hr.coach_applications.approve'], group: 'HR / Employee Management' },
  { segment: 'hiring', label: 'Hiring', icon: BriefcaseBusiness, permissions: ['hr.job_postings.view', 'hr.job_applications.view', 'hr.job_applications.approve'], group: 'HR / Employee Management' }, // (R-138)
  { segment: 'leave', label: 'Leave', icon: CalendarDays, permissions: ['hr.leave.view', 'hr.leave.manage', 'hr.holidays.manage'], group: 'HR / Employee Management' },
  { segment: 'payroll', label: 'Payroll', icon: Banknote, permissions: ['payroll.runs.view'], group: 'Finance' },
  { segment: 'schedule', label: 'Schedule', icon: CalendarDays, permissions: ['training.sessions.view'], group: 'Training' },
  { segment: 'bookings', label: 'Bookings', icon: ClipboardList, permissions: ['training.bookings.view'], group: 'Training' },
  { segment: 'check-in', label: 'Check-in', icon: UserCheck, permissions: ['training.check_ins.manage'], group: 'Training' },
  { segment: 'attendance', label: 'Attendance', icon: ClipboardList, permissions: ['training.attendance.view'], group: 'Training' },
  { segment: 'services', label: 'Services', icon: Dumbbell, permissions: ['gym_setup.services.view'], group: 'Training' },
  { segment: 'plans', label: 'Plans', icon: CreditCard, permissions: ['members.plans.view'], group: 'Members' },
  { segment: 'applications', label: 'Applications', icon: Inbox, permissions: ['members.memberships.view'], group: 'Members' },
  { segment: 'members', label: 'Members', icon: UserCheck, permissions: ['members.memberships.view', 'training.athletes.view'], group: 'Members' },
  { segment: 'announcements', label: 'Announcements', icon: Megaphone, permissions: ['announcements.general.view'], group: 'Members' },
  { segment: 'payments', label: 'Payments', icon: Receipt, permissions: ['finance.payments.view'], group: 'Finance' },
  { segment: 'finance', label: 'Finance', icon: Wallet, permissions: ['finance.payments.view', 'finance.expenses.view', 'finance.receivables.view', 'finance.payables.view', 'finance.budgets.view', 'finance.reports.view'], group: 'Finance' },
  { segment: 'customers', label: 'Customers', icon: UsersRound, permissions: ['customers.records.view'], group: 'Business' },
  { segment: 'offers', label: 'Offers', icon: TicketPercent, permissions: ['customers.offers.view'], group: 'Business' },
  { segment: 'stock', label: 'Stock', icon: Package, permissions: ['inventory.items.view'], group: 'Business' },
  { segment: 'purchasing', label: 'Purchasing', icon: ShoppingCart, permissions: ['procurement.requests.view', 'procurement.orders.view', 'procurement.suppliers.view', 'inventory.items.view'], group: 'Business' },
  { segment: 'settings', label: 'Setup', icon: Settings2, permissions: ['gym_setup.settings.view'], group: 'Setup' },
  { segment: 'rooms', label: 'Rooms', icon: DoorOpen, permissions: ['gym_setup.rooms.view'], group: 'Setup' },
  { segment: 'dashboard', label: 'Dashboard', icon: ChartColumn, permissions: ['decision_support.general.view'], group: 'Business' },
  { segment: 'decision-support', label: 'Analytics', icon: ClipboardList, permissions: ['decision_support.general.view'], group: 'Business' },
]

const GROUP_ORDER: GroupName[] = ['HR / Employee Management', 'Training', 'Members', 'Finance', 'Business', 'Setup']

/** Workspace links this person may open, in the order the sidebar and sub-navigation show them. */
export function workspaceNav(gym: GymAccess): GymNavItem[] {
  return ITEMS.filter((item) => can(gym, ...item.permissions)).sort((a, b) => GROUP_ORDER.indexOf(a.group) - GROUP_ORDER.indexOf(b.group)).map(({ segment, label, icon }) => ({
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

    // R-107: only the owner manages the gym's platform plan.
    if (label === 'Setup' && gym.roles.includes('owner')) {
      items.push({ to: `/gyms/${gym.id}/manage/subscription`, label: 'My subscription', icon: BadgeCheck })
    }

    return items.length > 0 ? [{ label, items }] : []
  })
}

/**
 * R-106: the workspace section at this path, when the gym's plan locks every module it uses.
 * A section that still has one open module (Purchasing's restock list runs on Stock) stays open.
 */
export function planLockedSection(gym: GymAccess, pathname: string): string | null {
  const locked = gym.subscription?.locked_modules ?? []
  if (locked.length === 0) return null
  const segment = pathname.match(/\/manage\/([^/]+)/)?.[1]
  if (segment === 'my-work' || segment === 'my-dashboard') return locked.includes('hr') ? (segment === 'my-work' ? 'My work' : 'Work dashboard') : null
  const item = ITEMS.find((entry) => entry.segment === segment)
  if (!item) return null
  return item.permissions.every((permission) => locked.includes(permission.split('.')[0])) ? item.label : null
}

/** Every gym employee can see their own schedule, leave and profile. */
export function myWorkNav(gymId: number): GymNavItem {
  return { to: `/gyms/${gymId}/manage/my-work`, label: 'My work', icon: ClipboardList }
}

/** P07: the employee's own dashboard, beside My work. */
export function myDashboardNav(gymId: number): GymNavItem {
  return { to: `/gyms/${gymId}/manage/my-dashboard`, label: 'Work dashboard', icon: ChartColumn }
}

/**
 * First workspace section this person may open, or their own work view when they
 * hold no module permission. A stock-only or CRM-only account lands on that module.
 */
export function workspaceHome(gym: GymAccess): string {
  const first = workspaceNav(gym)[0] ?? (gym.roles.includes('employee') ? myWorkNav(gym.id) : undefined)
  return first?.to ?? `/gyms/${gym.id}`
}
