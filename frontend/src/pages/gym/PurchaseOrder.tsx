import { useEffect, useState } from 'react'
import { ArrowLeft, PackageCheck } from 'lucide-react'
import { Link, useParams } from 'react-router'
import { PageHeader } from '@/components/page-header'
import { TextField } from '@/components/text-field'
import { Alert, AlertAction, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogDescription, DialogFooter, DialogHeader, DialogPanel, DialogPopup, DialogTitle } from '@/components/ui/dialog'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Form } from '@/components/ui/form'
import { Skeleton } from '@/components/ui/skeleton'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { toastManager } from '@/components/ui/toast'
import { api, ApiError, type FieldErrors } from '@/lib/api'
import { useAuth } from '@/lib/auth'
import { approvedGym, can } from '@/lib/gym-access'
import { dateLabel, dateTimeLabel } from '@/lib/membership'
import {
  money,
  orderProgressLabel,
  orderSupplierName,
  purchaseOrderLineAmount,
  purchaseOrderLineName,
  purchaseOrderLineUnit,
  purchaseOrderStatusMeta,
  quantityWithUnit,
  receiptLineName,
  receiptNumber,
  remainingQuantity,
  timelineActor,
  timelineAt,
  timelineLabel,
  type GoodsReceipt,
  type PurchaseOrder,
  type PurchaseOrderLine,
  type PurchaseOrderTimelineEvent,
} from '@/lib/procurement'

type OrderDetail = {
  order: PurchaseOrder
  lines: PurchaseOrderLine[]
  receipts: GoodsReceipt[]
  timeline: PurchaseOrderTimelineEvent[]
}

type OrderDetailResponse = {
  order: PurchaseOrder
  lines?: PurchaseOrderLine[] | null
  receipts?: GoodsReceipt[] | null
  timeline?: PurchaseOrderTimelineEvent[] | null
}

