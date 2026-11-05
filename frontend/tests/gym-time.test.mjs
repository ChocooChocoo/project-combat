import assert from 'node:assert/strict'
import test from 'node:test'
import { dateLabel, dateTimeLabel, gymDate } from '../src/lib/membership.ts'

test('wall time and offset timestamps display the same Philippine clock', () => {
  const wallTime = '2026-11-05 14:54:33'
  const utc = '2026-11-05T06:54:33.000000Z'
  const manila = '2026-11-05T14:54:33+08:00'
  assert.equal(gymDate(wallTime).toISOString(), '2026-11-05T06:54:33.000Z')
  assert.equal(dateTimeLabel(wallTime), dateTimeLabel(utc))
  assert.equal(dateTimeLabel(wallTime), dateTimeLabel(manila))
  assert.match(dateTimeLabel(wallTime), /2:54 PM/)
})

test('Philippine midnight retains its gym date regardless of browser timezone', () => {
  assert.equal(dateLabel('2026-11-05T16:30:00Z'), dateLabel('2026-11-06'))
  assert.equal(dateLabel('2026-11-06 00:30:00'), dateLabel('2026-11-06'))
  assert.equal(dateTimeLabel(null), '')
  assert.equal(dateTimeLabel('not a date'), 'not a date')
})
