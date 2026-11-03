# PHASE 2

**Gyms can apply, be checked, and go live**

[00 - START HERE](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) · [05 - ROADMAP](../05%20-%20ROADMAP.md) · Previous: [PHASE 1](../phase-1/PHASE%201.md) · Next: [PHASE 3](../phase-3/PHASE%203.md)

**Status:** ⭕ Not started

Citation forms are the same as in [02 - FINDINGS](../../02%20-%20FINDINGS/02%20-%20FINDINGS.md).

## The goal

A signed-up person can apply to run a gym. They choose a checking level ("tier") and upload exactly the documents that tier needs. They can then follow the application's progress. The platform administrator reviews it against a checklist and approves it, rejects it with a reason, or sends it back for correction. Approved gyms appear on the platform with their tier badge, and the administrator can see platform-wide numbers.

## Why it comes here

An application needs a confirmed account (R-01 and R-02 in [PHASE 1](../phase-1/PHASE%201.md)). The administrator role must be safe (R-07). The decision needs the notification inbox (R-10). Nothing in a gym's workspace can exist until the gym is approved.

## What it waits for

| Waits for | Why | Status |
|---|---|---|
| R-01, R-02 in [PHASE 1](../phase-1/PHASE%201.md) | Only confirmed platform users may apply | ⭕ |
| R-07, R-09 in [PHASE 1](../phase-1/PHASE%201.md) | The owner role and gym separation start here | ⭕ |
| R-10 in [PHASE 1](../phase-1/PHASE%201.md) | The applicant is told the decision | ⭕ |
| Q-16 and Q-17 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md) | Tier upgrades and document expiry change the application's life | ⭕ |

## What it unlocks

[PHASE 3](../phase-3/PHASE%203.md) cannot start without approved gyms (R-18). Gym discovery (R-30) needs the tier badge.

## Where this phase stands

| Status | How many |
|---|---|
| ✅ Finished | 0 |
| 🟨 Being worked on | 0 |
| ⭕ Not started | 9 |
| ❌ Blocked | 0 |
| 🔵 Already there | 0 |
| ⬜ Dropped | 0 |
| ❓ Unclear | 0 |
| **Total** | **9** |

---

## Module: Owner application

**Purpose:** let a platform user describe their gym and prove it is real and legal.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-11 | Apply to become a gym owner | R-02 | ⭕ | A confirmed user can submit a full gym profile |
| R-12 | Choose a tier and see its exact document list | R-11 | ⭕ | The applicant picks Tier 1 or Tier 2 and sees exactly what to upload |
| R-13 | Upload rules enforced | R-12, R-15 | ⭕ | Wrong file types, missing items or too few photos cannot be submitted |
| R-14 | Follow the application | R-11 | ⭕ | The applicant sees its status and everything they sent |
| R-15 | Private keeping of verification documents | R-09 | ⭕ | Only the applicant and the administrator can open the documents |

### R-11: Apply to become a gym owner

**In plain words:** A confirmed platform user fills in an application for their gym. It asks for the gym's name, address, map location, contacts, operating hours, the combat sports it offers, its services and its facilities.

**Why it is needed:** The spec, page 10, section 2.1. In the old system the gym was created during owner sign-up, so an ordinary user could never apply (C-02).

**Where the need came from:** D-11.

**Depends on:** R-02.

**Blockers:** Q-09 (can one owner run several gyms?).

**Done when:**

- [ ] A confirmed user can start an application from their account.
- [ ] The application cannot be sent until every required profile field is filled in.
- [ ] The location can be set on a map.
- [ ] The person becomes an owner only after approval (R-17), not when applying.

**Still open:** Q-09.

**Old system:** `frontend/src/pages/Register.vue`; the address lists there are hard-coded to Cavite (lines 1051 to 1145).

### R-12: Choose a tier and see its exact document list

**In plain words:** The applicant chooses a tier.

- **Tier 1** needs a DTI certificate (for a sole owner) or an SEC certificate (for a company or partnership), plus a current Mayor's or business permit and a barangay clearance.
- **Tier 2** needs everything in Tier 1, plus at least 10 facility photos, a video tour, the owner's valid ID, an owner selfie and a recent electricity or water bill.

The screen shows each document, what it proves, and who must send it.

