import { useState } from 'react'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { type GymRole, type PermissionCatalog, actionLabel, moduleLabel, resourceLabel } from '@/lib/workspace'

const RANK: Record<string, number> = { view: 1, manage: 2, approve: 3 }

export function RoleDialog({ gymId, role, catalog, ownPermissions, onClose, onSaved }: {
  gymId: number
  role: GymRole | null
  catalog: PermissionCatalog
  ownPermissions: string[]
  onClose: () => void
  onSaved: () => void
}) {
  const [name, setName] = useState(role?.name ?? '')
  const [description, setDescription] = useState(role?.description ?? '')
  const [selected, setSelected] = useState<string[]>(role?.permissions ?? [])
  const [template, setTemplate] = useState('none')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)
  const [search, setSearch] = useState('')
  const [editableOnly, setEditableOnly] = useState(false)
  const dormant = (role?.permissions ?? []).filter((key) => !catalog.permissions.some((p) => p.permission_string === key))
  const visible = catalog.permissions.filter((p) =>
    (!editableOnly || ownPermissions.includes(p.permission_string)) &&
    `${moduleLabel(p.module)} ${p.name} ${p.permission_string}`.toLowerCase().includes(search.toLowerCase()))
  const modules = [...new Set(visible.map((p) => p.module))]
  const items = { none: 'Start from scratch', ...Object.fromEntries(Object.entries(catalog.templates).map(([key, value]) => [key, value.name])) }

  function toggle(key: string, checked: boolean) {
    setSelected((current) => {
      if (!checked) return current.filter((value) => value !== key)
      const permission = catalog.permissions.find((p) => p.permission_string === key)
      const lower = permission && RANK[permission.action] ? catalog.permissions.filter((p) =>
        p.module === permission.module && p.resource === permission.resource &&
        RANK[p.action] <= RANK[permission.action] && ownPermissions.includes(p.permission_string)).map((p) => p.permission_string) : [key]
      return [...new Set([...current, key, ...lower])]
    })
  }

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    setErrors({})
    try {
      await api(`/gyms/${gymId}/roles${role ? `/${role.id}` : ''}`, {
        method: role ? 'PUT' : 'POST', body: { name, description: description || null, permissions: selected },
      })
      toastManager.add({ title: role ? 'Role saved.' : 'Role created.', type: 'success' })
      onSaved()
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
      if (!(error instanceof ApiError) || !Object.keys(error.errors).length) {
        toastManager.add({ title: error instanceof ApiError ? error.message : 'The role could not be saved.', type: 'error' })
      }
    } finally { setBusy(false) }
  }

  return <Dialog open onOpenChange={(open) => !open && onClose()}>
    <DialogContent className="sm:max-w-3xl">
      <DialogHeader>
        <DialogTitle>{role ? 'Edit role' : 'New role'}</DialogTitle>
        <DialogDescription>Changes apply to everyone holding this role. Permissions are independent.</DialogDescription>
      </DialogHeader>
      <Form errors={errors} onSubmit={save}>
        <DialogPanel className="flex flex-col gap-5">
          {Object.keys(errors).length > 0 && <p role="alert" className="text-destructive-foreground text-sm">{[...new Set(Object.values(errors).flat())].join(' ')}</p>}
          <Field name="name"><FieldLabel>Role name</FieldLabel><Input name="name" value={name} onChange={(e) => setName(e.target.value)} maxLength={60} required autoFocus /><FieldError /></Field>
          <Field name="description"><FieldLabel>Description</FieldLabel><Textarea name="description" value={description} onChange={(e) => setDescription(e.target.value)} maxLength={500} /><FieldError /></Field>
          <Field name="template"><FieldLabel>Start from a template</FieldLabel>
            <Select value={template} items={items} onValueChange={(value) => {
              const key = String(value ?? 'none')
              setTemplate(key)
              // Templates replace active choices; dormant grants require explicit removal.
              setSelected([...dormant.filter((p) => selected.includes(p)), ...(catalog.templates[key]?.permissions ?? []).filter((p) => ownPermissions.includes(p))])
            }}>
              <SelectTrigger><SelectValue /></SelectTrigger><SelectPopup>
                <SelectItem value="none">Start from scratch</SelectItem>
                {Object.entries(catalog.templates).map(([key, value]) => <SelectItem key={key} value={key}>{value.name}</SelectItem>)}
              </SelectPopup>
            </Select>
          </Field>
          <p className="text-muted-foreground text-sm">Selecting Manage or Approve also ticks available lower actions for convenience. Untick any action independently. Only checked permissions are saved.</p>
          <Field><FieldLabel>Find permissions</FieldLabel><Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search modules, resources or actions" /></Field>
          <label className="flex min-h-11 items-center gap-2 text-sm"><Checkbox checked={editableOnly} onCheckedChange={setEditableOnly} />Show only permissions I hold</label>
          {modules.length === 0 && <p className="text-muted-foreground text-sm">No matching permissions.</p>}
          {modules.map((module) => <fieldset key={module} className="flex min-w-0 flex-col gap-3 rounded-xl border p-4">
            <legend className="px-1 font-medium">{moduleLabel(module)}</legend>
            {[...new Set(visible.filter((p) => p.module === module).map((p) => p.resource))].map((resource) => <div key={resource} className="flex flex-wrap items-center gap-x-5 gap-y-1">
              <span className="w-full text-sm font-medium sm:w-40">{resourceLabel(resource)}</span>
              {visible.filter((p) => p.module === module && p.resource === resource).map((p) => <label key={p.id} className="flex min-h-11 items-center gap-2 text-sm" title={p.description ?? p.permission_string}>
                <Checkbox checked={selected.includes(p.permission_string)} disabled={!ownPermissions.includes(p.permission_string)} onCheckedChange={(checked) => toggle(p.permission_string, checked)} />{actionLabel(p.action)}
              </label>)}
            </div>)}
          </fieldset>)}
          {dormant.length > 0 && <fieldset className="flex min-w-0 flex-col gap-2 rounded-xl border p-4">
            <legend className="px-1 font-medium">Disabled permissions</legend>
            <p className="text-muted-foreground text-sm">These grants give no access while disabled. Keep them unchanged or untick to remove. You cannot add them again while disabled.</p>
            {dormant.map((key) => <label key={key} className="flex min-h-11 items-center gap-2 break-all text-sm"><Checkbox checked={selected.includes(key)} disabled={!selected.includes(key)} onCheckedChange={() => setSelected((current) => current.filter((p) => p !== key))} />{key}</label>)}
          </fieldset>}
          <p className="text-muted-foreground text-sm">{selected.length} permissions selected. Unavailable choices cannot be granted.</p>
        </DialogPanel>
        <DialogFooter><Button type="button" variant="outline" onClick={onClose}>Cancel</Button><Button type="submit" loading={busy}>{role ? 'Save role' : 'Create role'}</Button></DialogFooter>
      </Form>
    </DialogContent>
  </Dialog>
}
