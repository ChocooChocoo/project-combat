import { useCallback, useEffect, useState } from 'react'
import { ApiError, token } from '@/lib/api'
import { decisionUrl, readPayload, type Basis, type Payload, type View } from '@/lib/decision-support'

export function useDecisionSupport<T extends Payload>({ gymId, view, from, to, basis, enabled }: {
  gymId: number; view: View; from: string; to: string; basis: Basis; enabled: boolean
}) {
  const [version, setVersion] = useState(0)
  const retry = useCallback(() => setVersion((value) => value + 1), [])
  const key = JSON.stringify([gymId, view, from, to, basis, enabled, version])
  const [result, setResult] = useState<{ key: string; data: T | null; loading: boolean; error: string } | null>(null)

  useEffect(() => {
    if (!enabled) return
    const abort = new AbortController()
    let live = true
    const path = decisionUrl(gymId, view, from, to, basis)
    fetch(import.meta.env.VITE_API_URL + path, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${token.get() ?? ''}` }, signal: abort.signal,
    }).then(async (response) => {
      const value: unknown = await response.json()
      if (!response.ok) throw new ApiError(response.status, value as Record<string, unknown>)
      return readPayload(view, value) as T
    }).then((data) => {
      if (live) setResult({ key, data, loading: false, error: '' })
    }).catch((reason: unknown) => {
      if (live) setResult({ key, data: null, loading: false, error: reason instanceof Error ? reason.message : 'Could not load this dashboard.' })
    })
    return () => { live = false; abort.abort() }
  }, [gymId, view, from, to, basis, enabled, version, key])

  return enabled && result?.key === key ? { ...result, retry } : { data: null, loading: enabled, error: '', retry }
}
