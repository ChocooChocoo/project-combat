[Next: Packet 7.0 Foundations →](./phase-1.md)

**Status:** ✅ Complete

# Roadmap Phase 7: Everyone stays informed, on their phone too (R-80 to R-84)

**Goal:** Gyms can send targeted announcements and events, the platform administrator can send platform notices, timed reminders go out on their own, notices and booking places update without refreshing, and every athlete screen works in a phone app wrapped by Median.co.

This folder holds the build plan for all five Phase 7 requirements. Every packet file is self-contained: goal, dependencies, decisions, schema, tasks, invariants, tests, and a done-when list.

## Packets

| File | Packet | Requirements | Waits for | Status |
|---|---|---|---|---|
| [phase-1.md](./phase-1.md) | 7.0 Foundations | enabler only | — | ✅ Done |
| [phase-2.md](./phase-2.md) | 7A Announcements | R-80 | 7.0 | ✅ Done |
| [phase-3.md](./phase-3.md) | 7B Platform notices | R-81 | 7.0 | ✅ Done |
| [phase-4.md](./phase-4.md) | 7C Scheduled reminders | R-82 | 7.0 | ✅ Done |
| [phase-5.md](./phase-5.md) | 7D Live delivery | R-83 | 7.0, 7A, 7B | ✅ Done |
| [phase-6.md](./phase-6.md) | 7E Mobile and Median.co | R-84 | 7A–7D | ✅ Done |
| [phase-7.md](./phase-7.md) | 7F Integration and close-out | all | all | ✅ Done |

Phase 7 is complete: every requirement R-80 to R-84 is ✅ and the phase-level gates pass. Two checks remain external rather than unmet: the Packet 7.0 API-shutdown error state is covered by an injected 503 and the Packet 7D physical background-tab check is covered by hook tests plus a visibility simulation. The Median native build, install and store submission need the operator's account, public HTTPS URLs, signing credentials and devices; `median-co-setup.md` records them as external steps.

## Locked decisions

Settled with the user on 6 November 2026.

| Decision | Value |
|---|---|
| Platform notice audience (Q-23) | S-04 offers a choice: **owners of approved gyms** (default) or **all verified platform users**. |
| Session reminder | 60 minutes before `starts_at` by default. Per-gym setting `session_reminder_minutes`, editable in gym setup; choices 30, 60, 120 and 1440. |
| Membership expiry reminder | 7, 3 and 1 days before `ends_on`. App-config thresholds, not per gym. Catch up if a daily run was missed. |
| Follow-up reminder | At `due_at`, with a 24-hour catch-up window. Reset when the assignee or due date changes. |
| Live delivery | Visibility-aware polling every 15 seconds. No websockets, no SSE, no new realtime infrastructure. |
| Push while the app is closed | Deferred. In-app only. Documented as out of scope for R-83/R-84. |
| Median.co | The repository delivers an app-ready web app and a setup document. The store build and install check need a public HTTPS URL and the user's Median account, so they stay external. |
| "All members" audience | People with an `active` or `frozen` membership in the gym. No expired or cancelled memberships, no staff-only accounts. |
| Announcement class audience | One session occurrence, `booked` bookings only. No requested, waitlisted or cancelled bookings. |
| Announcement customer-group audience | Group customers with a linked user account. Customers without an account are skipped and counted. |
| Announcement events | Optional `event_starts_at` and `event_place`. No RSVP, calendar, drafts, scheduling or editing after send. |
| Announcement permissions | Reuse the existing `announcements.general.view` and `announcements.general.manage` strings. No catalog rename and no permission migration. `approve` stays unused. |
| Notification channel | Database only, synchronous chunked fan-out. No queue for notices, so the seeder still leaves `jobs` empty. |
| Notice links | New notices carry an optional `url`. The bell and inbox render an "Open" link when present. |
| Inbox | 20 per page, with working page controls and an error state. |

## Dependency order

```
7.0 Foundations
 ├── 7A Announcements (R-80) ─┐
 ├── 7B Platform notices (R-81)├── 7D Live delivery (R-83)
 └── 7C Reminders (R-82) ──────┘
                                   │
                              7E Mobile + Median.co (R-84)
                                   │
                              7F Integration close-out
```

7A, 7B and 7C are independent after 7.0. They are still built one at a time so routes, the seeder, navigation and shared tests do not collide.

## Architecture in one page

**Announcements (R-80).** One `announcements` table stores the sent record: gym, title, body, kind (`announcement` or `event`), event date and place, audience type, optional session or customer group, sender, recipient and skipped counts. `AnnouncementAudience` resolves the recipients. It returns unique users plus a skipped count. The controller stores the announcement and sends `AnnouncementPublished` to each recipient in chunks. There are no drafts and no edits after send.

**Platform notices (R-81).** One `platform_notices` table stores title, body, audience (`owners` or `all_users`), recipient count and sender. The admin controller sends `PlatformNoticeSent`, a gym-less database notification. Owners are resolved from approved gyms. All users are resolved from verified accounts. The sender is excluded.

**Reminders (R-82).** Three small support classes do the work, called by three scheduled closure commands.

