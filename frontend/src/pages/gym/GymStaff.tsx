import { MoreVertical, Pencil, Plus, Power, Shield, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { AddEmployeeDialog, AssignRolesDialog, RemoveEmployeeDialog } from '@/components/gym/staff-dialogs'
import { RoleDialog } from '@/components/gym/role-dialog'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Menu, MenuItem, MenuPopup, MenuTrigger } from '@/components/ui/menu'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/components/ui/tabs'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import {
  type PermissionCatalog,
  type GymRole,
  type StaffList,
  type StaffMember,
  type StaffOwner,
   moduleLabel,
} from '@/lib/workspace'

const isCoach = (employee: StaffMember) =>
  employee.is_coach === true ||
  employee.coach === true ||
  employee.roles.some((role) => role.toLowerCase().includes('coach')) ||
  (employee.profile?.job_title ?? '').toLowerCase().includes('coach') ||
  Boolean(
    employee.profile?.coach_experience ||
      employee.profile?.coach_rank ||
      employee.profile?.coach_specialisation ||
      employee.profile?.coach_bio,
  )

function initials(name: string): string {
  return name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()
}

/** S-17, R-22: the people side of the gym, with roles kept in the same place. */
export default function GymStaff() {
  const { gymId } = useParams()
  const { user, refresh } = useAuth()
  const gym = approvedGym(user, Number(gymId))
  const canView = gym ? can(gym, 'hr.staff.view') : false
  const canManage = gym ? can(gym, 'hr.staff.manage') : false
  const canViewRoles = gym ? can(gym, 'hr.roles.view') : false
  const canManageRoles = gym ? can(gym, 'hr.roles.manage') : false
  const canAssign = canViewRoles && canManageRoles

  const [staff, setStaff] = useState<StaffMember[] | null>(null)
  const [owner, setOwner] = useState<StaffOwner | null>(null)
  const [roles, setRoles] = useState<GymRole[] | null>(null)
  const [templates, setTemplates] = useState<PermissionCatalog | null>(null)
  const [loadError, setLoadError] = useState('')
  const [version, setVersion] = useState(0)
  const [tab, setTab] = useState<'employees' | 'roles'>('employees')
  const [addOpen, setAddOpen] = useState(false)
  const [assignTarget, setAssignTarget] = useState<StaffMember | null>(null)
  const [removeTarget, setRemoveTarget] = useState<StaffMember | null>(null)
  const [roleDialog, setRoleDialog] = useState<{ role: GymRole | null } | null>(null)
  const [roleDelete, setRoleDelete] = useState<GymRole | null>(null)
  const [deleting, setDeleting] = useState(false)

  useEffect(() => {
    if (!gymId || (!canView && !canViewRoles)) return
    let live = true
    setLoadError('')
    Promise.all([
      canView ? api<StaffList>(`/gyms/${gymId}/staff`) : Promise.resolve({ staff: [], owner: null }),
      canViewRoles ? api<{ roles: GymRole[] }>(`/gyms/${gymId}/roles`) : Promise.resolve({ roles: [] }),
      api<PermissionCatalog>('/permissions/catalog'),
    ])
      .then(([staffResult, rolesResult, templatesResult]) => {
        if (!live) return
        setStaff(staffResult.staff)
        setOwner(staffResult.owner)
        setRoles(rolesResult.roles)
        setTemplates(templatesResult)
      })
      .catch((error) => {
        if (live) setLoadError(error instanceof ApiError ? error.message : 'The staff list could not be loaded.')
      })
    return () => {
      live = false
    }
  }, [gymId, canView, canViewRoles, version])

  const employeeActions = (employee: StaffMember, isOwner: boolean, own: boolean) => {
    if (isOwner || own) {
      return <span className="text-muted-foreground text-xs">{isOwner ? 'Owner' : 'You'}</span>
    }
    const removable = canManage && (employee.roles.length === 0 || canAssign)
    if (!canAssign && !removable) return <span className="text-muted-foreground" aria-hidden>—</span>
    return (
      <Menu>
        <MenuTrigger render={<Button size="icon-sm" variant="ghost" aria-label={`Actions for ${employee.first_name} ${employee.last_name}`} />}>
          <MoreVertical aria-hidden />
        </MenuTrigger>
        <MenuPopup align="end" className="min-w-44">
          {canAssign && (
            <MenuItem onClick={() => setAssignTarget(employee)}>
              <Shield aria-hidden /> Edit roles
            </MenuItem>
          )}
          {removable && (
            <MenuItem variant="destructive" onClick={() => setRemoveTarget(employee)}>
              <Trash2 aria-hidden /> Remove from staff
            </MenuItem>
          )}
        </MenuPopup>
      </Menu>
    )
  }

  const roleActions = (role: GymRole) => {
    if (!canEditRole(role)) return null
    const toggleBlocked = deleting || (role.status === 'inactive' && role.permissions.some((key) => !(gym?.permissions.includes(key) ?? false)))
    return (
      <Menu>
        <MenuTrigger render={<Button size="icon-sm" variant="ghost" aria-label={`Actions for ${role.name}`} />}>
          <MoreVertical aria-hidden />
        </MenuTrigger>
        <MenuPopup align="end" className="min-w-44">
          <MenuItem disabled={toggleBlocked} onClick={() => toggleRole(role)}>
            <Power aria-hidden /> {role.status === 'active' ? 'Turn role off' : 'Turn role on'}
          </MenuItem>
          <MenuItem onClick={() => setRoleDialog({ role })}>
            <Pencil aria-hidden /> Edit role
          </MenuItem>
          {!role.is_system && (role.users_count ?? 0) === 0 && (
            <MenuItem variant="destructive" onClick={() => setRoleDelete(role)}>
              <Trash2 aria-hidden /> Delete role
            </MenuItem>
          )}
        </MenuPopup>
      </Menu>
    )
  }

  if (!gym) return null

  if (!canView && !canViewRoles) {
    return (
      <Empty className="rounded-xl border">
        <EmptyHeader>
          <EmptyTitle>Staff is not available</EmptyTitle>
          <EmptyDescription>Your role does not include HR access for this gym.</EmptyDescription>
        </EmptyHeader>
      </Empty>
    )
  }

  const reload = () => { setVersion((value) => value + 1); void refresh() }
  const canEditRole = (role: GymRole) => canManageRoles && !role.held_by_me && role.permissions.every((key) =>
    role.inactive_permissions.includes(key) || gym.permissions.includes(key))

  async function toggleRole(role: GymRole) {
    if (deleting) return
    setDeleting(true)
    try {
      await api(`/gyms/${gymId}/roles/${role.id}/toggle-status`, { method: 'POST' })
      toastManager.add({ title: role.status === 'active' ? 'Role turned off.' : 'Role turned on.', type: 'success' })
      reload()
    } catch (error) {
      toastManager.add({ title: error instanceof ApiError ? error.message : 'The role status could not be changed.', type: 'error' })
    } finally { setDeleting(false) }
  }

  async function deleteRole() {
    if (!gymId || !roleDelete) return
    setDeleting(true)
    try {
      await api(`/gyms/${gymId}/roles/${roleDelete.id}`, { method: 'DELETE' })
      toastManager.add({ title: 'Role deleted.', type: 'success' })
      setRoleDelete(null)
      reload()
    } catch (error) {
      toastManager.add({
        title: error instanceof ApiError ? error.message : 'The role could not be deleted.',
        type: 'error',
      })
    } finally {
      setDeleting(false)
    }
  }

  return (
    <div className="flex min-w-0 flex-col gap-6">
      <header className="flex flex-wrap items-end justify-between gap-4 border-b pb-6">
        <div>
          <h1 className="font-semibold text-3xl tracking-tight sm:text-4xl">Staff</h1>
          <p className="mt-3 max-w-prose text-muted-foreground text-sm leading-relaxed">The people who work at this gym, the roles they hold, and what each role can do.</p>
        </div>
        {staff && (
          <div>
            <p className="text-muted-foreground text-xs">Employees</p>
            <p className="mt-1 font-semibold text-2xl tabular-nums">{staff.length}</p>
          </div>
        )}
      </header>

      {loadError && (
        <Alert variant="error">
          <AlertTitle>Staff could not be loaded</AlertTitle>
          <AlertDescription>
            <p>{loadError}</p>
            <Button size="sm" variant="outline" onClick={reload}>
              Try again
            </Button>
          </AlertDescription>
        </Alert>
      )}

      {!staff || !roles || !templates ? (
        !loadError && (
          <div className="flex flex-col gap-2" aria-busy="true">
            <Skeleton className="h-9 w-52" />
            <Skeleton className="h-11" />
            <Skeleton className="h-11" />
            <Skeleton className="h-11" />
          </div>
        )
      ) : (
        <Tabs value={!canView ? 'roles' : !canViewRoles ? 'employees' : tab} onValueChange={(value) => setTab(value as 'employees' | 'roles')}>
          <TabsList variant="underline" aria-label="Staff sections">
            {canView && <TabsTab value="employees">Employees {staff.length}</TabsTab>}
            {canViewRoles && <TabsTab value="roles">Roles {roles.length}</TabsTab>}
          </TabsList>

          {canView && <TabsPanel value="employees" keepMounted className="flex flex-col gap-4 pt-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <p className="text-muted-foreground text-sm">
                {staff.length === 0
                  ? 'No employees yet.'
                  : `${staff.length} ${staff.length === 1 ? 'employee' : 'employees'}`}
                {owner && ` · Owner: ${owner.first_name} ${owner.last_name}`}
              </p>
              {canManage && (
                <Button onClick={() => setAddOpen(true)}>
                  <Plus aria-hidden /> Add employee
                </Button>
              )}
            </div>

            {staff.length === 0 && !owner ? (
              <Empty className="rounded-xl border">
                <EmptyHeader>
                  <EmptyTitle>No employees yet</EmptyTitle>
                  <EmptyDescription>Add a person by email, give them roles, and they receive an invitation to set their own password.</EmptyDescription>
                </EmptyHeader>
                {canManage && (
                  <EmptyContent>
                    <Button onClick={() => setAddOpen(true)}>
                      <Plus aria-hidden /> Add the first employee
                    </Button>
                  </EmptyContent>
                )}
              </Empty>
            ) : (
              <div className="flex flex-col gap-4">
                <ul className="flex flex-col gap-3 md:hidden">
                  {owner && (
                    <li className="flex items-start gap-3 rounded-xl border bg-card p-4">
                      <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted font-medium text-xs" aria-hidden>{initials(`${owner.first_name} ${owner.last_name}`)}</span>
                      <div className="flex min-w-0 flex-1 flex-col">
                        <p className="font-medium wrap-anywhere">{owner.first_name} {owner.last_name}</p>
                        <p className="text-muted-foreground text-xs wrap-anywhere">{owner.email}</p>
                        <div className="mt-2"><Badge variant="secondary">Owner</Badge></div>
                      </div>
                    </li>
                  )}
                  {staff.map((employee) => {
                    const own = employee.id === user?.id
                    const isOwner = employee.roles.includes('owner')
                    return (
                      <li key={employee.id} className="flex min-w-0 flex-col gap-3 rounded-xl border bg-card p-4">
                        <div className="flex items-start gap-3">
                          <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted font-medium text-xs" aria-hidden>{initials(`${employee.first_name} ${employee.last_name}`)}</span>
                          <div className="flex min-w-0 flex-1 flex-col">
                            <Link className="font-medium wrap-anywhere" to={`/gyms/${gymId}/manage/staff/${employee.id}`}>{employee.first_name} {employee.last_name}</Link>
                            <span className="text-muted-foreground text-xs wrap-anywhere">{employee.email}</span>
                          </div>
                          {(canManage || canAssign) && employeeActions(employee, isOwner, own)}
                        </div>
                        <p className="text-sm">{employee.profile?.job_title || <span className="text-muted-foreground">No job title</span>}</p>
                        <div className="flex flex-wrap gap-1.5">
                          {employee.roles.map((role) => <Badge key={role} variant="outline">{role}</Badge>)}
                          {isCoach(employee) && <Badge variant="info">Coach</Badge>}
                        </div>
                      </li>
                    )
                  })}
                </ul>
                <div className="hidden overflow-x-auto md:block">
                  <Table variant="card">
                    <TableHeader>
                      <TableRow>
                        <TableHead>Person</TableHead>
                        <TableHead>Job title</TableHead>
                        <TableHead>Roles</TableHead>
                        <TableHead>Coach</TableHead>
                        {(canManage || canAssign) && (
                          <TableHead>
                            <span className="sr-only">Actions</span>
                          </TableHead>
                        )}
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {owner && (
                        <TableRow>
                          <TableCell>
                            <span className="font-medium">
                              {owner.first_name} {owner.last_name}
                            </span>
                            <span className="block pt-1 text-muted-foreground text-xs">{owner.email}</span>
                          </TableCell>
                          <TableCell className="text-muted-foreground">Not set</TableCell>
                          <TableCell>
                            <Badge variant="secondary">Owner</Badge>
                          </TableCell>
                          <TableCell />
                          {(canManage || canAssign) && (
                            <TableCell className="text-right">
                              <span className="text-muted-foreground text-xs">Owner</span>
                            </TableCell>
                          )}
                        </TableRow>
                      )}
                      {staff.map((employee) => {
                        const own = employee.id === user?.id
                        const isOwner = employee.roles.includes('owner')
                        return (
                          <TableRow key={employee.id}>
                            <TableCell className="max-w-56 whitespace-normal">
                              <div className="flex items-center gap-3">
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted font-medium text-xs" aria-hidden>{initials(`${employee.first_name} ${employee.last_name}`)}</span>
                                <div className="flex min-w-0 flex-col">
                                  <Link
                                    className="font-medium underline-offset-4 wrap-anywhere transition-colors hover:text-muted-foreground"
                                    to={`/gyms/${gymId}/manage/staff/${employee.id}`}
                                  >
                                    {employee.first_name} {employee.last_name}
                                  </Link>
                                  <span className="text-muted-foreground text-xs wrap-anywhere">{employee.email}</span>
                                </div>
                              </div>
                            </TableCell>
                            <TableCell className="whitespace-normal">{employee.profile?.job_title || <span className="text-muted-foreground">Not set</span>}</TableCell>
                            <TableCell>
                              {employee.roles.length > 0 ? (
                                <div className="flex flex-wrap gap-1">
                                  {employee.roles.map((role) => (
                                    <Badge key={role} variant="outline">
                                      {role}
                                    </Badge>
                                  ))}
                                </div>
                              ) : (
                                <span className="text-muted-foreground">No roles</span>
                              )}
                            </TableCell>
                            <TableCell>{isCoach(employee) && <Badge variant="info">Coach</Badge>}</TableCell>
                            {(canManage || canAssign) && (
                              <TableCell className="w-12 text-right">
                                {employeeActions(employee, isOwner, own)}
                              </TableCell>
                            )}
                          </TableRow>
                        )
                      })}
                    </TableBody>
                  </Table>
                </div>

                {staff.length === 0 && (
                  <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed px-4 py-3">
                    <p className="text-muted-foreground text-sm">No employees yet. The owner is the only person with access.</p>
                    {canManage && (
                      <Button size="sm" onClick={() => setAddOpen(true)}>
                        <Plus aria-hidden /> Add the first employee
                      </Button>
                    )}
                  </div>
                )}
              </div>
            )}
          </TabsPanel>}

          {canViewRoles && <TabsPanel value="roles" keepMounted className="flex flex-col gap-4 pt-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <p className="text-muted-foreground text-sm">
                Roles hold independent permissions. Turning a role off keeps its grants but gives no access.
              </p>
              {canManageRoles && (
                <Button onClick={() => setRoleDialog({ role: null })}>
                  <Plus aria-hidden /> New role
                </Button>
              )}
            </div>

            {roles.length === 0 ? (
              <Empty className="rounded-xl border">
                <EmptyHeader>
                  <EmptyTitle>No roles yet</EmptyTitle>
                  <EmptyDescription>Start from a template or choose permissions, then give the role to an employee.</EmptyDescription>
                </EmptyHeader>
                {canManageRoles && (
                  <EmptyContent>
                    <Button onClick={() => setRoleDialog({ role: null })}>
                      <Plus aria-hidden /> Create the first role
                    </Button>
                  </EmptyContent>
                )}
              </Empty>
            ) : (
              <div className="grid gap-5 md:grid-cols-2">
                {roles.map((role) => {
                  const summary = [...new Set(role.permissions.map((key) => key.split('.')[0]))]
                  return (
                    <Card key={role.id} className="min-w-0 gap-0 overflow-hidden p-0">
                      <div className="flex items-start justify-between gap-3 border-b bg-muted/30 px-5 py-4">
                        <div className="min-w-0">
                          <div className="flex flex-wrap items-center gap-2">
                            <h2 className="font-semibold text-base tracking-tight wrap-anywhere">{role.name}</h2>
                            <Badge variant={role.status === 'active' ? 'success' : 'secondary'}>{role.status === 'active' ? 'On' : 'Off'}</Badge>
                            {role.is_system && <Badge variant="outline">Starter</Badge>}
                          </div>
                          {role.description && <p className="mt-1 text-muted-foreground text-xs leading-relaxed wrap-anywhere">{role.description}</p>}
                        </div>
                        {roleActions(role)}
                      </div>
                      <div className="flex flex-1 flex-col gap-4 p-5">
                        <dl className="grid grid-cols-2 gap-4 text-sm">
                          <div>
                            <dt className="text-muted-foreground text-xs">People</dt>
                            <dd className="mt-1 font-medium tabular-nums">{role.users_count ?? 0}</dd>
                          </div>
                          <div>
                            <dt className="text-muted-foreground text-xs">Permissions</dt>
                            <dd className="mt-1 font-medium tabular-nums">{role.permissions.length}</dd>
                          </div>
                        </dl>
                        {role.held_by_me && (
                          <p className="rounded-lg bg-muted/50 px-3 py-2 text-muted-foreground text-xs leading-relaxed">
                            You hold this role; another authorized person must edit it.
                          </p>
                        )}
                        <div className="mt-auto border-t pt-4">
                          <p className="mb-2 text-muted-foreground text-xs">Access by module</p>
                          <div className="flex flex-wrap gap-1.5">
                            {summary.length === 0 ? (
                              <span className="text-muted-foreground text-sm">No permissions</span>
                            ) : (
                              summary.map((module) => (
                                <Badge key={module} variant="outline">
                                  {moduleLabel(module)}: {role.permissions.filter((key) => key.startsWith(`${module}.`)).length}
                                </Badge>
                              ))
                            )}
                          </div>
                        </div>
                      </div>
                    </Card>
                  )
                })}
              </div>
            )}
          </TabsPanel>}
        </Tabs>
      )}

      {addOpen && gymId && (
        <AddEmployeeDialog
          gymId={Number(gymId)}
          roles={(roles ?? []).filter((role) => role.status === 'active' && role.permissions.every((key) => gym.permissions.includes(key)))}
          canAssign={canAssign}
          onClose={() => setAddOpen(false)}
          onSaved={() => {
            setAddOpen(false)
            reload()
          }}
        />
      )}

      {assignTarget && gymId && (
        <AssignRolesDialog
          gymId={Number(gymId)}
          employee={assignTarget}
          roles={roles ?? []}
          ownPermissions={gym.permissions}
          onClose={() => setAssignTarget(null)}
          onSaved={() => {
            setAssignTarget(null)
            reload()
          }}
        />
      )}

      {removeTarget && gymId && (
        <RemoveEmployeeDialog
          gymId={Number(gymId)}
          employee={removeTarget}
          onClose={() => setRemoveTarget(null)}
          onRemoved={() => {
            setRemoveTarget(null)
            reload()
          }}
        />
      )}

      {roleDialog && gymId && templates && (
        <RoleDialog
          gymId={Number(gymId)}
          role={roleDialog.role}
          catalog={templates}
          ownPermissions={gym.permissions}
          onClose={() => setRoleDialog(null)}
          onSaved={() => {
            setRoleDialog(null)
            reload()
          }}
        />
      )}

      <AlertDialog open={Boolean(roleDelete)} onOpenChange={(open) => !open && setRoleDelete(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete this role?</AlertDialogTitle>
            <AlertDialogDescription>
              {roleDelete?.name} will be removed. People who still have it lose its access. If anyone is still assigned, the server will refuse.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <Button variant="outline" onClick={() => setRoleDelete(null)}>
              Cancel
            </Button>
            <Button variant="destructive" loading={deleting} onClick={deleteRole}>
              Delete role
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
