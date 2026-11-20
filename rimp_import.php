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

// Bootstrap.
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

// Config.
$files = [
'es' => '/var/www/moodledata/NTimports/plans/RIMP WEP/student-District-CSVReport.csv',
'ms' => '/var/www/moodledata/NTimports/plans/RIMP WEP/student-District-CSVReport MS.csv',
];

$now = time();

// Goal text → JSON key mapping.
// Keys are substrings that appear in the comma-separated goals column.
$goalmap = [
    'Phonemic Awareness'   => 'phonemic',
    'Phonics'              => 'phonics',
    'Reading Fluency'      => 'fluency',
    'Vocabulary'           => 'vocabulary',
    'Reading Comprehension' => 'comprehension',
];

// Support text → JSON key mapping.
$supportmap = [
    'Phonemic Awareness'          => 'phon_awareness',
    'High Frequency Word'         => 'high_freq_words',
    'Decoding'                    => 'decoding',
    'Fluency'                     => 'fluency',
    'Comprehension'               => 'comprehension',
    'Vocabulary'                  => 'vocabulary',
];

/**
 * Parse a comma-separated text field into a JSON array of keys.
 *
 * @param string|null $raw Raw CSV cell text.
 * @param array       $map Map of text to find => key to store.
 * @return string JSON array.
 */
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

/**
 * Safely get a trimmed string value; null if empty or NaN.
 *
 * @param mixed $val Raw CSV value.
 * @return string|null
 */
function rimp_str($val) {
    if ($val === null || trim((string)$val) === '' || strtolower(trim((string)$val)) === 'nan') {
        return null;
    }
    return trim((string)$val);
}

/**
 * Safely get a positive int value; null if empty, NaN or zero.
 *
 * @param mixed $val Raw CSV value.
 * @return int|null
 */
function rimp_int($val) {
    $s = rimp_str($val);
    if ($s === null) {
        return null;
    }
    $i = intval($s);
    return ($i > 0) ? $i : null;
}

/**
 * Parse a date string to a Unix timestamp.
 *
 * @param mixed $val Raw CSV value.
 * @return int|null
 */
function rimp_date($val) {
    $s = rimp_str($val);
    if ($s === null) {
        return null;
    }
    $ts = strtotime($s);
    return ($ts && $ts > 0) ? $ts : null;
}

/**
 * Map Plan Status to our status field.
 *
 * 'Complete' → 1 (Finalized), anything else → 0 (Draft).
 *
 * @param mixed $val Raw CSV value.
 * @return int
 */
function rimp_status($val) {
    return (rimp_str($val) === 'Complete') ? 1 : 0;
}

