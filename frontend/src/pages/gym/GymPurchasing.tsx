import { useEffect, useState } from 'react'
import { RangePicker } from '@/components/decision-support/range-picker'
import { NO_RANGE, rangeQuery } from '@/lib/decision-support'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import { ChevronLeft, ChevronRight, Plus, Trash2, X } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { TextField } from '@/components/text-field'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import {
  AlertDialog,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogPopup,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Progress } from '@/components/ui/progress'
import { Select, SelectItem, SelectPopup, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import { STOCK_CATEGORY, type StockItem } from '@/lib/inventory'
import { dateLabel, dateTimeLabel } from '@/lib/membership'
import { pageMetaFrom, type PageMeta } from '@/lib/pagination'
import {
  orderProgress,
  orderProgressLabel,
  orderSupplierName,
  purchaseOrderStatusMeta,
  purchaseRequestEstimatedTotal,
  purchaseRequestLineAmount,
  purchaseRequestLineName,
  purchaseRequestStatusMeta,
  quantityWithUnit,
  supplyNeedStatusMeta,
  supplierStatusMeta,
  money,
  type PurchaseOrder,
  type PurchaseRequest,
  type RestockRow,
  type Supplier,
  type SupplyNeed,
} from '@/lib/procurement'
import type { TrainingSession } from '@/lib/sessions'

type TabKey = 'restock' | 'requests' | 'orders' | 'suppliers'

type ItemChoice = { id: number; name: string; unit: string; unitCost: string; quantity: number }
type RequestSeedLine = { stockItemId: number; name: string; quantity: string; estUnitCost: string; supplyNeedId: number | null }
type RequestSeed = RequestSeedLine[]
type NewRequestLine = {
  key: string
  stockItemId: number
  name: string
  quantity: string
  estUnitCost: string
  supplyNeedId: number | null
}
type DirectLine = { key: string; stockItemId: number; quantity: string; unitCost: string }
type SupplierAction = { supplier: Supplier; status: 'active' | 'archived' }

const EMPTY_RESTOCK: RestockRow[] = []

const todayIso = () => isoDay(new Date())

function isoDay(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

function dayFromNow(days: number): string {
  const date = new Date()
  date.setDate(date.getDate() + days)
  return isoDay(date)
}

function message(reason: unknown, fallback: string): string {
  return reason instanceof ApiError ? reason.message : fallback
}

function formErrors(reason: unknown, known: string[], fallback: string): FieldErrors {
  const errors = reason instanceof ApiError ? reason.errors : {}
  const keys = Object.keys(errors)
  if (keys.length === 0 || keys.some((key) => !known.includes(key) && !key.startsWith('lines.'))) {
    toastManager.add({ title: message(reason, fallback), type: 'error' })
  }
  return errors
}

function nestedErrors(errors: FieldErrors, prefix: string): string[] {
  return [...new Set(Object.entries(errors).filter(([key]) => key.startsWith(prefix)).flatMap(([, values]) => values))]
}

async function loadStockChoices(gymId: number, fallbackRows: RestockRow[]): Promise<{ items: ItemChoice[]; fallback: boolean }> {
  try {
    const result = await api<{ items: StockItem[] }>(`/gyms/${gymId}/stock-items`)
    return {
      items: (result.items ?? [])
        .filter((item) => item.status === 'active')
        .map((item) => ({ id: item.id, name: item.name, unit: item.unit, unitCost: item.unit_cost ?? '', quantity: item.quantity })),
      fallback: false,
    }
  } catch {
    return {
      items: fallbackRows.map((row) => ({
        id: row.stock_item_id,
        name: row.name,
        unit: row.unit,
        unitCost: row.unit_cost ?? '',
        quantity: row.quantity,
      })),
      fallback: true,
    }
  }
}

async function loadSuppliers(gymId: number): Promise<Supplier[]> {
  const result = await api<{ suppliers: Supplier[] }>(`/gyms/${gymId}/suppliers`)
  return (result.suppliers ?? []).slice().sort((a, b) => a.name.localeCompare(b.name))
}

function needTiming(need: SupplyNeed): string {
  const session =
    need.session_name ??
    need.training_session?.name ??
    (need.training_session_id ? `Session #${need.training_session_id}` : null)
  const date = need.needed_by ? dateLabel(need.needed_by) : null
  if (session && date) return `${session} · ${date}`
  if (session) return session
  if (date) return `Needed by ${date}`
  return 'No date'
}

function LoadingRows({ label }: { label: string }) {
  return (
    <div className="flex flex-col gap-2" aria-busy="true" aria-label={label}>
      <Skeleton className="h-12" />
      <Skeleton className="h-12" />
      <Skeleton className="h-12" />
    </div>
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
        value={value}
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

/** S-30b, R-75 to R-79: restock, approvals, orders and suppliers in one place. */
export default function GymPurchasing() {
  const { gymId: gymIdParam } = useParams()
  const gymId = Number(gymIdParam)
  const navigate = useNavigate()
  const [search] = useSearchParams()
  const requestedTab = search.get('tab')
  const { user } = useAuth()
  const gym = approvedGym(user, gymId)

  const canViewRequests = gym ? can(gym, 'procurement.requests.view') : false
  const canManageRequests = gym ? can(gym, 'procurement.requests.manage') : false
  const canApproveRequests = gym ? can(gym, 'procurement.requests.approve') : false
  const canViewOrders = gym ? can(gym, 'procurement.orders.view') : false
  const canManageOrders = gym ? can(gym, 'procurement.orders.manage') : false
  const canViewSuppliers = gym ? can(gym, 'procurement.suppliers.view') : false
  const canManageSuppliers = gym ? can(gym, 'procurement.suppliers.manage') : false
  const canViewRestock = canViewRequests || (gym ? can(gym, 'inventory.items.view') : false)
  const isOwner = gym?.roles.includes('owner') ?? false

  const [tab, setTab] = useState<TabKey>(() =>
    requestedTab === 'requests' || requestedTab === 'orders' || requestedTab === 'suppliers' ? requestedTab : 'restock',
  )
  const [version, setVersion] = useState(0)

  const [restock, setRestock] = useState<RestockRow[] | null>(null)
  const [restockError, setRestockError] = useState('')
  const [needs, setNeeds] = useState<SupplyNeed[] | null>(null)
  const [needsError, setNeedsError] = useState('')
  const [selected, setSelected] = useState<number[]>([])

  const [requests, setRequests] = useState<PurchaseRequest[] | null>(null)
  const [requestsMeta, setRequestsMeta] = useState<PageMeta | null>(null)
  const [requestsPage, setRequestsPage] = useState(1)
  const [requestsRange, setRequestsRange] = useState(NO_RANGE)
  const [requestsError, setRequestsError] = useState('')
  const [requestDetail, setRequestDetail] = useState<PurchaseRequest | null>(null)

  const [orders, setOrders] = useState<PurchaseOrder[] | null>(null)
  const [ordersMeta, setOrdersMeta] = useState<PageMeta | null>(null)
  const [ordersPage, setOrdersPage] = useState(1)
  const [ordersRange, setOrdersRange] = useState(NO_RANGE)
  const [ordersError, setOrdersError] = useState('')

  const [suppliers, setSuppliers] = useState<Supplier[] | null>(null)
  const [suppliersMeta, setSuppliersMeta] = useState<PageMeta | null>(null)
  const [suppliersPage, setSuppliersPage] = useState(1)
  const [suppliersError, setSuppliersError] = useState('')

  const [needOpen, setNeedOpen] = useState(false)
  const [requestSeed, setRequestSeed] = useState<RequestSeed | null>(null)
  const [editingRequest, setEditingRequest] = useState<PurchaseRequest | null>(null)
  const [directOpen, setDirectOpen] = useState(false)
  const [supplierDialog, setSupplierDialog] = useState<{ supplier: Supplier | null } | null>(null)
  const [supplierAction, setSupplierAction] = useState<SupplierAction | null>(null)
  const [supplierBusy, setSupplierBusy] = useState(false)
  const [deletingNeed, setDeletingNeed] = useState<SupplyNeed | null>(null)
  const [deleteNeedBusy, setDeleteNeedBusy] = useState(false)
  const [orderFromRequest, setOrderFromRequest] = useState<PurchaseRequest | null>(null)

  const availableTabs: { key: TabKey; label: string; show: boolean }[] = [
    { key: 'restock', label: 'Restock', show: canViewRestock },
    { key: 'requests', label: 'Requests', show: canViewRequests },
    { key: 'orders', label: 'Orders', show: canViewOrders },
    { key: 'suppliers', label: 'Suppliers', show: canViewSuppliers },
  ]
  const allowedTabs = availableTabs.filter((item) => item.show).map((item) => item.key)
  const activeTab = allowedTabs.includes(tab) ? tab : (allowedTabs[0] ?? 'restock')

  useEffect(() => {
    if (!gymId || !canViewRestock || activeTab !== 'restock') return
    let live = true
    setRestock(null)
    setRestockError('')
    api<{ restock: RestockRow[] }>(`/gyms/${gymId}/restock`)
      .then((result) => {
        if (live) setRestock(Array.isArray(result.restock) ? result.restock : [])
      })
      .catch((reason) => {
        if (!live) return
        setRestock([])
        setRestockError(message(reason, 'The restock list could not be loaded.'))
      })
    return () => {
      live = false
    }
  }, [gymId, canViewRestock, activeTab, version])

  useEffect(() => {
    if (!gymId || !canViewRequests || activeTab !== 'restock') return
    let live = true
    setNeeds(null)
    setNeedsError('')
    api<{ needs: SupplyNeed[] }>(`/gyms/${gymId}/supply-needs`)
      .then((result) => {
        if (live) setNeeds(Array.isArray(result.needs) ? result.needs : [])
      })
      .catch((reason) => {
        if (!live) return
        setNeeds([])
        setNeedsError(message(reason, 'Open needs could not be loaded.'))
      })
    return () => {
      live = false
    }
  }, [gymId, canViewRequests, activeTab, version])

  useEffect(() => {
    if (!gymId || !canViewRequests || activeTab !== 'requests') return
    let live = true
    setRequests(null)
    setRequestsError('')
    api<Record<string, unknown>>(`/gyms/${gymId}/purchase-requests?page=${requestsPage}${rangeQuery(requestsRange)}`)
      .then((result) => {
        if (!live) return
        const rows = Array.isArray(result.requests) ? (result.requests as PurchaseRequest[]) : []
        setRequests(rows)
        setRequestsMeta(pageMetaFrom(result, rows.length))
      })
      .catch((reason) => {
        if (!live) return
        setRequests([])
        setRequestsMeta(null)
        setRequestsError(message(reason, 'Purchase requests could not be loaded.'))
      })
    return () => {
      live = false
    }
  }, [gymId, canViewRequests, activeTab, requestsPage, version, requestsRange])

  useEffect(() => {
    if (!gymId || !canViewOrders || activeTab !== 'orders') return
    let live = true
    setOrders(null)
    setOrdersError('')
    api<Record<string, unknown>>(`/gyms/${gymId}/purchase-orders?page=${ordersPage}${rangeQuery(ordersRange)}`)
      .then((result) => {
        if (!live) return
        const rows = Array.isArray(result.orders) ? (result.orders as PurchaseOrder[]) : []
        setOrders(rows)
        setOrdersMeta(pageMetaFrom(result, rows.length))
      })
      .catch((reason) => {
        if (!live) return
        setOrders([])
        setOrdersMeta(null)
        setOrdersError(message(reason, 'Purchase orders could not be loaded.'))
      })
    return () => {
      live = false
    }
  }, [gymId, canViewOrders, activeTab, ordersPage, version, ordersRange])

  useEffect(() => {
    if (!gymId || !canViewSuppliers || activeTab !== 'suppliers') return
    let live = true
    setSuppliers(null)
    setSuppliersError('')
    api<Record<string, unknown>>(`/gyms/${gymId}/suppliers?page=${suppliersPage}`)
      .then((result) => {
        if (!live) return
        const rows = Array.isArray(result.suppliers) ? (result.suppliers as Supplier[]) : []
        setSuppliers(rows)
        setSuppliersMeta(pageMetaFrom(result, rows.length))
      })
      .catch((reason) => {
        if (!live) return
        setSuppliers([])
        setSuppliersMeta(null)
        setSuppliersError(message(reason, 'Suppliers could not be loaded.'))
      })
    return () => {
      live = false
    }
  }, [gymId, canViewSuppliers, activeTab, suppliersPage, version])

  if (!gym) return null

  if (!canViewRestock && !canViewRequests && !canViewOrders && !canViewSuppliers) {
    return (
      <div className="flex flex-col gap-6">
        <PageHeader title="Purchasing" />
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>Purchasing is not available</EmptyTitle>
            <EmptyDescription>Ask the owner for access to requests, orders or suppliers.</EmptyDescription>
          </EmptyHeader>
        </Empty>
      </div>
    )
  }

  const selectedRows = (restock ?? EMPTY_RESTOCK).filter((row) => selected.includes(row.stock_item_id))

  function reload() {
    setSelected([])
    setVersion((value) => value + 1)
  }

  function toggleSelected(stockItemId: number) {
    setSelected((current) =>
      current.includes(stockItemId) ? current.filter((id) => id !== stockItemId) : [...current, stockItemId],
    )
  }

  function selectAll() {
    if (!restock || restock.length === 0) return
    setSelected(restock.every((row) => selected.includes(row.stock_item_id)) ? [] : restock.map((row) => row.stock_item_id))
  }

  function openRequestFromRestock() {
    setRequestSeed(
      selectedRows.map((row) => {
        const openNeeds = (needs ?? []).filter(
          (need) => need.status === 'open' && need.stock_item_id === row.stock_item_id,
        )
        return {
          stockItemId: row.stock_item_id,
          name: row.name,
          quantity: String(Math.max(1, row.suggested_quantity || row.shortfall || 1)),
          estUnitCost: row.unit_cost ?? '',
          supplyNeedId: openNeeds.length === 1 ? openNeeds[0].id : null,
        }
      }),
    )
  }

  async function deleteNeed() {
    if (!deletingNeed || deleteNeedBusy) return
    setDeleteNeedBusy(true)
    try {
      await api(`/gyms/${gymId}/supply-needs/${deletingNeed.id}`, { method: 'DELETE' })
      toastManager.add({ title: 'Need removed.', type: 'success' })
      setDeletingNeed(null)
      reload()
    } catch (reason) {
      toastManager.add({ title: message(reason, 'The need could not be removed.'), type: 'error' })
    } finally {
      setDeleteNeedBusy(false)
    }
  }

  async function changeSupplierStatus() {
    if (!supplierAction || supplierBusy) return
    setSupplierBusy(true)
    try {
      const { supplier, status } = supplierAction
      await api(`/gyms/${gymId}/suppliers/${supplier.id}`, {
        method: 'PUT',
        body: {
          name: supplier.name,
          contact_person: supplier.contact_person,
          phone: supplier.phone,
          email: supplier.email,
          address: supplier.address,
          payment_terms: supplier.payment_terms,
          notes: supplier.notes,
          status,
        },
      })
      toastManager.add({
        title: status === 'archived' ? 'Supplier archived.' : 'Supplier is active again.',
        type: 'success',
      })
      setSupplierAction(null)
      reload()
    } catch (reason) {
      toastManager.add({ title: message(reason, 'The supplier could not be changed.'), type: 'error' })
    } finally {
      setSupplierBusy(false)
    }
  }

  function orderSaved(order: PurchaseOrder) {
    setOrderFromRequest(null)
    setDirectOpen(false)
    setRequestDetail(null)
    setTab('orders')
    setVersion((value) => value + 1)
    if (order?.id) navigate(`/gyms/${gymId}/manage/purchasing/orders/${order.id}`)
  }

  function headerActions() {
    if (activeTab === 'restock' && canManageRequests) {
      return (
        <>
          <Button size="sm" variant="outline" onClick={() => setNeedOpen(true)}>
            <Plus aria-hidden />
            Add need
          </Button>
          <Button size="sm" onClick={openRequestFromRestock} disabled={selectedRows.length === 0}>
            <Plus aria-hidden />
            {selectedRows.length > 0 ? `Create request (${selectedRows.length})` : 'Create request'}
          </Button>
        </>
      )
    }
    if (activeTab === 'requests' && canManageRequests) {
      return (
        <Button size="sm" onClick={() => setRequestSeed([])}>
          <Plus aria-hidden />
          New request
        </Button>
      )
    }
    if (activeTab === 'orders' && canManageOrders && isOwner) {
      return (
        <Button size="sm" onClick={() => setDirectOpen(true)}>
          <Plus aria-hidden />
          Direct purchase
        </Button>
      )
    }
    if (activeTab === 'suppliers' && canManageSuppliers) {
      return (
        <Button size="sm" onClick={() => setSupplierDialog({ supplier: null })}>
          <Plus aria-hidden />
          Add supplier
        </Button>
      )
    }
    return undefined
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Purchasing"
        description={`${gym.name}: what to restock, requests waiting for a decision, orders and suppliers.`}
        actions={headerActions()}
      />

      <Tabs value={activeTab} onValueChange={(value) => setTab(String(value) as TabKey)}>
        <TabsList
          variant="underline"
          aria-label="Purchasing sections"
          className="max-w-full overflow-x-auto overflow-y-hidden [scrollbar-width:none]"
        >
          {canViewRestock && <TabsTab value="restock">Restock</TabsTab>}
          {canViewRequests && <TabsTab value="requests">Requests</TabsTab>}
          {canViewOrders && <TabsTab value="orders">Orders</TabsTab>}
          {canViewSuppliers && <TabsTab value="suppliers">Suppliers</TabsTab>}
        </TabsList>

        {canViewRestock && (
          <TabsPanel value="restock" className="pt-4">
            <RestockPanel
              rows={restock}
              needs={needs}
              error={restockError}
              needsError={needsError}
              selected={selected}
              canManage={canManageRequests}
              canSeeNeeds={canViewRequests}
              onToggle={toggleSelected}
              onSelectAll={selectAll}
              onAddNeed={() => setNeedOpen(true)}
              onCreateRequest={openRequestFromRestock}
              onDeleteNeed={setDeletingNeed}
              onRetry={reload}
            />
          </TabsPanel>
        )}

        {canViewRequests && (
          <TabsPanel value="requests" className="flex flex-col gap-4 pt-4">
            <RangePicker optional name="requests" value={requestsRange} onChange={(next) => { setRequestsRange(next); setRequestsPage(1) }} />
            <RequestsPanel
              rows={requests}
              meta={requestsMeta}
              error={requestsError}
              canManage={canManageRequests}
              onRetry={reload}
              onNew={() => setRequestSeed([])}
              onOpen={setRequestDetail}
              onPage={setRequestsPage}
            />
          </TabsPanel>
        )}

        {canViewOrders && (
          <TabsPanel value="orders" className="flex flex-col gap-4 pt-4">
            <RangePicker optional name="orders" value={ordersRange} onChange={(next) => { setOrdersRange(next); setOrdersPage(1) }} />
            <OrdersPanel
              gymId={gymId}
              rows={orders}
              meta={ordersMeta}
              error={ordersError}
              canManage={canManageOrders}
              isOwner={isOwner}
              onRetry={reload}
              onDirect={() => setDirectOpen(true)}
              onPage={setOrdersPage}
            />
          </TabsPanel>
        )}

        {canViewSuppliers && (
          <TabsPanel value="suppliers" className="pt-4">
            <SuppliersPanel
              rows={suppliers}
              meta={suppliersMeta}
              error={suppliersError}
              canManage={canManageSuppliers}
              onRetry={reload}
              onAdd={() => setSupplierDialog({ supplier: null })}
              onEdit={(supplier) => setSupplierDialog({ supplier })}
              onPage={setSuppliersPage}
              onStatus={(supplier) =>
                setSupplierAction({ supplier, status: supplier.status === 'active' ? 'archived' : 'active' })
              }
            />
          </TabsPanel>
        )}
      </Tabs>

      {needOpen && canManageRequests && (
        <AddNeedDialog
          gymId={gymId}
          restockRows={restock ?? EMPTY_RESTOCK}
          onClose={() => setNeedOpen(false)}
          onSaved={() => {
            setNeedOpen(false)
            reload()
          }}
        />
      )}

      {requestSeed !== null && canManageRequests && (
        <NewRequestDialog
          gymId={gymId}
          seed={requestSeed}
          editing={editingRequest}
          restockRows={restock ?? EMPTY_RESTOCK}
          supplyNeeds={needs ?? []}
          onClose={() => { setRequestSeed(null); setEditingRequest(null) }}
          onSaved={() => {
            setRequestSeed(null)
            setEditingRequest(null)
            setTab('requests')
            reload()
          }}
        />
      )}

      {requestDetail && canViewRequests && (
        <RequestDetailDialog
          gymId={gymId}
          request={requestDetail}
          viewerId={user?.id ?? null}
          canApprove={canApproveRequests}
          canManage={canManageRequests}
          canManageOrders={canManageOrders}
          canViewOrders={canViewOrders}
          canViewSuppliers={canViewSuppliers}
          onClose={() => setRequestDetail(null)}
          onChanged={(updated) => {
            setRequestDetail(updated)
            setVersion((value) => value + 1)
          }}
          onOrder={(request) => {
            setRequestDetail(null)
            setOrderFromRequest(request)
          }}
          onEdit={(request) => {
            // R-113: reuse the new-request dialog with the request's lines filled in.
            setRequestDetail(null)
            setEditingRequest(request)
            setRequestSeed((request.lines ?? []).map((line) => ({
              stockItemId: line.stock_item_id,
              name: line.item_name ?? line.stock_item?.name ?? `Item #${line.stock_item_id}`,
              quantity: String(line.quantity),
              estUnitCost: line.est_unit_cost ?? '',
              supplyNeedId: line.supply_need_id,
            })))
          }}
        />
      )}

      {orderFromRequest && canManageOrders && (
        <CreateOrderDialog
          gymId={gymId}
          request={orderFromRequest}
          onClose={() => setOrderFromRequest(null)}
          onSaved={orderSaved}
        />
      )}

      {directOpen && canManageOrders && isOwner && (
        <DirectPurchaseDialog
          gymId={gymId}
          restockRows={restock ?? EMPTY_RESTOCK}
          onClose={() => setDirectOpen(false)}
          onSaved={orderSaved}
        />
      )}

      {supplierDialog && canManageSuppliers && (
        <SupplierDialog
          gymId={gymId}
          supplier={supplierDialog.supplier}
          onClose={() => setSupplierDialog(null)}
          onSaved={() => {
            setSupplierDialog(null)
            reload()
          }}
        />
      )}

      <AlertDialog open={Boolean(supplierAction)} onOpenChange={(open) => !open && !supplierBusy && setSupplierAction(null)}>
        <AlertDialogPopup>
          <AlertDialogHeader>
            <AlertDialogTitle className="break-words">
              {supplierAction?.status === 'archived' ? `Archive ${supplierAction.supplier.name}?` : `Activate ${supplierAction?.supplier.name ?? 'this supplier'}?`}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {supplierAction?.status === 'archived'
                ? 'The supplier stays on past orders and payables, but cannot be chosen for new orders.'
                : 'The supplier can be chosen for new purchase orders again.'}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <Button variant="outline" disabled={supplierBusy} onClick={() => setSupplierAction(null)}>
              Cancel
            </Button>
            <Button
              variant={supplierAction?.status === 'archived' ? 'destructive' : 'default'}
              loading={supplierBusy}
              onClick={changeSupplierStatus}
            >
              {supplierAction?.status === 'archived' ? 'Archive supplier' : 'Activate supplier'}
            </Button>
          </AlertDialogFooter>
        </AlertDialogPopup>
      </AlertDialog>

      <AlertDialog open={Boolean(deletingNeed)} onOpenChange={(open) => !open && !deleteNeedBusy && setDeletingNeed(null)}>
        <AlertDialogPopup>
          <AlertDialogHeader>
            <AlertDialogTitle className="break-words">Remove this need?</AlertDialogTitle>
            <AlertDialogDescription>
              {deletingNeed
                ? `${deletingNeed.quantity} × ${deletingNeed.item_name} will stop counting towards the restock shortfall.`
                : ''}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <Button variant="outline" disabled={deleteNeedBusy} onClick={() => setDeletingNeed(null)}>
              Cancel
            </Button>
            <Button variant="destructive" loading={deleteNeedBusy} onClick={deleteNeed}>
              Remove need
            </Button>
          </AlertDialogFooter>
        </AlertDialogPopup>
      </AlertDialog>
    </div>
  )
}

function RestockPanel({
  rows,
  needs,
  error,
  needsError,
  selected,
  canManage,
  canSeeNeeds,
  onToggle,
  onSelectAll,
  onAddNeed,
  onCreateRequest,
  onDeleteNeed,
  onRetry,
}: {
  rows: RestockRow[] | null
  needs: SupplyNeed[] | null
  error: string
  needsError: string
  selected: number[]
  canManage: boolean
  canSeeNeeds: boolean
  onToggle: (stockItemId: number) => void
  onSelectAll: () => void
  onAddNeed: () => void
  onCreateRequest: () => void
  onDeleteNeed: (need: SupplyNeed) => void
  onRetry: () => void
}) {
  const feed = rows ?? []
  const allSelected = feed.length > 0 && feed.every((row) => selected.includes(row.stock_item_id))
  const shownNeeds = (needs ?? []).filter((need) => need.status === 'open' || need.status === 'requested')

  if (error) {
    return (
      <Alert variant="error">
        <AlertTitle>Restock could not be loaded</AlertTitle>
        <AlertDescription>{error}</AlertDescription>
        <AlertAction>
          <Button size="xs" variant="outline" onClick={onRetry}>
            Try again
          </Button>
        </AlertAction>
      </Alert>
    )
  }

  return (
    <div className="flex flex-col gap-6">
      {rows === null ? (
        <LoadingRows label="Loading restock" />
      ) : rows.length === 0 ? (
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>Nothing needs restocking</EmptyTitle>
            <EmptyDescription>
              Every active item is above its minimum and no open need is pushing it below. Add a need for an upcoming class or event to plan ahead.
            </EmptyDescription>
          </EmptyHeader>
          {canManage && (
            <EmptyContent>
              <Button onClick={onAddNeed}>
                <Plus aria-hidden />
                Add need
              </Button>
            </EmptyContent>
          )}
        </Empty>
      ) : (
        <>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-muted-foreground text-sm" aria-live="polite">
              {rows.length} {rows.length === 1 ? 'item needs' : 'items need'} attention
            </p>
            {canManage && (
              <Button size="sm" variant="ghost" onClick={onSelectAll}>
                {allSelected ? 'Clear selection' : 'Select all'}
              </Button>
            )}
          </div>

          <ul className="flex flex-col divide-y rounded-xl border bg-card md:hidden">
            {rows.map((row) => {
              const checked = selected.includes(row.stock_item_id)
              return (
                <li key={row.stock_item_id} className="flex min-w-0 flex-col gap-3 p-4">
                  <div className="flex items-start gap-3">
                    {canManage && (
                      <Checkbox
                        checked={checked}
                        onCheckedChange={() => onToggle(row.stock_item_id)}
                        aria-label={`Select ${row.name}`}
                        className="mt-0.5"
                      />
                    )}
                    <div className="min-w-0 flex-1">
                      <h3 className="break-words font-medium">{row.name}</h3>
                      <p className="break-words text-muted-foreground text-sm">
                        {STOCK_CATEGORY[row.category as keyof typeof STOCK_CATEGORY] ?? row.category} · {row.location}
                      </p>
                    </div>
                    {row.shortfall > 0 && <Badge variant="warning">Short {row.shortfall}</Badge>}
                  </div>
                  <dl className="grid grid-cols-2 gap-3 text-sm">
                    <div>
                      <dt className="text-muted-foreground">On hand</dt>
                      <dd className="font-medium tabular-nums">{quantityWithUnit(row.quantity, row.unit)}</dd>
                    </div>
                    <div>
                      <dt className="text-muted-foreground">Minimum</dt>
                      <dd className="tabular-nums">{row.min_level ?? 'None'}</dd>
                    </div>
                    <div>
                      <dt className="text-muted-foreground">On order</dt>
                      <dd className="tabular-nums">{row.on_order}</dd>
                    </div>
                    <div>
                      <dt className="text-muted-foreground">Open needs</dt>
                      <dd className="tabular-nums">{row.open_needs}</dd>
                    </div>
                    <div>
                      <dt className="text-muted-foreground">Shortfall</dt>
                      <dd className="font-semibold tabular-nums">{row.shortfall}</dd>
                    </div>
                    <div>
                      <dt className="text-muted-foreground">Suggested</dt>
                      <dd className="font-semibold tabular-nums">{quantityWithUnit(row.suggested_quantity, row.unit)}</dd>
                    </div>
                  </dl>
                </li>
              )
            })}
          </ul>

          <div className="hidden min-w-0 overflow-x-auto md:block">
            <Table variant="card" className="min-w-[56rem]">
              <TableHeader>
                <TableRow>
                  {canManage && (
                    <TableHead className="w-10">
                      <span className="sr-only">Select</span>
                    </TableHead>
                  )}
                  <TableHead>Item</TableHead>
                  <TableHead className="text-right">On hand</TableHead>
                  <TableHead className="text-right">Minimum</TableHead>
                  <TableHead className="text-right">On order</TableHead>
                  <TableHead className="text-right">Open needs</TableHead>
                  <TableHead className="text-right">Shortfall</TableHead>
                  <TableHead className="text-right">Suggested</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((row) => (
                  <TableRow key={row.stock_item_id} data-state={selected.includes(row.stock_item_id) ? 'selected' : undefined}>
                    {canManage && (
                      <TableCell>
                        <Checkbox
                          checked={selected.includes(row.stock_item_id)}
                          onCheckedChange={() => onToggle(row.stock_item_id)}
                          aria-label={`Select ${row.name}`}
                        />
                      </TableCell>
                    )}
                    <TableCell className="max-w-64 whitespace-normal break-words">
                      <span className="font-medium">{row.name}</span>
                      <span className="block text-muted-foreground text-xs">
                        {STOCK_CATEGORY[row.category as keyof typeof STOCK_CATEGORY] ?? row.category} · {row.location}
                      </span>
                    </TableCell>
                    <TableCell className="text-right tabular-nums">{quantityWithUnit(row.quantity, row.unit)}</TableCell>
                    <TableCell className="text-right tabular-nums">{row.min_level ?? 'None'}</TableCell>
                    <TableCell className="text-right tabular-nums">{row.on_order}</TableCell>
                    <TableCell className="text-right tabular-nums">{row.open_needs}</TableCell>
                    <TableCell className="text-right tabular-nums">
                      {row.shortfall > 0 ? <Badge variant="warning">{row.shortfall}</Badge> : <span className="text-muted-foreground">0</span>}
                    </TableCell>
                    <TableCell className="text-right font-semibold tabular-nums">
                      {quantityWithUnit(row.suggested_quantity, row.unit)}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>

          {canManage && (
            <div className="flex flex-wrap gap-2">
              <Button variant="outline" onClick={onAddNeed}>
                <Plus aria-hidden />
                Add need
              </Button>
              <Button onClick={onCreateRequest} disabled={selected.length === 0}>
                <Plus aria-hidden />
                {selected.length > 0 ? `Create request (${selected.length})` : 'Create request'}
              </Button>
            </div>
          )}
        </>
      )}

      {canSeeNeeds && (
        <section aria-labelledby="upcoming-needs-heading" className="flex min-w-0 flex-col gap-3">
          <div>
            <h2 id="upcoming-needs-heading" className="font-heading font-semibold text-base">
              Upcoming needs
            </h2>
            <p className="text-muted-foreground text-sm">
              Needs for classes and events. Open needs count towards the shortfall above.
            </p>
          </div>

          {needsError ? (
            <Alert variant="error">
              <AlertTitle>Needs could not be loaded</AlertTitle>
              <AlertDescription>{needsError}</AlertDescription>
              <AlertAction>
                <Button size="xs" variant="outline" onClick={onRetry}>
                  Try again
                </Button>
              </AlertAction>
            </Alert>
          ) : needs === null ? (
            <LoadingRows label="Loading needs" />
          ) : shownNeeds.length === 0 ? (
            <p className="rounded-xl border px-4 py-3 text-muted-foreground text-sm">
              No open needs. Add one when a class or event will use more stock than usual.
            </p>
          ) : (
            <ul className="flex flex-col divide-y rounded-xl border bg-card">
              {shownNeeds.map((need) => {
                const status = supplyNeedStatusMeta(need.status)
                return (
                  <li key={need.id} className="flex min-w-0 flex-wrap items-start justify-between gap-3 p-4">
                    <div className="min-w-0 flex-1">
                      <p className="break-words font-medium">
                        {need.quantity} × {need.item_name}
                      </p>
                      <p className="break-words text-muted-foreground text-sm">{needTiming(need)}</p>
                      {need.note && <p className="mt-1 break-words text-sm">{need.note}</p>}
                      <p className="mt-1 text-muted-foreground text-xs">
                        Added by {need.created_by_name ?? 'an unknown person'}
                      </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                      <Badge variant={status.variant}>{status.label}</Badge>
                      {canManage && need.status === 'open' && (
                        <Button
                          size="icon-sm"
                          variant="ghost"
                          aria-label={`Remove need for ${need.item_name}`}
                          onClick={() => onDeleteNeed(need)}
                        >
                          <Trash2 aria-hidden />
                        </Button>
                      )}
                    </div>
                  </li>
                )
              })}
            </ul>
          )}
        </section>
      )}
    </div>
  )
}

function RequestsPanel({
  rows,
  meta,
  error,
  canManage,
  onRetry,
  onNew,
  onOpen,
  onPage,
}: {
  rows: PurchaseRequest[] | null
  meta: PageMeta | null
  error: string
  canManage: boolean
  onRetry: () => void
  onNew: () => void
  onOpen: (request: PurchaseRequest) => void
  onPage: (page: number) => void
}) {
  if (error) {
    return (
      <Alert variant="error">
        <AlertTitle>Purchase requests could not be loaded</AlertTitle>
        <AlertDescription>{error}</AlertDescription>
        <AlertAction>
          <Button size="xs" variant="outline" onClick={onRetry}>
            Try again
          </Button>
        </AlertAction>
      </Alert>
    )
  }
  if (rows === null) return <LoadingRows label="Loading purchase requests" />
  if (rows.length === 0) {
    return (
      <Empty className="rounded-xl border">
        <EmptyHeader>
          <EmptyTitle>No purchase requests yet</EmptyTitle>
          <EmptyDescription>
            Raise a request from the restock list or by hand. A different permitted person approves it before it becomes an order.
          </EmptyDescription>
        </EmptyHeader>
        {canManage && (
          <EmptyContent>
            <Button onClick={onNew}>
              <Plus aria-hidden />
              New request
            </Button>
          </EmptyContent>
        )}
      </Empty>
    )
  }

  return (
    <div className="flex flex-col gap-4">
      <p className="text-muted-foreground text-sm" aria-live="polite">
        {meta?.total ?? rows.length} {(meta?.total ?? rows.length) === 1 ? 'request' : 'requests'}
      </p>

      <ul className="flex flex-col divide-y rounded-xl border bg-card md:hidden">
        {rows.map((request) => {
          const status = purchaseRequestStatusMeta(request.status)
          return (
            <li key={request.id} className="flex min-w-0 flex-col gap-3 p-4">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="break-words font-medium">{request.number}</p>
                  <p className="break-words text-muted-foreground text-sm">
                    Requested by {request.requested_by_name ?? 'an unknown person'}
                  </p>
                </div>
                <Badge variant={status.variant}>{status.label}</Badge>
              </div>
              <dl className="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <dt className="text-muted-foreground">Needed by</dt>
                  <dd>{request.needed_by ? dateLabel(request.needed_by) : 'No date'}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">Estimated</dt>
                  <dd className="tabular-nums">{money(purchaseRequestEstimatedTotal(request))}</dd>
                </div>
              </dl>
              <p className="text-muted-foreground text-xs">
                {(request.lines ?? []).length} {(request.lines ?? []).length === 1 ? 'line' : 'lines'}
              </p>
              <Button size="sm" variant="outline" className="self-start" onClick={() => onOpen(request)}>
                Open request
              </Button>
            </li>
          )
        })}
      </ul>

      <div className="hidden min-w-0 overflow-x-auto md:block">
        <Table variant="card" className="min-w-[48rem]">
          <TableHeader>
            <TableRow>
              <TableHead>Number</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Requested by</TableHead>
              <TableHead>Needed by</TableHead>
              <TableHead className="text-right">Lines</TableHead>
              <TableHead className="text-right">Estimated</TableHead>
              <TableHead>
                <span className="sr-only">Actions</span>
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((request) => {
              const status = purchaseRequestStatusMeta(request.status)
              return (
                <TableRow key={request.id}>
                  <TableCell className="font-medium">{request.number}</TableCell>
                  <TableCell>
                    <Badge variant={status.variant}>{status.label}</Badge>
                  </TableCell>
                  <TableCell className="max-w-48 whitespace-normal break-words">
                    {request.requested_by_name ?? 'Unknown person'}
                  </TableCell>
                  <TableCell>{request.needed_by ? dateLabel(request.needed_by) : 'No date'}</TableCell>
                  <TableCell className="text-right tabular-nums">{(request.lines ?? []).length}</TableCell>
                  <TableCell className="text-right tabular-nums">{money(purchaseRequestEstimatedTotal(request))}</TableCell>
                  <TableCell>
                    <div className="flex justify-end">
                      <Button size="xs" variant="outline" onClick={() => onOpen(request)}>
                        Open
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      </div>

      <PageControls meta={meta} label="Purchase requests" onPage={onPage} />
    </div>
  )
}

function OrdersPanel({
  gymId,
  rows,
  meta,
  error,
  canManage,
  isOwner,
  onRetry,
  onDirect,
  onPage,
}: {
  gymId: number
  rows: PurchaseOrder[] | null
  meta: PageMeta | null
  error: string
  canManage: boolean
  isOwner: boolean
  onRetry: () => void
  onDirect: () => void
  onPage: (page: number) => void
}) {
  if (error) {
    return (
      <Alert variant="error">
        <AlertTitle>Purchase orders could not be loaded</AlertTitle>
        <AlertDescription>{error}</AlertDescription>
        <AlertAction>
          <Button size="xs" variant="outline" onClick={onRetry}>
            Try again
          </Button>
        </AlertAction>
      </Alert>
    )
  }
  if (rows === null) return <LoadingRows label="Loading purchase orders" />
  if (rows.length === 0) {
    return (
      <Empty className="rounded-xl border">
        <EmptyHeader>
          <EmptyTitle>No purchase orders yet</EmptyTitle>
          <EmptyDescription>
            An approved request becomes one order to one supplier. The owner can also record a direct purchase when they raised the request themselves.
          </EmptyDescription>
        </EmptyHeader>
        {canManage && isOwner && (
          <EmptyContent>
            <Button onClick={onDirect}>
              <Plus aria-hidden />
              Direct purchase
            </Button>
          </EmptyContent>
        )}
      </Empty>
    )
  }

  return (
    <div className="flex flex-col gap-4">
      <p className="text-muted-foreground text-sm" aria-live="polite">
        {meta?.total ?? rows.length} {(meta?.total ?? rows.length) === 1 ? 'order' : 'orders'}
      </p>

      <ul className="flex flex-col divide-y rounded-xl border bg-card md:hidden">
        {rows.map((order) => {
          const status = purchaseOrderStatusMeta(order.status)
          const progress = orderProgress(order)
          return (
            <li key={order.id} className="flex min-w-0 flex-col gap-3 p-4">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <Link className="break-words font-medium underline underline-offset-4" to={orderPath(gymId, order.id)}>
                    {order.number}
                  </Link>
                  <p className="break-words text-muted-foreground text-sm">{orderSupplierName(order)}</p>
                </div>
                <div className="flex shrink-0 flex-col items-end gap-1">
                  <Badge variant={status.variant}>{status.label}</Badge>
                  {order.is_direct && <Badge variant="outline">Owner direct</Badge>}
                </div>
              </div>
              <dl className="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <dt className="text-muted-foreground">Total</dt>
                  <dd className="font-semibold tabular-nums">{money(order.total)}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">Expected</dt>
                  <dd>{order.expected_on ? dateLabel(order.expected_on) : 'No date'}</dd>
                </div>
              </dl>
              {progress.percent !== null && (
                <div className="flex flex-col gap-1">
                  <Progress value={progress.percent} max={100} aria-label={`${order.number} received progress`} />
                  <span className="text-muted-foreground text-xs">{orderProgressLabel(order)}</span>
                </div>
              )}
              <Button
                size="sm"
                variant="outline"
                className="self-start"
                render={<Link to={orderPath(gymId, order.id)} />}
              >
                View order
              </Button>
            </li>
          )
        })}
      </ul>

      <div className="hidden min-w-0 overflow-x-auto md:block">
        <Table variant="card" className="min-w-[56rem]">
          <TableHeader>
            <TableRow>
              <TableHead>Number</TableHead>
              <TableHead>Supplier</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="text-right">Total</TableHead>
              <TableHead>Received</TableHead>
              <TableHead>Expected</TableHead>
              <TableHead>
                <span className="sr-only">Actions</span>
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((order) => {
              const status = purchaseOrderStatusMeta(order.status)
              const progress = orderProgress(order)
              return (
                <TableRow key={order.id}>
                  <TableCell className="font-medium">{order.number}</TableCell>
                  <TableCell className="max-w-48 whitespace-normal break-words">{orderSupplierName(order)}</TableCell>
                  <TableCell>
                    <div className="flex flex-col items-start gap-1">
                      <Badge variant={status.variant}>{status.label}</Badge>
                      {order.is_direct && <Badge variant="outline">Owner direct</Badge>}
                    </div>
                  </TableCell>
                  <TableCell className="text-right font-medium tabular-nums">{money(order.total)}</TableCell>
                  <TableCell className="min-w-40">
                    {progress.percent === null ? (
                      <span className="text-muted-foreground">View order</span>
                    ) : (
                      <div className="flex flex-col gap-1">
                        <Progress value={progress.percent} max={100} aria-label={`${order.number} received progress`} />
                        <span className="text-muted-foreground text-xs">{orderProgressLabel(order)}</span>
                      </div>
                    )}
                  </TableCell>
                  <TableCell>{order.expected_on ? dateLabel(order.expected_on) : 'No date'}</TableCell>
                  <TableCell>
                    <div className="flex justify-end">
                      <Button size="xs" variant="outline" render={<Link to={orderPath(gymId, order.id)} />}>
                        View
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      </div>

      <PageControls meta={meta} label="Purchase orders" onPage={onPage} />
    </div>
  )
}

function orderPath(gymId: number, orderId: number): string {
  return `/gyms/${gymId}/manage/purchasing/orders/${orderId}`
}

function SuppliersPanel({
  rows,
  meta,
  error,
  canManage,
  onRetry,
  onAdd,
  onEdit,
  onPage,
  onStatus,
}: {
  rows: Supplier[] | null
  meta: PageMeta | null
  error: string
  canManage: boolean
  onRetry: () => void
  onAdd: () => void
  onEdit: (supplier: Supplier) => void
  onPage: (page: number) => void
  onStatus: (supplier: Supplier) => void
}) {
  if (error) {
    return (
      <Alert variant="error">
        <AlertTitle>Suppliers could not be loaded</AlertTitle>
        <AlertDescription>{error}</AlertDescription>
        <AlertAction>
          <Button size="xs" variant="outline" onClick={onRetry}>
            Try again
          </Button>
        </AlertAction>
      </Alert>
    )
  }
  if (rows === null) return <LoadingRows label="Loading suppliers" />
  if (rows.length === 0) {
    return (
      <Empty className="rounded-xl border">
        <EmptyHeader>
          <EmptyTitle>No suppliers yet</EmptyTitle>
          <EmptyDescription>Add the shops and wholesalers the gym buys from so requests can become orders.</EmptyDescription>
        </EmptyHeader>
        {canManage && (
          <EmptyContent>
            <Button onClick={onAdd}>
              <Plus aria-hidden />
              Add supplier
            </Button>
          </EmptyContent>
        )}
      </Empty>
    )
  }

  return (
    <div className="flex flex-col gap-4">
      <p className="text-muted-foreground text-sm" aria-live="polite">
        {meta?.total ?? rows.length} {(meta?.total ?? rows.length) === 1 ? 'supplier' : 'suppliers'}
      </p>

      <ul className="flex flex-col divide-y rounded-xl border bg-card md:hidden">
        {rows.map((supplier) => {
          const status = supplierStatusMeta(supplier.status)
          return (
            <li key={supplier.id} className="flex min-w-0 flex-col gap-3 p-4">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <h3 className="break-words font-medium">{supplier.name}</h3>
                  {supplier.contact_person && <p className="break-words text-muted-foreground text-sm">{supplier.contact_person}</p>}
                </div>
                <Badge variant={status.variant}>{status.label}</Badge>
              </div>
              {(supplier.phone || supplier.email) && (
                <p className="break-words text-sm">{[supplier.phone, supplier.email].filter(Boolean).join(' · ')}</p>
              )}
              {supplier.payment_terms && <p className="break-words text-muted-foreground text-sm">Terms: {supplier.payment_terms}</p>}
              {supplier.address && <p className="break-words text-muted-foreground text-xs">{supplier.address}</p>}
              {canManage && (
                <div className="flex flex-wrap gap-2">
                  <Button size="sm" variant="outline" onClick={() => onEdit(supplier)}>
                    Edit
                  </Button>
                  <Button
                    size="sm"
                    variant={supplier.status === 'active' ? 'destructive-outline' : 'outline'}
                    onClick={() => onStatus(supplier)}
                  >
                    {supplier.status === 'active' ? 'Archive' : 'Activate'}
                  </Button>
                </div>
              )}
            </li>
          )
        })}
      </ul>

      <div className="hidden min-w-0 overflow-x-auto md:block">
        <Table variant="card" className="min-w-[52rem]">
          <TableHeader>
            <TableRow>
              <TableHead>Supplier</TableHead>
              <TableHead>Contact</TableHead>
              <TableHead>Phone or email</TableHead>
              <TableHead>Terms</TableHead>
              <TableHead>Status</TableHead>
              {canManage && (
                <TableHead>
                  <span className="sr-only">Actions</span>
                </TableHead>
              )}
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((supplier) => {
              const status = supplierStatusMeta(supplier.status)
              return (
                <TableRow key={supplier.id}>
                  <TableCell className="max-w-56 whitespace-normal break-words">
                    <span className="font-medium">{supplier.name}</span>
                    {supplier.address && <span className="block text-muted-foreground text-xs">{supplier.address}</span>}
                  </TableCell>
                  <TableCell className="max-w-44 whitespace-normal break-words">{supplier.contact_person ?? 'No contact'}</TableCell>
                  <TableCell className="max-w-56 whitespace-normal break-words">
                    {[supplier.phone, supplier.email].filter(Boolean).join(' · ') || 'None recorded'}
                  </TableCell>
                  <TableCell className="max-w-44 whitespace-normal break-words">{supplier.payment_terms ?? 'None'}</TableCell>
                  <TableCell>
                    <Badge variant={status.variant}>{status.label}</Badge>
                  </TableCell>
                  {canManage && (
                    <TableCell>
                      <div className="flex justify-end gap-1">
                        <Button size="xs" variant="ghost" onClick={() => onEdit(supplier)}>
                          Edit
                        </Button>
                        <Button
                          size="xs"
                          variant={supplier.status === 'active' ? 'destructive-outline' : 'outline'}
                          onClick={() => onStatus(supplier)}
                        >
                          {supplier.status === 'active' ? 'Archive' : 'Activate'}
                        </Button>
                      </div>
                    </TableCell>
                  )}
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      </div>

      <PageControls meta={meta} label="Suppliers" onPage={onPage} />
    </div>
  )
}

function PageControls({
  meta,
  label,
  onPage,
}: {
  meta: PageMeta | null
  label: string
  onPage: (page: number) => void
}) {
  if (!meta || meta.last_page <= 1) return null
  return (
    <nav aria-label={`${label} pages`} className="flex flex-wrap items-center justify-between gap-3">
      <p className="text-muted-foreground text-sm" aria-live="polite">
        Page {meta.current_page} of {meta.last_page} · {meta.total} {meta.total === 1 ? 'entry' : 'entries'}
      </p>
      <div className="flex gap-2">
        <Button size="sm" variant="outline" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>
          <ChevronLeft aria-hidden />
          Previous
        </Button>
        <Button
          size="sm"
          variant="outline"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPage(meta.current_page + 1)}
        >
          Next
          <ChevronRight aria-hidden />
        </Button>
      </div>
    </nav>
  )
}

function AddNeedDialog({
  gymId,
  restockRows,
  onClose,
  onSaved,
}: {
  gymId: number
  restockRows: RestockRow[]
  onClose: () => void
  onSaved: () => void
}) {
  const [items, setItems] = useState<ItemChoice[] | null>(null)
  const [itemId, setItemId] = useState('')
  const [quantity, setQuantity] = useState('1')
  const [mode, setMode] = useState<'date' | 'session'>('date')
  const [neededBy, setNeededBy] = useState(dayFromNow(7))
  const [sessions, setSessions] = useState<TrainingSession[] | null>(null)
  const [sessionsBlocked, setSessionsBlocked] = useState(false)
  const [sessionId, setSessionId] = useState('')
  const [note, setNote] = useState('')
  const [fallback, setFallback] = useState(false)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let live = true
    loadStockChoices(gymId, restockRows).then((result) => {
      if (!live) return
      setItems(result.items)
      setFallback(result.fallback)
    })
    api<{ sessions: TrainingSession[] }>(`/gyms/${gymId}/sessions?from=${todayIso()}&to=${dayFromNow(90)}`)
      .then((result) => {
        if (live) setSessions((result.sessions ?? []).filter((session) => session.status === 'scheduled'))
      })
      .catch(() => {
        if (!live) return
        setSessions([])
        setSessionsBlocked(true)
      })
    return () => {
      live = false
    }
  }, [gymId, restockRows])

  const count = Number(quantity)
  const quantityValid = quantity.trim() !== '' && Number.isInteger(count) && count > 0 && count <= 1000000
  const timingValid = mode === 'date' ? neededBy !== '' : sessionId !== ''
  const blocked = busy || !itemId || !quantityValid || !timingValid

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (blocked) return
    setBusy(true)
    setErrors({})
    try {
      const chosenSession = sessions?.find((session) => String(session.id) === sessionId)
      await api(`/gyms/${gymId}/supply-needs`, {
        body: {
          stock_item_id: Number(itemId),
          quantity: count,
          needed_by:
            mode === 'date' ? neededBy : chosenSession ? chosenSession.starts_at.slice(0, 10) : null,
          training_session_id: mode === 'session' ? Number(sessionId) : null,
          note: note.trim() || null,
        },
      })
      toastManager.add({ title: 'Need added.', type: 'success' })
      onSaved()
    } catch (reason) {
      setErrors(formErrors(reason, ['stock_item_id', 'quantity', 'needed_by', 'training_session_id', 'note'], 'The need could not be added.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Add a supply need</DialogTitle>
            <DialogDescription>
              Tell purchasing that a class, event or coming week will need more of an item. Open needs raise the restock shortfall.
            </DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            <Choice
              name="stock_item_id"
              label="Item"
              value={itemId}
              choices={(items ?? []).map((item) => ({
                value: String(item.id),
                label: `${item.name} · ${quantityWithUnit(item.quantity, item.unit)} on hand`,
              }))}
              onChange={setItemId}
              disabled={items === null || items.length === 0}
              required
              description={
                items === null
                  ? 'Loading items…'
                  : items.length === 0
                    ? 'No active stock items were found. Add one in Stock first.'
                    : fallback
                      ? 'Only items that already need restocking are listed. Open Stock to use the full list.'
                      : undefined
              }
            />
            <TextField
              name="quantity"
              label="Quantity needed"
              type="number"
              inputMode="numeric"
              min={1}
              max={1000000}
              step={1}
              value={quantity}
              onChange={(event) => setQuantity(event.target.value)}
              required
            />

            <fieldset className="flex flex-col gap-3">
              <legend className="font-medium text-sm">When is it needed?</legend>
              <div className="flex flex-wrap gap-2" role="group" aria-label="Need timing">
                <Button
                  type="button"
                  size="sm"
                  variant={mode === 'date' ? 'default' : 'outline'}
                  aria-pressed={mode === 'date'}
                  onClick={() => setMode('date')}
                >
                  By a date
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant={mode === 'session' ? 'default' : 'outline'}
                  aria-pressed={mode === 'session'}
                  disabled={sessionsBlocked || sessions === null || sessions.length === 0}
                  onClick={() => setMode('session')}
                >
                  For a training session
                </Button>
              </div>

              {mode === 'date' ? (
                <TextField
                  name="needed_by"
                  label="Needed by"
                  type="date"
                  value={neededBy}
                  onChange={(event) => setNeededBy(event.target.value)}
                  required
                />
              ) : (
                <Choice
                  name="training_session_id"
                  label="Training session"
                  value={sessionId}
                  choices={(sessions ?? []).map((session) => ({
                    value: String(session.id),
                    label: `${session.name} · ${dateTimeLabel(session.starts_at)}`,
                  }))}
                  onChange={setSessionId}
                  disabled={sessions === null || sessions.length === 0}
                  required
                  description={
                    sessionsBlocked
                      ? 'Session choices need the schedule permission. Use a date instead.'
                      : sessions === null
                        ? 'Loading sessions…'
                        : sessions.length === 0
                          ? 'No scheduled sessions in the next 90 days. Use a date instead.'
                          : 'The need is tied to this session.'
                  }
                />
              )}
            </fieldset>

            <Field name="note">
              <FieldLabel>
                Note <span className="font-normal text-muted-foreground">(optional)</span>
              </FieldLabel>
              <Textarea
                name="note"
                rows={3}
                maxLength={255}
                value={note}
                onChange={(event) => setNote(event.target.value)}
                placeholder="For example: sparring night, two extra boxes of tape."
              />
              <FieldError />
            </Field>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={blocked}>
              Add need
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function NewRequestDialog({
  gymId,
  seed,
  editing = null,
  restockRows,
  supplyNeeds,
  onClose,
  onSaved,
}: {
  gymId: number
  seed: RequestSeed
  editing?: PurchaseRequest | null
  restockRows: RestockRow[]
  supplyNeeds: SupplyNeed[]
  onClose: () => void
  onSaved: () => void
}) {
  const [items, setItems] = useState<ItemChoice[] | null>(null)
  const [fallback, setFallback] = useState(false)
  const [linkedNeeds, setLinkedNeeds] = useState<SupplyNeed[]>(() =>
    supplyNeeds.filter((need) => need.status === 'open'),
  )
  const [lines, setLines] = useState<NewRequestLine[]>(() =>
    seed.map((line, index) => ({
      key: `${line.stockItemId}-${index}`,
      stockItemId: line.stockItemId,
      name: line.name,
      quantity: line.quantity,
      estUnitCost: line.estUnitCost,
      supplyNeedId: line.supplyNeedId,
    })),
  )
  const [picker, setPicker] = useState('')
  const [neededBy, setNeededBy] = useState(editing?.needed_by ?? '')
  const [note, setNote] = useState(editing?.note ?? '')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let live = true
    loadStockChoices(gymId, restockRows).then((result) => {
      if (!live) return
      setItems(result.items)
      setFallback(result.fallback)
      setLines((current) =>
        current.map((line) => {
          if (line.estUnitCost.trim() !== '') return line
          const item = result.items.find((choice) => choice.id === line.stockItemId)
          return item?.unitCost ? { ...line, estUnitCost: item.unitCost } : line
        }),
      )
    })
    api<{ needs: SupplyNeed[] }>(`/gyms/${gymId}/supply-needs`)
      .then((result) => {
        if (live) setLinkedNeeds((result.needs ?? []).filter((need) => need.status === 'open'))
      })
      .catch(() => undefined)
    return () => {
      live = false
    }
  }, [gymId, restockRows])

  const lineViews = lines.map((line) => {
    const item = items?.find((choice) => choice.id === line.stockItemId)
    const quantity = Number(line.quantity)
    const quantityValid = line.quantity.trim() !== '' && Number.isInteger(quantity) && quantity > 0 && quantity <= 1000000
    const cost = line.estUnitCost.trim() === '' ? null : Number(line.estUnitCost)
    const costValid = cost === null || (Number.isFinite(cost) && cost >= 0)
    const otherNeedIds = new Set(
      lines
        .filter((other) => other.key !== line.key)
        .map((other) => other.supplyNeedId)
        .filter((id): id is number => id !== null),
    )
    const lineNeeds = linkedNeeds.filter(
      (need) =>
        need.stock_item_id === line.stockItemId &&
        (need.status === 'open' || need.id === line.supplyNeedId) &&
        (!otherNeedIds.has(need.id) || need.id === line.supplyNeedId),
    )
    const needChoices = [
      { value: 'none', label: 'No linked need' },
      ...lineNeeds.map((need) => ({
        value: String(need.id),
        label: `${need.quantity} needed · ${needTiming(need)}`,
      })),
    ]
    if (line.supplyNeedId !== null && !lineNeeds.some((need) => need.id === line.supplyNeedId)) {
      needChoices.push({ value: String(line.supplyNeedId), label: `Supply need #${line.supplyNeedId}` })
    }
    return {
      line,
      item,
      name: item?.name ?? line.name,
      quantity,
      quantityValid,
      cost,
      costValid,
      needChoices,
      needCount: lineNeeds.length,
      total: quantityValid && cost !== null && Number.isFinite(cost) ? quantity * cost : null,
    }
  })
  const available = (items ?? []).filter((item) => !lines.some((line) => line.stockItemId === item.id))
  const total = lineViews.reduce((sum, view) => sum + (view.total ?? 0), 0)
  const blocked = busy || lines.length === 0 || lineViews.some((view) => !view.quantityValid || !view.costValid)
  const lineErrors = nestedErrors(errors, 'lines')

  function addLine(value: string) {
    const stockItemId = Number(value)
    const item = items?.find((choice) => choice.id === stockItemId)
    if (!item || lines.some((line) => line.stockItemId === stockItemId)) return
    setLines((current) => [
      ...current,
      {
        key: `${item.id}-${Date.now()}`,
        stockItemId: item.id,
        name: item.name,
        quantity: '1',
        estUnitCost: item.unitCost,
        supplyNeedId: null,
      },
    ])
    setPicker('')
  }

  function updateLine(key: string, patch: Partial<NewRequestLine>) {
    setLines((current) => current.map((line) => (line.key === key ? { ...line, ...patch } : line)))
  }

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (blocked) return
    setBusy(true)
    setErrors({})
    try {
      await api(editing ? `/gyms/${gymId}/purchase-requests/${editing.id}` : `/gyms/${gymId}/purchase-requests`, {
        method: editing ? 'PUT' : 'POST',
        body: {
          needed_by: neededBy || null,
          note: note.trim() || null,
          lines: lineViews.map((view) => ({
            stock_item_id: view.line.stockItemId,
            quantity: view.quantity,
            est_unit_cost: view.cost,
            supply_need_id: view.line.supplyNeedId,
          })),
        },
      })
      toastManager.add({ title: editing ? 'Purchase request updated.' : 'Purchase request sent for approval.', type: 'success' })
      onSaved()
    } catch (reason) {
      setErrors(formErrors(reason, ['needed_by', 'note', 'lines'], 'The purchase request could not be created.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup className="sm:max-w-2xl">
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>{editing ? `Edit ${editing.number}` : 'New purchase request'}</DialogTitle>
            <DialogDescription>
              Ask for items and quantities. A different permitted person approves it before purchasing turns it into an order.
            </DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-5">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField
                name="needed_by"
                label="Needed by (optional)"
                type="date"
                value={neededBy}
                onChange={(event) => setNeededBy(event.target.value)}
              />
              <Field name="note" className="w-full">
                <FieldLabel>
                  Note <span className="font-normal text-muted-foreground">(optional)</span>
                </FieldLabel>
                <Textarea
                  name="note"
                  rows={2}
                  maxLength={5000}
                  value={note}
                  onChange={(event) => setNote(event.target.value)}
                  placeholder="Why it is needed"
                />
                <FieldError />
              </Field>
            </div>

            <fieldset className="flex flex-col gap-3">
              <legend className="font-medium text-sm">Request lines</legend>
              {seed.length > 0 && (
                <p className="text-muted-foreground text-xs">
                  Quantities start from the restock suggestion. Lower them if you need less.
                </p>
              )}
              <Choice
                name="stock_item"
                label="Add an item"
                value={picker}
                choices={available.map((item) => ({
                  value: String(item.id),
                  label: `${item.name} · ${quantityWithUnit(item.quantity, item.unit)} on hand`,
                }))}
                onChange={addLine}
                disabled={items === null || available.length === 0}
                description={
                  items === null
                    ? 'Loading items…'
                    : available.length === 0
                      ? 'Every available item is already on this request.'
                      : fallback
                        ? 'Only items that already need restocking are listed. Open Stock to use the full list.'
                        : undefined
                }
              />

              {lineViews.length === 0 ? (
                <p className="rounded-xl border px-4 py-3 text-muted-foreground text-sm">
                  Add at least one item to the request.
                </p>
              ) : (
                <ul className="flex flex-col gap-3">
                  {lineViews.map((view, index) => (
                    <li key={view.line.key} className="flex flex-col gap-3 rounded-xl border p-3">
                      <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                          <p className="break-words font-medium">{view.name}</p>
                          {view.item && (
                            <p className="text-muted-foreground text-xs">
                              {quantityWithUnit(view.item.quantity, view.item.unit)} on hand
                            </p>
                          )}
                        </div>
                        <Button
                          type="button"
                          size="icon-sm"
                          variant="ghost"
                          aria-label={`Remove ${view.name}`}
                          onClick={() => setLines((current) => current.filter((line) => line.key !== view.line.key))}
                        >
                          <X aria-hidden />
                        </Button>
                      </div>
                      <div className="grid gap-3 sm:grid-cols-2">
                        <TextField
                          name={`lines.${index}.quantity`}
                          label="Quantity"
                          type="number"
                          inputMode="numeric"
                          min={1}
                          max={1000000}
                          step={1}
                          value={view.line.quantity}
                          onChange={(event) => updateLine(view.line.key, { quantity: event.target.value })}
                          required
                        />
                        <TextField
                          name={`lines.${index}.est_unit_cost`}
                          label="Estimated unit cost (₱, optional)"
                          type="number"
                          inputMode="decimal"
                          min={0}
                          max="9999999999.99"
                          step="0.01"
                          value={view.line.estUnitCost}
                          onChange={(event) => updateLine(view.line.key, { estUnitCost: event.target.value })}
                        />
                      </div>
                      <Choice
                        name={`lines.${index}.supply_need_id`}
                        label="Open supply need (optional)"
                        value={view.line.supplyNeedId === null ? 'none' : String(view.line.supplyNeedId)}
                        choices={view.needChoices}
                        onChange={(value) => {
                          const nextId = value === 'none' ? null : Number(value)
                          const need = linkedNeeds.find((choice) => choice.id === nextId)
                          updateLine(view.line.key, {
                            supplyNeedId: nextId,
                            ...(need && Number(view.line.quantity || '0') < need.quantity
                              ? { quantity: String(need.quantity) }
                              : {}),
                          })
                        }}
                        description={
                          view.line.supplyNeedId !== null
                            ? 'When the request is sent, this need is marked as requested.'
                            : view.needCount === 0
                              ? 'No open activity needs for this item.'
                              : 'Link this line to an activity need so it does not stay open.'
                        }
                      />
                      <div className="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                        <span>
                          Estimated line total{' '}
                          <strong className="tabular-nums">{view.total === null ? '—' : money(view.total)}</strong>
                        </span>
                        {!view.costValid && <span className="text-destructive">Enter a valid cost or leave it blank.</span>}
                      </div>
                    </li>
                  ))}
                </ul>
              )}

              {lineViews.length > 0 && (
                <p className="text-sm">
                  Estimated total <strong className="tabular-nums">{money(total)}</strong>
                </p>
              )}
              {lineErrors.length > 0 && (
                <Alert variant="error">
                  <AlertTitle>Check the request lines</AlertTitle>
                  <AlertDescription>{lineErrors.join(' ')}</AlertDescription>
                </Alert>
              )}
            </fieldset>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={blocked}>
              Send request
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function RequestDetailDialog({
  gymId,
  request,
  viewerId,
  canApprove,
  canManage,
  canManageOrders,
  canViewOrders,
  canViewSuppliers,
  onClose,
  onChanged,
  onOrder,
  onEdit,
}: {
  gymId: number
  request: PurchaseRequest
  viewerId: number | null
  canApprove: boolean
  canManage: boolean
  canManageOrders: boolean
  canViewOrders: boolean
  canViewSuppliers: boolean
  onClose: () => void
  onChanged: (request: PurchaseRequest) => void
  onOrder: (request: PurchaseRequest) => void
  onEdit: (request: PurchaseRequest) => void
}) {
  const [mode, setMode] = useState<'approve' | 'reject' | 'cancel' | null>(null)
  const [reason, setReason] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  const status = purchaseRequestStatusMeta(request.status)
  const isRequester = viewerId !== null && String(request.requested_by) === String(viewerId)
  const pending = request.status === 'pending'
  const lines = request.lines ?? []
  const showDecision = pending && canApprove && !isRequester
  const raisedOrderId = request.purchase_order_id ?? request.purchase_order?.id ?? null
  const raisedOrderNumber = request.purchase_order?.number ?? null

  async function decide(decision: 'approve' | 'reject') {
    if (busy) return
    if (decision === 'reject' && !reason.trim()) {
      setErrors({ reason: ['Give a reason for the rejection.'] })
      return
    }
    setBusy(true)
    setErrors({})
    try {
      const result = await api<{ request: PurchaseRequest }>(`/gyms/${gymId}/purchase-requests/${request.id}/decision`, {
        body: { decision, reason: reason.trim() || null },
      })
      toastManager.add({
        title: decision === 'approve' ? 'Purchase request approved.' : 'Purchase request rejected.',
        type: 'success',
      })
      const updated = result.request ?? {
        ...request,
        status: decision === 'approve' ? 'approved' : 'rejected',
        decided_at: new Date().toISOString(),
        decision_reason: reason.trim() || null,
      }
      setMode(null)
      setReason('')
      onChanged(updated)
    } catch (failure) {
      setErrors(formErrors(failure, ['decision', 'reason'], 'The decision could not be saved.'))
    } finally {
      setBusy(false)
    }
  }

  async function cancelRequest() {
    if (busy) return
    setBusy(true)
    setErrors({})
    try {
      const result = await api<{ request: PurchaseRequest }>(`/gyms/${gymId}/purchase-requests/${request.id}/cancel`, {
        body: {},
      })
      toastManager.add({ title: 'Purchase request cancelled.', type: 'success' })
      setMode(null)
      onChanged(result.request ?? { ...request, status: 'cancelled' })
    } catch (failure) {
      setErrors(formErrors(failure, ['reason'], 'The request could not be cancelled.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup className="sm:max-w-2xl">
        <DialogHeader>
          <div className="flex flex-wrap items-center gap-2">
            <DialogTitle>{request.number}</DialogTitle>
            <Badge variant={status.variant}>{status.label}</Badge>
            {raisedOrderId !== null && (
              <Badge variant="success">{raisedOrderNumber ? `Order ${raisedOrderNumber}` : 'Order raised'}</Badge>
            )}
          </div>
          <DialogDescription>
            Requested by {request.requested_by_name ?? 'an unknown person'}
            {request.needed_by ? ` · Needed by ${dateLabel(request.needed_by)}` : ''}
          </DialogDescription>
        </DialogHeader>

        <DialogPanel className="flex flex-col gap-5">
          <dl className="grid grid-cols-2 gap-3 rounded-xl border p-3 text-sm sm:grid-cols-3">
            <div>
              <dt className="text-muted-foreground">Requested by</dt>
              <dd className="break-words font-medium">{request.requested_by_name ?? 'Unknown person'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Needed by</dt>
              <dd>{request.needed_by ? dateLabel(request.needed_by) : 'No date'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Estimated total</dt>
              <dd className="font-semibold tabular-nums">{money(purchaseRequestEstimatedTotal(request))}</dd>
            </div>
          </dl>

          {request.note && <p className="whitespace-pre-wrap break-words rounded-xl border px-4 py-3 text-sm">{request.note}</p>}

          <section aria-labelledby="request-lines-heading" className="flex flex-col gap-3">
            <h2 id="request-lines-heading" className="font-medium text-sm">
              Items
            </h2>
            {lines.length === 0 ? (
              <p className="text-muted-foreground text-sm">No lines were returned for this request.</p>
            ) : (
              <>
                <ul className="flex flex-col divide-y rounded-xl border bg-card md:hidden">
                  {lines.map((line) => (
                    <li key={line.id} className="flex flex-col gap-1 p-3 text-sm">
                      <div className="flex items-start justify-between gap-3">
                        <span className="min-w-0 break-words font-medium">{purchaseRequestLineName(line)}</span>
                        <span className="shrink-0 tabular-nums">{line.quantity} requested</span>
                      </div>
                      <span className="text-muted-foreground">
                        {line.est_unit_cost === null ? 'No estimate' : `${money(line.est_unit_cost)} each`}
                        {line.est_unit_cost !== null ? ` · ${money(purchaseRequestLineAmount(line))}` : ''}
                      </span>
                    </li>
                  ))}
                </ul>
                <div className="hidden min-w-0 overflow-x-auto md:block">
                  <Table variant="card" className="min-w-[32rem]">
                    <TableHeader>
                      <TableRow>
                        <TableHead>Item</TableHead>
                        <TableHead className="text-right">Quantity</TableHead>
                        <TableHead className="text-right">Est. unit cost</TableHead>
                        <TableHead className="text-right">Est. amount</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {lines.map((line) => (
                        <TableRow key={line.id}>
                          <TableCell className="max-w-64 whitespace-normal break-words">{purchaseRequestLineName(line)}</TableCell>
                          <TableCell className="text-right tabular-nums">{line.quantity}</TableCell>
                          <TableCell className="text-right tabular-nums">
                            {line.est_unit_cost === null ? '—' : money(line.est_unit_cost)}
                          </TableCell>
                          <TableCell className="text-right tabular-nums">{money(purchaseRequestLineAmount(line))}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </>
            )}
          </section>

          {request.decided_by_name && (
            <p className="text-muted-foreground text-sm">
              Decided by {request.decided_by_name}
              {request.decided_at ? ` on ${dateTimeLabel(request.decided_at)}` : ''}.
            </p>
          )}
          {request.decision_reason && (
            <Alert variant={request.status === 'rejected' ? 'error' : 'default'}>
              <AlertTitle>{request.status === 'rejected' ? 'Reason for rejection' : 'Decision note'}</AlertTitle>
              <AlertDescription>{request.decision_reason}</AlertDescription>
            </Alert>
          )}

          {mode === 'approve' && (
            <Alert variant="info">
              <AlertTitle>Approve this request?</AlertTitle>
              <AlertDescription>
                Purchasing can then turn it into one purchase order to one supplier. Quantities can go down but never above this request.
              </AlertDescription>
            </Alert>
          )}
          {mode === 'cancel' && (
            <Alert variant="warning">
              <AlertTitle>Cancel this request?</AlertTitle>
              <AlertDescription>It can no longer be approved or turned into an order. The history stays on record.</AlertDescription>
            </Alert>
          )}
          {mode === 'reject' && (
            <Field name="reason" className="w-full">
              <FieldLabel>Reason for rejection</FieldLabel>
              <Textarea
                name="reason"
                rows={3}
                maxLength={5000}
                value={reason}
                onChange={(event) => setReason(event.target.value)}
                required
                autoFocus
                placeholder="For example: buy from the current supplier first, the price is lower."
              />
              <FieldError />
            </Field>
          )}

          {errors.reason && <p className="text-destructive text-sm">{errors.reason.join(' ')}</p>}
        </DialogPanel>

        <DialogFooter>
          {mode === null ? (
            <>
              <Button variant="outline" onClick={onClose}>
                Close
              </Button>
              {pending && canManage && (
                <Button variant="destructive-outline" onClick={() => setMode('cancel')}>
                  Cancel request
                </Button>
              )}
              {pending && canManage && (isRequester || canApprove) && (
                <Button variant="outline" onClick={() => onEdit(request)}>
                  Edit
                </Button>
              )}
              {showDecision && (
                <>
                  <Button variant="destructive-outline" onClick={() => setMode('reject')}>
                    Reject
                  </Button>
                  <Button onClick={() => setMode('approve')}>Approve</Button>
                </>
              )}
              {request.status === 'approved' && canManageOrders && canViewSuppliers && raisedOrderId === null && (
                <Button onClick={() => onOrder(request)}>Create purchase order</Button>
              )}
              {raisedOrderId !== null && canViewOrders && (
                <Button variant="outline" render={<Link to={orderPath(gymId, raisedOrderId)} />}>
                  View purchase order
                </Button>
              )}
            </>
          ) : (
            <>
              <Button variant="outline" disabled={busy} onClick={() => setMode(null)}>
                Back
              </Button>
              {mode === 'approve' && (
                <Button loading={busy} onClick={() => decide('approve')}>
                  Approve request
                </Button>
              )}
              {mode === 'reject' && (
                <Button variant="destructive" loading={busy} onClick={() => decide('reject')}>
                  Reject request
                </Button>
              )}
              {mode === 'cancel' && (
                <Button variant="destructive" loading={busy} onClick={cancelRequest}>
                  Cancel request
                </Button>
              )}
            </>
          )}
        </DialogFooter>
      </DialogPopup>
    </Dialog>
  )
}

function CreateOrderDialog({
  gymId,
  request,
  onClose,
  onSaved,
}: {
  gymId: number
  request: PurchaseRequest
  onClose: () => void
  onSaved: (order: PurchaseOrder) => void
}) {
  const [suppliers, setSuppliers] = useState<Supplier[] | null>(null)
  const [suppliersError, setSuppliersError] = useState('')
  const [supplierId, setSupplierId] = useState('')
  const [expectedOn, setExpectedOn] = useState(dayFromNow(7))
  const [lines, setLines] = useState(() =>
    (request.lines ?? []).map((line) => ({
      requestLineId: line.id,
      stockItemId: line.stock_item_id,
      name: purchaseRequestLineName(line),
      max: line.quantity,
      quantity: String(line.quantity),
      unitCost: line.est_unit_cost ?? '',
    })),
  )
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let live = true
    setSuppliers(null)
    setSuppliersError('')
    loadSuppliers(gymId)
      .then((people) => {
        if (live) setSuppliers(people.filter((supplier) => supplier.status === 'active'))
      })
      .catch((reason) => {
        if (!live) return
        setSuppliers([])
        setSuppliersError(message(reason, 'Suppliers could not be loaded.'))
      })
    return () => {
      live = false
    }
  }, [gymId])

  const lineViews = lines.map((line) => {
    const quantity = Number(line.quantity)
    const quantityValid = line.quantity.trim() !== '' && Number.isInteger(quantity) && quantity > 0 && quantity <= line.max
    const cost = line.unitCost.trim() === '' ? Number.NaN : Number(line.unitCost)
    const costValid = Number.isFinite(cost) && cost >= 0
    return { line, quantity, quantityValid, cost, costValid, total: quantityValid && costValid ? quantity * cost : 0 }
  })
  const total = lineViews.reduce((sum, view) => sum + view.total, 0)
  const blocked =
    busy ||
    supplierId === '' ||
    lines.length === 0 ||
    lineViews.some((view) => !view.quantityValid || !view.costValid)
  const lineErrors = nestedErrors(errors, 'lines')

  function updateLine(requestLineId: number, patch: { quantity?: string; unitCost?: string }) {
    setLines((current) => current.map((line) => (line.requestLineId === requestLineId ? { ...line, ...patch } : line)))
  }

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (blocked) return
    setBusy(true)
    setErrors({})
    try {
      const result = await api<{ order: PurchaseOrder }>(`/gyms/${gymId}/purchase-orders`, {
        body: {
          purchase_request_id: request.id,
          supplier_id: Number(supplierId),
          expected_on: expectedOn || null,
          lines: lineViews.map((view) => ({
            purchase_request_line_id: view.line.requestLineId,
            stock_item_id: view.line.stockItemId,
            quantity: view.quantity,
            unit_cost: view.cost,
          })),
        },
      })
      toastManager.add({ title: 'Purchase order created.', type: 'success' })
      onSaved(result.order)
    } catch (failure) {
      setErrors(formErrors(failure, ['purchase_request_id', 'supplier_id', 'expected_on', 'lines'], 'The purchase order could not be created.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup className="sm:max-w-2xl">
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Create a purchase order</DialogTitle>
            <DialogDescription>
              {request.number}: choose one supplier and enter the unit cost for each item. Quantities cannot be above the request.
            </DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            {suppliersError && (
              <Alert variant="error">
                <AlertTitle>Suppliers could not be loaded</AlertTitle>
                <AlertDescription>{suppliersError}</AlertDescription>
              </Alert>
            )}
            <div className="grid gap-4 sm:grid-cols-2">
              <Choice
                name="supplier_id"
                label="Supplier"
                value={supplierId}
                choices={(suppliers ?? []).map((supplier) => ({ value: String(supplier.id), label: supplier.name }))}
                onChange={setSupplierId}
                disabled={suppliers === null || suppliers.length === 0}
                required
                description={
                  suppliers === null
                    ? 'Loading suppliers…'
                    : suppliers.length === 0
                      ? 'No active suppliers. Add one in the Suppliers tab first.'
                      : undefined
                }
              />
              <TextField
                name="expected_on"
                label="Expected delivery (optional)"
                type="date"
                value={expectedOn}
                onChange={(event) => setExpectedOn(event.target.value)}
              />
            </div>

            <fieldset className="flex flex-col gap-3">
              <legend className="font-medium text-sm">Order lines</legend>
              <ul className="flex flex-col gap-3">
                {lineViews.map((view, index) => (
                  <li key={view.line.requestLineId} className="flex flex-col gap-3 rounded-xl border p-3">
                    <div>
                      <p className="break-words font-medium">{view.line.name}</p>
                      <p className="text-muted-foreground text-xs">{view.line.max} requested</p>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                      <TextField
                        name={`lines.${index}.quantity`}
                        label="Quantity ordered"
                        type="number"
                        inputMode="numeric"
                        min={1}
                        max={view.line.max}
                        step={1}
                        value={view.line.quantity}
                        onChange={(event) => updateLine(view.line.requestLineId, { quantity: event.target.value })}
                        required
                      />
                      <TextField
                        name={`lines.${index}.unit_cost`}
                        label="Unit cost (₱)"
                        type="number"
                        inputMode="decimal"
                        min={0}
                        max="9999999999.99"
                        step="0.01"
                        value={view.line.unitCost}
                        onChange={(event) => updateLine(view.line.requestLineId, { unitCost: event.target.value })}
                        required
                      />
                    </div>
                    <div className="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                      <span>
                        Line total <strong className="tabular-nums">{view.quantityValid && view.costValid ? money(view.total) : '—'}</strong>
                      </span>
                      {!view.quantityValid && <span className="text-destructive">Use 1 to {view.line.max}.</span>}
                      {!view.costValid && <span className="text-destructive">Enter the unit cost.</span>}
                    </div>
                  </li>
                ))}
              </ul>
              {lineErrors.length > 0 && (
                <Alert variant="error">
                  <AlertTitle>Check the order lines</AlertTitle>
                  <AlertDescription>{lineErrors.join(' ')}</AlertDescription>
                </Alert>
              )}
              <p className="text-sm">
                Order total <strong className="tabular-nums">{money(total)}</strong>
              </p>
            </fieldset>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={blocked}>
              Create order
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function DirectPurchaseDialog({
  gymId,
  restockRows,
  onClose,
  onSaved,
}: {
  gymId: number
  restockRows: RestockRow[]
  onClose: () => void
  onSaved: (order: PurchaseOrder) => void
}) {
  const [items, setItems] = useState<ItemChoice[] | null>(null)
  const [suppliers, setSuppliers] = useState<Supplier[] | null>(null)
  const [suppliersError, setSuppliersError] = useState('')
  const [supplierId, setSupplierId] = useState('')
  const [expectedOn, setExpectedOn] = useState(dayFromNow(7))
  const [lines, setLines] = useState<DirectLine[]>([])
  const [picker, setPicker] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let live = true
    loadStockChoices(gymId, restockRows).then((result) => {
      if (live) setItems(result.items)
    })
    loadSuppliers(gymId)
      .then((people) => {
        if (live) setSuppliers(people.filter((supplier) => supplier.status === 'active'))
      })
      .catch((reason) => {
        if (!live) return
        setSuppliers([])
        setSuppliersError(message(reason, 'Suppliers could not be loaded.'))
      })
    return () => {
      live = false
    }
  }, [gymId, restockRows])

  const available = (items ?? []).filter((item) => !lines.some((line) => line.stockItemId === item.id))
  const lineViews = lines.map((line) => {
    const item = items?.find((choice) => choice.id === line.stockItemId)
    const quantity = Number(line.quantity)
    const quantityValid = line.quantity.trim() !== '' && Number.isInteger(quantity) && quantity > 0 && quantity <= 1000000
    const cost = line.unitCost.trim() === '' ? Number.NaN : Number(line.unitCost)
    const costValid = Number.isFinite(cost) && cost >= 0
    return { line, item, quantity, quantityValid, cost, costValid, total: quantityValid && costValid ? quantity * cost : 0 }
  })
  const total = lineViews.reduce((sum, view) => sum + view.total, 0)
  const blocked = busy || supplierId === '' || lines.length === 0 || lineViews.some((view) => !view.quantityValid || !view.costValid)
  const lineErrors = nestedErrors(errors, 'lines')

  function addLine(value: string) {
    const stockItemId = Number(value)
    const item = items?.find((choice) => choice.id === stockItemId)
    if (!item || lines.some((line) => line.stockItemId === stockItemId)) return
    setLines((current) => [
      ...current,
      { key: `${item.id}-${Date.now()}`, stockItemId: item.id, quantity: '1', unitCost: item.unitCost },
    ])
    setPicker('')
  }

  function updateLine(key: string, patch: Partial<DirectLine>) {
    setLines((current) => current.map((line) => (line.key === key ? { ...line, ...patch } : line)))
  }

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (blocked) return
    setBusy(true)
    setErrors({})
    try {
      const result = await api<{ order: PurchaseOrder }>(`/gyms/${gymId}/purchase-orders/direct`, {
        body: {
          supplier_id: Number(supplierId),
          expected_on: expectedOn || null,
          lines: lineViews.map((view) => ({
            stock_item_id: view.line.stockItemId,
            quantity: view.quantity,
            unit_cost: view.cost,
          })),
        },
      })
      toastManager.add({ title: 'Owner direct purchase recorded.', type: 'success' })
      onSaved(result.order)
    } catch (failure) {
      setErrors(formErrors(failure, ['supplier_id', 'expected_on', 'lines'], 'The direct purchase could not be recorded.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup className="sm:max-w-2xl">
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Owner direct purchase</DialogTitle>
            <DialogDescription>
              The only bypass when no other approver exists. It is marked as an owner direct purchase in the order history.
            </DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            {suppliersError && (
              <Alert variant="error">
                <AlertTitle>Suppliers could not be loaded</AlertTitle>
                <AlertDescription>{suppliersError}</AlertDescription>
              </Alert>
            )}
            <div className="grid gap-4 sm:grid-cols-2">
              <Choice
                name="supplier_id"
                label="Supplier"
                value={supplierId}
                choices={(suppliers ?? []).map((supplier) => ({ value: String(supplier.id), label: supplier.name }))}
                onChange={setSupplierId}
                disabled={suppliers === null || suppliers.length === 0}
                required
                description={
                  suppliers === null
                    ? 'Loading suppliers…'
                    : suppliers.length === 0
                      ? 'No active suppliers. Add one in the Suppliers tab first.'
                      : undefined
                }
              />
              <TextField
                name="expected_on"
                label="Expected delivery (optional)"
                type="date"
                value={expectedOn}
                onChange={(event) => setExpectedOn(event.target.value)}
              />
            </div>

            <fieldset className="flex flex-col gap-3">
              <legend className="font-medium text-sm">Items</legend>
              <Choice
                name="stock_item"
                label="Add an item"
                value={picker}
                choices={available.map((item) => ({
                  value: String(item.id),
                  label: `${item.name} · ${quantityWithUnit(item.quantity, item.unit)} on hand`,
                }))}
                onChange={addLine}
                disabled={items === null || available.length === 0}
                description={
                  items === null
                    ? 'Loading items…'
                    : available.length === 0
                      ? 'Every available item is already on this purchase.'
                      : undefined
                }
              />

              {lineViews.length === 0 ? (
                <p className="rounded-xl border px-4 py-3 text-muted-foreground text-sm">
                  Add at least one item to the purchase.
                </p>
              ) : (
                <ul className="flex flex-col gap-3">
                  {lineViews.map((view, index) => (
                    <li key={view.line.key} className="flex flex-col gap-3 rounded-xl border p-3">
                      <div className="flex items-start justify-between gap-3">
                        <p className="min-w-0 break-words font-medium">{view.item?.name ?? 'Item'}</p>
                        <Button
                          type="button"
                          size="icon-sm"
                          variant="ghost"
                          aria-label={`Remove ${view.item?.name ?? 'item'}`}
                          onClick={() => setLines((current) => current.filter((line) => line.key !== view.line.key))}
                        >
                          <X aria-hidden />
                        </Button>
                      </div>
                      <div className="grid gap-3 sm:grid-cols-2">
                        <TextField
                          name={`lines.${index}.quantity`}
                          label="Quantity ordered"
                          type="number"
                          inputMode="numeric"
                          min={1}
                          max={1000000}
                          step={1}
                          value={view.line.quantity}
                          onChange={(event) => updateLine(view.line.key, { quantity: event.target.value })}
                          required
                        />
                        <TextField
                          name={`lines.${index}.unit_cost`}
                          label="Unit cost (₱)"
                          type="number"
                          inputMode="decimal"
                          min={0}
                          max="9999999999.99"
                          step="0.01"
                          value={view.line.unitCost}
                          onChange={(event) => updateLine(view.line.key, { unitCost: event.target.value })}
                          required
                        />
                      </div>
                      <div className="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                        <span>
                          Line total <strong className="tabular-nums">{view.quantityValid && view.costValid ? money(view.total) : '—'}</strong>
                        </span>
                        {!view.quantityValid && <span className="text-destructive">Enter a quantity above zero.</span>}
                        {!view.costValid && <span className="text-destructive">Enter the unit cost.</span>}
                      </div>
                    </li>
                  ))}
                </ul>
              )}
              {lineErrors.length > 0 && (
                <Alert variant="error">
                  <AlertTitle>Check the purchase lines</AlertTitle>
                  <AlertDescription>{lineErrors.join(' ')}</AlertDescription>
                </Alert>
              )}
              <p className="text-sm">
                Purchase total <strong className="tabular-nums">{money(total)}</strong>
              </p>
            </fieldset>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={blocked}>
              Record purchase
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function SupplierDialog({
  gymId,
  supplier,
  onClose,
  onSaved,
}: {
  gymId: number
  supplier: Supplier | null
  onClose: () => void
  onSaved: () => void
}) {
  const [name, setName] = useState(supplier?.name ?? '')
  const [contactPerson, setContactPerson] = useState(supplier?.contact_person ?? '')
  const [phone, setPhone] = useState(supplier?.phone ?? '')
  const [email, setEmail] = useState(supplier?.email ?? '')
  const [address, setAddress] = useState(supplier?.address ?? '')
  const [paymentTerms, setPaymentTerms] = useState(supplier?.payment_terms ?? '')
  const [notes, setNotes] = useState(supplier?.notes ?? '')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (busy || name.trim() === '') return
    setBusy(true)
    setErrors({})
    try {
      const body = {
        name: name.trim(),
        contact_person: contactPerson.trim() || null,
        phone: phone.trim() || null,
        email: email.trim() || null,
        address: address.trim() || null,
        payment_terms: paymentTerms.trim() || null,
        notes: notes.trim() || null,
      }
      if (supplier) {
        await api(`/gyms/${gymId}/suppliers/${supplier.id}`, { method: 'PUT', body })
        toastManager.add({ title: 'Supplier saved.', type: 'success' })
      } else {
        await api(`/gyms/${gymId}/suppliers`, { body })
        toastManager.add({ title: 'Supplier added.', type: 'success' })
      }
      onSaved()
    } catch (reason) {
      setErrors(
        formErrors(
          reason,
          ['name', 'contact_person', 'phone', 'email', 'address', 'payment_terms', 'notes'],
          'The supplier could not be saved.',
        ),
      )
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup className="sm:max-w-xl">
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>{supplier ? 'Edit supplier' : 'Add supplier'}</DialogTitle>
            <DialogDescription>Keep the contact and payment terms purchasing needs.</DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            <TextField
              name="name"
              label="Supplier name"
              value={name}
              onChange={(event) => setName(event.target.value)}
              maxLength={120}
              required
              autoFocus
            />
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField
                name="contact_person"
                label="Contact person (optional)"
                value={contactPerson}
                onChange={(event) => setContactPerson(event.target.value)}
                maxLength={120}
              />
              <TextField
                name="phone"
                label="Phone (optional)"
                type="tel"
                value={phone}
                onChange={(event) => setPhone(event.target.value)}
                maxLength={40}
              />
              <TextField
                name="email"
                label="Email (optional)"
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                maxLength={150}
              />
              <TextField
                name="payment_terms"
                label="Payment terms (optional)"
                value={paymentTerms}
                onChange={(event) => setPaymentTerms(event.target.value)}
                maxLength={120}
                placeholder="For example: 30 days"
              />
            </div>
            <TextField
              name="address"
              label="Address (optional)"
              value={address}
              onChange={(event) => setAddress(event.target.value)}
              maxLength={255}
            />
            <Field name="notes">
              <FieldLabel>
                Notes <span className="font-normal text-muted-foreground">(optional)</span>
              </FieldLabel>
              <Textarea
                name="notes"
                rows={3}
                maxLength={5000}
                value={notes}
                onChange={(event) => setNotes(event.target.value)}
                placeholder="Delivery days, account number, or who to ask for."
              />
              <FieldError />
            </Field>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={name.trim() === ''}>
              {supplier ? 'Save supplier' : 'Add supplier'}
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}
