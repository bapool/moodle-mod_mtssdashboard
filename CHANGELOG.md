## [2026112001] — 2026-11-20 (v0.4.8)

### Changed
- Code brought up to the Moodle coding style so the GitHub Moodle Plugin CI
  passes: Moodle Code Checker (`--max-warnings 0`) and Moodle PHPDoc
  Checker both report zero problems. **No behaviour change.**
  - Automatic `phpcbf` fixes (spacing, indentation, braces on one-line
    `if` statements, `list()` → `[]`).
  - 193 local variables renamed to Moodle style (no underscores / all
    lower-case). Function names, DB fields, language strings and config
    names are unchanged.
  - `lib.php`: the RIMP and WEP print templates no longer switch in and out
    of PHP; the inline HTML is now emitted with `echo`. Output verified
    byte-for-byte identical for RIMP and WEP at ES/MS/HS, draft and
    finalized, with and without a student photo.
  - Long string literals split with `.` concatenation (same values).
  - Section-header comments (`// ── Title ──`, `// ═══`) changed to the
    Moodle comment style; inline comments capitalised and punctuated.
  - Missing PHPDoc `@param`/`@return` added (`stv_filter_students`,
    `stv_get_results`, `stv_get_all_results`, `stv_by_subject`,
    `stv_prof_counts`, `stv_render_filters`, `stv_filter_select`,
    `stv_subject_chart`, `stv_render_table`, `stv_get_grade_currency`, and
    the RIMP/WEP import and edit helpers); `{@link}` → `{@see}` in the
    restore task.
  - Unneeded `defined('MOODLE_INTERNAL')` checks removed from `lib.php`,
    `db/install.php`, `db/upgrade.php` and the backup/restore step files,
    as the code checker requires.
  - `view.php`: trailing spaces removed inside the state-test proficiency
    SQL string (whitespace only); two commented-out "Proficiency legend"
    blocks removed (they were already disabled).

### Removed
- `backup/moodle2/backup_statetestview_activity_task.class.php`,
  `backup_statetestview_stepslib.php`,
  `restore_statetestview_activity_task.class.php`,
  `restore_statetestview_stepslib.php` — leftovers from the old plugin name
  that were never loaded and duplicated the mtssdashboard class names.
- `mtssdashboard_additions.php` — old language-string notes, never loaded.

## [2026112000] — 2026-11-20 (v0.4.7)

### Added
- **iReady Lessons for RIMP Students** — new card on the MS Warning Levels
  tab, next to "iReady Lessons by Grade". Managers check grades per subject
  (Reading/ELA, Math); for students in a checked grade **who have a RIMP**,
  "Days Since ELA/Math Lesson" is blanked ("—") and not counted toward
  Overall Risk. Students without a RIMP and percentile columns are
  unaffected. "Has a RIMP" = any `mtssdashboard_rimp` record (Draft or
  Finalized), matching the Early Warning RIMP ✓ column.
  - Stored as plugin config `rimplessonsoff_{level}_{subject}`
    (e.g. `rimplessonsoff_ms_math` = `7`). No database changes.
  - New language strings: `rimplessonsoff_heading`, `rimplessonsoff_desc`,
    `rimplessonsoff_none`.

### Changed
- `lib.php`: `stv_get_lessons_off_grades()` takes an optional `$rimponly`
  flag to read the RIMP setting; `stv_apply_lessons_off()` takes an
  optional `$hasrimp` flag and blanks lesson days when either the grade
  toggle or (for RIMP students) the RIMP toggle is on. Both default to the
  old behaviour.
- `view.php`: the Warning Levels save handler processes both cards (each
  behind its own hidden `*_present` marker); the iReady Lessons card code
  now loops to draw both cards; the Early Warning table and Download CSV
  pass each student's RIMP status into `stv_apply_lessons_off()`. Download
  CSV now loads RIMP data (`stv_get_rimp_by_studentnumber()`) for this.

## [2026103001] — 2026-10-30 (v0.4.6)

