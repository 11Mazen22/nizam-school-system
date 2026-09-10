"""Build-time only: generates the three release PDFs (User-Guide,
Administrator-Guide, Release-Notes) from plain content defined below.
Not part of the shipped application; run whenever the documentation content
changes, before packaging\\build.ps1 (which copies the output into the
installer's [Files] section)."""
import os
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import cm
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_LEFT, TA_CENTER
from reportlab.lib import colors
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak,
    ListFlowable, ListItem, HRFlowable
)

TEAL = colors.HexColor("#1F6F5C")
TEAL_DARK = colors.HexColor("#123D33")
INK = colors.HexColor("#1E2622")
INK_SOFT = colors.HexColor("#5B675F")
OUT_DIR = os.path.dirname(os.path.abspath(__file__))

styles = getSampleStyleSheet()
styles.add(ParagraphStyle("NizamTitle", parent=styles["Title"], textColor=TEAL_DARK,
                           fontSize=26, spaceAfter=6, alignment=TA_LEFT))
styles.add(ParagraphStyle("NizamSubtitle", parent=styles["Normal"], textColor=INK_SOFT,
                           fontSize=13, spaceAfter=28))
styles.add(ParagraphStyle("NizamH1", parent=styles["Heading1"], textColor=TEAL_DARK,
                           fontSize=17, spaceBefore=22, spaceAfter=10,
                           borderColor=TEAL, borderWidth=0, borderPadding=0))
styles.add(ParagraphStyle("NizamH2", parent=styles["Heading2"], textColor=INK,
                           fontSize=13, spaceBefore=14, spaceAfter=6))
styles.add(ParagraphStyle("NizamBody", parent=styles["Normal"], textColor=INK,
                           fontSize=10.3, leading=15, spaceAfter=8))
styles.add(ParagraphStyle("NizamNote", parent=styles["Normal"], textColor=INK_SOFT,
                           fontSize=9.5, leading=13, spaceAfter=8, leftIndent=10,
                           borderColor=TEAL, borderWidth=0, backColor=colors.HexColor("#EEF6F3"),
                           borderPadding=8))


def cover(title, subtitle, doc_label):
    return [
        Spacer(1, 3 * cm),
        Paragraph("NIZAM", ParagraphStyle("Brand", parent=styles["NizamTitle"], fontSize=34)),
        Paragraph("School Management System", styles["NizamSubtitle"]),
        HRFlowable(width="100%", thickness=1.2, color=TEAL, spaceAfter=18),
        Paragraph(title, ParagraphStyle("CoverTitle", parent=styles["Heading1"], fontSize=20, textColor=INK)),
        Paragraph(subtitle, styles["NizamBody"]),
        Spacer(1, 6 * cm),
        Paragraph("Version 1.0.0", ParagraphStyle("Ver", parent=styles["Normal"], textColor=INK_SOFT, fontSize=10)),
        Paragraph(doc_label, ParagraphStyle("Lbl", parent=styles["Normal"], textColor=INK_SOFT, fontSize=10)),
        PageBreak(),
    ]


def h1(text):
    return Paragraph(text, styles["NizamH1"])


def h2(text):
    return Paragraph(text, styles["NizamH2"])


def p(text):
    return Paragraph(text, styles["NizamBody"])


def note(text):
    return Paragraph(text, styles["NizamNote"])


def bullets(items):
    return ListFlowable(
        [ListItem(Paragraph(i, styles["NizamBody"]), leftIndent=6) for i in items],
        bulletType="bullet", start="circle", leftIndent=14,
    )


def numbered(items):
    return ListFlowable(
        [ListItem(Paragraph(i, styles["NizamBody"])) for i in items],
        bulletType="1", leftIndent=18,
    )


def build(filename, story):
    doc = SimpleDocTemplate(
        os.path.join(OUT_DIR, filename), pagesize=A4,
        topMargin=2.2 * cm, bottomMargin=2 * cm, leftMargin=2.2 * cm, rightMargin=2.2 * cm,
        title=filename.replace(".pdf", "").replace("-", " "),
    )
    doc.build(story)
    print("wrote", filename)


