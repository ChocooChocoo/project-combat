import { FilePlus2, LocateFixed, MapPin, Search, X } from 'lucide-react'
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
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError } from '@/lib/api'
import { useAuth } from '@/lib/auth'
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

/** S-09, R-30, R-97 and R-98: find a gym by filters, distance and best match. */
export default function Home() {
  const { user } = useAuth()

  const [options, setOptions] = useState<DiscoveryOptions | null>(null)
  const [optionsError, setOptionsError] = useState('')
  const [optionsVersion, setOptionsVersion] = useState(0)

  const [filters, setFilters] = useState<SearchFilters>(EMPTY_FILTERS)
  const [applied, setApplied] = useState<AppliedSearch>({ filters: EMPTY_FILTERS, point: null })
  const [searchVersion, setSearchVersion] = useState(0)
  const [results, setResults] = useState<SearchResult | null>(null)
  const [searching, setSearching] = useState(true)
  const [searchError, setSearchError] = useState('')

  const [point, setPoint] = useState<Point | null>(null)
  const [locating, setLocating] = useState(false)
  const [locationError, setLocationError] = useState('')

  const [address, setAddress] = useState('')
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
        if (live) setSearchError(error instanceof ApiError ? error.message : 'Gyms could not be loaded.')
      })
      .finally(() => {
        if (live) setSearching(false)
      })

    return () => {
      live = false
    }
  }, [applied, searchVersion])

  useEffect(() => {
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
  }, [preferencesVersion])

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
  }

  function search(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setApplied({ filters: { ...filters }, point })
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
  }

  async function findAddress(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
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

  return (
    <div className="flex flex-col gap-10">
      <PageHeader
        title={`Welcome, ${user?.first_name ?? ''}`}
        description={user?.is_platform_admin ? 'Gyms live on the platform today.' : 'Find a gym, or put your own on the platform.'}
        actions={
          !user?.is_platform_admin && (
            <Button render={<Link to="/apply" />}>
              <FilePlus2 aria-hidden /> Apply to run a gym
            </Button>
          )
        }
      />

      {user && user.gyms.length > 0 && (
        <section className="flex flex-col gap-3" aria-labelledby="your-gyms">
          <h2 id="your-gyms" className="font-semibold text-xl tracking-tight">Your gyms</h2>
          <ul className="flex flex-col divide-y rounded-xl border">
            {user.gyms.map((gym) => {
              const approved = gym.status === 'approved'
              return (
                <li key={gym.id} className="flex flex-wrap items-center gap-2 px-4 py-3">
                  <span className="mr-auto font-medium">
                    {approved ? (
                      <Link className="underline-offset-4 hover:underline" to={`/gyms/${gym.id}`}>
                        {gym.name}
                      </Link>
                    ) : (
                      gym.name
                    )}
                  </span>
                  {gym.roles.map((role) => (
                    <Badge key={role} variant="outline" className="capitalize">{role}</Badge>
                  ))}
                  {approved && canOpenWorkspace(gym) && (
                    <Button size="sm" variant="outline" render={<Link to={`/gyms/${gym.id}/manage`} />}>
                      Open workspace
                    </Button>
                  )}
                  {approved && gym.roles.includes('member') && (
                    <Button size="sm" variant="outline" render={<Link to={`/gyms/${gym.id}/my-record`} />}>
                      My record
                    </Button>
                  )}
                </li>
              )
            })}
          </ul>
        </section>
      )}

      <section className="flex flex-col gap-4" aria-labelledby="find-gym">
        <div className="flex flex-wrap items-baseline gap-2">
          <h2 id="find-gym" className="font-semibold text-xl tracking-tight">Find a gym</h2>
          <span className="text-muted-foreground text-sm">Search by place, sport, service, tier or opening day.</span>
        </div>

        <Card>
          <CardPanel className="flex flex-col gap-5">
            <Form className="flex flex-col gap-4" onSubmit={search}>
              <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Field name="q" className="w-full sm:col-span-2 xl:col-span-2">
                  <FieldLabel>Search</FieldLabel>
                  <Input
                    type="search"
                    maxLength={120}
                    value={filters.q}
                    onChange={(event) => setFilters({ ...filters, q: event.target.value })}
                    placeholder="Gym name or service"
                  />
                  <FieldError />
                </Field>
                <Field name="location" className="w-full">
                  <FieldLabel>City or province</FieldLabel>
                  <Input
                    maxLength={120}
                    value={filters.location}
                    onChange={(event) => setFilters({ ...filters, location: event.target.value })}
                    placeholder="City, or city, province"
                  />
                  <FieldDescription>Add a comma to give the city and province together.</FieldDescription>
                  <FieldError />
                </Field>
                <Field name="discipline" className="w-full">
                  <FieldLabel>Combat sport</FieldLabel>
                  <Select
                    value={filters.discipline || 'any'}
                    onValueChange={(value) =>
                      setFilters({ ...filters, discipline: String(value ?? '') === 'any' ? '' : String(value ?? '') })
                    }
                    items={[
                      { value: 'any', label: 'Any sport' },
                      ...Object.entries(options?.disciplines ?? {}).map(([value, label]) => ({ value, label })),
                    ]}
                  >
                    <SelectTrigger disabled={!options}>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectPopup>
                      <SelectItem value="any">Any sport</SelectItem>
                      {Object.entries(options?.disciplines ?? {}).map(([value, label]) => (
                        <SelectItem key={value} value={value}>
                          {label}
                        </SelectItem>
                      ))}
                    </SelectPopup>
                  </Select>
                </Field>
                <Field name="service" className="w-full">
                  <FieldLabel>Service</FieldLabel>
                  <Input
                    maxLength={100}
                    value={filters.service}
                    onChange={(event) => setFilters({ ...filters, service: event.target.value })}
                    placeholder="For example, sparring"
                  />
                  <FieldError />
                </Field>
                <Field name="tier" className="w-full">
                  <FieldLabel>Tier</FieldLabel>
                  <Select
                    value={filters.tier || 'any'}
                    onValueChange={(value) => setFilters({ ...filters, tier: String(value ?? '') === 'any' ? '' : String(value ?? '') })}
                    items={[{ value: 'any', label: 'Any tier' }, ...tiers.map((tier) => ({ value: tier.value, label: tier.label }))]}
                  >
                    <SelectTrigger disabled={!options}>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectPopup>
                      <SelectItem value="any">Any tier</SelectItem>
                      {tiers.map((tier) => (
                        <SelectItem key={tier.value} value={tier.value}>
                          {tier.label}
                        </SelectItem>
                      ))}
                    </SelectPopup>
                  </Select>
                </Field>
                <Field name="day" className="w-full">
                  <FieldLabel>Open on</FieldLabel>
                  <Select
                    value={filters.day || 'any'}
                    onValueChange={(value) => setFilters({ ...filters, day: String(value ?? '') === 'any' ? '' : String(value ?? '') })}
                    items={[
                      { value: 'any', label: 'Any day' },
                      ...DAYS.map((day) => ({ value: day.key, label: options?.weekdays?.[day.key] ?? day.label })),
                    ]}
                  >
                    <SelectTrigger disabled={!options}>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectPopup>
                      <SelectItem value="any">Any day</SelectItem>
                      {DAYS.map((day) => (
                        <SelectItem key={day.key} value={day.key}>
                          {options?.weekdays?.[day.key] ?? day.label}
                        </SelectItem>
                      ))}
                    </SelectPopup>
                  </Select>
                </Field>
                <Field name="sort" className="w-full">
                  <FieldLabel>Sort by</FieldLabel>
                  <Select
                    value={filters.sort}
                    onValueChange={(value) => {
                      const sort = String(value ?? 'name') === 'distance' ? 'distance' : 'name'
                      setFilters({ ...filters, sort })
                    }}
                    items={SORTS}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectPopup>
                      {SORTS.map((sort) => (
                        <SelectItem key={sort.value} value={sort.value}>
                          {sort.label}
                        </SelectItem>
                      ))}
                    </SelectPopup>
                  </Select>
                </Field>
              </div>

              <div className="flex flex-wrap items-center gap-2">
                <Button type="submit">
                  <Search aria-hidden /> Search gyms
                </Button>
                <Button type="button" variant="outline" loading={locating} onClick={useMyLocation}>
                  <LocateFixed aria-hidden /> Use my location
                </Button>
                {point ? (
                  <span className="flex flex-wrap items-center gap-2 text-muted-foreground text-sm">
                    <MapPin className="size-4" aria-hidden />
                    <span>{point.label}</span>
                    <span className="font-mono tabular-nums">
                      {point.lat.toFixed(4)}, {point.lng.toFixed(4)}
                    </span>
                    <Button type="button" size="xs" variant="ghost" onClick={clearLocation}>
                      <X aria-hidden /> Clear
                    </Button>
                  </span>
                ) : (
                  <span className="text-muted-foreground text-sm">Location not set. Distance sort needs a location.</span>
                )}
              </div>
              {locationError && (
                <Alert variant="warning">
                  <AlertTitle>Location not available</AlertTitle>
                  <AlertDescription>{locationError}</AlertDescription>
                </Alert>
              )}
            </Form>

            <Form className="flex flex-col gap-2 border-t pt-5" onSubmit={findAddress}>
              <Field name="address" className="w-full">
                <FieldLabel>Or find an address</FieldLabel>
                <div className="flex flex-col gap-2 sm:flex-row">
                  <Input
                    required
                    minLength={2}
                    maxLength={120}
                    value={address}
                    onChange={(event) => setAddress(event.target.value)}
                    placeholder="Street, barangay, city or landmark"
                  />
                  <Button type="submit" variant="outline" loading={geoLoading} className="sm:w-auto">
                    Find place
                  </Button>
                </div>
                <FieldDescription>Pick a result to put it on the map and sort gyms by distance from it.</FieldDescription>
                <FieldError />
              </Field>
              {geoError && (
                <Alert variant="error">
                  <AlertTitle>Address search failed</AlertTitle>
                  <AlertDescription>{geoError}</AlertDescription>
                </Alert>
              )}
              {geoResults && (
                <div className="rounded-lg border">
                  {geoResults.length === 0 ? (
                    <p className="px-3 py-2 text-muted-foreground text-sm">No places found. Try a wider address.</p>
                  ) : (
                    <ul className="divide-y">
                      {geoResults.map((result) => (
                        <li key={`${result.lat}-${result.lng}-${result.label}`}>
                          <button
                            type="button"
                            className="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left hover:bg-accent/50 focus-visible:bg-accent/50 focus-visible:outline-none"
                            onClick={() => pickAddress(result)}
                          >
                            <span className="font-medium text-sm">{result.label}</span>
                            <span className="text-muted-foreground text-xs tabular-nums">
                              {result.lat.toFixed(4)}, {result.lng.toFixed(4)}
                            </span>
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              )}
            </Form>

            {optionsError && (
              <Alert variant="warning">
                <AlertTitle>Filter lists could not be loaded</AlertTitle>
                <AlertDescription>
                  <p>You can still search by text. The sport, tier and day lists need another try.</p>
                  <Button size="sm" variant="outline" onClick={() => setOptionsVersion((version) => version + 1)}>
                    Try again
                  </Button>
                </AlertDescription>
              </Alert>
            )}
          </CardPanel>
        </Card>
      </section>

      <div className="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,26rem)]">
        <section className="flex min-w-0 flex-col gap-3" aria-labelledby="gym-results">
          <div className="flex flex-wrap items-baseline gap-2" aria-live="polite">
            <h3 id="gym-results" className="font-semibold">Results</h3>
            {searching ? (
              <Skeleton className="h-4 w-16" aria-label="Searching" />
            ) : (
              results && (
                <span className="text-muted-foreground text-sm tabular-nums">
                  {results.total} {results.total === 1 ? 'gym' : 'gyms'}
                </span>
              )
            )}
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
            <div className="grid gap-3" aria-busy="true">
              <Skeleton className="h-36" />
              <Skeleton className="h-36" />
              <Skeleton className="h-36" />
            </div>
          ) : results && results.gyms.length === 0 ? (
            <Empty className="rounded-xl border">
              <EmptyHeader>
                <EmptyTitle>No gyms match those filters</EmptyTitle>
                <EmptyDescription>Try a wider place, another sport, or clear a filter and search again.</EmptyDescription>
              </EmptyHeader>
            </Empty>
          ) : (
            <ul className="grid min-w-0 gap-3">
              {(results?.gyms ?? []).map((gym) => {
                const services = serviceNames(gym)
                return (
                  <li key={gym.id} className="min-w-0">
                    <Card
                      render={<Link to={`/gyms/${gym.id}`} />}
                      className="gap-3 p-4 transition-colors hover:bg-accent/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                      <div className="flex flex-wrap items-start gap-2">
                        <span className="mr-auto font-semibold">{gym.name}</span>
                        {gym.distance_km != null && <Badge variant="outline">{distanceLabel(gym.distance_km)}</Badge>}
                        <Badge variant={gym.tier === 2 ? 'success' : 'info'}>{gym.badge || `Tier ${gym.tier}`}</Badge>
                      </div>
                      <span className="flex items-center gap-1.5 text-muted-foreground text-sm">
                        <MapPin className="size-4" aria-hidden />
                        {[gym.city, gym.province].filter(Boolean).join(', ') || 'Location not listed'}
                      </span>
                      {(gym.disciplines ?? []).length > 0 && (
                        <span className="flex flex-wrap gap-1.5">
                          {(gym.disciplines ?? []).map((discipline) => (
                            <Badge key={discipline} variant="outline">
                              {disciplineLabel(discipline)}
                            </Badge>
                          ))}
                        </span>
                      )}
                      {services.length > 0 && (
                        <p className="text-muted-foreground text-sm">Services: {services.join(', ')}</p>
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

        <section className="flex min-w-0 flex-col gap-3 lg:sticky lg:top-20 lg:self-start" aria-labelledby="gym-map">
          <div className="flex flex-wrap items-baseline gap-2">
            <h3 id="gym-map" className="font-semibold">Map</h3>
            <span className="text-muted-foreground text-sm tabular-nums">{mapGyms.length} with a pin</span>
          </div>
          {mapGyms.length === 0 && !point ? (
            <Empty className="min-h-72 rounded-xl border bg-card">
              <EmptyHeader>
                <EmptyTitle>No map pins yet</EmptyTitle>
                <EmptyDescription>No result has a map location. Pick an address to see where you are searching from.</EmptyDescription>
              </EmptyHeader>
            </Empty>
          ) : (
            <GymMap gyms={mapGyms} person={point ? { lat: point.lat, lng: point.lng, label: point.label } : null} />
          )}
        </section>
      </div>

      <section className="flex flex-col gap-4" aria-labelledby="best-matches">
        <div className="flex flex-wrap items-baseline gap-2">
          <h2 id="best-matches" className="font-semibold text-xl tracking-tight">Best matches</h2>
          <span className="text-muted-foreground text-sm">Gyms ranked by your sport, price range and distance.</span>
        </div>

        <Card>
          <CardHeader className="max-sm:grid-cols-1!">
            <CardTitle render={<h3 />}>What you want</CardTitle>
            <CardDescription>Saved to your account. Preferred times count once gyms publish session times.</CardDescription>
            <CardAction className="max-sm:col-start-1 max-sm:row-span-1 max-sm:row-start-3 max-sm:mt-2 max-sm:justify-self-stretch">
              <Button className="max-sm:w-full" size="sm" variant="outline" loading={matchesLoading} onClick={findMatches}>
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
              <div className="grid gap-3 sm:grid-cols-3" aria-busy="true">
                <Skeleton className="h-16" />
                <Skeleton className="h-16" />
                <Skeleton className="h-16" />
              </div>
            ) : (
              <Form className="flex flex-col gap-4" onSubmit={savePreferences}>
                <div className="grid gap-4 sm:grid-cols-3">
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
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectPopup>
                        <SelectItem value="any">Any sport</SelectItem>
                        {Object.entries(options?.disciplines ?? {}).map(([value, label]) => (
                          <SelectItem key={value} value={value}>
                            {label}
                          </SelectItem>
                        ))}
                      </SelectPopup>
                    </Select>
                  </Field>
                  <Field name="min_price" className="w-full">
                    <FieldLabel>Lowest price</FieldLabel>
                    <Input
                      type="number"
                      min="0"
                      step="0.01"
                      inputMode="decimal"
                      value={preferenceForm.min_price}
                      onChange={(event) => setPreferenceForm({ ...preferenceForm, min_price: event.target.value })}
                      placeholder="No minimum"
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
                    <FieldLabel>Highest price</FieldLabel>
                    <Input
                      type="number"
                      min="0"
                      step="0.01"
                      inputMode="decimal"
                      value={preferenceForm.max_price}
                      onChange={(event) => setPreferenceForm({ ...preferenceForm, max_price: event.target.value })}
                      placeholder="No maximum"
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
                    className="flex flex-wrap gap-x-6 gap-y-3"
                  >
                    {timeOptions.map((time) => (
                      <label key={time.value} className="flex items-center gap-2 text-sm">
                        <Checkbox value={time.value} />
                        {time.label}
                      </label>
                    ))}
                  </CheckboxGroup>
                  <FieldDescription>Leave all unchecked when any time works.</FieldDescription>
                </Field>

                {preferencesSaveError && (
                  <Alert variant="error">
                    <AlertTitle>Preferences could not be saved</AlertTitle>
                    <AlertDescription>{preferencesSaveError}</AlertDescription>
                  </Alert>
                )}

                <div className="flex flex-wrap items-center gap-2">
                  <Button type="submit" loading={savingPreferences}>
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
              <div className="grid gap-3 sm:grid-cols-2" aria-busy="true">
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
              <ul className="grid min-w-0 gap-3 sm:grid-cols-2">
                {recommendations.map((recommendation) => {
                  const gym = recommendation.gym
                  const away = recommendation.distance_km ?? gym.distance_km
                  return (
                    <li key={gym.id} className="min-w-0">
                      <Card
                        render={<Link to={`/gyms/${gym.id}`} />}
                        className="h-full gap-3 p-4 transition-colors hover:bg-accent/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                      >
                        <div className="flex flex-wrap items-start gap-2">
                          <span className="mr-auto font-semibold">{gym.name}</span>
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
                              <Badge key={reason} variant="outline">
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
    </div>
  )
}
