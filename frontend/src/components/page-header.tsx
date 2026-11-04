/** The one heading pattern every signed-in screen uses: title, optional line of context, actions on the right (below it on phones). */
export function PageHeader({
  title,
  description,
  actions,
  meta,
}: {
  title: React.ReactNode
  description?: React.ReactNode
  actions?: React.ReactNode
  meta?: React.ReactNode
}) {
  return (
    <header className="flex flex-col gap-x-6 gap-y-4 border-b pb-6 sm:flex-row sm:flex-wrap sm:items-end">
      <div className="flex min-w-0 flex-1 flex-col gap-1.5">
        <div className="flex flex-wrap items-center gap-3">
          <h1 className="text-balance font-semibold text-2xl tracking-tight">{title}</h1>
          {meta}
        </div>
        {description && <div className="max-w-prose text-muted-foreground text-sm">{description}</div>}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </header>
  )
}
