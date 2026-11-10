import { ArrowLeft, Building2, CalendarCheck, MapPin } from 'lucide-react'
import { Link } from 'react-router'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardDescription, CardFooter, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Frame, FrameDescription, FramePanel, FrameTitle } from '@/components/ui/frame'
import { cn } from '@/lib/utils'

const highlights = [
  { icon: MapPin, title: 'Find a place to train', text: 'Browse approved combat sports gyms.' },
  { icon: CalendarCheck, title: 'Join as a member', text: 'Find your best matches, join a gym and book sessions.' },
  { icon: Building2, title: 'Run your own gym', text: 'Apply as an owner and manage your gym once approved.' },
]

/** Same mark as the app sidebar. */
function Brand({ className }: { className?: string }) {
  return (
    <Link to="/" className={cn('flex items-center gap-2 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring', className)}>
      <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary font-semibold text-primary-foreground text-sm">CP</span>
      <span className="grid leading-tight">
        <span className="font-semibold text-sm">Combat Platform</span>
        <span className="text-muted-foreground text-xs">Training centers</span>
      </span>
    </Link>
  )
}

/** Shared layout for sign-up, sign-in and recovery screens. */
export function AuthCard({ title, description, children, footer, wide = false }: {
  title: string
  description?: React.ReactNode
  children: React.ReactNode
  footer?: React.ReactNode
  wide?: boolean
}) {
  return (
    <main className="grid min-h-svh lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
      <aside aria-label="Combat Platform" className="hidden flex-col justify-between gap-10 border-e bg-muted/40 p-10 lg:flex">
        <Brand className="self-start" />
        <div className="flex max-w-md flex-col gap-6">
          <Badge variant="outline" size="lg" className="self-start">Combat sports training</Badge>
          <h2 className="text-balance font-heading font-semibold text-4xl leading-tight tracking-tight">
            Find your gym. Make training part of your day.
          </h2>
          <p className="text-muted-foreground leading-relaxed">
            One account for finding gyms, booking sessions and running your own training center.
          </p>
          <Frame>
            {highlights.map(({ icon: Icon, title: heading, text }) => (
              <FramePanel key={heading} className="flex items-start gap-3 p-4">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg border bg-muted/40">
                  <Icon className="size-4" aria-hidden />
                </span>
                <span className="grid gap-0.5">
                  <FrameTitle>{heading}</FrameTitle>
                  <FrameDescription>{text}</FrameDescription>
                </span>
              </FramePanel>
            ))}
          </Frame>
        </div>
        <p className="text-muted-foreground text-xs">© {new Date().getFullYear()} Combat Platform</p>
      </aside>

      <div className="flex min-w-0 flex-col gap-6 p-4 sm:p-6 lg:p-10">
        <div className="flex items-center justify-between gap-3">
          <Brand className="lg:hidden" />
          <Button variant="ghost" size="sm" className="ms-auto text-muted-foreground" render={<Link to="/" />}>
            <ArrowLeft aria-hidden /> Back to home
          </Button>
        </div>
        <div className="flex flex-1 items-center justify-center">
          <Card className={cn('w-full min-w-0', wide ? 'max-w-xl' : 'max-w-md')}>
            <CardHeader>
              <CardTitle render={<h1 />} className="text-2xl tracking-tight">{title}</CardTitle>
              {description && <CardDescription>{description}</CardDescription>}
            </CardHeader>
            <CardPanel>{children}</CardPanel>
            {footer && <CardFooter className="justify-center gap-1 border-t py-4 text-muted-foreground text-sm">{footer}</CardFooter>}
          </Card>
        </div>
      </div>
    </main>
  )
}
