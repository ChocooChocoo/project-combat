import initialDark from '@/assets/initial-logo-dark-mode.png'
import initialLight from '@/assets/initial-logo-light-mode.png'
import logoDark from '@/assets/logo-dark-mode.png'
import logoLight from '@/assets/logo-light-mode.png'
import { cn } from '@/lib/utils'

/** CombatPro wordmark, or the "CP" mark when `compact`. Follows the `dark` class on <html> (or any parent with `dark`). Size it with `className="h-6"`. */
export function BrandLogo({ className, compact = false }: { className?: string; compact?: boolean }) {
  const [light, dark] = compact ? [initialLight, initialDark] : [logoLight, logoDark]
  return (
    <>
      <img src={light} alt="CombatPro" className={cn('w-auto dark:hidden', className)} />
      <img src={dark} alt="" aria-hidden className={cn('hidden w-auto dark:block', className)} />
    </>
  )
}
