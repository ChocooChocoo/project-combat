import { useEffect, useState } from 'react'
import QRCode from 'qrcode'
import { Button } from '@/components/ui/button'
import { api, ApiError } from '@/lib/api'
import type { Membership } from '@/lib/membership'

/** R-52: show only active gyms, including active gyms without any bookings. */
export function CheckInCodes({ gymId }: { gymId?: number }) {
  const [gyms, setGyms] = useState<{ id: number; name: string }[] | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  useEffect(() => {
    let live = true
    api<{ memberships: Membership[] }>('/my-memberships').then(({ memberships }) => {
      if (live) {
        setError('')
        setGyms([...new Map(memberships.filter((m) => m.status === 'active' && (!gymId || m.gym_id === gymId)).map((m) => [m.gym_id, { id: m.gym_id, name: m.gym_name }])).values()])
      }
    }).catch(() => live && setError('Check-in codes could not be loaded.'))
    return () => { live = false }
  }, [gymId, version])
  if (error) return <div role="alert"><p>{error}</p><Button variant="outline" onClick={() => setVersion((v) => v + 1)}>Retry check-in codes</Button></div>
  if (!gyms) return <p className="text-muted-foreground text-sm" role="status">Loading check-in codes…</p>
  return <div className="grid gap-4 sm:grid-cols-2">{gyms.map((gym) => <CheckInCodeCard key={gym.id} gym={gym} />)}</div>
}

function CheckInCodeCard({ gym }: { gym: { id: number; name: string } }) {
  const [code, setCode] = useState<{ short_code: string; expires_at: string; image: string } | null>(null)
  const [error, setError] = useState('')
  const [clock, setClock] = useState(() => Date.now())
  const [version, setVersion] = useState(0)
  useEffect(() => {
    let live = true
    let timer: ReturnType<typeof setTimeout>
    async function refresh() {
      try {
        const result = await api<{ payload: string; short_code: string; expires_at: string }>(`/gyms/${gym.id}/my-check-in-code`)
        const image = await QRCode.toDataURL(result.payload, { width: 224, margin: 4 })
        if (!live) return
        setCode({ ...result, image })
        setError('')
        // Refresh at the minute boundary, or within 50 seconds if the page woke from sleep.
        timer = setTimeout(refresh, Math.max(1000, Math.min(50_000, Date.parse(result.expires_at) - Date.now() + 100)))
      } catch (failure) {
        if (!live) return
        setCode(null)
        setError(failure instanceof ApiError ? failure.message : 'Your code could not be refreshed.')
        timer = setTimeout(refresh, 50_000)
      }
    }
    refresh()
    const tick = setInterval(() => setClock(Date.now()), 1000)
    return () => { live = false; clearTimeout(timer); clearInterval(tick) }
  }, [gym.id, version])
  const remaining = code ? Math.max(0, Math.ceil((Date.parse(code.expires_at) - clock) / 1000)) : 0
  return <section className="flex min-w-0 flex-col items-start gap-2 rounded-xl border p-4" aria-label={`Check-in code for ${gym.name}`}>
    <h2 className="font-semibold">Check-in code</h2><p className="break-words text-sm">{gym.name}</p>
    <p className="text-muted-foreground text-sm">Show this to gym staff when you arrive. Only staff can check you in.</p>
    {error ? <><p role="alert" className="text-sm">{error}</p><Button variant="outline" onClick={() => setVersion((v) => v + 1)}>Refresh code</Button></> : code && remaining > 0 ? <>
      <img src={code.image} width={224} height={224} className="max-w-full" alt="Your rotating check-in QR code" />
      <p className="font-mono text-2xl tracking-widest" aria-label={`Short code ${code.short_code.split('').join(' ')}`}>{code.short_code}</p>
      <p className="text-muted-foreground text-sm">Refreshes in {remaining}s</p>
    </> : <p role="status" className="text-sm">Refreshing code…</p>}
  </section>
}
