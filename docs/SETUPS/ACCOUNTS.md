# User Accounts by Training Center

> **Development-only credentials.** Every account below uses a known password. Do not ship this file, commit it to a public repository, or reuse these accounts in production. Add `ACCOUNTS.md` to `.gitignore` or delete it before deployment.

Verified against the local MySQL database `combat_platform` on 2026-11-06. 119 accounts total: 118 seeded accounts and 1 test account.

- **Password for every account:** `Password_123`
- All seeded accounts are already email-verified and do not need a password change at first sign-in.
- Recreate this data with `php artisan db:seed` from `backend/` (this wipes the database).
- Login page: `http://localhost:5173/sign-in`
- Centers are isolated. A person can belong to more than one center; those accounts appear in each center below and are also listed in **Cross-gym accounts**.

## Gym directory

| # | Training center | Location | Owner | Staff | Linked members | Status |
|---|---|---|---|---|---|---|
| 1 | Iron Will Boxing Gym | Quezon City, Metro Manila | Ramon Villanueva | 6 | 19 | Approved |
| 2 | Kalaw Combat Academy | Makati City, Metro Manila | Andrea Lim | 6 | 17 | Approved |
| 3 | Bakunawa Martial Arts Center | Cebu City, Cebu | Carlo Mendoza | 5 | 15 | Approved |
| 4 | Cordillera Fight Camp | Baguio City, Benguet | Maria Bautista | 4 | 15 | Approved |
| 5 | Davao Grappling Club | Davao City, Davao del Sur | Paolo Reyes | 5 | 16 | Approved |

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
| Athlete / Member | Iron Will Boxing Gym + Kalaw Combat Academy | `sofia.ramirez@gmail.com` | `Password_123` |
| Owner applicant (submitted) | Southside Muay Thai | `jomar.aquino@southsidemuaythai.ph` | `Password_123` |
| Owner applicant (under review) | Visayas Fight Lab | `grace.fuentes@visayasfightlab.ph` | `Password_123` |
| Membership applicant (pending) | Kalaw Combat Academy | `clara.domingo.gym2@example.test` | `Password_123` |

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
| Jomar Aquino | `jomar.aquino@southsidemuaythai.ph` | Southside Muay Thai | Las Piñas City, Metro Manila | Submitted (Tier 1) | `Password_123` |
| Grace Fuentes | `grace.fuentes@visayasfightlab.ph` | Visayas Fight Lab | Iloilo City, Iloilo | Under review (Tier 2) | `Password_123` |
| Nestor Cabral | `nestor.cabral@tagumboxing.ph` | Tagum Boxing Club | Tagum City, Davao del Norte | Returned for correction (Tier 1) | `Password_123` |
| Rebecca Marasigan | `rebecca.marasigan@mindorocombat.ph` | Mindoro Combat Sports | Calapan City, Oriental Mindoro | Rejected (Tier 1) | `Password_123` |
| Luis Herrera | `luis.herrera@gmail.com` | Northside Boxing Studio | Valenzuela City, Metro Manila | Draft (Tier 1) | `Password_123` |

---

## Gym 1 — Iron Will Boxing Gym

**Location:** Quezon City, Metro Manila  
**Owner:** Ramon Villanueva — `ramon.villanueva@ironwillboxing.ph` — `Password_123`  
**Counts:** 6 staff, 19 linked members, 1 rejected membership applicant

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
| Sofia Ramirez | `sofia.ramirez@gmail.com` | Active | `Password_123` |
| Patricia Gomez | `patricia.gomez@gmail.com` | Active | `Password_123` |
| Miguel Soriano | `miguel.soriano@gmail.com` | Active | `Password_123` |
| Christian Roxas | `christian.roxas@gmail.com` | Active | `Password_123` |
| Alyssa Mercado | `alyssa.mercado@gmail.com` | Active | `Password_123` |
| Elena Dela Cruz | `elena.dela.cruz.gym1@example.test` | Active | `Password_123` |
| Rafael Santos | `rafael.santos.gym1@example.test` | Active | `Password_123` |
| Isabel Garcia | `isabel.garcia.gym1@example.test` | Active | `Password_123` |
| Marco Torres | `marco.torres.gym1@example.test` | Active | `Password_123` |
| Diana Flores | `diana.flores.gym1@example.test` | Active | `Password_123` |
| Adrian Castillo | `adrian.castillo.gym1@example.test` | Active | `Password_123` |
| Beatriz Ramos | `beatriz.ramos.gym1@example.test` | Active | `Password_123` |
| Nico Rivera | `nico.rivera.gym1@example.test` | Active | `Password_123` |
| Clara Domingo | `clara.domingo.gym1@example.test` | Active | `Password_123` |

