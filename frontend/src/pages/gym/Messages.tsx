import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { usePolling } from '@/hooks/use-polling'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError } from '@/lib/api'
import { conversationLine, messageTimeLabel, roleLabel, type ChatConversation, type ChatMessage, type ChatPartner, type ChatThread } from '@/lib/chat'
import { pageMetaFrom, type PageMeta } from '@/lib/pagination'

type Conversations = { conversations: ChatConversation[] }
type StartedConversation = { conversation: ChatConversation }
type Partners = { partners: ChatPartner[] }

/**
 * R-145: chat inside one gym. A member writes to the staff, the coaches and the owner; staff and the
 * owner write to everyone; no member reaches another member. On a phone the list leads to one thread;
 * from `md` up the two sit side by side.
 */
export default function Messages() {
  const gymId = Number(useParams().gymId)
  const conversationId = Number(useParams().conversationId) || null
  const [conversations, setConversations] = useState<ChatConversation[] | null>(null)
  const [error, setError] = useState('')
  const [picking, setPicking] = useState(false)

  const load = useCallback(async () => {
    try {
      setConversations((await api<Conversations>(`/gyms/${gymId}/chat/conversations`)).conversations)
      setError('')
    } catch (failure) {
      setConversations([])
      setError(failure instanceof ApiError ? failure.message : 'Messages could not be loaded.')
    }
  }, [gymId])

  useEffect(() => {
    void load()
  }, [load])

  return (
    <div className="flex min-w-0 flex-col gap-6">
      <PageHeader
        title="Messages"
        description="Talk to this gym's staff, coaches and owner. Members cannot message other members."
        actions={<Button onClick={() => setPicking(true)}>New message</Button>}
      />

      <div className="flex min-w-0 flex-col gap-6 md:flex-row md:items-start">
        <section className={conversationId ? 'hidden md:block md:w-80 md:shrink-0' : 'min-w-0 md:w-80 md:shrink-0'} aria-label="Conversations">
          {error ? (
            <Alert variant="error">
              <AlertTitle>Messages could not be loaded</AlertTitle>
              <AlertDescription>
                <p>{error}</p>
                <Button variant="outline" size="sm" onClick={() => void load()}>Try again</Button>
              </AlertDescription>
            </Alert>
          ) : !conversations ? (
            <div className="flex flex-col gap-2" aria-busy="true" aria-label="Loading conversations">
              <Skeleton className="h-16" />
              <Skeleton className="h-16" />
            </div>
          ) : conversations.length === 0 ? (
            <Empty className="rounded-xl border bg-background">
              <EmptyHeader>
                <EmptyTitle>No messages yet</EmptyTitle>
                <EmptyDescription>Start a chat with the gym staff or the owner.</EmptyDescription>
              </EmptyHeader>
              <Button variant="outline" onClick={() => setPicking(true)}>New message</Button>
            </Empty>
          ) : (
            <ul className="flex flex-col divide-y rounded-xl border bg-background">
              {conversations.map((conversation) => (
                <li key={conversation.id}>
                  <Link
                    className={`flex min-w-0 flex-col gap-1 p-4 hover:bg-accent/50 ${conversation.id === conversationId ? 'bg-accent/50' : ''}`}
                    to={`/gyms/${gymId}/messages/${conversation.id}`}
                  >
                    <span className="flex min-w-0 items-center gap-2">
                      <span className="truncate font-medium">{conversation.partner.name}</span>
                      <Badge variant="secondary" size="sm">{roleLabel(conversation.partner.role_label)}</Badge>
                      {conversation.unread_count > 0 && (
                        <Badge className="ms-auto" size="sm" aria-label={`${conversation.unread_count} unread`}>
                          {conversation.unread_count > 99 ? '99+' : conversation.unread_count}
                        </Badge>
                      )}
                    </span>
                    <span className="line-clamp-1 text-muted-foreground text-sm">{conversationLine(conversation)}</span>
                    <span className="text-muted-foreground text-xs">{messageTimeLabel(conversation.last_message?.sent_at)}</span>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </section>

        {conversationId ? (
          <Thread key={conversationId} gymId={gymId} conversationId={conversationId} onSeen={load} />
        ) : (
          <div className="hidden min-w-0 flex-1 md:block">
            <Empty className="rounded-xl border bg-background">
              <EmptyHeader>
                <EmptyTitle>Pick a conversation</EmptyTitle>
                <EmptyDescription>Choose a thread on the left, or start a new message.</EmptyDescription>
              </EmptyHeader>
            </Empty>
          </div>
        )}
      </div>

      {picking && <NewMessageDialog gymId={gymId} onClose={() => setPicking(false)} />}
    </div>
  )
}

/** One thread: the messages oldest first, the composer, "load older" and the read call while it is open. */
function Thread({ gymId, conversationId, onSeen }: { gymId: number; conversationId: number; onSeen: () => Promise<void> }) {
  const [thread, setThread] = useState<ChatThread | null>(null)
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [meta, setMeta] = useState<PageMeta | null>(null)
  const [loadingOlder, setLoadingOlder] = useState(false)
  const [draft, setDraft] = useState('')
  const [sending, setSending] = useState(false)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const bottom = useRef<HTMLDivElement | null>(null)

  /** The server sends newest first; every page is folded in by id, so the thread never jumps. */
  const merge = useCallback((incoming: ChatMessage[]) => {
    setMessages((current) => {
      const byId = new Map(current.map((message) => [message.id, message]))
      incoming.forEach((message) => byId.set(message.id, message))
      return [...byId.values()].sort((a, b) => a.id - b.id)
    })
  }, [])

  const load = useCallback(async (silent: boolean, isCurrent: () => boolean) => {
    try {
      const result = await api<ChatThread>(`/gyms/${gymId}/chat/conversations/${conversationId}?page=1`)
      if (!isCurrent()) return
      setThread(result)
      setMeta(pageMetaFrom(result, result.messages.length))
      merge(result.messages)
      setError('')
      if (result.conversation.unread_count > 0) {
        await api(`/gyms/${gymId}/chat/conversations/${conversationId}/read`, { method: 'POST' })
        await onSeen()
      }
    } catch (failure) {
      if (isCurrent() && !silent) setError(failure instanceof ApiError ? failure.message : 'This conversation could not be loaded.')
    }
  }, [gymId, conversationId, merge, onSeen])

  usePolling(load, 15_000, true, version)

  useEffect(() => {
    bottom.current?.scrollIntoView({ block: 'nearest' })
  }, [messages.length])

  /** Older pages come from the server with `?page=`; they are added to the thread already on screen. */
  const older = async (page: number) => {
    if (loadingOlder) return
    setLoadingOlder(true)
    try {
      const result = await api<ChatThread>(`/gyms/${gymId}/chat/conversations/${conversationId}?page=${page}`)
      setMeta(pageMetaFrom(result, result.messages.length))
      merge(result.messages)
    } catch (failure) {
      toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Older messages could not be loaded.', type: 'error' })
    } finally {
      setLoadingOlder(false)
    }
  }

  const send = async () => {
    const body = draft.trim()
    if (!body || sending) return
    setSending(true)
    try {
      const result = await api<{ message: ChatMessage; conversation: ChatConversation }>(
        `/gyms/${gymId}/chat/conversations/${conversationId}/messages`,
        { body: { body } },
      )
      merge([result.message])
      setThread((current) => (current ? { ...current, conversation: result.conversation } : current))
      setDraft('')
      await onSeen()
    } catch (failure) {
      toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Your message could not be sent.', type: 'error' })
    } finally {
      setSending(false)
    }
  }

  if (error) {
    return (
      <Alert variant="error" className="min-w-0 flex-1">
        <AlertTitle>This conversation could not be loaded</AlertTitle>
        <AlertDescription>
          <p>{error}</p>
          <Button variant="outline" onClick={() => setVersion((value) => value + 1)}>Try again</Button>
        </AlertDescription>
      </Alert>
    )
  }

  return (
    <section className="flex min-w-0 flex-1 flex-col gap-4" aria-label="Conversation">
      <div className="flex flex-wrap items-center gap-3">
        <Button variant="outline" size="sm" className="md:hidden" render={<Link to={`/gyms/${gymId}/messages`} />}>All messages</Button>
        <div className="flex min-w-0 flex-col">
          <h2 className="truncate font-semibold">{thread?.conversation.partner.name ?? 'Conversation'}</h2>
          {thread && <span className="text-muted-foreground text-sm">{roleLabel(thread.conversation.partner.role_label)}</span>}
        </div>
      </div>

      {!thread ? (
        <div className="flex flex-col gap-2" aria-busy="true" aria-label="Loading conversation">
          <Skeleton className="h-20" />
          <Skeleton className="h-20" />
        </div>
      ) : messages.length === 0 ? (
        <Empty className="rounded-xl border bg-background">
          <EmptyHeader>
            <EmptyTitle>No messages yet</EmptyTitle>
            <EmptyDescription>Write the first message to {thread.conversation.partner.name}.</EmptyDescription>
          </EmptyHeader>
        </Empty>
      ) : (
        <ol className="flex flex-col gap-2">
          {messages.map((message) => (
            <li key={message.id} className={message.from_me ? 'flex flex-col items-end' : 'flex flex-col items-start'}>
              <div className={`max-w-[85%] rounded-xl border px-3 py-2 ${message.from_me ? 'bg-accent' : 'bg-muted/64'}`}>
                <p className="whitespace-pre-wrap break-words text-sm [overflow-wrap:anywhere]">{message.body}</p>
                <p className="mt-1 text-muted-foreground text-xs">
                  {message.from_me ? 'You' : message.sender_name} · {messageTimeLabel(message.sent_at)}
                </p>
              </div>
            </li>
          ))}
        </ol>
      )}

      {/* R-123: with `?page=` the server also sends meta, and older messages are added to this thread. */}
      {meta && meta.last_page > 1 && (
        <Button variant="outline" size="sm" className="self-center" loading={loadingOlder} disabled={loadingOlder || meta.current_page >= meta.last_page} onClick={() => void older(meta.current_page + 1)}>
          Load older messages
        </Button>
      )}

      <div className="flex flex-col gap-3 rounded-xl border bg-background p-3">
        <Textarea
          name="body"
          aria-label="Write a message"
          placeholder="Write a message"
          value={draft}
          maxLength={2000}
          onChange={(event) => setDraft(event.target.value)}
        />
        <div className="flex flex-wrap items-center justify-between gap-3">
          <span className="text-muted-foreground text-xs">{draft.length}/2000</span>
          <Button loading={sending} disabled={sending || draft.trim() === ''} onClick={send}>Send</Button>
        </div>
      </div>
      <div ref={bottom} />
    </section>
  )
}

/** Picks one person to chat with: an existing thread opens, otherwise it is started now. */
function NewMessageDialog({ gymId, onClose }: { gymId: number; onClose: () => void }) {
  const navigate = useNavigate()
  const [partners, setPartners] = useState<ChatPartner[] | null>(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let live = true
    api<Partners>(`/gyms/${gymId}/chat/partners`)
      .then((result) => live && setPartners(result.partners))
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'People you can message could not be loaded.'))
    return () => { live = false }
  }, [gymId])

  const open = async (partner: ChatPartner) => {
    if (partner.conversation_id) {
      onClose()
      navigate(`/gyms/${gymId}/messages/${partner.conversation_id}`)
      return
    }
    setBusy(true)
    try {
      const result = await api<StartedConversation>(`/gyms/${gymId}/chat/conversations`, { body: { with_user_id: partner.id } })
      onClose()
      navigate(`/gyms/${gymId}/messages/${result.conversation.id}`)
    } catch (failure) {
      toastManager.add({ title: failure instanceof ApiError ? failure.message : 'The chat could not be started.', type: 'error' })
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>New message</DialogTitle>
          <DialogDescription>Choose one person. A member can message the staff, the coaches and the owner.</DialogDescription>
        </DialogHeader>
        <DialogPanel>
          {error ? (
            <Alert variant="error">
              <AlertTitle>People could not be loaded</AlertTitle>
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          ) : !partners ? (
            <div className="flex flex-col gap-2" aria-busy="true" aria-label="Loading people">
              <Skeleton className="h-12" />
              <Skeleton className="h-12" />
            </div>
          ) : partners.length === 0 ? (
            <p className="py-6 text-center text-muted-foreground text-sm">There is no one to message yet.</p>
          ) : (
            <ul className="flex flex-col divide-y">
              {partners.map((partner) => (
                <li key={partner.id} className="flex flex-wrap items-center gap-3 py-3">
                  <span className="flex min-w-0 flex-1 flex-col">
                    <span className="truncate font-medium">{partner.name}</span>
                    <span className="text-muted-foreground text-sm">{roleLabel(partner.role_label)}</span>
                  </span>
                  {partner.unread_count > 0 && <Badge size="sm">{partner.unread_count}</Badge>}
                  <Button variant="outline" size="sm" disabled={busy} onClick={() => void open(partner)}>
                    {partner.conversation_id ? 'Open' : 'Message'}
                  </Button>
                </li>
              ))}
            </ul>
          )}
        </DialogPanel>
        <DialogFooter>
          <Button variant="outline" disabled={busy} onClick={onClose}>Cancel</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
