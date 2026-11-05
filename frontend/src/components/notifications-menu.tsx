import { Bell } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Popover, PopoverPopup, PopoverTitle, PopoverTrigger } from '@/components/ui/popover'
import { Skeleton } from '@/components/ui/skeleton'
import { api } from '@/lib/api'
import { dateTimeLabel } from '@/lib/membership'

export type Notice = {
  id: string
  data: { title?: string; message?: string }
  read_at: string | null
  created_at: string
}

export type NoticeList = { unread_count: number; notifications: { data: Notice[] } }

/** R-10: bell on the right of the navbar. Opening it loads the latest notices. */
export function NotificationsMenu({ unread, onUnreadChange }: { unread: number; onUnreadChange: (count: number) => void }) {
  const [open, setOpen] = useState(false)
  const [notices, setNotices] = useState<Notice[] | null>(null)

  const load = async () => {
    const result = await api<NoticeList>('/notifications')
    setNotices(result.notifications.data)
    onUnreadChange(result.unread_count)
  }

  const act = async (path: string) => {
    await api(path, { method: 'POST' })
    await load()
  }

  return (
    <Popover
      open={open}
      onOpenChange={(next) => {
        setOpen(next)
        if (next) load().catch(() => setNotices([]))
      }}
    >
      <PopoverTrigger
        render={<Button variant="ghost" size="icon" className="relative" />}
        aria-label={unread ? `Notifications, ${unread} unread` : 'Notifications'}
      >
        <Bell />
        {unread > 0 && (
          <Badge className="absolute -end-1 -top-1" size="sm" aria-hidden>
            {unread > 99 ? '99+' : unread}
          </Badge>
        )}
      </PopoverTrigger>
      <PopoverPopup side="bottom" align="end" className="w-[min(24rem,calc(100vw-2rem))]">
        <div className="flex items-center gap-2 pb-3">
          <PopoverTitle className="mr-auto text-base">Notifications</PopoverTitle>
          {unread > 0 && (
            <Button variant="ghost" size="xs" onClick={() => act('/notifications/read-all')}>
              Mark all as read
            </Button>
          )}
        </div>

        {notices === null ? (
          <div className="flex flex-col gap-2" aria-busy="true">
            <Skeleton className="h-14" />
            <Skeleton className="h-14" />
            <Skeleton className="h-14" />
          </div>
        ) : notices.length === 0 ? (
          <p className="py-6 text-center text-muted-foreground text-sm">No notifications yet.</p>
        ) : (
          <ul className="-mx-2 flex max-h-96 flex-col divide-y overflow-y-auto">
            {notices.slice(0, 8).map((notice) => (
              <li key={notice.id} className="flex items-start gap-2 px-2 py-3">
                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                  <span className={notice.read_at ? 'text-muted-foreground text-sm' : 'font-medium text-sm'}>
                    {notice.data.title ?? 'Notice'}
                  </span>
                  {notice.data.message && <span className="line-clamp-2 text-muted-foreground text-xs">{notice.data.message}</span>}
                  <span className="text-muted-foreground text-xs">{dateTimeLabel(notice.created_at)}</span>
                </div>
                {!notice.read_at && (
                  <Button variant="outline" size="xs" onClick={() => act(`/notifications/${notice.id}/read`)}>
                    Mark read
                  </Button>
                )}
              </li>
            ))}
          </ul>
        )}

        <div className="border-t pt-3">
          <Button variant="outline" size="sm" className="w-full" render={<Link to="/notifications" />} onClick={() => setOpen(false)}>
            View all
          </Button>
        </div>
      </PopoverPopup>
    </Popover>
  )
}
