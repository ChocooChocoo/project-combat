/** Light or dark screens. The choice is kept in this browser; dark is the default. */
export type Theme = 'light' | 'dark'

const KEY = 'theme'
const BAR = { light: '#ffffff', dark: '#0a0a0a' } as const

export function currentTheme(): Theme {
  return document.documentElement.classList.contains('dark') ? 'dark' : 'light'
}

export function setTheme(theme: Theme): void {
  const root = document.documentElement
  root.classList.toggle('dark', theme === 'dark')
  root.style.colorScheme = theme
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', BAR[theme])
  try {
    localStorage.setItem(KEY, theme)
  } catch {
    // Private mode can refuse storage; the switch still works for this visit.
  }
}
