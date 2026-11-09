import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { ATTENDANCE_LABELS, type Attendance, type AttendanceRow, type ProgressNote } from '@/lib/attendance'
import { dateLabel } from '@/lib/membership'
import { sessionTime } from '@/lib/sessions'

const ATTENDANCE_VARIANTS: Record<Attendance | 'gym_visit', 'success' | 'warning' | 'error' | 'info'> = {
  present: 'success',
  late: 'warning',
  absent: 'error',
  no_show: 'error',
  gym_visit: 'info',
}

function initials(name: string): string {
  return name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()
}

export function AttendanceList({ rows }: { rows: AttendanceRow[] }) {
  if (!rows.length) return <p className="text-muted-foreground text-sm">No attendance recorded for this selection.</p>
  const time = (value: string | null) => value ? sessionTime(value) : null
  const member = (row: AttendanceRow) => (
    <div className="flex min-w-0 items-center gap-3">
      <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted font-medium text-xs" aria-hidden>{initials(row.member.name)}</span>
      <span className="min-w-0 font-medium wrap-anywhere">{row.member.name}</span>
    </div>
  )
  const attendanceBadge = (row: AttendanceRow) => (
    <Badge variant={ATTENDANCE_VARIANTS[row.attendance]}>{ATTENDANCE_LABELS[row.attendance]}</Badge>
  )
  const clock = (value: string | null) => value
    ? <span className="tabular-nums">{time(value)}</span>
    : <span className="text-muted-foreground" aria-label="Not recorded">—</span>
  return <>
    <div className="hidden overflow-x-auto lg:block">
      <Table variant="card"><TableHeader><TableRow>{['Member', 'Session / visit', 'Date', 'Check-in', 'Check-out', 'Attendance', 'Recorded by'].map((label) => <TableHead key={label}>{label}</TableHead>)}</TableRow></TableHeader>
        <TableBody>{rows.map((row) => <TableRow key={row.id}>
          <TableCell className="max-w-48 whitespace-normal">{member(row)}</TableCell>
          <TableCell className="max-w-48 whitespace-normal">
            <p className="font-medium wrap-anywhere">{row.session?.name ?? 'Gym visit'}</p>
            <p className="mt-0.5 text-muted-foreground text-xs">{row.session ? 'Class session' : 'Open gym'}</p>
          </TableCell>
          <TableCell><span className="whitespace-nowrap tabular-nums">{dateLabel(row.date.slice(0, 10))}</span></TableCell>
          <TableCell>{clock(row.check_in_at)}</TableCell>
          <TableCell>{clock(row.check_out_at)}</TableCell>
          <TableCell>{attendanceBadge(row)}</TableCell>
          <TableCell><span className={row.by ? 'whitespace-normal text-sm' : 'text-muted-foreground text-sm'}>{row.by ?? 'Automatic'}</span></TableCell>
        </TableRow>)}</TableBody>
      </Table>
    </div>
    <ul className="flex flex-col gap-3 lg:hidden">{rows.map((row) => <li key={row.id} className="flex min-w-0 flex-col gap-3 rounded-xl border bg-card p-4 text-sm">
      <div className="flex items-start justify-between gap-3">
        {member(row)}
        {attendanceBadge(row)}
      </div>
      <div className="min-w-0">
        <p className="font-medium wrap-anywhere">{row.session?.name ?? 'Gym visit'}</p>
        <p className="mt-0.5 text-muted-foreground text-xs">{dateLabel(row.date.slice(0, 10))}</p>
      </div>
      <dl className="grid grid-cols-2 gap-3 border-t pt-3">
        <div>
          <dt className="text-muted-foreground text-xs">Check-in</dt>
          <dd className="mt-0.5 tabular-nums">{clock(row.check_in_at)}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground text-xs">Check-out</dt>
          <dd className="mt-0.5 tabular-nums">{clock(row.check_out_at)}</dd>
        </div>
      </dl>
      <p className="text-muted-foreground text-xs">Recorded by {row.by ?? 'Automatic'}</p>
    </li>)}</ul>
  </>
}

export function ProgressNotes({ notes }: { notes: ProgressNote[] }) {
  if (!notes.length) return <p className="text-muted-foreground text-sm">No progress notes yet.</p>
  return <ul className="flex flex-col gap-3">{notes.map((note) => <li key={note.id} className="min-w-0 rounded-lg border p-3 text-sm">
    <p className="break-words font-medium">{note.session.name} · {dateLabel(note.date)}</p>
    <p className="break-words text-muted-foreground">{note.author}</p><p className="mt-2 whitespace-pre-wrap break-words">{note.body}</p>
  </li>)}</ul>
}
