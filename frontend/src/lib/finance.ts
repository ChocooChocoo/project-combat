// Shapes, labels and defensive readers for income, expenses and member balances (R-63 to R-65).

import { METHOD_LABELS, type Payment } from '@/lib/membership'
import { pageMetaFrom } from '@/lib/pagination'

export { pageMetaFrom as metaFrom }
export type { PageMeta } from '@/lib/pagination'

export const INCOME_TYPE_LABELS = {
  membership: 'Membership',
  session: 'Session',
  private_coaching: 'Private coaching',
  walk_in: 'Walk-in',
  merchandise: 'Merchandise',
  other: 'Other income',
}

export type IncomeType = keyof typeof INCOME_TYPE_LABELS

/** Desk sales only. Membership, session and online payments are recorded elsewhere. */
export const SALE_INCOME_TYPES: IncomeType[] = ['walk_in', 'merchandise', 'other']

export const EXPENSE_CATEGORY_LABELS = {
  rent: 'Rent',
  utilities: 'Utilities',
  equipment: 'Equipment',
  supplies: 'Supplies',
  maintenance: 'Maintenance',
  marketing: 'Marketing',
  professional_fees: 'Professional fees',
  taxes_licenses: 'Taxes and licences',
  payroll: 'Payroll',
  statutory_contributions: 'Statutory contributions',
  purchases: 'Purchases',
  other: 'Other expense',
}

export type ExpenseCategory = keyof typeof EXPENSE_CATEGORY_LABELS

export type DeskMethod = 'cash' | 'card' | 'ewallet'
export type ExpenseMethod = DeskMethod | 'bank'

const METHOD_LABELS_ALL: Record<string, string> = { ...METHOD_LABELS, bank: 'Bank transfer' }

export type IncomeRow = {
  id: number
  type: IncomeType
  amount: string
  method: Payment['method'] | null
  paid_at: string
  member_name: string | null
  payer_name: string | null
  receipt_number: string | null
  note: string | null
  corrects_id: number | null
  membership_id: number | null
  booking_id: number | null
}

export type Expense = {
  id: number
  category: ExpenseCategory
  amount: string
  incurred_on: string
  method: string | null
  note: string | null
  source_type: string | null
  source_id: number | null
  corrects_id: number | null
  recorded_by_name: string | null
  created_at: string | null
}

export type ReceivableSource = {
  kind: string
  id: number | null
  label: string
  amount: string
}

export type Receivable = {
  user_id: number
  member_name: string
  total: string
  sources: ReceivableSource[]
}

export type BalanceSource = {
  kind?: string | null
  id?: number | null
  label?: string | null
  amount: string
  gym_name?: string | null
}

export type MyBalance = {
  balance: string
  sources: BalanceSource[]
}

export type FinanceTotal = { key: string; amount: string }

export function incomeTypeLabel(value: unknown): string {
  return typeof value === 'string' && value in INCOME_TYPE_LABELS
    ? INCOME_TYPE_LABELS[value as IncomeType]
    : titleWords(value) || 'Other income'
}

export function expenseCategoryLabel(value: unknown): string {
  return typeof value === 'string' && value in EXPENSE_CATEGORY_LABELS
    ? EXPENSE_CATEGORY_LABELS[value as ExpenseCategory]
    : titleWords(value) || 'Other expense'
}

export function methodLabel(value: unknown): string {
  const key = typeof value === 'string' ? value : ''
  return METHOD_LABELS_ALL[key] ?? (titleWords(key) || 'Not recorded')
}

export function sourceTypeLabel(value: unknown): string {
  if (typeof value !== 'string' || value === '') return ''
  if (value === 'payroll_run') return 'Payroll run'
  if (value === 'goods_receipt') return 'Goods receipt'
  return titleWords(value)
}

export function receivableSourceLabel(source: ReceivableSource): string {
  if (source.label?.trim()) return source.label
  return sourceKindLabel(source.kind)
}

export function balanceSourceLabel(source: BalanceSource): string {
  const name = source.label?.trim() || sourceKindLabel(source.kind)
  return source.gym_name?.trim() ? `${source.gym_name} · ${name}` : name
}

function sourceKindLabel(kind: unknown): string {
  switch (kind) {
    case 'membership':
      return 'Membership balance'
    case 'booking':
      return 'Session add-on'
    case 'session':
      return 'Session'
    case 'private_coaching':
      return 'Private coaching'
    case 'walk_in':
      return 'Walk-in sale'
    case 'merchandise':
      return 'Merchandise'
    default:
      return titleWords(kind) || 'Balance'
  }
}

/** Only a positive row that has not been reversed can be corrected. */
export function correctableExpense(expense: Expense, all: Expense[]): boolean {
  return Number(expense.amount) > 0 && !all.some((other) => other.corrects_id === expense.id)
}

export function hasExpenseCorrection(expense: Expense, all: Expense[]): boolean {
  return all.some((other) => other.corrects_id === expense.id)
}

/** Reads the list under `key`, accepting either the bare shape or a data envelope. */
export function rowsOf<T>(payload: unknown, key: string): T[] {
  const record = objectOf(payload)
  const nested = objectOf(record?.data)
  for (const box of [record, nested]) {
    const value = box?.[key]
    if (Array.isArray(value)) return value as T[]
    const nestedValue = objectOf(value)?.data
    if (Array.isArray(nestedValue)) return nestedValue as T[]
  }
  return []
}

/** Totals arrive grouped by income type or expense category. Objects and lists both work. */
export function totalsFrom(payload: unknown, kind: 'type' | 'category'): FinanceTotal[] {
  const record = objectOf(payload)
  const nested = objectOf(record?.data)
  const raw = record?.totals ?? nested?.totals
  if (Array.isArray(raw)) {
    return raw.flatMap((entry) => {
      const row = objectOf(entry)
      if (!row) return []
      const key = row[kind] ?? row.income_type ?? row.category ?? row.key ?? row.label
      const amount = row.amount ?? row.total
      return key === null || key === undefined ? [] : [{ key: String(key), amount: String(amount ?? 0) }]
    })
  }
  const map = objectOf(raw)
  if (!map) return []
  return Object.entries(map).map(([key, value]) => {
    const row = objectOf(value)
    return { key, amount: String(row?.amount ?? row?.total ?? value ?? 0) }
  })
}

/** Reads the signed-in person's combined balance, accepting a bare or wrapped body. */
export function myBalanceFrom(payload: unknown): MyBalance {
  const record = objectOf(payload)
  const nested = objectOf(record?.data)
  const source = nested && ('balance' in nested || 'sources' in nested) ? nested : record
  return {
    balance: String(source?.balance ?? 0),
    sources: rowsOf<BalanceSource>(payload, 'sources'),
  }
}

function objectOf(value: unknown): Record<string, unknown> | null {
  return value && typeof value === 'object' && !Array.isArray(value) ? (value as Record<string, unknown>) : null
}

function titleWords(value: unknown): string {
  if (typeof value !== 'string' || value.trim() === '') return ''
  const words = value.replaceAll('_', ' ').trim()
  return words.charAt(0).toUpperCase() + words.slice(1)
}
