import { useEffect, useRef, useState } from 'react'
import { Archive, ArrowLeftRight, History, Pencil, Plus } from 'lucide-react'
import { useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { TextField } from '@/components/text-field'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { AlertDialog, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogPopup, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Sheet, SheetDescription, SheetFooter, SheetHeader, SheetPanel, SheetPopup, SheetTitle } from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import {
  MANUAL_MOVEMENT_TYPES, MOVEMENT_TYPE, NOTE_REQUIRED, OUT_TYPES, STOCK_CATEGORY, STOCK_UNIT,
  quantityLabel, signedLabel, stockLevel,
  type OpenIssue, type StockCategory, type StockItem, type StockMovementType,
  type StockMovementsPage, type StockRecipient, type StockSummary, type StockUnit,
} from '@/lib/inventory'
import { dateTimeLabel, money } from '@/lib/membership'
import { cn } from '@/lib/utils'

const categoryChoices = Object.entries(STOCK_CATEGORY).map(([value, label]) => ({ value, label }))
const unitChoices = Object.entries(STOCK_UNIT).map(([value, labels]) => ({ value, label: labels.one }))

function message(reason: unknown, fallback: string) {
  return reason instanceof ApiError ? reason.message : fallback
}

function formErrors(reason: unknown, known: string[], fallback: string): FieldErrors {
  const errors = reason instanceof ApiError ? reason.errors : {}
  const keys = Object.keys(errors)
  if (keys.length === 0 || keys.some((key) => !known.includes(key))) {
    toastManager.add({ title: message(reason, fallback), type: 'error' })
  }
  return errors
}

function LoadingRows() {
  return <div className="flex flex-col gap-2" aria-busy="true" aria-label="Loading stock"><Skeleton className="h-12" /><Skeleton className="h-12" /><Skeleton className="h-12" /></div>
}

export default function GymStock() {
  const { gymId: gymIdParam } = useParams()
  const gymId = Number(gymIdParam)
  const { user } = useAuth()
  const gym = approvedGym(user, gymId)
  const canView = gym ? can(gym, 'inventory.items.view') : false
  const canManage = gym ? can(gym, 'inventory.items.manage') : false
  const canMove = gym ? can(gym, 'inventory.movements.manage') : false
  const [items, setItems] = useState<StockItem[] | null>(null)
  const [summary, setSummary] = useState<StockSummary | null>(null)
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const [category, setCategory] = useState('all')
  const [lowOnly, setLowOnly] = useState(false)
  const [archived, setArchived] = useState(false)
  const [itemDialog, setItemDialog] = useState<{ item: StockItem | null } | null>(null)
  const [moving, setMoving] = useState<StockItem | null>(null)
  const [historyOf, setHistoryOf] = useState<StockItem | null>(null)
  const [archiving, setArchiving] = useState<StockItem | null>(null)
  const [archiveBusy, setArchiveBusy] = useState(false)

  useEffect(() => {
    if (!gymId || !canView) return
    let live = true
    setError('')
    setItems(null)
    setSummary(null)
    const query = new URLSearchParams()
    if (category !== 'all') query.set('category', category)
    if (lowOnly) query.set('low', '1')
    if (archived) query.set('archived', '1')
    api<{ items: StockItem[]; summary: StockSummary }>(`/gyms/${gymId}/stock-items?${query}`)
      .then((result) => {
        if (!live) return
        setItems(result.items)
        setSummary(result.summary)
      })
      .catch((reason) => { if (live) setError(message(reason, 'Stock could not be loaded.')) })
    return () => { live = false }
  }, [gymId, canView, category, lowOnly, archived, version])

  const refresh = () => setVersion((value) => value + 1)
  function clearFilters() {
    setCategory('all')
    setLowOnly(false)
    setArchived(false)
  }

  async function archiveItem() {
    if (!archiving || archiveBusy) return
    setArchiveBusy(true)
    try {
      await api(`/gyms/${gymId}/stock-items/${archiving.id}/archive`, { method: 'POST' })
      toastManager.add({ title: 'Item archived.', type: 'success' })
      setArchiving(null)
      refresh()
    } catch (reason) {
      toastManager.add({ title: message(reason, 'The item could not be archived.'), type: 'error' })
    } finally {
      setArchiveBusy(false)
    }
  }

  if (!gym) return null
  if (!canView) return <div className="flex flex-col gap-6"><PageHeader title="Stock" /><Empty className="rounded-xl border"><EmptyHeader><EmptyTitle>Stock is not available</EmptyTitle><EmptyDescription>Ask the owner for the stock permission.</EmptyDescription></EmptyHeader></Empty></div>

  const filtered = category !== 'all' || lowOnly || archived
  function actions(item: StockItem, mobile = false) {
    const size = mobile ? 'sm' : 'xs'
    return <div className="flex flex-wrap items-center gap-1 md:justify-end">
      <Button size={size} variant="ghost" aria-label={`History for ${item.name}`} onClick={() => setHistoryOf(item)}><History aria-hidden />History</Button>
      {item.status === 'active' && <>
        {canMove && <Button size={size} variant="outline" aria-label={`Move ${item.name}`} onClick={() => setMoving(item)}><ArrowLeftRight aria-hidden />Move</Button>}
        {canManage && <>
          <Button size={mobile ? 'icon' : 'icon-xs'} variant="ghost" aria-label={`Edit ${item.name}`} onClick={() => setItemDialog({ item })}><Pencil aria-hidden /></Button>
          <Button size={mobile ? 'icon' : 'icon-xs'} variant="ghost" aria-label={`Archive ${item.name}`} onClick={() => setArchiving(item)}><Archive aria-hidden /></Button>
        </>}
      </>}
    </div>
  }

  return <div className="flex min-w-0 flex-col gap-6">
    <PageHeader title="Stock" description={`${gym.name}: what you have, where it is, and every change.`} actions={canManage && <Button size="sm" onClick={() => setItemDialog({ item: null })}><Plus aria-hidden />Add item</Button>} />
    {error && <Alert variant="error"><AlertTitle>Stock could not be loaded</AlertTitle><AlertDescription>{error}</AlertDescription><div className="pt-2"><Button size="sm" variant="outline" onClick={refresh}>Try again</Button></div></Alert>}
    {!error && <section aria-labelledby="stock-summary-heading">
      <h2 id="stock-summary-heading" className="sr-only">Active stock summary</h2>
      <dl className="grid grid-cols-3 gap-px overflow-hidden rounded-xl border bg-border" aria-live="polite">
        {([{ key: 'total', label: 'Items', dot: 'bg-primary' }, { key: 'low', label: 'Low stock', dot: 'bg-warning' }, { key: 'out', label: 'Out of stock', dot: 'bg-destructive' }] as const).map((cell) => <div key={cell.key} className="bg-card px-3 py-2.5">
          <dt className="flex items-center gap-1.5 text-muted-foreground text-xs"><span aria-hidden className={cn('size-2 shrink-0 rounded-full', cell.dot)} />{cell.label}</dt>
          <dd className="mt-1 font-semibold text-xl tabular-nums">{summary ? summary[cell.key] : <Skeleton className="h-7 w-10" />}</dd>
        </div>)}
      </dl>
      <p className="mt-2 text-muted-foreground text-xs">Active items only. Low stock includes items at or below their minimum.</p>
    </section>}
    <section aria-labelledby="stock-list-heading" className="flex min-w-0 flex-col gap-4">
      <h2 id="stock-list-heading" className="sr-only">Stock items</h2>
      <div className="grid items-end gap-3 sm:grid-cols-3">
        <Choice name="category-filter" label="Category" value={category} choices={[{ value: 'all', label: 'All categories' }, ...categoryChoices]} onChange={setCategory} />
        <label className="flex min-h-11 cursor-pointer items-center gap-3 text-sm"><Switch checked={lowOnly} onCheckedChange={setLowOnly} aria-label="Low stock only" />Low stock only</label>
        <label className="flex min-h-11 cursor-pointer items-center gap-3 text-sm"><Switch checked={archived} onCheckedChange={setArchived} aria-label="Show archived" />Show archived</label>
      </div>
      {!error && (items === null ? <LoadingRows /> : <>
        <p className="text-muted-foreground text-sm" aria-live="polite">{items.length} {items.length === 1 ? 'item' : 'items'}{archived ? ' archived' : ''}</p>
        {items.length === 0 ? <Empty className="rounded-xl border"><EmptyHeader>
          <EmptyTitle>{archived && category === 'all' && !lowOnly ? 'No archived items' : filtered ? 'No items match these filters' : 'No stock items yet'}</EmptyTitle>
          <EmptyDescription>{filtered ? 'Change the filters to see other stock items.' : 'Add equipment, gear, supplies and merchandise with where they are kept.'}</EmptyDescription>
        </EmptyHeader><EmptyContent>{filtered ? <Button variant="outline" onClick={clearFilters}>Clear filters</Button> : canManage && <Button onClick={() => setItemDialog({ item: null })}><Plus aria-hidden />Add item</Button>}</EmptyContent></Empty> : <>
          <ul className="flex flex-col divide-y rounded-xl border bg-card md:hidden">{items.map((item) => <li key={item.id} className="flex min-w-0 flex-col gap-3 p-4">
            <div className="min-w-0"><h3 className="break-words font-medium">{item.name}</h3>{item.sku && <p className="break-words text-muted-foreground text-xs">{item.sku}</p>}<p className="mt-1 break-words text-muted-foreground text-sm">{STOCK_CATEGORY[item.category]} · {item.location}</p></div>
            <dl className="grid grid-cols-2 gap-3 text-sm"><div><dt className="text-muted-foreground">On hand</dt><dd className="font-semibold tabular-nums">{quantityLabel(item.quantity, item.unit)}</dd></div><div><dt className="text-muted-foreground">Minimum</dt><dd className="tabular-nums">{item.min_level ?? 'None'}</dd></div></dl>
            <ItemBadges item={item} />{actions(item, true)}
          </li>)}</ul>
          <div className="hidden min-w-0 overflow-x-auto md:block"><Table variant="card" className="min-w-[56rem]">
            <TableHeader><TableRow><TableHead>Item / SKU</TableHead><TableHead>Category</TableHead><TableHead>Location</TableHead><TableHead className="text-right">Quantity</TableHead><TableHead className="text-right">Minimum</TableHead><TableHead>Status</TableHead><TableHead><span className="sr-only">Actions</span></TableHead></TableRow></TableHeader>
            <TableBody>{items.map((item) => <TableRow key={item.id}>
              <TableCell className="max-w-64 whitespace-normal break-words"><span className="font-medium">{item.name}</span>{item.sku && <span className="block text-muted-foreground text-xs">{item.sku}</span>}</TableCell>
              <TableCell>{STOCK_CATEGORY[item.category]}</TableCell><TableCell className="max-w-48 whitespace-normal break-words">{item.location}</TableCell>
              <TableCell className="text-right tabular-nums">{quantityLabel(item.quantity, item.unit)}</TableCell><TableCell className="text-right tabular-nums">{item.min_level ?? 'None'}</TableCell>
              <TableCell><ItemBadges item={item} /></TableCell><TableCell>{actions(item)}</TableCell>
            </TableRow>)}</TableBody>
          </Table></div>
        </>}
      </>)}
    </section>
    {itemDialog && canManage && <ItemDialog key={`${gymId}-${itemDialog.item?.id ?? 'new'}`} gymId={gymId} item={itemDialog.item} onClose={() => setItemDialog(null)} onSaved={() => { setItemDialog(null); refresh() }} />}
    {moving && canMove && <MovementDialog key={`${gymId}-${moving.id}`} gymId={gymId} item={moving} onClose={() => setMoving(null)} onSaved={() => { setMoving(null); refresh() }} />}
    {historyOf && <HistorySheet key={`${gymId}-${historyOf.id}`} gymId={gymId} item={historyOf} onClose={() => setHistoryOf(null)} />}
    <AlertDialog open={Boolean(archiving)} onOpenChange={(open) => { if (!open && !archiveBusy) setArchiving(null) }}>
      <AlertDialogPopup><AlertDialogHeader><AlertDialogTitle className="break-words">Archive {archiving?.name}?</AlertDialogTitle><AlertDialogDescription>It stays in the history but can no longer be moved or edited.</AlertDialogDescription></AlertDialogHeader>
        <AlertDialogFooter><Button variant="outline" disabled={archiveBusy} onClick={() => setArchiving(null)}>Cancel</Button><Button variant="destructive" loading={archiveBusy} onClick={archiveItem}>Archive item</Button></AlertDialogFooter>
      </AlertDialogPopup>
    </AlertDialog>
  </div>
}

function ItemBadges({ item }: { item: StockItem }) {
  const level = stockLevel(item)
  return <div className="flex flex-wrap gap-1"><Badge variant={item.status === 'archived' ? 'secondary' : 'outline'}>{item.status === 'archived' ? 'Archived' : 'Active'}</Badge>{item.status === 'active' && level !== 'ok' && <Badge variant={level === 'out' ? 'error' : 'warning'}>{level === 'out' ? 'Out of stock' : 'Low'}</Badge>}</div>
}

function Choice({ name, label, value, choices, onChange, required = false, disabled = false, description }: {
  name: string; label: string; value: string; choices: { value: string; label: string }[]
  onChange: (value: string) => void; required?: boolean; disabled?: boolean; description?: string
}) {
  return <Field name={name} className="min-w-0"><FieldLabel>{label}</FieldLabel>
    <Select name={name} items={choices} value={value} onValueChange={(next) => onChange(next ?? '')} required={required} disabled={disabled}>
      <SelectTrigger aria-label={label} className="w-full min-w-0"><SelectValue className="min-w-0" placeholder="Choose one" /></SelectTrigger>
      <SelectPopup className="w-(--anchor-width) max-w-[calc(100vw-2rem)]">{choices.map((choice) => <SelectItem key={choice.value} value={choice.value} className="whitespace-normal break-words">{choice.label}</SelectItem>)}</SelectPopup>
    </Select>
    {description && <FieldDescription>{description}</FieldDescription>}<FieldError />
  </Field>
}

function ItemDialog({ gymId, item, onClose, onSaved }: { gymId: number; item: StockItem | null; onClose: () => void; onSaved: () => void }) {
  const [name, setName] = useState(item?.name ?? '')
  const [sku, setSku] = useState(item?.sku ?? '')
  const [category, setCategory] = useState<StockCategory>(item?.category ?? 'equipment')
  const [unit, setUnit] = useState<StockUnit>(item?.unit ?? 'piece')
  const [location, setLocation] = useState(item?.location ?? '')
  const [openingQuantity, setOpeningQuantity] = useState('0')
  const [minLevel, setMinLevel] = useState(String(item?.min_level ?? ''))
  const [reorderQty, setReorderQty] = useState(String(item?.reorder_qty ?? ''))
  const [unitCost, setUnitCost] = useState(item?.unit_cost ?? '')
  const [salePrice, setSalePrice] = useState(item?.sale_price ?? '')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (busy) return
    setErrors({})
    if (!item && openingQuantity === '') { setErrors({ quantity: ['Enter an opening quantity. Zero is allowed.'] }); return }
    setBusy(true)
    const body = {
      name: name.trim(), sku: sku.trim() || null, category, unit, location: location.trim(),
      min_level: minLevel === '' ? null : Number(minLevel), reorder_qty: reorderQty === '' ? null : Number(reorderQty),
      unit_cost: unitCost === '' ? null : unitCost, sale_price: category === 'merchandise' && salePrice !== '' ? salePrice : null,
    }
    try {
      if (item) await api(`/gyms/${gymId}/stock-items/${item.id}`, { method: 'PUT', body })
      else await api(`/gyms/${gymId}/stock-items`, { body: { ...body, quantity: Number(openingQuantity) } })
      toastManager.add({ title: item ? 'Item saved.' : 'Item added.', type: 'success' })
      onSaved()
    } catch (reason) {
      setErrors(formErrors(reason, ['name', 'sku', 'category', 'unit', 'location', 'min_level', 'reorder_qty', 'unit_cost', ...(category === 'merchandise' ? ['sale_price'] : []), ...(!item ? ['quantity'] : [])], 'The item could not be saved.'))
    } finally { setBusy(false) }
  }

  return <Dialog open onOpenChange={(open) => { if (!open && !busy) onClose() }}><DialogPopup>
    <Form errors={errors} onSubmit={save}>
      <DialogHeader><DialogTitle>{item ? 'Edit item' : 'Add item'}</DialogTitle><DialogDescription>Keep the count and storage place clear for everyone at the gym.</DialogDescription></DialogHeader>
      <DialogPanel className="flex flex-col gap-4">
        <TextField name="name" label="Name" value={name} onChange={(event) => setName(event.target.value)} maxLength={120} required autoFocus />
        <TextField name="sku" label="SKU (optional)" value={sku} onChange={(event) => setSku(event.target.value)} maxLength={60} />
        <div className="grid gap-4 sm:grid-cols-2"><Choice name="category" label="Category" value={category} choices={categoryChoices} onChange={(value) => setCategory(value as StockCategory)} required /><Choice name="unit" label="Unit" value={unit} choices={unitChoices} onChange={(value) => setUnit(value as StockUnit)} required /></div>
        <TextField name="location" label="Storage place" value={location} onChange={(event) => setLocation(event.target.value)} maxLength={120} placeholder="Front desk cabinet" required />
        {item ? <div className="rounded-xl border p-3 text-sm"><p className="font-medium">On hand: {quantityLabel(item.quantity, item.unit)}</p><p className="text-muted-foreground">Quantity changes only through a movement.</p></div> : <TextField name="quantity" label="Opening quantity" type="number" inputMode="numeric" min={0} max={1000000} step={1} value={openingQuantity} onChange={(event) => setOpeningQuantity(event.target.value)} description="Recorded as the opening count in the history." required />}
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField name="min_level" label="Minimum level (optional)" type="number" inputMode="numeric" min={0} max={1000000} step={1} value={minLevel} onChange={(event) => setMinLevel(event.target.value)} description="Stock managers get a notice at or below this number." />
          <TextField name="reorder_qty" label="Reorder quantity (optional)" type="number" inputMode="numeric" min={1} max={1000000} step={1} value={reorderQty} onChange={(event) => setReorderQty(event.target.value)} />
          <TextField name="unit_cost" label="Unit cost (₱, optional)" type="number" inputMode="decimal" min={0} max="9999999999.99" step="0.01" value={unitCost} onChange={(event) => setUnitCost(event.target.value)} />
          {category === 'merchandise' && <TextField name="sale_price" label="Sale price (₱, optional)" type="number" inputMode="decimal" min={0} max="9999999999.99" step="0.01" value={salePrice} onChange={(event) => setSalePrice(event.target.value)} />}
        </div>
      </DialogPanel>
      <DialogFooter><Button type="button" variant="outline" disabled={busy} onClick={onClose}>Cancel</Button><Button type="submit" loading={busy}>{item ? 'Save item' : 'Add item'}</Button></DialogFooter>
    </Form>
  </DialogPopup></Dialog>
}