**Why it is needed:** The spec, pages 10 and 11, PF-1. In the old system the choice was ignored, the tier was picked automatically, and the other tier's files were deleted (C-04). SEC was treated as optional rather than as the alternative to DTI (C-05).

**Where the need came from:** D-12, D-13 and D-14.

**Depends on:** R-11.

**Blockers:** Q-16 (can a Tier 1 gym later move up to Tier 2?).

**Done when:**

- [ ] The applicant picks Tier 1 or Tier 2, and the choice is saved.
- [ ] The Tier 1 list asks for DTI *or* SEC, and both permits.
- [ ] The Tier 2 list adds the photos, video, ID, selfie and bill.
- [ ] Changing tier never deletes files already sent without the applicant agreeing.

**Still open:** Q-16.

**Old system:** `frontend/src/pages/Dashboard.vue`, lines 201 to 320; `backend/app/Models/TrainingCenter.php`, lines 248 to 259; `backend/app/Http/Controllers/API/DocumentController.php`, lines 246 to 277.

### R-13: Upload rules enforced

**In plain words:** The system accepts PDF, JPG or PNG for documents and photos, and MP4 for the video. It refuses to send an application with a missing document or fewer than 10 photos (Tier 2). It reminds the applicant that documents must be clear, in colour and not expired, and that names and addresses must match.

**Why it is needed:** The spec, page 11, "Upload rules (proposed)". Missing or unreadable items are the main reason an application is sent back (page 10).

**Where the need came from:** D-15.

**Depends on:** R-12 (which list applies) and R-15 (where files go).

**Blockers:** none known.

**Done when:**

- [ ] A file of the wrong type is refused with a clear message.
- [ ] An application with a missing required document cannot be sent.
- [ ] A Tier 2 application with fewer than 10 photos cannot be sent.
- [ ] The upload screen states the clarity, colour, validity and matching rules.

**Still open:** the spec marks these rules "proposed" (page 11). They are treated as settled unless you say otherwise.

**Old system:** `backend/app/Http/Controllers/API/DocumentController.php`, lines 18 to 143. The old system also still accepted outdated document types that no tier counts (line 21).

### R-14: Follow the application

**In plain words:** The applicant can see where their application stands (draft, sent, under review, returned, approved or rejected) and look at every document they sent.

**Why it is needed:** The spec, page 10: "the system records the application status and lets the applicant review what has been submitted".

**Where the need came from:** D-12.

**Depends on:** R-11.

**Blockers:** none known.

**Done when:**

- [ ] The applicant sees the current status at any time.
- [ ] The applicant can open each file they uploaded.
- [ ] After a return or rejection, the reason is shown.

**Still open:** nothing.

**Old system:** `frontend/src/pages/Dashboard.vue`, lines 109 to 198 (pending, rejected and approved banners only).

### R-15: Private keeping of verification documents

**In plain words:** Permits, IDs, selfies, bills, photos and the video are kept where only the applicant and the platform administrator can open them. Anyone else following a link gets nothing.

**Why it is needed:** These are private documents. The old system kept them in a public folder and let any file there be deleted (`DocumentController.php`, lines 66 and 187).

**Where the need came from:** *drawn from* D-12 and D-17 (only the applicant and administrator handle them) and from the safety faults in [02 - FINDINGS](../../02%20-%20FINDINGS/02%20-%20FINDINGS.md).

**Depends on:** R-09.

**Blockers:** none known.

**Done when:**

- [ ] A person who is not the applicant or the administrator cannot open a document, even with its link.
- [ ] An applicant can delete only their own files, and only before sending.
- [ ] The same private keeping is ready for later private files: member IDs, medical files and leave attachments.

**Still open:** nothing.

**Old system:** as above.

---

## Module: Administrator review

**Purpose:** let the platform administrator check each application properly and record a clear decision.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-16 | Review against a checklist | R-14, R-15 | ⭕ | The administrator ticks each check item for each application |
| R-17 | Approve, reject or return for correction | R-16, R-10 | ⭕ | All three decisions work, and the applicant is told |
| R-18 | Approved gym goes live with its badge | R-17 | ⭕ | An approved gym appears on the platform with its tier badge |

### R-16: Review against a checklist

