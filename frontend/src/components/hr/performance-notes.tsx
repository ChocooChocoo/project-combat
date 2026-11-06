import { useEffect, useState } from 'react'
import { Lock, Plus } from 'lucide-react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { TextField } from '@/components/text-field'
import { isoDay } from '@/components/leave-calendar'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { PERFORMANCE_KIND, initialsOf, workDayLabel, type PerformanceKind, type PerformanceNote } from '@/lib/hr'

const KIND_VARIANT: Record<PerformanceKind, 'success' | 'warning' | 'info' | 'outline'> = {
  commendation: 'success',
  concern: 'warning',
  review: 'info',
  general: 'outline',
}

/** R-58 on the employee record. Never shown on the employee's own screens. */
export function PerformanceNotes({ gymId, userId, canManage }: { gymId: number; userId: number; canManage: boolean }) {
  const [notes, setNotes] = useState<PerformanceNote[] | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [adding, setAdding] = useState(false)

  useEffect(() => {
    let live = true
    setError('')
    api<{ notes: PerformanceNote[] }>(`/gyms/${gymId}/staff/${userId}/performance-notes`)
      .then((result) => {
        if (live) setNotes(result.notes)
      })
      .catch((reason) => {
        if (live) setError(reason instanceof ApiError ? reason.message : 'Performance notes could not be loaded.')
      })
    return () => {
      live = false
    }
  }, [gymId, userId, version])

  function refresh() {
    setVersion((current) => current + 1)
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle render={<h2 />}>Performance notes</CardTitle>
        <CardDescription className="flex items-start gap-1.5">
          <Lock aria-hidden className="mt-0.5 size-3.5 shrink-0" />
          <span>Dated notes for managers. The employee they describe cannot see them.</span>
        </CardDescription>
        {canManage && (
          <CardAction>
            <Button size="sm" onClick={() => setAdding(true)}>
              <Plus aria-hidden />
              Add note
            </Button>
          </CardAction>
        )}
      </CardHeader>
      <CardPanel>
        {error ? (
          <Alert variant="error">
            <AlertTitle>Notes could not be loaded</AlertTitle>
            <AlertDescription>{error}</AlertDescription>
            <div className="pt-2">
              <Button size="sm" variant="outline" onClick={refresh}>
                Try again
              </Button>
            </div>
          </Alert>
        ) : notes === null ? (
          <div className="flex flex-col gap-5" aria-busy="true">
            {[0, 1].map((key) => (
              <div key={key} className="grid grid-cols-[2rem_minmax(0,1fr)] gap-3">
                <Skeleton className="size-8 rounded-full" />
                <div className="flex flex-col gap-2">
                  <Skeleton className="h-4 w-40" />
                  <Skeleton className="h-12" />
                </div>
              </div>
            ))}
          </div>
        ) : notes.length === 0 ? (
          <Empty className="py-10 md:py-12">
            <EmptyHeader>
              <EmptyTitle>No notes yet</EmptyTitle>
              <EmptyDescription>
                {canManage
                  ? 'Add a note after a review, a commendation or a concern. It stays with managers.'
                  : 'Nobody has added a note about this employee yet.'}
              </EmptyDescription>
            </EmptyHeader>
            {canManage && (
              <EmptyContent>
                <Button variant="outline" onClick={() => setAdding(true)}>
                  <Plus aria-hidden />
                  Add the first note
                </Button>
              </EmptyContent>
            )}
          </Empty>
        ) : (
          <ol className="flex flex-col" aria-label="Performance notes, newest first">
            {notes.map((note, index) => (
              <li key={note.id} className="relative grid grid-cols-[2rem_minmax(0,1fr)] gap-x-3 pb-6 last:pb-0">
                {index < notes.length - 1 && <span aria-hidden className="absolute top-10 bottom-2 left-4 w-px -translate-x-1/2 bg-border" />}
                <Avatar className="size-8" aria-hidden>
                  <AvatarFallback className="text-xs">{initialsOf(note.author_name)}</AvatarFallback>
                </Avatar>
                <article className="flex min-w-0 flex-col gap-1.5 pt-1">
                  <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <time dateTime={note.noted_on} className="font-medium text-sm">
                      {workDayLabel(note.noted_on)}
                    </time>
                    <Badge variant={KIND_VARIANT[note.kind]}>{PERFORMANCE_KIND[note.kind]}</Badge>
                    <span className="text-muted-foreground text-xs sm:ms-auto">By {note.author_name ?? 'Unknown author'}</span>
                  </div>
                  <p className="max-w-prose whitespace-pre-wrap break-words text-sm leading-relaxed">{note.body}</p>
                </article>
              </li>
            ))}
          </ol>
        )}
      </CardPanel>

      {adding && (
        <NoteDialog
          gymId={gymId}
          userId={userId}
          onClose={() => setAdding(false)}
          onSaved={() => {
            setAdding(false)
            refresh()
          }}
        />
      )}
    </Card>
  )
}

function NoteDialog({ gymId, userId, onClose, onSaved }: { gymId: number; userId: number; onClose: () => void; onSaved: () => void }) {
  const [notedOn, setNotedOn] = useState(() => isoDay(new Date()))
  const [kind, setKind] = useState<PerformanceKind>('general')
  const [body, setBody] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setErrors({})
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/staff/${userId}/performance-notes`, {
        body: { noted_on: notedOn, kind, body },
      })
      toastManager.add({ title: 'Performance note saved.', type: 'success' })
      onSaved()
    } catch (error) {
      const fieldErrors = error instanceof ApiError ? error.errors : {}
      setErrors(fieldErrors)
      if (!(error instanceof ApiError) || Object.keys(fieldErrors).length === 0) {
        toastManager.add({ title: error instanceof ApiError ? error.message : 'Could not save the note.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Add a performance note</DialogTitle>
            <DialogDescription>Be specific and factual. The employee does not see this.</DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="noted_on" label="Date" type="date" value={notedOn} onChange={(event) => setNotedOn(event.target.value)} required />
              <Field name="kind">
                <FieldLabel>Kind</FieldLabel>
                <Select
                  items={Object.entries(PERFORMANCE_KIND).map(([value, label]) => ({ value, label }))}
                  value={kind}
                  onValueChange={(next) => setKind((next ?? 'general') as PerformanceKind)}
                >
                  <SelectTrigger aria-label="Note kind">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectPopup>
                    {Object.entries(PERFORMANCE_KIND).map(([value, label]) => (
                      <SelectItem key={value} value={value}>
                        {label}
                      </SelectItem>
                    ))}
                  </SelectPopup>
                </Select>
                <FieldError />
              </Field>
            </div>
            <Field name="body">
              <FieldLabel>Note</FieldLabel>
              <Textarea name="body" rows={5} maxLength={5000} value={body} onChange={(event) => setBody(event.target.value)} required />
              <FieldDescription>Say what happened and when. Other managers may read this later.</FieldDescription>
              <FieldError />
            </Field>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={!body.trim()}>
              Save note
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}
