import { ChevronLeft, ChevronRight, Plus, Trash2, X } from 'lucide-react'
import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { TextField } from '@/components/text-field'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import {
  PAYROLL_FREQUENCY_LABELS,
  PAYROLL_STEP_CHOICES,
  STATUTORY_KINDS,
  bracketSummary,
  countLabel,
  currentStepLabel,
  dateLabel,
  frequencyLabel,
  isTaxRate,
  money,
  periodLabel,
  periodPresets,
  readRates,
  readRun,
  readRuns,
  readSettingsResponse,
  runStatusMeta,
  statutoryKindLabel,
  type PayrollFrequency,
  type PayrollPageMeta,
  type PayrollRun,
  type PayrollSettings,
  type PayrollStepKey,
  type StatutoryKind,
  type StatutoryRate,
} from '@/lib/payroll'

const todayIso = () => isoDay(new Date())

function isoDay(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

function message(reason: unknown, fallback: string): string {
  return reason instanceof ApiError ? reason.message : fallback
}

function formErrors(reason: unknown, known: string[], fallback: string): FieldErrors {
  const errors = reason instanceof ApiError ? reason.errors : {}
  const keys = Object.keys(errors)
  if (keys.length === 0 || keys.some((key) => !known.includes(key) && !key.startsWith('brackets.'))) {
    toastManager.add({ title: message(reason, fallback), type: 'error' })
  }
  return errors
}

function nestedErrors(errors: FieldErrors, prefix: string): string[] {
  return [...new Set(Object.entries(errors).filter(([key]) => key.startsWith(prefix)).flatMap(([, values]) => values))]
}

/** S-31 and R-59 to R-62: run payroll, keep the approval chain and statutory rates. */
export default function GymPayroll() {
  const { gymId: gymIdParam } = useParams()
  const gymId = Number(gymIdParam)
  const { user } = useAuth()
  const gym = approvedGym(user, gymId)
  const navigate = useNavigate()

  const canView = gym ? can(gym, 'payroll.runs.view') : false
  const canManage = gym ? can(gym, 'payroll.runs.manage') : false
  const canManageRates = gym ? can(gym, 'payroll.rates.manage') : false
  const isOwner = gym?.roles.includes('owner') ?? false

  const [tab, setTab] = useState('runs')
  const activeTab = tab === 'settings' || tab === 'rates' ? tab : 'runs'

  const [runs, setRuns] = useState<PayrollRun[] | null>(null)
  const [runsMeta, setRunsMeta] = useState<PayrollPageMeta | null>(null)
  const [runsError, setRunsError] = useState('')
  const [runsPage, setRunsPage] = useState(1)
  const [runsVersion, setRunsVersion] = useState(0)

  const [settings, setSettings] = useState<PayrollSettings | null>(null)
  const [settingsError, setSettingsError] = useState('')
  const [settingsVersion, setSettingsVersion] = useState(0)

  const [rates, setRates] = useState<StatutoryRate[] | null>(null)
  const [ratesError, setRatesError] = useState('')
  const [ratesVersion, setRatesVersion] = useState(0)

  const [newRunOpen, setNewRunOpen] = useState(false)
  const [rateOpen, setRateOpen] = useState(false)

  useEffect(() => {
    if (!gymId || !canView) return
    let live = true
    setRuns(null)
    setRunsError('')
    api<Record<string, unknown>>(`/gyms/${gymId}/payroll/runs?page=${runsPage}`)
      .then((payload) => {
        if (!live) return
        const result = readRuns(payload)
        setRuns(result.runs)
        setRunsMeta(result.meta)
      })
      .catch((reason) => {
        if (!live) return
        setRuns([])
        setRunsMeta(null)
        setRunsError(message(reason, 'Payroll runs could not be loaded.'))
      })
    return () => {
      live = false
    }
  }, [gymId, canView, runsPage, runsVersion])

  useEffect(() => {
    if (!gymId || !canView) return
    let live = true
    setSettingsError('')
    api<Record<string, unknown>>(`/gyms/${gymId}/payroll/settings`)
      .then((payload) => {
        if (live) setSettings(readSettingsResponse(payload))
      })
      .catch((reason) => {
        if (live) setSettingsError(message(reason, 'Payroll settings could not be loaded.'))
      })
    return () => {
      live = false
    }
  }, [gymId, canView, settingsVersion])

  useEffect(() => {
    if (!gymId || !canView || activeTab !== 'rates') return
    let live = true
    setRates(null)
    setRatesError('')
    api<Record<string, unknown>>(`/gyms/${gymId}/payroll/rates`)
      .then((payload) => {
        if (live) setRates(readRates(payload))
      })
      .catch((reason) => {
        if (!live) return
        setRates([])
        setRatesError(message(reason, 'Statutory rates could not be loaded.'))
      })
    return () => {
      live = false
    }
  }, [gymId, canView, activeTab, ratesVersion])

  if (!gym) return null

  if (!canView) {
    return (
      <div className="flex flex-col gap-6">
        <PageHeader title="Payroll" />
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>Payroll is not available</EmptyTitle>
            <EmptyDescription>Ask the owner for the payroll permission.</EmptyDescription>
          </EmptyHeader>
        </Empty>
      </div>
    )
  }

  const refreshRuns = () => setRunsVersion((value) => value + 1)
  const refreshSettings = () => setSettingsVersion((value) => value + 1)
  const refreshRates = () => setRatesVersion((value) => value + 1)
  const frequency = settings?.frequency ?? 'semi_monthly'

  const actions =
    activeTab === 'runs' && canManage ? (
      <Button size="sm" onClick={() => setNewRunOpen(true)}>
        <Plus aria-hidden />
        New run
      </Button>
    ) : activeTab === 'rates' && canManageRates ? (
      <Button size="sm" onClick={() => setRateOpen(true)}>
        <Plus aria-hidden />
        Add rate
      </Button>
    ) : undefined

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Payroll"
        description={`${gym.name}: pay every employee from stored attendance, leave, overtime, holidays and sessions. Runs move through an approval chain before release.`}
        meta={isOwner ? <Badge variant="outline">Owner</Badge> : null}
        actions={actions}
      />

      <Tabs value={activeTab} onValueChange={(value) => setTab(String(value))}>
        <TabsList variant="underline" aria-label="Payroll sections" className="max-w-full overflow-x-auto overflow-y-hidden [scrollbar-width:none]">
          <TabsTab value="runs">Runs</TabsTab>
          <TabsTab value="settings">Settings</TabsTab>
          <TabsTab value="rates">Rates</TabsTab>
        </TabsList>

        <TabsPanel value="runs" className="pt-4">
          <RunsPanel
            gymId={gymId}
            runs={runs}
            meta={runsMeta}
            error={runsError}
            canManage={canManage}
            onRetry={refreshRuns}
            onNew={() => setNewRunOpen(true)}
            onPage={setRunsPage}
          />
        </TabsPanel>

        <TabsPanel value="settings" className="pt-4">
          {settingsError ? (
            <Alert variant="error">
              <AlertTitle>Payroll settings could not be loaded</AlertTitle>
              <AlertDescription>{settingsError}</AlertDescription>
              <AlertAction>
                <Button size="xs" variant="outline" onClick={refreshSettings}>
                  Try again
                </Button>
              </AlertAction>
            </Alert>
          ) : settings === null ? (
            <LoadingRows label="Loading payroll settings" />
          ) : (
            <SettingsForm
              gymId={gymId}
              settings={settings}
              canEdit={isOwner}
              onSaved={(saved) => {
                setSettings(saved)
                refreshSettings()
              }}
            />
          )}
        </TabsPanel>

        <TabsPanel value="rates" className="pt-4">
          <RatesPanel
            rates={rates}
            error={ratesError}
            canManage={canManageRates}
            onRetry={refreshRates}
            onAdd={() => setRateOpen(true)}
          />
        </TabsPanel>
      </Tabs>

      {newRunOpen && (
        <NewRunDialog
          gymId={gymId}
          frequency={frequency}
          onClose={() => setNewRunOpen(false)}
          onCreated={(run) => {
            setNewRunOpen(false)
            refreshRuns()
            if (run.id > 0) navigate(`/gyms/${gymId}/manage/payroll/${run.id}`)
          }}
        />
      )}

      {rateOpen && (
        <RateDialog
          gymId={gymId}
          existing={rates ?? []}
          onClose={() => setRateOpen(false)}
          onSaved={() => {
            setRateOpen(false)
            refreshRates()
          }}
        />
      )}
    </div>
  )
}

