# 01 - OVERVIEW

[00 - START HERE](../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · Next: [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md)

In these notes, "the spec" means `Combat_Sports_Platform_Features_and_Modules.pdf`, and "the old system" means the code and stored information in `docs/existing-source-code` and `docs/existing-database`.

## What it is

It is a website for combat sports gyms in Cavite: boxing, Brazilian jiu-jitsu, judo, karate, kickboxing, MMA, Muay Thai, taekwondo and wrestling (the spec, page 3). Many gyms can join the one platform. A gym owner applies and is checked by a platform administrator. Once approved, each gym gets its own private workspace. There it runs members, classes, bookings, attendance, staff, pay, money, stock and purchasing. It also gets reports that help the owner make decisions (the spec, pages 3 and 8).

There is no separate phone app. The same website is wrapped in a ready-made phone shell from a service called Median.co (the spec, pages 3 and 4).

## Who uses it

The spec names five kinds of user (the spec, page 7).

| Who they are | What they come here to do |
|---|---|
| Platform administrator | Checks gym applications and their documents. Approves, rejects or returns them. Watches platform-wide numbers. Never sees a gym's private records (the spec, page 19). Planned: sets up subscription plans for gyms, what each includes, and the free-trial length (your client's request, 9 November 2026). |
| Platform user | Anyone who has signed up. Browses gyms and their services, looks after their own account, and applies to join a gym or to become a gym owner. |
| Gym owner | A checked platform user who runs one registered gym: staff, services, schedules, members, money and reports. |
| Gym employee, coaches included | Someone the owner has added to the gym. Sees only the parts of the system the owner has allowed. |
| Athlete or member | A platform user whose membership at a gym was approved. Books sessions, checks in, and sees their own attendance and progress. |

## What it does

The spec groups the work into seven modules. Here they are in the order a person meets them (the spec, page 20).

1. **Accounts and access.** People sign up, confirm their email with a one-time code, sign in, and find gyms. Owners create their own roles and decide what each employee may see and do. One gym's records are never visible to another gym (the spec, page 9).
2. **Gym setup.** A user applies to become an owner and uploads the exact documents for the checking level they chose. The administrator reviews them. An approved gym gets a badge, a workspace, a services list with photos and full descriptions, and membership plans with one all-inclusive price (the spec, pages 10 to 12).
3. **Training.** Staff create classes and sessions. The system refuses clashes of coach, room or time. It checks the coach's availability before a booking, shows a details page for each session, keeps a waiting list, and records check-ins, including by QR code (the spec, pages 13 and 14).
4. **Members and athlete records.** People apply for membership, and their membership is renewed, frozen or ended. Each athlete has a record of attendance, progress notes, rank, competitions, achievements and health details (the spec, page 15).
5. **Business side.** The spec groups this as one module (pages 16 and 17). At your client's request it is split into six separate modules: HR and employee management, payroll, finance, customer relationships (CRM), inventory and procurement (asked by your client, relayed in conversation, 3 November 2026). All are fed by the training side. Online payments go through PayMongo, using GCash, Maya or card, for every fee (also a client request).
6. **Keeping people informed.** Announcements, automatic notices for set events, and the phone shell (the spec, page 18).
7. **Decision support.** Two sides. For athletes and visitors, it finds the nearest gyms and the best matches for what they want; your client defined decision support this way (asked by your client, relayed in conversation, 3 November 2026). For owners, it gives dashboards, trends, forecasts and sustainability measures (the spec, page 19). The administrator sees only platform-wide numbers.

## What state it is in

There are three pieces of material, and they are at different stages.

- **The spec is the target.** It was revised in November 2026 after panel feedback (the spec, pages 1 and 6).
- **The old system is about half-built.** It handles sign-up, gym checking, membership applications and payment, sessions, and a detailed Philippine staff payroll. Several spec modules have no code at all: waiting lists, QR check-in, coach availability, rank and competitions, customer follow-up, stock and purchasing, expenses and budgets, and real forecasting. It also has serious safety faults. For example, an owner can make one of their staff a platform administrator. Details are in [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md).
- **The rebuild is far along.** Phases 1 to 8 are built (98 items). Phases 9 and 10, added 9 November 2026, tighten staff grace and payroll attendance and add paid subscription plans with a free trial for gyms; they are not started. See [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md).
- **The plan is a rebuild from scratch.** The old system is used only as a reference for what was tried. When the spec and the old system disagree, the spec wins (said in conversation, 3 November 2026).

## What it does not do

All of these come from the spec, page 4.

- It covers selected gyms in Cavite only.
- No body-sensor analysis and no wearable devices.
- No full accounting, tax or statutory financial reports. Money handling covers income and expense records, payments, amounts owed in both directions, payroll records and pay-outs, budgets and summaries.
- Most in-person payments still happen at the front desk. The system records them; it does not replace the till.
- The phone app is not a separately built native app.

## Where the details are

- [03 - SYSTEM ARCHITECTURE](../03%20-%20SYSTEM%20ARCHITECTURE/03%20-%20SYSTEM%20ARCHITECTURE.md): how the old system is put together, and the arrangement proposed for the rebuild.
- [04 - DIAGRAMS](../04%20-%20DIAGRAMS/04%20-%20DIAGRAMS.md): pictures of the main journeys.
- [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md): what to build, in what order.
- [06 - SCREENS BY ROLE](../06%20-%20SCREENS%20BY%20ROLE/06%20-%20SCREENS%20BY%20ROLE.md): what each kind of user sees.

## Added after P09 (12 November 2026)

Gyms can now sell prepaid plans that keep the member's price, run approved promos and vouchers, filter history by date, attach receipt photos to purchase orders, take coach applications from members, track training goals, injuries and equipment safety checks, and get notices about inactive members, staff absences and due safety checks. See [PHASE 11](../05%20-%20ROADMAP/phase-11/PHASE%2011.md).

## Added after the feature change list, section A (13 November 2026)

HR can store each employee's SSS, PhilHealth and Pag-IBIG numbers, with other staff seeing them masked. A gym can set a distance limit for staff clock-in. Long lists show 25 rows a page. A session's sport is picked from a list, and a session can have a thumbnail picture. Camera check-in now also works in browsers without a built-in QR reader. After approving an application, front desk staff go straight to recording the payment. See [PHASE 12](../05%20-%20ROADMAP/phase-12/PHASE%2012.md).
