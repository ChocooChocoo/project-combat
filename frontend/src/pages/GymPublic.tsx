import { ArrowLeft, Check, ChevronDown, Clock, Dumbbell, Mail, MapPin, Phone, Users } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { NotificationsMenu, type NoticeList } from '@/components/notifications-menu'
import { PageHeader } from '@/components/page-header'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Collapsible, CollapsiblePanel, CollapsibleTrigger } from '@/components/ui/collapsible'
import { Dialog, DialogDescription, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { api, ApiError } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { type GymDetailPayload, labelKey, type PublicCoach, type PublicGymDetail, type PublicPlan, type PublicService, readGymDetail } from '@/lib/discovery'
import { approvedGym, canManage } from '@/lib/gym-access'
import { DAYS } from '@/lib/gyms'
import { cn } from '@/lib/utils'

const ACTIVITY_LABELS: Record<string, string> = {
  classes: 'Classes',
  programs: 'Programs',
  private_coaching: 'Private coaching',
  sparring: 'Sparring',
  open_mat: 'Open mat',
}

const LEVEL_LABELS: Record<string, string> = {
  beginner: 'Beginner',
  intermediate: 'Intermediate',
  advanced: 'Advanced',
  all: 'All levels',
}

const FREQUENCY_LABELS: Record<string, string> = {
  daily: 'Daily',
  weekly: 'Weekly',
  monthly: 'Monthly',
  one_off: 'One off',
}

const DISCIPLINE_LABELS: Record<string, string> = {
  boxing: 'Boxing',
  muay_thai: 'Muay Thai',
  kickboxing: 'Kickboxing',
  mma: 'Mixed martial arts',
  bjj: 'Brazilian jiu-jitsu',
  wrestling: 'Wrestling',
  judo: 'Judo',
  karate: 'Karate',
  taekwondo: 'Taekwondo',
  arnis: 'Arnis',
}

const POLICY_LABELS: { key: keyof Pick<PublicPlan, 'policy_validity' | 'policy_freezing' | 'policy_renewal' | 'policy_cancellation'>; label: string }[] = [
  { key: 'policy_validity', label: 'Validity' },
  { key: 'policy_freezing', label: 'Freezing' },
  { key: 'policy_renewal', label: 'Renewal' },
  { key: 'policy_cancellation', label: 'Cancellation and refunds' },
]

type Gallery = {
  title: string
  images: string[]
  index: number
}

function money(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return ''
  const amount = Number(value)
  if (!Number.isFinite(amount)) return ''
  return `₱${amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function timeLabel(value: string): string {
  const [hourText, minute = '00'] = value.split(':')
  const hour = Number(hourText)
  if (!Number.isFinite(hour)) return value
  const suffix = hour >= 12 ? 'PM' : 'AM'
  return `${hour % 12 || 12}:${minute.padStart(2, '0')} ${suffix}`
}

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase()
}

function serviceImages(service: PublicService): string[] {
  const gallery = service.photos?.map((photo) => photo.url) ?? service.photo_urls ?? []
  return [...new Set([service.cover_photo_url, ...gallery].filter((url): url is string => Boolean(url)))]
}

function planLength(plan: PublicPlan): string {
  if (plan.billing_type === 'sessions') {
    return `${plan.session_count ?? 0} ${plan.session_count === 1 ? 'session' : 'sessions'}`
  }
  return `${plan.duration_months ?? 0} ${plan.duration_months === 1 ? 'month' : 'months'}`
}

function BrandBar({ signedIn }: { signedIn: boolean }) {
  const [unread, setUnread] = useState(0)

  useEffect(() => {
    if (signedIn) api<NoticeList>('/notifications').then((r) => setUnread(r.unread_count)).catch(() => undefined)
  }, [signedIn])

  return (
    <header className="flex flex-wrap items-center gap-3">
      <Link className="flex items-center gap-2 font-semibold" to="/">
        <span className="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground text-sm">CP</span>
        Combat Platform
      </Link>
      <div className="ms-auto flex items-center gap-2">
        {signedIn && <NotificationsMenu unread={unread} onUnreadChange={setUnread} />}
        <Button size="sm" variant="outline" render={<Link to={signedIn ? '/' : '/sign-in'} />}>
          {signedIn ? 'Home' : 'Sign in'}
        </Button>
      </div>
    </header>
  )
}

/** S-10, R-20, R-28 and R-29: the public page for one approved gym. */
export default function GymPublic() {
  const { gymId } = useParams()
  const { user, loading: authLoading } = useAuth()
  const id = Number(gymId)
  const userId = user?.id ?? null

  const [gym, setGym] = useState<PublicGymDetail | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<{ status: number; message: string } | null>(null)
  const [version, setVersion] = useState(0)
  const [gallery, setGallery] = useState<Gallery | null>(null)

  useEffect(() => {
    if (!userId || !Number.isInteger(id) || id <= 0) {
      setGym(null)
      setError(null)
      setLoading(false)
      return
    }

    let live = true
    setLoading(true)
    setError(null)

    api<{ gym: GymDetailPayload }>(`/gyms/${id}`)
      .then((result) => {
        if (live) setGym(readGymDetail(result.gym))
      })
      .catch((failure) => {
        if (!live) return
        setGym(null)
        setError({
          status: failure instanceof ApiError ? failure.status : 0,
          message: failure instanceof ApiError ? failure.message : 'This gym could not be loaded.',
        })
      })
      .finally(() => {
        if (live) setLoading(false)
      })

    return () => {
      live = false
    }
  }, [id, userId, version])

  const access = approvedGym(user, id)
  const manage = access ? canManage(access) : false

  if (authLoading) {
    return (
      <main className="mx-auto flex min-h-svh w-full max-w-6xl flex-col gap-8 px-4 py-8 md:px-8">
        <BrandBar signedIn={false} />
        <div className="flex flex-col gap-6" aria-busy="true">
          <Skeleton className="h-24" />
          <Skeleton className="h-64" />
        </div>
      </main>
    )
  }

  if (!user) {
    return (
      <main className="mx-auto flex min-h-svh w-full max-w-6xl flex-col gap-8 px-4 py-8 md:px-8">
        <BrandBar signedIn={false} />
        <Card className="mx-auto w-full max-w-lg">
          <CardHeader>
            <CardTitle render={<h1 />}>Sign in to view this gym</CardTitle>
            <CardDescription>
              Gym profiles are for people with a platform account. Sign in to see the gym&apos;s services, plans, coaches and location.
            </CardDescription>
          </CardHeader>
          <CardPanel className="flex flex-wrap items-center gap-3">
            <Button render={<Link to="/sign-in" />}>Sign in</Button>
            <Link className="text-sm underline underline-offset-4" to="/sign-up">
              Create an account
            </Link>
          </CardPanel>
        </Card>
      </main>
    )
  }

  if (error?.status === 404 || !Number.isInteger(id) || id <= 0) {
    return (
      <main className="mx-auto flex min-h-svh w-full max-w-6xl flex-col gap-8 px-4 py-8 md:px-8">
        <BrandBar signedIn />
        <PageHeader title="Gym not found" description="This gym is not on the platform, or it is not approved yet." />
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>No public page for this gym</EmptyTitle>
            <EmptyDescription>Only approved gyms appear on the platform. Check the link, or look for another gym from the home page.</EmptyDescription>
          </EmptyHeader>
          <Button render={<Link to="/" />}>Back to home</Button>
        </Empty>
      </main>
    )
  }

  if (error) {
    return (
      <main className="mx-auto flex min-h-svh w-full max-w-6xl flex-col gap-8 px-4 py-8 md:px-8">
        <BrandBar signedIn />
        <PageHeader title="Gym profile" description="This gym could not be loaded." />
        <Alert variant="error">
          <AlertTitle>Gym could not be loaded</AlertTitle>
          <AlertDescription>
            <p>{error.message}</p>
            <div className="flex flex-wrap gap-2">
              <Button size="sm" variant="outline" onClick={() => setVersion((value) => value + 1)}>
                Try again
              </Button>
              <Button size="sm" variant="ghost" render={<Link to="/" />}>
                Back to home
              </Button>
            </div>
          </AlertDescription>
        </Alert>
      </main>
    )
  }

  if (loading || !gym) {
    return (
      <main className="mx-auto flex min-h-svh w-full max-w-6xl flex-col gap-8 px-4 py-8 md:px-8">
        <BrandBar signedIn />
        <div className="flex flex-col gap-6" aria-busy="true">
          <Skeleton className="h-28" />
          <Skeleton className="h-10 w-2/3" />
          <div className="grid gap-6 lg:grid-cols-2">
            <Skeleton className="h-56" />
            <Skeleton className="h-56" />
          </div>
          <Skeleton className="h-72" />
        </div>
      </main>
    )
  }

  const address = [gym.address_line, gym.barangay, gym.city, gym.province].filter(Boolean).join(', ')
  const services = gym.services
  const plans = gym.plans
  const coaches = gym.coaches

  const openGallery = (service: PublicService, index: number) => {
    const images = serviceImages(service)
    if (images.length === 0) return
    setGallery({ title: service.name, images, index })
  }

  return (
    <main className="mx-auto flex min-h-svh w-full max-w-6xl flex-col gap-8 px-4 py-8 md:px-8">
      <BrandBar signedIn />

      <PageHeader
        title={gym.name}
        description={
          <div className="flex flex-col gap-2">
            {gym.badge && (
              <Badge className="w-fit" variant={gym.tier === 2 ? 'success' : 'info'}>
                {gym.badge}
              </Badge>
            )}
            <span className="flex items-start gap-1.5">
              <MapPin className="mt-0.5 size-4 shrink-0" aria-hidden />
              {address || 'Address not listed'}
            </span>
            {(gym.contact_phone || gym.contact_email) && (
              <span className="flex flex-wrap items-center gap-x-4 gap-y-1">
                {gym.contact_phone && (
                  <a className="flex items-center gap-1.5 underline-offset-4 hover:underline" href={`tel:${gym.contact_phone}`}>
                    <Phone className="size-4" aria-hidden />
                    {gym.contact_phone}
                  </a>
                )}
                {gym.contact_email && (
                  <a className="flex items-center gap-1.5 underline-offset-4 hover:underline" href={`mailto:${gym.contact_email}`}>
                    <Mail className="size-4" aria-hidden />
                    {gym.contact_email}
                  </a>
                )}
              </span>
            )}
          </div>
        }
        actions={
          <>
            <Button size="sm" variant="outline" render={<Link to="/" />}>
              <ArrowLeft aria-hidden /> Back
            </Button>
            {manage && (
              <Button size="sm" render={<Link to={`/gyms/${id}/manage`} />}>
                Manage gym
              </Button>
            )}
          </>
        }
      />

      {(gym.disciplines.length > 0 || gym.activity_types.length > 0) && (
        <div className="flex flex-wrap gap-2">
          {gym.disciplines.map((discipline) => (
            <Badge key={discipline} variant="outline">
              {DISCIPLINE_LABELS[discipline] ?? labelKey(discipline)}
            </Badge>
          ))}
          {gym.activity_types.map((activity) => (
            <Badge key={activity} variant="secondary">
              {ACTIVITY_LABELS[activity] ?? labelKey(activity)}
            </Badge>
          ))}
        </div>
      )}

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle render={<h2 />}>Opening hours</CardTitle>
            <CardDescription>When the gym is open during the week.</CardDescription>
          </CardHeader>
          <CardPanel className="pt-2">
            <dl className="divide-y rounded-lg border">
              {DAYS.map((day) => {
                const hours = gym.operating_hours[day.key]
                return (
                  <div key={day.key} className="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                    <dt className="font-medium">{day.label}</dt>
                    <dd className={cn('tabular-nums', !hours && 'text-muted-foreground')}>
                      {hours ? `${timeLabel(hours.open)} – ${timeLabel(hours.close)}` : 'Closed'}
                    </dd>
                  </div>
                )
              })}
            </dl>
          </CardPanel>
        </Card>

        <div className="flex flex-col gap-6">
          <Card>
            <CardHeader>
              <CardTitle render={<h2 />}>Policies</CardTitle>
              <CardDescription>House rules and what members agree to.</CardDescription>
            </CardHeader>
            <CardPanel className="pt-2">
              {gym.policies ? (
                <p className="whitespace-pre-line text-sm leading-relaxed">{gym.policies}</p>
              ) : (
                <p className="text-muted-foreground text-sm">The gym has not published its policies yet.</p>
              )}
            </CardPanel>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle render={<h2 />}>Facilities</CardTitle>
              <CardDescription>What is available at the gym.</CardDescription>
            </CardHeader>
            <CardPanel className="pt-2">
              {gym.facilities.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {gym.facilities.map((facility) => (
                    <Badge key={facility} variant="outline">
                      {facility}
                    </Badge>
                  ))}
                </div>
              ) : (
                <p className="text-muted-foreground text-sm">No facilities are listed yet.</p>
              )}
            </CardPanel>
          </Card>
        </div>
      </div>

      <section className="flex flex-col gap-4" aria-labelledby="gym-services">
        <div className="flex flex-wrap items-baseline gap-2">
          <h2 id="gym-services" className="font-semibold text-xl tracking-tight">Services</h2>
          <span className="text-muted-foreground text-sm tabular-nums">
            {services.length} {services.length === 1 ? 'published service' : 'published services'}
          </span>
        </div>

        {services.length === 0 ? (
          <Empty className="rounded-xl border">
            <EmptyHeader>
              <EmptyTitle>No services published yet</EmptyTitle>
              <EmptyDescription>This gym has not published classes, programs or coaching yet. Check back later.</EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <ul className="grid items-start gap-4 lg:grid-cols-2">
            {services.map((service) => {
              const images = serviceImages(service)
              return (
                <li key={service.id} className="min-w-0">
                  <Card className="overflow-hidden">
                    {images.length > 0 ? (
                      <button
                        type="button"
                        className="block w-full bg-muted text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
                        onClick={() => openGallery(service, 0)}
                      >
                        <img
                          className="aspect-[16/9] w-full object-cover"
                          src={images[0]}
                          alt={`${service.name} cover photo`}
                          loading="lazy"
                        />
                      </button>
                    ) : (
                      <div className="flex aspect-[16/9] items-center justify-center bg-muted text-muted-foreground">
                        <Dumbbell className="size-8" aria-hidden />
                        <span className="sr-only">No cover photo</span>
                      </div>
                    )}

                    <CardHeader>
                      <CardTitle render={<h3 />}>{service.name}</CardTitle>
                      <CardDescription className="flex flex-wrap gap-1.5">
                        {service.discipline && (
                          <Badge variant="outline">{DISCIPLINE_LABELS[service.discipline] ?? labelKey(service.discipline)}</Badge>
                        )}
                        {service.activity_type && (
                          <Badge variant="secondary">{ACTIVITY_LABELS[service.activity_type] ?? labelKey(service.activity_type)}</Badge>
                        )}
                      </CardDescription>
                    </CardHeader>

                    <CardPanel className="flex flex-col gap-4 pt-0">
                      {service.overview && <p className="whitespace-pre-line text-sm leading-relaxed">{service.overview}</p>}

                      <dl className="grid gap-3 sm:grid-cols-3">
                        <div>
                          <dt className="text-muted-foreground text-xs">Level</dt>
                          <dd className="text-sm">{service.level ? (LEVEL_LABELS[service.level] ?? labelKey(service.level)) : 'Not stated'}</dd>
                        </div>
                        <div>
                          <dt className="text-muted-foreground text-xs">Duration</dt>
                          <dd className="text-sm">{service.duration_minutes ? `${service.duration_minutes} minutes` : 'Not stated'}</dd>
                        </div>
                        <div>
                          <dt className="text-muted-foreground text-xs">Runs</dt>
                          <dd className="text-sm">
                            {service.frequency ? (FREQUENCY_LABELS[service.frequency] ?? labelKey(service.frequency)) : 'Not stated'}
                          </dd>
                        </div>
                      </dl>

                      <p className="font-semibold tabular-nums">
                        {service.price_included || service.price == null ? 'Included in membership' : money(service.price)}
                      </p>

                      {((service.included ?? []).length > 0 || (service.bring ?? []).length > 0) && (
                        <Collapsible className="border-t pt-3">
                          <CollapsibleTrigger
                            render={<Button variant="ghost" size="sm" className="-ms-2 group" />}
                          >
                            What is included and what to bring
                            <ChevronDown className="transition-transform group-data-panel-open:rotate-180" aria-hidden />
                          </CollapsibleTrigger>
                          <CollapsiblePanel>
                            <div className="flex flex-col gap-4 pt-3">
                            {(service.included ?? []).length > 0 && (
                              <div>
                                <h4 className="mb-1.5 font-medium text-sm">Included</h4>
                                <ul className="flex flex-col gap-1 text-sm">
                                  {(service.included ?? []).map((item) => (
                                    <li key={item} className="flex items-start gap-2">
                                      <Check className="mt-0.5 size-4 shrink-0 text-success" aria-hidden />
                                      {item}
                                    </li>
                                  ))}
                                </ul>
                              </div>
                            )}

                            {(service.bring ?? []).length > 0 && (
                              <div>
                                <h4 className="mb-1.5 font-medium text-sm">What to bring</h4>
                                <ul className="flex flex-col gap-1 text-sm">
                                  {(service.bring ?? []).map((item) => (
                                    <li key={item} className="flex items-start gap-2">
                                      <span className="mt-2 size-1.5 shrink-0 rounded-full bg-muted-foreground" aria-hidden />
                                      {item}
                                    </li>
                                  ))}
                                </ul>
                              </div>
                            )}
                            </div>
                          </CollapsiblePanel>
                        </Collapsible>
                      )}

                      {images.length > 1 && (
                        <div className="flex flex-wrap gap-2 border-t pt-3">
                          {images.map((url, index) => (
                            <button
                              key={url}
                              type="button"
                              className="overflow-hidden rounded-md border focus-visible:ring-2 focus-visible:ring-ring"
                              onClick={() => openGallery(service, index)}
                              aria-label={`Open ${service.name} photo ${index + 1}`}
                            >
                              <img className="size-16 object-cover" src={url} alt="" loading="lazy" />
                            </button>
                          ))}
                        </div>
                      )}
                    </CardPanel>
                  </Card>
                </li>
              )
            })}
          </ul>
        )}
      </section>

      <section className="flex flex-col gap-4" aria-labelledby="gym-plans">
        <div className="flex flex-wrap items-baseline gap-2">
          <h2 id="gym-plans" className="font-semibold text-xl tracking-tight">Membership plans</h2>
          <span className="text-muted-foreground text-sm tabular-nums">
            {plans.length} {plans.length === 1 ? 'published plan' : 'published plans'}
          </span>
        </div>

        {plans.length === 0 ? (
          <Empty className="rounded-xl border">
            <EmptyHeader>
              <EmptyTitle>No plans published yet</EmptyTitle>
              <EmptyDescription>This gym has not published its membership plans yet. Ask at the front desk for the current fees.</EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <ul className="grid gap-4 lg:grid-cols-2">
            {plans.map((plan) => (
              <li key={plan.id} className="min-w-0">
                <Card className="h-full">
                  <CardHeader>
                    <div className="flex flex-wrap items-start gap-2">
                      <CardTitle render={<h3 />} className="mr-auto">
                        {plan.name}
                      </CardTitle>
                      {plan.registration_included && <Badge variant="success">Registration included</Badge>}
                    </div>
                    <CardDescription>
                      {plan.billing_type === 'sessions' ? 'Priced by sessions' : 'Priced by time'} · {planLength(plan)}
                    </CardDescription>
                  </CardHeader>

                  <CardPanel className="flex flex-col gap-5 pt-0">
                    <div className="flex flex-wrap items-baseline justify-between gap-2 rounded-lg border bg-muted/40 px-3 py-2.5">
                      <span className="font-medium text-sm">Total fee</span>
                      <span className="font-semibold text-xl tabular-nums">{money(plan.total_fee)}</span>
                    </div>

                    <div>
                      <h4 className="mb-2 font-medium text-sm">What the fee covers</h4>
                      {(plan.inclusions ?? []).length > 0 ? (
                        <ul className="flex flex-col gap-1.5 text-sm">
                          {(plan.inclusions ?? []).map((item) => (
                            <li key={item} className="flex items-start gap-2">
                              <Check className="mt-0.5 size-4 shrink-0 text-success" aria-hidden />
                              {item}
                            </li>
                          ))}
                        </ul>
                      ) : (
                        <p className="text-muted-foreground text-sm">No inclusions listed.</p>
                      )}
                    </div>

                    {(plan.addons ?? []).length > 0 && (
                      <div className="rounded-lg border border-dashed px-3 py-3">
                        <div className="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                          <h4 className="font-medium text-sm">Not included</h4>
                          <span className="text-muted-foreground text-xs">Optional extras, priced separately</span>
                        </div>
                        <ul className="divide-y">
                          {(plan.addons ?? []).map((addon, index) => (
                            <li key={addon.id ?? `${addon.service_name}-${index}`} className="flex flex-wrap items-baseline justify-between gap-2 py-1.5 text-sm">
                              <span>{addon.service_name ?? addon.service?.name ?? 'Extra service'}</span>
                              <span className="font-medium tabular-nums">{money(addon.price)}</span>
                            </li>
                          ))}
                        </ul>
                      </div>
                    )}

                    <Collapsible className="border-t pt-3">
                      <CollapsibleTrigger render={<Button variant="ghost" size="sm" className="-ms-2 group" />}>
                        Plan policies
                        <ChevronDown className="transition-transform group-data-panel-open:rotate-180" aria-hidden />
                      </CollapsibleTrigger>
                      <CollapsiblePanel>
                        <dl className="grid gap-3 pt-3 sm:grid-cols-2">
                          {POLICY_LABELS.map((policy) => (
                            <div key={policy.key} className="rounded-lg border px-3 py-2">
                              <dt className="font-medium text-xs">{policy.label}</dt>
                              <dd className="mt-1 whitespace-pre-line text-muted-foreground text-sm">{plan[policy.key] || 'Not stated.'}</dd>
                            </div>
                          ))}
                        </dl>
                      </CollapsiblePanel>
                    </Collapsible>
                  </CardPanel>
                </Card>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="flex flex-col gap-4" aria-labelledby="gym-coaches">
        <div className="flex flex-wrap items-baseline gap-2">
          <h2 id="gym-coaches" className="font-semibold text-xl tracking-tight">Coaches</h2>
          <span className="text-muted-foreground text-sm tabular-nums">
            {coaches.length} {coaches.length === 1 ? 'coach' : 'coaches'}
          </span>
        </div>

        {coaches.length === 0 ? (
          <Empty className="rounded-xl border">
            <EmptyHeader>
              <EmptyTitle>No coaches listed yet</EmptyTitle>
              <EmptyDescription>This gym has not made any coach profiles public yet.</EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {coaches.map((coach) => (
              <li key={coach.id} className="min-w-0">
                <CoachCard coach={coach} />
              </li>
            ))}
          </ul>
        )}
      </section>

      <Dialog open={gallery !== null} onOpenChange={(open) => !open && setGallery(null)}>
        <DialogPopup className="max-w-3xl">
          <DialogHeader>
            <DialogTitle>{gallery?.title}</DialogTitle>
            <DialogDescription>
              {gallery ? `Photo ${gallery.index + 1} of ${gallery.images.length}` : ''}
            </DialogDescription>
          </DialogHeader>
          <DialogPanel scrollFade={false} className="flex flex-col gap-3">
            {gallery && (
              <>
                <img
                  className="max-h-[60vh] w-full rounded-lg bg-muted object-contain"
                  src={gallery.images[gallery.index]}
                  alt={`${gallery.title} photo ${gallery.index + 1}`}
                />
                {gallery.images.length > 1 && (
                  <div className="flex flex-wrap gap-2">
                    {gallery.images.map((url, index) => (
                      <button
                        key={url}
                        type="button"
                        className={cn(
                          'overflow-hidden rounded-md border focus-visible:ring-2 focus-visible:ring-ring',
                          index === gallery.index && 'ring-2 ring-ring',
                        )}
                        onClick={() => setGallery({ ...gallery, index })}
                        aria-label={`Show photo ${index + 1}`}
                      >
                        <img className="size-16 object-cover" src={url} alt="" />
                      </button>
                    ))}
                  </div>
                )}
              </>
            )}
          </DialogPanel>
        </DialogPopup>
      </Dialog>
    </main>
  )
}

function CoachCard({ coach }: { coach: PublicCoach }) {
  return (
    <Card className="h-full">
      <CardPanel className="flex flex-col gap-4">
        <div className="flex items-start gap-3">
          {coach.photo_url ? (
            <img className="size-16 rounded-full object-cover" src={coach.photo_url} alt={`${coach.name} photo`} loading="lazy" />
          ) : (
            <span className="flex size-16 items-center justify-center rounded-full bg-muted font-semibold text-muted-foreground text-lg">
              {initials(coach.name)}
            </span>
          )}
          <div className="flex min-w-0 flex-col gap-1">
            <h3 className="font-semibold">{coach.name}</h3>
            {coach.rank && <Badge variant="outline" className="w-fit">{coach.rank}</Badge>}
            {coach.specialisation && <p className="text-muted-foreground text-sm">{coach.specialisation}</p>}
          </div>
        </div>

        {coach.experience && (
          <div>
            <h4 className="mb-1 flex items-center gap-1.5 font-medium text-sm">
              <Users className="size-4" aria-hidden /> Experience
            </h4>
            <p className="whitespace-pre-line text-sm leading-relaxed">{coach.experience}</p>
          </div>
        )}

        {coach.bio && (
          <div>
            <h4 className="mb-1 flex items-center gap-1.5 font-medium text-sm">
              <Clock className="size-4" aria-hidden /> About
            </h4>
            <p className="whitespace-pre-line text-sm leading-relaxed">{coach.bio}</p>
          </div>
        )}
      </CardPanel>
    </Card>
  )
}
