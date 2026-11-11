import { Plus } from 'lucide-react'
import { useEffect, useState } from 'react'
import { PageHeader } from '@/components/page-header'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
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
import { audienceDescriptions, audienceLabels, noticeDate, type PlatformNotice, type PlatformNoticeAudience, type PlatformNoticePage } from '@/lib/platform-notices'

const AUDIENCE_ITEMS = Object.entries(audienceLabels).map(([value, label]) => ({ value, label }))

/** R-81, S-04: platform-wide sent notices, without gym information. */
export default function AdminNotices() {
  const [data, setData] = useState<PlatformNoticePage | null>(null)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [version, setVersion] = useState(0)
  const [composing, setComposing] = useState(false)

  useEffect(() => {
    let live = true
    setData(null)
    setError('')
    api<PlatformNoticePage>(`/admin/notices?page=${page}`)
      .then((result) => live && setData(result))
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'Platform notices could not be loaded.'))
    return () => { live = false }
  }, [page, version])

  return <div className="flex min-w-0 flex-col gap-6">
    <PageHeader title="Platform notices" description="Send platform-wide updates to owners or verified users. Sent notices cannot be edited or recalled."
      actions={<Button onClick={() => setComposing(true)}><Plus aria-hidden />Send notice</Button>} />
    {error ? <Alert variant="error"><AlertTitle>Platform notices could not be loaded</AlertTitle><AlertDescription>
      <p>{error}</p><Button variant="outline" onClick={() => setVersion((value) => value + 1)}>Try again</Button>
    </AlertDescription></Alert>
      : !data ? <div className="flex flex-col gap-2" aria-busy="true" aria-label="Loading platform notices"><Skeleton className="h-20" /><Skeleton className="h-20" /></div>
        : data.notices.length === 0 ? <Empty className="rounded-xl border"><EmptyHeader><EmptyTitle>No platform notices sent</EmptyTitle><EmptyDescription>Sent notices will appear here with their audience and delivery count.</EmptyDescription></EmptyHeader></Empty>
          : <>
            <div className="hidden md:block"><Table variant="card">
              <TableHeader><TableRow><TableHead className="min-w-80">Notice</TableHead><TableHead>Audience</TableHead><TableHead>Recipients</TableHead><TableHead>Sent</TableHead></TableRow></TableHeader>
              <TableBody>{data.notices.map((notice) => <TableRow key={notice.id}>
                <TableCell className="min-w-80 align-top whitespace-normal"><NoticeContent notice={notice} /></TableCell>
                <TableCell className="align-top whitespace-normal">{audienceLabels[notice.audience]}</TableCell>
                <TableCell className="align-top tabular-nums">{notice.recipients_count}</TableCell>
                <TableCell className="align-top whitespace-normal text-muted-foreground">{noticeDate(notice.created_at)}</TableCell>
              </TableRow>)}</TableBody>
            </Table></div>
            <div className="flex flex-col gap-3 md:hidden">{data.notices.map((notice) => <article key={notice.id} className="min-w-0 rounded-xl border p-4 text-sm">
              <NoticeContent notice={notice} />
              <p className="mt-4">{audienceLabels[notice.audience]} · {notice.recipients_count} recipients</p>
              <p className="mt-2 text-muted-foreground">Sent {noticeDate(notice.created_at)}</p>
            </article>)}</div>
            <nav aria-label="Platform notice pages" className="flex flex-wrap items-center justify-between gap-3 text-sm">
              <span className="text-muted-foreground">Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} sent</span>
              <div className="flex gap-2"><Button variant="outline" disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Previous</Button>
                <Button variant="outline" disabled={page >= data.meta.last_page} onClick={() => setPage((value) => value + 1)}>Next</Button></div>
            </nav>
          </>}
    {composing && <ComposeDialog onClose={() => setComposing(false)} onSent={() => {
      setComposing(false)
      setPage(1)
      setVersion((value) => value + 1)
    }} />}
  </div>
}

function NoticeContent({ notice }: { notice: PlatformNotice }) {
  return <div className="min-w-0 break-words [overflow-wrap:anywhere]"><h2 className="font-medium">{notice.title}</h2>
    <p className="mt-2 whitespace-pre-wrap text-muted-foreground">{notice.body}</p>
    {notice.recipients_count === 0 && <p className="mt-2 text-sm text-muted-foreground">No recipients matched this audience. This notice was saved but no inboxes received it.</p>}
  </div>
}

function ComposeDialog({ onClose, onSent }: { onClose: () => void; onSent: () => void }) {
  const [title, setTitle] = useState('')
  const [body, setBody] = useState('')
  const [audience, setAudience] = useState<PlatformNoticeAudience>('owners')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function send(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (busy) return
    setBusy(true)
    setErrors({})
    try {
      const result = await api<{ notice: PlatformNotice }>('/admin/notices', { body: { title, body, audience } })
      toastManager.add({ title: result.notice.recipients_count === 0 ? 'Notice saved. No recipients matched this audience.' : `Notice sent to ${result.notice.recipients_count} recipients.`, type: result.notice.recipients_count === 0 ? 'warning' : 'success' })
      onSent()
    } catch (failure) {
      setErrors(failure instanceof ApiError ? failure.errors : {})
      if (!(failure instanceof ApiError) || Object.keys(failure.errors).length === 0) {
        toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Notice could not be sent.', type: 'error' })
      }
    } finally { setBusy(false) }
  }

  return <Dialog open onOpenChange={(open) => !open && !busy && onClose()}><DialogContent>
    <DialogHeader><DialogTitle>Send platform notice</DialogTitle><DialogDescription>Recipients receive an inbox notice immediately. Do not include any gym’s private information.</DialogDescription></DialogHeader>
    <Form errors={errors} onSubmit={send}><DialogPanel className="flex flex-col gap-4">
      <fieldset disabled={busy} className="flex min-w-0 flex-col gap-4">
        <TextField name="title" label="Title" value={title} onChange={(event) => setTitle(event.target.value)} required maxLength={120} autoFocus />
        <Field name="body"><FieldLabel>Message</FieldLabel><Textarea name="body" value={body} onChange={(event) => setBody(event.target.value)} required maxLength={2000} rows={4} /><FieldDescription>Up to 2,000 characters.</FieldDescription><FieldError /></Field>
        <Field name="audience"><FieldLabel>Audience</FieldLabel><Select items={AUDIENCE_ITEMS} value={audience} onValueChange={(value) => { setAudience(value as PlatformNoticeAudience); setErrors({}) }}>
          <SelectTrigger aria-label="Audience"><SelectValue /></SelectTrigger><SelectPopup>{AUDIENCE_ITEMS.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}</SelectPopup>
        </Select><FieldDescription>{audienceDescriptions[audience]} Your account is excluded.</FieldDescription><FieldError /></Field>
        <p className="text-sm text-muted-foreground">If no recipients match, the notice is still saved with a delivery count of zero.</p>
      </fieldset>
    </DialogPanel><DialogFooter><Button type="button" variant="outline" disabled={busy} onClick={onClose}>Cancel</Button><Button type="submit" loading={busy}>Send notice</Button></DialogFooter></Form>
  </DialogContent></Dialog>
}
