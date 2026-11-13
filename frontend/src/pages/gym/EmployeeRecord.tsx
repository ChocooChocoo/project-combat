import { ArrowLeft, Camera } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { PerformanceNotes } from '@/components/hr/performance-notes'
import { PageHeader } from '@/components/page-header'
import { CoachCalendar } from '@/components/schedule/coach-calendar'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button, buttonVariants } from '@/components/ui/button'
import { Card, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import { DAYS, type Day } from '@/lib/gyms'
import { cn } from '@/lib/utils'
import type {
  EmployeeLeaveBalance,
  EmployeeProfile,
  LeaveRequest,
  LeaveRequestStatus,
  PayBasis,
  ScheduleWeek,
  StaffMember,
  WorkspaceOptions,
} from '@/lib/workspace'
import { fileProblem } from '@/lib/validation'

const LEAVE_STATUS: Record<LeaveRequestStatus, { label: string; variant: 'warning' | 'success' | 'error' | 'secondary' }> = {
  pending: { label: 'Pending', variant: 'warning' },
  approved: { label: 'Approved', variant: 'success' },
  rejected: { label: 'Rejected', variant: 'error' },
  cancelled: { label: 'Cancelled', variant: 'secondary' },
}

const messagesOf = (errors: FieldErrors) => [...new Set(Object.values(errors).flat())]

/** S-18, R-23, R-24 and R-26: one employee's work record, kept to the sections that matter. */
export default function EmployeeRecord() {
  const { gymId, userId } = useParams()
  const { user } = useAuth()
  const gym = approvedGym(user, Number(gymId))
  const canViewHr = gym ? can(gym, 'hr.staff.view') : false
  const canManageHr = gym ? can(gym, 'hr.staff.manage') : false
  const canReadSchedule = gym ? can(gym, 'hr.schedules.view') : false
  const canEditSchedule = gym ? can(gym, 'hr.schedules.manage') : false
  const canReadPerformance = gym ? can(gym, 'hr.performance.view') : false
  const canManagePerformance = gym ? can(gym, 'hr.performance.manage') : false
  const targetIsSelf = Number(userId) === user?.id
  const canOpen = Boolean(gym) && (canViewHr || targetIsSelf)

  const [employee, setEmployee] = useState<StaffMember | null>(null)
  const [options, setOptions] = useState<WorkspaceOptions | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [tab, setTab] = useState('employment')
  const [calendarVersion, setCalendarVersion] = useState(0)

  useEffect(() => {
    if (!gymId || !canOpen) return
    let live = true
    setError('')
    setEmployee(null)
    setOptions(null)
    Promise.all([
      api<{ employee: StaffMember }>(`/gyms/${gymId}/staff/${userId}`),
      api<{ options: WorkspaceOptions }>(`/gyms/${gymId}/options`),
    ])
      .then(([employeeResult, optionsResult]) => {
        if (!live) return
        setEmployee(employeeResult.employee)
        setOptions(optionsResult.options)
      })
      .catch((loadFailure) => {
        if (live) setError(loadFailure instanceof ApiError ? loadFailure.message : 'The employee record could not be loaded.')
      })
    return () => {
      live = false
    }
  }, [gymId, userId, canOpen, version])

  if (!gym) return null

  const backPath = canViewHr ? `/gyms/${gymId}/manage/staff` : `/gyms/${gymId}/manage/my-work`
  const backLabel = canViewHr ? 'Staff' : 'My work'
  const applyEmployee = (next: StaffMember) =>
    setEmployee((current) => (current ? { ...current, ...next } : next))

  if (!canOpen) {
    return (
      <div className="flex flex-col gap-6">
        <BackLink to={backPath} label={backLabel} />
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>This record is private</EmptyTitle>
            <EmptyDescription>You can only open your own employee record unless you have HR access.</EmptyDescription>
          </EmptyHeader>
        </Empty>
      </div>
    )
  }

  if (error) {
    return (
      <div className="flex flex-col gap-6">
        <BackLink to={backPath} label={backLabel} />
        <PageHeader title="Employee record" />
        <Alert variant="error">
          <AlertTitle>The employee record could not be loaded</AlertTitle>
          <AlertDescription>
            <p>{error}</p>
            <Button size="sm" variant="outline" onClick={() => setVersion((value) => value + 1)}>
              Try again
            </Button>
          </AlertDescription>
        </Alert>
      </div>
    )
  }

  if (!employee || !options) {
    return (
      <div className="flex flex-col gap-6" aria-busy="true">
        <Skeleton className="h-5 w-28" />
        <Skeleton className="h-10 w-72" />
        <Skeleton className="h-9 w-80" />
        <Skeleton className="h-96" />
      </div>
    )
  }

  const profile = employee.profile
  const isCoach = Boolean(
    employee.is_coach ||
      profile?.coach_experience ||
      profile?.coach_rank ||
      profile?.coach_specialisation ||
      profile?.coach_bio,
  )

  return (
    <div className="flex flex-col gap-6">
      <BackLink to={backPath} label={backLabel} />

      <PageHeader
        title={`${employee.first_name} ${employee.last_name}`}
        meta={
          <div className="flex flex-wrap items-center gap-1">
            {employee.roles.map((role) => (
              <Badge key={role} variant="outline">
                {role}
              </Badge>
            ))}
            {isCoach && <Badge variant="info">Coach</Badge>}
          </div>
        }
        description={
          <>
            {employee.email}
            {profile?.job_title ? ` · ${profile.job_title}` : ''}
          </>
        }
      />

      <Tabs value={tab} onValueChange={(value) => setTab(String(value))}>
        <TabsList variant="underline" aria-label="Employee record sections" className="max-w-full overflow-x-auto overflow-y-hidden [scrollbar-width:none]">
          <TabsTab value="employment">Employment</TabsTab>
          <TabsTab value="coach">Coach profile</TabsTab>
          {(targetIsSelf || canReadSchedule) && <TabsTab value="schedule">Schedule</TabsTab>}
          <TabsTab value="leave">Leave</TabsTab>
          {canReadPerformance && <TabsTab value="performance">Performance</TabsTab>}
        </TabsList>

        <TabsPanel value="employment" keepMounted className="pt-4">
          <EmploymentSection
            gymId={Number(gymId)}
            employee={employee}
            options={options}
            canEdit={canManageHr}
            onEmployee={applyEmployee}
          />
        </TabsPanel>

        <TabsPanel value="coach" keepMounted className="pt-4">
          <CoachSection
            gymId={Number(gymId)}
            employee={employee}
            canEdit={targetIsSelf || canManageHr}
            onEmployee={applyEmployee}
          />
        </TabsPanel>

        {(targetIsSelf || canReadSchedule) && <TabsPanel value="schedule" keepMounted className="pt-4">
          {isCoach && <div className="mb-6"><CoachCalendar gymId={Number(gymId)} userId={Number(userId)} refreshKey={calendarVersion} /></div>}
          <ScheduleSection gymId={Number(gymId)} userId={Number(userId)} options={options} canEdit={canEditSchedule} onSaved={() => setCalendarVersion((value) => value + 1)} />
        </TabsPanel>}

        <TabsPanel value="leave" keepMounted className="pt-4">
          <LeaveSection
            gymId={Number(gymId)}
            userId={Number(userId)}
            options={options}
            balances={employee.balances ?? []}
          />
        </TabsPanel>

        {canReadPerformance && (
          <TabsPanel value="performance" keepMounted className="pt-4">
            <PerformanceNotes gymId={Number(gymId)} userId={Number(userId)} canManage={canManagePerformance} />
          </TabsPanel>
        )}
      </Tabs>
    </div>
  )
}

function BackLink({ to, label }: { to: string; label: string }) {
  return (
    <Link
      to={to}
      className="inline-flex w-fit items-center gap-1.5 text-muted-foreground text-sm underline-offset-4 hover:text-foreground hover:underline"
    >
      <ArrowLeft className="size-4" aria-hidden /> Back to {label.toLowerCase()}
    </Link>
  )
}

function EmploymentSection({
  gymId,
  employee,
  options,
  canEdit,
  onEmployee,
}: {
  gymId: number
  employee: StaffMember
  options: WorkspaceOptions
  canEdit: boolean
  onEmployee: (employee: StaffMember) => void
}) {
  const profile = employee.profile
  const [jobTitle, setJobTitle] = useState(profile?.job_title ?? '')
  const [startedOn, setStartedOn] = useState(profile?.started_on ?? '')
  const [payBasis, setPayBasis] = useState<PayBasis | 'none'>(profile?.pay_basis ?? 'none')
  const [monthlyRate, setMonthlyRate] = useState(profile?.monthly_rate ?? '')
  const [sessionRate, setSessionRate] = useState(profile?.per_session_rate ?? '')
  const [ids, setIds] = useState({
    sss_number: profile?.sss_number ?? '',
    philhealth_number: profile?.philhealth_number ?? '',
    pagibig_number: profile?.pagibig_number ?? '',
  })
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  // The server omits pay fields it will not show, so their presence is the permission signal.
  // A brand-new profile has no keys yet; HR management is enough to offer the empty fields.
  const showPay = profile ? 'monthly_rate' in profile || 'per_session_rate' in profile : canEdit
  const payItems = { none: 'Not set', ...options.pay_bases }

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    try {
      const result = await api<{ employee?: StaffMember }>(`/gyms/${gymId}/staff/${employee.id}`, {
        method: 'PUT',
        body: {
          job_title: jobTitle || null,
          started_on: startedOn || null,
          pay_basis: payBasis === 'none' ? null : payBasis,
          // R-121: masked values are read-only and never sent back.
          ...Object.fromEntries(
            Object.entries(ids)
              .filter(([, value]) => !value.startsWith('****'))
              .map(([key, value]) => [key, value.trim() || null]),
          ),
          ...(showPay
            ? {
                monthly_rate: monthlyRate === '' ? null : monthlyRate,
                per_session_rate: sessionRate === '' ? null : sessionRate,
              }
            : {}),
        },
      })
      if (result.employee) onEmployee(result.employee)
      setErrors({})
      toastManager.add({ title: 'Employment details saved.', type: 'success' })
    } catch (saveFailure) {
      setErrors(saveFailure instanceof ApiError ? saveFailure.errors : {})
      if (!(saveFailure instanceof ApiError) || Object.keys(saveFailure.errors).length === 0) {
        toastManager.add({
          title: saveFailure instanceof ApiError ? saveFailure.message : 'The employment details could not be saved.',
          type: 'error',
        })
      }
    } finally {
      setBusy(false)
    }
  }

  const messages = messagesOf(errors)

  return (
    <Card>
      <CardHeader>
        <CardTitle render={<h2 />}>Employment</CardTitle>
        <CardDescription>Job title, start date and how this person is paid.</CardDescription>
      </CardHeader>
      <CardPanel>
        <Form errors={errors} onSubmit={save} className="flex flex-col gap-6">
          {messages.length > 0 && (
            <Alert variant="error">
              <AlertTitle>Employment details need fixing</AlertTitle>
              <AlertDescription>{messages.join(' ')}</AlertDescription>
            </Alert>
          )}

          <div className="grid gap-4 sm:grid-cols-2">
            <TextField
              name="job_title"
              label="Job title"
              maxLength={100}
              value={jobTitle}
              onChange={(e) => setJobTitle(e.target.value)}
              placeholder="Head coach"
              disabled={!canEdit}
            />
            <TextField
              name="started_on"
              label="Start date"
              type="date"
              value={startedOn}
              onChange={(e) => setStartedOn(e.target.value)}
              disabled={!canEdit}
            />
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field name="pay_basis" className="w-full">
              <FieldLabel>Pay basis</FieldLabel>
              <Select
                value={payBasis}
                onValueChange={(value) => setPayBasis(String(value ?? 'none') as PayBasis | 'none')}
                items={payItems}
                disabled={!canEdit}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectPopup>
                  <SelectItem value="none">Not set</SelectItem>
                  {Object.entries(options.pay_bases).map(([key, label]) => (
                    <SelectItem key={key} value={key}>
                      {label}
                    </SelectItem>
                  ))}
                </SelectPopup>
              </Select>
              <FieldDescription>Monthly, per session, or both.</FieldDescription>
              <FieldError />
            </Field>
            <div className="flex items-end">
              <p className="text-muted-foreground text-sm">
                {showPay ? 'Pay details are visible to HR, payroll and finance roles.' : 'Pay details are hidden for your access.'}
              </p>
            </div>
          </div>

          {showPay && (
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField
                name="monthly_rate"
                label="Monthly rate"
                type="number"
                min="0"
                max="9999999999.99"
                step="0.01"
                inputMode="decimal"
                value={monthlyRate}
                onChange={(e) => setMonthlyRate(e.target.value)}
                disabled={!canEdit}
              />
              <TextField
                name="per_session_rate"
                label="Rate per session"
                type="number"
                min="0"
                max="9999999999.99"
                step="0.01"
                inputMode="decimal"
                value={sessionRate}
                onChange={(e) => setSessionRate(e.target.value)}
                disabled={!canEdit}
              />
            </div>
          )}

          {/* R-121: government IDs; others only see the last 4 digits. */}
          <div className="flex flex-col gap-3">
            <h3 className="font-medium text-sm">Government IDs</h3>
            <div className="grid gap-4 sm:grid-cols-3">
              {(
                [
                  ['sss_number', 'SSS number'],
                  ['philhealth_number', 'PhilHealth number'],
                  ['pagibig_number', 'Pag-IBIG number'],
                ] as const
              ).map(([key, label]) =>
                ids[key].startsWith('****') ? (
                  <div key={key} className="flex flex-col gap-1">
                    <span className="text-sm font-medium">{label}</span>
                    <span className="text-muted-foreground font-mono text-sm">{ids[key]}</span>
                  </div>
                ) : (
                  <TextField
                    key={key}
                    name={key}
                    label={label}
                    inputMode="numeric"
                    maxLength={20}
                    description="Numbers only; dashes are fine"
                    value={ids[key]}
                    onChange={(e) => setIds((current) => ({ ...current, [key]: e.target.value }))}
                    disabled={!canEdit}
                  />
                ),
              )}
            </div>
          </div>

          {!canEdit && <p className="text-muted-foreground text-sm">Only someone with HR management can change employment details.</p>}

          {canEdit && (
            <div className="flex justify-end">
              <Button type="submit" loading={busy}>
                Save employment details
              </Button>
            </div>
          )}
        </Form>
      </CardPanel>
    </Card>
  )
}

