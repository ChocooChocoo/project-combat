[← Previous: Packet 6B Inventory](./phase-3.md) | [Next: Packet 6D Finance core →](./phase-5.md)

**Status:** ✅ Done

# Packet 6C: CRM — leads, history and conversion

**Goal:** Staff record prospective and existing customers, their inquiries, follow-ups assigned to a person, every interaction and concern, and groups for future announcements. A lead links to a membership application, manually or by email. Covers R-69, R-70 and R-71.

## Requirements and done-when

| # | Requirement | Done when |
|---|---|---|
| R-69 | Leads, customers, inquiries, follow-ups | A lead or customer can be added with contact details and inquiry; a follow-up can be scheduled and assigned |
| R-70 | Interaction and feedback history | Every interaction, preference, feedback item and concern is kept; concerns can be resolved with who resolved them |
| R-71 | Turn a lead into a member | A lead links to a membership application; customer groups can be chosen as announcement audiences later |

## Dependencies

- Packet 6.0: `customers.records.view/manage`, `customers.groups.manage`.
- Roadmap Phase 3: gym membership and `MembershipApplicationController` (R-31) for linking.
- Roadmap Phase 4: `memberships` for the member stage.

## Decisions

- One `customers` table holds both leads and customers. The stage is derived, never stored: `lead` when no membership is linked, `applied` when an application is linked but not active, `member` when the linked membership has started, `lost` when explicitly closed.
- A customer may link to a user account and a membership. Both are nullable; a walk-in has neither.
- Follow-ups belong to a customer, are assigned to the owner or an employee, and move `open → done` or `open → cancelled`. Marking done records the outcome and who did it. The due reminder is R-82 and out of scope, but `due_at`, `status`, `assigned_to` and `reminded_at` are stored now.
- Interaction kinds: `interaction`, `feedback`, `concern`, `preference`. Only `concern` can be resolved, and resolving records who and when plus a resolution text.
- Interactions are append-only apart from the concern resolution fields.
- Linking is allowed in both directions: link an existing customer to one of this gym's membership applications, or create a customer whose email matches an existing applicant.
- Email matching is case-insensitive and limited to the same gym.
- Groups are a named set of customers. Adding a customer with a linked user also makes them reachable by announcements later; adding a person with no customer row creates one linked by `user_id`.

## Schema

Migration `backend/database/migrations/2026_11_13_100000_create_customer_tables.php`.

**`customers`**

| Column | Type | Notes |
|---|---|---|
| `gym_id` | FK cascade | |
| `name` | string(120) | |
| `email` | string(150), nullable | |
| `phone` | string(40), nullable | |
| `source` | string(20) | `walk_in`, `referral`, `social`, `website`, `event`, `other` |
| `inquiry` | text, nullable | what they asked about |
| `user_id` | FK users, nullable, nullOnDelete | linked account |
| `membership_id` | FK memberships, nullable, nullOnDelete | linked application or membership |
| `linked_at` | dateTime, nullable | |
| `status` | string(20), default `open` | `open`, `lost` |
| `assigned_to` | FK users, nullable, nullOnDelete | default follow-up owner |
| `created_by` | FK users, nullOnDelete | |

Indexes `(gym_id, email)`, `(gym_id, user_id)`, `(gym_id, assigned_to)`, `(gym_id, status)`.

**`customer_interactions`**

`gym_id`, `customer_id` FK cascade, `kind`, `channel` (phone, sms, email, messenger, walk_in, other), `body` text, `occurred_at`, `recorded_by` FK users, `resolved_at`, `resolved_by`, `resolution` text, timestamps. Index `(customer_id, occurred_at)`.

**`customer_follow_ups`**

`gym_id`, `customer_id` FK cascade, `assigned_to` FK users, `due_at` dateTime, `note` text, `status` (`open`, `done`, `cancelled`), `done_at`, `done_by`, `outcome` text, `reminded_at` dateTime nullable, timestamps. Index `(gym_id, assigned_to, status, due_at)` and `(customer_id, status)`.

**`customer_groups`**

`gym_id`, `name` string(80), `description` nullable, timestamps. Unique `(gym_id, name)`.

**`customer_group_members`**

`customer_group_id` FK cascade, `customer_id` FK cascade, timestamps. Unique pair.

## Backend tasks

- [x] Models `Customer`, `CustomerInteraction`, `CustomerFollowUp`, `CustomerGroup`, all with `BelongsToGym` except the pivot. `Customer` relations: `user`, `membership` (bypass gym scope), `interactions`, `followUps`, `groups`.
- [x] Derived stage accessor on `Customer`: `lead`, `applied`, `member`, `lost`. Loaded membership status and `starts_on` decide between applied and member.
- [x] Controllers `CustomerController`, `FollowUpController`, `CustomerGroupController`.
- [x] Routes:

