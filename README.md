# mod_mtssdashboard — My Student Data
**Version:** 2026112000 (0.4.7)
**Moodle:** 4.5.x
**Author:** Brian Pool, National Trail Local Schools
**License:** GNU GPL v3 or later

---

## 0.4.7 changes

- **iReady Lessons for RIMP Students toggle (MS)** — a second card on the
  Warning Levels tab, beside "iReady Lessons by Grade". On the MS
  management course, a manager clicks Edit thresholds and checks a grade
  under Reading (ELA) and/or Math. Students in that grade **who have a
  RIMP** get the same treatment as the grade toggle: "Days Since ELA/Math
  Lesson" shows "—" and is not counted toward Overall Risk. Students
  without a RIMP, and all percentile columns, are not affected.
  - **Why:** some students with a RIMP take iReady diagnostics but not
    iReady lessons in a subject, so they showed "Never" and picked up a
    false risk flag, even though the rest of their grade does use lessons.
  - "Has a RIMP" means any record in `mtssdashboard_rimp` (Draft or
    Finalized), the same rule as the RIMP ✓ column on Early Warning.
  - Applies to both the Early Warning table and Download CSV. If a grade is
    checked on the "by Grade" card, it is off for every student in that
    grade regardless of RIMP.
  - Stored as plugin config `rimplessonsoff_ms_ela` /
    `rimplessonsoff_ms_math` (comma list of grades, e.g. `7`). No database
    table changes.

## 0.4.6 changes

- **Download RIMPs / Download WEPs** — a new button next to **View** on the
  RIMP and WEP tabs opens every *finalized* RIMP/WEP for the students
  currently shown — the selected group plus whatever demographic filters
  were applied — as one document, one student after another, each starting
  on a new page. The print window opens automatically; choose **Save as
  PDF** to get a single file to upload to ParentSquare Secure Documents.
  Draft records and students with no record are left out. Each report is
  built by the same code as the single-student Print RIMP/Print WEP pages,
  so the bulk file always matches what teachers see individually.
- **Why:** so finalized plans can be sent to all families at once through
  ParentSquare instead of being printed and mailed one by one.
- **CTC and departed students hidden** — students with StudentStatus J or
  NJ (career-tech center) or I or R (no longer attending) do not appear on
  the RIMP or WEP tabs or in the downloads, because plans are not written
  for them. To skip more codes, edit the `$skip` list in
  `mtssdashboard_remove_skipped_students()` in `lib.php`.
- **Correct school on printouts** — RIMPs and WEPs print the school for the
  student's current grade (set by the HS/MS/ES grade ranges in plugin
  settings) instead of the school in the old import file. The three school
  names are language strings (`school_name_hs`, `school_name_ms`,
  `school_name_es`) and can be edited there.
- **WEP header** — Student ID, Date of Birth, Current Grade, Gender, School
  Year and Review Date are now one left-aligned table that does not wrap.
- **RIMP page breaks** — fixed a missing closing tag in the RIMP report so
  each student starts on a new page in Download RIMPs.
- **Fixes on the same tabs:** the RIMP tab's View button pointed to an old
  tab name and did not show the selected student, and pressing View on
  either tab dropped the applied filters. Both are fixed, so a download
  after pressing View still uses the filters on screen.

## 0.4.5 changes

- **iReady Lessons by Grade toggle (MS)** — new card on the Warning Levels
  tab. On the MS management course, a manager clicks Edit thresholds and
  checks any grade (6, 7, 8) that takes iReady *diagnostics* but not iReady
  *lessons* in Reading or Math. For those students the "Days Since ELA/Math
  Lesson" column shows "—" and is not counted toward Overall Risk.
  Percentile columns are not affected.
  - **Why:** starting 2026-27, 7th grade stopped using iReady Math lessons,
    so every 7th grader showed "Never" and picked up a false risk flag.
    The toggle avoids hardcoding grades so future curriculum changes are a
    checkbox, not a code change.
  - Stored as plugin config `lessonsoff_ms_ela` / `lessonsoff_ms_math`
    (comma list of grades, e.g. `7`). No database table changes.
