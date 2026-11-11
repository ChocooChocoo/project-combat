import { Monitor, Moon, Sun } from 'lucide-react'
import { useSyncExternalStore } from 'react'
import { applyPreference, getPreference, subscribeTheme } from '@/lib/theme'
import { cn } from '@/lib/utils'

const OPTIONS = [
  { value: 'system', label: 'System', Icon: Monitor },
  { value: 'light', label: 'Light', Icon: Sun },
  { value: 'dark', label: 'Dark', Icon: Moon },
] as const

export function ThemeSwitch() {
  const preference = useSyncExternalStore(subscribeTheme, getPreference)

  return (
    <div role="group" aria-label="Color theme" className="inline-flex shrink-0 border border-landing-line-soft">
      {OPTIONS.map(({ value, label, Icon }) => (
        <button
          key={value}
          type="button"
          aria-label={`${label} theme`}
          aria-pressed={preference === value}
          title={value === 'system' ? 'Follow system theme' : `${label} theme`}
          onClick={() => applyPreference(value)}
          className={cn(
            'inline-flex size-11 items-center justify-center focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-landing-fg',
            preference === value
              ? 'bg-landing-invert text-landing-invert-fg'
              : 'text-landing-soft hover:bg-landing-surface',
          )}
        >
          <Icon className="size-4" aria-hidden />
        </button>
      ))}
    </div>
  )
}