### Athletes — inactive or pending

| Name | Email | Membership status | Password |
|---|---|---|---|
| Mika Salcedo | `mika.salcedo.gym1@example.test` | Awaiting payment | `Password_123` |
| Leo Valdez | `leo.valdez.gym1@example.test` | Frozen | `Password_123` |
| Julia Pascual | `julia.pascual.gym1@example.test` | Expired | `Password_123` |
| Tomas Estrada | `tomas.estrada.gym1@example.test` | Cancelled | `Password_123` |
| Case Check | `CaseCheck.20261106@Example.com` | Awaiting payment — test account, not part of the seeder | `Password_123` |
| Enzo Aguilar | `enzo.aguilar.gym1@example.test` | Application rejected (no active gym link) | `Password_123` |

---

## Gym 2 — Kalaw Combat Academy

**Location:** Makati City, Metro Manila  
**Owner:** Andrea Lim — `andrea.lim@kalawcombat.ph` — `Password_123`  
**Counts:** 6 staff, 17 linked members, 1 pending application, 1 rejected application

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
| Sofia Ramirez | `sofia.ramirez@gmail.com` | Active | `Password_123` |
| Gabriel Tan | `gabriel.tan@gmail.com` | Active | `Password_123` |
| Daniel Villamor | `daniel.villamor@gmail.com` | Active | `Password_123` |
| Jasmine Co | `jasmine.co@gmail.com` | Active | `Password_123` |
| Alyssa Mercado | `alyssa.mercado@gmail.com` | Active | `Password_123` |
| Elena Dela Cruz | `elena.dela.cruz.gym2@example.test` | Active | `Password_123` |
| Rafael Santos | `rafael.santos.gym2@example.test` | Active | `Password_123` |
| Isabel Garcia | `isabel.garcia.gym2@example.test` | Active | `Password_123` |
| Marco Torres | `marco.torres.gym2@example.test` | Active | `Password_123` |
| Diana Flores | `diana.flores.gym2@example.test` | Active | `Password_123` |
| Adrian Castillo | `adrian.castillo.gym2@example.test` | Active | `Password_123` |
| Beatriz Ramos | `beatriz.ramos.gym2@example.test` | Active | `Password_123` |
| Nico Rivera | `nico.rivera.gym2@example.test` | Active | `Password_123` |

### Athletes — inactive or pending

| Name | Email | Membership status | Password |
|---|---|---|---|
| Mika Salcedo | `mika.salcedo.gym2@example.test` | Awaiting payment | `Password_123` |
| Leo Valdez | `leo.valdez.gym2@example.test` | Frozen | `Password_123` |
| Julia Pascual | `julia.pascual.gym2@example.test` | Expired | `Password_123` |
| Tomas Estrada | `tomas.estrada.gym2@example.test` | Cancelled | `Password_123` |
| Clara Domingo | `clara.domingo.gym2@example.test` | Application pending (no active gym link) | `Password_123` |
| Enzo Aguilar | `enzo.aguilar.gym2@example.test` | Application rejected (no active gym link) | `Password_123` |

---

## Gym 3 — Bakunawa Martial Arts Center

