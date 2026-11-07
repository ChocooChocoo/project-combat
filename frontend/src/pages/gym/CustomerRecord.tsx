import { useEffect, useState } from 'react'
import { ArrowLeft, Mail, Pencil, Phone, Plus, Search } from 'lucide-react'
import { Link, useParams } from 'react-router'
import { MembershipStatusBadge } from '@/components/membership/membership-parts'
import { PageHeader } from '@/components/page-header'
import { TextField } from '@/components/text-field'
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import {
  CUSTOMER_SOURCE_LABELS,
  CUSTOMER_STATUS_LABELS,
  INTERACTION_CHANNEL_LABELS,
  INTERACTION_KIND_LABELS,
  channelLabel,
  dateTimeForApi,
  followUpStatusLabel,
  followUpStatusVariant,
  groupCountLabel,
  groupMemberIds,
  interactionKindLabel,
  interactionKindVariant,
  loadStaffChoices,
  nowDateTimeInput,
  sourceLabel,
  stageBadge,
  type Customer,
  type CustomerDetail,
  type CustomerFollowUp,
  type CustomerGroup,
  type CustomerInteraction,
  type CustomerSource,
  type CustomerStatus,
  type InteractionChannel,
  type InteractionKind,
  type LinkableMembership,
  type StaffChoice,
} from '@/lib/customers'
import { STATUS_LABELS, dateLabel, dateTimeLabel, gymDate, type Membership } from '@/lib/membership'
import { phoneRule } from '@/lib/validation'
import { cn } from '@/lib/utils'

type CustomerDetailResponse = {
  customer: Customer
  interactions?: CustomerInteraction[] | null
  follow_ups?: CustomerFollowUp[] | null
  followUps?: CustomerFollowUp[] | null
  groups?: CustomerGroup[] | null
}

const SOURCE_ITEMS = (Object.entries(CUSTOMER_SOURCE_LABELS) as [CustomerSource, string][]).map(([value, label]) => ({
  value,
  label,
}))

const KIND_ITEMS = (Object.entries(INTERACTION_KIND_LABELS) as [InteractionKind, string][]).map(([value, label]) => ({
  value,
  label,
}))

const CHANNEL_ITEMS = (Object.entries(INTERACTION_CHANNEL_LABELS) as [InteractionChannel, string][]).map(([value, label]) => ({
  value,
  label,
}))

const STATUS_ITEMS = (Object.entries(CUSTOMER_STATUS_LABELS) as [CustomerStatus, string][]).map(([value, label]) => ({
  value,
  label,
}))

function message(reason: unknown, fallback: string): string {
  return reason instanceof ApiError ? reason.message : fallback
}

function formErrors(reason: unknown, fallback: string, known: string[]): FieldErrors {
  const errors = reason instanceof ApiError ? reason.errors : {}
  const keys = Object.keys(errors)
  if (!(reason instanceof ApiError) || keys.length === 0 || keys.some((key) => !known.includes(key))) {
    toastManager.add({ title: message(reason, fallback), type: 'error' })
  }
  return errors
}

function normalizeDetail(response: CustomerDetailResponse): CustomerDetail {
  return {
    customer: response.customer,
    interactions: response.interactions ?? [],
    // The contract calls the key follow_ups; followUps is accepted as a small fallback.
    followUps: response.follow_ups ?? response.followUps ?? [],
    groups: response.groups ?? [],
  }
}

function followUpOrder(a: CustomerFollowUp, b: CustomerFollowUp): number {
  const rank = (status: string) => (status === 'open' ? 0 : status === 'done' ? 1 : 2)
  const byStatus = rank(a.status) - rank(b.status)
  if (byStatus !== 0) return byStatus
  const dueA = gymDate(a.due_at).getTime()
  const dueB = gymDate(b.due_at).getTime()
  return (Number.isNaN(dueA) ? Number.MAX_SAFE_INTEGER : dueA) - (Number.isNaN(dueB) ? Number.MAX_SAFE_INTEGER : dueB)
}

function isOverdue(followUp: CustomerFollowUp): boolean {
  if (followUp.status !== 'open') return false
  const due = gymDate(followUp.due_at).getTime()
  return !Number.isNaN(due) && due < Date.now()
}