| Method and path | Controller | Permission |
|---|---|---|
| `GET customers` | CustomerController@index | `customers.records.view` |
| `GET customers/{customer}` | @show | `customers.records.view` |
| `POST customers` | @store | `customers.records.manage` |
| `PUT customers/{customer}` | @update | `customers.records.manage` |
| `POST customers/{customer}/interactions` | @addInteraction | `customers.records.manage` |
| `POST interactions/{interaction}/resolve` | @resolveInteraction | `customers.records.manage` |
| `GET follow-ups` | FollowUpController@index | `customers.records.view` |
| `POST customers/{customer}/follow-ups` | @store | `customers.records.manage` |
| `PUT follow-ups/{followUp}` | @update | `customers.records.manage` |
| `POST customers/{customer}/link-membership` | CustomerController@linkMembership | `customers.records.manage` |
| `GET customer-groups` | CustomerGroupController@index | `customers.records.view` |
| `POST customer-groups` | @store | `customers.groups.manage` |
| `PUT customer-groups/{group}` | @update | `customers.groups.manage` |
| `DELETE customer-groups/{group}` | @destroy | `customers.groups.manage` |
| `PUT customer-groups/{group}/members` | @syncMembers | `customers.groups.manage` |

- [x] Email auto-link:
  - `CustomerController@store`: when the email matches an applicant in the same gym with no linked customer, set `membership_id` and `linked_at`.
  - `MembershipApplicationController@store`: after creating the application, find a customer in that gym with the same email and no membership link, then link. This route is outside the `gym` middleware, so use an explicit `gym_id` and `withoutGlobalScope('gym')`.
- [x] Notifications: `FollowUpAssigned` to the assignee, `FollowUpDueSoon` is deferred to R-82. `FollowUpAssigned` is database-only.
- [x] Add the four tables to `DatabaseSeeder::resetData()` and seed a few leads, follow-ups and one group for demo gyms.

## Frontend tasks

- [x] `frontend/src/pages/gym/GymCustomers.tsx` at route `customers`:
  - Tabs: **Customers** (search, stage filter, table) and **My follow-ups** (assigned to me, open first).
  - Add customer dialog: name, contact, source, inquiry, assignee.
- [x] `frontend/src/pages/gym/CustomerRecord.tsx` at route `customers/:customerId`:
  - Header with stage badge and contact actions.
  - Details and link-to-membership control.
  - Timeline of interactions with an add form and a resolve action for concerns.
  - Follow-ups card with add, reassign, done with outcome, cancel.
  - Groups card with add/remove.
- [x] Nav entry "Customers" with `[customers.records.view]`.
- [x] Types in `frontend/src/lib/customers.ts`.

## Tests

| File | Must prove |
|---|---|
| `CustomerTest` | Add lead and customer; derived stage changes when a membership is linked and when it starts; lost status; assignee must be staff of this gym |
| `CustomerInteractionTest` | All four kinds stored; only concerns can be resolved; resolution records who and when; interactions are append-only |
| `FollowUpTest` | Create, reassign, done with outcome, cancel; assignee notified; no self-assignment rule unless chosen (default allowed) |
| `CustomerConversionTest` | Manual link to an application in the same gym; email auto-link both directions and case-insensitive; another gym's application cannot be linked |
| `CustomerGroupTest` | Name unique per gym; member sync; adding a linked user creates no duplicate customer |
| `CustomerTenancyTest` | Another gym's customer, follow-up, group and application return 404 |

## Checks

`php artisan test`, `php artisan migrate:fresh --seed` on MySQL, `pnpm lint`, `pnpm build`, browser pass with a phone-width layout.

## Done when

- [x] Staff add a lead or customer with contact details and inquiry.
- [x] A follow-up can be scheduled and assigned, and the assignee is notified.
- [x] Interaction, feedback, preference and concern entries are kept per customer.
- [x] A concern can be resolved, with who and when recorded.
- [x] A lead links to a membership application manually and by email, same gym only.
- [x] Customer groups can be created and filled.
- [x] All tests, lint and build pass.

## Status notes

- **6 November 2026 — automated build complete; browser pass pending.** Built `2026_11_13_100000_create_customer_tables`, `Customer`, `CustomerInteraction`, `CustomerFollowUp`, `CustomerGroup`, `CustomerLinker`, the three CRM controllers, `PresentsCustomers`, `FollowUpAssigned`, `GymCustomers.tsx`, `CustomerRecord.tsx` and `lib/customers.ts`. `CustomerStage` is derived in the model and mirrored in SQL for paginated stage filters. Tests: `CustomerTest`, `CustomerInteractionTest`, `FollowUpTest`, `CustomerConversionTest`, `CustomerGroupTest` (including a converted lead showing as a member in a group), `CustomerTenancyTest` (all green in the full suite). `DatabaseSeederTest` proves seeded leads, interactions, follow-ups, a lost record and a filled group for every demo gym. 6H integration: customer and follow-up lists now return paginated `meta`; the workspace nav groups Customers under **Business**. Browser pass (desktop and phone) still to run.
- **6 November 2026 — browser pass done.** Owner created `E2E Lead` with assignee Bianca, added interaction/feedback/preference/concern records, resolved the concern with resolver name, time and resolution text, added a follow-up (Bianca received the `Follow-up assigned` notice), marked it done with an outcome, created the `E2E Beginners` group and added a converted member to it. Manual link to application #16 changed the stage to Applied; creating Clara Domingo with the applicant email auto-linked application #14, and paying the membership moved the stage to Member. Search and stage filters worked. A view-only Operations Manager initially saw follow-up action buttons and got 403; the write controls are now permission-gated and the retest showed no write buttons. Phone-width customer list and record had no horizontal overflow.

---

[← Previous: Packet 6B Inventory](./phase-3.md) | [Next: Packet 6D Finance core →](./phase-5.md)
