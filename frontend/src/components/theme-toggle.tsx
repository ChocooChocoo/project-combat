import { Moon, Sun } from 'lucide-react'
import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { currentTheme, setTheme, type Theme } from '@/lib/theme'

/** One button that switches between light and dark screens. */
export function ThemeToggle() {
  const [theme, setLocal] = useState<Theme>(currentTheme)
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
        setLocal(next)
      }}
    >
      {theme === 'dark' ? <Sun aria-hidden /> : <Moon aria-hidden />}
    </Button>
  )
}
