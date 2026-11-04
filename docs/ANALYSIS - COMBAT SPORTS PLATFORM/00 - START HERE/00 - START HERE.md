# 00 - START HERE

Next: [01 - OVERVIEW](../01%20-%20OVERVIEW/01%20-%20OVERVIEW.md)

**What this is about:** Combat Sports Platform, the "Web-Based Integrated Management Platform for Combat Sports Training Centers in Cavite"
**Depth:** Deep
**Written:** 3 November 2026
**Last updated:** 3 November 2026 (Phase 1 built)

> **Security warning.** The old code folder contains secrets in plain sight:
>
> - `backend/.env` holds a real app secret key, a Gmail email password and PayMongo test secret keys.
> - The loose scripts in `backend/` (for example `check_login.php`, `fix_password.php` and `verify_system.php`) hold real personal email addresses, plain-text passwords and store login details.
>
> The values are not repeated anywhere in these notes. Change that email password and those keys, and avoid sharing the `docs/existing-source-code` folder as it is.

## What you told me

| What was asked | What you said | When |
|---|---|---|
| In the first message | Analyse `docs/documentation`, `docs/existing-database` and `docs/existing-source-code` to learn the existing process. The real objective is to re-create it into a correct process from scratch. | 3 November 2026 |
| Where to save the notes | `docs/analysis/ANALYSIS - COMBAT SPORTS PLATFORM`; later moved by you to `docs/ANALYSIS - COMBAT SPORTS PLATFORM` | 3 November 2026 |
| How the roadmap marks features the old system already has | Rebuild everything: every item is ⭕ Not started, with the old system named as reference only | 3 November 2026 |
| Whether to include `06 - SCREENS BY ROLE` | Yes | 3 November 2026 |
| What to do with old features the spec never mentions | Keep the ones that fit inside a spec module as roadmap items. Make the rest open questions. | 3 November 2026 |
| Which wins when the spec and old code disagree | The spec wins. Disagreements are still recorded. | 3 November 2026 |
| Which technology the rebuild uses | Laravel, MySQL and coss UI. The idea first reached one of my reading helpers, and you confirmed it when asked. | 3 November 2026 |
| Which staff access model the rebuild follows | The spec's model: one employee role; the owner grants permissions per module; job titles are only ready-made permission sets | 3 November 2026 |
| Where the rebuild code lives | `backend/` and `frontend/` in the project folder | 3 November 2026 |
| Which database server is used for development | MySQL, through Laragon (MySQL 8.4) | 3 November 2026 |
| Which kind of React app the frontend is | React with Vite, a single-page app | 3 November 2026 |
| Where the R-08 permission screen belongs | Move it to R-22 in Phase 3, with adding employees | 3 November 2026 |
| Your client's feedback, relayed in Taglish | Employee management and CRM looked missing; RBAC should be dynamic; Integrated Business Management should be split into separate modules (HR and the rest); PayMongo is missing from the documents; decision support is "the nearest combat sports training center" | 3 November 2026 |
| What "dynamic RBAC" means | Owner-made roles: each owner creates, names, edits and deletes roles and gives them to employees; the four sets become templates | 3 November 2026 |
| How decision support changes | Add a nearest-gym and best-match recommender for athletes; keep the owner dashboards | 3 November 2026 |
| Whether to split the business modules | Yes: HR and employee management, payroll, finance, CRM, inventory and procurement become six separate modules | 3 November 2026 |
| How far PayMongo goes | Core for every online fee. Use the PayMongo keys from the old source `.env` in the new one; they are for testing and the thesis only, and you gave permission | 3 November 2026 |
| Q-09 and Q-10: several gyms per person | Yes to both: one person may own, work at or be a member of several gyms | 3 November 2026 |
| Whether setting up the backend, frontend and store belongs in the phases | Add it: the Phase 1 implementation plan must start with it. It is not a roadmap item. | 3 November 2026 |

The old features judged to "fit inside a spec module" are:

- the first-sign-in password change;
- leave balances;
- the holiday calendar;
- the health questionnaire and waivers;
- PayMongo online payment;
- weight divisions in competition records;
- overtime;
- Philippine payroll deductions.

