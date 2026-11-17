// Shapes and display helpers for the in-gym chat (R-145).
// No `@/` imports, so node --test can load this file.
import { dateLabel, gymDate } from './membership.ts'
import type { PageMeta } from './pagination.ts'

export type ChatPartner = {
  id: number
  name: string
  role_label: string
  conversation_id: number | null
  unread_count: number
}

export type ChatConversation = {
  id: number
  partner: { id: number; name: string; role_label: string }
  last_message: { body: string; sent_at: string; from_me: boolean } | null
  unread_count: number
  updated_at: string
}

export type ChatMessage = {
  id: number
  body: string
  from_me: boolean
  sender_name: string | null
  sent_at: string
  read_at: string | null
}

export type ChatThread = {
  conversation: ChatConversation
  messages: ChatMessage[]
  meta?: PageMeta
}

/** Owner, Coach, Staff or Member. Anything else shows as a member. */
export function roleLabel(role: string | null | undefined): string {
  return role === 'Owner' || role === 'Coach' || role === 'Staff' ? role : 'Member'
}

/** The last message a thread shows under the partner's name. */
export function conversationLine(conversation: ChatConversation): string {
  const last = conversation.last_message
  if (!last) return 'No messages yet'
  const who = last.from_me ? 'You' : conversation.partner.name.split(' ')[0]
  return `${who}: ${last.body}`
}

/** Today's calendar day in the gym's timezone, as Y-m-d. */
export function todayInManila(): string {
  return new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' })
}

/** The clock time for a message sent today, the short date for older ones. */
export function messageTimeLabel(value: string | null | undefined, today?: string): string {
  if (!value) return ''
  const date = gymDate(value)
  if (Number.isNaN(date.getTime())) return value
  const day = date.toLocaleDateString('en-CA', { timeZone: 'Asia/Manila' })
  if (day !== (today ?? todayInManila())) return dateLabel(value)
  return date.toLocaleTimeString('en-PH', { timeZone: 'Asia/Manila', hour: 'numeric', minute: '2-digit' })
}
