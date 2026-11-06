// Shapes, labels and defensive readers for restock, requests, orders, suppliers and payables (R-75 to R-79, R-66).

import { STOCK_UNIT } from '@/lib/inventory'
import { money } from '@/lib/membership'

export { money }

export type BadgeTone = 'success' | 'warning' | 'error' | 'info' | 'secondary' | 'outline'

export type RestockRow = {
  stock_item_id: number
  name: string
  category: string
  unit: string
  location: string
  quantity: number
  min_level: number | null
  reorder_qty: number | null
  on_order: number
  open_needs: number
  shortfall: number
  suggested_quantity: number
  unit_cost?: string | null
  sku?: string | null
}

export type SupplyNeedStatus = 'open' | 'requested' | 'closed'

export type SupplyNeed = {
  id: number
  stock_item_id: number
  item_name: string
  training_session_id: number | null
  needed_by: string | null
  quantity: number
  note: string | null
  status: SupplyNeedStatus | string
  created_by_name: string | null
  training_session?: { id: number; name?: string | null; starts_at?: string | null } | null
  session_name?: string | null
}

export const SUPPLY_NEED_STATUS_LABELS = {
  open: 'Open',
  requested: 'On a request',
  closed: 'Closed',
} as const

export type PurchaseRequestStatus = 'pending' | 'approved' | 'rejected' | 'cancelled'

export type PurchaseRequestLine = {
  id: number
  stock_item_id: number
  item_name: string | null
  quantity: number
  est_unit_cost: string | null
  supply_need_id: number | null
  stock_item?: { id: number; name?: string | null; unit?: string | null } | null
}

export type PurchaseRequest = {
  id: number
  number: string
  status: PurchaseRequestStatus
  requested_by: number
  requested_by_name: string | null
  decided_by_name: string | null
  decided_at: string | null
  decision_reason: string | null
  needed_by: string | null
  note: string | null
  lines: PurchaseRequestLine[]
  created_at?: string | null
  purchase_order_id?: number | null
  purchase_order?: { id: number; number?: string | null } | null
}

export const PURCHASE_REQUEST_STATUS_LABELS = {
  pending: 'Waiting for decision',
  approved: 'Approved',
  rejected: 'Rejected',
  cancelled: 'Cancelled',
} as const

export type SupplierStatus = 'active' | 'archived'

export type Supplier = {
  id: number
  name: string
  contact_person: string | null
  phone: string | null
  email: string | null
  address: string | null
  payment_terms: string | null
  notes: string | null
  status: SupplierStatus | string
  created_at?: string | null
  updated_at?: string | null
}

export const SUPPLIER_STATUS_LABELS = {
  active: 'Active',
  archived: 'Archived',
} as const

export type PurchaseOrderStatus = 'pending' | 'partly_received' | 'completed' | 'cancelled'

export type PurchaseOrderLine = {
  id: number
  stock_item_id: number
  item_name?: string | null
  unit?: string | null
  quantity_ordered: number
  quantity_received: number
  remaining?: number | null
  unit_cost: string
  amount?: string | null
  quantity?: number | null
  stock_item?: { id: number; name?: string | null; unit?: string | null } | null
}

export type GoodsReceiptLine = {
  id?: number
  purchase_order_line_id?: number | null
  item_name?: string | null
  stock_item_id?: number | null
  quantity: number
  unit_cost?: string | null
  amount?: string | null
  stock_item?: { id: number; name?: string | null; unit?: string | null } | null
}

export type GoodsReceipt = {
  id: number
  number: string
  receipt_number?: string | null
  received_on: string
  received_by_name: string | null
  total: string
  note: string | null
  created_at?: string | null
  lines?: GoodsReceiptLine[] | null
}

export type PurchaseOrderTimelineEvent = {
  id?: number | string
  type?: string | null
  status?: string | null
  label?: string | null
  note?: string | null
  by?: string | null
  actor_name?: string | null
  at?: string | null
  created_at?: string | null
}

export type PurchaseOrder = {
  id: number
  number: string
  supplier_id: number
  supplier_name?: string | null
  supplier?: { id: number; name?: string | null } | null
  purchase_request_id: number | null
  is_direct: boolean
  ordered_by?: number | null
  ordered_by_name?: string | null
  status: PurchaseOrderStatus
  expected_on: string | null
  total: string
  closed_short: boolean
  cancel_reason: string | null
  created_at?: string | null
  updated_at?: string | null
  received_total?: string | null
  quantity_ordered?: number | null
  quantity_received?: number | null
  lines?: PurchaseOrderLine[] | null
  receipts?: GoodsReceipt[] | null
  timeline?: PurchaseOrderTimelineEvent[] | null
}

export const PURCHASE_ORDER_STATUS_LABELS = {
  pending: 'Pending',
  partly_received: 'Partly received',
  completed: 'Completed',
  cancelled: 'Cancelled',
} as const

