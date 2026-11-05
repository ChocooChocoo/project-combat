import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { AddEmployeeDialog, AssignRolesDialog, RemoveEmployeeDialog } from '@/components/gym/staff-dialogs'
import { RoleDialog } from '@/components/gym/role-dialog'
import { PageHeader } from '@/components/page-header'
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
import { Card, CardAction, CardDescription, CardHeader, CardPanel, CardTitle } from '@/components/ui/card'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
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
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Staff"
        description="The people who work at this gym, the roles they hold, and what each role can do."
      />

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

          {canView && <TabsPanel value="employees" keepMounted className="flex flex-col gap-4 pt-4">
            <div className="flex flex-wrap items-center gap-3">
              <p className="mr-auto text-muted-foreground text-sm">
                {staff.length === 0
                  ? 'No employees yet.'
                  : `${staff.length} ${staff.length === 1 ? 'employee' : 'employees'}.`}
                {owner && ` Owner: ${owner.first_name} ${owner.last_name}.`}
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
                <div className="overflow-x-auto">
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
                            <TableCell>
                              <Link
                                className="font-medium underline-offset-4 hover:underline"
                                to={`/gyms/${gymId}/manage/staff/${employee.id}`}
                              >
                                {employee.first_name} {employee.last_name}
                              </Link>
                              <span className="block pt-1 text-muted-foreground text-xs">{employee.email}</span>
                            </TableCell>
                            <TableCell>{employee.profile?.job_title || <span className="text-muted-foreground">Not set</span>}</TableCell>
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
                              <TableCell className="text-right">
                                {isOwner ? (
                                  <span className="text-muted-foreground text-xs">Owner</span>
                                ) : own ? (
                                  <span className="text-muted-foreground text-xs">You</span>
                                ) : (
                                  <div className="flex flex-wrap justify-end gap-1">
                                    {canAssign && <Button size="xs" variant="ghost" onClick={() => setAssignTarget(employee)}>
                                      Edit roles
                                    </Button>}
                                    {canManage && (employee.roles.length === 0 || canAssign) && <Button size="xs" variant="destructive-outline" onClick={() => setRemoveTarget(employee)}>
                                      Remove
                                    </Button>}
                                  </div>
                                )}
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

          {canViewRoles && <TabsPanel value="roles" keepMounted className="flex flex-col gap-4 pt-4">
            <div className="flex flex-wrap items-center gap-3">
              <p className="mr-auto text-muted-foreground text-sm">
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
              <div className="grid gap-4 md:grid-cols-2">
                {roles.map((role) => {
                  const summary = [...new Set(role.permissions.map((key) => key.split('.')[0]))]
                  return (
                    <Card key={role.id}>
                      <CardHeader>
                        <CardTitle render={<h2 />}>{role.name}</CardTitle>
                        <CardDescription>
                          {role.users_count ?? 0} {(role.users_count ?? 0) === 1 ? 'person' : 'people'}
                          {' · '}{role.permissions.length} permissions{' · '}{role.status === 'active' ? 'On' : 'Off'}
                          {role.is_system && ' · Starter role'}
                          {role.held_by_me && ' · You hold this role; another authorized person must edit it.'}
                          {role.description && <span className="block pt-1">{role.description}</span>}
                        </CardDescription>
                        {canEditRole(role) && (
                          <CardAction className="flex gap-1">
                            <Button size="xs" variant="ghost" disabled={deleting || (role.status === 'inactive' && role.permissions.some((key) => !gym.permissions.includes(key)))} onClick={() => toggleRole(role)}>
                              {role.status === 'active' ? 'Turn off' : 'Turn on'}
                            </Button>
                            <Button
                              size="icon-sm"
                              variant="ghost"
                              aria-label={`Edit ${role.name}`}
                              onClick={() => setRoleDialog({ role })}
                            >
                              <Pencil aria-hidden />
                            </Button>
                            {!role.is_system && (role.users_count ?? 0) === 0 && <Button
                              size="icon-sm"
                              variant="ghost"
                              aria-label={`Delete ${role.name}`}
                              onClick={() => setRoleDelete(role)}
                            >
                              <Trash2 aria-hidden />
                            </Button>}
                          </CardAction>
                        )}
                      </CardHeader>
                      <CardPanel className="flex flex-wrap content-start gap-1.5">
                        {summary.length === 0 ? (
                          <span className="text-muted-foreground text-sm">No permissions</span>
                        ) : (
                          summary.map((module) => (
                            <Badge key={module} variant="outline">
                              {moduleLabel(module)}: {role.permissions.filter((key) => key.startsWith(`${module}.`)).length}
                            </Badge>
                          ))
                        )}
                      </CardPanel>
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