- **CSV export fix** — Download CSV now includes real Days Since ELA/Math
  Lesson values (previously always blank), so its Overall Risk matches the
  screen. 99 is exported as "Never".

## 0.4.4 changes

- **Reset WEP** — a "Reset WEP" button now sits between Print and Delete
  on the WEP edit screen, letting a teacher clear a prior year's WEP for
  reuse without deleting it. A confirmation popup names the target school
  year first. On confirm it sets the school year to the current year, sets
  the review date to today, clears every goal/progress/parent-input/
  signature text field, and leaves all checked boxes untouched. Nothing
  saves until the teacher clicks Save as Draft or Save WEP.

## 0.4.2 changes

- **Delete RIMP / Delete WEP** — a "Delete RIMP"/"Delete WEP" button now sits
  between Print and Cancel on the RIMP/WEP edit screens, letting teachers
  remove a record that no longer applies. A confirmation popup guards
  against accidental clicks — canceling it leaves the record untouched.
  Deletes require the same edit capability as the RIMP/WEP itself.

## 0.4.1 changes

- **RIMP K-3 "On Track" scores moved to the language file** — the
  Kindergarten/Grade 1/Grade 2/Grade 3 iReady benchmark scores on the RIMP
  report are now pulled from four new language strings (`rimp_k_ontrack`,
  `rimp_grade1_ontrack`, `rimp_grade2_ontrack`, `rimp_grade3_ontrack`)
  instead of being hardcoded in `lib.php`. Future score updates only
  require editing `lang/en/mtssdashboard.php`.

## 0.4.0 changes

- **RIMP/WEP edit locking** — two teachers can no longer open the same
  student's RIMP or WEP at the same time and silently overwrite each
  other's changes. The second teacher sees a "currently being edited by
  [Name]" screen instead of the form. Locks release automatically on save,
  on tab close, or after 2 minutes of inactivity (crashed tab, etc.). A
  `timemodified` check on save is a second line of defense even if the
  lock is somehow bypassed. New table: `mtssdashboard_edit_sessions`.

## 0.2.8 changes

- Contacts table gained `Locker`/`Combination` columns — Student Info tab's
  contact panel now shows a "Locker: #### Combination: ##-##-##" line under
  Email.
- Classes & Grades table class names are now clickable, linking teachers
  straight to that student's Single View gradebook report for the course.
- The RIMP report (`rimp_print.php`), WEP report (`wep_print.php`), and the
  Score Trend / Subject Proficiency charts on the Student Info tab were each
  extracted into shared `lib.php` functions
  (`mtssdashboard_render_rimp_report()`, `mtssdashboard_render_wep_report()`,
  `mtssdashboard_render_student_charts()`) so a new companion plugin,
  `block_parentgrades`, can reuse the exact same report/chart logic on a
  parent-facing page, without duplicating it. Staff-facing behavior and
  output are unchanged.
- Student Info tab now shows a "View Elementary Gradecard" button above
  Classes & Grades for K-4 students, linking to
  `blocks/parentgrades/viewgrades.php` (the same elementary report card
  parents see). This introduces a soft dependency on `block_parentgrades`
  being installed for that one button to function — the rest of the tab
  works fine without it.

---

## Overview

My Student Data (`mod_mtssdashboard`) is a Moodle activity module that gives teachers,
counselors, and administrators a unified dashboard of student academic data. It connects
state testing results, gradebook grades, attendance, discipline, iReady assessment data,
Ohio RIMP plans, Written Education Plans (WEPs), and student plan files into a single
view — scoped to the students enrolled in the course where the activity is placed.

---

## Dependencies

