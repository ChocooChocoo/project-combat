# Synthetic upload fixtures

Documents and original illustrations are generated fixtures. Twenty JPEGs were
replaced with user-provided images from `iron_fist_services_and_session`:
profile, coach, gym cover, ten facility views, service cover, five service gallery
views and session thumbnail. Their source is recorded in `manifest.json`.
All old JPEG illustrations have been removed. No real government seals or signatures are used.
Document layouts are Philippine-inspired specimens, not exact copies of official
agency forms. Certificates, permits, clearance letters, bills, clinic letters,
IDs, resumes, receipts, delivery notes and policies have distinct layouts.
IDs use landscape cards; sales receipts use a narrow receipt page; other PDFs use A4.

## Folders

- `documents/`: selectable-text PDFs, each under 5 MB.
- `images/`: PNG document alternatives and JPEG illustrated upload fixtures.
- `manifest.json`: filenames, purposes, dimensions, sizes and fictional identities.

Use these values in matching test accounts and applications:

- Gym: **IRON FIST COMBAT ACADEMY**
- Owner: **JUAN MIGUEL DELA CRUZ**
- Member/job applicant: **MARIA SANTOS REYES**
- Address: fictional address recorded in `manifest.json`.

## Upload mapping

| Upload | File(s) |
|---|---|
| User profile | `images/user-profile.jpg` |
| Gym cover | `images/gym-cover.jpg` |
| Gym gallery | Use supplied `images/facility-01.jpg` through `10.jpg` |
| Gym policies | `documents/gym-policy.pdf`, `membership-policy.pdf`, `privacy-policy.pdf` |
| DTI or SEC | `documents/dti-certificate.pdf` or `sec-certificate.pdf` |
| Mayor's permit | `documents/mayors-permit.pdf` |
| Barangay clearance | `documents/barangay-clearance.pdf` |
| Facility verification | `images/facility-01.jpg` through `10.jpg` |
| Owner ID | `documents/owner-id.pdf` |
| Owner selfie | Replacement not supplied |
| Utility bill | `documents/utility-bill.pdf` |
| Service cover | `images/service-cover.jpg` |
| Service gallery | `images/service-gallery-01.jpg` through `05.jpg` |
| Coach portrait | `images/coach-photo.jpg` |
| Leave attachment | `documents/leave-attachment.pdf` |
| Membership and athlete medical files | `documents/medical-certificate.pdf` |
| Job credentials | `documents/job-credentials.pdf` |
| Job valid ID | `documents/job-valid-id.pdf` |
| Purchase order proofs | `documents/purchase-receipt.pdf`, `delivery-note.pdf` |
| Session thumbnail | `images/session-thumbnail.jpg` |

Every PDF also has an `images/<name>-scan.png` alternative. Use PNGs when testing
image OCR. PDF text extraction and browser OCR are separate capabilities.
No claim is made that browser OCR, blur checks or an application upload has passed.
Generated illustrations are not authentic facility or identity evidence.
DTI and SEC are alternative fixtures, not assertions about a real business.

Dates target November 2026. Mayor's permit and barangay clearance expire
31 December 2026; update fixtures when testing later dates.
Video tours require an actual MP4, which this image/PDF pack does not include.
Separate gallery illustrations and the owner-selfie and delivery-photo illustrations
were removed. The supplied facility images can also be used for the gym gallery.

## Recreate

Requires Python with `Pillow`, `reportlab`, `pypdf`, and `pypdfium2`.
Run `python assets/generate_test_assets.py` only with empty output folders and
no existing manifest. The script refuses to overwrite existing fixture files.
The generator does not reproduce the imported user-provided JPEGs; preserve those
files separately before any full regeneration.
The original generator recreates the removed illustrations if run from scratch.
It verifies PDF text, page counts, image decoding and file-size limits.
No application records, database rows, or production code are changed.
