# Combat Platform: Median.co setup

Packet 7E (R-84), 6 November 2026. This repository supplies the mobile-ready website, not a native project, signed binary, service worker or store submission. A Median account, production deployment and physical devices are required for the remaining checks.

## 1. Deployment prerequisites

- Deploy the frontend at a public HTTPS origin with a valid certificate. Replace `APP_HOST` below with that exact hostname, for example your own `app` subdomain. Do not use localhost, a Laragon hostname or the example placeholders in a build.
- Deploy the Laravel API at a reachable HTTPS origin. Replace `API_HOST` with its exact hostname. Confirm `/api` responds from a phone on mobile data, not only your local network.
- Set production `VITE_API_URL=https://API_HOST/api` **before** running `pnpm build` in `frontend/`. This value is embedded at build time; changing a server environment variable after building does not change it.
- Configure the API's CORS allowed origins for `https://APP_HOST`. Permit the existing `Authorization`, `Accept` and `Content-Type` headers, API methods and preflight requests. Do not solve CORS with a wildcard origin. The app uses Sanctum bearer tokens in the website's local storage, not a new native login or cookie-based session.
- Serve the frontend's `index.html` for application routes such as `/memberships/1`, `/gyms/1/sessions` and `/verify-email`. Check those URLs after a cold launch and refresh.
- Configure production backend URLs for email verification, invitation and PayMongo return links. Confirm PayMongo webhooks can reach the production HTTPS API and use the production webhook secret. Never embed PayMongo secret keys in the frontend or Median settings.
- Keep the existing backend scheduler running. Native packaging does not run Laravel reminders. In-app polling needs the API and network.
- Run `php artisan storage:link` on the production API host. Public gym and coach photos are served from `https://API_HOST/storage/...`, so that host must be reachable over HTTPS from the phone.
- Supply approved branding, privacy/support URLs, store account access and a private review/test account. Do not publish the local development accounts or `ACCOUNTS.md`.

Use these exact production values. The names come from `backend/config/app.php`, `backend/config/cors.php` and `frontend/.env`:

| Value | Production setting |
|---|---|
| `APP_URL` (backend) | `https://API_HOST` |
| `FRONTEND_URL` (backend) | `https://APP_HOST` |
| `VITE_API_URL` (frontend build) | `https://API_HOST/api` |
| CORS allowed origin | `https://APP_HOST` |
| PayMongo webhook URL | `https://API_HOST/api/webhooks/paymongo` |
| PayMongo keys and webhook secret | Production values from the PayMongo dashboard; backend only, never in the frontend or Median settings |

The website is live: content and UI changes ship with the next web deployment. A new app icon, splash, package/bundle ID, permission declaration or native plugin needs a new Median build and a new store release.

## 2. App Studio fields

Create an app at <https://median.co/new>. Save the chosen production values in the operator's release record.

| Field | Setting |
|---|---|
| App name | `Combat Platform` |
| Website / start URL | `https://APP_HOST/` (existing guards send guests to sign-in) |
| Android application ID / iOS bundle ID | A unique reverse-domain ID owned by your organization; select once and keep stable for upgrades |
| Theme / launch background | `#0a0a0a`; preserve the website's dark theme |
| Status bar | Visible, dark mode with white text/icons, background `#0a0a0a` |
| Status bar overlay | Off initially on both platforms. Forms must remain above the keyboard. Test any future edge-to-edge configuration on devices before enabling it. Website already has `viewport-fit=cover` and safe-area padding. Avoid applying a second native inset when the WebView already excludes the status bar. |
| Native navigation | Do not duplicate the website's sidebar, header or role-specific links with native tabs or a second sidebar |
| App icon | Approved square PNG/JPG, recommended **1024 × 1024 px**, opaque background; do not pre-round the corners |
| Splash | Use Median's auto-generated splash from the same **1024 × 1024 px** icon, on `#0a0a0a`. No separate full-screen image dimensions are required for this setup: iOS uses a generated launch storyboard; Android 12+ centers/crops the icon. Preview both platforms. Custom device-specific artwork is not supplied by this packet. |
| Push plugins | Disabled; no notification permission prompt at launch |

The HTML Apple mobile meta tags do not install or sign an app. Median still needs a build and platform signing.

## 3. Allowed domains and link handling

