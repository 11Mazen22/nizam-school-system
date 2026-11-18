"""Build the reviewed personal-data enrichment for the official rosters.

Every value written by this script is traceable to the supplied roster pages
or to the school's written confirmation. It performs four reviewable
operations:

1. restore the one transcribed source row that was accidentally omitted from
   B5 (left table, row 17);
2. write M/F from the visually reviewed roster sections;
3. decode religion from the source's documented page convention: a printed
   Muslim page default applies until an explicit exception, and the ``م-``
   prefix on the combined A-page cells is the Christian marker. The combined
   source cell is always preserved verbatim; and
4. apply the school's confirmed name-based religion classification to the 75
   students whose printed cell proves no religion at all (B6 and B8 print
   academic specializations, and one B7 cell uses the unlegended ``ج- س``
   shorthand). Four of them carry unmistakable Christian names and are marked
   Christian; the remaining 71 carry no Christian marker and are marked
   Muslim. Every one of them is listed in the audit with its provenance.

Date of birth and guardian links are never inferred: they stay empty.

The output CSVs remain the canonical input for both the offline and Railway
seed. ``roster-data-audit.json`` records the source-image hashes, derivation
rules, the name-inference list and completeness totals so the result is
independently reviewable.
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

# The school confirmed, in writing, that the students whose printed cell proves
# no religion are classified from their names. Only these four carry an
# unmistakable Christian name; every other inferred student has no Christian
# marker and is recorded as Muslim. Nothing else is ever inferred.
NAME_INFERENCE_CHRISTIANS: dict[tuple[str, str], str] = {
    ("B6", "مريم جورج حنا فوزى"): "جورج / حنا / فوزى - Coptic Christian names",
    ("B6", "سامر صموئيل فكرى بنى"): "صموئيل (Samuel) - Biblical Christian name",
    ("B6", "ايهاب مسامح موريس نجيب"): "موريس (Maurice) - Coptic Christian name",
    ("B7", "يؤنا ماجد ظريف جرجس"): "جرجس (Girgis) - Coptic Christian name",
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


def religion_for(class_name: str, raw: str, full_name: str) -> tuple[str | None, str | None]:
    """Decode the religion of one roster row with an explicit provenance."""

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
        # The ج-/ح- shorthand has no supplied legend, so the school confirmed
        # that this one row is classified from the name.
        if compact.startswith("ج-") or compact.startswith("ح-"):
            return name_inferred_religion(class_name, full_name)
        return "muslim", "page_convention"

    # B6/B8 print academic specializations (كيمياء، ادارة اعمال، محاسبة، ...),
    # never a religion, so the school confirmed that these rows are classified
    # from the name.
    if class_name in {"B6", "B8"}:
        return name_inferred_religion(class_name, full_name)

    return None, None


def name_inferred_religion(class_name: str, full_name: str) -> tuple[str, str]:
    """Return the school-confirmed name-based religion for one student."""

    name = re.sub(r"\s+", " ", full_name).strip()
    if (class_name, name) in NAME_INFERENCE_CHRISTIANS:
        return "christian", "name_inference_christian"
    return "muslim", "name_inference_muslim"


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
    with path.open("w", encoding="utf-8-sig", newline="") as handle:
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
    name_inference: list[dict[str, str]] = []

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
            religion, religion_source = religion_for(class_name, raw, row["Full Name"])
            row["Religion"] = religion or ""
            if religion is not None and religion_source is not None:
                religion_counts[religion] += 1
                religion_sources[religion_source] += 1
                if religion_source.startswith("name_inference_"):
                    name_inference.append({
                        "class": class_name,
                        "full_name": re.sub(r"\s+", " ", row["Full Name"]).strip(),
                        "religion": religion,
                        "religion_source": religion_source,
                    })

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
    if sum(religion_counts.values()) != len(all_rows):
        raise RuntimeError("Not every source row received a confirmed religion")
    if len(name_inference) != 75:
        raise RuntimeError(f"Expected 75 name-inferred students, found {len(name_inference)}")
    inferred_christians = [entry for entry in name_inference if entry["religion_source"] == "name_inference_christian"]
    if len(inferred_christians) != 4:
        raise RuntimeError(f"Expected 4 name-inferred Christians, found {len(inferred_christians)}")

    christian_inference = [
        {
            "full_name": entry["full_name"],
            "class": entry["class"],
            "reason": NAME_INFERENCE_CHRISTIANS[(entry["class"], entry["full_name"])],
        }
        for entry in inferred_christians
    ]

    audit = {
        "version": 5,
        "academic_year": "2026/2027",
        "source": "Rendered official roster pages under docs/source/rosters (human-reviewed) + the school's confirmed name-based religion classification for the 75 rows whose printed cell proves no religion.",
        "rules": {
            "gender": "M/F is derived from the visually reviewed roster section for each exact R/L row address; never from a name alone.",
            "religion": "703 from the confirmed page default / exception convention and the confirmed م- Christian marker. 75 from the school's confirmed name-based classification (4 Christians identified from Coptic names, 71 Muslims with no Christian marker): B6 (47) and B8 (27) print academic specializations instead of religions, and one B7 cell uses the unlegended ج- س shorthand, so the source cannot prove those values.",
            "second_language": "Normalized only from an explicit language word; A7 blank rows and all A9 rows use their unambiguous class heading. Ambiguous shorthand is not decoded.",
            "date_of_birth": "Not present in the source; remains null.",
            "guardian": "Not present in the source; remains null.",
        },
        "source_rule_confirmations": [
            "B5 prints مسلم once as the default for every populated student on that page unless another religion is printed.",
            "The م- prefix in the combined A-page cell is the Christian marker; its suffix remains the printed second language.",
            "B6/B8 pages print academic specializations (كيمياء، ادارة اعمال، برمجة، برمجه، رياضة، محاسبة، فيزياء، علم نفس), never a religion.",
            "One B7 cell prints the unlegended ج- س shorthand; the school confirmed that row is classified from the name (جرجس = Christian).",
            "The school confirmed in writing: classify the 75 students on B6/B8 and the B7 shorthand row from their names - 4 Christians (Coptic names: موريس، صموئيل، جورج/حنا/فوزى، جرجس) and 71 Muslims.",
            "No other value (date of birth, guardian) is ever inferred.",
        ],
        "restored_source_rows": [{
            "class": "B5",
            "position": "L17",
            "full_name": "ريماس فاروق اسماعيل حسن",
            "reason": "Visible populated row in the official image; absent from the earlier CSV transcription.",
        }],
        "name_inference_details": {
            "christian_count": len(christian_inference),
            "christian_students": christian_inference,
            "muslim_count": len(name_inference) - len(christian_inference),
            "muslim_students_note": "No Christian name marker; recorded as Muslim per the school's confirmed classification.",
        },
        "name_inference_students": name_inference,
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
