[← Previous: Packet 6A HR extras](./phase-2.md) | [Next: Packet 6C CRM →](./phase-4.md)

**Status:** ✅ Done

# Packet 6B: Inventory — stock items, movements and low-stock alerts

**Goal:** The gym keeps a list of equipment, protective gear, supplies and merchandise with quantity and storage place. Every change is a recorded movement with a person and a time. Minimum levels trigger a notice. Covers R-72, R-73 and R-74.

## Requirements and done-when

| # | Requirement | Done when |
|---|---|---|
| R-72 | Stock items | Permitted staff add items with category, quantity and storage place |
| R-73 | Movements and history | Each movement type changes quantity correctly; full history is viewable; quantity never changes without a movement |
| R-74 | Minimum levels and alerts | A minimum can be set per item; dropping to it notifies stock staff and the owner |

## Dependencies

- Packet 6.0: `inventory.items.view/manage`, `inventory.movements.manage`.
- Roadmap Phase 3: room/location naming conventions only; stock has no room foreign key.

## Decisions

- One storage place per item, stored as text. Per-location quantities are out of scope.
- `stock_items.quantity` is a cache of the movement sum. It is not fillable and cannot be written directly.
- All quantity changes go through `StockLedger::move()`. The ledger locks the item row, writes the signed movement with `balance_after`, updates the cache, and checks the minimum crossing in the same transaction.
- Movement types and signs: `opening` +, `receipt` +, `issue` −, `return` +, `adjustment` ±, `damaged` −, `lost` −, `sale` −.
- `return` must point at an `issue` and may not return more than was issued. Returns are checked per issue, not globally.
- Stock can never go negative. The movement is refused.
- Low stock notifies only on a crossing: above the minimum before, at or below after. It does not repeat while stock stays low. The flag clears when quantity rises above the minimum.
- Merchandise items may carry a `sale_price`; the sale flow arrives in 6D and calls `StockLedger::move()` with type `sale`.
- Equipment is counted, not depreciated. No valuation or cost-of-goods accounting in this packet.

## Schema

Migration `backend/database/migrations/2026_11_12_100000_create_stock_tables.php`.

**`stock_items`**

| Column | Type | Notes |
|---|---|---|
| `gym_id` | FK cascade | |
| `name` | string(120) | |
| `sku` | string(60), nullable | unique per gym |
| `category` | string(30) | `equipment`, `protective_gear`, `supplies`, `merchandise` |
| `unit` | string(20) | piece, pair, box, litre, kg |
| `location` | string(120) | storage place |
| `quantity` | integer, default 0 | cache; not fillable |
| `min_level` | unsigned int, nullable | |
| `reorder_qty` | unsigned int, nullable | |
| `unit_cost` | decimal(12,2), nullable | |
| `sale_price` | decimal(12,2), nullable | merchandise only |
| `status` | string(20), default `active` | `active`, `archived` |
| `low_notified_at` | dateTime, nullable | crossing flag |

Unique `(gym_id, sku)`. Index `(gym_id, category)`, `(gym_id, status)`.

**`stock_movements`** (append-only)

| Column | Type | Notes |
|---|---|---|
| `gym_id`, `stock_item_id` | FK cascade | |
| `type` | string(20) | one of the eight |
| `quantity` | integer, signed | |
| `balance_after` | integer | |
| `note` | string(255), nullable | required for adjustment, damaged, lost |
| `actor_id` | FK users, nullOnDelete | |
| `occurred_at` | dateTime | |
| `issued_to_user_id` | FK users, nullable | issue |
| `returns_movement_id` | FK stock_movements, nullable | return |
| `goods_receipt_line_id` | unsigned big int, nullable, indexed | filled in 6E; no cross-packet foreign key |
| `payment_id` | unsigned big int, nullable, indexed | filled in 6D; no cross-packet foreign key |

Indexes `(stock_item_id, id)`, `(gym_id, occurred_at)`.

## Backend tasks

