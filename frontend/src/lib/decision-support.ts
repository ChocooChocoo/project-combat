export const VIEWS = [
  { key: 'attendance', label: 'Attendance' },
  { key: 'memberships', label: 'Membership activity' },
  { key: 'athletes', label: 'Athlete performance' },
  { key: 'services', label: 'Service use' },
  { key: 'customers', label: 'Customer engagement' },
  { key: 'employees', label: 'Employees' },
  { key: 'money', label: 'Money and revenue' },
  { key: 'stock', label: 'Stock and purchasing' },
  { key: 'trends', label: 'Trends' },
  { key: 'forecast', label: 'Money forecast' },
  { key: 'problems', label: 'Problems' },
  { key: 'sustainability', label: 'Sustainability' },
] as const

export type View = typeof VIEWS[number]['key']
export type Basis = 'income_expense' | 'cash_flow'
export type Range = { from: string; to: string }
export type Thresholds = {
  trends: { months: number }
  forecast: { history_months: number; ahead_months: number; minimum_trend_months: number }
  falling_attendance: { window_sessions: number; drop_points: number; current_below: number }
  low_use_slot: { minimum_sessions: number; fill_below: number }
  high_no_show: { minimum_expected: number; rate_at_least: number }
}
export type Options = { today: string; timezone: 'Asia/Manila'; default_range: Range; views: View[]; thresholds: Thresholds }
export type Meta = Range & { timezone: 'Asia/Manila'; generated_at: string; has_data: boolean; notes: string[] }
export type Payload = { meta: Meta }
export type AttendanceCounts = {
  present: number; late: number; absent: number; no_show: number; attended: number; marked: number
  awaiting_attendance: number; unknown_markers: number; cancelled_session_markers: number
  attendance_rate: number | null; no_show_rate: number | null
}
export type ClassRef = { class_key: string; service_id: number | null; name: string }
export type Traffic = { label: string; class_attended: number; gym_visits: number; total: number }
export type AttendancePayload = Payload & {
  summary: AttendanceCounts; classes: (ClassRef & AttendanceCounts & { sessions: number })[]
  weekdays: (Traffic & { weekday: number })[]; hours: (Traffic & { hour: number })[]
  peaks: { weekdays: number[]; hours: number[] }
}
export type ProgressCounts = { promotion: number; assessment: number; competition: number; achievement: number }
export type Effectiveness = { attending_athletes: number; athletes_with_progress: number; effectiveness_rate: number | null }
export type AthletePayload = Payload & {
  attribution: 'coach_based_on_attendance'; summary: ProgressCounts & Effectiveness
  buckets: (ProgressCounts & { period: string })[]; disciplines: (ProgressCounts & { discipline: string | null })[]
  coaches: (ProgressCounts & Effectiveness & { coach_id: number | null; name: string })[]
  results: { period: string; coach_id: number | null; coach_name: string; type: string; discipline: string | null; title: string; division: string | null; result: string | null; count: number }[]
}
export type UseCounts = {
  sessions: number; delivered_sessions: number; scheduled_minutes: number; delivered_minutes: number
  held_seats: number; confirmed_bookings: number; capacity: number; invalid_capacity_sessions: number; fill_rate: number | null
}
export type ServicePayload = Payload & {
  summary: UseCounts; services: (UseCounts & { service_id: number | null; name: string })[]
  most_booked: { service_id: number | null; name: string; held_seats: number }[]
  least_booked: { service_id: number | null; name: string; held_seats: number }[]
  classes: (ClassRef & UseCounts)[]; coaches: (UseCounts & { coach_id: number; name: string })[]
  rooms: (UseCounts & { room_id: number; name: string })[]
  private_coaching: { offered_sessions: number; booked_sessions: number; booking_rate: number | null }
}
export type MembershipActivity = { new: number; renewed: number; expired: number; cancelled: number }
export type MembershipSummary = MembershipActivity & {
  active_at_start: number; churned_from_start: number; retained_from_start: number; active_at_end: number
  retention_rate: number | null; renewal_rate: number | null; missing_activation_history: number
}
export type MembershipPayload = Payload & { summary: MembershipSummary; buckets: (MembershipActivity & { period: string })[] }
export type CustomerSummary = {
  leads: number; converted_leads: number; conversion_rate: number | null; members_gained: number
  feedback: number; concerns_raised: number; concerns_open_at_end: number; concerns_resolved: number
}
export type CustomerPayload = Payload & {
  summary: CustomerSummary; sources: { source: string; leads: number; converted_leads: number; conversion_rate: number | null }[]
}
export type StaffCounts = {
  present: number; late: number; absent: number; attendance_rate: number | null; late_rate: number | null
  worked_minutes: number; late_minutes: number; undertime_minutes: number; missing_clock_out: number
}
export type FinanceAmounts = { income: string; expenses: string; net: string }
export type FinanceReport = { buckets: (FinanceAmounts & { period: string })[]; totals: FinanceAmounts }
export type CashFlowAmounts = { in: string; out: string; net: string }
export type CashFlowReport = { buckets: (CashFlowAmounts & { period: string })[]; totals: CashFlowAmounts }
export type PayrollAmounts = { gross: string; net: string; employer_statutory: string; count: number }
export type EmployeePayload = Payload & {
  pay_visible: boolean; summary: StaffCounts; attendance: (StaffCounts & { user_id: number; name: string })[]
  workload: (UseCounts & { coach_id: number; name: string })[]
  payroll: { buckets: (PayrollAmounts & { period: string })[]; totals: PayrollAmounts }
  payroll_by_employee?: { user_id: number; name: string; gross: string; net: string; employer_statutory: string; line_count: number }[]
}
export type IncomeMixRow = { type: string; amount: string; share: number | null }
export type ServiceRevenueRow = { service_id: number | null; name: string; amount: string }
export type MoneyPayload = Payload & {
  income_expense: FinanceReport; cash_flow: CashFlowReport; income_types: IncomeMixRow[]
  session_services: ServiceRevenueRow[]; service_allocation_anomalies: number
  receivables: { basis: 'current_snapshot'; as_of: string; historical_available: false; outstanding: string; debtor_count: number; members: { user_id: number; member_name: string; total: string }[] }
}
export type StockPayload = Payload & {
  as_of: string; summary: { items: number; low_items: number; out_items: number }
  items: { id: number; name: string; category: string; unit: string; location: string | null; quantity: number; min_level: number | null; low: boolean }[]
  purchasing: { basis: 'created_in_range_current_status'; requests: { status: string; count: number }[]; orders: { status: string; count: number }[]; receipts: { count: number; total: string } }
}
export type TrendBucket = MembershipActivity & { period: string; attended: number; marked: number; attendance_rate: number | null; active_at_end: number; revenue: string }
export type TrendPayload = Payload & {
  window: Range & { months: number; partial_final_month: boolean }
  first_records: { attendance: string | null; memberships: string | null; revenue: string | null }
  buckets: TrendBucket[]; totals: MembershipActivity & { attended: number; marked: number; attendance_rate: number | null; revenue: string }; closing_active: number
}
export type ForecastPayload = Payload & FinanceReport & {
  basis: Basis; series_labels: { income: string; expenses: string; net: string }
  method: 'least_squares' | 'average' | 'no_history'; method_name: string
  window: { from: string | null; to: string; months: number; excluded_partial_month: string | null }
  fit: { income_r_squared: number | null; expense_r_squared: number | null }
  history: (FinanceAmounts & { period: string })[]
}
type ProblemWindow = Range & { attended: number; marked: number; rate: number | null }
export type ProblemItem = { key: string; name: string } & (
  { code: 'falling_attendance'; evidence: { previous: ProblemWindow; current: ProblemWindow; drop_points: number; session_ids: number[] } } |
  { code: 'low_use_slot'; evidence: { weekday: number; hour: number; sessions: number; held_seats: number; capacity: number; average_fill: number; session_ids: number[] } } |
  { code: 'high_no_show'; evidence: Range & { marked: number; no_show: number; awaiting_attendance: number; rate: number } }
)
export type ProblemPayload = Payload & { thresholds: Thresholds; summary: { falling_attendance: number; low_use_slot: number; high_no_show: number; total: number }; items: ProblemItem[] }
type BudgetAmounts = { budget: string; actual: string; difference: string; percentage: number | null }
export type SustainabilityPayload = Payload & {
  financial: FinanceReport
  revenue_mix: { income_types: IncomeMixRow[]; session_services: ServiceRevenueRow[]; service_allocation_anomalies: number }
  membership: MembershipSummary
  utilization: { classes: ServicePayload['classes']; rooms: ServicePayload['rooms'] }
  budget: { month: string; basis: 'whole_month_containing_range_end'; rows: (BudgetAmounts & { kind: 'income' | 'expense'; category: string })[]; totals: (BudgetAmounts & { kind: 'income' | 'expense' })[] }
  cash_flow_forecast: ForecastPayload
}

