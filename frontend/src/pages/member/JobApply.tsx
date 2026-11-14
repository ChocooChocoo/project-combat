import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { Button } from '@/components/ui/button'
import { Card, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { JOB_FILE_ACCEPT, JOB_FILE_MAX_KB } from '@/lib/hiring'
import { fileProblem } from '@/lib/validation'

/** R-138: a member applies for a job posting with experience, credentials and a valid ID. */
export default function JobApply() {
  const { gymId, postingId } = useParams()
  const navigate = useNavigate()
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const body = new FormData(event.currentTarget)
    const problems: FieldErrors = {}
    for (const name of ['credentials', 'valid_id']) {
      const file = body.get(name)
      const problem = file instanceof File && file.size > 0 ? fileProblem(file, JOB_FILE_ACCEPT, JOB_FILE_MAX_KB) : 'Choose a file.'
      if (problem) problems[name] = [problem]
    }
    setErrors(problems)
    if (Object.keys(problems).length > 0) return

    setBusy(true)
    try {
      await api(`/gyms/${gymId}/job-postings/${postingId}/applications`, { body })
      toastManager.add({ title: 'Application sent. You will get a notice when the gym decides.', type: 'success' })
      navigate(`/gyms/${gymId}`)
    } catch (failure) {
      setErrors(failure instanceof ApiError ? failure.errors : {})
      if (!(failure instanceof ApiError) || Object.keys(failure.errors).length === 0) {
        toastManager.add({ title: failure instanceof ApiError ? failure.message : 'Application could not be sent.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="flex min-w-0 flex-col gap-6">
      <PageHeader title="Apply for this job" description="Tell the gym about your experience and attach your credentials and a valid ID. Only the gym's hiring staff can open them." />
      <Form errors={errors} onSubmit={submit} className="flex max-w-3xl flex-col gap-6">
        <Card>
          <CardHeader><CardTitle render={<h2 />}>Your application</CardTitle></CardHeader>
          <CardPanel className="flex flex-col gap-4">
            <Field name="experience"><FieldLabel>Experience</FieldLabel><Textarea name="experience" rows={4} maxLength={5000} required /><FieldError /></Field>
            <Field name="credentials"><FieldLabel>Credentials</FieldLabel>
              <Input type="file" name="credentials" accept={JOB_FILE_ACCEPT} required />
              <FieldDescription>Certificates or a resume. PDF, JPG or PNG, up to 5 MB.</FieldDescription><FieldError />
            </Field>
            <Field name="valid_id"><FieldLabel>Valid ID</FieldLabel>
              <Input type="file" name="valid_id" accept={JOB_FILE_ACCEPT} required />
              <FieldDescription>PDF, JPG or PNG, up to 5 MB.</FieldDescription><FieldError />
            </Field>
          </CardPanel>
        </Card>
        <div className="flex gap-3"><Button type="submit" loading={busy}>Send application</Button><Button type="button" variant="outline" render={<Link to={`/gyms/${gymId}`} />}>Back to gym</Button></div>
      </Form>
    </div>
  )
}