They are items R-06, R-26, R-27, R-32, R-35, R-38, R-57 and R-60 in [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md). This split was my proposal, and you accepted it.

## What was handed over

| What | Kind | Where it came from | Read? |
|---|---|---|---|
| `Combat_Sports_Platform_Features_and_Modules.pdf` | Document, 20 pages | `docs/documentation/`, read 3 November 2026 | Yes, in full |
| `combat_sports_db.sql` | Stored information (a full copy of the old store) | `docs/existing-database/`, read 3 November 2026 | Yes, in full; personal details not copied into the notes |
| `laravel_app/backend` | Source code | `docs/existing-source-code/laravel_app/backend`, read 3 November 2026 | Yes. Every address list, job, permission check, data shape, setting, setup step and loose script. |
| `laravel_app/frontend` | Source code | `docs/existing-source-code/laravel_app/frontend`, read 3 November 2026 | Yes. Every screen, part and helper. |
| `backend/vendor`, `node_modules` | Ready-made parts by others | as above | No: not part of this system's own work |
| `frontend/dist` | The generated copy of the screens | as above | Only checked to see that it matches the source |
| `backend/storage`, `backend/public/storage` | Uploaded files: IDs, documents, photos | as above | No: personal files, not needed to understand the process |
| `backend/database/database.sqlite` | A small test store | as above | No: the SQL file is the fuller copy |
| `laravel_app.rar` | Packed archive | `docs/existing-source-code/` | No: not opened, so its contents were not checked; its name matches the unpacked folder |

The material adds up to three views of one system:

- the spec says what it **must** do;
- the code shows what was **actually** built;
- the stored information shows what the build **left behind**.

Coverage of the system's own work is complete. The material was **not** copied into a `MATERIAL` folder, because it is large and includes personal files. Citations point at the original files, and line numbers are as they were on 3 November 2026. Wherever a note says `backend/...` or `frontend/...`, the path sits inside `docs/existing-source-code/laravel_app/`.

## The short version

The spec describes a multi-gym platform for combat sports gyms in Cavite. Gyms apply and are checked by a platform administrator. Each approved gym then runs everything in its own private workspace: members, classes, bookings, attendance, staff and payroll, money, customers, stock, purchasing and decision-support reports. It all works in a browser and in a Median.co phone app.

The old system built roughly half of this. It has detailed Philippine payroll and leave. It has nothing for waiting lists, QR check-in, coach availability, rank and competitions, customer follow-up, stock, purchasing, expenses and budgets. It also has serious safety faults: an owner can make someone a platform administrator; every staff member counts as HR; private IDs and medical files sit in a public folder; and bookings are marked "paid" without any payment.

The most important thing to know: **the rebuild must put sign-in, permissions and gym separation first and get them right.** That is where the old system failed most, and everything else leans on it.

## Everything in this analysis

| File | What it holds |
|---|---|
| [01 - OVERVIEW](../01%20-%20OVERVIEW/01%20-%20OVERVIEW.md) | What the platform is, who uses it, what it does, what state it is in |
| [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md) | Every spec requirement (D-01 to D-81) and your client's additions (D-82 to D-85), what the old code actually does, promised against built, and 25 contradictions (C-01 to C-25) |
| [03 - SYSTEM ARCHITECTURE](../03%20-%20SYSTEM%20ARCHITECTURE/03%20-%20SYSTEM%20ARCHITECTURE.md) | How the old system is put together, and the arrangement proposed for the rebuild |
| [04 - DIAGRAMS](../04%20-%20DIAGRAMS/04%20-%20DIAGRAMS.md) | Eleven pictures: old journeys, the spec's life stories, the phase order, the proposed arrangement |
| [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md) | The whole rebuild plan at a glance, with a link to each phase |
| [PHASE 1](../05%20-%20ROADMAP/phase-1/PHASE%201.md) | People can sign up, prove who they are, and be recognised safely |
| [PHASE 2](../05%20-%20ROADMAP/phase-2/PHASE%202.md) | Gyms can apply, be checked, and go live |
| [PHASE 3](../05%20-%20ROADMAP/phase-3/PHASE%203.md) | Each approved gym runs its own workspace, staff and offer |
| [PHASE 4](../05%20-%20ROADMAP/phase-4/PHASE%204.md) | Athletes can join a gym and have a complete record |
| [PHASE 5](../05%20-%20ROADMAP/phase-5/PHASE%205.md) | Sessions are scheduled without clashes, booked fairly, and attended |
| [PHASE 6](../05%20-%20ROADMAP/phase-6/PHASE%206.md) | The business side runs in one place, fed by training |
| [PHASE 7](../05%20-%20ROADMAP/phase-7/PHASE%207.md) | Everyone stays informed, on their phone too |
| [PHASE 8](../05%20-%20ROADMAP/phase-8/PHASE%208.md) | Owners decide with data |
| [06 - SCREENS BY ROLE](../06%20-%20SCREENS%20BY%20ROLE/06%20-%20SCREENS%20BY%20ROLE.md) | What each kind of user sees, in the old system and proposed for the rebuild |
| [07 - WORD LIST](../07%20-%20WORD%20LIST/07%20-%20WORD%20LIST.md) | Plain meanings for the names that could not be avoided |