# ============================================================ User Guide
user_guide = cover(
    "User Guide",
    "How school staff use Nizam day to day: signing in, records, promotion, and reports.",
    "For teachers and administrative staff",
)
user_guide += [
    h1("1. Signing In"),
    p("Nizam opens in your web browser automatically when you launch it from the Desktop or Start Menu shortcut. "
      "Enter your username and password on the sign-in screen. After several incorrect attempts, an account is "
      "temporarily locked for a few minutes as a security precaution -- this is expected behavior, not an error."),
    note("Nizam runs entirely on this computer. It does not need an internet connection to work, and no school "
         "data ever leaves this PC."),

    h1("2. The Dashboard"),
    p("After signing in you land on the Dashboard: total students, teachers, classes, subjects and grades for the "
      "active academic year, a breakdown of students by religion, and a log of recent activity. If no academic "
      "year is active yet, an administrator needs to create and activate one first (Academic Years)."),

    h1("3. Academic Structure"),
    h2("Academic Years"),
    p("Nizam operates on exactly one active academic year at a time. All enrollment, workload, and report figures "
      "are scoped to it. A new year can be created “from previous,” which copies last year's classes and "
      "teacher assignments as an editable starting point. A year cannot be closed while any student still has an "
      "unresolved status in it -- Nizam will tell you exactly how many remain."),
    h2("Grades, Classes, and Subjects"),
    p("Grades and Subjects are simple lists managed inline. Classes belong to one grade within one academic year "
      "and carry an optional capacity used by class-density reports."),

    h1("4. Teachers"),
    p("Add a teacher with their name, phone, and email, and mark which subjects they are qualified to teach. An "
      "optional photo can be uploaded or removed at any time from the teacher's edit screen (JPG or PNG, 2 MB "
      "maximum). Archiving a teacher preserves their history -- it never deletes their record."),

    h1("5. Students"),
    p("Add a student with their personal details, religion, and an optional guardian phone number. Nizam "
      "generates a unique student code automatically (for example STU-2026-000118). An optional photo works the "
      "same way as for teachers."),
    h2("Promotion"),
    p("At the end of a school year, use Students → Promotion to move every student from one academic year to "
      "the next in a single guided batch:"),
    numbered([
        "Choose the source year (the year ending) and the target year (the year beginning).",
        "Review the proposed outcome for every student -- Promote, Repeat, Graduate, Transfer, or Withdraw -- and "
        "adjust any that need a different decision.",
        "Confirm. The batch either completes in full or changes nothing -- there is no partial or half-applied "
        "result.",
        "If a mistake is made, open the affected student's profile immediately afterward and use Undo Promotion "
        "to cleanly reverse just that one student.",
    ]),
    h2("Reassigning a Class Mid-Year"),
    p("To move a currently-enrolled student to a different section without waiting for the next promotion cycle, "
      "use Reassign Class from the student's profile. This only allows a move within the same grade and year."),

    h1("6. Assignments & Workload"),
    p("Assign a teacher to teach a subject in a class with a weekly period count. The Workload Summary totals "
      "each teacher's periods against the expected weekly capacity for the year."),

    h1("7. Reports"),
    p("Six built-in reports cover religion statistics, class rosters, class density, teacher workload, staffing "
      "shortages, and teachers-by-subject. Every report can be printed directly, or exported to PDF or Excel with "
      "full Arabic right-to-left formatting where applicable."),

    h1("8. Activity Log"),
    p("A running history of consequential actions -- who did what, and when. Administrators see every user's "
      "activity; other staff see only their own actions."),

    h1("9. Roles"),
    p("Nizam has two roles. Administrators have full access, including Users, Settings, and Backup & Restore. "
      "Staff can manage students, teachers, and academic data, but cannot reach administrator-only screens -- "
      "this is enforced by Nizam itself, not just hidden menus."),
]
build("User-Guide.pdf", user_guide)

