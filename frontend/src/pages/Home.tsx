import { FilePlus2, MapPin } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { api } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { useRequirements } from '@/lib/gyms'

type LiveGym = { id: number; name: string; city: string | null; province: string | null; disciplines: string[] | null; tier: number; badge: string }

/** S-09. Approved gyms with their tier badge (R-18). Search and filters arrive with R-30 in Phase 3. */
export default function Home() {
  const { user } = useAuth()
  const requirements = useRequirements()
  const [gyms, setGyms] = useState<LiveGym[] | null>(null)

  useEffect(() => {
    api<{ gyms: LiveGym[] }>('/gyms').then((r) => setGyms(r.gyms)).catch(() => setGyms([]))
  }, [])

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
          <h2 id="your-gyms" className="font-semibold">Your gyms</h2>
          <ul className="flex flex-col divide-y rounded-xl border">
            {user.gyms.map((gym) => (
              <li key={gym.id} className="flex flex-wrap items-center gap-2 px-4 py-3">
                <span className="mr-auto font-medium">{gym.name}</span>
                {gym.roles.map((role) => (
                  <Badge key={role} variant="outline" className="capitalize">{role}</Badge>
                ))}
              </li>
            ))}
          </ul>
        </section>
      )}

      <section className="flex flex-col gap-3" aria-labelledby="live-gyms">
        <div className="flex flex-wrap items-baseline gap-2">
          <h2 id="live-gyms" className="font-semibold">Gyms on the platform</h2>
          {gyms && gyms.length > 0 && <span className="text-muted-foreground text-sm tabular-nums">{gyms.length} verified</span>}
        </div>
        {!gyms ? (
          <div className="grid gap-3 sm:grid-cols-2" aria-busy="true">
            <Skeleton className="h-24" />
            <Skeleton className="h-24" />
          </div>
        ) : gyms.length === 0 ? (
          <Empty className="rounded-xl border">
            <EmptyHeader>
              <EmptyTitle>No verified gyms yet</EmptyTitle>
              <EmptyDescription>Gyms appear here once the platform administrator approves their application. Search by location and sport comes later.</EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <ul className="grid gap-3 sm:grid-cols-2">
            {gyms.map((gym) => (
              <li key={gym.id} className="flex flex-col gap-3 rounded-xl border p-4">
                <div className="flex flex-wrap items-start gap-2">
                  <span className="mr-auto font-semibold">{gym.name}</span>
                  <Badge variant={gym.tier === 2 ? 'success' : 'info'}>{gym.badge}</Badge>
                </div>
                <span className="flex items-center gap-1.5 text-muted-foreground text-sm">
                  <MapPin className="size-4" aria-hidden />
                  {[gym.city, gym.province].filter(Boolean).join(', ')}
                </span>
                {(gym.disciplines ?? []).length > 0 && (
                  <span className="flex flex-wrap gap-1.5">
                    {(gym.disciplines ?? []).map((d) => (
                      <Badge key={d} variant="outline">{requirements?.disciplines[d] ?? d}</Badge>
                    ))}
                  </span>
                )}
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}
