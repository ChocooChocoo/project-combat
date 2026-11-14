# User Accounts by Training Center

> **Development-only credentials.** Every account below uses a known password. Do not ship this file, commit it to a public repository, or reuse these accounts in production. Add `ACCOUNTS.md` to `.gitignore` or delete it before deployment.

Verified against the local MySQL database `combat_platform` and the live site `https://combatpro.site` on 2026-11-10. 118 seeded accounts (the live database is a copy of the local one).

- **Password for every account:** `Password_123`
- Every account is already email-verified and does not need a password change at first sign-in.
- Member accounts use `@demo.combatplatform.ph` emails. Older `@gmail.com` and `@example.test` member emails no longer exist.
- Recreate this data with `php artisan db:seed` from `backend/` (this wipes the database).
- Login page: `http://localhost:5173/sign-in` (local) or `https://combatpro.site/sign-in` (live)
- Centers are isolated. A person can belong to more than one center; those accounts appear in each center below and are also listed in **Cross-gym accounts**.

## Gym directory

| # | Training center | Location | Owner | Staff | Member accounts | Status |
|---|---|---|---|---|---|---|
| 1 | Iron Will Boxing Gym | Quezon City, Metro Manila | Ramon Villanueva | 6 | 19 | Approved |
| 2 | Kalaw Combat Academy | Makati City, Metro Manila | Andrea Lim | 6 | 19 | Approved |
| 3 | Bakunawa Martial Arts Center | Cebu City, Cebu | Carlo Mendoza | 5 | 17 | Approved |
| 4 | Cordillera Fight Camp | Baguio City, Benguet | Maria Bautista | 4 | 17 | Approved |
| 5 | Davao Grappling Club | Davao City, Davao del Sur | Paolo Reyes | 5 | 18 | Approved |

Five more centers exist only as owner applications: Southside Muay Thai, Visayas Fight Lab, Tagum Boxing Club, Mindoro Combat Sports, and Northside Boxing Studio. See **Pending centers**.

## Quick reference: one account per role

| Role | Training center | Email | Password |
|---|---|---|---|
| Platform Administrator | Platform-wide | `admin@combatplatform.ph` | `Password_123` |
| Owner | Iron Will Boxing Gym | `ramon.villanueva@ironwillboxing.ph` | `Password_123` |
| Operations Manager | Iron Will Boxing Gym | `bianca.ramos@ironwillboxing.ph` | `Password_123` |
| Head Coach | Iron Will Boxing Gym | `dindo.salazar@ironwillboxing.ph` | `Password_123` |
| Coach | Iron Will Boxing Gym | `jomar.delosantos@ironwillboxing.ph` | `Password_123` |
| Front Desk | Iron Will Boxing Gym | `trish.gonzales@ironwillboxing.ph` | `Password_123` |
| HR Officer | Iron Will Boxing Gym | `liza.fernandez@ironwillboxing.ph` | `Password_123` |
| Finance Officer | Iron Will Boxing Gym | `mark.cruz@ironwillboxing.ph` | `Password_123` |
| Sports Therapist | Davao Grappling Club | `elena.vasquez@davaograppling.ph` | `Password_123` |
| Athlete / Member | Iron Will Boxing Gym + Kalaw Combat Academy | `sofia.ramirez@demo.combatplatform.ph` | `Password_123` |
| Owner applicant (under review) | Visayas Fight Lab | `grace.fuentes@visayasfightlab.ph` | `Password_123` |
| Owner applicant (returned) | Tagum Boxing Club | `nestor.cabral@tagumboxing.ph` | `Password_123` |
| Membership applicant (pending) | Kalaw Combat Academy | `mara.soriano@demo.combatplatform.ph` | `Password_123` |

## What each role can access