# ============================================================ Administrator Guide
admin_guide = cover(
    "Administrator Guide",
    "Installing, configuring, backing up, updating, and maintaining Nizam.",
    "For the person responsible for the school's Nizam installation",
)
admin_guide += [
    h1("1. Installation"),
    p("Nizam is installed from a single file: <b>Nizam-Setup-1.0.0.exe</b>. No other software needs to be "
      "installed beforehand -- Nizam brings its own web server and database with it."),
    numbered([
        "Copy Nizam-Setup-1.0.0.exe onto the school computer (a USB drive works fine -- no internet connection "
        "is needed at any point).",
        "Double-click it and follow the installer. Windows may ask for permission to make changes -- this is "
        "expected for installing new software and only happens this once.",
        "Choose whether to also create a Desktop shortcut, then click Install.",
        "When installation finishes, Nizam launches automatically.",
    ]),
    note("The installer sets up two small background services (the local web server and database) that start "
         "automatically with Windows. Day-to-day, nobody needs to start or stop anything by hand."),

    h1("2. First Launch: the Setup Wizard"),
    p("The very first time Nizam runs, it walks through a one-time Setup Wizard:"),
    numbered([
        "Environment check (automatic -- nothing to do).",
        "School details: name in English and Arabic, address, and phone.",
        "First academic year: a label such as 2026/2027 and its start/end dates.",
        "The first Administrator account: full name, username, and password.",
    ]),
    p("Once finished, the Setup Wizard is permanently locked and Nizam goes straight to the sign-in screen from "
      "then on."),

    h1("3. Users & Permissions"),
    p("From the Users screen (Administrator only), add an account for every staff member who needs access, "
      "choosing Administrator or Staff for each. Leave the password field blank when editing an existing user to "
      "keep their current password unchanged. Nizam will not let the last active Administrator account be "
      "archived or demoted -- there must always be at least one Administrator who can sign in."),

    h1("4. Settings"),
    p("The Settings screen is grouped into five pages so a routine change never sits next to a security-related "
      "one:"),
    bullets([
        "<b>Profile</b> -- school name, address, phone, an optional logo, and an optional custom report footer.",
        "<b>Localization</b> -- the language a new sign-in session starts in.",
        "<b>Academic Rules</b> -- default expected weekly teaching capacity, and student/teacher ID patterns.",
        "<b>Security</b> -- maximum login attempts, lockout duration, and session idle timeout.",
        "<b>Backup</b> -- where one-click backups are saved.",
    ]),

    h1("5. Backup & Restore"),
    p("Take a manual backup any time from Backup & Restore -- it produces a single file containing the complete "
      "database. Do this before any major operation such as a Promotion or a Restore."),
    note("Restoring a backup completely replaces the current data with what is in the file, and signs everyone "
         "out for safety. Nizam automatically saves an emergency backup of the current data immediately before "
         "any restore, so a mistaken or corrupted restore attempt can always be recovered from."),

    h1("6. Updates"),
    p("To install a newer version of Nizam, simply run the new Nizam-Setup-x.x.x.exe on the same computer. An "
      "update never touches the database, uploaded photos, the school logo, settings, user accounts, activity "
      "history, or existing backups -- only the application program files are replaced. There is no need to "
      "uninstall the previous version first."),

    h1("7. Troubleshooting"),
    h2("“Nizam could not start its local service”"),
    bullets([
        "Try launching Nizam again after restarting the computer.",
        "Check that no other software is already using the same network ports.",
        "If the problem continues, check the log files under the Nizam data folder "
        "(shown by your administrator or IT support) for the technical detail.",
    ]),
    h2("Forgotten password"),
    p("Another Administrator can reset it from the Users screen. If no Administrator account is reachable, "
      "restoring the most recent backup that still had a working account is the recovery path -- this is why a "
      "recent backup always matters."),

    h1("8. Uninstallation"),
    p("Uninstall Nizam from Windows Settings → Apps, like any other program. You will be asked whether to "
      "keep or permanently remove the school's data (database, photos, backups, settings). Keeping the data is "
      "the default and recommended choice -- it also means a fresh install afterward can pick up exactly where "
      "things left off. Choosing to remove everything is permanent and cannot be undone."),
]
build("Administrator-Guide.pdf", admin_guide)

# ============================================================ Release Notes
release_notes = cover(
    "Release Notes",
    "What's included in this release.",
    "Version 1.0.0",
)
release_notes += [
    h1("Nizam 1.0.0"),
    p("The first production release of Nizam, a complete, fully offline school management system for "
      "independent K-12 schools."),

    h2("Included Modules"),
    bullets([
        "Academic Years, Grades, Classes, and Subjects",
        "Teachers, with photos and subject qualifications",
        "Students, with photos, enrollment history, and a guided Promotion workflow",
        "Teacher-class-subject Assignments and Workload reporting",
        "Six built-in Reports with print, PDF, and Excel export (full Arabic right-to-left support)",
        "Users and role-based permissions (Administrator / Staff)",
        "Settings (Profile, Localization, Academic Rules, Security, Backup)",
        "Activity Log",
        "Backup & Restore, with automatic pre-restore safety snapshots",
    ]),

    h2("System Requirements"),
    bullets([
        "Windows 10 or Windows 11, 64-bit",
        "Approximately 700 MB of free disk space",
        "No internet connection required at any point, before or after installation",
    ]),

    h2("Language Support"),
    p("Full Arabic (right-to-left) and English (left-to-right) interfaces, switchable at any time."),

    h2("Data & Privacy"),
    p("All school data is stored locally on the installation computer. Nizam makes no network connections beyond "
      "its own local web server on this machine, and includes no analytics, telemetry, or online license checks."),
]
build("Release-Notes.pdf", release_notes)

print("All three PDFs generated.")
