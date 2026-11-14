// Job postings and staff applications (R-138).

export type JobPosting = {
  id: number
  title: string
  gym_role_id: number | null
  role_name: string | null
  description: string
  requirements: string | null
  status: 'open' | 'closed'
  pending_count: number
  created_at: string | null
}

export type JobApplication = {
  id: number
  posting: { id: number; title: string | null }
  applicant: { id: number; name: string | null; email: string | null }
  status: 'pending' | 'approved' | 'rejected'
  experience: string
  credentials_name: string
  valid_id_name: string
  decided_by: string | null
  decided_at: string | null
  decision_reason: string | null
  created_at: string | null
}

export type PublicJobPosting = { id: number; title: string; description: string; requirements: string | null }

export const jobStatus: Record<JobApplication['status'], { label: string; variant: 'warning' | 'success' | 'error' }> = {
  pending: { label: 'Waiting for decision', variant: 'warning' },
  approved: { label: 'Approved', variant: 'success' },
  rejected: { label: 'Not approved', variant: 'error' },
}

/** Documents the apply form accepts (same as the server). */
export const JOB_FILE_ACCEPT = '.pdf,.jpg,.jpeg,.png'
export const JOB_FILE_MAX_KB = 5120