function isDay(value: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false
  const date = new Date(`${value}T00:00:00Z`)
  return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 10) === value
}

export function validRange(from: string, to: string): boolean {
  return isDay(from) && isDay(to) && from <= to
}

export function decisionUrl(gymId: number, view: View, from: string, to: string, basis: Basis = 'income_expense'): string {
  if (!Number.isSafeInteger(gymId) || gymId < 1 || !VIEWS.some(({ key }) => key === view) || !validRange(from, to)) throw new Error('Invalid decision-support request.')
  if (!['income_expense', 'cash_flow'].includes(basis)) throw new Error('Invalid forecast basis.')
  const query = new URLSearchParams({ from, to })
  if (view === 'forecast') query.set('basis', basis)
  return `/gyms/${encodeURIComponent(gymId)}/decision-support/${view}?${query}`
}

export function rateLabel(value: number | null): string {
  if (value === null) return 'Not available'
  if (typeof value !== 'number' || !Number.isFinite(value)) throw new Error('Invalid rate.')
  return `${value}%`
}

export function moneyValue(value: unknown): string {
  if (typeof value !== 'string' || !/^-?\d+\.\d{2}$/.test(value)) throw new Error('Invalid money amount.')
  return value
}

export function record(value: unknown): Record<string, unknown> {
  if (value === null || typeof value !== 'object' || Array.isArray(value)) throw new Error('Invalid dashboard response.')
  return value as Record<string, unknown>
}