Every note in the set was made this run.

## Status legend

| Emoji | Means |
|---|---|
| ✅ | Finished: built, checked, working |
| 🟨 | Being worked on |
| ⭕ | Not started: waiting its turn |
| ❌ | Blocked: something is stopping it; the tracker says what |
| 🔵 | Already there: found in the supplied material, built before this plan |
| ⬜ | Dropped: decided against, kept for the record |
| ❓ | Unclear: the material does not say |

## Open questions

Each one is a real gap, not a guess dressed up as a question. When a question is answered, it is marked ✅, the answer and its date go in the *Status* cell, and it is added to *What you told me*. It is never deleted.

| # | Question | Why it matters | Who can answer | Status |
|---|---|---|---|---|
| Q-01 | Keep the old **leave-to-cash conversion**, where unused leave is paid out? | Adds a payroll line and approval flow (R-26, R-59) | You | ⭕ |
| Q-02 | Keep **absence explanations**, where staff explain an automatic absence with an attachment? | Adds a review flow to staff attendance (R-56) | You | ⭕ |
| Q-03 | Keep **GPS-fenced staff check-in**, which only counts within 150 metres of the gym? | Changes how staff attendance is recorded (R-56). The old version could be faked. | You | ⭕ |
| Q-04 | Should the platform administrator also **check each member's government ID** before they can use the platform, as the old system did? | The spec does not ask for it (C-01). It adds an approval step and private ID storage. | You | ⭕ |
| Q-05 | Keep the automatic **health risk score** worked out from the membership questionnaire? | Affects what staff see on applications (R-32) | You | ⭕ |
| Q-06 | Keep **member reviews of gyms**? | Would add a public rating to gym pages | You | ⭕ |
| Q-07 | Keep the **newsletter sign-up** and the **public contact form**? | Would add public screens and an email list | You | ⭕ |
| Q-08 | Carry any **old stored information** into the new system, or only reference lists (leave types, payroll rates, holidays)? | Moving old records would need cleaning, because the old store has many broken links | You | ⭕ |
| Q-09 | Can one owner run **more than one gym**? | Shapes roles, gym separation and the owner application (R-07, R-09, R-11) | You | ✅ Yes, an owner may run several gyms. Answered 3 November 2026. |
| Q-10 | Can one athlete or employee belong to **more than one gym** at once? | Shapes roles, membership and staff records (R-07, R-09, R-22, R-31). The old store blocked staff at two gyms. | You | ✅ Yes, a person may belong to several gyms at once. Answered 3 November 2026. |
| Q-11 | For **QR check-in**, does the member show a code for staff to scan, or scan a code posted at the gym? | Decides what R-52 builds and what the front desk needs | You | ⭕ |
| Q-12 | When a place frees up, is it **given automatically** to the next on the waiting list, or **offered with a time limit**? | Decides how R-50 works | You | ⭕ |
| Q-13 | How are **coaches paid**: monthly salary, per session, or both? | The old system never solved this and left coaches out of payroll (R-23, R-59) | You | ⭕ |
| Q-14 | What counts as **late**, for staff and for athletes? | Needed for attendance records (R-54, R-56) | You | ⭕ |
| Q-15 | Which **forecasting method** should decision support use? | Needed for R-94 and R-96. The spec names no method. | You, or the spec's author | ⭕ |
| Q-16 | Can a Tier 1 gym later **move up to Tier 2**, and does its badge change? | Affects the application's life story (R-12, R-18) | The spec's author | ⭕ |
| Q-17 | Do verification documents **expire** (for example the yearly business permit), and must gyms check again? | Would add renewals to the gym's life (R-17) | The spec's author | ⭕ |
| Q-18 | How does a gym set its **booking-confirmation policy**: on or off for all bookings, or per session type? | Decides how R-49 works | You | ⭕ |
| Q-19 | Which **leave types and yearly allowances** apply? Keep the old Philippine list? | Needed for R-25 and R-26 | You | ⭕ |
| Q-20 | What is the **refund rule** when the gym cancels a session someone paid extra for? | Needed for R-45 | You | ⭕ |
| Q-21 | What are a gym's **working days** for absences and payroll? Is Saturday a working day? | The old system disagreed with itself (C-22). Needed for R-20, R-24, R-56 and R-59. | You | ⭕ |
| Q-22 | Should **online payment** also cover session add-ons, or only memberships? | Decides the reach of R-35 and R-51 | You | ✅ Every fee: memberships, registration, session add-ons, private coaching and other fees. Answered 3 November 2026. |
| Q-23 | What are **platform notices** for, and who receives them: owners only, or all users? | The spec only names them (page 7). Needed for R-81. | The spec's author | ⭕ |
| Q-24 | Can athletes **cancel their own bookings**, and until when before the session? | Affects R-48 and the booking's life story | You | ⭕ |

