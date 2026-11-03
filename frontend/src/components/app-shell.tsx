import { useEffect, useState } from 'react'
import { NavLink, Outlet } from 'react-router'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { api } from '@/lib/api'
import { useAuth } from '@/lib/auth'

const linkClass = ({ isActive }: { isActive: boolean }) =>
  `rounded-md px-3 py-1.5 text-sm ${isActive ? 'bg-accent font-medium text-foreground' : 'text-muted-foreground hover:text-foreground'}`

/** Frame for every signed-in screen. */
export function AppShell() {
  const { user, signOut } = useAuth()
  const [unread, setUnread] = useState(0)

  useEffect(() => {
    api<{ unread_count: number }>('/notifications').then((r) => setUnread(r.unread_count)).catch(() => undefined)
  }, [])

  return (
    <div className="min-h-svh bg-muted/40">
      <header className="border-b bg-background">
        <div className="mx-auto flex max-w-5xl flex-wrap items-center gap-2 px-4 py-3">
          <span className="mr-auto font-semibold">Combat Platform</span>
          <nav className="flex flex-wrap items-center gap-1">
            <NavLink className={linkClass} to="/" end>Home</NavLink>
            <NavLink className={linkClass} to="/notifications">
              Notifications {unread > 0 && <Badge className="ml-1">{unread}</Badge>}
            </NavLink>
            <NavLink className={linkClass} to="/profile">{user?.first_name ?? 'Profile'}</NavLink>
          </nav>
          <Button variant="outline" size="sm" onClick={signOut}>Sign out</Button>
        </div>
      </header>
      <main className="mx-auto max-w-5xl px-4 py-8">
        <Outlet context={{ setUnread }} />
      </main>
    </div>
  )
}