export function readOptions(value: unknown): Options {
  const object = record(value)
  const range = record(object.default_range)
  const views = object.views
  if (typeof object.today !== 'string' || !isDay(object.today) || object.timezone !== 'Asia/Manila' ||
    typeof range.from !== 'string' || typeof range.to !== 'string' || !validRange(range.from, range.to) ||
    !Array.isArray(views) || views.length !== VIEWS.length || !VIEWS.every(({ key }, index) => views[index] === key)) throw new Error('Invalid dashboard options.')
  readThresholds(object.thresholds)
  return object as Options
}

function readThresholds(value: unknown): void {
  const thresholds = record(value)
  const fields = {
    trends: ['months'], forecast: ['history_months', 'ahead_months', 'minimum_trend_months'],
    falling_attendance: ['window_sessions', 'drop_points', 'current_below'],
    low_use_slot: ['minimum_sessions', 'fill_below'], high_no_show: ['minimum_expected', 'rate_at_least'],
  }
  for (const [key, names] of Object.entries(fields)) {
    const rule = record(thresholds[key])
    for (const name of names) if (typeof rule[name] !== 'number' || !Number.isFinite(rule[name])) throw new Error('Invalid dashboard thresholds.')
  }
}

export function readPayload(view: View, value: unknown): Payload {
  const object = record(value)
  const meta = record(object.meta)
  if (typeof meta.from !== 'string' || typeof meta.to !== 'string' || !validRange(meta.from, meta.to) ||
    meta.timezone !== 'Asia/Manila' || typeof meta.generated_at !== 'string' || !Number.isFinite(Date.parse(meta.generated_at)) ||
    typeof meta.has_data !== 'boolean' || !Array.isArray(meta.notes) || !meta.notes.every((note) => typeof note === 'string')) throw new Error('Invalid dashboard metadata.')
  if (view === 'attendance') {
    fields(object.summary, attendanceFields)
    rows(object.classes, { ...classFields, ...attendanceFields, sessions: 'count' })
    rows(object.weekdays, { weekday: 'count', ...trafficFields })
    rows(object.hours, { hour: 'count', ...trafficFields })
    if ((object.weekdays as unknown[]).length !== 7 || (object.hours as unknown[]).length !== 24) throw new Error('Incomplete traffic buckets.')
    for (const [key, field] of [['weekdays', 'weekday'], ['hours', 'hour']]) {
      if (!(object[key] as Record<string, unknown>[]).every((row, index) => row[field] === index)) throw new Error('Invalid traffic bucket order.')
    }
    const peaks = record(object.peaks)
    for (const key of ['weekdays', 'hours']) if (!Array.isArray(peaks[key]) || !peaks[key].every((value: unknown) => Number.isSafeInteger(value) && Number(value) >= 0 && Number(value) < (key === 'hours' ? 24 : 7))) throw new Error('Invalid peak buckets.')
  } else if (view === 'athletes') {
    if (object.attribution !== 'coach_based_on_attendance') throw new Error('Invalid athlete attribution.')
    fields(object.summary, { ...progressFields, ...effectivenessFields })
    rows(object.buckets, { period: 'string', ...progressFields })
    rows(object.disciplines, { discipline: 'nullable_string', ...progressFields })
    rows(object.coaches, { coach_id: 'nullable_count', name: 'string', ...progressFields, ...effectivenessFields })
    rows(object.results, { period: 'string', coach_id: 'nullable_count', coach_name: 'string', type: 'string', discipline: 'nullable_string', title: 'string', division: 'nullable_string', result: 'nullable_string', count: 'count' })
  } else if (view === 'services') {
    fields(object.summary, useFields)
    rows(object.services, { service_id: 'nullable_count', name: 'string', ...useFields })
    for (const key of ['most_booked', 'least_booked']) rows(object[key], { service_id: 'nullable_count', name: 'string', held_seats: 'count' })
    rows(object.classes, { ...classFields, ...useFields })
    rows(object.coaches, { coach_id: 'count', name: 'string', ...useFields })
    rows(object.rooms, { room_id: 'count', name: 'string', ...useFields })
    fields(object.private_coaching, { offered_sessions: 'count', booked_sessions: 'count', booking_rate: 'rate' })
  } else if (view === 'memberships') {
    fields(object.summary, { ...membershipActivityFields, active_at_start: 'count', churned_from_start: 'count', retained_from_start: 'count', active_at_end: 'count', retention_rate: 'rate', renewal_rate: 'rate', missing_activation_history: 'count' })
    rows(object.buckets, { period: 'string', ...membershipActivityFields })
  } else if (view === 'customers') {
    fields(object.summary, { leads: 'count', converted_leads: 'count', conversion_rate: 'rate', members_gained: 'count', feedback: 'count', concerns_raised: 'count', concerns_open_at_end: 'count', concerns_resolved: 'count' })
    rows(object.sources, { source: 'string', leads: 'count', converted_leads: 'count', conversion_rate: 'rate' })
  } else if (view === 'employees') {
    fields(object, { pay_visible: 'boolean' })
    fields(object.summary, staffFields)
    rows(object.attendance, { user_id: 'count', name: 'string', ...staffFields })
    rows(object.workload, { coach_id: 'count', name: 'string', ...useFields })
    report(object.payroll, payrollFields)
    if (object.pay_visible) rows(object.payroll_by_employee, { user_id: 'count', name: 'string', gross: 'money', net: 'money', employer_statutory: 'money', line_count: 'count' })
    else if (Object.hasOwn(object, 'payroll_by_employee')) throw new Error('Individual payroll must be omitted when pay is masked.')
  } else if (view === 'money') {
    report(object.income_expense, financeFields)
    report(object.cash_flow, cashFlowFields)
    rows(object.income_types, { type: 'string', amount: 'money', share: 'rate' })
    rows(object.session_services, { service_id: 'nullable_count', name: 'string', amount: 'money' })
    fields(object, { service_allocation_anomalies: 'count' })
    const receivables = record(object.receivables)
    fields(receivables, { outstanding: 'money', debtor_count: 'count' })
    if (receivables.basis !== 'current_snapshot' || receivables.historical_available !== false || typeof receivables.as_of !== 'string' || !isDay(receivables.as_of)) throw new Error('Invalid current receivables basis.')
    rows(receivables.members, { user_id: 'count', member_name: 'string', total: 'money' })
  } else if (view === 'stock') {
    if (typeof object.as_of !== 'string' || !isDay(object.as_of)) throw new Error('Invalid stock snapshot date.')
    fields(object.summary, { items: 'count', low_items: 'count', out_items: 'count' })
    rows(object.items, { id: 'count', name: 'string', category: 'string', unit: 'string', location: 'nullable_string', quantity: 'count', min_level: 'nullable_count', low: 'boolean' })
    const purchasing = record(object.purchasing)
    if (purchasing.basis !== 'created_in_range_current_status') throw new Error('Invalid purchasing cohort basis.')
    rows(purchasing.requests, { status: 'string', count: 'count' })
    rows(purchasing.orders, { status: 'string', count: 'count' })
    fields(purchasing.receipts, { count: 'count', total: 'money' })
  } else if (view === 'trends') {
    const window = record(object.window)
    fields(window, { from: 'string', to: 'string', months: 'count', partial_final_month: 'boolean' })
    if (!validRange(window.from as string, window.to as string)) throw new Error('Invalid trend window.')
    const first = record(object.first_records)
    for (const key of ['attendance', 'memberships', 'revenue']) if (first[key] !== null && (typeof first[key] !== 'string' || !isDay(first[key]))) throw new Error('Invalid first-record date.')
    rows(object.buckets, { period: 'string', attended: 'count', marked: 'count', attendance_rate: 'rate', ...membershipActivityFields, active_at_end: 'count', revenue: 'money' })
    if ((object.buckets as unknown[]).length !== window.months) throw new Error('Incomplete trend buckets.')
    fields(object.totals, { attended: 'count', marked: 'count', attendance_rate: 'rate', ...membershipActivityFields, revenue: 'money' })
    fields(object, { closing_active: 'count' })
  } else if (view === 'forecast') {
    readForecast(object)
  } else if (view === 'problems') {
    readThresholds(object.thresholds)
    fields(object.summary, { falling_attendance: 'count', low_use_slot: 'count', high_no_show: 'count', total: 'count' })
    rows(object.items, { key: 'string', name: 'string', code: 'string' })
    for (const item of object.items as Record<string, unknown>[]) {
      const evidence = record(item.evidence)
      if (item.code === 'falling_attendance') {
        for (const key of ['previous', 'current']) fields(evidence[key], { from: 'string', to: 'string', attended: 'count', marked: 'count', rate: 'rate' })
        fields(evidence, { drop_points: 'number' })
      } else if (item.code === 'low_use_slot') {
        fields(evidence, { weekday: 'count', hour: 'count', sessions: 'count', held_seats: 'count', capacity: 'count', average_fill: 'number' })
      } else if (item.code === 'high_no_show') {
        fields(evidence, { from: 'string', to: 'string', marked: 'count', no_show: 'count', awaiting_attendance: 'count', rate: 'number' })
      } else throw new Error('Invalid problem rule.')
      if (item.code !== 'high_no_show' && (!Array.isArray(evidence.session_ids) || !evidence.session_ids.every((id: unknown) => typeof id === 'number' && Number.isSafeInteger(id) && id > 0))) throw new Error('Invalid evidence session IDs.')
    }
  } else if (view === 'sustainability') {
    report(object.financial, financeFields)
    const mix = record(object.revenue_mix)
    rows(mix.income_types, { type: 'string', amount: 'money', share: 'rate' })
    rows(mix.session_services, { service_id: 'nullable_count', name: 'string', amount: 'money' })
    fields(mix, { service_allocation_anomalies: 'count' })
    fields(object.membership, { ...membershipActivityFields, active_at_start: 'count', churned_from_start: 'count', retained_from_start: 'count', active_at_end: 'count', retention_rate: 'rate', renewal_rate: 'rate', missing_activation_history: 'count' })
    const utilization = record(object.utilization)
    rows(utilization.classes, { ...classFields, ...useFields })
    rows(utilization.rooms, { room_id: 'count', name: 'string', ...useFields })
    const budget = record(object.budget)
    if (budget.basis !== 'whole_month_containing_range_end' || typeof budget.month !== 'string' || !/^\d{4}-(0[1-9]|1[0-2])$/.test(budget.month)) throw new Error('Invalid budget window.')
    const budgetFields: Fields = { kind: 'string', budget: 'money', actual: 'money', difference: 'money', percentage: 'rate' }
    rows(budget.rows, { ...budgetFields, category: 'string' })
    rows(budget.totals, budgetFields)
    for (const row of [...budget.rows as Record<string, unknown>[], ...budget.totals as Record<string, unknown>[]]) if (!['income', 'expense'].includes(String(row.kind))) throw new Error('Invalid budget kind.')
    const forecast = record(object.cash_flow_forecast)
    if (forecast.basis !== 'cash_flow') throw new Error('Sustainability forecast must use cash flow.')
    readPayload('forecast', forecast)
  } else throw new Error(`Dashboard contract not implemented: ${view}`)
  return object as Payload
}

