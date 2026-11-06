// Shapes, labels and defensive readers for monthly budgets and sustainability reports (R-67, R-68).

import { expenseCategoryLabel, incomeTypeLabel } from '@/lib/finance'
import { dateLabel } from '@/lib/membership'

export type BudgetKind = 'income' | 'expense'

export type BudgetCategory = { key: string; label: string }

export type BudgetRow = {
  kind: BudgetKind
  category: string
  label: string
  budget: number
  actual: number
  difference: number
  percentage: number | null
}

export type BudgetGroup = {
  kind: BudgetKind
  title: string
  rows: BudgetRow[]
  totals: { budget: number; actual: number; difference: number; percentage: number | null }
}

export type BudgetOverview = {
  month: string
  categories: { income: BudgetCategory[]; expense: BudgetCategory[] }
  lines: BudgetRow[]
}

export function budgetKey(kind: BudgetKind, category: string): string {
  return `${kind}:${category}`
}

/** The month picker value, "YYYY-MM", for a date in the browser's local calendar. */
export function monthKey(date = new Date()): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
}

export function isMonthKey(value: string): boolean {
  return /^\d{4}-(0[1-9]|1[0-2])$/.test(value)
}

export function monthTitle(value: string): string {
  const match = /^(\d{4})-(\d{2})$/.exec(value)
  if (!match) return value
  const date = new Date(Number(match[1]), Number(match[2]) - 1, 1)
  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })
}

function budgetCategoryLabel(kind: BudgetKind, value: string): string {
  return kind === 'income' ? incomeTypeLabel(value) : expenseCategoryLabel(value)
}

/**
 * Reads a budget month. Categories arrive as lists of keys or of labelled objects. Any category
 * present in a budget line but missing from its list is added, so a partial API response still
 * shows every row that has a budget or actual value.
 */
export function budgetOverviewFrom(payload: unknown, fallbackMonth: string): BudgetOverview {
  const record = objectOf(payload)
  const nested = objectOf(record?.data)
  const source =
    nested && ('month' in nested || 'budgets' in nested || 'categories' in nested || 'lines' in nested)
      ? nested
      : record

  const month = stringOf(source?.month) ?? fallbackMonth
  const rawLines = arrayOf(source?.budgets) ?? arrayOf(source?.lines) ?? []
  const lines = rawLines.flatMap((entry) => {
    const row = objectOf(entry)
    if (!row) return []
    const kind = row.kind === 'income' || row.kind === 'expense' ? row.kind : null
    const category = stringOf(row.category ?? row.key)
    if (!kind || !category) return []
    const budget = numberOr(row.budget ?? row.amount, 0)
    const actual = numberOr(row.actual ?? row.actual_amount, 0)
    const difference = roundMoney(budget - actual)
    const percentage = budget !== 0 ? roundPercent((actual / budget) * 100) : null
    return [
      {
        kind,
        category,
        label: stringOf(row.label) ?? budgetCategoryLabel(kind, category),
        budget,
        actual,
        difference,
        percentage,
      } satisfies BudgetRow,
    ]
  })

  const rawCategories = objectOf(source?.categories)
  const categories = {
    income: mergeCategories(categoryList(rawCategories?.income, 'income'), lines, 'income'),
    expense: mergeCategories(categoryList(rawCategories?.expense, 'expense'), lines, 'expense'),
  }

  return { month, categories, lines }
}

function categoryList(value: unknown, kind: BudgetKind): BudgetCategory[] {
  if (!Array.isArray(value)) {
    const map = objectOf(value)
    if (!map) return []
    return Object.entries(map).flatMap(([key, entry]) => {
      if (entry === null || entry === undefined || entry === false) return []
      if (typeof entry === 'string') {
        return [{ key, label: entry.trim() || budgetCategoryLabel(kind, key) }]
      }
      const row = objectOf(entry)
      return [
        {
          key,
          label: stringOf(row?.label ?? row?.title ?? row?.name) ?? budgetCategoryLabel(kind, key),
        },
      ]
    })
  }
  return value.flatMap((entry) => {
    if (typeof entry === 'string') {
      return entry.trim() === '' ? [] : [{ key: entry, label: budgetCategoryLabel(kind, entry) }]
    }
    const row = objectOf(entry)
    if (!row) return []
    const key = stringOf(row.value ?? row.key ?? row.category ?? row.slug ?? row.name)
    if (!key) return []
    return [
      {
        key,
        label: stringOf(row.label ?? row.title ?? row.name) ?? budgetCategoryLabel(kind, key),
      },
    ]
  })
}

function mergeCategories(
  listed: BudgetCategory[],
  lines: BudgetRow[],
  kind: BudgetKind,
): BudgetCategory[] {
  const seen = new Set<string>()
  const merged: BudgetCategory[] = []
  for (const category of listed) {
    if (seen.has(category.key)) continue
    seen.add(category.key)
    merged.push(category)
  }
  for (const line of lines) {
    if (line.kind !== kind || seen.has(line.category)) continue
    seen.add(line.category)
    merged.push({ key: line.category, label: line.label || budgetCategoryLabel(kind, line.category) })
  }
  return merged
}

