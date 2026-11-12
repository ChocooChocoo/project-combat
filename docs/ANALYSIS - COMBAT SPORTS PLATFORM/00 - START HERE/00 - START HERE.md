# 00 - START HERE

Next: [01 - OVERVIEW](../01%20-%20OVERVIEW/01%20-%20OVERVIEW.md)

**What this is about:** Combat Sports Platform, the "Web-Based Integrated Management Platform for Combat Sports Training Centers in Cavite"
**Depth:** Deep
**Written:** 3 November 2026
**Last updated:** 9 November 2026 (Phases 9 and 10 finished; all 107 roadmap items done)

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
| R-33: must the fee be paid before a membership is active? | Approve first, then the membership becomes active once the fee is fully paid | 4 November 2026 |
| R-36: how freezing and cancelling follow the plan's policy | Each plan gets a "most days it can be frozen" limit and a "refund on cancel" yes or no | 4 November 2026 |
| R-37: do gyms define rank ladders? | No: ranks are typed freely per discipline | 4 November 2026 |
| R-34: keep the old "front desk passes money to finance" chain? | No: each payment is recorded once by a permitted staff member | 4 November 2026 |
| Whether setting up the backend, frontend and store belongs in the phases | Add it: the Phase 1 implementation plan must start with it. It is not a roadmap item. | 3 November 2026 |
| Q-11: QR check-in | The member shows a code from the app that changes every minute; staff scan it or type its short code | 5 November 2026 |
| Q-12: freed places on a waiting list | Given automatically to the next athlete in line, who is notified | 5 November 2026 |
| Q-14: what counts as late | For athletes: checking in after the start time plus a grace period the owner sets (default 10 minutes) | 5 November 2026 |
| Q-18: booking-confirmation policy | One gym-wide on/off switch, which each session can override | 5 November 2026 |
| Q-20: refund when the gym cancels a paid session | A full refund entry is recorded against each payment; staff hand the money back | 5 November 2026 |
| Q-24: athletes cancelling their own bookings | Yes, until a cut-off the owner sets (default 2 hours before the start); after that only staff | 5 November 2026 |
| Q-15: decision-support forecast | Straight-line least-squares on up to 12 completed months, next 3 months, R² confidence (fit) shown; fewer than 3 months uses "average, not enough history". Incoming and outgoing clamp at zero; net may be negative. | 7 November 2026 |
| R-91 historical outstanding balances | "Accept current snapshot (Recommended)". Revenue and income/expense use selected dates; balance label is "Outstanding balances as of today; not a historical balance". No historical debt reconstruction required. | 7 November 2026 |
| R-87 coach grouping | "Accept attendance-based coach (Recommended)". Label "coach based on attendance" and visibly explain that context is inferred, not causal. | 7 November 2026 |
| Your client's later requests (`docs/prompts/P05.md`) | Add staff attendance against owner schedules with at least 10 minutes' grace, attendance on payroll, a role-filtered sidebar, and admin-made subscription plans with a free trial | 9 November 2026 |
| Staff grace period: fixed or owner-set? | The owner sets it, with 10 minutes as the minimum and the default | 9 November 2026 |
| How lateness and absence affect pay | Keep the current rules (late minutes deducted at the minute rate, absent days unpaid); only add present, late and absent day counts | 9 November 2026 |
| The "current issue" with roles and permissions | Nothing extra; record the role-filtered sidebar as already there | 9 November 2026 |
| What an owner loses when the trial ends unsubscribed | The gym drops to the free plan; only the modules that plan allows stay usable | 9 November 2026 |
| Is "Free" a permanent plan or the trial? | A permanent free plan (named Starter) that gyms fall back to; the trial is separate | 9 November 2026 |
| Trial length | The administrator sets it; it starts at 30 days | 9 November 2026 |
| How owners pay for a plan | Through PayMongo, like other online fees (R-35) | 9 November 2026 |
| Suggested plan names | Starter, Pro, Elite | 9 November 2026 |
| Q-25 to Q-28: trial contents and start, lapse, billing | The trial opens every module and starts at approval; an unpaid month drops to Starter at once; plans are monthly only | 9 November 2026 |

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

The first eight phases of the rebuild are built. On 9 November 2026 your client asked for more: a minimum 10-minute staff grace, attendance counts on payroll, a role-filtered sidebar (already built), and subscription plans for gyms with a free trial. These are Phases 9 and 10. Both are built and checked in a browser.

