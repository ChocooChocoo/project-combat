/** The one heading pattern every signed-in screen uses: title, optional line of context, actions on the right. */
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
    <header className="flex flex-wrap items-end gap-x-6 gap-y-3 border-b pb-6">
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
