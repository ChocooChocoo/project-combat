import { Image as ImageIcon, Pencil, Plus, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useParams } from 'react-router'
import { ServiceEditor } from '@/components/offer/service-editor'
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
import { labelKey, type PublicService } from '@/lib/discovery'
import { approvedGym, can } from '@/lib/gym-access'
import type { WorkspaceOptions } from '@/lib/workspace'

function priceLabel(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return ''
  const amount = Number(value)
  if (!Number.isFinite(amount)) return ''
  return `₱${amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function upsert(services: PublicService[], saved: PublicService): PublicService[] {
  const next = services.some((service) => service.id === saved.id)
    ? services.map((service) => (service.id === saved.id ? saved : service))
    : [...services, saved]
  return next.sort((left, right) => left.name.localeCompare(right.name))
}

/** R-28: the gym's service list, with full editing, photos and publishing. */
export default function GymServices() {
  const { gymId } = useParams()
  const { user } = useAuth()
  const gym = approvedGym(user, Number(gymId))
  const canView = gym ? can(gym, 'gym_setup.services.view') : false
  const canManage = gym ? can(gym, 'gym_setup.services.manage') : false

  const [services, setServices] = useState<PublicService[] | null>(null)
  const [options, setOptions] = useState<WorkspaceOptions | null>(null)
  const [loadError, setLoadError] = useState('')
  const [version, setVersion] = useState(0)
  const [editing, setEditing] = useState<PublicService | 'new' | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<PublicService | null>(null)
  const [deleteError, setDeleteError] = useState('')
  const [deleting, setDeleting] = useState(false)

  useEffect(() => {
    if (!gymId || !canView) return

    let live = true
    setLoadError('')
    Promise.all([
      api<{ services: PublicService[] }>(`/gyms/${gymId}/services`),
      api<{ options: WorkspaceOptions }>(`/gyms/${gymId}/options`),
    ])
      .then(([servicesResult, optionsResult]) => {
        if (!live) return
        setServices(servicesResult.services)
        setOptions(optionsResult.options)
      })
      .catch((error) => {
        if (live) setLoadError(error instanceof ApiError ? error.message : 'The services could not be loaded.')
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
          <EmptyTitle>Services are not available</EmptyTitle>
          <EmptyDescription>Your role does not include gym setup access for this gym.</EmptyDescription>
        </EmptyHeader>
      </Empty>
    )
  }

  const reload = () => {
    setServices(null)
    setOptions(null)
    setVersion((value) => value + 1)
  }

  async function removeService() {
    if (!gymId || !deleteTarget) return
    setDeleting(true)
    setDeleteError('')
    try {
      await api(`/gyms/${gymId}/services/${deleteTarget.id}`, { method: 'DELETE' })
      setServices((current) => (current ? current.filter((service) => service.id !== deleteTarget.id) : current))
      setDeleteTarget(null)
      toastManager.add({ title: 'Service deleted.', type: 'success' })
    } catch (error) {
      setDeleteError(
        error instanceof ApiError
          ? (error.errors.service?.[0] ?? error.message)
          : 'The service could not be deleted.',
      )
    } finally {
      setDeleting(false)
    }
  }

  if (editing !== null && options) {
    return (
      <ServiceEditor
        gymId={gymId ?? ''}
        service={editing === 'new' ? null : editing}
        options={options}
        onBack={() => setEditing(null)}
        onSaved={(saved) => setServices((current) => upsert(current ?? [], saved))}
      />
    )
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Services"
        description="Classes, programs and coaching the gym offers, with photos and prices. Only published services appear on the public page."
        actions={
          canManage && (
            <Button onClick={() => setEditing('new')}>
              <Plus aria-hidden /> Add service
            </Button>
          )
        }
      />

      {loadError ? (
        <Alert variant="error">
          <AlertTitle>Services could not be loaded</AlertTitle>
          <AlertDescription>
            <p>{loadError}</p>
            <Button size="sm" variant="outline" onClick={reload}>
              Try again
            </Button>
          </AlertDescription>
        </Alert>
      ) : !services || !options ? (
        <div className="grid gap-3 xl:grid-cols-2" aria-busy="true">
          <Skeleton className="h-36" />
          <Skeleton className="h-36" />
          <Skeleton className="h-36" />
          <Skeleton className="h-36" />
        </div>
      ) : services.length === 0 ? (
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>No services yet</EmptyTitle>
            <EmptyDescription>
              Add a class, program or coaching service. A service needs a cover photo and all its details before it can be published.
            </EmptyDescription>
          </EmptyHeader>
          {canManage && (
            <EmptyContent>
              <Button onClick={() => setEditing('new')}>
                <Plus aria-hidden /> Add the first service
              </Button>
            </EmptyContent>
          )}
        </Empty>
      ) : (
        <ul className="grid gap-3 xl:grid-cols-2">
          {services.map((service) => {
            const galleryCount = service.photos?.length ?? 0
            return (
              <li key={service.id} className="min-w-0">
                <Card className="flex flex-col gap-4 p-4 sm:flex-row">
                  <div className="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-muted text-muted-foreground">
                    {service.cover_photo_url ? (
                      <img className="size-full object-cover" src={service.cover_photo_url} alt="" loading="lazy" />
                    ) : (
                      <ImageIcon className="size-6" aria-hidden />
                    )}
                  </div>

                  <div className="flex min-w-0 flex-1 flex-col gap-2">
                    <div className="flex flex-wrap items-start gap-2">
                      <span className="mr-auto font-semibold">{service.name}</span>
                      <Badge variant={service.published_at ? 'success' : 'secondary'}>
                        {service.published_at ? 'Published' : 'Draft'}
                      </Badge>
                    </div>

                    <p className="text-muted-foreground text-sm">
                      {options.disciplines[service.discipline ?? ''] ?? labelKey(service.discipline ?? '')} ·{' '}
                      {options.activity_types[service.activity_type ?? ''] ?? labelKey(service.activity_type ?? '')}
                      {service.duration_minutes ? ` · ${service.duration_minutes} min` : ''}
                      {service.frequency ? ` · ${options.frequencies[service.frequency] ?? labelKey(service.frequency)}` : ''}
                    </p>

                    {service.overview && <p className="line-clamp-2 text-sm">{service.overview}</p>}

                    <div className="flex flex-wrap items-center gap-2 text-sm">
                      <span className="font-medium">
                        {service.price_included ? 'Included in membership' : priceLabel(service.price) || 'No price set'}
                      </span>
                      <span className="text-muted-foreground">·</span>
                      <span className="text-muted-foreground">
                        {galleryCount} {galleryCount === 1 ? 'gallery photo' : 'gallery photos'}
                      </span>
                      {!service.cover_photo_url && (
                        <>
                          <span className="text-muted-foreground">·</span>
                          <Badge variant="warning">No cover photo</Badge>
                        </>
                      )}
                    </div>

                    {canManage && (
                      <div className="flex flex-wrap gap-2 pt-1">
                        <Button size="sm" variant="outline" onClick={() => setEditing(service)}>
                          <Pencil aria-hidden /> Edit
                        </Button>
                        <Button
                          size="sm"
                          variant="destructive-outline"
                          onClick={() => {
                            setDeleteError('')
                            setDeleteTarget(service)
                          }}
                        >
                          <Trash2 aria-hidden /> Delete
                        </Button>
                      </div>
                    )}
                  </div>
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
            <AlertDialogTitle>Delete this service?</AlertDialogTitle>
            <AlertDialogDescription>
              {deleteTarget?.name} and its photos will be removed. A service used by a membership plan cannot be deleted until it is removed from that plan.
            </AlertDialogDescription>
          </AlertDialogHeader>
          {deleteError && (
            <div className="px-6 pb-4">
              <Alert variant="error">
                <AlertTitle>Service was not deleted</AlertTitle>
                <AlertDescription>{deleteError}</AlertDescription>
              </Alert>
            </div>
          )}
          <AlertDialogFooter>
            <Button variant="outline" onClick={() => setDeleteTarget(null)}>
              Cancel
            </Button>
            <Button variant="destructive" loading={deleting} onClick={removeService}>
              Delete service
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
