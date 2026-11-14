import assert from 'node:assert/strict'
import test from 'node:test'
import { matchCavitePlace } from '../src/lib/places.ts'
import { ageOn } from '../src/lib/validation.ts'

test('ageOn counts whole years', () => {
  assert.equal(ageOn('2019-11-13', '2026-11-13'), 7)
  assert.equal(ageOn('2019-11-14', '2026-11-13'), 6)
})

test('matchCavitePlace matches a city and barangay by clean name', () => {
  const towns = [{ name: 'Dasmariñas', barangays: ['Salawag', 'Zone I'] }]
  assert.deepEqual(matchCavitePlace({ city: 'City of Dasmariñas', barangay: 'salawag' }, towns), { city: 'Dasmariñas', barangay: 'Salawag' })
  assert.deepEqual(matchCavitePlace({ city: 'Dasmariñas City' }, towns), { city: 'Dasmariñas', barangay: '' })
})

test('matchCavitePlace gives empty strings for an unknown city', () => {
  assert.deepEqual(matchCavitePlace({ city: 'Nowhere' }, [{ name: 'Silang', barangays: [] }]), { city: '', barangay: '' })
})