| Role | Access summary |
|---|---|
| Platform Administrator | Platform-level only: center applications, verification review, permission catalog, platform counts. Cannot open a center's private workspace. |
| Training Center Owner | Full access to every module in their own center only. Creates staff roles and assigns permissions. |
| Operations Manager | Manage HR, center setup, training, members, announcements; view health records, payroll, finance, CRM, inventory, procurement, decision support. |
| Head Coach | Approve training and sessions; view members, health records, announcements. |
| Coach | Manage training and sessions; view members. |
| Front Desk | Approve members; manage training, payments, customers, inventory movements, announcements; view receivables. |
| HR Officer | Approve HR actions; manage payroll; view announcements. |
| Finance Officer | Approve finance, payroll, and procurement; view decision support. |
| Sports Therapist | Manage health records; view training and members. |
| Athlete / Member | Book sessions, view own attendance and progress, view own membership and balance. |
| Platform User / Owner Applicant | Browse centers, manage own profile, manage own center application. |

## Account types

An account is created with one fixed type, chosen at sign-up; it cannot change later.

| Type | At a glance |
|---|---|
| `member` | Browse and join approved centers, book sessions, see best matches. Cannot apply to own a center. |
| `owner` | Apply to run a center and manage it once approved. On the member side it can only browse: no joining, no preferences, no best matches. |

- Staff accounts made by an owner get the default `member` type; the staff link itself is per center and unaffected.
- Existing accounts were inferred: owning a center or having any center application makes the account `owner`; everything else is `member`. Older memberships of an owner account still work, but no new ones can start.

---

## Platform-wide accounts

### Platform Administrator

| Name | Email | Role | Password |
|---|---|---|---|
| Marisol Santiago | `admin@combatplatform.ph` | Platform Administrator | `Password_123` |

### Pending centers (owner applicants, no workspace yet)

| Name | Email | Center | Location | Application status | Password |
|---|---|---|---|---|---|
| Jomar Aquino | `jomar.aquino@southsidemuaythai.ph` | Southside Muay Thai | Las Piñas City, Metro Manila | Rejected (Tier 1) | `Password_123` |
| Grace Fuentes | `grace.fuentes@visayasfightlab.ph` | Visayas Fight Lab | Iloilo City, Iloilo | Under review (Tier 2) | `Password_123` |
| Nestor Cabral | `nestor.cabral@tagumboxing.ph` | Tagum Boxing Club | Tagum City, Davao del Norte | Returned for correction (Tier 1) | `Password_123` |
| Rebecca Marasigan | `rebecca.marasigan@mindorocombat.ph` | Mindoro Combat Sports | Calapan City, Oriental Mindoro | Rejected (Tier 1) | `Password_123` |
| Luis Herrera | `luis.herrera@demo.combatplatform.ph` | Northside Boxing Studio | Valenzuela City, Metro Manila | Draft (Tier 1) | `Password_123` |

---

## Gym 1 — Iron Will Boxing Gym

**Location:** Quezon City, Metro Manila  
**Owner:** Ramon Villanueva — `ramon.villanueva@ironwillboxing.ph` — `Password_123`  
**Counts:** 6 staff, 13 active members, 6 inactive or pending

### Owner

| Name | Email | Role | Password |
|---|---|---|---|
| Ramon Villanueva | `ramon.villanueva@ironwillboxing.ph` | Training Center Owner | `Password_123` |

### Staff

| Name | Email | Role | Password |
|---|---|---|---|
| Bianca Ramos | `bianca.ramos@ironwillboxing.ph` | Operations Manager | `Password_123` |
| Dindo Salazar | `dindo.salazar@ironwillboxing.ph` | Head Coach | `Password_123` |
| Jomar Delos Santos | `jomar.delosantos@ironwillboxing.ph` | Coach | `Password_123` |
| Patricia Gonzales | `trish.gonzales@ironwillboxing.ph` | Front Desk | `Password_123` |
| Liza Fernandez | `liza.fernandez@ironwillboxing.ph` | HR Officer | `Password_123` |
| Mark Anthony Cruz | `mark.cruz@ironwillboxing.ph` | Finance Officer | `Password_123` |

