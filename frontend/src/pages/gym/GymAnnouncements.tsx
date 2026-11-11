import { Megaphone, Plus } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { announcementDate, audienceLabels, eventTimestamp, kindLabels, type Announcement, type AnnouncementAudience, type AnnouncementKind, type AnnouncementOptions, type AnnouncementPage } from '@/lib/announcements'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'

const KIND_ITEMS = Object.entries(kindLabels).map(([value, label]) => ({ value, label }))
const AUDIENCE_ITEMS = Object.entries(audienceLabels).map(([value, label]) => ({ value, label }))

/** R-80, S-35: sent announcements, with no drafts or edits after delivery. */
export default function GymAnnouncements() {
  const { gymId } = useParams()
  const { user } = useAuth()
  const gym = approvedGym(user, Number(gymId))
  const canView = Boolean(gym && can(gym, 'announcements.general.view'))
  const canManage = Boolean(gym && can(gym, 'announcements.general.manage'))
  const [data, setData] = useState<AnnouncementPage | null>(null)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [version, setVersion] = useState(0)
  const [composing, setComposing] = useState(false)

  useEffect(() => {
    if (!canView) return
    let live = true
    setData(null)
    setError('')
    api<AnnouncementPage>(`/gyms/${gymId}/announcements?page=${page}`)
      .then((result) => live && setData(result))
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'Announcements could not be loaded.'))
    return () => { live = false }
  }, [gymId, page, version, canView])

  if (!gym) return null
  if (!canView) return (
    <Empty>
      <EmptyHeader>
        <EmptyTitle>Announcements are not available</EmptyTitle>
        <EmptyDescription>You do not have permission to view this gym’s announcements.</EmptyDescription>
      </EmptyHeader>
    </Empty>
  )

  return (
    <div className="flex min-w-0 flex-col gap-6">
      <PageHeader title="Announcements" description="Send training updates and events to the members who need them. Sent announcements cannot be edited."
        actions={canManage && <Button onClick={() => setComposing(true)}><Plus aria-hidden /> Send announcement</Button>} />
      {error ? (
        <Alert variant="error"><AlertTitle>Announcements could not be loaded</AlertTitle><AlertDescription>
          <p>{error}</p><Button variant="outline" onClick={() => setVersion((value) => value + 1)}>Try again</Button>
        </AlertDescription></Alert>
      ) : !data ? (
        <div className="flex flex-col gap-2" aria-busy="true" aria-label="Loading announcements"><Skeleton className="h-20" /><Skeleton className="h-20" /></div>
      ) : data.announcements.length === 0 ? (
        <Empty className="rounded-xl border"><EmptyHeader><Megaphone aria-hidden className="size-6 text-muted-foreground" />
          <EmptyTitle>No announcements sent</EmptyTitle><EmptyDescription>Sent updates and events will appear here with their delivery counts.</EmptyDescription>
        </EmptyHeader></Empty>
      ) : (
        <>
          <div className="hidden md:block">
            <Table variant="card">
              <TableHeader><TableRow><TableHead className="min-w-80">Announcement</TableHead><TableHead>Audience</TableHead><TableHead>Delivery</TableHead><TableHead>Sent</TableHead></TableRow></TableHeader>
              <TableBody>{data.announcements.map((announcement) => (
                <TableRow key={announcement.id}>
                  <TableCell className="min-w-80 align-top whitespace-normal"><AnnouncementContent announcement={announcement} /></TableCell>
                  <TableCell className="align-top whitespace-normal"><AudienceContent announcement={announcement} /></TableCell>
                  <TableCell className="align-top whitespace-normal"><DeliveryContent announcement={announcement} /></TableCell>
                  <TableCell className="align-top whitespace-normal text-muted-foreground">{announcementDate(announcement.created_at)}<span className="mt-1 block break-words">{announcement.created_by_name}</span></TableCell>
                </TableRow>
              ))}</TableBody>
            </Table>
          </div>
          <div className="flex flex-col gap-3 md:hidden">{data.announcements.map((announcement) => (
            <article key={announcement.id} className="min-w-0 rounded-xl border p-4 text-sm">
              <AnnouncementContent announcement={announcement} />
              <div className="mt-4 grid grid-cols-2 gap-3"><AudienceContent announcement={announcement} /><DeliveryContent announcement={announcement} /></div>
              <p className="mt-3 break-words text-xs text-muted-foreground">Sent {announcementDate(announcement.created_at)}{announcement.created_by_name && ` by ${announcement.created_by_name}`}</p>
            </article>
          ))}</div>
          <nav aria-label="Announcement pages" className="flex flex-wrap items-center justify-between gap-3 text-sm">
            <span className="text-muted-foreground">Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} sent</span>
            <div className="flex gap-2"><Button variant="outline" disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Previous</Button>
              <Button variant="outline" disabled={page >= data.meta.last_page} onClick={() => setPage((value) => value + 1)}>Next</Button></div>
          </nav>
        </>
      )}
      {composing && canManage && <ComposeDialog key={gymId} gymId={gym.id} onClose={() => setComposing(false)} onSent={() => {
        setComposing(false)
        setPage(1)
        setVersion((value) => value + 1)
      }} />}
    </div>
  )
}