## Changed

| Date | What prompted it | What it touched |
|---|---|---|
| 4 November 2026 | Progress: Phase 2 built (R-11 to R-19). Gyms can apply with a tier and its documents, the administrator reviews with a checklist and approves, rejects or returns, and approved gyms show their tier badge. Q-16 and Q-17 are still open: built with no tier upgrade and no document expiry. | [PHASE 2](../05%20-%20ROADMAP/phase-2/PHASE%202.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md), this note |
| 3 November 2026 | Progress: R-08 rebuilt as owner-made roles and finished, so Phase 1 is finished. | [PHASE 1](../05%20-%20ROADMAP/phase-1/PHASE%201.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md) |
| 3 November 2026 | Your client's feedback. Added D-82 to D-85 and C-24, C-25. R-08 reopened as owner-made roles. Business work split into six modules (5A to 5F). PayMongo made core for every fee (R-35); Q-22 answered. Nearest-gym and best-match recommender added (R-97, R-98). PayMongo test keys copied into `backend/.env`. | [01 - OVERVIEW](../01%20-%20OVERVIEW/01%20-%20OVERVIEW.md), [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md), [03 - SYSTEM ARCHITECTURE](../03%20-%20SYSTEM%20ARCHITECTURE/03%20-%20SYSTEM%20ARCHITECTURE.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md) and phases 1, 3, 4, 5, 6, [06 - SCREENS BY ROLE](../06%20-%20SCREENS%20BY%20ROLE/06%20-%20SCREENS%20BY%20ROLE.md), [07 - WORD LIST](../07%20-%20WORD%20LIST/07%20-%20WORD%20LIST.md), this note |
| 3 November 2026 | Correction: the R-08 permission screen moved to R-22 in Phase 3, where employees are added. R-08 is now finished, and so is Phase 1. | [PHASE 1](../05%20-%20ROADMAP/phase-1/PHASE%201.md), [PHASE 3](../05%20-%20ROADMAP/phase-3/PHASE%203.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md) |
| 3 November 2026 | Progress: Phase 1 built. Setup done (Laravel 13, Laragon MySQL 8.4, React + Vite + coss UI). R-01 to R-07, R-09 and R-10 finished; R-08 works on the backend and waits for its screen in Phase 3. Q-09 and Q-10 answered. | [PHASE 1](../05%20-%20ROADMAP/phase-1/PHASE%201.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md), this note |
| 3 November 2026 | You asked whether setting up the backend, frontend and store is in the phases. Added a note that the Phase 1 implementation plan starts with setup, which carries no `R-` number. | [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md): *Deliberately left out* and *Implementation handoff*; this note's *What you told me* |
