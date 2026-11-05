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

/** Approved owner starts in their gym workspace; everyone else starts on the home page. */
export function homePath(user: User | null): string {
  const own = user?.gyms.find((gym) => gym.status === 'approved' && gym.roles.includes('owner'))
  return own ? `/gyms/${own.id}/manage` : '/'
}

/** The person's approved membership of one gym, if they have one. */
export function approvedGym(user: User | null, id: number): GymAccess | undefined {
  return user?.gyms.find((gym) => gym.id === id && gym.status === 'approved')
}
