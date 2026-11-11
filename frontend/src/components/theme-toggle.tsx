import { Moon, Sun } from 'lucide-react'
import { useSyncExternalStore } from 'react'
import { Button } from '@/components/ui/button'
import { currentTheme, setTheme, subscribeTheme, type Theme } from '@/lib/theme'

/** One button that switches between light and dark screens. */
export function ThemeToggle() {
  const theme = useSyncExternalStore(subscribeTheme, currentTheme)
  const next: Theme = theme === 'dark' ? 'light' : 'dark'
  const label = `Switch to ${next} mode`

  return (
    <Button
      variant="ghost"
      size="icon"
      className="size-11 md:size-8"
      aria-label={label}
      title={label}
      onClick={() => {
        setTheme(next)
      }}
    >
      {theme === 'dark' ? <Sun aria-hidden /> : <Moon aria-hidden />}
    </Button>
  )
}
