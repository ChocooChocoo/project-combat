import { Plus } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { AlertDialog, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { MODULE_LABELS, moduleLabel, type CatalogPermission } from '@/lib/workspace'

type AdminPermission = CatalogPermission & { status: 'active' | 'inactive'; is_system: boolean; roles_count: number }
type PendingAction = { permission: AdminPermission; remove: boolean }

export default function AdminPermissions() {
  const [permissions, setPermissions] = useState<AdminPermission[] | null>(null)
  const [loadError, setLoadError] = useState('')
  const [version, setVersion] = useState(0)
  const [editing, setEditing] = useState<AdminPermission | 'new' | null>(null)
  const [pending, setPending] = useState<PendingAction | null>(null)
  const [busy, setBusy] = useState(false)
  const [search, setSearch] = useState('')

  useEffect(() => {
    let live = true
    api<{ permissions: AdminPermission[] }>('/admin/permissions')
      .then((result) => { if (live) { setPermissions(result.permissions); setLoadError('') } })
      .catch((error) => { if (live) setLoadError(error instanceof ApiError ? error.message : 'The catalog could not be loaded.') })
    return () => { live = false }
  }, [version])

  const reload = () => setVersion((value) => value + 1)
  const rows = permissions?.filter((permission) => `${permission.name} ${permission.permission_string} ${moduleLabel(permission.module)}`.toLowerCase().includes(search.toLowerCase())) ?? []

  async function confirmAction() {
    if (!pending || busy) return
    setBusy(true)
    try {
      await api(`/admin/permissions/${pending.permission.id}${pending.remove ? '' : '/toggle-status'}`, { method: pending.remove ? 'DELETE' : 'POST' })
      toastManager.add({ title: pending.remove ? 'Permission deleted.' : 'Permission status changed.', type: 'success' })
      setPending(null)
      reload()
    } catch (error) {
      toastManager.add({ title: error instanceof ApiError ? error.message : 'The permission could not be changed.', type: 'error' })
    } finally { setBusy(false) }
  }

  return <div className="flex flex-col gap-6">
    <Link to="/admin" className="w-fit text-sm underline underline-offset-4">Back to applications</Link>
    <PageHeader title="Permission catalog" description="Every gym builds roles from this list. Turning a permission off removes its access from everyone, including owners."
      actions={<Button onClick={() => setEditing('new')}><Plus aria-hidden />New permission</Button>} />
    <Alert><AlertTitle>New permissions need code</AlertTitle><AlertDescription>A permission added here can be selected on roles, but guards nothing until a developer adds its exact key to config/permissions.php, adds a code check, and syncs the catalog.</AlertDescription></Alert>
    <Field><FieldLabel>Find permissions</FieldLabel><Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search names or permission keys" /></Field>
    {loadError ? <Alert variant="error"><AlertTitle>The catalog could not be loaded</AlertTitle><AlertDescription><p>{loadError}</p><Button variant="outline" onClick={reload}>Try again</Button></AlertDescription></Alert>
      : permissions === null ? <div className="flex flex-col gap-2" aria-busy="true"><Skeleton className="h-10" /><Skeleton className="h-10" /><Skeleton className="h-10" /></div>
        : rows.length === 0 ? <Empty className="rounded-xl border"><EmptyHeader><EmptyTitle>No matching permissions</EmptyTitle><EmptyDescription>Change the search or add a permission.</EmptyDescription></EmptyHeader></Empty>
          : <div className="overflow-x-auto"><Table variant="card">
            <TableHeader><TableRow><TableHead>Module</TableHead><TableHead>Permission</TableHead><TableHead>Key</TableHead><TableHead>Roles</TableHead><TableHead className="text-right">Actions</TableHead></TableRow></TableHeader>
            <TableBody>{rows.map((permission) => <TableRow key={permission.id}>
              <TableCell>{moduleLabel(permission.module)}</TableCell>
              <TableCell><div className="flex flex-wrap items-center gap-2">{permission.name}{!permission.is_system && <Badge variant="outline">Added</Badge>}<Badge variant={permission.status === 'active' ? 'secondary' : 'outline'}>{permission.status === 'active' ? 'On' : 'Off'}</Badge></div>{permission.description && <p className="pt-1 text-muted-foreground text-sm">{permission.description}</p>}</TableCell>
              <TableCell className="font-mono text-xs">{permission.permission_string}</TableCell><TableCell>{permission.roles_count}</TableCell>
              <TableCell><div className="flex justify-end gap-1">
                <Button variant="ghost" onClick={() => setEditing(permission)}>Rename</Button>
                <Button variant="ghost" onClick={() => setPending({ permission, remove: false })}>{permission.status === 'active' ? 'Turn off' : 'Turn on'}</Button>
                {!permission.is_system && permission.roles_count === 0 && <Button variant="destructive-outline" onClick={() => setPending({ permission, remove: true })}>Delete</Button>}
              </div></TableCell>
            </TableRow>)}</TableBody>
          </Table></div>}
    {editing && <PermissionDialog permission={editing === 'new' ? null : editing} onClose={() => setEditing(null)} onSaved={() => { setEditing(null); reload() }} />}
    <AlertDialog open={Boolean(pending)} onOpenChange={(open) => !open && !busy && setPending(null)}><AlertDialogContent>
      <AlertDialogHeader><AlertDialogTitle>{pending?.remove ? 'Delete this permission?' : `Turn this permission ${pending?.permission.status === 'active' ? 'off' : 'on'}?`}</AlertDialogTitle>
        <AlertDialogDescription>{pending?.permission.permission_string}. {pending?.remove ? 'This unused custom permission will be removed.' : 'This affects every gym. Existing role selections stay in place; turning access back on restores those grants.'}</AlertDialogDescription></AlertDialogHeader>
      <AlertDialogFooter><Button variant="outline" disabled={busy} onClick={() => setPending(null)}>Cancel</Button><Button variant="destructive" loading={busy} onClick={confirmAction}>Confirm change</Button></AlertDialogFooter>
    </AlertDialogContent></AlertDialog>
  </div>
}

function PermissionDialog({ permission, onClose, onSaved }: { permission: AdminPermission | null; onClose: () => void; onSaved: () => void }) {
  const [values, setValues] = useState({ module: permission?.module ?? 'inventory', resource: permission?.resource ?? '', action: permission?.action ?? '', name: permission?.name ?? '', description: permission?.description ?? '' })
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)
  const set = (key: keyof typeof values) => (event: React.ChangeEvent<HTMLInputElement>) => setValues((current) => ({ ...current, [key]: event.target.value }))

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    setErrors({})
    try {
      await api(`/admin/permissions${permission ? `/${permission.id}` : ''}`, { method: permission ? 'PUT' : 'POST', body: permission ? { name: values.name, description: values.description || null } : { ...values, name: values.name || null, description: values.description || null } })
      toastManager.add({ title: 'Permission saved.', type: 'success' })
      onSaved()
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
      if (!(error instanceof ApiError) || !Object.keys(error.errors).length) toastManager.add({ title: error instanceof ApiError ? error.message : 'The permission could not be saved.', type: 'error' })
    } finally { setBusy(false) }
  }

  return <Dialog open onOpenChange={(open) => !open && onClose()}><DialogContent>
    <DialogHeader><DialogTitle>{permission ? 'Rename permission' : 'New permission'}</DialogTitle><DialogDescription>{permission?.permission_string ?? 'Use lowercase words joined by underscores. Module names are fixed.'}</DialogDescription></DialogHeader>
    <Form errors={errors} onSubmit={save}><DialogPanel className="flex flex-col gap-4">
      {Object.keys(errors).length > 0 && <p role="alert" className="text-destructive-foreground text-sm">{[...new Set(Object.values(errors).flat())].join(' ')}</p>}
      {!permission && <>
        <Field name="module"><FieldLabel>Module</FieldLabel><Select value={values.module} items={MODULE_LABELS} onValueChange={(value) => setValues((current) => ({ ...current, module: String(value) }))}><SelectTrigger><SelectValue /></SelectTrigger><SelectPopup>{Object.entries(MODULE_LABELS).map(([key, label]) => <SelectItem key={key} value={key}>{label}</SelectItem>)}</SelectPopup></Select><FieldError /></Field>
        {(['resource', 'action'] as const).map((key) => <Field key={key} name={key}><FieldLabel>{key === 'resource' ? 'Resource' : 'Action'}</FieldLabel><Input name={key} value={values[key]} onChange={set(key)} required maxLength={key === 'resource' ? 60 : 30} pattern="[a-z][a-z_]*" /><FieldError /></Field>)}
      </>}
      <Field name="name"><FieldLabel>Name</FieldLabel><Input name="name" value={values.name} onChange={set('name')} required={Boolean(permission)} maxLength={100} /><FieldError /></Field>
      <Field name="description"><FieldLabel>Description</FieldLabel><Input name="description" value={values.description} onChange={set('description')} maxLength={500} /><FieldError /></Field>
    </DialogPanel><DialogFooter><Button type="button" variant="outline" onClick={onClose}>Cancel</Button><Button type="submit" loading={busy}>Save permission</Button></DialogFooter></Form>
  </DialogContent></Dialog>
}
