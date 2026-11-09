import { Check } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { planPrice, type SubscriptionModule, type SubscriptionPlan } from '@/lib/subscriptions'

/**
 * One platform plan, laid out like a pricing card: name, price, the action, then what it includes.
 * Only the gym's real current plan is highlighted, never a made-up "most popular" tier.
 */
export function PlanCard({
  plan,
  modules,
  current = false,
  badge,
  action,
  footer,
}: {
  plan: SubscriptionPlan
  modules: SubscriptionModule[]
  current?: boolean
  badge?: React.ReactNode
  action?: React.ReactNode
  footer?: React.ReactNode
}) {
  const [tagline, ...points] = (plan.inclusions ?? '').split('\n').map((line) => line.trim()).filter(Boolean)
  const label = (key: string) => modules.find((module) => module.key === key)?.label ?? key
  const included = modules.filter((module) => plan.modules.includes(module.key))
  const missing = modules.filter((module) => !plan.modules.includes(module.key)).map((module) => module.label)

  return (
    <article
      aria-label={`${plan.name} plan${current ? ', your current plan' : ''}`}
      className={cn(
        'flex min-w-0 flex-col rounded-2xl border bg-card p-6',
        current && 'border-primary/60 ring-1 ring-primary/40',
        plan.retired && 'opacity-70',
      )}
    >
      <div className="flex flex-wrap items-center gap-2">
        <h3 className="font-semibold text-lg">{plan.name}</h3>
        {badge}
      </div>
      <p className="mt-1 min-h-10 text-muted-foreground text-sm">{tagline ?? (plan.is_free ? 'The plan every gym falls back to.' : '')}</p>

      <p className="mt-5 flex items-baseline gap-1.5">
        <span className="font-semibold text-4xl tabular-nums tracking-tight">{planPrice(plan.monthly_price)}</span>
        <span className="text-muted-foreground text-sm">/ month</span>
      </p>
      <p className="mt-1 text-muted-foreground text-xs">{plan.is_free ? 'No charge' : 'Billed monthly through PayMongo'}</p>

      {action && <div className="mt-5">{action}</div>}

      <div className="mt-6 border-t pt-5">
        {points.length > 0 && (
          <ul className="mb-4 flex flex-col gap-2 text-sm">
            {points.map((point) => (
              <li key={point} className="flex gap-2.5">
                <Check aria-hidden className="mt-0.5 size-4 shrink-0 text-success-foreground" />
                <span>{point}</span>
              </li>
            ))}
          </ul>
        )}
        <p className="font-medium text-muted-foreground text-xs">Modules</p>
        <ul className="mt-2 flex flex-wrap gap-1.5" aria-label={`${plan.name} modules`}>
          {included.length === 0
            ? <li className="text-muted-foreground text-sm">None</li>
            : included.map((module) => <li key={module.key}><Badge variant="secondary">{label(module.key)}</Badge></li>)}
        </ul>
        {missing.length > 0 && (
          <p className="mt-3 text-muted-foreground text-xs">Not included: {missing.join(', ')}</p>
        )}
      </div>

      {footer && <div className="mt-auto pt-5">{footer}</div>}
    </article>
  )
}

/** Cards size themselves, so two plans or five both fill the row without a forced three-column layout. */
export const PLAN_GRID = 'grid gap-4 [grid-template-columns:repeat(auto-fill,minmax(min(100%,17rem),1fr))]'
