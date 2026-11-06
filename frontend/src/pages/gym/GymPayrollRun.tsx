import { ArrowLeft, Check, ChevronDown, ChevronRight, Play, Printer, Send, X } from 'lucide-react'
import { Fragment, useEffect, useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router'
import { PayslipSheet } from '@/components/payroll/payslip-sheet'
import { PageHeader } from '@/components/page-header'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import { minutesLabel } from '@/lib/hr'
import {
  countLabel,
  currentStepLabel,
  currentStepOf,
  dateTimeLabel,
  deductionsOf,
  employmentTypeLabel,
  isFinalStep,
  lineDeductions,
  money,
  payslipFromLine,
  periodLabel,
  readRunDetail,
  runStatusMeta,
  stepLabel,
  type PayrollApproval,
  type PayrollLine,
  type PayrollRun,
  type PayrollRunDetail,
  type Payslip,
} from '@/lib/payroll'

function message(reason: unknown, fallback: string): string {
  return reason instanceof ApiError ? reason.message : fallback
}

function formErrors(reason: unknown, known: string[], fallback: string): FieldErrors {
  const errors = reason instanceof ApiError ? reason.errors : {}
  const keys = Object.keys(errors)
  if (keys.length === 0 || keys.some((key) => !known.includes(key))) {
    toastManager.add({ title: message(reason, fallback), type: 'error' })
  }
  return errors
}

/** S-31, R-59 to R-62: one payroll run from draft through approval to payslips. */
export default function GymPayrollRun() {
  const { gymId: gymIdParam, runId: runIdParam } = useParams()
  const gymId = Number(gymIdParam)
  const runId = Number(runIdParam)
  const { user } = useAuth()
  const gym = approvedGym(user, gymId)

  const canView = gym ? can(gym, 'payroll.runs.view') : false
  const canManage = gym ? can(gym, 'payroll.runs.manage') : false
  const canApprove = gym ? can(gym, 'payroll.runs.approve') : false
  const isOwner = gym?.roles.includes('owner') ?? false

  const [detail, setDetail] = useState<PayrollRunDetail | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [busy, setBusy] = useState('')
  const [expanded, setExpanded] = useState<Set<number>>(() => new Set())
  const [submitOpen, setSubmitOpen] = useState(false)
  const [decision, setDecision] = useState<{ decision: 'approve' | 'reject'; release: boolean } | null>(null)
  const [payslipOf, setPayslipOf] = useState<Payslip | null>(null)

  useEffect(() => {
    if (!gymId || !runId || !canView) return
    let live = true
    setDetail(null)
    setError('')
    api<Record<string, unknown>>(`/gyms/${gymId}/payroll/runs/${runId}`)
      .then((payload) => {
        if (live) setDetail(readRunDetail(payload))
      })
      .catch((reason) => {
        if (live) setError(message(reason, 'The payroll run could not be loaded.'))
      })
    return () => {
      live = false
    }
  }, [gymId, runId, canView, version])

  if (!gym) return null

  if (!canView) {
    return (
      <div className="flex flex-col gap-6">
        <BackLink gymId={gymId} />
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>Payroll is not available</EmptyTitle>
            <EmptyDescription>Ask the owner for the payroll permission.</EmptyDescription>
          </EmptyHeader>
        </Empty>
      </div>
    )
  }

  if (error) {
    return (
      <div className="flex flex-col gap-6">
        <BackLink gymId={gymId} />
        <PageHeader title="Payroll run" />
        <Alert variant="error">
          <AlertTitle>The payroll run could not be loaded</AlertTitle>
          <AlertDescription>{error}</AlertDescription>
          <AlertAction>
            <Button size="xs" variant="outline" onClick={() => setVersion((value) => value + 1)}>
              Try again
            </Button>
          </AlertAction>
        </Alert>
      </div>
    )
  }

  if (detail === null) {
    return (
      <div className="flex flex-col gap-6" aria-busy="true">
        <BackLink gymId={gymId} />
        <Skeleton className="h-9 w-72" />
        <Skeleton className="h-24" />
        <Skeleton className="h-96" />
      </div>
    )
  }

  const { run, lines, approvals, warnings, payslips } = detail
  const status = runStatusMeta(run.status)
  const blocking = warnings.filter((warning) => warning.blocking)
  const lineWarnings = lines.reduce((total, line) => total + line.warnings.length, 0)
  const current = currentStepOf(run)
  const final = isFinalStep(run)
  const selfPrepared = run.prepared_by !== null && run.prepared_by === user?.id
  const actedThisRound = approvals.some(
    (approval) => approval.round === run.round && approval.decision === 'approved' && approval.user_id === user?.id,
  )
  const ownerFinalRelease = final && isOwner

  let canDecide = run.status === 'in_review' && canApprove
  if (final && !isOwner) canDecide = false
  if (actedThisRound) canDecide = false
  if (selfPrepared && !ownerFinalRelease) canDecide = false
  if (run.explicit_can_decide !== null) canDecide = canApprove && run.explicit_can_decide

  const canCompute = run.status === 'draft' && canManage && run.explicit_can_compute !== false
  const canSubmit =
    run.status === 'draft' &&
    canManage &&
    (run.explicit_can_submit ?? blocking.length === 0) &&
    (run.explicit_can_submit === true || blocking.length === 0)

  const linePayslips =
    payslips.length > 0
      ? payslips
      : run.status === 'released'
        ? lines.map((line) => payslipFromLine(line, run, gym.name))
        : []

  function refresh() {
    setVersion((value) => value + 1)
  }

  function toggleLine(line: PayrollLine) {
    setExpanded((currentSet) => {
      const next = new Set(currentSet)
      if (next.has(line.id)) next.delete(line.id)
      else next.add(line.id)
      return next
    })
  }

  async function compute() {
    if (!canCompute || busy) return
    setBusy('compute')
    try {
      await api(`/gyms/${gymId}/payroll/runs/${runId}/compute`, { method: 'POST' })
      toastManager.add({ title: 'Payroll computed from stored records.', type: 'success' })
      refresh()
    } catch (reason) {
      toastManager.add({ title: message(reason, 'The payroll could not be computed.'), type: 'error' })
    } finally {
      setBusy('')
    }
  }

  const actions =
    canCompute || canSubmit || canDecide ? (
      <div className="flex flex-wrap items-center gap-2">
        {canCompute && (
          <Button size="sm" variant="outline" loading={busy === 'compute'} disabled={busy !== ''} onClick={compute}>
            <Play aria-hidden />
            Compute
          </Button>
        )}
        {canSubmit && (
          <Button size="sm" disabled={busy !== ''} onClick={() => setSubmitOpen(true)}>
            <Send aria-hidden />
            Submit for approval
          </Button>
        )}
        {canDecide && (
          <>
            <Button
              size="sm"
              onClick={() => setDecision({ decision: 'approve', release: final })}
              disabled={busy !== ''}
            >
              <Check aria-hidden />
              {final ? 'Release payroll' : 'Approve'}
            </Button>
            <Button
              size="sm"
              variant="destructive-outline"
              onClick={() => setDecision({ decision: 'reject', release: false })}
              disabled={busy !== ''}
            >
              <X aria-hidden />
              Reject
            </Button>
          </>
        )}
      </div>
    ) : undefined

  return (
    <div className="flex flex-col gap-6">
      <BackLink gymId={gymId} />

      <PageHeader
        title={`Payroll · ${periodLabel(run.starts_on, run.ends_on)}`}
        description={`${gym.name}: run #${run.id}, round ${run.round}. ${lines.length} ${lines.length === 1 ? 'line' : 'lines'}.`}
        meta={
          <div className="flex flex-wrap items-center gap-1.5">
            <Badge variant={status.variant}>{status.label}</Badge>
            {run.self_released && <Badge variant="warning">Self-released</Badge>}
          </div>
        }
        actions={actions}
      />

      <Totals run={run} lineCount={lines.length} />

      {run.status === 'draft' && run.rejection_reason && (
        <Alert variant="error">
          <AlertTitle>Returned to draft</AlertTitle>
          <AlertDescription>{run.rejection_reason}</AlertDescription>
        </Alert>
      )}

      {run.status === 'draft' && (
        <Alert variant={blocking.length > 0 ? 'warning' : 'info'}>
          <AlertTitle>
            {blocking.length > 0
              ? `${blocking.length} blocking ${blocking.length === 1 ? 'warning' : 'warnings'}`
              : run.computed_at
                ? 'Draft computed'
                : 'Draft not computed yet'}
          </AlertTitle>
          <AlertDescription>
            {blocking.length > 0
              ? 'Fix these before submitting. A missing pay basis, rate or statutory table stops the run.'
              : run.computed_at
                ? `Computed ${dateTimeLabel(run.computed_at)}. Submit when the figures look right; lines freeze at submit.`
                : 'Compute to create every line from stored schedules, attendance, leave, overtime, holidays and sessions.'}
          </AlertDescription>
        </Alert>
      )}

      {run.status === 'in_review' && (
        <Alert variant="warning">
          <AlertTitle>In review at {current?.label ?? 'the next step'}</AlertTitle>
          <AlertDescription>
            Round {run.round}. {currentStepLabel(run)} must decide. Rejection returns the run to draft with a new round. The period is locked while in review.
          </AlertDescription>
        </Alert>
      )}

      {run.status === 'released' && (
        <Alert variant="success">
          <AlertTitle>Released</AlertTitle>
          <AlertDescription>
            {run.released_at ? `Released ${dateTimeLabel(run.released_at)}` : 'Released'}
            {run.released_by_name ? ` by ${run.released_by_name}` : ''}. Gross pay and the employer statutory share were posted as expenses. Payslips are below.
            {run.self_released ? ' The owner prepared and released this run; every earlier approval step is recorded in the history.' : ''}
          </AlertDescription>
        </Alert>
      )}

      {warnings.length > 0 && (
        <Alert variant={blocking.length > 0 ? 'error' : 'warning'}>
          <AlertTitle>
            {blocking.length > 0
              ? 'Blocking warnings'
              : 'Warnings'}
          </AlertTitle>
          <AlertDescription>
            <ul className="flex list-disc flex-col gap-1 ps-4">
              {warnings.map((warning, index) => (
                <li key={`${warning.message}-${index}`} className="break-words">
                  {warning.employee ? `${warning.employee}: ` : ''}
                  {warning.message}
                  {!warning.blocking && <span className="text-muted-foreground"> (not blocking)</span>}
                </li>
              ))}
            </ul>
          </AlertDescription>
        </Alert>
      )}

      {lineWarnings > 0 && (
        <Alert variant="warning">
          <AlertTitle>{countLabel(lineWarnings, 'line warning', 'line warnings')}</AlertTitle>
          <AlertDescription>
            Open a line below to see what was left out, such as a session that did not meet the pay rule. These do not stop submission on their own.
          </AlertDescription>
        </Alert>
      )}

      <LinesTable
        lines={lines}
        expanded={expanded}
        onToggle={toggleLine}
        empty={run.status === 'draft' && !run.computed_at}
      />

      <ApprovalTimeline run={run} approvals={approvals} />

      {run.status === 'released' && (
        <section aria-labelledby="payslips-heading" className="flex flex-col gap-3">
          <div className="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="payslips-heading" className="font-heading font-semibold text-base">
              Payslips
            </h2>
            <p className="text-muted-foreground text-sm">Released. Each employee sees only their own payslip.</p>
          </div>
          {linePayslips.length === 0 ? (
            <p className="rounded-xl border px-4 py-3 text-muted-foreground text-sm">No payslip lines were returned for this run.</p>
          ) : (
            <PayslipList payslips={linePayslips} onOpen={setPayslipOf} />
          )}
        </section>
      )}

      {run.status !== 'released' && lines.length > 0 && (
        <p className="text-muted-foreground text-sm">Payslips appear after the final approval releases this run.</p>
      )}

      {submitOpen && (
        <SubmitDialog
          gymId={gymId}
          run={run}
          onClose={() => setSubmitOpen(false)}
          onSaved={() => {
            setSubmitOpen(false)
            refresh()
          }}
        />
      )}

      {decision && (
        <DecisionDialog
          gymId={gymId}
          run={run}
          decision={decision.decision}
          release={decision.release}
          onClose={() => setDecision(null)}
          onSaved={() => {
            setDecision(null)
            refresh()
          }}
        />
      )}

      <Dialog open={payslipOf !== null} onOpenChange={(open) => !open && setPayslipOf(null)}>
        <DialogPopup className="sm:max-w-2xl">
          {payslipOf && (
            <>
              <DialogHeader>
                <DialogTitle>Payslip</DialogTitle>
                <DialogDescription>{periodLabel(payslipOf.starts_on, payslipOf.ends_on)}</DialogDescription>
              </DialogHeader>
              <div className="max-h-[70vh] overflow-y-auto px-2 print:hidden">
                <PayslipSheet gymName={gym.name} payslip={payslipOf} />
              </div>
              <DialogFooter>
                <Button variant="outline" onClick={() => setPayslipOf(null)}>
                  Close
                </Button>
                <Button onClick={() => window.print()}>
                  <Printer aria-hidden />
                  Print payslip
                </Button>
              </DialogFooter>
            </>
          )}
        </DialogPopup>
      </Dialog>

      {payslipOf && (
        <div className="hidden print:block">
          <PayslipSheet gymName={gym.name} payslip={payslipOf} />
        </div>
      )}
    </div>
  )
}

function BackLink({ gymId }: { gymId: number }) {
  return (
    <Link
      to={`/gyms/${gymId}/manage/payroll`}
      className="inline-flex w-fit items-center gap-1.5 text-muted-foreground text-sm underline-offset-4 hover:text-foreground hover:underline"
    >
      <ArrowLeft className="size-4" aria-hidden /> Back to payroll
    </Link>
  )
}

function Totals({ run, lineCount }: { run: PayrollRun; lineCount: number }) {
  const cells = [
    { key: 'headcount', label: 'Headcount', value: String(run.headcount || lineCount) },
    { key: 'gross', label: 'Gross pay', value: money(run.gross_total) },
    { key: 'employee', label: 'Employee statutory', value: money(run.employee_statutory_total) },
    { key: 'tax', label: 'Withholding tax', value: money(run.tax_total) },
    { key: 'net', label: 'Net pay', value: money(run.net_total) },
    { key: 'employer', label: 'Employer cost', value: money(run.employer_statutory_total) },
  ]
  return (
    <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border bg-border sm:grid-cols-3 xl:grid-cols-6">
      {cells.map((cell) => (
        <div key={cell.key} className="bg-card px-3 py-2.5">
          <dt className="text-muted-foreground text-xs">{cell.label}</dt>
          <dd className="mt-1 font-semibold tabular-nums">{cell.value}</dd>
        </div>
      ))}
    </dl>
  )
}

function LinesTable({
  lines,
  expanded,
  onToggle,
  empty,
}: {
  lines: PayrollLine[]
  expanded: Set<number>
  onToggle: (line: PayrollLine) => void
  empty: boolean
}) {
  if (lines.length === 0) {
    return (
      <Empty className="rounded-xl border">
        <EmptyHeader>
          <EmptyTitle>{empty ? 'Not computed yet' : 'No lines in this run'}</EmptyTitle>
          <EmptyDescription>
            {empty
              ? 'Compute the draft to build a line for every employee and coach whose employment overlaps the period.'
              : 'No employee records overlap this period, or the lines could not be returned.'}
          </EmptyDescription>
        </EmptyHeader>
      </Empty>
    )
  }

  return (
    <section aria-labelledby="lines-heading" className="flex flex-col gap-3">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="lines-heading" className="font-heading font-semibold text-base">
          Lines
        </h2>
        <p className="text-muted-foreground text-sm">{countLabel(lines.length, 'employee', 'employees')}</p>
      </div>

      <ul className="flex flex-col divide-y rounded-xl border bg-card md:hidden">
        {lines.map((line) => {
          const open = expanded.has(line.id)
          return (
            <li key={line.id} className="flex min-w-0 flex-col gap-3 p-4">
              <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-0.5">
                  <span className="break-words font-medium">{line.name}</span>
                  <span className="text-muted-foreground text-sm">
                    {line.job_title || 'No job title'} · {employmentTypeLabel(line.employment_type)}
                  </span>
                </div>
                <span className="shrink-0 font-semibold tabular-nums">{money(line.net_pay)}</span>
              </div>
              <dl className="grid grid-cols-2 gap-2 text-sm">
                <div>
                  <dt className="text-muted-foreground text-xs">Gross</dt>
                  <dd className="tabular-nums">{money(line.gross_pay)}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground text-xs">Deductions</dt>
                  <dd className="tabular-nums">{money(lineDeductions(line))}</dd>
                </div>
              </dl>
              {line.warnings.length > 0 && (
                <p className="text-warning-foreground text-xs">{countLabel(line.warnings.length, 'warning', 'warnings')}</p>
              )}
              <Button
                size="sm"
                variant="ghost"
                className="self-start"
                aria-expanded={open}
                aria-controls={`line-breakdown-mobile-${line.id}`}
                onClick={() => onToggle(line)}
              >
                {open ? <ChevronDown aria-hidden /> : <ChevronRight aria-hidden />}
                {open ? 'Hide breakdown' : 'Show breakdown'}
              </Button>
              {open && (
                <div id={`line-breakdown-mobile-${line.id}`}>
                  <LineBreakdown line={line} />
                </div>
              )}
            </li>
          )
        })}
      </ul>

      <div className="hidden min-w-0 overflow-x-auto md:block">
        <Table variant="card" className="min-w-[58rem]">
          <TableHeader>
            <TableRow>
              <TableHead>Employee</TableHead>
              <TableHead>Employment</TableHead>
              <TableHead className="text-right">Gross</TableHead>
              <TableHead className="text-right">Deductions</TableHead>
              <TableHead className="text-right">Net</TableHead>
              <TableHead>
                <span className="sr-only">Breakdown</span>
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {lines.map((line) => {
              const open = expanded.has(line.id)
              return (
                <Fragment key={line.id}>
                  <TableRow>
                    <TableCell>
                      <div className="flex flex-col gap-0.5">
                        <span className="font-medium">{line.name}</span>
                        <span className="text-muted-foreground text-xs">{line.job_title || 'No job title'}</span>
                        {line.warnings.length > 0 && (
                          <span className="text-warning-foreground text-xs">{countLabel(line.warnings.length, 'warning', 'warnings')}</span>
                        )}
                      </div>
                    </TableCell>
                    <TableCell>{employmentTypeLabel(line.employment_type)}</TableCell>
                    <TableCell className="text-right tabular-nums">{money(line.gross_pay)}</TableCell>
                    <TableCell className="text-right tabular-nums">{money(lineDeductions(line))}</TableCell>
                    <TableCell className="text-right font-semibold tabular-nums">{money(line.net_pay)}</TableCell>
                    <TableCell>
                      <div className="flex justify-end">
                        <Button
                          size="xs"
                          variant="ghost"
                          aria-expanded={open}
                          aria-controls={`line-breakdown-${line.id}`}
                          onClick={() => onToggle(line)}
                        >
                          {open ? 'Hide' : 'Breakdown'}
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                  {open && (
                    <TableRow>
                      <TableCell colSpan={6} className="whitespace-normal bg-muted/40 p-3">
                        <div id={`line-breakdown-${line.id}`}>
                          <LineBreakdown line={line} />
                        </div>
                      </TableCell>
                    </TableRow>
                  )}
                </Fragment>
              )
            })}
          </TableBody>
        </Table>
      </div>
    </section>
  )
}

function attendanceAmount(value: string): number {
  const amount = Number(value)
  if (!Number.isFinite(amount) || amount === 0) return 0
  return amount > 0 ? -amount : amount
}

function LineBreakdown({ line }: { line: PayrollLine }) {
  const attendance = attendanceAmount(line.attendance_deduction)
  const earnings = [
    { key: 'base', label: 'Base pay', amount: line.base_pay },
    { key: 'attendance', label: 'Attendance and leave adjustment', amount: attendance.toFixed(2) },
    { key: 'holiday', label: 'Holiday premium', amount: line.holiday_premium },
    { key: 'overtime', label: 'Overtime', amount: line.overtime_pay },
    { key: 'sessions', label: 'Sessions', amount: line.session_pay },
  ]
  const deductions = [
    { key: 'sss', label: 'SSS', amount: line.sss_employee },
    { key: 'philhealth', label: 'PhilHealth', amount: line.philhealth_employee },
    { key: 'pagibig', label: 'Pag-IBIG', amount: line.pagibig_employee },
    { key: 'tax', label: 'Withholding tax', amount: line.tax },
  ]
  const employer = [
    { key: 'sss', label: 'SSS employer', amount: line.sss_employer },
    { key: 'ec', label: 'EC employer', amount: line.ec_employer },
    { key: 'philhealth', label: 'PhilHealth employer', amount: line.philhealth_employer },
    { key: 'pagibig', label: 'Pag-IBIG employer', amount: line.pagibig_employer },
  ]

  return (
    <div className="flex flex-col gap-5">
      <div className="grid gap-5 lg:grid-cols-2">
        <div className="flex flex-col gap-1.5">
          <h3 className="font-medium text-sm">Earnings</h3>
          <dl className="flex flex-col divide-y rounded-lg border bg-card">
            {earnings.map((row) => (
              <div key={row.key} className="flex items-baseline justify-between gap-3 px-3 py-1.5 text-sm">
                <dt>{row.label}</dt>
                <dd className="tabular-nums">{money(row.amount)}</dd>
              </div>
            ))}
            <div className="flex items-baseline justify-between gap-3 border-t-2 px-3 py-2 font-medium text-sm">
              <dt>Gross pay</dt>
              <dd className="tabular-nums">{money(line.gross_pay)}</dd>
            </div>
          </dl>
        </div>

        <div className="flex flex-col gap-1.5">
          <h3 className="font-medium text-sm">Deductions</h3>
          <dl className="flex flex-col divide-y rounded-lg border bg-card">
            {deductions.map((row) => (
              <div key={row.key} className="flex items-baseline justify-between gap-3 px-3 py-1.5 text-sm">
                <dt>{row.label}</dt>
                <dd className="tabular-nums">{money(row.amount)}</dd>
              </div>
            ))}
            <div className="flex items-baseline justify-between gap-3 border-t-2 px-3 py-2 font-medium text-sm">
              <dt>Net pay</dt>
              <dd className="tabular-nums">{money(line.net_pay)}</dd>
            </div>
          </dl>
        </div>
      </div>

      <div className="grid gap-5 lg:grid-cols-2">
        <section aria-labelledby={`counts-${line.id}`} className="flex flex-col gap-1.5">
          <h3 id={`counts-${line.id}`} className="font-medium text-sm">
            Counts and rates
          </h3>
          <dl className="grid grid-cols-2 gap-x-4 gap-y-1.5 rounded-lg border bg-card p-3 text-sm sm:grid-cols-3">
            <Fact label="Scheduled days" value={String(line.scheduled_days)} />
            <Fact label="Days worked" value={String(line.days_worked)} />
            <Fact label="Absent days" value={String(line.days_absent)} />
            <Fact label="Paid leave" value={String(line.paid_leave_days)} />
            <Fact label="Unpaid leave" value={String(line.unpaid_leave_days)} />
            <Fact label="Holidays" value={String(line.holiday_days)} />
            <Fact label="Late" value={minutesLabel(line.late_minutes)} />
            <Fact label="Undertime" value={minutesLabel(line.undertime_minutes)} />
            <Fact label="Approved overtime" value={minutesLabel(line.overtime_minutes)} />
            <Fact label="Sessions" value={String(line.sessions_count)} />
            <Fact label="Pay basis" value={line.pay_basis ? line.pay_basis.replaceAll('_', ' ') : 'Not set'} />
            <Fact label="Monthly rate" value={money(line.monthly_rate)} />
            <Fact label="Per session" value={money(line.per_session_rate)} />
            <Fact label="Daily rate" value={money(line.daily_rate)} />
            <Fact label="Minute rate" value={money(line.minute_rate)} />
          </dl>
        </section>

        <section aria-labelledby={`employer-${line.id}`} className="flex flex-col gap-1.5">
          <h3 id={`employer-${line.id}`} className="font-medium text-sm">
            Employer contributions
          </h3>
          {line.employment_type === 'contractor' ? (
            <p className="rounded-lg border bg-card px-3 py-2 text-muted-foreground text-sm">
              Contractor: no employee statutory deductions or employer contributions.
            </p>
          ) : (
            <dl className="flex flex-col divide-y rounded-lg border bg-card">
              {employer.map((row) => (
                <div key={row.key} className="flex items-baseline justify-between gap-3 px-3 py-1.5 text-sm">
                  <dt>{row.label}</dt>
                  <dd className="tabular-nums">{money(row.amount)}</dd>
                </div>
              ))}
            </dl>
          )}
        </section>
      </div>

      {line.items.length > 0 && (
        <section aria-labelledby={`items-${line.id}`} className="flex flex-col gap-1.5">
          <h3 id={`items-${line.id}`} className="font-medium text-sm">
            Stored line items
          </h3>
          <ul className="flex flex-col divide-y rounded-lg border bg-card">
            {line.items.map((item, index) => (
              <li key={`${item.id ?? item.kind}-${index}`} className="flex flex-wrap items-baseline justify-between gap-2 px-3 py-1.5 text-sm">
                <span className="min-w-0 break-words">
                  {item.label}
                  {item.quantity !== null && <span className="text-muted-foreground"> · {item.quantity}</span>}
                  {item.rate !== null && <span className="text-muted-foreground"> × {money(item.rate)}</span>}
                </span>
                <span className="tabular-nums">{money(item.amount)}</span>
              </li>
            ))}
          </ul>
        </section>
      )}

      {line.warnings.length > 0 && (
        <section aria-labelledby={`warnings-${line.id}`} className="flex flex-col gap-1.5">
          <h3 id={`warnings-${line.id}`} className="font-medium text-sm">
            Warnings for this line
          </h3>
          <ul className="flex list-disc flex-col gap-1 rounded-lg border border-warning/32 bg-warning/4 px-6 py-3 text-sm">
            {line.warnings.map((warning, index) => (
              <li key={`${warning}-${index}`} className="break-words">
                {warning}
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  )
}

function Fact({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col gap-0.5">
      <dt className="text-muted-foreground text-xs">{label}</dt>
      <dd className="tabular-nums">{value}</dd>
    </div>
  )
}

function ApprovalTimeline({ run, approvals }: { run: PayrollRun; approvals: PayrollApproval[] }) {
  const rounds = [...new Set(approvals.map((approval) => approval.round))].sort((a, b) => b - a)

  return (
    <section aria-labelledby="approval-heading" className="flex flex-col gap-4">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="approval-heading" className="font-heading font-semibold text-base">
          Approval
        </h2>
        <p className="text-muted-foreground text-sm">
          {run.steps.map((step, index) => `${index + 1}. ${step.label}`).join(' → ')}
        </p>
      </div>

      {approvals.length === 0 ? (
        <p className="rounded-xl border px-4 py-3 text-muted-foreground text-sm">
          {run.status === 'draft'
            ? 'Not submitted yet. The chain starts when the run is submitted.'
            : run.status === 'in_review'
              ? `Waiting at ${currentStepLabel(run)}. No decision has been recorded in this round yet.`
              : 'No approval history was returned for this released run.'}
        </p>
      ) : (
        <div className="flex flex-col gap-5">
          {rounds.map((round) => {
            const rows = approvals
              .filter((approval) => approval.round === round)
              .sort((a, b) => a.step_index - b.step_index || (a.decided_at ?? '').localeCompare(b.decided_at ?? ''))
            return (
              <section key={round} aria-labelledby={`round-${round}`} className="flex flex-col gap-2">
                <h3 id={`round-${round}`} className="font-medium text-sm">
                  Round {round}
                  {round === run.round && run.status === 'in_review' && <Badge variant="warning" className="ms-2">Current</Badge>}
                </h3>
                <ol className="flex flex-col divide-y rounded-xl border bg-card">
                  {rows.map((approval, index) => (
                    <li key={`${approval.id ?? 'row'}-${approval.step_index}-${index}`} className="flex flex-col gap-1.5 p-3 text-sm">
                      <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <span className="font-medium">{approval.step_label || stepLabel(approval.step_key)}</span>
                        <div className="flex flex-wrap items-center gap-2">
                          <Badge variant={approval.decision === 'rejected' ? 'error' : approval.decision === 'approved' ? 'success' : 'outline'}>
                            {approval.decision === 'approved' ? 'Approved' : approval.decision === 'rejected' ? 'Rejected' : 'Pending'}
                          </Badge>
                          {approval.decided_at && (
                            <time className="text-muted-foreground text-xs tabular-nums" dateTime={approval.decided_at}>
                              {dateTimeLabel(approval.decided_at)}
                            </time>
                          )}
                        </div>
                      </div>
                      <p className="text-muted-foreground">{approval.user_name || 'Unknown person'}</p>
                      {approval.reason && <p className="break-words rounded-lg bg-muted px-2 py-1.5">{approval.reason}</p>}
                    </li>
                  ))}
                  {round === run.round && run.status === 'in_review' && (
                    <li className="flex flex-wrap items-baseline justify-between gap-2 p-3 text-sm">
                      <span className="font-medium">{currentStepLabel(run)}</span>
                      <Badge variant="warning">Waiting for a decision</Badge>
                    </li>
                  )}
                </ol>
              </section>
            )
          })}
        </div>
      )}
    </section>
  )
}

function PayslipList({ payslips, onOpen }: { payslips: Payslip[]; onOpen: (payslip: Payslip) => void }) {
  return (
    <>
      <ul className="flex flex-col divide-y rounded-xl border bg-card md:hidden">
        {payslips.map((payslip) => (
          <li key={`${payslip.line_id}-${payslip.user_id ?? 'employee'}`} className="flex min-w-0 flex-col gap-2 p-4">
            <div className="flex items-start justify-between gap-3">
              <span className="break-words font-medium">{payslip.name}</span>
              <span className="shrink-0 font-semibold tabular-nums">{money(payslip.net_pay)}</span>
            </div>
            <dl className="grid grid-cols-2 gap-2 text-sm">
              <div>
                <dt className="text-muted-foreground text-xs">Gross</dt>
                <dd className="tabular-nums">{money(payslip.gross_pay)}</dd>
              </div>
              <div>
                <dt className="text-muted-foreground text-xs">Deductions</dt>
                <dd className="tabular-nums">{money(deductionsOf(payslip))}</dd>
              </div>
            </dl>
            <Button size="sm" variant="outline" className="self-start" onClick={() => onOpen(payslip)}>
              <Printer aria-hidden />
              View and print
            </Button>
          </li>
        ))}
      </ul>

      <div className="hidden min-w-0 overflow-x-auto md:block">
        <Table variant="card" className="min-w-[42rem]">
          <TableHeader>
            <TableRow>
              <TableHead>Employee</TableHead>
              <TableHead className="text-right">Gross</TableHead>
              <TableHead className="text-right">Deductions</TableHead>
              <TableHead className="text-right">Net</TableHead>
              <TableHead>
                <span className="sr-only">Payslip</span>
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {payslips.map((payslip) => (
              <TableRow key={`${payslip.line_id}-${payslip.user_id ?? 'employee'}`}>
                <TableCell>
                  <div className="flex flex-col gap-0.5">
                    <span className="font-medium">{payslip.name}</span>
                    {payslip.job_title && <span className="text-muted-foreground text-xs">{payslip.job_title}</span>}
                  </div>
                </TableCell>
                <TableCell className="text-right tabular-nums">{money(payslip.gross_pay)}</TableCell>
                <TableCell className="text-right tabular-nums">{money(deductionsOf(payslip))}</TableCell>
                <TableCell className="text-right font-semibold tabular-nums">{money(payslip.net_pay)}</TableCell>
                <TableCell>
                  <div className="flex justify-end">
                    <Button size="xs" variant="outline" onClick={() => onOpen(payslip)}>
                      View
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </>
  )
}

function SubmitDialog({
  gymId,
  run,
  onClose,
  onSaved,
}: {
  gymId: number
  run: PayrollRun
  onClose: () => void
  onSaved: () => void
}) {
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)
  const messages = [...new Set(Object.values(errors).flat())]

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (busy) return
    setBusy(true)
    setErrors({})
    try {
      await api(`/gyms/${gymId}/payroll/runs/${run.id}/submit`, { method: 'POST' })
      toastManager.add({ title: 'Payroll submitted for approval.', type: 'success' })
      onSaved()
    } catch (reason) {
      setErrors(formErrors(reason, ['steps', 'warnings'], 'The payroll run could not be submitted.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={submit} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Submit this payroll?</DialogTitle>
            <DialogDescription>
              Lines and rates are frozen at submit. The approval chain is {run.steps.map((step) => step.label).join(' → ')}.
            </DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            <Alert variant="warning">
              <AlertTitle>The period will be locked</AlertTitle>
              <AlertDescription>
                Staff attendance corrections, leave decisions, overtime decisions and holiday changes inside this period are refused until the run is released or rejected.
              </AlertDescription>
            </Alert>
            {messages.length > 0 && (
              <Alert variant="error">
                <AlertTitle>This run cannot be submitted</AlertTitle>
                <AlertDescription>{messages.join(' ')}</AlertDescription>
              </Alert>
            )}
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Keep editing
            </Button>
            <Button type="submit" loading={busy} disabled={busy}>
              Submit for approval
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function DecisionDialog({
  gymId,
  run,
  decision,
  release,
  onClose,
  onSaved,
}: {
  gymId: number
  run: PayrollRun
  decision: 'approve' | 'reject'
  release: boolean
  onClose: () => void
  onSaved: () => void
}) {
  const [reason, setReason] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)
  const messages = [...new Set(Object.values(errors).flat())]
  const rejecting = decision === 'reject'
  const blocked = busy || (rejecting && reason.trim() === '')

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (blocked) return
    setBusy(true)
    setErrors({})
    try {
      await api(`/gyms/${gymId}/payroll/runs/${run.id}/decision`, {
        body: { decision, reason: reason.trim() || null },
      })
      toastManager.add({
        title: rejecting ? 'Payroll rejected and returned to draft.' : release ? 'Payroll released.' : 'Payroll step approved.',
        type: 'success',
      })
      onSaved()
    } catch (failure) {
      setErrors(formErrors(failure, ['decision', 'reason'], 'The decision could not be saved.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>{rejecting ? 'Reject this payroll?' : release ? 'Release this payroll?' : 'Approve this step?'}</DialogTitle>
            <DialogDescription>
              {rejecting
                ? 'The run returns to draft with your reason and a new round. Old approvals stay in the history.'
                : release
                  ? 'This is the final owner step. Release posts gross pay and employer statutory contributions as expenses and cannot be undone.'
                  : `Approve the ${currentStepLabel(run)} step. The chain then moves to the next step.`}
            </DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            {release && (
              <Alert variant="warning">
                <AlertTitle>This is the release step</AlertTitle>
                <AlertDescription>
                  The period is already locked. Release posts the payroll and statutory contribution expenses exactly once and makes payslips visible to employees.
                </AlertDescription>
              </Alert>
            )}
            <Field name="reason">
              <FieldLabel>
                Reason <span className="font-normal text-muted-foreground">{rejecting ? '' : '(optional)'}</span>
              </FieldLabel>
              <Textarea
                name="reason"
                rows={3}
                maxLength={2000}
                value={reason}
                onChange={(event) => setReason(event.target.value)}
                required={rejecting}
                placeholder={rejecting ? 'Tell the preparer what to fix.' : 'Optional note for the history.'}
              />
              <FieldError />
            </Field>
            {messages.length > 0 && (
              <Alert variant="error">
                <AlertTitle>The decision could not be saved</AlertTitle>
                <AlertDescription>{messages.join(' ')}</AlertDescription>
              </Alert>
            )}
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" variant={rejecting ? 'destructive' : 'default'} loading={busy} disabled={blocked}>
              {rejecting ? 'Reject payroll' : release ? 'Release payroll' : 'Approve step'}
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}