Allow the exact app and API domains used by the deployment. If they share one origin, one hostname is enough. Include a separate first-party media host only if deployment actually uses one. Retain HTTPS; do not grant a wildcard over unrelated sites or subdomains. Median's link rules control top-level navigation; they do not replace API CORS, backend authorization or content-security rules for fetched resources.

Configure **Link Behavior** in this order (first match wins). Current App Studio rules use route patterns, not regular expressions:

| Destination | Mode |
|---|---|
| `checkout.paymongo.com/**` | **External**, system browser, not the main WebView |
| Other checkout/payment provider domains actually returned by PayMongo, including sandbox checkout hosts when testing | **External**, add each observed hostname before the app-domain rules; do not guess or broadly allow every payment site |
| `mailto:`, `tel:` and approved map/app links | **External**, corresponding device app |
| `APP_HOST/**` | **Internal**, existing web router and auth guards |
| `API_HOST/**` | First-party allowed domain for requests; do not create a native menu entry to the API |
| All other links | **External**; do not leave the default App Browser behavior if full external separation is required |

Test the actual checkout redirect initiated through `window.location.href`, not only an ordinary anchor. Do not put authentication tokens in payment URLs.

### Payment returns

The external browser and WebView do **not** share local storage. A PayMongo return can therefore land on the website's sign-in screen in Safari/Chrome instead of reopening the installed app. Do not treat that as a confirmed payment or copy the WebView token into the browser. Return to Combat Platform, refresh the membership/bookings screen, and verify the server-recorded balance/receipt. Existing confirmation and webhook logic remain authoritative; a checkout success query parameter is not proof of payment.

Automatic app return is an optional deployment task: configure verified Android App Links / iOS Universal Links for `APP_HOST`, including the required domain association files and signed app identifiers, then test success and cancellation. This repository does not configure those native associations or promise automatic return.

## 4. Device permissions

Request access when the user chooses the relevant action, not on launch. Supply purpose text in App Studio where available and inspect the generated platform permission declarations before release.

| Feature | Permission / behavior |
|---|---|
| Staff camera QR scanning | Camera access for `getUserMedia`, over HTTPS; purpose: “Scan a member's check-in QR code.” No microphone, audio capture, contacts or background recording. Athletes display their code; staff scan it. |
| Profile photo, application documents, medical clearance | Enable native file/photo selection and optional camera capture. Existing HTML file inputs use the system picker. Basic uploads do not need a paid Median plugin or blanket device-storage access. Request photos access only if the selected platform picker/capture flow requires it; do not request full-library access unnecessarily. |
| Nearest-gym search | Foreground / while-in-use location; purpose: “Find gyms near your current location.” No background location. Manual city/province/address search remains available if denied. |

The camera scanner depends on the WebView exposing `BarcodeDetector`. Camera permission alone does not add that API, including on iOS versions without it. The existing UI provides typed short-code and USB/keyboard scanner fallback. Do not enable a Median native scanner plugin and assume the web code calls it: native bridge integration is not part of Packet 7E.

On physical Android and iOS devices, test allow, deny, cancellation, retry after changing device settings, file type/size validation and camera cleanup after closing/navigating away. The browser audit cannot prove native permission prompts or a real camera scan.

## 5. Navigation and refresh

- Keep same-origin pages inside the existing WebView and React router. Retain web sidebar navigation, modal close/cancel buttons and page back links. Role guards remain enforced by the API; hiding a native item is not access control.
- Keep Android's normal history Back behavior. Test session → timetable, membership → list and gym page → prior page, including React soft navigation. At the history root, normal platform exit/background behavior is acceptable; do not reload the start page on every Back press.
- On iOS, test history/swipe Back where enabled and the web page's explicit Back links. Do not replace web navigation with a new native navigation design.
- Test Back while a sheet/dialog is open. Use its explicit close/cancel or backdrop; do not assume a browser-history Back automatically dismisses a web dialog.
- **Pull-to-refresh: disable on Android and iOS for this release.** It reloads the document and can discard application, profile or health form edits. Existing retry controls and 15-second visibility-aware polling refresh data. No service worker or offline editing is provided.
- Check keyboard appearance, rotation, notch/home indicator, sidebar scrolling and content below the sticky header. Keep system zoom/accessibility available.

## 6. Notifications: in-app only

Notices use database notifications. Unread counts, inbox and booking availability refresh while the app page is visible (15-second polling and focus refresh). A closed, suspended or offline app does **not** receive OS push notifications from this implementation.

