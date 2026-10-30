<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * One-time RIMP CSV import script for mod_mtssdashboard.
 *
 * Imports the district RIMP CSV exports into mtssdashboard_rimp_import
 * (staging/reference table) and — for students who do NOT already have a
 * record in mtssdashboard_rimp — also creates an initial mtssdashboard_rimp
 * record so teachers do not have to re-enter data.
 *
 * Students who already have a record in mtssdashboard_rimp are SKIPPED
 * entirely; their existing work is never overwritten.
 *
 * Usage (run as root on the server):
 *   php /var/www/html/mod/mtssdashboard/rimp_import.php
 *
 * Place both CSV files in /tmp/ before running:
 *   /tmp/rimp_es.csv   — student-District-CSVReport.csv     (Elementary)
 *   /tmp/rimp_ms.csv   — student-District-CSVReport_MS.csv  (Middle School)
 *
 * @package    mod_mtssdashboard
 * @copyright  2026 National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────────
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

// ── Config ────────────────────────────────────────────────────────────────────
$files = [
'es' => '/var/www/moodledata/NTimports/plans/RIMP WEP/student-District-CSVReport.csv',
'ms' => '/var/www/moodledata/NTimports/plans/RIMP WEP/student-District-CSVReport MS.csv',
];

$now = time();

// ── Goal text → JSON key mapping ─────────────────────────────────────────────
// Keys are substrings that appear in the comma-separated goals column.
$goal_map = [
    'Phonemic Awareness'   => 'phonemic',
    'Phonics'              => 'phonics',
    'Reading Fluency'      => 'fluency',
    'Vocabulary'           => 'vocabulary',
    'Reading Comprehension'=> 'comprehension',
];

// ── Support text → JSON key mapping ──────────────────────────────────────────
$support_map = [
    'Phonemic Awareness'          => 'phon_awareness',
    'High Frequency Word'         => 'high_freq_words',
    'Decoding'                    => 'decoding',
    'Fluency'                     => 'fluency',
    'Comprehension'               => 'comprehension',
    'Vocabulary'                  => 'vocabulary',
];

// ── Helper: parse a comma-separated text field into a JSON array ──────────────
function rimp_parse_csv_list($raw, $map) {
    if (empty($raw) || strtolower(trim($raw)) === 'nan') {
        return json_encode([]);
    }
    $parts  = explode(',', $raw);
    $result = [];
    foreach ($parts as $part) {
        $part = trim($part);
        foreach ($map as $needle => $key) {
            if (stripos($part, $needle) !== false) {
                if (!in_array($key, $result, true)) {
                    $result[] = $key;
                }
                break;
            }
        }
    }
    return json_encode($result);
}

// ── Helper: safely get a string value, return null if empty/NaN ──────────────
function rimp_str($val) {
    if ($val === null || trim((string)$val) === '' || strtolower(trim((string)$val)) === 'nan') {
        return null;
    }
    return trim((string)$val);
}

// ── Helper: safely get an int value ──────────────────────────────────────────
function rimp_int($val) {
    $s = rimp_str($val);
    if ($s === null) {
        return null;
    }
    $i = intval($s);
    return ($i > 0) ? $i : null;
}

// ── Helper: parse a date string to a Unix timestamp ──────────────────────────
function rimp_date($val) {
    $s = rimp_str($val);
    if ($s === null) {
        return null;
    }
    $ts = strtotime($s);
    return ($ts && $ts > 0) ? $ts : null;
}

// ── Helper: map Plan Status to our status field ───────────────────────────────
// 'Complete' → 1 (Finalized), anything else → 0 (Draft).
function rimp_status($val) {
    return (rimp_str($val) === 'Complete') ? 1 : 0;
}