### Athletes — active members

| Name | Email | Membership status | Password |
|---|---|---|---|
| Sofia Ramirez | `sofia.ramirez@demo.combatplatform.ph` | Active | `Password_123` |
| Patricia Gomez | `patricia.gomez@demo.combatplatform.ph` | Active | `Password_123` |
| Miguel Soriano | `miguel.soriano@demo.combatplatform.ph` | Active | `Password_123` |
| Christian Roxas | `christian.roxas@demo.combatplatform.ph` | Active | `Password_123` |
| Alyssa Mercado | `alyssa.mercado@demo.combatplatform.ph` | Active | `Password_123` |
| Elena Dela Cruz | `elena.dela.cruz@demo.combatplatform.ph` | Active | `Password_123` |
| Rafael Santos | `rafael.santos@demo.combatplatform.ph` | Active | `Password_123` |
| Isabel Garcia | `isabel.garcia@demo.combatplatform.ph` | Active | `Password_123` |
| Marco Torres | `marco.torres@demo.combatplatform.ph` | Active | `Password_123` |
| Diana Flores | `diana.flores@demo.combatplatform.ph` | Active | `Password_123` |
| Adrian Castillo | `adrian.castillo@demo.combatplatform.ph` | Active | `Password_123` |
| Beatriz Ramos | `beatriz.ramos@demo.combatplatform.ph` | Active | `Password_123` |
| Nico Rivera | `nico.rivera@demo.combatplatform.ph` | Active | `Password_123` |

### Athletes — inactive or pending

| Name | Email | Membership status | Password |
|---|---|---|---|
| Clara Domingo | `clara.domingo@demo.combatplatform.ph` | Application pending (no active gym link) | `Password_123` |
| Enzo Aguilar | `enzo.aguilar@demo.combatplatform.ph` | Application rejected (no active gym link) | `Password_123` |
| Mika Salcedo | `mika.salcedo@demo.combatplatform.ph` | Awaiting payment | `Password_123` |
| Leo Valdez | `leo.valdez@demo.combatplatform.ph` | Frozen | `Password_123` |
| Julia Pascual | `julia.pascual@demo.combatplatform.ph` | Expired | `Password_123` |
| Tomas Estrada | `tomas.estrada@demo.combatplatform.ph` | Cancelled | `Password_123` |

---

## Gym 2 — Kalaw Combat Academy

**Location:** Makati City, Metro Manila  
**Owner:** Andrea Lim — `andrea.lim@kalawcombat.ph` — `Password_123`  
**Counts:** 6 staff, 13 active members, 6 inactive or pending

### Owner

| Name | Email | Role | Password |
|---|---|---|---|
| Andrea Lim | `andrea.lim@kalawcombat.ph` | Training Center Owner | `Password_123` |

### Staff

| Name | Email | Role | Password |
|---|---|---|---|
| Carlo Dizon | `carlo.dizon@kalawcombat.ph` | Operations Manager | `Password_123` |
| Paolo Gutierrez | `paolo.gutierrez@kalawcombat.ph` | Head Coach | `Password_123` |
| Jonas Labrador | `jonas.labrador@kalawcombat.ph` | Coach | `Password_123` |
| Efren Tolentino | `efren.tolentino@kalawcombat.ph` | Coach | `Password_123` |
| Kimberly Tan | `kimberly.tan@kalawcombat.ph` | Front Desk | `Password_123` |
| Rafael Ocampo | `rafael.ocampo@kalawcombat.ph` | Finance Officer | `Password_123` |

### Athletes — active members

