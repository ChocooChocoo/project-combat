[← Previous: Packet 6D Finance core](./phase-5.md) | [Next: Packet 6F Payroll →](./phase-7.md)

**Status:** ✅ Done

# Packet 6E: Procurement — restock, requests, orders, receiving and payables

**Goal:** The system lists what to restock from minimum levels and upcoming activities. Staff raise requests that a different permitted person approves. Approved requests become purchase orders to recorded suppliers. Receiving part or all of an order raises stock, records the expense and builds the payable. Every purchase shows a status and notifies the people involved. Covers R-75, R-76, R-77, R-78, R-79 and R-66.

## Requirements and done-when

| # | Requirement | Done when |
|---|---|---|
| R-75 | Work out what needs restocking | The list shows every item at or below its minimum; staff can add needs for upcoming activities |
| R-76 | Purchase requests with approval | A request has items and quantities; only a permitted approver who is not the requester can approve |
| R-77 | Suppliers and purchase orders | Suppliers can be added and edited; an approved request becomes one purchase order to one supplier |
| R-78 | Receiving updates stock | A delivery raises stock; a part delivery leaves the order open; the received amount is owed and becomes an expense |
| R-79 | Purchase status tracking | Every purchase is pending, partly received, completed or cancelled; each change notifies the requester and approver |
| R-66 | Money owed to suppliers | Each supplier's outstanding amount is visible; a supplier payment reduces it and appears as cash out |

## Dependencies

- Packet 6B: `stock_items`, `StockLedger`, movement types `receipt`.
- Packet 6D: `expenses`, `Finance\Expenses::post()`, expense categories.
- Packet 6.0: `procurement.*`, `finance.payables.*`, `inventory.items.view`.
- Phase 5: `training_sessions` for activity-linked needs.

## Decisions

- `supply_needs` is the only link from activities to purchasing. Staff add a need by hand and tie it to a training session or a date. There is no automatic per-service usage rule.
- Restock shortfall per active item: `max(0, min_level + open_needs − quantity − on_order)`. An item appears when it is at or below its minimum or the shortfall is above zero. Suggested quantity is `max(shortfall, reorder_qty ?? 0)`.
- Open needs are those with `needed_by` within 14 days, or with no date.
- A purchase request is approved or rejected by a holder of `procurement.requests.approve` who is not the requester. This applies to the owner too.
- If the owner is the requester and no other approver exists, the owner may create a purchase order directly. It is marked `is_direct = true` and shows "Owner direct purchase" in the history. This is the only bypass, and it requires the owner role.
- One purchase order per approved request. Quantities may go down, never above the request. Unit cost is entered on the order.
- Purchase order statuses: `pending`, `partly_received`, `completed`, `cancelled`. Cancel only from `pending` with nothing received. "Close with remainder" moves `partly_received` to `completed` with `closed_short = true` and a note.
- Receiving is one transaction: lock the order, validate quantities, write the receipt and lines, add one `receipt` stock movement per line, update received quantities, recompute status, post the expense.
- The payable is derived: received totals minus supplier payments. No payable table.
- A supplier payment may not exceed the outstanding amount. It is append-only and may be corrected by a negative row.
- Numbers are `PR-{gym}-{000000-id}`, `PO-...`, `GR-...`, following the receipt pattern in `MembershipLedger`. Gaps are acceptable.
- Status notices go to the requester, the approver and the order creator, minus whoever caused the change.

## Schema

Migration `backend/database/migrations/2026_11_15_100000_create_procurement_tables.php`.

**`supply_needs`**

`gym_id`, `stock_item_id` FK cascade, `training_session_id` FK nullable nullOnDelete, `needed_by` date nullable, `quantity` unsigned int, `note` string(255) nullable, `status` (`open`, `requested`, `closed`), `created_by` FK users, timestamps.

**`purchase_requests`**