export type Payable = {
  supplier_id: number
  supplier_name: string
  received: string
  paid: string
  outstanding: string
}

export type PayableOrder = {
  id: number
  number: string
  status: PurchaseOrderStatus | string
  total: string
  received?: string | null
  received_total?: string | null
  expected_on?: string | null
  created_at?: string | null
}

export type SupplierPayment = {
  id: number
  amount: string
  method: string | null
  paid_on: string
  reference: string | null
  note: string | null
  recorded_by_name?: string | null
  purchase_order_id?: number | null
  corrects_id?: number | null
}

export type SupplierPayableDetail = {
  supplier: Supplier
  orders: PayableOrder[]
  payments: SupplierPayment[]
  outstanding: string
}

export const SUPPLIER_PAYMENT_METHOD_LABELS: Record<string, string> = {
  cash: 'Cash',
  card: 'Card',
  ewallet: 'E-wallet',
  bank: 'Bank transfer',
}

export function purchaseRequestStatusMeta(value: unknown): { label: string; variant: BadgeTone } {
  switch (value) {
    case 'pending':
      return { label: PURCHASE_REQUEST_STATUS_LABELS.pending, variant: 'warning' }
    case 'approved':
      return { label: PURCHASE_REQUEST_STATUS_LABELS.approved, variant: 'success' }
    case 'rejected':
      return { label: PURCHASE_REQUEST_STATUS_LABELS.rejected, variant: 'error' }
    case 'cancelled':
      return { label: PURCHASE_REQUEST_STATUS_LABELS.cancelled, variant: 'outline' }
    default:
      return { label: titleWords(value) || 'Unknown', variant: 'secondary' }
  }
}

export function purchaseOrderStatusMeta(value: unknown): { label: string; variant: BadgeTone } {
  switch (value) {
    case 'pending':
      return { label: PURCHASE_ORDER_STATUS_LABELS.pending, variant: 'warning' }
    case 'partly_received':
      return { label: PURCHASE_ORDER_STATUS_LABELS.partly_received, variant: 'info' }
    case 'completed':
      return { label: PURCHASE_ORDER_STATUS_LABELS.completed, variant: 'success' }
    case 'cancelled':
      return { label: PURCHASE_ORDER_STATUS_LABELS.cancelled, variant: 'outline' }
    default:
      return { label: titleWords(value) || 'Unknown', variant: 'secondary' }
  }
}

export function supplierStatusMeta(value: unknown): { label: string; variant: BadgeTone } {
  switch (value) {
    case 'active':
      return { label: SUPPLIER_STATUS_LABELS.active, variant: 'success' }
    case 'archived':
      return { label: SUPPLIER_STATUS_LABELS.archived, variant: 'secondary' }
    default:
      return { label: titleWords(value) || 'Unknown', variant: 'secondary' }
  }
}

export function supplyNeedStatusMeta(value: unknown): { label: string; variant: BadgeTone } {
  switch (value) {
    case 'open':
      return { label: SUPPLY_NEED_STATUS_LABELS.open, variant: 'warning' }
    case 'requested':
      return { label: SUPPLY_NEED_STATUS_LABELS.requested, variant: 'info' }
    case 'closed':
      return { label: SUPPLY_NEED_STATUS_LABELS.closed, variant: 'secondary' }
    default:
      return { label: titleWords(value) || 'Unknown', variant: 'secondary' }
  }
}

export function supplierPaymentMethodLabel(value: unknown): string {
  const key = typeof value === 'string' ? value : ''
  return SUPPLIER_PAYMENT_METHOD_LABELS[key] ?? (titleWords(key) || 'Not recorded')
}

/** Quantities come back with a stock unit key; unknown units still read sensibly. */
export function quantityWithUnit(quantity: number, unit: string | null | undefined): string {
  if (!unit) return String(quantity)
  const labels = (STOCK_UNIT as Record<string, { one: string; many: string }>)[unit]
  return `${quantity} ${labels ? labels[Math.abs(quantity) === 1 ? 'one' : 'many'] : unit}`
}

export function orderSupplierName(order: PurchaseOrder): string {
  return order.supplier_name?.trim() || order.supplier?.name?.trim() || `Supplier #${order.supplier_id}`
}

export function purchaseRequestLineName(line: PurchaseRequestLine): string {
  return line.item_name?.trim() || line.stock_item?.name?.trim() || `Item #${line.stock_item_id}`
}

export function purchaseOrderLineName(line: PurchaseOrderLine): string {
  return line.item_name?.trim() || line.stock_item?.name?.trim() || `Item #${line.stock_item_id}`
}

export function purchaseOrderLineUnit(line: PurchaseOrderLine): string {
  return line.unit?.trim() || line.stock_item?.unit?.trim() || ''
}

