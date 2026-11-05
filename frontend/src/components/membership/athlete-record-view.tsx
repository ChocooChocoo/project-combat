import { EntriesList } from '@/components/membership/membership-parts'
import { Card, CardAction, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/components/ui/tabs'
import { dateLabel, type AthleteRecord, type HealthDetails, type MembershipForm } from '@/lib/membership'
import { Button } from '@/components/ui/button'
import { FileText } from 'lucide-react'
import { AttendanceList, ProgressNotes } from '@/components/schedule/attendance-list'
import { ATTENDANCE_LABELS } from '@/lib/attendance'

/** Current ranks plus the four lists of the athlete record (R-37 to R-39, R-41). */
export function AthleteRecordView({ record, action }: { record: AthleteRecord; action?: React.ReactNode }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle render={<h2 />}>Athlete record</CardTitle>
        <CardDescription>Rank, skill assessments, competitions and achievements recorded by the gym's coaches.</CardDescription>
        {action && <CardAction>{action}</CardAction>}
      </CardHeader>
      <CardPanel className="flex flex-col gap-5">
        <div className="flex flex-col gap-2">
          <h3 className="font-medium text-sm">Current rank</h3>
          {record.ranks.length > 0 ? (
            <ul className="flex flex-wrap gap-2">
              {record.ranks.map((rank) => (
                <li key={rank.discipline} className="rounded-lg border px-3 py-2 text-sm">
                  <span className="font-medium">{rank.rank}</span>
                  <span className="text-muted-foreground"> · {rank.discipline}, since {dateLabel(rank.since)}</span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-muted-foreground text-sm">
              {record.starting_rank ? `Rank given when joining: ${record.starting_rank}. No promotions recorded here yet.` : 'No rank recorded yet.'}
            </p>
          )}
        </div>

        <Tabs defaultValue="promotion">
          <TabsList variant="underline" aria-label="Record sections" className="max-w-full overflow-x-auto">
            <TabsTab value="promotion">Rank history</TabsTab>
            <TabsTab value="assessment">Assessments</TabsTab>
            <TabsTab value="competition">Competitions</TabsTab>
            <TabsTab value="achievement">Achievements</TabsTab>
          </TabsList>
          {(['promotion', 'assessment', 'competition', 'achievement'] as const).map((type) => (
            <TabsPanel key={type} value={type} className="pt-3">
              <EntriesList entries={record.entries} type={type} />
            </TabsPanel>
          ))}
        </Tabs>

        <section className="flex min-w-0 flex-col gap-3 border-t pt-4">
          <h3 className="font-semibold">Attendance</h3>
          <dl className="flex flex-wrap gap-4 text-sm">{Object.entries(record.attendance_counts).map(([status, count]) => <div key={status}><dt className="text-muted-foreground">{ATTENDANCE_LABELS[status as keyof typeof ATTENDANCE_LABELS]}</dt><dd className="font-medium tabular-nums">{count}</dd></div>)}</dl>
          <AttendanceList rows={record.attendance} />
        </section>
        <section className="flex min-w-0 flex-col gap-3 border-t pt-4">
          <h3 className="font-semibold">Progress notes</h3><ProgressNotes notes={record.progress_notes} />
        </section>
        {(record.background || record.training_history) && (
          <dl className="grid gap-3 border-t pt-4 text-sm sm:grid-cols-2">
            {record.background && (
              <div>
                <dt className="font-medium">Background</dt>
                <dd className="mt-1 whitespace-pre-line text-muted-foreground">{record.background}</dd>
              </div>
            )}
            {record.training_history && (
              <div>
                <dt className="font-medium">Training history</dt>
                <dd className="mt-1 whitespace-pre-line text-muted-foreground">{record.training_history}</dd>
              </div>
            )}
          </dl>
        )}
      </CardPanel>
    </Card>
  )
}

/** Health answers and emergency contacts, for the athlete or staff with health permission (R-40). */
export function HealthSummary({
  health,
  form,
  onOpenFile,
}: {
  health: HealthDetails
  form: MembershipForm | null
  onOpenFile?: () => void
}) {
  const yes = Object.entries(health.answers).filter(([, answer]) => answer)

  return (
    <div className="flex flex-col gap-4 text-sm">
      <div>
        <h3 className="mb-2 font-medium">Emergency contacts</h3>
        <ul className="flex flex-col gap-1.5">
          {health.emergency_contacts.map((contact, index) => (
            <li key={index}>
              <span className="font-medium">{contact.name}</span>
              <span className="text-muted-foreground">
                {' '}
                · {contact.relationship} ·{' '}
                <a className="underline-offset-4 hover:underline" href={`tel:${contact.phone}`}>
                  {contact.phone}
                </a>
              </span>
            </li>
          ))}
        </ul>
      </div>
      <div>
        <h3 className="mb-2 font-medium">Health declaration</h3>
        {yes.length === 0 ? (
          <p className="text-muted-foreground">Answered no to every question.</p>
        ) : (
          <ul className="flex list-inside list-disc flex-col gap-1">
            {yes.map(([key]) => (
              <li key={key}>{form?.health_questions[key] ?? key} — Yes</li>
            ))}
          </ul>
        )}
        {health.notes && <p className="mt-2 whitespace-pre-line text-muted-foreground">{health.notes}</p>}
      </div>
      {health.medical_file_name && onOpenFile && (
        <Button type="button" size="sm" variant="outline" className="self-start" onClick={onOpenFile}>
          <FileText aria-hidden />
          {health.medical_file_name}
        </Button>
      )}
    </div>
  )
}