### Added
- **Download RIMPs / Download WEPs** — new button beside **View** on the RIMP
  and WEP tabs (`view.php`). Opens, in a new tab, one print-ready page with
  every finalized (status = 1) RIMP or WEP for the students currently shown:
  the active Moodle group plus the applied demographic filters. Students are
  in last-name order and each report starts on a new printed page. The
  browser print dialog opens on load so staff can Save as PDF and upload
  one file to ParentSquare Secure Documents. Drafts and students without a
  record are skipped.
  - Handled in `view.php` as `action=downloadrimps` / `action=downloadweps`
    (sesskey-protected, same pattern as the Early Warning CSV download),
    gated by `mod/mtssdashboard:editrimp` / `mod/mtssdashboard:editwep`.
  - New `lib.php` helper `mtssdashboard_render_bulk_reports()` reuses the
    existing `mtssdashboard_render_rimp_report()` /
    `mtssdashboard_render_wep_report()` output, so bulk and single-student
    printouts always match. The shared renderers used by block_parentgrades
    are unchanged.
  - New language strings: `rimp_btn_download`, `rimp_btn_download_help`,
    `rimp_bulk_title`, `rimp_bulk_count`, `rimp_bulk_none`,
    `wep_btn_download`, `wep_btn_download_help`, `wep_bulk_title`,
    `wep_bulk_count`, `wep_bulk_none`, `bulk_btn_print`.

- **CTC and departed students hidden on RIMP/WEP tabs** — students whose
  `msb_contacts.StudentStatus` is J or NJ (attending the career-tech center)
  or I or R (no longer attending) are left out of the RIMP and WEP tables,
  student dropdowns, and Download RIMPs/Download WEPs, since plans are not
  written for them. Other tabs are unchanged.
  - `mtssdashboard_get_enrolled_students_with_ssid()` now also returns
    `studentstatus`.
  - New `lib.php` helper `mtssdashboard_remove_skipped_students()`; the
    skipped codes are the `$skip` list inside it.
- **School name follows the current grade** — RIMP and WEP printouts now
  show the school for the student's current grade (`GradeLevelCode` and the
  plugin's HS/MS/ES grade ranges) instead of the school stored in the import
  table, which went stale when a student moved up a building (9th graders
  printed as Middle School). Falls back to the import value if the grade is
  unknown. New helper `mtssdashboard_school_name_for_grade()` and language
  strings `school_name_es`, `school_name_ms`, `school_name_hs`. Also applies
  to the single-student Print pages and the block_parentgrades parent view.

### Fixed
- **WEP header layout** — the Student ID / Date of Birth / Current Grade /
  Gender and School Year / Review Date rows are now one left-aligned table.
  Values no longer wrap (Date of Birth wrapped in Chrome) and the labels
  line up under each other.
- **RIMP page breaks in Download RIMPs** — the RIMP report's "Plans for
  Monitoring Student Success" section was missing its closing `</div>`, so
  each student's RIMP was nested inside the previous one and never started
  a new printed page. The section is now closed properly.
- **RIMP tab View button** — the student selector form submitted
  `tab=mtss` (an old tab name), so the selected student's panel never
  appeared. It now submits `tab=rimp`.
- **Filters kept on View** — the RIMP and WEP student selector forms now
  carry the applied demographic filters, so pressing View no longer resets
  them (and a following Download uses the same filtered list).

## [2026101500] — 2026-10-15 (v0.4.5)

### Added
- **iReady Lessons by Grade toggle** — new card on the MS Warning Levels
  tab (`view.php`). Managers can check grades that take iReady diagnostics
  but not lessons, separately for Reading (ELA) and Math. For those
  students "Days Since ELA/Math Lesson" is blanked ("—") and excluded from
  the Overall Risk flag count. Percentiles are unaffected. Reason: 7th
  grade dropped iReady Math lessons in 2026-27.
  - Saved through the existing Edit thresholds form as plugin config
    `lessonsoff_{level}_{ela|math}`; no schema change.
  - New `lib.php` helpers: `stv_get_school_level_grades()`,
    `stv_get_lessons_off_grades()`, `stv_apply_lessons_off()`.
  - New language strings `lessonsoff_*`.

