import { Building2, CalendarDays, Check, CreditCard, ChevronsUpDown, ClipboardCheck, FilePlus2, FileText, House, IdCard, LogOut, Medal, UserRound } from 'lucide-react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router'
import { myWorkNav, workspaceNavGroups } from '@/components/gym-nav'
import { NotificationsMenu } from '@/components/notifications-menu'
import { ThemeToggle } from '@/components/theme-toggle'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Menu, MenuGroup, MenuGroupLabel, MenuItem, MenuPopup, MenuSeparator, MenuTrigger } from '@/components/ui/menu'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarInset,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider,
  SidebarTrigger,
  useSidebar,
} from '@/components/ui/sidebar'
import { useNotifications } from '@/lib/notifications'
import { useAuth, type GymAccess } from '@/lib/auth'
import { approvedGym, canOpenWorkspace } from '@/lib/gym-access'

type Item = { to: string; label: string; icon: React.ComponentType; end?: boolean }

/** Where a gym's card in the switcher leads: workspace when allowed, public page otherwise. */
function gymEntry(gym: GymAccess): string {
  if (canOpenWorkspace(gym)) return `/gyms/${gym.id}/manage`
  if (gym.roles.includes('employee')) return `/gyms/${gym.id}/manage/my-work`
  if (gym.roles.includes('member')) return `/gyms/${gym.id}/my-record`
  return `/gyms/${gym.id}`
}

function NavGroup({ label, items }: { label?: string; items: Item[] }) {
  const { pathname } = useLocation()
  const { isMobile, setOpenMobile } = useSidebar()

  return (
    <SidebarGroup>
      {label && <SidebarGroupLabel>{label}</SidebarGroupLabel>}
      <SidebarGroupContent>
        <SidebarMenu>
          {items.map(({ to, label: text, icon: Icon, end }) => {
            const active = end ? pathname === to : pathname === to || pathname.startsWith(`${to}/`)
            return (
              <SidebarMenuItem key={to}>
                <SidebarMenuButton
                  isActive={active}
                  tooltip={text}
                  render={<NavLink to={to} end={end} onClick={() => isMobile && setOpenMobile(false)} />}
                >
                  <Icon />
                  <span>{text}</span>
                </SidebarMenuButton>
              </SidebarMenuItem>
            )
          })}
        </SidebarMenu>
      </SidebarGroupContent>
    </SidebarGroup>
  )
}

/** Picks the gym the workspace screens should show. The URL is the only source of truth. */
function GymSwitcher({ gyms, current }: { gyms: GymAccess[]; current?: GymAccess }) {
  const navigate = useNavigate()
  const onlyGym = gyms.length === 1 ? gyms[0] : undefined

  return (
    <SidebarMenuItem>
      <Menu>
        <MenuTrigger
          render={<SidebarMenuButton size="lg" className="data-popup-open:bg-sidebar-accent" />}
        >
          <span className="flex size-8 shrink-0 items-center justify-center rounded-lg border bg-background">
            <Building2 className="size-4" aria-hidden />
          </span>
          <span className="grid flex-1 text-left text-sm leading-tight">
            <span className="truncate font-medium">{current?.name ?? onlyGym?.name ?? 'Choose a gym'}</span>
            <span className="truncate text-muted-foreground text-xs">
              {current || onlyGym ? 'Gym workspace' : `${gyms.length} approved`}
            </span>
          </span>
          <ChevronsUpDown className="ms-auto size-4 opacity-60" />
        </MenuTrigger>
        <MenuPopup side="bottom" align="start" className="min-w-56">
          <MenuGroup>
            <MenuGroupLabel>Your gyms</MenuGroupLabel>
            {gyms.map((gym) => (
              <MenuItem key={gym.id} onClick={() => navigate(gymEntry(gym))}>
                <Building2 />
                <span className="truncate">{gym.name}</span>
                {current?.id === gym.id && <Check className="ms-auto" />}
              </MenuItem>
            ))}
          </MenuGroup>
        </MenuPopup>
      </Menu>
    </SidebarMenuItem>
  )
}

function AccountMenu({ roleLabel }: { roleLabel: string }) {
  const { user, signOut } = useAuth()
  const navigate = useNavigate()
  if (!user) return null
  const initials = `${user.first_name[0] ?? ''}${user.last_name[0] ?? ''}`

  return (
    <Menu>
      <MenuTrigger
        render={<SidebarMenuButton size="lg" className="data-popup-open:bg-sidebar-accent" />}
      >
        <Avatar className="size-8 rounded-lg">
          {user.photo_url && <AvatarImage src={user.photo_url} alt="" />}
          <AvatarFallback className="rounded-lg">{initials}</AvatarFallback>
        </Avatar>
        <span className="grid flex-1 text-left text-sm leading-tight">
          <span className="truncate font-medium">{user.first_name} {user.last_name}</span>
          <span className="truncate text-muted-foreground text-xs">{roleLabel}</span>
        </span>
        <ChevronsUpDown className="ms-auto size-4 opacity-60" />
      </MenuTrigger>
      <MenuPopup side="top" align="start" className="min-w-60">
        <div className="flex items-center gap-3 px-2 py-2">
          <Avatar className="size-9 rounded-lg">
            {user.photo_url && <AvatarImage src={user.photo_url} alt="" />}
            <AvatarFallback className="rounded-lg">{initials}</AvatarFallback>
          </Avatar>
          <div className="flex min-w-0 flex-col gap-1">
            <p className="truncate font-medium text-sm">{user.first_name} {user.last_name}</p>
            <p className="truncate text-muted-foreground text-xs">{user.email}</p>
          </div>
        </div>
        <div className="px-2 pb-2">
          <Badge variant="outline" className="h-auto max-w-full whitespace-normal py-0.5">{roleLabel}</Badge>
        </div>
        <MenuSeparator />
        <MenuItem onClick={() => navigate('/profile')}>
          <UserRound /> Profile
        </MenuItem>
        <MenuSeparator />
        <MenuItem variant="destructive" onClick={signOut}>
          <LogOut /> Sign out
        </MenuItem>
      </MenuPopup>
    </Menu>
  )
}

