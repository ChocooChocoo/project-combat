import { minutesLabel } from '@/lib/hr'
import { dateLabel, deductionsOf, employerContributionsOf, money, periodLabel, type Payslip } from '@/lib/payroll'

/**
 * The one print-friendly payslip. Both the employee's own list and a released
 * run use it, so the printed sheet always has the same shape.
 */
export function PayslipSheet({ gymName, payslip }: { gymName: string; payslip: Payslip }) {
  const deductions = deductionsOf(payslip)
  const employer = employerContributionsOf(payslip)
  const attendance = Number(payslip.attendance_deduction) || 0
  const attendanceShown = attendance === 0 ? '0.00' : (attendance > 0 ? -attendance : attendance).toFixed(2)

  // A released line carries its stored snapshot. An older payload carries only
  // gross and net, so show those totals rather than a misleading base of zero.
  const earnings = payslip.has_breakdown
    ? [
        { key: 'base', label: 'Base pay', amount: payslip.base_pay },
        { key: 'attendance', label: 'Attendance and leave adjustment', amount: attendanceShown },
        { key: 'holiday', label: 'Holiday premium', amount: payslip.holiday_premium },
        { key: 'overtime', label: 'Overtime', amount: payslip.overtime_pay },
        { key: 'sessions', label: 'Session pay', amount: payslip.session_pay },
      ].filter((row) => row.key === 'base' || Number(row.amount) !== 0)
    : [{ key: 'gross', label: 'Gross pay (stored)', amount: payslip.gross_pay }]

  const statutory = [
    { key: 'sss', label: 'SSS', amount: payslip.sss_employee },
    { key: 'philhealth', label: 'PhilHealth', amount: payslip.philhealth_employee },
    { key: 'pagibig', label: 'Pag-IBIG', amount: payslip.pagibig_employee },
    { key: 'tax', label: 'Withholding tax', amount: payslip.tax },
  ]
  const itemised = statutory.some((row) => Number(row.amount) !== 0)
  // Show the stored components when the line snapshot came with them. The
  // aggregate stays as the fallback for older payloads that only carry totals.
  const deductionRows =
    itemised || (payslip.has_breakdown && deductions === 0)
      ? statutory
      : [{ key: 'statutory', label: 'Statutory and withholding tax', amount: String(deductions) }]

  const facts = [
    { key: 'scheduled', label: 'Scheduled days', value: String(payslip.scheduled_days) },
    { key: 'worked', label: 'Days worked', value: String(payslip.days_worked) },
    { key: 'present', label: 'Present days', value: String(payslip.days_present) },
    { key: 'late_days', label: 'Late days', value: String(payslip.days_late) },
    { key: 'absent', label: 'Absent days', value: String(payslip.days_absent) },
    { key: 'paid_leave', label: 'Paid leave', value: String(payslip.paid_leave_days) },
    { key: 'unpaid_leave', label: 'Unpaid leave', value: String(payslip.unpaid_leave_days) },
    { key: 'late', label: 'Late minutes', value: minutesLabel(payslip.late_minutes) },
    { key: 'undertime', label: 'Undertime', value: minutesLabel(payslip.undertime_minutes) },
    { key: 'overtime', label: 'Approved overtime', value: minutesLabel(payslip.overtime_minutes) },
    { key: 'sessions', label: 'Sessions', value: String(payslip.sessions_count) },
  ]

  return (
    <article
      className="print-area flex flex-col gap-6 bg-background p-6 text-foreground print:max-w-none print:gap-4 print:p-0"
      aria-label={`Payslip for ${payslip.name || 'this employee'}`}
      data-print-sheet
    >
      <header className="flex flex-wrap items-start justify-between gap-4 border-b pb-4">
        <div className="flex min-w-0 flex-col gap-1">
          <p className="font-medium text-muted-foreground text-sm">{gymName || payslip.gym_name || 'Gym'}</p>
          <h2 className="font-heading font-semibold text-2xl">Payslip</h2>
          <p className="text-sm">{periodLabel(payslip.starts_on, payslip.ends_on)}</p>
        </div>
        <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-right text-sm">
          <dt className="text-muted-foreground">Employee</dt>
          <dd className="font-medium">{payslip.name || 'Unknown employee'}</dd>
          {payslip.job_title && (
            <>
              <dt className="text-muted-foreground">Job title</dt>
              <dd>{payslip.job_title}</dd>
            </>
          )}
          {payslip.released_at && (
            <>
              <dt className="text-muted-foreground">Released</dt>
              <dd>{dateLabel(payslip.released_at)}</dd>
            </>
          )}
          {payslip.run_id && (
            <>
              <dt className="text-muted-foreground">Run</dt>
              <dd className="font-mono">#{payslip.run_id}</dd>
            </>
          )}
        </dl>
      </header>

      <div className="grid gap-6 sm:grid-cols-2 print:grid-cols-2">
        <section aria-labelledby="payslip-earnings" className="flex flex-col gap-2">
          <h3 id="payslip-earnings" className="font-medium text-sm">
            Earnings
          </h3>
          <table className="w-full border-collapse text-sm">
            <caption className="sr-only">Earnings for this pay period</caption>
            <tbody>
              {earnings.map((row) => (
                <tr key={row.key} className="border-b border-border/60 last:border-0">
                  <th scope="row" className="py-1.5 text-left font-normal">
                    {row.label}
                  </th>
                  <td className="py-1.5 text-right tabular-nums">{money(row.amount)}</td>
                </tr>
              ))}
              <tr className="border-t-2 border-foreground/20">
                <th scope="row" className="py-2 text-left font-semibold">
                  Gross pay
                </th>
                <td className="py-2 text-right font-semibold tabular-nums">{money(payslip.gross_pay)}</td>
              </tr>
            </tbody>
          </table>
        </section>

        <section aria-labelledby="payslip-deductions" className="flex flex-col gap-2">
          <h3 id="payslip-deductions" className="font-medium text-sm">
            Deductions
          </h3>
          <table className="w-full border-collapse text-sm">
            <caption className="sr-only">Statutory deductions for this pay period</caption>
            <tbody>
              {deductionRows.map((row) => (
                <tr key={row.key} className="border-b border-border/60 last:border-0">
                  <th scope="row" className="py-1.5 text-left font-normal">
                    {row.label}
                  </th>
                  <td className="py-1.5 text-right tabular-nums">{money(row.amount)}</td>
                </tr>
              ))}
              <tr className="border-t-2 border-foreground/20">
                <th scope="row" className="py-2 text-left font-semibold">
                  Total deductions
                </th>
                <td className="py-2 text-right font-semibold tabular-nums">{money(deductions)}</td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>

      {payslip.has_breakdown && (
        <section aria-labelledby="payslip-facts" className="flex flex-col gap-2">
          <h3 id="payslip-facts" className="font-medium text-sm">
            Attendance and sessions
          </h3>
          <dl className="grid grid-cols-2 gap-x-4 gap-y-1.5 rounded-xl border bg-card p-3 text-sm sm:grid-cols-3 print:grid-cols-3">
            {facts.map((fact) => (
              <div key={fact.key} className="flex flex-col gap-0.5">
                <dt className="text-muted-foreground text-xs">{fact.label}</dt>
                <dd className="tabular-nums">{fact.value}</dd>
              </div>
            ))}
          </dl>
        </section>
      )}

      {(payslip.warnings?.length ?? 0) > 0 && (
        <section aria-labelledby="payslip-warnings" className="flex flex-col gap-1 rounded-xl border border-warning/32 bg-warning/4 px-4 py-3 text-sm">
          <h3 id="payslip-warnings" className="font-medium">
            Notes on this payslip
          </h3>
          <ul className="flex list-disc flex-col gap-1 ps-4">
            {(payslip.warnings ?? []).map((warning, index) => (
              <li key={`${warning}-${index}`} className="break-words">
                {warning}
              </li>
            ))}
          </ul>
        </section>
      )}

      <section aria-labelledby="payslip-net" className="flex flex-wrap items-baseline justify-between gap-3 rounded-xl border bg-muted/40 px-4 py-3 print:border-foreground/20 print:bg-transparent">
        <h3 id="payslip-net" className="font-medium">
          Net pay
        </h3>
        <p className="font-heading font-semibold text-2xl tabular-nums">{money(payslip.net_pay)}</p>
      </section>

      {employer > 0 && (
        <section aria-labelledby="payslip-employer" className="flex flex-col gap-1 text-sm">
          <h3 id="payslip-employer" className="font-medium">
            Employer contributions
          </h3>
          <p className="text-muted-foreground">
            {[
              ['SSS', payslip.sss_employer],
              ['EC', payslip.ec_employer],
              ['PhilHealth', payslip.philhealth_employer],
              ['Pag-IBIG', payslip.pagibig_employer],
            ]
              .filter(([, amount]) => Number(amount) !== 0)
              .map(([label, amount]) => `${label} ${money(amount)}`)
              .join(' · ')}{' '}
            — paid by the gym, not taken from net pay.
          </p>
        </section>
      )}

      <footer className="border-t pt-3 text-muted-foreground text-xs">
        Computed from stored schedules, attendance, approved leave and overtime, holidays, sessions and the statutory rates effective for this period.
      </footer>
    </article>
  )
}
