// Shapes, labels and defensive readers for payroll (R-59 to R-62).
// The backend sends snake_case payloads. Everything here accepts the small
// shape differences a paginated, wrapped or otherwise trimmed response may have.

import { dateLabel, dateTimeLabel, money } from '@/lib/membership'

export { dateLabel, dateTimeLabel, money }

export type BadgeTone = 'default' | 'destructive' | 'error' | 'info' | 'outline' | 'secondary' | 'success' | 'warning'

export type PayrollStatus = 'draft' | 'in_review' | 'released'
export type PayrollFrequency = 'semi_monthly' | 'monthly'
export type EmploymentType = 'employee' | 'contractor'
export type StatutoryKind = 'sss' | 'philhealth' | 'pagibig' | 'tax_semi_monthly' | 'tax_monthly'
export type PayrollStepKey = 'hr_review' | 'finance_review' | 'owner'

export const PAYROLL_STATUS: Record<PayrollStatus, { label: string; variant: BadgeTone }> = {
  draft: { label: 'Draft', variant: 'outline' },
  in_review: { label: 'In review', variant: 'warning' },
  released: { label: 'Released', variant: 'success' },
}

export const PAYROLL_STEP_LABELS: Record<PayrollStepKey, string> = {
  hr_review: 'HR review',
  finance_review: 'Finance review',
  owner: 'Owner',
}

export const PAYROLL_STEP_CHOICES: { value: PayrollStepKey; label: string }[] = [
  { value: 'hr_review', label: 'HR review' },
  { value: 'finance_review', label: 'Finance review' },
  { value: 'owner', label: 'Owner' },
]

export const PAYROLL_FREQUENCY_LABELS: Record<PayrollFrequency, string> = {
  semi_monthly: 'Twice a month',
  monthly: 'Once a month',
}

export const EMPLOYMENT_TYPE_LABELS: Record<EmploymentType, string> = {
  employee: 'Employee',
  contractor: 'Contractor',
}

export const STATUTORY_KIND_LABELS: Record<StatutoryKind, string> = {
  sss: 'SSS',
  philhealth: 'PhilHealth',
  pagibig: 'Pag-IBIG',
  tax_semi_monthly: 'BIR tax (semi-monthly)',
  tax_monthly: 'BIR tax (monthly)',
}

export const STATUTORY_KINDS: StatutoryKind[] = ['sss', 'philhealth', 'pagibig', 'tax_semi_monthly', 'tax_monthly']

export type PayrollStep = { key: string; label: string }

export type PayrollRun = {
  id: number
  gym_name: string | null
  starts_on: string
  ends_on: string
  status: PayrollStatus
  steps: PayrollStep[]
  current_step: number
  round: number
  prepared_by: number | null
  prepared_by_name: string | null
  submitted_by_name: string | null
  submitted_at: string | null
  computed_at: string | null
  rejection_reason: string | null
  released_by_name: string | null
  released_at: string | null
  self_released: boolean
  gross_total: string
  employee_statutory_total: string
  tax_total: string
  net_total: string
  employer_statutory_total: string
  headcount: number
  explicit_can_compute: boolean | null
  explicit_can_submit: boolean | null
  explicit_can_decide: boolean | null
  current_step_key: string | null
  current_step_label: string | null
}

export type PayrollLineItem = {
  id: number | null
  kind: string
  label: string
  quantity: string | number | null
  rate: string | number | null
  amount: string
  ref: Record<string, unknown> | null
}

export type PayrollLine = {
  id: number
  user_id: number
  name: string
  job_title: string | null
  pay_basis: string | null
  employment_type: EmploymentType
  monthly_rate: string
  per_session_rate: string
  daily_rate: string
  minute_rate: string
  scheduled_days: number
  days_worked: number
  days_present: number
  days_late: number
  days_absent: number
  paid_leave_days: number
  unpaid_leave_days: number
  holiday_days: number
  late_minutes: number
  undertime_minutes: number
  overtime_minutes: number
  sessions_count: number
  base_pay: string
  attendance_deduction: string
  holiday_premium: string
  overtime_pay: string
  session_pay: string
  gross_pay: string
  sss_employee: string
  philhealth_employee: string
  pagibig_employee: string
  tax: string
  net_pay: string
  sss_employer: string
  ec_employer: string
  philhealth_employer: string
  pagibig_employer: string
  warnings: string[]
  items: PayrollLineItem[]
}

export type PayrollApproval = {
  id: number | null
  round: number
  step_index: number
  step_key: string
  step_label: string
  user_id: number | null
  user_name: string | null
  decision: 'approved' | 'rejected' | 'pending'
  reason: string | null
  decided_at: string | null
}

