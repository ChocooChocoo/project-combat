"""Document-specific specimen layouts, inspired by common Philippine paperwork."""

from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.lib.utils import simpleSplit
from reportlab.pdfgen import canvas
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont


INK = "#202020"
MUTED = "#555555"
RED = "#555555"
BLUE = "#202020"
GREEN = "#202020"

for family, file in [
    ("DocumentSans", "arial.ttf"), ("DocumentSansBold", "arialbd.ttf"),
    ("DocumentSerif", "times.ttf"), ("DocumentSerifBold", "timesbd.ttf"),
    ("DocumentSerifItalic", "timesi.ttf"),
]:
    pdfmetrics.registerFont(TTFont(family, "C:/Windows/Fonts/" + file))


def print_font(font, size):
    if size >= 15 and font == "Helvetica-Bold":
        return "DocumentSerifBold"
    return {
        "Helvetica": "DocumentSans", "Helvetica-Bold": "DocumentSansBold",
        "Times-Roman": "DocumentSerif", "Times-Bold": "DocumentSerifBold",
        "Times-Italic": "DocumentSerifItalic",
    }.get(font, font)


class Sheet:
    def __init__(self, path, size, title):
        self.w, self.h = size
        self.c = canvas.Canvas(str(path), pagesize=size)
        self.c.setTitle(f"SPECIMEN: {title}")
        self.c.setAuthor("Combat Platform synthetic fixtures")

    def text(self, x, y, text, size=10, font="Helvetica", color=INK, width=None):
        font = print_font(font, size)
        size = min(size, 22)
        lines = [line for paragraph in text.splitlines()
                 for line in (simpleSplit(paragraph, font, size, width or self.w - x - 48) or [""])]
        self.c.setFillColor(colors.HexColor(INK if color == "#ffffff" else color))
        self.c.setFont(font, size)
        for line in lines:
            assert 0 <= x < self.w and 20 < y < self.h, (text, x, y)
            assert self.c.stringWidth(line, font, size) + x <= self.w - 10, line
            self.c.drawString(x, self.h - y, line)
            y += size * 1.45
        return y

    def center(self, y, text, size=12, font="Helvetica", color=INK):
        font = print_font(font, size)
        size = min(size, 22)
        width = self.c.stringWidth(text, font, size)
        return self.text((self.w - width) / 2, y, text, size, font, color, width + 1)

    def line(self, x, y, end, color="#bcc6ca", weight=0.6):
        self.c.setStrokeColor(colors.HexColor(color))
        self.c.setLineWidth(weight)
        self.c.line(x, self.h - y, end, self.h - y)

    def box(self, x, y, width, height, fill, stroke=None):
        self.c.setFillColor(colors.white)
        self.c.setStrokeColor(colors.HexColor("#777777"))
        self.c.setLineWidth(0.5)
        self.c.rect(x, self.h - y - height, width, height, fill=1, stroke=bool(stroke))
        if fill in (BLUE, GREEN) and width > 100:
            self.line(max(x, 24), y + height, min(x + width, self.w - 24), INK, 0.8)

    def field(self, x, y, label, value, width=235):
        self.text(x, y, label, 9, color=MUTED, width=width)
        end = self.text(x, y + 18, value, 11, "Helvetica", width=width)
        return end + 13

    def signatory(self, x, y, caption, width=190):
        signature = {
            "REGISTRY OFFICER": "Elena M. Santos",
            "REGISTRATION OFFICER": "Rafael D. Mercado",
            "APPROVING OFFICER": "Andres V. Navarro",
            "BARANGAY AUTHORIZED OFFICER": "Leonardo P. Ramos",
            "DR. ALEX SAMPLE": "Alex Sample",
            "RELEASED BY": "Carlo R. Mendoza",
            "RECEIVED BY": "Juan M. Dela Cruz",
        }.get(caption, "Authorized signatory")
        # Original vector strokes, not a tracing of a person's signature.
        c = self.c
        c.saveState()
        c.translate(x+7, self.h-y+7)
        c.scale((width-14)/180, 0.82)
        c.setStrokeColor(colors.HexColor("#24334b"))
        c.setLineCap(1)
        c.setLineJoin(1)
        variant = sum(map(ord, signature)) % 7
        paths = [
            [(3,18), (19,49,48,58,44,31), (40,7,7,-4,9,10),
             (14,27,57,30,75,25)],
            [(24,2), (40,21,67,53,69,49), (72,43,47,15,53,13),
             (59,11,69,31,71,24), (72,14,78,14,83,23)],
            [(79,16), (87,32,91,29,87,19), (83,8,98,15,101,23),
             (105,34,99,7,110,15), (115,19,118,28,118,19),
             (119,10,127,16,131,24)],
            [(125,13), (144,31,152,55,148,51), (141,44,131,8,139,12),
             (151,18,156,29,155,22), (151,9,164,15,170,24)],
            [(10,0), (50,8,105,17,175,27)],
        ]
        for index, segments in enumerate(paths):
            c.setLineWidth(0.85 if index == 4 else 1.15 + (index % 2)*0.3)
            p = c.beginPath()
            start = segments[0]
            p.moveTo(start[0], start[1]+variant*0.35)
            for coords in segments[1:]:
                p.curveTo(*coords)
            c.drawPath(p)
        c.restoreState()
        self.line(x, y, x + width, INK)
        self.text(x, y-48, signature, 7, color=MUTED, width=width)
        self.text(x, y + 15, caption, 9, "Helvetica-Bold", width=width)
        self.text(x, y + 29, "SPECIMEN SIGNATURE / NOT AUTHENTICATED", 7, color=MUTED, width=width)

    def finish(self):
        self.line(24, self.h - 43, self.w - 24)
        self.center(self.h - 28, "SPECIMEN / NO LEGAL OR TRANSACTIONAL EFFECT", 7, color=RED)
        self.c.save()


