import { Construction } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty'

/** Temporary body for a Phase 3 screen. Feature work replaces the placeholder, not the page shell. */
export function PagePlaceholder({
  title,
  description,
  note,
}: {
  title: string
  description?: React.ReactNode
  note?: string
}) {
  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={title} description={description} />
      <Empty className="rounded-xl border">
        <EmptyHeader>
          <EmptyMedia variant="icon">
            <Construction aria-hidden />
          </EmptyMedia>
          <EmptyTitle>This screen is being built</EmptyTitle>
          <EmptyDescription>{note ?? 'It arrives with the next part of the gym workspace.'}</EmptyDescription>
        </EmptyHeader>
      </Empty>
    </div>
  )
}
