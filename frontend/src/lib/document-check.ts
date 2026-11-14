// (R-133) Browser-side checks on uploaded document photos: blur, expiry date, names.
// No `@/` imports, so node --test can load this file.

// ponytail: starting value; tune with real phone photos (raise if blurry ones pass)
export const BLUR_MIN = 80

const pad = (n: number) => String(n).padStart(2, '0')

/** Today as YYYY-MM-DD in the gym timezone (Asia/Manila). */
export const todayIso = () => new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' })

/** Variance of the 3x3 Laplacian over the inner pixels. Low means few sharp edges, so a blurry photo. */
export function laplacianVariance(gray: ArrayLike<number>, width: number, height: number): number {
  const values: number[] = []
  for (let y = 1; y < height - 1; y++) {
    for (let x = 1; x < width - 1; x++) {
      const i = y * width + x
      values.push(gray[i - width] + gray[i - 1] + gray[i + 1] + gray[i + width] - 4 * gray[i])
    }
  }
  if (values.length === 0) return 0
  const mean = values.reduce((a, b) => a + b, 0) / values.length
  return values.reduce((a, b) => a + (b - mean) ** 2, 0) / values.length
}

export async function blurScore(file: File): Promise<number> {
  const bitmap = await createImageBitmap(file)
  const scale = Math.min(1, 800 / Math.max(bitmap.width, bitmap.height))
  const width = Math.max(1, Math.round(bitmap.width * scale))
  const height = Math.max(1, Math.round(bitmap.height * scale))
  const canvas = document.createElement('canvas')
  canvas.width = width
  canvas.height = height
  const context = canvas.getContext('2d')
  if (!context) throw new Error('No canvas')
  context.drawImage(bitmap, 0, 0, width, height)
  bitmap.close()
  const { data } = context.getImageData(0, 0, width, height)
  const gray = new Float32Array(width * height)
  for (let i = 0; i < gray.length; i++) gray[i] = 0.299 * data[i * 4] + 0.587 * data[i * 4 + 1] + 0.114 * data[i * 4 + 2]
  return laplacianVariance(gray, width, height)
}

/** Reads the text of an image in the browser. Loaded on demand so the main bundle stays small. */
export async function readText(file: File): Promise<string> {
  const { createWorker } = await import('tesseract.js')
  const worker = await createWorker('eng')
  try {
    return (await worker.recognize(file)).data.text
  } finally {
    await worker.terminate()
  }
}

const MONTHS = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC']

/** The expiry date in a document text as YYYY-MM-DD, or null. */
export function findExpiryDate(text: string): string | null {
  const upper = text.toUpperCase()
  const found: { index: number; iso: string }[] = []
  const add = (index: number, y: number, m: number, d: number) => {
    const date = new Date(Date.UTC(y, m - 1, d))
    if (m >= 1 && m <= 12 && date.getUTCMonth() === m - 1 && date.getUTCDate() === d) found.push({ index, iso: `${y}-${pad(m)}-${pad(d)}` })
  }
  const month = (name: string) => MONTHS.indexOf(name.slice(0, 3)) + 1

  for (const m of upper.matchAll(/(?<!\d)(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})(?!\d)/g)) add(m.index, +m[1], +m[2], +m[3])
  for (const m of upper.matchAll(/(?<!\d)(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})(?!\d)/g)) add(m.index, +m[3], +m[1], +m[2])
  for (const m of upper.matchAll(/([A-Z]{3,9})\.?\s+(\d{1,2})(?:ST|ND|RD|TH)?,?\s+(\d{4})(?!\d)/g)) if (month(m[1])) add(m.index, +m[3], month(m[1]), +m[2])
  for (const m of upper.matchAll(/(?<!\d)(\d{1,2})\s+([A-Z]{3,9})\.?,?\s+(\d{4})(?!\d)/g)) if (month(m[2])) add(m.index, +m[3], month(m[2]), +m[1])

  if (found.length === 0) return null
  found.sort((a, b) => a.index - b.index)
  const keyword = upper.search(/VALID\s*(UNTIL|THRU|THROUGH|TO)|EXPIR|VALIDITY|UNTIL/)
  if (keyword >= 0) {
    const after = found.find((f) => f.index >= keyword)
    if (after) return after.iso
  }
  return found.map((f) => f.iso).sort().at(-1) ?? null
}

const clean = (s: string) => s.toUpperCase().replace(/[^A-Z0-9]+/g, ' ').trim()
const GYM_FILLER = new Set(['GYM', 'FITNESS', 'CENTER', 'CENTRE', 'THE', 'AND', 'INC', 'CORP', 'CLUB'])

/** Flags for names the document should show: owner_name, gym_name, or no_text when nothing was read. */
export function nameFlags(text: string, checks: string[], names: { owner: string; gym: string }): string[] {
  const body = clean(text)
  if (body.length < 20) return ['no_text']
  const words = new Set(body.split(' '))
  const flags: string[] = []
  if (checks.includes('owner')) {
    const last = clean(names.owner)
    if (last && !` ${body} `.includes(` ${last} `)) flags.push('owner_name')
  }
  if (checks.includes('gym')) {
    const parts = clean(names.gym).split(' ').filter((w) => w.length >= 3 && !GYM_FILLER.has(w))
    if (parts.length > 0 && parts.filter((w) => words.has(w)).length < parts.length / 2) flags.push('gym_name')
  }
  return flags
}
