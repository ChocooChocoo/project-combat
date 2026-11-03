import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'

/** Centred card used by the sign-up, sign-in and recovery screens. */
export function AuthCard({ title, description, children }: { title: string; description?: React.ReactNode; children: React.ReactNode }) {
  return (
    <main className="flex min-h-svh items-center justify-center bg-muted/40 px-4 py-10">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle>{title}</CardTitle>
          {description && <CardDescription>{description}</CardDescription>}
        </CardHeader>
        <CardPanel>{children}</CardPanel>
      </Card>
    </main>
  )
}