| Name | Email | Membership status | Password |
|---|---|---|---|
| Sofia Ramirez | `sofia.ramirez@demo.combatplatform.ph` | Active | `Password_123` |
| Gabriel Tan | `gabriel.tan@demo.combatplatform.ph` | Active | `Password_123` |
| Daniel Villamor | `daniel.villamor@demo.combatplatform.ph` | Active | `Password_123` |
| Jasmine Co | `jasmine.co@demo.combatplatform.ph` | Active | `Password_123` |
| Alyssa Mercado | `alyssa.mercado@demo.combatplatform.ph` | Active | `Password_123` |
| Nico Valdez | `nico.valdez@demo.combatplatform.ph` | Active | `Password_123` |
| Clara Pascual | `clara.pascual@demo.combatplatform.ph` | Active | `Password_123` |
| Enzo Estrada | `enzo.estrada@demo.combatplatform.ph` | Active | `Password_123` |
| Mika Dizon | `mika.dizon@demo.combatplatform.ph` | Active | `Password_123` |
| Leo Velasco | `leo.velasco@demo.combatplatform.ph` | Active | `Password_123` |
| Julia Fontanilla | `julia.fontanilla@demo.combatplatform.ph` | Active | `Password_123` |
| Tomas Ocampo | `tomas.ocampo@demo.combatplatform.ph` | Active | `Password_123` |
| Rico Villaflor | `rico.villaflor@demo.combatplatform.ph` | Active | `Password_123` |

### Athletes — inactive or pending

| Name | Email | Membership status | Password |
|---|---|---|---|
| Mara Soriano | `mara.soriano@demo.combatplatform.ph` | Application pending (no active gym link) | `Password_123` |
| Celina Malabanan | `celina.malabanan@demo.combatplatform.ph` | Application rejected (no active gym link) | `Password_123` |
| Francis Manalo | `francis.manalo@demo.combatplatform.ph` | Awaiting payment | `Password_123` |
| Rochelle Alcantara | `rochelle.alcantara@demo.combatplatform.ph` | Frozen | `Password_123` |
| Jose Pineda | `jose.pineda@demo.combatplatform.ph` | Expired | `Password_123` |
| Teresa De Leon | `teresa.de.leon@demo.combatplatform.ph` | Cancelled | `Password_123` |

---

## Gym 3 — Bakunawa Martial Arts Center

**Location:** Cebu City, Cebu  
**Owner:** Carlo Mendoza — `carlo.mendoza@bakunawama.ph` — `Password_123`  
**Counts:** 5 staff, 11 active members, 6 inactive or pending

### Owner

| Name | Email | Role | Password |
|---|---|---|---|
| Carlo Mendoza | `carlo.mendoza@bakunawama.ph` | Training Center Owner | `Password_123` |

### Staff

| Name | Email | Role | Password |
|---|---|---|---|
| Miguel Sarmiento | `miguel.sarmiento@bakunawama.ph` | Operations Manager | `Password_123` |
| Dante Villamor | `dante.villamor@bakunawama.ph` | Head Coach | `Password_123` |
| Cristina Uy | `cristina.uy@bakunawama.ph` | Coach | `Password_123` |
| Joel Bacalso | `joel.bacalso@bakunawama.ph` | Coach | `Password_123` |
| Robyn Salcedo | `robyn.salcedo@bakunawama.ph` | Front Desk | `Password_123` |

### Athletes — active members

| Name | Email | Membership status | Password |
|---|---|---|---|
| Camille Navarro | `camille.navarro@demo.combatplatform.ph` | Active | `Password_123` |
| Jasmine Co | `jasmine.co@demo.combatplatform.ph` | Active | `Password_123` |
| Nicole Enriquez | `nicole.enriquez@demo.combatplatform.ph` | Active | `Password_123` |
| Rico Alcantara | `rico.alcantara@demo.combatplatform.ph` | Active | `Password_123` |
| Mara Pineda | `mara.pineda@demo.combatplatform.ph` | Active | `Password_123` |
| Celina De Leon | `celina.de.leon@demo.combatplatform.ph` | Active | `Password_123` |
| Francis Rosales | `francis.rosales@demo.combatplatform.ph` | Active | `Password_123` |
| Rochelle Vergara | `rochelle.vergara@demo.combatplatform.ph` | Active | `Password_123` |
| Jose Evangelista | `jose.evangelista@demo.combatplatform.ph` | Active | `Password_123` |
| Teresa Bautista | `teresa.bautista@demo.combatplatform.ph` | Active | `Password_123` |
| Vincent Tolentino | `vincent.tolentino@demo.combatplatform.ph` | Active | `Password_123` |

