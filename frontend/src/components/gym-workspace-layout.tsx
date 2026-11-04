import { Link, NavLink, Navigate, Outlet, useLocation } from 'react-router'
import { myWorkNav, workspaceNav } from '@/components/gym-nav'
import { useAuth } from '@/lib/auth'
import { approvedGym } from '@/lib/gym-access'
import { cn } from '@/lib/utils'

/**
 * Frame for one gym's workspace: the gym name, then only the sections this
 * person's permissions allow. The gym comes from the URL so the layout works
 * on a full page load without the menu that led here.
 */
export function GymWorkspaceLayout() {
  const { user } = useAuth()
  const { pathname } = useLocation()
  const gymId = Number(pathname.match(/^\/gyms\/(\d+)(?:\/|$)/)?.[1])
  const gym = approvedGym(user, gymId)

  if (!gym) return <Navigate to="/" replace />

  const items = gym.roles.includes('employee') ? [...workspaceNav(gym), myWorkNav(gym.id)] : workspaceNav(gym)

  return (
    <div className="flex flex-col gap-8">
      <div className="flex flex-col gap-3 border-b pb-4">
        <div className="flex flex-wrap items-baseline gap-2">
          <Link className="font-semibold underline-offset-4 hover:underline" to={`/gyms/${gym.id}`}>
            {gym.name}
          </Link>
          <span className="text-muted-foreground text-sm">workspace</span>
        </div>
        {items.length > 0 && (
          <nav aria-label="Gym workspace" className="flex flex-wrap gap-1">
            {items.map(({ to, label, icon: Icon }) => (
              <NavLink
                key={to}
                to={to}
                className={({ isActive }) =>
                  cn(
                    'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm',
                    isActive
                      ? 'bg-muted font-medium text-foreground'
                      : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                  )
                }
              >
                <Icon className="size-4" aria-hidden />
                {label}
              </NavLink>
            ))}
          </nav>
        )}
      </div>
      <Outlet />
    </div>
  )
}
