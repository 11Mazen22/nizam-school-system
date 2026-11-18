"""Extract the 17 class timetables from the supplied PDFs.

The PDFs embed a nonstandard font-to-Unicode map, so whole-page text
extraction scrambles the grid. The glyphs still sit at exact coordinates, so
each table cell is read through its own clip rectangle: inside a cell the
first line is the subject and the remaining line is the teacher. That matches
the printed layout and avoids guessing word order.

Run:  python tools/extract_timetables.py docs/source/timetables database/official/timetables_17.json
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

LIGATURES = {"األ": "الأ", "اإل": "الإ", "اآل": "الآ", "اال": "الا", "Ǆ": "ل"}
TOKEN_FIXES = {
    "إىل": "إلى",
    "عىل": "على",
    "األوىل": "الأولى",
    "األسبوعي": "الأسبوعي",
}


def clean(text: str) -> str:
    text = unicodedata.normalize("NFC", text).strip().strip("—").strip()
    for bad, good in LIGATURES.items():
        text = text.replace(bad, good)
    return TOKEN_FIXES.get(text, text)


def cluster_lines(entries: list[tuple[float, float, str]]) -> list[str]:
    """Group a cell's words into visual lines (y proximity) and join RTL."""
    lines: list[list[tuple[float, float, str]]] = []
    for entry in sorted(entries, key=lambda e: e[1]):
        if lines and abs(entry[1] - lines[-1][0][1]) <= 3:
            lines[-1].append(entry)
        else:
            lines.append([entry])
    joined = []
    for group in lines:
        text = " ".join(clean(word) for _, _, word in sorted(group, key=lambda e: -e[0]))
        text = text.replace(" / ", " / ").strip()
        if text and text != "/":
            joined.append(text)
    return joined


def read_page(pdf: Path) -> tuple[list[dict], list[str]]:
    doc = pymupdf.open(pdf)
    page = doc[0]
    words = page.get_text("words")
    warnings: list[str] = []

    columns: dict[int, float] = {}
    for w in words:
        if 175 < w[1] < 205 and re.fullmatch(r"[1-9]", w[4]):
            columns.setdefault(int(w[4]), (w[0] + w[2]) / 2)
    if len(columns) < 8:
        warnings.append(f"{pdf.name}: only {len(columns)} period columns found")

    anchors: dict[str, float] = {}
    for w in words:
        name = DAY_ALIASES.get(w[4], clean(w[4]))
        if name in DAYS and w[0] > 690 and w[1] > 205:
            anchors.setdefault(name, w[1])
    is_pathway = bool(re.search(r"B\d+", pdf.name))
    if is_pathway and len(anchors) != 3:
        warnings.append(f"{pdf.name}: pathway class should have 3 weekdays, found {len(anchors)}")
    if not is_pathway and len(anchors) != 5:
        warnings.append(f"{pdf.name}: expected 5 weekdays, found {len(anchors)}")

    ordered_days = sorted(anchors.items(), key=lambda kv: kv[1])
    ordered_periods = sorted(columns.items())
    gaps = [b[1] - a[1] for a, b in zip(ordered_periods, ordered_periods[1:])]
    step = abs(sum(gaps) / len(gaps)) if gaps else 75.0

    # Text lines, not words: a word can straddle a cell border, a line does not.
    text_lines: list[tuple[float, float, str]] = []
    for block in page.get_text("dict")["blocks"]:
        for line in block.get("lines", []):
            text = "".join(span["text"] for span in line["spans"]).strip()
            if not text:
                continue
            x0, y0, x1, y1 = line["bbox"]
            text_lines.append(((x0 + x1) / 2, (y0 + y1) / 2, text))

    centers = [(period, cx) for period, cx in ordered_periods]
    rows: list[dict] = []
    for day, day_y in ordered_days:
        grouped: dict[int, list[tuple[float, float, str]]] = {period: [] for period, _ in centers}
        for entry in text_lines:
            if not (day_y - 16 <= entry[1] <= day_y + 22) or entry[0] >= 690:
                continue
            period = min(centers, key=lambda c: abs(c[1] - entry[0]))[0]
            grouped[period].append(entry)
        for period, _ in centers:
            ordered = [clean(t) for _, _, t in sorted(grouped[period], key=lambda e: e[1])]
            ordered = [t.replace(" / ", "/").strip("/").strip() for t in ordered]
            ordered = [t for t in ordered if t and t not in {"/", "—"}]
            raw = " | ".join(ordered)
            if not ordered:
                subject, teacher = "", ""
            elif len(ordered) == 1:
                subject, teacher = ordered[0], ""
            else:
                subject = ordered[0]
                teacher = ordered[-1]
            rows.append({
                "day": day,
                "period": period,
                "subject": subject,
                "teacher": teacher,
                "raw": raw,
                "special": len(ordered) > 2,
            })

    # A teacher's name occasionally lands one period over because its PDF
    # line merged with the neighbouring cell's dash. Move it back when the
    # previous cell has a subject with no teacher and this text is a name that
    # appears as a teacher elsewhere on the page.
    vocabulary = set()
    for row in rows:
        for name in str(row.get("teacher", "")).split("/"):
            name = name.strip()
            if name:
                vocabulary.add(name)
    for index in range(1, len(rows)):
        current, previous = rows[index], rows[index - 1]
        if (current["day"] == previous["day"]
                and current["period"] == previous["period"] + 1
                and current["subject"] in vocabulary
                and not current["teacher"]
                and previous["subject"] and not previous["teacher"]):
            previous["teacher"] = current["subject"]
            previous["raw"] = previous["raw"] + " | " + current["subject"]
            current["subject"] = ""
            current["raw"] = ""

    doc.close()
    return rows, warnings


def main() -> int:
    src = Path(sys.argv[1] if len(sys.argv) > 1 else "docs/source/timetables")
    out = Path(sys.argv[2] if len(sys.argv) > 2 else "database/official/timetables_17.json")
    out.parent.mkdir(parents=True, exist_ok=True)

    classes, warnings = [], []
    for pdf in sorted(src.glob("*.pdf")):
        match = re.search(r"(A\d+|B\d+)", pdf.name)
        if not match:
            continue
        rows, warns = read_page(pdf)
        warnings.extend(warns)
        classes.append({"class_id": match.group(1), "source_pdf": pdf.name, "rows": rows})

    payload = {
        "academic_year": "2026/2027",
        "extraction": "per-cell clip rectangles on exact PDF coordinates; upper line = subject, lower = teacher",
        "classes": classes,
        "warnings": warnings,
    }
    out.write_text(json.dumps(payload, ensure_ascii=False, indent=1), encoding="utf-8")

    for entry in classes:
        filled = sum(1 for r in entry["rows"] if r["subject"])
        print(f"{entry['class_id']}: {filled} filled of {len(entry['rows'])} slots")
    print(f"wrote {out}")
    for warning in warnings:
        print("WARN", warning)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
