export type Theme = 'light' | 'dark'
export type ThemePreference = Theme | 'system'

const KEY = 'theme'
const BAR = { light: '#ffffff', dark: '#0a0a0a' } as const
let preference: ThemePreference

export function getPreference(): ThemePreference {
  if (preference) return preference
  try {
    const saved = localStorage.getItem(KEY)
    if (saved === 'light' || saved === 'dark') return saved
  } catch {
    // Storage may be unavailable; system preference still works.
  }
  return 'system'
}

function applyTheme(): void {
  const choice = getPreference()
  const theme = choice === 'system'
    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
    : choice
  const root = document.documentElement
  root.classList.toggle('dark', theme === 'dark')
  root.style.colorScheme = theme
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', BAR[theme])
  window.dispatchEvent(new Event('theme-change'))
}

export function subscribeTheme(listener: () => void): () => void {
  window.addEventListener('theme-change', listener)
  return () => window.removeEventListener('theme-change', listener)
}

export function initTheme(): () => void {
  preference = getPreference()
  applyTheme()
  const media = window.matchMedia('(prefers-color-scheme: dark)')
  const onChange = () => {
    if (getPreference() === 'system') applyTheme()
  }
  media.addEventListener('change', onChange)
  return () => media.removeEventListener('change', onChange)
}

export function currentTheme(): Theme {
  return document.documentElement.classList.contains('dark') ? 'dark' : 'light'
}

export function setTheme(theme: Theme): void {
  applyPreference(theme)
}

export function applyPreference(choice: ThemePreference): void {
  preference = choice
  applyTheme()
  try {
    localStorage.setItem(KEY, choice)
  } catch {
    // Private mode can refuse storage; the switch still works for this visit.
  }
}
