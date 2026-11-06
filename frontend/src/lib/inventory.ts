export type StockCategory = 'equipment' | 'protective_gear' | 'supplies' | 'merchandise'
export type StockUnit = 'piece' | 'pair' | 'box' | 'litre' | 'kg'
export type StockStatus = 'active' | 'archived'
export type StockMovementType = 'opening' | 'receipt' | 'issue' | 'return' | 'adjustment' | 'damaged' | 'lost' | 'sale'

export type StockItem = {
  id: number
  name: string
  sku: string | null
  category: StockCategory
  unit: StockUnit
  location: string
  quantity: number
  min_level: number | null
  reorder_qty: number | null
  unit_cost: string | null
  sale_price: string | null
  status: StockStatus
  is_low: boolean
  low_notified_at: string | null
  created_at: string | null
  updated_at: string | null
}

export type StockSummary = { total: number; low: number; out: number }
export type StockMovement = {
  id: number
  type: StockMovementType
  quantity: number
  balance_after: number
  note: string | null
  actor_id: number | null
  actor_name: string | null
  occurred_at: string
  issued_to_user_id: number | null
  issued_to_name: string | null
  returns_movement_id: number | null
  returned: number | null
  outstanding: number | null
  payment_id: number | null
  goods_receipt_line_id: number | null
}
export type OpenIssue = { id: number; quantity: number; outstanding: number; issued_to_name: string | null; occurred_at: string }
export type StockRecipient = { id: number; name: string; role: string }
export type StockMovementsPage = {
  item: StockItem
  movements: StockMovement[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
  open_issues: OpenIssue[]
}

export const STOCK_CATEGORY: Record<StockCategory, string> = {
  equipment: 'Equipment', protective_gear: 'Protective gear', supplies: 'Supplies', merchandise: 'Merchandise',
}
export const STOCK_UNIT: Record<StockUnit, { one: string; many: string }> = {
  piece: { one: 'piece', many: 'pieces' },
  pair: { one: 'pair', many: 'pairs' },
  box: { one: 'box', many: 'boxes' },
  litre: { one: 'litre', many: 'litres' },
  kg: { one: 'kg', many: 'kg' },
}
export const MOVEMENT_TYPE: Record<StockMovementType, { label: string; hint: string }> = {
  opening: { label: 'Opening count', hint: 'The count when this item was added.' },
  receipt: { label: 'Received', hint: 'Add stock that has arrived at the gym.' },
  issue: { label: 'Issued', hint: 'Take stock out for a person or class.' },
  return: { label: 'Returned', hint: 'Bring stock back against an earlier issue.' },
  adjustment: { label: 'Adjusted', hint: 'Correct the count and explain the difference.' },
  damaged: { label: 'Damaged', hint: 'Remove unusable stock and explain the damage.' },
  lost: { label: 'Lost', hint: 'Remove missing stock and explain what happened.' },
  sale: { label: 'Sold', hint: 'Stock sold through a payment.' },
}
export const MANUAL_MOVEMENT_TYPES: StockMovementType[] = ['receipt', 'issue', 'return', 'adjustment', 'damaged', 'lost']
export const NOTE_REQUIRED: StockMovementType[] = ['adjustment', 'damaged', 'lost']
export const OUT_TYPES: StockMovementType[] = ['issue', 'damaged', 'lost', 'sale']

export function quantityLabel(quantity: number, unit: StockUnit): string {
  return `${quantity} ${STOCK_UNIT[unit][Math.abs(quantity) === 1 ? 'one' : 'many']}`
}

export function signedLabel(quantity: number): string {
  return quantity < 0 ? `−${Math.abs(quantity)}` : `+${quantity}`
}

export function stockLevel(item: StockItem): 'out' | 'low' | 'ok' {
  return item.quantity === 0 ? 'out' : item.is_low ? 'low' : 'ok'
}