// Process each file.
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
    $rimpnew  = 0;
    $rimpskip = 0;
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

        // Read raw fields.
        $studentnumber = rimp_int($r['Student ID'] ?? null);
        if (!$studentnumber) {
            echo "  WARN row $rownum: no Student ID, skipping.\n";
            $errors++;
            continue;
        }

        $goalsjson    = rimp_parse_csv_list(
            $r['This plan was created for this student with the goal of improving:'] ?? '',
            $goalmap
        );
        $supportsjson = rimp_parse_csv_list(
            $r['Instructional Supports:'] ?? '',
            $supportmap
        );

        // Monitoring field differs between ES and MS.
        if ($schoollevel === 'ms') {
            $monitoringassessment = rimp_str(
                $r['Assessment used for Progress Monitoring and Frequency:'] ?? null
            );
            $monitoringfrequency  = null; // MS combines both into one field.
        } else {
            $monitoringassessment = null;
            $monitoringfrequency  = rimp_str($r['Frequency of Monitoring:'] ?? null);
        }

        // Signature fields differ between ES and MS.
        $sigparent       = rimp_str($r['Parent/Guardian: _________________________________________________'] ?? null);
        $sigteacher      = rimp_str($r['Current Teacher: _________________________________________________'] ?? null);
        $sigelateacher  = rimp_str($r['English language arts Teacher: _________________________________________________'] ?? null);
        $sigintervention = rimp_str($r['Intervention/Title Teacher: _________________________________________________'] ?? null);

        // Build import staging record.
        $importrec = (object) [
            'studentnumber'    => $studentnumber,
            'firstname'        => rimp_str($r['First Name'] ?? null),
            'lastname'         => rimp_str($r['Last Name'] ?? null),
            'gender'           => rimp_str($r['Gender'] ?? null),
            'current_school'   => rimp_str($r['Current School'] ?? null),
            'current_grade'    => rimp_int($r['Current Grade'] ?? null),
            'student_status'   => rimp_str($r['Student Status'] ?? null),
            'plan_status'      => rimp_str($r['Plan Status'] ?? null),
            'last_modified'    => rimp_str($r['Last Modified'] ?? null),
            'student_score'    => rimp_int($r['Student Score:'] ?? null),
            'assessment_status' => rimp_str($r['Student Assessment Status'] ?? null),
            'effective_date'   => rimp_str($r['RIMP Effective Date:'] ?? null),
            'goals'            => $goalsjson,
            'supports'         => $supportsjson,
            'days_per_week'    => rimp_str($r['Number of Days Per Week:'] ?? null),
            'minutes_per_week' => rimp_int($r['Number of Minutes Per Week:'] ?? null),
            'monitoring_freq'  => $monitoringassessment ?? $monitoringfrequency,
            'monitored_by'     => rimp_str($r['Intervention Plan Monitored By:'] ?? null),
            'grade3_select'    => rimp_str($r['Select One'] ?? null),
            'sig_parent'       => $sigparent,
            'sig_teacher'      => $sigteacher,
            'schoollevel'      => $schoollevel,
            'timeimported'     => $now,
        ];

        // Always upsert the staging table (replace existing import row).
        $existingimport = $DB->get_record(
            'mtssdashboard_rimp_import',
            ['studentnumber' => $studentnumber, 'schoollevel' => $schoollevel]
        );
        if ($existingimport) {
            $importrec->id = $existingimport->id;
            $DB->update_record('mtssdashboard_rimp_import', $importrec);
        } else {
            $DB->insert_record('mtssdashboard_rimp_import', $importrec);
        }
        $imported++;

        // Build mtssdashboard_rimp record — SKIP if one already exists.
        $existingrimp = $DB->record_exists(
            'mtssdashboard_rimp',
            ['studentnumber' => $studentnumber]
        );

        if ($existingrimp) {
            // Merge — only fill in fields that are currently empty.
            $existing = $DB->get_record('mtssdashboard_rimp', ['studentnumber' => $studentnumber]);
            $merge = (object) ['id' => $existing->id];
            $merge->timemodified = rimp_date($r['Last Modified'] ?? null) ?? $existing->timemodified;
            $merge->schoolyear            = $existing->schoolyear ?: '2025-2026';
            $merge->rimp_effective_date   = $existing->rimp_effective_date ?: rimp_date($r['RIMP Effective Date:'] ?? null);
            $merge->student_score         = $existing->student_score ?: rimp_int($r['Student Score:'] ?? null);
            $merge->assessment_status     = $existing->assessment_status ?: rimp_str($r['Student Assessment Status'] ?? null);
            $merge->goals                 = ($existing->goals && $existing->goals !== '[]') ? $existing->goals : $goalsjson;
            $merge->supports              = ($existing->supports && $existing->supports !== '[]')
                ? $existing->supports : $supportsjson;
            $merge->days_per_week         = $existing->days_per_week ?: rimp_str($r['Number of Days Per Week:'] ?? null);
            $merge->minutes_per_week      = $existing->minutes_per_week ?: rimp_int($r['Number of Minutes Per Week:'] ?? null);
            $merge->monitoring_assessment = $existing->monitoring_assessment ?: $monitoringassessment;
            $merge->monitoring_frequency  = $existing->monitoring_frequency ?: $monitoringfrequency;
            $merge->monitored_by          = $existing->monitored_by ?: rimp_str($r['Intervention Plan Monitored By:'] ?? null);
            $merge->grade3_guarantee      = $existing->grade3_guarantee ?: rimp_str($r['Select One'] ?? null);
            $merge->sig_parent            = $existing->sig_parent ?: $sigparent;
            $merge->sig_teacher           = $existing->sig_teacher ?: $sigteacher;
            $merge->sig_ela_teacher       = $existing->sig_ela_teacher ?: $sigelateacher;
            $merge->sig_intervention      = $existing->sig_intervention ?: $sigintervention;
            $DB->update_record('mtssdashboard_rimp', $merge);
            echo "  INFO row $rownum: student $studentnumber — merged with existing RIMP.\n";
            $rimpskip++;
            continue;
        }
        $rimprec = (object) [
            'studentnumber'         => $studentnumber,
            'schoollevel'           => $schoollevel,
            'schoolyear'            => '2025-2026',
            'rimp_effective_date'   => rimp_date($r['RIMP Effective Date:'] ?? null),
            'student_score'         => rimp_int($r['Student Score:'] ?? null),
            'assessment_status'     => rimp_str($r['Student Assessment Status'] ?? null),
            'goals'                 => $goalsjson,
            'supports'              => $supportsjson,
            'days_per_week'         => rimp_str($r['Number of Days Per Week:'] ?? null),
            'minutes_per_week'      => rimp_int($r['Number of Minutes Per Week:'] ?? null),
            'monitoring_assessment' => $monitoringassessment,
            'monitoring_frequency'  => $monitoringfrequency,
            'monitored_by'          => rimp_str($r['Intervention Plan Monitored By:'] ?? null),
            'grade3_guarantee'      => rimp_str($r['Select One'] ?? null),
            'sig_parent'            => $sigparent,
            'sig_teacher'           => $sigteacher,
            'sig_ela_teacher'       => $sigelateacher,
            'sig_intervention'      => $sigintervention,
            'status'                => rimp_status($r['Plan Status'] ?? null),
            'timecreated'           => $now,
            'timemodified'          => rimp_date($r['Last Modified'] ?? null) ?? $now,
            'usermodified'          => 0,
        ];

        $DB->insert_record('mtssdashboard_rimp', $rimprec);
        $rimpnew++;

        echo "  OK  row $rownum: student $studentnumber imported.\n";
    }

    fclose($fh);

    echo "\n--- $schoollevel summary ---\n";
    echo "  Staging rows imported/updated : $imported\n";
    echo "  RIMP records created          : $rimpnew\n";
    echo "  RIMP records skipped (exist)  : $rimpskip\n";
    echo "  Rows with errors              : $errors\n";
}

echo "\nDone.\n";
