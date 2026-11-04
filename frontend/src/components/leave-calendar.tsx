import { Calendar } from '@/components/ui/calendar'
import { cn } from '@/lib/utils'

/** A holiday row. National holidays arrive without an id, so they cannot be removed. */
export type CalendarHoliday = {
  id?: number | null
  date: string
  name: string
  national?: boolean
}

/** The parts of a leave request the calendar needs. */
export type CalendarLeave = {
  id: number
  starts_on: string
  ends_on: string
  status?: string
}

export function isoDay(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

function parseDay(value: string): Date | null {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value)
  if (!match) return null
  const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
  return Number.isNaN(date.getTime()) ? null : date
}

export function formatDay(value: string): string {
  const date = parseDay(value)
  return date ? date.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : value
}

export function formatDateRange(start: string, end: string): string {
  return start === end ? formatDay(start) : `${formatDay(start)} – ${formatDay(end)}`
}

/** Every calendar day a leave request covers. A broken or reversed range adds nothing. */
function daysCovered(start: string, end: string): Date[] {
  const first = parseDay(start)
  const last = parseDay(end)
  if (!first || !last || last < first) return []

  const days: Date[] = []
  const cursor = new Date(first)
  while (cursor <= last && days.length < 3660) {
    days.push(new Date(cursor))
    cursor.setDate(cursor.getDate() + 1)
  }
  return days
}

/**
 * R-27: a read-only month calendar with national holidays, gym holidays and
 * approved leave marked. The arrows change the month; the page owns that state.
 */
export function LeaveCalendar({
  holidays,
  leaves = [],
  leaveDates,
  month,
  onMonthChange,
  className,
  label = 'Leave calendar',
}: {
  holidays: CalendarHoliday[]
  leaves?: CalendarLeave[]
  /** Approved single dates, when the API already returns them one by one. */
  leaveDates?: string[]
  month: Date
  onMonthChange: (month: Date) => void
  className?: string
  label?: string
}) {
  const holidayDates = holidays.map((holiday) => parseDay(holiday.date)).filter((date): date is Date => date !== null)
  const approvedDates = leaveDates
    ? leaveDates.map((date) => parseDay(date)).filter((date): date is Date => date !== null)
    : leaves
        .filter((leave) => !leave.status || leave.status === 'approved')
        .flatMap((leave) => daysCovered(leave.starts_on, leave.ends_on))

  return (
    <div className={cn('flex flex-col gap-4', className)}>
      <div className="-mx-1 overflow-x-auto px-1" role="group" aria-label={label}>
        <Calendar
          mode="single"
          month={month}
          onMonthChange={onMonthChange}
          showOutsideDays={false}
          modifiers={{ holiday: holidayDates, leave: approvedDates }}
          modifiersClassNames={{
            holiday: '[&>button]:bg-warning/16 [&>button]:font-semibold [&>button]:text-warning-foreground',
            leave: '[&>button]:bg-success/16 [&>button]:text-success-foreground',
          }}
          className="mx-auto"
        />
      </div>
      <div className="flex flex-wrap gap-x-5 gap-y-2 text-muted-foreground text-sm">
        <span className="inline-flex items-center gap-2">
          <span aria-hidden className="size-3.5 rounded-[4px] border border-warning/40 bg-warning/16" />
          Holiday
        </span>
        <span className="inline-flex items-center gap-2">
          <span aria-hidden className="size-3.5 rounded-[4px] border border-success/40 bg-success/16" />
          Approved leave
        </span>
      </div>
    </div>
  )
}
