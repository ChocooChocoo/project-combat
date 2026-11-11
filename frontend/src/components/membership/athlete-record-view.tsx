import { EntriesList } from '@/components/membership/membership-parts'
import { Card, CardAction, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/components/ui/tabs'
import { useState } from 'react'
import { ATHLETE_LEVELS, dateLabel, type AthleteLevel, type AthleteRecord, type HealthDetails, type MembershipForm } from '@/lib/membership'
import { Button } from '@/components/ui/button'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { FileText } from 'lucide-react'
import { AttendanceList, ProgressNotes } from '@/components/schedule/attendance-list'
import { ATTENDANCE_LABELS } from '@/lib/attendance'

/** D-35: the level booking checks. Coaches with training.athletes.manage can change it. */
function AthleteLevelRow({ level, onChange }: { level: AthleteLevel | null; onChange?: (level: AthleteLevel | null) => Promise<void> }) {
  const [busy, setBusy] = useState(false)
  const items = [{ value: 'none', label: 'Not set (counts as Beginner)' }, ...Object.entries(ATHLETE_LEVELS).map(([value, label]) => ({ value, label }))]
  async function change(next: string) {
    if (!onChange) return
    setBusy(true)
    try { await onChange(next === 'none' ? null : next as AthleteLevel) } finally { setBusy(false) }
  }
  return (
    <div className="flex flex-col gap-2 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h3 className="font-medium text-sm">Training level</h3>
        <p className="mt-1 text-muted-foreground text-xs">Sessions for a higher level cannot be booked.</p>
      </div>
      {onChange ? (
        <Select items={items} value={level ?? 'none'} disabled={busy} onValueChange={(next) => change(String(next ?? 'none'))}>
          <SelectTrigger aria-label="Training level" className="sm:w-64"><SelectValue /></SelectTrigger>
          <SelectPopup>{items.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}</SelectPopup>
        </Select>
      ) : (
        <p className="font-semibold text-sm">{level ? ATHLETE_LEVELS[level] : 'Beginner (not set)'}</p>
      )}
    </div>
  )
}

/** Current ranks plus the four lists of the athlete record (R-37 to R-39, R-41). */
export function AthleteRecordView({ record, action, onLevelChange }: {
  record: AthleteRecord; action?: React.ReactNode; onLevelChange?: (level: AthleteLevel | null) => Promise<void>
}) {
  return (
    <Card className="min-w-0">
      <CardHeader>
        <CardTitle render={<h2 />} className="text-xl tracking-tight">Athlete record</CardTitle>
        <CardDescription>Rank, skill assessments, competitions and achievements recorded by the gym's coaches.</CardDescription>
        {action && <CardAction>{action}</CardAction>}
      </CardHeader>
      <CardPanel className="flex min-w-0 flex-col gap-6">
        <div className="flex flex-col gap-3 border-y bg-muted/30 px-4 py-5">
          <h3 className="font-medium text-sm">Current rank</h3>
          {record.ranks.length > 0 ? (
            <ul className="grid gap-4 sm:grid-cols-2">
              {record.ranks.map((rank) => (
                <li key={rank.discipline} className="min-w-0">
                  <p className="font-semibold text-lg tracking-tight wrap-anywhere">{rank.rank}</p>
                  <p className="mt-1 text-sm wrap-anywhere">{rank.discipline}</p>
                  <p className="mt-1 text-muted-foreground text-xs">Since {dateLabel(rank.since)}</p>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-muted-foreground text-sm">
              {record.starting_rank ? `Rank given when joining: ${record.starting_rank}. No promotions recorded here yet.` : 'No rank recorded yet.'}
            </p>
          )}
          <AthleteLevelRow level={record.level} onChange={onLevelChange} />
        </div>

        <Tabs defaultValue="promotion" className="min-w-0">
          <TabsList variant="underline" aria-label="Record sections" className="h-auto max-w-full flex-wrap justify-start">
            <TabsTab className="min-h-11" value="promotion">Rank history</TabsTab>
            <TabsTab className="min-h-11" value="assessment">Assessments</TabsTab>
            <TabsTab className="min-h-11" value="competition">Competitions</TabsTab>
            <TabsTab className="min-h-11" value="achievement">Achievements</TabsTab>
          </TabsList>
          {(['promotion', 'assessment', 'competition', 'achievement'] as const).map((type) => (
            <TabsPanel key={type} value={type} className="pt-3">
              <EntriesList entries={record.entries} type={type} />
            </TabsPanel>
          ))}
        </Tabs>

        <section className="flex min-w-0 flex-col gap-4 border-t pt-6">
          <h3 className="font-semibold text-lg tracking-tight">Attendance</h3>
          <dl className="flex flex-wrap gap-x-8 gap-y-4 pb-2 text-sm">{Object.entries(record.attendance_counts).map(([status, count]) => <div key={status}><dt className="text-muted-foreground text-xs">{ATTENDANCE_LABELS[status as keyof typeof ATTENDANCE_LABELS]}</dt><dd className="mt-1 font-semibold text-2xl tabular-nums">{count}</dd></div>)}</dl>
          <AttendanceList rows={record.attendance} hideMember />
        </section>
        <section className="flex min-w-0 flex-col gap-4 border-t pt-6">
          <h3 className="font-semibold text-lg tracking-tight">Progress notes</h3><ProgressNotes notes={record.progress_notes} />
        </section>
        {(record.background || record.training_history) && (
          <dl className="grid gap-6 border-t pt-6 text-sm sm:grid-cols-2">
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
    <div className="flex min-w-0 flex-col gap-5 text-sm">
      <div>
        <h3 className="mb-2 font-medium">Emergency contacts</h3>
        <ul className="divide-y">
          {health.emergency_contacts.map((contact, index) => (
            <li key={index} className="flex min-w-0 flex-col items-start py-3 first:pt-0 last:pb-0">
              <span className="font-medium wrap-anywhere">{contact.name}</span>
              <span className="mt-1 text-muted-foreground text-xs wrap-anywhere">{contact.relationship}</span>
                <a className="flex min-h-11 items-center underline-offset-4 hover:underline wrap-anywhere" href={`tel:${contact.phone}`}>
                  {contact.phone}
                </a>
            </li>
          ))}
        </ul>
      </div>
      <div className="border-t pt-5">
        <h3 className="mb-3 font-medium">Health declaration</h3>
        {yes.length === 0 ? (
          <p className="text-muted-foreground leading-relaxed">Answered no to every question.</p>
        ) : (
          <dl className="divide-y">
            {yes.map(([key]) => (
              <div key={key} className="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
                <dt className="min-w-0 flex-1 leading-relaxed wrap-anywhere">{form?.health_questions[key] ?? key}</dt>
                <dd className="shrink-0 rounded-sm bg-muted px-2 py-0.5 font-medium text-xs leading-5">Yes</dd>
              </div>
            ))}
          </dl>
        )}
        {health.notes && (
          <div className="mt-5 border-t pt-4">
            <h4 className="mb-2 font-medium text-xs">Additional health notes</h4>
            <p className="whitespace-pre-line text-muted-foreground leading-relaxed wrap-anywhere">{health.notes}</p>
          </div>
        )}
      </div>
      {health.medical_file_name && onOpenFile && (
        <div className="min-w-0 border-t pt-5">
          <h3 className="mb-3 font-medium">Medical clearance</h3>
          <Button type="button" size="sm" variant="outline" className="min-h-11 h-auto w-full justify-start gap-3 whitespace-normal px-3 py-3 text-left" onClick={onOpenFile}>
            <FileText className="shrink-0" aria-hidden />
            <span className="min-w-0 leading-relaxed wrap-anywhere">{health.medical_file_name}</span>
          </Button>
        </div>
      )}
    </div>
  )
}