function defaultFollowUpDue(): string {
  const date = new Date()
  date.setDate(date.getDate() + 1)
  date.setHours(9, 0, 0, 0)
  const pad = (value: number) => String(value).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function mergeAssignees(me: StaffChoice | null, staff: StaffChoice[], customer?: Customer): StaffChoice[] {
  const list = [...(me ? [me] : []), ...staff]
  if (customer?.assigned_to && !list.some((person) => person.id === customer.assigned_to)) {
    list.push({ id: customer.assigned_to, name: customer.assigned_to_name ?? `User #${customer.assigned_to}` })
  }
  const seen = new Set<number>()
  return list.filter((person) => {
    if (seen.has(person.id)) return false
    seen.add(person.id)
    return true
  })
}

function membershipOptionLabel(membership: LinkableMembership): string {
  return `#${membership.id} · ${membership.member_name} · ${membership.plan_name ?? 'No plan'} · ${
    STATUS_LABELS[membership.status] ?? membership.status
  }`
}

function membershipMatches(membership: LinkableMembership, search: string): boolean {
  const term = search.trim().toLowerCase()
  if (!term) return true
  return [String(membership.id), membership.member_name, membership.email, membership.plan_name, STATUS_LABELS[membership.status]]
    .filter(Boolean)
    .some((value) => String(value).toLowerCase().includes(term))
}

/**
 * The CRM-specific route is the normal source. The wider membership list is a fallback
 * for builds that only have the older endpoint; both are limited to waiting applications.
 */
async function loadLinkableMemberships(gymId: number): Promise<LinkableMembership[]> {
  try {
    const result = await api<{ memberships: LinkableMembership[] }>(`/gyms/${gymId}/customers/linkable-memberships`)
    return result.memberships
  } catch (primary) {
    try {
      const result = await api<{ memberships: Membership[] }>(`/gyms/${gymId}/memberships`)
      return result.memberships
        .filter((membership) => membership.status === 'applied' || membership.status === 'awaiting_payment')
        .map((membership) => ({
          id: membership.id,
          status: membership.status,
          starts_on: membership.starts_on,
          created_at: membership.created_at,
          member_id: membership.member?.id ?? 0,
          member_name: membership.member?.name ?? 'Unknown person',
          email: membership.member?.email ?? null,
          plan_name: membership.plan?.name ?? null,
        }))
    } catch {
      throw primary
    }
  }
}

/**
 * Syncs the whole group audience. The packet names the route but not the body; `customer_ids`
 * is the natural sync payload. A validation error naming `members` retries that shape.
 */
async function syncGroupMembers(gymId: number, groupId: number, customerIds: number[]): Promise<void> {
  const path = `/gyms/${gymId}/customer-groups/${groupId}/members`
  try {
    await api<{ group?: CustomerGroup; members?: unknown }>(path, { method: 'PUT', body: { customer_ids: customerIds } })
  } catch (reason) {
    if (reason instanceof ApiError && reason.status === 422 && reason.errors.members) {
      await api<{ group?: CustomerGroup; members?: unknown }>(path, { method: 'PUT', body: { members: customerIds } })
      return
    }
    throw reason
  }
}

/** S-25, R-69 to R-71: one customer's details, history, follow-ups and groups. */
export default function CustomerRecord() {
  const { gymId: gymIdParam, customerId: customerIdParam } = useParams()
  const gymId = Number(gymIdParam)
  const customerId = Number(customerIdParam)
  const { user } = useAuth()
  const gym = approvedGym(user, gymId)
  const canView = gym ? can(gym, 'customers.records.view') : false
  const canManage = gym ? can(gym, 'customers.records.manage') : false
  const canGroups = gym ? can(gym, 'customers.groups.manage') : false

  const [detail, setDetail] = useState<CustomerDetail | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)

  const [memberships, setMemberships] = useState<LinkableMembership[] | null>(null)
  const [membershipError, setMembershipError] = useState('')
  const [groups, setGroups] = useState<CustomerGroup[] | null>(null)
  const [groupError, setGroupError] = useState('')
  const [staff, setStaff] = useState<StaffChoice[]>([])

  const canViewMembership = gym ? can(gym, 'members.memberships.view') : false
  const [editOpen, setEditOpen] = useState(false)
  const [followUpOpen, setFollowUpOpen] = useState(false)
  const [doneTarget, setDoneTarget] = useState<CustomerFollowUp | null>(null)
  const [reassignTarget, setReassignTarget] = useState<CustomerFollowUp | null>(null)
  const [cancelTarget, setCancelTarget] = useState<CustomerFollowUp | null>(null)
  const [cancelBusy, setCancelBusy] = useState(false)
  const [addingGroup, setAddingGroup] = useState(false)
  const [creatingGroup, setCreatingGroup] = useState(false)
  const [removingGroup, setRemovingGroup] = useState<CustomerGroup | null>(null)
  const [groupBusy, setGroupBusy] = useState(false)

  useEffect(() => {
    if (!gymId || !customerId || !canView) return
    let live = true
    setError('')
    api<CustomerDetailResponse>(`/gyms/${gymId}/customers/${customerId}`)
      .then((result) => {
        if (live) setDetail(normalizeDetail(result))
      })
      .catch((reason) => {
        if (live) setError(message(reason, 'This customer could not be loaded.'))
      })
    return () => {
      live = false
    }
  }, [gymId, customerId, canView, version])

  useEffect(() => {
    if (!gymId || !canView) return
    let live = true
    setMembershipError('')
    loadLinkableMemberships(gymId)
      .then((people) => {
        if (live) setMemberships(people)
      })
      .catch((reason) => {
        if (live) {
          setMemberships((current) => current ?? [])
          setMembershipError(message(reason, 'Membership choices could not be loaded.'))
        }
      })
    return () => {
      live = false
    }
  }, [gymId, canView, version])

  useEffect(() => {
    if (!gymId || !canView) return
    let live = true
    setGroupError('')
    api<{ groups: CustomerGroup[] }>(`/gyms/${gymId}/customer-groups`)
      .then((result) => {
        if (live) setGroups(result.groups)
      })
      .catch((reason) => {
        if (live) {
          setGroups((current) => current ?? [])
          setGroupError(message(reason, 'Groups could not be loaded.'))
        }
      })
    return () => {
      live = false
    }
  }, [gymId, canView, version])

  useEffect(() => {
    if (!gymId || !canManage) return
    let live = true
    loadStaffChoices(gymId)
      .then((people) => {
        if (live) setStaff(people)
      })
      .catch(() => {
        if (live) setStaff([])
      })
    return () => {
      live = false
    }
  }, [gymId, canManage])

  if (!gym) return null

  if (!canView) {
    return (
      <div className="flex flex-col gap-6">
        <PageHeader title="Customer" />
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>Customer records are not available</EmptyTitle>
            <EmptyDescription>Ask the owner for the customer records permission.</EmptyDescription>
          </EmptyHeader>
        </Empty>
      </div>
    )
  }

  const refresh = () => setVersion((current) => current + 1)

  if (!detail) {
    if (error) {
      return (
        <div className="flex flex-col gap-6">
          <PageHeader
            title="Customer"
            actions={
              <Button size="sm" variant="outline" render={<Link to={`/gyms/${gymId}/manage/customers`} />}>
                <ArrowLeft aria-hidden /> Customers
              </Button>
            }
          />
          <Alert variant="error">
            <AlertTitle>This customer could not be loaded</AlertTitle>
            <AlertDescription>{error}</AlertDescription>
            <AlertAction>
              <Button size="xs" variant="outline" onClick={refresh}>
                Try again
              </Button>
            </AlertAction>
          </Alert>
        </div>
      )
    }
    return (
      <div className="flex flex-col gap-6" aria-busy="true" aria-label="Loading customer">
        <Skeleton className="h-16 w-72" />
        <Skeleton className="h-48" />
        <Skeleton className="h-64" />
      </div>
    )
  }

  const customer = detail.customer
  const stage = stageBadge(customer.stage, customer.status)
  const me = user && canManage ? ({ id: user.id, name: `${user.first_name} ${user.last_name}`.trim() } satisfies StaffChoice) : null
  const assignees = mergeAssignees(me, staff, customer)
  const followUps = [...detail.followUps].sort(followUpOrder)
  const linkedMembership = customer.membership_id
    ? (memberships?.find((membership) => String(membership.id) === String(customer.membership_id)) ?? null)
    : null
  const customerGroups = detail.groups
    .map((group) => groups?.find((candidate) => String(candidate.id) === String(group.id)) ?? group)
    .sort((a, b) => a.name.localeCompare(b.name))
  const availableGroups = (groups ?? []).filter(
    (group) => !customerGroups.some((mine) => String(mine.id) === String(group.id)),
  )
  const defaultAssignee = String(user?.id ?? customer.assigned_to ?? assignees[0]?.id ?? '')

  async function cancelFollowUp() {
    if (!canManage || !cancelTarget) return
    setCancelBusy(true)
    try {
      await api(`/gyms/${gymId}/follow-ups/${cancelTarget.id}`, { method: 'PUT', body: { status: 'cancelled' } })
      toastManager.add({ title: 'Follow-up cancelled.', type: 'success' })
      setCancelTarget(null)
      refresh()
    } catch (reason) {
      toastManager.add({ title: message(reason, 'The follow-up could not be cancelled.'), type: 'error' })
    } finally {
      setCancelBusy(false)
    }
  }

  async function removeFromGroup() {
    if (!canGroups || !removingGroup || groupBusy) return
    const ids = groupMemberIds(removingGroup)
    if (ids === null && (removingGroup.members_count ?? 0) > 1) {
      toastManager.add({
        title: 'This group did not include its member list, so removing someone could affect other members. Try again after refreshing.',
        type: 'error',
      })
      setRemovingGroup(null)
      return
    }
    const next = (ids ?? []).filter((id) => id !== customer.id)
    setGroupBusy(true)
    try {
      await syncGroupMembers(gymId, removingGroup.id, next)
      toastManager.add({ title: `Removed from ${removingGroup.name}.`, type: 'success' })
      setRemovingGroup(null)
      refresh()
    } catch (reason) {
      toastManager.add({ title: message(reason, 'The group could not be changed.'), type: 'error' })
    } finally {
      setGroupBusy(false)
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={customer.name}
        meta={<Badge variant={stage.variant}>{stage.label}</Badge>}
        description={`${sourceLabel(customer.source)} · Added ${dateLabel(customer.created_at)}`}
        actions={
          <>
            <Button size="sm" variant="outline" render={<Link to={`/gyms/${gymId}/manage/customers`} />}>
              <ArrowLeft aria-hidden /> Customers
            </Button>
            {customer.email && (
              <Button size="sm" variant="outline" render={<a href={`mailto:${customer.email}`} />}>
                <Mail aria-hidden /> Email
              </Button>
            )}
            {customer.phone && (
              <Button
                size="sm"
                variant="outline"
                render={<a href={`tel:${customer.phone.replace(/[^\d+]/g, '')}`} />}
              >
                <Phone aria-hidden /> Call
              </Button>
            )}
            {canManage && (
              <Button size="sm" variant="outline" onClick={() => setEditOpen(true)}>
                <Pencil aria-hidden /> Edit
              </Button>
            )}
          </>
        }
      />

      {error && (
        <Alert variant="error">
          <AlertTitle>This customer may be out of date</AlertTitle>
          <AlertDescription>{error}</AlertDescription>
          <AlertAction>
            <Button size="xs" variant="outline" onClick={refresh}>
              Try again
            </Button>
          </AlertAction>
        </Alert>
      )}

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div className="flex min-w-0 flex-col gap-6">
          <TimelineCard
            gymId={gymId}
            customerId={customer.id}
            interactions={detail.interactions}
            canManage={canManage}
            onChanged={refresh}
          />
          <FollowUpsCard
            followUps={followUps}
            canManage={canManage}
            onAdd={() => setFollowUpOpen(true)}
            onDone={setDoneTarget}
            onReassign={setReassignTarget}
            onCancel={setCancelTarget}
          />
        </div>

        <div className="flex min-w-0 flex-col gap-6">
          <DetailsCard customer={customer} />
          <MembershipCard
            gymId={gymId}
            customer={customer}
            memberships={memberships}
            linked={linkedMembership}
            error={membershipError}
            canManage={canManage}
            canViewLinked={canViewMembership}
            onLinked={refresh}
            onRetry={refresh}
          />
          <GroupsCard
            groups={customerGroups}
            canManage={canGroups}
            addDisabled={groups === null || groupError !== ''}
            error={groupError}
            onAdd={() => setAddingGroup(true)}
            onCreate={() => setCreatingGroup(true)}
            onRemove={setRemovingGroup}
            onRetry={refresh}
          />
        </div>
      </div>

      {canManage && editOpen && (
        <EditCustomerDialog
          gymId={gymId}
          customer={customer}
          assignees={assignees}
          onClose={() => setEditOpen(false)}
          onSaved={() => {
            setEditOpen(false)
            refresh()
          }}
        />
      )}

      {canManage && followUpOpen && (
        <FollowUpDialog
          gymId={gymId}
          customer={customer}
          assignees={assignees}
          defaultAssignee={defaultAssignee}
          onClose={() => setFollowUpOpen(false)}
          onSaved={() => {
            setFollowUpOpen(false)
            refresh()
          }}
        />
      )}

      {canManage && doneTarget && (
        <DoneFollowUpDialog
          gymId={gymId}
          followUp={doneTarget}
          customerName={customer.name}
          onClose={() => setDoneTarget(null)}
          onSaved={() => {
            setDoneTarget(null)
            refresh()
          }}
        />
      )}

      {canManage && reassignTarget && (
        <ReassignFollowUpDialog
          gymId={gymId}
          followUp={reassignTarget}
          customerName={customer.name}
          assignees={assignees}
          onClose={() => setReassignTarget(null)}
          onSaved={() => {
            setReassignTarget(null)
            refresh()
          }}
        />
      )}

      <AlertDialog open={canManage && cancelTarget !== null} onOpenChange={(open) => !open && !cancelBusy && setCancelTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Cancel this follow-up?</AlertDialogTitle>
            <AlertDialogDescription>
              {cancelTarget ? `${customer.name} · ${cancelTarget.note}` : ''} It stays on the record as cancelled and
              leaves the open list.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <Button variant="outline" disabled={cancelBusy} onClick={() => setCancelTarget(null)}>
              Keep it open
            </Button>
            <Button variant="destructive" loading={cancelBusy} onClick={cancelFollowUp}>
              Cancel follow-up
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {canGroups && addingGroup && (
        <AddToGroupDialog
          gymId={gymId}
          customer={customer}
          groups={availableGroups}
          onClose={() => setAddingGroup(false)}
          onSaved={() => {
            setAddingGroup(false)
            refresh()
          }}
        />
      )}

      {canGroups && creatingGroup && (
        <CreateGroupDialog
          gymId={gymId}
          customer={customer}
          onClose={() => setCreatingGroup(false)}
          onSaved={() => {
            setCreatingGroup(false)
            refresh()
          }}
        />
      )}

      <AlertDialog open={canGroups && removingGroup !== null} onOpenChange={(open) => !open && !groupBusy && setRemovingGroup(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle className="break-words">Remove from {removingGroup?.name}?</AlertDialogTitle>
            <AlertDialogDescription>
              {customer.name} leaves this group. The group and its other members stay as they are.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <Button variant="outline" disabled={groupBusy} onClick={() => setRemovingGroup(null)}>
              Keep in group
            </Button>
            <Button variant="destructive" loading={groupBusy} onClick={removeFromGroup}>
              Remove from group
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}

function DetailsCard({ customer }: { customer: Customer }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle render={<h2 />}>Details</CardTitle>
        <CardDescription>Contact, source and the person who owns the follow-up.</CardDescription>
      </CardHeader>
      <CardPanel>
        <dl className="grid min-w-0 gap-4 text-sm">
          <div>
            <dt className="text-muted-foreground text-xs">Email</dt>
            <dd className="break-words">{customer.email ?? <span className="text-muted-foreground">Not given</span>}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground text-xs">Phone</dt>
            <dd className="break-words">{customer.phone ?? <span className="text-muted-foreground">Not given</span>}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground text-xs">Source</dt>
            <dd>{sourceLabel(customer.source)}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground text-xs">Follow-up owner</dt>
            <dd className="break-words">
              {customer.assigned_to_name ?? <span className="text-muted-foreground">Unassigned</span>}
            </dd>
          </div>
          <div>
            <dt className="text-muted-foreground text-xs">Status</dt>
            <dd>{CUSTOMER_STATUS_LABELS[customer.status] ?? customer.status}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground text-xs">Added</dt>
            <dd>
              <time dateTime={customer.created_at}>{dateTimeLabel(customer.created_at)}</time>
            </dd>
          </div>
          <div>
            <dt className="text-muted-foreground text-xs">Inquiry</dt>
            <dd className="whitespace-pre-line wrap-anywhere">
              {customer.inquiry ?? <span className="text-muted-foreground">Nothing recorded yet.</span>}
            </dd>
          </div>
        </dl>
      </CardPanel>
    </Card>
  )
}

function MembershipCard({
  gymId,
  customer,
  memberships,
  linked,
  error,
  canManage,
  canViewLinked,
  onLinked,
  onRetry,
}: {
  gymId: number
  customer: Customer
  memberships: LinkableMembership[] | null
  linked: LinkableMembership | null
  error: string
  canManage: boolean
  canViewLinked: boolean
  onLinked: () => void
  onRetry: () => void
}) {
  const [search, setSearch] = useState('')
  const [choice, setChoice] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)
  const [fullMembership, setFullMembership] = useState<Membership | null>(null)

  useEffect(() => {
    if (!customer.membership_id || !canViewLinked) {
      setFullMembership(null)
      return
    }
    setFullMembership(null)
    let live = true
    api<{ membership: Membership }>(`/gyms/${gymId}/memberships/${customer.membership_id}`)
      .then((result) => {
        if (live) setFullMembership(result.membership)
      })
      .catch(() => {
        if (live) setFullMembership(null)
      })
    return () => {
      live = false
    }
  }, [gymId, customer.membership_id, canViewLinked])

  const display = linked
    ? {
        id: linked.id,
        name: linked.member_name,
        status: linked.status,
        plan: linked.plan_name,
        email: linked.email,
      }
    : fullMembership
      ? {
          id: fullMembership.id,
          name: fullMembership.member?.name ?? customer.name,
          status: fullMembership.status,
          plan: fullMembership.plan?.name ?? null,
          email: fullMembership.member?.email ?? null,
        }
      : null

  const selected = memberships?.find((membership) => String(membership.id) === choice) ?? null
  const filtered = (memberships ?? []).filter((membership) => membershipMatches(membership, search))
  const options = selected && !filtered.includes(selected) ? [selected, ...filtered] : filtered
  const choices = options.map((membership) => ({ value: String(membership.id), label: membershipOptionLabel(membership) }))

  async function link(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!canManage) return
    setErrors({})
    if (!choice) {
      setErrors({ membership_id: ['Choose an application or member to link.'] })
      return
    }
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/customers/${customer.id}/link-membership`, {
        body: { membership_id: Number(choice) },
      })
      toastManager.add({ title: 'Application linked.', type: 'success' })
      onLinked()
    } catch (reason) {
      setErrors(formErrors(reason, 'The application could not be linked.', ['membership_id']))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle render={<h2 />}>Membership</CardTitle>
        <CardDescription>The application or member this customer is linked to.</CardDescription>
      </CardHeader>
      <CardPanel className="flex flex-col gap-4">
        {customer.membership_id ? (
          <>
            <div className="flex flex-col gap-2">
              <p className="break-words font-medium">{display?.name ?? customer.name}</p>
              <div className="flex flex-wrap items-center gap-2">
                {display && <MembershipStatusBadge status={display.status} />}
                <span className="text-muted-foreground text-sm">
                  {display ? `Membership #${display.id} · ${display.plan ?? 'No plan'}` : `Membership #${customer.membership_id}`}
                </span>
              </div>
              {display?.email && <p className="break-words text-muted-foreground text-sm">{display.email}</p>}
              {customer.linked_at && <p className="text-muted-foreground text-xs">Linked {dateLabel(customer.linked_at)}</p>}
            </div>
            {canViewLinked && (
              <Button
                size="sm"
                variant="outline"
                className="self-start"
                render={<Link to={`/gyms/${gymId}/manage/members/${customer.membership_id}`} />}
              >
                Open membership record
              </Button>
            )}
          </>
        ) : canManage ? (
          <>
            {memberships === null ? (
              <div className="flex flex-col gap-2" aria-busy="true">
                <Skeleton className="h-9" />
                <Skeleton className="h-9" />
              </div>
            ) : memberships.length === 0 ? (
              <p className="text-muted-foreground text-sm">No applications are waiting to be linked.</p>
            ) : (
              <Form errors={errors} onSubmit={link} className="flex flex-col gap-4">
                <Field name="membership_search">
                  <FieldLabel>Find an application</FieldLabel>
                  <InputGroup>
                    <InputGroupAddon>
                      <Search aria-hidden />
                    </InputGroupAddon>
                    <InputGroupInput
                      type="search"
                      name="membership_search"
                      value={search}
                      onChange={(event) => setSearch(event.target.value)}
                      placeholder="Name, plan or number"
                      aria-label="Find an application"
                    />
                  </InputGroup>
                </Field>
                <Field name="membership_id">
                  <FieldLabel>Membership application</FieldLabel>
                  <Select
                    items={choices}
                    value={choice || null}
                    onValueChange={(next) => setChoice(next ?? '')}
                    disabled={choices.length === 0}
                  >
                    <SelectTrigger aria-label="Membership application">
                      <SelectValue placeholder={choices.length === 0 ? 'No matches' : 'Choose one'} />
                    </SelectTrigger>
                    <SelectPopup className="w-(--anchor-width) max-w-[calc(100vw-2rem)]">
                      {choices.map((item) => (
                        <SelectItem key={item.value} value={item.value} className="whitespace-normal break-words">
                          {item.label}
                        </SelectItem>
                      ))}
                    </SelectPopup>
                  </Select>
                  <FieldDescription>
                    {choices.length === 0
                      ? 'Change the search to see other people.'
                      : 'Only applications from this gym are shown.'}
                  </FieldDescription>
                  <FieldError />
                </Field>
                <Button type="submit" size="sm" className="self-start" loading={busy} disabled={!choice}>
                  Link application
                </Button>
              </Form>
            )}
            {error && (
              <div className="flex flex-col gap-2">
                <p className="text-muted-foreground text-sm">{error}</p>
                <Button size="xs" variant="outline" className="self-start" onClick={onRetry}>
                  Try again
                </Button>
              </div>
            )}
          </>
        ) : (
          <p className="text-muted-foreground text-sm">
            Not linked. Someone with customer records access can link an application or member.
          </p>
        )}
      </CardPanel>
    </Card>
  )
}

