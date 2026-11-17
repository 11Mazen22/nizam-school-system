"""Extract the 17 class timetables from the supplied PDFs.

The PDFs embed a nonstandard font-to-Unicode map, so naive text extraction
shuffles the page. The glyphs nevertheless sit at exact coordinates, which is
enough to rebuild the table cell by cell: find the period header columns, find
the weekday row anchors, then read the two text lines inside each cell
(subject on top, teacher underneath).

Run:  python tools/extract_timetables.py docs/source/timetables docs/source/data/timetables_17.json
"""

from __future__ import annotations

import json
import re
import sys
import unicodedata
from pathlib import Path

import pymupdf

DAYS = ["الأحد", "الاثنين", "الثلاثاء", "الأربعاء", "الخميس"]
DAY_ALIASES = {
    "األحد": "الأحد",
    "االثنين": "الاثنين",
    "اإلثنين": "الاثنين",
    "الثالثاء": "الثلاثاء",
    "األربعاء": "الأربعاء",
    "الخميس": "الخميس",
}

# Presentation-form ligatures the embedded font emits for lam-alef.
LIGATURES = {
    "األ": "الأ",
    "اإل": "الإ",
    "اآل": "الآ",
    "اال": "الا",
    "Ǆ": "ل",
}

TOKEN_FIXES = {
    "إىل": "إلى",
    "عىل": "على",
    "األوىل": "الأولى",
    "االوىل": "الاولى",
    "األسبوعي": "الأسبوعي",
    "األهرام": "الأهرام",
}


def clean(text: str) -> str:
    text = unicodedata.normalize("NFC", text)
    for bad, good in LIGATURES.items():
        text = text.replace(bad, good)
    return TOKEN_FIXES.get(text, text).strip()


def read_page(pdf: Path) -> tuple[list[dict], list[str]]:
    doc = pymupdf.open(pdf)
    page = doc[0]
    words = page.get_text("words")
    warnings: list[str] = []

    # Period columns: header digits 1..9 above the grid.
    headers = [w for w in words if w[1] < 205 and re.fullmatch(r"[1-9]", w[4])]
    columns: dict[int, float] = {}
    for w in headers:
        period = int(w[4])
        center = (w[0] + w[2]) / 2
        columns.setdefault(period, center)
    if len(columns) < 8:
        warnings.append(f"{pdf.name}: only {len(columns)} period columns found")

    # Weekday anchors on the right edge.
    anchors: dict[str, float] = {}
    for w in words:
        name = DAY_ALIASES.get(w[4], clean(w[4]))
        if name in DAYS and w[0] > 690 and w[1] > 205:
            anchors.setdefault(name, w[1])
    if len(anchors) < 5 and not re.search(r"B\d+", pdf.name):
        warnings.append(f"{pdf.name}: only {len(anchors)} weekday rows found")
    if re.search(r"B\d+", pdf.name) and len(anchors) != 3:
        warnings.append(f"{pdf.name}: pathway class should have 3 weekdays, found {len(anchors)}")

    ordered_days = sorted(anchors.items(), key=lambda kv: kv[1])
    gap = 59.0
    if len(ordered_days) > 1:
        gaps = [b[1] - a[1] for a, b in zip(ordered_days, ordered_days[1:])]
        gap = sum(gaps) / len(gaps)

    def band_words(day_y: float) -> list[tuple[float, float, str]]:
        low, high = day_y - 18, day_y + 40
        return [
            ((w[0] + w[2]) / 2, w[1], clean(w[4]))
            for w in words
            if low <= w[1] <= high and w[0] < 690 and w[4].strip()
        ]

    rows: list[dict] = []
    for day, day_y in ordered_days:
        cells = band_words(day_y)
        for period, cx in columns.items():
            near = [c for c in cells if abs(c[0] - cx) < gap / 2]
            lines: dict[int, list[tuple[float, str]]] = {}
            pending_period_digits = False
            for x, y, text in sorted(near, key=lambda c: c[1]):
                if re.fullmatch(r"[1-9/]", text):
                    pending_period_digits = True
                    continue
                key = int(round((y - (day_y - 18)) / 6))
                lines.setdefault(key, []).append((x, text))
            ordered = sorted(lines.items(), key=lambda kv: kv[0])
            parts = [" ".join(t for _, t in sorted(group, key=lambda c: -c[0])) for _, group in ordered]
            parts = [p for p in parts if p and p != "/"]
            subject = parts[0] if parts else ""
            teacher = parts[1] if len(parts) > 1 else ""
            if subject == "—":
                subject, teacher = "", ""
            rows.append({
                "day": day,
                "period": period,
                "subject": subject,
                "teacher": teacher,
                "combined": pending_period_digits,
            })

    doc.close()
    return rows, warnings


def main() -> int:
    src = Path(sys.argv[1] if len(sys.argv) > 1 else "docs/source/timetables")
    out = Path(sys.argv[2] if len(sys.argv) > 2 else "docs/source/data/timetables_17.json")
    out.parent.mkdir(parents=True, exist_ok=True)

    classes = []
    warnings: list[str] = []
    for pdf in sorted(src.glob("*.pdf")):
        match = re.search(r"(A\d+|B\d+)", pdf.name)
        if not match:
            continue
        class_id = match.group(1)
        rows, warns = read_page(pdf)
        warnings.extend(warns)
        classes.append({
            "class_id": class_id,
            "source_pdf": pdf.name,
            "rows": rows,
        })

    payload = {
        "academic_year": "2026/2027",
        "extraction": "pymupdf word coordinates; subject is the upper cell line, teacher the lower one",
        "classes": classes,
        "warnings": warnings,
    }
    out.write_text(json.dumps(payload, ensure_ascii=False, indent=1), encoding="utf-8")

    for entry in classes:
        filled = sum(1 for r in entry["rows"] if r["subject"])
        subjects = len({r["subject"] for r in entry["rows"] if r["subject"]})
        print(f"{entry['class_id']}: {filled} filled cells, {subjects} subjects, {len(entry['rows'])} grid slots")
    print(f"wrote {out}")
    for warning in warnings:
        print("WARN", warning)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