## Everything in this analysis

| File | What it holds |
|---|---|
| [01 - OVERVIEW](../01%20-%20OVERVIEW/01%20-%20OVERVIEW.md) | What the platform is, who uses it, what it does, what state it is in |
| [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md) | Every spec requirement (D-01 to D-81) and your client's additions (D-82 to D-93), what the old code actually does, promised against built, and 25 contradictions (C-01 to C-25) |
| [03 - SYSTEM ARCHITECTURE](../03%20-%20SYSTEM%20ARCHITECTURE/03%20-%20SYSTEM%20ARCHITECTURE.md) | How the old system is put together, and the arrangement proposed for the rebuild |
| [04 - DIAGRAMS](../04%20-%20DIAGRAMS/04%20-%20DIAGRAMS.md) | Twelve pictures: old journeys, the spec's life stories, the phase order, the proposed arrangement, and a gym subscription's life story |
| [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md) | The whole rebuild plan at a glance, with a link to each phase |
| [PHASE 1](../05%20-%20ROADMAP/phase-1/PHASE%201.md) | People can sign up, prove who they are, and be recognised safely |
| [PHASE 2](../05%20-%20ROADMAP/phase-2/PHASE%202.md) | Gyms can apply, be checked, and go live |
| [PHASE 3](../05%20-%20ROADMAP/phase-3/PHASE%203.md) | Each approved gym runs its own workspace, staff and offer |
| [PHASE 4](../05%20-%20ROADMAP/phase-4/PHASE%204.md) | Athletes can join a gym and have a complete record |
| [PHASE 5](../05%20-%20ROADMAP/phase-5/PHASE%205.md) | Sessions are scheduled without clashes, booked fairly, and attended |
| [PHASE 6](../05%20-%20ROADMAP/phase-6/PHASE%206.md) | The business side runs in one place, fed by training |
| [PHASE 7](../05%20-%20ROADMAP/phase-7/PHASE%207.md) | Everyone stays informed, on their phone too |
| [PHASE 8](../05%20-%20ROADMAP/phase-8/PHASE%208.md) | Owners decide with data |
| [PHASE 9](../05%20-%20ROADMAP/phase-9/PHASE%209.md) | Staff time and access rules are tightened |
| [PHASE 10](../05%20-%20ROADMAP/phase-10/PHASE%2010.md) | Gyms subscribe to the platform |
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
| Q-01 | Keep the old **leave-to-cash conversion**, where unused leave is paid out? | Adds a payroll line and approval flow (R-26, R-59) | You | ✅ Drop for Phase 6. Unused leave conversion is not in R-56 to R-79 and is left for later. Answered 5 November 2026. |
| Q-02 | Keep **absence explanations**, where staff explain an automatic absence with an attachment? | Adds a review flow to staff attendance (R-56) | You | ✅ Keep as a plain text explanation on the employee's own attendance row, with no approval step and no effect on pay. Answered 5 November 2026. |
| Q-03 | Keep **GPS-fenced staff check-in**, which only counts within 150 metres of the gym? | Changes how staff attendance is recorded (R-56). The old version could be faked. | You | ✅ Drop. No GPS-fenced check-in; audited desk corrections replace it. Answered 5 November 2026. |
| Q-04 | Should the platform administrator also **check each member's government ID** before they can use the platform, as the old system did? | The spec does not ask for it (C-01). It adds an approval step and private ID storage. | You | ⭕ |
| Q-05 | Keep the automatic **health risk score** worked out from the membership questionnaire? | Affects what staff see on applications (R-32) | You | ⭕ |
| Q-06 | Keep **member reviews of gyms**? | Would add a public rating to gym pages | You | ⭕ |
| Q-07 | Keep the **newsletter sign-up** and the **public contact form**? | Would add public screens and an email list | You | ⭕ |
| Q-08 | Carry any **old stored information** into the new system, or only reference lists (leave types, payroll rates, holidays)? | Moving old records would need cleaning, because the old store has many broken links | You | ⭕ |
| Q-09 | Can one owner run **more than one gym**? | Shapes roles, gym separation and the owner application (R-07, R-09, R-11) | You | ✅ Yes, an owner may run several gyms. Answered 3 November 2026. |
| Q-10 | Can one athlete or employee belong to **more than one gym** at once? | Shapes roles, membership and staff records (R-07, R-09, R-22, R-31). The old store blocked staff at two gyms. | You | ✅ Yes, a person may belong to several gyms at once. Answered 3 November 2026. |
| Q-11 | For **QR check-in**, does the member show a code for staff to scan, or scan a code posted at the gym? | Decides what R-52 builds and what the front desk needs | You | ✅ The member shows a code from the app that changes every minute; staff scan it or type its short code. Answered 5 November 2026. |
| Q-12 | When a place frees up, is it **given automatically** to the next on the waiting list, or **offered with a time limit**? | Decides how R-50 works | You | ✅ Given automatically to the next athlete in line, who is notified. Answered 5 November 2026. |
| Q-13 | How are **coaches paid**: monthly salary, per session, or both? | The old system never solved this and left coaches out of payroll (R-23, R-59) | You | ✅ Basis + rate: a monthly rate, a per-session rate, or both. Answered 4 November 2026.
| Q-14 | What counts as **late**, for staff and for athletes? | Needed for attendance records (R-54, R-56) | You | ✅ For athletes: late means checking in after the start time plus a grace period the owner sets (default 10 minutes). For staff: late when clock-in passes the scheduled start plus the gym grace (default 10 minutes), with late minutes counted from the scheduled start; undertime is deducted. Answered 5 November 2026. |
| Q-15 | Which **forecasting method** should decision support use? | Needed for R-94 and R-96. The spec names no method. | You, or the spec's author | ✅ Straight-line least-squares, up to 12 completed history months and 3 projected months; R² fit shown, not probability. Below 3 observed months use "average, not enough history"; no history means no projections. Clamp incoming/outgoing at zero, allow negative net. Answered 7 November 2026. |
| Q-16 | Can a Tier 1 gym later **move up to Tier 2**, and does its badge change? | Affects the application's life story (R-12, R-18) | The spec's author | ⭕ |
| Q-17 | Do verification documents **expire** (for example the yearly business permit), and must gyms check again? | Would add renewals to the gym's life (R-17) | The spec's author | ⭕ |
| Q-18 | How does a gym set its **booking-confirmation policy**: on or off for all bookings, or per session type? | Decides how R-49 works | You | ✅ One gym-wide on/off switch, which each session can override. Answered 5 November 2026. |
| Q-19 | Which **leave types and yearly allowances** apply? Keep the old Philippine list? | Needed for R-25 and R-26 | You | ✅ Keep the old Philippine list: vacation 15, sick 15, service incentive 5, solo parent 7, bereavement 3, emergency 3, maternity 105, paternity 7, special leave for women 60, VAWC 10. Answered 4 November 2026.
| Q-20 | What is the **refund rule** when the gym cancels a session someone paid extra for? | Needed for R-45 | You | ✅ A full refund entry is recorded against each payment; staff hand the money back. Answered 5 November 2026. |
| Q-21 | What are a gym's **working days** for absences and payroll? Is Saturday a working day? | The old system disagreed with itself (C-22). Needed for R-20, R-24, R-56 and R-59. | You | ✅ Monday to Friday by default; the owner can change the gym working days. Answered 4 November 2026.
| Q-22 | Should **online payment** also cover session add-ons, or only memberships? | Decides the reach of R-35 and R-51 | You | ✅ Every fee: memberships, registration, session add-ons, private coaching and other fees. Answered 3 November 2026. |
| Q-23 | What are **platform notices** for, and who receives them: owners only, or all users? | The spec only names them (page 7). Needed for R-81. | You | ✅ Platform-wide updates such as maintenance notices. Administrator chooses owners of approved gyms (default, deduplicated) or all verified users. Sender excluded; no gym data. Answered 6 November 2026. |
| Q-24 | Can athletes **cancel their own bookings**, and until when before the session? | Affects R-48 and the booking's life story | You | ✅ Yes, until a cut-off the owner sets (default 2 hours before the start); after that only staff. Answered 5 November 2026. |
| Q-25 | What does a gym get **during the free trial**: every module, or one chosen plan's modules? | Decides what R-104 switches on | You | ✅ Every module. Answered 9 November 2026. |
| Q-26 | Does the trial **start** when the owner applies, or when the gym is approved (R-18)? | An application can wait days for review; decides R-104's start date | You | ✅ At approval. Answered 9 November 2026. |
| Q-27 | When a **paid period ends** unpaid, does the gym get extra days, renew automatically, or drop to Starter at once? | Decides when R-106 locks modules | You | ✅ Drop to Starter at once. Answered 9 November 2026. |
| Q-28 | Which **billing periods** do plans offer: monthly, yearly, or both? | Shapes plan prices (R-102) and payment (R-105) | You | ✅ Monthly only. Answered 9 November 2026. |
| Q-29 | Is the staff grace **fixed at 10 minutes** or set by each owner? | Decides R-99 | You | ✅ Owner sets it; minimum and default 10 minutes. Answered 9 November 2026. |
| Q-30 | How should **late arrivals and absences affect pay**? | Decides R-100 | You | ✅ Keep current rules: late minutes deducted at the minute rate, absent days unpaid. Answered 9 November 2026. |
| Q-31 | What happens when staff **clock in after the grace period**? | Decides R-99 | You | ✅ They can still clock in; they are marked late with minutes counted from the scheduled start (unchanged from Q-14). Confirmed 9 November 2026. |
| Q-32 | What is the **current issue with roles and permissions**? | Could add work to R-101 | You | ✅ Nothing extra; the role-filtered sidebar is already there. Answered 9 November 2026. |
| Q-33 | When a trial ends unsubscribed, are **all or only some** owner-side modules blocked? | Decides R-106 | You | ✅ Only those the free plan does not include. Answered 9 November 2026. |
| Q-34 | Is the **trial length** fixed or set by the administrator? | Decides R-104 | You | ✅ Set by the administrator; 30 days to start. Answered 9 November 2026. |
| Q-35 | Is **"Free"** a permanent plan or the trial? | Decides R-102 and R-106 | You | ✅ A permanent free plan (Starter); the trial is separate. Answered 9 November 2026. |
| Q-36 | **Who runs payroll**: HR, operations or the owner? | Decides who sees R-100 | The rebuild | ✅ The owner and anyone whose role holds the payroll permissions (`backend/config/permissions.php` in the rebuild). No fixed role. Checked 9 November 2026. |

