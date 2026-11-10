import { Plus, Trash2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Radio, RadioGroup } from '@/components/ui/radio-group'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import {
  dateLabel,
  dateTimeLabel,
  ENTRY_LABELS,
  EVENT_LABELS,
  METHOD_LABELS,
  money,
  STATUS_LABELS,
  STATUS_VARIANTS,
  type AthleteEntry,
  type EmergencyContact,
  type HistoryEvent,
  type MembershipStatus,
  type Payment,
} from '@/lib/membership'
import { phoneRule } from '@/lib/validation'

export function MembershipStatusBadge({ status }: { status: MembershipStatus }) {
  return <Badge variant={STATUS_VARIANTS[status] ?? 'outline'}>{STATUS_LABELS[status] ?? status}</Badge>
}

/** Payments with receipt numbers. Corrections and refunds show as negative amounts (R-34). */
export function PaymentsList({ payments, actions }: { payments: Payment[]; actions?: (payment: Payment) => React.ReactNode }) {
  if (payments.length === 0) {
    return (
      <Empty className="rounded-xl border">
        <EmptyHeader>
          <EmptyTitle>No payments yet</EmptyTitle>
          <EmptyDescription>Payments made at the front desk or online show here with their receipt numbers.</EmptyDescription>
        </EmptyHeader>
      </Empty>
    )
  }

  return (
    <>
      <ul className="flex flex-col gap-3 md:hidden">
        {payments.map((payment) => (
          <li key={payment.id} className="flex flex-col gap-1.5 rounded-xl border p-3 text-sm">
            <div className="flex items-baseline justify-between gap-2">
              <span className="font-medium font-mono text-xs">{payment.receipt_number}</span>
              <span className="font-semibold tabular-nums">{money(payment.amount)}</span>
            </div>
            {payment.member && <span>{payment.member.name}</span>}
            {payment.session && <span className="break-words">Session add-on: {payment.session.name} · {dateLabel(payment.session.starts_at)}</span>}
            <span className="text-muted-foreground">
              {METHOD_LABELS[payment.method]} · {dateTimeLabel(payment.paid_at)}
            </span>
            <span className="text-muted-foreground">{payment.received_by ? `Received by ${payment.received_by}` : 'Recorded by the system'}</span>
            {payment.note && <span className="break-words text-muted-foreground">{payment.note}</span>}
            {actions?.(payment)}
          </li>
        ))}
      </ul>
      <div className="hidden md:block">
        <Table variant="card">
          <TableHeader>
            <TableRow>
              <TableHead>Receipt</TableHead>
              {payments.some((payment) => payment.member) && <TableHead>Member</TableHead>}
              <TableHead>Method</TableHead>
              <TableHead>Paid</TableHead>
              <TableHead>Recorded by</TableHead>
              <TableHead className="text-right">Amount</TableHead>
              {actions && (
                <TableHead>
                  <span className="sr-only">Actions</span>
                </TableHead>
              )}
            </TableRow>
          </TableHeader>
          <TableBody>
            {payments.map((payment) => (
              <TableRow key={payment.id}>
                <TableCell>
                  <div className="flex flex-col gap-0.5">
                    <span className="font-mono text-xs">{payment.receipt_number}</span>
                    {payment.session && <span className="max-w-64 whitespace-normal break-words text-sm">Session add-on: {payment.session.name} · {dateLabel(payment.session.starts_at)}</span>}
                    {payment.note && <span className="max-w-64 truncate text-muted-foreground text-xs" title={payment.note}>{payment.note}</span>}
                  </div>
                </TableCell>
                {payments.some((row) => row.member) && <TableCell>{payment.member?.name}</TableCell>}
                <TableCell>{METHOD_LABELS[payment.method]}</TableCell>
                <TableCell>{dateTimeLabel(payment.paid_at)}</TableCell>
                <TableCell>{payment.received_by ?? 'System'}</TableCell>
                <TableCell className="text-right font-medium tabular-nums">{money(payment.amount)}</TableCell>
                {actions && (
                  <TableCell>
                    <div className="flex justify-end">{actions(payment)}</div>
                  </TableCell>
                )}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </>
  )
}

/** Every change to a membership, newest first (R-36). */
export function HistoryList({ events }: { events: HistoryEvent[] }) {
  if (events.length === 0) return <p className="text-muted-foreground text-sm">No changes recorded yet.</p>

  return (
    <ol className="flex flex-col">
      {events.map((event) => (
        <li key={event.id} className="grid grid-cols-[7.5rem_1fr] gap-3 border-b py-2.5 text-sm last:border-b-0 sm:grid-cols-[10rem_1fr]">
          <time className="text-muted-foreground tabular-nums" dateTime={event.at}>
            {dateLabel(event.at)}
          </time>
          <div className="flex min-w-0 flex-col gap-0.5">
            <span className="font-medium">{EVENT_LABELS[event.type] ?? event.type}</span>
            {event.note && <span className="break-words text-muted-foreground">{event.note}</span>}
            {event.by && <span className="text-muted-foreground text-xs">By {event.by}</span>}
          </div>
        </li>
      ))}
    </ol>
  )
}

/** Rank history, assessments, competitions and achievements in date order (R-37 to R-39). */
export function EntriesList({ entries, type }: { entries: AthleteEntry[]; type: AthleteEntry['type'] }) {
  const rows = entries.filter((entry) => entry.type === type)
  const empty: Record<AthleteEntry['type'], string> = {
    promotion: 'No promotions recorded yet. A coach adds each new rank with its date.',
    assessment: 'No skill assessments yet.',
    competition: 'No competitions recorded yet.',
    achievement: 'No achievements recorded yet.',
  }

  if (rows.length === 0) return <p className="text-muted-foreground text-sm">{empty[type]}</p>

  return (
    <ol className="flex flex-col">
      {rows.map((entry) => (
        <li key={entry.id} className="grid grid-cols-[7.5rem_1fr] gap-3 border-b py-2.5 text-sm last:border-b-0 sm:grid-cols-[10rem_1fr]">
          <time className="text-muted-foreground tabular-nums" dateTime={entry.occurred_on}>
            {dateLabel(entry.occurred_on)}
          </time>
          <div className="flex min-w-0 flex-col gap-0.5">
            <span className="font-medium">
              {entry.title}
              {entry.discipline && <span className="font-normal text-muted-foreground"> · {entry.discipline}</span>}
            </span>
            {(entry.division || entry.result) && (
              <span>{[entry.division && `Division: ${entry.division}`, entry.result && `Result: ${entry.result}`].filter(Boolean).join(' · ')}</span>
            )}
            {entry.notes && <span className="break-words text-muted-foreground">{entry.notes}</span>}
            {entry.recorded_by && (
              <span className="text-muted-foreground text-xs">
                {ENTRY_LABELS[entry.type]} recorded by {entry.recorded_by}
              </span>
            )}
          </div>
        </li>
      ))}
    </ol>
  )
}

/** One to `max` emergency contacts. Field names match the server's errors. */
export function ContactsEditor({ contacts, max, onChange }: { contacts: EmergencyContact[]; max: number; onChange: (next: EmergencyContact[]) => void }) {
  const update = (index: number, patch: Partial<EmergencyContact>) =>
    onChange(contacts.map((contact, position) => (position === index ? { ...contact, ...patch } : contact)))

  return (
    <div className="flex flex-col gap-3">
      {contacts.map((contact, index) => (
        <fieldset key={index} className="grid gap-3 rounded-lg border p-3 sm:grid-cols-[1fr_10rem_11rem_auto] sm:items-start">
          <legend className="sr-only">Emergency contact {index + 1}</legend>
          <Field name={`emergency_contacts.${index}.name`} className="w-full">
            <FieldLabel>Name</FieldLabel>
            <Input value={contact.name} onChange={(e) => update(index, { name: e.target.value })} maxLength={150} required autoComplete="off" />
            <FieldError />
          </Field>
          <Field name={`emergency_contacts.${index}.relationship`} className="w-full">
            <FieldLabel>Relationship</FieldLabel>
            <Input value={contact.relationship} onChange={(e) => update(index, { relationship: e.target.value })} maxLength={60} required placeholder="Parent, partner…" />
            <FieldError />
          </Field>
          <Field name={`emergency_contacts.${index}.phone`} className="w-full" validate={phoneRule}>
            <FieldLabel>Phone</FieldLabel>
            <Input type="tel" value={contact.phone} onChange={(e) => update(index, { phone: e.target.value })} maxLength={30} required inputMode="tel" />
            <FieldError />
          </Field>
          {contacts.length > 1 && (
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              className="sm:mt-6"
              aria-label={`Remove emergency contact ${index + 1}`}
              onClick={() => onChange(contacts.filter((_, position) => position !== index))}
            >
              <Trash2 aria-hidden />
            </Button>
          )}
        </fieldset>
      ))}
      {contacts.length < max && (
        <Button type="button" variant="outline" size="sm" className="self-start" onClick={() => onChange([...contacts, { name: '', relationship: '', phone: '' }])}>
          <Plus aria-hidden />
          Add another contact
        </Button>
      )}
    </div>
  )
}

/** Yes or no for each health question (R-32). Unanswered questions stay undefined. */
export function HealthQuestions({
  questions,
  answers,
  onChange,
  missing,
}: {
  questions: Record<string, string>
  answers: Record<string, boolean | undefined>
  onChange: (next: Record<string, boolean | undefined>) => void
  missing?: boolean
}) {
  return (
    <ul className="divide-y rounded-lg border">
      {Object.entries(questions).map(([key, question]) => {
        const value = answers[key]
        const unanswered = missing && value === undefined
        return (
          <li key={key} className="flex flex-col gap-2 px-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-6">
            <span id={`q-${key}`} className="text-sm">
              {question}
              {unanswered && <span className="mt-1 block text-destructive-foreground text-xs">Answer this question.</span>}
            </span>
            <Field name={`health_answers.${key}`} className="shrink-0">
              <RadioGroup
                aria-labelledby={`q-${key}`}
                className="flex-row gap-5"
                value={value === undefined ? '' : value ? 'yes' : 'no'}
                onValueChange={(next) => onChange({ ...answers, [key]: next === 'yes' })}
              >
                <label className="flex items-center gap-2 text-sm">
                  <Radio value="no" aria-label={`${question}: No`} aria-invalid={unanswered || undefined} /> No
                </label>
                <label className="flex items-center gap-2 text-sm">
                  <Radio value="yes" aria-label={`${question}: Yes`} aria-invalid={unanswered || undefined} /> Yes
                </label>
              </RadioGroup>
              <FieldError />
            </Field>
          </li>
        )
      })}
    </ul>
  )
}