**Location:** Cebu City, Cebu  
**Owner:** Carlo Mendoza — `carlo.mendoza@bakunawama.ph` — `Password_123`  
**Counts:** 5 staff, 15 linked members, 1 pending application, 1 rejected application

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
| Camille Navarro | `camille.navarro@gmail.com` | Active | `Password_123` |
| Jasmine Co | `jasmine.co@gmail.com` | Active | `Password_123` |
| Nicole Enriquez | `nicole.enriquez@gmail.com` | Active | `Password_123` |
| Elena Dela Cruz | `elena.dela.cruz.gym3@example.test` | Active | `Password_123` |
| Rafael Santos | `rafael.santos.gym3@example.test` | Active | `Password_123` |
| Isabel Garcia | `isabel.garcia.gym3@example.test` | Active | `Password_123` |
| Marco Torres | `marco.torres.gym3@example.test` | Active | `Password_123` |
| Diana Flores | `diana.flores.gym3@example.test` | Active | `Password_123` |
| Adrian Castillo | `adrian.castillo.gym3@example.test` | Active | `Password_123` |
| Beatriz Ramos | `beatriz.ramos.gym3@example.test` | Active | `Password_123` |
| Nico Rivera | `nico.rivera.gym3@example.test` | Active | `Password_123` |

### Athletes — inactive or pending

| Name | Email | Membership status | Password |
|---|---|---|---|
| Mika Salcedo | `mika.salcedo.gym3@example.test` | Awaiting payment | `Password_123` |
| Leo Valdez | `leo.valdez.gym3@example.test` | Frozen | `Password_123` |
| Julia Pascual | `julia.pascual.gym3@example.test` | Expired | `Password_123` |
| Tomas Estrada | `tomas.estrada.gym3@example.test` | Cancelled | `Password_123` |
| Clara Domingo | `clara.domingo.gym3@example.test` | Application pending (no active gym link) | `Password_123` |
| Enzo Aguilar | `enzo.aguilar.gym3@example.test` | Application rejected (no active gym link) | `Password_123` |

---

## Gym 4 — Cordillera Fight Camp

**Location:** Baguio City, Benguet  
**Owner:** Maria Bautista — `maria.bautista@cordillerafightcamp.ph` — `Password_123`  
**Counts:** 4 staff, 15 linked members, 1 pending application, 1 rejected application

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
| Patricia Gomez | `patricia.gomez@gmail.com` | Active | `Password_123` |
| Miguel Soriano | `miguel.soriano@gmail.com` | Active | `Password_123` |
| Anthony Padilla | `anthony.padilla@gmail.com` | Active | `Password_123` |
| Elena Dela Cruz | `elena.dela.cruz.gym4@example.test` | Active | `Password_123` |
| Rafael Santos | `rafael.santos.gym4@example.test` | Active | `Password_123` |
| Isabel Garcia | `isabel.garcia.gym4@example.test` | Active | `Password_123` |
| Marco Torres | `marco.torres.gym4@example.test` | Active | `Password_123` |
| Diana Flores | `diana.flores.gym4@example.test` | Active | `Password_123` |
| Adrian Castillo | `adrian.castillo.gym4@example.test` | Active | `Password_123` |
| Beatriz Ramos | `beatriz.ramos.gym4@example.test` | Active | `Password_123` |
| Nico Rivera | `nico.rivera.gym4@example.test` | Active | `Password_123` |

### Athletes — inactive or pending

| Name | Email | Membership status | Password |
|---|---|---|---|
| Mika Salcedo | `mika.salcedo.gym4@example.test` | Awaiting payment | `Password_123` |
| Leo Valdez | `leo.valdez.gym4@example.test` | Frozen | `Password_123` |
| Julia Pascual | `julia.pascual.gym4@example.test` | Expired | `Password_123` |
| Tomas Estrada | `tomas.estrada.gym4@example.test` | Cancelled | `Password_123` |
| Clara Domingo | `clara.domingo.gym4@example.test` | Application pending (no active gym link) | `Password_123` |
| Enzo Aguilar | `enzo.aguilar.gym4@example.test` | Application rejected (no active gym link) | `Password_123` |

---

## Gym 5 — Davao Grappling Club