type FieldKind = 'count' | 'nullable_count' | 'rate' | 'number' | 'string' | 'nullable_string' | 'money' | 'boolean'
type Fields = Record<string, FieldKind>
const attendanceFields: Fields = { present: 'count', late: 'count', absent: 'count', no_show: 'count', attended: 'count', marked: 'count', awaiting_attendance: 'count', unknown_markers: 'count', cancelled_session_markers: 'count', attendance_rate: 'rate', no_show_rate: 'rate' }
const classFields: Fields = { class_key: 'string', service_id: 'nullable_count', name: 'string' }
const trafficFields: Fields = { label: 'string', class_attended: 'count', gym_visits: 'count', total: 'count' }
const progressFields: Fields = { promotion: 'count', assessment: 'count', competition: 'count', achievement: 'count' }
const effectivenessFields: Fields = { attending_athletes: 'count', athletes_with_progress: 'count', effectiveness_rate: 'rate' }
const useFields: Fields = { sessions: 'count', delivered_sessions: 'count', scheduled_minutes: 'count', delivered_minutes: 'count', held_seats: 'count', confirmed_bookings: 'count', capacity: 'count', invalid_capacity_sessions: 'count', fill_rate: 'rate' }
const membershipActivityFields: Fields = { new: 'count', renewed: 'count', expired: 'count', cancelled: 'count' }
const staffFields: Fields = { present: 'count', late: 'count', absent: 'count', attendance_rate: 'rate', late_rate: 'rate', worked_minutes: 'count', late_minutes: 'count', undertime_minutes: 'count', missing_clock_out: 'count' }
const financeFields: Fields = { income: 'money', expenses: 'money', net: 'money' }
const cashFlowFields: Fields = { in: 'money', out: 'money', net: 'money' }
const payrollFields: Fields = { gross: 'money', net: 'money', employer_statutory: 'money', count: 'count' }

