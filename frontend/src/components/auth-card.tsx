import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { cn } from '@/lib/utils'

/** Shared layout for sign-up, sign-in and recovery screens. */
export function AuthCard({ title, description, children, wide = false }: { title: string; description?: React.ReactNode; children: React.ReactNode; wide?: boolean }) {
  return (
    <main className="flex min-h-svh items-center justify-center bg-muted/40 px-4 py-6 sm:px-6 sm:py-10">
      <div className={cn('grid w-full items-center gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:gap-16', wide ? 'max-w-6xl' : 'max-w-5xl')}>
        <aside aria-label="Combat Platform" className={cn('mx-auto w-full lg:max-w-none lg:pr-4', wide ? 'max-w-lg' : 'max-w-sm')}>
          <p className="font-semibold text-lg tracking-tight">Combat Platform</p>
          <div className="hidden lg:block">
            <p className="mt-6 max-w-sm text-balance text-4xl font-semibold leading-tight tracking-tight">Find your gym. Make training part of your day.</p>
            <ul className="mt-10 max-w-sm space-y-5 border-t pt-6 text-sm leading-relaxed">
              <li><span className="block font-semibold">Find a place to train</span><span className="text-muted-foreground">Browse approved combat sports gyms.</span></li>
              <li><span className="block font-semibold">Join as a member</span><span className="text-muted-foreground">Find your best matches, join a gym and book sessions.</span></li>
              <li><span className="block font-semibold">Run your own gym</span><span className="text-muted-foreground">Apply as an owner and manage your gym once approved.</span></li>
            </ul>
          </div>
        </aside>
        <Card className={cn('mx-auto w-full min-w-0', wide ? 'max-w-lg' : 'max-w-sm')}>
          <CardHeader className="gap-3">
            <CardTitle render={<h1 />} className="text-2xl leading-tight tracking-tight sm:text-3xl">{title}</CardTitle>
            {description && <CardDescription className="leading-relaxed">{description}</CardDescription>}
          </CardHeader>
          <CardPanel className="[&_input:not([type=radio])]:min-h-11">{children}</CardPanel>
        </Card>
      </div>
    </main>
  )
}