def draw_document(path, name, fields, gym, owner, member, address):
    values = dict(fields)
    titles = {
        "dti-certificate": "CERTIFICATE OF BUSINESS NAME REGISTRATION",
        "sec-certificate": "CERTIFICATE OF INCORPORATION",
        "mayors-permit": "BUSINESS PERMIT",
        "barangay-clearance": "BARANGAY BUSINESS CLEARANCE",
        "utility-bill": "STATEMENT OF ACCOUNT",
        "owner-id": "IDENTITY RECORD",
        "job-valid-id": "IDENTITY RECORD",
        "medical-certificate": "MEDICAL CERTIFICATE",
        "leave-attachment": "MEDICAL CERTIFICATE",
        "job-credentials": "PROFESSIONAL PROFILE",
        "purchase-receipt": "SALES RECEIPT",
        "delivery-note": "DELIVERY RECEIPT",
        "gym-policy": "GYM RULES AND SAFETY POLICY",
        "membership-policy": "MEMBERSHIP TERMS",
        "privacy-policy": "PRIVACY NOTICE",
    }
    title = titles[name]
    size = (540, 340) if name in ("owner-id", "job-valid-id") else (
        (270, 700) if name == "purchase-receipt" else A4)
    s = Sheet(path, size, title)
    w, h = size
    if name == "dti-certificate":
        s.box(25, 25, w-50, h-82, "#fffdf4", "#998859")
        s.line(40, 40, w-40, INK, 0.5)
        s.center(76, "BUSINESS NAME REGISTRY", 17, "Times-Bold", INK)
        s.center(98, "Philippine business registration specimen", 10, "Times-Roman")
        s.center(137, "SPECIMEN", 10, "Helvetica-Bold", RED)
        s.center(183, "CERTIFICATE OF BUSINESS", 23, "Times-Bold")
        s.center(214, "NAME REGISTRATION", 23, "Times-Bold")
        s.center(269, "This specimen records the business name", 12, "Times-Roman")
        s.center(311, gym, 19, "Times-Bold")
        s.center(353, "Proprietor", 10, "Times-Italic")
        s.center(377, owner, 15, "Times-Bold")
        y = s.text(90, 422, address, 12, "Times-Roman", width=w-180)
        s.line(85, y+13, w-85, "#998859")
        s.field(85, y+42, "Registration reference", values["Reference"], 210)
        s.field(330, y+42, "Territorial scope", "City / municipality", 175)
        s.field(85, y+105, "Date of issue", values["Date issued"], 210)
        s.field(330, y+105, "Valid until", values["Expiry date"], 175)
        s.text(85, y+178, "Illustrative record only. No agency has issued this certificate.",
               10, "Times-Italic", width=w-170)
        s.text(85, 666, "Business classification: sports and recreation instruction. "
               "Form of ownership: sole proprietorship.", 10, "Times-Roman", width=210)
        s.signatory(315, 717, "REGISTRY OFFICER")
    elif name == "sec-certificate":
        s.center(64, "CORPORATE REGISTRATION OFFICE", 15, "Times-Bold", BLUE)
        s.center(87, "Philippine corporate-record specimen", 10, "Times-Roman")
        s.line(65, 111, w-65, BLUE, 2)
        s.text(68, 148, "Registration No. " + values["Reference"], 10, "Times-Roman")
        s.text(68, 173, "SPECIMEN", 9, "Helvetica-Bold", RED)
        s.center(231, title, 21, "Times-Bold")
        y = s.text(75, 296, "This specimen illustrates the registration of a domestic corporation "
                   "under a separate corporate application route.", 12, "Times-Roman", width=w-150)
        s.center(y+41, gym, 18, "Times-Bold")
        y = s.text(75, y+93, "Registered office: " + address, 12, "Times-Roman", width=w-150)
        y = s.text(75, y+27, "Named representative: " + owner + ". This corporate specimen is an "
                   "alternative to the sole-proprietor registration document, not a second registration "
                   "of an actual enterprise.", 12, "Times-Roman", width=w-150)
        s.text(75, y+32, "Issued for document-upload demonstrations on 05 January 2026. "
               "No registration rights, authority, or corporate existence are conferred.",
               12, "Times-Roman", width=w-150)
        s.text(75, 620, "Corporate purpose: operation of a sports instruction and conditioning "
               "facility. Classification: domestic stock corporation.", 10, "Times-Roman", width=w-150)
        s.signatory(310, 685, "REGISTRATION OFFICER")
    elif name == "mayors-permit":
        s.box(0, 0, w, 112, GREEN)
        s.center(45, "CITY OF TAGUIG", 19, "Helvetica-Bold", "#ffffff")
        s.center(70, "BUSINESS PERMITS AND LICENSING / SPECIMEN", 10, color="#ffffff")
        s.center(98, "CALENDAR YEAR 2026", 11, "Helvetica-Bold", "#ffffff")
        s.text(43, 155, title, 29, "Helvetica-Bold", GREEN)
        s.text(43, 181, "Permit No. " + values["Reference"], 11)
        s.box(w-145, 132, 100, 56, "#e8f0e9")
        s.text(w-128, 166, "2026", 26, "Helvetica-Bold", GREEN)
        y = s.field(43, 233, "Registered business name", gym, w-86)
        y = s.field(43, y+12, "Owner / applicant", owner, w-86)
        y = s.field(43, y+12, "Business location", address, w-86)
        s.field(43, y+12, "Line of business", "Combat sports / fitness instruction", 250)
        s.field(338, y+12, "Permit category", "Renewal", 200)
        s.line(43, 515, w-43, GREEN)
        s.field(43, 545, "Issued on", "05 January 2026")
        s.field(338, 545, "Valid until", "31 December 2026", 200)
        s.text(43, 622, "Conditions: maintain safe premises, keep exits accessible, and observe "
               "applicable operating requirements. This specimen does not authorize business operations.",
               11, width=w-86)
        s.text(43, 684, "Operating hours: Monday-Saturday, 06:00-21:00. "
               "Declared floor area: 240 square metres.", 10, width=w-86)
        s.signatory(315, 734, "APPROVING OFFICER")
    elif name == "barangay-clearance":
        for y, text, fs in [(49, "CITY OF TAGUIG", 11), (71, "BARANGAY POBLACION", 17),
                            (92, "OFFICE OF THE BARANGAY / SPECIMEN", 9)]:
            s.center(y, text, fs, "Times-Bold")
        s.line(62, 114, w-62, INK, 1.5)
        s.text(62, 151, "Control No. " + values["Reference"], 10)
        s.text(62, 174, "Date: 05 January 2026", 10)
        s.center(229, title, 20, "Times-Bold")
        s.text(62, 278, "TO WHOM IT MAY CONCERN:", 12, "Times-Bold")
        y = s.text(62, 320, "This clearance specimen identifies " + gym + ", represented by "
                   + owner + ", with premises at " + address + ".", 12, "Times-Roman", width=w-124)
        y = s.text(62, y+24, "Purpose: business permit application. Proposed business activity: "
                   "combat sports and fitness training. The premises and applicant details are "
                   "provided as illustrative data only.", 12, "Times-Roman", width=w-124)
        s.text(62, y+24, "Validity shown: until 31 December 2026. This document has not been "
               "issued or endorsed by a barangay office and is not an operational clearance.",
               12, "Times-Roman", width=w-124)
        s.signatory(313, 657, "BARANGAY AUTHORIZED OFFICER")
        s.text(62, 578, "Application purpose: annual business permit renewal. "
               "Premises category: leased commercial unit. Record series: 2026.", 10,
               "Times-Roman", width=w-124)
        s.text(62, 733, "Community reference: SPECIMEN-BRGY-2026-0001", 9, color=MUTED)
    elif name == "utility-bill":
        s.text(40, 56, "SAMPLE ENERGY", 25, "Helvetica-Bold", BLUE)
        s.text(40, 79, "Electric distribution account / SPECIMEN", 10)
        s.text(40, 127, title, 16, "Helvetica-Bold")
        s.text(40, 153, "Billing period: 01-31 October 2026", 10)
        s.box(365, 111, 190, 126, "#e9f0f5")
        s.text(380, 137, "TOTAL AMOUNT DUE", 9, "Helvetica-Bold")
        s.text(380, 169, "PHP 12,480.55", 21, "Helvetica-Bold", BLUE)
        s.text(380, 200, "Due date: 15 November 2026", 10)
        s.text(40, 196, owner, 11, "Helvetica-Bold", width=305)
        s.text(40, 219, address, 10, width=305)
        s.line(40, 270, w-40)
        s.field(40, 298, "Account number", "SPECIMEN-UTILITY-0001")
        s.field(330, 298, "Meter number", "MTR-SP-1026", 210)
        s.field(40, 360, "Previous / current reading", "24,850 / 25,975 kWh")
        s.field(330, 360, "Consumption", "1,125 kWh", 210)
        s.box(40, 423, w-80, 28, BLUE)
        s.text(51, 442, "CHARGE DETAILS", 10, "Helvetica-Bold", "#ffffff")
        for y, label, amount in [(480,"Energy charges","9,700.00"),
                                  (514,"Distribution charges","1,500.00"),
                                  (548,"Other charges","1,280.55")]:
            s.text(51, y, label, 11)
            s.text(450, y, amount, 11, width=100)
            s.line(40, y+12, w-40)
        s.text(51, 602, "TOTAL (PHP)", 12, "Helvetica-Bold")
        s.text(438, 602, "12,480.55", 13, "Helvetica-Bold", width=110)
        s.box(40, 644, w-80, 66, "#f4f6f7")
        s.text(52, 665, "No payment requested. No collection account or payment barcode.",
               10, width=w-104)
        s.text(52, 688, "Illustrative billing data only; not proof of utility service.", 10)
        s.text(40, 746, "Reading dates: 30 September / 31 October 2026. "
               "Meter multiplier: 1. Account class: commercial.", 9, width=w-80)
    elif name in ("owner-id", "job-valid-id"):
        s.box(0, 0, w, 68, BLUE if name == "owner-id" else GREEN)
        s.text(23, 29, "COMMUNITY IDENTITY RECORD", 16, "Helvetica-Bold", "#ffffff")
        s.text(23, 50, "SPECIMEN / NOT A GOVERNMENT IDENTIFICATION CARD", 9, color="#ffffff")
        s.box(24, 90, 112, 139, "#e7ecee", "#aebcc4")
        s.c.setFillColor(colors.HexColor("#7c8f99"))
        s.c.circle(80, h-133, 24, fill=1, stroke=0)
        s.c.roundRect(42, h-218, 76, 58, 16, fill=1, stroke=0)
        s.text(30, 247, "PORTRAIT PLACEHOLDER", 7, color=MUTED, width=120)
        s.text(155, 101, title, 10, "Helvetica-Bold", BLUE)
        s.field(155, 124, "Name", values["Full name"], 355)
        s.field(155, 173, "Date of birth", values["Date of birth"], 160)
        s.field(337, 173, "Expiry", values["Expiry date"], 170)
        s.text(155, 227, address, 9, width=355)
        s.text(155, 267, values["Identifier"], 10, "Helvetica-Bold", width=355)
    elif name in ("medical-certificate", "leave-attachment"):
        s.center(59, "SAMPLE TRAINING HEALTH CLINIC", 17, "Times-Bold")
        s.center(79, "Sports assessment and primary care / SPECIMEN", 9)
        s.line(41, 106, w-41, GREEN, 1.5)
        s.text(370, 143, "Date: 09 November 2026", 10, width=185)
        s.center(202, title, 23, "Times-Bold")
        s.text(53, 254, "TO WHOM IT MAY CONCERN:", 11, "Times-Bold")
        y = s.field(53, 297, "Patient name", member, w-106)
        s.field(53, y+10, "Date of birth", "19 June 1995")
        s.field(331, y+10, "Reference", "SP-MED-2026-0001", 210)
        if name == "medical-certificate":
            body = ("Assessment scenario: cleared for supervised combat sports participation. "
                    "Training should be progressive and coached; any pain, dizziness or "
                    "new symptoms should prompt cessation of activity.")
        else:
            body = ("Leave scenario: rest advised from 09 November 2026 through 11 November "
                    "2026, with an intended return on 12 November 2026. Duration: three calendar days.")
        y = s.text(53, 442, body, 12, "Times-Roman", width=w-106)
        s.text(53, y+30, "No examination occurred. This specimen is not medical advice, "
               "fitness clearance, or an excuse letter for actual use.", 11, "Times-Italic", width=w-106)
        s.text(53, 608, "Purpose: " + ("membership pre-participation record." if name == "medical-certificate"
               else "supporting attachment for an employee leave request.") +
               " Issued upon the named person's request.", 10, "Times-Roman", width=w-106)
        s.signatory(323, 681, "DR. ALEX SAMPLE")
        s.text(323, 728, "Licence: not applicable (specimen)", 9, width=215)
    elif name == "job-credentials":
        s.c.setStrokeColor(colors.HexColor("#aaaaaa"))
        s.c.setLineWidth(0.5)
        s.c.line(177, h-48, 177, 92)
        s.text(204, 72, member, 23, "Helvetica-Bold", width=w-230)
        s.text(204, 104, "COMBAT SPORTS COACH", 11, "Helvetica-Bold", GREEN)
        s.text(24, 65, "CONTACT", 11, "Helvetica-Bold")
        s.text(24, 95, "maria.reyes@", 10, width=137)
        s.text(24, 110, "example.invalid", 10, width=137)
        s.text(24, 151, "Metro Manila", 10, width=137)
        s.text(24, 212, "SPECIALISATIONS", 10, "Helvetica-Bold", width=137)
        s.text(24, 244, "Boxing fundamentals\n", 10, width=137)
        s.text(24, 283, "Muay Thai conditioning", 10, width=137)
        s.text(24, 360, "AVAILABILITY", 10, "Helvetica-Bold")
        s.text(24, 392, "Monday-Friday\n09:00-17:00", 10, width=137)
        s.text(204, 162, title, 12, "Helvetica-Bold", GREEN)
        s.text(204, 189, "Six years of illustrative coaching experience. Beginner instruction, "
               "safe class management and athlete progress tracking.", 11, width=w-236)
        s.text(204, 287, "EXPERIENCE", 12, "Helvetica-Bold", GREEN)
        s.text(204, 318, "Coach / Practice Training Studio", 11, "Helvetica-Bold", width=w-236)
        s.text(204, 340, "2023-2026", 10, color=MUTED)
        s.text(204, 370, "Plan classes, deliver pad drills, record attendance and maintain "
               "safe equipment handling.", 11, width=w-236)
        s.text(204, 447, "Assistant Coach / Sample Combat Club", 11, "Helvetica-Bold", width=w-236)
        s.text(204, 470, "2020-2023", 10, color=MUTED)
        s.text(204, 507, "QUALIFICATIONS", 12, "Helvetica-Bold", GREEN)
        s.text(204, 537, "Coaching Foundations (2024)\nFirst Aid Workshop (2025)", 11, width=w-236)
        s.text(204, 622, "SPECIMEN PROFILE. Experience and qualifications are invented; "
               "no accredited award or employment reference is claimed.", 10, width=w-236)
    elif name == "purchase-receipt":
        s.center(43, "SUMMIT SPORTS SUPPLY", 13, "Helvetica-Bold")
        s.center(65, "Sports equipment and training supplies", 8)
        s.center(91, title, 15, "Helvetica-Bold")
        s.center(111, "SPECIMEN", 9, "Helvetica-Bold", RED)
        s.text(17, 148, "Receipt: SP-RECEIPT-1189", 9)
        s.text(17, 167, "Date: 08 November 2026", 9)
        s.text(17, 186, "PO: PO-SPECIMEN-0034", 9)
        s.text(17, 216, "Sold to: " + gym, 9, width=w-34)
        s.line(17, 253, w-17)
        y = 281
        for item, qty, price, total in [
            ("Boxing gloves", "6", "1,800.00", "10,800.00"),
            ("Hand wraps", "24", "175.00", "4,200.00"),
            ("Mouthguards", "12", "312.50", "3,750.00")]:
            s.text(17, y, item, 10, "Helvetica-Bold")
            s.text(17, y+19, f"{qty} x {price}", 9)
            s.text(185, y+19, total, 9, width=68)
            y += 61
        s.line(17, 453, w-17)
        s.text(17, 483, "TOTAL PHP", 12, "Helvetica-Bold")
        s.text(164, 483, "18,750.00", 12, "Helvetica-Bold", width=89)
        s.text(17, 527, "Illustrative payment status: PAID", 9, width=w-34)
        s.text(17, 561, "No sale occurred. Not a registered invoice or proof of payment. "
               "No tax registration is represented.", 9, width=w-34)
        s.text(17, 621, "Prepared by: Carlo R. Mendoza / specimen", 8, width=w-34)
    elif name == "delivery-note":
        s.text(38, 54, "SUMMIT SPORTS SUPPLY", 21, "Helvetica-Bold", BLUE)
        s.text(38, 78, "Equipment dispatch / SPECIMEN", 10)
        s.text(38, 123, title, 24, "Helvetica-Bold")
        s.field(38, 168, "Receipt number", "SP-DEL-0034")
        s.field(333, 168, "Delivery date", "08 November 2026", 220)
        s.field(38, 236, "Delivered to", gym, w-76)
        s.text(38, 297, address, 11, width=w-76)
        s.text(38, 347, "Purchase order: PO-SPECIMEN-0034", 11)
        s.box(38, 374, w-76, 31, BLUE)
        for x, label in [(48, "QTY"), (110, "UNIT"), (200, "DESCRIPTION")]:
            s.text(x, 395, label, 10, "Helvetica-Bold", "#ffffff")
        for y, qty, unit, description in [(442,"6","pairs","Boxing gloves"),
                                         (487,"24","pieces","Hand wraps"),
                                         (532,"12","pieces","Mouthguards")]:
            for x, value in [(48,qty),(110,unit),(200,description)]:
                s.text(x, y, value, 11)
            s.line(38, y+17, w-38)
        s.text(38, 595, "Condition: complete / packaging intact (illustrative entry).", 10)
        s.text(38, 623, "Package count: 3 cartons. Inspection: quantities checked against the "
               "purchase order. Shortage / damage noted: none in this scenario.", 10, width=w-76)
        s.signatory(38, 696, "RELEASED BY", 200)
        s.signatory(333, 696, "RECEIVED BY", 220)
    else:
        policy = {
            "gym-policy": [
                ("01 / Before training", "Check in before each session. Use clean clothing, wraps and protective "
                 "equipment. Tell the coach about restrictions relevant to safe participation."),
                ("02 / Conduct and supervision", "Respect staff and other members. Contact drills and sparring "
                 "require coach supervision and suitable protective equipment."),
                ("03 / Equipment and premises", "Return gear after use. Report damaged equipment immediately. "
                 "Keep walkways and emergency exits unobstructed."),
                ("04 / Incidents", "Stop activity if pain or dizziness occurs. Inform staff of injuries or "
                 "unsafe conditions. Follow the facility's emergency procedure.")],
            "membership-policy": [
                ("01 / Membership period", "Illustrative term: one calendar month. Start dates, entitlements "
                 "and prices are those recorded in the application, not established by this specimen."),
                ("02 / Renewal", "Renewal is not automatic in this example. Confirm renewal and payment "
                 "with authorized staff before the next membership period."),
                ("03 / Freeze and cancellation", "Submit requests to staff for review. Eligibility, effective "
                 "dates and any adjustments follow the gym's actual approved terms."),
                ("04 / Record of changes", "Keep the request date, staff decision and member acknowledgment "
                 "with the membership record.")],
            "privacy-policy": [
                ("01 / Information scope", "This specimen describes invented application records. Do not "
                 "supply actual identity, health or payment records for demonstration uploads."),
                ("02 / Access", "Limit records to personnel authorized for the relevant gym and business "
                 "function. Keep private attachments out of public galleries."),
                ("03 / Retention", "Choose a documented retention period for demonstration records. Remove "
                 "uploaded files and related copies when the demonstration is finished."),
                ("04 / Production notice", "A production privacy notice must identify the actual controller, "
                 "purposes, lawful bases, retention rules and contact route. This is not legal advice.")],
        }
        s.text(48, 58, gym, 12, "Helvetica-Bold", GREEN)
        s.text(48, 113, title, 24, "Helvetica-Bold", width=w-96)
        s.text(48, 146, "SPECIMEN 1.0  /  Effective 01 November 2026", 10, color=MUTED)
        s.line(48, 169, w-48, GREEN, 2)
        y = 216
        for heading, body in policy[name]:
            y = s.text(48, y, heading, 13, "Helvetica-Bold", GREEN) + 12
            y = s.text(48, y, body, 11, width=w-96) + 29
        s.text(48, 733, "Internal specimen. Not an executed agreement or production policy.", 9,
               color=MUTED, width=w-96)
    s.finish()
    return title
