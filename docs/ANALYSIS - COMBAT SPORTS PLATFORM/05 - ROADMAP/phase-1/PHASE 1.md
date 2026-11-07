# PHASE 1

**People can sign up, prove who they are, and be recognised safely**

[00 - START HERE](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · [05 - ROADMAP](../05%20-%20ROADMAP.md) · Previous: [05 - ROADMAP](../05%20-%20ROADMAP.md) · Next: [PHASE 2](../phase-2/PHASE%202.md)

**Status:** ✅ Finished

Citation forms are the same as in [02 - FINDINGS](../../02%20-%20FINDINGS/02%20-%20FINDINGS.md). Every item here is a rebuild. "Old system" lines point at what was tried before, for reference only (said in conversation, 3 November 2026).

## The goal

Anyone can create one account, confirm it with an emailed code, sign in safely, and look after their own profile. The system knows each person's role and, for gym employees, exactly which modules they may use. No one can ever see another gym's records.

## Why it comes here

Everything else leans on this. Gyms need owner accounts, members need accounts, and every later screen needs to know who is asking and what they may do. In the old system, gym separation was added job by job afterwards, and it ended up with gaps (C-18 in [02 - FINDINGS](../../02%20-%20FINDINGS/02%20-%20FINDINGS.md)). Building it first avoids that.

## What it waits for

Nothing. This is the starting point.

## What it unlocks

[PHASE 2](../phase-2/PHASE%202.md) needs accounts (R-01, R-02) for owner applications. Every later phase needs the permission model and gym separation (R-07 to R-09), and the notification inbox (R-10).

## Where this phase stands

| Status | How many |
|---|---|
| ✅ Finished | 10 |
| 🟨 Being worked on | 0 |
| ⭕ Not started | 0 |
| ❌ Blocked | 0 |
| 🔵 Already there | 0 |
| ⬜ Dropped | 0 |
| ❓ Unclear | 0 |
| **Total** | **10** |

---

## Module: Accounts and sign-in

**Purpose:** let people create an account, confirm it, sign in and out safely, and keep their details up to date.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-01 | One sign-up for every platform user | — | ✅ | Anyone can create an account from one sign-up page |
| R-02 | Emailed one-time code confirms the account | R-01 | ✅ | An unconfirmed account cannot use protected features |
| R-03 | Safe sign-in and sign-out | R-02 | ✅ | Passes expire and signing out ends them |
| R-04 | Forgotten-password recovery | R-01 | ✅ | A person can set a new password by email |
| R-05 | Own profile | R-03 | ✅ | A person can view and change their own details |
| R-06 | Forced password change at first sign-in | R-03, R-08 | ✅ | Staff accounts made by an owner must change password before doing anything else |

### R-01: One sign-up for every platform user

**In plain words:** There is one sign-up page. A person gives their personal details and sign-in details and becomes a "platform user". Becoming an owner or a member happens later, by applying.

**Update (6 November 2026):** Account type, `member` or `owner`, is now chosen at sign-up and cannot change. Owner accounts apply to run a gym and browse the member side only (no joins, no preferences or best matches); member accounts cannot apply to own a gym. Staff links are unaffected; existing accounts were inferred from owning a gym or having an application (`owner`), otherwise `member`.

**Why it is needed:** The spec makes owner and member roles things you apply for after signing up (the spec, pages 7 and 10). The old system had two separate sign-up pages, and the owner one created a gym straight away (C-02).

**Where the need came from:** D-01, the spec, page 9, section 1.1. Also page 7.

**Depends on:** nothing.

**Blockers:** none known.

**Done when:**

- [x] A visitor can create an account from one page with their name, email and password.
- [x] An email address can be used for only one account.
- [x] A new account starts as a platform user, not as an owner or member.

**Still open:** whether an age limit applies. The old member page required 18 or over (`backend/app/Http/Controllers/API/MemberController.php`, lines 49 to 56); the spec says nothing. It is not raised as a separate question, because it can be settled during implementation planning.

**Old system:** `frontend/src/pages/Register.vue` and `frontend/src/pages/RegisterMember.vue`; `backend/app/Http/Controllers/API/AuthController.php`, lines 50 to 125.

### R-02: Emailed one-time code confirms the account

**In plain words:** After sign-up, the system emails a one-time code. Until the person types it in, the account cannot use anything protected.

**Why it is needed:** It proves the email address belongs to the person (the spec, page 9). The old codes were plain text, with no limit on guesses (`AuthController.php`, lines 80, 81 and 130 to 224).

**Where the need came from:** D-05, the spec, page 9, section 1.2.

**Depends on:** R-01, because there must be an account to confirm.

**Blockers:** none known.

**Done when:**

- [x] A code arrives by email within moments of sign-up.
- [x] The code stops working after a short time.
- [x] After a small number of wrong tries, the person must wait or ask for a new code.
- [x] A person can ask for a new code.
- [x] After a correct code, the person lands on the platform home page (D-06).

**Still open:** nothing.

**Old system:** `AuthController.php`, lines 130 to 224; `MemberController.php`, lines 99 to 193.

### R-03: Safe sign-in and sign-out

**In plain words:** A confirmed person signs in with email and password and gets a pass that proves they are signed in. The pass runs out after a while. Signing out ends it everywhere it should.

**Why it is needed:** The spec asks for secure sign-in (page 9). Old passes never expired (`backend/config/sanctum.php`, line 50). Four old sign-out buttons left the pass working (for example `frontend/src/pages/AdminDashboard.vue`, line 1565). Nothing slowed down password guessing.

**Where the need came from:** D-02, the spec, page 9, section 1.1.

**Depends on:** R-02, because only confirmed accounts sign in.

**Blockers:** none known.

**Done when:**

- [x] A person with the right email and password is signed in.
- [x] A person who types the wrong password several times in a row is made to wait before trying again.
- [x] A pass stops working after a set time.
- [x] Pressing sign out on any screen means the old pass no longer works.

**Still open:** nothing.

**Old system:** `AuthController.php`, lines 229 to 294.

### R-04: Forgotten-password recovery

**In plain words:** A person who forgets their password asks for a reset, gets a code or link by email, and sets a new password.

**Why it is needed:** The spec, page 9. The old version told strangers whether an email had an account (`AuthController.php`, lines 1012 to 1015). It also used weaker password rules than sign-up (`frontend/src/pages/ForgotPassword.vue`, line 580, against `frontend/src/pages/Register.vue`, line 1382).

**Where the need came from:** D-04, the spec, page 9, section 1.1.

**Depends on:** R-01.

**Blockers:** none known.

**Done when:**

- [x] A person can reset their password using only their email.
- [x] The reply looks the same whether or not the email has an account.
- [x] The new password must meet the same rules as at sign-up.
- [x] The reset code stops working after use or after a short time.

**Still open:** nothing.

**Old system:** `AuthController.php`, lines 1004 to 1119.

### R-05: Own profile

**In plain words:** Every signed-in person can see and change their own name, contact details, photo and password.

**Why it is needed:** The spec, page 9; the access table on page 7 gives everyone "own profile".

**Where the need came from:** D-03, the spec, page 9, section 1.1.

**Depends on:** R-03.

**Blockers:** none known.

**Done when:**

- [x] A person can change their own details and photo.
- [x] Changing the email address needs the new address to be confirmed again.
- [x] A person can change their password by giving the current one.

**Still open:** nothing.

**Old system:** `backend/app/Http/Controllers/API/SettingsController.php`; `frontend/src/components/SettingsPanel.vue`. The old email change did not truly re-confirm the address (`SettingsController.php`, lines 74 to 77).

### R-06: Forced password change at first sign-in

*From the old system, not in the spec. Kept as a fitting extra (said in conversation, 3 November 2026).*

**In plain words:** When an owner creates an account for an employee, the employee must choose their own password the first time they sign in. Until they do, nothing else works.

**Why it is needed:** Otherwise the password the owner set, or the one emailed, stays in use. The old system only enforced this on screen (`frontend/src/App.vue`, lines 34 to 77). The loose scripts show the developers struggled to get it right (`backend/check_password_changed_at.php` and others).

**Where the need came from:** the old system, `AuthController.php`, lines 275 to 277, and `backend/app/Http/Controllers/API/UserManagementController.php`, lines 868 to 956. It supports D-02 (secure sign-in).

**Depends on:** R-03 (sign-in) and R-08 (owners create employee accounts).

**Blockers:** none known.

**Done when:**

- [x] A newly created employee who signs in is asked to set a new password first.
- [x] Until they do, every other screen and request is refused.
- [x] The temporary password is never shown back on screen.

**Still open:** nothing.

**Old system:** as above.

---

## Module: Roles, permissions and gym separation

**Purpose:** make sure each person can see and do only what their role and permissions allow, and only inside their own gym.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-07 | Five roles | R-01 | ✅ | Every account has exactly the roles the spec names, and only the platform can create an administrator |
| R-08 | Owner-made roles with named permissions | R-07 | ✅ | Owners create their own roles, and an employee reaches only what their active, unexpired roles allow |
| R-09 | Each gym's records kept apart | R-07 | ✅ | Nobody from one gym can reach another gym's records |

### R-07: Five roles

**In plain words:** The system knows five roles: platform administrator, platform user, gym owner, gym employee (coaches included) and athlete/member. A person may hold more than one; for example, a member of one gym may own another. The platform administrator role can only be given by the platform itself, never by an owner.

**Update (6 November 2026):** Account type, `member` or `owner`, is fixed at sign-up, so owning and joining no longer mix. Owners apply to run a gym and browse the member side only (no joins, no preferences or best matches); members cannot apply to own a gym. Staff links and the platform administrator are unaffected; existing accounts were inferred from owning a gym or having an application (`owner`), otherwise `member`.

**Why it is needed:** The spec, pages 7 and 9. The old system had 13 fixed roles plus per-gym roles, stored three ways that disagreed (C-08). An owner could make someone an administrator (`backend/app/Http/Controllers/API/RolePermissionController.php`, lines 165 to 182).

**Where the need came from:** D-08, the spec, page 7 and page 9, section 1.4. Staff model: said in conversation, 3 November 2026.

**Depends on:** R-01.

**Blockers:** Q-09 and Q-10 (whether a person can own or belong to more than one gym) change how roles attach to gyms.

**Done when:**

- [x] Every account is a platform user.
- [x] Owner, employee and member roles are always tied to a specific gym.
- [x] Only the platform can create a platform administrator, and no owner screen or request can grant it.
- [x] Each role is stored in one place only.

**Still open:** Q-09 and Q-10 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md).

