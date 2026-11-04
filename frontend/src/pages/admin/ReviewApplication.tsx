import { ArrowLeft, Check, X } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { DocumentSummary, ProfileSummary, StatusBadge } from '@/components/application-summary'
import { PageHeader } from '@/components/page-header'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { type Application, useRequirements } from '@/lib/gyms'

type Review = Application & { applicant: { first_name: string; last_name: string; email: string; phone: string | null } | null }

/** S-02, R-16 and R-17: check the application against the checklist, then decide, without leaving the page. */
export default function ReviewApplication() {
  const { id } = useParams()
  const requirements = useRequirements()
  const [app, setApp] = useState<Review | null>(null)
  const [items, setItems] = useState<string[]>([])
  const [decision, setDecision] = useState<'reject' | 'return' | null>(null)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState('')
  const [missing, setMissing] = useState(false)

  useEffect(() => {
    api<{ application: Review; checklist_items: string[] }>(`/admin/applications/${id}`)
      .then((r) => {
        setApp(r.application)
        setItems(r.checklist_items)
      })
      .catch(() => setMissing(true))
  }, [id])

  if (missing) {
    return (
      <Empty className="rounded-xl border">
        <EmptyHeader>
          <EmptyTitle>Application not found</EmptyTitle>
          <EmptyDescription>
            It may have been removed, or the link is wrong. <Link className="underline" to="/admin">Back to gym applications</Link>
          </EmptyDescription>
        </EmptyHeader>
      </Empty>
    )
  }

  if (!app || !requirements) {
    return (
      <div className="flex flex-col gap-6" aria-busy="true">
        <Skeleton className="h-9 w-72" />
        <div className="grid gap-6 lg:grid-cols-[1fr_22rem]">
          <Skeleton className="h-96" />
          <Skeleton className="h-80" />
        </div>
      </div>
    )
  }

  const open = app.status === 'submitted' || app.status === 'under_review'
  const passed = items.filter((item) => app.checklist?.[item] === true).length
  const allPassed = passed === items.length

  async function mark(item: string, value: boolean | null) {
    try {
      const r = await api<{ checklist: Review['checklist'] }>(`/admin/applications/${app!.id}/checklist`, {
        method: 'PUT',
        body: { checklist: { [item]: value } },
      })
      setApp((current) => current && { ...current, checklist: r.checklist })
    } catch (error) {
      toastManager.add({ title: error instanceof ApiError ? error.message : 'Could not save the check.', type: 'error' })
    }
  }

  async function decide(body: Record<string, unknown>) {
    setBusy(String(body.decision))
    try {
      const r = await api<{ application: Application }>(`/admin/applications/${app!.id}/decision`, { body })
      setApp((current) => current && { ...current, ...r.application })
      setDecision(null)
      setErrors({})
      toastManager.add({ title: 'Decision saved. The applicant has been told.', type: 'success' })
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
      if (!(error instanceof ApiError) || Object.keys(error.errors).length === 0) {
        toastManager.add({ title: error instanceof ApiError ? error.message : 'Could not save the decision.', type: 'error' })
      }
    } finally {
      setBusy('')
    }
  }

  function submitDecision(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const values = Object.fromEntries(new FormData(event.currentTarget)) as Record<string, string>
    if (decision === 'reject') decide({ decision: 'reject', reason: values.reason })
    else
      decide({
        decision: 'return',
        items: (values.items ?? '').split('\n').map((line) => line.trim()).filter(Boolean),
        reason: values.reason || null,
      })
  }

  return (
    <div className="flex flex-col gap-6">
      <Link to="/admin" className="inline-flex w-fit items-center gap-1.5 text-muted-foreground text-sm underline-offset-4 hover:text-foreground hover:underline">
        <ArrowLeft className="size-4" aria-hidden /> Gym applications
      </Link>

      <PageHeader
        title={app.name}
        meta={<StatusBadge status={app.status} />}
        description={
          app.applicant && (
            <>
              {app.tier && `${requirements.tiers[app.tier].label} · `}Applicant {app.applicant.first_name} {app.applicant.last_name} · {app.applicant.email}
              {app.applicant.phone && ` · ${app.applicant.phone}`}
            </>
          )
        }
      />

      <div className="grid items-start gap-6 lg:grid-cols-[1fr_22rem]">
        <div className="flex min-w-0 flex-col gap-6">
          <Card>
            <CardHeader>
              <CardTitle render={<h2 />}>Documents</CardTitle>
              <CardDescription>Open each file to check it is clear, current and matches the profile.</CardDescription>
            </CardHeader>
            <CardPanel>
              <DocumentSummary app={app} requirements={requirements} />
            </CardPanel>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle render={<h2 />}>Gym profile</CardTitle>
            </CardHeader>
            <CardPanel>
              <ProfileSummary app={app} requirements={requirements} />
            </CardPanel>
          </Card>
        </div>

        <aside className="flex flex-col gap-6 lg:sticky lg:top-6" aria-label="Review decision">
          {app.status === 'approved' && (
            <Alert variant="success">
              <AlertTitle>Approved</AlertTitle>
              <AlertDescription>The gym is live and the applicant is its owner.</AlertDescription>
            </Alert>
          )}
          {app.status === 'rejected' && (
            <Alert variant="error">
              <AlertTitle>Rejected</AlertTitle>
              <AlertDescription>{app.decision_reason}</AlertDescription>
            </Alert>
          )}
          {app.status === 'returned' && (
            <Alert variant="warning">
              <AlertTitle>Returned to the applicant</AlertTitle>
              <AlertDescription>
                <ul className="list-disc ps-5">{(app.return_items ?? []).map((i) => <li key={i}>{i}</li>)}</ul>
              </AlertDescription>
            </Alert>
          )}

          <Card>
            <CardHeader>
              <CardTitle render={<h2 />}>Checklist</CardTitle>
              <CardDescription className="tabular-nums">
                {passed} of {items.length} passed
              </CardDescription>
            </CardHeader>
            <CardPanel>
              <ul className="flex flex-col gap-4">
                {items.map((item) => {
                  const value = app.checklist?.[item]
                  return (
                    <li key={item} className="flex flex-col gap-2">
                      <span id={`check-${item}`} className="text-sm">{requirements.checklist[item].label}</span>
                      <ToggleGroup
                        aria-labelledby={`check-${item}`}
                        variant="outline"
                        size="sm"
                        disabled={!open}
                        value={value === true ? ['pass'] : value === false ? ['fail'] : []}
                        onValueChange={(v) => mark(item, v[0] === 'pass' ? true : v[0] === 'fail' ? false : null)}
                      >
                        <ToggleGroupItem value="pass" className="data-pressed:text-success-foreground">
                          <Check aria-hidden /> Passed
                        </ToggleGroupItem>
                        <ToggleGroupItem value="fail" className="data-pressed:text-destructive-foreground">
                          <X aria-hidden /> Failed
                        </ToggleGroupItem>
                      </ToggleGroup>
                    </li>
                  )
                })}
              </ul>
              {errors.checklist && <p role="alert" className="mt-4 text-destructive-foreground text-sm">{errors.checklist[0]}</p>}
            </CardPanel>
          </Card>

          {open && (
            <Card>
              <CardHeader>
                <CardTitle render={<h2 />}>Decision</CardTitle>
                <CardDescription>The applicant gets a notice either way.</CardDescription>
              </CardHeader>
              <CardPanel className="flex flex-col gap-3">
                <Button disabled={!allPassed} loading={busy === 'approve'} onClick={() => decide({ decision: 'approve' })}>
                  Approve and go live
                </Button>
                {!allPassed && <p className="text-muted-foreground text-xs">Approve unlocks when every check has passed.</p>}
                <div className="grid grid-cols-2 gap-2">
                  <Button
                    variant={decision === 'return' ? 'secondary' : 'outline'}
                    aria-expanded={decision === 'return'}
                    onClick={() => {
                      setErrors({})
                      setDecision(decision === 'return' ? null : 'return')
                    }}
                  >
                    Return
                  </Button>
                  <Button
                    variant={decision === 'reject' ? 'secondary' : 'destructive-outline'}
                    aria-expanded={decision === 'reject'}
                    onClick={() => {
                      setErrors({})
                      setDecision(decision === 'reject' ? null : 'reject')
                    }}
                  >
                    Reject
                  </Button>
                </div>

                {decision && (
                  <Form errors={errors} onSubmit={submitDecision} className="flex flex-col gap-4 border-t pt-4">
                    {decision === 'return' && (
                      <Field name="items">
                        <FieldLabel>What to fix</FieldLabel>
                        <Textarea name="items" rows={4} required autoFocus />
                        <FieldDescription>One item per line, for example "Barangay clearance is blurry".</FieldDescription>
                        <FieldError />
                      </Field>
                    )}
                    <Field name="reason">
                      <FieldLabel>{decision === 'reject' ? 'Reason for rejecting' : 'Note to the applicant (optional)'}</FieldLabel>
                      <Textarea name="reason" rows={3} required={decision === 'reject'} autoFocus={decision === 'reject'} />
                      {decision === 'reject' && <FieldDescription>The applicant sees this. A rejected application cannot be sent again.</FieldDescription>}
                      <FieldError />
                    </Field>
                    <div className="flex flex-wrap justify-end gap-2">
                      <Button type="button" variant="ghost" onClick={() => setDecision(null)}>
                        Cancel
                      </Button>
                      <Button type="submit" variant={decision === 'reject' ? 'destructive' : 'default'} loading={busy === decision}>
                        {decision === 'reject' ? 'Reject application' : 'Return to applicant'}
                      </Button>
                    </div>
                  </Form>
                )}
              </CardPanel>
            </Card>
          )}
        </aside>
      </div>
    </div>
  )
}
