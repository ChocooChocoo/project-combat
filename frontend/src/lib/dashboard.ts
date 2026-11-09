import { useCallback, useEffect, useState, type DependencyList } from 'react'

/** P07: response from GET /me/dashboard. */
export type MemberDashboardData = {
  visits: { date: string; count: number }[]
  visits_since: string
  sessions: { attended: number; late: number; no_show: number; upcoming: number }
  monthly: { period: string; attended: number; paid: string }[]
  memberships: { id: number; gym: string | null; plan: string | null; status: string; starts_on: string | null; ends_on: string | null; days_left: number | null }[]
  balance: string
}

/** P07: the extra keys GET /admin/stats returns for the admin dashboard. */
export type PlatformStats = {
  gyms_registered: number
  gyms_verified: number
  verified_by_tier: Record<string, number>
  applications_pending: number
  applications_returned: number
  applications_rejected: number
  monthly: { period: string; submitted: number; approved: number }[]
  subscriptions: { trial: number; paid: number; free: number; unmanaged: number }
}

/** Today's date in Manila as YYYY-MM-DD. */
export function manilaToday(): string {
  return new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' })
}

/** Adds days to a YYYY-MM-DD date without timezone drift. */
export function addDays(day: string, days: number): string {
  const date = new Date(`${day}T00:00:00Z`)
  date.setUTCDate(date.getUTCDate() + days)
  return date.toISOString().slice(0, 10)
}

/** The Monday on or before a YYYY-MM-DD date. */
export function mondayOf(day: string): string {
  const weekday = new Date(`${day}T00:00:00Z`).getUTCDay()
  return addDays(day, -((weekday + 6) % 7))
}

/** YYYY-MM for the month `offset` months away from the month of a YYYY-MM-DD date. */
export function monthKey(day: string, offset: number): string {
  const date = new Date(`${day.slice(0, 7)}-01T00:00:00Z`)
  date.setUTCMonth(date.getUTCMonth() + offset)
  return date.toISOString().slice(0, 7)
}

/** "2026-11" becomes "Nov". Adds the year when asked, for axes that span two years. */
export function monthLabel(period: string, withYear = false): string {
  const date = new Date(`${period}-01T00:00:00Z`)
  return date.toLocaleDateString('en-PH', { timeZone: 'UTC', month: 'short', ...(withYear ? { year: '2-digit' } : {}) })
}

/** "2026-11-02" becomes "Mon, 2 Nov". */
export function dayLabel(day: string): string {
  return new Date(`${day}T00:00:00Z`).toLocaleDateString('en-PH', { timeZone: 'UTC', weekday: 'short', day: 'numeric', month: 'short' })
}

/** ₱84K style label for chart marks. The exact amount still shows in tooltips and tables. */
export function compactPeso(value: number | string): string {
  return `₱${new Intl.NumberFormat('en-PH', { notation: 'compact', maximumFractionDigits: 1 }).format(Number(value))}`
}

/** Percentage of part in whole, rounded, or null when there is nothing to compare. */
export function percent(part: number, whole: number): number | null {
  return whole > 0 ? Math.round((part / whole) * 100) : null
}

/** Loads dashboard data once per dependency change, with a retry. */
export function useDashboardData<T>(load: () => Promise<T>, deps: DependencyList) {
  const [version, setVersion] = useState(0)
  const [state, setState] = useState<{ data: T | null; error: string }>({ data: null, error: '' })
  const retry = useCallback(() => setVersion((value) => value + 1), [])

  useEffect(() => {
    let live = true
    setState({ data: null, error: '' })
    load()
      .then((data) => { if (live) setState({ data, error: '' }) })
      .catch((reason: unknown) => { if (live) setState({ data: null, error: reason instanceof Error ? reason.message : 'Could not load this dashboard.' }) })
    return () => { live = false }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...deps, version])

  return { ...state, retry }
}