**Old system:** `combat_sports_db.sql`, lines 3335 to 3362, 4345 and 4292.

### R-08: Owner-made roles with named permissions

**In plain words:** Roles hold named permissions from a shared catalog (`config/permissions.php`, written `module.resource.action`). Owners build roles per gym and can turn them off or give them until a date. Owners hold every active permission. Permissions are independent, so Approve does not imply View or Manage. Nobody can grant access they do not hold, change their own assignments or expiry dates, or edit a role they hold. Disabled keys and dormant grants stay until explicitly removed; authorized cleanup does not permit new or reactivated access. The platform admin runs the catalog at `/admin/permissions`. Four starter roles can be customized but not deleted. See [permission operations](../../../implementation-plan/rbac-redesign/rbac-operations.md) for deployment and guarded recovery.

**Why it is needed:** The spec, page 9, section 1.4, and the "by role" note on page 7. Your client asked for "dynamic RBAC", meaning roles the owner can create and change (asked by your client, relayed in conversation, 3 November 2026). You chose owner-made roles (said in conversation, 3 November 2026). In the old system, every staff member counted as HR (`backend/app/Support/RbacPermissionCatalog.php`, lines 52 to 59; `backend/app/Models/User.php`, lines 166 to 180). Owners also skipped every check (`backend/app/Http/Middleware/CheckPermission.php`, lines 32 to 34).

