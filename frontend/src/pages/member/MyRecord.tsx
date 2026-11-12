import { GoalsCard } from '@/components/training/goals-card'
import { InjuriesCard } from '@/components/training/injuries-card'
import { ArrowLeft, Pencil } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { AthleteRecordView, HealthSummary } from '@/components/membership/athlete-record-view'
import { ContactsEditor, HealthQuestions, MembershipStatusBadge } from '@/components/membership/membership-parts'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, apiFile, ApiError, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { appendForm, periodLabel, type AthleteRecord, type EmergencyContact, type MembershipForm } from '@/lib/membership'
import { fileProblem } from '@/lib/validation'

/** S-43, R-40 and R-41: the athlete's own record, with their health details to keep up to date. */
export default function MyRecord() {
  const { gymId } = useParams()
  const id = Number(gymId)
  const { user } = useAuth()
  const [record, setRecord] = useState<AthleteRecord | null>(null)
  const [form, setForm] = useState<MembershipForm | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)

  const [editing, setEditing] = useState(false)
  const [contacts, setContacts] = useState<EmergencyContact[]>([])
  const [answers, setAnswers] = useState<Record<string, boolean | undefined>>({})
  const [notes, setNotes] = useState('')
  const [file, setFile] = useState<File | null>(null)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let live = true
    setError('')
    Promise.all([api<{ record: AthleteRecord }>(`/gyms/${id}/my-record`), api<MembershipForm>('/membership-form')])
      .then(([recordResult, formResult]) => {
        if (!live) return
        setRecord(recordResult.record)
        setForm(formResult)
      })
      .catch((failure) => {
        if (!live) return
        setError(
          failure instanceof ApiError && failure.status === 404
            ? 'You do not have an athlete record at this gym yet. It is made when your membership is approved.'
            : failure instanceof ApiError
              ? failure.message
              : 'Try again in a moment.',
        )
      })
    return () => {
      live = false
    }
  }, [id, version])

  function startEditing() {
    if (!record?.health) return
    setContacts(record.health.emergency_contacts)
    setAnswers(record.health.answers)
    setNotes(record.health.notes ?? '')
    setFile(null)
    setErrors({})
    setEditing(true)
  }

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!form) return
    if (file) {
      const problem = fileProblem(file, form.medical_file.mimes.map((ext) => `.${ext}`).join(','), form.medical_file.max_kb)
      if (problem) {
        setErrors({ medical_file: [problem] })
        return
      }
    }
    const body = new FormData()
    appendForm(body, 'emergency_contacts', contacts)
    appendForm(body, 'health_answers', answers)
    appendForm(body, 'health_notes', notes)
    if (file) body.append('medical_file', file)

    setBusy(true)
    try {
      const result = await api<{ record: AthleteRecord }>(`/gyms/${id}/my-record/health`, { body })
      setRecord(result.record)
      setEditing(false)
      toastManager.add({ title: 'Health details saved.', type: 'success' })
    } catch (failure) {
      const fieldErrors = failure instanceof ApiError ? failure.errors : {}
      setErrors(fieldErrors)
      if (Object.keys(fieldErrors).length === 0) {
        toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Could not save.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  async function openFile() {
    try {
      await apiFile(`/gyms/${id}/athletes/${user?.id}/medical-file`)
    } catch (failure) {
      toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Could not open the file.', type: 'error' })
    }
  }

  if (error) {
    return (
      <Alert variant="error">
        <AlertTitle>Your record could not be loaded</AlertTitle>
        <AlertDescription>{error}</AlertDescription>
        <AlertAction>
          <Button size="xs" variant="outline" onClick={() => setVersion((v) => v + 1)}>
            Try again
          </Button>
        </AlertAction>
      </Alert>
    )
  }

  if (!record || !form) {
    return (
      <div className="flex flex-col gap-6" aria-busy="true" aria-label="Loading">
        <Skeleton className="h-16 w-72" />
        <Skeleton className="h-64" />
      </div>
    )
  }

  const current = record.memberships[0]

  return (
    <div className="flex flex-col gap-6">
      {current && (
        <Button className="-ms-3 min-h-11 self-start" size="sm" variant="ghost" render={<Link to={`/memberships/${current.id}`} />}>
          <ArrowLeft aria-hidden /> My membership
        </Button>
      )}
      <header className="flex flex-col gap-3 border-b pb-6">
        <div className="flex flex-wrap items-center gap-x-4 gap-y-3">
          <h1 className="font-semibold text-3xl tracking-tight sm:text-4xl">My record</h1>
          {current && <MembershipStatusBadge status={current.status} />}
        </div>
        {current && (
          <div className="flex flex-col gap-1">
            <p className="font-medium text-base wrap-anywhere">{current.gym_name}</p>
            {periodLabel(current) && <p className="text-muted-foreground text-sm">{periodLabel(current)}</p>}
          </div>
        )}
      </header>

      <div className="grid min-w-0 grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div className="flex min-w-0 flex-col gap-6">
          <AthleteRecordView record={record} />
          {user && <GoalsCard gymId={id} athleteId={user.id} canEdit />}
          {user && <InjuriesCard gymId={id} athleteId={user.id} canReport canManage={false} />}
        </div>

        {record.health && (
          <Card className="min-w-0 gap-0 overflow-hidden p-0">
            <div className="border-b bg-muted/30 p-5">
              <h2 className="font-semibold text-lg tracking-tight">Health and emergency</h2>
              <p className="mt-2 text-muted-foreground text-xs leading-relaxed">Only you and staff allowed to see health details can see this.</p>
                <Button className="mt-4 min-h-11 w-full" size="sm" variant="outline" onClick={startEditing}>
                  <Pencil aria-hidden />
                   Update details
                </Button>
            </div>
            <div className="p-5">
              <HealthSummary health={record.health} form={form} onOpenFile={openFile} />
            </div>
          </Card>
        )}
      </div>

      <Dialog open={editing} onOpenChange={setEditing}>
        <DialogPopup className="sm:max-w-3xl">
          <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
            <DialogHeader>
              <DialogTitle>Update health and emergency details</DialogTitle>
              <DialogDescription>Keep these current so the gym can look after you.</DialogDescription>
            </DialogHeader>
            <DialogPanel className="flex flex-col gap-5">
              <section className="flex flex-col gap-2">
                <h3 className="font-medium text-sm">Emergency contacts</h3>
                <ContactsEditor contacts={contacts} max={form.emergency_contacts_max} onChange={setContacts} />
              </section>
              <section className="flex flex-col gap-2">
                <h3 className="font-medium text-sm">Health questions</h3>
                <HealthQuestions questions={form.health_questions} answers={answers} onChange={setAnswers} />
              </section>
              <Field name="health_notes" className="w-full">
                <FieldLabel>Details</FieldLabel>
                <Textarea value={notes} onChange={(event) => setNotes(event.target.value)} rows={3} maxLength={2000} />
                <FieldError />
              </Field>
              <Field name="medical_file" className="w-full">
                <FieldLabel>New medical clearance (optional)</FieldLabel>
                <Input
                  type="file"
                  accept={form.medical_file.mimes.map((ext) => `.${ext}`).join(',')}
                  onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                />
                <FieldDescription>Replaces the file on record. Kept privately.</FieldDescription>
                <FieldError />
              </Field>
            </DialogPanel>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setEditing(false)}>
                Cancel
              </Button>
              <Button type="submit" loading={busy}>
                Save details
              </Button>
            </DialogFooter>
          </Form>
        </DialogPopup>
      </Dialog>
    </div>
  )
}