`gym_id`, `number` string(30) unique, `status` (`pending`, `approved`, `rejected`, `cancelled`), `requested_by`, `decided_by` nullable, `decided_at`, `decision_reason` text nullable, `needed_by` date nullable, `note` text nullable, timestamps.

**`purchase_request_lines`**

`purchase_request_id` FK cascade, `stock_item_id` FK, `quantity` unsigned int, `est_unit_cost` decimal(12,2) nullable, `supply_need_id` FK nullable, timestamps.

**`suppliers`**

`gym_id`, `name` string(120), `contact_person` string(120) nullable, `phone` string(40) nullable, `email` string(150) nullable, `address` string(255) nullable, `payment_terms` string(120) nullable, `notes` text nullable, `status` (`active`, `archived`), timestamps.

**`purchase_orders`**

`gym_id`, `number` string(30) unique, `supplier_id` FK, `purchase_request_id` FK nullable unique, `is_direct` boolean default false, `status` (`pending`, `partly_received`, `completed`, `cancelled`), `ordered_by`, `expected_on` date nullable, `total` decimal(12,2), `closed_short` boolean default false, `cancel_reason` text nullable, timestamps.

**`purchase_order_lines`**

`purchase_order_id` FK cascade, `stock_item_id` FK, `quantity_ordered` unsigned int, `quantity_received` unsigned int default 0, `unit_cost` decimal(12,2), timestamps.

**`goods_receipts`**

`gym_id`, `number` string(30) unique, `purchase_order_id` FK, `received_by`, `received_on` date, `note` string(255) nullable, `total` decimal(12,2), timestamps.

**`goods_receipt_lines`**

`goods_receipt_id` FK cascade, `purchase_order_line_id` FK, `quantity` unsigned int, `unit_cost` decimal(12,2), `amount` decimal(12,2), timestamps.

**`supplier_payments`**

`gym_id`, `supplier_id` FK, `purchase_order_id` FK nullable, `amount` decimal(12,2) signed, `method` string(20), `paid_on` date, `reference` string(80) nullable, `note` string(255) nullable, `corrects_id` nullable, `recorded_by`, timestamps. Index `(gym_id, supplier_id, paid_on)`.

`stock_movements.goods_receipt_line_id` already exists as a nullable indexed column from 6B; this packet fills it. No foreign key is added across packets.

## Backend tasks

- [x] Models `SupplyNeed`, `PurchaseRequest`, `PurchaseRequestLine`, `Supplier`, `PurchaseOrder`, `PurchaseOrderLine`, `GoodsReceipt`, `GoodsReceiptLine`, `SupplierPayment`, all with `BelongsToGym` except the line tables.
- [x] `App\Support\Procurement\Restock` — computes the list per active item with open needs and on-order sums. One query per aggregate, then a PHP merge.
- [x] `App\Support\Procurement\Ordering`:
  - `fromRequest(Gym, PurchaseRequest, Supplier, array $lines, User): PurchaseOrder` — quantities at or below the request, unit cost required, number assigned, request `approved`.
  - `direct(Gym, Supplier, array $lines, User $owner): PurchaseOrder` — owner only, `is_direct = true`.
  - `cancel(PurchaseOrder, string $reason, User)` — only pending with nothing received.
  - `closeShort(PurchaseOrder, string $reason, User)` — only partly received.
  - `refreshStatus(PurchaseOrder)` — pending, partly, completed.
- [x] `App\Support\Procurement\Receiving::receive(Gym, PurchaseOrder, array $lines, User, Carbon $date, ?string $note)`:
  - Lock the order.
  - Refuse over-receipt per line.
  - Create receipt and lines with cost snapshots and totals.
  - Call `StockLedger::move(..., 'receipt', ...)` per line with `goods_receipt_line_id`.
  - Increase `quantity_received`, recompute status.
  - Call `Finance\Expenses::post(..., 'purchases', total, date, 'goods_receipt', receipt id, actor)`.
  - One transaction.
