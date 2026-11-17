import { Plus } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { manilaToday } from '@/lib/dashboard'
import { api, apiFile, ApiError, type FieldErrors } from '@/lib/api'
import { appendForm, dateLabel, dateTimeLabel } from '@/lib/membership'
import { fileProblem } from '@/lib/validation'

type Update = { id: number; status: string; note: string | null; by: string | null; at: string | null }
type Injury = {
  id: number
  body_part: string
  description: string | null
  severity: 'minor' | 'moderate' | 'severe'
  occurred_on: string
  status: 'active' | 'recovering' | 'cleared'
  restrictions: string | null
  cleared_on: string | null
  reported_by: string | null
  files: { id: number; name: string; mime: string; size: number | null }[]
  updates: Update[]
}

const STATUS = { active: { label: 'Injured', variant: 'error' }, recovering: { label: 'Recovering', variant: 'warning' }, cleared: { label: 'Cleared', variant: 'success' } } as const
const SEVERITY_ITEMS = [{ value: 'minor', label: 'Minor' }, { value: 'moderate', label: 'Moderate' }, { value: 'severe', label: 'Severe' }]
const STATUS_ITEMS = [{ value: 'active', label: 'Injured' }, { value: 'recovering', label: 'Recovering' }, { value: 'cleared', label: 'Cleared' }]
// (R-147) proof of injury: the same file rules as the medical clearance on the membership form.
const PROOF_ACCEPT = '.pdf,.jpg,.jpeg,.png'
const PROOF_MAX_KB = 5120
const PROOF_MAX_FILES = 3

function errorText(failure: unknown, fallback: string): string {
  return failure instanceof ApiError ? Object.values(failure.errors).flat()[0] ?? failure.message : fallback
}

/**
 * R-119: injuries and the recovery log. Staff need health details access to open this card;
 * the member sees and reports their own. Only injury managers record recovery steps.
 */