function TimelineCard({
  gymId,
  customerId,
  interactions,
  canManage,
  onChanged,
}: {
  gymId: number
  customerId: number
  interactions: CustomerInteraction[]
  canManage: boolean
  onChanged: () => void
}) {
  const [kind, setKind] = useState<InteractionKind>('interaction')
  const [channel, setChannel] = useState<InteractionChannel>('phone')
  const [body, setBody] = useState('')
  const [occurred, setOccurred] = useState(nowDateTimeInput)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  const [resolving, setResolving] = useState<CustomerInteraction | null>(null)
  const [resolution, setResolution] = useState('')
  const [resolveErrors, setResolveErrors] = useState<FieldErrors>({})
  const [resolveBusy, setResolveBusy] = useState(false)

  const ordered = [...interactions].sort((a, b) => {
    const byTime = gymDate(b.occurred_at).getTime() - gymDate(a.occurred_at).getTime()
    return Number.isNaN(byTime) || byTime === 0 ? b.id - a.id : byTime
  })

  async function addInteraction(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!canManage) return
    setErrors({})
    if (!body.trim()) {
      setErrors({ body: ['Write what happened.'] })
      return
    }
    if (!occurred) {
      setErrors({ occurred_at: ['Choose when it happened.'] })
      return
    }
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/customers/${customerId}/interactions`, {
        body: {
          kind,
          channel,
          body: body.trim(),
          occurred_at: dateTimeForApi(occurred),
        },
      })
      toastManager.add({ title: 'Entry added to the timeline.', type: 'success' })
      setBody('')
      setOccurred(nowDateTimeInput())
      onChanged()
    } catch (reason) {
      setErrors(formErrors(reason, 'The entry could not be saved.', ['kind', 'channel', 'body', 'occurred_at']))
    } finally {
      setBusy(false)
    }
  }

  function openResolve(interaction: CustomerInteraction) {
    setResolving(interaction)
    setResolution('')
    setResolveErrors({})
  }

  async function resolve(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!canManage || !resolving) return
    setResolveErrors({})
    if (!resolution.trim()) {
      setResolveErrors({ resolution: ['Say how the concern was resolved.'] })
      return
    }
    setResolveBusy(true)
    try {
      await api(`/gyms/${gymId}/interactions/${resolving.id}/resolve`, {
        body: { resolution: resolution.trim() },
      })
      toastManager.add({ title: 'Concern resolved.', type: 'success' })
      setResolving(null)
      onChanged()
    } catch (reason) {
      setResolveErrors(formErrors(reason, 'The concern could not be resolved.', ['resolution']))
    } finally {
      setResolveBusy(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle render={<h2 />}>Timeline</CardTitle>
        <CardDescription>
          Every interaction, feedback item, preference and concern, newest first. Entries are kept.
        </CardDescription>
      </CardHeader>
      <CardPanel className="flex flex-col gap-6">
        {canManage ? (
          <Form errors={errors} onSubmit={addInteraction} className="flex flex-col gap-4 rounded-xl border bg-muted/30 p-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <Choice name="kind" label="Kind" value={kind} choices={KIND_ITEMS} onChange={(next) => setKind(next as InteractionKind)} required />
              <Choice
                name="channel"
                label="Channel"
                value={channel}
                choices={CHANNEL_ITEMS}
                onChange={(next) => setChannel(next as InteractionChannel)}
                required
              />
            </div>
            <Field name="body">
              <FieldLabel>What happened?</FieldLabel>
              <Textarea
                name="body"
                rows={3}
                maxLength={5000}
                value={body}
                onChange={(event) => setBody(event.target.value)}
                required
                placeholder="What was said, asked for or noticed."
              />
              <FieldError />
            </Field>
            <div className="grid items-end gap-4 sm:grid-cols-[15rem_auto]">
              <TextField
                name="occurred_at"
                label="When"
                type="datetime-local"
                value={occurred}
                onChange={(event) => setOccurred(event.target.value)}
                required
              />
              <Button type="submit" className="justify-self-start" loading={busy} disabled={!body.trim() || !occurred}>
                <Plus aria-hidden /> Add entry
              </Button>
            </div>
          </Form>
        ) : (
          <p className="text-muted-foreground text-sm">
            The timeline is read-only for you. Customer records access lets staff add entries.
          </p>
        )}

        {ordered.length === 0 ? (
          <Empty>
            <EmptyHeader>
              <EmptyTitle>Nothing recorded yet</EmptyTitle>
              <EmptyDescription>
                Calls, messages, preferences and concerns appear here in the order they happened.
              </EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <ol className="flex flex-col divide-y rounded-xl border bg-card">
            {ordered.map((interaction) => (
              <li key={interaction.id} className="flex flex-col gap-2 p-4">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge variant={interactionKindVariant(interaction.kind)}>{interactionKindLabel(interaction.kind)}</Badge>
                  <span className="text-muted-foreground text-xs">{channelLabel(interaction.channel)}</span>
                  <time className="text-muted-foreground text-xs tabular-nums" dateTime={interaction.occurred_at}>
                    {dateTimeLabel(interaction.occurred_at)}
                  </time>
                </div>
                <p className="whitespace-pre-line break-words text-sm">{interaction.body}</p>
                <p className="text-muted-foreground text-xs">
                  Recorded by {interaction.recorded_by_name ?? 'unknown staff'}
                </p>
                {interaction.kind === 'concern' &&
                  (interaction.resolved_at ? (
                    <div className="rounded-lg border border-success/24 bg-success/4 p-3 text-sm">
                      <p className="font-medium">
                        Resolved{interaction.resolved_by_name ? ` by ${interaction.resolved_by_name}` : ''}
                        {interaction.resolved_at ? ` on ${dateTimeLabel(interaction.resolved_at)}` : ''}
                      </p>
                      {interaction.resolution && (
                        <p className="mt-1 whitespace-pre-line break-words text-muted-foreground">{interaction.resolution}</p>
                      )}
                    </div>
                  ) : canManage ? (
                    <Button size="xs" variant="outline" className="self-start" onClick={() => openResolve(interaction)}>
                      Resolve concern
                    </Button>
                  ) : (
                    <p className="text-muted-foreground text-xs">Open concern. Someone with customer records access can resolve it.</p>
                  ))}
              </li>
            ))}
          </ol>
        )}
      </CardPanel>

      <Dialog open={resolving !== null} onOpenChange={(open) => !open && !resolveBusy && setResolving(null)}>
        <DialogPopup>
          {resolving && (
            <Form errors={resolveErrors} onSubmit={resolve} className="flex min-h-0 flex-col">
              <DialogHeader>
                <DialogTitle>Resolve concern</DialogTitle>
                <DialogDescription>The resolution and your name stay with the concern.</DialogDescription>
              </DialogHeader>
              <DialogPanel className="flex flex-col gap-4">
                <div className="rounded-xl border bg-muted/40 p-3 text-sm">
                  <p className="whitespace-pre-line break-words">{resolving.body}</p>
                  <p className="mt-2 text-muted-foreground text-xs">
                    Recorded by {resolving.recorded_by_name ?? 'unknown staff'} on {dateTimeLabel(resolving.occurred_at)}
                  </p>
                </div>
                <Field name="resolution">
                  <FieldLabel>How was it resolved?</FieldLabel>
                  <Textarea
                    name="resolution"
                    rows={3}
                    maxLength={5000}
                    value={resolution}
                    onChange={(event) => setResolution(event.target.value)}
                    required
                    autoFocus
                    placeholder="For example: called the customer and changed the class booking."
                  />
                  <FieldError />
                </Field>
              </DialogPanel>
              <DialogFooter>
                <Button type="button" variant="outline" disabled={resolveBusy} onClick={() => setResolving(null)}>
                  Cancel
                </Button>
                <Button type="submit" loading={resolveBusy} disabled={!resolution.trim()}>
                  Resolve concern
                </Button>
              </DialogFooter>
            </Form>
          )}
        </DialogPopup>
      </Dialog>
    </Card>
  )
}

function FollowUpsCard({
  followUps,
  canManage,
  onAdd,
  onDone,
  onReassign,
  onCancel,
}: {
  followUps: CustomerFollowUp[]
  canManage: boolean
  onAdd: () => void
  onDone: (followUp: CustomerFollowUp) => void
  onReassign: (followUp: CustomerFollowUp) => void
  onCancel: (followUp: CustomerFollowUp) => void
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle render={<h2 />}>Follow-ups</CardTitle>
        <CardDescription>Open items first, then what was done or cancelled.</CardDescription>
        {canManage && (
          <CardAction>
            <Button size="sm" variant="outline" onClick={onAdd}>
              <Plus aria-hidden /> Add follow-up
            </Button>
          </CardAction>
        )}
      </CardHeader>
      <CardPanel>
        {followUps.length === 0 ? (
          <Empty>
            <EmptyHeader>
              <EmptyTitle>No follow-ups yet</EmptyTitle>
              <EmptyDescription>
                Schedule the next call, message or visit and assign it to someone at the gym.
              </EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <ul className="flex flex-col divide-y rounded-xl border bg-card">
            {followUps.map((followUp) => (
              <li key={followUp.id} className="flex flex-col gap-2 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div className="flex min-w-0 flex-col gap-0.5">
                    <span className="flex flex-wrap items-center gap-2">
                      <time
                        className={cn('text-sm tabular-nums', isOverdue(followUp) && 'font-medium text-destructive-foreground')}
                        dateTime={followUp.due_at}
                      >
                        Due {dateTimeLabel(followUp.due_at)}
                      </time>
                      {isOverdue(followUp) && <Badge variant="error">Overdue</Badge>}
                    </span>
                    <span className="text-muted-foreground text-xs">
                      Assigned to {followUp.assigned_to_name ?? 'someone at the gym'}
                    </span>
                  </div>
                  <Badge variant={followUpStatusVariant(followUp.status)}>{followUpStatusLabel(followUp.status)}</Badge>
                </div>
                <p className="whitespace-pre-line break-words text-sm">{followUp.note}</p>
                {followUp.outcome && (
                  <p className="whitespace-pre-line break-words text-muted-foreground text-sm">Outcome: {followUp.outcome}</p>
                )}
                {followUp.status === 'done' && (
                  <p className="text-muted-foreground text-xs">
                    Done{followUp.done_by_name ? ` by ${followUp.done_by_name}` : ''}
                    {followUp.done_at ? ` on ${dateTimeLabel(followUp.done_at)}` : ''}.
                  </p>
                )}
                {followUp.status === 'cancelled' && <p className="text-muted-foreground text-xs">Cancelled.</p>}
                {canManage && followUp.status === 'open' && (
                  <div className="flex flex-wrap gap-2">
                    <Button size="xs" onClick={() => onDone(followUp)}>
                      Mark done
                    </Button>
                    <Button size="xs" variant="outline" onClick={() => onReassign(followUp)}>
                      Reassign
                    </Button>
                    <Button size="xs" variant="ghost" onClick={() => onCancel(followUp)}>
                      Cancel
                    </Button>
                  </div>
                )}
              </li>
            ))}
          </ul>
        )}
      </CardPanel>
    </Card>
  )
}

function GroupsCard({
  groups,
  canManage,
  addDisabled,
  error,
  onAdd,
  onCreate,
  onRemove,
  onRetry,
}: {
  groups: CustomerGroup[]
  canManage: boolean
  addDisabled: boolean
  error: string
  onAdd: () => void
  onCreate: () => void
  onRemove: (group: CustomerGroup) => void
  onRetry: () => void
}) {
  return (
    <Card>
      <CardHeader>
        <CardTitle render={<h2 />}>Groups</CardTitle>
        <CardDescription>Named sets that can become announcement audiences later.</CardDescription>
        {canManage && (
          <CardAction className="flex flex-wrap gap-2">
            <Button size="xs" variant="outline" onClick={onAdd} disabled={addDisabled}>
              Add to group
            </Button>
            <Button size="xs" onClick={onCreate}>
              <Plus aria-hidden /> New group
            </Button>
          </CardAction>
        )}
      </CardHeader>
      <CardPanel className="flex flex-col gap-3">
        {error && (
          <div className="flex flex-col gap-2">
            <p className="text-muted-foreground text-sm">{error}</p>
            <Button size="xs" variant="outline" className="self-start" onClick={onRetry}>
              Try again
            </Button>
          </div>
        )}
        {groups.length === 0 ? (
          <p className="text-muted-foreground text-sm">
            Not in a group yet. Create one here to collect customers for a future announcement.
          </p>
        ) : (
          <ul className="flex flex-col divide-y rounded-xl border">
            {groups.map((group) => {
              const removable = groupMemberIds(group) !== null || (group.members_count ?? 0) <= 1
              return (
                <li key={group.id} className="flex flex-col gap-1 p-3">
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                      <p className="break-words font-medium">{group.name}</p>
                      <p className="text-muted-foreground text-xs">{groupCountLabel(group)}</p>
                    </div>
                    {canManage && (
                      <Button
                        size="xs"
                        variant="ghost"
                        disabled={!removable}
                        title={
                          removable
                            ? undefined
                            : 'This group did not include its member list, so removing one person could affect the others.'
                        }
                        onClick={() => onRemove(group)}
                      >
                        Remove
                      </Button>
                    )}
                  </div>
                  {group.description && <p className="break-words text-muted-foreground text-xs">{group.description}</p>}
                </li>
              )
            })}
          </ul>
        )}
        {canManage && groups.some((group) => groupMemberIds(group) === null && (group.members_count ?? 0) > 1) && (
          <p className="text-muted-foreground text-xs">
            Some groups did not include their member list, so those can only be changed after the page is refreshed.
          </p>
        )}
      </CardPanel>
    </Card>
  )
}

function EditCustomerDialog({
  gymId,
  customer,
  assignees,
  onClose,
  onSaved,
}: {
  gymId: number
  customer: Customer
  assignees: StaffChoice[]
  onClose: () => void
  onSaved: () => void
}) {
  const [name, setName] = useState(customer.name)
  const [email, setEmail] = useState(customer.email ?? '')
  const [phone, setPhone] = useState(customer.phone ?? '')
  const [source, setSource] = useState<CustomerSource>(customer.source)
  const [inquiry, setInquiry] = useState(customer.inquiry ?? '')
  const [assignedTo, setAssignedTo] = useState(customer.assigned_to ? String(customer.assigned_to) : 'none')
  const [status, setStatus] = useState<CustomerStatus>(customer.status)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setErrors({})
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/customers/${customer.id}`, {
        method: 'PUT',
        body: {
          name: name.trim(),
          email: email.trim() || null,
          phone: phone.trim() || null,
          source,
          inquiry: inquiry.trim() || null,
          assigned_to: assignedTo === 'none' ? null : Number(assignedTo),
          status,
        },
      })
      toastManager.add({ title: 'Customer details saved.', type: 'success' })
      onSaved()
    } catch (reason) {
      setErrors(
        formErrors(reason, 'The customer details could not be saved.', [
          'name',
          'email',
          'phone',
          'source',
          'inquiry',
          'assigned_to',
          'status',
        ]),
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
            <DialogTitle>Edit customer</DialogTitle>
            <DialogDescription>
              The stage follows the linked membership. Marking the record lost closes it until it is opened again.
            </DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            <TextField
              name="name"
              label="Name"
              value={name}
              onChange={(event) => setName(event.target.value)}
              maxLength={120}
              required
              autoFocus
            />
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField
                name="email"
                label="Email (optional)"
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                maxLength={150}
              />
              <TextField
                name="phone"
                label="Phone (optional)"
                type="tel"
                value={phone}
                onChange={(event) => setPhone(event.target.value)}
                maxLength={40}
                validate={phoneRule}
              />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <Choice
                name="source"
                label="Where they came from"
                value={source}
                choices={SOURCE_ITEMS}
                onChange={(next) => setSource(next as CustomerSource)}
                required
              />
              <Choice
                name="status"
                label="Record status"
                value={status}
                choices={STATUS_ITEMS}
                onChange={(next) => setStatus(next as CustomerStatus)}
                required
              />
            </div>
            <Field name="assigned_to">
              <FieldLabel>Follow-up owner</FieldLabel>
              <Select
                items={[
                  { value: 'none', label: 'No one yet' },
                  ...assignees.map((person) => ({ value: String(person.id), label: person.name })),
                ]}
                value={assignedTo}
                onValueChange={(next) => setAssignedTo(next ?? 'none')}
              >
                <SelectTrigger aria-label="Follow-up owner">
                  <SelectValue />
                </SelectTrigger>
                <SelectPopup className="w-(--anchor-width) max-w-[calc(100vw-2rem)]">
                  <SelectItem value="none">No one yet</SelectItem>
                  {assignees.map((person) => (
                    <SelectItem key={person.id} value={String(person.id)}>
                      {person.name}
                    </SelectItem>
                  ))}
                </SelectPopup>
              </Select>
              <FieldError />
            </Field>
            <Field name="inquiry">
              <FieldLabel>
                What they asked about <span className="font-normal text-muted-foreground">(optional)</span>
              </FieldLabel>
              <Textarea
                name="inquiry"
                rows={3}
                maxLength={5000}
                value={inquiry}
                onChange={(event) => setInquiry(event.target.value)}
              />
              <FieldError />
            </Field>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={!name.trim()}>
              Save details
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function FollowUpDialog({
  gymId,
  customer,
  assignees,
  defaultAssignee,
  onClose,
  onSaved,
}: {
  gymId: number
  customer: Customer
  assignees: StaffChoice[]
  defaultAssignee: string
  onClose: () => void
  onSaved: () => void
}) {
  const [dueAt, setDueAt] = useState(defaultFollowUpDue)
  const [note, setNote] = useState('')
  const [assignedTo, setAssignedTo] = useState(defaultAssignee)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setErrors({})
    if (!assignedTo) {
      setErrors({ assigned_to: ['Choose who owns this follow-up.'] })
      return
    }
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/customers/${customer.id}/follow-ups`, {
        body: {
          due_at: dateTimeForApi(dueAt),
          note: note.trim(),
          assigned_to: Number(assignedTo),
        },
      })
      toastManager.add({ title: 'Follow-up added.', type: 'success' })
      onSaved()
    } catch (reason) {
      setErrors(formErrors(reason, 'The follow-up could not be added.', ['due_at', 'note', 'assigned_to']))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Add follow-up</DialogTitle>
            <DialogDescription>{customer.name}. The assignee is notified.</DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            <TextField
              name="due_at"
              label="Due"
              type="datetime-local"
              value={dueAt}
              onChange={(event) => setDueAt(event.target.value)}
              required
            />
            <Field name="note">
              <FieldLabel>What needs doing?</FieldLabel>
              <Textarea
                name="note"
                rows={3}
                maxLength={5000}
                value={note}
                onChange={(event) => setNote(event.target.value)}
                required
                placeholder="For example: call back about the trial class."
              />
              <FieldError />
            </Field>
            <Choice
              name="assigned_to"
              label="Assigned to"
              value={assignedTo}
              choices={assignees.map((person) => ({ value: String(person.id), label: person.name }))}
              onChange={setAssignedTo}
              required
              description={
                assignees.length > 1
                  ? 'They receive a notification when this is saved.'
                  : 'Only you are listed here. Staff choices come from the staff attendance list.'
              }
            />
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={!assignedTo || !note.trim() || !dueAt}>
              Add follow-up
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function DoneFollowUpDialog({
  gymId,
  followUp,
  customerName,
  onClose,
  onSaved,
}: {
  gymId: number
  followUp: CustomerFollowUp
  customerName: string
  onClose: () => void
  onSaved: () => void
}) {
  const [outcome, setOutcome] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setErrors({})
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/follow-ups/${followUp.id}`, {
        method: 'PUT',
        body: { status: 'done', outcome: outcome.trim() },
      })
      toastManager.add({ title: 'Follow-up marked done.', type: 'success' })
      onSaved()
    } catch (reason) {
      setErrors(formErrors(reason, 'The follow-up could not be updated.', ['outcome']))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Mark follow-up done</DialogTitle>
            <DialogDescription>{customerName}. The outcome is kept with your name and the time.</DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            <div className="rounded-xl border bg-muted/40 p-3 text-sm">
              <p className="font-medium">Due {dateTimeLabel(followUp.due_at)}</p>
              <p className="mt-1 whitespace-pre-line break-words text-muted-foreground">{followUp.note}</p>
            </div>
            <Field name="outcome">
              <FieldLabel>What happened?</FieldLabel>
              <Textarea
                name="outcome"
                rows={3}
                maxLength={5000}
                value={outcome}
                onChange={(event) => setOutcome(event.target.value)}
                required
                autoFocus
                placeholder="For example: booked a trial class for Thursday."
              />
              <FieldError />
            </Field>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={!outcome.trim()}>
              Mark done
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function ReassignFollowUpDialog({
  gymId,
  followUp,
  customerName,
  assignees,
  onClose,
  onSaved,
}: {
  gymId: number
  followUp: CustomerFollowUp
  customerName: string
  assignees: StaffChoice[]
  onClose: () => void
  onSaved: () => void
}) {
  const [assignedTo, setAssignedTo] = useState(String(followUp.assigned_to))
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setErrors({})
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/follow-ups/${followUp.id}`, {
        method: 'PUT',
        body: { assigned_to: Number(assignedTo) },
      })
      toastManager.add({ title: 'Follow-up reassigned.', type: 'success' })
      onSaved()
    } catch (reason) {
      setErrors(formErrors(reason, 'The follow-up could not be reassigned.', ['assigned_to']))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Reassign follow-up</DialogTitle>
            <DialogDescription>{customerName}. The new owner is notified.</DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            <Choice
              name="assigned_to"
              label="Assign to"
              value={assignedTo}
              choices={assignees.map((person) => ({ value: String(person.id), label: person.name }))}
              onChange={setAssignedTo}
              required
            />
            <div className="rounded-xl border bg-muted/40 p-3 text-sm">
              <p className="font-medium">Due {dateTimeLabel(followUp.due_at)}</p>
              <p className="mt-1 whitespace-pre-line break-words text-muted-foreground">{followUp.note}</p>
            </div>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={!assignedTo}>
              Reassign
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function AddToGroupDialog({
  gymId,
  customer,
  groups,
  onClose,
  onSaved,
}: {
  gymId: number
  customer: Customer
  groups: CustomerGroup[]
  onClose: () => void
  onSaved: () => void
}) {
  const [groupId, setGroupId] = useState(groups[0] ? String(groups[0].id) : '')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setErrors({})
    const group = groups.find((candidate) => String(candidate.id) === groupId)
    if (!group) {
      setErrors({ group_id: ['Choose a group.'] })
      return
    }
    const ids = groupMemberIds(group)
    if (ids === null && (group.members_count ?? 0) > 0) {
      setErrors({
        group_id: ['This group did not include its member list, so it cannot be updated safely. Refresh and try again.'],
      })
      return
    }
    setBusy(true)
    try {
      await syncGroupMembers(gymId, group.id, [...(ids ?? []), customer.id])
      toastManager.add({ title: `Added to ${group.name}.`, type: 'success' })
      onSaved()
    } catch (reason) {
      setErrors(formErrors(reason, 'The group could not be changed.', ['group_id']))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Add to group</DialogTitle>
            <DialogDescription>{customer.name} joins the group's current members.</DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            {groups.length === 0 ? (
              <p className="text-muted-foreground text-sm">
                This customer is already in every group. Create a new group to start another audience.
              </p>
            ) : (
              <Field name="group_id">
                <FieldLabel>Group</FieldLabel>
                <Select
                  items={groups.map((group) => ({ value: String(group.id), label: `${group.name} · ${groupCountLabel(group)}` }))}
                  value={groupId || null}
                  onValueChange={(next) => setGroupId(next ?? '')}
                >
                  <SelectTrigger aria-label="Group">
                    <SelectValue placeholder="Choose a group" />
                  </SelectTrigger>
                  <SelectPopup className="w-(--anchor-width) max-w-[calc(100vw-2rem)]">
                    {groups.map((group) => (
                      <SelectItem key={group.id} value={String(group.id)} className="whitespace-normal break-words">
                        {group.name} · {groupCountLabel(group)}
                      </SelectItem>
                    ))}
                  </SelectPopup>
                </Select>
                <FieldError />
              </Field>
            )}
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={groups.length === 0 || !groupId}>
              Add to group
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function CreateGroupDialog({
  gymId,
  customer,
  onClose,
  onSaved,
}: {
  gymId: number
  customer: Customer
  onClose: () => void
  onSaved: () => void
}) {
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setErrors({})
    setBusy(true)
    try {
      const result = await api<{ group: CustomerGroup }>(`/gyms/${gymId}/customer-groups`, {
        body: { name: name.trim(), description: description.trim() || null },
      })
      let added = true
      try {
        await syncGroupMembers(gymId, result.group.id, [customer.id])
      } catch {
        added = false
      }
      toastManager.add({
        title: added
          ? 'Group created and customer added.'
          : 'Group created, but the customer could not be added. Add them from the Groups card.',
        type: added ? 'success' : 'error',
      })
      onSaved()
    } catch (reason) {
      setErrors(formErrors(reason, 'The group could not be created.', ['name', 'description']))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>New group</DialogTitle>
            <DialogDescription>
              {customer.name} is added after the group is created. Groups can become announcement audiences later.
            </DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            <TextField
              name="name"
              label="Group name"
              value={name}
              onChange={(event) => setName(event.target.value)}
              maxLength={80}
              required
              autoFocus
              placeholder="For example: Beginner course leads"
            />
            <Field name="description">
              <FieldLabel>
                Description <span className="font-normal text-muted-foreground">(optional)</span>
              </FieldLabel>
              <Textarea
                name="description"
                rows={2}
                maxLength={1000}
                value={description}
                onChange={(event) => setDescription(event.target.value)}
                placeholder="Who belongs in this group and why."
              />
              <FieldError />
            </Field>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={!name.trim()}>
              Create group
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
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
        value={value || null}
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
