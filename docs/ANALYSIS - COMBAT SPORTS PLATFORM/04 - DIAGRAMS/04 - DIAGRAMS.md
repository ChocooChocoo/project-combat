# 04 - DIAGRAMS

[00 - START HERE](../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · Previous: [03 - SYSTEM ARCHITECTURE](../03%20-%20SYSTEM%20ARCHITECTURE/03%20-%20SYSTEM%20ARCHITECTURE.md) · Next: [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md)

These are pictures of how the system works. Each one has a plain-language reading beneath it, so you never need to read a diagram to follow the notes.

- **Diagrams 1 to 6** show the **old system** as it works today.
- **Diagrams 7 to 9** show how things should move **according to the spec**.
- **Diagrams 10 to 12** show **the plan**.

Citation forms are the same as in [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md).

## 1. The big picture, old system

```mermaid
flowchart LR
    subgraph people["People"]
        admin["Platform administrator"]
        owner["Gym owner"]
        staff["Gym staff"]
        member["Member"]
    end

    subgraph screens["The screens, Vue"]
        adminScreen["Administrator dashboard"]
        ownerScreen["Owner dashboard"]
        staffScreen["Staff dashboard"]
        memberScreen["Member dashboard"]
    end

    backend["The work behind the screens, Laravel"]
    store1["The store, MariaDB"]
    files["Public file folder"]
    mail["Gmail"]
    pay["PayMongo"]

    admin --> adminScreen
    owner --> ownerScreen
    staff --> staffScreen
    member --> memberScreen
    adminScreen --> backend
    ownerScreen --> backend
    staffScreen --> backend
    memberScreen --> backend
    backend --> store1
    backend --> files
    backend --> mail
    backend --> pay
```

**Reading this:** Four kinds of people each have their own dashboard:

- administrators: `frontend/src/pages/AdminDashboard.vue`
- owners: `frontend/src/pages/TrainingDashboard.vue`
- staff: `frontend/src/pages/DynamicRoleDashboard.vue`
- members: `frontend/src/pages/MemberDashboard.vue`

All four dashboards send their requests to one Laravel backend. The backend keeps records in the MariaDB store and saves every upload in a folder anyone can reach by link. It also sends email through Gmail and takes online membership payments through PayMongo. This matches *The arrangement today* in [03 - SYSTEM ARCHITECTURE](../03%20-%20SYSTEM%20ARCHITECTURE/03%20-%20SYSTEM%20ARCHITECTURE.md).

## 2. Signing up and getting in, old system

```mermaid
flowchart TD
    start(["Someone opens the site"])
    which{"Owner or member?"}
    ownerForm["Owner sign-up form<br>also creates a pending gym"]
    memberForm["Member sign-up form<br>must be 18 or over"]
    code["Six-digit code emailed<br>valid ten minutes, no try limit"]
    codeOk{"Code correct?"}
    ownerWait["Owner sees the tier upload page"]
    idUpload["Member uploads a government ID"]
    adminCheck{"Administrator approves the ID?"}
    memberIn["Member dashboard opens"]
    refused["Member told the ID was rejected"]
    done(["Finished"])

    start --> which
    which -->|"owner"| ownerForm
    which -->|"member"| memberForm
    ownerForm --> code
    memberForm --> code
    code --> codeOk
    codeOk -->|"no, try again"| code
    codeOk -->|"yes, owner"| ownerWait
    codeOk -->|"yes, member"| idUpload
    idUpload --> adminCheck
    adminCheck -->|"yes"| memberIn
    adminCheck -->|"no"| refused
    ownerWait --> done
    memberIn --> done
    refused --> done
```

**Reading this:**

1. **Two separate doors.** Owners sign up on one page, and that step already creates their gym as "pending" (`backend/app/Http/Controllers/API/AuthController.php`, lines 50 to 125). Members sign up on another page, which checks they are 18 or over (`backend/app/Http/Controllers/API/MemberController.php`, lines 49 to 56).
2. **The emailed code.** Both get a code by email. It can be guessed again and again with no limit (`AuthController.php`, lines 130 to 224).
3. **After the code.** An owner goes to the tier upload page. A member must upload an ID and wait for the platform administrator (`MemberController.php`, lines 198 to 280; `backend/app/Http/Controllers/API/AdminController.php`, lines 181 to 246).

The spec has neither the two doors nor the ID wait: C-01 and C-02 in [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md).

## 3. A gym applies and is checked, old system

```mermaid
flowchart TD
    start(["Owner is signed in, gym pending"])
    pick["Owner looks at the tier picker"]
    upload["Owner uploads documents<br>to a public folder"]
    submit["Owner presses submit"]
    auto{"Are Tier 1 documents there?"}
    tier1["Gym set to Tier 1<br>Tier 2 files deleted"]
    tier2["Gym set to Tier 2<br>Tier 1 files deleted"]
    review{"Administrator decides"}
    approve["Gym approved, badge given"]
    reject["Gym rejected with a reason"]
    done(["Finished"])

    start --> pick
    pick --> upload
    upload --> submit
    submit --> auto
    auto -->|"yes"| tier1
    auto -->|"no"| tier2
    tier1 --> review
    tier2 --> review
    review -->|"approve"| approve
    review -->|"reject"| reject
    reject -->|"owner uploads again"| upload
    approve --> done
```

**Reading this:**

1. **Choosing a tier does nothing.** The owner sees a tier choice (`frontend/src/pages/Dashboard.vue`, lines 201 to 320), but the choice is never sent.
2. **The backend decides.** On submit, the backend picks the tier: Tier 1 wins if its documents exist. It then deletes the other tier's files (`backend/app/Models/TrainingCenter.php`, lines 248 to 259; `backend/app/Http/Controllers/API/DocumentController.php`, lines 246 to 277).
3. **The administrator has two choices.** Approve or reject (`backend/routes/api.php`, lines 568 to 584). A rejected owner can upload again. There is no "return for correction" and no checklist.

The spec version is diagram 7.

## 4. Joining a gym and paying, old system

```mermaid
sequenceDiagram
    participant Member as Member
    participant Screen as Member dashboard
    participant Backend as Backend
    participant PayMongo as PayMongo
    participant Desk as Front desk
    participant Finance as Finance

    Member->>Screen: Fills in the application and waivers
    Screen->>Backend: Sends the application
    Backend->>Backend: Works out a risk score and weight class
    Backend-->>Screen: Application saved, waiting for payment
    alt Pays online
        Screen->>Backend: Asks for a checkout page
        Backend->>PayMongo: Creates a checkout
        PayMongo-->>Member: Member pays
        Screen->>Backend: Asks if payment arrived
        Backend->>PayMongo: Checks the payment
        Backend-->>Screen: Payment marked as passed to finance
    else Pays cash
        Member->>Desk: Pays cash at the gym
        Desk->>Backend: Marks cash received
        Backend->>Backend: Membership approved at once
        Desk->>Backend: Marks money passed to finance
    end
    Finance->>Backend: Confirms the money arrived
```

**Reading this:** Time runs downwards. Solid arrows are requests going out; dashed arrows are answers coming back.

1. **Applying.** The member sends an application. The backend adds a health "risk score" and a boxing weight class (`backend/app/Http/Controllers/API/MembershipApplicationController.php`, lines 148 to 155).
2. **Paying online.** The backend creates a PayMongo checkout. When the member comes back, the screen asks the backend to check with PayMongo (lines 301 to 461).
3. **Paying cash.** The front desk marks the cash received, and **that alone approves the membership**, before finance sees anything (`backend/app/Http/Controllers/API/MembershipPaymentController.php`, lines 78 to 85).
4. **Finance last.** Finance confirms at the end (lines 146 to 211).

No staff member ever decides whether the person should be a member. The spec says one must: C-13 in [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md).

## 5. Booking a session: the old way and the spec way

### 5a. The old way

```mermaid
flowchart TD
    start(["Member picks a session"])
    already{"Already booked?"}
    full{"Places left?"}
    saved["Booking saved as paid<br>no money taken"]
    refuseDup["Told already booked"]
    refuseFull["Told session is full"]
    done(["Finished"])

    start --> already
    already -->|"yes"| refuseDup
    already -->|"no"| full
    full -->|"no"| refuseFull
    full -->|"yes"| saved
    saved --> done
    refuseDup --> done
    refuseFull --> done
```

**Reading this:** The old booking checks only two things: whether the member already booked, and whether places are left. It then saves the booking as "paid" without taking any money (`backend/app/Http/Controllers/API/MemberTrainerSessionController.php`, lines 133 to 200).

### 5b. The spec way

```mermaid
flowchart TD
    start(["Member opens the session details page"])
    member{"Active membership<br>and right level?"}
    coach{"Coach free at that time?"}
    places{"Places left?"}
    wait["Added to the waiting list"]
    policy{"Gym needs staff to confirm?"}
    staffOk{"Staff approve?"}
    booked["Booking confirmed<br>fee recorded as owed or paid"]
    refused["Told why it was refused"]
    done(["Finished"])

    start --> member
    member -->|"no"| refused
    member -->|"yes"| coach
    coach -->|"no"| refused
    coach -->|"yes"| places
    places -->|"no"| wait
    places -->|"yes"| policy
    policy -->|"no"| booked
    policy -->|"yes"| staffOk
    staffOk -->|"yes"| booked
    staffOk -->|"no"| refused
    booked --> done
    refused --> done
    wait --> done
```

**Reading this:** The spec booking checks four things in turn:

1. **Details first.** The member sees the session's details first (the spec, pages 13 and 14).
2. **Eligibility.** They must have an active membership at the right level.
3. **The coach.** The coach must be free (the spec, page 13).
4. **Places.** If the session is full, they join the waiting list (the spec, page 13).

If the gym requires it, a staff member approves (the spec, page 14). Each outcome sends the athlete a notice: approved, rejected or waitlisted (the spec, page 18). "Fee recorded as owed or paid" is *drawn from* the spec, page 16 (session payments are income), and from C-09.

## 6. Payroll approval, old system

```mermaid
flowchart TD
    start(["HR runs the payroll for a period"])
    skipCoach["Trainers and coaches left out"]
    compute["Pay worked out per employee<br>with Philippine deductions"]
    hr{"HR approves?"}
    fin{"Finance approves?"}
    own{"Owner approves?"}
    released["Payroll released, payslips visible"]
    rejected["Sent back as rejected"]
    shortcut["Any payroll manager can set released directly"]
    done(["Finished"])

    start --> skipCoach
    skipCoach --> compute
    compute --> hr
    hr -->|"yes"| fin
    fin -->|"yes"| own
    fin -->|"no"| rejected
    own -->|"yes"| released
    own -->|"no"| rejected
    compute -.-> shortcut
    shortcut -.-> released
    released --> done
    rejected --> done
```

**Reading this:**

1. **Coaches dropped.** HR runs payroll for a period. Trainers and coaches are dropped straight away (`backend/app/Http/Controllers/API/PayrollController.php`, lines 389 to 392).
2. **Working out pay.** Pay is worked out with SSS, PhilHealth, Pag-IBIG and tax deductions (lines 1339 to 1535).
3. **The approval chain.** It then needs HR, finance and owner approval in turn (lines 594 to 894).
4. **The shortcut.** The dotted line shows a back door: a plain edit can set a payroll straight to "released" (lines 133 to 145).

## 7. The life story of a gym application, spec

```mermaid
stateDiagram-v2
    [*] --> Draft
    Draft --> Submitted : applicant chooses a tier and sends all documents
    Submitted --> UnderReview : administrator opens it
    UnderReview --> Approved : checklist passes
    UnderReview --> Rejected : administrator gives a reason
    UnderReview --> Returned : items missing or unreadable
    Returned --> Submitted : applicant corrects and resends
    Approved --> [*]
    Rejected --> [*]
```

**Reading this:**

1. **Draft.** The applicant builds the application, chooses a tier and uploads that tier's documents (the spec, page 10).
2. **Under review.** Once sent, the administrator reviews it against the checklist (the spec, page 11).
3. **Three endings.**
   - *Approved*: the gym becomes visible with its tier badge.
   - *Rejected*: the decision is final and comes with a reason.
   - *Returned*: the applicant fixes the problems and sends it again (the spec, pages 10 and 11).

The applicant is notified of the decision (the spec, page 18). The state names "Draft" and "UnderReview" are *drawn from* the spec, page 10: "the system records the application status and lets the applicant review what has been submitted".

## 8. The life story of a membership, spec

```mermaid
stateDiagram-v2
    [*] --> Applied
    Applied --> Rejected : staff reject
    Applied --> Active : staff approve and fee is settled
    Active --> Suspended : frozen or suspended under the plan policy
    Suspended --> Active : freeze ends
    Active --> Expired : validity period ends
    Expired --> Active : member renews
    Active --> Cancelled : member or gym cancels
    Rejected --> [*]
    Cancelled --> [*]
```

**Reading this:**

1. **Applying.** A platform user applies. Staff approve or reject (the spec, page 15).
2. **Active.** An approved membership is active until its validity ends. Reminders go out before it expires (the spec, pages 15 and 18).
3. **Changes while active.** It can be frozen under the plan's policy (the spec, page 12), renewed after it expires, or cancelled.

"Fee is settled" is *drawn from* the spec, page 12 (one all-inclusive fee) and page 16 (membership fees are income). Whether approval must wait for payment is not stated. The rebuild should decide it, and it is part of R-33 in [PHASE 4](../05%20-%20ROADMAP/phase-4/PHASE%204.md).

## 9. The life story of a booking, spec

```mermaid
stateDiagram-v2
    [*] --> Requested
    Requested --> Waitlisted : session full
    Waitlisted --> Requested : a place opens
    Requested --> Confirmed : checks pass and staff approve if needed
    Requested --> Rejected : checks fail or staff reject
    Confirmed --> Flagged : coach becomes unavailable
    Flagged --> Confirmed : coach reassigned or session moved
    Confirmed --> Cancelled : session cancelled
    Confirmed --> Attended : athlete checks in
    Confirmed --> NoShow : athlete never arrives
    Rejected --> [*]
    Cancelled --> [*]
    Attended --> [*]
    NoShow --> [*]
```

**Reading this:**

1. **Requested.** A booking starts as a request. If the session is full, it waits on the list until a place opens (the spec, page 13).
2. **Confirmed or rejected.** It is confirmed when the checks pass and, if the gym requires it, a staff member approves (the spec, page 14).
3. **Flagged.** If the coach becomes unavailable, the booking is flagged until staff reassign the coach or move the session (the spec, page 13).
4. **How it ends.** Attended, a no-show, cancelled or rejected (the spec, page 14).

Whether an athlete may cancel their own booking is open: Q-24 in the [open questions](../00%20-%20START%20HERE/00%20-%20START%20HERE.md).

## 10. The order of the phases

```mermaid
flowchart LR
    p1["Phase 1 - People sign up and are recognised"]
    p2["Phase 2 - Gyms apply and go live"]
    p3["Phase 3 - Each gym runs its workspace"]
    p4["Phase 4 - Athletes join and have a record"]
    p5["Phase 5 - Sessions are scheduled, booked and attended"]
    p6["Phase 6 - The business side runs"]
    p7["Phase 7 - Everyone stays informed"]
    p8["Phase 8 - Owners decide with data"]
    p9["Phase 9 - Staff time and access rules tightened"]
    p10["Phase 10 - Gyms subscribe to the platform"]

    p1 --> p2
    p2 --> p3
    p3 --> p4
    p4 --> p5
    p5 --> p6
    p4 --> p7
    p6 --> p8
    p6 --> p9
    p9 --> p10
```

**Reading this:** An arrow means the phase it points at cannot start until the phase behind it is done.

- **Phases 1 to 6 run in a line.** Each needs the one before it. Gyms need accounts. Workspaces need approved gyms. Members need a gym's plans. Bookings need members and coaches. Payroll and income need attendance and bookings.
- **Phase 7 can start once Phase 4 is done,** alongside Phases 5 and 6. Its individual items still wait for the specific parts they announce or remind about.
- **Phase 8 reports on everything** in the first six phases.
- **Phase 9 needs only Phase 6,** because it adjusts staff attendance and payroll. It was added after Phase 8 was finished.
- **Phase 10 follows Phase 9,** because it reuses Phase 9's sidebar rule to hide locked modules. It also needs approved gyms (Phase 2) and PayMongo (Phase 4), both done long before.

This matches *The phases* in [05 - ROADMAP](../05%20-%20ROADMAP/05%20-%20ROADMAP.md), and the notes in `05 - ROADMAP/phase-1/PHASE 1.md` to `phase-10/PHASE 10.md`.

## 11. The proposed arrangement

```mermaid
flowchart LR
    subgraph access["Ways in"]
        browser["Browser"]
        shell["Median.co phone shell"]
    end

    app["One screen app, coss UI"]
    door["Front door, Laravel<br>checks pass, permission, gym"]

    subgraph stores["Where things are kept"]
        db["The store, MySQL"]
        privateFiles["Private file store"]
    end

    subgraph helpers["Helpers"]
        runner["Scheduled job runner"]
        sendLine["Background sending line"]
        live["Live update channel"]
    end

    mail["Email service"]
    pay["PayMongo"]

    browser --> app
    shell --> app
    app --> door
    door --> db
    door --> privateFiles
    door --> sendLine
    door --> pay
    runner --> sendLine
    sendLine --> mail
    sendLine --> live
    live --> app
```

**Reading this:**

1. **Ways in.** People reach one screen app, from a browser or the Median.co phone shell. The app is built from coss UI parts (said in conversation, 3 November 2026).
2. **The front door.** Every request goes through one Laravel front door. It checks the sign-in pass, the person's permission for that module and their gym before doing anything. This fixes C-08, C-16 and C-18 in [02 - FINDINGS](../02%20-%20FINDINGS/02%20-%20FINDINGS.md).
3. **Storage.** Records go to MySQL. Private files go to a store nobody can reach without permission, which fixes the public-folder fault.
4. **Notices.** Anything that should tell people something goes to the background sending line. The line sends emails and pushes live updates back to open screens (C-20).
5. **Timed reminders.** The scheduled runner adds reminders on its own timetable.
6. **Payments.** Online payments go through PayMongo.

This matches *The arrangement being proposed* in [03 - SYSTEM ARCHITECTURE](../03%20-%20SYSTEM%20ARCHITECTURE/03%20-%20SYSTEM%20ARCHITECTURE.md).

## 12. The life story of a gym subscription, planned

```mermaid
stateDiagram-v2
    [*] --> Trial : new gym registered
    Trial --> Paid : owner pays through PayMongo
    Trial --> Starter : trial ends with no payment
    Starter --> Paid : owner pays through PayMongo
    Paid --> Paid : owner pays for the next period
    Paid --> Starter : paid period ends with no payment
```

**Reading this:**

1. **Trial.** A new gym starts on a free trial. The administrator sets its length; it starts at 30 days (said in conversation, 9 November 2026). It opens every module and starts at approval (Q-25, Q-26).
2. **Paid.** The owner picks a paid plan, such as Pro or Elite, and pays through PayMongo. The plan starts only when the payment is confirmed (R-105).
3. **Starter.** A gym whose trial or paid period ends without payment moves to the free plan, Starter. It keeps only the modules Starter includes; its records are kept, and the rest unlock when it pays (R-106). There are no extra days after a paid month (Q-27).

This matches [PHASE 10](../05%20-%20ROADMAP/phase-10/PHASE%2010.md) and the subscription row in *The arrangement being proposed* in [03 - SYSTEM ARCHITECTURE](../03%20-%20SYSTEM%20ARCHITECTURE/03%20-%20SYSTEM%20ARCHITECTURE.md).

## 13. An offer from draft to discount, built

```mermaid
flowchart LR
    A[Staff saves offer] --> B{Made by owner?}
    B -- yes --> D[Approved]
    B -- no --> C[Pending: approvers notified]
    C -- approver says yes --> D
    C -- approver says no --> R[Rejected: maker notified]
    D --> E{At membership approval: in date, right plan, uses left?}
    E -- yes --> F[Discount off first charge; uses + 1]
    E -- no --> G[Voucher refused / promo skipped]
```

**Reading this:** a staff offer never works until someone else approves it (R-111). Editing an approved offer as staff sends it back to pending. Renewals never get the discount.

## 14. A coach application, built

```mermaid
flowchart LR
    A[Member sends hours] --> B{Overlaps a shift at another gym?}
    B -- yes --> X[Refused, clash listed]
    B -- no --> C[Pending: owner notified]
    C --> D{Approve: check clashes again}
    D -- clash --> X
    D -- clear --> E[Employee link + coach profile + schedule + Coach role]
    C -- reject --> F[Applicant told the reason]
```

**Reading this:** the clash check runs when applying and again when approving, because schedules change (R-117). The account stays a member account.

## 15. The daily inactivity check, built

```mermaid
flowchart LR
    A[08:20 each day] --> B[Each approved gym]
    B --> C[Active members]
    C --> D{Last visit or attended session older than the gym's inactivity days?}
    D -- no --> C
    D -- yes --> E{Already told for this quiet spell?}
    E -- yes --> C
    E -- no --> F[Notice to member]
    F --> G[One digest to staff with members access]
```

**Reading this:** notices only; nothing is blocked (R-116). A new visit starts a new quiet spell, which can be told once more.