export function orderedQuantity(line: PurchaseOrderLine): number {
  const value = line.quantity_ordered ?? line.quantity ?? 0
  return Number.isFinite(Number(value)) ? Number(value) : 0
}

export function receivedQuantity(line: PurchaseOrderLine): number {
  const value = Number(line.quantity_received ?? 0)
  return Number.isFinite(value) ? value : 0
}

export function remainingQuantity(line: PurchaseOrderLine): number {
  return Math.max(0, orderedQuantity(line) - receivedQuantity(line))
}

export function purchaseOrderLineAmount(line: PurchaseOrderLine): number {
  return orderedQuantity(line) * Number(line.unit_cost ?? 0)
}

export function purchaseRequestEstimatedTotal(request: PurchaseRequest): number {
  return (request.lines ?? []).reduce((sum, line) => sum + Number(line.quantity ?? 0) * Number(line.est_unit_cost ?? 0), 0)
}

export function purchaseRequestLineAmount(line: PurchaseRequestLine): number {
  return Number(line.quantity ?? 0) * Number(line.est_unit_cost ?? 0)
}

export type OrderProgress = { received: number; ordered: number; percent: number | null }

/** Prefers line quantities, then order totals, so the list works whichever shape the API returns. */
export function orderProgress(order: PurchaseOrder, lines?: PurchaseOrderLine[] | null): OrderProgress {
  const source = lines ?? order.lines ?? []
  if (source.length > 0) {
    let ordered = 0
    let received = 0
    for (const line of source) {
      ordered += orderedQuantity(line)
      received += receivedQuantity(line)
    }
    if (ordered > 0) {
      return { received, ordered, percent: Math.min(100, Math.round((received / ordered) * 100)) }
    }
  }

  const ordered = Number(order.quantity_ordered ?? 0)
  const received = Number(order.quantity_received ?? 0)
  if (Number.isFinite(ordered) && ordered > 0) {
    return {
      received: Number.isFinite(received) ? received : 0,
      ordered,
      percent: Math.min(100, Math.round((received / ordered) * 100)),
    }
  }

  const total = Number(order.total ?? 0)
  if (order.received_total !== null && order.received_total !== undefined && total > 0) {
    const receivedTotal = Number(order.received_total)
    return {
      received: Number.isFinite(receivedTotal) ? receivedTotal : 0,
      ordered: total,
      percent: Math.min(100, Math.round((receivedTotal / total) * 100)),
    }
  }

  return { received: 0, ordered: 0, percent: null }
}

export function orderProgressLabel(order: PurchaseOrder, lines?: PurchaseOrderLine[] | null): string | null {
  if (order.received_total !== null && order.received_total !== undefined && Number(order.total) > 0) {
    return `${money(order.received_total)} of ${money(order.total)} received`
  }
  const progress = orderProgress(order, lines)
  if (progress.ordered > 0) return `${progress.received} of ${progress.ordered} received`
  return null
}

export function receiptNumber(receipt: GoodsReceipt): string {
  return receipt.number?.trim() || receipt.receipt_number?.trim() || `Receipt #${receipt.id}`
}

export function receiptLineName(line: GoodsReceiptLine, orderLines: PurchaseOrderLine[] = []): string {
  const direct = line.item_name?.trim() || line.stock_item?.name?.trim()
  if (direct) return direct
  const match = orderLines.find((orderLine) => orderLine.id === line.purchase_order_line_id)
  return match ? purchaseOrderLineName(match) : `Item #${line.stock_item_id ?? line.purchase_order_line_id ?? 'unknown'}`
}

export function timelineLabel(event: PurchaseOrderTimelineEvent): string {
  if (event.label?.trim()) return event.label.trim()
  const raw = (event.type ?? event.status ?? '').trim()
  if (!raw) return 'Update'
  if (raw in PURCHASE_ORDER_STATUS_LABELS) return PURCHASE_ORDER_STATUS_LABELS[raw as PurchaseOrderStatus]
  switch (raw) {
    case 'created':
      return 'Purchase order created'
    case 'ordered':
      return 'Purchase order placed'
    case 'direct':
      return 'Owner direct purchase'
    case 'receipt':
      return 'Goods received'
    case 'status':
    case 'status_changed':
      return event.status ? purchaseOrderStatusMeta(event.status).label : 'Status changed'
    case 'closed_short':
      return 'Closed with remainder'
    default:
      return titleWords(raw)
  }
}

export function timelineActor(event: PurchaseOrderTimelineEvent): string | null {
  return event.by?.trim() || event.actor_name?.trim() || null
}

export function timelineAt(event: PurchaseOrderTimelineEvent): string | null {
  return event.at ?? event.created_at ?? null
}

function titleWords(value: unknown): string {
  if (typeof value !== 'string' || value.trim() === '') return ''
  const words = value.replaceAll('_', ' ').trim()
  return words.charAt(0).toUpperCase() + words.slice(1)
}
