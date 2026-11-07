import { useCallback, useState } from 'react'
import { Link } from 'react-router'
import type { Notice, NoticeList } from '@/components/notifications-menu'
import { PageHeader } from '@/components/page-header'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { api, ApiError } from '@/lib/api'
import { dateTimeLabel } from '@/lib/membership'
import { useNotifications } from '@/lib/notifications'
import { usePolling } from '@/hooks/use-polling'
import { useAuth } from '@/lib/auth'

/** S-15, R-10: the person's own notices. Later features add the notices themselves. */
export default function Notifications() {
  const { setUnread, refresh } = useNotifications()
  const { user } = useAuth()
  const [notices, setNotices] = useState<Notice[] | null>(null)
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [version, setVersion] = useState(0)

  const load = useCallback(async (silent: boolean, isCurrent: () => boolean) => {
    if (!silent) { setLoading(true); setError('') }
    try {
      const result = await api<NoticeList>(`/notifications?page=${page}`)
      if (!isCurrent()) return
      setUnread(result.unread_count)
      if (result.notifications.data.length === 0 && page > 1) {
        setPage((current) => current - 1)
        return
      }
      setNotices(result.notifications.data)
      setLastPage(result.notifications.last_page)
      setError('')
    } catch (failure) {
      if (isCurrent() && !silent) setError(failure instanceof ApiError ? failure.message : 'Notifications could not be loaded.')
    } finally {
      if (isCurrent()) setLoading(false)
    }
  }, [page, setUnread])

  usePolling(load, 15_000, Boolean(user), version)

  const act = async (path: string, method = 'POST') => {
    setBusy(true)
    try {
      await api(path, { method })
      await refresh()
      setVersion((current) => current + 1)
    } catch (failure) {
      setError(failure instanceof ApiError ? failure.message : 'Notifications could not be updated.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Notifications"
        description="Updates about your account, gyms and bookings."
        actions={
          <>
            <Button variant="outline" size="sm" disabled={busy || loading} onClick={() => act('/notifications/read-all')}>Mark all as read</Button>
            <Button variant="ghost" size="sm" disabled={busy || loading} onClick={() => act('/notifications/read', 'DELETE')}>Clear read</Button>
          </>
        }
      />

      {error ? (
        <Alert variant="error">
          <AlertTitle>Notifications could not be loaded</AlertTitle>
          <AlertDescription>
            <p>{error}</p>
            <Button variant="outline" size="sm" onClick={() => setVersion((current) => current + 1)}>Retry</Button>
          </AlertDescription>
        </Alert>
      ) : !notices ? (
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
              <div className="mr-auto flex min-w-0 flex-1 flex-col gap-1 break-words">
                <span className={notice.read_at ? 'text-muted-foreground' : 'font-medium'}>
                  {notice.data.title ?? 'Notice'}
                </span>
                {notice.data.message && <span className="text-muted-foreground text-sm">{notice.data.message}</span>}
                <span className="text-muted-foreground text-xs">{dateTimeLabel(notice.created_at)}</span>
                {notice.data.url && <Link className="text-sm underline underline-offset-4" to={notice.data.url}>Open</Link>}
              </div>
              {!notice.read_at && (
                <Button variant="outline" size="xs" disabled={busy} onClick={() => act(`/notifications/${notice.id}/read`)}>Mark read</Button>
              )}
            </li>
          ))}
        </ul>
      )}
      {!error && notices && (
        <nav aria-label="Notification pages" className="flex flex-wrap items-center justify-between gap-3">
          <Button variant="outline" size="sm" disabled={busy || loading || page <= 1} onClick={() => setPage((current) => current - 1)}>Previous</Button>
          <span className="text-muted-foreground text-sm" aria-live="polite">Page {page} of {lastPage}</span>
          <Button variant="outline" size="sm" disabled={busy || loading || page >= lastPage} onClick={() => setPage((current) => current + 1)}>Next</Button>
        </nav>
      )}
    </div>
  )
}
