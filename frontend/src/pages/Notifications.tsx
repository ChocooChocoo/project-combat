import { useCallback, useEffect, useState } from 'react'
import { useOutletContext } from 'react-router'
import type { Notice, NoticeList } from '@/components/notifications-menu'
import { PageHeader } from '@/components/page-header'
import { Button } from '@/components/ui/button'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { api } from '@/lib/api'

/** S-15, R-10: the person's own notices. Later features add the notices themselves. */
export default function Notifications() {
  const { setUnread } = useOutletContext<{ setUnread: (n: number) => void }>()
  const [notices, setNotices] = useState<Notice[] | null>(null)

  const load = useCallback(async () => {
    const result = await api<NoticeList>('/notifications')
    setNotices(result.notifications.data)
    setUnread(result.unread_count)
  }, [setUnread])

  useEffect(() => {
    load()
  }, [load])

  const act = async (path: string, method = 'POST') => {
    await api(path, { method })
    await load()
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Notifications"
        description="Updates about your account, gyms and bookings."
        actions={
          <>
            <Button variant="outline" size="sm" onClick={() => act('/notifications/read-all')}>Mark all as read</Button>
            <Button variant="ghost" size="sm" onClick={() => act('/notifications/read', 'DELETE')}>Clear read</Button>
          </>
        }
      />

      {!notices ? (
        <div className="flex flex-col gap-2" aria-busy="true">
          <Skeleton className="h-20" />
          <Skeleton className="h-20" />
          <Skeleton className="h-20" />
        </div>
      ) : notices.length === 0 ? (
        <Empty className="rounded-xl border bg-background">
          <EmptyHeader>
            <EmptyTitle>No notifications</EmptyTitle>
            <EmptyDescription>Updates about your account, gyms and bookings will show up here.</EmptyDescription>
          </EmptyHeader>
        </Empty>
      ) : (
        <ul className="flex flex-col divide-y rounded-xl border bg-background">
          {notices.map((notice) => (
            <li key={notice.id} className="flex flex-wrap items-start gap-3 p-4">
              <div className="mr-auto flex flex-col gap-1">
                <span className={notice.read_at ? 'text-muted-foreground' : 'font-medium'}>
                  {notice.data.title ?? 'Notice'}
                </span>
                {notice.data.message && <span className="text-muted-foreground text-sm">{notice.data.message}</span>}
                <span className="text-muted-foreground text-xs">{new Date(notice.created_at).toLocaleString()}</span>
              </div>
              {!notice.read_at && (
                <Button variant="outline" size="xs" onClick={() => act(`/notifications/${notice.id}/read`)}>Mark read</Button>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
