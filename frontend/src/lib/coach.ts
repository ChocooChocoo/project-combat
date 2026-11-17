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
  // (R-146) Names only. Older applications have none.
  resume_name: string | null
  certifications_name: string | null
  valid_id_name: string | null
  medical_clearance_name: string | null
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

/** (R-146) The documents an application carries, in the order the form asks for them. Old applications have no files. */
export function attachedFiles(row: CoachApplication) {
  const files = [
    { kind: 'resume', label: 'Resume', name: row.resume_name },
    { kind: 'certifications', label: 'Certifications', name: row.certifications_name },
    { kind: 'valid_id', label: 'Valid ID', name: row.valid_id_name },
    { kind: 'medical_clearance', label: 'Medical clearance', name: row.medical_clearance_name },
  ]

  return files.filter((file): file is { kind: string; label: string; name: string } => file.name !== null)
}

/** (R-146) Documents the apply form accepts (same as the server). */
export const COACH_FILE_ACCEPT = '.pdf,.jpg,.jpeg,.png'
export const COACH_FILE_MAX_KB = 5120
