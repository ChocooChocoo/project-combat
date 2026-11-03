import { useCallback, useEffect, useState } from 'react'
import { useOutletContext } from 'react-router'
import { Button } from '@/components/ui/button'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Spinner } from '@/components/ui/spinner'
import { api } from '@/lib/api'

type Notice = {
  id: string
  data: { title?: string; message?: string }
  read_at: string | null
  created_at: string
}

/** S-15, R-10: the person's own notices. Later features add the notices themselves. */
export default function Notifications() {
  const { setUnread } = useOutletContext<{ setUnread: (n: number) => void }>()
  const [notices, setNotices] = useState<Notice[] | null>(null)

  const load = useCallback(async () => {
    const result = await api<{ unread_count: number; notifications: { data: Notice[] } }>('/notifications')
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

  if (!notices) return <Spinner />

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center gap-2">
        <h1 className="mr-auto font-semibold text-2xl">Notifications</h1>
        <Button variant="outline" size="sm" onClick={() => act('/notifications/read-all')}>Mark all as read</Button>
        <Button variant="ghost" size="sm" onClick={() => act('/notifications/read', 'DELETE')}>Clear read</Button>
      </div>

      {notices.length === 0 ? (
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