**Location:** Davao City, Davao del Sur  
**Owner:** Paolo Reyes — `paolo.reyes@davaograppling.ph` — `Password_123`  
**Counts:** 5 staff, 16 linked members, 1 pending application, 1 rejected application

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
| Gabriel Tan | `gabriel.tan@gmail.com` | Active | `Password_123` |
| Daniel Villamor | `daniel.villamor@gmail.com` | Active | `Password_123` |
| Jasmine Co | `jasmine.co@gmail.com` | Active | `Password_123` |
| Anthony Padilla | `anthony.padilla@gmail.com` | Active | `Password_123` |
| Elena Dela Cruz | `elena.dela.cruz.gym5@example.test` | Active | `Password_123` |
| Rafael Santos | `rafael.santos.gym5@example.test` | Active | `Password_123` |
| Isabel Garcia | `isabel.garcia.gym5@example.test` | Active | `Password_123` |
| Marco Torres | `marco.torres.gym5@example.test` | Active | `Password_123` |
| Diana Flores | `diana.flores.gym5@example.test` | Active | `Password_123` |
| Adrian Castillo | `adrian.castillo.gym5@example.test` | Active | `Password_123` |
| Beatriz Ramos | `beatriz.ramos.gym5@example.test` | Active | `Password_123` |
| Nico Rivera | `nico.rivera.gym5@example.test` | Active | `Password_123` |

### Athletes — inactive or pending

| Name | Email | Membership status | Password |
|---|---|---|---|
| Mika Salcedo | `mika.salcedo.gym5@example.test` | Awaiting payment | `Password_123` |
| Leo Valdez | `leo.valdez.gym5@example.test` | Frozen | `Password_123` |
| Julia Pascual | `julia.pascual.gym5@example.test` | Expired | `Password_123` |
| Tomas Estrada | `tomas.estrada.gym5@example.test` | Cancelled | `Password_123` |
| Clara Domingo | `clara.domingo.gym5@example.test` | Application pending (no active gym link) | `Password_123` |
| Enzo Aguilar | `enzo.aguilar.gym5@example.test` | Application rejected (no active gym link) | `Password_123` |

---

## Cross-gym accounts

These members belong to more than one center. Signing in once gives access to all listed centers.

| Name | Email | Centers | Role | Password |
|---|---|---|---|---|
| Sofia Ramirez | `sofia.ramirez@gmail.com` | Iron Will Boxing Gym + Kalaw Combat Academy | Athlete / Member | `Password_123` |
| Patricia Gomez | `patricia.gomez@gmail.com` | Iron Will Boxing Gym + Cordillera Fight Camp | Athlete / Member | `Password_123` |
| Miguel Soriano | `miguel.soriano@gmail.com` | Iron Will Boxing Gym + Cordillera Fight Camp | Athlete / Member | `Password_123` |
| Alyssa Mercado | `alyssa.mercado@gmail.com` | Iron Will Boxing Gym + Kalaw Combat Academy | Athlete / Member | `Password_123` |
| Gabriel Tan | `gabriel.tan@gmail.com` | Kalaw Combat Academy + Davao Grappling Club | Athlete / Member | `Password_123` |
| Daniel Villamor | `daniel.villamor@gmail.com` | Kalaw Combat Academy + Davao Grappling Club | Athlete / Member | `Password_123` |
| Anthony Padilla | `anthony.padilla@gmail.com` | Cordillera Fight Camp + Davao Grappling Club | Athlete / Member | `Password_123` |
| Jasmine Co | `jasmine.co@gmail.com` | Kalaw Combat Academy + Bakunawa Martial Arts Center + Davao Grappling Club | Athlete / Member | `Password_123` |

## Account counts

| Category | Count |
|---|---|
| Platform Administrator | 1 |
| Training Center Owners | 5 |
| Staff / employees | 26 |
| Member accounts (73 linked + 9 pending/rejected) | 82 |
| Pending center owner applicants | 5 |
| **Total** | **119** |

Staff by gym: Iron Will 6, Kalaw 6, Bakunawa 5, Cordillera 4, Davao 5.

## Notes

- All passwords were checked against the current database hashes; every account matches `Password_123`.
- "Active", "Awaiting payment", "Frozen", "Expired", and "Cancelled" are membership statuses, not account statuses.
- Clara Domingo and Enzo Aguilar have no active center link because their membership applications are still pending or were rejected. They can still sign in and view their application.
- `CaseCheck.20261106@Example.com` was created during testing, not by `DatabaseSeeder`. Reseeding removes it.
- Staff accounts are created by the owner through the app in normal use; these demo staff accounts are already verified and active.