## Changed

| Date | What prompted it | What it touched |
|---|---|---|
| 12 November 2026 | Progress: P09 built as Phase 11 (R-108 to R-120) from `docs/prompts/P09.md`: sidebar groups and last gym, prepay and price lock, promos and vouchers, date filters, purchase edits and receipt proof, per-gym reminders, inactivity and absence notices, coach applications, goals, injuries and equipment safety. Backend feature tests and the full suite, frontend build, lint and node tests run. Not run: browser check, MySQL migration. All 13 items are 🟨 until then. | [PHASE 11](../05%20-%20ROADMAP/phase-11/PHASE%2011.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md), [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md), [03 - SYSTEM ARCHITECTURE](../03%20-%20SYSTEM%20ARCHITECTURE/03%20-%20SYSTEM%20ARCHITECTURE.md), [04 - DIAGRAMS](../04%20-%20DIAGRAMS/04%20-%20DIAGRAMS.md), [06 - SCREENS BY ROLE](../06%20-%20SCREENS%20BY%20ROLE/06%20-%20SCREENS%20BY%20ROLE.md), [07 - WORD LIST](../07%20-%20WORD%20LIST/07%20-%20WORD%20LIST.md), this note |
| 9 November 2026 | Verification: new migrations applied to the development MySQL database (backup taken first). Browser-checked 9 November 2026 on a throwaway copy of the database: trial banner, My subscription, a real PayMongo GCash test payment for Pro (recorded once, month starts after the trial), free-plan lock (sidebar hides locked modules, Finance refused, banner shows), admin plan edit, and present/late/absent days on a computed pay line (1 / 2 / 9). Fixed during the check: plan module checkboxes now carry their own names for screen readers. Phases 9 and 10 are ✅; the roadmap is 106 finished plus 1 already there, of 107. | [PHASE 9](../05%20-%20ROADMAP/phase-9/PHASE%209.md), [PHASE 10](../05%20-%20ROADMAP/phase-10/PHASE%2010.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md), this note |
| 9 November 2026 | Progress: Phases 9 and 10 built. Staff grace can no longer go below 10 minutes; pay lines and payslips show present, late and absent days; R-101 confirmed. Platform plans (Starter, Pro, Elite), per-plan modules, an admin-set trial that starts at approval, PayMongo subscription payments, plan limits enforced in the one permission check, the owner's *My subscription* screen, a workspace notice and a daily reminder. Q-25 to Q-28 answered. Full backend suite 603 tests / 27,997 assertions pass; frontend build, lint (no errors) and node tests pass. Not run: browser check, real PayMongo test payment, MySQL migration. All 8 built items are 🟨 until then. | [PHASE 9](../05%20-%20ROADMAP/phase-9/PHASE%209.md), [PHASE 10](../05%20-%20ROADMAP/phase-10/PHASE%2010.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md), this note |
| 9 November 2026 | New material: your client's later requests in `docs/prompts/P05.md`, with your answers in conversation. Added D-86 to D-93. The rebuild was checked: staff check-in against schedules (R-56), late deductions in payroll (R-59) and the role-filtered sidebar already exist; the staff grace can be set to 0; payroll has no late-day count; there are no subscriptions. Added Phase 9 (R-99 grace of at least 10 minutes, R-100 present/late/absent days on payroll, R-101 sidebar 🔵) and Phase 10 (R-102 to R-107: subscription plans, plan modules, free trial, PayMongo subscription, limits after the trial, owner subscription screen). Open questions Q-25 to Q-28 added; Q-29 to Q-36 record P05's own questions as answered. Added screens S-45 and S-46, diagram 12 and three word-list entries. Roadmap now 98 finished, 1 already there, 8 not started, of 107. | [01 - OVERVIEW](../01%20-%20OVERVIEW/01%20-%20OVERVIEW.md), [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md), [03 - SYSTEM ARCHITECTURE](../03%20-%20SYSTEM%20ARCHITECTURE/03%20-%20SYSTEM%20ARCHITECTURE.md), [04 - DIAGRAMS](../04%20-%20DIAGRAMS/04%20-%20DIAGRAMS.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md), [PHASE 6](../05%20-%20ROADMAP/phase-6/PHASE%206.md), [PHASE 8](../05%20-%20ROADMAP/phase-8/PHASE%208.md), [PHASE 9](../05%20-%20ROADMAP/phase-9/PHASE%209.md), [PHASE 10](../05%20-%20ROADMAP/phase-10/PHASE%2010.md), [06 - SCREENS BY ROLE](../06%20-%20SCREENS%20BY%20ROLE/06%20-%20SCREENS%20BY%20ROLE.md), [07 - WORD LIST](../07%20-%20WORD%20LIST/07%20-%20WORD%20LIST.md), this note |
| 7 November 2026 | Phase 8 finished and independently accepted: all twelve decision-support views (R-85 to R-96) are built and verified, and the roadmap is **98 finished / 0 open**. Full backend 595 tests / 27,884 assertions pass; isolated throwaway MySQL fresh-seed/sync/stock check and all twelve controller paths pass under ONLY_FULL_GROUP_BY, then database dropped. Frontend Node/lint/build pass. Owner all twelve views at four widths and active reports checked; real decision-only all-view access, plain-employee denial, delegated payroll mask, pure payroll-only and front-desk denial, five-role matrix, keyboard Print/Retry, invalid/empty/stale/basis states, and own/owner payslip print regression all pass. A shared print visibility defect was fixed after reproducing six blank pages; the new stored fixture yields 9/9/12 nonblank attendance/athlete/sustainability pages. Independent validation records **14/14 frozen rows met**. Q-15 answered; user-approved current balance snapshot and inferred coach context documented. | [PHASE 8](../05%20-%20ROADMAP/phase-8/PHASE%208.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md), [S-36](../06%20-%20SCREENS%20BY%20ROLE/06%20-%20SCREENS%20BY%20ROLE.md), packet plans, this note |
| 7 November 2026 | Progress: Phase 7 built and browser-verified. Gyms can send announcements and events to all members, one class occurrence or one customer group (R-80); the platform administrator can send notices to owners of approved gyms or all verified users without gym data (R-81); session, membership-expiry and follow-up reminders run on their own schedules with no duplicates (R-82); notices and places left update on open screens through 15-second visibility-aware polling (R-83); and every athlete screen fits a phone for the Median.co wrapper, with the setup document and external install/store steps recorded (R-84). Final gates: 520 backend tests / 26,513 assertions; MySQL `migrate:fresh --seed` with 10 demo announcements, 1 platform notice and no jobs; permission sync and schedule list passed; frontend lint, build and 6 node tests passed; the four new migrations roll back and re-apply on a throwaway database. Browser checks covered all audiences, front-desk send, coach denial, expired-member exclusion, non-admin denial, owner and all-user notices, live notice in 4.8 seconds, live places-left in 11.2 seconds, and athlete screens at 360 px and 390 px with no sideways scrolling. Phase 7 is ✅; 86 items are finished and 12 remain in Phase 8. | [PHASE 7](../05%20-%20ROADMAP/phase-7/PHASE%207.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md), [06 - SCREENS BY ROLE](../06%20-%20SCREENS%20BY%20ROLE/06%20-%20SCREENS%20BY%20ROLE.md), this note |
| 6 November 2026 | Correction: account type is now chosen at sign-up and fixed (`member` or `owner`), replacing "one sign-up, roles later" for the owning/joining split (R-01, R-07). Owner accounts apply to run a gym and browse the member side only, with no joining, preferences or best matches; member accounts cannot apply to own a gym. Staff links are unaffected, and existing accounts were inferred as `owner` if they own a gym or have an application, otherwise `member`. | [PHASE 1](../05%20-%20ROADMAP/phase-1/PHASE%201.md), [ACCOUNTS.md](../../SETUPS/ACCOUNTS.md), this note |
| 6 November 2026 | Verification: Phase 6 browser matrix completed in Playwright at desktop and phone width. Owner, HR, Finance, Front Desk, Operations Manager, Coach, plain employee and member roles were exercised. CRM lead/conversion/interactions/follow-ups/groups, finance sales/expenses/receivables/balances, procurement restock→approval→order→receiving→supplier payment, payroll compute→submit→release with payslips and lock, and budgets/reports all passed. Three defects were found and fixed: negative payroll gross/net, missing payslip deduction/breakdown data, and CRM write controls shown to view-only users. Final suite 476 tests / 25,821 assertions; lint/build green; `app:check-stock-counts` clean. Phase 6 is ✅. | [PHASE 6](../05%20-%20ROADMAP/phase-6/PHASE%206.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md), [06 - SCREENS BY ROLE](../06%20-%20SCREENS%20BY%20ROLE/06%20-%20SCREENS%20BY%20ROLE.md), this note |
| 6 November 2026 | Progress: Phase 6 built and its automated close-out passed. All 24 requirements (R-56 to R-79) have code and tests: staff attendance, overtime and performance; payroll with statutory tables, approval chain and payslips; income, expenses, receivables, payables, budgets and reports; CRM leads, follow-ups and groups; stock; and procurement from restock to receiving. The workspace sidebar is grouped into People, Training, Members, Business and Setup, and every growing list is paginated. `php artisan test` passes 471 tests and 25,736 assertions; `pnpm lint` has no errors; `pnpm build` passes; a throwaway MySQL database migrated and seeded; `app:sync-permissions`, `app:check-stock-counts` and two runs of `app:mark-staff-absences` over 16–31 October 2026 all passed. **Browser verification is not run yet**, so Phase 6 stays 🟨 and R-69's due reminder still belongs to R-82. | [PHASE 6](../05%20-%20ROADMAP/phase-6/PHASE%206.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md), [06 - SCREENS BY ROLE](../06%20-%20SCREENS%20BY%20ROLE/06%20-%20SCREENS%20BY%20ROLE.md), this note |
| 5 November 2026 | Progress: Phase 6 Packet 6B inventory built (R-72 to R-74). Stock items keep quantity, category and storage place; every change is an append-only movement with an actor and time; the quantity cache can only move through `StockLedger`; a crossing to the minimum sends one low-stock notice to stock managers and the owner. Built with `php artisan test` 339 passing, frontend lint and build green, an isolated MySQL seed check, and a browser pass at phone width with owner, Front Desk, Operations Manager and Coach roles. | [PHASE 6](../05%20-%20ROADMAP/phase-6/PHASE%206.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md), this note |
| 5 November 2026 | Correction: Phase 4 is finished. The three checks that kept R-35, R-36 and R-41 at 🟨 could only pass once later phases exist, so they were moved to the items that build them: R-51, R-54 and R-55 (Phase 5), R-63 (Phase 6) and R-82 (Phase 7). Decided in conversation. | [PHASE 4](../05%20-%20ROADMAP/phase-4/PHASE%204.md), [PHASE 5](../05%20-%20ROADMAP/phase-5/PHASE%205.md), [PHASE 6](../05%20-%20ROADMAP/phase-6/PHASE%206.md), [PHASE 7](../05%20-%20ROADMAP/phase-7/PHASE%207.md), [PHASE 8](../05%20-%20ROADMAP/phase-8/PHASE%208.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md), this note |
| 4 November 2026 | Progress: Phase 4 built (R-31 to R-41) and checked in a browser, including a real PayMongo test payment. People apply on a plan with health answers and waivers, staff approve or reject, desk and online payments are recorded with receipts, memberships renew, freeze, expire and cancel with a history, and each athlete has a record. R-35, R-36 and R-41 stay 🟨 for one check each that joins up in Phases 5 to 7. Built without a member ID check (Q-04) or a health risk score (Q-05). | [PHASE 4](../05%20-%20ROADMAP/phase-4/PHASE%204.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md), this note |
| 4 November 2026 | Verification: Phase 3 browser checks passed for R-20 to R-30, R-97 and R-98. R-27 was rechecked after the holiday list was changed to update in place: add and remove now change the calendar and list without a reload, and both dialogs close. Phase 3 is finished. | [PHASE 3](../05%20-%20ROADMAP/phase-3/PHASE%203.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md), this note |
| 4 November 2026 | Progress: Phase 3 built (R-20 to R-30, R-97, R-98). Approved gyms have a workspace, rooms, employees with owner-made roles, employment records, schedules, leave and balances, holidays, services with photos, all-inclusive plans, gym search, nearest gyms and best matches. Q-13, Q-19 and Q-21 answered. | [PHASE 3](../05%20-%20ROADMAP/phase-3/PHASE%203.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md), this note |
| 4 November 2026 | Progress: Phase 2 built (R-11 to R-19). Gyms can apply with a tier and its documents, the administrator reviews with a checklist and approves, rejects or returns, and approved gyms show their tier badge. Q-16 and Q-17 are still open: built with no tier upgrade and no document expiry. | [PHASE 2](../05%20-%20ROADMAP/phase-2/PHASE%202.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md), this note |
| 3 November 2026 | Progress: R-08 rebuilt as owner-made roles and finished, so Phase 1 is finished. | [PHASE 1](../05%20-%20ROADMAP/phase-1/PHASE%201.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md) |
| 3 November 2026 | Your client's feedback. Added D-82 to D-85 and C-24, C-25. R-08 reopened as owner-made roles. Business work split into six modules (5A to 5F). PayMongo made core for every fee (R-35); Q-22 answered. Nearest-gym and best-match recommender added (R-97, R-98). PayMongo test keys copied into `backend/.env`. | [01 - OVERVIEW](../01%20-%20OVERVIEW/01%20-%20OVERVIEW.md), [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md), [03 - SYSTEM ARCHITECTURE](../03%20-%20SYSTEM%20ARCHITECTURE/03%20-%20SYSTEM%20ARCHITECTURE.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md) and phases 1, 3, 4, 5, 6, [06 - SCREENS BY ROLE](../06%20-%20SCREENS%20BY%20ROLE/06%20-%20SCREENS%20BY%20ROLE.md), [07 - WORD LIST](../07%20-%20WORD%20LIST/07%20-%20WORD%20LIST.md), this note |
| 3 November 2026 | Correction: the R-08 permission screen moved to R-22 in Phase 3, where employees are added. R-08 is now finished, and so is Phase 1. | [PHASE 1](../05%20-%20ROADMAP/phase-1/PHASE%201.md), [PHASE 3](../05%20-%20ROADMAP/phase-3/PHASE%203.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md) |
| 3 November 2026 | Progress: Phase 1 built. Setup done (Laravel 13, Laragon MySQL 8.4, React + Vite + coss UI). R-01 to R-07, R-09 and R-10 finished; R-08 works on the backend and waits for its screen in Phase 3. Q-09 and Q-10 answered. | [PHASE 1](../05%20-%20ROADMAP/phase-1/PHASE%201.md), [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md), this note |
| 3 November 2026 | You asked whether setting up the backend, frontend and store is in the phases. Added a note that the Phase 1 implementation plan starts with setup, which carries no `R-` number. | [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md): *Deliberately left out* and *Implementation handoff*; this note's *What you told me* |