- Session reminders: `session_bookings.reminder_sent_at` marks the booking. The query only looks at `booked` bookings on `scheduled` sessions whose `starts_at` is inside the lead window. Rescheduling a session clears the marker on its bookings.
- Membership expiry: `membership_reminders` has a unique key on `(membership_id, ends_on, days_before)`. The daily command finds the smallest crossed threshold that has not been sent, so a missed day catches up without duplicates. Frozen memberships and session-count plans without `ends_on` are skipped.
- Follow-ups: `customer_follow_ups.reminded_at` marks the row. The command only looks at open, assigned follow-ups due in the last 24 hours. Reassignment or a new due date clears the marker.

**Live delivery (R-83).** One `NotificationsProvider` owns the unread count and polls `GET /api/notifications/unread-count` every 15 seconds while the tab is visible, plus on focus. The bell, the inbox, the app shell and the public gym header read from it. A shared `usePolling` hook refreshes the timetable, session details, my bookings and the staff booking list. Polling pauses while the tab is hidden.

**Mobile (R-84).** The web app gets the missing phone metadata, safe-area padding and a pass over every athlete screen at 360 and 390 pixels. Screens that only work with sideways scrolling get a card layout. `median-co-setup.md` records the wrapper configuration: start URL, allowed domains, PayMongo external links, camera, file uploads, location, back button and deferred push.

## Requirement coverage

| Requirement | What it means | Packet |
|---|---|---|
| R-80 | Targeted announcements and events to all members, one class or a customer group | 7A |
| R-81 | Platform notices from the administrator | 7B |
| R-82 | Scheduled session, membership-expiry and follow-up reminders | 7C |
| R-83 | Live delivery of notices and places left | 7D |
| R-84 | Median.co phone app for athletes | 7E |

## Execution protocol

1. Read the packet file fully. Read the files it names.
2. Set the packet status to **In progress** and add a dated note.
3. Build test-first. Write the failing test, make the smallest change, run the test.
4. Run the packet's checks: `php artisan test`, then `pnpm lint` and `pnpm build` when the frontend is touched.
5. Update the packet file after each task: tick a box only when verified, and record files changed and command results in the status notes.
6. Stop at the packet boundary. The next packet starts only after review.
7. Never commit unless the user asks. When asked, commit only the files the packet names.

Each packet brief handed to an implementer carries that packet file plus the decisions above. No implementer should have to re-derive a product rule.

## Global checks

Run from `backend/`:

```bash
php artisan test
php artisan migrate:fresh --seed   # MySQL, once per packet that changes schema
php artisan app:sync-permissions
```

Run from `frontend/`:

```bash
pnpm lint
pnpm build
```

Browser check at the end of any packet with screens: owner, front-desk employee, coach, member and platform administrator where relevant. Check the empty state, loading state, error state, a phone-width layout, and that nav visibility matches permissions.

## Close-out record (7 November 2026)

- **Backend:** `php artisan test` passed 520 tests / 26,513 assertions in 176.6 seconds. `php artisan migrate:fresh --seed --force` passed on MySQL and left 10 demo announcements, 1 platform notice, 118 users and no queued jobs. `php artisan app:sync-permissions` passed, and `php artisan schedule:list` showed session reminders `*/5`, membership reminders `0 8` and follow-up reminders `*/15`.
- **Migrations:** the four new migrations rolled back and re-applied cleanly on a throwaway MySQL schema, which was then dropped.
- **Frontend:** `pnpm lint` exit 0 with existing warnings only; `pnpm build` exit 0 (3,138 modules); `node --test` 6 passed / 0 failed.
- **Browser:** all-members, one-class, customer-group and event announcements; front-desk send and coach denial; expired-member and waitlisted-athlete exclusions; owners and all-users platform notices; non-admin denial and gym-free payloads; live notice in 4.8 seconds; live places-left in 11.2 seconds; zero horizontal overflow on every athlete screen at 360 px and 390 px.
- **Still external:** the Median Android/iOS build, native permission prompts, physical-device checks and store submission. `median-co-setup.md` records them as user steps.

## Out of scope for Phase 7

- Push notifications while the app is closed. Median.co push is a later add-on.
- Native app code. R-84 is a Median.co wrapper around the web app.
- Drafts, scheduled announcements, editing or deleting a sent announcement.
- RSVP, calendar invites, waitlists or ticket sales for events.
- Announcements to customers with no user account. They are counted as skipped.
- Websockets, SSE, broadcasting, Laravel Echo, Reverb, Pusher or Socket.IO.
- Per-gym expiry thresholds or per-gym follow-up timing.
- Email or SMS delivery of announcements and reminders. In-app only.

## Documentation to keep in step

- `docs/ANALYSIS - COMBAT SPORTS PLATFORM/05 - ROADMAP/phase-7/PHASE 7.md`: statuses, per-requirement notes, build record.
- `docs/ANALYSIS - COMBAT SPORTS PLATFORM/05 - ROADMAP/05 - ROADMAP.md`: Phase 7 counts.
- `docs/ANALYSIS - COMBAT SPORTS PLATFORM/00 - START HERE/00 - START HERE.md`: Q-23 answer and change log.
- `docs/ANALYSIS - COMBAT SPORTS PLATFORM/06 - SCREENS BY ROLE/06 - SCREENS BY ROLE.md`: mark S-04, S-15 and S-35 built.
- `CLAUDE.md` only if a convention a future agent must know changed.

---

[Next: Packet 7.0 Foundations →](./phase-1.md)
