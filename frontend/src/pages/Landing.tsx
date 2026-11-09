import { Menu, Minus, Plus, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsiblePanel, CollapsibleTrigger } from '@/components/ui/collapsible'
import { api, ApiError } from '@/lib/api'
import {
  disciplineLabel,
  feeRange,
  type LandingCoach,
  type LandingDiscipline,
  type LandingGym,
  type LandingPayload,
  type LandingPlan,
  type LandingSession,
  type LandingStats,
  PHOTO_CREDITS,
  PHOTOS,
  peso,
  photoUrl,
  planLength,
  sessionTime,
} from '@/lib/landing'
import { cn } from '@/lib/utils'

const SHELL = 'mx-auto w-full max-w-[1320px] px-5 md:px-8 lg:px-12'
const SECTION = 'scroll-mt-24 border-t border-white/10 py-16 md:py-20 lg:py-28'
const MUTED = 'text-[#A6A096]'
const RULE = 'border-white/10'
const YEAR = new Date().getFullYear()

const NAV = [
  { href: '#gyms', label: 'Gyms' },
  { href: '#plans', label: 'Plans' },
  { href: '#owners', label: 'For gym owners' },
]

const FAQ = [
  {
    q: 'What do membership plans cost?',
    a: 'Published plan fees range from ₱1,500 to ₱32,000. Monthly memberships, session packs, and annual passes are all listed by their gym.',
  },
  {
    q: 'Does every plan include the same access?',
    a: 'No. Each plan lists its own inclusions, add-ons, and policies. Check the selected plan before applying.',
  },
  {
    q: 'Can I join a waiting list for a session?',
    a: 'Session booking supports waiting lists. Check the selected session for availability and the gym’s booking rules.',
  },
  {
    q: 'How does check-in work?',
    a: 'Members show a QR check-in code, and the training desk redeems it. Check your booking and membership details before your session.',
  },
  {
    q: 'What is the difference between Tier 1 and Tier 2?',
    a: 'Tier 2 adds facility photos, a video tour, identity documents, and a utility bill to the Tier 1 requirements. Every application is reviewed by an administrator.',
  },
]

const MEMBER_MODULES = [
  { title: 'Find your gym', body: 'Use maps, distance, best-match recommendations, and full gym pages.' },
  { title: 'Apply and pay', body: 'Complete health questionnaires and waivers, then pay online through PayMongo.' },
  { title: 'Book and check in', body: 'Reserve sessions, join waiting lists, and use QR check-in codes.' },
  { title: 'Track your progress', body: 'Keep athlete records, promotions, assessments, and competitions together.' },
]

const OWNER_MODULES = [
  { title: 'Memberships and attendance', body: 'Manage applications, sessions, bookings, and attendance.' },
  { title: 'People and payroll', body: 'Handle staff records, leave, and payroll with statutory rates.' },
  { title: 'Finance', body: 'Track income, expenses, receivables, payables, and budgets.' },
  { title: 'Stock and purchasing', body: 'Manage inventory movements, suppliers, and purchase orders.' },
  { title: 'Member communication', body: 'Manage CRM follow-ups, announcements, and notifications.' },
  { title: 'Roles and permissions', body: 'Define staff roles with exact, module-level permissions.' },
]

