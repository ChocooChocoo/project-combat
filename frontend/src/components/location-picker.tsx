import L from 'leaflet'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'
import { useState } from 'react'
import { MapContainer, Marker, TileLayer, useMapEvents } from 'react-leaflet'
import { Button } from '@/components/ui/button'

export type LatLng = { lat: number; lng: number }

// Bundlers break Leaflet's default pin image paths, so the pin is set explicitly.
const pin = L.icon({
  iconUrl: markerIcon,
  iconRetinaUrl: markerIcon2x,
  shadowUrl: markerShadow,
  iconSize: [25, 41],
  iconAnchor: [12, 41],
})

const MANILA: LatLng = { lat: 14.3294, lng: 120.9367 } // Dasmariñas, Cavite (system scope)

function ClickToPlace({ onChange }: { onChange: (value: LatLng) => void }) {
  useMapEvents({ click: (event) => onChange(event.latlng) })
  return null
}

/** R-11: set the gym's location on a map (OpenStreetMap). Click or drag the pin, or type the numbers. */
export function LocationPicker({ value, onChange }: { value: LatLng | null; onChange: (value: LatLng) => void }) {
  const [map, setMap] = useState<L.Map | null>(null)
  const [locating, setLocating] = useState(false)
  const [error, setError] = useState('')

  const place = (next: LatLng) => {
    onChange(next)
    map?.setView(next, Math.max(map.getZoom(), 16))
  }

  const useMyLocation = () => {
    setError('')
    if (!('geolocation' in navigator)) {
      setError('This browser cannot share your location. Click the map instead.')
      return
    }
    setLocating(true)
    // The browser's own timeout does not count time spent on its permission prompt, so guard that too.
    let settled = false
    const guard = setTimeout(() => {
      if (settled) return
      settled = true
      setLocating(false)
      setError('No answer from your browser about your location. Click the map instead.')
    }, 15_000)
    navigator.geolocation.getCurrentPosition(
      (position) => {
        if (settled) return
        settled = true
        clearTimeout(guard)
        setLocating(false)
        place({ lat: position.coords.latitude, lng: position.coords.longitude })
      },
      (failure) => {
        if (settled) return
        settled = true
        clearTimeout(guard)
        setLocating(false)
        setError(
          failure.code === failure.PERMISSION_DENIED
            ? 'Location access is blocked in your browser. Click the map instead.'
            : 'Your location could not be read. Click the map instead.',
        )
      },
      { timeout: 10_000, maximumAge: 60_000 },
    )
  }


  const start = value ?? MANILA

  return (
    <div className="flex flex-col gap-3">
      <div className="isolate h-72 overflow-hidden rounded-lg border">
        <MapContainer ref={setMap} center={[start.lat, start.lng]} zoom={value ? 16 : 11} className="size-full">
          <TileLayer
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            url="https://tile.openstreetmap.org/{z}/{x}/{y}.png"
          />
          <ClickToPlace onChange={onChange} />
          {value && (
            <Marker
              draggable
              icon={pin}
              position={[value.lat, value.lng]}
              eventHandlers={{ dragend: (event) => onChange((event.target as L.Marker).getLatLng()) }}
            />
          )}
        </MapContainer>
      </div>
      <div className="flex flex-wrap items-end gap-3">
        <Button type="button" variant="outline" loading={locating} onClick={useMyLocation}>Use my location</Button>
      </div>
      <p className="text-muted-foreground text-sm">
        {error || (value ? 'Drag the pin to adjust it.' : 'Click the map where the gym is.')}
      </p>
    </div>
  )
}