**Where the need came from:** D-09, D-81 and D-82.

**Depends on:** R-07.

**Blockers:** none known.

**Done when:**

- [x] An owner can create, name, edit and delete unused custom roles, choosing independent named permissions.
- [x] An owner can give an employee one or more roles, and take them away.
- [x] Changing a role changes access for everyone holding it straight away.
- [x] Four starter roles are seeded per gym and are also available as templates. They can be renamed, edited, or turned off, but not deleted.
- [x] One gym's roles are never visible to, or usable by, another gym.
- [x] An employee without the finance permission is refused on every finance screen and request, not just hidden from the menu.
- [x] An employee cannot create an account or a permission set with more access than their own. *(Must still hold once roles exist: nobody may create or give a role above their own access.)*
- [x] Decision-support screens are available to an employee only when this permission is given (D-81).

The screens for managing roles and giving them to employees are part of R-22 in [PHASE 3](../phase-3/PHASE%203.md), where employees are added. This item covers the rules behind them.

**Still open:** nothing.

**How it was built:** first built with fixed presets, then owner-made module levels, then converted to exact permission sets on 5 November 2026. Roles live in `gym_roles`, permissions in `permissions`, and links in `gym_role_permission` and `gym_role_user`. The latter records grant expiry and grantor. Role viewing uses `hr.roles.view`; changes use `hr.roles.manage`, separate from staff access. The shared assignment service enforces transactional replacement and self-change and access guards.