const todayIso = () => {
  const date = new Date()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
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

function normaliseDetail(payload: OrderDetailResponse): OrderDetail {
  const order = payload.order
  return {
    order,
    lines: payload.lines ?? order.lines ?? [],
    receipts: payload.receipts ?? order.receipts ?? [],
    timeline: payload.timeline ?? order.timeline ?? [],
  }
}

function derivedTimeline(order: PurchaseOrder): PurchaseOrderTimelineEvent[] {
  const events: PurchaseOrderTimelineEvent[] = []
  if (order.created_at) {
    events.push({ type: 'created', at: order.created_at })
  }
  if (order.is_direct) {
    events.push({ type: 'direct', at: order.created_at })
  }
  events.push({
    status: order.status,
    at: order.updated_at ?? order.created_at ?? null,
    note: order.cancel_reason,
  })
  return events
}

function LoadingBody() {
  return (
    <div className="flex flex-col gap-3" aria-busy="true" aria-label="Loading purchase order">
      <Skeleton className="h-24" />
      <Skeleton className="h-48" />
      <Skeleton className="h-32" />
    </div>
  )
}

/** S-30c, R-78 and R-79: one purchase order, its delivery history and status. */
export default function PurchaseOrder() {
  const { gymId: gymIdParam, orderId: orderIdParam } = useParams()
  const gymId = Number(gymIdParam)
  const orderId = Number(orderIdParam)
  const { user } = useAuth()
  const gym = approvedGym(user, gymId)

  const canView = gym ? can(gym, 'procurement.orders.view') : false
  const canManage = gym ? can(gym, 'procurement.orders.manage') : false
  const canReceive = gym ? can(gym, 'procurement.receipts.manage') : false

  const [detail, setDetail] = useState<OrderDetail | null>(null)
  const [error, setError] = useState('')
  const [notFound, setNotFound] = useState(false)
  const [version, setVersion] = useState(0)
  const [receiveOpen, setReceiveOpen] = useState(false)
  const [reasonMode, setReasonMode] = useState<'cancel' | 'close' | null>(null)

  useEffect(() => {
    if (!gymId || !orderId || !canView) return
    let live = true
    setDetail(null)
    setError('')
    setNotFound(false)
    api<OrderDetailResponse>(`/gyms/${gymId}/purchase-orders/${orderId}`)
      .then((payload) => {
        if (live) setDetail(normaliseDetail(payload))
      })
      .catch((reason) => {
        if (!live) return
        setDetail(null)
        if (reason instanceof ApiError && reason.status === 404) {
          setNotFound(true)
        } else {
          setError(message(reason, 'The purchase order could not be loaded.'))
        }
      })
    return () => {
      live = false
    }
  }, [gymId, orderId, canView, version])

  if (!gym) return null

  const back = (
    <Button size="sm" variant="outline" render={<Link to={`/gyms/${gymId}/manage/purchasing?tab=orders`} />}>
      <ArrowLeft aria-hidden />
      Purchasing
    </Button>
  )

  if (!canView) {
    return (
      <div className="flex flex-col gap-6">
        <PageHeader title="Purchase order" actions={back} />
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>Purchase orders are not available</EmptyTitle>
            <EmptyDescription>Ask the owner for the purchase order permission.</EmptyDescription>
          </EmptyHeader>
        </Empty>
      </div>
    )
  }

  if (notFound) {
    return (
      <div className="flex flex-col gap-6">
        <PageHeader title="Purchase order" actions={back} />
        <Empty className="rounded-xl border">
          <EmptyHeader>
            <EmptyTitle>Order not found</EmptyTitle>
            <EmptyDescription>It may belong to another gym, or it may have been removed. Check the orders list.</EmptyDescription>
          </EmptyHeader>
          <EmptyContent>
            <Button render={<Link to={`/gyms/${gymId}/manage/purchasing`} />}>Back to purchasing</Button>
          </EmptyContent>
        </Empty>
      </div>
    )
  }

  if (error) {
    return (
      <div className="flex flex-col gap-6">
        <PageHeader title="Purchase order" actions={back} />
        <Alert variant="error">
          <AlertTitle>The order could not be loaded</AlertTitle>
          <AlertDescription>{error}</AlertDescription>
          <AlertAction>
            <Button size="xs" variant="outline" onClick={() => setVersion((value) => value + 1)}>
              Try again
            </Button>
          </AlertAction>
        </Alert>
      </div>
    )
  }

  if (!detail) {
    return (
      <div className="flex flex-col gap-6">
        <PageHeader title="Purchase order" actions={back} />
        <LoadingBody />
      </div>
    )
  }

  const { order, lines, receipts, timeline } = detail
  const status = purchaseOrderStatusMeta(order.status)
  const unitTotal = lines.reduce((sum, line) => sum + purchaseOrderLineAmount(line), 0)
  const total = Number(order.total) || unitTotal
  const receivedValue =
    order.received_total !== null && order.received_total !== undefined
      ? Number(order.received_total)
      : lines.reduce((sum, line) => sum + Number(line.quantity_received ?? 0) * Number(line.unit_cost ?? 0), 0)
  const remainingValue = Math.max(0, total - (Number.isFinite(receivedValue) ? receivedValue : 0))
  const hasRemaining = lines.some((line) => remainingQuantity(line) > 0)
  const events = timeline.length > 0 ? timeline : derivedTimeline(order)
  const canReceiveNow = canReceive && (order.status === 'pending' || order.status === 'partly_received') && hasRemaining

  function refresh() {
    setVersion((value) => value + 1)
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={order.number}
        meta={
          <>
            <Badge variant={status.variant}>{status.label}</Badge>
            {order.is_direct && <Badge variant="outline">Owner direct purchase</Badge>}
            {order.closed_short && <Badge variant="warning">Closed with remainder</Badge>}
          </>
        }
        description={
          <>
            {orderSupplierName(order)}
            {order.ordered_by_name ? ` · Ordered by ${order.ordered_by_name}` : ''}
            {order.expected_on ? ` · Expected ${dateLabel(order.expected_on)}` : ''}
          </>
        }
        actions={
          <>
            {back}
            {canReceiveNow && (
              <Button size="sm" onClick={() => setReceiveOpen(true)}>
                <PackageCheck aria-hidden />
                Receive
              </Button>
            )}
            {canManage && order.status === 'pending' && (
              <Button size="sm" variant="destructive-outline" onClick={() => setReasonMode('cancel')}>
                Cancel order
              </Button>
            )}
            {canManage && order.status === 'partly_received' && (
              <Button size="sm" variant="outline" onClick={() => setReasonMode('close')}>
                Close with remainder
              </Button>
            )}
          </>
        }
      />

      {order.status === 'cancelled' && order.cancel_reason ? (
        <Alert variant="error">
          <AlertTitle>Order cancelled</AlertTitle>
          <AlertDescription>{order.cancel_reason}</AlertDescription>
        </Alert>
      ) : order.closed_short ? (
        <Alert variant="warning">
          <AlertTitle>Closed with remainder</AlertTitle>
          <AlertDescription>
            {order.cancel_reason ? `${order.cancel_reason}. ` : ''}
            Some ordered quantities were never received. The order is finished and no more goods can be received against it.
          </AlertDescription>
        </Alert>
      ) : null}

      <section aria-labelledby="order-summary-heading" className="flex flex-col gap-3">
        <h2 id="order-summary-heading" className="sr-only">
          Order summary
        </h2>
        <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-xl border bg-border lg:grid-cols-4">
          <div className="bg-card px-3 py-2.5">
            <dt className="text-muted-foreground text-xs">Order total</dt>
            <dd className="mt-1 font-semibold text-lg tabular-nums">{money(total)}</dd>
          </div>
          <div className="bg-card px-3 py-2.5">
            <dt className="text-muted-foreground text-xs">Received value</dt>
            <dd className="mt-1 font-semibold text-lg tabular-nums">{money(receivedValue)}</dd>
          </div>
          <div className="bg-card px-3 py-2.5">
            <dt className="text-muted-foreground text-xs">Still to receive</dt>
            <dd className="mt-1 font-semibold text-lg tabular-nums">{money(remainingValue)}</dd>
          </div>
          <div className="bg-card px-3 py-2.5">
            <dt className="text-muted-foreground text-xs">Expected delivery</dt>
            <dd className="mt-1 font-semibold">{order.expected_on ? dateLabel(order.expected_on) : 'Not set'}</dd>
          </div>
        </dl>
        <p className="text-muted-foreground text-sm" aria-live="polite">
          {orderProgressLabel(order, lines) ?? 'No received quantities recorded yet.'}
          {order.purchase_request_id ? ` · From purchase request #${order.purchase_request_id}` : ''}
        </p>
      </section>

      <section aria-labelledby="order-lines-heading" className="flex min-w-0 flex-col gap-3">
        <div>
          <h2 id="order-lines-heading" className="font-heading font-semibold text-base">
            Items
          </h2>
          <p className="text-muted-foreground text-sm">Ordered, received and what is still outstanding.</p>
        </div>

        {lines.length === 0 ? (
          <p className="rounded-xl border px-4 py-3 text-muted-foreground text-sm">No lines were returned for this order.</p>
        ) : (
          <>
            <ul className="flex flex-col divide-y rounded-xl border bg-card md:hidden">
              {lines.map((line) => {
                const unit = purchaseOrderLineUnit(line)
                const remaining = remainingQuantity(line)
                return (
                  <li key={line.id} className="flex min-w-0 flex-col gap-3 p-4">
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <h3 className="break-words font-medium">{purchaseOrderLineName(line)}</h3>
                        <p className="text-muted-foreground text-sm">
                          {money(line.unit_cost)} each
                        </p>
                      </div>
                      <span className="shrink-0 font-semibold tabular-nums">{money(purchaseOrderLineAmount(line))}</span>
                    </div>
                    <dl className="grid grid-cols-3 gap-3 text-sm">
                      <div>
                        <dt className="text-muted-foreground">Ordered</dt>
                        <dd className="font-medium tabular-nums">{quantityWithUnit(line.quantity_ordered, unit)}</dd>
                      </div>
                      <div>
                        <dt className="text-muted-foreground">Received</dt>
                        <dd className="tabular-nums">{quantityWithUnit(line.quantity_received, unit)}</dd>
                      </div>
                      <div>
                        <dt className="text-muted-foreground">Remaining</dt>
                        <dd className={remaining > 0 ? 'font-semibold tabular-nums' : 'text-muted-foreground tabular-nums'}>
                          {quantityWithUnit(remaining, unit)}
                        </dd>
                      </div>
                    </dl>
                  </li>
                )
              })}
            </ul>

            <div className="hidden min-w-0 overflow-x-auto md:block">
              <Table variant="card" className="min-w-[48rem]">
                <TableHeader>
                  <TableRow>
                    <TableHead>Item</TableHead>
                    <TableHead className="text-right">Ordered</TableHead>
                    <TableHead className="text-right">Received</TableHead>
                    <TableHead className="text-right">Remaining</TableHead>
                    <TableHead className="text-right">Unit cost</TableHead>
                    <TableHead className="text-right">Amount</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {lines.map((line) => {
                    const unit = purchaseOrderLineUnit(line)
                    const remaining = remainingQuantity(line)
                    return (
                      <TableRow key={line.id}>
                        <TableCell className="max-w-64 whitespace-normal break-words font-medium">
                          {purchaseOrderLineName(line)}
                        </TableCell>
                        <TableCell className="text-right tabular-nums">{quantityWithUnit(line.quantity_ordered, unit)}</TableCell>
                        <TableCell className="text-right tabular-nums">{quantityWithUnit(line.quantity_received, unit)}</TableCell>
                        <TableCell className="text-right tabular-nums">
                          {remaining > 0 ? (
                            <strong>{quantityWithUnit(remaining, unit)}</strong>
                          ) : (
                            <span className="text-muted-foreground">0</span>
                          )}
                        </TableCell>
                        <TableCell className="text-right tabular-nums">{money(line.unit_cost)}</TableCell>
                        <TableCell className="text-right font-medium tabular-nums">{money(purchaseOrderLineAmount(line))}</TableCell>
                      </TableRow>
                    )
                  })}
                </TableBody>
              </Table>
            </div>
          </>
        )}
      </section>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section aria-labelledby="receipts-heading" className="flex min-w-0 flex-col gap-3">
          <div>
            <h2 id="receipts-heading" className="font-heading font-semibold text-base">
              Receipts
            </h2>
            <p className="text-muted-foreground text-sm">Every delivery recorded against this order.</p>
          </div>
          {receipts.length === 0 ? (
            <p className="rounded-xl border px-4 py-3 text-muted-foreground text-sm">
              Nothing received yet. Recording a delivery raises stock and creates the expense and supplier payable.
            </p>
          ) : (
            <ul className="flex flex-col gap-3">
              {receipts.map((receipt) => (
                <li key={receipt.id} className="flex flex-col gap-2 rounded-xl border bg-card p-4">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                      <h3 className="font-medium">{receiptNumber(receipt)}</h3>
                      <p className="text-muted-foreground text-sm">
                        {dateLabel(receipt.received_on)}
                        {receipt.received_by_name ? ` · Received by ${receipt.received_by_name}` : ''}
                      </p>
                    </div>
                    <span className="font-semibold tabular-nums">{money(receipt.total)}</span>
                  </div>
                  {receipt.note && <p className="break-words text-sm">{receipt.note}</p>}
                  {receipt.lines && receipt.lines.length > 0 && (
                    <ul className="flex flex-col divide-y rounded-lg border">
                      {receipt.lines.map((line, index) => (
                        <li
                          key={line.id ?? `${receipt.id}-${line.purchase_order_line_id ?? index}`}
                          className="flex flex-wrap items-baseline justify-between gap-2 px-3 py-2 text-sm"
                        >
                          <span className="min-w-0 break-words">{receiptLineName(line, lines)}</span>
                          <span className="tabular-nums">
                            {line.quantity} received
                            {line.amount ? ` · ${money(line.amount)}` : ''}
                          </span>
                        </li>
                      ))}
                    </ul>
                  )}
                </li>
              ))}
            </ul>
          )}
        </section>

        <section aria-labelledby="order-timeline-heading" className="flex min-w-0 flex-col gap-3">
          <div>
            <h2 id="order-timeline-heading" className="font-heading font-semibold text-base">
              Status history
            </h2>
            <p className="text-muted-foreground text-sm">The requester and approver get a notice on each change.</p>
          </div>
          {events.length === 0 ? (
            <p className="rounded-xl border px-4 py-3 text-muted-foreground text-sm">No status history was returned.</p>
          ) : (
            <ol className="flex flex-col gap-4 rounded-xl border bg-card p-4">
              {events.map((event, index) => {
                const actor = timelineActor(event)
                const at = timelineAt(event)
                return (
                  <li key={event.id ?? `${event.type ?? event.status ?? 'event'}-${index}`} className="flex gap-3">
                    <span aria-hidden className="mt-1.5 size-2 shrink-0 rounded-full bg-primary" />
                    <div className="min-w-0">
                      <p className="break-words font-medium text-sm">{timelineLabel(event)}</p>
                      {event.note && <p className="break-words text-muted-foreground text-sm">{event.note}</p>}
                      {(actor || at) && (
                        <p className="break-words text-muted-foreground text-xs">
                          {[actor, at ? dateTimeLabel(at) : null].filter(Boolean).join(' · ')}
                        </p>
                      )}
                    </div>
                  </li>
                )
              })}
            </ol>
          )}
        </section>
      </div>

      {receiveOpen && canReceiveNow && (
        <ReceiveDialog
          gymId={gymId}
          order={order}
          lines={lines.filter((line) => remainingQuantity(line) > 0)}
          onClose={() => setReceiveOpen(false)}
          onSaved={() => {
            setReceiveOpen(false)
            refresh()
          }}
        />
      )}

      {reasonMode && canManage && (
        <ReasonDialog
          gymId={gymId}
          order={order}
          mode={reasonMode}
          onClose={() => setReasonMode(null)}
          onSaved={() => {
            setReasonMode(null)
            refresh()
          }}
        />
      )}
    </div>
  )
}

function ReceiveDialog({
  gymId,
  order,
  lines,
  onClose,
  onSaved,
}: {
  gymId: number
  order: PurchaseOrder
  lines: PurchaseOrderLine[]
  onClose: () => void
  onSaved: () => void
}) {
  const [quantities, setQuantities] = useState<Record<string, string>>(() =>
    Object.fromEntries(lines.map((line) => [line.id, String(remainingQuantity(line))])),
  )
  const [receivedOn, setReceivedOn] = useState(todayIso)
  const [note, setNote] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)

  const lineViews = lines.map((line) => {
    const remaining = remainingQuantity(line)
    const value = quantities[line.id] ?? '0'
    const quantity = Number(value)
    const valid =
      value.trim() !== '' &&
      Number.isInteger(quantity) &&
      quantity >= 0 &&
      quantity <= remaining
    return { line, value, quantity, valid, remaining }
  })
  const someReceived = lineViews.some((view) => view.valid && view.quantity > 0)
  const blocked = busy || !receivedOn || !someReceived || lineViews.some((view) => !view.valid)
  const lineErrors = nestedErrors(errors, 'lines')

  function setQuantity(lineId: number, value: string) {
    setQuantities((current) => ({ ...current, [lineId]: value }))
  }

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (blocked) return
    setBusy(true)
    setErrors({})
    try {
      const result = await api<{ order: PurchaseOrder; receipt?: GoodsReceipt }>(`/gyms/${gymId}/purchase-orders/${order.id}/receipts`, {
        body: {
          received_on: receivedOn,
          note: note.trim() || null,
          lines: lineViews
            .filter((view) => view.quantity > 0)
            .map((view) => ({ purchase_order_line_id: view.line.id, quantity: view.quantity })),
        },
      })
      const number = result.receipt ? receiptNumber(result.receipt) : null
      toastManager.add({
        title: number ? `Goods received. ${number}.` : 'Goods received.',
        type: 'success',
      })
      onSaved()
    } catch (failure) {
      setErrors(formErrors(failure, ['received_on', 'note', 'lines'], 'The delivery could not be recorded.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup className="sm:max-w-2xl">
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>Receive goods</DialogTitle>
            <DialogDescription>
              Enter what actually arrived. A part delivery leaves the order open; a full delivery completes it.
            </DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField
                name="received_on"
                label="Received on"
                type="date"
                value={receivedOn}
                onChange={(event) => setReceivedOn(event.target.value)}
                required
              />
              <TextField
                name="note"
                label="Note (optional)"
                value={note}
                onChange={(event) => setNote(event.target.value)}
                maxLength={255}
                placeholder="Delivery note or reference"
              />
            </div>

            <fieldset className="flex flex-col gap-3">
              <legend className="font-medium text-sm">Quantities received</legend>
              <ul className="flex flex-col gap-3">
                {lineViews.map((view, index) => {
                  const unit = purchaseOrderLineUnit(view.line)
                  return (
                    <li key={view.line.id} className="flex flex-col gap-2 rounded-xl border p-3">
                      <div className="flex flex-wrap items-start justify-between gap-2">
                        <div className="min-w-0">
                          <p className="break-words font-medium">{purchaseOrderLineName(view.line)}</p>
                          <p className="text-muted-foreground text-xs">
                            {quantityWithUnit(view.remaining, unit)} still to receive · {money(view.line.unit_cost)} each
                          </p>
                        </div>
                        <span className="text-muted-foreground text-xs">
                          Ordered {quantityWithUnit(view.line.quantity_ordered, unit)}
                        </span>
                      </div>
                      <TextField
                        name={`lines.${index}.quantity`}
                        label="Quantity received"
                        type="number"
                        inputMode="numeric"
                        min={0}
                        max={view.remaining}
                        step={1}
                        value={view.value}
                        onChange={(event) => setQuantity(view.line.id, event.target.value)}
                        disabled={view.remaining === 0}
                        required
                        description={view.remaining === 0 ? 'This line is complete.' : `Up to ${quantityWithUnit(view.remaining, unit)}.`}
                      />
                      {!view.valid && view.remaining > 0 && (
                        <p className="text-destructive text-sm">Enter a whole number from 0 to {view.remaining}.</p>
                      )}
                    </li>
                  )
                })}
              </ul>
              {lineErrors.length > 0 && (
                <Alert variant="error">
                  <AlertTitle>Check the received quantities</AlertTitle>
                  <AlertDescription>{lineErrors.join(' ')}</AlertDescription>
                </Alert>
              )}
              {!someReceived && (
                <p className="text-sm">Enter at least one quantity above zero, or cancel.</p>
              )}
            </fieldset>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" loading={busy} disabled={blocked}>
              Record delivery
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}

function ReasonDialog({
  gymId,
  order,
  mode,
  onClose,
  onSaved,
}: {
  gymId: number
  order: PurchaseOrder
  mode: 'cancel' | 'close'
  onClose: () => void
  onSaved: () => void
}) {
  const [reason, setReason] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [busy, setBusy] = useState(false)
  const closing = mode === 'close'

  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (busy || reason.trim() === '') {
      setErrors({ reason: ['Give a reason.'] })
      return
    }
    setBusy(true)
    setErrors({})
    try {
      await api(`/gyms/${gymId}/purchase-orders/${order.id}/${closing ? 'close' : 'cancel'}`, {
        body: { reason: reason.trim() },
      })
      toastManager.add({ title: closing ? 'Order closed with remainder.' : 'Purchase order cancelled.', type: 'success' })
      onSaved()
    } catch (failure) {
      setErrors(formErrors(failure, ['reason'], closing ? 'The order could not be closed.' : 'The order could not be cancelled.'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onOpenChange={(open) => !open && !busy && onClose()}>
      <DialogPopup>
        <Form errors={errors} onSubmit={save} className="flex min-h-0 flex-col">
          <DialogHeader>
            <DialogTitle>{closing ? 'Close with remainder?' : 'Cancel this order?'}</DialogTitle>
            <DialogDescription>
              {closing
                ? 'The order becomes completed with the goods already received. Remaining quantities cannot be received later.'
                : 'Only a pending order with nothing received can be cancelled. The order stays on record with the reason.'}
            </DialogDescription>
          </DialogHeader>
          <DialogPanel className="flex flex-col gap-4">
            <Field name="reason" className="w-full">
              <FieldLabel>Reason</FieldLabel>
              <Textarea
                name="reason"
                rows={3}
                maxLength={2000}
                value={reason}
                onChange={(event) => setReason(event.target.value)}
                required
                autoFocus
                placeholder={closing ? 'For example: the supplier discontinued the last item.' : 'For example: ordered from the wrong supplier.'}
              />
              <FieldError />
            </Field>
          </DialogPanel>
          <DialogFooter>
            <Button type="button" variant="outline" disabled={busy} onClick={onClose}>
              Back
            </Button>
            <Button type="submit" variant="destructive" loading={busy} disabled={reason.trim() === ''}>
              {closing ? 'Close order' : 'Cancel order'}
            </Button>
          </DialogFooter>
        </Form>
      </DialogPopup>
    </Dialog>
  )
}
