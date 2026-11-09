import { useEffect, useState } from 'react'
import QRCode from 'qrcode'
import { RefreshCcw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { api, ApiError } from '@/lib/api'
import type { Membership } from '@/lib/membership'

/** R-52: show only active gyms, including active gyms without any bookings. */
export function CheckInCodes({ gymId, columns = 2 }: { gymId?: number; columns?: 1 | 2 }) {
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
  if (error) {
    return (
      <div className="rounded-xl border bg-card p-5" role="alert">
        <p className="font-medium text-sm">Check-in code unavailable</p>
        <p className="mt-1 text-muted-foreground text-sm">{error}</p>
        <Button className="mt-4 min-h-11" variant="outline" onClick={() => setVersion((v) => v + 1)}>Try again</Button>
      </div>
    )
  }
  if (!gyms) return <p className="text-muted-foreground text-sm" role="status">Loading check-in codes…</p>
  if (gyms.length === 0) {
    return (
      <div className="rounded-xl border bg-card p-5">
        <h2 className="font-semibold text-base">Check-in code</h2>
        <p className="mt-2 text-muted-foreground text-sm leading-relaxed">Your code appears once a membership here is active.</p>
      </div>
    )
  }
  return (
    <div className={columns === 2 ? 'grid gap-4 sm:grid-cols-2' : 'flex flex-col gap-4'}>
      {gyms.map((gym) => <CheckInCodeCard key={gym.id} gym={gym} />)}
    </div>
  )
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
  return (
    <section className="flex min-w-0 flex-col gap-4 rounded-xl border bg-card p-5" aria-label={`Check-in code for ${gym.name}`}>
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <h2 className="font-semibold text-base">Check-in code</h2>
          <p className="mt-1 text-muted-foreground text-sm wrap-anywhere">{gym.name}</p>
        </div>
        <span className="shrink-0 rounded-sm border px-2 py-0.5 font-medium text-xs" aria-hidden>Live</span>
      </div>

      {error ? (
        <div role="alert" className="flex flex-col gap-3 rounded-lg border bg-muted/30 p-4">
          <p className="text-sm leading-relaxed">{error}</p>
          <Button className="min-h-11 h-auto whitespace-normal py-2" variant="outline" onClick={() => setVersion((v) => v + 1)}>
            <RefreshCcw aria-hidden /> Refresh code
          </Button>
        </div>
      ) : code && remaining > 0 ? (
        <>
          <div className="flex items-center justify-center rounded-lg bg-white p-3 ring-1 ring-black/10">
            <img src={code.image} width={224} height={224} className="h-auto w-full max-w-56" alt="Your rotating check-in QR code" />
          </div>
          <div className="flex flex-col items-center gap-1 text-center">
            <p className="text-muted-foreground text-xs">Short code</p>
            <p className="font-mono font-semibold text-3xl tracking-widest" aria-label={`Short code ${code.short_code.split('').join(' ')}`}>{code.short_code}</p>
            <p className="text-muted-foreground text-xs tabular-nums" role="status">Refreshes in {remaining}s</p>
          </div>
          <p className="border-t pt-4 text-muted-foreground text-xs leading-relaxed">Show this to gym staff when you arrive. Only staff can check you in.</p>
        </>
      ) : (
        <div className="flex aspect-square items-center justify-center rounded-lg bg-muted" role="status">
          <p className="text-muted-foreground text-sm">Refreshing code…</p>
        </div>
      )}
    </section>
  )
}
