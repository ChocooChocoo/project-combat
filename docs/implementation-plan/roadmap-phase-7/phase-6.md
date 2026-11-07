[← Previous: Packet 7D Live delivery](./phase-5.md) · [Next: Packet 7F Integration and close-out →](./phase-7.md)

**Status:** ✅ Done

# Packet 7E: Mobile and Median.co (R-84)

**Goal:** Every athlete screen works on a phone without sideways scrolling, and the repository carries a complete Median.co setup guide. The store build and the install check need a public HTTPS URL and the user's Median account, so they stay external and are recorded as a user step.

## Dependencies

Packets 7A to 7D. The screens they add must be final before the phone audit.

## Decisions

- The repository delivers an app-ready web app, not native code and not a built store package.
- No PWA service worker. Median wraps the live website.
- Push notifications while the app is closed are out of scope. Median push is documented as a later add-on.
- The phone audit covers every athlete and platform-user screen, not only the member screens.
- The existing dark theme stays.

## Mobile tasks

### Document head

- [x] `frontend/index.html`:
  - title `Combat Platform`.
  - `viewport` becomes `width=device-width, initial-scale=1.0, viewport-fit=cover`.
  - `<meta name="theme-color" content="#0a0a0a">`.
  - `<meta name="apple-mobile-web-app-capable" content="yes">`.
  - `<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">`.
  - `<meta name="apple-mobile-web-app-title" content="Combat Platform">`.
  - keep the existing favicon link.

### Safe areas

- [x] Add small CSS utilities in `frontend/src/index.css` for the top and bottom safe areas:
  `padding-top: env(safe-area-inset-top, 0px)` and `padding-bottom: env(safe-area-inset-bottom, 0px)`.
- [x] Apply the top inset to the app shell sticky header and the public gym brand bar.
- [x] Apply the bottom inset to the app shell content container in addition to its existing bottom padding.
- [x] Confirm the mobile sidebar sheet still closes and scrolls with the inset applied.

### Horizontal overflow

- [x] `frontend/src/pages/MyApplications.tsx`: add a card list below `md` and keep the table above `md`. The current screen only has a horizontally scrolling table.
- [x] Audit these screens at 360 px and 390 px; fix any element that causes sideways scrolling:
  - sign-in, sign-up, forgot password, verify email, first password, accept invite;
  - home, profile, notifications;
  - gym discovery and the public gym page;
  - membership application, my memberships and membership detail;
  - my bookings, timetable, session details, private coaching;
  - my record;
  - the gym workspace screens added in 7A and 7B.
- [x] Use the existing pattern: cards below `md`, table above `md`, or a guarded `overflow-x-auto` for small tables that cannot become cards.
- [x] Check long gym names, long announcement titles, long customer notes and the notification popover at 360 px.

### Median.co setup document

- [x] Create `docs/implementation-plan/roadmap-phase-7/median-co-setup.md` with:
  - prerequisites: public HTTPS frontend URL, reachable HTTPS API URL, production `VITE_API_URL`, CORS for the app domain;
  - Median app fields: name, start URL, theme/status bar, app icon and splash sizes;
  - allowed domains: the app domain and the API domain;
  - external links: PayMongo checkout and any other payment domain open outside the webview;
  - permissions to enable: camera (QR check-in), photos/files (membership documents and profile photo), location (nearest-gym discovery);
  - back-button behaviour and the in-app navigation rules;
  - pull-to-refresh;
  - push notifications: explicitly deferred, with a short note on what a later Median push integration would need;
  - a build and install checklist for Android and iOS;
  - a note that the install check cannot be completed from this repository.

## Browser checks

Run the frontend at a phone viewport.

- [x] 360 px and 390 px: no horizontal scrolling on any athlete screen.
- [x] Tap targets are at least 44 px for primary actions. The shared button hit area is 44 × 44 px under `pointer: coarse`; the sidebar trigger and notification bell are 44 px on phones.
- [x] The notification bell and popover fit inside the viewport. Measured 360 px: popover right edge 333; 390 px: right edge 363.
- [x] The QR check-in code and camera scanner fit without clipping. QR image is 224 px inside both viewports; the camera preview panel fits at 313 px (360) and 343 px (390).
- [x] The sticky header does not overlap content after scrolling. At rest the content starts at the header's bottom edge; while scrolling the opaque header stays above the content.
- [x] The public gym page works signed out and signed in. Signed out shows the sign-in card; signed in shows the full gym page at both widths.

## Done when

- [x] The setup document gives the start URL and configuration to create the build; build, install and store are external and not done.
- [x] Every athlete task (apply, schedules, booking, QR check-in and live notices) works in the web app and passes the phone-width audit; the installed-app transaction check remains external.
- [ ] The phone app is installed and opens the platform (external, evidence pending).
- [x] Every athlete screen fits 360 px and 390 px without sideways scrolling.
- [x] `median-co-setup.md` is complete enough for the user to build the app without re-deriving settings.
- [x] The install and store checks are recorded as external user steps, not claimed as done.
- [x] `pnpm lint` and `pnpm build` pass.

## Status notes

- 6 November 2026 — packet created; not started.
- 6 November 2026: Packet 7E started. Read objectives, findings, implementation plan, overview and CLAUDE.md. Preserve Packets 7.0–7D and all unrelated working-tree changes. Plan: metadata, safe areas, existing mobile card patterns, 360/390 px browser audit, Median setup guide, frontend checks. Native builds/install remain external.
- 6 November 2026: Repository-side Packet 7E work complete and verified.
  - Files changed: `frontend/index.html` (document title, `viewport-fit=cover`, theme colour, Apple mobile meta); `frontend/src/index.css` (safe-area top/bottom utilities); `frontend/src/components/app-shell.tsx` (safe areas on sidebar header/footer, sticky content header and content container; 44 px mobile trigger); `frontend/src/pages/GymPublic.tsx` (safe-area brand bar, long-text wrapping, wrapping coach-rank badge); `frontend/src/pages/MyApplications.tsx` (cards below `md`, table above `md`); `frontend/src/pages/gym/CustomerRecord.tsx` (`min-w-0` detail column and `wrap-anywhere` long inquiry); `docs/implementation-plan/roadmap-phase-7/median-co-setup.md`.
  - Browser audit: 360 px and 390 px across auth screens, home, profile, notifications, public gyms 1–5, memberships and detail, bookings, timetable, session details, private coaching, my record, applications, apply, membership application, admin home/notices/permissions/review, owner announcements/check-in, MyApplications cards/table, bell popover, QR code, camera scanner and the mobile sidebar. The audit found two real overflow cases and both are fixed: a long coach rank badge on the public gym page, and a long unbroken customer inquiry on the customer record. The first-password route was checked at 390 px with a temporary password-change-required account; at 360 px it uses the same `AuthCard` layout verified on sign-in/sign-up/forgot-password/verify-email/accept-invite.
  - Commands from `frontend/`: `pnpm lint` passed with existing warnings only; `pnpm build` passed; `node --test` passed 6 of 6.
  - Not run: installed Android/iOS Median build, native permission prompts, physical-device camera/location, PayMongo return into the installed app and store submission. These need the operator's Median account, public HTTPS URLs, signing credentials and devices; `median-co-setup.md` records them as external checklist items.
- 7 November 2026 — Final Phase 7 close-out recorded in `phase-7.md`: the shipped `index.html` matches the Median setup document (title, `viewport-fit=cover`, `#0a0a0a` theme, Apple web-app tags, safe areas), and the 360/390 px athlete screen audit found no horizontal overflow. Native install and store checks remain external. No Packet 7E source changed in this pass.