export default function Landing() {
  const [payload, setPayload] = useState<LandingPayload | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [menuOpen, setMenuOpen] = useState(false)
  const menuButton = useRef<HTMLButtonElement>(null)

  useEffect(() => {
    let live = true

    api<LandingPayload>('/public/landing')
      .then((result) => {
        if (live) setPayload(result)
      })
      .catch((failure) => {
        if (live) setError(failure instanceof ApiError ? failure.message : 'The platform numbers could not be loaded.')
      })
      .finally(() => {
        if (live) setLoading(false)
      })

    return () => {
      live = false
    }
  }, [version])

  function retry() {
    setLoading(true)
    setError('')
    setVersion((value) => value + 1)
  }

  useEffect(() => {
    if (!menuOpen) return
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setMenuOpen(false)
        menuButton.current?.focus()
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [menuOpen])

  const stats = payload?.stats ?? null

  return (
    <div className="dark min-h-svh bg-[#0A0A0B] text-[#F2EFE7] [color-scheme:dark]">
      <a
        href="#main"
        className="sr-only z-50 focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:bg-[#F2EFE7] focus:px-4 focus:py-3 focus:font-semibold focus:text-sm focus:text-[#0A0A0B]"
      >
        Skip to content
      </a>

      <header className="sticky top-0 z-40 border-b border-white/10 bg-[#0A0A0B]">
        <div className={cn(SHELL, 'flex h-16 items-center gap-4 md:h-20 md:gap-8')}>
          <Link to="/" className="font-display text-[21px] uppercase leading-none tracking-[-0.01em]">
            Combat Platform
          </Link>

          <nav className="hidden items-center gap-6 md:flex" aria-label="Landing page">
            {NAV.map((item) => (
              <a
                key={item.href}
                href={item.href}
                className="text-sm text-[#C9C3B7] underline-offset-4 transition-colors hover:text-[#F2EFE7] hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#F2EFE7]"
              >
                {item.label}
              </a>
            ))}
          </nav>

          <div className="ms-auto flex items-center gap-3">
            <Link
              to="/sign-in"
              className="hidden min-h-11 items-center text-sm text-[#C9C3B7] underline-offset-4 transition-colors hover:text-[#F2EFE7] hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#F2EFE7] sm:inline-flex"
            >
              Sign in
            </Link>
            <Button
              className="hidden h-11 rounded-none border-[#F2EFE7] bg-[#F2EFE7] px-4 text-[#0A0A0B] hover:bg-[#E2DDD1] sm:inline-flex"
              render={<Link to="/sign-up?type=member" />}
            >
              Find a gym
            </Button>
            <button
              ref={menuButton}
              type="button"
              className="inline-flex size-11 items-center justify-center border border-white/15 text-[#F2EFE7] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#F2EFE7] md:hidden"
              aria-expanded={menuOpen}
              aria-controls="landing-menu"
              onClick={() => setMenuOpen((open) => !open)}
            >
              {menuOpen ? <X className="size-5" aria-hidden /> : <Menu className="size-5" aria-hidden />}
              <span className="sr-only">{menuOpen ? 'Close menu' : 'Open menu'}</span>
            </button>
          </div>
        </div>

        {menuOpen && (
          <div id="landing-menu" className="border-t border-white/10 md:hidden">
            <nav className={cn(SHELL, 'flex flex-col py-3')} aria-label="Landing page">
              {NAV.map((item) => (
                <a
                  key={item.href}
                  href={item.href}
                  className="flex min-h-12 items-center border-b border-white/10 text-base text-[#F2EFE7]"
                  onClick={() => setMenuOpen(false)}
                >
                  {item.label}
                </a>
              ))}
              <Link
                to="/sign-in"
                className="flex min-h-12 items-center border-b border-white/10 text-base text-[#F2EFE7]"
                onClick={() => setMenuOpen(false)}
              >
                Sign in
              </Link>
              <Link
                to="/sign-up?type=member"
                className="mt-4 flex min-h-12 items-center justify-center bg-[#F2EFE7] px-4 font-semibold text-[#0A0A0B]"
                onClick={() => setMenuOpen(false)}
              >
                Find a gym
              </Link>
            </nav>
          </div>
        )}
      </header>

      <main id="main">
        <section aria-labelledby="hero-title" className="border-b border-white/10">
          <div className={cn(SHELL, 'grid grid-cols-1 gap-10 py-12 md:py-16 lg:grid-cols-12 lg:gap-12 lg:py-20')}>
            <div className="lg:col-span-7">
              <p className="font-mono text-[11px] uppercase tracking-[0.18em] text-[#9A958B]">01 / Philippine combat sports</p>
              <h1
                id="hero-title"
                className="mt-5 font-display text-[54px] uppercase leading-[0.94] tracking-[-0.02em] sm:text-[72px] lg:text-[88px] xl:text-[102px]"
              >
                Find your gym.
                <br />
                Put in the <span className="text-[#C63C26]">work.</span>
              </h1>              <p className="mt-6 max-w-[440px] text-[17px] leading-7 text-[#C9C3B7]">
                Find verified gyms, apply for membership, book sessions, and track your progress.
              </p>
              <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <Button
                  className="h-12 rounded-none border-[#F2EFE7] bg-[#F2EFE7] px-5 text-[#0A0A0B] hover:bg-[#E2DDD1]"
                  render={<Link to="/sign-up?type=member" />}
                >
                  Find a gym
                </Button>
                <Button
                  variant="outline"
                  className="h-12 rounded-none border-white/25 bg-transparent px-5 text-[#F2EFE7] hover:bg-white/5 dark:bg-transparent dark:hover:bg-white/5"
                  render={<Link to="/sign-up?type=owner" />}
                >
                  Apply to list your gym
                </Button>
              </div>
            </div>

            <figure className="lg:col-span-5">
              <div className="aspect-[16/10] w-full overflow-hidden bg-[#141416] lg:aspect-[4/5]">
                <img
                  className="h-full w-full object-cover object-[center_25%] grayscale contrast-[1.05]"
                  src={photoUrl(PHOTOS.hero.id, PHOTOS.hero.width, PHOTOS.hero.height)}
                  alt={PHOTOS.hero.alt}
                  width={PHOTOS.hero.width}
                  height={PHOTOS.hero.height}
                  loading="eager"
                  fetchPriority="high"
                />
              </div>
              <figcaption className={cn('mt-2 font-mono text-[10px] uppercase tracking-[0.12em]', MUTED)}>
                Editorial training image ·{' '}
                <a
                  className="underline underline-offset-2 hover:text-[#F2EFE7]"
                  href={`https://www.pexels.com/photo/${PHOTOS.hero.id}/`}
                  target="_blank"
                  rel="noreferrer"
                >
                  Pexels
                </a>
              </figcaption>
            </figure>
          </div>

          <div className={SHELL}>
            <dl className="grid grid-cols-2 border-t border-white/10 lg:grid-cols-4" aria-label="Platform totals" aria-busy={loading}>
              <Stat loading={loading} value={stats ? String(stats.gyms) : null} label="Verified gyms" />
              <Stat loading={loading} value={stats ? String(stats.cities) : null} label="Cities" />
              <Stat loading={loading} value={stats ? String(stats.disciplines) : null} label="Disciplines" />
              <Stat
                loading={loading}
                value={stats ? String(stats.upcoming_sessions) : null}
                label="Upcoming sessions"
              />
            </dl>
          </div>
        </section>

        {error && (
          <div className={cn(SHELL, 'pt-6')}>
            <div
              role="alert"
              className="flex flex-wrap items-center gap-x-4 gap-y-2 border border-[#C63C26]/40 px-4 py-3 text-sm text-[#F2EFE7]"
            >
              <span>{error} The page still shows what it can.</span>
              <button
                type="button"
                className="min-h-11 font-semibold underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#F2EFE7]"
                onClick={retry}
              >
                Try again
              </button>
            </div>
          </div>
        )}

        <GymsSection data={payload} loading={loading} error={error} />
        <DisciplinesSection data={payload} loading={loading} error={error} />
        <PlansSection data={payload} loading={loading} error={error} />
        <CoachesSection data={payload} loading={loading} error={error} />
        <OwnerSection />
        <VerificationSection />
        <FaqSection stats={stats} />
        <ClosingSection />
      </main>

      <Footer />
    </div>
  )
}

function Stat({ value, label, loading }: { value: string | null; label: string; loading: boolean }) {
  return (
    <div className="flex flex-col-reverse border-b border-white/10 py-5 pe-4 last:border-b-0 lg:border-e lg:border-b-0 lg:px-6 lg:first:ps-0 lg:last:border-e-0 lg:last:pe-0">
      <dt className={cn('mt-2 text-[13px] leading-5', MUTED)}>{value === null && loading ? 'Loading platform totals' : label}</dt>
      <dd className="font-mono text-[32px] leading-none tracking-[-0.02em] tabular-nums lg:text-[40px]">
        {value ?? (loading ? (
          <span className="inline-block h-8 w-12 bg-[#141416]" aria-hidden>
            <span className="sr-only">Loading</span>
          </span>
        ) : (
          <span aria-label="Not available">N/A</span>
        ))}
      </dd>
    </div>
  )
}

function GymsSection({
  data,
  loading,
  error,
}: {
  data: LandingPayload | null
  loading: boolean
  error: string
}) {
  const gyms = data?.gyms ?? []
  const stats = data?.stats ?? null

  return (
    <section id="gyms" className={SECTION} aria-labelledby="gyms-title">
      <div className={cn(SHELL, 'grid gap-10 lg:grid-cols-12 lg:gap-12')}>
        <div className="lg:col-span-4">
          <p className={cn('font-mono text-[11px] uppercase tracking-[0.16em]', MUTED)}>02</p>
          <h2 id="gyms-title" className="mt-3 font-display text-[38px] uppercase leading-[0.96] tracking-[-0.015em] md:text-[52px] lg:text-[60px]">
            {stats ? `${stats.cities} cities.` : 'Verified gyms.'}
            <br />
            Find your corner.
          </h2>
          <p className={cn('mt-5 max-w-sm text-[15px] leading-7', MUTED)}>
            Compare verified gyms and choose where you want to train.
          </p>
          <a
            href="#verification"
            className="mt-6 inline-flex min-h-11 items-center text-sm text-[#F2EFE7] underline underline-offset-4 hover:text-[#D8D2C6] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#F2EFE7]"
          >
            What verification checks
          </a>
        </div>

        <div className="min-w-0 lg:col-span-8">
          {error ? (
            <StateLine>Couldn’t load gyms. Try again above.</StateLine>
          ) : loading ? (
            <LoadingRows rows={5} />
          ) : gyms.length === 0 ? (
            <StateLine>No verified gyms are listed right now.</StateLine>
          ) : (
            <ul className={cn('divide-y border-y', RULE, 'divide-white/10')}>
              {gyms.map((gym, index) => (
                <GymRow key={gym.id} gym={gym} index={index} />
              ))}
            </ul>
          )}
        </div>
      </div>
    </section>
  )
}

function GymRow({ gym, index }: { gym: LandingGym; index: number }) {
  return (
    <li className="group flex flex-col gap-3 py-5 transition-colors hover:bg-[#141416] md:flex-row md:items-center md:gap-6 md:px-4">
      <span className={cn('font-mono text-[11px] tabular-nums', MUTED)}>{String(index + 1).padStart(2, '0')}</span>
      <div className="min-w-0 flex-1">
        <h3 className="text-[19px] font-semibold leading-7 tracking-[-0.01em]">{gym.name}</h3>
        <p className={cn('mt-1 text-sm', MUTED)}>
          {[gym.city, gym.province].filter(Boolean).join(', ') || 'Location not listed'}
          {gym.services > 0 ? ` · ${gym.services} ${gym.services === 1 ? 'class' : 'classes'}` : ''}
        </p>
      </div>
      <div className="flex flex-wrap items-center gap-2">
        {gym.badge && (
          <span className="border border-white/15 px-2 py-1 font-mono text-[10px] uppercase tracking-[0.08em] text-[#C9C3B7]">
            {gym.badge}
          </span>
        )}
        {gym.disciplines.slice(0, 2).map((key) => (
          <span key={key} className={cn('text-xs', MUTED)}>
            {disciplineLabel(key)}
          </span>
        ))}
      </div>
      <Link
        to={`/gyms/${gym.id}`}
        className="inline-flex min-h-11 shrink-0 items-center text-sm font-semibold underline underline-offset-4 hover:text-[#D8D2C6] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#F2EFE7]"
      >
        View gym
      </Link>
    </li>
  )
}

function DisciplinesSection({
  data,
  loading,
  error,
}: {
  data: LandingPayload | null
  loading: boolean
  error: string
}) {
  const disciplines = data?.disciplines ?? []

  return (
    <section id="disciplines" className={SECTION} aria-labelledby="disciplines-title">
      <div className={SHELL}>
        <div className="max-w-3xl">
          <p className={cn('font-mono text-[11px] uppercase tracking-[0.16em]', MUTED)}>03</p>
          <h2 id="disciplines-title" className="mt-3 font-display text-[38px] uppercase leading-[0.96] tracking-[-0.015em] md:text-[52px] lg:text-[60px]">
            Choose your discipline.
          </h2>
          <p className={cn('mt-5 max-w-xl text-[15px] leading-7', MUTED)}>
            Browse classes across the disciplines taught by listed gyms.
          </p>
          {data && (
            <p className={cn('mt-4 font-mono text-[12px] uppercase tracking-[0.1em]', MUTED)}>
              {data.stats.disciplines} disciplines · {data.stats.services} published services
            </p>
          )}
        </div>

        <div className="mt-8 grid grid-cols-1 gap-2 sm:grid-cols-12">
          <figure className="sm:col-span-6">
            <div className="h-[240px] w-full overflow-hidden bg-[#141416] sm:h-[340px] lg:h-[380px]">
              <img
                className="h-full w-full object-cover grayscale contrast-[1.05]"
                src={photoUrl(PHOTOS.muayThai.id, PHOTOS.muayThai.width, PHOTOS.muayThai.height)}
                alt={PHOTOS.muayThai.alt}
                width={PHOTOS.muayThai.width}
                height={PHOTOS.muayThai.height}
                loading="lazy"
              />
            </div>
            <figcaption className={cn('mt-2 font-mono text-[10px] uppercase tracking-[0.1em]', MUTED)}>Muay Thai · Pexels</figcaption>
          </figure>
          <figure className="hidden sm:col-span-4 sm:block">
            <div className="h-[240px] w-full overflow-hidden bg-[#141416] sm:h-[340px] lg:h-[380px]">
              <img
                className="h-full w-full object-cover grayscale contrast-[1.05]"
                src={photoUrl(PHOTOS.bjj.id, PHOTOS.bjj.width, PHOTOS.bjj.height)}
                alt={PHOTOS.bjj.alt}
                width={PHOTOS.bjj.width}
                height={PHOTOS.bjj.height}
                loading="lazy"
              />
            </div>
            <figcaption className={cn('mt-2 font-mono text-[10px] uppercase tracking-[0.1em]', MUTED)}>Brazilian jiu-jitsu · Pexels</figcaption>
          </figure>
          <figure className="hidden sm:col-span-2 sm:block">
            <div className="h-[240px] w-full overflow-hidden bg-[#141416] sm:h-[340px] lg:h-[380px]">
              <img
                className="h-full w-full object-cover grayscale contrast-[1.05]"
                src={photoUrl(PHOTOS.karate.id, PHOTOS.karate.width, PHOTOS.karate.height)}
                alt={PHOTOS.karate.alt}
                width={PHOTOS.karate.width}
                height={PHOTOS.karate.height}
                loading="lazy"
              />
            </div>
            <figcaption className={cn('mt-2 font-mono text-[10px] uppercase tracking-[0.1em]', MUTED)}>Karate · Pexels</figcaption>
          </figure>
        </div>

        <div className="mt-10">
          {error ? (
            <StateLine>Couldn’t load discipline data. Try again above.</StateLine>
          ) : loading ? (
            <LoadingRows rows={4} />
          ) : disciplines.length === 0 ? (
            <StateLine>No published disciplines are available right now.</StateLine>
          ) : (
            <>
              <ul className="grid border-t border-white/10 sm:grid-cols-2 sm:gap-x-12">
                {disciplines.map((discipline) => (
                  <DisciplineRow key={discipline.key} discipline={discipline} />
                ))}
              </ul>

              {data && data.sessions.length > 0 && (
                <div className="mt-10 border-t border-white/10 pt-6">
                  <p className={cn('font-mono text-[11px] uppercase tracking-[0.12em]', MUTED)}>Next scheduled sessions</p>
                  <ul className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {data.sessions.map((session) => (
                      <SessionCell key={session.id} session={session} />
                    ))}
                  </ul>
                </div>
              )}

              <Link
                to="/sign-up?type=member"
                className="mt-8 inline-flex min-h-11 items-center text-sm font-semibold underline underline-offset-4 hover:text-[#D8D2C6] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#F2EFE7]"
              >
                Browse gyms by discipline
              </Link>
            </>
          )}
        </div>
      </div>
    </section>
  )
}

function DisciplineRow({ discipline }: { discipline: LandingDiscipline }) {
  return (
    <li className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-white/10 py-3">
      <span className="text-[17px] font-medium">{discipline.label}</span>
      <span className={cn('font-mono text-[11px] tabular-nums', MUTED)}>
        {discipline.gyms} {discipline.gyms === 1 ? 'gym' : 'gyms'} ·{' '}
        {discipline.services === 0
          ? 'no classes listed'
          : `${discipline.services} ${discipline.services === 1 ? 'class' : 'classes'}`}
        {discipline.from ? ` · from ${peso(discipline.from)}` : ''}
      </span>
    </li>
  )
}

function SessionCell({ session }: { session: LandingSession }) {
  return (
    <li className="border-s border-white/10 ps-3">
      <p className={cn('font-mono text-[10px] uppercase tracking-[0.08em]', MUTED)}>{sessionTime(session.starts_at)}</p>
      <p className="mt-1 text-sm font-semibold leading-5">{session.name}</p>
      <p className={cn('mt-1 text-xs leading-5', MUTED)}>{session.gym_name}</p>
    </li>
  )
}

function PlansSection({
  data,
  loading,
  error,
}: {
  data: LandingPayload | null
  loading: boolean
  error: string
}) {
  const plans = data?.plans ?? []
  const stats = data?.stats ?? null

  return (
    <section id="plans" className={SECTION} aria-labelledby="plans-title">
      <div className={cn(SHELL, 'grid gap-10 lg:grid-cols-12 lg:gap-12')}>
        <div className="lg:col-span-7">
          <p className={cn('font-mono text-[11px] uppercase tracking-[0.16em]', MUTED)}>04</p>
          <h2 id="plans-title" className="mt-3 font-display text-[38px] uppercase leading-[0.96] tracking-[-0.015em] md:text-[52px] lg:text-[60px]">
            Pick a plan.
            <br />
            Make training routine.
          </h2>
          <p className={cn('mt-5 max-w-xl text-[15px] leading-7', MUTED)}>
            Compare published memberships, session packs, and annual passes before applying.
          </p>
        </div>
        <div className="lg:col-span-5 lg:justify-self-end lg:text-right">
          <p className={cn('font-mono text-[11px] uppercase tracking-[0.12em]', MUTED)}>Published plan fees</p>
          <p className="mt-2 font-mono text-[28px] leading-tight tracking-[-0.02em] tabular-nums md:text-[36px]">
            {stats ? feeRange(stats) || 'Not listed' : 'Loading'}
          </p>
          {stats && (
            <p className={cn('mt-2 text-sm', MUTED)}>
              {stats.plans} {stats.plans === 1 ? 'published membership plan' : 'published membership plans'}
            </p>
          )}
        </div>
      </div>

      <div className={cn(SHELL, 'mt-10')}>
        {error ? (
          <StateLine>Couldn’t load plans. Try again above.</StateLine>
        ) : loading ? (
          <LoadingRows rows={5} />
        ) : plans.length === 0 ? (
          <StateLine>No published membership plans are available right now.</StateLine>
        ) : (
          <>
            <ul className="border-y border-white/10">
              {plans.map((plan) => (
                <PlanRow key={plan.id} plan={plan} />
              ))}
            </ul>

            {data && data.plan_inclusions.length > 0 && (
              <div className="mt-10 max-w-2xl">
                <h3 className={cn('font-mono text-[11px] uppercase tracking-[0.12em]', MUTED)}>
                  Examples across published plans
                </h3>
                <ul className="mt-4 grid gap-2 sm:grid-cols-2">
                  {data.plan_inclusions.map((item) => (
                    <li key={item} className="flex gap-2 text-sm leading-6 text-[#C9C3B7]">
                      <span className="mt-2 size-1 shrink-0 bg-[#C63C26]" aria-hidden />
                      {item}
                    </li>
                  ))}
                </ul>
                <p className={cn('mt-4 text-sm leading-6', MUTED)}>
                  Inclusions vary by plan. Check the selected plan before applying.
                </p>
              </div>
            )}
          </>
        )}
      </div>
    </section>
  )
}

function PlanRow({ plan }: { plan: LandingPlan }) {
  return (
    <li className="flex flex-col gap-2 border-b border-white/10 py-5 last:border-b-0 md:flex-row md:items-center md:gap-6">
      <div className="min-w-0 flex-1">
        <h3 className="text-[18px] font-semibold leading-7 tracking-[-0.01em]">{plan.name}</h3>
        <p className={cn('mt-1 text-sm', MUTED)}>
          {plan.gym_name} · {planLength(plan)}
        </p>
      </div>
      <p className="font-mono text-[24px] leading-tight tracking-[-0.02em] tabular-nums md:text-[28px]">
        {peso(plan.total_fee)}
      </p>
      <Link
        to={`/gyms/${plan.gym_id}`}
        className="inline-flex min-h-11 items-center text-sm font-semibold underline underline-offset-4 hover:text-[#D8D2C6] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#F2EFE7]"
      >
        View plan
      </Link>
    </li>
  )
}

function CoachesSection({
  data,
  loading,
  error,
}: {
  data: LandingPayload | null
  loading: boolean
  error: string
}) {
  const coaches = data?.coaches ?? []

  return (
    <section id="coaches" className={SECTION} aria-labelledby="coaches-title">
      <div className={cn(SHELL, 'grid gap-10 lg:grid-cols-12 lg:gap-12')}>
        <div className="lg:col-span-4">
          <p className={cn('font-mono text-[11px] uppercase tracking-[0.16em]', MUTED)}>05</p>
          <h2 id="coaches-title" className="mt-3 font-display text-[38px] uppercase leading-[0.96] tracking-[-0.015em] md:text-[52px] lg:text-[60px]">
            Know who teaches.
          </h2>
          <p className={cn('mt-5 max-w-sm text-[15px] leading-7', MUTED)}>
            Read coach credentials and find the gym where they teach.
          </p>
          {data && (
            <p className={cn('mt-4 font-mono text-[12px] uppercase tracking-[0.1em]', MUTED)}>
              {data.stats.coaches} public {data.stats.coaches === 1 ? 'coach' : 'coaches'}
            </p>
          )}

          <figure className="mt-8">
            <div className="aspect-[4/5] w-full overflow-hidden bg-[#141416]">
              <img
                className="h-full w-full object-cover grayscale contrast-[1.05]"
                src={photoUrl(PHOTOS.coach.id, PHOTOS.coach.width, PHOTOS.coach.height)}
                alt={PHOTOS.coach.alt}
                width={PHOTOS.coach.width}
                height={PHOTOS.coach.height}
                loading="lazy"
              />
            </div>
            <figcaption className={cn('mt-2 font-mono text-[10px] uppercase tracking-[0.1em]', MUTED)}>
              Editorial boxing image · Pexels. Not a coach portrait.
            </figcaption>
          </figure>
        </div>

        <div className="min-w-0 lg:col-span-8">
          <p className={cn('font-mono text-[11px] uppercase tracking-[0.12em]', MUTED)}>Selected public coaches</p>
          {error ? (
            <StateLine>Couldn’t load coaches. Try again above.</StateLine>
          ) : loading ? (
            <div className="mt-4">
              <LoadingRows rows={6} />
            </div>
          ) : coaches.length === 0 ? (
            <StateLine>No public coaches are listed right now.</StateLine>
          ) : (
            <ul className="mt-4 border-t border-white/10">
              {coaches.slice(0, 6).map((coach) => (
                <CoachRow key={`${coach.gym_id}-${coach.id}`} coach={coach} />
              ))}
            </ul>
          )}
        </div>
      </div>
    </section>
  )
}

function CoachRow({ coach }: { coach: LandingCoach }) {
  return (
    <li className="flex flex-col gap-1 border-b border-white/10 py-5 md:flex-row md:items-baseline md:justify-between md:gap-6">
      <div className="min-w-0">
        <h3 className="text-[19px] font-semibold leading-7 tracking-[-0.01em]">{coach.name}</h3>
        <p className={cn('mt-1 text-sm leading-6', MUTED)}>
          {[coach.rank, coach.specialisation].filter(Boolean).join(' · ') || 'Coach profile'}
        </p>
      </div>
      <Link
        to={`/gyms/${coach.gym_id}`}
        className="inline-flex min-h-11 shrink-0 items-center text-sm text-[#C9C3B7] underline underline-offset-4 hover:text-[#F2EFE7] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#F2EFE7]"
      >
        {coach.gym_name}
      </Link>
    </li>
  )
}

function OwnerSection() {
  return (
    <section id="owners" className={cn(SECTION, 'scroll-mt-24')} aria-labelledby="owners-title">
      <div className={SHELL}>
        <div className="max-w-4xl">
          <p className={cn('font-mono text-[11px] uppercase tracking-[0.16em]', MUTED)}>06</p>
          <h2 id="owners-title" className="mt-3 font-display text-[38px] uppercase leading-[0.96] tracking-[-0.015em] md:text-[52px] lg:text-[60px]">
            Train here.
            <br />
            Run your gym here.
          </h2>
          <p className={cn('mt-5 max-w-2xl text-[15px] leading-7', MUTED)}>
            Members manage their training. Owners manage the work behind it.
          </p>
        </div>

        <div className="mt-10 grid gap-12 lg:grid-cols-12 lg:gap-16">
          <div className="lg:col-span-5">
            <h3 className="font-display text-[28px] uppercase leading-tight tracking-[-0.01em] md:text-[32px]">For members</h3>
            <ul className="mt-6 border-t border-white/10">
              {MEMBER_MODULES.map((module) => (
                <ModuleRow key={module.title} title={module.title} body={module.body} />
              ))}
            </ul>
            <Button
              className="mt-6 h-12 rounded-none border-[#F2EFE7] bg-[#F2EFE7] px-5 text-[#0A0A0B] hover:bg-[#E2DDD1]"
              render={<Link to="/sign-up?type=member" />}
            >
              Find a gym
            </Button>
          </div>

          <div className="lg:col-span-6 lg:col-start-7">
            <h3 className="font-display text-[28px] uppercase leading-tight tracking-[-0.01em] md:text-[32px]">For gym owners</h3>
            <ul className="mt-6 border-t border-white/10">
              {OWNER_MODULES.map((module) => (
                <ModuleRow key={module.title} title={module.title} body={module.body} />
              ))}
            </ul>
            <Button
              variant="outline"
              className="mt-6 h-12 rounded-none border-white/25 bg-transparent px-5 text-[#F2EFE7] hover:bg-white/5 dark:bg-transparent dark:hover:bg-white/5"
              render={<Link to="/sign-up?type=owner" />}
            >
              Apply to list your gym
            </Button>
          </div>
        </div>
      </div>
    </section>
  )
}

function ModuleRow({ title, body }: { title: string; body: string }) {
  return (
    <li className="border-b border-white/10 py-4">
      <h4 className="text-[17px] font-semibold leading-6">{title}</h4>
      <p className={cn('mt-1 text-[15px] leading-6', MUTED)}>{body}</p>
    </li>
  )
}

function VerificationSection() {
  return (
    <section id="verification" className={cn(SECTION, 'scroll-mt-24')} aria-labelledby="verification-title">
      <div className={SHELL}>
        <div className="max-w-4xl">
          <p className={cn('font-mono text-[11px] uppercase tracking-[0.16em]', MUTED)}>07</p>
          <h2 id="verification-title" className="mt-3 font-display text-[38px] uppercase leading-[0.96] tracking-[-0.015em] md:text-[52px] lg:text-[60px]">
            Know the process.
            <br />
            Then step in.
          </h2>
          <p className={cn('mt-5 max-w-2xl text-[15px] leading-7', MUTED)}>
            Membership applications and gym verification follow different checks.
          </p>
        </div>

        <div className="mt-10 grid gap-12 lg:grid-cols-12 lg:gap-16">
          <div className="lg:col-span-5">
            <h3 className="text-[22px] font-semibold leading-8 tracking-[-0.01em]">Joining as a member</h3>
            <ol className="mt-6 flex flex-col gap-5">
              {[
                'Choose a gym and plan.',
                'Complete the membership application, health questionnaire, and waivers.',
                'Follow the gym’s application and checkout steps.',
                'Book sessions and use QR check-in when your membership allows.',
              ].map((step, index) => (
                <li key={step} className="flex gap-4">
                  <span className={cn('mt-1 font-mono text-[13px] tabular-nums', MUTED)}>{String(index + 1).padStart(2, '0')}</span>
                  <span className="max-w-[38ch] text-[15px] leading-7 text-[#C9C3B7]">{step}</span>
                </li>
              ))}
            </ol>
            <p className={cn('mt-5 max-w-[44ch] text-sm leading-6', MUTED)}>
              Payment does not guarantee acceptance. The gym decides each application.
            </p>
          </div>

          <div className="lg:col-span-7">
            <h3 className="text-[22px] font-semibold leading-8 tracking-[-0.01em]">Listing a gym</h3>
            <p className={cn('mt-3 max-w-[52ch] text-[15px] leading-7', MUTED)}>
              Submit your verification documents. An administrator reviews every application.
            </p>

            <div className="mt-6 border-t border-white/10 pt-5">
              <h4 className="font-mono text-[12px] uppercase tracking-[0.12em]">Tier 1</h4>
              <ul className="mt-3 grid gap-2 sm:grid-cols-2">
                {['DTI or SEC certificate', 'Mayor’s or business permit', 'Barangay clearance'].map((item) => (
                  <li key={item} className="flex gap-2 text-sm leading-6 text-[#C9C3B7]">
                    <span className="mt-2 size-1 shrink-0 bg-[#C63C26]" aria-hidden />
                    {item}
                  </li>
                ))}
              </ul>
            </div>

            <div className="mt-6 border-t border-white/10 pt-5">
              <h4 className="font-mono text-[12px] uppercase tracking-[0.12em]">Tier 2</h4>
              <p className={cn('mt-2 text-sm', MUTED)}>All Tier 1 requirements, plus:</p>
              <ul className="mt-3 grid gap-2 sm:grid-cols-2">
                {['10+ facility photos', 'Video tour', 'Owner’s valid ID', 'Owner selfie', 'Recent utility bill'].map((item) => (
                  <li key={item} className="flex gap-2 text-sm leading-6 text-[#C9C3B7]">
                    <span className="mt-2 size-1 shrink-0 bg-[#C63C26]" aria-hidden />
                    {item}
                  </li>
                ))}
              </ul>
            </div>

            <p className={cn('mt-5 max-w-[56ch] text-sm leading-6', MUTED)}>
              Verification tiers describe the documents a gym submitted. They are not training-quality rankings.
            </p>

            <Button
              className="mt-6 h-12 rounded-none border-[#F2EFE7] bg-[#F2EFE7] px-5 text-[#0A0A0B] hover:bg-[#E2DDD1]"
              render={<Link to="/sign-up?type=owner" />}
            >
              Apply for gym verification
            </Button>
          </div>
        </div>
      </div>
    </section>
  )
}

function FaqSection({ stats }: { stats: LandingStats | null }) {
  const items = stats?.plan_fee_min && stats.plan_fee_max
    ? [
        {
          ...FAQ[0],
          a: `Published plan fees range from ${peso(stats.plan_fee_min)} to ${peso(stats.plan_fee_max)}. Monthly memberships, session packs, and annual passes are all listed by their gym.`,
        },
        ...FAQ.slice(1),
      ]
    : FAQ

  return (
    <section id="faq" className={cn(SECTION, 'scroll-mt-24')} aria-labelledby="faq-title">
      <div className={cn(SHELL, 'grid gap-10 lg:grid-cols-12 lg:gap-12')}>
        <div className="lg:col-span-4">
          <p className={cn('font-mono text-[11px] uppercase tracking-[0.16em]', MUTED)}>08</p>
          <h2 id="faq-title" className="mt-3 font-display text-[38px] uppercase leading-[0.96] tracking-[-0.015em] md:text-[52px] lg:text-[60px]">
            Before you join.
          </h2>
          <p className={cn('mt-5 max-w-sm text-[15px] leading-7', MUTED)}>
            Answers about plans, booking, check-in, and verification.
          </p>
        </div>

        <div className="lg:col-span-8">
          <div className="border-t border-white/10">
            {items.map((item) => (
              <Collapsible key={item.q} className="border-b border-white/10">
                <CollapsibleTrigger className="group flex min-h-[72px] w-full items-start justify-between gap-4 py-6 pr-1 text-left focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#F2EFE7]">
                  <span className="text-[17px] font-semibold leading-6">{item.q}</span>
                  <span className="mt-0.5 shrink-0 text-[#C9C3B7]">
                    <Plus className="size-5 group-data-panel-open:hidden" aria-hidden />
                    <Minus className="hidden size-5 group-data-panel-open:block" aria-hidden />
                  </span>
                </CollapsibleTrigger>
                <CollapsiblePanel>
                  <p className={cn('max-w-[60ch] pb-6 text-[15px] leading-7', MUTED)}>{item.a}</p>
                </CollapsiblePanel>
              </Collapsible>
            ))}
          </div>
        </div>
      </div>
    </section>
  )
}

function ClosingSection() {
  return (
    <section aria-labelledby="closing-title">
      <div className="h-[160px] w-full overflow-hidden bg-[#141416] md:h-[240px]">
        <img
          className="h-full w-full object-cover grayscale contrast-[1.05]"
          src={photoUrl(PHOTOS.closing.id, PHOTOS.closing.width, PHOTOS.closing.height)}
          alt={PHOTOS.closing.alt}
          width={PHOTOS.closing.width}
          height={PHOTOS.closing.height}
          loading="lazy"
        />
      </div>

      <div className={cn(SHELL, 'py-14 md:py-16 lg:py-20')}>
        <div className="grid gap-8 lg:grid-cols-12 lg:items-end">
          <div className="lg:col-span-8">
            <p className={cn('font-mono text-[11px] uppercase tracking-[0.16em]', MUTED)}>09</p>
            <h2 id="closing-title" className="mt-3 font-display text-[44px] uppercase leading-[0.95] tracking-[-0.015em] md:text-[64px] lg:text-[80px]">
              Your next session
              <br />
              starts with a <span className="text-[#C63C26]">gym.</span>
            </h2>
            <p className={cn('mt-5 max-w-xl text-[15px] leading-7', MUTED)}>
              Find a place to train, or apply to bring your gym onto Combat Platform.
            </p>
          </div>
          <div className="flex flex-col gap-3 lg:col-span-4 lg:items-end">
            <Button
              className="h-12 w-full rounded-none border-[#F2EFE7] bg-[#F2EFE7] px-5 text-[#0A0A0B] hover:bg-[#E2DDD1] lg:w-auto"
              render={<Link to="/sign-up?type=member" />}
            >
              Find a gym
            </Button>
            <Button
              variant="outline"
              className="h-12 w-full rounded-none border-white/25 bg-transparent px-5 text-[#F2EFE7] hover:bg-white/5 dark:bg-transparent dark:hover:bg-white/5 lg:w-auto"
              render={<Link to="/sign-up?type=owner" />}
            >
              Apply to list your gym
            </Button>
          </div>
        </div>
        <p className={cn('mt-8 font-mono text-[10px] uppercase tracking-[0.1em]', MUTED)}>
          Editorial gym image · Pexels. Not a listed gym’s facility.
        </p>
      </div>
    </section>
  )
}

function Footer() {
  return (
    <footer className="border-t border-white/10">
      <div className={cn(SHELL, 'py-10')}>
        <div className="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">
          <div>
            <p className="font-display text-[26px] uppercase leading-none tracking-[-0.01em]">Combat Platform</p>
            <p className={cn('mt-3 max-w-xs text-sm leading-6', MUTED)}>Combat-sports training centers in the Philippines.</p>
          </div>
          <nav className="flex flex-wrap gap-x-6 gap-y-3" aria-label="Footer">
            {[
              { href: '#gyms', label: 'Gyms' },
              { href: '#plans', label: 'Plans' },
              { href: '#verification', label: 'Verification' },
            ].map((item) => (
              <a
                key={item.href}
                href={item.href}
                className="inline-flex min-h-11 items-center text-sm text-[#C9C3B7] underline-offset-4 hover:text-[#F2EFE7] hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#F2EFE7]"
              >
                {item.label}
              </a>
            ))}
            <Link
              to="/sign-in"
              className="inline-flex min-h-11 items-center text-sm text-[#C9C3B7] underline-offset-4 hover:text-[#F2EFE7] hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#F2EFE7]"
            >
              Sign in
            </Link>
          </nav>
        </div>

        <details className="mt-10 border-t border-white/10 pt-6">
          <summary className="inline-flex min-h-11 cursor-pointer items-center font-mono text-[11px] uppercase tracking-[0.12em] text-[#C9C3B7] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#F2EFE7]">
            Photo credits
          </summary>
          <ul className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            {PHOTO_CREDITS.map((credit) => (
              <li key={credit.id} className="text-sm leading-6 text-[#A6A096]">
                {credit.label} ·{' '}
                <a
                  className="underline underline-offset-2 hover:text-[#F2EFE7]"
                  href={`https://www.pexels.com/photo/${credit.id}/`}
                  target="_blank"
                  rel="noreferrer"
                >
                  Pexels
                </a>
              </li>
            ))}
          </ul>
        </details>

        <p className={cn('mt-8 text-sm', MUTED)}>© {YEAR} Combat Platform</p>
      </div>
    </footer>
  )
}

function StateLine({ children }: { children: React.ReactNode }) {
  return <p className={cn('border-y border-white/10 py-8 text-sm leading-6', MUTED)}>{children}</p>
}

function LoadingRows({ rows }: { rows: number }) {
  return (
    <div className="flex flex-col gap-2" aria-hidden="true">
      {Array.from({ length: rows }).map((_, index) => (
        <div key={index} className="h-[76px] w-full bg-[#141416]" />
      ))}
    </div>
  )
}