- [x] Models `StockItem`, `StockMovement` with `BelongsToGym`. `StockItem` casts money and integers, and hides `quantity` from mass assignment.
- [x] `App\Support\StockLedger`:
  - `open(Gym $gym, array $item, int $quantity, User $actor): StockItem` — creates the item and the `opening` movement in one transaction.
  - `move(StockItem $item, string $type, int $quantity, User $actor, array $extra = []): StockMovement` — validates the sign and rules, locks the row, refuses negative balances, writes the movement, updates the cache, then calls `checkLowStock()`.
  - `checkLowStock(StockItem $item): void` — crossing rule, `StockLow` notification to `PeopleWithPermission('inventory.items.manage')` (owners included), sets or clears `low_notified_at`.
  - `assertQuantityMatchesMovements(StockItem $item): void` — used by tests and a repair command.
- [x] `App\Console\Commands\CheckStockCounts` (`app:check-stock-counts`) — reports items whose cache differs from the movement sum. No automatic correction; a mismatch is a bug.
- [x] `StockItem` model guard: `updating` throws when `quantity` is dirty unless `StockLedger` set an internal flag. Same for `creating` with a non-zero quantity — `open()` handles the opening movement.
- [x] Controllers `StockItemController`, `StockMovementController`.
- [x] Routes:

| Method and path | Controller | Permission |
|---|---|---|
| `GET stock-items` | StockItemController@index | `inventory.items.view` |
| `GET stock-items/{item}` | @show | `inventory.items.view` |
| `POST stock-items` | @store | `inventory.items.manage` |
| `PUT stock-items/{item}` | @update | `inventory.items.manage` |
| `POST stock-items/{item}/archive` | @archive | `inventory.items.manage` |
| `GET stock-items/{item}/movements` | StockMovementController@index | `inventory.items.view` |
| `POST stock-items/{item}/movements` | @store | `inventory.movements.manage` |

- [x] `StockLow` notification with item, quantity, minimum and location.
- [x] Add `stock_items` and `stock_movements` to `DatabaseSeeder::resetData()` and seed a handful of realistic items for approved demo gyms.

## Frontend tasks

- [x] `frontend/src/pages/gym/GymStock.tsx` at route `stock`:
  - Summary cards: total items, low stock, out of stock.
  - Table: item, category, location, quantity, minimum, status, low badge.
  - Filters: category, low only, archived.
  - Item dialog (add/edit) and archive confirmation.
  - Movement dialog: type, quantity, note, issued to (for `issue`), returns link (for `return`).
  - History sheet or dialog for one item.
- [x] Nav entry "Stock" with `[inventory.items.view]`.
- [x] Types in `frontend/src/lib/inventory.ts`.

## Tests

| File | Must prove |
|---|---|
| `StockTest` | Opening movement sets quantity; issue lowers; return cannot exceed the issue; adjustment needs a reason; damaged and lost lower; sale lowers; negative refused; direct `quantity` update throws; `quantity === SUM(movements)` after every path; archived items reject movements |
| `LowStockTest` | Notice on crossing only; no repeat while low; flag clears above minimum; changing minimum runs the check; owner receives it |
| `StockTenancyTest` | Another gym's item, movement and id return 404 |

## Checks

`php artisan test`, `php artisan migrate:fresh --seed` on MySQL, `pnpm lint`, `pnpm build`, browser pass at phone width and with a read-only role.

## Done when

- [x] An item can be added with category, quantity and place; the opening quantity is a movement.
- [x] Every movement type changes the quantity in the documented direction.
- [x] A direct quantity write is refused by the model.
- [x] History shows every movement with actor and time.
- [x] A crossing below the minimum sends one notice to stock staff and the owner.
- [x] All tests, lint and build pass.

## Status notes