// ── Process each file ─────────────────────────────────────────────────────────
foreach ($files as $schoollevel => $filepath) {

    if (!file_exists($filepath)) {
        echo "SKIP: $filepath not found — skipping $schoollevel.\n";
        continue;
    }

    echo "\n=== Importing $schoollevel from $filepath ===\n";

    // Open and parse CSV.
    $fh = fopen($filepath, 'r');
    if (!$fh) {
        echo "ERROR: Cannot open $filepath\n";
        continue;
    }

    // Read header row.
    $headers = fgetcsv($fh);
    if (!$headers) {
        echo "ERROR: Empty file $filepath\n";
        fclose($fh);
        continue;
    }

    // Trim headers (CSVs sometimes have BOM or trailing spaces).
    $headers = array_map('trim', $headers);

    $imported  = 0;
    $skipped   = 0;
    $rimp_new  = 0;
    $rimp_skip = 0;
    $errors    = 0;
    $rownum    = 1;

    while (($row = fgetcsv($fh)) !== false) {
        $rownum++;

        if (count($row) !== count($headers)) {
            echo "  WARN row $rownum: column count mismatch, skipping.\n";
            $errors++;
            continue;
        }

        $r = array_combine($headers, $row);

        // ── Read raw fields ───────────────────────────────────────────────────
        $studentnumber = rimp_int($r['Student ID'] ?? null);
        if (!$studentnumber) {
            echo "  WARN row $rownum: no Student ID, skipping.\n";
            $errors++;
            continue;
        }

        $goals_json    = rimp_parse_csv_list(
            $r['This plan was created for this student with the goal of improving:'] ?? '',
            $goal_map);
        $supports_json = rimp_parse_csv_list(
            $r['Instructional Supports:'] ?? '',
            $support_map);

        // Monitoring field differs between ES and MS.
        if ($schoollevel === 'ms') {
            $monitoring_assessment = rimp_str(
                $r['Assessment used for Progress Monitoring and Frequency:'] ?? null);
            $monitoring_frequency  = null; // MS combines both into one field.
        } else {
            $monitoring_assessment = null;
            $monitoring_frequency  = rimp_str($r['Frequency of Monitoring:'] ?? null);
        }

        // Signature fields differ between ES and MS.
        $sig_parent       = rimp_str($r['Parent/Guardian: _________________________________________________'] ?? null);
        $sig_teacher      = rimp_str($r['Current Teacher: _________________________________________________'] ?? null);
        $sig_ela_teacher  = rimp_str($r['English language arts Teacher: _________________________________________________'] ?? null);
        $sig_intervention = rimp_str($r['Intervention/Title Teacher: _________________________________________________'] ?? null);

        // ── Build import staging record ───────────────────────────────────────
        $import_rec = (object) [
            'studentnumber'    => $studentnumber,
            'firstname'        => rimp_str($r['First Name']         ?? null),
            'lastname'         => rimp_str($r['Last Name']          ?? null),
            'gender'           => rimp_str($r['Gender']             ?? null),
            'current_school'   => rimp_str($r['Current School']     ?? null),
            'current_grade'    => rimp_int($r['Current Grade']      ?? null),
            'student_status'   => rimp_str($r['Student Status']     ?? null),
            'plan_status'      => rimp_str($r['Plan Status']        ?? null),
            'last_modified'    => rimp_str($r['Last Modified']      ?? null),
            'student_score'    => rimp_int($r['Student Score:']     ?? null),
            'assessment_status'=> rimp_str($r['Student Assessment Status'] ?? null),
            'effective_date'   => rimp_str($r['RIMP Effective Date:']      ?? null),
            'goals'            => $goals_json,
            'supports'         => $supports_json,
            'days_per_week'    => rimp_str($r['Number of Days Per Week:']    ?? null),
            'minutes_per_week' => rimp_int($r['Number of Minutes Per Week:'] ?? null),
            'monitoring_freq'  => $monitoring_assessment ?? $monitoring_frequency,
            'monitored_by'     => rimp_str($r['Intervention Plan Monitored By:'] ?? null),
            'grade3_select'    => rimp_str($r['Select One'] ?? null),
            'sig_parent'       => $sig_parent,
            'sig_teacher'      => $sig_teacher,
            'schoollevel'      => $schoollevel,
            'timeimported'     => $now,
        ];

        // Always upsert the staging table (replace existing import row).
        $existing_import = $DB->get_record('mtssdashboard_rimp_import',
            ['studentnumber' => $studentnumber, 'schoollevel' => $schoollevel]);
        if ($existing_import) {
            $import_rec->id = $existing_import->id;
            $DB->update_record('mtssdashboard_rimp_import', $import_rec);
        } else {
            $DB->insert_record('mtssdashboard_rimp_import', $import_rec);
        }
        $imported++;

        // ── Build mtssdashboard_rimp record — SKIP if one already exists ──────
        $existing_rimp = $DB->record_exists('mtssdashboard_rimp',
            ['studentnumber' => $studentnumber]);

        if ($existing_rimp) {
            // Merge — only fill in fields that are currently empty.
            $existing = $DB->get_record('mtssdashboard_rimp', ['studentnumber' => $studentnumber]);
            $merge = (object) ['id' => $existing->id];
            $merge->timemodified = rimp_date($r['Last Modified'] ?? null) ?? $existing->timemodified;
            $merge->schoolyear            = $existing->schoolyear            ?: '2025-2026';
            $merge->rimp_effective_date   = $existing->rimp_effective_date   ?: rimp_date($r['RIMP Effective Date:'] ?? null);
            $merge->student_score         = $existing->student_score         ?: rimp_int($r['Student Score:'] ?? null);
            $merge->assessment_status     = $existing->assessment_status     ?: rimp_str($r['Student Assessment Status'] ?? null);
            $merge->goals                 = ($existing->goals && $existing->goals !== '[]') ? $existing->goals : $goals_json;
            $merge->supports              = ($existing->supports && $existing->supports !== '[]') ? $existing->supports : $supports_json;
            $merge->days_per_week         = $existing->days_per_week         ?: rimp_str($r['Number of Days Per Week:'] ?? null);
            $merge->minutes_per_week      = $existing->minutes_per_week      ?: rimp_int($r['Number of Minutes Per Week:'] ?? null);
            $merge->monitoring_assessment = $existing->monitoring_assessment ?: $monitoring_assessment;
            $merge->monitoring_frequency  = $existing->monitoring_frequency  ?: $monitoring_frequency;
            $merge->monitored_by          = $existing->monitored_by          ?: rimp_str($r['Intervention Plan Monitored By:'] ?? null);
            $merge->grade3_guarantee      = $existing->grade3_guarantee      ?: rimp_str($r['Select One'] ?? null);
            $merge->sig_parent            = $existing->sig_parent            ?: $sig_parent;
            $merge->sig_teacher           = $existing->sig_teacher           ?: $sig_teacher;
            $merge->sig_ela_teacher       = $existing->sig_ela_teacher       ?: $sig_ela_teacher;
            $merge->sig_intervention      = $existing->sig_intervention      ?: $sig_intervention;
            $DB->update_record('mtssdashboard_rimp', $merge);
            echo "  INFO row $rownum: student $studentnumber — merged with existing RIMP.\n";
            $rimp_skip++;
            continue;
        }
        $rimp_rec = (object) [
            'studentnumber'         => $studentnumber,
            'schoollevel'           => $schoollevel,
            'schoolyear'            => '2025-2026',
            'rimp_effective_date'   => rimp_date($r['RIMP Effective Date:'] ?? null),
            'student_score'         => rimp_int($r['Student Score:'] ?? null),
            'assessment_status'     => rimp_str($r['Student Assessment Status'] ?? null),
            'goals'                 => $goals_json,
            'supports'              => $supports_json,
            'days_per_week'         => rimp_str($r['Number of Days Per Week:'] ?? null),
            'minutes_per_week'      => rimp_int($r['Number of Minutes Per Week:'] ?? null),
            'monitoring_assessment' => $monitoring_assessment,
            'monitoring_frequency'  => $monitoring_frequency,
            'monitored_by'          => rimp_str($r['Intervention Plan Monitored By:'] ?? null),
            'grade3_guarantee'      => rimp_str($r['Select One'] ?? null),
            'sig_parent'            => $sig_parent,
            'sig_teacher'           => $sig_teacher,
            'sig_ela_teacher'       => $sig_ela_teacher,
            'sig_intervention'      => $sig_intervention,
            'status'                => rimp_status($r['Plan Status'] ?? null),
            'timecreated'           => $now,
            'timemodified'          => rimp_date($r['Last Modified'] ?? null) ?? $now,
            'usermodified'          => 0,
        ];

        $DB->insert_record('mtssdashboard_rimp', $rimp_rec);
        $rimp_new++;

        echo "  OK  row $rownum: student $studentnumber imported.\n";
    }

    fclose($fh);

    echo "\n--- $schoollevel summary ---\n";
    echo "  Staging rows imported/updated : $imported\n";
    echo "  RIMP records created          : $rimp_new\n";
    echo "  RIMP records skipped (exist)  : $rimp_skip\n";
    echo "  Rows with errors              : $errors\n";
}

echo "\nDone.\n";