/** Category rows joined to their budget line, in the API's category order. */
export function budgetGroups(overview: BudgetOverview): BudgetGroup[] {
  const byKey = new Map(overview.lines.map((line) => [budgetKey(line.kind, line.category), line]))
  return [
    groupOf('income', 'Income budgets', overview.categories.income, byKey),
    groupOf('expense', 'Expense budgets', overview.categories.expense, byKey),
  ]
}

function groupOf(
  kind: BudgetKind,
  title: string,
  categories: BudgetCategory[],
  byKey: Map<string, BudgetRow>,
): BudgetGroup {
  const rows = categories.map((category) => {
    const line = byKey.get(budgetKey(kind, category.key))
    return {
      kind,
      category: category.key,
      label: category.label || budgetCategoryLabel(kind, category.key),
      budget: line?.budget ?? 0,
      actual: line?.actual ?? 0,
      difference: line?.difference ?? 0,
      percentage: line?.percentage ?? null,
    } satisfies BudgetRow
  })
  const budget = rows.reduce((sum, row) => sum + row.budget, 0)
  const actual = rows.reduce((sum, row) => sum + row.actual, 0)
  const difference = roundMoney(budget - actual)
  return {
    kind,
    title,
    rows,
    totals: {
      budget,
      actual,
      difference,
      percentage: budget !== 0 ? roundPercent((actual / budget) * 100) : null,
    },
  }
}

export function amountsFrom(overview: BudgetOverview): Record<string, string> {
  const amounts: Record<string, string> = {}
  for (const line of overview.lines) {
    amounts[budgetKey(line.kind, line.category)] = String(line.budget)
  }
  return amounts
}

export function percentLabel(value: number | null | undefined): string {
  if (value === null || value === undefined || !Number.isFinite(value)) return '—'
  const rounded = Math.round(value * 10) / 10
  return `${rounded.toLocaleString('en-PH', { maximumFractionDigits: 1 })}%`
}

export type ReportKind = 'cash-flow' | 'income-expense' | 'payroll' | 'payouts'

export type ReportColumn = {
  key: string
  label: string
  format: 'money' | 'count'
}

export type ReportDefinition = {
  kind: ReportKind
  title: string
  description: string
  path: string
  columns: ReportColumn[]
  barKeys: string[]
  aliases: Record<string, string[]>
}

export type ReportBucket = {
  period: string
  values: Record<string, number>
}

export type ReportResult = {
  buckets: ReportBucket[]
  totals: Record<string, number>
}

export const REPORT_DEFINITIONS: ReportDefinition[] = [
  {
    kind: 'cash-flow',
    title: 'Cash flow',
    description: 'Money actually received and paid out. Unpaid goods receipts are left out; the later supplier payment counts once.',
    path: 'cash-flow',
    columns: [
      { key: 'in', label: 'In', format: 'money' },
      { key: 'out', label: 'Out', format: 'money' },
      { key: 'net', label: 'Net', format: 'money' },
    ],
    barKeys: ['in', 'out'],
    aliases: {
      in: ['in', 'cash_in', 'money_in', 'income'],
      out: ['out', 'cash_out', 'money_out', 'expenses'],
      net: ['net'],
    },
  },
  {
    kind: 'income-expense',
    title: 'Income vs expenses',
    description: 'Everything earned against everything incurred, including purchases that were received but not yet paid.',
    path: 'income-expense',
    columns: [
      { key: 'income', label: 'Income', format: 'money' },
      { key: 'expenses', label: 'Expenses', format: 'money' },
      { key: 'net', label: 'Net', format: 'money' },
    ],
    barKeys: ['income', 'expenses'],
    aliases: {
      income: ['income', 'in', 'money_in'],
      expenses: ['expenses', 'expense', 'out', 'money_out'],
      net: ['net'],
    },
  },
  {
    kind: 'payroll',
    title: 'Payroll summary',
    description: 'Released payroll runs only. Totals are rebuilt from the payroll lines, not the cached run totals.',
    path: 'payroll',
    columns: [
      { key: 'gross', label: 'Gross', format: 'money' },
      { key: 'net', label: 'Net pay', format: 'money' },
      { key: 'employer_statutory', label: 'Employer statutory', format: 'money' },
      { key: 'count', label: 'Staff', format: 'count' },
    ],
    barKeys: ['gross', 'net'],
    aliases: {
      gross: ['gross', 'gross_total'],
      net: ['net', 'net_total', 'total_net', 'payroll_net'],
      employer_statutory: ['employer_statutory', 'employer_statutory_total'],
      count: ['count', 'staff_count', 'employees'],
    },
  },
  {
    kind: 'payouts',
    title: 'Payouts',
    description: 'Released payroll net pay and supplier payments, by date. Each peso paid out is counted once.',
    path: 'payouts',
    columns: [
      { key: 'payroll_net', label: 'Payroll net', format: 'money' },
      { key: 'supplier_payments', label: 'Supplier payments', format: 'money' },
      { key: 'total', label: 'Total', format: 'money' },
    ],
    barKeys: ['payroll_net', 'supplier_payments'],
    aliases: {
      payroll_net: ['payroll_net', 'payroll', 'net'],
      supplier_payments: ['supplier_payments', 'supplier_payment', 'supplier_total', 'suppliers'],
      total: ['total'],
    },
  },
]

