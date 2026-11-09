import { useEffect, useState } from 'react'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { CheckboxGroup } from '@/components/ui/checkbox-group'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogPanel,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { TextField } from '@/components/text-field'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import type { GymRole, StaffMember } from '@/lib/workspace'
import { cn } from '@/lib/utils'

/** R-22: add a person by email. Existing accounts keep their details and gain this gym's roles. */
export function AddEmployeeDialog({
  gymId,
  roles,
  canAssign,
  onClose,
  onSaved,
}: {
  gymId: number
  roles: GymRole[]
  canAssign: boolean
  onClose: () => void
  onSaved: () => void
}) {
  const [email, setEmail] = useState('')
  const [firstName, setFirstName] = useState('')
  const [lastName, setLastName] = useState('')
  const [jobTitle, setJobTitle] = useState('')
  const [roleIds, setRoleIds] = useState<string[]>([])
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/staff`, {
        body: {
          email,
          ...(firstName.trim() ? { first_name: firstName.trim() } : {}),
          ...(lastName.trim() ? { last_name: lastName.trim() } : {}),
          job_title: jobTitle || null,
          ...(canAssign ? { role_ids: roleIds.map(Number) } : {}),
        },
      })
      toastManager.add({ title: 'Employee added.', type: 'success' })
      onSaved()
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
      if (!(error instanceof ApiError) || Object.keys(error.errors).length === 0) {
        toastManager.add({
          title: error instanceof ApiError ? error.message : 'The employee could not be added.',
          type: 'error',
        })
      }
    } finally {
      setBusy(false)
    }
  }

  const messages = [...new Set(Object.values(errors).flat())]

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Add employee</DialogTitle>
          <DialogDescription>They receive an invitation to set their own password. No password is shown here.</DialogDescription>
        </DialogHeader>
        <Form errors={errors} onSubmit={save}>
          <DialogPanel className="flex flex-col gap-4">
            {messages.length > 0 && (
              <p role="alert" className="text-destructive-foreground text-sm">
                {messages.join(' ')}
              </p>
            )}

            <TextField
              name="email"
              label="Email"
              type="email"
              autoComplete="email"
              maxLength={255}
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              autoFocus
            />
            <p className="text-muted-foreground text-sm">
              {canAssign ? 'For an existing account, the selected roles replace its current roles in this gym.' : 'Existing accounts keep their current roles. An authorized role manager can assign access later.'}
            </p>

            <div className="grid gap-4 sm:grid-cols-2">
              <TextField
                name="first_name"
                label="First name"
                autoComplete="given-name"
                maxLength={100}
                value={firstName}
                onChange={(e) => setFirstName(e.target.value)}
                description="Needed when this email has no account yet."
              />
              <TextField
                name="last_name"
                label="Last name"
                autoComplete="family-name"
                maxLength={100}
                value={lastName}
                onChange={(e) => setLastName(e.target.value)}
                description="Needed when this email has no account yet."
              />
            </div>

            <TextField
              name="job_title"
              label="Job title"
              maxLength={100}
              value={jobTitle}
              onChange={(e) => setJobTitle(e.target.value)}
              placeholder="Coach"
            />

            {canAssign && <Field name="role_ids" className="w-full">
              <FieldLabel>Roles</FieldLabel>
              {roles.length === 0 ? (
                <FieldDescription>No roles exist yet. You can add this person now and give roles after creating one.</FieldDescription>
              ) : (
                <CheckboxGroup value={roleIds} onValueChange={setRoleIds} className="grid w-full gap-x-4 gap-y-3 sm:grid-cols-2">
                  {roles.map((role) => (
                    <label key={role.id} className="flex items-center gap-2 text-sm">
                      <Checkbox value={String(role.id)} aria-label={role.name} />
                      {role.name}
                    </label>
                  ))}
                </CheckboxGroup>
              )}
              <FieldError />
            </Field>}
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy}>
              Add employee
            </Button>
          </DialogFooter>
        </Form>
      </DialogContent>
    </Dialog>
  )
}

/** R-22: replace one employee's roles. The server still checks that no role is above the editor's own access. */
export function AssignRolesDialog({
  gymId,
  employee,
  roles,
  ownPermissions,
  onClose,
  onSaved,
}: {
  gymId: number
  employee: StaffMember
  roles: GymRole[]
  ownPermissions: string[]
  onClose: () => void
  onSaved: () => void
}) {
  const [selected, setSelected] = useState<string[] | null>(null)
  const [grants, setGrants] = useState<GymRole[]>([])
  const [expiryChanges, setExpiryChanges] = useState<Record<string, string | null>>({})
  const [today] = useState(() => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date()))
  const [errors, setErrors] = useState<FieldErrors>({})
  const [loadError, setLoadError] = useState('')
  const [version, setVersion] = useState(0)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let live = true
    setLoadError('')
    api<{ roles: GymRole[] }>(`/gyms/${gymId}/staff/${employee.id}/roles`)
      .then((result) => {
        if (live) {
          setSelected(result.roles.map((role) => String(role.id)))
          setGrants(result.roles)
          setExpiryChanges({})
        }
      })
      .catch((error) => {
        if (live) setLoadError(error instanceof ApiError ? error.message : 'This person\'s roles could not be loaded.')
      })
    return () => {
      live = false
    }
  }, [gymId, employee.id, version])

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/staff/${employee.id}/roles`, {
        method: 'PUT',
        body: { role_ids: (selected ?? []).map(Number), expires_at: Object.fromEntries(Object.entries(expiryChanges).filter(([id]) => selected?.includes(id))) },
      })
      toastManager.add({ title: 'Roles updated.', type: 'success' })
      onSaved()
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
      if (!(error instanceof ApiError) || Object.keys(error.errors).length === 0) {
        toastManager.add({
          title: error instanceof ApiError ? error.message : 'The roles could not be changed.',
          type: 'error',
        })
      }
    } finally {
      setBusy(false)
    }
  }

  const messages = [...new Set(Object.values(errors).flat())]
  const initials = `${employee.first_name[0] ?? ''}${employee.last_name[0] ?? ''}`

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="sm:max-w-xl">
        <DialogHeader>
          <div className="flex items-start gap-3">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted font-medium text-sm" aria-hidden>{initials}</span>
            <div className="flex min-w-0 flex-1 flex-col">
              <DialogTitle>Edit roles</DialogTitle>
              <DialogDescription className="mt-1">
                {employee.first_name} {employee.last_name} · <span className="wrap-anywhere">{employee.email}</span>
              </DialogDescription>
              <p className="mt-1 text-muted-foreground text-xs">They will have exactly these roles. This replaces what they have now.</p>
            </div>
          </div>
        </DialogHeader>
        <Form errors={errors} onSubmit={save}>
          <DialogPanel className="flex flex-col gap-5">
            {loadError ? (
              <Alert variant="error">
                <AlertTitle>Roles could not be loaded</AlertTitle>
                <AlertDescription>
                  <p>{loadError}</p>
                  <Button size="sm" variant="outline" onClick={() => setVersion((value) => value + 1)}>
                    Try again
                  </Button>
                </AlertDescription>
              </Alert>
            ) : selected === null ? (
              <div className="flex flex-col gap-2" aria-busy="true">
                <Skeleton className="h-6 w-40" />
                <Skeleton className="h-6 w-52" />
                <Skeleton className="h-6 w-44" />
              </div>
            ) : (
              <>
                {messages.length > 0 && (
                  <p role="alert" className="text-destructive-foreground text-sm">
                    {messages.join(' ')}
                  </p>
                )}
                <Field name="role_ids" className="w-full">
                  <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <FieldLabel>Roles</FieldLabel>
                    {roles.length > 0 && (
                      <span className="text-muted-foreground text-xs tabular-nums">
                        {selected.length} of {roles.length} selected
                      </span>
                    )}
                  </div>
                  {roles.length === 0 ? (
                    <FieldDescription>No roles exist yet. Create one on the Roles tab first.</FieldDescription>
                  ) : (
                    <div className="mt-2 flex w-full flex-col gap-2">
                      {roles.map((role) => {
                        const id = String(role.id)
                        const checked = selected.includes(id)
                        const grant = grants.find((value) => value.id === role.id)
                        const original = grant?.expires_at?.slice(0, 10) ?? ''
                        const expired = original !== '' && original < today
                        const canAdd = role.status === 'active' && role.permissions.every((key) => ownPermissions.includes(key))
                        const canRemove = role.permissions.every((key) => role.inactive_permissions.includes(key) || ownPermissions.includes(key))
                        const dateValue = Object.hasOwn(expiryChanges, id) ? expiryChanges[id] ?? '' : expired ? '' : original
                        return (
                          <div key={role.id} className={cn('w-full rounded-lg border p-3 transition-colors has-focus-visible:ring-2 has-focus-visible:ring-ring has-focus-visible:ring-offset-1 hover:bg-muted/30', checked && 'border-ring/40 bg-muted/40 hover:bg-muted/50')}>
                            <label className="flex w-full cursor-pointer items-start gap-3 data-disabled:cursor-not-allowed">
                              <Checkbox
                                className="mt-0.5 size-5 shrink-0 rounded-md sm:size-5"
                                aria-label={role.name}
                                checked={checked}
                                disabled={checked ? !canRemove : !canAdd}
                                onCheckedChange={(value) => setSelected((current) => value ? [...(current ?? []), id] : (current ?? []).filter((key) => key !== id))}
                              />
                              <span className="min-w-0 flex-1">
                                <span className="flex flex-wrap items-center gap-2 font-medium text-sm">
                                  <span className="wrap-anywhere">{role.name}</span>
                                  {role.status === 'inactive' && <Badge variant="outline">Off</Badge>}
                                </span>
                                <span className="mt-0.5 block text-muted-foreground text-xs leading-relaxed">
                                  {role.permissions.length} {role.permissions.length === 1 ? 'permission' : 'permissions'}
                                  {role.description ? ` · ${role.description}` : ''}
                                </span>
                              </span>
                            </label>
                            {checked && (
                              <div className="mt-3 border-t pt-3 ps-8">
                                <Field name={`expires_at.${id}`}>
                                  <FieldLabel>Last day for {role.name}</FieldLabel>
                                  {expired && !Object.hasOwn(expiryChanges, id) && <FieldDescription>Expired on {original}. Leave unchanged to keep this historical grant.</FieldDescription>}
                                  <Input type="date" min={today} value={dateValue} disabled={!canAdd} onChange={(event) => setExpiryChanges((current) => ({ ...current, [id]: event.target.value || null }))} />
                                  <FieldDescription>{canAdd ? 'Blank means no end date when changed. Unchanged grants keep their current end date.' : 'This dormant grant can be preserved or removed, not extended.'}</FieldDescription>
                                  <FieldError />
                                </Field>
                              </div>
                            )}
                          </div>
                        )
                      })}
                    </div>
                  )}
                  <FieldError />
                </Field>
              </>
            )}
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={selected === null}>
              Save roles
            </Button>
          </DialogFooter>
        </Form>
      </DialogContent>
    </Dialog>
  )
}

/** Removing an employee ends their access now; the server checks that it is not the owner. */
export function RemoveEmployeeDialog({
  gymId,
  employee,
  onClose,
  onRemoved,
}: {
  gymId: number
  employee: StaffMember
  onClose: () => void
  onRemoved: () => void
}) {
  const [busy, setBusy] = useState(false)

  async function remove() {
    setBusy(true)
    try {
      await api(`/gyms/${gymId}/staff/${employee.id}`, { method: 'DELETE' })
      toastManager.add({ title: 'Employee removed. Their access ends now.', type: 'success' })
      onRemoved()
    } catch (error) {
      toastManager.add({
        title: error instanceof ApiError ? error.message : 'The employee could not be removed.',
        type: 'error',
      })
    } finally {
      setBusy(false)
    }
  }

  return (
    <AlertDialog open onOpenChange={(open) => !open && onClose()}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Remove this employee?</AlertDialogTitle>
          <AlertDialogDescription>
            {employee.first_name} {employee.last_name} loses access to this gym straight away. Their records stay on file.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button variant="destructive" loading={busy} onClick={remove}>
            Remove employee
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
