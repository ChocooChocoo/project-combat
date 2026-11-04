import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { DocumentSummary, ProfileSummary, StatusBadge, StatusTrack } from '@/components/application-summary'
import { PageHeader } from '@/components/page-header'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { api } from '@/lib/api'
import { type Application, editable, useRequirements } from '@/lib/gyms'

/** R-14: the applicant follows the application and sees everything they sent. */
export default function ApplicationDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const requirements = useRequirements()
  const [app, setApp] = useState<Application | null>(null)

  useEffect(() => {
    api<{ application: Application }>(`/gym-applications/${id}`)
      .then((r) => setApp(r.application))
      .catch(() => navigate('/applications', { replace: true }))
  }, [id, navigate])

  if (!app || !requirements) {
    return (
      <div className="flex flex-col gap-6" aria-busy="true">
        <Skeleton className="h-9 w-64" />
        <Skeleton className="h-20" />
        <Skeleton className="h-80" />
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={app.name}
        meta={<StatusBadge status={app.status} />}
        description={<StatusTrack status={app.status} />}
        actions={
          editable(app.status) && (
            <Button render={<Link to={`/applications/${app.id}/edit${app.status === 'returned' ? '?step=review' : ''}`} />}>
              {app.status === 'returned' ? 'Fix and send again' : 'Continue application'}
            </Button>
          )
        }
      />

      {app.status === 'returned' && (
        <Alert variant="warning">
          <AlertTitle>Returned for correction</AlertTitle>
          <AlertDescription>
            <ul className="list-disc ps-5">{(app.return_items ?? []).map((item) => <li key={item}>{item}</li>)}</ul>
            {app.decision_reason && <p>{app.decision_reason}</p>}
          </AlertDescription>
        </Alert>
      )}
      {app.status === 'rejected' && (
        <Alert variant="error">
          <AlertTitle>Not approved</AlertTitle>
          <AlertDescription>
            <p>{app.decision_reason}</p>
            <p>A rejected application cannot be sent again. You can start a new one.</p>
          </AlertDescription>
        </Alert>
      )}
      {app.status === 'approved' && app.approved_tier && (
        <Alert variant="success">
          <AlertTitle>Approved</AlertTitle>
          <AlertDescription>The gym is live with the {requirements.tiers[app.approved_tier].badge} badge, and you are its owner.</AlertDescription>
        </Alert>
      )}
      {(app.status === 'submitted' || app.status === 'under_review') && (
        <Alert variant="info">
          <AlertTitle>{app.status === 'submitted' ? 'Waiting for review' : 'Being reviewed'}</AlertTitle>
          <AlertDescription>The platform administrator is checking your documents. You will get a notice with the decision.</AlertDescription>
        </Alert>
      )}

      <div className="grid items-start gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle render={<h2 />}>Gym profile</CardTitle>
          </CardHeader>
          <CardPanel>
            <ProfileSummary app={app} requirements={requirements} />
          </CardPanel>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle render={<h2 />}>Documents sent</CardTitle>
          </CardHeader>
          <CardPanel>
            <DocumentSummary app={app} requirements={requirements} />
          </CardPanel>
        </Card>
      </div>
    </div>
  )
}