- [x] `App\Support\Procurement\Payables` — per supplier received total minus payments; `pay(Supplier, amount, data, User)` refuses overpayment and records cash out. Supplier payments are listed in the finance expenses view as their own rows but are not added to expense totals.
- [x] Controllers `RestockController`, `SupplyNeedController`, `PurchaseRequestController`, `SupplierController`, `PurchaseOrderController`, `ReceivingController`, `PayableController`.
- [x] Routes:

| Method and path | Controller | Permission |
|---|---|---|
| `GET restock` | RestockController@index | `procurement.requests.view` or `inventory.items.view` |
| `GET supply-needs` | SupplyNeedController@index | `procurement.requests.view` |
| `POST supply-needs` | @store | `procurement.requests.manage` |
| `DELETE supply-needs/{need}` | @destroy | `procurement.requests.manage` |
| `GET purchase-requests` | PurchaseRequestController@index | `procurement.requests.view` |
| `POST purchase-requests` | @store | `procurement.requests.manage` |
| `POST purchase-requests/{request}/decision` | @decide | `procurement.requests.approve` |
| `POST purchase-requests/{request}/cancel` | @cancel | `procurement.requests.manage` |
| `GET suppliers` | SupplierController@index | `procurement.suppliers.view` |
| `POST suppliers` | @store | `procurement.suppliers.manage` |
| `PUT suppliers/{supplier}` | @update | `procurement.suppliers.manage` |
| `GET purchase-orders` | PurchaseOrderController@index | `procurement.orders.view` |
| `GET purchase-orders/{order}` | @show | `procurement.orders.view` |
| `POST purchase-orders` | @store (from approved request) | `procurement.orders.manage` |
| `POST purchase-orders/direct` | @direct (owner only in controller) | `procurement.orders.manage` |
| `POST purchase-orders/{order}/cancel` | @cancel | `procurement.orders.manage` |
| `POST purchase-orders/{order}/close` | @closeShort | `procurement.orders.manage` |
| `POST purchase-orders/{order}/receipts` | ReceivingController@store | `procurement.receipts.manage` |
| `GET finance/payables` | PayableController@index | `finance.payables.view` |
| `GET finance/payables/{supplier}` | @show | `finance.payables.view` |
| `POST finance/supplier-payments` | @pay | `finance.payables.manage` |

- [x] Notifications: `PurchaseRequestDecided` (requester), `PurchaseStatusChanged` (requester, approver, creator, minus actor), `SupplierPaymentRecorded` optional.
- [x] Add the new tables to `DatabaseSeeder::resetData()` and seed two suppliers, one request, one order and one partial receipt for demo gyms.

## Frontend tasks

- [x] `frontend/src/pages/gym/GymPurchasing.tsx` at route `purchasing`:
  - Tabs: **Restock**, **Requests**, **Orders**, **Suppliers**.
  - Restock: table with on hand, minimum, on order, needed, shortfall, suggested quantity; "Add need" and "Create request" actions.
  - Requests: table with status and requester; detail with approve, reject with reason, cancel.
  - Orders: table with number, supplier, status, total, received progress; direct purchase for owners.
  - Suppliers: list, add/edit, archive.
- [x] `frontend/src/pages/gym/PurchaseOrder.tsx` at route `purchasing/orders/:orderId`:
  - Header with status badge, supplier, totals.
  - Lines with ordered, received, remaining, unit cost.
  - Receive dialog with per-line quantities; cancel and close-short dialogs.
  - Receipt history and status timeline.
- [x] Payables card in `GymFinance.tsx` (6D page): supplier list, outstanding, pay dialog.
- [x] Nav entry "Purchasing" with `[procurement.requests.view, procurement.orders.view, procurement.suppliers.view]`.
- [x] Types in `frontend/src/lib/procurement.ts`.

## Tests

