"""Generate fictional upload fixtures. Requires Pillow, reportlab, pypdf, pypdfium2."""

import json
import re
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont
from pypdf import PdfReader
import pypdfium2 as pdfium
from document_layouts import draw_document


ROOT = Path(__file__).resolve().parent
DOCS = ROOT / "documents"
IMAGES = ROOT / "images"
GYM = "IRON FIST COMBAT ACADEMY"
OWNER = "JUAN MIGUEL DELA CRUZ"
MEMBER = "MARIA SANTOS REYES"
ADDRESS = "Unit 4, 218 Training Avenue, Barangay Poblacion, Taguig City, Metro Manila 1630"
MARK = "SPECIMEN"
manifest = []


def display_text(value):
    value = value.replace("example.test", "example.invalid")
    return re.sub(r"\b(?:testing|tests|test|fictional)\b", "SPECIMEN", value,
                  flags=re.IGNORECASE)


def record(path, purpose, limit_mb, **extra):
    size = path.stat().st_size
    assert 0 < size < limit_mb * 1024 * 1024, path
    manifest.append(dict(file=path.relative_to(ROOT).as_posix(), purpose=purpose,
                         bytes=size, limit_mb=limit_mb, **extra))


def pdf(name, title, issuer, fields, paragraphs, purpose):
    title = display_text(title)
    issuer = display_text(issuer)
    fields = [(display_text(k), display_text(v)) for k, v in fields]
    paragraphs = [display_text(p) for p in paragraphs]
    path = DOCS / f"{name}.pdf"
    if path.exists() or (IMAGES / f"{name}-scan.png").exists():
        raise FileExistsError(f"Refusing to overwrite {name}")
    title = draw_document(path, name, fields, GYM, OWNER, MEMBER, ADDRESS)
    reader = PdfReader(path)
    assert len(reader.pages) == 1, f"{name}: expected one page"
    text = reader.pages[0].extract_text()
    assert " ".join(title.split()) in " ".join(text.split())
    assert not re.search(r"\b(?:test|fictional)\b", text, re.IGNORECASE), path
    record(path, purpose, 5, pages=1, selectable_text=True)
    rendered = pdfium.PdfDocument(str(path))
    page = rendered[0]
    bitmap = page.render(scale=2)
    image = bitmap.to_pil().convert("RGB")
    scan = IMAGES / f"{name}-scan.png"
    image.save(scan)
    bitmap.close()
    page.close()
    rendered.close()
    record(scan, f"Image alternative: {purpose}", 5, width=image.width, height=image.height)


def font(size, bold=False):
    return ImageFont.truetype(
        str(Path("C:/Windows/Fonts") / ("arialbd.ttf" if bold else "arial.ttf")), size)


def portrait(draw, cx, cy, scale=1, coach=False):
    s = scale
    draw.ellipse((cx-115*s, cy-140*s, cx+115*s, cy+115*s), fill="#bc876b")
    draw.pieslice((cx-120*s, cy-155*s, cx+120*s, cy+80*s), 180, 360, fill="#25262b")
    draw.rounded_rectangle((cx-190*s, cy+100*s, cx+190*s, cy+450*s),
                           radius=int(70*s), fill="#af4636" if coach else "#263f53")
    for dx in (-42, 42):
        draw.ellipse((cx+(dx-7)*s, cy-20*s, cx+(dx+7)*s, cy-6*s), fill="#202731")
    draw.arc((cx-40*s, cy+25*s, cx+40*s, cy+65*s), 0, 180, fill="#593f35", width=max(1,int(4*s)))