export function InjuriesCard({ gymId, athleteId, canReport, canManage }: { gymId: number; athleteId: number; canReport: boolean; canManage: boolean }) {
  const [injuries, setInjuries] = useState<Injury[] | null>(null)
  const [error, setError] = useState('')
  const [adding, setAdding] = useState(false)
  const [severity, setSeverity] = useState('minor')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [steps, setSteps] = useState<Record<number, { status: string; note: string }>>({})
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let live = true
    api<{ injuries: Injury[] }>(`/gyms/${gymId}/athletes/${athleteId}/injuries`)
      .then((data) => live && setInjuries(data.injuries))
      .catch((failure) => live && setError(errorText(failure, 'Injuries could not be loaded.')))
    return () => { live = false }
  }, [gymId, athleteId])

  async function report(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const picked = form.getAll('files').filter((file): file is File => file instanceof File && file.size > 0)
    // (R-147) checked here, so a wrong proof file is caught before the report is sent.
    const problem = picked.length > PROOF_MAX_FILES
      ? `Attach up to ${PROOF_MAX_FILES} files.`
      : picked.map((file) => fileProblem(file, PROOF_ACCEPT, PROOF_MAX_KB)).find(Boolean)
    if (problem) {
      setErrors({ files: [problem] })
      return
    }
    setErrors({})

    const text = (key: string) => String(form.get(key) ?? '').trim()
    const body = new FormData()
    appendForm(body, 'body_part', text('body_part'))
    appendForm(body, 'severity', severity)
    appendForm(body, 'occurred_on', text('occurred_on'))
    appendForm(body, 'description', text('description') || null)
    appendForm(body, 'restrictions', text('restrictions') || null)
    // Laravel reads files[0], files[1]... as the `files` array.
    picked.forEach((file, index) => body.append(`files[${index}]`, file))

    setBusy(true)
    try {
      const data = await api<{ injuries: Injury[] }>(`/gyms/${gymId}/athletes/${athleteId}/injuries`, { body })
      setInjuries(data.injuries)
      setAdding(false)
      toastManager.add({ title: 'Injury recorded.', type: 'success' })
    } catch (failure) {
      setErrors(failure instanceof ApiError ? failure.errors : {})
      toastManager.add({ title: errorText(failure, 'The injury could not be saved.'), type: 'error' })
    } finally {
      setBusy(false)
    }
  }

  async function openFile(injury: Injury, file: Injury['files'][number]) {
    try {
      await apiFile(`/gyms/${gymId}/injuries/${injury.id}/files/${file.id}`)
    } catch (failure) {
      toastManager.add({ title: errorText(failure, 'Could not open the file.'), type: 'error' })
    }
  }

  async function step(injury: Injury) {
    const value = steps[injury.id] ?? { status: injury.status, note: '' }
    setBusy(true)
    try {
      const data = await api<{ injuries: Injury[] }>(`/gyms/${gymId}/injuries/${injury.id}`, { method: 'PUT', body: { status: value.status, note: value.note || null } })
      setInjuries(data.injuries)
      setSteps((current) => ({ ...current, [injury.id]: { status: value.status, note: '' } }))
    } catch (failure) {
      toastManager.add({ title: errorText(failure, 'Could not save the update.'), type: 'error' })
    } finally {
      setBusy(false)
    }
  }

  const today = manilaToday()

  return (
    <Card>
      <CardHeader>
        <CardTitle render={<h2 />}>Injuries</CardTitle>
        <CardDescription>Health details. Coaches without health access see only that the member is restricted.</CardDescription>
      </CardHeader>
      <CardPanel className="flex flex-col gap-4 text-sm">
        {error && <p className="text-destructive">{error}</p>}
        {injuries?.length === 0 && <p className="text-muted-foreground">No injuries recorded.</p>}
        {injuries?.map((injury) => {
          const value = steps[injury.id] ?? { status: injury.status, note: '' }
          return (
            <section key={injury.id} className="flex flex-col gap-2 rounded-xl border p-3" aria-label={injury.body_part}>
              <div className="flex flex-wrap items-center gap-2">
                <h3 className="font-medium">{injury.body_part}</h3>
                <Badge variant={STATUS[injury.status].variant}>{STATUS[injury.status].label}</Badge>
                <span className="text-muted-foreground text-xs">{injury.severity} · {dateLabel(injury.occurred_on)}{injury.cleared_on && ` · cleared ${dateLabel(injury.cleared_on)}`}</span>
              </div>
              {injury.description && <p className="whitespace-pre-wrap break-words">{injury.description}</p>}
              {injury.restrictions && <p><span className="text-muted-foreground">Restrictions: </span>{injury.restrictions}</p>}
              {injury.files.length > 0 && (
                <ul className="flex flex-col gap-1">
                  {injury.files.map((file) => (
                    <li key={file.id} className="flex flex-wrap items-center gap-2">
                      <span className="min-w-0 break-words">{file.name}</span>
                      <Button size="xs" variant="outline" onClick={() => openFile(injury, file)}>View</Button>
                    </li>
                  ))}
                </ul>
              )}
              <ol className="flex flex-col gap-1 text-muted-foreground text-xs">
                {injury.updates.map((update) => <li key={update.id}>{dateTimeLabel(update.at)} · {STATUS[update.status as Injury['status']]?.label ?? update.status}{update.note && ` · ${update.note}`}{update.by && ` · ${update.by}`}</li>)}
              </ol>
              {canManage && (
                <form className="flex flex-col gap-2 sm:flex-row sm:items-end" onSubmit={(event) => { event.preventDefault(); step(injury) }}>
                  <Select items={STATUS_ITEMS} value={value.status} onValueChange={(next) => setSteps((current) => ({ ...current, [injury.id]: { ...value, status: String(next) } }))}>
                    <SelectTrigger className="sm:w-40" aria-label={`Status of ${injury.body_part}`}><SelectValue /></SelectTrigger>
                    <SelectPopup>{STATUS_ITEMS.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}</SelectPopup>
                  </Select>
                  <Input className="sm:flex-1" placeholder="Recovery note" aria-label={`Recovery note for ${injury.body_part}`} maxLength={2000} value={value.note}
                    onChange={(event) => setSteps((current) => ({ ...current, [injury.id]: { ...value, note: event.target.value } }))} />
                  <Button type="submit" size="sm" variant="outline" disabled={busy}>Add update</Button>
                </form>
              )}
            </section>
          )
        })}
        {canReport && !adding && <Button variant="outline" className="self-start" onClick={() => setAdding(true)}><Plus aria-hidden /> Report injury</Button>}
        {canReport && adding && (
          <Form errors={errors} onSubmit={report} className="flex flex-col gap-3 rounded-xl border p-3">
            <div className="grid gap-3 sm:grid-cols-3">
              <Field name="body_part"><FieldLabel>Body part</FieldLabel><Input name="body_part" required maxLength={100} placeholder="Left knee" /><FieldError /></Field>
              <Field name="severity">
                <FieldLabel>Severity</FieldLabel>
                <Select items={SEVERITY_ITEMS} value={severity} onValueChange={(next) => setSeverity(String(next))}>
                  <SelectTrigger aria-label="Severity"><SelectValue /></SelectTrigger>
                  <SelectPopup>{SEVERITY_ITEMS.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}</SelectPopup>
                </Select>
                <FieldError />
              </Field>
              <Field name="occurred_on"><FieldLabel>When</FieldLabel><Input name="occurred_on" type="date" required max={today} defaultValue={today} /><FieldError /></Field>
            </div>
            <Field name="description"><FieldLabel>What happened (optional)</FieldLabel><Textarea name="description" rows={2} maxLength={2000} /><FieldError /></Field>
            <Field name="restrictions"><FieldLabel>Restrictions (optional)</FieldLabel><Input name="restrictions" maxLength={2000} placeholder="No sparring" /><FieldError /></Field>
            <Field name="files">
              <FieldLabel>Medical certificate or other documents (optional)</FieldLabel>
              <Input type="file" name="files" multiple accept={PROOF_ACCEPT} />
              <FieldDescription>Up to {PROOF_MAX_FILES} files. PDF, JPG or PNG, up to 5 MB each.</FieldDescription>
              <FieldError />
            </Field>
            <div className="flex gap-2"><Button type="submit" loading={busy}>Save injury</Button><Button type="button" variant="ghost" onClick={() => setAdding(false)}>Cancel</Button></div>
          </Form>
        )}
      </CardPanel>
    </Card>
  )
}