### Fixed
- **Early Warning CSV** — the Days Since ELA/Math Lesson columns were in the
  export but never populated, so they were always blank and the CSV's
  Overall Risk could differ from the screen. They are now filled (with the
  lessons toggle applied), labeled properly, and 99 exports as "Never".

## [2026092200] — 2026-09-22 (v0.4.4)

### Added
- **Reset WEP** — a "Reset WEP" button now sits between Print and Delete
  on the WEP edit screen (`wep_edit.php`), letting a teacher clear a prior
  year's WEP for reuse. A confirmation popup names the target school year
  before anything changes. On confirm, it sets the school year to the
  current one, sets the review date to today, clears every goal/progress/
  parent-input/signature text field, and leaves all checked boxes as they
  are. Nothing is written to the database until the teacher clicks Save as
  Draft or Save WEP, so canceling out of the form afterward leaves the
  record untouched.

## [2026090401] — 2026-09-04 (v0.4.2)

### Added
- **Delete RIMP / Delete WEP** — teachers can now delete a student's RIMP or
  WEP record directly from the edit screen (`rimp_edit.php` / `wep_edit.php`)
  when it no longer applies. A "Delete RIMP"/"Delete WEP" button was added
  between the Print and Cancel buttons. Clicking it shows a confirmation
  popup ("Are you sure you want to delete this...? This cannot be undone.")
  before anything happens — canceling the popup leaves the record untouched.
  - New endpoints `rimp_delete.php` and `wep_delete.php` handle the actual
    delete, gated behind the same `mod/mtssdashboard:editrimp` /
    `mod/mtssdashboard:editwep` capabilities as editing, plus a sesskey
    check.
  - Deleting also releases any active edit lock on that record.
  - Deleting redirects back to the RIMP/WEP tab with a success notification.

## [2026090400] — 2026-09-04 (v0.4.1)

### Changed
- **RIMP K-3 "On Track" benchmark scores moved to the language file** — the
  Kindergarten/Grade 1/Grade 2/Grade 3 iReady "On Track" cut scores shown
  on the RIMP report (`rimp_print.php` via `lib.php`) were previously
  hardcoded directly in `lib.php`. They're now pulled via `get_string()`
  from four new language strings: `rimp_k_ontrack`, `rimp_grade1_ontrack`,
  `rimp_grade2_ontrack`, `rimp_grade3_ontrack`. Updating a benchmark score
  going forward only requires editing `lang/en/mtssdashboard.php` — no
  code changes needed.

## [2026081400] — 2026-08-14 (v0.4.0)

### Added
- **RIMP/WEP edit locking** — prevents two teachers from editing the same
  student's RIMP or WEP at the same time and silently overwriting each
  other's work.
  - New table `mtssdashboard_edit_sessions` tracks who currently has a
    given student's RIMP or WEP open for editing.
  - Opening `rimp_edit.php` / `wep_edit.php` while someone else is already
    editing that same record shows a block screen: "This RIMP/WEP is
    currently being edited by [Name]. Please close this window and try
    again later." Clicking OK closes the tab (or falls back to returning
    to the dashboard if the browser won't let the tab close itself).
  - While a teacher is actively editing, the page pings a heartbeat every
    45 seconds to keep the lock alive. Locks with no heartbeat for 2
    minutes (crashed tab, lost connection, etc.) are treated as abandoned
    and automatically released — no stuck locks requiring admin cleanup.
  - The lock is released as soon as the teacher saves (draft or finalize)
    or navigates away/closes the tab (via `navigator.sendBeacon`).
  - **Safety net:** even with the lock in place, each save also compares
    the record's `timemodified` against what was loaded in the form. If
    it doesn't match — meaning someone else saved in between — the save
    is rejected with a clear error instead of silently overwriting their
    changes.
  - New AJAX endpoint `edit_lock_ajax.php` handles the heartbeat and
    release calls. No new capabilities required — it reuses the existing
    `mod/mtssdashboard:editrimp` / `mod/mtssdashboard:editwep` checks.

## [2026080400] — 2026-08-04 (v0.3.0)

