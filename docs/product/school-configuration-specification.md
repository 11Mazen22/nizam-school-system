# NIZAM UNIVERSAL SCHOOL PLATFORM -- CONFIGURATION ENGINE SPECIFICATION
**Product:** Nizam Universal School Platform  
**System:** Institutional & Academic Configuration Engine  
**Document Version:** 1.0.0

---

## 1. Executive Purpose

Nizam eliminates hardcoded school structures. Rather than forcing institutions to adapt their operations to Nizam, Nizam configures itself to the school. This specification defines the configuration parameters, presets, and behavior rules that govern an institution on Nizam.

---

## 2. Institutional Profile Configuration

Stored in `schools` and `settings` tables:

```json
{
  "profile": {
    "name": "Al-Manarah International Academy",
    "name_ar": "أكاديمية المنارة الدولية",
    "logo_path": "uploads/school/logo.png",
    "school_type": "international_private",
    "school_structure": "k12_combined",
    "country": "EG",
    "region": "Giza",
    "timezone": "Africa/Cairo",
    "default_locale": "ar",
    "available_locales": ["ar", "en"],
    "currency": "EGP",
    "currency_symbol": "ج.م",
    "date_format": "YYYY-MM-DD",
    "number_format": "standard",
    "school_week": {
      "days": [0, 1, 2, 3, 4],
      "first_day": 0,
      "labels": ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday"]
    },
    "tax_number": "TR-982341",
    "affiliation_code": "CAMBRIDGE-EG-042"
  }
}
```

---

## 3. Supported Institutional Preset Profiles

To simplify initial onboarding while retaining complete customizability, Nizam provides 6 baseline profiles:

1. **National Public / Language School (Egypt/MENA Standard):**
   - 3 Stages: Primary (Grades 1-6), Preparatory (Grades 7-9), Secondary (Grades 10-12).
   - 2 Semesters (Term 1 & Term 2).
   - 100-Point numerical grading with government passing thresholds (50%).
   - School week: Sunday through Thursday (5 days, 7-8 periods per day).
2. **K-12 American Curriculum School:**
   - 3 Stages: Elementary (K-5), Middle School (6-8), High School (9-12).
   - 4 Quarters / 2 Semesters.
   - 4.0 GPA scale with Letter Grades (A+, A, A-, B+... F).
   - Credit-hour courses and cumulative GPA calculations.
3. **British / Cambridge Curriculum School:**
   - Key Stages: KS1-KS2 (Primary), KS3 (Lower Secondary), KS4 (IGCSE), KS5 (A-Levels).
   - 3 Terms (Autumn, Spring, Summer).
   - Letter grades 9-1 / A*-G, mock examinations, predicted grades.
4. **Standalone Primary School:**
   - 1 Stage: Grades 1 through 5/6.
   - Trimester / Term schedule.
   - Descriptive / competency-based standards (Exceeds, Meets, Approaching, Beginning).
   - Daily homeroom attendance; single class teacher per group.
5. **Secondary Specialized Academy:**
   - 1 Stage: Grades 10 through 12.
   - Scientific, Literary, and Vocational tracks.
   - Period-based attendance tied to teacher assignments.
6. **Multi-Campus Educational Group:**
   - Central organization with multiple geographic campuses sharing curriculum standards.

---

## 4. Academic Configuration Model

### 4.1 Educational Stages & Grade Levels
- **Educational Stage:** Defines broad divisional categories (`primary`, `preparatory`, `secondary`).
- **Grade Level:** Belongs to a stage, defines numerical order, default age band, and curriculum subjects.
- **Class Section:** Belongs to a grade and academic year, specifies room, capacity, and homeroom supervisor.

### 4.2 Academic Calendar & Terms
- Supported Term Types:
  - `two_semesters` (Semester 1, Semester 2)
  - `three_terms` (Autumn, Spring, Summer)
  - `four_quarters` (Q1, Q2, Q3, Q4)
- Each term maintains: `start_date`, `end_date`, `is_active`, `is_locked`.

### 4.3 Flexible Grading Scales
Institutions define grading schemes in `grading_scales`:
- Scale Types:
  - `percentage`: 0% to 100%, passing threshold configurable per subject.
  - `letter`: A+, A, B, C, D, F with minimum score boundaries.
  - `gpa`: 4.0 scale with grade points (A = 4.0, B = 3.0, etc.).
  - `competency`: 4-tier rubric scale.

---

## 5. Attendance Configuration Model

- **Modes:**
  - `daily`: Single morning roll call per class section.
  - `period`: Attendance recorded for each period in the timetable.
- **Attendance Codes:**
  - `P` (Present): In classroom on time (counts as present).
  - `A` (Absent Unexcused): Not in school; triggers guardian notification.
  - `L` (Late Arrival): Arrived after cutoff time; can accumulate toward absence.
  - `E` (Excused Absence): Medical or sanctioned absence; exempt from truancy sanctions.
- **Calculation Rules:**
  - Configurable minimum attendance threshold for exam eligibility (e.g. 75% or 85%).
  - Automatic notification dispatch upon consecutive unexcused absences.

---

## 6. Timetable Configuration Model

- **School Days:** Configurable bitmask or array of active weekday integers (0 = Sunday to 6 = Saturday).
- **Periods per Day:** Configurable count (typically 6-9 periods) with start time, duration, and end time.
- **Breaks / Recess:** Configurable break slots (e.g., Morning Assembly, Recess 1, Prayer/Lunch).
- **Room Management:** Rooms with building, floor, room number, and capacity constraints.
- **Conflict Prevention:** Timetable scheduling checks:
  1. No teacher assigned to two different classes in the same period.
  2. No class assigned two different subjects in the same period.
  3. No room double-booked in the same period.