- 2026-11-05: Built the schema, enums and models. Files: migration `2026_11_12_100000_create_stock_tables.php`, `app/Enums/StockCategory.php`, `StockItemStatus.php`, `StockMovementType.php`, `app/Models/StockItem.php`, `StockMovement.php`, and the two `Gym` relations.
- 2026-11-05: `StockLedger` owns every quantity change. `open()` writes the opening movement; `move()` locks the item, checks archived state, per-issue returns, notes and negative balances, writes the signed movement with `balance_after`, updates the cache through the model guard bypass, and checks low stock in the same transaction. `StockItem` refuses direct quantity writes.
- 2026-11-05: Low stock is crossing-only through `low_notified_at`. `StockLow` goes to `peopleWithPermission('inventory.items.manage')`, so owners are included and Front Desk (which can move stock but not manage items) is not. The flag clears above the minimum; changing the minimum runs the same check.
- 2026-11-05: Routes and screens. `StockItemController`, `StockMovementController`, `PresentsStock`, the eight packet routes, and one supporting `GET stock-items/recipients` route for the movement dialog (same precedent as 6A's employee list). New `GymStock.tsx`, `lib/inventory.ts`, route `stock`, nav entry "Stock".
- 2026-11-05: `app:check-stock-counts` is a closure command in `routes/console.php`, matching the repo's existing command convention (there is no `app/Console` directory). It reports mismatches and never corrects them.
- 2026-11-05: Seeder adds both tables to `resetData()` and nine realistic items per approved gym with an opening count and six later movements. Seeded low notices come from the water bottle and the mouth-guard issue.
- 2026-11-05: Tests added: `StockTest` (10), `LowStockTest` (5), `StockTenancyTest` (1), plus `Concerns/StockSetup`. Small additions to `RouteAccessTest` and `DatabaseSeederTest`.
- 2026-11-05: `php artisan test`: 339 passed, 23,480 assertions. Packet files: `StockTest` 10 passed/168 assertions, `LowStockTest` 5/41, `StockTenancyTest` 1/15.
- 2026-11-05: `pnpm lint` exit 0 (existing warning pattern; four new `set-state-in-effect` warnings follow the repo's loading pattern). `pnpm build` passed; Vite config and chunk-size warnings only.
- 2026-11-05: Isolated MySQL gate passed on a throwaway database: `migrate:fresh --seed`, `app:check-stock-counts` exit 0, `app:sync-permissions` exit 0. Five approved gyms each had 9 items and 15 movements; cache, movement sum and last balance matched. The dev database was not wiped; only the new migration was applied.
- 2026-11-05: Browser pass on the running app. Owner added "Boxing gloves 14 oz" (12 pairs, minimum 5), issued 2 to Dindo, returned 2, and the history showed actor, time, note and outstanding count. A second item at 3 with minimum 5 produced exactly one "Low stock" notice in the bell; archive removed it from the active list and summary. Front Desk could move stock but saw no add, edit or archive actions; Operations Manager was read-only; Coach had no Stock nav and the direct URL showed the not-available state. At 390 px the page used cards, dialogs and the sheet without horizontal overflow; no console errors.
- 2026-11-05: The dev database's Front Desk role was created before the 6.0 inventory template, so it was granted `inventory.items.view` and `inventory.movements.manage` for the role check. Fresh seeds already include them. The browser pass left two demo items (Boxing gloves, archived Mouth guards) and their movements in the dev database.
- 2026-11-05: Assumptions: the ledger takes a positive count for fixed-direction types and a signed change only for `adjustment`; HTTP accepts receipt, issue, return, adjustment, damaged and lost, while `sale` stays available to code for 6D; `issued_to_user_id` is optional; an item opened at or below its minimum notifies once.
- 2026-11-06: Independent browser verification against R-72 to R-74. Created disposable `QA *` items and exercised add/edit/archive, all six manual movement types, per-issue return caps, negative refusal, required notes, filtered and empty states, paginated history, low-stock crossings, minimum changes, and role gates. Active data ended at 6 items with summary 6 items / 2 low / 1 out; `app:check-stock-counts` stayed clean after every path. Owner, Front Desk, Operations Manager and Coach gates matched the packet; a second gym owner received 404 for Iron Will's list, item and movements.
- 2026-11-06: Findings, none of which blocks the packet. The demo seed gives no non-owner role `inventory.items.manage`, so out of the box only the owner receives low notices; the manager path was verified after a temporary grant and the grant was then removed. An archived `PUT` returns the correct 409 but the dev error body includes a full stack trace because `APP_DEBUG` is on. One older Mouth guards notice still says "3 piece" because it predates the unit-plural fix; new notices read correctly. `sale` is not exposed in the UI by design (6D), and the model-level direct-quantity guard is covered by tests rather than a browser path. The disposable QA rows and their movements were left in the dev database for inspection.

---

[← Previous: Packet 6A HR extras](./phase-2.md) | [Next: Packet 6C CRM →](./phase-4.md)
