import { ArrowLeft, Check, ChevronDown, Clock, Dumbbell, Mail, MapPin, Phone } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { BrandLogo } from '@/components/brand-logo'
import { NotificationsMenu } from '@/components/notifications-menu'
import { PageHeader } from '@/components/page-header'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Collapsible, CollapsiblePanel, CollapsibleTrigger } from '@/components/ui/collapsible'
import { Dialog, DialogDescription, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { api, ApiError } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { planLength, type GymDetailPayload, labelKey, type PublicCoach, type PublicGymDetail, type PublicPlan, type PublicService, readGymDetail } from '@/lib/discovery'
import { approvedGym, canOpenWorkspace } from '@/lib/gym-access'
import { LIVE_STATUSES, STATUS_LABELS, type Membership } from '@/lib/membership'
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

/** 00:00 to 00:00 is a day open around the clock. */
function hoursLabel(open: string, close: string): string {
  return open === '00:00' && close === '00:00' ? 'Open 24 hours' : `${timeLabel(open)} – ${timeLabel(close)}`
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

function BrandBar({ signedIn, showBack = false }: { signedIn: boolean; showBack?: boolean }) {
  return (
    <header className="safe-area-top flex flex-wrap items-center gap-3 border-b pb-5">
      <Link className="flex min-h-11 items-center gap-2.5 font-medium text-sm" to="/">
        <BrandLogo className="h-7" />
      </Link>
      <div className="ms-auto flex items-center gap-2">
        {signedIn && <NotificationsMenu />}
        <Button className="min-h-11" size="sm" variant="ghost" render={<Link to={signedIn ? '/' : '/sign-in'} />}>
          {showBack && <ArrowLeft aria-hidden />}
          {signedIn ? (showBack ? 'Back to gyms' : 'Home') : 'Sign in'}
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
  const [mine, setMine] = useState<Membership | null>(null)

  // R-31: a person with a current application or membership here sees it instead of Apply.
  useEffect(() => {
    if (!userId || !Number.isInteger(id) || id <= 0) return
    let live = true
    api<{ memberships: Membership[] }>('/my-memberships')
      .then((result) => live && setMine(result.memberships.find((m) => m.gym_id === id && LIVE_STATUSES.includes(m.status)) ?? null))
      .catch(() => undefined)
    return () => {
      live = false
    }
  }, [id, userId])

  useEffect(() => {
    if (!Number.isInteger(id) || id <= 0) {
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
  const manage = access ? canOpenWorkspace(access) : false
  const canApply = !mine && !access?.roles.includes('owner') && (!user || user.account_type === 'member')

  if (authLoading) {
    return (
      <main className="mx-auto flex min-h-svh w-full max-w-6xl flex-col gap-8 wrap-anywhere px-4 py-8 md:px-8">
        <BrandBar signedIn={false} />
        <div className="flex flex-col gap-6" aria-busy="true">
          <Skeleton className="h-24" />
          <Skeleton className="h-64" />
        </div>
      </main>
    )
  }

  if (error?.status === 404 || !Number.isInteger(id) || id <= 0) {
    return (
      <main className="mx-auto flex min-h-svh w-full max-w-6xl flex-col gap-8 wrap-anywhere px-4 py-8 md:px-8">
        <BrandBar signedIn={Boolean(user)} />
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
      <main className="mx-auto flex min-h-svh w-full max-w-6xl flex-col gap-8 wrap-anywhere px-4 py-8 md:px-8">
        <BrandBar signedIn={Boolean(user)} />
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
      <main className="mx-auto flex min-h-svh w-full max-w-6xl flex-col gap-8 wrap-anywhere px-4 py-8 md:px-8">
        <BrandBar signedIn={Boolean(user)} />
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

  const address = [gym.address_line, gym.barangay, gym.city, gym.province, gym.zip_code].filter(Boolean).join(', ')
  const services = gym.services
  const plans = gym.plans
  const coaches = gym.coaches
  const todayKey = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'][new Date().getDay()]
  const policyParagraphs = gym.policies ? gym.policies.split(/\n{2,}/).filter(Boolean) : []

  const openGallery = (service: PublicService, index: number) => {
    const images = serviceImages(service)
    if (images.length === 0) return
    setGallery({ title: service.name, images, index })
  }

  return (
    <main className="mx-auto flex min-h-svh w-full max-w-6xl flex-col gap-8 wrap-anywhere px-4 py-8 md:px-8">
      <BrandBar signedIn={Boolean(user)} showBack />

      <section className="flex flex-col gap-6 border-b pt-2 pb-8 lg:flex-row lg:items-end lg:justify-between" aria-labelledby="gym-name">
          <div className="flex min-w-0 flex-1 flex-col items-start">
            <h1 id="gym-name" className="max-w-3xl text-balance font-semibold text-3xl leading-tight tracking-tight sm:text-4xl">{gym.name}</h1>
            {gym.badge && (
              <Badge className="mt-3 h-auto max-w-full whitespace-normal py-1 sm:h-auto" variant={gym.tier === 2 ? 'success' : 'info'}>{gym.badge}</Badge>
            )}
            <p className="mt-5 flex items-start gap-2 text-muted-foreground text-sm leading-6">
              <MapPin className="mt-1 size-4 shrink-0" aria-hidden />
              <span>{address || 'Address not listed'}</span>
            </p>
            {(gym.contact_phone || gym.contact_email) && (
              <div className="mt-1 flex w-full flex-wrap items-center gap-x-6 gap-y-1 text-sm">
                {gym.contact_phone && (
                  <a className="flex min-h-11 items-center gap-2 underline-offset-4 hover:underline" href={`tel:${gym.contact_phone}`}>
                    <Phone className="size-4 shrink-0 text-muted-foreground" aria-hidden />
                    <span>{gym.contact_phone}</span>
                  </a>
                )}
                {gym.contact_email && (
                  <a className="flex min-h-11 min-w-0 items-center gap-2 underline-offset-4 hover:underline" href={`mailto:${gym.contact_email}`}>
                    <Mail className="size-4 shrink-0 text-muted-foreground" aria-hidden />
                    <span className="min-w-0 wrap-anywhere">{gym.contact_email}</span>
                  </a>
                )}
              </div>
            )}
          </div>
          {(manage || mine) && (
          <div className="flex flex-wrap items-center gap-3 lg:max-w-xs lg:justify-end">
            {manage && (
              <Button className="min-h-11" render={<Link to={`/gyms/${id}/manage`} />}>
                Open workspace
              </Button>
            )}
            {mine && (
              <Button className="min-h-11 h-auto whitespace-normal py-2" variant={manage ? 'outline' : 'default'} render={<Link to={`/memberships/${mine.id}`} />}>
                My membership · {STATUS_LABELS[mine.status]}
              </Button>
            )}
          </div>
          )}
      </section>

      {(gym.disciplines.length > 0 || gym.activity_types.length > 0) && (
        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:gap-x-8">
          {gym.disciplines.length > 0 && (
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-muted-foreground text-xs">Disciplines</span>
              {gym.disciplines.map((discipline) => (
                <Badge key={discipline} variant="outline">
                  {DISCIPLINE_LABELS[discipline] ?? labelKey(discipline)}
                </Badge>
              ))}
            </div>
          )}
          {gym.activity_types.length > 0 && (
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-muted-foreground text-xs">Activities</span>
              {gym.activity_types.map((activity) => (
                <Badge key={activity} variant="secondary">
                  {ACTIVITY_LABELS[activity] ?? labelKey(activity)}
                </Badge>
              ))}
            </div>
          )}
        </div>
      )}

      <div className="grid overflow-hidden rounded-xl border bg-card text-card-foreground lg:grid-cols-5">
        <section className="min-w-0 border-b p-5 sm:p-6 lg:col-span-2 lg:border-r lg:border-b-0 lg:p-8" aria-labelledby="gym-hours">
          <div className="mb-5 flex items-center justify-between gap-4">
            <h2 id="gym-hours" className="font-semibold text-lg tracking-tight">Opening hours</h2>
            <Clock className="size-4 shrink-0 text-muted-foreground" aria-hidden />
          </div>
            <dl className="flex flex-col gap-1">
              {DAYS.map((day) => {
                const hours = gym.operating_hours[day.key]
                const isToday = day.key === todayKey
                return (
                  <div
                    className={cn(
                      'flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 rounded-md px-3 py-2.5 text-sm',
                      isToday ? 'bg-accent font-medium' : 'text-muted-foreground',
                    )}
                    key={day.key}
                  >
                    <dt className="flex items-baseline gap-2">
                      {day.label}
                      {isToday && <span className="text-xs text-success-foreground">Today</span>}
                    </dt>
                    <dd className={cn('ms-auto whitespace-nowrap tabular-nums', hours && 'text-card-foreground')}>
                      {hours ? hoursLabel(hours.open, hours.close) : 'Closed'}
                    </dd>
                  </div>
                )
              })}
            </dl>
        </section>

        <div className="min-w-0 lg:col-span-3">
          <section className="p-5 sm:p-6 lg:p-8" aria-labelledby="gym-facilities">
            <h2 id="gym-facilities" className="mb-5 font-semibold text-lg tracking-tight">Facilities</h2>
              {gym.facilities.length > 0 ? (
                <ul className="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                  {gym.facilities.map((facility) => (
                    <li className="flex items-start gap-3 text-sm leading-6" key={facility}>
                      <Check className="mt-1 size-4 shrink-0 text-success-foreground" aria-hidden />
                      <span>{facility}</span>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="text-muted-foreground text-sm">No facilities are listed yet.</p>
              )}
          </section>

          <section className="border-t p-5 sm:p-6 lg:p-8" aria-labelledby="gym-policies">
            <h2 id="gym-policies" className="mb-2 font-semibold text-lg tracking-tight">Policies</h2>
            <p className="mb-4 text-xs text-muted-foreground">Before you train</p>
            {policyParagraphs.length > 0 ? (
              <div className="flex max-w-prose flex-col gap-3">
                {policyParagraphs.map((paragraph, index) => (
                  <p className="whitespace-pre-line text-sm leading-7" key={`${index}-${paragraph.slice(0, 24)}`}>
                    {paragraph}
                  </p>
                ))}
              </div>
            ) : (
              <p className="text-muted-foreground text-sm">The gym has not published its policies yet.</p>
            )}
          </section>
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
          <ul className="grid gap-5 lg:grid-cols-2">
            {services.map((service) => {
              const images = serviceImages(service)
              return (
                <li key={service.id} className="min-w-0">
                  <Card className="flex h-full flex-col gap-0 overflow-hidden p-0">
                    {images.length > 0 ? (
                      <button
                        type="button"
                        className="block w-full bg-muted text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
                        onClick={() => openGallery(service, 0)}
                        aria-label={`Open ${service.name} photo gallery`}
                      >
                        <img
                          className="aspect-[16/10] w-full object-cover"
                          src={images[0]}
                          alt={`${service.name} cover photo`}
                          loading="lazy"
                        />
                      </button>
                    ) : (
                      <div className="flex aspect-[16/10] flex-col items-center justify-center gap-3 bg-muted text-muted-foreground">
                        <Dumbbell className="size-8" aria-hidden />
                        <span className="text-sm">No cover photo yet</span>
                      </div>
                    )}

                    {images.length > 1 && (
                      <div className="flex flex-wrap gap-2 border-b bg-muted/30 p-3" aria-label={`${service.name} photos`}>
                        {images.map((url, index) => (
                          <button
                            key={url}
                            type="button"
                            className="overflow-hidden rounded-md border focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                            onClick={() => openGallery(service, index)}
                            aria-label={`Open ${service.name} photo ${index + 1}`}
                          >
                            <img className="size-12 object-cover" src={url} alt="" loading="lazy" />
                          </button>
                        ))}
                      </div>
                    )}

                    <div className="flex flex-1 flex-col gap-5 p-6">
                      <div>
                      <h3 className="font-semibold text-2xl tracking-tight wrap-anywhere">{service.name}</h3>
                      <div className="mt-3 flex flex-wrap gap-2">
                        {service.discipline && (
                          <Badge variant="outline">{DISCIPLINE_LABELS[service.discipline] ?? labelKey(service.discipline)}</Badge>
                        )}
                        {service.activity_type && (
                          <Badge variant="secondary">{service.activity_type_label ?? ACTIVITY_LABELS[service.activity_type] ?? labelKey(service.activity_type)}</Badge>
                        )}
                      </div>
                      </div>

                      {service.overview && <p className="whitespace-pre-line text-muted-foreground text-sm leading-7 wrap-anywhere">{service.overview}</p>}

                      <dl className="mt-auto grid grid-cols-2 gap-x-4 gap-y-4 border-t pt-5 sm:grid-cols-3">
                        <div>
                          <dt className="text-muted-foreground text-xs">Level</dt>
                          <dd className="mt-1 font-medium text-sm">{service.level ? (LEVEL_LABELS[service.level] ?? labelKey(service.level)) : 'Not stated'}</dd>
                        </div>
                        <div>
                          <dt className="text-muted-foreground text-xs">Duration</dt>
                          <dd className="mt-1 font-medium text-sm tabular-nums">{service.duration_minutes ? `${service.duration_minutes} minutes` : 'Not stated'}</dd>
                        </div>
                        <div>
                          <dt className="text-muted-foreground text-xs">Schedule</dt>
                          <dd className="mt-1 font-medium text-sm">
                            {service.frequency ? (FREQUENCY_LABELS[service.frequency] ?? labelKey(service.frequency)) : 'Not stated'}
                          </dd>
                        </div>
                      </dl>
                    </div>

                    <div className="border-t bg-muted/30 p-6">
                      <p className="mb-1 text-muted-foreground text-xs">Price</p>
                      <p className={cn('font-semibold tracking-tight tabular-nums wrap-anywhere', service.price_included || service.price == null ? 'text-lg' : 'text-2xl')}>
                        {service.price_included || service.price == null ? 'Included in membership' : money(service.price)}
                      </p>

                      {((service.included ?? []).length > 0 || (service.bring ?? []).length > 0) && (
                        <Collapsible className="mt-4 border-t pt-3">
                          <CollapsibleTrigger
                            render={<Button variant="ghost" size="sm" className="group min-h-11 h-auto w-full justify-between gap-3 whitespace-normal py-2 text-left" />}
                          >
                            Included & what to bring
                            <ChevronDown className="shrink-0 transition-transform motion-reduce:transition-none group-data-panel-open:rotate-180" aria-hidden />
                          </CollapsibleTrigger>
                          <CollapsiblePanel>
                            <div className="flex flex-col gap-4 pt-3">
                            {(service.included ?? []).length > 0 && (
                              <div>
                                <h4 className="mb-1.5 font-medium text-sm">Included</h4>
                                <ul className="flex flex-col gap-2 text-sm leading-6">
                                  {(service.included ?? []).map((item) => (
                                    <li key={item} className="flex items-start gap-2">
                                      <Check className="mt-1 size-4 shrink-0 text-success-foreground" aria-hidden />
                                      {item}
                                    </li>
                                  ))}
                                </ul>
                              </div>
                            )}

                            {(service.bring ?? []).length > 0 && (
                              <div>
                                <h4 className="mb-1.5 font-medium text-sm">What to bring</h4>
                                <ul className="flex flex-col gap-2 text-sm leading-6">
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

                    </div>
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

        {user?.account_type === 'owner' && (
          <p className="text-muted-foreground text-sm">Gym Owner accounts can view gyms but not join them.</p>
        )}

        {plans.length === 0 ? (
          <Empty className="rounded-xl border">
            <EmptyHeader>
              <EmptyTitle>No plans published yet</EmptyTitle>
              <EmptyDescription>This gym has not published its membership plans yet. Ask at the front desk for the current fees.</EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <ul className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            {plans.map((plan) => (
              <li key={plan.id} className="min-w-0">
                <Card className="flex h-full flex-col gap-0 overflow-hidden p-0">
                  <div className="flex flex-col gap-5 p-6">
                      <h3 className="font-semibold text-xl tracking-tight wrap-anywhere">
                        {plan.name}
                      </h3>
                    <p className="-mt-3 text-muted-foreground text-sm">
                      {plan.billing_type === 'sessions' ? 'Priced by sessions' : 'Priced by time'} · {planLength(plan)}
                    </p>
                    <div className="py-3">
                      <p className="font-semibold text-4xl tracking-tight tabular-nums wrap-anywhere">{money(plan.total_fee) || 'Not set'}</p>
                      <p className="mt-2 text-muted-foreground text-sm">Total for {planLength(plan)}</p>
                    </div>
                  </div>

                  <div className="flex flex-1 flex-col gap-6 border-t p-6">
                    <div>
                      <h4 className="mb-4 font-medium text-sm">What’s included</h4>
                      {(plan.inclusions ?? []).length > 0 || plan.registration_included ? (
                        <ul className="flex flex-col gap-3">
                          {plan.registration_included && (
                            <li className="flex items-start gap-3 text-sm leading-6">
                              <Check className="mt-1 size-4 shrink-0 text-success-foreground" aria-hidden />
                              <span>Registration included</span>
                            </li>
                          )}
                          {(plan.inclusions ?? []).map((item, index) => (
                            <li key={`${index}-${item}`} className="flex items-start gap-3 text-sm leading-6">
                              <Check className="mt-1 size-4 shrink-0 text-success-foreground" aria-hidden />
                              <span className="min-w-0 wrap-anywhere">{item}</span>
                            </li>
                          ))}
                        </ul>
                      ) : (
                        <p className="text-muted-foreground text-sm">No inclusions listed.</p>
                      )}
                    </div>

                    {(plan.addons ?? []).length > 0 && (
                      <div className="border-t pt-4">
                        <h4 className="font-medium text-sm">Optional add-ons</h4>
                        <p className="mt-1 text-muted-foreground text-xs">Priced separately from the plan</p>
                        <ul className="mt-3 flex flex-col gap-2">
                          {(plan.addons ?? []).map((addon, index) => (
                            <li key={addon.id ?? `${addon.service_name}-${index}`} className="flex flex-wrap items-baseline justify-between gap-2 py-1.5 text-sm">
                              <span className="min-w-0 text-muted-foreground wrap-anywhere">{addon.service_name ?? addon.service?.name ?? 'Extra service'}</span>
                              <span className="ms-auto font-medium tabular-nums">{money(addon.price)}</span>
                            </li>
                          ))}
                        </ul>
                      </div>
                    )}

                    <Collapsible className="mt-auto border-t pt-3">
                      <CollapsibleTrigger render={<Button variant="ghost" size="sm" className="-ms-2 group min-h-11" />}>
                        Plan policies
                        <ChevronDown className="transition-transform group-data-panel-open:rotate-180" aria-hidden />
                      </CollapsibleTrigger>
                      <CollapsiblePanel>
                        <dl className="flex flex-col gap-4 pt-3">
                          {POLICY_LABELS.map((policy) => (
                            <div key={policy.key}>
                              <dt className="font-medium text-xs">{policy.label}</dt>
                              <dd className="mt-1 whitespace-pre-line text-muted-foreground text-sm">{plan[policy.key] || 'Not stated.'}</dd>
                            </div>
                          ))}
                        </dl>
                      </CollapsiblePanel>
                    </Collapsible>

                  </div>
                  {canApply && (
                    <div className="border-t bg-muted/30 p-6">
                      <Button className="min-h-11 w-full" render={<Link to={`/gyms/${id}/apply?plan=${plan.id}`} />}>
                        Apply on this plan
                      </Button>
                    </div>
                  )}
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
          <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
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
    <Card className="flex h-full flex-col gap-0 overflow-hidden p-0">
      <div className="flex items-center gap-4 bg-muted/30 p-5">
          {coach.photo_url ? (
            <img
              className="size-20 shrink-0 rounded-full border-2 border-card object-cover object-top"
              src={coach.photo_url}
              alt={`${coach.name} photo`}
              width={80}
              height={80}
              loading="lazy"
            />
          ) : (
            <span className="flex size-20 shrink-0 items-center justify-center rounded-full border-2 border-card bg-muted font-semibold text-2xl tracking-tight" aria-hidden>
              {initials(coach.name)}
            </span>
          )}
          <div className="flex min-w-0 flex-1 flex-col items-start gap-1.5">
            <h3 className="font-semibold text-lg tracking-tight wrap-anywhere">{coach.name}</h3>
            {coach.rank && (
              <Badge variant="outline" className="h-auto min-h-5.5 max-w-full justify-start bg-card px-2 py-1 text-left sm:h-auto" title={coach.rank}>
                <span className="min-w-0 truncate">{coach.rank}</span>
              </Badge>
            )}
            {coach.specialisation && <p className="max-w-prose text-muted-foreground text-sm leading-relaxed wrap-anywhere">{coach.specialisation}</p>}
          </div>
      </div>
      {(coach.bio || coach.experience) && (
        <div className="flex flex-1 flex-col gap-4 border-t p-5">
          {coach.bio && (
            <div>
              <h4 className="mb-1 font-medium text-sm">About</h4>
              <p className="whitespace-pre-line text-muted-foreground text-sm leading-6 wrap-anywhere">{coach.bio}</p>
            </div>
          )}
          {coach.experience && (
            <div className={cn(coach.bio && 'border-t pt-4')}>
              <h4 className="mb-1 font-medium text-sm">Experience</h4>
              <p className="whitespace-pre-line text-muted-foreground text-sm leading-6 wrap-anywhere">{coach.experience}</p>
            </div>
          )}
        </div>
      )}
    </Card>
  )
}