function CoachSection({
  gymId,
  employee,
  canEdit,
  onEmployee,
}: {
  gymId: number
  employee: StaffMember
  canEdit: boolean
  onEmployee: (employee: StaffMember) => void
}) {
  const profile = employee.profile
  const [experience, setExperience] = useState(profile?.coach_experience ?? '')
  const [rank, setRank] = useState(profile?.coach_rank ?? '')
  const [specialisation, setSpecialisation] = useState(profile?.coach_specialisation ?? '')
  const [bio, setBio] = useState(profile?.coach_bio ?? '')
  const [isPublic, setIsPublic] = useState(profile?.coach_public ?? false)
  const [photo, setPhoto] = useState<File | null>(null)
  const [preview, setPreview] = useState<string | null>(null)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    return () => {
      if (preview) URL.revokeObjectURL(preview)
    }
  }, [preview])

  function choosePhoto(file: File | null) {
    const problem = file && fileProblem(file, 'image/*', 2048)
    if (problem) {
      toastManager.add({ title: problem, type: 'error' })
      return
    }
    setPhoto(file)
    setPreview(file ? URL.createObjectURL(file) : null)
  }

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    try {
      let result: { employee?: StaffMember; profile?: EmployeeProfile }
      if (photo) {
        const body = new FormData()
        body.append('coach_experience', experience)
        body.append('coach_rank', rank)
        body.append('coach_specialisation', specialisation)
        body.append('coach_bio', bio)
        body.append('coach_public', isPublic ? '1' : '0')
        body.append('photo', photo)
        // PHP only fills the file table for POST, so the request is posted and routed as PUT.
        body.append('_method', 'PUT')
        result = await api(`/gyms/${gymId}/staff/${employee.id}/profile`, { method: 'POST', body })
      } else {
        result = await api(`/gyms/${gymId}/staff/${employee.id}/profile`, {
          method: 'PUT',
          body: {
            coach_experience: experience || null,
            coach_rank: rank || null,
            coach_specialisation: specialisation || null,
            coach_bio: bio || null,
            coach_public: isPublic,
          },
        })
      }
      if (result.employee) onEmployee(result.employee)
      else if (result.profile) onEmployee({ ...employee, profile: result.profile })
      setPhoto(null)
      setPreview(null)
      setErrors({})
      toastManager.add({ title: 'Coach profile saved.', type: 'success' })
    } catch (saveFailure) {
      setErrors(saveFailure instanceof ApiError ? saveFailure.errors : {})
      if (!(saveFailure instanceof ApiError) || Object.keys(saveFailure.errors).length === 0) {
        toastManager.add({
          title: saveFailure instanceof ApiError ? saveFailure.message : 'The coach profile could not be saved.',
          type: 'error',
        })
      }
    } finally {
      setBusy(false)
    }
  }

  const initials = `${employee.first_name[0] ?? ''}${employee.last_name[0] ?? ''}`
  const messages = messagesOf(errors)

  return (
    <Card>
      <CardHeader>
        <CardTitle render={<h2 />}>Coach profile</CardTitle>
        <CardDescription>The public face athletes see on the gym page and session pages when this is on.</CardDescription>
      </CardHeader>
      <CardPanel>
        <Form errors={errors} onSubmit={save} className="flex flex-col gap-6">
          {messages.length > 0 && (
            <Alert variant="error">
              <AlertTitle>Coach profile needs fixing</AlertTitle>
              <AlertDescription>{messages.join(' ')}</AlertDescription>
            </Alert>
          )}

          <div className="flex flex-wrap items-center gap-4">
            <Avatar className="size-16">
              {(preview || profile?.coach_photo_url) && <AvatarImage src={preview ?? profile?.coach_photo_url ?? ''} alt="" />}
              <AvatarFallback>{initials}</AvatarFallback>
            </Avatar>
            <div className="flex flex-col gap-2">
              <label
                className={cn(
                  buttonVariants({ variant: 'outline', size: 'sm' }),
                  canEdit ? 'cursor-pointer' : 'pointer-events-none opacity-64',
                )}
              >
                <Camera aria-hidden /> Choose photo
                <input
                  type="file"
                  accept="image/*"
                  className="sr-only"
                  disabled={!canEdit}
                  onChange={(e) => {
                    choosePhoto(e.target.files?.[0] ?? null)
                    e.target.value = ''
                  }}
                />
              </label>
              <span className="text-muted-foreground text-xs">
                {photo ? photo.name : 'A clear head-and-shoulders photo works best.'}
              </span>
            </div>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <TextField
              name="coach_rank"
              label="Rank"
              maxLength={100}
              value={rank}
              onChange={(e) => setRank(e.target.value)}
              placeholder="Black belt"
              disabled={!canEdit}
            />
            <TextField
              name="coach_specialisation"
              label="Specialisation"
              maxLength={150}
              value={specialisation}
              onChange={(e) => setSpecialisation(e.target.value)}
              placeholder="Boxing, strength and conditioning"
              disabled={!canEdit}
            />
          </div>

          <Field name="coach_experience" className="w-full">
            <FieldLabel>Experience</FieldLabel>
            <Textarea
              name="coach_experience"
              rows={3}
              maxLength={5000}
              value={experience}
              onChange={(e) => setExperience(e.target.value)}
              disabled={!canEdit}
            />
            <FieldError />
          </Field>

          <Field name="coach_bio" className="w-full">
            <FieldLabel>Short profile</FieldLabel>
            <Textarea name="coach_bio" rows={5} maxLength={5000} value={bio} onChange={(e) => setBio(e.target.value)} disabled={!canEdit} />
            <FieldDescription>Two or three sentences athletes will read before booking.</FieldDescription>
            <FieldError />
          </Field>

          <Field name="coach_public">
            <FieldLabel>Show this profile publicly</FieldLabel>
            <div className="flex items-center gap-2">
              <Switch checked={isPublic} onCheckedChange={setIsPublic} disabled={!canEdit} />
              <span className="text-muted-foreground text-sm">{isPublic ? 'Visible to athletes' : 'Hidden from athletes'}</span>
            </div>
            <FieldError />
          </Field>

          {!canEdit && <p className="text-muted-foreground text-sm">Only this person or someone with HR management can change the coach profile.</p>}

          {canEdit && (
            <div className="flex justify-end">
              <Button type="submit" loading={busy}>
                Save coach profile
              </Button>
            </div>
          )}
        </Form>
      </CardPanel>
    </Card>
  )
}

