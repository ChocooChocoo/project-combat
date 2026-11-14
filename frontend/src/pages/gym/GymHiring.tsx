import { Plus } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, apiFile, ApiError, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import { jobStatus, type JobApplication, type JobPosting } from '@/lib/hiring'
import { dateLabel } from '@/lib/membership'
import type { GymRole } from '@/lib/workspace'

const NO_ROLE = 'none'

/** R-138: the owner's job openings and the applications they receive. */
export default function GymHiring() {
  const { gymId } = useParams()
  const { user } = useAuth()
  const gym = approvedGym(user, Number(gymId))
  const canPostings = Boolean(gym && can(gym, 'hr.job_postings.view', 'hr.job_postings.manage'))
  const canManage = Boolean(gym && can(gym, 'hr.job_postings.manage'))
  const canApplications = Boolean(gym && can(gym, 'hr.job_applications.view', 'hr.job_applications.approve'))
  const canApprove = Boolean(gym && can(gym, 'hr.job_applications.approve'))
  const canRoles = Boolean(gym && can(gym, 'hr.roles.view'))

  const [postings, setPostings] = useState<JobPosting[] | null>(null)
  const [applications, setApplications] = useState<JobApplication[] | null>(null)
  const [roles, setRoles] = useState<GymRole[]>([])
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [dialog, setDialog] = useState<{ posting: JobPosting | null } | null>(null)
  const [reasons, setReasons] = useState<Record<number, string>>({})
  const [busy, setBusy] = useState<number | null>(null)

  useEffect(() => {
    if (!gym) return
    let live = true
    Promise.all([
      canPostings ? api<{ postings: JobPosting[] }>(`/gyms/${gymId}/job-postings`) : Promise.resolve({ postings: [] }),
      canApplications ? api<{ applications: JobApplication[] }>(`/gyms/${gymId}/job-applications`) : Promise.resolve({ applications: [] }),
      canRoles ? api<{ roles: GymRole[] }>(`/gyms/${gymId}/roles`) : Promise.resolve({ roles: [] }),
    ])
      .then(([postingResult, applicationResult, roleResult]) => {
        if (!live) return
        setPostings(postingResult.postings)
        setApplications(applicationResult.applications)
        setRoles(roleResult.roles.filter((role) => role.status === 'active'))
      })
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'Hiring could not be loaded.'))
    return () => { live = false }
  }, [gym, gymId, canPostings, canApplications, canRoles, version])

  if (!gym) return null

  const reload = () => setVersion((value) => value + 1)

  async function decide(row: JobApplication, decision: 'approve' | 'reject') {
    const reason = reasons[row.id]?.trim() ?? ''
    if (decision === 'reject' && !reason) {
      toastManager.add({ title: 'Write a reason before rejecting.', type: 'error' })
      return
    }
    setBusy(row.id)
    try {
      await api(`/gyms/${gymId}/job-applications/${row.id}/decision`, { body: { decision, reason: reason || null } })
      toastManager.add({ title: decision === 'approve' ? `${row.applicant.name} now works here.` : 'Application rejected.', type: 'success' })
      reload()
    } catch (failure) {
      const first = failure instanceof ApiError ? Object.values(failure.errors).flat()[0] ?? failure.message : 'Decision could not be saved.'
      toastManager.add({ title: first, type: 'error' })
    } finally {
      setBusy(null)
    }
  }

  return (
    <div className="flex min-w-0 flex-col gap-6">
      <PageHeader title="Hiring" description="Post openings. Members apply from your public gym page. Approving adds the person as an employee with the job title and starter role." />
      {error ? <Alert variant="error"><AlertTitle>Could not load</AlertTitle><AlertDescription>{error}</AlertDescription></Alert> : (
        <Tabs defaultValue={canPostings ? 'openings' : 'applications'}>
          <TabsList variant="underline" aria-label="Hiring">
            {canPostings && <TabsTab value="openings">Openings</TabsTab>}
            {canApplications && <TabsTab value="applications">Applications</TabsTab>}
          </TabsList>

          {canPostings && (
            <TabsPanel value="openings" className="flex flex-col gap-4 pt-4">
              {canManage && <Button className="self-start" onClick={() => setDialog({ posting: null })}><Plus aria-hidden /> New opening</Button>}
              {!postings ? <Skeleton className="h-24" /> : postings.length === 0 ? (
                <Empty className="rounded-xl border"><EmptyHeader><EmptyTitle>No openings yet</EmptyTitle><EmptyDescription>Post a job and it shows on your public gym page.</EmptyDescription></EmptyHeader></Empty>
              ) : (
                <ul className="flex flex-col gap-3">
                  {postings.map((posting) => (
                    <li key={posting.id} className="flex flex-col gap-2 rounded-xl border p-4 text-sm">
                      <div className="flex flex-wrap items-center gap-2">
                        <h2 className="font-medium text-base">{posting.title}</h2>
                        <Badge variant={posting.status === 'open' ? 'success' : 'secondary'}>{posting.status === 'open' ? 'Open' : 'Closed'}</Badge>
                        {posting.role_name && <Badge variant="outline">Role: {posting.role_name}</Badge>}
                        {posting.pending_count > 0 && <Badge variant="warning">{posting.pending_count} waiting</Badge>}
                      </div>
                      <p className="whitespace-pre-wrap break-words">{posting.description}</p>
                      {posting.requirements && <p className="whitespace-pre-wrap break-words text-muted-foreground"><span className="font-medium">Requirements: </span>{posting.requirements}</p>}
                      {canManage && <Button size="xs" variant="outline" className="self-start" onClick={() => setDialog({ posting })}>Edit</Button>}
                    </li>
                  ))}
                </ul>
              )}
            </TabsPanel>
          )}

          {canApplications && (
            <TabsPanel value="applications" className="flex flex-col gap-4 pt-4">
              {!applications ? <Skeleton className="h-24" /> : applications.length === 0 ? (
                <Empty className="rounded-xl border"><EmptyHeader><EmptyTitle>No applications</EmptyTitle><EmptyDescription>Applications for your openings show here.</EmptyDescription></EmptyHeader></Empty>
              ) : (
                <ul className="flex flex-col gap-3">
                  {applications.map((row) => (
                    <li key={row.id} className="flex flex-col gap-3 rounded-xl border p-4 text-sm">
                      <div className="flex flex-wrap items-center gap-2">
                        <h2 className="font-medium text-base">{row.applicant.name}</h2>
                        <Badge variant={jobStatus[row.status].variant}>{jobStatus[row.status].label}</Badge>
                        <span className="text-muted-foreground text-xs">{row.applicant.email} · for {row.posting.title} · sent {dateLabel(row.created_at)}</span>
                      </div>
                      <p className="whitespace-pre-wrap break-words">{row.experience}</p>
                      <div className="flex flex-wrap gap-2">
                        <Button size="sm" variant="outline" onClick={() => apiFile(`/gyms/${gymId}/job-applications/${row.id}/files/credentials`)}>View credentials</Button>
                        <Button size="sm" variant="outline" onClick={() => apiFile(`/gyms/${gymId}/job-applications/${row.id}/files/valid_id`)}>View ID</Button>
                      </div>
                      {row.decided_by && <p className="text-muted-foreground text-xs">Decided by {row.decided_by}{row.decision_reason && ` · ${row.decision_reason}`}</p>}
                      {canApprove && row.status === 'pending' && row.applicant.id !== user?.id && (
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                          <Textarea aria-label={`Reason for ${row.applicant.name}`} placeholder="Reason (needed to reject)" rows={2} maxLength={2000} className="sm:flex-1"
                            value={reasons[row.id] ?? ''} onChange={(event) => setReasons((current) => ({ ...current, [row.id]: event.target.value }))} />
                          <div className="flex gap-2">
                            <Button variant="destructive-outline" disabled={busy === row.id} onClick={() => decide(row, 'reject')}>Reject</Button>
                            <Button loading={busy === row.id} onClick={() => decide(row, 'approve')}>Approve</Button>
                          </div>
                        </div>
                      )}
                    </li>
                  ))}
                </ul>
              )}
            </TabsPanel>
          )}
        </Tabs>
      )}
      {dialog && <PostingDialog gymId={gymId} posting={dialog.posting} roles={roles} canRoles={canRoles} onClose={() => setDialog(null)} onSaved={() => { setDialog(null); reload() }} />}
    </div>
  )
}

