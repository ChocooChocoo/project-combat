import { useEffect, useState } from 'react'
import { useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { coachStatus, slotLabel, type CoachApplication } from '@/lib/coach'
import { approvedGym, can } from '@/lib/gym-access'
import { dateLabel } from '@/lib/membership'

/** R-117: members who applied to coach here, with their hours and a fresh clash check. */
export default function GymCoachApplications() {
  const { gymId } = useParams()
  const { user } = useAuth()
  const gym = approvedGym(user, Number(gymId))
  const canApprove = Boolean(gym && can(gym, 'hr.coach_applications.approve'))
  const [rows, setRows] = useState<CoachApplication[] | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [reasons, setReasons] = useState<Record<number, string>>({})
  const [busy, setBusy] = useState<number | null>(null)

  useEffect(() => {
    let live = true
    api<{ applications: CoachApplication[] }>(`/gyms/${gymId}/coach-applications`)
      .then((result) => live && setRows(result.applications))
      .catch((failure) => live && setError(failure instanceof ApiError ? failure.message : 'Applications could not be loaded.'))
    return () => { live = false }
  }, [gymId, version])

  if (!gym) return null

  async function decide(row: CoachApplication, decision: 'approve' | 'reject') {
    const reason = reasons[row.id]?.trim() ?? ''
    if (decision === 'reject' && !reason) {
      toastManager.add({ title: 'Write a reason before rejecting.', type: 'error' })
      return
    }
    setBusy(row.id)
    try {
      await api(`/gyms/${gymId}/coach-applications/${row.id}/decision`, { body: { decision, reason: reason || null } })
      toastManager.add({ title: decision === 'approve' ? `${row.applicant.name} is now a coach here.` : 'Application rejected.', type: 'success' })
      setVersion((value) => value + 1)
    } catch (failure) {
      const first = failure instanceof ApiError ? Object.values(failure.errors).flat()[0] ?? failure.message : 'Decision could not be saved.'
      toastManager.add({ title: first, type: 'error' })
    } finally {
      setBusy(null)
    }
  }

  return (
    <div className="flex min-w-0 flex-col gap-6">
      <PageHeader title="Coach applications" description="Approving adds the person as an employee with a coach profile, their hours as a work schedule and the Coach starter role." />
      {error ? <Alert variant="error"><AlertTitle>Could not load</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>
        : !rows ? <Skeleton className="h-24" />
        : rows.length === 0 ? <Empty className="rounded-xl border"><EmptyHeader><EmptyTitle>No coach applications</EmptyTitle><EmptyDescription>Members apply from your public gym page.</EmptyDescription></EmptyHeader></Empty>
        : (
          <ul className="flex flex-col gap-3">
            {rows.map((row) => (
              <li key={row.id} className="flex flex-col gap-3 rounded-xl border p-4 text-sm">
                <div className="flex flex-wrap items-center gap-2">
                  <h2 className="font-medium text-base">{row.applicant.name}</h2>
                  <Badge variant={coachStatus[row.status].variant}>{coachStatus[row.status].label}</Badge>
                  <span className="text-muted-foreground text-xs">{row.applicant.email} · sent {dateLabel(row.created_at)}</span>
                </div>
                <p className="whitespace-pre-wrap break-words">{row.experience}</p>
                <p className="text-muted-foreground">{[row.rank, row.specialisation].filter(Boolean).join(' · ')}</p>
                <p><span className="text-muted-foreground">Hours: </span>{row.availability.map(slotLabel).join(' · ')}</p>
                {row.conflicts.length > 0 ? (
                  <Alert variant="error"><AlertTitle>Clashes with another gym</AlertTitle><AlertDescription>
                    <ul className="list-disc ps-5">{row.conflicts.map((line) => <li key={line}>{line}</li>)}</ul>
                  </AlertDescription></Alert>
                ) : row.status === 'pending' && <p className="text-success-foreground text-xs">No clash with their other jobs.</p>}
                {row.decided_by && <p className="text-muted-foreground text-xs">Decided by {row.decided_by}{row.decision_reason && ` · ${row.decision_reason}`}</p>}
                {canApprove && row.status === 'pending' && row.applicant.id !== user?.id && (
                  <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <Textarea aria-label={`Reason for ${row.applicant.name}`} placeholder="Reason (needed to reject)" rows={2} maxLength={2000} className="sm:flex-1"
                      value={reasons[row.id] ?? ''} onChange={(event) => setReasons((current) => ({ ...current, [row.id]: event.target.value }))} />
                    <div className="flex gap-2">
                      <Button variant="destructive-outline" disabled={busy === row.id} onClick={() => decide(row, 'reject')}>Reject</Button>
                      <Button loading={busy === row.id} disabled={row.conflicts.length > 0} onClick={() => decide(row, 'approve')}>Approve</Button>
                    </div>
                  </div>
                )}
              </li>
            ))}
          </ul>
        )}
    </div>
  )
}
