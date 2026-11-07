import assert from 'node:assert/strict'
import test from 'node:test'
import { feeRange, peso, photoUrl, planLength, sessionTime } from '../src/lib/landing.ts'

const stats = {
  gyms: 5,
  cities: 5,
  disciplines: 9,
  services: 28,
  plans: 16,
  coaches: 11,
  upcoming_sessions: 73,
  plan_fee_min: '1500.00',
  plan_fee_max: '32000.00',
}

const plan = {
  id: 1,
  gym_id: 1,
  gym_name: 'Iron Will Boxing Gym',
  name: 'Monthly Unlimited',
  billing_type: 'time',
  duration_months: 1,
  session_count: null,
  total_fee: '2800.00',
  inclusions: [],
}

test('plan fees print whole pesos without centavos and keep real centavos', () => {
  assert.equal(peso(1500), '₱1,500')
  assert.equal(peso('2800.50'), '₱2,800.50')
  assert.equal(peso(null), '')
  assert.equal(feeRange(stats), '₱1,500-₱32,000')
  assert.equal(feeRange({ ...stats, plan_fee_min: null }), '')
})

test('a plan length describes months or sessions', () => {
  assert.equal(planLength(plan), '1 month')
  assert.equal(planLength({ ...plan, billing_type: 'sessions', duration_months: null, session_count: 12 }), '12 sessions')
})

test('session times show the Manila clock', () => {
  const label = sessionTime('2026-11-09T06:00:00+08:00')
  assert.match(label, /Nov 9/)
  assert.match(label, /6:00\sAM/)
  assert.equal(sessionTime('not a date'), 'not a date')
})

test('photo links ask Pexels for the size and crop the section needs', () => {
  const url = photoUrl(35045523, 1000, 1250)
  assert.match(url, /photos\/35045523\/pexels-photo-35045523\.jpeg/)
  assert.match(url, /w=1000/)
  assert.match(url, /h=1250/)
  assert.match(url, /fit=crop/)
})