### Athletes — inactive or pending

| Name | Email | Membership status | Password |
|---|---|---|---|
| Marielle David | `marielle.david@demo.combatplatform.ph` | Application pending (no active gym link) | `Password_123` |
| Dennis Dela Cruz | `dennis.dela.cruz@demo.combatplatform.ph` | Application rejected (no active gym link) | `Password_123` |
| Carmela Santos | `carmela.santos@demo.combatplatform.ph` | Awaiting payment | `Password_123` |
| Joshua Garcia | `joshua.garcia@demo.combatplatform.ph` | Frozen | `Password_123` |
| Danica Torres | `danica.torres@demo.combatplatform.ph` | Expired | `Password_123` |
| Lorenzo Flores | `lorenzo.flores@demo.combatplatform.ph` | Cancelled | `Password_123` |

---

## Gym 4 — Cordillera Fight Camp

**Location:** Baguio City, Benguet  
**Owner:** Maria Bautista — `maria.bautista@cordillerafightcamp.ph` — `Password_123`  
**Counts:** 4 staff, 11 active members, 6 inactive or pending

### Owner

| Name | Email | Role | Password |
|---|---|---|---|
| Maria Bautista | `maria.bautista@cordillerafightcamp.ph` | Training Center Owner | `Password_123` |

### Staff

| Name | Email | Role | Password |
|---|---|---|---|
| Althea Padilla | `althea.padilla@cordillerafightcamp.ph` | Operations Manager | `Password_123` |
| Ben Tolentino | `ben.tolentino@cordillerafightcamp.ph` | Head Coach | `Password_123` |
| Samuel Bumanglag | `samuel.bumanglag@cordillerafightcamp.ph` | Coach | `Password_123` |
| Joy Aban | `joy.aban@cordillerafightcamp.ph` | Front Desk | `Password_123` |

### Athletes — active members

| Name | Email | Membership status | Password |
|---|---|---|---|
| Patricia Gomez | `patricia.gomez@demo.combatplatform.ph` | Active | `Password_123` |
| Miguel Soriano | `miguel.soriano@demo.combatplatform.ph` | Active | `Password_123` |
| Anthony Padilla | `anthony.padilla@demo.combatplatform.ph` | Active | `Password_123` |
| Vincent Garcia | `vincent.garcia@demo.combatplatform.ph` | Active | `Password_123` |
| Marielle Torres | `marielle.torres@demo.combatplatform.ph` | Active | `Password_123` |
| Dennis Flores | `dennis.flores@demo.combatplatform.ph` | Active | `Password_123` |
| Carmela Castillo | `carmela.castillo@demo.combatplatform.ph` | Active | `Password_123` |
| Joshua Ramos | `joshua.ramos@demo.combatplatform.ph` | Active | `Password_123` |
| Danica Rivera | `danica.rivera@demo.combatplatform.ph` | Active | `Password_123` |
| Lorenzo Domingo | `lorenzo.domingo@demo.combatplatform.ph` | Active | `Password_123` |
| Rowena Aguilar | `rowena.aguilar@demo.combatplatform.ph` | Active | `Password_123` |

### Athletes — inactive or pending

| Name | Email | Membership status | Password |
|---|---|---|---|
| Angelo Salcedo | `angelo.salcedo@demo.combatplatform.ph` | Application pending (no active gym link) | `Password_123` |
| Luisa Valdez | `luisa.valdez@demo.combatplatform.ph` | Application rejected (no active gym link) | `Password_123` |
| Elena Pascual | `elena.pascual@demo.combatplatform.ph` | Awaiting payment | `Password_123` |
| Rafael Estrada | `rafael.estrada@demo.combatplatform.ph` | Frozen | `Password_123` |
| Isabel Dizon | `isabel.dizon@demo.combatplatform.ph` | Expired | `Password_123` |
| Marco Velasco | `marco.velasco@demo.combatplatform.ph` | Cancelled | `Password_123` |

