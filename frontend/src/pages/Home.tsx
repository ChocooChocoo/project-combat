import { Badge } from '@/components/ui/badge'
import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { useAuth } from '@/lib/auth'

/** S-09 placeholder. Gym search arrives with R-30 in Phase 3. */
export default function Home() {
  const { user } = useAuth()

  return (
    <div className="flex flex-col gap-6">
      <h1 className="font-semibold text-2xl">Welcome, {user?.first_name}</h1>

      {user && user.gyms.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>Your gyms</CardTitle>
            <CardDescription>Your role in each gym you belong to.</CardDescription>
          </CardHeader>
          <CardPanel className="flex flex-col gap-2">
            {user.gyms.map((gym) => (
              <div key={gym.id} className="flex flex-wrap items-center gap-2">
                <span className="font-medium">{gym.name}</span>
                {gym.roles.map((role) => (
                  <Badge key={role} variant="outline">{role}</Badge>
                ))}
              </div>
            ))}
          </CardPanel>
        </Card>
      )}

      <Empty className="rounded-xl border bg-background">
        <EmptyHeader>
          <EmptyTitle>Finding gyms is coming soon</EmptyTitle>
          <EmptyDescription>
            Search by location, discipline, services and schedules will appear here once gyms can join the platform.
          </EmptyDescription>
        </EmptyHeader>
      </Empty>
    </div>
  )
}
