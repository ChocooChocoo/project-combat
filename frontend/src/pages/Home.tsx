import { Dumbbell, FilePlus2, LocateFixed, MapPin, Search, SlidersHorizontal, Sun, Sunrise, Sunset, X } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router'
import { GymMap, distanceLabel, type GymMapGym } from '@/components/gym-map'
import { PageHeader } from '@/components/page-header'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { CheckboxGroup } from '@/components/ui/checkbox-group'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group'
import { Popover, PopoverDescription, PopoverPopup, PopoverTitle, PopoverTrigger } from '@/components/ui/popover'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError } from '@/lib/api'
import { type GymAccess, useAuth } from '@/lib/auth'
import { cn } from '@/lib/utils'
import {
  type DiscoveryOptions,
  type GeoResult,
  type GymPreferences,
  labelKey,
  type PreferredTime,
  preferredTimeOptions,
  type PublicGym,
  type Recommendation,
  type SearchResult,
  tierOptions,
} from '@/lib/discovery'
import { canOpenWorkspace } from '@/lib/gym-access'
import { DAYS } from '@/lib/gyms'

type SearchFilters = {
  q: string
  location: string
  discipline: string
  service: string
  tier: string
  day: string
  sort: 'name' | 'distance'
}

type Point = {
  lat: number
  lng: number
  label: string
}

type AppliedSearch = {
  filters: SearchFilters
  point: Point | null
}

type PreferenceForm = {
  discipline: string
  min_price: string
  max_price: string
  preferred_times: PreferredTime[]
}

const EMPTY_FILTERS: SearchFilters = {
  q: '',
  location: '',
  discipline: '',
  service: '',
  tier: '',
  day: '',
  sort: 'name',
}

const SORTS = [
  { value: 'name', label: 'Name' },
  { value: 'distance', label: 'Distance' },
]

const TIME_ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
  morning: Sunrise,
  afternoon: Sun,
  evening: Sunset,
}

function searchQuery(applied: AppliedSearch): string {
  const params = new URLSearchParams()
  const { filters, point } = applied
  const location = filters.location.trim()

  // The API takes city and province separately. One location field is easier to use: a value
  // with a comma splits into the two, and a single value is matched against either field.
  if (location.includes(',')) {
    const [city, province] = location.split(',').map((part) => part.trim())
    if (city) params.set('city', city)
    if (province) params.set('province', province)
  }
  if (filters.q.trim()) params.set('q', filters.q.trim())
  else if (location && !location.includes(',')) params.set('q', location)
  if (filters.discipline) params.set('discipline', filters.discipline)
  if (filters.service.trim()) params.set('service', filters.service.trim())
  if (filters.tier) params.set('tier', filters.tier)
  if (filters.day) params.set('day', filters.day)
  params.set('sort', filters.sort)
  if (point) {
    params.set('lat', String(point.lat))
    params.set('lng', String(point.lng))
  }

  return params.toString()
}

function matchesLocation(gym: PublicGym, location: string): boolean {
  const terms = location
    .split(',')
    .map((part) => part.trim().toLowerCase())
    .filter(Boolean)
  if (terms.length === 0) return true
  return terms.every((term) => [gym.city, gym.province].some((place) => place?.toLowerCase().includes(term)))
}