function MovementDialog({ gymId, item, onClose, onSaved }: { gymId: number; item: StockItem; onClose: () => void; onSaved: () => void }) {
  const [type, setType] = useState<StockMovementType>('receipt')
  const [quantity, setQuantity] = useState('1')
  const [remove, setRemove] = useState(false)
  const [note, setNote] = useState('')
  const [issuedTo, setIssuedTo] = useState('none')
  const [returnsId, setReturnsId] = useState('')
  const [people, setPeople] = useState<StockRecipient[] | null>(null)
  const [issues, setIssues] = useState<OpenIssue[] | null>(null)
  const [peopleError, setPeopleError] = useState('')
  const [issuesError, setIssuesError] = useState('')
  const [version, setVersion] = useState(0)
  const [currentItem, setCurrentItem] = useState(item)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let live = true
    setPeopleError('')
    setIssuesError('')
    setPeople(null)
    setIssues(null)
    api<{ people: StockRecipient[] }>(`/gyms/${gymId}/stock-items/recipients`)
      .then((result) => { if (live) setPeople(result.people) })
      .catch((reason) => {
        if (!live) return
        setPeopleError('People could not be loaded. You can still issue without naming a person.')
        toastManager.add({ title: message(reason, 'People could not be loaded.'), type: 'error' })
      })
    api<StockMovementsPage>(`/gyms/${gymId}/stock-items/${item.id}/movements?page=1`)
      .then((result) => { if (live) { setIssues(result.open_issues); setCurrentItem(result.item) } })
      .catch((reason) => {
        if (!live) return
        setIssuesError('Open issues could not be loaded. Returns are unavailable until you try again.')
        toastManager.add({ title: message(reason, 'Open issues could not be loaded.'), type: 'error' })
      })
    return () => { live = false }
  }, [gymId, item.id, version])

  const count = Number(quantity)
  const validCount = quantity !== '' && Number.isInteger(count) && count > 0 && count <= 1000000
  const signed = (OUT_TYPES.includes(type) || (type === 'adjustment' && remove)) ? -count : count
  const after = currentItem.quantity + signed
  const selectedIssue = issues?.find((issue) => String(issue.id) === returnsId)
  const returnInvalid = type === 'return' && (!selectedIssue || count > selectedIssue.outstanding)
  const needsNote = NOTE_REQUIRED.includes(type)
  const blocked = !validCount || after < 0 || returnInvalid || (needsNote && !note.trim()) || currentItem.status === 'archived'

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (busy || blocked) return
    setBusy(true)
    setErrors({})
    try {
      await api(`/gyms/${gymId}/stock-items/${item.id}/movements`, { body: {
        type, quantity: type === 'adjustment' && remove ? -count : count, note: note.trim() || null,
        issued_to_user_id: type === 'issue' && people && issuedTo !== 'none' ? Number(issuedTo) : null,
        returns_movement_id: type === 'return' ? Number(returnsId) : null,
      } })
      toastManager.add({ title: 'Movement recorded.', type: 'success' })
      onSaved()
    } catch (reason) {
      setErrors(formErrors(reason, ['type', 'quantity', 'note', 'issued_to_user_id', 'returns_movement_id'], 'The movement could not be recorded.'))
    } finally { setBusy(false) }
  }

  return <Dialog open onOpenChange={(open) => { if (!open && !busy) onClose() }}><DialogPopup>
    <Form errors={errors} onSubmit={save}>
      <DialogHeader><DialogTitle>Record movement</DialogTitle><DialogDescription className="break-words">{item.name} · {item.location}</DialogDescription></DialogHeader>
      <DialogPanel className="flex flex-col gap-4">
        <Choice name="type" label="Movement type" value={type} choices={MANUAL_MOVEMENT_TYPES.map((value) => ({ value, label: MOVEMENT_TYPE[value].label }))} onChange={(value) => { setType(value as StockMovementType); setErrors({}) }} description={MOVEMENT_TYPE[type].hint} required />
        {type === 'adjustment' && <div className="flex gap-2" role="group" aria-label="Adjustment direction"><Button variant={remove ? 'outline' : 'default'} aria-pressed={!remove} onClick={() => setRemove(false)}>Add</Button><Button variant={remove ? 'default' : 'outline'} aria-pressed={remove} onClick={() => setRemove(true)}>Remove</Button></div>}
        <TextField name="quantity" label={`Quantity (${STOCK_UNIT[item.unit].many})`} type="number" inputMode="numeric" min={1} max={1000000} step={1} value={quantity} onChange={(event) => setQuantity(event.target.value)} required />
        {type === 'issue' && <><Choice name="issued_to_user_id" label="Issued to (optional)" value={issuedTo} choices={[{ value: 'none', label: 'Not named' }, ...(people ?? []).map((person) => ({ value: String(person.id), label: person.name }))]} onChange={setIssuedTo} disabled={people === null} description={peopleError || (people === null ? 'Loading people…' : undefined)} />{peopleError && <Button size="sm" variant="outline" onClick={() => setVersion((value) => value + 1)}>Try again</Button>}</>}
        {type === 'return' && <><Choice name="returns_movement_id" label="Return against issue" value={returnsId} choices={(issues ?? []).map((issue) => ({ value: String(issue.id), label: `Issue #${issue.id}: ${issue.quantity} on ${dateTimeLabel(issue.occurred_at)}${issue.issued_to_name ? ` to ${issue.issued_to_name}` : ''} · ${issue.outstanding} still out` }))} onChange={setReturnsId} disabled={issues === null || issues.length === 0} required description={issuesError || (issues === null ? 'Loading open issues…' : issues.length === 0 ? 'Nothing from this item is out right now.' : undefined)} />
          {issuesError && <Button size="sm" variant="outline" onClick={() => setVersion((value) => value + 1)}>Try again</Button>}
          {selectedIssue && validCount && count > selectedIssue.outstanding && <p className="text-destructive text-sm">Only {quantityLabel(selectedIssue.outstanding, item.unit)} from this issue are still out.</p>}
        </>}
        <Field name="note"><FieldLabel>Note ({needsNote ? 'required' : 'optional'})</FieldLabel><Textarea name="note" value={note} onChange={(event) => setNote(event.target.value)} required={needsNote} maxLength={255} rows={3} /><FieldError /></Field>
        <div className="rounded-xl border p-3 text-sm" aria-live="polite"><p>On hand <strong className="tabular-nums">{quantityLabel(currentItem.quantity, item.unit)}</strong> → after this <strong className="tabular-nums">{validCount ? quantityLabel(after, item.unit) : 'Enter a quantity'}</strong></p>{validCount && after < 0 && <p className="mt-1 text-destructive">That is more than you have.</p>}{currentItem.status === 'archived' && <p className="mt-1 text-destructive">This item has been archived and cannot be moved.</p>}</div>
      </DialogPanel>
      <DialogFooter><Button type="button" variant="outline" disabled={busy} onClick={onClose}>Cancel</Button><Button type="submit" loading={busy} disabled={blocked}>Record movement</Button></DialogFooter>
    </Form>
  </DialogPopup></Dialog>
}

