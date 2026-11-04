import { CreditCard, Pencil, Plus, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useParams } from 'react-router'
import { PlanEditor } from '@/components/offer/plan-editor'
import { PageHeader } from '@/components/page-header'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { labelKey, type PublicPlan, type PublicService } from '@/lib/discovery'
import { approvedGym, can } from '@/lib/gym-access'
import type { WorkspaceOptions } from '@/lib/workspace'

function priceLabel(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return 'Not set'
  const amount = Number(value)
  if (!Number.isFinite(amount)) return 'Not set'
  return `₱${amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function billingLength(plan: PublicPlan): string {
  if (plan.billing_type === 'sessions') {
    return `${plan.session_count ?? 0} ${plan.session_count === 1 ? 'session' : 'sessions'}`
  }
  return `${plan.duration_months ?? 0} ${plan.duration_months === 1 ? 'month' : 'months'}`
}

function upsert(plans: PublicPlan[], saved: PublicPlan): PublicPlan[] {
  const next = plans.some((plan) => plan.id === saved.id)
    ? plans.map((plan) => (plan.id === saved.id ? saved : plan))
    : [...plans, saved]
  return next.sort((left, right) => left.name.localeCompare(right.name))
}

/** R-29: membership plans, their inclusions, add-ons and policies. */
export default function GymPlans() {
  const { gymId } = useParams()
  const { user } = useAuth()
  const gym = approvedGym(user, Number(gymId))
  const canView = gym ? can(gym, 'members', 'view') : false
  const canManage = gym ? can(gym, 'members', 'manage') : false

  const [plans, setPlans] = useState<PublicPlan[] | null>(null)
  const [options, setOptions] = useState<WorkspaceOptions | null>(null)
  const [services, setServices] = useState<PublicService[]>([])
  const [servicesError, setServicesError] = useState('')
  const [loadError, setLoadError] = useState('')
  const [version, setVersion] = useState(0)
  const [editing, setEditing] = useState<PublicPlan | 'new' | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<PublicPlan | null>(null)
  const [deleteError, setDeleteError] = useState('')
  const [deleting, setDeleting] = useState(false)

  useEffect(() => {
    if (!gymId || !canView) return

    let live = true
    setLoadError('')
    setServicesError('')

    Promise.allSettled([
      api<{ plans: PublicPlan[] }>(`/gyms/${gymId}/plans`),
      api<{ options: WorkspaceOptions }>(`/gyms/${gymId}/options`),
      api<{ services: PublicService[] }>(`/gyms/${gymId}/services`),
    ]).then(([plansResult, optionsResult, servicesResult]) => {
      if (!live) return

      if (plansResult.status === 'rejected' || optionsResult.status === 'rejected') {
        const error =
          plansResult.status === 'rejected'
            ? plansResult.reason
            : optionsResult.status === 'rejected'
              ? optionsResult.reason
              : null
        setLoadError(error instanceof ApiError ? error.message : 'The membership plans could not be loaded.')
        return
      }

      setPlans(plansResult.value.plans)
      setOptions(optionsResult.value.options)

      if (servicesResult.status === 'fulfilled') {
        setServices(servicesResult.value.services)
      } else {
        setServices([])
        setServicesError(
          servicesResult.reason instanceof ApiError
            ? servicesResult.reason.message
            : 'The service list could not be loaded.',
        )
      }
    })

    return () => {
      live = false
    }
  }, [gymId, canView, version])

  if (!gym) return null

  if (!canView) {
    return (
      <Empty className="rounded-xl border">
        <EmptyHeader>
          <EmptyTitle>Membership plans are not available</EmptyTitle>
          <EmptyDescription>Your role does not include members access for this gym.</EmptyDescription>
        </EmptyHeader>
      </Empty>
    )
  }

  const reload = () => {
    setPlans(null)
    setOptions(null)
    setServices([])
    setVersion((value) => value + 1)
  }

  async function removePlan() {
    if (!gymId || !deleteTarget) return
    setDeleting(true)
    setDeleteError('')
    try {
      await api(`/gyms/${gymId}/plans/${deleteTarget.id}`, { method: 'DELETE' })
      setPlans((current) => (current ? current.filter((plan) => plan.id !== deleteTarget.id) : current))
      setDeleteTarget(null)
      toastManager.add({ title: 'Plan deleted.', type: 'success' })
    } catch (error) {
      setDeleteError(error instanceof ApiError ? error.message : 'The plan could not be deleted.')
    } finally {
      setDeleting(false)
    }
  }

  if (editing !== null && options) {
    return (
      <PlanEditor
        gymId={gymId ?? ''}
        plan={editing === 'new' ? null : editing}
        options={options}
        services={services}
        servicesError={servicesError}
        onBack={() => setEditing(null)}
        onSaved={(saved) => setPlans((current) => upsert(current ?? [], saved))}
      />
    )
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Membership plans"
        description="Time-based and session-based plans, their inclusions, add-ons and policies. Only published plans appear on the public page."
        actions={
          canManage && (
            <Button onClick={() => setEditing('new')}>
              <Plus aria-hidden /> Add plan
            </Button>
          )
        }
        actionsClassName="max-sm:w-full max-sm:justify-end"
      />

      {loadError ? (
        <Alert variant="error">
          <AlertTitle>Membership plans could not be loaded</AlertTitle>
          <AlertDescription>
            <p>{loadError}</p>
            <Button size="sm" variant="outline" onClick={reload}>
              Try again
            </Button>
          </AlertDescription>
        </Alert>
      ) : !plans || !options ? (
        <div className="grid gap-3 xl:grid-cols-2" aria-busy="true">
          <Skeleton className="h-40" />
          <Skeleton className="h-40" />
          <Skeleton className="h-40" />
          <Skeleton className="h-40" />
        </div>
      ) : plans.length === 0 ? (
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>No plans yet</EmptyTitle>
            <EmptyDescription>
              Add a membership plan with one total fee, what it covers, optional add-ons and its policies. It stays a draft until you publish it.
            </EmptyDescription>
          </EmptyHeader>
          {canManage && (
            <EmptyContent>
              <Button onClick={() => setEditing('new')}>
                <Plus aria-hidden /> Add the first plan
              </Button>
            </EmptyContent>
          )}
        </Empty>
      ) : (
        <ul className="grid gap-3 xl:grid-cols-2">
          {plans.map((plan) => {
            const addonCount = plan.addons?.length ?? 0
            return (
              <li key={plan.id} className="min-w-0">
                <Card className="flex flex-col gap-3 p-4">
                  <div className="flex flex-wrap items-start gap-2">
                    <span className="mr-auto flex items-center gap-2 font-semibold">
                      <CreditCard className="size-4 text-muted-foreground" aria-hidden />
                      {plan.name}
                    </span>
                    <Badge variant={plan.published_at ? 'success' : 'secondary'}>
                      {plan.published_at ? 'Published' : 'Draft'}
                    </Badge>
                  </div>

                  <p className="text-muted-foreground text-sm">
                    {(options.plan_billing as Record<string, string>)[plan.billing_type] ?? labelKey(plan.billing_type)} ·{' '}
                    {billingLength(plan)}
                  </p>

                  <div className="flex flex-wrap items-baseline gap-2">
                    <span className="font-semibold text-lg tabular-nums">{priceLabel(plan.total_fee)}</span>
                    {plan.registration_included && <Badge variant="outline">Registration included</Badge>}
                  </div>

                  <div className="flex flex-wrap items-center gap-2 text-sm">
                    <span className="text-muted-foreground">
                      {addonCount} {addonCount === 1 ? 'add-on' : 'add-ons'}
                    </span>
                    <span className="text-muted-foreground">·</span>
                    <span className="text-muted-foreground">
                      {(plan.inclusions ?? []).length} {(plan.inclusions ?? []).length === 1 ? 'inclusion' : 'inclusions'}
                    </span>
                  </div>

                  {canManage && (
                    <div className="flex flex-wrap gap-2 pt-1">
                      <Button size="sm" variant="outline" onClick={() => setEditing(plan)}>
                        <Pencil aria-hidden /> Edit
                      </Button>
                      <Button
                        size="sm"
                        variant="destructive-outline"
                        onClick={() => {
                          setDeleteError('')
                          setDeleteTarget(plan)
                        }}
                      >
                        <Trash2 aria-hidden /> Delete
                      </Button>
                    </div>
                  )}
                </Card>
              </li>
            )
          })}
        </ul>
      )}

      <AlertDialog
        open={deleteTarget !== null}
        onOpenChange={(open) => {
          if (!open) {
            setDeleteTarget(null)
            setDeleteError('')
          }
        }}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete this plan?</AlertDialogTitle>
            <AlertDialogDescription>
              {deleteTarget?.name} and its add-ons will be removed. Members already on the plan should be moved first.
            </AlertDialogDescription>
          </AlertDialogHeader>
          {deleteError && (
            <div className="px-6 pb-4">
              <Alert variant="error">
                <AlertTitle>Plan was not deleted</AlertTitle>
                <AlertDescription>{deleteError}</AlertDescription>
              </Alert>
            </div>
          )}
          <AlertDialogFooter>
            <Button variant="outline" onClick={() => setDeleteTarget(null)}>
              Cancel
            </Button>
            <Button variant="destructive" loading={deleting} onClick={removePlan}>
              Delete plan
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
