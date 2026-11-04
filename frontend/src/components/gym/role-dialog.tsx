import { useState } from 'react'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogPanel,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import {
  type AccessTemplates,
  type GymRole,
  LEVEL_LABELS,
  moduleLabel,
  templateLabel,
} from '@/lib/workspace'

const LEVEL_ITEMS = { none: 'None', view: 'View', manage: 'Manage', approve: 'Approve' }

/**
 * R-08: create or change one gym role. The template fills the starting levels,
 * then every module can be adjusted on its own.
 */
export function RoleDialog({
  gymId,
  role,
  templates,
  onClose,
  onSaved,
}: {
  gymId: number
  role: GymRole | null
  templates: AccessTemplates
  onClose: () => void
  onSaved: () => void
}) {
  const [name, setName] = useState(role?.name ?? '')
  const [levels, setLevels] = useState<Record<string, string>>(() =>
    Object.fromEntries(templates.modules.map((module) => [module, role?.permissions[module] ?? 'none'])),
  )
  const [template, setTemplate] = useState('none')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  const templateItems = {
    none: 'Start from scratch',
    ...Object.fromEntries(Object.keys(templates.templates).map((key) => [key, templateLabel(key)])),
  }

  function applyTemplate(key: string) {
    setTemplate(key)
    const chosen = key === 'none' ? {} : templates.templates[key] ?? {}
    setLevels(Object.fromEntries(templates.modules.map((module) => [module, chosen[module] ?? 'none'])))
  }

  function setLevel(module: string, level: string) {
    setLevels((current) => ({ ...current, [module]: level }))
  }

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    const permissions = Object.fromEntries(Object.entries(levels).filter(([, level]) => level !== 'none'))
    try {
      if (role) {
        await api(`/gyms/${gymId}/roles/${role.id}`, { method: 'PUT', body: { name, permissions } })
        toastManager.add({ title: 'Role saved.', type: 'success' })
      } else {
        await api(`/gyms/${gymId}/roles`, { body: { name, permissions } })
        toastManager.add({ title: 'Role created.', type: 'success' })
      }
      setErrors({})
      onSaved()
    } catch (error) {
      setErrors(error instanceof ApiError ? error.errors : {})
      if (!(error instanceof ApiError) || Object.keys(error.errors).length === 0) {
        toastManager.add({
          title: error instanceof ApiError ? error.message : 'The role could not be saved.',
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
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{role ? 'Edit role' : 'New role'}</DialogTitle>
          <DialogDescription>
            A role is a set of access levels. Changes apply to everyone who has it.
          </DialogDescription>
        </DialogHeader>
        <Form errors={errors} onSubmit={save}>
          <DialogPanel className="flex flex-col gap-5">
            {messages.length > 0 && (
              <p role="alert" className="text-destructive-foreground text-sm">
                {messages.join(' ')}
              </p>
            )}

            <Field name="name" className="w-full">
              <FieldLabel>Role name</FieldLabel>
              <Input name="name" value={name} onChange={(e) => setName(e.target.value)} placeholder="Head coach" required autoFocus />
              <FieldError />
            </Field>

            <Field name="template" className="w-full">
              <FieldLabel>Start from a template</FieldLabel>
              <Select value={template} onValueChange={(value) => applyTemplate(String(value ?? 'none'))} items={templateItems}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectPopup>
                  <SelectItem value="none">Start from scratch</SelectItem>
                  {Object.keys(templates.templates).map((key) => (
                    <SelectItem key={key} value={key}>
                      {templateLabel(key)}
                    </SelectItem>
                  ))}
                </SelectPopup>
              </Select>
              <FieldDescription>Picking a template replaces the levels below. You can change them after.</FieldDescription>
              <FieldError />
            </Field>

            <div className="flex flex-col gap-3">
              <p className="font-medium text-sm">Access by module</p>
              <div className="grid gap-3 sm:grid-cols-2">
                {templates.modules.map((module) => (
                  <Field key={module} name={`permissions.${module}`} className="w-full">
                    <FieldLabel>{moduleLabel(module)}</FieldLabel>
                    <Select
                      value={levels[module] ?? 'none'}
                      onValueChange={(value) => setLevel(module, String(value ?? 'none'))}
                      items={LEVEL_ITEMS}
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectPopup>
                        <SelectItem value="none">None</SelectItem>
                        {templates.levels.map((level) => (
                          <SelectItem key={level} value={level}>
                            {LEVEL_LABELS[level] ?? level}
                          </SelectItem>
                        ))}
                      </SelectPopup>
                    </Select>
                    <FieldError />
                  </Field>
                ))}
              </div>
            </div>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy}>
              {role ? 'Save role' : 'Create role'}
            </Button>
          </DialogFooter>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
