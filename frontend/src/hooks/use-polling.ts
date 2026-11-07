import { useEffect, useRef } from 'react'

export function usePolling(callback: (silent: boolean, isCurrent: () => boolean) => void | Promise<void>, intervalMs: number, enabled = true, refreshKey = 0) {
  const running = useRef<Promise<void> | null>(null)

  useEffect(() => {
    if (!enabled) return
    let active = true
    let first = true
    let pending = false
    const run = () => {
      if (!active || pending || document.visibilityState !== 'visible') return
      if (!first && running.current) return
      pending = true
      const previous = running.current
      const task = (async () => {
        await previous
        if (!active || document.visibilityState !== 'visible') return
        const silent = !first
        first = false
        await callback(silent, () => active)
      })().catch(() => undefined)
      running.current = task
      void task.finally(() => {
        pending = false
        if (running.current === task) running.current = null
      })
    }
    const refresh = () => {
      run()
    }
    run()
    const timer = window.setInterval(refresh, intervalMs)
    window.addEventListener('focus', refresh)
    document.addEventListener('visibilitychange', refresh)
    return () => {
      active = false
      window.clearInterval(timer)
      window.removeEventListener('focus', refresh)
      document.removeEventListener('visibilitychange', refresh)
    }
  }, [callback, intervalMs, enabled, refreshKey])
}
