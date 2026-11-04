import { Bell, Building2, Check, ChevronsUpDown, ClipboardCheck, FilePlus2, FileText, House, LogOut, UserRound } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router'
import { myWorkNav, workspaceNav } from '@/components/gym-nav'
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
  SidebarMenuBadge,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider,
  SidebarTrigger,
  useSidebar,
} from '@/components/ui/sidebar'
import { api } from '@/lib/api'
import { useAuth, type GymAccess } from '@/lib/auth'
import { approvedGym, canManage } from '@/lib/gym-access'

type Item = { to: string; label: string; icon: React.ComponentType; end?: boolean; badge?: number }

/** Where a gym's card in the switcher leads: workspace when allowed, public page otherwise. */
function gymEntry(gym: GymAccess): string {
  if (canManage(gym)) return `/gyms/${gym.id}/manage`
  if (gym.roles.includes('employee')) return `/gyms/${gym.id}/manage/my-work`
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
          {items.map(({ to, label: text, icon: Icon, end, badge }) => {
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
                {badge ? <SidebarMenuBadge aria-label={`${badge} unread`}>{badge}</SidebarMenuBadge> : null}
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
            <span className="truncate font-medium">{current?.name ?? 'Choose a gym'}</span>
            <span className="truncate text-muted-foreground text-xs">
              {current ? 'Gym workspace' : `${gyms.length} approved`}
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

function AccountMenu() {
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
          <span className="truncate text-muted-foreground text-xs">
            {user.is_platform_admin ? 'Platform administrator' : user.email}
          </span>
        </span>
        <ChevronsUpDown className="ms-auto size-4 opacity-60" />
      </MenuTrigger>
      <MenuPopup side="top" align="start" className="min-w-56">
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

/** Frame for every signed-in screen: a sidebar that shows only what this person's role allows. */
export function AppShell() {
  const { user } = useAuth()
  const { pathname } = useLocation()
  const [unread, setUnread] = useState(0)

  useEffect(() => {
    api<{ unread_count: number }>('/notifications').then((r) => setUnread(r.unread_count)).catch(() => undefined)
  }, [])

  const approvedGyms = user?.gyms.filter((gym) => gym.status === 'approved') ?? []
  const currentGym = approvedGym(user, Number(pathname.match(/^\/gyms\/(\d+)(?:\/|$)/)?.[1]))

  const general: Item[] = [
    { to: '/', label: 'Home', icon: House, end: true },
    { to: '/notifications', label: 'Notifications', icon: Bell, badge: unread },
  ]
  if (currentGym?.roles.includes('employee')) general.push(myWorkNav(currentGym.id))

  const workspace = currentGym ? workspaceNav(currentGym) : []
  const owner: Item[] = [
    { to: '/applications', label: 'My applications', icon: FileText },
    { to: '/apply', label: 'New application', icon: FilePlus2 },
  ]
  const admin: Item[] = [{ to: '/admin', label: 'Gym applications', icon: ClipboardCheck }]

  return (
    <SidebarProvider>
      <Sidebar collapsible="icon">
        <SidebarHeader>
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
          {workspace.length > 0 && <NavGroup label={currentGym?.name ?? 'Workspace'} items={workspace} />}
          {user?.is_platform_admin ? <NavGroup label="Platform" items={admin} /> : <NavGroup label="Run a gym" items={owner} />}
        </SidebarContent>
        <SidebarFooter>
          <SidebarMenu>
            <SidebarMenuItem>
              <AccountMenu />
            </SidebarMenuItem>
          </SidebarMenu>
        </SidebarFooter>
      </Sidebar>

      <SidebarInset>
        <header className="sticky top-0 z-10 flex h-12 items-center gap-2 border-b bg-background/95 px-3 backdrop-blur-sm md:hidden">
          <SidebarTrigger aria-label="Open navigation" />
          <span className="font-semibold text-sm">Combat Platform</span>
        </header>
        <div className="hidden px-3 pt-3 md:block">
          <SidebarTrigger aria-label="Collapse navigation" />
        </div>
        <div className="mx-auto w-full max-w-6xl px-4 pt-4 pb-16 md:px-8 md:pt-2">
          <Outlet context={{ setUnread }} />
        </div>
      </SidebarInset>
    </SidebarProvider>
  )
}
