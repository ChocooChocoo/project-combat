import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import test from 'node:test'
import vm from 'node:vm'
import * as theme from '../src/lib/theme.ts'

test('theme follows system until overridden, persists choices, and boots without a flash', () => {
  assert.equal(typeof theme.initTheme, 'function', 'system theme initialization exists')
  const media = new EventTarget()
  media.matches = true
  let stored = null
  let dark = false
  let bar = ''
  const root = {
    classList: {
      contains: () => dark,
      toggle: (_, value) => { dark = value },
    },
    style: {},
  }
  const document = {
    documentElement: root,
    querySelector: () => ({ setAttribute: (_, value) => { bar = value } }),
  }
  const storage = {
    getItem: () => stored,
    setItem: (_, value) => { stored = value },
  }
  const window = Object.assign(new EventTarget(), { matchMedia: () => media })
  Object.assign(globalThis, { document, window, localStorage: storage })

  const stop = theme.initTheme()
  assert.equal(theme.getPreference(), 'system')
  assert.equal(dark, true)
  media.matches = false
  media.dispatchEvent(new Event('change'))
  assert.equal(dark, false)
  assert.equal(root.style.colorScheme, 'light')
  assert.equal(bar, '#ffffff')

  theme.applyPreference('dark')
  assert.equal(stored, 'dark')
  media.dispatchEvent(new Event('change'))
  assert.equal(dark, true, 'explicit choice ignores system changes')
  theme.setTheme('light')
  assert.equal(theme.currentTheme(), 'light', 'existing app-shell toggle stays compatible')
  theme.applyPreference('system')
  assert.equal(stored, 'system')
  media.matches = true
  media.dispatchEvent(new Event('change'))
  assert.equal(dark, true)

  storage.setItem = () => { throw new Error('Storage blocked') }
  theme.applyPreference('light')
  assert.equal(dark, false, 'blocked storage does not block switching')
  media.dispatchEvent(new Event('change'))
  assert.equal(dark, false, 'in-memory override survives blocked storage')
  stop()

  const boot = readFileSync(new URL('../index.html', import.meta.url), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1]
  for (const preference of [null, 'invalid', 'system', 'dark', 'light']) {
    for (const systemDark of [true, false]) {
      stored = preference
      media.matches = systemDark
      vm.runInNewContext(boot, { document, window, localStorage: storage })
      assert.equal(dark, preference === 'dark' || (preference !== 'light' && systemDark))
      assert.equal(root.style.colorScheme, dark ? 'dark' : 'light')
      assert.equal(bar, dark ? '#0a0a0a' : '#ffffff')
    }
  }
  storage.getItem = () => { throw new Error('Storage blocked') }
  media.matches = false
  vm.runInNewContext(boot, { document, window, localStorage: storage })
  assert.equal(dark, false, 'boot follows system with blocked storage')
})