function money(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return ''
  const amount = Number(value)
  if (!Number.isFinite(amount)) return ''
  return `₱${amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function serviceNames(gym: PublicGym): string[] {
  if (gym.service_names && gym.service_names.length > 0) return gym.service_names
  return (gym.services ?? [])
    .map((service) => (typeof service === 'string' ? service : (service.name ?? '')))
    .filter((service) => service.length > 0)
}

function preferencesForm(preferences: GymPreferences | null | undefined): PreferenceForm {
  return {
    discipline: preferences?.discipline ?? '',
    min_price: preferences?.min_price ?? '',
    max_price: preferences?.max_price ?? '',
    preferred_times: preferences?.preferred_times ?? [],
  }
}

/** The owner's return path. One divided list, approved gyms first, no decorative status badges. */
function YourGymsSection({ gyms, workspaceGymId }: { gyms: GymAccess[]; workspaceGymId?: number }) {
  const sorted = [...gyms].sort(
    (left, right) => Number(right.status === 'approved') - Number(left.status === 'approved'),
  )

  return (
    <section className="flex flex-col gap-4" aria-labelledby="your-gyms">
      <div className="flex flex-wrap items-baseline gap-2">
        <h2 className="font-semibold text-xl tracking-tight" id="your-gyms">
          Your gyms
        </h2>
        {sorted.length > 0 && (
          <span className="text-muted-foreground text-sm tabular-nums">
            {sorted.length} {sorted.length === 1 ? 'gym' : 'gyms'}
          </span>
        )}
      </div>

      {sorted.length === 0 ? (
        <Card>
          <CardHeader>
            <CardTitle render={<h3 />}>No gym yet</CardTitle>
            <CardDescription>Apply to run your own gym, then follow the review from your applications.</CardDescription>
          </CardHeader>
          <CardPanel className="pt-0">
            <Button size="sm" variant="outline" render={<Link to="/applications" />}>
              My applications
            </Button>
          </CardPanel>
        </Card>
      ) : (
        <Card>
          <ul className="divide-y">
            {sorted.map((gym) => {
              const approved = gym.status === 'approved'
              return (
                <li
                  className="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:gap-4 sm:px-6"
                  key={gym.id}
                >
                  <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1.5">
                    {approved ? (
                      <Link className="font-medium underline-offset-4 hover:underline" to={`/gyms/${gym.id}`}>
                        {gym.name}
                      </Link>
                    ) : (
                      <span className="font-medium">{gym.name}</span>
                    )}
                    {!approved && (
                      <Badge className="capitalize" variant={gym.status === 'rejected' ? 'error' : 'warning'}>
                        {gym.status.replaceAll('_', ' ')}
                      </Badge>
                    )}
                    {gym.roles.map((role) => (
                      <Badge className="capitalize" key={role} variant="outline">
                        {role}
                      </Badge>
                    ))}
                  </div>

                  <div className="flex flex-wrap items-center gap-2">
                    {approved && canOpenWorkspace(gym) && gym.id !== workspaceGymId && (
                      <Button size="sm" variant="outline" render={<Link to={`/gyms/${gym.id}/manage`} />}>
                        Open workspace
                      </Button>
                    )}
                    {approved && gym.roles.includes('member') && (
                      <Button size="sm" variant="ghost" render={<Link to={`/gyms/${gym.id}/my-record`} />}>
                        My record
                      </Button>
                    )}
                  </div>
                </li>
              )
            })}
          </ul>
        </Card>
      )}
    </section>
  )
}

/** S-09, R-30, R-97 and R-98: find a gym by filters, distance and best match. */
export default function Home() {
  const { user } = useAuth()
  const isOwner = user?.account_type === 'owner'
  const isMember = user?.account_type === 'member'

  const [options, setOptions] = useState<DiscoveryOptions | null>(null)
  const [optionsError, setOptionsError] = useState('')
  const [optionsVersion, setOptionsVersion] = useState(0)

  const [filters, setFilters] = useState<SearchFilters>(EMPTY_FILTERS)
  const [filtersOpen, setFiltersOpen] = useState(false)
  const [applied, setApplied] = useState<AppliedSearch>({ filters: EMPTY_FILTERS, point: null })
  const [searchVersion, setSearchVersion] = useState(0)
  const [results, setResults] = useState<SearchResult | null>(null)
  const [searching, setSearching] = useState(true)
  const [searchError, setSearchError] = useState('')

  const [point, setPoint] = useState<Point | null>(null)
  const [locating, setLocating] = useState(false)
  const [locationError, setLocationError] = useState('')
  const [locationNotice, setLocationNotice] = useState('')

  const [address, setAddress] = useState('')
  const [addressOpen, setAddressOpen] = useState(false)
  const [geoResults, setGeoResults] = useState<GeoResult[] | null>(null)
  const [geoLoading, setGeoLoading] = useState(false)
  const [geoError, setGeoError] = useState('')

  const [preferencesLoading, setPreferencesLoading] = useState(true)
  const [preferencesLoadError, setPreferencesLoadError] = useState('')
  const [preferencesSaveError, setPreferencesSaveError] = useState('')
  const [preferencesVersion, setPreferencesVersion] = useState(0)
  const [preferenceForm, setPreferenceForm] = useState<PreferenceForm>(preferencesForm(null))
  const [savingPreferences, setSavingPreferences] = useState(false)

  const [recommendations, setRecommendations] = useState<Recommendation[] | null>(null)
  const [matchesLoading, setMatchesLoading] = useState(false)
  const [matchesError, setMatchesError] = useState('')

  useEffect(() => {
    let live = true
    setOptionsError('')

    api<{ options: DiscoveryOptions }>('/discovery/options')
      .then((result) => {
        if (live) setOptions(result.options ?? null)
      })
      .catch((error) => {
        if (live) setOptionsError(error instanceof ApiError ? error.message : 'The filter lists could not be loaded.')
      })

    return () => {
      live = false
    }
  }, [optionsVersion])

  useEffect(() => {
    let live = true
    setSearching(true)
    setSearchError('')

    api<SearchResult>(`/gyms?${searchQuery(applied)}`)
      .then((result) => {
        if (!live) return
        const gyms = (result.gyms ?? []).filter((gym) => matchesLocation(gym, applied.filters.location))
        setResults({ gyms, total: gyms.length })
      })
      .catch((error) => {
        if (!live) return
        setResults(null)
        setSearchError(error instanceof ApiError ? error.message : 'Gyms could not be loaded.')
      })
      .finally(() => {
        if (live) setSearching(false)
      })

    return () => {
      live = false
    }
  }, [applied, searchVersion])

  useEffect(() => {
    if (!isMember) return

    let live = true
    setPreferencesLoading(true)
    setPreferencesLoadError('')

    api<{ preferences: GymPreferences }>('/gym-preferences')
      .then((result) => {
        if (live) setPreferenceForm(preferencesForm(result.preferences))
      })
      .catch((error) => {
        if (live) setPreferencesLoadError(error instanceof ApiError ? error.message : 'Your preferences could not be loaded.')
      })
      .finally(() => {
        if (live) setPreferencesLoading(false)
      })

    return () => {
      live = false
    }
  }, [isMember, preferencesVersion])

  const mapGyms = useMemo<GymMapGym[]>(
    () =>
      (results?.gyms ?? []).flatMap((gym) => {
        if (gym.latitude == null || gym.longitude == null) return []
        return [
          {
            id: gym.id,
            name: gym.name,
            city: gym.city,
            province: gym.province,
            latitude: gym.latitude,
            longitude: gym.longitude,
            distance_km: gym.distance_km,
          },
        ]
      }),
    [results],
  )

  const disciplineLabel = (key: string) => options?.disciplines?.[key] ?? labelKey(key)
  const tiers = tierOptions(options)
  const timeOptions = preferredTimeOptions(options)

  function applyPoint(next: Point, nextFilters: SearchFilters = { ...filters, sort: 'distance' }) {
    setPoint(next)
    setFilters(nextFilters)
    setApplied({ filters: nextFilters, point: next })
    setFiltersOpen(false)
    setLocationNotice(`Location set to ${next.label}.`)
  }

  function search(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    // The address form lives in a portal but still bubbles through this form in React.
    if (event.target !== event.currentTarget) return
    setApplied({ filters: { ...filters }, point })
    setFiltersOpen(false)
  }

  function clearFilters() {
    const nextFilters = { ...filters, discipline: '', service: '', tier: '', day: '' }
    setFilters(nextFilters)
    setApplied({ filters: nextFilters, point })
  }

  function changeSort(value: string | null) {
    const sort = value === 'distance' ? 'distance' : 'name'
    setFilters((current) => ({ ...current, sort }))
    setApplied((current) => ({ ...current, filters: { ...current.filters, sort } }))
  }

  function useMyLocation() {
    setLocationError('')
    if (!('geolocation' in navigator)) {
      setLocationError('This browser cannot share your location. Type an address instead.')
      return
    }

    setLocating(true)
    // The browser's own timeout does not count time spent on its permission prompt, so guard that too.
    let settled = false
    const guard = setTimeout(() => {
      if (settled) return
      settled = true
      setLocating(false)
      setLocationError('No answer from your browser about your location. Type an address instead.')
    }, 15_000)

    navigator.geolocation.getCurrentPosition(
      (position) => {
        if (settled) return
        settled = true
        clearTimeout(guard)
        setLocating(false)
        applyPoint({
          lat: position.coords.latitude,
          lng: position.coords.longitude,
          label: 'Your location',
        })
      },
      (failure) => {
        if (settled) return
        settled = true
        clearTimeout(guard)
        setLocating(false)
        setLocationError(
          failure.code === failure.PERMISSION_DENIED
            ? 'Location access is blocked in your browser. Type an address instead.'
            : 'Your location could not be read. Type an address instead.',
        )
      },
      { timeout: 10_000, maximumAge: 60_000 },
    )
  }

  function clearLocation() {
    const nextFilters = filters.sort === 'distance' ? { ...filters, sort: 'name' as const } : filters
    setPoint(null)
    setFilters(nextFilters)
    setApplied({ filters: nextFilters, point: null })
    setLocationNotice('Location cleared.')
  }

  async function findAddress(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    // The popover is portalled but still a React descendant of the search form.
    event.stopPropagation()
    const query = address.trim()
    if (!query) return

    setGeoLoading(true)
    setGeoError('')
    try {
      const result = await api<{ results: GeoResult[] }>(`/geo/search?q=${encodeURIComponent(query)}`)
      setGeoResults((result.results ?? []).slice(0, 5))
    } catch (error) {
      setGeoResults(null)
      setGeoError(error instanceof ApiError ? error.message : 'The address could not be searched.')
    } finally {
      setGeoLoading(false)
    }
  }

  function pickAddress(result: GeoResult) {
    setGeoResults(null)
    setAddress(result.label)
    applyPoint({ lat: result.lat, lng: result.lng, label: result.label })
    setAddressOpen(false)
  }

  async function savePreferences(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSavingPreferences(true)
    setPreferencesSaveError('')

    try {
      const result = await api<{ preferences: GymPreferences }>('/gym-preferences', {
        method: 'PUT',
        body: {
          discipline: preferenceForm.discipline || null,
          min_price: preferenceForm.min_price.trim() === '' ? null : Number(preferenceForm.min_price),
          max_price: preferenceForm.max_price.trim() === '' ? null : Number(preferenceForm.max_price),
          preferred_times: preferenceForm.preferred_times,
        },
      })
      setPreferenceForm(preferencesForm(result.preferences))
      setPreferencesLoadError('')
      toastManager.add({ title: 'Preferences saved.', type: 'success' })
      await findMatches()
    } catch (error) {
      setPreferencesSaveError(error instanceof ApiError ? error.message : 'Your preferences could not be saved.')
    } finally {
      setSavingPreferences(false)
    }
  }

  async function findMatches() {
    if (!isMember) return
    setMatchesLoading(true)
    setMatchesError('')
    try {
      const query = point ? `?lat=${point.lat}&lng=${point.lng}` : ''
      const result = await api<{ recommendations: Recommendation[] }>(`/gyms/recommendations${query}`)
      setRecommendations(result.recommendations ?? [])
    } catch (error) {
      setMatchesError(error instanceof ApiError ? error.message : 'Best matches could not be loaded.')
    } finally {
      setMatchesLoading(false)
    }
  }

  const approvedGyms = user?.gyms.filter((gym) => gym.status === 'approved') ?? []
  const workspaceGym = user?.is_platform_admin ? undefined : approvedGyms.find(canOpenWorkspace)
  const showGymsFirst = isOwner && !user?.is_platform_admin
  const activeFilterCount = [filters.discipline, filters.service.trim(), filters.tier, filters.day].filter(Boolean).length
  const showMap = !searchError && !(results && results.gyms.length === 0 && !point)

  return (
    <div className="flex flex-col gap-8">
      <PageHeader
        title={`Welcome, ${user?.first_name ?? ''}`}
        description={
          user?.is_platform_admin
            ? 'Gyms live on the platform today.'
            : workspaceGym
              ? 'Return to your gym, or find one on the platform.'
              : isOwner && user?.gyms.length
                ? 'Follow your application, or find a gym on the platform.'
                : isOwner
                  ? 'Apply to run your own gym, then follow the review from your applications.'
                  : 'Find a gym and see your best matches.'
        }
        actions={
          user?.is_platform_admin ? undefined : workspaceGym ? (
            <Button className="max-sm:min-h-11" render={<Link to={`/gyms/${workspaceGym.id}/manage`} />}>
              Open workspace
            </Button>
          ) : isOwner && user?.gyms.length ? (
            <Button className="max-sm:min-h-11" variant="outline" render={<Link to="/applications" />}>
              My applications
            </Button>
          ) : isOwner ? (
            <Button className="max-sm:min-h-11" render={<Link to="/apply" />}>
              <FilePlus2 aria-hidden /> Start an application
            </Button>
          ) : undefined
        }
      />

      {showGymsFirst && <YourGymsSection gyms={user?.gyms ?? []} workspaceGymId={workspaceGym?.id} />}

      <section className="flex flex-col gap-4" aria-labelledby="find-gym">
        <div className="flex flex-col gap-1.5">
          <h2 id="find-gym" className="font-semibold text-xl tracking-tight">Find a gym</h2>
          <span id="find-gym-description" className="text-muted-foreground text-sm">
            Search by place, sport, service, tier or opening day.
          </span>
        </div>

        <Card className="overflow-hidden">
          <CardPanel className="flex flex-col p-0">
            {optionsError && (
              <div className="border-b p-4 sm:p-6">
                <Alert variant="warning">
                  <AlertTitle>Filter lists could not be loaded</AlertTitle>
                  <AlertDescription>
                    <p>You can still search by text. The sport, tier and day lists need another try.</p>
                    <Button size="sm" variant="outline" onClick={() => setOptionsVersion((version) => version + 1)}>
                      Try again
                    </Button>
                  </AlertDescription>
                </Alert>
              </div>
            )}

            <Form className="flex flex-col" aria-describedby="find-gym-description" onSubmit={search}>
              <div className="p-4 sm:p-6">
                <div className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <Field name="q" className="w-full min-w-0">
                  <FieldLabel>Search</FieldLabel>
                  <InputGroup>
                    <InputGroupAddon>
                      <Search aria-hidden />
                    </InputGroupAddon>
                    <InputGroupInput
                      maxLength={120}
                      name="q"
                      onChange={(event) => setFilters({ ...filters, q: event.target.value })}
                      placeholder="Gym name or service"
                      type="search"
                      value={filters.q}
                    />
                  </InputGroup>
                  <FieldError />
                </Field>
                <Field name="location" className="w-full min-w-0">
                  <FieldLabel>City or province</FieldLabel>
                  <Input
                    className="max-sm:h-11"
                    maxLength={120}
                    onChange={(event) => setFilters({ ...filters, location: event.target.value })}
                    placeholder="City, or city, province"
                    value={filters.location}
                  />
                  <FieldError />
                </Field>
                </div>
              </div>

              <div className="border-t bg-muted/40 p-4 sm:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div className="flex items-baseline gap-2">
                    <h3 className="font-medium text-sm">Refine results</h3>
                    {activeFilterCount > 0 && (
                      <span className="text-muted-foreground text-xs tabular-nums">
                        {activeFilterCount} active
                      </span>
                    )}
                  </div>
                  <div className="flex items-center gap-2">
                    {activeFilterCount > 0 && (
                      <Button onClick={clearFilters} size="xs" type="button" variant="ghost">
                        Clear
                      </Button>
                    )}
                    <Button
                      aria-controls="find-gym-filters"
                      aria-expanded={filtersOpen}
                      className="max-sm:min-h-11 lg:hidden"
                      onClick={() => setFiltersOpen((open) => !open)}
                      type="button"
                      variant="outline"
                    >
                      <SlidersHorizontal aria-hidden />{' '}
                      {filtersOpen
                        ? 'Hide filters'
                        : `Show filters${activeFilterCount > 0 ? ` (${activeFilterCount})` : ''}`}
                    </Button>
                  </div>
                </div>

                <div
                  className={cn('mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4', !filtersOpen && 'max-lg:hidden')}
                  id="find-gym-filters"
                >
                <Field name="discipline" className="w-full min-w-0">
                  <FieldLabel>Combat sport</FieldLabel>
                  <Select
                    items={[
                      { value: 'any', label: 'Any sport' },
                      ...Object.entries(options?.disciplines ?? {}).map(([value, label]) => ({ value, label })),
                    ]}
                    onValueChange={(value) =>
                      setFilters({ ...filters, discipline: String(value ?? '') === 'any' ? '' : String(value ?? '') })
                    }
                    value={filters.discipline || 'any'}
                  >
                    <SelectTrigger className="max-sm:min-h-11" disabled={!options}>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectPopup>
                      <SelectItem className="max-sm:min-h-11" value="any">
                        Any sport
                      </SelectItem>
                      {Object.entries(options?.disciplines ?? {}).map(([value, label]) => (
                        <SelectItem className="max-sm:min-h-11" key={value} value={value}>
                          {label}
                        </SelectItem>
                      ))}
                    </SelectPopup>
                  </Select>
                </Field>
                <Field name="service" className="w-full min-w-0">
                  <FieldLabel>Service</FieldLabel>
                  <Input
                    className="max-sm:h-11"
                    maxLength={100}
                    onChange={(event) => setFilters({ ...filters, service: event.target.value })}
                    placeholder="For example, sparring"
                    value={filters.service}
                  />
                  <FieldError />
                </Field>
                <Field name="tier" className="w-full min-w-0">
                  <FieldLabel>Tier</FieldLabel>
                  <Select
                    items={[
                      { value: 'any', label: 'Any tier' },
                      ...tiers.map((tier) => ({ value: tier.value, label: tier.label })),
                    ]}
                    onValueChange={(value) =>
                      setFilters({ ...filters, tier: String(value ?? '') === 'any' ? '' : String(value ?? '') })
                    }
                    value={filters.tier || 'any'}
                  >
                    <SelectTrigger className="max-sm:min-h-11" disabled={!options}>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectPopup>
                      <SelectItem className="max-sm:min-h-11" value="any">
                        Any tier
                      </SelectItem>
                      {tiers.map((tier) => (
                        <SelectItem className="max-sm:min-h-11" key={tier.value} value={tier.value}>
                          {tier.label}
                        </SelectItem>
                      ))}
                    </SelectPopup>
                  </Select>
                </Field>
                <Field name="day" className="w-full min-w-0">
                  <FieldLabel>Open on</FieldLabel>
                  <Select
                    items={[
                      { value: 'any', label: 'Any day' },
                      ...DAYS.map((day) => ({ value: day.key, label: options?.weekdays?.[day.key] ?? day.label })),
                    ]}
                    onValueChange={(value) =>
                      setFilters({ ...filters, day: String(value ?? '') === 'any' ? '' : String(value ?? '') })
                    }
                    value={filters.day || 'any'}
                  >
                    <SelectTrigger className="max-sm:min-h-11" disabled={!options}>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectPopup>
                      <SelectItem className="max-sm:min-h-11" value="any">
                        Any day
                      </SelectItem>
                      {DAYS.map((day) => (
                        <SelectItem className="max-sm:min-h-11" key={day.key} value={day.key}>
                          {options?.weekdays?.[day.key] ?? day.label}
                        </SelectItem>
                      ))}
                    </SelectPopup>
                  </Select>
                </Field>
                </div>
              </div>

              <div className="flex flex-col gap-3 border-t p-4 sm:flex-row sm:flex-wrap sm:items-center sm:p-6">
                <Button className="max-sm:min-h-11" type="submit">
                  <Search aria-hidden /> Search gyms
                </Button>
                <Button
                  className="max-sm:min-h-11"
                  loading={locating}
                  onClick={useMyLocation}
                  type="button"
                  variant="outline"
                >
                  <LocateFixed aria-hidden /> Use my location
                </Button>

                <Popover onOpenChange={setAddressOpen} open={addressOpen}>
                  <PopoverTrigger render={<Button className="max-sm:min-h-11" type="button" variant="outline" />}>
                    <MapPin aria-hidden /> Find an address
                  </PopoverTrigger>
                  <PopoverPopup align="start" className="w-[min(22rem,calc(100vw-2rem))]" side="bottom">
                    <div className="flex flex-col gap-3">
                      <div className="flex flex-col gap-1">
                        <PopoverTitle className="text-base">Find an address</PopoverTitle>
                        <PopoverDescription>
                          Pick a result to put it on the map and sort gyms by distance.
                        </PopoverDescription>
                      </div>

                      <Form className="flex flex-col gap-2" onSubmit={findAddress}>
                        <Field name="address" className="w-full min-w-0">
                          <FieldLabel className="sr-only">Address</FieldLabel>
                          <Input
                            className="max-sm:h-11"
                            maxLength={120}
                            minLength={2}
                            onChange={(event) => setAddress(event.target.value)}
                            placeholder="Street, barangay, city"
                            required
                            value={address}
                          />
                          <FieldError />
                        </Field>
                        <Button className="max-sm:min-h-11 w-full" loading={geoLoading} type="submit" variant="outline">
                          Find place
                        </Button>
                      </Form>

                      {geoError && (
                        <Alert variant="error">
                          <AlertTitle>Address search failed</AlertTitle>
                          <AlertDescription>{geoError}</AlertDescription>
                        </Alert>
                      )}

                      {geoResults && (
                        <div className="overflow-hidden rounded-lg border">
                          {geoResults.length === 0 ? (
                            <p className="px-3 py-2 text-muted-foreground text-sm">
                              No places found. Try a wider address.
                            </p>
                          ) : (
                            <ul className="divide-y">
                              {geoResults.map((result) => (
                                <li key={`${result.lat}-${result.lng}-${result.label}`}>
                                  <button
                                    className="flex min-h-11 w-full flex-col items-start gap-0.5 px-3 py-2 text-left hover:bg-accent/50 focus-visible:bg-accent/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
                                    onClick={() => pickAddress(result)}
                                    type="button"
                                  >
                                    <span className="font-medium text-sm">{result.label}</span>
                                  </button>
                                </li>
                              ))}
                            </ul>
                          )}
                        </div>
                      )}
                      <p className="sr-only" role="status" aria-live="polite">
                        {geoResults
                          ? `${geoResults.length} ${geoResults.length === 1 ? 'place' : 'places'} found.`
                          : ''}
                      </p>
                    </div>
                  </PopoverPopup>
                </Popover>

                {point && (
                  <span className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-muted-foreground text-sm">
                    <MapPin aria-hidden className="size-4 shrink-0" />
                    <span className="line-clamp-1 max-w-64 min-w-0 font-medium text-foreground">{point.label}</span>
                    <Button aria-label="Clear saved location" onClick={clearLocation} size="xs" type="button" variant="ghost">
                      <X aria-hidden /> Clear
                    </Button>
                  </span>
                )}

              </div>

              <p className="sr-only" role="status" aria-live="polite">
                {locationNotice}
              </p>

              {locationError && (
                <div className="px-4 pb-4 sm:px-6 sm:pb-6">
                  <Alert variant="warning">
                    <AlertTitle>Location not available</AlertTitle>
                    <AlertDescription>{locationError}</AlertDescription>
                  </Alert>
                </div>
              )}
            </Form>

          </CardPanel>
        </Card>

        <div
          className={cn(
            'grid min-w-0 items-start gap-4 xl:gap-6',
            showMap && 'xl:grid-cols-[minmax(0,1fr)_minmax(18rem,24rem)]',
          )}
        >
          <section className="flex min-w-0 flex-col gap-3" aria-labelledby="gym-results">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h3 className="font-semibold text-base" id="gym-results">
                Results
              </h3>
              <div className="flex flex-wrap items-center gap-3">
                <p aria-live="polite" className="text-muted-foreground text-sm tabular-nums" role="status">
                  {searching ? 'Searching…' : results ? `${results.total} ${results.total === 1 ? 'gym' : 'gyms'}` : ''}
                </p>
                <div className="flex items-center gap-1.5">
                  <span className="text-muted-foreground text-xs" id="results-sort-label">
                    Sort
                  </span>
                  <Select items={SORTS} onValueChange={changeSort} value={filters.sort}>
                    <SelectTrigger
                      aria-labelledby="results-sort-label"
                      className="w-32 max-sm:min-h-11"
                    >
                      <SelectValue />
                    </SelectTrigger>
                    <SelectPopup>
                      {SORTS.map((sort) => (
                        <SelectItem
                          className="max-sm:min-h-11"
                          disabled={sort.value === 'distance' && !point}
                          key={sort.value}
                          value={sort.value}
                        >
                          {sort.label}
                        </SelectItem>
                      ))}
                    </SelectPopup>
                  </Select>
                </div>
              </div>
            </div>

            {searchError && (
              <Alert variant="error">
                <AlertTitle>Gyms could not be loaded</AlertTitle>
                <AlertDescription>
                  <p>{searchError}</p>
                  <Button size="sm" variant="outline" onClick={() => setSearchVersion((version) => version + 1)}>
                    Try again
                  </Button>
                </AlertDescription>
              </Alert>
            )}

            {searching ? (
              <div aria-hidden="true" className="grid gap-3">
                {[0, 1, 2].map((key) => (
                  <div className="rounded-2xl border bg-card p-4" key={key}>
                    <div className="flex flex-col gap-2.5">
                      <div className="flex items-start justify-between gap-2">
                        <Skeleton className="h-5 w-40 max-w-[60%]" />
                        <Skeleton className="h-5 w-16 rounded-full" />
                      </div>
                      <Skeleton className="h-4 w-56 max-w-[70%]" />
                      <Skeleton className="h-5 w-36" />
                      <Skeleton className="h-4 w-1/2" />
                    </div>
                  </div>
                ))}
              </div>
            ) : results && results.gyms.length === 0 ? (
              <Empty className="rounded-2xl border bg-card px-4 py-10">
                <EmptyHeader>
                  <EmptyTitle className="text-lg">No gyms match those filters</EmptyTitle>
                  <EmptyDescription>
                    Try a wider place, another sport, or clear a filter and search again.
                  </EmptyDescription>
                </EmptyHeader>
                {activeFilterCount > 0 && (
                  <EmptyContent>
                    <Button onClick={clearFilters} size="sm" variant="outline">
                      Clear filters ({activeFilterCount})
                    </Button>
                  </EmptyContent>
                )}
              </Empty>
            ) : (
              <ul className={`grid min-w-0 gap-3${showMap ? '' : ' sm:grid-cols-2'}`}>
                {(results?.gyms ?? []).map((gym) => {
                  const services = serviceNames(gym)
                  return (
                    <li className="min-w-0" key={gym.id}>
                      <Card
                        className="gap-3 p-4 transition-colors hover:bg-accent/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring active:bg-accent/40"
                        render={<Link to={`/gyms/${gym.id}`} />}
                      >
                        {/* (R-134) */}
                        {gym.cover_photo_url ? (
                          <img className="aspect-video w-full rounded-lg object-cover" loading="lazy" alt="" src={gym.cover_photo_url} />
                        ) : (
                          <div className="flex aspect-video w-full items-center justify-center rounded-lg bg-muted text-muted-foreground">
                            <Dumbbell aria-hidden className="size-7" />
                          </div>
                        )}
                        <div className="flex items-start justify-between gap-2">
                          <span className="min-w-0 font-semibold">{gym.name}</span>
                          <span className="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                            {gym.distance_km != null && <Badge variant="outline">{distanceLabel(gym.distance_km)}</Badge>}
                            <Badge className="max-w-full whitespace-normal" variant={gym.tier === 2 ? 'success' : 'info'}>
                              {gym.badge || `Tier ${gym.tier}`}
                            </Badge>
                          </span>
                        </div>
                        <span className="flex items-center gap-1.5 text-muted-foreground text-sm">
                          <MapPin aria-hidden className="size-4 shrink-0" />
                          {[gym.city, gym.province].filter(Boolean).join(', ') || 'Location not listed'}
                        </span>
                        {(gym.disciplines ?? []).length > 0 && (
                          <span className="flex flex-wrap gap-1.5">
                            {(gym.disciplines ?? []).map((discipline) => (
                              <Badge className="max-w-full whitespace-normal" key={discipline} variant="outline">
                                {disciplineLabel(discipline)}
                              </Badge>
                            ))}
                          </span>
                        )}
                        {services.length > 0 && (
                          <p className="line-clamp-2 text-muted-foreground text-sm">Services: {services.join(', ')}</p>
                        )}
                        {gym.min_price != null && gym.min_price !== '' && (
                          <p className="text-sm">
                            From <span className="font-medium tabular-nums">{money(gym.min_price)}</span>
                          </p>
                        )}
                      </Card>
                    </li>
                  )
                })}
              </ul>
            )}
          </section>

          {showMap && (
            <section
              className="flex min-w-0 flex-col gap-3 xl:sticky xl:top-20 xl:self-start"
              aria-labelledby="gym-map"
            >
              <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h3 className="font-semibold text-base" id="gym-map">
                  Map
                </h3>
                <span className="text-muted-foreground text-sm tabular-nums">{mapGyms.length} mapped</span>
              </div>
              {searching && !results ? (
                <Skeleton
                  aria-hidden="true"
                  className="h-72 rounded-2xl sm:h-96 xl:h-[clamp(20rem,calc(100dvh-8rem),34rem)]"
                />
              ) : mapGyms.length === 0 && !point ? (
                <Empty className="min-h-56 rounded-2xl border bg-card px-4 py-8">
                  <EmptyHeader>
                    <EmptyTitle className="text-lg">No map pins yet</EmptyTitle>
                    <EmptyDescription>
                      None of these results has a map location. Find an address to set your search point.
                    </EmptyDescription>
                  </EmptyHeader>
                </Empty>
              ) : (
                <Card
                  aria-busy={searching}
                  className={cn('overflow-hidden transition-opacity', searching && 'pointer-events-none opacity-60')}
                >
                  <GymMap
                    className="h-72 rounded-none border-0 sm:h-96 xl:h-[clamp(20rem,calc(100dvh-8rem),34rem)]"
                    gyms={mapGyms}
                    person={point ? { lat: point.lat, lng: point.lng, label: point.label } : null}
                  />
                </Card>
              )}
            </section>
          )}
        </div>
      </section>

      {isMember && (
        <section className="flex flex-col gap-4" aria-labelledby="best-matches">
        <div className="flex flex-wrap items-baseline gap-2">
          <h2 id="best-matches" className="font-semibold text-xl tracking-tight">Best matches</h2>
          <span className="text-muted-foreground text-sm">Gyms ranked by your sport, price range and distance.</span>
        </div>
        <p className="sr-only" role="status" aria-live="polite">
          {matchesLoading
            ? 'Loading best matches.'
            : recommendations
              ? `${recommendations.length} best ${recommendations.length === 1 ? 'match' : 'matches'}.`
              : ''}
        </p>

        <Card>
          <CardHeader className="max-sm:grid-cols-1!">
            <CardTitle render={<h3 />}>What you want</CardTitle>
            <CardDescription>Saved to your account. Preferred times count once gyms publish session times.</CardDescription>
            <CardAction className="max-sm:col-start-1 max-sm:row-span-1 max-sm:row-start-3 max-sm:mt-2 max-sm:justify-self-stretch">
              <Button
                className="max-sm:min-h-11 max-sm:w-full"
                loading={matchesLoading}
                onClick={findMatches}
                size="sm"
                variant="outline"
              >
                Find best matches
              </Button>
            </CardAction>
          </CardHeader>
          <CardPanel className="flex flex-col gap-5">
            {preferencesLoadError && !preferencesLoading && (
              <Alert variant="error">
                <AlertTitle>Preferences could not be loaded</AlertTitle>
                <AlertDescription>
                  <p>{preferencesLoadError}</p>
                  <Button size="sm" variant="outline" onClick={() => setPreferencesVersion((version) => version + 1)}>
                    Try again
                  </Button>
                </AlertDescription>
              </Alert>
            )}

            {preferencesLoading ? (
              <div aria-hidden="true" className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Skeleton className="h-16" />
                <Skeleton className="h-16" />
                <Skeleton className="h-16" />
              </div>
            ) : preferencesLoadError ? null : (
              <Form className="flex flex-col gap-4" onSubmit={savePreferences}>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                  <Field name="discipline" className="w-full">
                    <FieldLabel>Combat sport</FieldLabel>
                    <Select
                      value={preferenceForm.discipline || 'any'}
                      onValueChange={(value) =>
                        setPreferenceForm({
                          ...preferenceForm,
                          discipline: String(value ?? '') === 'any' ? '' : String(value ?? ''),
                        })
                      }
                      items={[
                        { value: 'any', label: 'Any sport' },
                        ...Object.entries(options?.disciplines ?? {}).map(([value, label]) => ({ value, label })),
                      ]}
                    >
                      <SelectTrigger className="max-sm:min-h-11">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectPopup>
                        <SelectItem className="max-sm:min-h-11" value="any">
                          Any sport
                        </SelectItem>
                        {Object.entries(options?.disciplines ?? {}).map(([value, label]) => (
                          <SelectItem className="max-sm:min-h-11" key={value} value={value}>
                            {label}
                          </SelectItem>
                        ))}
                      </SelectPopup>
                    </Select>
                  </Field>
                  <Field name="min_price" className="w-full">
                    <FieldLabel>Lowest price (₱)</FieldLabel>
                    <Input
                      className="max-sm:h-11"
                      inputMode="decimal"
                      min="0"
                      onChange={(event) => setPreferenceForm({ ...preferenceForm, min_price: event.target.value })}
                      placeholder="No minimum"
                      step="0.01"
                      type="number"
                      value={preferenceForm.min_price}
                    />
                    <FieldError />
                  </Field>
                  <Field
                    name="max_price"
                    className="w-full"
                    validate={(value, values) =>
                      value && values.min_price && Number(value) < Number(values.min_price)
                        ? 'Must be at least the lowest price.'
                        : null
                    }
                  >
                    <FieldLabel>Highest price (₱)</FieldLabel>
                    <Input
                      className="max-sm:h-11"
                      inputMode="decimal"
                      min="0"
                      onChange={(event) => setPreferenceForm({ ...preferenceForm, max_price: event.target.value })}
                      placeholder="No maximum"
                      step="0.01"
                      type="number"
                      value={preferenceForm.max_price}
                    />
                    <FieldError />
                  </Field>
                </div>

                <Field name="preferred_times" className="w-full">
                  <FieldLabel>Preferred training times</FieldLabel>
                  <CheckboxGroup
                    value={preferenceForm.preferred_times}
                    onValueChange={(value) =>
                      setPreferenceForm({ ...preferenceForm, preferred_times: value as PreferredTime[] })
                    }
                    className="grid w-full gap-2 sm:grid-cols-3"
                  >
                    {timeOptions.map((time) => {
                      const selected = preferenceForm.preferred_times.includes(time.value as PreferredTime)
                      const Icon = TIME_ICONS[time.value] ?? Sun
                      return (
                        <label
                          className={cn(
                            'group flex min-h-16 cursor-pointer items-center gap-3 rounded-xl border p-3 transition-colors focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2 focus-within:ring-offset-background',
                            selected
                              ? 'border-primary bg-primary/8'
                              : 'border-border bg-background hover:bg-accent/40',
                          )}
                          key={time.value}
                        >
                          <span
                            className={cn(
                              'flex size-9 shrink-0 items-center justify-center rounded-lg border transition-colors',
                              selected
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-muted/40 text-muted-foreground',
                            )}
                          >
                            <Icon aria-hidden className="size-4" />
                          </span>
                          <span className="min-w-0 flex-1 font-medium text-sm">{time.label}</span>
                          <Checkbox
                            aria-label={time.label}
                            className="size-5 focus-visible:ring-0 focus-visible:ring-offset-0"
                            value={time.value}
                          />
                        </label>
                      )
                    })}
                  </CheckboxGroup>
                  <FieldDescription>Leave all unchecked when any time works.</FieldDescription>
                </Field>

                {preferencesSaveError && (
                  <Alert variant="error">
                    <AlertTitle>Preferences could not be saved</AlertTitle>
                    <AlertDescription>{preferencesSaveError}</AlertDescription>
                  </Alert>
                )}

                <div className="flex flex-col items-start gap-2">
                  <Button className="max-sm:min-h-11" loading={savingPreferences} type="submit">
                    Save preferences
                  </Button>
                  <span className="text-muted-foreground text-sm">Saving also refreshes your best matches.</span>
                </div>
              </Form>
            )}

            {matchesError && (
              <Alert variant="error">
                <AlertTitle>Best matches could not be loaded</AlertTitle>
                <AlertDescription>
                  <p>{matchesError}</p>
                  <Button size="sm" variant="outline" onClick={findMatches}>
                    Try again
                  </Button>
                </AlertDescription>
              </Alert>
            )}

            {matchesLoading && (
              <div aria-hidden="true" className="grid gap-3 lg:grid-cols-2">
                <Skeleton className="h-32" />
                <Skeleton className="h-32" />
              </div>
            )}

            {!matchesLoading && recommendations && recommendations.length === 0 && (
              <Empty className="rounded-xl border">
                <EmptyHeader>
                  <EmptyTitle>No matches yet</EmptyTitle>
                  <EmptyDescription>Set a sport, price range or preferred time, then try again.</EmptyDescription>
                </EmptyHeader>
              </Empty>
            )}

            {!matchesLoading && recommendations && recommendations.length > 0 && (
              <ul className="grid min-w-0 gap-3 lg:grid-cols-2">
                {recommendations.map((recommendation) => {
                  const gym = recommendation.gym
                  const away = recommendation.distance_km ?? gym.distance_km
                  return (
                    <li key={gym.id} className="min-w-0">
                      <Card
                        render={<Link to={`/gyms/${gym.id}`} />}
                        className="h-full gap-3 p-4 transition-colors hover:bg-accent/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring active:bg-accent/40"
                      >
                        <div className="flex items-start justify-between gap-2">
                          <span className="min-w-0 font-semibold">{gym.name}</span>
                          <Badge variant="success">{Math.round(recommendation.score)}% match</Badge>
                        </div>
                        <span className="flex items-center gap-1.5 text-muted-foreground text-sm">
                          <MapPin className="size-4" aria-hidden />
                          {[gym.city, gym.province].filter(Boolean).join(', ') || 'Location not listed'}
                          {away != null ? ` · ${distanceLabel(away)}` : ''}
                        </span>
                        {recommendation.reasons.length > 0 && (
                          <span className="flex flex-wrap gap-1.5">
                            {recommendation.reasons.map((reason) => (
                              <Badge className="max-w-full whitespace-normal" key={reason} variant="outline">
                                {reason}
                              </Badge>
                            ))}
                          </span>
                        )}
                      </Card>
                    </li>
                  )
                })}
              </ul>
            )}
          </CardPanel>
        </Card>
        </section>
      )}

      {!showGymsFirst && user && user.gyms.length > 0 && (
        <YourGymsSection gyms={user.gyms} workspaceGymId={workspaceGym?.id} />
      )}
    </div>
  )
}