### Fixed
- **Course backup/restore broken since the plugin rename** — the four files
  under `backup/moodle2/` were never renamed when the plugin moved from
  `mod_statetestview` to `mod_mtssdashboard`. Moodle's backup/restore loader
  builds the expected filename from the component name
  (`backup_mtssdashboard_activity_task.class.php`, etc.), so it could not find
  the still `statetestview`-named files and threw
  `Exception - Class "backup_mtssdashboard_activity_task" not found` any time
  a course containing this activity was backed up (automated or manual). This
  silently broke automated nightly backups for every course the activity was
  bulk-deployed into this spring.
  - Renamed `backup_statetestview_activity_task.class.php` →
    `backup_mtssdashboard_activity_task.class.php`.
  - Renamed `backup_statetestview_stepslib.php` →
    `backup_mtssdashboard_stepslib.php`.
  - Renamed `restore_statetestview_activity_task.class.php` →
    `restore_mtssdashboard_activity_task.class.php`.
  - Renamed `restore_statetestview_stepslib.php` →
    `restore_mtssdashboard_stepslib.php`.
  - No class or field names needed to change — the code inside each file
    already referenced `mtssdashboard` correctly; only the filenames were
    stale.

## [2026071501] — 2026-07-15 (v0.2.6)

### Added
- **Student Info tab contact/grades panel** — new two-column section on the
  Student Data tab (below the Grade/Gender/504/IEP/WEP/RIMP/Gifted badge row,
  above the trend/radar charts):
  - **Contact Information** — student photo (Moodle user picture, same
    pluginfile pattern as `rimp_print.php`/`wep_print.php`), address
    (`contacts.Address`/`Address2`/`City`/`State`/`Zip`, blank lines skipped),
    Parent/Guardian (`contacts.ParentName`), Home Phone (`contacts.HomePhone`),
    and Email (`contacts.Email`) rendered as a clickable `mailto:` link.
  - **Classes & Grades** — table of every course with a recorded final grade:
    course name, teacher (single earliest-assigned `editingteacher`), and
    percentage with letter grade, e.g. `88.95% (B+)`.
  - Field labels render as real `<th>` cells (via `html_table_cell->header`)
    using the same `generaltable table-sm` classes as the grades table, so
    label styling matches exactly rather than approximating with a utility class.
- **504/IEP/WEP/RIMP badges now link out** — 504 and IEP badges link to the
  student's plan PDF via `plan.php` (same file/route the Early Warning tab's
  Student ID column already used) when one exists for that student. New WEP
  and RIMP badges were added to the same row, linking to `wep_print.php` and
  `rimp_print.php` respectively when a record exists.
- **`stv_get_student_course_grades()`** in `lib.php` — fetches a single
  student's enrolled courses with final grade percentage, letter (via the
  same management-course grade-letter scale used by the Grades tab), and the
  earliest-assigned `editingteacher` for each course.
- New language strings: `studentdata_contact_heading`, `studentdata_address`,
  `studentdata_parentname`, `studentdata_homephone`, `studentdata_email`,
  `studentdata_classes_heading`, `studentdata_col_course`,
  `studentdata_col_teacher`, `studentdata_col_grade`, `studentdata_nogrades`.

### Changed
- **Tab renamed and reordered** — "Student Data" tab renamed to "Student Info"
  (`tab_student` string) and moved to the second tab position, immediately
  after Early Warning (previously third, after Grades). The tab's page heading
  reuses the same string, so it now reads "<Student> — Student Info" as well.
- **`version.php`** bumped to `2026071501` / release `0.2.6`.

---

## [2026052802] — 2026-06-01 (v0.2.5)

### Added
- **WEP tab** — new tab visible to all course teachers (teacher, editingteacher,
  manager) for creating and editing Written Education Plans for gifted students.
  - Student selector with group and demographic filter support.
  - Class WEP status table showing Draft/Finalized badges and last modified date.
  - Create New WEP / Edit WEP / Print WEP action buttons per student.
