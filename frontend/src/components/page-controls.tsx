import { ChevronLeft, ChevronRight } from 'lucide-react'

import { Button } from '@/components/ui/button'
import type { PageMeta } from '@/lib/pagination'

// Shared Previous / Next control for paged lists (R-123).
export function PageControls({
  meta,
  label,
  onPage,
}: {
  meta: PageMeta | null
  label: string
  onPage: (page: number) => void
}) {
  if (!meta || meta.last_page <= 1) return null
  return (
    <nav aria-label={`${label} pages`} className="flex flex-wrap items-center justify-between gap-3">
      <p className="text-muted-foreground text-sm" aria-live="polite">
        Page {meta.current_page} of {meta.last_page} · {meta.total} {meta.total === 1 ? 'entry' : 'entries'}
      </p>
      <div className="flex gap-2">
        <Button size="sm" variant="outline" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>
          <ChevronLeft aria-hidden />
          Previous
        </Button>
        <Button
          size="sm"
          variant="outline"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPage(meta.current_page + 1)}
        >
          Next
          <ChevronRight aria-hidden />
        </Button>
      </div>
    </nav>
  )
}