def illustration(name, title, variant=0, kind="gym", size=(1600, 1200)):
    title = display_text(title)
    path = IMAGES / f"{name}.jpg"
    if path.exists():
        raise FileExistsError(path)
    w, h = size
    image = Image.new("RGB", size, "#dce4e5")
    d = ImageDraw.Draw(image)
    if kind in ("portrait", "coach", "selfie"):
        d.rectangle((0, h*.65, w, h), fill="#c3cecd")
        portrait(d, w/2, h*.38, min(w/520, h/850), kind == "coach")
        if kind == "selfie":
            d.rounded_rectangle((w*.60, h*.58, w*.95, h*.76), radius=12,
                                fill="#faf5e8", outline="#34495a", width=4)
            d.text((w*.62, h*.60), "SPECIMEN ID", font=font(26, True), fill="#a12626")
            d.text((w*.62, h*.66), "JUAN MIGUEL", font=font(20), fill="#233340")
            d.text((w*.62, h*.70), "DELA CRUZ", font=font(20), fill="#233340")
    else:
        d.rectangle((0, h*.62, w, h), fill="#687c83")
        for x in range(0, w, 160):
            d.line((x, h*.62, w/2+(x-w/2)*1.9, h), fill="#8b9a9e", width=3)
        for y in range(int(h*.7), h, 85):
            d.line((0, y, w, y), fill="#8b9a9e", width=3)
        for x in (100, 500, 900, 1300):
            d.rectangle((x, 170, x+160, 390), fill="#f0e5c4", outline="#627780", width=8)
        d.rectangle((70, 50, w-70, 140), fill="#233340")
        d.text((95, 68), GYM, font=font(40, True), fill="#ffffff")
        if variant % 4 == 0:
            for i in range(4):
                x = 210+i*330
                d.line((x, 150, x, 430), fill="#34414c", width=9)
                d.rounded_rectangle((x-65, 420, x+65, 790), radius=55,
                                    fill="#a74235", outline="#652f2a", width=6)
                d.text((x-43, 560), "IFCA", font=font(26, True), fill="#f8e6d2")
        elif variant % 4 == 1:
            d.polygon([(160,760),(1130,650),(1470,940),(430,1050)], fill="#b4c3c8")
            for x,y in [(160,760),(1130,650),(1470,940),(430,1050)]:
                d.line((x,y,x,y-300), fill="#273c50", width=18)
            for offset in (90,170,250):
                points = [(160,760-offset),(1130,650-offset),(1470,940-offset),
                          (430,1050-offset),(160,760-offset)]
                d.line(points, fill="#a74235", width=8)
        elif variant % 4 == 2:
            for i in range(5):
                x,y = 150+i*280, 650+(i%2)*140
                d.line((x-70,y,x+100,y), fill="#d4dadd", width=18)
                for dx in (-65,75):
                    d.rounded_rectangle((x+dx-25,y-75,x+dx+25,y+75),
                                        radius=12, fill="#263644")
            d.rectangle((100,900,650,960), fill="#a74235")
        else:
            for i in range(4):
                x=130+i*350
                d.rectangle((x,460,x+250,830), fill="#b0c1c3", outline="#34495a", width=6)
                d.line((x+125,470,x+125,820), fill="#34495a", width=4)
                d.ellipse((x+90,610,x+105,625), fill="#34495a")
        if kind == "delivery":
            for x in (300,620,940):
                d.rectangle((x,720,x+230,970), fill="#bd9461", outline="#795c39", width=5)
                d.text((x+18,800), "PO-SPECIMEN-0034", font=font(18,True), fill="#233340")
    d.rectangle((0,h-100,w,h), fill="#233340")
    d.text((24,h-86), title, font=font(max(17,min(32,w//29)),True), fill="#ffffff")
    d.text((24,h-43), "ILLUSTRATION",
           font=font(max(12,min(23,w//37))), fill="#f0c8ac")
    image.save(path, quality=88, subsampling=0)
    with Image.open(path) as check:
        check.verify()
    record(path, title, 2 if kind in ("portrait","coach") else 4, width=w, height=h,
           synthetic_illustration=True)


def main():
    DOCS.mkdir(exist_ok=True)
    IMAGES.mkdir(exist_ok=True)
    shared = [("Business name",GYM),("Owner",OWNER),("Premises",ADDRESS)]
    for name,title,ref,expires in [
        ("dti-certificate","Business Name Registration (DTI test)","TEST-DTI-0001","13 January 2029"),
        ("sec-certificate","Incorporation Certificate (SEC test)","TEST-SEC-0001","Not applicable"),
        ("mayors-permit","Business Permit (Mayor's permit test)","TEST-PERMIT-2026-0001","31 December 2026"),
        ("barangay-clearance","Barangay Business Clearance (test)","TEST-BRGY-2026-0001","31 December 2026"),
    ]:
        pdf(name,title,"Fictional Business Records Office / Sandbox",
            shared+[("Reference",ref),("Date issued","05 January 2026"),("Expiry date",expires)],
            ["This synthetic record is provided solely to exercise business verification uploads.",
             "Business activity: combat sports instruction, fitness classes and supervised training.",
             "The DTI and SEC files are alternative test routes. This pack does not assert both registrations.",
             "No government body issued this sample. All names, identifiers and approvals are fictional."],
            f"Gym verification: {name}")
    pdf("utility-bill","Electricity Statement (test)","Sample Energy Services / Fictional supplier",
        shared+[("Account","TEST-UTILITY-0001"),("Billing period","01-31 October 2026"),
                ("Consumption","1,125 kWh"),("Amount due","PHP 12,480.55"),("Due date","15 November 2026")],
        ["Test breakdown: energy PHP 9,700.00; distribution PHP 1,500.00; other charges PHP 1,280.55.",
         "Not a real utility bill. No payment is requested and no bank or payment information exists."],
        "Gym verification: utility_bill")
    for name,person,purpose in [("owner-id",OWNER,"Gym verification: owner_id"),
                               ("job-valid-id",MEMBER,"Job application: valid_id")]:
        pdf(name,"Fictional Identity Record","Sandbox Identity Office / Not a government ID",
            [("Full name",person),("Date of birth","07 March 1992" if person==OWNER else "19 June 1995"),
             ("Address",ADDRESS),("Identifier","TEST-IDENTITY-0001" if person==OWNER else "TEST-IDENTITY-0002"),
             ("Date issued","01 January 2026"),("Expiry date","31 December 2028")],
            ["Use only for file-upload and name-matching tests. Not valid for identity verification.",
             "This deliberately non-official layout has no agency branding, security feature, real ID number or signature."],
            purpose)
    for name,title,details in [
        ("medical-certificate","Pre-participation Medical Certificate (test)",
         "Synthetic assessment outcome: cleared for supervised training in this test scenario. "
         "No actual examination occurred. This statement is not medical advice or clearance."),
        ("leave-attachment","Medical Leave Supporting Document (test)",
         "Synthetic leave period: 09-11 November 2026, three calendar days. "
         "Reason: fictional short-term illness. Return date: 12 November 2026."),
    ]:
        pdf(name,title,"Sample Training Health Clinic / Fictional clinic",
            [("Patient",MEMBER),("Date of birth","19 June 1995"),("Issue date","09 November 2026"),
             ("Clinician","Dr. Alex Sample (fictional)"),("Reference",f"TEST-{name.upper()}-0001")],
            [details,"For application, athlete-record and leave attachment testing only. "
             "No real clinical records, physician licence or authentic signature are included."],
            "Membership / athlete medical file" if name=="medical-certificate" else "Leave attachment")
    pdf("job-credentials","Coaching Resume and Credentials (test)","Synthetic applicant profile",
        [("Applicant",MEMBER),("Email","maria.reyes@example.test"),("Experience","6 fictional years"),
         ("Specialisation","Boxing fundamentals; Muay Thai conditioning"),
         ("Availability","Monday-Friday, 09:00-17:00")],
        ["Experience: Sample Combat Club, assistant coach, 2020-2023; Practice Training Studio, coach, 2023-2026.",
         "Responsibilities: plan beginner sessions, lead warmups, monitor equipment and record attendance.",
         "Synthetic qualifications: TEST Coaching Foundations (2024); TEST First Aid Workshop (2025). "
         "Neither entry represents an accredited award.",
         "References: not provided. All experience and credentials are fictional."],
        "Job application: credentials")
    pdf("purchase-receipt","Supplier Receipt (test)","SUMMIT SPORTS SUPPLY / Fictional supplier",
        [("Buyer",GYM),("Purchase order","PO-TEST-0034"),("Receipt","TEST-RECEIPT-1189"),
         ("Date","08 November 2026"),("Boxing gloves","6 pairs x PHP 1,800.00 = PHP 10,800.00"),
         ("Hand wraps","24 x PHP 175.00 = PHP 4,200.00"),
         ("Mouthguards","12 x PHP 312.50 = PHP 3,750.00"),("Total","PHP 18,750.00")],
        ["Synthetic payment status: paid for fixture demonstration only. No real transaction occurred.",
         "Inspect quantities and totals when exercising procurement attachments."],"Purchase order proof")
    pdf("delivery-note","Delivery Note (test)","SUMMIT SPORTS SUPPLY / Fictional supplier",
        [("Deliver to",GYM),("Address",ADDRESS),("Purchase order","PO-TEST-0034"),
         ("Date","08 November 2026"),("Contents","6 pairs gloves; 24 hand wraps; 12 mouthguards"),
         ("Received by",OWNER)],
        ["Synthetic delivery: all listed items received, packaging intact. No physical delivery occurred.",
         "Use with purchase-receipt.pdf and po-delivery-photo.jpg."],"Purchase order delivery proof")
    for name,title,body in [
        ("gym-policy","Gym Rules and Safety Policy",
         "Check in before training. Wear clean equipment. Report damaged gear immediately. "
         "Spar only under coach supervision. Stop activity and notify staff if pain or dizziness occurs."),
        ("membership-policy","Membership and Cancellation Policy",
         "Test membership period: one calendar month. Renewal is not automatic. "
         "Cancellation requests are recorded by staff. Freeze requests require review. "
         "This fixture does not set the application's actual billing rules."),
        ("privacy-policy","Privacy Notice",
         "Synthetic sample only. Test records contain invented data. "
         "Do not upload real identity or medical records during fixture testing. "
         "Delete test uploads after testing according to the developer's chosen cleanup process."),
    ]:
        pdf(name,title,GYM+" / Sample policy",
            [("Version","TEST 1.0"),("Effective date","01 November 2026"),("Scope","Sandbox only")],
            [body,"This sample is not a legal agreement, professional advice or production policy."],
            "Gym policy PDF")
    illustration("user-profile",OWNER,kind="portrait",size=(768,768))
    illustration("coach-photo","FICTIONAL COACH",kind="coach",size=(900,1200))
    illustration("owner-selfie",OWNER,kind="selfie",size=(1200,1600))
    illustration("gym-cover","Gym interior",variant=1,size=(1920,1080))
    illustration("service-cover","Muay Thai training area",variant=0)
    illustration("session-thumbnail","Supervised boxing session area",variant=1,size=(1600,900))
    illustration("po-delivery-photo","Delivery illustration / PO-TEST-0034",kind="delivery",variant=2)
    for prefix,count in [("gym-gallery",12),("facility",10),("service-gallery",5)]:
        for i in range(1,count+1):
            scene=["Heavy bags","Boxing ring","Strength equipment","Changing area"][(i-1)%4]
            illustration(f"{prefix}-{i:02}",f"{scene} / test view {i:02}",variant=i-1)
    manifest_path=ROOT/"manifest.json"
    if manifest_path.exists():
        raise FileExistsError(manifest_path)
    manifest_path.write_text(json.dumps(dict(
        warning=MARK, gym=GYM, owner=OWNER, member=MEMBER, address=ADDRESS,
        omitted=["MP4 video tour: not part of requested image/PDF pack"],
        assets=manifest),indent=2),encoding="utf-8")
    print(f"Verified {len(manifest)} files: "
          f"{sum(x['file'].endswith('.pdf') for x in manifest)} PDFs, "
          f"{sum(not x['file'].endswith('.pdf') for x in manifest)} images.")


if __name__ == "__main__":
    main()