function HistorySheet({ gymId, item, onClose }: { gymId: number; item: StockItem; onClose: () => void }) {
  const [data, setData] = useState<StockMovementsPage | null>(null)
  const [page, setPage] = useState(1)
  const [version, setVersion] = useState(0)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)
  const [jumpTo, setJumpTo] = useState<number | null>(null)
  const loadLock = useRef(false)

  useEffect(() => {
    let live = true
    setLoading(true)
    setError('')
    loadLock.current = true
    api<StockMovementsPage>(`/gyms/${gymId}/stock-items/${item.id}/movements?page=${page}`)
      .then((result) => {
        if (!live) return
        setData((current) => ({ ...result, movements: page === 1 ? result.movements : [...(current?.movements ?? []), ...result.movements] }))
      })
      .catch((reason) => { if (live) setError(message(reason, 'History could not be loaded.')) })
      .finally(() => { if (live) { setLoading(false); loadLock.current = false } })
    return () => { live = false }
  }, [gymId, item.id, page, version])

  useEffect(() => {
    if (jumpTo === null || loading || error || !data) return
    const row = document.getElementById(`stock-movement-${jumpTo}`)
    if (row) {
      row.scrollIntoView({ block: 'nearest' })
      row.focus({ preventScroll: true })
      setJumpTo(null)
    } else if (data.meta.current_page < data.meta.last_page && !loadLock.current) {
      loadLock.current = true
      setPage(data.meta.current_page + 1)
    } else {
      setJumpTo(null)
    }
  }, [jumpTo, loading, error, data])

  const currentItem = data?.item ?? item
  return <Sheet open onOpenChange={(open) => { if (!open) onClose() }}><SheetPopup side="right" className="w-full max-w-md">
    <SheetHeader className="pr-12"><SheetTitle className="break-words">{item.name}</SheetTitle><SheetDescription className="break-words">{quantityLabel(currentItem.quantity, item.unit)} · {currentItem.location}</SheetDescription></SheetHeader>
    <SheetPanel className="flex min-w-0 flex-col gap-4">
      <h2 className="font-medium text-sm">Movement history</h2>
      {(currentItem.unit_cost !== null || currentItem.sale_price !== null) && <dl className="flex flex-wrap gap-x-6 gap-y-2 text-sm">{currentItem.unit_cost !== null && <div><dt className="text-muted-foreground">Unit cost</dt><dd>{money(currentItem.unit_cost)}</dd></div>}{currentItem.category === 'merchandise' && currentItem.sale_price !== null && <div><dt className="text-muted-foreground">Sale price</dt><dd>{money(currentItem.sale_price)}</dd></div>}</dl>}
      {error && <Alert variant="error"><AlertTitle>History could not be loaded</AlertTitle><AlertDescription>{error}</AlertDescription><div className="pt-2"><Button size="sm" variant="outline" onClick={() => setVersion((value) => value + 1)}>Try again</Button></div></Alert>}
      {!data && loading && <LoadingRows />}
      {data && <>
        <p className="text-muted-foreground text-xs" aria-live="polite">{data.movements.length} of {data.meta.total} movements, newest first</p>
        {data.movements.length === 0 ? <p className="text-muted-foreground text-sm">No movements recorded.</p> : <ol className="divide-y">{data.movements.map((movement) => <li key={movement.id} id={`stock-movement-${movement.id}`} tabIndex={-1} className="flex min-w-0 scroll-mt-4 flex-col gap-1.5 py-4 text-sm focus-visible:rounded-lg focus-visible:outline-2 focus-visible:outline-ring">
          <div className="flex flex-wrap justify-between gap-2"><h3 className="font-medium">{MOVEMENT_TYPE[movement.type].label} <span className="text-muted-foreground text-xs">#{movement.id}</span></h3><span className={cn('font-semibold tabular-nums', movement.quantity < 0 ? 'text-destructive' : 'text-foreground')}>{signedLabel(movement.quantity)}</span></div>
          <p className="text-muted-foreground">Balance {quantityLabel(movement.balance_after, item.unit)}</p>
          <p className="break-words">{movement.actor_name ?? 'Unknown person'}</p><time className="text-muted-foreground text-xs" dateTime={movement.occurred_at.replace(' ', 'T') + '+08:00'}>{dateTimeLabel(movement.occurred_at)}</time>
          {movement.note && <p className="whitespace-pre-wrap break-words">{movement.note}</p>}
          {movement.type === 'issue' && <p className="break-words text-muted-foreground">{movement.issued_to_name ? `To ${movement.issued_to_name} · ` : ''}{movement.outstanding ?? 0} still out</p>}
          {movement.returns_movement_id !== null && <Button variant="link" size="sm" className="h-auto justify-start self-start whitespace-normal px-0 text-left" disabled={loading} onClick={() => setJumpTo(movement.returns_movement_id)}>Return of issue #{movement.returns_movement_id}</Button>}
        </li>)}</ol>}
      </>}
    </SheetPanel>
    <SheetFooter>{data && data.meta.current_page < data.meta.last_page && !error && <Button variant="outline" loading={loading} onClick={() => { if (loadLock.current) return; loadLock.current = true; setPage(data.meta.current_page + 1) }}>Show older</Button>}<Button variant="outline" onClick={onClose}>Close</Button></SheetFooter>
  </SheetPopup></Sheet>
}
