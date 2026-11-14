import assert from 'node:assert/strict'
import test from 'node:test'
import { BLUR_MIN, findExpiryDate, laplacianVariance, nameFlags } from '../src/lib/document-check.ts'

test('findExpiryDate reads common forms', () => {
  assert.equal(findExpiryDate('VALID UNTIL DECEMBER 31, 2027'), '2027-12-31')
  assert.equal(findExpiryDate('Issued 01/05/2026 Expiry Date: 2027/01/04'), '2027-01-04')
  assert.equal(findExpiryDate('12 MAR 2028'), '2028-03-12')
  assert.equal(findExpiryDate('Business permit, no dates here'), null)
})

test('nameFlags finds the owner and flags a missing gym name', () => {
  const text = 'This is to certify that JUAN REYES operates a business at Dasmarinas Cavite under permit 12345'
  assert.deepEqual(nameFlags(text, ['owner'], { owner: 'Reyes', gym: 'Iron Fist Gym' }), [])
  assert.deepEqual(nameFlags(text, ['owner', 'gym'], { owner: 'Reyes', gym: 'Iron Fist Gym' }), ['gym_name'])
  assert.deepEqual(nameFlags('abc', ['gym'], { owner: 'Reyes', gym: 'Iron Fist Gym' }), ['no_text'])
})

test('laplacianVariance is zero for a flat image and high for a checkerboard', () => {
  assert.equal(laplacianVariance(new Array(100).fill(128), 10, 10), 0)
  const board = Array.from({ length: 100 }, (_, i) => ((i % 10) + Math.floor(i / 10)) % 2 === 0 ? 0 : 255)
  assert.ok(laplacianVariance(board, 10, 10) > BLUR_MIN)
})
