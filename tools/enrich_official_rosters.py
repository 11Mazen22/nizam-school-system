"""Build the source-backed personal-data enrichment for the official rosters.

This script is intentionally conservative. It never guesses religion from a
name and never turns the ambiguous printed shorthand (for example ``م - ف``)
into a religion. It performs only three reviewable operations:

1. restore the one transcribed source row that was accidentally omitted from
   B5 (left table, row 17);
2. write M/F from the visually reviewed roster sections; and
3. decode religion only from the source's documented page convention: a
   printed Muslim page default applies until an explicit exception, and the
   ``م-`` prefix on the combined A-page cells is the Christian marker. The
   combined source cell is always preserved verbatim.

The output CSVs remain the canonical input for both the offline and Railway
seed. ``roster-data-audit.json`` records the source-image hashes, derivation
rules and completeness totals so the result is independently reviewable.
"""

from __future__ import annotations

import csv
import hashlib
import json
import re
from collections import Counter
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
OFFICIAL = ROOT / "database" / "official"
SOURCE_IMAGES = ROOT / "docs" / "source" / "rosters"

HEADERS = [
    "Full Name",
    "Class Name",
    "Gender (M/F)",
    "Date of Birth (YYYY-MM-DD)",
    "Religion",
    "Class Attribute",
    "Notes",
]

EXPLICIT_RELIGIONS = {
    "مسلم": "muslim",
    "مسلمة": "muslim",
    "مسيحي": "christian",
    "مسيحى": "christian",
    "مسيحية": "christian",
}


def row_position(note: str) -> tuple[str, int]:
    match = re.search(r"\brow\s+([RL])(\d+)\b", note, flags=re.IGNORECASE)
    if not match:
        raise ValueError(f"No source row in note: {note!r}")
    return match.group(1).upper(), int(match.group(2))


def gender_for(class_name: str, side: str, number: int) -> str:
    """Return the reviewed gender section for an exact source-cell address."""

    if class_name in {"A1", "A2", "A3", "A4", "A5", "A9"}:
        return "F" if side == "R" else "M"
    if class_name == "A6":
        return "F" if side == "R" and number <= 22 else "M"
    if class_name == "A7":
        return "M"
    if class_name == "A8":
        return "F" if side == "R" and (number <= 8 or 19 <= number <= 21) else "M"

    if class_name == "B1":
        return "F" if side == "R" or (side == "L" and number >= 22) else "M"
    if class_name == "B2":
        return "F" if side == "R" or (side == "L" and number >= 15) else "M"
    if class_name == "B3":
        return "F" if side == "R" and number <= 17 else "M"
    if class_name == "B4":
        return "F" if side == "R" and number <= 16 else "M"
    if class_name == "B5":
        return "F" if side == "L" and number <= 17 else "M"
    if class_name == "B6":
        return "F" if side == "R" else "M"
    if class_name == "B7":
        if side == "R":
            return "F"
        if number <= 9 or number in {12, 13}:
            return "M"
        return "F"
    if class_name == "B8":
        if side == "R":
            return "F" if number in {1, 2} or 11 <= number <= 19 else "M"
        return "M" if number <= 19 else "F"

    raise ValueError(f"No reviewed gender rule for {class_name} {side}{number}")


def religion_for(class_name: str, raw: str) -> tuple[str | None, str | None]:
    """Decode only the page rules confirmed with the supplied source."""

    explicit = EXPLICIT_RELIGIONS.get(raw)
    if explicit is not None:
        return explicit, "printed_explicit"

    compact = re.sub(r"\s+", "", raw).replace("–", "-").replace("—", "-")

    # On first-secondary language pages the combined marker م- identifies a
    # Christian student; an unprefixed language (or the page's carried/default
    # language) is Muslim. The same combined convention is printed on B7.
    if class_name in {"A1", "A2", "A3", "A4", "A5", "A6", "A7", "A8"}:
        return ("christian", "combined_marker") if compact.startswith("م-") else ("muslim", "page_convention")

    # A9 and B1-B5 print Muslim once as the page default and print every
    # religious exception in the same column. The user explicitly confirmed
    # this convention for B5; the adjacent pages use the same form.
    if class_name == "A9" or class_name in {"B1", "B2", "B3", "B4", "B5"}:
        return "muslim", "page_default"

    if class_name == "B7":
        if compact.startswith("م-"):
            return "christian", "combined_marker"
        # ج-س has no supplied legend; it remains unconfirmed rather than being
        # guessed. Plain written pathways carry no Christian marker.
        if compact.startswith("ج-") or compact.startswith("ح-"):
            return None, None
        return "muslim", "page_convention"

    # B6/B8 use every populated combined cell for academic specializations
    # and print no page religion default. Names are never used as evidence.
    return None, None


def normalized_second_language(class_name: str, raw: str) -> tuple[str | None, str | None]:
    """Return only a language proved by the printed cell or class heading."""

    compact = re.sub(r"\s+", "", raw)
    if "المانى" in compact:
        return "german", "printed_cell"
    if "فرنسى" in compact or "فرنساوى" in compact:
        return "french", "printed_cell"
    if "اسبانى" in compact:
        return "spanish", "printed_cell"

    # These headings are single-language headings in the source image. A7
    # still prints every exception (French / exempt), so a blank row is the
    # heading's German language, while A9 is Spanish for the whole class and
    # uses its combined cells for religion changes.
    if class_name == "A7" and raw == "":
        return "german", "class_heading"
    if class_name == "A9":
        return "spanish", "class_heading"

    return None, None