---

## Gym 5 — Davao Grappling Club

**Location:** Davao City, Davao del Sur  
**Owner:** Paolo Reyes — `paolo.reyes@davaograppling.ph` — `Password_123`  
**Counts:** 5 staff, 12 active members, 6 inactive or pending

### Owner

| Name | Email | Role | Password |
|---|---|---|---|
| Paolo Reyes | `paolo.reyes@davaograppling.ph` | Training Center Owner | `Password_123` |

### Staff

| Name | Email | Role | Password |
|---|---|---|---|
| Nico Alcantara | `nico.alcantara@davaograppling.ph` | Operations Manager | `Password_123` |
| Gabriel Sison | `gabriel.sison@davaograppling.ph` | Head Coach | `Password_123` |
| Marlon Tiu | `marlon.tiu@davaograppling.ph` | Coach | `Password_123` |
| Charmaine Go | `charmaine.go@davaograppling.ph` | Front Desk | `Password_123` |
| Elena Vasquez | `elena.vasquez@davaograppling.ph` | Sports Therapist | `Password_123` |

### Athletes — active members

| Name | Email | Membership status | Password |
|---|---|---|---|
| Gabriel Tan | `gabriel.tan@demo.combatplatform.ph` | Active | `Password_123` |
| Daniel Villamor | `daniel.villamor@demo.combatplatform.ph` | Active | `Password_123` |
| Jasmine Co | `jasmine.co@demo.combatplatform.ph` | Active | `Password_123` |
| Anthony Padilla | `anthony.padilla@demo.combatplatform.ph` | Active | `Password_123` |
| Rowena Estrada | `rowena.estrada@demo.combatplatform.ph` | Active | `Password_123` |
| Angelo Dizon | `angelo.dizon@demo.combatplatform.ph` | Active | `Password_123` |
| Luisa Velasco | `luisa.velasco@demo.combatplatform.ph` | Active | `Password_123` |
| Elena Fontanilla | `elena.fontanilla@demo.combatplatform.ph` | Active | `Password_123` |
| Rafael Ocampo | `rafael.ocampo@demo.combatplatform.ph` | Active | `Password_123` |
| Isabel Villaflor | `isabel.villaflor@demo.combatplatform.ph` | Active | `Password_123` |
| Marco Soriano | `marco.soriano@demo.combatplatform.ph` | Active | `Password_123` |
| Diana Malabanan | `diana.malabanan@demo.combatplatform.ph` | Active | `Password_123` |

### Athletes — inactive or pending

| Name | Email | Membership status | Password |
|---|---|---|---|
| Adrian Manalo | `adrian.manalo@demo.combatplatform.ph` | Application pending (no active gym link) | `Password_123` |
| Beatriz Alcantara | `beatriz.alcantara@demo.combatplatform.ph` | Application rejected (no active gym link) | `Password_123` |
| Nico Pineda | `nico.pineda@demo.combatplatform.ph` | Awaiting payment | `Password_123` |
| Clara De Leon | `clara.de.leon@demo.combatplatform.ph` | Frozen | `Password_123` |
| Enzo Rosales | `enzo.rosales@demo.combatplatform.ph` | Expired | `Password_123` |
| Mika Vergara | `mika.vergara@demo.combatplatform.ph` | Cancelled | `Password_123` |

---

## Cross-gym accounts

These members belong to more than one center. Signing in once gives access to all listed centers.

