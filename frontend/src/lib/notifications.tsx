import { createContext, useCallback, useContext, useState } from 'react'
import { usePolling } from '@/hooks/use-polling'
import { api } from '@/lib/api'
import { useAuth } from '@/lib/auth'

type NotificationsState = {
  unread: number
  setUnread: (count: number) => void
  refresh: () => Promise<void>
}

const NotificationsContext = createContext<NotificationsState | null>(null)

export function NotificationsProvider({ children }: { children: React.ReactNode }) {
  const { user } = useAuth()
  return <NotificationsSession key={user?.id ?? 'guest'} enabled={Boolean(user)}>{children}</NotificationsSession>
}

function NotificationsSession({ children, enabled }: { children: React.ReactNode; enabled: boolean }) {
  const [unread, setUnread] = useState(0)
  const refresh = useCallback(async () => {
    if (!enabled) return
    try {
      const result = await api<{ unread_count: number }>('/notifications/unread-count')
      setUnread(result.unread_count)
    } catch {
      // A background refresh must not interrupt the screen the person is using.
    }
  }, [enabled])
  const poll = useCallback(async () => {
    if (document.visibilityState === 'visible') await refresh()
  }, [refresh])
  usePolling(poll, 15_000, enabled)

  return <NotificationsContext.Provider value={{ unread, setUnread, refresh }}>{children}</NotificationsContext.Provider>
}

export function useNotifications(): NotificationsState {
  const value = useContext(NotificationsContext)
  if (!value) throw new Error('useNotifications must be used inside NotificationsProvider')
  return value
}
