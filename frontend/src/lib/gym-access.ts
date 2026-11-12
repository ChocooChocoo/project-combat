import type { GymAccess, User } from '@/lib/auth'

export type Permission = `${string}.${string}.${string}`

export type AccessLevel = 'view' | 'manage' | 'approve'

export function can(gym: GymAccess, ...anyOf: Permission[]): boolean {
  return anyOf.some((permission) => gym.permissions.includes(permission))
}

/** Workspace entry includes read-only staff, not only managers. */
export function canOpenWorkspace(gym: GymAccess): boolean {
  return gym.roles.includes('owner') || gym.permissions.length > 0
}

/** Where a gym's card in the switcher leads: workspace when allowed, public page otherwise. */
export function gymEntry(gym: GymAccess): string {
  if (canOpenWorkspace(gym)) return `/gyms/${gym.id}/manage`
  if (gym.roles.includes('employee')) return `/gyms/${gym.id}/manage/my-work`
  if (gym.roles.includes('member')) return `/gyms/${gym.id}/my-record`
  return `/gyms/${gym.id}`
}

// R-108: the last gym workspace opened, kept per user so a shared device never opens another user's gym.
const lastGymKey = (userId: number) => `last_gym:${userId}`

export function rememberGym(userId: number, gymId: number): void {
  globalThis.localStorage?.setItem(lastGymKey(userId), String(gymId))
}

/** Last gym used when still approved for this person; else an approved owner starts in their gym, others on home. */
export function homePath(user: User | null): string {
  const saved = user ? Number(globalThis.localStorage?.getItem(lastGymKey(user.id))) : 0
  const last = saved ? approvedGym(user, saved) : undefined
  if (last) return gymEntry(last)
  const own = user?.gyms.find((gym) => gym.status === 'approved' && gym.roles.includes('owner'))
  return own ? `/gyms/${own.id}/manage` : '/'
}

/** The person's approved membership of one gym, if they have one. */
export function approvedGym(user: User | null, id: number): GymAccess | undefined {
  return user?.gyms.find((gym) => gym.id === id && gym.status === 'approved')
}