| Dependency | Source | Required |
|-----------|--------|---------| 
| `contacts` | SIS sync (PowerSchool/other) | Yes — links Moodle users to SSIDs and demographics |
| `NTstatetesting` | `NTstatetesting.php` cron script | Yes — Ohio state testing results |
| `NTireadyela` | `NTautoiReadyELA.php` cron script | Optional — iReady ELA percentile data |
| `NTireadymath` | `NTautoiReadyMath.php` cron script | Optional — iReady Math percentile data |
| Chart.js 4.4.0 | Bundled (`chartjs.umd.min.js`) | Yes — included, no CDN needed |
| Plan PDFs | `$CFG->dataroot/NTimports/plans/` | Optional — student IEP/504/Gifted plan files |

---

## Database Tables

### Created by this plugin

| Table | Purpose |
|-------|---------|
| `mtssdashboard` | One row per activity instance added to a course |
| `mtssdashboard_warning_levels` | Configurable thresholds for Early Warning indicators |
| `mtssdashboard_rimp` | Editable RIMP records per student (Draft/Finalized) |
| `mtssdashboard_rimp_import` | Staging table for district RIMP CSV imports |
| `mtssdashboard_wep` | Editable WEP records per student (Draft/Finalized) |
| `mtssdashboard_wep_import` | Staging table for district WEP CSV imports |
| `mtssdashboard_edit_sessions` | Tracks who currently has a student's RIMP/WEP open for editing (prevents concurrent-edit overwrites) |

### External tables used (not created by this plugin)

| Table | Created By | Purpose |
|-------|-----------|---------| 
| `NTstatetesting` | `NTstatetesting.php` | Ohio state test results |
| `NTireadyela` | `NTautoiReadyELA.php` | iReady ELA diagnostic data |
| `NTireadymath` | `NTautoiReadyMath.php` | iReady Math diagnostic data |
| `contacts` | SIS sync | Student demographics, SSID, Birthdate linkage |

---

## How Student Data Is Linked

```
user.idnumber
    → contacts.StudentNumber
    → contacts.StateStudentID
    → NTstatetesting.ssid
```

iReady data linked via:
```
contacts.StudentID
    → NTireadyela.StudentID
    → NTireadymath.StudentID
```

RIMP data linked via:
```
user.idnumber
    → mtssdashboard_rimp.studentnumber
```

WEP data linked via:
```
user.idnumber
    → mtssdashboard_wep.studentnumber
```

Plan PDFs stored as:
```
$CFG->dataroot/NTimports/plans/{studentnumber}.pdf
```

---

## Tabs

| Tab | Levels | Visible To | Description |
|-----|--------|-----------|-------------|
| Early Warning | All | All teachers | Color-coded at-risk indicators. RIMP ✓ links to print view. Plan PDF link on Student ID. |
| Student Info | All | All teachers | Individual student full history with trend lines and radar chart, plus a contact information panel (photo, address, parent/guardian, home phone, clickable email) and a current classes & grades table (course, teacher, percentage/letter). 504/IEP/WEP/RIMP badges link to the matching document when one exists. |
| Grades | HS, MS | All teachers | Gradebook data for enrolled students |
| State Testing | All | All teachers | Current level proficiency doughnut charts + data table |
| Subject History | All | All teachers | Year-over-year trend line charts |
| History | HS, MS | All teachers | Previous school level test results |
| Warning Levels | All | Managers only | Configure Early Warning thresholds per school level, plus (MS) which grades, and which grades for RIMP students, do not take iReady lessons |
| RIMP | All | All teachers | Create and edit Ohio Reading Improvement and Monitoring Plans |
| WEP | All | All teachers | Create and edit Written Education Plans for gifted students |
| Plans | All | Managers only | Student plan status (IEP/504/Gifted) with sortable plan date links |
| Grade Updates | MS, HS | Managers only | Teacher course grade staleness tracking |

---

## Student Info — Contact & Grades Panel

### Overview
The Student Info tab (renamed from "Student Data" and moved to the second tab
position in v0.2.6) includes a two-column panel above the trend/radar charts:

- **Left — Contact Information:** student photo (Moodle user picture), address
  (`contacts.Address`/`Address2`/`City`/`State`/`Zip`), Parent/Guardian
  (`contacts.ParentName`), Home Phone (`contacts.HomePhone`), and Email
  (`contacts.Email`) rendered as a clickable `mailto:` link. Fields with no data
  show `—`.
- **Right — Classes & Grades:** every course the student is enrolled in with a
  recorded final grade, showing course name, teacher, and percentage with letter
  grade (e.g. `88.95% (B+)`). Only one teacher is shown per course — whoever was
  assigned the `editingteacher` role first (earliest `role_assignments.timemodified`).

The Grade/Gender/504/IEP/WEP/RIMP/Gifted badge row above this panel also changed:
504 and IEP now link to the student's plan PDF (same file `plan.php` serves from
the Early Warning tab's Student ID column) when one exists, and WEP/RIMP badges
were added, linking to `wep_print.php` / `rimp_print.php` respectively.

### Data source notes
- `contacts` has only single `HomePhone` and `ParentName` fields — no separate
  cell/work numbers or multiple parent slots.
- There is no "contact date" field in `contacts`; none is shown.
- New helper: `stv_get_student_course_grades()` in `lib.php`.

---

## RIMP Feature

### Overview
The RIMP tab allows any course teacher to create, edit, and finalize Ohio Reading
Improvement and Monitoring Plans for enrolled students.

### Workflow
1. Teacher opens the RIMP tab and selects a student.
2. If no RIMP exists, click **Create New RIMP**.
3. Fill in the form and click **Save as Draft** or **Save as Finalized**.
4. Click **Print RIMP** to open the print-friendly report in a new tab.
5. Click ✓ on the Early Warning tab to open the print-friendly report directly.
6. To send RIMPs home in bulk, set the group and filters, click **Apply
   Filters**, then click **Download RIMPs**. All finalized RIMPs for the
   students shown open in one print window — choose **Save as PDF** and
   upload the file to ParentSquare.

### ES vs MS/HS differences
- ES: Phonemic Awareness goal, K-3 iReady benchmarks, Third Grade Reading Guarantee,
  Current Teacher signature.
- MS/HS: monitoring checkboxes (UFLR/Phonics for Reading/Rewards), grades 4-8
  benchmarks, ELA Teacher + Intervention/Title Teacher signatures.

### Initial Data Import
```bash
php /var/www/html/mod/mtssdashboard/rimp_import.php
```
CSV files must be at:
- `/var/www/moodledata/NTimports/plans/RIMP WEP/student-District-CSVReport.csv`
- `/var/www/moodledata/NTimports/plans/RIMP WEP/student-District-CSVReport MS.csv`

---

## WEP Feature

### Overview
The WEP tab allows any course teacher to create, edit, and finalize Written Education
Plans for gifted students. WEPs document annual goals and progress for students
identified as gifted under Ohio Administrative Code 3301-51-15.

### Workflow
1. Teacher opens the WEP tab and selects a student.
2. If no WEP exists, click **Create New WEP**.
3. Check identification areas, service settings, progress measures, and personnel.
4. Enter goals and progress notes grouped by subject.
5. Click **Save as Draft** or **Save as Finalized**.
6. Click **Print WEP** to open the print-friendly report in a new tab.
7. To send WEPs home in bulk, set the group and filters, click **Apply
   Filters**, then click **Download WEPs**. All finalized WEPs for the
   students shown open in one print window — choose **Save as PDF** and
   upload the file to ParentSquare.

### ES vs MS/HS differences
- ES: Reading/Writing and Mathematics goals only. Service setting: Cluster Grouping
  (HIVE time) or Other.
- MS/HS: Reading/Writing, Mathematics, Science, and Social Studies goals. Service
  settings include Cluster Grouping (205062), Acceleration (205052), and College
  Credit Plus (205070).
- Superior Cognitive or Creative Thinking Ability identification → goals shown for
  all academic areas on the print report.

### Print report
- Only checked identification areas are shown.
- Goal and progress fields are shown only for checked identification areas (even if
  empty, as a reminder to the teacher).