type DaySchedule = Record<Day, { working: boolean; start: string; end: string }>

const timeValue = (value: string | null | undefined, fallback: string) => (value ? value.slice(0, 5) : fallback)

function ScheduleSection({
  gymId,
  userId,
  options,
  canEdit,
  onSaved,
}: {
  gymId: number
  userId: number
  options: WorkspaceOptions
  canEdit: boolean
  onSaved: () => void
}) {
  const [days, setDays] = useState<DaySchedule | null>(null)
  const [loadError, setLoadError] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)
  const [version, setVersion] = useState(0)

  useEffect(() => {
    let live = true
    setLoadError('')
    api<ScheduleWeek>(`/gyms/${gymId}/staff/${userId}/schedule`)
      .then((result) => {
        if (!live) return
        const byDay = new Map((result.schedule ?? []).map((row) => [row.weekday, row]))
        setDays(
          Object.fromEntries(
            DAYS.map(({ key }) => {
              const row = byDay.get(key)
              return [
                key,
                {
                  working: Boolean(row?.start_time && row?.end_time),
                  start: timeValue(row?.start_time, '09:00'),
                  end: timeValue(row?.end_time, '17:00'),
                },
              ]
            }),
          ) as DaySchedule,
        )
      })
      .catch((loadFailure) => {
        if (live) setLoadError(loadFailure instanceof ApiError ? loadFailure.message : 'The schedule could not be loaded.')
      })
    return () => {
      live = false
    }
  }, [gymId, userId, version])

  const setDay = (day: Day, change: Partial<DaySchedule[Day]>) =>
    setDays((current) => (current ? { ...current, [day]: { ...current[day], ...change } } : current))

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!days) return
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/staff/${userId}/schedule`, {
        method: 'PUT',
        body: {
          // The server wants all seven days; a day off is a row with both times empty.
          schedule: DAYS.map(({ key }) => ({
            weekday: key,
            start_time: days[key].working ? days[key].start : null,
            end_time: days[key].working ? days[key].end : null,
          })),
        },
      })
      setErrors({})
      toastManager.add({ title: 'Schedule saved.', type: 'success' })
      onSaved()
    } catch (saveFailure) {
      setErrors(saveFailure instanceof ApiError ? saveFailure.errors : {})
      if (!(saveFailure instanceof ApiError) || Object.keys(saveFailure.errors).length === 0) {
        toastManager.add({
          title: saveFailure instanceof ApiError ? saveFailure.message : 'The schedule could not be saved.',
          type: 'error',
        })
      }
    } finally {
      setBusy(false)
    }
  }

  const messages = messagesOf(errors)

  return (
    <Card>
      <CardHeader>
        <CardTitle render={<h2 />}>Weekly schedule</CardTitle>
        <CardDescription>Working hours and days off. Leave is counted in these days when they are set.</CardDescription>
      </CardHeader>
      <CardPanel>
        {loadError ? (
          <Alert variant="error">
            <AlertTitle>Schedule could not be loaded</AlertTitle>
            <AlertDescription>
              <p>{loadError}</p>
              <Button size="sm" variant="outline" onClick={() => setVersion((value) => value + 1)}>
                Try again
              </Button>
            </AlertDescription>
          </Alert>
        ) : !days ? (
          <div className="flex flex-col gap-2" aria-busy="true">
            {DAYS.map(({ key }) => (
              <Skeleton key={key} className="h-11" />
            ))}
          </div>
        ) : (
          <Form errors={errors} onSubmit={save} className="flex flex-col gap-4">
            {messages.length > 0 && (
              <Alert variant="error">
                <AlertTitle>Schedule needs fixing</AlertTitle>
                <AlertDescription>{messages.join(' ')}</AlertDescription>
              </Alert>
            )}

            <div className="divide-y rounded-lg border">
              {DAYS.map(({ key }, index) => (
                <div key={key} className="flex min-h-14 flex-wrap items-center gap-3 px-3 py-2">
                  <label className="flex w-36 items-center gap-2 text-sm">
                    <Checkbox
                      checked={days[key].working}
                      onCheckedChange={(working) => setDay(key, { working })}
                      disabled={!canEdit}
                    />
                    {options.weekdays[key] ?? key}
                  </label>
                  {days[key].working ? (
                    <div className="grid w-full grid-cols-[auto_1fr] items-start gap-x-3 gap-y-2 sm:flex sm:w-auto sm:gap-2">
                      <span className="pt-2 text-muted-foreground text-sm sm:hidden">Starts</span>
                      <Field name={`schedule.${index}.start_time`} className="min-w-0 sm:w-36">
                        <Input
                          type="time"
                          aria-label={`${options.weekdays[key] ?? key} starts`}
                          value={days[key].start}
                          onChange={(e) => setDay(key, { start: e.target.value })}
                          disabled={!canEdit}
                          required
                        />
                        <FieldError />
                      </Field>
                      <span className="pt-2 text-muted-foreground text-sm sm:pt-1.5">
                        <span className="sm:hidden">Ends</span>
                        <span className="max-sm:hidden">to</span>
                      </span>
                      <Field
                        name={`schedule.${index}.end_time`}
                        className="min-w-0 sm:w-36"
                        validate={(value) => (value && String(value) <= days[key].start ? 'Must be after the start time.' : null)}
                      >
                        <Input
                          type="time"
                          aria-label={`${options.weekdays[key] ?? key} ends`}
                          value={days[key].end}
                          onChange={(e) => setDay(key, { end: e.target.value })}
                          disabled={!canEdit}
                          required
                        />
                        <FieldError />
                      </Field>
                    </div>
                  ) : (
                    <span className="text-muted-foreground text-sm">Day off</span>
                  )}
                </div>
              ))}
            </div>

            <p className="text-muted-foreground text-sm">
              {canEdit
                ? 'Days without working hours are days off. Times use the gym\'s local clock.'
                : 'Only someone with HR management can change this schedule.'}
            </p>

            {canEdit && (
              <div className="flex justify-end">
                <Button type="submit" loading={busy}>
                  Save schedule
                </Button>
              </div>
            )}
          </Form>
        )}
      </CardPanel>
    </Card>
  )
}

function LeaveSection({
  gymId,
  userId,
  options,
  balances,
}: {
  gymId: number
  userId: number
  options: WorkspaceOptions
  balances: EmployeeLeaveBalance[]
}) {
  const [requests, setRequests] = useState<LeaveRequest[] | null>(null)
  const [loadError, setLoadError] = useState('')
  const [version, setVersion] = useState(0)

  useEffect(() => {
    let live = true
    setLoadError('')
    api<{ requests: LeaveRequest[] }>(`/gyms/${gymId}/leave-requests`)
      .then((result) => {
        if (!live) return
        setRequests(result.requests.filter((request) => request.user_id === userId).slice(0, 5))
      })
      .catch((loadFailure) => {
        if (live) setLoadError(loadFailure instanceof ApiError ? loadFailure.message : 'Leave requests could not be loaded.')
      })
    return () => {
      live = false
    }
  }, [gymId, userId, version])

  const leaveLabel = (type: string) => options.leave_types[type]?.label ?? type.replaceAll('_', ' ')

  return (
    <Card>
      <CardHeader>
        <CardTitle render={<h2 />}>Leave</CardTitle>
        <CardDescription>Balances and the latest requests for this employee.</CardDescription>
      </CardHeader>
      <CardPanel className="flex flex-col gap-6">
        {loadError && (
          <Alert variant="info">
            <AlertTitle>Recent leave requests are not available here</AlertTitle>
            <AlertDescription>
              <p>{loadError}</p>
              <div className="flex flex-wrap gap-2">
                <Button size="sm" variant="outline" onClick={() => setVersion((value) => value + 1)}>
                  Try again
                </Button>
                <Button size="sm" variant="outline" render={<Link to={`/gyms/${gymId}/manage/leave`} />}>
                  Open leave
                </Button>
              </div>
            </AlertDescription>
          </Alert>
        )}

        <section className="flex flex-col gap-3">
          <h3 className="font-medium text-sm">Balances</h3>
          {balances.length === 0 ? (
            <p className="text-muted-foreground text-sm">No leave balances have been set for this year.</p>
          ) : (
            <div className="overflow-x-auto">
              <Table variant="card">
                <TableHeader>
                  <TableRow>
                    <TableHead>Leave type</TableHead>
                    <TableHead>Allowance</TableHead>
                    <TableHead>Used</TableHead>
                    <TableHead>Remaining</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {balances.map((balance) => (
                    <TableRow key={balance.type}>
                      <TableCell className="font-medium">{balance.label || leaveLabel(balance.type)}</TableCell>
                      <TableCell className="tabular-nums">{balance.allowance}</TableCell>
                      <TableCell className="tabular-nums">{balance.used}</TableCell>
                      <TableCell className="tabular-nums">{balance.remaining}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </section>

        <section className="flex flex-col gap-3">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h3 className="font-medium text-sm">Recent requests</h3>
            <Button size="sm" variant="outline" render={<Link to={`/gyms/${gymId}/manage/leave`} />}>
              Open leave
            </Button>
          </div>
          {requests === null ? (
            loadError ? (
              <p className="text-muted-foreground text-sm">Open the leave screen for this person's requests.</p>
            ) : (
              <div className="flex flex-col gap-2" aria-busy="true">
                <Skeleton className="h-9" />
                <Skeleton className="h-9" />
              </div>
            )
          ) : requests.length === 0 ? (
            <p className="text-muted-foreground text-sm">No leave requests recorded yet.</p>
          ) : (
            <div className="overflow-x-auto">
              <Table variant="card">
                <TableHeader>
                  <TableRow>
                    <TableHead>Dates</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Working days</TableHead>
                    <TableHead>Status</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {requests.map((request) => (
                    <TableRow key={request.id}>
                      <TableCell>
                        {new Date(`${request.starts_on}T00:00:00`).toLocaleDateString()} –{' '}
                        {new Date(`${request.ends_on}T00:00:00`).toLocaleDateString()}
                      </TableCell>
                      <TableCell>{leaveLabel(request.type)}</TableCell>
                      <TableCell className="tabular-nums">{request.working_days}</TableCell>
                      <TableCell>
                        <Badge variant={LEAVE_STATUS[request.status].variant}>{LEAVE_STATUS[request.status].label}</Badge>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </section>
      </CardPanel>
    </Card>
  )
}