Later push work would need a chosen Median push provider/plugin (for example OneSignal or FCM), APNs/FCM credentials, permission/consent flows, authenticated user-to-device registration, logout/unlink and token rotation, backend push delivery with retries/deduplication and safe deep-link handling. None is configured here. Do not request push permission or claim background push is available.

## 7. Android build and install checklist (external)

- [ ] Replace all hostname placeholders; verify production HTTPS API, CORS, deep-route fallback and login.
- [ ] Save App Studio fields, icon/splash, domains, external payment rules, permissions and disabled pull-to-refresh/push.
- [ ] Generate a test APK in Median and install on a physical Android device (allow installation from the trusted download source if needed).
- [ ] Launch from the installed icon; check sign-in, verification, invitation/first password, token persistence after relaunch and sign-out.
- [ ] Apply for membership with uploads, read timetable/session details, book/cancel, show the rotating QR/short code and receive an in-app notice while open.
- [ ] Test staff scanning on a supported WebView, denial and typed-code fallback; test location and photo/file pickers.
- [ ] Check 360/390 px-equivalent layouts, keyboard, long text, bell/popover, safe areas, sidebar and Back behavior.
- [ ] Complete PayMongo sandbox success/cancel in the external browser and verify updated server balance after returning.
- [ ] Test loss of network/resume without duplicate submits or promises of offline access/background push.
- [ ] For Play distribution, configure release signing, retain the signing/upload-key recovery information, create a signed AAB and upload to internal testing first. Complete privacy/data safety, permissions, screenshots, support and store review requirements before requesting production release. Confirm the Google Play account-deletion policy; this repository has no self-service delete-account screen, so add one or provide the approved operator-assisted route before submission.

## 8. iOS build and install checklist (external)

- [ ] Recheck the shared production configuration, permission purpose strings, branding and payment rules.
- [ ] Supply Apple Developer / App Store Connect access, a registered bundle ID and correct signing/provisioning via Median's iOS build flow. Use a registered device for a development/ad hoc build, or TestFlight for distribution.
- [ ] Generate the signed IPA and install through the selected supported method. A raw unsigned download cannot be installed on a normal iPhone.
- [ ] Launch on a physical notched/home-indicator iPhone; repeat the athlete, uploads, external payment, bell, safe-area, keyboard, Back and resume checks above.
- [ ] Confirm camera capability detection and typed-code fallback on that exact iOS/WebView version. Do not claim the unsupported `BarcodeDetector` path scans.
- [ ] Upload to App Store Connect/TestFlight, install the distributed build, and complete App Privacy, permission declarations, screenshots, support/privacy URLs and review credentials before App Store submission. Confirm Apple's account-deletion requirement; this repository has no self-service delete-account screen, so add one or provide an approved route before submission. Store approval is a separate external decision, not guaranteed by a successful web build.

## External-install limitation and release record

**Build, install and store checks above are intentionally unticked.** Repository checks cannot create the owner's Median project, obtain production hostnames or branding, sign with their Apple/Google credentials, install on physical devices or secure store approval. Phase documents mark R-84 done for repository scope only; native build and installed-app items stay unticked until the operator supplies evidence. Browser phone-width checks prove website layout only.

Record operator/date, public start URL, API origin, Median app ID, app version/build numbers, OS/WebView versions, device models, distribution method and results for every checklist item. Keep credentials and signing keys out of this repository.

## Reference documentation

Checked against official Median documentation on 6 November 2026. Context7's library search did not contain Median; these vendor pages supplied the settings above:

- [App icon](https://docs.median.co/docs/app-icon), [splash screen](https://docs.median.co/docs/splash-screen), [status bar](https://docs.median.co/docs/status-bar)
- [Link behavior and route patterns](https://docs.median.co/docs/internal-vs-external-links), [deep linking](https://docs.median.co/docs/deep-linking)
- [Camera and files](https://docs.median.co/docs/camera-and-file-uploads), [WebRTC camera permissions](https://docs.median.co/docs/web-rtc), [location](https://docs.median.co/docs/location-services)
- [Native gestures / pull-to-refresh](https://docs.median.co/docs/swipe-gestures)
- [Android deployment](https://docs.median.co/docs/android-build-deployment), [iOS deployment](https://docs.median.co/docs/ios-build-deployment)