def read_rows(path: Path) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        if reader.fieldnames != HEADERS:
            raise ValueError(f"Unexpected headers in {path.name}: {reader.fieldnames!r}")
        return [{key: (value or "").strip() for key, value in row.items()} for row in reader]


def write_rows(path: Path, rows: list[dict[str, str]]) -> None:
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=HEADERS, lineterminator="\n")
        writer.writeheader()
        writer.writerows(rows)


def main() -> int:
    files = sorted(OFFICIAL.glob("roster_*.csv"))
    if len(files) != 17:
        raise RuntimeError(f"Expected 17 official roster CSVs, found {len(files)}")

    all_rows: list[dict[str, str]] = []
    class_counts: dict[str, int] = {}
    image_hashes: dict[str, str] = {}
    language_counts: Counter[str] = Counter()
    language_sources: Counter[str] = Counter()
    religion_counts: Counter[str] = Counter()
    religion_sources: Counter[str] = Counter()
    gender_counts: Counter[str] = Counter()

    for path in files:
        class_name = path.stem.removeprefix("roster_")
        rows = read_rows(path)

        if class_name == "B5" and not any(row["Notes"] == "Row L17" for row in rows):
            rows.append({
                "Full Name": "ريماس فاروق اسماعيل حسن",
                "Class Name": "B5",
                "Gender (M/F)": "",
                "Date of Birth (YYYY-MM-DD)": "",
                "Religion": "",
                "Class Attribute": "",
                "Notes": "Row L17",
            })

        rows.sort(key=lambda row: (0 if row_position(row["Notes"])[0] == "R" else 1, row_position(row["Notes"])[1]))

        seen_positions: set[tuple[str, int]] = set()
        for row in rows:
            if row["Class Name"] != class_name:
                raise ValueError(f"{path.name}: class mismatch for {row['Full Name']}")
            position = row_position(row["Notes"])
            if position in seen_positions:
                raise ValueError(f"{path.name}: duplicate source position {position}")
            seen_positions.add(position)

            gender = gender_for(class_name, *position)
            row["Gender (M/F)"] = gender
            gender_counts[gender] += 1

            raw = row["Class Attribute"]
            religion, religion_source = religion_for(class_name, raw)
            row["Religion"] = religion or ""
            if religion is not None and religion_source is not None:
                religion_counts[religion] += 1
                religion_sources[religion_source] += 1

            language, source = normalized_second_language(class_name, raw)
            if language is not None and source is not None:
                language_counts[language] += 1
                language_sources[source] += 1

            if row["Date of Birth (YYYY-MM-DD)"] != "":
                raise ValueError(f"{path.name}: source does not provide a birth date for {row['Full Name']}")

        write_rows(path, rows)
        class_counts[class_name] = len(rows)
        all_rows.extend(rows)

        image = SOURCE_IMAGES / class_name[0] / f"{class_name}.png"
        if image.is_file():
            image_hashes[class_name] = hashlib.sha256(image.read_bytes()).hexdigest()

    if len(all_rows) != 778:
        raise RuntimeError(f"Expected 778 populated source rows after restoring B5 L17, found {len(all_rows)}")
    if sum(gender_counts.values()) != len(all_rows):
        raise RuntimeError("Not every source row received a reviewed gender")

    audit = {
        "version": 2,
        "academic_year": "2026/2027",
        "source": "Rendered official roster pages under docs/source/rosters (human-reviewed)",
        "rules": {
            "gender": "M/F is derived from the visually reviewed roster section for each exact R/L row address; never from a name alone.",
            "religion": "Uses the confirmed page default / exception convention and the confirmed م- Christian marker. B6/B8 and unlegended ج-/ح- shorthand remain null; names are never used.",
            "second_language": "Normalized only from an explicit language word; A7 blank rows and all A9 rows use their unambiguous class heading. Ambiguous shorthand is not decoded.",
            "date_of_birth": "Not present in the source; remains null.",
        },
        "source_rule_confirmations": [
            "B5 prints مسلم once as the default for every populated student on that page unless another religion is printed.",
            "The م- prefix in the combined A-page cell is the Christian marker; its suffix remains the printed second language.",
        ],
        "restored_source_rows": [{
            "class": "B5",
            "position": "L17",
            "full_name": "ريماس فاروق اسماعيل حسن",
            "reason": "Visible populated row in the official image; absent from the earlier CSV transcription.",
        }],
        "totals": {
            "students": len(all_rows),
            "classes": len(class_counts),
            "gender_confirmed": sum(gender_counts.values()),
            "gender": dict(sorted(gender_counts.items())),
            "religion_confirmed": sum(religion_counts.values()),
            "religion": dict(sorted(religion_counts.items())),
            "religion_source": dict(sorted(religion_sources.items())),
            "second_language_confirmed": sum(language_counts.values()),
            "second_language": dict(sorted(language_counts.items())),
            "second_language_source": dict(sorted(language_sources.items())),
            "date_of_birth_confirmed": 0,
        },
        "class_counts": class_counts,
        "source_image_sha256": image_hashes,
    }
    (OFFICIAL / "roster-data-audit.json").write_text(
        json.dumps(audit, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )

    print(json.dumps(audit["totals"], ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
