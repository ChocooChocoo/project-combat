// Talks to the Laravel backend. The sign-in pass expires on the server (R-03).
const BASE = import.meta.env.VITE_API_URL as string
const TOKEN_KEY = 'token'

export type FieldErrors = Record<string, string[]>

export class ApiError extends Error {
  status: number
  errors: FieldErrors
  data: Record<string, unknown>

  constructor(status: number, data: Record<string, unknown>) {
    super((data.message as string) || 'Something went wrong. Try again.')
    this.status = status
    this.errors = (data.errors as FieldErrors) ?? {}
    this.data = data
  }
}

export const token = {
  get: () => localStorage.getItem(TOKEN_KEY),
  set: (value: string) => localStorage.setItem(TOKEN_KEY, value),
  clear: () => localStorage.removeItem(TOKEN_KEY),
}

export async function api<T = Record<string, unknown>>(
  path: string,
  options: { method?: string; body?: unknown } = {},
): Promise<T> {
  const isForm = options.body instanceof FormData
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (!isForm && options.body !== undefined) headers['Content-Type'] = 'application/json'
  const pass = token.get()
  if (pass) headers.Authorization = `Bearer ${pass}`

  const response = await fetch(BASE + path, {
    method: options.method ?? (options.body === undefined ? 'GET' : 'POST'),
    headers,
    body: isForm ? (options.body as FormData) : options.body === undefined ? undefined : JSON.stringify(options.body),
  })

  const data = response.status === 204 ? {} : await response.json().catch(() => ({}))
  if (!response.ok) throw new ApiError(response.status, data)

  return data as T
}

/** Reads a submitted form into a plain object. */
export function formValues(event: React.FormEvent<HTMLFormElement>): Record<string, string> {
  event.preventDefault()
  return Object.fromEntries(new FormData(event.currentTarget)) as Record<string, string>
}
