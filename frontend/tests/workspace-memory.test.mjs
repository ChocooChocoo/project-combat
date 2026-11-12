import assert from 'node:assert/strict'
import test from 'node:test'

const store = new Map()
globalThis.localStorage = {
  getItem: (key) => (store.has(key) ? store.get(key) : null),
  setItem: (key, value) => store.set(key, String(value)),
  removeItem: (key) => store.delete(key),
}

const { homePath, rememberGym } = await import('../src/lib/gym-access.ts')

const gym = (id, roles, permissions = []) => ({ id, status: 'approved', roles, permissions })
const user = (id, gyms) => ({ id, gyms })

test('saved gym opens its entry page after sign-in', () => {
  store.clear()
  const person = user(1, [gym(5, ['owner']), gym(9, ['member'])])
  assert.equal(homePath(person), '/gyms/5/manage')
  rememberGym(1, 9)
  assert.equal(homePath(person), '/gyms/9/my-record')
})

test('stale saved gym is ignored', () => {
  store.clear()
  rememberGym(1, 42)
  assert.equal(homePath(user(1, [gym(9, ['member'])])), '/')
  assert.equal(homePath(user(1, [{ ...gym(42, ['employee']), status: 'pending' }])), '/')
})

test("another user's saved gym is ignored", () => {
  store.clear()
  rememberGym(2, 9)
  assert.equal(homePath(user(1, [gym(9, ['member'])])), '/')
  assert.equal(homePath(user(2, [gym(9, ['employee'])])), '/gyms/9/manage/my-work')
})
