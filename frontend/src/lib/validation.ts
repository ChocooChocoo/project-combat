/**
 * Browser-side checks for Base UI `Field validate`. They mirror the Laravel rules so people
 * see the problem before sending; the backend still checks everything.
 */

/** Same as `Password::min(8)->letters()->mixedCase()->numbers()` in AppServiceProvider. */
export function passwordRule(value: unknown): string | null {
  const text = String(value ?? '')
  if (!text) return null
  if (text.length < 8) return 'Use at least 8 characters.'
  if (!/[a-z]/.test(text) || !/[A-Z]/.test(text)) return 'Use both upper and lower case letters.'
  if (!/\d/.test(text)) return 'Add at least one number.'
  return null
}

/** Phone numbers: digits with optional spaces, dashes, brackets and a leading +; 7 to 15 digits. */
export function phoneRule(value: unknown): string | null {
  const text = String(value ?? '').trim()
  if (!text) return null
  const digits = text.replace(/\D/g, '').length
  return /^\+?[\d\s()-]+$/.test(text) && digits >= 7 && digits <= 15 ? null : 'Enter a valid phone number, for example 0917 123 4567.'
}

/** Laravel `confirmed`: this field must equal the field called `other`. */
export function sameAs(other: string, message = 'The passwords do not match.') {
  return (value: unknown, values: Record<string, unknown>) => (value && value !== values[other] ? message : null)
}

/** Value must not come before the field called `other` (dates as yyyy-mm-dd, times as hh:mm). */
export function notBefore(other: string, message: string) {
  return (value: unknown, values: Record<string, unknown>) => {
    const start = values[other]
    return value && start && String(value) < String(start) ? message : null
  }
}

/** Checks a picked file against an `accept` list and a size limit in kilobytes (Laravel `max` on files). */
export function fileProblem(file: File, accept: string | undefined, maxKb: number | undefined): string | null {
  if (accept) {
    const ext = `.${file.name.split('.').pop()?.toLowerCase() ?? ''}`
    const ok = accept.split(',').map((rule) => rule.trim().toLowerCase()).some((rule) =>
      rule.startsWith('.') ? rule === ext : rule.endsWith('/*') ? file.type.startsWith(rule.slice(0, -1)) : rule === file.type,
    )
    if (!ok) return `This file type is not accepted. Use: ${accept}.`
  }
  if (maxKb && file.size > maxKb * 1024) return `The file is too big. The limit is ${maxKb >= 1024 ? `${Math.round(maxKb / 1024)} MB` : `${maxKb} KB`}.`
  return null
}