function LoadingRows({ label }: { label: string }) {
  return (
    <div className="flex flex-col gap-2" aria-busy="true" aria-label={label}>
      <Skeleton className="h-12" />
      <Skeleton className="h-12" />
      <Skeleton className="h-12" />
    </div>
  )
}

function Choice({
  name,
  label,
  value,
  choices,
  onChange,
  required = false,
  disabled = false,
  description,
}: {
  name: string
  label: string
  value: string
  choices: { value: string; label: string }[]
  onChange: (value: string) => void
  required?: boolean
  disabled?: boolean
  description?: string
}) {
  return (
    <Field name={name} className="min-w-0">
      <FieldLabel>{label}</FieldLabel>
      <Select
        name={name}
        items={choices}
        value={value}
        onValueChange={(next) => onChange(next ?? '')}
        required={required}
        disabled={disabled}
      >
        <SelectTrigger aria-label={label} className="w-full min-w-0">
          <SelectValue className="min-w-0" placeholder="Choose one" />
        </SelectTrigger>
        <SelectPopup className="w-(--anchor-width) max-w-[calc(100vw-2rem)]">
          {choices.map((choice) => (
            <SelectItem key={choice.value} value={choice.value} className="whitespace-normal break-words">
              {choice.label}
            </SelectItem>
          ))}
        </SelectPopup>
      </Select>
      {description && <FieldDescription>{description}</FieldDescription>}
      <FieldError />
    </Field>
  )
}

function PageControls({ meta, label, onPage }: { meta: PayrollPageMeta | null; label: string; onPage: (page: number) => void }) {
  if (!meta || meta.last_page <= 1) return null
  return (
    <nav aria-label={`${label} pages`} className="flex flex-wrap items-center justify-between gap-3">
      <p className="text-muted-foreground text-sm" aria-live="polite">
        Page {meta.current_page} of {meta.last_page} · {meta.total} {meta.total === 1 ? 'run' : 'runs'}
      </p>
      <div className="flex gap-2">
        <Button size="sm" variant="outline" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>
          <ChevronLeft aria-hidden />
          Previous
        </Button>
        <Button size="sm" variant="outline" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)}>
          Next
          <ChevronRight aria-hidden />
        </Button>
      </div>
    </nav>
  )
}