- School name is pulled from the import staging table and cleaned to proper case
  (e.g. "NT NATIONAL TRAIL HIGH SCHOOL" → "National Trail High School").

### Initial Data Import
```bash
php /var/www/html/mod/mtssdashboard/wep_import.php
```
CSV files must be at:
- `/var/www/moodledata/NTimports/plans/RIMP WEP/student-District-CSVReport WEP.csv`
- `/var/www/moodledata/NTimports/plans/RIMP WEP/student-District-CSVReport WEP HS-MS.csv`

---

## Plans Feature

### Overview
The Plans tab shows managers a sortable table of all enrolled students with their
IEP, 504, and Gifted status and a link to their plan PDF.

### Plan file location
```
$CFG->dataroot/NTimports/plans/{studentnumber}.pdf
```

### Features
- Plan Date column is sortable (oldest → newest → oldest).
- Plans older than 365 days are highlighted in muted orange.
- Students with no plan file show "No Plan".
- All demographic and group filters apply.

---

## Admin Settings

Site Administration → Plugins → Activity Modules → My Student Data

| Setting | Default | Description |
|---------|---------|-------------|
| Current graduation year | 2026 | Year current 12th graders graduate. Update every year. |
| High School category ID | 2 | Moodle course category ID for HS courses |
| Middle School category ID | 3 | Moodle course category ID for MS courses |
| Elementary category ID | 28 | Moodle course category ID for ES courses |
| HS/MS/ES management course | varies | Course ID where Warning Levels and Plans tabs appear |

---

## Data Import Scripts

| Script | Location | Input | Table |
|--------|---------|-------|-------|
| `NTstatetesting.php` | `/var/www/html/admin/attendance/` | `OHstatetesting.csv` | `NTstatetesting` |
| `NTautoiReadyELA.php` | `/var/www/html/admin/attendance/` | `iReadyELA.csv` | `NTireadyela` |
| `NTautoiReadyMath.php` | `/var/www/html/admin/attendance/` | `iReadyMath.csv` | `NTireadymath` |
| `rimp_import.php` | `/var/www/html/mod/mtssdashboard/` | District RIMP CSVs | `mtssdashboard_rimp` |
| `wep_import.php` | `/var/www/html/mod/mtssdashboard/` | District WEP CSVs | `mtssdashboard_wep` |

---

## Installation

1. Copy plugin:
```bash
cp -r mod/mtssdashboard /var/www/html/mod/
chown -R www-data:www-data /var/www/html/mod/mtssdashboard
chmod -R 755 /var/www/html/mod/mtssdashboard
```

2. Run Moodle upgrade: `http://yourmoodle/admin/index.php`

3. Configure admin settings.

4. Import RIMP data (first time only):
```bash
php /var/www/html/mod/mtssdashboard/rimp_import.php
```

5. Import WEP data (first time only):
```bash
php /var/www/html/mod/mtssdashboard/wep_import.php
```

6. Place plan PDFs in `$CFG->dataroot/NTimports/plans/` named as `{studentnumber}.pdf`.

---

## Annual Maintenance

1. Update graduation year in admin settings
2. Import new state testing CSV when results are released
3. Verify contacts table is synced with SIS
4. Update iReady CSVs as new diagnostic windows complete
5. Re-run `rimp_import.php` if new district RIMP CSVs are available
6. Re-run `wep_import.php` if new district WEP CSVs are available
7. Replace plan PDFs in `NTimports/plans/` as updated plans are received
8. Review the MS "iReady Lessons by Grade" checkboxes on the Warning Levels
   tab if any grade starts or stops using iReady lessons

---

## Known Limitations

- Currently Beta — not submitted to Moodle plugin directory
- Backup/restore does not include student data tables
- `contacts` structure is specific to National Trail Local Schools SIS format
- Subject History tab is a placeholder — in development

---

## Support & Development

Developer: Brian Pool, National Trail Local Schools
License: GNU GPL v3 or later