| File | Must prove |
|---|---|
| `RestockTest` | At or below minimum appears; above does not; open needs add; on-order subtracts; suggested quantity respects reorder quantity |
| `PurchaseRequestTest` | Create with lines; requester cannot approve, owner-requester included; reject needs a reason; cancel only pending |
| `PurchaseOrderTest` | Only an approved request; one order per request; quantities cannot exceed the request; direct purchase is owner-only and flagged; cancel rules; close-short |
| `ReceivingTest` | Partial receipt raises stock and leaves the order open; over-receipt refused; full receipt completes; expense posted once; duplicate submission idempotent; stock cache equals movements |
| `PayableTest` | Outstanding equals receipts minus payments; overpayment refused; payment lowers the payable and appears as cash out, not as a second expense; correction |
| `ProcurementTenancyTest` | Another gym's supplier, request, order, receipt and payment return 404 |

## Checks

`php artisan test`, `php artisan migrate:fresh --seed` on MySQL, `pnpm lint`, `pnpm build`, browser pass for the whole restock-to-receiving flow.

## Done when

- [x] The restock list shows items at or below minimum and items with activity needs.
- [x] A request goes to a different permitted approver and cannot be self-approved.
- [x] An approved request becomes one purchase order to one supplier.
- [x] A delivery raises stock and a part delivery leaves the order open.
- [x] Received goods create an expense and a supplier payable.
- [x] A supplier payment reduces the payable and counts as cash out.
- [x] Every status change notifies the people involved, except the person who made it.
- [x] All tests, lint and build pass.

## Status notes

- **6 November 2026 — automated build complete; browser pass pending.** Built `2026_11_15_100000_create_procurement_tables`, all procurement models, `Restock`, `Ordering`, `Receiving`, `Payables`, `Notifier`, the seven controllers, `PresentsProcurement`, `GymPurchasing.tsx`, `PurchaseOrder.tsx` and `lib/procurement.ts`. Receiving is one transaction: stock movement, receipt line, order status and one `purchases` expense with a unique source key. Tests: `RestockTest`, `PurchaseRequestTest`, `PurchaseOrderTest`, `ReceivingTest`, `PayableTest`, `ProcurementTenancyTest`, `LowStockTest` (all green). 6H integration: request, order and supplier lists now paginate; the request detail reads its raised order from the request payload instead of a separate first-page order list. Browser pass still to run.
- **6 November 2026 — browser pass done.** Owner added an `E2E Merch` need (10 units), saw the restock shortfall become 4, created request PR-1-000001 for 4 units, and could not approve it (no Approve/Reject controls on an own request). Finance Officer Mark approved it, creating a notification to the owner. Owner created supplier `E2E Supplies`, raised PO-1-000001 for 4 × ₱250, received 2 units (status Partly received, stock 8→10, expense and payable ₱500), then received the remaining 2 (Completed, stock 10, expense ₱1,000). The payable showed ₱1,000 and dropped to zero after a supplier payment; it appears as cash out and not a second expense. Status notifications for Pending, Partly received and Completed were present. A second owner request PR-1-000002 was cancelled successfully. Phone width had no overflow.
- **6 November 2026 — full-window retest and banner fix.** Re-ran procurement at 1920×1080. Owner direct purchase created PO-1-000002 and was marked in its history; the Receive form capped quantity at the ordered amount and disabled submit, so over-receipt cannot be entered. PO-1-000002 was cancelled with a reason. A second direct order PO-1-000003 was partly received (GR-1-000003, stock +1, expense ₱250), then closed with remainder with the reason recorded. That exposed a UI defect: the completed/closed-short order showed a red “Order cancelled” alert because the page keyed off `cancel_reason` rather than status. `PurchaseOrder.tsx` now shows “Order cancelled” only for cancelled orders and a “Closed with remainder” warning (including the reason) for closed-short orders. Both states rechecked; lint and build pass.

---

[← Previous: Packet 6D Finance core](./phase-5.md) | [Next: Packet 6F Payroll →](./phase-7.md)
