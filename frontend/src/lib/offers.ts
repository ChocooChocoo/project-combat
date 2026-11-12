// Shapes and labels for promos and vouchers on membership plans (R-111).
import { money } from '@/lib/membership'

export type OfferStatus = 'pending' | 'approved' | 'rejected' | 'archived'

export type Offer = {
  id: number
  kind: 'promo' | 'voucher'
  name: string
  code: string | null
  discount_type: 'percent' | 'fixed'
  value: string
  starts_on: string
  ends_on: string | null
  max_uses: number | null
  uses: number
  status: OfferStatus
  plans: { id: number; name: string }[]
  created_by: string | null
  created_by_id: number | null
  decided_by: string | null
  decided_at: string | null
  decision_reason: string | null
}

export const offerStatus: Record<OfferStatus, { label: string; variant: 'warning' | 'success' | 'error' | 'secondary' }> = {
  pending: { label: 'Waiting for approval', variant: 'warning' },
  approved: { label: 'Approved', variant: 'success' },
  rejected: { label: 'Rejected', variant: 'error' },
  archived: { label: 'Archived', variant: 'secondary' },
}

export function discountLabel(offer: Pick<Offer, 'discount_type' | 'value'>): string {
  return offer.discount_type === 'percent' ? `${Number(offer.value)}% off` : `${money(offer.value)} off`
}
