import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { ATTENDANCE_LABELS, type AttendanceRow, type ProgressNote } from '@/lib/attendance'
import { dateLabel } from '@/lib/membership'
import { sessionTime } from '@/lib/sessions'

export function AttendanceList({ rows }: { rows: AttendanceRow[] }) {
  if (!rows.length) return <p className="text-muted-foreground text-sm">No attendance recorded for this selection.</p>
  const time = (value: string | null) => value ? sessionTime(value) : 'Not recorded'
  return <>
    <div className="hidden overflow-x-auto lg:block">
      <Table variant="card"><TableHeader><TableRow>{['Member', 'Session / visit', 'Date', 'Check-in', 'Check-out', 'Attendance', 'By'].map((label) => <TableHead key={label}>{label}</TableHead>)}</TableRow></TableHeader>
        <TableBody>{rows.map((row) => <TableRow key={row.id}>
          <TableCell className="max-w-48 whitespace-normal break-words">{row.member.name}</TableCell>
          <TableCell className="max-w-48 whitespace-normal break-words">{row.session?.name ?? 'Gym visit'}</TableCell>
          <TableCell>{dateLabel(row.date.slice(0, 10))}</TableCell><TableCell>{time(row.check_in_at)}</TableCell><TableCell>{time(row.check_out_at)}</TableCell>
          <TableCell>{ATTENDANCE_LABELS[row.attendance]}</TableCell><TableCell className="whitespace-normal">{row.by ?? 'Automatic'}</TableCell>
        </TableRow>)}</TableBody>
      </Table>
    </div>
    <ul className="flex flex-col gap-3 lg:hidden">{rows.map((row) => <li key={row.id} className="min-w-0 rounded-xl border p-4 text-sm">
      <p className="break-words font-medium">{row.member.name}</p><p className="break-words">{row.session?.name ?? 'Gym visit'} · {ATTENDANCE_LABELS[row.attendance]}</p>
      <p className="mt-2">{dateLabel(row.date.slice(0, 10))}</p><p>In: {time(row.check_in_at)} · Out: {time(row.check_out_at)}</p><p className="break-words text-muted-foreground">By {row.by ?? 'Automatic'}</p>
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
