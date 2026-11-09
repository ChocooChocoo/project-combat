import assert from 'node:assert/strict'
import test from 'node:test'
import { addDays, mondayOf, monthKey, percent } from '../src/lib/dashboard.ts'

test('calendar date helpers cross month and year edges', () => {
  assert.equal(addDays('2026-12-30', 3), '2027-01-02')
  assert.equal(addDays('2026-03-01', -1), '2026-02-28')
  assert.equal(mondayOf('2026-11-09'), '2026-11-09') // a Monday
  assert.equal(mondayOf('2026-11-08'), '2026-11-02') // a Sunday
  assert.equal(monthKey('2026-11-30', -5), '2026-06')
  assert.equal(monthKey('2026-02-15', -3), '2025-11')
})

test('percent is null without a whole to compare against', () => {
  assert.equal(percent(1, 0), null)
  assert.equal(percent(2, 3), 67)
})