| Name | Email | Centers | Role | Password |
|---|---|---|---|---|
| Sofia Ramirez | `sofia.ramirez@demo.combatplatform.ph` | Iron Will Boxing Gym + Kalaw Combat Academy | Athlete / Member | `Password_123` |
| Patricia Gomez | `patricia.gomez@demo.combatplatform.ph` | Iron Will Boxing Gym + Cordillera Fight Camp | Athlete / Member | `Password_123` |
| Miguel Soriano | `miguel.soriano@demo.combatplatform.ph` | Iron Will Boxing Gym + Cordillera Fight Camp | Athlete / Member | `Password_123` |
| Alyssa Mercado | `alyssa.mercado@demo.combatplatform.ph` | Iron Will Boxing Gym + Kalaw Combat Academy | Athlete / Member | `Password_123` |
| Gabriel Tan | `gabriel.tan@demo.combatplatform.ph` | Kalaw Combat Academy + Davao Grappling Club | Athlete / Member | `Password_123` |
| Daniel Villamor | `daniel.villamor@demo.combatplatform.ph` | Kalaw Combat Academy + Davao Grappling Club | Athlete / Member | `Password_123` |
| Jasmine Co | `jasmine.co@demo.combatplatform.ph` | Kalaw Combat Academy + Bakunawa Martial Arts Center + Davao Grappling Club | Athlete / Member | `Password_123` |
| Anthony Padilla | `anthony.padilla@demo.combatplatform.ph` | Cordillera Fight Camp + Davao Grappling Club | Athlete / Member | `Password_123` |

## Account counts

| Category | Count |
|---|---|
| Platform Administrator | 1 |
| Training Center Owners | 5 |
| Staff / employees | 26 |
| Member accounts (71 linked + 10 pending/rejected only) | 81 |
| Pending center owner applicants | 5 |
| **Total** | **118** |

Staff by gym: Iron 6, Kalaw 6, Bakunawa 5, Cordillera 4, Davao 5.

## Notes

- All passwords were checked against the current database hashes on local and live; every account matches `Password_123`.
- "Active", "Awaiting payment", "Frozen", "Expired", and "Cancelled" are membership statuses, not account statuses.
- Members with only a pending or rejected application have no active center link. They can still sign in and view their application.
- Staff accounts are created by the owner through the app in normal use; these demo staff accounts are already verified and active.

## Ready-to-test data (P09 seed)

Sign in as the owner of Iron Will (`ramon.villanueva@ironwillboxing.ph`) or Kalaw (`andrea.lim@kalawcombat.ph`). Every approved center has the same kinds of items; gym 1 and 2 are the fullest.

| What | Where | Try |
|---|---|---|
| Payroll period with attendance and no run yet | Payroll, new run for the month before the in-review run | Create, compute, submit. Iron Will: Finance Officer (`mark.cruz@ironwillboxing.ph`) then Ramon. Kalaw: HR then Andrea. Other centers: owner only |
| Payroll run in review | Payroll | Approve, or reject with a reason |
| Offer waiting (voucher `WELCOME10`, made by Front Desk) | Business, Offers | Owner approves or rejects. Front Desk (`trish.gonzales@ironwillboxing.ph`) cannot decide it |
| Offer rejected, used up voucher `FRIEND200`, expired promo | Business, Offers | Read the reason, edit and resubmit |
| Membership applications (one prepaying 3 months, one with `WELCOME10`) | Members, Applications | Approve before and after the offer is approved |
| Coach applications (2 waiting, 1 rejected, 1 withdrawn) | HR, Coach applications | Owner approves or rejects. HR can only view |
| Purchase requests waiting (2 per center) | Purchasing | Edit, approve, reject |
| Part-received order with a delivery photo | Purchasing, orders | Open the Receipt proof section, add more photos |
| Leave, overtime, booking and session requests waiting | Leave, Overtime, Sessions | Approve or reject |
| Training goals, injuries (one Restricted), equipment checks (needs repair, unsafe, overdue) | Member record, Stock, Safety | Update status, clear the injury |
| Notices already in the bell | Owner: waiting offer and coach application, inactive members, safety checks due | Open each link |
| Inactive members (last two active members of each center) | Bell and Members | Check the notice text |