- **`wep_edit.php`** — WEP create/edit form page.
  - ES and MS/HS form variants differ in service setting options and subject coverage.
  - ES: Reading/Writing and Mathematics goals only.
  - MS/HS: Reading/Writing, Mathematics, Science, and Social Studies goals.
  - Fields grouped by subject — goal, mid year progress, and end of year progress
    are together for each subject area.
  - Identification areas: Superior Cognitive, Superior Creative, Reading/Writing,
    Mathematics, Science, Social Studies.
  - ES service settings: Cluster Grouping (HIVE time), Other.
  - MS/HS service settings: Cluster Grouping (205062), Acceleration (205052),
    College Credit Plus (205070), Other.
  - Draft and Finalize workflow with sesskey CSRF protection.
  - Print WEP button appears on existing WEPs (opens in new tab).
- **`wep_print.php`** — print-friendly WEP report page.
  - RIMP-style header: school name, student photo, student meta table.
  - School name sourced from import staging table, cleaned to proper case
    (e.g. "NT NATIONAL TRAIL HIGH SCHOOL" → "National Trail High School").
  - Only checked identification areas shown in checkboxes section.
  - Goal and progress fields shown only for checked identification areas — empty
    fields included as a reminder to the teacher to complete them.
  - Superior Cognitive or Creative Thinking Ability → all subject areas shown.
  - ES: Reading/Writing and Mathematics sections only.
  - MS/HS: all four subject sections as applicable.
  - Draft/Finalized status badge. Print button top and bottom.
- **`wep_import.php`** — one-time CLI import script for district WEP CSV exports.
  - Imports ES and MS/HS CSVs in a single run.
  - Populates both `mtssdashboard_wep_import` staging and `mtssdashboard_wep` live tables.
  - Merges into existing WEP records without overwriting teacher-entered data.
  - Parses comma-separated identification areas, service settings, progress measures,
    and personnel into JSON key arrays matching checkbox keys.
  - Plan Status "Complete" → Finalized (status=1); all others → Draft.
- **Two new database tables** via upgrade step 2026052800:
  - `mtssdashboard_wep` — editable WEP records per student.
  - `mtssdashboard_wep_import` — staging table for district WEP CSV imports.
- **`mod/mtssdashboard:editwep`** capability — granted to teacher, editingteacher,
  manager. Controls WEP tab visibility and wep_edit.php access.
- **`mod/mtssdashboard:importwep`** capability — manager only. Reserved for
  future web-based import UI.
- **`mod/mtssdashboard:editrimp`** capability — granted to teacher, editingteacher,
  manager. Replaces the previous `stv_can_edit_warning_levels()` gate on the RIMP tab.

### Changed
- **RIMP tab** — now visible to all course teachers (teacher, editingteacher, manager)
  in any course, matching WEP tab behavior. Previously restricted to managers on the
  management course only.
- **`rimp_edit.php`** — access now controlled by `editrimp` capability instead of
  `stv_can_edit_warning_levels()`. Any course teacher can create and edit RIMPs.
- **`rimp_edit.php`** — Print RIMP button added next to Finalize button. Appears
  only when an existing RIMP record exists. Opens print report in a new tab.
- **RIMP and WEP finalize** — confirmation popup removed. Saving as Finalized
  now saves immediately without a JavaScript confirmation dialog.
- **RIMP and WEP tabs** — top filter bar (group, gender, IEP, Gifted, etc.) now
  works correctly on both tabs. Duplicate internal group selector removed.
- **WEP tab label** — displays as "WEP" (not "WEPs").
- **`version.php`** bumped to `2026052802` / release `0.2.5`.

---

## [2026052700] — 2026-05-27

### Added
- **Plans tab** — new tab (managers only, all school levels) showing a sortable
  student plan status table.
  - Columns: Student Name, Grade, Student ID, Status (IEP/504/Gifted badges), Plan Date.
  - Plan Date is a clickable link that opens the student's PDF plan via `plan.php`.
  - Date is sourced from the file's last modified timestamp.
  - Plans older than 365 days are highlighted with a muted orange background.
  - Plan Date column is sortable (oldest first / newest first) via a click toggle.
  - Supports all existing demographic filters (Gender, IEP, 504, Gifted, etc.)
    and group filter.
  - Students with no plan file show "No Plan" in muted text.

