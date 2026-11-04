import { Link, Navigate, Outlet, useLocation } from 'react-router'
import { useAuth } from '@/lib/auth'
import { approvedGym } from '@/lib/gym-access'

/**
 * Frame for one gym's workspace: the gym name above the section. The sections
 * themselves are listed once, in the sidebar. The gym comes from the URL so the
 * layout works on a full page load without the menu that led here.
 */
export function GymWorkspaceLayout() {
  const { user } = useAuth()
  const { pathname } = useLocation()
  const gymId = Number(pathname.match(/^\/gyms\/(\d+)(?:\/|$)/)?.[1])
  const gym = approvedGym(user, gymId)

  if (!gym) return <Navigate to="/" replace />

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-baseline gap-2">
        <Link className="font-semibold underline-offset-4 hover:underline" to={`/gyms/${gym.id}`}>
          {gym.name}
        </Link>
        <span className="text-muted-foreground text-sm">workspace</span>
      </div>
      <Outlet />
    </div>
  )
}
