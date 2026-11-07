import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { test } from 'node:test'
import vm from 'node:vm'
import { stripTypeScriptTypes } from 'node:module'

const source = stripTypeScriptTypes((await readFile(new URL('../src/hooks/use-polling.ts', import.meta.url), 'utf8'))
  .replace("import { useEffect, useRef } from 'react'", '').replace('export function', 'function')) + '\nexports.usePolling = usePolling'

function harness(visibility = 'visible') {
  let effect, cleanup, timer, interval
  const refs = []
  let cursor = 0
  const window = new EventTarget()
  window.setInterval = (fn, ms) => { timer = fn; interval = ms; return 1 }
  window.clearInterval = () => { timer = undefined }
  const document = new EventTarget()
  document.visibilityState = visibility
  const context = { exports: {}, window, document, useEffect: (fn) => { effect = fn },
    useRef: (value) => refs[cursor++] ?? (refs[cursor - 1] = { current: value }) }
  vm.runInNewContext(source, context)
  return {
    render(callback, enabled = true) {
      cleanup?.()
      cursor = 0
      context.exports.usePolling(callback, 15_000, enabled)
      cleanup = effect()
    },
    tick: () => timer?.(),
    interval: () => interval,
    focus: () => window.dispatchEvent(new Event('focus')),
    visibility(value) { document.visibilityState = value; document.dispatchEvent(new Event('visibilitychange')) },
    stop: () => cleanup?.(),
  }
}

const flush = async () => { for (let i = 0; i < 12; i++) await Promise.resolve() }

test('initial load is normal; interval/focus are silent; hidden and disabled do not fetch', async () => {
  const h = harness('hidden')
  const calls = []
  const load = async (silent) => { calls.push(silent) }
  h.render(load)
  assert.equal(h.interval(), 15_000)
  await flush()
  assert.deepEqual(calls, [])
  h.visibility('visible')
  await flush()
  assert.deepEqual(calls, [false])
  h.tick()
  await flush()
  h.focus()
  await flush()
  assert.deepEqual(calls, [false, true, true])
  h.visibility('hidden')
  h.tick()
  h.focus()
  await flush()
  assert.equal(calls.length, 3)
  h.render(load, false)
  h.visibility('visible')
  h.focus()
  h.tick()
  await flush()
  assert.equal(calls.length, 3)
})

test('never overlaps; changed requests run normally after old request settles and invalidate old results', async () => {
  const h = harness()
  let release, current
  const calls = []
  h.render(async (silent, isCurrent) => {
    calls.push(['old', silent])
    current = isCurrent
    await new Promise((resolve) => { release = resolve })
  })
  await flush()
  h.tick()
  h.focus()
  await flush()
  assert.equal(calls.length, 1)
  h.render(async (silent) => { calls.push(['new', silent]) })
  await flush()
  assert.equal(calls.length, 1)
  assert.equal(current(), false)
  release()
  await flush()
  assert.deepEqual(calls, [['old', false], ['new', false]])
  h.stop()
})

test('rejected requests do not stop future silent polls', async () => {
  const h = harness()
  let count = 0
  h.render(async () => { count++; throw new Error('offline') })
  await flush()
  h.tick()
  await flush()
  assert.equal(count, 2)
  h.stop()
})

test('rapid filter changes coalesce to newest request, including repeated focus while queued', async () => {
  const h = harness()
  let release
  const calls = []
  h.render(async () => { await new Promise((resolve) => { release = resolve }) })
  await flush()
  h.render(async () => { calls.push('obsolete') })
  h.render(async (silent) => { calls.push(silent) })
  for (let i = 0; i < 20; i++) { h.focus(); h.tick() }
  release()
  await flush()
  assert.deepEqual(calls, [false])
  h.visibility('hidden')
  for (let i = 0; i < 4; i++) h.tick()
  await flush()
  assert.deepEqual(calls, [false])
  h.visibility('visible')
  h.focus()
  await flush()
  assert.deepEqual(calls, [false, true])
  h.stop()
})