function gymRoleLabel(roles: string[]): string {
  if (roles.includes('owner')) return 'Gym owner'
  if (roles.includes('employee')) return 'Gym employee'
  if (roles.includes('member')) return 'Gym member'
  return 'Signed in'
}

/** Frame for every signed-in screen: a sidebar that shows only what this person's role allows. */
export function AppShell({ children }: { children?: React.ReactNode }) {
  const { user } = useAuth()
  const { pathname } = useLocation()
  const { unread } = useNotifications()

  const approvedGyms = user?.gyms.filter((gym) => gym.status === 'approved') ?? []
  const currentGym = approvedGym(user, Number(pathname.match(/^\/gyms\/(\d+)(?:\/|$)/)?.[1]))
  const roleLabel = user?.is_platform_admin
    ? 'Platform administrator'
    : currentGym
      ? (currentGym.role_names ?? []).length > 0
        ? (currentGym.role_names ?? []).join(' · ')
        : gymRoleLabel(currentGym.roles)
      : user?.account_type === 'owner'
        ? 'Gym owner'
        : 'Member'

  const general: Item[] = [{ to: '/', label: 'Home', icon: House, end: true }]
  const isMemberDashboard = !user?.is_platform_admin && (user?.account_type === 'member' || user?.has_member_records)
  if (isMemberDashboard) {
    general.push({ to: '/memberships', label: 'My memberships', icon: IdCard })
    general.push({ to: '/bookings', label: 'My bookings', icon: CalendarDays })
  }
  // R-106: staff self-service is part of HR, so it hides when the gym's plan locks HR.
  if (currentGym?.roles.includes('employee') && !currentGym.subscription?.locked_modules.includes('hr')) general.push(myWorkNav(currentGym.id))
  if (currentGym?.roles.includes('member')) general.push({ to: `/gyms/${currentGym.id}/my-record`, label: 'My record', icon: Medal })
  if (currentGym?.roles.includes('member')) general.push({ to: `/gyms/${currentGym.id}/sessions`, label: 'Sessions', icon: CalendarDays })

  const workspaceGroups = currentGym ? workspaceNavGroups(currentGym) : []
  const owner: Item[] = [
    { to: '/applications', label: 'My applications', icon: FileText },
    { to: '/apply', label: 'New application', icon: FilePlus2 },
  ]
  const admin: Item[] = [
    { to: '/admin', label: 'Gym applications', icon: ClipboardCheck, end: true },
    { to: '/admin/notices', label: 'Platform notices', icon: FileText },
    { to: '/admin/plans', label: 'Subscription plans', icon: CreditCard },
  ]

  return (
    <SidebarProvider>
      <a
        className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:rounded-lg focus:bg-background focus:px-4 focus:py-2 focus:font-medium focus:text-sm focus:ring-2 focus:ring-ring"
        href="#main-content"
      >
        Skip to content
      </a>
      <Sidebar collapsible="icon">
        <SidebarHeader className="safe-area-top [--safe-area-top-padding:0.5rem]">
          <SidebarMenu>
            <SidebarMenuItem>
              <SidebarMenuButton size="lg" tooltip="Combat Platform" render={<Link to="/" />}>
                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary font-semibold text-primary-foreground text-sm">
                  CP
                </span>
                <span className="grid text-left leading-tight">
                  <span className="font-semibold">Combat Platform</span>
                  <span className="text-muted-foreground text-xs">Training centers</span>
                </span>
              </SidebarMenuButton>
            </SidebarMenuItem>
            {approvedGyms.length > 0 && <GymSwitcher gyms={approvedGyms} current={currentGym} />}
          </SidebarMenu>
        </SidebarHeader>
        <SidebarContent>
          <NavGroup items={general} />
          {workspaceGroups.map((group) => (
            <NavGroup key={group.label} label={group.label} items={group.items} />
          ))}
          {user?.is_platform_admin && <NavGroup label="Platform" items={admin} />}
          {user?.account_type === 'owner' && <NavGroup label="Run a gym" items={owner} />}
        </SidebarContent>
        <SidebarFooter className="safe-area-bottom [--safe-area-bottom-padding:0.5rem]">
          <SidebarMenu>
            <SidebarMenuItem>
              <AccountMenu roleLabel={roleLabel} />
            </SidebarMenuItem>
          </SidebarMenu>
        </SidebarFooter>
      </Sidebar>

      <SidebarInset className="min-w-0" id="main-content" tabIndex={-1}>
        <header className="safe-area-top sticky top-0 z-20 flex h-[calc(3.5rem+env(safe-area-inset-top,0px))] shrink-0 items-center gap-2 border-b bg-background px-3 md:px-4">
          <SidebarTrigger className="size-11 md:size-7" aria-label="Toggle navigation" />
          <span className="font-semibold text-sm md:hidden">Combat Platform</span>
          <div className="ms-auto flex items-center gap-1">
            <ThemeToggle />
            <span className="contents" title={unread ? `${unread} unread notifications` : 'Notifications'}>
              <NotificationsMenu />
            </span>
          </div>
        </header>
        <div className="safe-area-bottom mx-auto w-full min-w-0 max-w-6xl wrap-anywhere px-4 pt-6 [--safe-area-bottom-padding:4rem] md:px-8">
          {children ?? <Outlet />}
        </div>
      </SidebarInset>
    </SidebarProvider>
  )
}
