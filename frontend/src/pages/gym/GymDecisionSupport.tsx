import { useEffect, useRef, useState } from 'react'
import { useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { DashboardEmpty, DashboardError, DashboardLoading } from '@/components/decision-support/dashboard-parts'
import { ReportMode } from '@/components/decision-support/report-mode'
import { AttendanceView } from '@/components/decision-support/attendance-view'
import { AthleteView } from '@/components/decision-support/athlete-view'
import { ServiceView } from '@/components/decision-support/service-view'
import { MembershipView } from '@/components/decision-support/membership-view'
import { CustomerView } from '@/components/decision-support/customer-view'
import { EmployeeView } from '@/components/decision-support/employee-view'
import { MoneyView } from '@/components/decision-support/money-view'
import { StockView } from '@/components/decision-support/stock-view'
import { TrendsView } from '@/components/decision-support/trends-view'
import { ForecastView } from '@/components/decision-support/forecast-view'
import { ProblemsView } from '@/components/decision-support/problems-view'
import { SustainabilityView } from '@/components/decision-support/sustainability-view'
import { PrintSheet } from '@/components/decision-support/print-sheet'
import { RangePicker } from '@/components/decision-support/range-picker'
import { Button } from '@/components/ui/button'
import { Activity, Users, ChartNoAxesCombined, Printer } from 'lucide-react'
import '@/components/decision-support/decision-support.css'
import { Field, FieldLabel } from '@/components/ui/field'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { api } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import { readOptions, validRange, VIEWS, type AttendancePayload, type AthletePayload, type ServicePayload, type MembershipPayload, type CustomerPayload, type EmployeePayload, type MoneyPayload, type StockPayload, type TrendPayload, type ForecastPayload, type ProblemPayload, type SustainabilityPayload, type Basis, type Payload, type Options, type Range, type View } from '@/lib/decision-support'
import { useDecisionSupport } from '@/hooks/use-decision-support'

const BASIS_CHOICES = [{ value: 'income_expense', label: 'Income against expenses (accrual)' }, { value: 'cash_flow', label: 'Cash flow' }]

const VIEW_GROUPS = [
  { title: 'Training & members', icon: Activity, keys: ['attendance', 'memberships', 'athletes', 'services'] },
  { title: 'Business operations', icon: Users, keys: ['customers', 'employees', 'money', 'stock'] },
  { title: 'Planning & outlook', icon: ChartNoAxesCombined, keys: ['trends', 'forecast', 'problems', 'sustainability'] },
] as const

const VIEW_DESCRIPTIONS: Record<View, string> = {
  attendance: 'Understand participation, missed sessions and peak training hours.',
  memberships: 'Follow activations, renewals and the members who stay.',
  athletes: 'Track recorded progress, assessments and competition results.',
  services: 'See which offerings fill up and where capacity remains.',
  customers: 'Follow lead conversion, feedback and outstanding concerns.',
  employees: 'Review staff attendance, coaching workload and released payroll.',
  money: 'Compare income, expenses and the sources of revenue.',
  stock: 'Check current stock and purchasing activity in your selected dates.',
  trends: 'Read the longer pattern across attendance, membership and revenue.',
  forecast: 'Compare completed-month results with the next three months.',
  problems: 'Review rule-based flags and the records behind each one.',
  sustainability: 'Bring financial health, retention, capacity and outlook together.',
}

function LoadedView({ view, data, mode }: { view: View; data: Payload; mode: 'screen' | 'print' }) {
  return <ReportMode value={mode}><ViewContent view={view} data={data} mode={mode} /></ReportMode>
}

function ViewContent({ view, data, mode }: { view: View; data: Payload; mode: 'screen' | 'print' }) {
  if (view === 'attendance') return <AttendanceView data={data as AttendancePayload} mode={mode} />
  if (view === 'athletes') return <AthleteView data={data as AthletePayload} mode={mode} />
  if (view === 'services') return <ServiceView data={data as ServicePayload} mode={mode} />
  if (view === 'memberships') return <MembershipView data={data as MembershipPayload} mode={mode} />
  if (view === 'customers') return <CustomerView data={data as CustomerPayload} mode={mode} />
  if (view === 'employees') return <EmployeeView data={data as EmployeePayload} mode={mode} />
  if (view === 'money') return <MoneyView data={data as MoneyPayload} mode={mode} />
  if (view === 'stock') return <StockView data={data as StockPayload} mode={mode} />
  if (view === 'trends') return <TrendsView data={data as TrendPayload} mode={mode} />
  if (view === 'forecast') return <ForecastView data={data as ForecastPayload} mode={mode} />
  if (view === 'problems') return <ProblemsView data={data as ProblemPayload} mode={mode} />
  if (view === 'sustainability') return <SustainabilityView data={data as SustainabilityPayload} mode={mode} />
  return null
}

export default function GymDecisionSupport() {
  const { gymId } = useParams()
  const { user } = useAuth()
  const gym = approvedGym(user, Number(gymId))
  if (!gym || !can(gym, 'decision_support.general.view')) return <p>You do not have permission to view decision support.</p>
  return <DecisionWorkspace key={`${gym.id}:${user?.id}:${gym.permissions.join(',')}`} gymId={gym.id} gymName={gym.name} />
}

function DecisionWorkspace({ gymId, gymName }: { gymId: number; gymName: string }) {
  const [options, setOptions] = useState<Options | null>(null)
  const [range, setRange] = useState<Range>({ from: '', to: '' })
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [view, setView] = useState<View>('attendance')
  const [basis, setBasis] = useState<Basis>('income_expense')
  const optionsRequest = useRef<{ version: number; promise: Promise<Options> } | null>(null)
  const dashboard = useDecisionSupport({ gymId, view, from: range.from, to: range.to, basis, enabled: options !== null && validRange(range.from, range.to) })
  const title = VIEWS.find(({ key }) => key === view)!.label

  useEffect(() => {
    let live = true
    if (optionsRequest.current?.version !== version) {
      optionsRequest.current = { version, promise: api<unknown>(`/gyms/${gymId}/decision-support/options`).then(readOptions) }
    }
    optionsRequest.current.promise.then((value) => {
      if (live) { setOptions(value); setRange(value.default_range); setError('') }
    }).catch((reason: unknown) => { if (live) setError(reason instanceof Error ? reason.message : 'Could not load options.') })
    return () => { live = false }
  }, [gymId, version])

  return <div className="decision-workspace min-w-0 space-y-6">
    <PageHeader title="Decision support" description="Your gym, in perspective. Activity, business performance and what comes next." actions={<Button variant="outline" disabled={!dashboard.data} onClick={() => window.print()}><Printer aria-hidden="true" />Print report</Button>} />
    {!options ? error ? <DashboardError message={error} retry={() => { setError(''); setVersion((value) => value + 1) }} /> : <DashboardLoading /> : <>
      <nav className="ds-navigation" aria-label="Decision support views">{VIEW_GROUPS.map((group) => <div className="ds-nav-group" key={group.title}><h2><group.icon aria-hidden="true" />{group.title}</h2><div>{group.keys.map((key) => <button key={key} type="button" aria-pressed={view === key} aria-controls="decision-panel" onClick={() => setView(key)}>{VIEWS.find((item) => item.key === key)!.label}</button>)}</div></div>)}</nav>
      <label className="ds-mobile-nav">View<select value={view} onChange={(event) => setView(event.target.value as View)}>{VIEW_GROUPS.map((group) => <optgroup key={group.title} label={group.title}>{group.keys.map((key) => <option key={key} value={key}>{VIEWS.find((item) => item.key === key)!.label}</option>)}</optgroup>)}</select></label>
      <div className="ds-toolbar"><div><h2 id="decision-heading">{title}</h2><p>{VIEW_DESCRIPTIONS[view]}</p></div><RangePicker value={range} onChange={setRange} /></div>
      {view === 'forecast' && <Field name="forecast-basis" className="max-w-sm print:hidden"><FieldLabel>Forecast basis</FieldLabel><Select name="forecast-basis" items={BASIS_CHOICES} value={basis} onValueChange={(value) => { if (value === 'income_expense' || value === 'cash_flow') setBasis(value) }}><SelectTrigger aria-label="Forecast basis" className="min-h-11"><SelectValue /></SelectTrigger><SelectPopup>{BASIS_CHOICES.map((choice) => <SelectItem key={choice.value} value={choice.value}>{choice.label}</SelectItem>)}</SelectPopup></Select></Field>}
      <section id="decision-panel" aria-labelledby="decision-heading" aria-busy={dashboard.loading} className="ds-content space-y-6" key={view}>
          {dashboard.loading ? <DashboardLoading /> : dashboard.error ? <DashboardError message={dashboard.error} retry={dashboard.retry} /> : dashboard.data && <>
            {!dashboard.data.meta.has_data && <DashboardEmpty />}
            <LoadedView view={view} data={dashboard.data} mode="screen" />
            <details className="ds-detail ds-methodology"><summary>Report notes & methodology</summary><ul className="list-disc space-y-2 p-5 pl-9 text-sm text-muted-foreground">{dashboard.data.meta.notes.map((note, index) => <li key={index}>{note}</li>)}</ul></details>
          </>}
      </section>
    </>}
    {dashboard.data && <PrintSheet gymName={gymName} title={title} meta={dashboard.data.meta}><LoadedView view={view} data={dashboard.data} mode="print" /></PrintSheet>}
  </div>
}