**Old system:** `backend/app/Support/RbacPermissionCatalog.php`; `backend/app/Http/Middleware/CheckRole.php`, lines 51 to 53 and 82 to 102. The old system also had per-gym "saved role profiles" (`combat_sports_db.sql`, line 4292), stored as a third role layer that disagreed with the other two.

### R-09: Each gym's records kept apart

**In plain words:** Every record that belongs to a gym is marked with that gym. One shared rule makes sure every person sees and changes only their own gym's records. The platform administrator sees only platform-wide numbers (see R-19 in [PHASE 2](../phase-2/PHASE%202.md)).

**Why it is needed:** The spec, page 7 note, page 8 and page 9, section 1.4. The old system did this by hand in each job, with gaps (C-18).

**Where the need came from:** D-10.

**Depends on:** R-07.

**Blockers:** Q-09 and Q-10.

**Done when:**

- [x] A staff member of gym A who asks for a gym B record, by any route, is refused.
- [x] Creating a record for another gym's people is refused.
- [x] The platform administrator cannot open any gym's private records.
- [x] One check covers every module, and new modules get it without extra work. *(This box is checked by the person building the system; it has no visible effect.)*

**Still open:** Q-09 and Q-10.

**Old system:** for example `backend/app/Http/Controllers/Api/AttendanceController.php`, lines 51 to 61, and `backend/app/Http/Controllers/API/PayrollController.php`, lines 1621 to 1623.

---

## Module: Notification inbox

**Purpose:** give every person one place where the system's notices appear, so later modules can send them.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-10 | In-app notification inbox | R-03 | ✅ | Every person has a list of their notices, with unread ones marked |

### R-10: In-app notification inbox

**In plain words:** Every signed-in person has a notification list. Notices appear there, unread ones stand out, and the person can mark them read or clear them. The notices themselves are added by later items (for example R-17, R-33 and R-49). Live delivery comes in R-83 in [PHASE 7](../phase-7/PHASE%207.md).

**Why it is needed:** Nine of the spec's automatic notices land in modules built before Phase 7 (the spec, page 18). They need somewhere to go.

**Where the need came from:** *drawn from* D-72, the spec, page 18, and the phase order in [04 - DIAGRAMS](../../04%20-%20DIAGRAMS/04%20-%20DIAGRAMS.md).

**Depends on:** R-03.

**Blockers:** none known.

**Done when:**

- [x] A person sees a list of their own notices, newest first, with an unread count.
- [x] A person can mark one or all as read, and clear read ones.
- [x] A person never sees anyone else's notices.

**Still open:** nothing.

**Old system:** `backend/app/Http/Controllers/NotificationController.php`. In the old system, clearing read notices was caught by the wrong address (`backend/routes/api.php`, lines 495 and 496).

---

## Where it was built

Built on 3 November 2026. Paths are from the project folder `project-02-combat`.

- **Setup:** Laravel 13 backend in `backend/`, MySQL database `combat_platform` (Laragon MySQL 8.4), React + Vite + coss UI frontend in `frontend/`.
- **Accounts and sign-in (R-01 to R-06):** `backend/app/Http/Controllers/Auth/AuthController.php`, `backend/app/Http/Controllers/ProfileController.php`, `backend/app/Support/OneTimeCodes.php`; screens in `frontend/src/pages/`.
- **Roles, permissions, gym separation (R-07 to R-09):** `backend/app/Models/User.php`, `backend/app/Models/Concerns/BelongsToGym.php`, `backend/app/Http/Middleware/`, `backend/app/Models/GymRole.php`, `backend/app/Http/Controllers/Gym/GymRoleController.php`, role templates in `backend/config/access.php`. A platform administrator is made only with the `app:make-admin` command.
- **Notification inbox (R-10):** `backend/app/Http/Controllers/NotificationController.php`, `frontend/src/pages/Notifications.tsx`.
- **Checks:** 26 automatic tests in `backend/tests/Feature/`, all passing. A full sign-up, confirm, sign-in and sign-out run was also made against the real MySQL database.
- **Reopened and finished:** R-08, rebuilt from fixed presets into owner-made roles ("dynamic RBAC", asked by your client, relayed in conversation, 3 November 2026).
- **Q-09 and Q-10 answered:** a person may hold roles in several gyms (said in conversation, 3 November 2026).

## Phase blockers

None.

## Phase is finished when

Every item above is ✅, 🔵 or ⬜. Anyone can sign up, confirm by email and sign in safely. Each person reaches only what their role, permissions and gym allow.