function RunsPanel({
  gymId,
  runs,
  meta,
  error,
  canManage,
  onRetry,
  onNew,
  onPage,
}: {
  gymId: number
  runs: PayrollRun[] | null
  meta: PayrollPageMeta | null
  error: string
  canManage: boolean
  onRetry: () => void
  onNew: () => void
  onPage: (page: number) => void
}) {
  if (error) {
    return (
      <Alert variant="error">
        <AlertTitle>Payroll runs could not be loaded</AlertTitle>
        <AlertDescription>{error}</AlertDescription>
        <AlertAction>
          <Button size="xs" variant="outline" onClick={onRetry}>
            Try again
          </Button>
        </AlertAction>
      </Alert>
    )
  }

  if (runs === null) return <LoadingRows label="Loading payroll runs" />

  if (runs.length === 0) {
    return (
      <Empty className="rounded-xl border">
        <EmptyHeader>
          <EmptyTitle>No payroll runs yet</EmptyTitle>
          <EmptyDescription>
            Start with a closed pay period. Every employee and coach with an employment record gets a line, worked out from stored records.
          </EmptyDescription>
        </EmptyHeader>
        {canManage && (
          <EmptyContent>
            <Button onClick={onNew}>
              <Plus aria-hidden />
              New run
            </Button>
          </EmptyContent>
        )}
      </Empty>
    )
  }

  return (
    <div className="flex flex-col gap-4">
      <p className="text-muted-foreground text-sm" aria-live="polite">
        {countLabel(meta?.total ?? runs.length, 'run', 'runs')}, newest period first
      </p>

      <ul className="flex flex-col divide-y rounded-xl border bg-card md:hidden">
        {runs.map((run) => {
          const status = runStatusMeta(run.status)
          return (
            <li key={run.id} className="flex min-w-0 flex-col gap-3 p-4">
              <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-1">
                  <Link className="font-medium underline-offset-4 hover:underline" to={`/gyms/${gymId}/manage/payroll/${run.id}`}>
                    {periodLabel(run.starts_on, run.ends_on)}
                  </Link>
                  <span className="text-muted-foreground text-sm">{currentStepLabel(run)}</span>
                </div>
                <Badge variant={status.variant} className="shrink-0">
                  {status.label}
                </Badge>
              </div>
              <dl className="grid grid-cols-2 gap-2 text-sm">
                <div>
                  <dt className="text-muted-foreground text-xs">Headcount</dt>
                  <dd className="tabular-nums">{run.headcount}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground text-xs">Released</dt>
                  <dd>{run.released_at ? dateLabel(run.released_at) : 'Not released'}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground text-xs">Gross</dt>
                  <dd className="tabular-nums">{money(run.gross_total)}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground text-xs">Net</dt>
                  <dd className="font-medium tabular-nums">{money(run.net_total)}</dd>
                </div>
              </dl>
              <Button size="sm" variant="outline" className="self-start" render={<Link to={`/gyms/${gymId}/manage/payroll/${run.id}`} />}>
                Open payroll
              </Button>
            </li>
          )
        })}
      </ul>

      <div className="hidden min-w-0 overflow-x-auto md:block">
        <Table variant="card" className="min-w-[58rem]">
          <TableHeader>
            <TableRow>
              <TableHead>Period</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="text-right">Headcount</TableHead>
              <TableHead className="text-right">Gross</TableHead>
              <TableHead className="text-right">Net</TableHead>
              <TableHead>Current step</TableHead>
              <TableHead>Released</TableHead>
              <TableHead>
                <span className="sr-only">Actions</span>
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {runs.map((run) => {
              const status = runStatusMeta(run.status)
              return (
                <TableRow key={run.id}>
                  <TableCell className="font-medium">{periodLabel(run.starts_on, run.ends_on)}</TableCell>
                  <TableCell>
                    <Badge variant={status.variant}>{status.label}</Badge>
                  </TableCell>
                  <TableCell className="text-right tabular-nums">{run.headcount}</TableCell>
                  <TableCell className="text-right tabular-nums">{money(run.gross_total)}</TableCell>
                  <TableCell className="text-right font-medium tabular-nums">{money(run.net_total)}</TableCell>
                  <TableCell>{currentStepLabel(run)}</TableCell>
                  <TableCell>{run.released_at ? dateLabel(run.released_at) : <span className="text-muted-foreground">Not released</span>}</TableCell>
                  <TableCell>
                    <div className="flex justify-end">
                      <Button size="xs" variant="outline" render={<Link to={`/gyms/${gymId}/manage/payroll/${run.id}`} />}>
                        Open
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      </div>

      <PageControls meta={meta} label="Payroll runs" onPage={onPage} />
    </div>
  )
}

function SettingsForm({
  gymId,
  settings,
  canEdit,
  onSaved,
}: {
  gymId: number
  settings: PayrollSettings
  canEdit: boolean
  onSaved: (settings: PayrollSettings) => void
}) {
  const [frequency, setFrequency] = useState<PayrollFrequency>(settings.frequency)
  const [steps, setSteps] = useState<PayrollStepKey[]>(settings.approval_steps)
  const [grace, setGrace] = useState(String(settings.staff_grace_minutes))
  const [overtime, setOvertime] = useState(String(settings.overtime_min_minutes))
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  const nonOwner = steps.filter((step): step is 'hr_review' | 'finance_review' => step !== 'owner')
  const available = PAYROLL_STEP_CHOICES.map((choice) => choice.value).filter(
    (choice): choice is 'hr_review' | 'finance_review' => choice !== 'owner' && !nonOwner.includes(choice),
  )
  const messages = [...new Set(Object.values(errors).flat())]

  function setStepAt(index: number, value: string) {
    if (value !== 'hr_review' && value !== 'finance_review') return
    setSteps((current) => {
      const next = [...current]
      const ownerIndex = next.indexOf('owner')
      next[index] = value
      if (ownerIndex === -1) next.push('owner')
      return next
    })
  }

  function removeStep(index: number) {
    setSteps((current) => current.filter((_, position) => position !== index))
  }

  function addStep() {
    const next = available[0]
    if (!next) return
    setSteps((current) => [...current.filter((step) => step !== 'owner'), next, 'owner'])
  }

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!canEdit) return
    setBusy(true)
    setErrors({})
    try {
      const payload = await api<Record<string, unknown>>(`/gyms/${gymId}/payroll/settings`, {
        method: 'PUT',
        body: {
          frequency,
          approval_steps: [...nonOwner, 'owner'],
          staff_grace_minutes: Number(grace),
          overtime_min_minutes: Number(overtime),
        },
      })
      const saved = readSettingsResponse(payload)
      onSaved(saved)
      toastManager.add({ title: 'Payroll settings saved.', type: 'success' })
    } catch (reason) {
      setErrors(
        formErrors(
          reason,
          ['frequency', 'approval_steps', 'staff_grace_minutes', 'overtime_min_minutes'],
          'Payroll settings could not be saved.',
        ),
      )
    } finally {
      setBusy(false)
    }
  }

  return (
    <Form errors={errors} onSubmit={save} className="flex flex-col gap-6">
      <div className="flex flex-col gap-4 rounded-2xl border bg-card p-4 shadow-xs/5 sm:p-6">
        <div className="flex flex-col gap-1">
          <h2 className="font-heading font-semibold text-base">Pay period</h2>
          <p className="text-muted-foreground text-sm">How often this gym pays. A period cannot overlap another run.</p>
        </div>
        <Choice
          name="frequency"
          label="Pay frequency"
          value={frequency}
          choices={Object.entries(PAYROLL_FREQUENCY_LABELS).map(([value, label]) => ({ value, label }))}
          onChange={(value) => setFrequency(value === 'monthly' ? 'monthly' : 'semi_monthly')}
          disabled={!canEdit}
          required
          description={frequency === 'semi_monthly' ? 'Periods are the 1st to the 15th and the 16th to the end of the month.' : 'One period a month, from the 1st to the last day.'}
        />
      </div>

      <Field name="approval_steps" className="flex flex-col gap-4 rounded-2xl border bg-card p-4 shadow-xs/5 sm:p-6">
        <div className="flex flex-col gap-1">
          <FieldLabel className="text-base">Approval chain</FieldLabel>
          <FieldDescription>
            One to three steps. The owner is always the final step and releases the run. Nobody can approve two steps of the same round.
          </FieldDescription>
        </div>

        <ol className="flex flex-col gap-3">
          {nonOwner.map((step, index) => (
            <li key={`${step}-${index}`} className="flex flex-wrap items-end gap-3 rounded-xl border bg-background p-3">
              <div className="min-w-48 flex-1">
                <Choice
                  name={`approval_steps.${index}`}
                  label={`Step ${index + 1}`}
                  value={step}
                  choices={PAYROLL_STEP_CHOICES.filter(
                    (choice) => choice.value === step || (choice.value !== 'owner' && !nonOwner.includes(choice.value)),
                  )}
                  onChange={(value) => setStepAt(index, value)}
                  disabled={!canEdit}
                />
              </div>
              {canEdit && (
                <Button
                  type="button"
                  size="icon-sm"
                  variant="ghost"
                  aria-label={`Remove step ${index + 1}`}
                  onClick={() => removeStep(index)}
                >
                  <Trash2 aria-hidden />
                </Button>
              )}
            </li>
          ))}
          <li className="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-muted/40 p-3">
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="secondary">Last step</Badge>
              <span className="font-medium text-sm">Owner</span>
            </div>
            <span className="text-muted-foreground text-xs">Fixed. Only the owner can release.</span>
          </li>
        </ol>

        {canEdit && (
          <div>
            <Button type="button" size="sm" variant="outline" disabled={nonOwner.length >= 2 || available.length === 0} onClick={addStep}>
              <Plus aria-hidden />
              Add step
            </Button>
          </div>
        )}
        <FieldError />
      </Field>

      <div className="flex flex-col gap-4 rounded-2xl border bg-card p-4 shadow-xs/5 sm:p-6">
        <div className="flex flex-col gap-1">
          <h2 className="font-heading font-semibold text-base">Attendance thresholds</h2>
          <p className="text-muted-foreground text-sm">These decide when lateness becomes a deduction and when extra time counts as overtime.</p>
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField
            name="staff_grace_minutes"
            label="Grace period (minutes)"
            type="number"
            inputMode="numeric"
            min={0}
            max={1440}
            step={1}
            value={grace}
            onChange={(event) => setGrace(event.target.value)}
            disabled={!canEdit}
            required
            description="Late minutes beyond this are deducted at the minute rate."
          />
          <TextField
            name="overtime_min_minutes"
            label="Minimum overtime (minutes)"
            type="number"
            inputMode="numeric"
            min={0}
            max={1440}
            step={1}
            value={overtime}
            onChange={(event) => setOvertime(event.target.value)}
            disabled={!canEdit}
            required
            description="Extra minutes below this are not treated as an overtime request."
          />
        </div>
      </div>

      {messages.length > 0 && (
        <Alert variant="error">
          <AlertTitle>Settings need fixing</AlertTitle>
          <AlertDescription>{messages.join(' ')}</AlertDescription>
        </Alert>
      )}

      {canEdit ? (
        <div className="flex flex-wrap items-center gap-3">
          <Button type="submit" loading={busy} disabled={busy || nonOwner.length !== new Set(nonOwner).size}>
            Save payroll settings
          </Button>
          <span className="text-muted-foreground text-xs">Only the owner can change these settings.</span>
        </div>
      ) : (
        <Alert variant="info">
          <AlertTitle>Read-only settings</AlertTitle>
          <AlertDescription>Only the owner can change the pay frequency, approval chain or attendance thresholds.</AlertDescription>
        </Alert>
      )}
    </Form>
  )
}

function RatesPanel({
  rates,
  error,
  canManage,
  onRetry,
  onAdd,
}: {
  rates: StatutoryRate[] | null
  error: string
  canManage: boolean
  onRetry: () => void
  onAdd: () => void
}) {
  if (error) {
    return (
      <Alert variant="error">
        <AlertTitle>Statutory rates could not be loaded</AlertTitle>
        <AlertDescription>{error}</AlertDescription>
        <AlertAction>
          <Button size="xs" variant="outline" onClick={onRetry}>
            Try again
          </Button>
        </AlertAction>
      </Alert>
    )
  }

  if (rates === null) return <LoadingRows label="Loading statutory rates" />

  if (rates.length === 0) {
    return (
      <Empty className="rounded-xl border">
        <EmptyHeader>
          <EmptyTitle>No statutory rates yet</EmptyTitle>
          <EmptyDescription>
            Add the SSS, PhilHealth, Pag-IBIG and BIR tables with the date each one starts. A released period keeps the rates it was computed with.
          </EmptyDescription>
        </EmptyHeader>
        {canManage && (
          <EmptyContent>
            <Button onClick={onAdd}>
              <Plus aria-hidden />
              Add the first rate
            </Button>
          </EmptyContent>
        )}
      </Empty>
    )
  }

  const today = todayIso()
  const ordered = [...rates].sort((a, b) => kindIndex(a.kind) - kindIndex(b.kind) || b.effective_from.localeCompare(a.effective_from))
  const current = new Map<StatutoryKind, StatutoryRate>()
  for (const rate of ordered) {
    if (rate.effective_from <= today && !current.has(rate.kind)) current.set(rate.kind, rate)
  }
  const currentRows = STATUTORY_KINDS.flatMap((kind) => {
    const rate = current.get(kind)
    return rate ? [rate] : []
  })
  const currentIds = new Set(currentRows.map((rate) => rate.id))
  const history = ordered.filter((rate) => !currentIds.has(rate.id))

  return (
    <div className="flex flex-col gap-8">
      <section aria-labelledby="rates-current" className="flex flex-col gap-3">
        <div className="flex flex-wrap items-baseline justify-between gap-2">
          <h2 id="rates-current" className="font-heading font-semibold text-base">
            In effect now
          </h2>
          <p className="text-muted-foreground text-sm">Newest rate per kind starting on or before today.</p>
        </div>
        {currentRows.length === 0 ? (
          <p className="rounded-xl border px-4 py-3 text-muted-foreground text-sm">
            No rate has started yet. A run submitted before rates exist will carry a blocking warning.
          </p>
        ) : (
          <RateTable rates={currentRows} currentIds={currentIds} today={today} />
        )}
      </section>

      {history.length > 0 && (
        <section aria-labelledby="rates-history" className="flex flex-col gap-3">
          <h2 id="rates-history" className="font-heading font-semibold text-base">
            Past and upcoming
          </h2>
          <RateTable rates={history} currentIds={currentIds} today={today} />
        </section>
      )}

      {canManage && (
        <div>
          <Button size="sm" variant="outline" onClick={onAdd}>
            <Plus aria-hidden />
            Add rate
          </Button>
        </div>
      )}
    </div>
  )
}

function RateTable({ rates, currentIds, today }: { rates: StatutoryRate[]; currentIds: Set<number>; today: string }) {
  return (
    <>
      <ul className="flex flex-col divide-y rounded-xl border bg-card md:hidden">
        {rates.map((rate) => {
          const state = currentIds.has(rate.id) ? 'Current' : rate.effective_from > today ? 'Upcoming' : 'Past'
          return (
            <li key={rate.id} className="flex min-w-0 flex-col gap-2 p-4">
              <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-1">
                  <span className="font-medium">{statutoryKindLabel(rate.kind)}</span>
                  <span className="text-muted-foreground text-sm">From {dateLabel(rate.effective_from)}</span>
                </div>
                <Badge variant={state === 'Current' ? 'success' : state === 'Upcoming' ? 'info' : 'outline'} className="shrink-0">
                  {state}
                </Badge>
              </div>
              <p className="text-sm">{bracketSummary(rate)}</p>
              <p className="break-words text-muted-foreground text-xs">{rate.source || 'No source recorded'}</p>
            </li>
          )
        })}
      </ul>

      <div className="hidden min-w-0 overflow-x-auto md:block">
        <Table variant="card" className="min-w-[46rem]">
          <TableHeader>
            <TableRow>
              <TableHead>Effective from</TableHead>
              <TableHead>Kind</TableHead>
              <TableHead>Brackets</TableHead>
              <TableHead>Source</TableHead>
              <TableHead>State</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rates.map((rate) => {
              const state = currentIds.has(rate.id) ? 'Current' : rate.effective_from > today ? 'Upcoming' : 'Past'
              return (
                <TableRow key={rate.id}>
                  <TableCell>
                    <time dateTime={rate.effective_from}>{dateLabel(rate.effective_from)}</time>
                  </TableCell>
                  <TableCell className="font-medium">{statutoryKindLabel(rate.kind)}</TableCell>
                  <TableCell>{bracketSummary(rate)}</TableCell>
                  <TableCell className="max-w-64 whitespace-normal">
                    {rate.source ? <span className="break-words">{rate.source}</span> : <span className="text-muted-foreground">No source</span>}
                    {rate.created_by_name && <span className="mt-0.5 block text-muted-foreground text-xs">Added by {rate.created_by_name}</span>}
                  </TableCell>
                  <TableCell>
                    <Badge variant={state === 'Current' ? 'success' : state === 'Upcoming' ? 'info' : 'outline'}>{state}</Badge>
                  </TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      </div>
    </>
  )
}

function NewRunDialog({
  gymId,
  frequency,
  onClose,
  onCreated,
}: {
  gymId: number
  frequency: PayrollFrequency
  onClose: () => void
  onCreated: (run: PayrollRun) => void
}) {
  const presets = periodPresets(frequency)
  const [preset, setPreset] = useState(presets[0]?.value ?? 'custom')
  const [startsOn, setStartsOn] = useState(presets[0]?.starts_on ?? '')
  const [endsOn, setEndsOn] = useState(presets[0]?.ends_on ?? '')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    const next = periodPresets(frequency)
    setPreset(next[0]?.value ?? 'custom')
    setStartsOn(next[0]?.starts_on ?? '')
    setEndsOn(next[0]?.ends_on ?? '')
  }, [frequency])

  const presetItems = [
    ...presets.map((item) => ({ value: item.value, label: item.label })),
    { value: 'custom', label: 'Custom dates' },
  ]
  const valid = Boolean(startsOn && endsOn && endsOn >= startsOn)
  const messages = [...new Set(Object.values(errors).flat())]

  function choosePreset(value: string) {
    setPreset(value)
    const chosen = presets.find((item) => item.value === value)
    if (chosen) {
      setStartsOn(chosen.starts_on)
      setEndsOn(chosen.ends_on)
    }
  }

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!valid || busy) return
    setBusy(true)
    setErrors({})
    try {
      const payload = await api<Record<string, unknown>>(`/gyms/${gymId}/payroll/runs`, {
        body: { starts_on: startsOn, ends_on: endsOn },
      })
      const run = readRun(payload.run ?? payload)
      toastManager.add({ title: 'Payroll run created.', type: 'success' })
      onCreated(run)
    } catch (reason) {
      setErrors(formErrors(reason, ['starts_on', 'ends_on'], 'The payroll run could not be created.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>New payroll run</DialogTitle>
            <DialogDescription>
              {frequencyLabel(frequency)}. Choose a closed period; the run gets a line for every employee and coach whose employment overlaps it.
            </DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-5">
            <Choice
              name="preset"
              label="Period preset"
              value={preset}
              choices={presetItems}
              onChange={choosePreset}
              description="Presets are completed periods for this gym's frequency."
            />

            <div className="grid gap-4 sm:grid-cols-2">
              <TextField
                name="starts_on"
                label="First day"
                type="date"
                value={startsOn}
                onChange={(event) => {
                  setPreset('custom')
                  setStartsOn(event.target.value)
                }}
                required
              />
              <TextField
                name="ends_on"
                label="Last day"
                type="date"
                value={endsOn}
                min={startsOn || undefined}
                onChange={(event) => {
                  setPreset('custom')
                  setEndsOn(event.target.value)
                }}
                required
              />
            </div>

            {startsOn && endsOn && endsOn >= startsOn && (
              <p className="text-muted-foreground text-sm">{periodLabel(startsOn, endsOn)} will be reserved for this run.</p>
            )}
            {startsOn && endsOn && endsOn < startsOn && <p className="text-destructive text-sm">The last day must be on or after the first day.</p>}

            {messages.length > 0 && (
              <Alert variant="error">
                <AlertTitle>The period cannot be used</AlertTitle>
                <AlertDescription>{messages.join(' ')}</AlertDescription>
              </Alert>
            )}
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={!valid || busy}>
              Create run
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

type BracketRow = {
  min: string
  max: string
  employee: string
  employer: string
  base: string
  rate: string
}

const emptyBracket = (): BracketRow => ({ min: '0', max: '', employee: '', employer: '', base: '', rate: '' })

function RateDialog({
  gymId,
  existing,
  onClose,
  onSaved,
}: {
  gymId: number
  existing: StatutoryRate[]
  onClose: () => void
  onSaved: () => void
}) {
  const [kind, setKind] = useState<StatutoryKind>('sss')
  const [effectiveFrom, setEffectiveFrom] = useState(todayIso)
  const [source, setSource] = useState('')
  const [mode, setMode] = useState<'table' | 'json'>('table')
  const [rows, setRows] = useState<BracketRow[]>([emptyBracket()])
  const [json, setJson] = useState('[\n  { "min": 0, "max": null, "employee": 0, "employer": 0 }\n]')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [localProblem, setLocalProblem] = useState('')
  const [busy, setBusy] = useState(false)

  const tax = isTaxRate(kind)
  const messages = [...new Set([...Object.values(errors).flat(), ...nestedErrors(errors, 'brackets')])]
  const duplicate = existing.some((rate) => rate.kind === kind && rate.effective_from === effectiveFrom)

  function changeKind(value: string) {
    if (!STATUTORY_KINDS.includes(value as StatutoryKind)) return
    const next = value as StatutoryKind
    setKind(next)
    setRows([emptyBracket()])
    setJson(
      isTaxRate(next)
        ? '[\n  { "min": 0, "max": null, "base": 0, "rate": 0 }\n]'
        : '[\n  { "min": 0, "max": null, "employee": 0, "employer": 0 }\n]',
    )
    setLocalProblem('')
    setErrors({})
  }

  function updateRow(index: number, patch: Partial<BracketRow>) {
    setRows((current) => current.map((row, position) => (position === index ? { ...row, ...patch } : row)))
  }

  function buildBrackets(): unknown[] | null {
    if (mode === 'json') {
      try {
        const parsed: unknown = JSON.parse(json)
        if (!Array.isArray(parsed) || parsed.length === 0) {
          setLocalProblem('The brackets must be a JSON array with at least one entry.')
          return null
        }
        return parsed
      } catch {
        setLocalProblem('The bracket JSON is not valid. Check the commas and brackets.')
        return null
      }
    }

    const brackets: Record<string, unknown>[] = []
    for (const [index, row] of rows.entries()) {
      const min = Number(row.min)
      if (row.min.trim() === '' || !Number.isFinite(min) || min < 0) {
        setLocalProblem(`Bracket ${index + 1}: enter a starting amount of zero or more.`)
        return null
      }
      const max = row.max.trim() === '' ? null : Number(row.max)
      if (max !== null && (!Number.isFinite(max) || max < min)) {
        setLocalProblem(`Bracket ${index + 1}: the ending amount must be empty or at least the starting amount.`)
        return null
      }

      if (tax) {
        const base = row.base.trim() === '' ? 0 : Number(row.base)
        const rate = row.rate.trim() === '' ? 0 : Number(row.rate)
        if (!Number.isFinite(base) || base < 0 || !Number.isFinite(rate) || rate < 0) {
          setLocalProblem(`Bracket ${index + 1}: base and rate must be zero or more.`)
          return null
        }
        brackets.push({ min, max, base, rate: rate / 100 })
      } else {
        const employee = row.employee.trim() === '' ? 0 : Number(row.employee)
        const employer = row.employer.trim() === '' ? 0 : Number(row.employer)
        if (!Number.isFinite(employee) || employee < 0 || !Number.isFinite(employer) || employer < 0) {
          setLocalProblem(`Bracket ${index + 1}: employee and employer shares must be zero or more.`)
          return null
        }
        brackets.push({ min, max, employee, employer })
      }
    }
    if (brackets.length === 0) {
      setLocalProblem('Add at least one bracket.')
      return null
    }
    return brackets
  }

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (busy) return
    setLocalProblem('')
    setErrors({})
    if (!effectiveFrom) {
      setLocalProblem('Choose the date this rate starts.')
      return
    }
    const brackets = buildBrackets()
    if (!brackets) return

    setBusy(true)
    try {
      await api(`/gyms/${gymId}/payroll/rates`, {
        body: {
          kind,
          effective_from: effectiveFrom,
          source: source.trim() || null,
          brackets,
        },
      })
      toastManager.add({ title: 'Statutory rate added.', type: 'success' })
      onSaved()
    } catch (reason) {
      setErrors(formErrors(reason, ['kind', 'effective_from', 'source', 'brackets'], 'The statutory rate could not be added.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup className="sm:max-w-2xl">
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Add statutory rate</DialogTitle>
            <DialogDescription>
              Rates are insert-only and effective-dated. New runs use the newest rate whose start date is on or before the period end.
            </DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-5">
            <div className="grid gap-4 sm:grid-cols-2">
              <Choice
                name="kind"
                label="Kind"
                value={kind}
                choices={STATUTORY_KINDS.map((value) => ({ value, label: statutoryKindLabel(value) }))}
                onChange={changeKind}
                required
              />
              <TextField
                name="effective_from"
                label="Starts on"
                type="date"
                value={effectiveFrom}
                onChange={(event) => setEffectiveFrom(event.target.value)}
                required
              />
            </div>
            <TextField
              name="source"
              label="Source (optional)"
              value={source}
              onChange={(event) => setSource(event.target.value)}
              maxLength={255}
              placeholder="For example: SSS circular 2025-001"
              description="The circular or table this rate came from. It stays with released history."
            />

            {duplicate && (
              <Alert variant="warning">
                <AlertTitle>This kind already starts on that date</AlertTitle>
                <AlertDescription>
                  A {statutoryKindLabel(kind)} rate with this effective date is already listed. The server may refuse a duplicate.
                </AlertDescription>
              </Alert>
            )}

            <fieldset className="flex flex-col gap-3">
              <legend className="font-medium text-sm">Brackets</legend>
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-muted-foreground text-sm">
                  {tax
                    ? 'BIR table rows: the amount range, the base tax and the marginal rate in percent.'
                    : 'Contribution rows: the monthly salary range and the employee and employer shares in pesos.'}
                </p>
                <div className="flex gap-2" role="group" aria-label="Bracket editor mode">
                  <Button type="button" size="xs" variant={mode === 'table' ? 'default' : 'outline'} aria-pressed={mode === 'table'} onClick={() => setMode('table')}>
                    Table
                  </Button>
                  <Button type="button" size="xs" variant={mode === 'json' ? 'default' : 'outline'} aria-pressed={mode === 'json'} onClick={() => setMode('json')}>
                    Paste JSON
                  </Button>
                </div>
              </div>

              {mode === 'table' ? (
                <div className="flex flex-col gap-3">
                  {rows.map((row, index) => (
                    <div key={index} className="flex flex-col gap-3 rounded-xl border bg-card p-3">
                      <div className="flex items-center justify-between gap-2">
                        <span className="font-medium text-sm">Bracket {index + 1}</span>
                        <Button
                          type="button"
                          size="icon-sm"
                          variant="ghost"
                          aria-label={`Remove bracket ${index + 1}`}
                          disabled={rows.length === 1}
                          onClick={() => setRows((current) => current.filter((_, position) => position !== index))}
                        >
                          <X aria-hidden />
                        </Button>
                      </div>
                      <div className="grid gap-3 sm:grid-cols-2">
                        <TextField
                          name={`brackets.${index}.min`}
                          label="From (₱)"
                          type="number"
                          inputMode="decimal"
                          min={0}
                          step="0.01"
                          value={row.min}
                          onChange={(event) => updateRow(index, { min: event.target.value })}
                          required
                        />
                        <TextField
                          name={`brackets.${index}.max`}
                          label="To (₱, empty for no ceiling)"
                          type="number"
                          inputMode="decimal"
                          min={0}
                          step="0.01"
                          value={row.max}
                          onChange={(event) => updateRow(index, { max: event.target.value })}
                        />
                        {tax ? (
                          <>
                            <TextField
                              name={`brackets.${index}.base`}
                              label="Base tax (₱)"
                              type="number"
                              inputMode="decimal"
                              min={0}
                              step="0.01"
                              value={row.base}
                              onChange={(event) => updateRow(index, { base: event.target.value })}
                            />
                            <TextField
                              name={`brackets.${index}.rate`}
                              label="Rate (%)"
                              type="number"
                              inputMode="decimal"
                              min={0}
                              step="0.01"
                              value={row.rate}
                              onChange={(event) => updateRow(index, { rate: event.target.value })}
                            />
                          </>
                        ) : (
                          <>
                            <TextField
                              name={`brackets.${index}.employee`}
                              label="Employee share (₱)"
                              type="number"
                              inputMode="decimal"
                              min={0}
                              step="0.01"
                              value={row.employee}
                              onChange={(event) => updateRow(index, { employee: event.target.value })}
                            />
                            <TextField
                              name={`brackets.${index}.employer`}
                              label="Employer share (₱)"
                              type="number"
                              inputMode="decimal"
                              min={0}
                              step="0.01"
                              value={row.employer}
                              onChange={(event) => updateRow(index, { employer: event.target.value })}
                            />
                          </>
                        )}
                      </div>
                    </div>
                  ))}
                  <div>
                    <Button type="button" size="sm" variant="outline" onClick={() => setRows((current) => [...current, emptyBracket()])}>
                      <Plus aria-hidden />
                      Add bracket
                    </Button>
                  </div>
                </div>
              ) : (
                <Field name="brackets">
                  <FieldLabel>Bracket JSON</FieldLabel>
                  <Textarea
                    name="brackets"
                    rows={8}
                    className="font-mono text-xs"
                    value={json}
                    onChange={(event) => setJson(event.target.value)}
                    spellCheck={false}
                    aria-describedby="bracket-json-help"
                  />
                  <FieldDescription id="bracket-json-help">
                    A JSON array. Use the exact keys the payroll calculator expects; table mode sends min, max and the shares or tax fields.
                  </FieldDescription>
                  <FieldError />
                </Field>
              )}
            </fieldset>

            {(localProblem || messages.length > 0) && (
              <Alert variant="error">
                <AlertTitle>Check the rate</AlertTitle>
                <AlertDescription>{localProblem || messages.join(' ')}</AlertDescription>
              </Alert>
            )}
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={busy || !effectiveFrom}>
              Add rate
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function kindIndex(kind: StatutoryKind): number {
  const index = STATUTORY_KINDS.indexOf(kind)
  return index === -1 ? STATUTORY_KINDS.length : index
}