## Ready-to-test data (P10 and P11 seed)

Seeding needs the repo-root `assets/` folder (sample images and PDFs). If a file is missing, `db:seed` stops with a "Demo asset missing" message.

**New accounts** (password `Password_123`, all `@demo.combatplatform.ph`, all `member` type):

- **Job applicants:** 20 people, 4 per approved center, in this order: Kevin Lacson, Princess Magbanua, Ronald Sarmiento, Hazel Quinto (Iron Will); Jerome Abad, Kristine Nepomuceno, Ariel Buenaventura, Shiela Cortez (Kalaw); Ramil Ilagan, Joanna Pacheco, Bryan Salonga, Mylene Dimaculangan (Bakunawa); Arnel Bustamante, Czarina Lim, Edgar Macaraeg, Faith Sumulong (Cordillera); Gilbert Tan, Irene Mangahas, Wilfredo Ponce, Lovely Guevarra (Davao). Email is first and last name with a dot, lower case, e.g. `kevin.lacson@demo.combatplatform.ph`. Per center the 4th person is the approved applicant and is now staff: Sports massage therapist (Sports Therapist role). The 1st and 3rd are waiting, the 2nd was rejected.
- **Approved coach applicant (now a Coach, from day 3 ago):** the 8th active member of each center, listed as an active athlete above (Isabel Garcia, Enzo Estrada, Rochelle Vergara, Joshua Ramos, Elena Fontanilla). They now also have a staff link, so they can open the manage screens.
- **Minor member with guardian:** the 9th active member of each center (Marco Torres, Mika Dizon, Jose Evangelista, Danica Rivera, Rafael Ocampo). Age 14 to 16, guardian name, phone, relationship and consent are filled.
- **Walk-in visitors are customers, not accounts.** They appear under Customers (source walk-in) and in Check-in, "Today's walk-ins".
- Every account now has a birthdate. Most have a middle name, city and province. Nestor Cabral and Daniel Villamor are "Jr."; Paolo Reyes is "III".

**What each center has:**

| What | Where | Detail |
|---|---|---|
| Offers | Business, Offers | Pending, approved, rejected and archived |
| Coach applications | HR, Coach applications | Pending, rejected, withdrawn and approved |
| Training goals | Member record | Active, achieved and dropped |
| Equipment checks | Stock, Safety | Pass, needs repair and unsafe |
| Purchase proofs | Purchasing, orders | One image and one PDF on the part-received order |
| Government IDs | HR, employee record | SSS, PhilHealth, Pag-IBIG on staff, except the contractor (3rd staff) |
| Clock-in radius | Gym settings, Attendance | Iron Will 150 m, Kalaw 200 m; staff there have self clock-ins with distance for the last 3 working days. Other centers: no check |
| Session thumbnail | Sessions | One class service has a thumbnail on all its sessions |
| Gym photos | Gym settings | Cover and 6 gallery photos |
| Policy files | Gym settings | Iron Will 3, Kalaw 2, Bakunawa 1, Cordillera 0, Davao 0 |
| Medical clearance | Members | Every membership has a PDF or scan |
| Sparring profiles | Athletes | Mixed known and unknown weight, empty and real records |
| Job postings | HR, Hiring | Front desk (open), assistant coach (open), sports massage therapist (closed) |
| Job applications | HR, Hiring | Two pending, one approved, one rejected |
| Walk-ins | Check-in | 3 today (1 paid), 1 yesterday and 1 three days ago (both paid); one answers "recent injury" |

**Document review (admin, Applications):** Southside (submitted) has only the DTI opened. Visayas (under review) has every document opened, and the owner ID is flagged for an owner name mismatch. Tagum (returned) has an expired mayor's permit. Mindoro (rejected) has the DTI flagged for a gym name mismatch. Approved centers have every document opened. Northside (draft) has no review data.