### Changed
- **History tab** — now hidden on Elementary School courses (ES has no previous
  school level data).
- **Grades tab** — now hidden on Elementary School courses (ES has no gradebook
  grades).
- **Plans column header** label changed from "Plan" to "Plan Date".

---

## [2026052600] — 2026-05-27

### Added
- **RIMP tab** — new tab on the mtssdashboard activity (managers only) for creating
  and editing Ohio Reading Improvement and Monitoring Plans (RIMPs) for enrolled students.
  - Student selector with group filter support.
  - Class RIMP status table showing Draft/Finalized status and last modified date
    for all enrolled students.
  - Create New RIMP and Edit RIMP buttons per student.
  - Draft and Finalize workflow — saving a finalized RIMP reverts it to Draft with
    a JavaScript confirmation warning before any changes are accepted.
- **`rimp_edit.php`** — RIMP create/edit form page.
  - ES and MS/HS forms differ: goals, supports, monitoring, signatures, and
    Third Grade Reading Guarantee section are school-level aware.
  - All 11 instructional support checkboxes matching the district's official form.
  - MS/HS monitoring uses checkboxes (UFLR, Phonics for Reading, Rewards).
  - ES monitoring uses free-text assessment field + frequency dropdown.
  - Third Grade Reading Guarantee section (ES only) with all 4 official Ohio options.
  - Draft/Finalize buttons with sesskey CSRF protection.
- **`rimp_print.php`** — print-friendly RIMP report page (all teachers with view
  capability, session-protected).
  - Shows student photo from Moodle user profile (pluginfile.php, session-enforced).
  - Student ID, Date of Birth (from msb_contacts.Birthdate), grade, gender.
  - School name cleaned: strips leading "NT " prefix and converts to proper case.
  - ES and MS/HS reports differ in law text, benchmark scores, goals, supports,
    monitoring, Third Grade Guarantee, and signature blocks.
  - Only selected goals, supports, and monitoring options shown as a bulleted list.
  - Draft/Finalized status badge.
- **`rimp_import.php`** — one-time CLI import script for district RIMP CSV exports.
  - Populates staging and live RIMP tables.
  - Merges into existing records without overwriting teacher-entered data.
- **RIMP column on Early Warning tab** — shown for all school levels (ES, MS, HS).
  Green ✓ links to print view; — for students with no RIMP.
- **Two new database tables** via upgrade step 2026052600:
  - `msb_mtssdashboard_rimp` — editable RIMP records per student.
  - `msb_mtssdashboard_rimp_import` — staging table for district CSV imports.

### Changed
- **RIMPS tab removed** — replaced by the new RIMP tab and `msb_mtssdashboard_rimp` table.
- **`lib.php`** — `stv_get_rimp_by_studentnumber()` now reads from
  `msb_mtssdashboard_rimp`. `stv_ensure_rimp_table()` and `stv_set_rimp()` removed.
- **`version.php`** bumped to `2026052600` / release `0.2.4`.

### Removed
- `msb_NTrimp` table is no longer used. Can be dropped after confirming migration.

---

### Changed
- **Plugin renamed** from `mod_statetestview` to `mod_mtssdashboard` to reflect its
  broader scope as a full MTSS dashboard, not just state testing data.
- **Removed dependency on `local_statetesting` plugin.** State testing data is now
  imported directly via `NTstatetesting.php` cron script into `msb_NTstatetesting`.
  The `local_statetesting` plugin is no longer required and can be uninstalled.
- **State testing table** renamed from `msb_local_statetesting_results` to
  `msb_NTstatetesting` to match the naming convention of other NT data tables.
- All internal database queries updated to use raw table name `msb_NTstatetesting`
  directly instead of Moodle XMLDB notation, consistent with other external data tables.
- README and CHANGELOG updated to reflect new plugin name, architecture, and dependencies.

