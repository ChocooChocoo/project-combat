import L from 'leaflet'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'
import { useEffect } from 'react'
import { CircleMarker, MapContainer, Marker, Popup, TileLayer, useMap } from 'react-leaflet'
import { Link } from 'react-router'
import { cn } from '@/lib/utils'

/** The few gym fields the discovery map needs. */
export type GymMapGym = {
  id: number
  name: string
  city: string | null
  province: string | null
  latitude: number
  longitude: number
  distance_km?: number | null
}

export type GymMapPerson = {
  lat: number
  lng: number
  label?: string
}

// Bundlers break Leaflet's default pin image paths, so the pin is set explicitly.
const pin = L.icon({
  iconUrl: markerIcon,
  iconRetinaUrl: markerIcon2x,
  shadowUrl: markerShadow,
  iconSize: [25, 41],
  iconAnchor: [12, 41],
})

const MANILA: [number, number] = [14.5995, 120.9842]

/** Keeps every pin and the person's location in view without resetting the map on unrelated renders. */
function FitBounds({ boundsKey }: { boundsKey: string }) {
  const map = useMap()

  useEffect(() => {
    if (!boundsKey) return
    const coordinates = boundsKey.split('|').map((pair) => pair.split(',').map(Number) as [number, number])

    if (coordinates.length === 1) {
      map.setView(coordinates[0], 13)
      return
    }

    map.fitBounds(L.latLngBounds(coordinates), { padding: [32, 32], maxZoom: 14 })
  }, [boundsKey, map])

  return null
}

/** R-97: approved gyms on a map, plus where the person is looking from. */
export function GymMap({
  gyms,
  person,
  className,
}: {
  gyms: GymMapGym[]
  person: GymMapPerson | null
  className?: string
}) {
  const points: [number, number][] = [
    ...gyms.map((gym) => [gym.latitude, gym.longitude] as [number, number]),
    ...(person ? ([[person.lat, person.lng]] as [number, number][]) : []),
  ]
  const boundsKey = points.map((point) => point.join(',')).join('|')
  const start = points[0] ?? MANILA

  return (
    <div className={cn('isolate h-72 overflow-hidden rounded-xl border sm:h-96', className)} role="region" aria-label="Gyms on a map">
      <MapContainer center={start} zoom={points.length > 1 ? 10 : 13} className="size-full">
        <TileLayer
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
          url="https://tile.openstreetmap.org/{z}/{x}/{y}.png"
        />
        <FitBounds boundsKey={boundsKey} />
        {gyms.map((gym) => (
          <Marker key={gym.id} icon={pin} position={[gym.latitude, gym.longitude]}>
            <Popup>
              <div className="flex flex-col gap-1">
                <span className="font-semibold">{gym.name}</span>
                <span className="text-muted-foreground text-xs">
                  {[gym.city, gym.province].filter(Boolean).join(', ') || 'Location set'}
                  {gym.distance_km != null ? ` · ${distanceLabel(gym.distance_km)}` : ''}
                </span>
                <Link className="text-primary text-xs underline underline-offset-4" to={`/gyms/${gym.id}`}>
                  Open gym page
                </Link>
              </div>
            </Popup>
          </Marker>
        ))}
        {person && (
          <CircleMarker
            center={[person.lat, person.lng]}
            radius={8}
            pathOptions={{ color: '#1d4ed8', fillColor: '#1d4ed8', fillOpacity: 0.9, weight: 2 }}
          >
            <Popup>
              <div className="flex flex-col gap-1">
                <span className="font-semibold">{person.label ?? 'Your location'}</span>
                <span className="text-muted-foreground text-xs tabular-nums">
                  {person.lat.toFixed(4)}, {person.lng.toFixed(4)}
                </span>
              </div>
            </Popup>
          </CircleMarker>
        )}
      </MapContainer>
    </div>
  )
}

export function distanceLabel(km: number): string {
  const value = Number(km)
  if (!Number.isFinite(value)) return ''
  if (value < 1) return `${Math.max(50, Math.round((value * 1000) / 50) * 50)} m away`
  return `${value.toFixed(value < 10 ? 1 : 0)} km away`
}