**In plain words:** The administrator opens an application and works through a checklist:

- every required document is present and readable;
- the permits are current;
- the business name and address match across documents.

For Tier 2, two more checks: the selfie matches the ID, and the photos and video match the declared facility.

**Why it is needed:** The spec, page 11, section 2.3. The old administrator just looked at the documents in a pop-up and pressed approve or reject (`frontend/src/pages/AdminDashboard.vue`, lines 1530 to 1559).

**Where the need came from:** D-17.

**Depends on:** R-14 and R-15.

**Blockers:** none known.

**Done when:**

- [ ] The administrator sees the application profile and every document side by side.
- [ ] Each checklist item can be marked as passed or failed.
- [ ] Tier 2 applications show the two extra checks.

**Still open:** nothing.

**Old system:** `backend/app/Http/Controllers/API/AdminController.php`, lines 253 to 393.

### R-17: Approve, reject or return for correction

**In plain words:** The administrator chooses one of three decisions:

- **Approve.**
- **Reject**, which needs a reason.
- **Return for correction**, which names what to fix. The applicant fixes it and sends it again.

Whatever the decision, the applicant gets a notice.

**Why it is needed:** The spec, page 11; page 10 (missing items are returned); page 18 (a notice on the verification decision). The old system had only approve and reject (C-03), and approving did not check that documents existed (`AdminController.php`, lines 301 to 342).

**Where the need came from:** D-16, D-18 and D-72.

**Depends on:** R-16 and R-10.

**Blockers:** none known.

**Done when:**

- [ ] Approve is possible only when every checklist item has passed.
- [ ] Reject cannot be saved without a reason.
- [ ] A returned application goes back to the applicant with the items to fix, and can be sent again.
- [ ] The applicant gets a notice for each decision.

**Still open:** Q-17 (whether documents expire and gyms must re-verify).

**Old system:** as above.

### R-18: Approved gym goes live with its badge

**In plain words:** Once approved, the gym becomes visible on the platform and shows the badge for its tier. The applicant becomes the gym's owner, and the gym's workspace opens (R-20).

**Why it is needed:** The spec, page 11, sections 2.3 and 2.4.

**Where the need came from:** D-19.

**Depends on:** R-17.

**Blockers:** none known.

**Done when:**

- [ ] An approved gym appears on the platform; pending, returned and rejected gyms do not.
- [ ] The gym shows the badge for its approved tier.
- [ ] The applicant now holds the owner role for that gym.

**Still open:** Q-16 (does the badge change if the gym moves up a tier?).

**Old system:** `backend/app/Models/TrainingCenter.php`, lines 214 to 281 (old badges: "verified business", "legit gym", "pending review"; `combat_sports_db.sql`, line 4193).

---

## Module: Platform monitoring

**Purpose:** give the platform administrator a view of the whole platform without exposing any gym's private records.

| # | Capability | Depends on | Status | Done when |
|---|---|---|---|---|
| R-19 | Platform-wide numbers for the administrator | R-17 | ⭕ | The administrator sees gym counts and pending applications, and nothing private |

### R-19: Platform-wide numbers for the administrator

**In plain words:** The administrator's home screen shows platform-wide numbers: gyms registered, gyms verified by tier, and applications pending, returned and rejected. It never shows a gym's members, money or staff records.

**Why it is needed:** The spec, page 19, section 7.4, and the "platform-level" cell of the access table on page 7.

**Where the need came from:** D-80.

**Depends on:** R-17.

**Blockers:** none known.

**Done when:**

- [ ] The administrator sees counts of registered and verified gyms, and pending applications.
- [ ] The administrator has no screen or request that returns a gym's private records.

**Still open:** which other platform-wide numbers are "permitted" is not listed beyond the examples (the spec, page 19). The examples are taken as the list.

**Old system:** `backend/app/Http/Controllers/API/ActivityController.php` (activity feed); `frontend/src/pages/AdminDashboard.vue`.

---

## Phase blockers

Q-09, Q-16 and Q-17 in the [open questions](../../00%20-%20START%20HERE/00%20-%20START%20HERE.md).

## Phase is finished when

Every item above is ✅, 🔵 or ⬜. A user can apply with the right documents, the administrator can decide with a checklist, and approved gyms are visible with their badge.