### Added
- `NTstatetesting.php` — standalone cron import script for Ohio state testing CSV files.
  Replaces the `local_statetesting` plugin import UI. Reads from
  `$CFG->dataroot/moodleimport/OHstatetesting.csv`. Safe to run via cron — requires
  minimum 100 rows before truncating existing data. Falls back to row-by-row PHP insert
  if LOAD DATA LOCAL INFILE is unavailable.

# Changelog — mod_statetestview (My Student Data)

All notable changes to this project are documented here.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [2026042504] — 2026-04-26

### Added
- **Configurable Risk Level Thresholds.** Low, Moderate, and Extreme Risk flag-count
  thresholds are now stored per school level in `msb_statetestview_warning_levels`
  and editable on the management course Warning Levels tab (alongside band thresholds).
  Default: Low = 1 flag, Moderate = 2–3, Extreme = 4+.
- **ELA/Math Graduation Passing Score** now displayed as an editable "Minimum Score"
  card on the Warning Levels tab (HS only, default: 684). Previously the card label
  showed `[[color_config]]` — fixed.
- **Risk Level Thresholds card** on Warning Levels tab, visually distinct with a blue
  border. Shows Low/Moderate/Extreme flag-count thresholds with a "No Risk" note.
  Editable in edit mode with single-number inputs.

### Fixed
- Warning Levels edit button now works correctly (URL parameter `$editmode` was being
  dropped from the parameter block during a previous refactor).
- Sort by Risk button on Early Warning tab was broken (`$ewsort` parameter was missing
  from the parameter parsing block). Restored.
- Cancel editing button on Warning Levels was rendering oversized. Fixed with
  `align-items:flex-start` on the flex container.

---

## [2026042503] — 2026-04-25

### Added
- **HS OST Graduation Passing Score** stored as a `config` sentinel row in
  `msb_statetestview_warning_levels`. A Basic score ≥ 684 on ALG1, MTH1, or ELA2
  is now excluded from the Low OST Performance count (graduation competency rule).
  Score is configurable — not hardcoded.

### Changed
- `stv_get_low_ost_by_ssid()` now applies a split HS/non-HS rule:
  - **HS tests** (`grade_level = 'High School'`): all counted, no time limit,
    subject codes ALG1/MTH1/ELA2 respect the passing score override.
  - **MS/ES tests**: most recent per subject area within the last 2 years only.
- iReady data now filtered to **current school year** (AcademicYear matching
  `gradyear` setting) instead of a rolling 365-day window.

---

## [2026042502] — 2026-04-25

### Added
- **Elementary School (ES) warning level seed data.** 20 rows seeded for ES covering:
  Total Hours Missed, Unexcused Hours Missed, iReady ELA Percentile, iReady Math
  Percentile, OST Performance. ES does not have Office Incidents, Course Failure Count,
  or GPA thresholds.
- ES-specific OST label: "OST Performance (Grades 3 & 4 only)" via lang string
  `metric_ost_performance_es`, used on Warning Levels tab and Early Warning column header.
- School-aware metric label lookup on Warning Levels tab (falls back to generic if no
  school-specific override exists).

### Fixed
- ES courses on Early Warning tab now show the correct 5-column metric set instead of
  the HS/MS 8-column set.

---

## [2026042501] — 2026-04-25

### Added
- **Early Warning tab** — new tab showing a color-coded at-risk student table.
  - 8 metrics for HS/MS; 5 for ES.
  - Overall Risk Level computed per student (No/Low/Moderate/Extreme).
  - Sort by Risk toggle button (server-side, preserves active filters).
  - Group filter and demographic filters supported.
  - iReady ELA and Math Percentile columns wired to `msb_NTireadyela` /
    `msb_NTireadymath` tables.
  - Office Incidents from `msb_NTdiscipline` (count per StudentId).
  - Attendance (Total Hours, Unexcused Hours) from `msb_NTattendance`.
  - YTD Failed Courses from Moodle gradebook (courses matching `^[0-9]{4}[A-Z]{2}$`).
  - GPA calculated from gradebook using management course letter scale (4.0 standard).
  - Low OST Performance Count from `msb_NTstatetesting`.