function report(value: unknown, spec: Fields): void {
  const object = record(value)
  fields(object.totals, spec)
  rows(object.buckets, { period: 'string', ...spec })
}

function readForecast(object: Record<string, unknown>): void {
  if (!['income_expense', 'cash_flow'].includes(String(object.basis))) throw new Error('Invalid forecast basis.')
  forecastMethodLabel(object)
  fields(object.series_labels, { income: 'string', expenses: 'string', net: 'string' })
  const window = record(object.window)
  fields(window, { from: 'nullable_string', to: 'string', months: 'count', excluded_partial_month: 'nullable_string' })
  if (!isDay(window.to as string) || window.from !== null && !isDay(window.from as string) || window.excluded_partial_month !== null && !/^\d{4}-(0[1-9]|1[0-2])$/.test(window.excluded_partial_month as string)) throw new Error('Invalid forecast history window.')
  const fit = record(object.fit)
  fields(fit, { income_r_squared: 'rate', expense_r_squared: 'rate' })
  fitLabel(fit.income_r_squared as number | null)
  fitLabel(fit.expense_r_squared as number | null)
  rows(object.history, { period: 'string', ...financeFields })
  report(object, financeFields)
}

function fields(value: unknown, spec: Fields): void {
  const object = record(value)
  for (const [key, kind] of Object.entries(spec)) {
    const field = object[key]
    const valid = kind === 'rate' ? field === null || typeof field === 'number' && Number.isFinite(field)
      : kind === 'number' ? typeof field === 'number' && Number.isFinite(field)
      : kind === 'nullable_string' ? field === null || typeof field === 'string'
      : kind === 'string' ? typeof field === 'string'
      : kind === 'boolean' ? typeof field === 'boolean'
      : kind === 'money' ? moneyValue(field) === field
      : kind === 'nullable_count' && field === null || typeof field === 'number' && Number.isSafeInteger(field) && field >= 0
    if (!valid) throw new Error(`Invalid dashboard field: ${key}`)
  }
}

