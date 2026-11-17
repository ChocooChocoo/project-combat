import assert from 'node:assert/strict'
import test from 'node:test'
import { conversationLine, messageTimeLabel, roleLabel, todayInManila } from '../src/lib/chat.ts'
import { dateLabel } from '../src/lib/membership.ts'

function thread(body, fromMe) {
  return {
    id: 1,
    partner: { id: 2, name: 'Carlo Diaz', role_label: 'Coach' },
    last_message: body === null ? null : { body, sent_at: '2026-11-17 09:30:00', from_me: fromMe },
    unread_count: 0,
    updated_at: '2026-11-17 09:30:00',
  }
}

test('the partner role label falls back to member', () => {
  assert.equal(roleLabel('Owner'), 'Owner')
  assert.equal(roleLabel('Coach'), 'Coach')
  assert.equal(roleLabel('Staff'), 'Staff')
  assert.equal(roleLabel('Member'), 'Member')
  assert.equal(roleLabel(null), 'Member')
  assert.equal(roleLabel(''), 'Member')
})

test('a thread line names who spoke last', () => {
  assert.equal(conversationLine(thread('See you at six.', false)), 'Carlo: See you at six.')
  assert.equal(conversationLine(thread('I will be there.', true)), 'You: I will be there.')
  assert.equal(conversationLine(thread(null, false)), 'No messages yet')
})

test('a message shows a clock time today and a date before that', () => {
  const today = '2026-11-17'
  assert.equal(messageTimeLabel('2026-11-17 09:30:00', today), '9:30 AM')
  assert.equal(messageTimeLabel('2026-11-17T01:30:00.000000Z', today), '9:30 AM')
  assert.equal(messageTimeLabel('2026-11-05 14:54:00', today), dateLabel('2026-11-05 14:54:00'))
  assert.equal(messageTimeLabel('', today), '')
  assert.equal(messageTimeLabel(null, today), '')
  assert.equal(messageTimeLabel('not a date', today), 'not a date')
})

test('the gym day follows Manila whatever the machine clock says', () => {
  assert.match(todayInManila(), /^\d{4}-\d{2}-\d{2}$/)
  const utcMidnight = new Date('2026-11-17T16:30:00Z')
  assert.equal(utcMidnight.toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' }), '2026-11-18')
})