export type PayrollWarning = {
  message: string
  blocking: boolean
  line_id: number | null
  employee: string | null
}

export type PayrollSettings = {
  frequency: PayrollFrequency
  approval_steps: PayrollStepKey[]
  staff_grace_minutes: number
  overtime_min_minutes: number
}

export type StatutoryBracket = {
  min: number | null
  max: number | null
  employee: number | null
  employer: number | null
  base: number | null
  rate: number | null
  extra: Record<string, unknown>
}

export type StatutoryRate = {
  id: number
  gym_id: number | null
  kind: StatutoryKind
  effective_from: string
  source: string | null
  brackets: StatutoryBracket[]
  created_by_name: string | null
  created_at: string | null
}

export type Payslip = {
  id: number | null
  line_id: number
  user_id: number | null
  name: string
  job_title: string | null
  gym_name: string | null
  status: string | null
  starts_on: string | null
  ends_on: string | null
  released_at: string | null
  run_id: number | null
  pay_basis: string | null
  employment_type: EmploymentType
  scheduled_days: number
  days_worked: number
  days_present: number
  days_late: number
  days_absent: number
  paid_leave_days: number
  unpaid_leave_days: number
  late_minutes: number
  undertime_minutes: number
  overtime_minutes: number
  sessions_count: number
  base_pay: string
  attendance_deduction: string
  holiday_premium: string
  overtime_pay: string
  session_pay: string
  gross_pay: string
  sss_employee: string
  philhealth_employee: string
  pagibig_employee: string
  tax: string
  net_pay: string
  sss_employer: string
  ec_employer: string
  philhealth_employer: string
  pagibig_employer: string
  warnings: string[]
  items: PayrollLineItem[]
  /** False for a payload that only carries gross and net. */
  has_breakdown: boolean
}