function AnnouncementContent({ announcement }: { announcement: Announcement }) {
  return <div className="flex min-w-0 flex-col gap-2 break-words [overflow-wrap:anywhere]">
    <div><Badge variant="secondary">{kindLabels[announcement.kind]}</Badge><h2 className="mt-2 font-medium">{announcement.title}</h2></div>
    <p className="whitespace-pre-wrap text-muted-foreground">{announcement.body}</p>
    {announcement.kind === 'event' && <p className="text-sm">{announcementDate(announcement.event_starts_at)}<span className="block">{announcement.event_place}</span></p>}
  </div>
}

function AudienceContent({ announcement }: { announcement: Announcement }) {
  return <div className="break-words text-sm [overflow-wrap:anywhere]">{audienceLabels[announcement.audience_type]}
    <span className="mt-1 block text-muted-foreground">{announcement.session_name ?? announcement.group_name}</span>
  </div>
}

function DeliveryContent({ announcement }: { announcement: Announcement }) {
  return <div className="text-sm"><p className="tabular-nums">{announcement.recipients_count} recipients</p>
    <p className="mt-1 text-xs text-muted-foreground">{announcement.skipped_count} skipped (customers without an account)</p>
  </div>
}

function ComposeDialog({ gymId, onClose, onSent }: { gymId: number; onClose: () => void; onSent: () => void }) {
  const [options, setOptions] = useState<AnnouncementOptions | null>(null)
  const [optionsError, setOptionsError] = useState('')
  const [version, setVersion] = useState(0)
  const [title, setTitle] = useState('')
  const [body, setBody] = useState('')
  const [kind, setKind] = useState<AnnouncementKind>('announcement')
  const [audience, setAudience] = useState<AnnouncementAudience>('all_members')
  const [target, setTarget] = useState<string | null>(null)
  const [eventDate, setEventDate] = useState('')
  const [eventPlace, setEventPlace] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let live = true
    api<AnnouncementOptions>(`/gyms/${gymId}/announcements/options`)
      .then((result) => live && setOptions(result))
      .catch((failure) => live && setOptionsError(failure instanceof ApiError ? failure.message : 'Audience choices could not be loaded.'))
    return () => { live = false }
  }, [gymId, version])

  const targetItems = audience === 'session'
    ? (options?.sessions ?? []).map((session) => ({ value: String(session.id), label: `${session.name} · ${announcementDate(session.starts_at)} · ${session.booked_count} booked${session.room_name ? ` · ${session.room_name}` : ''}` }))
    : (options?.groups ?? []).map((group) => ({ value: String(group.id), label: `${group.name} · ${group.members_count} customers` }))
  const emptyAudience = options && (audience === 'all_members' ? options.member_count === 0 : targetItems.length === 0)

  async function send(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    setErrors({})
    try {
      await api(`/gyms/${gymId}/announcements`, { body: {
        title, body, kind, audience_type: audience,
        ...(kind === 'event' ? { event_starts_at: eventTimestamp(eventDate), event_place: eventPlace } : {}),
        ...(audience === 'session' ? { training_session_id: Number(target) } : {}),
        ...(audience === 'customer_group' ? { customer_group_id: Number(target) } : {}),
      } })
      toastManager.add({ title: 'Announcement sent.', type: 'success' })
      onSent()
    } catch (failure) {
      setErrors(failure instanceof ApiError ? failure.errors : {})
      if (!(failure instanceof ApiError) || Object.keys(failure.errors).length === 0) {
        toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Announcement could not be sent.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  return <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
    <DialogContent>
      <DialogHeader><DialogTitle>Send announcement</DialogTitle><DialogDescription>Recipients receive an inbox notice immediately. You cannot edit or recall it after sending.</DialogDescription></DialogHeader>
      <Form errors={errors} onSubmit={send}>
        <DialogPanel className="flex flex-col gap-4">
          {optionsError ? <Alert variant="error"><AlertTitle>Audience choices unavailable</AlertTitle><AlertDescription>
            <p>{optionsError}</p><Button type="button" variant="outline" onClick={() => { setOptionsError(''); setVersion((value) => value + 1) }}>Try again</Button>
          </AlertDescription></Alert> : !options && <div aria-busy="true" aria-label="Loading audiences"><Skeleton className="h-12" /></div>}
          <fieldset disabled={busy} className="flex min-w-0 flex-col gap-4">
            <TextField name="title" label="Title" value={title} onChange={(event) => setTitle(event.target.value)} required maxLength={120} autoFocus />
            <Field name="body"><FieldLabel>Message</FieldLabel><Textarea name="body" value={body} onChange={(event) => setBody(event.target.value)} required maxLength={2000} rows={4} /><FieldDescription>Up to 2,000 characters.</FieldDescription><FieldError /></Field>
            <Field name="kind"><FieldLabel>Kind</FieldLabel><Select items={KIND_ITEMS} value={kind} onValueChange={(value) => { setKind(value as AnnouncementKind); setErrors({}) }}>
              <SelectTrigger aria-label="Kind"><SelectValue /></SelectTrigger><SelectPopup>{KIND_ITEMS.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}</SelectPopup>
            </Select><FieldError /></Field>
            {kind === 'event' && <>
              <TextField name="event_starts_at" label="Event date and time" type="datetime-local" value={eventDate} onChange={(event) => setEventDate(event.target.value)} required description="Philippine time (Asia/Manila). Choose a future date and time." />
              <TextField name="event_place" label="Event place" value={eventPlace} onChange={(event) => setEventPlace(event.target.value)} required maxLength={180} />
            </>}
            <Field name="audience_type"><FieldLabel>Audience</FieldLabel><Select items={AUDIENCE_ITEMS} value={audience} onValueChange={(value) => { setAudience(value as AnnouncementAudience); setTarget(null); setErrors({}) }}>
              <SelectTrigger aria-label="Audience"><SelectValue /></SelectTrigger><SelectPopup>{AUDIENCE_ITEMS.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}</SelectPopup>
            </Select><FieldError /></Field>
            {audience === 'all_members' ? <p className="text-sm text-muted-foreground">{options ? `${options.member_count} members with active or frozen memberships.` : 'Loading member count…'}</p> : (
              <Field name={audience === 'session' ? 'training_session_id' : 'customer_group_id'}>
                <FieldLabel>{audience === 'session' ? 'Class occurrence' : 'Customer group'}</FieldLabel>
                <Select items={targetItems} value={target} onValueChange={setTarget} disabled={!options || targetItems.length === 0} required>
                  <SelectTrigger aria-label={audience === 'session' ? 'Class occurrence' : 'Customer group'}><SelectValue placeholder={audience === 'session' ? 'Choose a class' : 'Choose a group'} /></SelectTrigger>
                  <SelectPopup className="w-(--anchor-width) max-w-[calc(100vw-2rem)]">{targetItems.map((item) => <SelectItem className="whitespace-normal break-words" key={item.value} value={item.value}>{item.label}</SelectItem>)}</SelectPopup>
                </Select>
                <FieldDescription>{audience === 'session' ? 'Only booked athletes receive the notice. Showing upcoming scheduled classes within 90 days.' : 'Customers without an account are skipped and counted after sending.'}</FieldDescription><FieldError />
              </Field>
            )}
            {emptyAudience && <p role="status" className="text-sm text-muted-foreground">{audience === 'all_members' ? 'No active or frozen members to notify.' : audience === 'session' ? 'No upcoming scheduled classes available.' : 'No customer groups available.'}</p>}
          </fieldset>
        </DialogPanel>
        <DialogFooter><Button type="button" variant="outline" disabled={busy} onClick={onClose}>Cancel</Button>
          <Button type="submit" loading={busy} disabled={!options || Boolean(optionsError) || Boolean(emptyAudience) || (audience !== 'all_members' && !target)}>Send announcement</Button></DialogFooter>
      </Form>
    </DialogContent>
  </Dialog>
}
