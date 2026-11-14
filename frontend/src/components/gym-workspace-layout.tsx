import { Link, Navigate, Outlet, useLocation } from 'react-router'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { useAuth, type GymAccess } from '@/lib/auth'
import { planLockedSection } from '@/components/gym-nav'
import { approvedGym } from '@/lib/gym-access'
import { dateLabel } from '@/lib/membership'
import { daysLeft } from '@/lib/subscriptions'

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
  const locked = planLockedSection(gym, pathname)

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-baseline gap-2">
        <Link className="font-semibold underline-offset-4 hover:underline" to={`/gyms/${gym.id}`}>
          {gym.name}
        </Link>
        <span className="text-muted-foreground text-sm">workspace</span>
      </div>
      {gym.roles.includes('owner') && !locked && !pathname.endsWith('/subscription') && <PlanNotice gym={gym} />}
      {locked ? <PlanLocked gym={gym} section={locked} /> : <Outlet />}
    </div>
  )
}

/** R-106, R-107, R-139: tells the owner when modules are locked or the trial or paid month is about to end. */
function PlanNotice({ gym }: { gym: GymAccess }) {
  const sub = gym.subscription
  if (!sub || sub.status === 'unmanaged') return null
  const left = daysLeft(sub.period_ends_at)
  const ending = left !== null && left <= 7
  if (sub.status === 'paid' && !ending) return null

  const title = sub.status === 'free'
    ? 'Your gym is on the free plan'
    : sub.status === 'trial'
      ? `Free trial: ${left} ${left === 1 ? 'day' : 'days'} left`
      : `Your paid plan ends in ${left} ${left === 1 ? 'day' : 'days'}`
  const body = sub.status === 'free'
    ? `${sub.locked_modules.length} ${sub.locked_modules.length === 1 ? 'module is' : 'modules are'} locked. Your records are kept and come back when you subscribe.`
    : sub.status === 'trial'
      ? `Every module is open until ${dateLabel(sub.period_ends_at)}. Choose a plan before then to keep the modules you use.`
      : 'Subscribe to keep every module you use. Without a plan, the gym moves to the free plan.'
  const warn = sub.status === 'free' || ending

  return (
    <Alert variant={warn ? 'warning' : 'info'}>
      <AlertTitle>{title}</AlertTitle>
      <AlertDescription>
        <p>{body}</p>
        <Button size="sm" variant="outline" className="w-fit" render={<Link to={`/gyms/${gym.id}/manage/subscription`} />}>See plans</Button>
      </AlertDescription>
    </Alert>
  )
}

/** One honest message for a section the plan leaves out, instead of each page's "ask for access" text. */
function PlanLocked({ gym, section }: { gym: GymAccess; section: string }) {
  const owner = gym.roles.includes('owner')
  return (
    <section aria-labelledby="plan-locked" className="flex flex-col items-start gap-3 rounded-xl border bg-card p-6">
      <h1 id="plan-locked" className="font-semibold text-xl">{section} is not in your gym's plan</h1>
      <p className="max-w-prose text-muted-foreground text-sm">
        {owner
          ? 'Your records here are kept. Subscribe to a plan that includes this module to open it again.'
          : "The gym's current plan leaves this module out. Ask the owner to subscribe to a plan that includes it."}
      </p>
      {owner && <Button render={<Link to={`/gyms/${gym.id}/manage/subscription`} />}>See plans</Button>}
    </section>
  )
}
