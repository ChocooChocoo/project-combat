// Laravel paginator metadata shared by every growing list.

export type PageMeta = {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

function objectOf(value: unknown): Record<string, unknown> | null {
  return value && typeof value === 'object' && !Array.isArray(value) ? (value as Record<string, unknown>) : null
}

/** Reads `meta`, a bare paginator or a data envelope, with sane fallbacks. */
export function pageMetaFrom(payload: unknown, shown: number): PageMeta {
  const record = objectOf(payload)
  const nested = objectOf(record?.data)
  const raw = objectOf(record?.meta) ?? objectOf(nested?.meta) ?? nested ?? record
  const number = (value: unknown) => {
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : 0
  }
  const current = number(raw?.current_page) || 1
  const last = Math.max(number(raw?.last_page) || 1, 1)
  return {
    current_page: Math.min(current, last),
    last_page: last,
    per_page: number(raw?.per_page) || shown || 25,
    total: number(raw?.total) || shown,
  }
}
