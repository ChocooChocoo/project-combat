import { Fragment, useEffect, useRef, useState } from 'react'
import { Plus, X } from 'lucide-react'
import { Link, useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { TextField } from '@/components/text-field'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { PageControls } from '@/components/page-controls'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import {
  EXPENSE_CATEGORY_LABELS,
  INCOME_TYPE_LABELS,
  SALE_INCOME_TYPES,
  correctableExpense,
  expenseCategoryLabel,
  hasExpenseCorrection,
  incomeTypeLabel,
  metaFrom,
  methodLabel,
  receivableSourceLabel,
  rowsOf,
  sourceTypeLabel,
  totalsFrom,
  type DeskMethod,
  type Expense,
  type ExpenseCategory,
  type ExpenseMethod,
  type FinanceTotal,
  type IncomeRow,
  type IncomeType,
  type PageMeta,
  type Receivable,
  type ReceivableSource,
} from '@/lib/finance'
import { quantityLabel, type StockItem } from '@/lib/inventory'
import { dateLabel, money, type Membership } from '@/lib/membership'
import {
  SUPPLIER_PAYMENT_METHOD_LABELS,
  purchaseOrderStatusMeta,
  supplierPaymentMethodLabel,
  type Payable,
  type PayableOrder,
  type Supplier as ProcurementSupplier,
  type SupplierPayment,
} from '@/lib/procurement'
import {
  REPORT_DEFINITIONS,
  amountsFrom,
  budgetGroups,
  budgetKey,
  budgetOverviewFrom,
  bucketModeLabel,
  countLabel,
  isMonthKey,
  monthKey,
  monthTitle,
  percentLabel,
  reportBarMax,
  reportBarWidth,
  reportBucketMode,
  reportFrom,
  reportPeriodDateTime,
  reportPeriodLabel,
  type BudgetGroup,
  type BudgetOverview,
  type ReportDefinition,
  type ReportResult,
} from '@/lib/reports'
import { cn } from '@/lib/utils'

const deskMethods: { value: DeskMethod; label: string }[] = [
  { value: 'cash', label: 'Cash' },
  { value: 'card', label: 'Card' },
  { value: 'ewallet', label: 'E-wallet' },
]

const expenseMethods = [...deskMethods, { value: 'bank' as const, label: 'Bank transfer' }]

const incomeTypeChoices = [
  { value: 'all', label: 'All income types' },
  ...Object.entries(INCOME_TYPE_LABELS).map(([value, label]) => ({ value, label })),
]

const expenseCategoryChoices = Object.entries(EXPENSE_CATEGORY_LABELS).map(([value, label]) => ({ value, label }))

const expenseCategoryFilterChoices = [{ value: 'all', label: 'All categories' }, ...expenseCategoryChoices]

const saleTypeChoices = SALE_INCOME_TYPES.map((value) => ({ value, label: INCOME_TYPE_LABELS[value] }))

const supplierPaymentMethods = Object.entries(SUPPLIER_PAYMENT_METHOD_LABELS).map(([value, label]) => ({ value, label }))

type MemberChoice = { id: number; name: string }
type SaleLine = { stockItemId: number; quantity: string }
type PayTarget = { supplierId: number; supplierName: string; outstanding: string }

const todayIso = () => isoDay(new Date())
const monthStartIso = () => isoDay(new Date(new Date().getFullYear(), new Date().getMonth(), 1))

function isoDay(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

function message(reason: unknown, fallback: string): string {
  return reason instanceof ApiError ? reason.message : fallback
}

function formErrors(
  reason: unknown,
  known: string[],
  fallback: string,
  prefixes: string[] = ['items.'],
): FieldErrors {
  const errors = reason instanceof ApiError ? reason.errors : {}
  const keys = Object.keys(errors)
  if (
    keys.length === 0 ||
    keys.some(
      (key) => !known.includes(key) && !prefixes.some((prefix) => key.startsWith(prefix)),
    )
  ) {
    toastManager.add({ title: message(reason, fallback), type: 'error' })
  }
  return errors
}

function nestedErrors(errors: FieldErrors, prefix: string): string[] {
  return [...new Set(Object.entries(errors).filter(([key]) => key.startsWith(prefix)).flatMap(([, values]) => values))]
}

function aggregateTotals<T>(rows: T[], key: (row: T) => string, amount: (row: T) => string | number): FinanceTotal[] {
  const totals = new Map<string, number>()
  for (const row of rows) {
    const value = Number(amount(row))
    if (!Number.isFinite(value)) continue
    const name = key(row)
    totals.set(name, (totals.get(name) ?? 0) + value)
  }
  return [...totals.entries()].map(([name, value]) => ({ key: name, amount: String(value) }))
}

function incomeParty(row: IncomeRow): string {
  return row.member_name?.trim() || row.payer_name?.trim() || 'No buyer named'
}

function hasFilters(from: string, to: string, extra: string, extraAll = 'all'): boolean {
  return from !== monthStartIso() || to !== todayIso() || extra !== extraAll
}

/** S-30 and R-63 to R-68: income, expenses, balances owed, budgets and sustainability reports. */
export default function GymFinance() {
  const { gymId: gymIdParam } = useParams()
  const gymId = Number(gymIdParam)
  const { user } = useAuth()
  const gym = approvedGym(user, gymId)

  const canViewIncome = gym ? can(gym, 'finance.payments.view') : false
  const canViewExpenses = gym ? can(gym, 'finance.expenses.view') : false
  const canViewReceivables = gym ? can(gym, 'finance.receivables.view') : false
  const canViewPayables = gym ? can(gym, 'finance.payables.view') : false
  const canManagePayables = gym ? can(gym, 'finance.payables.manage') : false
  const canViewPurchaseOrders = gym ? can(gym, 'procurement.orders.view') : false
  const canRecordSale = gym ? can(gym, 'finance.payments.manage') : false
  const canManageExpenses = gym ? can(gym, 'finance.expenses.manage') : false
  const canViewBudgets = gym ? can(gym, 'finance.budgets.view') : false
  const canManageBudgets = gym ? can(gym, 'finance.budgets.manage') : false
  const canViewReports = gym ? can(gym, 'finance.reports.view') : false

  const firstTab = canViewIncome
    ? 'income'
    : canViewExpenses
      ? 'expenses'
      : canViewReceivables
        ? 'receivables'
        : canViewPayables
          ? 'payables'
          : canViewBudgets
            ? 'budgets'
            : canViewReports
              ? 'reports'
              : 'income'
  const allowedTabs = [
    canViewIncome ? 'income' : null,
    canViewExpenses ? 'expenses' : null,
    canViewReceivables ? 'receivables' : null,
    canViewPayables ? 'payables' : null,
    canViewBudgets ? 'budgets' : null,
    canViewReports ? 'reports' : null,
  ].filter((value): value is string => value !== null)
  const [tab, setTab] = useState(firstTab)
  const activeTab = allowedTabs.includes(tab) ? tab : (allowedTabs[0] ?? 'income')
  const [from, setFrom] = useState(monthStartIso)
  const [to, setTo] = useState(todayIso)
  const [typeFilter, setTypeFilter] = useState('all')
  const [categoryFilter, setCategoryFilter] = useState('all')

  const [income, setIncome] = useState<IncomeRow[] | null>(null)
  const [incomeMeta, setIncomeMeta] = useState<PageMeta | null>(null)
  const [incomeTotals, setIncomeTotals] = useState<FinanceTotal[]>([])
  const [incomeError, setIncomeError] = useState('')
  const [incomePage, setIncomePage] = useState(1)
  const [incomeVersion, setIncomeVersion] = useState(0)

  const [expenses, setExpenses] = useState<Expense[] | null>(null)
  const [expenseMeta, setExpenseMeta] = useState<PageMeta | null>(null)
  const [expenseTotals, setExpenseTotals] = useState<FinanceTotal[]>([])
  const [expenseError, setExpenseError] = useState('')
  const [expensePage, setExpensePage] = useState(1)
  const [expenseVersion, setExpenseVersion] = useState(0)

  const [receivables, setReceivables] = useState<Receivable[] | null>(null)
  const [receivablesError, setReceivablesError] = useState('')
  const [receivablesVersion, setReceivablesVersion] = useState(0)

  const [payables, setPayables] = useState<Payable[] | null>(null)
  const [payablesError, setPayablesError] = useState('')
  const [payablesVersion, setPayablesVersion] = useState(0)

  const [saleOpen, setSaleOpen] = useState(false)
  const [expenseOpen, setExpenseOpen] = useState(false)
  const [correcting, setCorrecting] = useState<Expense | null>(null)

  useEffect(() => {
    if (!gymId || !canViewIncome || activeTab !== 'income') return
    let live = true
    setIncome(null)
    setIncomeError('')
    const query = new URLSearchParams()
    if (from) query.set('from', from)
    if (to) query.set('to', to)
    if (typeFilter !== 'all') query.set('type', typeFilter)
    query.set('page', String(incomePage))
    api<Record<string, unknown>>(`/gyms/${gymId}/finance/income?${query.toString()}`)
      .then((payload) => {
        if (!live) return
        const rows = rowsOf<IncomeRow>(payload, 'income')
        setIncome(rows)
        setIncomeMeta(metaFrom(payload, rows.length))
        setIncomeTotals(totalsFrom(payload, 'type'))
      })
      .catch((reason) => {
        if (!live) return
        setIncome([])
        setIncomeMeta(null)
        setIncomeTotals([])
        setIncomeError(message(reason, 'Income could not be loaded.'))
      })
    return () => {
      live = false
    }
  }, [gymId, canViewIncome, activeTab, from, to, typeFilter, incomePage, incomeVersion])

  useEffect(() => {
    if (!gymId || !canViewExpenses || activeTab !== 'expenses') return
    let live = true
    setExpenses(null)
    setExpenseError('')
    const query = new URLSearchParams()
    if (from) query.set('from', from)
    if (to) query.set('to', to)
    if (categoryFilter !== 'all') query.set('category', categoryFilter)
    query.set('page', String(expensePage))
    api<Record<string, unknown>>(`/gyms/${gymId}/finance/expenses?${query.toString()}`)
      .then((payload) => {
        if (!live) return
        const rows = rowsOf<Expense>(payload, 'expenses')
        setExpenses(rows)
        setExpenseMeta(metaFrom(payload, rows.length))
        setExpenseTotals(totalsFrom(payload, 'category'))
      })
      .catch((reason) => {
        if (!live) return
        setExpenses([])
        setExpenseMeta(null)
        setExpenseTotals([])
        setExpenseError(message(reason, 'Expenses could not be loaded.'))
      })
    return () => {
      live = false
    }
  }, [gymId, canViewExpenses, activeTab, from, to, categoryFilter, expensePage, expenseVersion])

  useEffect(() => {
    if (!gymId || !canViewReceivables || activeTab !== 'receivables') return
    let live = true
    setReceivables(null)
    setReceivablesError('')
    api<Record<string, unknown>>(`/gyms/${gymId}/finance/receivables`)
      .then((payload) => {
        if (live) setReceivables(rowsOf<Receivable>(payload, 'receivables'))
      })
      .catch((reason) => {
        if (!live) return
        setReceivables([])
        setReceivablesError(message(reason, 'Balances owed could not be loaded.'))
      })
    return () => {
      live = false
    }
  }, [gymId, canViewReceivables, activeTab, receivablesVersion])

  useEffect(() => {
    if (!gymId || !canViewPayables || activeTab !== 'payables') return
    let live = true
    setPayables(null)
    setPayablesError('')
    api<Record<string, unknown>>(`/gyms/${gymId}/finance/payables`)
      .then((payload) => {
        if (live) setPayables(rowsOf<Payable>(payload, 'payables'))
      })
      .catch((reason) => {
        if (!live) return
        setPayables([])
        setPayablesError(message(reason, 'Supplier payables could not be loaded.'))
      })
    return () => {
      live = false
    }
  }, [gymId, canViewPayables, activeTab, payablesVersion])

  if (!gym) return null

  if (!canViewIncome && !canViewExpenses && !canViewReceivables && !canViewPayables && !canViewBudgets && !canViewReports) {
    return (
      <div className="flex flex-col gap-6">
        <PageHeader title="Finance" />
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>Finance is not available</EmptyTitle>
            <EmptyDescription>Ask the owner for access to payments, expenses, budgets, reports or member balances.</EmptyDescription>
          </EmptyHeader>
        </Empty>
      </div>
    )
  }

  const refreshIncome = () => setIncomeVersion((value) => value + 1)
  const refreshExpenses = () => setExpenseVersion((value) => value + 1)
  const refreshReceivables = () => setReceivablesVersion((value) => value + 1)
  const refreshPayables = () => setPayablesVersion((value) => value + 1)

  function changeFrom(value: string) {
    setFrom(value)
    if (value && to && value > to) setTo(value)
    setIncomePage(1)
    setExpensePage(1)
  }

  function changeTo(value: string) {
    setTo(value)
    if (value && from && value < from) setFrom(value)
    setIncomePage(1)
    setExpensePage(1)
  }

  function clearFilters() {
    setFrom(monthStartIso())
    setTo(todayIso())
    setTypeFilter('all')
    setCategoryFilter('all')
    setIncomePage(1)
    setExpensePage(1)
  }

  const actions = (
    <>
      {activeTab === 'income' && canViewIncome && (
        <Button size="sm" variant="outline" render={<Link to={`/gyms/${gymId}/manage/payments`} />}>
          Payments desk
        </Button>
      )}
      {activeTab === 'income' && canRecordSale && (
        <Button size="sm" onClick={() => setSaleOpen(true)}>
          <Plus aria-hidden />
          Record sale
        </Button>
      )}
      {activeTab === 'expenses' && canManageExpenses && (
        <Button size="sm" onClick={() => setExpenseOpen(true)}>
          <Plus aria-hidden />
          Add expense
        </Button>
      )}
    </>
  )
  const showActions =
    (activeTab === 'income' && (canViewIncome || canRecordSale)) ||
    (activeTab === 'expenses' && canManageExpenses)

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Finance"
        description={`${gym.name}: money in and out, balances owed, monthly budgets and sustainability reports. Membership, session and online payments arrive here on their own.`}
        actions={showActions ? actions : undefined}
      />

      <Tabs value={activeTab} onValueChange={(value) => setTab(String(value))}>
        <TabsList
          variant="underline"
          aria-label="Finance sections"
          className="max-w-full overflow-x-auto overflow-y-hidden [scrollbar-width:none]"
        >
          {canViewIncome && <TabsTab value="income">Income</TabsTab>}
          {canViewExpenses && <TabsTab value="expenses">Expenses</TabsTab>}
          {canViewReceivables && <TabsTab value="receivables">Receivables</TabsTab>}
          {canViewPayables && <TabsTab value="payables">Payables</TabsTab>}
          {canViewBudgets && <TabsTab value="budgets">Budgets</TabsTab>}
          {canViewReports && <TabsTab value="reports">Reports</TabsTab>}
        </TabsList>

        {canViewIncome && (
          <TabsPanel value="income" className="pt-4">
            <IncomePanel
              rows={income}
              meta={incomeMeta}
              totals={incomeTotals}
              error={incomeError}
              from={from}
              to={to}
              type={typeFilter}
              canRecordSale={canRecordSale}
              onFrom={changeFrom}
              onTo={changeTo}
              onType={(value) => {
                setTypeFilter(value)
                setIncomePage(1)
              }}
              onClear={clearFilters}
              onRetry={refreshIncome}
              onRecord={() => setSaleOpen(true)}
              onPage={setIncomePage}
            />
          </TabsPanel>
        )}

        {canViewExpenses && (
          <TabsPanel value="expenses" className="pt-4">
            <ExpensePanel
              rows={expenses}
              meta={expenseMeta}
              totals={expenseTotals}
              error={expenseError}
              from={from}
              to={to}
              category={categoryFilter}
              canManage={canManageExpenses}
              onFrom={changeFrom}
              onTo={changeTo}
              onCategory={(value) => {
                setCategoryFilter(value)
                setExpensePage(1)
              }}
              onClear={clearFilters}
              onRetry={refreshExpenses}
              onAdd={() => setExpenseOpen(true)}
              onCorrect={setCorrecting}
              onPage={setExpensePage}
            />
          </TabsPanel>
        )}

        {canViewReceivables && (
          <TabsPanel value="receivables" className="pt-4">
            <ReceivablePanel rows={receivables} error={receivablesError} onRetry={refreshReceivables} />
          </TabsPanel>
        )}

        {canViewPayables && (
          <TabsPanel value="payables" className="pt-4">
            <PayablesPanel
              gymId={gymId}
              rows={payables}
              error={payablesError}
              canManage={canManagePayables}
              canViewPurchaseOrders={canViewPurchaseOrders}
              onRetry={refreshPayables}
              onPaid={refreshPayables}
            />
          </TabsPanel>
        )}

        {canViewBudgets && (
          <TabsPanel value="budgets" className="pt-4">
            <BudgetsPanel
              gymId={gymId}
              canManage={canManageBudgets}
              active={activeTab === 'budgets'}
            />
          </TabsPanel>
        )}

        {canViewReports && (
          <TabsPanel value="reports" className="pt-4">
            <ReportsPanel gymId={gymId} active={activeTab === 'reports'} />
          </TabsPanel>
        )}
      </Tabs>

      {saleOpen && canRecordSale && (
        <RecordSaleDialog
          gymId={gymId}
          onClose={() => setSaleOpen(false)}
          onSaved={() => {
            setSaleOpen(false)
            refreshIncome()
          }}
        />
      )}

      {expenseOpen && canManageExpenses && (
        <ExpenseDialog
          gymId={gymId}
          onClose={() => setExpenseOpen(false)}
          onSaved={() => {
            setExpenseOpen(false)
            refreshExpenses()
          }}
        />
      )}

      {correcting && canManageExpenses && (
        <CorrectExpenseDialog
          gymId={gymId}
          expense={correcting}
          onClose={() => setCorrecting(null)}
          onSaved={() => {
            setCorrecting(null)
            refreshExpenses()
          }}
        />
      )}
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

function LoadingRows({ label }: { label: string }) {
  return (
    <div className="flex flex-col gap-2" aria-busy="true" aria-label={label}>
      <Skeleton className="h-12" />
      <Skeleton className="h-12" />
      <Skeleton className="h-12" />
    </div>
  )
}

function FinanceTotals({
  label,
  total,
  items,
}: {
  label: string
  total: number
  items: { key: string; label: string; amount: string }[]
}) {
  return (
    <section aria-label={label} className="flex flex-col gap-3">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="font-heading font-semibold text-base">{label}</h2>
        <p className="text-sm">
          Total <strong className="tabular-nums">{money(total)}</strong>
        </p>
      </div>
      {items.length > 0 && (
        <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-xl border bg-border sm:grid-cols-3 xl:grid-cols-6">
          {items.map((item) => (
            <div key={item.key} className="bg-card px-3 py-2.5">
              <dt className="text-muted-foreground text-xs">{item.label}</dt>
              <dd className="mt-1 font-semibold tabular-nums">{money(item.amount)}</dd>
            </div>
          ))}
        </dl>
      )}
    </section>
  )
}

function IncomePanel({
  rows,
  meta,
  totals,
  error,
  from,
  to,
  type,
  canRecordSale,
  onFrom,
  onTo,
  onType,
  onClear,
  onRetry,
  onRecord,
  onPage,
}: {
  rows: IncomeRow[] | null
  meta: PageMeta | null
  totals: FinanceTotal[]
  error: string
  from: string
  to: string
  type: string
  canRecordSale: boolean
  onFrom: (value: string) => void
  onTo: (value: string) => void
  onType: (value: string) => void
  onClear: () => void
  onRetry: () => void
  onRecord: () => void
  onPage: (page: number) => void
}) {
  const filtered = hasFilters(from, to, type)
  const fallback = rows ? aggregateTotals(rows, (row) => row.type, (row) => row.amount) : []
  const grouped = totals.length > 0 ? totals : fallback
  const shown = grouped.filter((item) => Number(item.amount) !== 0)
  const total = grouped.reduce((sum, item) => sum + Number(item.amount), 0)

  return (
    <div className="flex flex-col gap-4">
      <div className="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,10rem)_minmax(0,10rem)_minmax(0,14rem)]">
        <TextField name="from" label="From" type="date" value={from} max={to} onChange={(event) => onFrom(event.target.value)} />
        <TextField name="to" label="To" type="date" value={to} min={from} onChange={(event) => onTo(event.target.value)} />
        <Choice name="type" label="Income type" value={type} choices={incomeTypeChoices} onChange={onType} />
      </div>

      {error ? (
        <Alert variant="error">
          <AlertTitle>Income could not be loaded</AlertTitle>
          <AlertDescription>{error}</AlertDescription>
          <AlertAction>
            <Button size="xs" variant="outline" onClick={onRetry}>
              Try again
            </Button>
          </AlertAction>
        </Alert>
      ) : rows === null ? (
        <LoadingRows label="Loading income" />
      ) : rows.length === 0 ? (
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>{filtered ? 'No income matches these filters' : 'No income in this range'}</EmptyTitle>
            <EmptyDescription>
              {filtered
                ? 'Change the type or date range to see other income.'
                : 'Membership, session and online payments appear here on their own. Desk sales you record appear here too.'}
            </EmptyDescription>
          </EmptyHeader>
          {(filtered || canRecordSale) && (
            <EmptyContent>
              {filtered ? (
                <Button variant="outline" onClick={onClear}>
                  Clear filters
                </Button>
              ) : (
                <Button onClick={onRecord}>
                  <Plus aria-hidden />
                  Record sale
                </Button>
              )}
            </EmptyContent>
          )}
        </Empty>
      ) : (
        <>
          <FinanceTotals
            label="Income by type"
            total={total}
            items={shown.map((item) => ({ key: item.key, label: incomeTypeLabel(item.key), amount: item.amount }))}
          />

          <p className="text-muted-foreground text-sm" aria-live="polite">
            {rows.length} {rows.length === 1 ? 'entry' : 'entries'} on this page, newest first
          </p>

          <ul className="flex flex-col divide-y rounded-xl border bg-card md:hidden">
            {rows.map((row) => (
              <li key={row.id} className="flex min-w-0 flex-col gap-2 p-4">
                <div className="flex items-start justify-between gap-3">
                  <div className="flex min-w-0 flex-col gap-1">
                    <span className="break-words font-medium">{incomeParty(row)}</span>
                    <time className="text-muted-foreground text-sm tabular-nums" dateTime={row.paid_at}>
                      {dateLabel(row.paid_at)}
                    </time>
                  </div>
                  <span
                    className={cn(
                      'shrink-0 font-semibold tabular-nums',
                      Number(row.amount) < 0 && 'text-destructive',
                    )}
                  >
                    {money(row.amount)}
                  </span>
                </div>
                <div className="flex flex-wrap gap-1.5">
                  <Badge variant="outline">{incomeTypeLabel(row.type)}</Badge>
                  <Badge variant="secondary">{methodLabel(row.method)}</Badge>
                  {row.corrects_id !== null && <Badge variant="warning">Corrects #{row.corrects_id}</Badge>}
                </div>
                <div className="flex flex-wrap justify-between gap-2 text-muted-foreground text-xs">
                  <span className="font-mono">{row.receipt_number ?? 'No receipt number'}</span>
                  {row.note && <span className="max-w-full break-words">{row.note}</span>}
                </div>
              </li>
            ))}
          </ul>

          <div className="hidden min-w-0 overflow-x-auto md:block">
            <Table variant="card" className="min-w-[46rem]">
              <TableHeader>
                <TableRow>
                  <TableHead>Date</TableHead>
                  <TableHead>Type</TableHead>
                  <TableHead>Member or payer</TableHead>
                  <TableHead>Method</TableHead>
                  <TableHead>Receipt</TableHead>
                  <TableHead className="text-right">Amount</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((row) => (
                  <TableRow key={row.id}>
                    <TableCell>
                      <time dateTime={row.paid_at}>{dateLabel(row.paid_at)}</time>
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-col items-start gap-1">
                        <Badge variant="outline">{incomeTypeLabel(row.type)}</Badge>
                        {row.corrects_id !== null && <Badge variant="warning">Corrects #{row.corrects_id}</Badge>}
                      </div>
                    </TableCell>
                    <TableCell className="max-w-64 whitespace-normal">
                      <span className="block break-words font-medium">{incomeParty(row)}</span>
                      {row.note && (
                        <span className="mt-0.5 block break-words text-muted-foreground text-xs">{row.note}</span>
                      )}
                    </TableCell>
                    <TableCell>{methodLabel(row.method)}</TableCell>
                    <TableCell>
                      {row.receipt_number ? (
                        <span className="font-mono text-xs">{row.receipt_number}</span>
                      ) : (
                        <span className="text-muted-foreground">None</span>
                      )}
                    </TableCell>
                    <TableCell
                      className={cn(
                        'text-right font-medium tabular-nums',
                        Number(row.amount) < 0 && 'text-destructive',
                      )}
                    >
                      {money(row.amount)}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>

          <PageControls meta={meta} label="Income" onPage={onPage} />
        </>
      )}
    </div>
  )
}

function ExpensePanel({
  rows,
  meta,
  totals,
  error,
  from,
  to,
  category,
  canManage,
  onFrom,
  onTo,
  onCategory,
  onClear,
  onRetry,
  onAdd,
  onCorrect,
  onPage,
}: {
  rows: Expense[] | null
  meta: PageMeta | null
  totals: FinanceTotal[]
  error: string
  from: string
  to: string
  category: string
  canManage: boolean
  onFrom: (value: string) => void
  onTo: (value: string) => void
  onCategory: (value: string) => void
  onClear: () => void
  onRetry: () => void
  onAdd: () => void
  onCorrect: (expense: Expense) => void
  onPage: (page: number) => void
}) {
  const filtered = hasFilters(from, to, category)
  const fallback = rows ? aggregateTotals(rows, (row) => row.category, (row) => row.amount) : []
  const grouped = totals.length > 0 ? totals : fallback
  const shown = grouped.filter((item) => Number(item.amount) !== 0)
  const total = grouped.reduce((sum, item) => sum + Number(item.amount), 0)

  return (
    <div className="flex flex-col gap-4">
      <div className="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,10rem)_minmax(0,10rem)_minmax(0,14rem)]">
        <TextField name="from" label="From" type="date" value={from} max={to} onChange={(event) => onFrom(event.target.value)} />
        <TextField name="to" label="To" type="date" value={to} min={from} onChange={(event) => onTo(event.target.value)} />
        <Choice
          name="category"
          label="Expense category"
          value={category}
          choices={expenseCategoryFilterChoices}
          onChange={onCategory}
        />
      </div>

      {error ? (
        <Alert variant="error">
          <AlertTitle>Expenses could not be loaded</AlertTitle>
          <AlertDescription>{error}</AlertDescription>
          <AlertAction>
            <Button size="xs" variant="outline" onClick={onRetry}>
              Try again
            </Button>
          </AlertAction>
        </Alert>
      ) : rows === null ? (
        <LoadingRows label="Loading expenses" />
      ) : rows.length === 0 ? (
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>{filtered ? 'No expenses match these filters' : 'No expenses in this range'}</EmptyTitle>
            <EmptyDescription>
              {filtered
                ? 'Change the category or date range to see other expenses.'
                : 'Record rent, utilities, supplies and other costs here. Payroll and purchase costs post here automatically later.'}
            </EmptyDescription>
          </EmptyHeader>
          {(filtered || canManage) && (
            <EmptyContent>
              {filtered ? (
                <Button variant="outline" onClick={onClear}>
                  Clear filters
                </Button>
              ) : (
                <Button onClick={onAdd}>
                  <Plus aria-hidden />
                  Add expense
                </Button>
              )}
            </EmptyContent>
          )}
        </Empty>
      ) : (
        <>
          <FinanceTotals
            label="Expenses by category"
            total={total}
            items={shown.map((item) => ({
              key: item.key,
              label: expenseCategoryLabel(item.key),
              amount: item.amount,
            }))}
          />

          <p className="text-muted-foreground text-sm" aria-live="polite">
            {rows.length} {rows.length === 1 ? 'entry' : 'entries'} on this page, newest first
          </p>

          <ul className="flex flex-col divide-y rounded-xl border bg-card md:hidden">
            {rows.map((row) => {
              const source = sourceTypeLabel(row.source_type)
              const corrected = hasExpenseCorrection(row, rows)
              return (
                <li key={row.id} className="flex min-w-0 flex-col gap-3 p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex min-w-0 flex-col gap-1">
                      <span className="break-words font-medium">{expenseCategoryLabel(row.category)}</span>
                      <time className="text-muted-foreground text-sm tabular-nums" dateTime={row.incurred_on}>
                        {dateLabel(row.incurred_on)}
                      </time>
                    </div>
                    <span
                      className={cn(
                        'shrink-0 font-semibold tabular-nums',
                        Number(row.amount) < 0 && 'text-destructive',
                      )}
                    >
                      {money(row.amount)}
                    </span>
                  </div>
                  <div className="flex flex-wrap gap-1.5">
                    <Badge variant="secondary">{methodLabel(row.method)}</Badge>
                    {source && <Badge variant="info">{source}</Badge>}
                    {row.corrects_id !== null && <Badge variant="warning">Corrects #{row.corrects_id}</Badge>}
                    {corrected && <Badge variant="success">Corrected</Badge>}
                  </div>
                  {row.note && <p className="break-words text-sm">{row.note}</p>}
                  <p className="text-muted-foreground text-xs">
                    Recorded by {row.recorded_by_name ?? 'an unknown person'}
                  </p>
                  {canManage && correctableExpense(row, rows) && (
                    <Button size="sm" variant="outline" className="self-start" onClick={() => onCorrect(row)}>
                      Correct
                    </Button>
                  )}
                </li>
              )
            })}
          </ul>

          <div className="hidden min-w-0 overflow-x-auto md:block">
            <Table variant="card" className="min-w-[48rem]">
              <TableHeader>
                <TableRow>
                  <TableHead>Date</TableHead>
                  <TableHead>Category</TableHead>
                  <TableHead className="text-right">Amount</TableHead>
                  <TableHead>Method</TableHead>
                  <TableHead>Note</TableHead>
                  <TableHead>
                    <span className="sr-only">Actions</span>
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((row) => {
                  const source = sourceTypeLabel(row.source_type)
                  const corrected = hasExpenseCorrection(row, rows)
                  return (
                    <TableRow key={row.id}>
                      <TableCell>
                        <time dateTime={row.incurred_on}>{dateLabel(row.incurred_on)}</time>
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-col items-start gap-1">
                          <span>{expenseCategoryLabel(row.category)}</span>
                          <div className="flex flex-wrap gap-1">
                            {source && <Badge variant="info">{source}</Badge>}
                            {row.corrects_id !== null && <Badge variant="warning">Corrects #{row.corrects_id}</Badge>}
                            {corrected && <Badge variant="success">Corrected</Badge>}
                          </div>
                        </div>
                      </TableCell>
                      <TableCell
                        className={cn(
                          'text-right font-medium tabular-nums',
                          Number(row.amount) < 0 && 'text-destructive',
                        )}
                      >
                        {money(row.amount)}
                      </TableCell>
                      <TableCell>{methodLabel(row.method)}</TableCell>
                      <TableCell className="max-w-64 whitespace-normal">
                        {row.note ? (
                          <span className="line-clamp-2 break-words">{row.note}</span>
                        ) : (
                          <span className="text-muted-foreground">No note</span>
                        )}
                        <span className="mt-0.5 block text-muted-foreground text-xs">
                          {row.recorded_by_name ?? 'Unknown person'}
                        </span>
                      </TableCell>
                      <TableCell>
                        <div className="flex justify-end">
                          {canManage && correctableExpense(row, rows) && (
                            <Button
                              size="xs"
                              variant="ghost"
                              aria-label={`Correct ${expenseCategoryLabel(row.category)} on ${dateLabel(row.incurred_on)}`}
                              onClick={() => onCorrect(row)}
                            >
                              Correct
                            </Button>
                          )}
                        </div>
                      </TableCell>
                    </TableRow>
                  )
                })}
              </TableBody>
            </Table>
          </div>

          <PageControls meta={meta} label="Expenses" onPage={onPage} />
        </>
      )}
    </div>
  )
}

function ReceivablePanel({
  rows,
  error,
  onRetry,
}: {
  rows: Receivable[] | null
  error: string
  onRetry: () => void
}) {
  const [openId, setOpenId] = useState<number | null>(null)

  if (error) {
    return (
      <Alert variant="error">
        <AlertTitle>Member balances could not be loaded</AlertTitle>
        <AlertDescription>{error}</AlertDescription>
        <AlertAction>
          <Button size="xs" variant="outline" onClick={onRetry}>
            Try again
          </Button>
        </AlertAction>
      </Alert>
    )
  }

  if (rows === null) return <LoadingRows label="Loading member balances" />

  if (rows.length === 0) {
    return (
      <Empty className="rounded-xl border">
        <EmptyHeader>
          <EmptyTitle>No outstanding balances</EmptyTitle>
          <EmptyDescription>
            Every membership and session add-on is paid. Unpaid sessions and private coaching fees will appear here.
          </EmptyDescription>
        </EmptyHeader>
      </Empty>
    )
  }

  const total = rows.reduce((sum, row) => sum + Number(row.total), 0)

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <p className="text-muted-foreground text-sm" aria-live="polite">
          {rows.length} {rows.length === 1 ? 'member owes' : 'members owe'} money
        </p>
        <p className="text-sm">
          Total <strong className="tabular-nums">{money(total)}</strong>
        </p>
      </div>

      <ul className="flex flex-col divide-y rounded-xl border bg-card md:hidden">
        {rows.map((row) => (
          <li key={row.user_id} className="flex min-w-0 flex-col gap-3 p-4">
            <div className="flex items-start justify-between gap-3">
              <span className="min-w-0 break-words font-medium">{row.member_name || `Member #${row.user_id}`}</span>
              <span className="shrink-0 font-semibold tabular-nums">{money(row.total)}</span>
            </div>
            <Button
              size="sm"
              variant="ghost"
              className="self-start"
              aria-expanded={openId === row.user_id}
              aria-controls={`receivable-sources-mobile-${row.user_id}`}
              onClick={() => setOpenId(openId === row.user_id ? null : row.user_id)}
            >
              {openId === row.user_id ? 'Hide sources' : `Sources (${row.sources?.length ?? 0})`}
            </Button>
            {openId === row.user_id && (
              <div id={`receivable-sources-mobile-${row.user_id}`}>
                <SourceList sources={row.sources ?? []} />
              </div>
            )}
          </li>
        ))}
      </ul>

      <div className="hidden min-w-0 overflow-x-auto md:block">
        <Table variant="card" className="min-w-[36rem]">
          <TableHeader>
            <TableRow>
              <TableHead>Member</TableHead>
              <TableHead className="text-right">Outstanding</TableHead>
              <TableHead>
                <span className="sr-only">Sources</span>
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((row) => (
              <Fragment key={row.user_id}>
                <TableRow>
                  <TableCell className="font-medium">{row.member_name || `Member #${row.user_id}`}</TableCell>
                  <TableCell className="text-right font-semibold tabular-nums">{money(row.total)}</TableCell>
                  <TableCell>
                    <div className="flex justify-end">
                      <Button
                        size="xs"
                        variant="ghost"
                        aria-expanded={openId === row.user_id}
                        aria-controls={`receivable-sources-${row.user_id}`}
                        onClick={() => setOpenId(openId === row.user_id ? null : row.user_id)}
                      >
                        {openId === row.user_id ? 'Hide sources' : `Sources (${row.sources?.length ?? 0})`}
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
                {openId === row.user_id && (
                  <TableRow>
                    <TableCell colSpan={3} className="whitespace-normal bg-muted/40 p-3">
                      <div id={`receivable-sources-${row.user_id}`}>
                        <SourceList sources={row.sources ?? []} />
                      </div>
                    </TableCell>
                  </TableRow>
                )}
              </Fragment>
            ))}
          </TableBody>
        </Table>
      </div>
    </div>
  )
}

function SourceList({ sources }: { sources: ReceivableSource[] }) {
  if (sources.length === 0) {
    return <p className="text-muted-foreground text-sm">No source lines were returned for this balance.</p>
  }
  return (
    <ul className="flex flex-col divide-y rounded-lg border bg-card">
      {sources.map((source, index) => (
        <li
          key={`${source.kind}-${source.id ?? 'unknown'}-${index}`}
          className="flex flex-wrap items-baseline justify-between gap-2 px-3 py-2 text-sm"
        >
          <span className="min-w-0 break-words">{receivableSourceLabel(source)}</span>
          <span className="font-medium tabular-nums">{money(source.amount)}</span>
        </li>
      ))}
    </ul>
  )
}

type SupplierPayableResponse = {
  supplier?: ProcurementSupplier | null
  orders?: PayableOrder[] | null
  payments?: SupplierPayment[] | null
  received?: string | null
  paid?: string | null
  outstanding?: string | null
}

type SupplierPayableDetail = {
  supplier: ProcurementSupplier
  orders: PayableOrder[]
  payments: SupplierPayment[]
  received: string
  paid: string
  outstanding: string
}

function fallbackSupplier(row: Payable): ProcurementSupplier {
  return {
    id: row.supplier_id,
    name: row.supplier_name,
    contact_person: null,
    phone: null,
    email: null,
    address: null,
    payment_terms: null,
    notes: null,
    status: 'active',
  }
}

function PayablesPanel({
  gymId,
  rows,
  error,
  canManage,
  canViewPurchaseOrders,
  onRetry,
  onPaid,
}: {
  gymId: number
  rows: Payable[] | null
  error: string
  canManage: boolean
  canViewPurchaseOrders: boolean
  onRetry: () => void
  onPaid: () => void
}) {
  const [detailOf, setDetailOf] = useState<Payable | null>(null)
  const [paying, setPaying] = useState<PayTarget | null>(null)

  if (error) {
    return (
      <Alert variant="error">
        <AlertTitle>Supplier payables could not be loaded</AlertTitle>
        <AlertDescription>{error}</AlertDescription>
        <AlertAction>
          <Button size="xs" variant="outline" onClick={onRetry}>
            Try again
          </Button>
        </AlertAction>
      </Alert>
    )
  }
  if (rows === null) return <LoadingRows label="Loading supplier payables" />
  if (rows.length === 0) {
    return (
      <Empty className="rounded-xl border">
        <EmptyHeader>
          <EmptyTitle>No supplier payables</EmptyTitle>
          <EmptyDescription>
            Received goods create a payable for the supplier. Payments you record lower it and appear as cash out in expenses.
          </EmptyDescription>
        </EmptyHeader>
      </Empty>
    )
  }

  const total = rows.reduce((sum, row) => sum + Number(row.outstanding ?? 0), 0)
  const toPay = rows.filter((row) => Number(row.outstanding) > 0)

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <p className="text-muted-foreground text-sm" aria-live="polite">
          {toPay.length} {toPay.length === 1 ? 'supplier has' : 'suppliers have'} money outstanding
        </p>
        <p className="text-sm">
          Total owed <strong className="tabular-nums">{money(total)}</strong>
        </p>
      </div>

      <ul className="flex flex-col divide-y rounded-xl border bg-card md:hidden">
        {rows.map((row) => (
          <li key={row.supplier_id} className="flex min-w-0 flex-col gap-3 p-4">
            <div className="flex items-start justify-between gap-3">
              <button
                type="button"
                className="min-w-0 break-words text-left font-medium underline underline-offset-4"
                onClick={() => setDetailOf(row)}
              >
                {row.supplier_name}
              </button>
              <span className="shrink-0 font-semibold tabular-nums">{money(row.outstanding)}</span>
            </div>
            <dl className="grid grid-cols-2 gap-3 text-sm">
              <div>
                <dt className="text-muted-foreground">Received</dt>
                <dd className="tabular-nums">{money(row.received)}</dd>
              </div>
              <div>
                <dt className="text-muted-foreground">Paid</dt>
                <dd className="tabular-nums">{money(row.paid)}</dd>
              </div>
            </dl>
            <div className="flex flex-wrap gap-2">
              <Button size="sm" variant="outline" onClick={() => setDetailOf(row)}>
                View account
              </Button>
              {canManage && Number(row.outstanding) > 0 && (
                <Button
                  size="sm"
                  onClick={() =>
                    setPaying({ supplierId: row.supplier_id, supplierName: row.supplier_name, outstanding: row.outstanding })
                  }
                >
                  Pay supplier
                </Button>
              )}
            </div>
          </li>
        ))}
      </ul>

      <div className="hidden min-w-0 overflow-x-auto md:block">
        <Table variant="card" className="min-w-[44rem]">
          <TableHeader>
            <TableRow>
              <TableHead>Supplier</TableHead>
              <TableHead className="text-right">Received</TableHead>
              <TableHead className="text-right">Paid</TableHead>
              <TableHead className="text-right">Outstanding</TableHead>
              <TableHead>
                <span className="sr-only">Actions</span>
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((row) => (
              <TableRow key={row.supplier_id}>
                <TableCell className="font-medium">
                  <button
                    type="button"
                    className="max-w-56 break-words text-left underline underline-offset-4"
                    onClick={() => setDetailOf(row)}
                  >
                    {row.supplier_name}
                  </button>
                </TableCell>
                <TableCell className="text-right tabular-nums">{money(row.received)}</TableCell>
                <TableCell className="text-right tabular-nums">{money(row.paid)}</TableCell>
                <TableCell className="text-right font-semibold tabular-nums">{money(row.outstanding)}</TableCell>
                <TableCell>
                  <div className="flex justify-end gap-1">
                    <Button size="xs" variant="ghost" onClick={() => setDetailOf(row)}>
                      View
                    </Button>
                    {canManage && Number(row.outstanding) > 0 && (
                      <Button
                        size="xs"
                        variant="outline"
                        onClick={() =>
                          setPaying({ supplierId: row.supplier_id, supplierName: row.supplier_name, outstanding: row.outstanding })
                        }
                      >
                        Pay
                      </Button>
                    )}
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      {detailOf && (
        <SupplierPayableDialog
          gymId={gymId}
          row={detailOf}
          canManage={canManage}
          canViewPurchaseOrders={canViewPurchaseOrders}
          onClose={() => setDetailOf(null)}
          onPay={(target) => {
            setDetailOf(null)
            setPaying(target)
          }}
        />
      )}

      {paying && (
        <PaySupplierDialog
          gymId={gymId}
          target={paying}
          onClose={() => setPaying(null)}
          onSaved={() => {
            setPaying(null)
            onPaid()
          }}
        />
      )}
    </div>
  )
}

function SupplierPayableDialog({
  gymId,
  row,
  canManage,
  canViewPurchaseOrders,
  onClose,
  onPay,
}: {
  gymId: number
  row: Payable
  canManage: boolean
  canViewPurchaseOrders: boolean
  onClose: () => void
  onPay: (target: PayTarget) => void
}) {
  const [detail, setDetail] = useState<SupplierPayableDetail | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)

  useEffect(() => {
    let live = true
    setDetail(null)
    setError('')
    api<SupplierPayableResponse>(`/gyms/${gymId}/finance/payables/${row.supplier_id}`)
      .then((payload) => {
        if (!live) return
        setDetail({
          supplier: payload.supplier ?? fallbackSupplier(row),
          orders: Array.isArray(payload.orders) ? payload.orders : [],
          payments: Array.isArray(payload.payments) ? payload.payments : [],
          received: payload.received ?? row.received,
          paid: payload.paid ?? row.paid,
          outstanding: payload.outstanding ?? row.outstanding,
        })
      })
      .catch((reason) => {
        if (live) setError(message(reason, 'The supplier account could not be loaded.'))
      })
    return () => {
      live = false
    }
  }, [gymId, row, version])

  const outstanding = detail?.outstanding ?? row.outstanding
  const received = detail?.received ?? row.received
  const paid = detail?.paid ?? row.paid

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogPopup className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle className="break-words">{detail?.supplier.name ?? row.supplier_name}</DialogTitle>
          <DialogDescription>What was received, what was paid, and the orders behind the balance.</DialogDescription>
        </DialogHeader>

        <DialogPanel className="flex flex-col gap-5">
          <dl className="grid grid-cols-3 gap-px overflow-hidden rounded-xl border bg-border">
            {[
              { key: 'received', label: 'Received', value: received },
              { key: 'paid', label: 'Paid', value: paid },
              { key: 'outstanding', label: 'Outstanding', value: outstanding },
            ].map((cell) => (
              <div key={cell.key} className="bg-card px-3 py-2.5">
                <dt className="text-muted-foreground text-xs">{cell.label}</dt>
                <dd className="mt-1 font-semibold tabular-nums">{money(cell.value)}</dd>
              </div>
            ))}
          </dl>

          {detail &&
            (detail.supplier.contact_person || detail.supplier.phone || detail.supplier.email) && (
              <p className="break-words text-sm">
                {[detail.supplier.contact_person, detail.supplier.phone, detail.supplier.email].filter(Boolean).join(' · ')}
              </p>
            )}

          {error && (
            <Alert variant="error">
              <AlertTitle>The supplier account could not be loaded</AlertTitle>
              <AlertDescription>{error}</AlertDescription>
              <AlertAction>
                <Button size="xs" variant="outline" onClick={() => setVersion((value) => value + 1)}>
                  Try again
                </Button>
              </AlertAction>
            </Alert>
          )}

          {!error && detail === null && <LoadingRows label="Loading supplier account" />}

          {detail && (
            <>
              <section aria-labelledby="payable-orders-heading" className="flex flex-col gap-2">
                <h2 id="payable-orders-heading" className="font-medium text-sm">
                  Orders
                </h2>
                {detail.orders.length === 0 ? (
                  <p className="text-muted-foreground text-sm">No purchase orders were returned for this supplier.</p>
                ) : (
                  <ul className="flex flex-col divide-y rounded-xl border bg-card">
                    {detail.orders.map((order) => {
                      const status = purchaseOrderStatusMeta(order.status)
                      return (
                        <li key={order.id} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5 text-sm">
                          <div className="min-w-0">
                            {canViewPurchaseOrders ? (
                              <Link
                                className="break-words font-medium underline underline-offset-4"
                                to={`/gyms/${gymId}/manage/purchasing/orders/${order.id}`}
                              >
                                {order.number}
                              </Link>
                            ) : (
                              <span className="break-words font-medium">{order.number}</span>
                            )}
                            <p className="text-muted-foreground text-xs">
                              {order.expected_on ? `Expected ${dateLabel(order.expected_on)}` : 'No expected date'}
                            </p>
                          </div>
                          <div className="flex flex-wrap items-center gap-2">
                            <Badge variant={status.variant}>{status.label}</Badge>
                            <span className="tabular-nums">{money(order.total)}</span>
                          </div>
                        </li>
                      )
                    })}
                  </ul>
                )}
              </section>

              <section aria-labelledby="payable-payments-heading" className="flex flex-col gap-2">
                <h2 id="payable-payments-heading" className="font-medium text-sm">
                  Payments
                </h2>
                {detail.payments.length === 0 ? (
                  <p className="text-muted-foreground text-sm">No supplier payments recorded yet.</p>
                ) : (
                  <ul className="flex flex-col divide-y rounded-xl border bg-card">
                    {detail.payments.map((payment) => (
                      <li key={payment.id} className="flex flex-col gap-1 px-3 py-2.5 text-sm">
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                          <span className="font-medium">
                            {dateLabel(payment.paid_on)} · {supplierPaymentMethodLabel(payment.method)}
                          </span>
                          <span className={cn('font-semibold tabular-nums', Number(payment.amount) < 0 && 'text-destructive')}>
                            {money(payment.amount)}
                          </span>
                        </div>
                        {payment.corrects_id !== null && payment.corrects_id !== undefined && (
                          <Badge variant="warning" className="self-start">
                            Corrects #{payment.corrects_id}
                          </Badge>
                        )}
                        {(payment.reference || payment.note) && (
                          <p className="break-words text-muted-foreground text-xs">
                            {[payment.reference, payment.note].filter(Boolean).join(' · ')}
                          </p>
                        )}
                        {payment.recorded_by_name && (
                          <p className="text-muted-foreground text-xs">Recorded by {payment.recorded_by_name}</p>
                        )}
                      </li>
                    ))}
                  </ul>
                )}
              </section>
            </>
          )}
        </DialogPanel>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Close
          </Button>
          {canManage && Number(outstanding) > 0 && (
            <Button
              onClick={() =>
                onPay({
                  supplierId: row.supplier_id,
                  supplierName: detail?.supplier.name ?? row.supplier_name,
                  outstanding,
                })
              }
            >
              Pay supplier
            </Button>
          )}
        </DialogFooter>
      </DialogPopup>
    </Dialog>
  )
}

function PaySupplierDialog({
  gymId,
  target,
  onClose,
  onSaved,
}: {
  gymId: number
  target: PayTarget
  onClose: () => void
  onSaved: () => void
}) {
  const [amount, setAmount] = useState(target.outstanding)
  const [method, setMethod] = useState('cash')
  const [paidOn, setPaidOn] = useState(todayIso)
  const [reference, setReference] = useState('')
  const [note, setNote] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  const outstanding = Number(target.outstanding)
  const amountNumber = Number(amount)
  const amountValid = amount.trim() !== '' && Number.isFinite(amountNumber) && amountNumber > 0
  const overOutstanding = amountValid && amountNumber > outstanding + 0.001
  const blocked = busy || !amountValid || overOutstanding || !paidOn || method === ''

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (blocked) return
    setBusy(true)
    setErrors({})
    try {
      await api(`/gyms/${gymId}/finance/supplier-payments`, {
        body: {
          supplier_id: target.supplierId,
          amount: amountNumber,
          method,
          paid_on: paidOn,
          reference: reference.trim() || null,
          note: note.trim() || null,
        },
      })
      toastManager.add({ title: 'Supplier payment recorded.', type: 'success' })
      onSaved()
    } catch (reason) {
      setErrors(
        formErrors(
          reason,
          ['supplier_id', 'amount', 'method', 'paid_on', 'reference', 'note'],
          'The supplier payment could not be recorded.',
        ),
      )
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Pay a supplier</DialogTitle>
            <DialogDescription>
              {target.supplierName}: {money(outstanding)} outstanding. A payment lowers the payable and appears as cash out; it does not post a second expense.
            </DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField
                name="amount"
                label="Amount (₱)"
                type="number"
                inputMode="decimal"
                min="0.01"
                max={outstanding > 0 ? outstanding : undefined}
                step="0.01"
                value={amount}
                onChange={(event) => setAmount(event.target.value)}
                required
                autoFocus
                description={`Cannot be more than ${money(outstanding)}.`}
              />
              <TextField
                name="paid_on"
                label="Paid on"
                type="date"
                value={paidOn}
                onChange={(event) => setPaidOn(event.target.value)}
                required
              />
            </div>
            <Choice
              name="method"
              label="Paid by"
              value={method}
              choices={supplierPaymentMethods}
              onChange={setMethod}
              required
            />
            <TextField
              name="reference"
              label="Reference (optional)"
              value={reference}
              onChange={(event) => setReference(event.target.value)}
              maxLength={80}
              placeholder="Cheque or transfer number"
            />
            <Field name="note">
              <FieldLabel>
                Note <span className="font-normal text-muted-foreground">(optional)</span>
              </FieldLabel>
              <Textarea
                name="note"
                rows={2}
                maxLength={255}
                value={note}
                onChange={(event) => setNote(event.target.value)}
              />
              <FieldError />
            </Field>
            {overOutstanding && <p className="text-destructive text-sm">That is more than the outstanding amount.</p>}
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={blocked}>
              Record payment
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function RecordSaleDialog({
  gymId,
  onClose,
  onSaved,
}: {
  gymId: number
  onClose: () => void
  onSaved: () => void
}) {
  const [type, setType] = useState<IncomeType>('walk_in')
  const [buyerMode, setBuyerMode] = useState<'guest' | 'member'>('guest')
  const [payerName, setPayerName] = useState('')
  const [memberId, setMemberId] = useState('')
  const [method, setMethod] = useState<DeskMethod>('cash')
  const [amount, setAmount] = useState('')
  const [discount, setDiscount] = useState('')
  const [note, setNote] = useState('')
  const [lines, setLines] = useState<SaleLine[]>([])
  const [picker, setPicker] = useState('')
  const [members, setMembers] = useState<MemberChoice[] | null>(null)
  const [membersError, setMembersError] = useState('')
  const [stock, setStock] = useState<StockItem[] | null>(null)
  const [stockError, setStockError] = useState('')
  const [choicesVersion, setChoicesVersion] = useState(0)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let live = true
    setMembers(null)
    setMembersError('')
    setStock(null)
    setStockError('')

    api<{ memberships: Membership[] }>(`/gyms/${gymId}/memberships?view=members`)
      .then((result) => {
        if (!live) return
        const people = new Map<number, string>()
        for (const membership of result.memberships) {
          if (membership.member && !people.has(membership.member.id)) {
            people.set(membership.member.id, membership.member.name)
          }
        }
        setMembers(
          [...people.entries()]
            .map(([id, name]) => ({ id, name }))
            .sort((a, b) => a.name.localeCompare(b.name)),
        )
      })
      .catch((reason) => {
        if (!live) return
        setMembers([])
        setMembersError(
          reason instanceof ApiError && reason.status === 403
            ? 'Member choices need the membership permission. Record the buyer name instead.'
            : message(reason, 'Member choices could not be loaded. Record a buyer name instead.'),
        )
      })

    api<{ items: StockItem[] }>(`/gyms/${gymId}/stock-items?category=merchandise`)
      .then((result) => {
        if (!live) return
        setStock(
          result.items.filter(
            (item) =>
              item.category === 'merchandise' &&
              item.status === 'active' &&
              item.quantity > 0 &&
              item.sale_price !== null &&
              Number(item.sale_price) > 0,
          ),
        )
      })
      .catch((reason) => {
        if (!live) return
        setStock([])
        setStockError(
          reason instanceof ApiError && reason.status === 403
            ? 'Selling merchandise needs the stock permission. Ask the owner, or record a walk-in or other sale.'
            : message(reason, 'Merchandise stock could not be loaded.'),
        )
      })

    return () => {
      live = false
    }
  }, [gymId, choicesVersion])

  const selectedMember = members?.find((person) => String(person.id) === memberId)
  const selectedStock = new Set(lines.map((line) => line.stockItemId))
  const availableItems = (stock ?? []).filter((item) => !selectedStock.has(item.id))
  const lineViews = lines.map((line) => {
    const item = stock?.find((stockItem) => stockItem.id === line.stockItemId)
    const quantity = Number(line.quantity)
    const quantityValid =
      line.quantity !== '' && Number.isInteger(quantity) && quantity > 0 && (!item || quantity <= item.quantity)
    const price = item && item.sale_price !== null ? Number(item.sale_price) : Number.NaN
    const priceValid = Number.isFinite(price) && price > 0
    const total = quantityValid && priceValid ? (Math.round(price * 100) * quantity) / 100 : 0
    return {
      line,
      item,
      quantity,
      quantityValid,
      price,
      priceValid,
      total,
      remaining: item ? item.quantity - quantity : null,
    }
  })
  const linesValid =
    lines.length > 0 &&
    lineViews.every((view) => view.item && view.quantityValid && view.priceValid)
  const merchandiseTotal = lineViews.reduce((sum, view) => sum + view.total, 0)
  const enteredAmount = amount.trim() === '' ? Number.NaN : Number(amount)
  const gross = type === 'merchandise' ? merchandiseTotal : enteredAmount
  const grossValid = Number.isFinite(gross) && gross > 0
  const discountNumber = discount.trim() === '' ? 0 : Number(discount)
  const discountValid = Number.isFinite(discountNumber) && discountNumber >= 0
  const discountTooLarge = discountValid && grossValid && discountNumber > gross
  const total = grossValid && discountValid ? gross - discountNumber : 0
  const buyerValid = buyerMode === 'member' ? Boolean(memberId && selectedMember) : payerName.trim() !== ''
  const blocked =
    busy ||
    !buyerValid ||
    !grossValid ||
    !discountValid ||
    discountTooLarge ||
    (type === 'merchandise' && (stock === null || !linesValid))
  const itemErrors = nestedErrors(errors, 'items')

  function updateLine(stockItemId: number, patch: Partial<SaleLine>) {
    setLines((current) => current.map((line) => (line.stockItemId === stockItemId ? { ...line, ...patch } : line)))
  }

  function addLine(value: string) {
    const stockItemId = Number(value)
    const item = stock?.find((stockItem) => stockItem.id === stockItemId)
    if (!item || selectedStock.has(stockItemId)) return
    setLines((current) => [...current, { stockItemId, quantity: '1' }])
    setPicker('')
  }

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (blocked) return
    setBusy(true)
    setErrors({})
    const body: Record<string, unknown> = {
      income_type: type,
      method,
      discount: discountNumber,
      user_id: buyerMode === 'member' ? Number(memberId) : null,
      payer_name: buyerMode === 'member' ? null : payerName.trim(),
      note: note.trim() || null,
    }
    if (type === 'merchandise') {
      body.items = lineViews.map((view) => ({
        stock_item_id: view.item?.id,
        quantity: view.quantity,
      }))
    } else {
      body.amount = enteredAmount
    }

    try {
      const result = await api<{ payment?: { receipt_number?: string | null } }>(`/gyms/${gymId}/finance/sales`, {
        body,
      })
      const receipt = result.payment?.receipt_number
      toastManager.add({
        title: receipt ? `Sale recorded. Receipt ${receipt}.` : 'Sale recorded.',
        type: 'success',
      })
      onSaved()
    } catch (reason) {
      setErrors(
        formErrors(
          reason,
          ['income_type', 'method', 'amount', 'discount', 'user_id', 'payer_name', 'note', 'items'],
          'The sale could not be recorded.',
        ),
      )
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup className="sm:max-w-2xl">
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Record a sale</DialogTitle>
            <DialogDescription>
              A walk-in, merchandise or other desk sale. It becomes income immediately and merchandise lowers stock.
            </DialogDescription>
          </DialogHeader>

          <DialogPanel className="flex flex-col gap-5">
            <Choice
              name="income_type"
              label="Sale type"
              value={type}
              choices={saleTypeChoices}
              onChange={(value) => {
                setType(value as IncomeType)
                setErrors({})
              }}
              required
              description={
                type === 'merchandise'
                  ? 'Choose the items sold below. The stock count falls when you save.'
                  : 'Walk-in and other sales record money without changing stock.'
              }
            />

            <fieldset className="flex flex-col gap-3">
              <legend className="font-medium text-sm">Buyer</legend>
              <div className="flex flex-wrap gap-2" role="group" aria-label="Buyer kind">
                <Button
                  type="button"
                  size="sm"
                  variant={buyerMode === 'guest' ? 'default' : 'outline'}
                  aria-pressed={buyerMode === 'guest'}
                  onClick={() => {
                    setBuyerMode('guest')
                    setErrors({})
                  }}
                >
                  Walk-in / guest
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant={buyerMode === 'member' ? 'default' : 'outline'}
                  aria-pressed={buyerMode === 'member'}
                  disabled={members === null || members.length === 0}
                  onClick={() => {
                    setBuyerMode('member')
                    setErrors({})
                  }}
                >
                  Member
                </Button>
              </div>

              {buyerMode === 'guest' ? (
                <TextField
                  name="payer_name"
                  label="Buyer name"
                  value={payerName}
                  onChange={(event) => setPayerName(event.target.value)}
                  maxLength={150}
                  required
                  autoComplete="name"
                  placeholder="For example: Ana Cruz"
                />
              ) : (
                <Choice
                  name="user_id"
                  label="Member"
                  value={memberId}
                  choices={(members ?? []).map((person) => ({ value: String(person.id), label: person.name }))}
                  onChange={setMemberId}
                  disabled={members === null || (members ?? []).length === 0}
                  required
                  description={
                    members === null
                      ? 'Loading members…'
                      : (members ?? []).length === 0
                        ? 'No members are listed. Record the buyer name instead.'
                        : undefined
                  }
                />
              )}

              {membersError && (
                <Alert variant="warning">
                  <AlertTitle>Member choices unavailable</AlertTitle>
                  <AlertDescription>
                    {membersError}
                    <Button size="xs" variant="outline" className="self-start" onClick={() => setChoicesVersion((value) => value + 1)}>
                      Try again
                    </Button>
                  </AlertDescription>
                </Alert>
              )}
            </fieldset>

            <div className="grid gap-4 sm:grid-cols-2">
              <Choice
                name="method"
                label="Paid by"
                value={method}
                choices={deskMethods}
                onChange={(value) => setMethod(value as DeskMethod)}
                required
              />
              {type !== 'merchandise' && (
                <TextField
                  name="amount"
                  label="Amount before discount (₱)"
                  type="number"
                  inputMode="decimal"
                  min="0.01"
                  max="9999999"
                  step="0.01"
                  value={amount}
                  onChange={(event) => setAmount(event.target.value)}
                  required
                />
              )}
              <TextField
                name="discount"
                label="Discount (₱, optional)"
                type="number"
                inputMode="decimal"
                min="0"
                max="9999999999.99"
                step="0.01"
                value={discount}
                onChange={(event) => setDiscount(event.target.value)}
                description={type === 'merchandise' ? 'Taken off the merchandise total.' : 'Taken off the amount entered above.'}
              />
            </div>

            {type === 'merchandise' && (
              <fieldset className="flex flex-col gap-3">
                <legend className="font-medium text-sm">Merchandise lines</legend>

                {stockError ? (
                  <Alert variant="error">
                    <AlertTitle>Merchandise stock could not be loaded</AlertTitle>
                    <AlertDescription>
                      {stockError}
                      <Button size="xs" variant="outline" className="self-start" onClick={() => setChoicesVersion((value) => value + 1)}>
                        Try again
                      </Button>
                    </AlertDescription>
                  </Alert>
                ) : stock === null ? (
                  <div className="flex flex-col gap-2" aria-busy="true" aria-label="Loading merchandise">
                    <Skeleton className="h-10" />
                    <Skeleton className="h-20" />
                  </div>
                ) : stock.length === 0 ? (
                  <p className="rounded-xl border px-4 py-3 text-muted-foreground text-sm">
                    No active merchandise with a sale price has stock right now. Set a sale price and receive stock first.
                  </p>
                ) : (
                  <>
                    <Choice
                      name="stock_item"
                      label="Add merchandise item"
                      value={picker}
                      choices={availableItems.map((item) => ({
                        value: String(item.id),
                        label: `${item.name} · ${quantityLabel(item.quantity, item.unit)} on hand${
                          item.sale_price === null ? '' : ` · ${money(item.sale_price)}`
                        }`,
                      }))}
                      onChange={addLine}
                      disabled={availableItems.length === 0}
                      description={availableItems.length === 0 ? 'Every item in stock is already on this sale.' : undefined}
                    />
                    <p className="text-muted-foreground text-xs">
                      Only active merchandise with a sale price is listed. Prices come from Stock.
                    </p>

                    {lineViews.length > 0 && (
                      <ul className="flex flex-col gap-3">
                        {lineViews.map((view, index) => {
                          const item = view.item
                          const remaining = view.remaining
                          return (
                            <li key={view.line.stockItemId} className="flex flex-col gap-3 rounded-xl border p-3">
                              <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                  <p className="break-words font-medium">{item?.name ?? 'Item no longer available'}</p>
                                  <p className="text-muted-foreground text-xs">
                                    {item ? `${quantityLabel(item.quantity, item.unit)} on hand` : 'Remove this line and choose again.'}
                                  </p>
                                </div>
                                <Button
                                  type="button"
                                  size="icon-sm"
                                  variant="ghost"
                                  aria-label={`Remove ${item?.name ?? 'merchandise item'}`}
                                  onClick={() => setLines((current) => current.filter((line) => line.stockItemId !== view.line.stockItemId))}
                                >
                                  <X aria-hidden />
                                </Button>
                              </div>

                              <div className="grid gap-3 sm:grid-cols-2">
                                <TextField
                                  name={`items.${index}.quantity`}
                                  label="Quantity"
                                  type="number"
                                  inputMode="numeric"
                                  min={1}
                                  max={Math.min(item?.quantity ?? 1, 100000)}
                                  step={1}
                                  value={view.line.quantity}
                                  onChange={(event) => updateLine(view.line.stockItemId, { quantity: event.target.value })}
                                  required
                                />
                                <div className="flex min-h-14 flex-col justify-center gap-0.5 rounded-lg border bg-muted/30 px-3 py-2">
                                  <span className="text-muted-foreground text-xs">Unit price</span>
                                  <span className="font-medium text-sm tabular-nums">
                                    {item && view.priceValid ? money(view.price) : '—'}
                                  </span>
                                </div>
                              </div>

                              <div className="flex flex-wrap items-baseline justify-between gap-2 text-sm" aria-live="polite">
                                <span>
                                  Line total{' '}
                                  <strong className="tabular-nums">
                                    {view.quantityValid && view.priceValid ? money(view.total) : '—'}
                                  </strong>
                                </span>
                                <span
                                  className={cn(
                                    'tabular-nums',
                                    remaining !== null && remaining < 0 ? 'text-destructive' : 'text-muted-foreground',
                                  )}
                                >
                                  {item && view.quantityValid && remaining !== null
                                    ? remaining < 0
                                      ? 'That is more than you have.'
                                      : `${quantityLabel(remaining, item.unit)} left after this sale`
                                    : 'Enter a quantity to see stock remaining.'}
                                </span>
                              </div>
                              {item && view.line.quantity !== '' && !view.quantityValid && (
                                <p className="text-destructive text-sm">Only {quantityLabel(item.quantity, item.unit)} in stock.</p>
                              )}
                              {!view.priceValid && <p className="text-destructive text-sm">This item has no sale price. Set one in Stock first.</p>}
                              <p className="text-muted-foreground text-xs">The unit price comes from Stock.</p>
                            </li>
                          )
                        })}
                      </ul>
                    )}
                  </>
                )}
              </fieldset>
            )}

            <div className="rounded-xl border bg-muted/40 p-3 text-sm" aria-live="polite">
              <dl className="flex flex-col gap-1.5">
                <div className="flex justify-between gap-3">
                  <dt>{type === 'merchandise' ? 'Items total' : 'Amount'}</dt>
                  <dd className="tabular-nums">{grossValid ? money(gross) : '—'}</dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt>Discount</dt>
                  <dd className="tabular-nums">{discountValid && discountNumber > 0 ? `−${money(discountNumber)}` : money(0)}</dd>
                </div>
                <div className="flex justify-between gap-3 border-t pt-1.5 font-medium">
                  <dt>Total charged</dt>
                  <dd className="tabular-nums">{grossValid ? money(total) : '—'}</dd>
                </div>
              </dl>
              {discountTooLarge && <p className="mt-2 text-destructive">The discount cannot be more than the amount.</p>}
            </div>

            <Field name="note">
              <FieldLabel>
                Note <span className="font-normal text-muted-foreground">(optional)</span>
              </FieldLabel>
              <Textarea
                name="note"
                rows={2}
                maxLength={500}
                value={note}
                onChange={(event) => setNote(event.target.value)}
                placeholder="For example: rash guard and hand wraps."
              />
              <FieldError />
            </Field>

            {itemErrors.length > 0 && (
              <Alert variant="error">
                <AlertTitle>Check the merchandise lines</AlertTitle>
                <AlertDescription>{itemErrors.join(' ')}</AlertDescription>
              </Alert>
            )}
          </DialogPanel>

          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={blocked}>
              Record sale
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function ExpenseDialog({
  gymId,
  onClose,
  onSaved,
}: {
  gymId: number
  onClose: () => void
  onSaved: () => void
}) {
  const [category, setCategory] = useState<ExpenseCategory>('rent')
  const [amount, setAmount] = useState('')
  const [incurredOn, setIncurredOn] = useState(todayIso)
  const [method, setMethod] = useState<ExpenseMethod>('cash')
  const [note, setNote] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)
  const amountValid = amount.trim() !== '' && Number(amount) > 0

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (busy) return
    setBusy(true)
    setErrors({})
    try {
      await api<{ expense?: Expense }>(`/gyms/${gymId}/finance/expenses`, {
        body: {
          category,
          amount: Number(amount),
          incurred_on: incurredOn,
          method,
          note: note.trim() || null,
        },
      })
      toastManager.add({ title: 'Expense recorded.', type: 'success' })
      onSaved()
    } catch (reason) {
      setErrors(
        formErrors(reason, ['category', 'amount', 'incurred_on', 'method', 'note'], 'The expense could not be recorded.'),
      )
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Add expense</DialogTitle>
            <DialogDescription>
              Record a cost with the day it happened. Expenses are never edited; a mistake is fixed with a correction entry.
            </DialogDescription>
          </DialogHeader>

          <DialogPanel className="flex flex-col gap-4">
            <Choice
              name="category"
              label="Category"
              value={category}
              choices={expenseCategoryChoices}
              onChange={(value) => setCategory(value as ExpenseCategory)}
              required
            />
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField
                name="amount"
                label="Amount (₱)"
                type="number"
                inputMode="decimal"
                min="0.01"
                max="9999999999.99"
                step="0.01"
                value={amount}
                onChange={(event) => setAmount(event.target.value)}
                required
                autoFocus
              />
              <TextField
                name="incurred_on"
                label="Date"
                type="date"
                value={incurredOn}
                onChange={(event) => setIncurredOn(event.target.value)}
                required
              />
            </div>
            <Choice
              name="method"
              label="Paid by"
              value={method}
              choices={expenseMethods}
              onChange={(value) => setMethod(value as ExpenseMethod)}
              required
            />
            <Field name="note">
              <FieldLabel>
                Note <span className="font-normal text-muted-foreground">(optional)</span>
              </FieldLabel>
              <Textarea
                name="note"
                rows={3}
                maxLength={255}
                value={note}
                onChange={(event) => setNote(event.target.value)}
                placeholder="For example: October electricity bill."
              />
              <FieldDescription>Kept with the entry and shown in the expense list.</FieldDescription>
              <FieldError />
            </Field>
          </DialogPanel>

          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={!amountValid || !incurredOn}>
              Add expense
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function CorrectExpenseDialog({
  gymId,
  expense,
  onClose,
  onSaved,
}: {
  gymId: number
  expense: Expense
  onClose: () => void
  onSaved: () => void
}) {
  const [reason, setReason] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (busy) return
    setBusy(true)
    setErrors({})
    try {
      await api<{ expense?: Expense }>(`/gyms/${gymId}/finance/expenses/${expense.id}/correct`, {
        body: { reason: reason.trim() },
      })
      toastManager.add({ title: 'Correction recorded.', type: 'success' })
      onSaved()
    } catch (failure) {
      setErrors(formErrors(failure, ['reason'], 'The correction could not be recorded.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Correct this expense?</DialogTitle>
            <DialogDescription>
              {expenseCategoryLabel(expense.category)} on {dateLabel(expense.incurred_on)}. This adds a new entry of{' '}
              {money(-Number(expense.amount))} that cancels the original. The original stays on record.
            </DialogDescription>
          </DialogHeader>

          <DialogPanel className="flex flex-col gap-4">
            <Alert variant="warning">
              <AlertTitle>This cannot be undone</AlertTitle>
              <AlertDescription>
                Record the right amount afterwards as a new expense. The correction is linked to entry #{expense.id}.
              </AlertDescription>
            </Alert>
            <Field name="reason">
              <FieldLabel>Why is it being corrected?</FieldLabel>
              <Textarea
                name="reason"
                rows={3}
                maxLength={200}
                value={reason}
                onChange={(event) => setReason(event.target.value)}
                required
                autoFocus
                placeholder="For example: entered twice, or the wrong category."
              />
              <FieldDescription>Shown with the correction in the expense list.</FieldDescription>
              <FieldError />
            </Field>
          </DialogPanel>

          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Keep it
            </Button>
            <Button type="submit" variant="destructive" loading={busy} disabled={!reason.trim()}>
              Record correction
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

const MAX_BUDGET = 9_999_999_999.99

/** An empty amount means zero; anything else must be a non-negative amount the database can hold. */
function budgetAmount(value: string): number | null {
  if (value.trim() === '') return 0
  const amount = Number(value)
  return Number.isFinite(amount) && amount >= 0 && amount <= MAX_BUDGET ? amount : null
}

function budgetLooksComplete(payload: unknown): boolean {
  if (!payload || typeof payload !== 'object') return false
  const record = payload as Record<string, unknown>
  if ('budgets' in record && 'categories' in record) return true
  const nested = record.data
  if (!nested || typeof nested !== 'object') return false
  const inner = nested as Record<string, unknown>
  return 'budgets' in inner && 'categories' in inner
}

/** R-67: a planned amount per category per month, beside the actual and the difference. */
function BudgetsPanel({
  gymId,
  canManage,
  active,
}: {
  gymId: number
  canManage: boolean
  active: boolean
}) {
  const [month, setMonth] = useState(() => monthKey())
  const [overview, setOverview] = useState<BudgetOverview | null>(null)
  const [amounts, setAmounts] = useState<Record<string, string>>({})
  const [error, setError] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)
  const [version, setVersion] = useState(0)
  const loaded = useRef<{ gymId: number; month: string; version: number } | null>(null)

  useEffect(() => {
    if (!active) return
    if (!gymId || !isMonthKey(month)) {
      loaded.current = null
      return
    }
    if (
      loaded.current?.gymId === gymId &&
      loaded.current.month === month &&
      loaded.current.version === version
    ) {
      return
    }
    let live = true
    setOverview(null)
    setError('')
    const query = new URLSearchParams({ month })
    api<unknown>(`/gyms/${gymId}/finance/budgets?${query.toString()}`)
      .then((payload) => {
        if (!live) return
        const next = budgetOverviewFrom(payload, month)
        loaded.current = { gymId, month, version }
        setOverview(next)
        setAmounts(amountsFrom(next))
      })
      .catch((reason) => {
        if (!live) return
        setOverview(null)
        setAmounts({})
        setError(message(reason, 'Budgets could not be loaded.'))
      })
    return () => {
      live = false
    }
  }, [gymId, month, version, active])

  const groups = overview ? budgetGroups(overview) : []
  const rows = groups.flatMap((group) => group.rows)
  const hasInvalid = rows.some(
    (row) => budgetAmount(amounts[budgetKey(row.kind, row.category)] ?? String(row.budget)) === null,
  )
  const monthValid = isMonthKey(month)
  const blocked = busy || !canManage || !monthValid || overview === null || rows.length === 0 || hasInvalid

  const indexedGroups = groups.map((group, groupIndex) => {
    const startIndex = groups
      .slice(0, groupIndex)
      .reduce((total, item) => total + item.rows.length, 0)
    return { group, startIndex }
  })

  function changeMonth(value: string) {
    setMonth(value)
    setErrors({})
  }

  function changeAmount(key: string, value: string) {
    setAmounts((current) => ({ ...current, [key]: value }))
  }

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (blocked) return
    setBusy(true)
    setErrors({})
    try {
      const payload = await api<unknown>(`/gyms/${gymId}/finance/budgets`, {
        method: 'PUT',
        body: {
          month,
          budgets: rows.map((row) => ({
            kind: row.kind,
            category: row.category,
            amount: budgetAmount(amounts[budgetKey(row.kind, row.category)] ?? String(row.budget)) ?? 0,
          })),
        },
      })
      if (budgetLooksComplete(payload)) {
        const next = budgetOverviewFrom(payload, month)
        setOverview(next)
        setAmounts(amountsFrom(next))
      } else {
        setVersion((value) => value + 1)
      }
      toastManager.add({ title: `Budgets saved for ${monthTitle(month)}.`, type: 'success' })
    } catch (reason) {
      setErrors(
        formErrors(
          reason,
          ['month', 'budgets'],
          'The budgets could not be saved.',
          ['budgets.'],
        ),
      )
    } finally {
      setBusy(false)
    }
  }

  return (
    <Form errors={errors} onSubmit={save} className="flex flex-col gap-5">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="flex w-full max-w-md flex-col gap-1.5">
          <TextField
            name="month"
            label="Month"
            type="month"
            value={month}
            disabled={busy}
            onChange={(event) => changeMonth(event.target.value)}
          />
          <p className="text-muted-foreground text-sm">
            Budgets are set for one month. Edit the amounts, then save all of them together.
          </p>
        </div>
        {canManage && (
          <Button type="submit" loading={busy} disabled={blocked}>
            Save all
          </Button>
        )}
      </div>

      {error ? (
        <Alert variant="error">
          <AlertTitle>Budgets could not be loaded</AlertTitle>
          <AlertDescription>{error}</AlertDescription>
          <AlertAction>
            <Button size="xs" variant="outline" onClick={() => setVersion((value) => value + 1)}>
              Try again
            </Button>
          </AlertAction>
        </Alert>
      ) : !monthValid ? (
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>Choose a month</EmptyTitle>
            <EmptyDescription>Pick a month to set its budgets and compare them with actual income and expenses.</EmptyDescription>
          </EmptyHeader>
        </Empty>
      ) : overview === null ? (
        <LoadingRows label="Loading budgets" />
      ) : rows.length === 0 ? (
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>No budget categories yet</EmptyTitle>
            <EmptyDescription>
              Income types and expense categories will appear here once the gym has them. Record income or an expense first.
            </EmptyDescription>
          </EmptyHeader>
        </Empty>
      ) : (
        <>
          <div className="flex flex-col gap-6">
            {indexedGroups.map(({ group, startIndex }) =>
              group.rows.length > 0 ? (
                <BudgetGroupSection
                  key={group.kind}
                  group={group}
                  startIndex={startIndex}
                  canManage={canManage}
                  amounts={amounts}
                  onAmount={changeAmount}
                />
              ) : null,
            )}
          </div>
          {canManage && (
            <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
              <p className="text-muted-foreground text-sm">
                {hasInvalid
                  ? 'Fix the highlighted amounts before saving.'
                  : 'Saving updates every category for this month.'}
              </p>
              <Button type="submit" loading={busy} disabled={blocked}>
                Save all
              </Button>
            </div>
          )}
        </>
      )}
    </Form>
  )
}

function BudgetGroupSection({
  group,
  startIndex,
  canManage,
  amounts,
  onAmount,
}: {
  group: BudgetGroup
  startIndex: number
  canManage: boolean
  amounts: Record<string, string>
  onAmount: (key: string, value: string) => void
}) {
  return (
    <section aria-labelledby={`budget-group-${group.kind}`} className="flex flex-col gap-3">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div className="flex flex-col gap-0.5">
          <h2 id={`budget-group-${group.kind}`} className="font-heading font-semibold text-base">
            {group.title}
          </h2>
          <p className="text-muted-foreground text-sm">
            {group.rows.length} {group.rows.length === 1 ? 'category' : 'categories'}
          </p>
        </div>
        <dl className="flex flex-wrap items-baseline gap-x-5 gap-y-1 text-sm">
          <div className="flex items-baseline gap-1.5">
            <dt className="text-muted-foreground">Budget</dt>
            <dd className="font-semibold tabular-nums">{money(group.totals.budget)}</dd>
          </div>
          <div className="flex items-baseline gap-1.5">
            <dt className="text-muted-foreground">Actual</dt>
            <dd className="font-semibold tabular-nums">{money(group.totals.actual)}</dd>
          </div>
          <div className="flex items-baseline gap-1.5">
            <dt className="text-muted-foreground">Difference</dt>
            <dd className="font-semibold tabular-nums">{money(group.totals.difference)}</dd>
          </div>
          <div className="flex items-baseline gap-1.5">
            <dt className="text-muted-foreground">Used</dt>
            <dd className="font-semibold tabular-nums">{percentLabel(group.totals.percentage)}</dd>
          </div>
        </dl>
      </header>

      <div className="overflow-x-auto rounded-xl border bg-card">
        <div className="md:min-w-[44rem]">
        <div className="hidden items-center gap-3 border-b bg-muted/40 px-3 py-2 font-medium text-muted-foreground text-xs md:grid md:grid-cols-[minmax(0,1fr)_9rem_7.5rem_8.5rem_6rem]">
          <span>Category</span>
          <span>Budget</span>
          <span className="text-right">Actual</span>
          <span className="text-right">Difference</span>
          <span className="text-right">Used</span>
        </div>

        <ul className="divide-y">
          {group.rows.map((row, index) => {
            const key = budgetKey(row.kind, row.category)
            const value = amounts[key] ?? String(row.budget)
            const invalid = budgetAmount(value) === null
            const fieldName = `budgets.${startIndex + index}.amount`
            return (
              <li
                key={key}
                className="grid min-w-0 gap-3 p-4 md:grid-cols-[minmax(0,1fr)_9rem_7.5rem_8.5rem_6rem] md:items-center md:gap-3 md:px-3 md:py-2.5"
              >
                <span className="min-w-0 break-words font-medium">{row.label}</span>

                {canManage ? (
                  <Field name={fieldName} className="w-full gap-1">
                    <FieldLabel className="sr-only">{row.label} budget</FieldLabel>
                    <Input
                      name={fieldName}
                      type="number"
                      inputMode="decimal"
                      min="0"
                      max={MAX_BUDGET}
                      step="0.01"
                      value={value}
                      aria-label={`Budget for ${row.label}`}
                      aria-invalid={invalid || undefined}
                      className="tabular-nums"
                      onChange={(event) => onAmount(key, event.target.value)}
                    />
                    {invalid && <FieldDescription className="text-destructive">Enter zero or more.</FieldDescription>}
                    <FieldError />
                  </Field>
                ) : (
                  <span className="font-medium tabular-nums">{money(row.budget)}</span>
                )}

                <div className="grid grid-cols-3 gap-2 text-sm md:contents">
                  <div className="flex flex-col gap-0.5 md:block md:text-right">
                    <span className="text-muted-foreground text-xs md:hidden">Actual</span>
                    <span className="tabular-nums">{money(row.actual)}</span>
                  </div>
                  <div className="flex flex-col gap-0.5 md:block md:text-right">
                    <span className="text-muted-foreground text-xs md:hidden">Difference</span>
                    <span className="font-medium tabular-nums">{money(row.difference)}</span>
                  </div>
                  <div className="flex flex-col gap-0.5 md:block md:text-right">
                    <span className="text-muted-foreground text-xs md:hidden">Used</span>
                    <span className="tabular-nums">{percentLabel(row.percentage)}</span>
                  </div>
                </div>
              </li>
            )
          })}
        </ul>
        </div>
      </div>
    </section>
  )
}

/** R-68: cash flow, income against expenses, payroll and pay-outs over one shared date range. */
function ReportsPanel({ gymId, active }: { gymId: number; active: boolean }) {
  const [from, setFrom] = useState(monthStartIso)
  const [to, setTo] = useState(todayIso)

  const rangeMessage = !from || !to
    ? 'Choose both dates to see the reports.'
    : from > to
      ? 'The From date is after the To date. Choose From on or before To.'
      : ''

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-col gap-2">
        <div className="grid items-end gap-3 sm:max-w-md sm:grid-cols-2">
          <TextField
            name="report_from"
            label="From"
            type="date"
            value={from}
            onChange={(event) => setFrom(event.target.value)}
          />
          <TextField
            name="report_to"
            label="To"
            type="date"
            value={to}
            onChange={(event) => setTo(event.target.value)}
          />
        </div>
        <p className="text-muted-foreground text-sm">
          All four reports use the same range. Ranges over 62 days are grouped by month; shorter ranges by day.
        </p>
      </div>

      {rangeMessage ? (
        <Alert variant="warning">
          <AlertTitle>Check the date range</AlertTitle>
          <AlertDescription>{rangeMessage}</AlertDescription>
        </Alert>
      ) : active ? (
        <div className="grid min-w-0 gap-4 2xl:grid-cols-2">
          {REPORT_DEFINITIONS.map((definition) => (
            <ReportCard key={definition.kind} gymId={gymId} definition={definition} from={from} to={to} />
          ))}
        </div>
      ) : null}
    </div>
  )
}

const reportBarTones = ['bg-chart-1', 'bg-chart-2', 'bg-chart-3'] as const

function ReportCard({
  gymId,
  definition,
  from,
  to,
}: {
  gymId: number
  definition: ReportDefinition
  from: string
  to: string
}) {
  const [result, setResult] = useState<{
    key: string
    data: ReportResult | null
    error: string
  } | null>(null)
  const [version, setVersion] = useState(0)
  const requestKey = `${gymId}:${definition.kind}:${from}:${to}:${version}`

  useEffect(() => {
    let live = true
    const query = new URLSearchParams({ from, to })
    api<unknown>(`/gyms/${gymId}/finance/reports/${definition.path}?${query.toString()}`)
      .then((payload) => {
        if (live) {
          setResult({ key: requestKey, data: reportFrom(payload, definition.kind), error: '' })
        }
      })
      .catch((reason) => {
        if (!live) return
        setResult({
          key: requestKey,
          data: null,
          error: message(reason, 'This report could not be loaded.'),
        })
      })
    return () => {
      live = false
    }
  }, [gymId, definition.kind, definition.path, from, to, requestKey])

  const current = result?.key === requestKey ? result : null
  const data = current?.data ?? null
  const error = current?.error ?? ''
  const mode = data ? reportBucketMode(data.buckets) : null
  const modeLabel = bucketModeLabel(mode)

  return (
    <section
      aria-labelledby={`report-${definition.kind}`}
      className="flex min-w-0 flex-col rounded-2xl border bg-card"
    >
      <header className="flex flex-wrap items-start justify-between gap-3 border-b p-4">
        <div className="flex min-w-0 flex-col gap-1">
          <h2 id={`report-${definition.kind}`} className="font-heading font-semibold text-base">
            {definition.title}
          </h2>
          <p className="max-w-prose text-muted-foreground text-sm">{definition.description}</p>
        </div>
        {modeLabel && <Badge variant="outline">{modeLabel}</Badge>}
      </header>

      <div className="min-w-0 p-4">
        {error ? (
          <Alert variant="error">
            <AlertTitle>{definition.title} could not be loaded</AlertTitle>
            <AlertDescription>{error}</AlertDescription>
            <AlertAction>
              <Button size="xs" variant="outline" onClick={() => setVersion((value) => value + 1)}>
                Try again
              </Button>
            </AlertAction>
          </Alert>
        ) : data === null ? (
          <LoadingRows label={`Loading ${definition.title.toLowerCase()}`} />
        ) : data.buckets.length === 0 ? (
          <Empty className="py-8 md:py-10">
            <EmptyHeader>
              <EmptyTitle className="text-base">No figures for this range</EmptyTitle>
              <EmptyDescription>Change the dates to see this report.</EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <ReportContent definition={definition} data={data} />
        )}
      </div>
    </section>
  )
}

function ReportContent({ definition, data }: { definition: ReportDefinition; data: ReportResult }) {
  const max = reportBarMax(data.buckets, definition.barKeys)

  return (
    <div className="flex min-w-0 flex-col gap-3">
      <ReportLegend definition={definition} />

      <div className="hidden min-w-0 overflow-x-auto md:block">
        <Table variant="card" className="min-w-[36rem]">
          <TableHeader>
            <TableRow>
              <TableHead>Period</TableHead>
              {definition.columns.map((column) => (
                <TableHead key={column.key} className="text-right">
                  {column.label}
                </TableHead>
              ))}
              <TableHead>
                <span className="sr-only">Share</span>
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {data.buckets.map((bucket, index) => (
              <TableRow key={`${bucket.period}-${index}`}>
                <TableCell>
                  <time dateTime={reportPeriodDateTime(bucket.period)}>
                    {reportPeriodLabel(bucket.period)}
                  </time>
                </TableCell>
                {definition.columns.map((column) => (
                  <ReportValueCell
                    key={column.key}
                    value={bucket.values[column.key] ?? 0}
                    format={column.format}
                  />
                ))}
                <TableCell>
                  <ReportBars bucket={bucket} definition={definition} max={max} className="w-28" />
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
          <TableFooter>
            <TableRow>
              <TableCell className="font-semibold">Total</TableCell>
              {definition.columns.map((column) => (
                <ReportValueCell
                  key={column.key}
                  value={data.totals[column.key] ?? 0}
                  format={column.format}
                  strong
                />
              ))}
              <TableCell />
            </TableRow>
          </TableFooter>
        </Table>
      </div>

      <div className="flex flex-col gap-3 md:hidden">
        <ul className="flex flex-col divide-y rounded-xl border bg-card">
          {data.buckets.map((bucket, index) => (
            <li key={`${bucket.period}-${index}`} className="flex min-w-0 flex-col gap-3 p-3">
              <time
                className="font-medium tabular-nums"
                dateTime={reportPeriodDateTime(bucket.period)}
              >
                {reportPeriodLabel(bucket.period)}
              </time>
              <dl className="grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                {definition.columns.map((column) => {
                  const value = bucket.values[column.key] ?? 0
                  return (
                    <div key={column.key} className="flex min-w-0 flex-col gap-0.5">
                      <dt className="text-muted-foreground text-xs">{column.label}</dt>
                      <dd
                        className={cn(
                          'tabular-nums',
                          column.format === 'money' && value < 0 && 'text-destructive',
                        )}
                      >
                        {reportValue(value, column.format)}
                      </dd>
                    </div>
                  )
                })}
              </dl>
              <ReportBars bucket={bucket} definition={definition} max={max} className="w-full" />
            </li>
          ))}
        </ul>

        <dl
          aria-label={`${definition.title} totals`}
          className="grid grid-cols-2 gap-px overflow-hidden rounded-xl border bg-border"
        >
          {definition.columns.map((column) => {
            const value = data.totals[column.key] ?? 0
            return (
              <div key={column.key} className="bg-card px-3 py-2.5">
                <dt className="text-muted-foreground text-xs">{column.label}</dt>
                <dd
                  className={cn(
                    'mt-0.5 font-semibold tabular-nums',
                    column.format === 'money' && value < 0 && 'text-destructive',
                  )}
                >
                  {reportValue(value, column.format)}
                </dd>
              </div>
            )
          })}
        </dl>
      </div>
    </div>
  )
}

function ReportValueCell({
  value,
  format,
  strong = false,
}: {
  value: number
  format: 'money' | 'count'
  strong?: boolean
}) {
  return (
    <TableCell
      className={cn(
        'text-right tabular-nums',
        strong && 'font-semibold',
        format === 'money' && value < 0 && 'text-destructive',
      )}
    >
      {reportValue(value, format)}
    </TableCell>
  )
}

function ReportLegend({ definition }: { definition: ReportDefinition }) {
  return (
    <div
      role="group"
      className="flex flex-wrap gap-x-4 gap-y-1 text-muted-foreground text-xs"
      aria-label="Chart legend"
    >
      {definition.barKeys.map((key, index) => {
        const column = definition.columns.find((item) => item.key === key)
        return (
          <span key={key} className="inline-flex items-center gap-1.5">
            <span
              className={cn('size-2 rounded-full', reportBarTones[index % reportBarTones.length])}
              aria-hidden
            />
            {column?.label ?? key}
          </span>
        )
      })}
    </div>
  )
}

function ReportBars({
  bucket,
  definition,
  max,
  className,
}: {
  bucket: ReportResult['buckets'][number]
  definition: ReportDefinition
  max: number
  className?: string
}) {
  return (
    <div className={cn('flex flex-col gap-1', className)} aria-hidden="true">
      {definition.barKeys.map((key, index) => (
        <div key={key} className="h-2 overflow-hidden rounded-full bg-muted">
          <div
            className={cn('h-full rounded-full', reportBarTones[index % reportBarTones.length])}
            style={{ width: `${reportBarWidth(bucket.values[key] ?? 0, max)}%` }}
          />
        </div>
      ))}
    </div>
  )
}

function reportValue(value: number, format: 'money' | 'count'): string {
  return format === 'count' ? countLabel(value) : money(value)
}