export function reportFrom(payload: unknown, kind: ReportKind): ReportResult {
  const definition = REPORT_DEFINITIONS.find((item) => item.kind === kind) ?? REPORT_DEFINITIONS[0]
  const record = objectOf(payload)
  const nested = objectOf(record?.data)
  const source = nested && ('buckets' in nested || 'totals' in nested) ? nested : record
  const rawBuckets = arrayOf(source?.buckets) ?? []

  const buckets = rawBuckets.flatMap((entry) => {
    const row = objectOf(entry)
    if (!row) return []
    const period = periodOf(row.period ?? row.month ?? row.date ?? row.label)
    const values: Record<string, number> = {}
    for (const column of definition.columns) {
      values[column.key] = valueFrom(row, definition.aliases[column.key] ?? [column.key]) ?? 0
    }
    return [{ period, values } satisfies ReportBucket]
  })

  const rawTotals = objectOf(source?.totals)
  const totals: Record<string, number> = {}
  for (const column of definition.columns) {
    const explicit = rawTotals
      ? valueFrom(rawTotals, definition.aliases[column.key] ?? [column.key])
      : null
    totals[column.key] =
      explicit ?? buckets.reduce((sum, bucket) => sum + (bucket.values[column.key] ?? 0), 0)
  }

  return { buckets, totals }
}

export function reportPeriodLabel(value: string): string {
  if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return dateLabel(value)
  if (/^\d{4}-\d{2}$/.test(value)) return monthTitle(value)
  return value || 'Unknown period'
}

export function reportPeriodDateTime(value: string): string | undefined {
  return /^\d{4}-\d{2}(-\d{2})?$/.test(value) ? value : undefined
}

export function reportBucketMode(buckets: ReportBucket[]): 'daily' | 'monthly' | 'mixed' | null {
  if (buckets.length === 0) return null
  const monthly = buckets.filter((bucket) => /^\d{4}-\d{2}$/.test(bucket.period)).length
  if (monthly === buckets.length) return 'monthly'
  if (monthly === 0) return 'daily'
  return 'mixed'
}

export function bucketModeLabel(mode: ReturnType<typeof reportBucketMode>): string | null {
  if (mode === 'monthly') return 'Monthly'
  if (mode === 'daily') return 'Daily'
  if (mode === 'mixed') return 'Mixed buckets'
  return null
}

export function countLabel(value: number): string {
  return Number.isFinite(value) ? value.toLocaleString('en-PH', { maximumFractionDigits: 0 }) : '0'
}

export function reportBarMax(buckets: ReportBucket[], keys: string[]): number {
  let max = 0
  for (const bucket of buckets) {
    for (const key of keys) {
      const value = Math.abs(bucket.values[key] ?? 0)
      if (Number.isFinite(value) && value > max) max = value
    }
  }
  return max
}

export function reportBarWidth(value: number, max: number): number {
  if (!Number.isFinite(value) || !Number.isFinite(max) || max <= 0) return 0
  return Math.min(100, Math.max(0, (Math.abs(value) / max) * 100))
}

function valueFrom(row: Record<string, unknown>, keys: string[]): number | null {
  for (const key of keys) {
    if (!(key in row)) continue
    const value = nullableNumber(row[key])
    if (value !== null) return value
  }
  return null
}

/** ISO date-times are reduced to their day; month keys are left alone. */
function periodOf(value: unknown): string {
  const text = stringOf(value)
  if (!text) return ''
  const date = /^(\d{4}-\d{2}-\d{2})/.exec(text)
  return date ? date[1] : text
}

function nullableNumber(value: unknown): number | null {
  if (value === null || value === undefined || value === '') return null
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
}

function numberOr(value: unknown, fallback: number): number {
  return nullableNumber(value) ?? fallback
}

function roundMoney(value: number): number {
  return Math.round(value * 100) / 100
}

function roundPercent(value: number): number {
  return Math.round(value * 100) / 100
}

function objectOf(value: unknown): Record<string, unknown> | null {
  return value && typeof value === 'object' && !Array.isArray(value)
    ? (value as Record<string, unknown>)
    : null
}

function arrayOf(value: unknown): unknown[] | null {
  return Array.isArray(value) ? value : null
}

function stringOf(value: unknown): string | null {
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null
}