function PostingDialog({ gymId, posting, roles, canRoles, onClose, onSaved }: {
  gymId: string | undefined; posting: JobPosting | null; roles: GymRole[]; canRoles: boolean; onClose: () => void; onSaved: () => void
}) {
  const [roleId, setRoleId] = useState(posting?.gym_role_id ? String(posting.gym_role_id) : NO_ROLE)
  const [status, setStatus] = useState<'open' | 'closed'>(posting?.status ?? 'open')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)
  const roleItems = [{ value: NO_ROLE, label: 'No starter role' }, ...roles.map((role) => ({ value: String(role.id), label: role.name }))]
  const statusItems = [{ value: 'open', label: 'Open' }, { value: 'closed', label: 'Closed' }]

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const values = Object.fromEntries(new FormData(event.currentTarget)) as Record<string, string>
    setBusy(true)
    setErrors({})
    try {
      await api(`/gyms/${gymId}/job-postings${posting ? `/${posting.id}` : ''}`, {
        method: posting ? 'PUT' : 'POST',
        body: {
          title: values.title, description: values.description, requirements: values.requirements || null, status,
          // Without the roles list, an edit keeps the role the opening already has.
          gym_role_id: canRoles ? (roleId === NO_ROLE ? null : Number(roleId)) : posting?.gym_role_id ?? null,
        },
      })
      toastManager.add({ title: posting ? 'Opening saved.' : 'Opening posted.', type: 'success' })
      onSaved()
    } catch (failure) {
      setErrors(failure instanceof ApiError ? failure.errors : {})
      if (!(failure instanceof ApiError) || Object.keys(failure.errors).length === 0) {
        toastManager.add({ title: failure instanceof ApiError ? failure.message : 'The opening could not be saved.', type: 'error' })
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{posting ? 'Edit opening' : 'New opening'}</DialogTitle>
          <DialogDescription>Open jobs show on your public gym page.</DialogDescription>
        </DialogHeader>
        <Form errors={errors} onSubmit={save}>
          <DialogPanel className="flex flex-col gap-4">
            <TextField name="title" label="Job title" maxLength={120} defaultValue={posting?.title} required autoFocus />
            {canRoles && (
              <Field name="gym_role_id"><FieldLabel>Starter role</FieldLabel>
                <Select items={roleItems} value={roleId} onValueChange={(value) => setRoleId(String(value))}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectPopup>{roleItems.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}</SelectPopup>
                </Select><FieldError />
              </Field>
            )}
            <Field name="description"><FieldLabel>Description</FieldLabel><Textarea name="description" rows={4} maxLength={5000} defaultValue={posting?.description} required /><FieldError /></Field>
            <Field name="requirements"><FieldLabel>Requirements (optional)</FieldLabel><Textarea name="requirements" rows={3} maxLength={5000} defaultValue={posting?.requirements ?? ''} /><FieldError /></Field>
            <Field name="status"><FieldLabel>Status</FieldLabel>
              <Select items={statusItems} value={status} onValueChange={(value) => setStatus(value as 'open' | 'closed')}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectPopup>{statusItems.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}</SelectPopup>
              </Select><FieldError />
            </Field>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
            <Button type="submit" loading={busy}>{posting ? 'Save opening' : 'Post opening'}</Button>
          </DialogFooter>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