- **Warning Levels tab** — configurable threshold bands for Early Warning metrics.
  - Per-school-level configuration (HS, MS, ES independent).
  - Read-only everywhere; editable only on the designated management course by
    managers or site admins.
  - Edit form with min/max inputs per band, CSRF-protected (sesskey).
  - JS confirmation dialog before save.
  - `stv_format_threshold_text()` auto-regenerates human-readable labels from
    min/max values on save.
- **Elementary management course** setting (`es_mgmt_course`, default: 118).
- **Warning level database table** (`msb_statetestview_warning_levels`) with 64 seed
  rows (32 HS + 32 MS) covering 8 metrics × 4–5 color bands each.
- Helper functions in `lib.php`:
  - `stv_get_school_level_for_course()` — walks category tree to determine HS/MS/ES.
  - `stv_is_management_course()` — checks if current course is the mgmt course.
  - `stv_can_edit_warning_levels()` — role + management course check.
  - `stv_get_band_color()` — cached threshold lookup (static cache per request).
  - `stv_band_is_flagged()` — returns true for orange/red/purple bands.
  - `stv_compute_risk_level()` — maps flag count to risk key.
  - `stv_format_threshold_text()` — auto-generates human-readable threshold labels.
  - `stv_get_office_incidents_by_studentnumber()` — bulk query, `NTdiscipline`.
  - `stv_get_attendance_by_studentnumber()` — bulk query, `NTattendance`.
  - `stv_get_failed_courses_by_userid()` — gradebook F-count, shortname-filtered.
  - `stv_get_gpa_by_userid()` — unweighted GPA using management course letter scale.
  - `stv_get_low_ost_by_ssid()` — OST low score count per student.
  - `stv_get_iready_ela_by_studentnumber()` — most recent ELA percentile.
  - `stv_get_iready_math_by_studentnumber()` — most recent Math percentile.

### Changed
- Tab order: Early Warning, Grades, Student Data, State Testing, Subject History,
  History, Warning Levels. Default landing tab changed to Early Warning.
- "Student View" tab renamed to "Student Data".
- Filters hidden on Warning Levels tab (thresholds are school-wide, not per-student).

---

## [2026042500] — 2026-04-25

### Added
- **`db/upgrade.php`** created from scratch (did not previously exist).
- **`msb_statetestview_warning_levels` table** created via XMLDB upgrade step.
  Fields: id, schoollevel, metric_key, color, threshold_min, threshold_max,
  threshold_text, sortorder, timemodified, usermodified.

---

## [Pre-2026042500] — Initial Build

### Added
- **State Testing tab** — proficiency distribution doughnut + per-subject doughnuts
  with center totals and slice count labels. Color-coded proficiency table.
- **History tab** — previous school level state testing results. Same chart layout.
  Collapsible data table.
- **Subject History tab** — year-over-year cohort line charts per subject family
  (ELA, Math, Science, Social Studies). Y-axis = proficiency level (1–5).
  Lines colored per graduation cohort. Count labels on each data point.
- **Student Data tab** — single-student view with:
  - Score Trend Over Time chart (one line per subject area, proficiency Y-axis,
    score labels on dots, dots colored to match line).
  - Subject Proficiency Profile radar (one polygon per year, carry-forward for
    missing areas).
  - Student info card (Grade, Gender, 504, IEP, Gifted).
  - Collapsible data table.
  - Student dropdown auto-submits on change. No Apply/Reset buttons.
  - Group selector filters the student dropdown list.
- **Grades tab** — grade distribution doughnut (this course or all courses) plus
  per-subject-category doughnuts. Collapsible student grade detail table.
  Demographic and group filtering. Grade letter scale from management course.
- **Admin settings** — graduation year, school category IDs, grade ranges,
  management course IDs, subject category ID mappings for HS and MS.
- Group filter preserved across tab navigation via `$PAGE->set_url()` fix.
- Grades tab group filter bug fixed (`statetestview_get_grades_data()` was
  overwriting the passed-in student list with a fresh DB query).
- Per-subject chart legend removed from State Testing and History tabs.
- Chart.js center-number plugin and slice-count plugin applied to all doughnut
  charts for consistency with the Grades tab style.