function rows(value: unknown, spec: Fields): void {
  if (!Array.isArray(value)) throw new Error('Invalid dashboard rows.')
  for (const row of value) fields(row, spec)
}

export function forecastMethodLabel(value: unknown): string {
  const object = record(value)
  if (!['least_squares', 'average', 'no_history'].includes(String(object.method)) || typeof object.method_name !== 'string' || !object.method_name.trim()) throw new Error('Invalid forecast method.')
  return object.method_name
}

export function fitLabel(value: number | null): string {
  if (value === null) return 'Not available'
  if (typeof value !== 'number' || !Number.isFinite(value) || value < 0 || value > 1) throw new Error('Invalid R² fit.')
  return String(value)
}

export function chartCoordinates(values: (number | null)[]): { baseline: number; points: ({ x: number; y: number } | null)[] } {
  const finite = values.filter((value): value is number => value !== null && Number.isFinite(value))
  const low = Math.min(0, ...finite)
  const high = Math.max(0, ...finite)
  const span = high - low || 1
  const y = (value: number) => 100 - (value - low) / span * 100
  return { baseline: y(0), points: values.map((value, index) => value === null ? null : { x: values.length < 2 ? 50 : index / (values.length - 1) * 100, y: y(value) }) }
}

export const NO_RANGE: Range = { from: '', to: '' }

/** R-112: `from`/`to` query part for history lists; empty dates add nothing (no filter). */
export function rangeQuery(range: Range, join: '?' | '&' = '&'): string {
  const query = new URLSearchParams()
  if (range.from) query.set('from', range.from)
  if (range.to) query.set('to', range.to)
  const text = query.toString()
  return text ? `${join}${text}` : ''
}
