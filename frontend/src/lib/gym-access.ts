import type { GymAccess, User } from '@/lib/auth'

/** The gym-side modules an owner can grant on the Roles screen. */
export type GymModule =
  | 'hr'
  | 'gym_setup'
  | 'training'
  | 'members'
  | 'health_records'
  | 'payroll'
  | 'finance'
  | 'customers'
  | 'inventory'
  | 'procurement'
  | 'announcements'
  | 'decision_support'

export type AccessLevel = 'view' | 'manage' | 'approve'

const RANK: Record<AccessLevel, number> = { view: 1, manage: 2, approve: 3 }

/** True when this gym grants at least `level` in `module`. Owners arrive with every module at approve. */
export function can(gym: GymAccess, module: GymModule, level: AccessLevel = 'view'): boolean {
  const held = gym.permissions[module] as AccessLevel | undefined
  return held !== undefined && (RANK[held] ?? 0) >= RANK[level]
}

/** True when this gym grants management somewhere, so the workspace is worth showing. */
export function canManage(gym: GymAccess): boolean {
  return gym.roles.includes('owner') || Object.values(gym.permissions).some((level) => level === 'manage' || level === 'approve')
}

/** Approved owner starts in their gym workspace; everyone else starts on the home page. */
export function homePath(user: User | null): string {
  const own = user?.gyms.find((gym) => gym.status === 'approved' && gym.roles.includes('owner'))
  return own ? `/gyms/${own.id}/manage` : '/'
}

/** The person's approved membership of one gym, if they have one. */
export function approvedGym(user: User | null, id: number): GymAccess | undefined {
  return user?.gyms.find((gym) => gym.id === id && gym.status === 'approved')
}
