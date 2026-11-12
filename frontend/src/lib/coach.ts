// Coach applications (R-117).

export type Slot = { weekday: string; start: string; end: string }

export type CoachApplication = {
  id: number
  gym: { id: number; name: string } | null
  applicant: { id: number; name: string | null; email: string | null }
  status: 'pending' | 'approved' | 'rejected' | 'withdrawn'
  experience: string
  rank: string | null
  specialisation: string | null
  bio: string | null
  availability: Slot[]
  conflicts: string[]
  decided_by: string | null
  decided_at: string | null
  decision_reason: string | null
  created_at: string | null
}

export const WEEKDAYS: Record<string, string> = { mon: 'Monday', tue: 'Tuesday', wed: 'Wednesday', thu: 'Thursday', fri: 'Friday', sat: 'Saturday', sun: 'Sunday' }

export const coachStatus: Record<CoachApplication['status'], { label: string; variant: 'warning' | 'success' | 'error' | 'secondary' }> = {
  pending: { label: 'Waiting for decision', variant: 'warning' },
  approved: { label: 'Approved', variant: 'success' },
  rejected: { label: 'Not approved', variant: 'error' },
  withdrawn: { label: 'Withdrawn', variant: 'secondary' },
}

export function slotLabel(slot: Slot): string {
  return `${WEEKDAYS[slot.weekday] ?? slot.weekday} ${slot.start}–${slot.end}`
}