export type PayrollPageMeta = {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export type PayrollRunDetail = {
  run: PayrollRun
  lines: PayrollLine[]
  approvals: PayrollApproval[]
  warnings: PayrollWarning[]
  payslips: Payslip[]
}

export type PeriodPreset = {
  value: string
  label: string
  starts_on: string
  ends_on: string
}

export function runStatusMeta(status: unknown): { label: string; variant: BadgeTone } {
  return status === 'draft' || status === 'in_review' || status === 'released'
    ? PAYROLL_STATUS[status]
    : { label: titleWords(status) || 'Unknown', variant: 'outline' }
}

export function stepLabel(value: unknown): string {
  if (typeof value !== 'string' || value === '') return ''
  return value in PAYROLL_STEP_LABELS ? PAYROLL_STEP_LABELS[value as PayrollStepKey] : titleWords(value)
}

export function employmentTypeLabel(value: unknown): string {
  return value === 'contractor' ? EMPLOYMENT_TYPE_LABELS.contractor : EMPLOYMENT_TYPE_LABELS.employee
}

export function statutoryKindLabel(value: unknown): string {
  return typeof value === 'string' && value in STATUTORY_KIND_LABELS
    ? STATUTORY_KIND_LABELS[value as StatutoryKind]
    : titleWords(value) || 'Statutory rate'
}

export function frequencyLabel(value: unknown): string {
  return value === 'monthly' ? PAYROLL_FREQUENCY_LABELS.monthly : PAYROLL_FREQUENCY_LABELS.semi_monthly
}

export function isTaxKind(kind: StatutoryKind): boolean {
  return kind === 'tax_semi_monthly' || kind === 'tax_monthly'
}

export function periodLabel(starts: string | null | undefined, ends: string | null | undefined): string {
  if (!starts && !ends) return 'No period set'
  if (!starts) return `Until ${dateLabel(ends)}`
  if (!ends) return `From ${dateLabel(starts)}`
  return `${dateLabel(starts)} – ${dateLabel(ends)}`
}

export function countLabel(value: number, one: string, many: string): string {
  return `${value.toLocaleString('en-PH')} ${value === 1 ? one : many}`
}

/** A run's approval chain, accepting keys, a key-to-label object or objects. */
export function stepsFrom(value: unknown): PayrollStep[] {
  if (typeof value === 'string') {
    const trimmed = value.trim()
    if (trimmed.startsWith('[') || trimmed.startsWith('{')) {
      try {
        return stepsFrom(JSON.parse(trimmed))
      } catch {
        return []
      }
    }
    return trimmed ? [{ key: trimmed, label: stepLabel(trimmed) }] : []
  }
  const steps: PayrollStep[] = []
  if (Array.isArray(value)) {
    for (const entry of value) {
      const row = objectOf(entry)
      const key = row ? textOf(row.key ?? row.step_key ?? row.step ?? row.value ?? row.id) : textOf(entry)
      if (!key) continue
      steps.push({ key, label: titleWords(row?.label ?? row?.name) || stepLabel(key) })
    }
  } else {
    const rows = objectOf(value)
    if (rows) {
      for (const [key, label] of Object.entries(rows)) {
        if (!key) continue
        const row = objectOf(label)
        steps.push({ key, label: titleWords(row?.label ?? row?.name ?? label) || stepLabel(key) })
      }
    }
  }
  return steps.length > 0 ? steps : [{ key: 'owner', label: PAYROLL_STEP_LABELS.owner }]
}

export function currentStepOf(run: PayrollRun): PayrollStep | null {
  if (run.status !== 'in_review') return null
  const explicit = run.current_step_key
  if (explicit) {
    const found = run.steps.find((step) => step.key === explicit)
    if (found) return found
    return { key: explicit, label: run.current_step_label ?? stepLabel(explicit) }
  }
  const index = Math.min(Math.max(run.current_step, 0), Math.max(run.steps.length - 1, 0))
  return run.steps[index] ?? null
}

export function currentStepLabel(run: PayrollRun): string {
  if (run.status === 'released') return 'Released'
  if (run.status === 'draft') return 'Not submitted'
  return currentStepOf(run)?.label ?? 'Approval pending'
}

export function isFinalStep(run: PayrollRun): boolean {
  const current = currentStepOf(run)
  if (!current) return false
  return current.key === run.steps[run.steps.length - 1]?.key || run.current_step >= run.steps.length - 1
}

export function readRun(value: unknown): PayrollRun {
  const raw = objectOf(value) ?? {}
  const nestedData = objectOf(raw.data)
  const row = objectOf(raw.run) ?? objectOf(nestedData?.run) ?? raw
  const nestedUser = objectOf(row.prepared_by)
  const nestedGym = objectOf(row.gym)
  return {
    id: numberValue(row.id),
    gym_name: textOf(row.gym_name) || textOf(nestedGym?.name) || null,
    starts_on: textOf(row.starts_on ?? row.start_date),
    ends_on: textOf(row.ends_on ?? row.end_date),
    status: normalizeStatus(row.status),
    steps: stepsFrom(row.steps ?? row.approval_steps ?? row.payroll_steps ?? row.chain),
    current_step: numberValue(row.current_step),
    round: Math.max(1, numberValue(row.round) || 1),
    prepared_by: numberValue(row.prepared_by_id ?? nestedUser?.id ?? row.prepared_by) || null,
    prepared_by_name: textOf(row.prepared_by_name) || personName(nestedUser) || (typeof row.prepared_by === 'string' ? row.prepared_by : null),
    submitted_by_name: textOf(row.submitted_by_name) || personName(objectOf(row.submitted_by)) || null,
    submitted_at: textOf(row.submitted_at) || null,
    computed_at: textOf(row.computed_at) || null,
    rejection_reason: textOf(row.rejection_reason) || null,
    released_by_name: textOf(row.released_by_name) || personName(objectOf(row.released_by)) || null,
    released_at: textOf(row.released_at) || null,
    self_released: row.self_released === true,
    gross_total: moneyText(row.gross_total ?? row.gross ?? row.total_gross),
    employee_statutory_total: moneyText(row.employee_statutory_total ?? row.statutory_total ?? row.deductions_total),
    tax_total: moneyText(row.tax_total ?? row.tax),
    net_total: moneyText(row.net_total ?? row.net ?? row.total_net),
    employer_statutory_total: moneyText(row.employer_statutory_total ?? row.employer_total),
    headcount: numberValue(row.headcount ?? row.lines_count ?? row.employee_count ?? (Array.isArray(row.lines) ? row.lines.length : 0)),
    explicit_can_compute: booleanValue(row.can_compute),
    explicit_can_submit: booleanValue(row.can_submit),
    explicit_can_decide: booleanValue(row.can_decide),
    current_step_key: textOf(row.current_step_key) || null,
    current_step_label: textOf(row.current_step_label) || null,
  }
}

export function readLine(value: unknown): PayrollLine {
  const row = objectOf(value) ?? {}
  const user = objectOf(row.user)
  return {
    id: numberValue(row.id),
    user_id: numberValue(row.user_id ?? row.employee_id ?? user?.id),
    name: textOf(row.name ?? row.user_name ?? row.employee_name) || personName(user) || 'Unknown employee',
    job_title: textOf(row.job_title) || null,
    pay_basis: textOf(row.pay_basis) || null,
    employment_type: row.employment_type === 'contractor' ? 'contractor' : 'employee',
    monthly_rate: moneyText(row.monthly_rate),
    per_session_rate: moneyText(row.per_session_rate),
    daily_rate: moneyText(row.daily_rate),
    minute_rate: moneyText(row.minute_rate),
    scheduled_days: numberValue(row.scheduled_days),
    days_worked: numberValue(row.days_worked),
    days_present: numberValue(row.days_present),
    days_late: numberValue(row.days_late),
    days_absent: numberValue(row.days_absent),
    paid_leave_days: numberValue(row.paid_leave_days),
    unpaid_leave_days: numberValue(row.unpaid_leave_days),
    holiday_days: numberValue(row.holiday_days),
    late_minutes: numberValue(row.late_minutes),
    undertime_minutes: numberValue(row.undertime_minutes),
    overtime_minutes: numberValue(row.overtime_minutes),
    sessions_count: numberValue(row.sessions_count),
    base_pay: moneyText(row.base_pay),
    attendance_deduction: moneyText(row.attendance_deduction),
    holiday_premium: moneyText(row.holiday_premium),
    overtime_pay: moneyText(row.overtime_pay),
    session_pay: moneyText(row.session_pay),
    gross_pay: moneyText(row.gross_pay ?? row.gross),
    sss_employee: moneyText(row.sss_employee),
    philhealth_employee: moneyText(row.philhealth_employee),
    pagibig_employee: moneyText(row.pagibig_employee),
    tax: moneyText(row.tax),
    net_pay: moneyText(row.net_pay ?? row.net),
    sss_employer: moneyText(row.sss_employer),
    ec_employer: moneyText(row.ec_employer),
    philhealth_employer: moneyText(row.philhealth_employer),
    pagibig_employer: moneyText(row.pagibig_employer),
    warnings: warningMessages(row.warnings),
    items: itemsFrom(row.items ?? row.line_items),
  }
}

export function readApproval(value: unknown): PayrollApproval {
  const row = objectOf(value) ?? {}
  const user = objectOf(row.user)
  const decision = row.decision === 'rejected' || row.decision === 'reject' ? 'rejected' : row.decision === 'approved' || row.decision === 'approve' ? 'approved' : 'pending'
  return {
    id: numberValue(row.id) || null,
    round: Math.max(1, numberValue(row.round) || 1),
    step_index: numberValue(row.step_index ?? row.step),
    step_key: textOf(row.step_key ?? row.key ?? row.step),
    step_label: titleWords(row.step_label ?? row.label) || stepLabel(row.step_key ?? row.key ?? row.step),
    user_id: numberValue(row.user_id ?? user?.id) || null,
    user_name: textOf(row.user_name ?? row.name) || personName(user) || null,
    decision,
    reason: textOf(row.reason ?? row.note) || null,
    decided_at: textOf(row.decided_at) || null,
  }
}

export function readSettings(value: unknown): PayrollSettings {
  const payload = objectOf(value) ?? {}
  const row = objectOf(payload.settings) ?? objectOf(payload.data) ?? payload
  return {
    frequency: (row.frequency ?? row.payroll_frequency) === 'monthly' ? 'monthly' : 'semi_monthly',
    approval_steps: settingsSteps(row.approval_steps ?? row.payroll_steps ?? row.steps),
    staff_grace_minutes: Math.max(0, numberValue(row.staff_grace_minutes ?? row.grace_minutes)),
    overtime_min_minutes: Math.max(0, numberValue(row.overtime_min_minutes ?? row.overtime_minutes)),
  }
}

export function readStatutoryRate(value: unknown): StatutoryRate {
  const row = objectOf(value) ?? {}
  const createdBy = objectOf(row.created_by)
  return {
    id: numberValue(row.id),
    gym_id: numberValue(row.gym_id) || null,
    kind: normalizeKind(row.kind),
    effective_from: textOf(row.effective_from ?? row.effective_on),
    source: textOf(row.source) || null,
    brackets: bracketsFrom(row.brackets),
    created_by_name: textOf(row.created_by_name) || personName(createdBy) || null,
    created_at: textOf(row.created_at) || null,
  }
}

export function readPayslip(value: unknown, run?: PayrollRun | null): Payslip {
  const row = objectOf(value) ?? {}
  const line = objectOf(row.line) ?? objectOf(row.payroll_line)
  const payslip = objectOf(row.payslip)
  const source = payslip ?? line ?? row
  const runSource = objectOf(row.run) ?? objectOf(row.payroll_run)
  const runRow = run ?? (runSource ? readRun(runSource) : null)
  const hasBreakdown = ['base_pay', 'attendance_deduction', 'holiday_premium', 'overtime_pay', 'session_pay']
    .some((key) => isPresent(source[key]) || isPresent(line?.[key]))
  return {
    id: numberValue(source.id ?? payslip?.id) || null,
    line_id: numberValue(source.line_id ?? line?.id ?? source.id),
    user_id: numberValue(source.user_id ?? line?.user_id) || null,
    name: textOf(source.name ?? source.user_name ?? source.employee_name ?? line?.name) || 'Unknown employee',
    job_title: textOf(source.job_title ?? line?.job_title) || null,
    gym_name: textOf(source.gym_name ?? runRow?.gym_name) || null,
    status: textOf(source.status ?? source.run_status ?? runRow?.status) || null,
    starts_on: textOf(source.starts_on ?? source.period_start ?? runRow?.starts_on) || null,
    ends_on: textOf(source.ends_on ?? source.period_end ?? runRow?.ends_on) || null,
    released_at: textOf(source.released_at ?? runRow?.released_at) || null,
    run_id: numberValue(source.payroll_run_id ?? source.run_id ?? runRow?.id) || null,
    pay_basis: textOf(source.pay_basis ?? line?.pay_basis) || null,
    employment_type: (source.employment_type ?? line?.employment_type) === 'contractor' ? 'contractor' : 'employee',
    scheduled_days: numberValue(source.scheduled_days ?? line?.scheduled_days),
    days_worked: numberValue(source.days_worked ?? line?.days_worked),
    days_present: numberValue(source.days_present ?? line?.days_present),
    days_late: numberValue(source.days_late ?? line?.days_late),
    days_absent: numberValue(source.days_absent ?? line?.days_absent),
    paid_leave_days: numberValue(source.paid_leave_days ?? line?.paid_leave_days),
    unpaid_leave_days: numberValue(source.unpaid_leave_days ?? line?.unpaid_leave_days),
    late_minutes: numberValue(source.late_minutes ?? line?.late_minutes),
    undertime_minutes: numberValue(source.undertime_minutes ?? line?.undertime_minutes),
    overtime_minutes: numberValue(source.overtime_minutes ?? line?.overtime_minutes),
    sessions_count: numberValue(source.sessions_count ?? line?.sessions_count),
    base_pay: moneyText(source.base_pay ?? line?.base_pay),
    attendance_deduction: moneyText(source.attendance_deduction ?? line?.attendance_deduction),
    holiday_premium: moneyText(source.holiday_premium ?? line?.holiday_premium),
    overtime_pay: moneyText(source.overtime_pay ?? line?.overtime_pay),
    session_pay: moneyText(source.session_pay ?? line?.session_pay),
    gross_pay: moneyText(source.gross_pay ?? source.gross ?? line?.gross_pay),
    sss_employee: moneyText(source.sss_employee ?? line?.sss_employee),
    philhealth_employee: moneyText(source.philhealth_employee ?? line?.philhealth_employee),
    pagibig_employee: moneyText(source.pagibig_employee ?? line?.pagibig_employee),
    tax: moneyText(source.tax ?? line?.tax),
    net_pay: moneyText(source.net_pay ?? source.net ?? line?.net_pay),
    sss_employer: moneyText(source.sss_employer ?? line?.sss_employer),
    ec_employer: moneyText(source.ec_employer ?? line?.ec_employer),
    philhealth_employer: moneyText(source.philhealth_employer ?? line?.philhealth_employer),
    pagibig_employer: moneyText(source.pagibig_employer ?? line?.pagibig_employer),
    warnings: warningMessages(source.warnings ?? line?.warnings),
    items: itemsFrom(row.items ?? source.items ?? source.line_items),
    has_breakdown: hasBreakdown,
  }
}

export function payslipFromLine(line: PayrollLine, run: PayrollRun, gymName?: string | null): Payslip {
  return {
    id: line.id || null,
    line_id: line.id,
    user_id: line.user_id,
    name: line.name,
    job_title: line.job_title,
    gym_name: gymName ?? null,
    status: run.status,
    starts_on: run.starts_on,
    ends_on: run.ends_on,
    released_at: run.released_at,
    run_id: run.id,
    pay_basis: line.pay_basis,
    employment_type: line.employment_type,
    scheduled_days: line.scheduled_days,
    days_worked: line.days_worked,
    days_present: line.days_present,
    days_late: line.days_late,
    days_absent: line.days_absent,
    paid_leave_days: line.paid_leave_days,
    unpaid_leave_days: line.unpaid_leave_days,
    late_minutes: line.late_minutes,
    undertime_minutes: line.undertime_minutes,
    overtime_minutes: line.overtime_minutes,
    sessions_count: line.sessions_count,
    base_pay: line.base_pay,
    attendance_deduction: line.attendance_deduction,
    holiday_premium: line.holiday_premium,
    overtime_pay: line.overtime_pay,
    session_pay: line.session_pay,
    gross_pay: line.gross_pay,
    sss_employee: line.sss_employee,
    philhealth_employee: line.philhealth_employee,
    pagibig_employee: line.pagibig_employee,
    tax: line.tax,
    net_pay: line.net_pay,
    sss_employer: line.sss_employer,
    ec_employer: line.ec_employer,
    philhealth_employer: line.philhealth_employer,
    pagibig_employer: line.pagibig_employer,
    warnings: line.warnings,
    items: line.items,
    has_breakdown: true,
  }
}

export function readRunDetail(payload: unknown): PayrollRunDetail {
  const record = objectOf(payload) ?? {}
  const nested = objectOf(record.data) ?? record
  const runValue = nested.run ?? record.run
  const run = readRun(runValue ?? nested)
  const linesValue = nested.lines ?? record.lines ?? objectOf(runValue)?.lines
  const approvalsValue = nested.approvals ?? record.approvals ?? objectOf(runValue)?.approvals
  return {
    run,
    lines: arrayOf(linesValue).map(readLine),
    approvals: arrayOf(approvalsValue).map(readApproval),
    warnings: warningsFrom(nested.warnings ?? record.warnings ?? objectOf(runValue)?.warnings),
    payslips: arrayOf(nested.payslips ?? record.payslips ?? objectOf(runValue)?.payslips).map((entry) => readPayslip(entry, run)),
  }
}

export function readRuns(payload: unknown): { runs: PayrollRun[]; meta: PayrollPageMeta } {
  const runs = listOf(payload, ['runs', 'payroll_runs']).map(readRun)
  return { runs, meta: pageMetaFrom(payload, runs.length) }
}

export function readSettingsResponse(payload: unknown): PayrollSettings {
  return readSettings(payload)
}

export function readRates(payload: unknown): StatutoryRate[] {
  return listOf(payload, ['rates', 'statutory_rates']).map(readStatutoryRate)
}

export function readPayslips(payload: unknown): Payslip[] {
  return listOf(payload, ['payslips']).map((entry) => readPayslip(entry))
}

export function readPayslipDetail(payload: unknown): Payslip {
  const record = objectOf(payload) ?? {}
  const nested = objectOf(record.data) ?? record
  const runSource = objectOf(nested.run) ?? objectOf(nested.payroll_run)
  // Keep the whole envelope: older responses put the breakdown in `line` and
  // only gross/net in `payslip`, and readPayslip falls back between the two.
  return readPayslip(nested, runSource ? readRun(runSource) : undefined)
}

/** Warnings come as strings, rows, a `{blocking, messages}` box or line warnings. */
export function warningsFrom(value: unknown): PayrollWarning[] {
  if (value === null || value === undefined) return []
  if (typeof value === 'string') return value.trim() ? [{ message: value.trim(), blocking: true, line_id: null, employee: null }] : []
  if (Array.isArray(value)) return value.flatMap((entry) => warningsFrom(entry))
  const row = objectOf(value) ?? {}
  const ownMessage = textOf(row.message ?? row.warning ?? row.label ?? row.reason ?? row.text ?? row.detail)
  if (ownMessage) {
    return [{
      message: ownMessage,
      blocking: row.blocking !== false && row.is_blocking !== false,
      line_id: numberValue(row.line_id ?? row.payroll_line_id) || null,
      employee: textOf(row.employee ?? row.user_name ?? row.name) || null,
    }]
  }
  const messages: PayrollWarning[] = []
  const downgraded = row.blocking === false || row.is_blocking === false
  for (const key of ['blocking', 'errors', 'warnings', 'messages', 'items']) {
    if (Array.isArray(row[key])) {
      messages.push(...warningsFrom(row[key]).map((warning) => (downgraded ? { ...warning, blocking: false } : warning)))
    }
  }
  for (const key of ['non_blocking', 'nonblocking', 'advisory']) {
    if (Array.isArray(row[key])) {
      messages.push(...warningsFrom(row[key]).map((warning) => ({ ...warning, blocking: false })))
    }
  }
  if (messages.length > 0) return messages
  return Object.entries(row).flatMap(([employee, entry]) =>
    Array.isArray(entry)
      ? warningsFrom(entry).map((warning) => ({ ...warning, employee: warning.employee ?? titleWords(employee) }))
      : [],
  )
}

export function warningMessages(value: unknown): string[] {
  return warningsFrom(value).map((warning) => warning.message)
}

/**
 * What was withheld from this payslip. Older payslip payloads carry only gross
 * and net, so when no itemised statutory amount came with the row, use their
 * difference. The backend computes net as gross minus employee statutory and
 * withholding tax, so the two readings agree with the line table.
 */
export function deductionsOf(payslip: Payslip): number {
  const itemised = [payslip.sss_employee, payslip.philhealth_employee, payslip.pagibig_employee, payslip.tax]
    .reduce((sum, value) => sum + safeNumber(value), 0)
  if (itemised > 0) return itemised
  const difference = safeNumber(payslip.gross_pay) - safeNumber(payslip.net_pay)
  return Math.max(0, Math.round(difference * 100) / 100)
}

export function employerContributionsOf(payslip: Payslip): number {
  return [payslip.sss_employer, payslip.ec_employer, payslip.philhealth_employer, payslip.pagibig_employer]
    .reduce((sum, value) => sum + safeNumber(value), 0)
}

export function lineDeductions(line: PayrollLine): number {
  return [line.sss_employee, line.philhealth_employee, line.pagibig_employee, line.tax]
    .reduce((sum, value) => sum + safeNumber(value), 0)
}

export function bracketSummary(rate: StatutoryRate): string {
  const count = rate.brackets.length
  if (count === 0) return 'No brackets'
  const first = rate.brackets[0]
  const last = rate.brackets[count - 1]
  const range =
    first?.min !== null && first?.min !== undefined
      ? `${money(first.min)}${last?.max === null || last?.max === undefined ? ' and up' : ` – ${money(last.max)}`}`
      : ''
  return `${countLabel(count, 'bracket', 'brackets')}${range ? ` · ${range}` : ''}`
}

export function isTaxRate(kind: StatutoryKind): boolean {
  return isTaxKind(kind)
}

export function startOfDayIso(date: Date): string {
  return isoDay(date)
}

export function isoDay(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

/** Completed pay periods for the frequency, newest first. */
export function periodPresets(frequency: PayrollFrequency, count = 6, today = new Date()): PeriodPreset[] {
  const todayIso = isoDay(today)
  let cursor = new Date(today.getFullYear(), today.getMonth(), today.getDate())
  const current = periodAt(frequency, cursor)
  if (current.ends_on >= todayIso) {
    cursor = new Date(current.starts.getFullYear(), current.starts.getMonth(), current.starts.getDate() - 1)
  }

  const presets: PeriodPreset[] = []
  for (let index = 0; index < count; index += 1) {
    const period = periodAt(frequency, cursor)
    presets.push({
      value: `${period.starts_on}|${period.ends_on}`,
      label: periodLabel(period.starts_on, period.ends_on),
      starts_on: period.starts_on,
      ends_on: period.ends_on,
    })
    cursor = new Date(period.starts.getFullYear(), period.starts.getMonth(), period.starts.getDate() - 1)
  }
  return presets
}

function periodAt(frequency: PayrollFrequency, date: Date): { starts: Date; starts_on: string; ends_on: string } {
  if (frequency === 'monthly') {
    const starts = new Date(date.getFullYear(), date.getMonth(), 1)
    const ends = new Date(date.getFullYear(), date.getMonth() + 1, 0)
    return { starts, starts_on: isoDay(starts), ends_on: isoDay(ends) }
  }
  if (date.getDate() <= 15) {
    const starts = new Date(date.getFullYear(), date.getMonth(), 1)
    const ends = new Date(date.getFullYear(), date.getMonth(), 15)
    return { starts, starts_on: isoDay(starts), ends_on: isoDay(ends) }
  }
  const starts = new Date(date.getFullYear(), date.getMonth(), 16)
  const ends = new Date(date.getFullYear(), date.getMonth() + 1, 0)
  return { starts, starts_on: isoDay(starts), ends_on: isoDay(ends) }
}

function settingsSteps(value: unknown): PayrollStepKey[] {
  const steps = stepsFrom(value)
  const chosen: PayrollStepKey[] = []
  for (const step of steps) {
    if (step.key === 'hr_review' || step.key === 'finance_review') {
      if (!chosen.includes(step.key) && chosen.length < 2) chosen.push(step.key)
    }
  }
  return [...chosen, 'owner']
}

function normalizeStatus(value: unknown): PayrollStatus {
  return value === 'in_review' || value === 'released' ? value : 'draft'
}

function normalizeKind(value: unknown): StatutoryKind {
  return value === 'sss' || value === 'philhealth' || value === 'pagibig' || value === 'tax_semi_monthly' || value === 'tax_monthly'
    ? value
    : 'sss'
}

function bracketsFrom(value: unknown): StatutoryBracket[] {
  let raw = value
  if (typeof raw === 'string' && raw.trim().startsWith('[')) {
    try {
      raw = JSON.parse(raw)
    } catch {
      raw = []
    }
  }
  const entries = Array.isArray(raw) ? raw : Array.isArray(objectOf(raw)?.brackets) ? (objectOf(raw)?.brackets as unknown[]) : []
  return entries.flatMap((entry) => {
    const row = objectOf(entry)
    if (!row) return []
    const used = new Set(['min', 'max', 'employee', 'employer', 'base', 'rate'])
    const extra = Object.fromEntries(Object.entries(row).filter(([key]) => !used.has(key)))
    return [{
      min: nullableNumber(row.min ?? row.from),
      max: nullableNumber(row.max ?? row.to),
      employee: nullableNumber(row.employee ?? row.employee_share ?? row.employee_amount),
      employer: nullableNumber(row.employer ?? row.employer_share ?? row.employer_amount),
      base: nullableNumber(row.base),
      rate: nullableNumber(row.rate),
      extra,
    }]
  })
}

function itemsFrom(value: unknown): PayrollLineItem[] {
  return arrayOf(value).flatMap((entry) => {
    const row = objectOf(entry)
    if (!row) return []
    const label = textOf(row.label ?? row.name ?? row.kind)
    if (!label) return []
    return [{
      id: numberValue(row.id) || null,
      kind: textOf(row.kind) || 'item',
      label,
      quantity: row.quantity === null || row.quantity === undefined ? null : (row.quantity as string | number),
      rate: row.rate === null || row.rate === undefined ? null : (row.rate as string | number),
      amount: moneyText(row.amount),
      ref: objectOf(row.ref),
    }]
  })
}

function listOf(payload: unknown, keys: string[]): unknown[] {
  const record = objectOf(payload) ?? {}
  const nested = objectOf(record.data) ?? record
  if (Array.isArray(payload)) return payload
  if (Array.isArray(record.data)) return record.data
  if (Array.isArray(nested.data)) return nested.data
  for (const source of [record, nested]) {
    for (const key of keys) {
      if (Array.isArray(source[key])) return source[key] as unknown[]
      const inner = objectOf(source[key])?.data
      if (Array.isArray(inner)) return inner as unknown[]
    }
  }
  return []
}

function pageMetaFrom(payload: unknown, shown: number): PayrollPageMeta {
  const record = objectOf(payload) ?? {}
  const nested = objectOf(record.data) ?? record
  const raw = objectOf(record.meta) ?? objectOf(nested.meta) ?? objectOf(record.pagination) ?? nested ?? record
  const current = numberValue(raw.current_page) || 1
  const last = Math.max(numberValue(raw.last_page) || 1, 1)
  return {
    current_page: Math.min(current, last),
    last_page: last,
    per_page: numberValue(raw.per_page) || shown || 25,
    total: numberValue(raw.total) || shown,
  }
}

function arrayOf(value: unknown): unknown[] {
  if (Array.isArray(value)) return value
  const row = objectOf(value)
  if (row) {
    if (Array.isArray(row.data)) return row.data
    if (Array.isArray(row.items)) return row.items
  }
  return []
}

function objectOf(value: unknown): Record<string, unknown> | null {
  return value && typeof value === 'object' && !Array.isArray(value) ? (value as Record<string, unknown>) : null
}

function textOf(value: unknown): string {
  return typeof value === 'string' ? value : typeof value === 'number' ? String(value) : ''
}

function numberValue(value: unknown): number {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : 0
}

function nullableNumber(value: unknown): number | null {
  if (value === null || value === undefined || value === '') return null
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
}

function isPresent(value: unknown): boolean {
  return value !== null && value !== undefined && value !== ''
}

function safeNumber(value: string): number {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : 0
}

function moneyText(value: unknown): string {
  if (value === null || value === undefined || value === '') return '0'
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed.toFixed(2) : '0'
}

function booleanValue(value: unknown): boolean | null {
  return typeof value === 'boolean' ? value : null
}

function personName(value: Record<string, unknown> | null): string {
  if (!value) return ''
  const direct = textOf(value.name)
  if (direct) return direct
  return `${textOf(value.first_name)} ${textOf(value.last_name)}`.trim()
}

function titleWords(value: unknown): string {
  if (typeof value !== 'string' || value.trim() === '') return ''
  const words = value.replaceAll('_', ' ').trim()
  return words.charAt(0).toUpperCase() + words.slice(1)
}
