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
 * One-time WEP CSV import script for mod_mtssdashboard.
 *
 * Imports district WEP CSV exports into mtssdashboard_wep_import (staging)
 * and — for students who do NOT already have a record in mtssdashboard_wep —
 * also creates an initial mtssdashboard_wep record so teachers do not have
 * to re-enter data from the old system.
 *
 * Students who already have a record in mtssdashboard_wep are merged:
 * only empty fields are filled in from the CSV; existing work is preserved.
 *
 * Usage (run as root on the server):
 *   php /var/www/html/mod/mtssdashboard/wep_import.php
 *
 * Place both CSV files in the NTimports directory before running:
 *   /var/www/moodledata/NTimports/plans/RIMP WEP/student-District-CSVReport_WEP.csv
 *   /var/www/moodledata/NTimports/plans/RIMP WEP/student-District-CSVReport_WEP_HS-MS.csv
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
    'es'   => '/var/www/moodledata/NTimports/plans/RIMP WEP/student-District-CSVReport WEP.csv',
    'mshs' => '/var/www/moodledata/NTimports/plans/RIMP WEP/student-District-CSVReport WEP HS-MS.csv',
];

$now = time();

// Identification area text → JSON key mapping.
$idareamap = [
    'Superior Cognitive'         => 'superior_cognitive',
    'Superior Creative'          => 'superior_creative',
    'Reading/Writing'            => 'reading_writing',
    'Mathematics'                => 'mathematics',
    'Science'                    => 'science',
    'Social Studies'             => 'social_studies',
];

// Service setting text → JSON key mapping.
$svcmapes = [
    'Cluster Grouping'           => 'cluster_es',
    'Other'                      => 'other',
];

$svcmapmshs = [
    'Cluster Grouping'           => 'cluster_mshs',
    'Acceleration'               => 'acceleration',
    'College Credit Plus'        => 'college_credit',
    'Other'                      => 'other',
];

// Progress measures text → JSON key mapping.
$progressmap = [
    'Observations'               => 'observations',
    'Checklists'                 => 'observations',
    'Projects'                   => 'projects',
    'Benchmarks'                 => 'benchmarks',
    'Presentations'              => 'presentations',
    'Classroom Assessments'      => 'assessments',
    'Classroom'                  => 'assessments',
    'Other'                      => 'other',
];

// Personnel text → JSON key mapping.
$personnelmap = [
    'General Education'          => 'gen_ed',
];

/**
 * Parse a comma-separated text column into a JSON array of keys.
 *
 * @param string|null $raw Raw CSV cell text.
 * @param array       $map Map of text to find => key to store.
 * @return string JSON array.
 */
function wep_parse_list($raw, $map) {
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
 * Safely get a trimmed string; null if empty or NaN.
 *
 * @param mixed $val Raw CSV value.
 * @return string|null
 */
function wep_str($val) {
    if ($val === null || trim((string)$val) === '' || strtolower(trim((string)$val)) === 'nan') {
        return null;
    }
    return trim((string)$val);
}

/**
 * Safely get an int; null if empty, NaN or zero.
 *
 * @param mixed $val Raw CSV value.
 * @return int|null
 */
function wep_int($val) {
    $s = wep_str($val);
    if ($s === null) {
        return null;
    }
    // Strip leading zeros from grade codes like "03".
    $i = intval($s);
    return ($i > 0) ? $i : null;
}

/**
 * Parse a date string to a Unix timestamp.
 *
 * @param mixed $val Raw CSV value.
 * @return int|null
 */
function wep_date($val) {
    $s = wep_str($val);
    if ($s === null) {
        return null;
    }
    $ts = strtotime($s);
    return ($ts && $ts > 0) ? $ts : null;
}

/**
 * Map Plan Status to the status field (1 = Finalized, 0 = Draft).
 *
 * @param mixed $val Raw CSV value.
 * @return int
 */
function wep_status($val) {
    return (wep_str($val) === 'Complete') ? 1 : 0;
}

// Process each file.
foreach ($files as $schoollevel => $filepath) {
    if (!file_exists($filepath)) {
        echo "SKIP: $filepath not found — skipping $schoollevel.\n";
        continue;
    }

    echo "\n=== Importing $schoollevel from $filepath ===\n";

    $fh = fopen($filepath, 'r');
    if (!$fh) {
        echo "ERROR: Cannot open $filepath\n";
        continue;
    }

    // Read and clean header row (handle BOM).
    $rawheaders = fgetcsv($fh);
    if (!$rawheaders) {
        echo "ERROR: Empty file $filepath\n";
        fclose($fh);
        continue;
    }
    $headers = array_map(function ($h) {
        return trim(str_replace("\xEF\xBB\xBF", '', $h));
    }, $rawheaders);

    // Use the correct service map for this level.
    $svcmap = ($schoollevel === 'es') ? $svcmapes : $svcmapmshs;

    $imported  = 0;
    $wepnew   = 0;
    $wepmerge = 0;
    $errors    = 0;
    $rownum    = 1;

    while (($row = fgetcsv($fh)) !== false) {
        $rownum++;

        if (count($row) < count($headers)) {
            // Pad short rows rather than skip — some trailing columns may be empty.
            $row = array_pad($row, count($headers), '');
        }

        $r = array_combine($headers, array_slice($row, 0, count($headers)));

        // Student number.
        $studentnumber = wep_int($r['Student ID'] ?? null);
        if (!$studentnumber) {
            echo "  WARN row $rownum: no Student ID, skipping.\n";
            $errors++;
            continue;
        }

        // Parse multi-value checkbox columns.
        $idareasjson   = wep_parse_list($r['Area(s) of Identification'] ?? '', $idareamap);
        $svcjson        = wep_parse_list($r['Service Setting / EMIS Code'] ?? '', $svcmap);
        $progressjson   = wep_parse_list($r['Student Progress Measures '] ?? '', $progressmap);
        $personneljson  = wep_parse_list($r['Personnel Responsible for Service:'] ?? '', $personnelmap);

        // Parse text fields — ES vs MS/HS differ in column layout.
        if ($schoollevel === 'es') {
            // ES: single mid/end year progress fields (not per-subject).
            $goalreading   = wep_str($r['WEP Measurable Annual Reading/Writing Goal'] ?? null);
            $goalmath      = wep_str($r['WEP Measurable Annual Mathematics Goal'] ?? null);
            $goalsci       = null;
            $goalss        = null;
            $midreading    = wep_str($r['Mid Year Progress'] ?? null);
            $endreading    = wep_str($r['End of Year Progress'] ?? null);
            $midmath       = null;
            $endmath       = null;
            $midsci        = null;
            $endsci        = null;
            $midss         = null;
            $endss         = null;
            $parentinput   = wep_str($r['Parent Input'] ?? null);
            $sigparent     = wep_str($r['Parent/Guardian Representative:'] ?? null);
        } else {
            // MS/HS: per-subject mid/end year progress.
            $goalreading   = wep_str($r['WEP Measurable Annual Reading/Writing Goal'] ?? null);
            $goalmath      = wep_str($r['WEP Measurable Annual Mathematics Goal'] ?? null);
            $goalsci       = wep_str($r['WEP Measurable Annual Science Goal'] ?? null);
            $goalss        = wep_str($r['WEP Measurable Annual Social Studies Goal'] ?? null);
            $midreading    = wep_str($r['Mid Year Progress - Reading'] ?? null);
            $endreading    = wep_str($r['End of Year Progress - Reading'] ?? null);
            $midmath       = wep_str($r['Mid Year Progress - Mathematics'] ?? null);
            $endmath       = wep_str($r['End of Year Progress - Mathematics'] ?? null);
            $midsci        = wep_str($r['Mid Year Progress - Science'] ?? null);
            $endsci        = wep_str($r['End of Year Progress - Science'] ?? null);
            $midss         = wep_str($r['Mid Year Progress - Social Studies'] ?? null);
            $endss         = wep_str($r['End of Year Progress - Social Studies'] ?? null);
            $parentinput   = wep_str($r['Parent Input'] ?? null);
            $sigparent     = wep_str($r['Parent/Guardian Representative:'] ?? null);
        }

        $reviewts    = wep_date($r['2025-2026 School Year - Reviewed Annually'] ?? null);
        $lastmodts  = wep_date($r['Last Modified'] ?? null);

        // Upsert staging table.
        $importrec = (object) [
            'studentnumber'            => $studentnumber,
            'firstname'                => wep_str($r['First Name'] ?? null),
            'lastname'                 => wep_str($r['Last Name'] ?? null),
            'gender'                   => wep_str($r['Gender'] ?? null),
            'current_school'           => wep_str($r['Current School'] ?? null),
            'current_grade'            => wep_int($r['Current Grade'] ?? null),
            'student_status'           => wep_str($r['Student Status'] ?? null),
            'plan_status'              => wep_str($r['Plan Status'] ?? null),
            'last_modified'            => wep_str($r['Last Modified'] ?? null),
            'review_date'              => wep_str($r['2025-2026 School Year - Reviewed Annually'] ?? null),
            'identification_areas'     => $idareasjson,
            'service_setting'          => $svcjson,
            'progress_measures'        => $progressjson,
            'personnel'                => $personneljson,
            'goal_reading_writing'     => $goalreading,
            'goal_mathematics'         => $goalmath,
            'goal_science'             => $goalsci,
            'goal_social_studies'      => $goalss,
            'midyear_reading'          => $midreading,
            'midyear_mathematics'      => $midmath,
            'midyear_science'          => $midsci,
            'midyear_social_studies'   => $midss,
            'endofyear_reading'        => $endreading,
            'endofyear_mathematics'    => $endmath,
            'endofyear_science'        => $endsci,
            'endofyear_social_studies' => $endss,
            'parent_input'             => $parentinput,
            'sig_parent'               => $sigparent,
            'schoollevel'              => $schoollevel,
            'timeimported'             => $now,
        ];

        $existingimport = $DB->get_record(
            'mtssdashboard_wep_import',
            ['studentnumber' => $studentnumber, 'schoollevel' => $schoollevel]
        );
        if ($existingimport) {
            $importrec->id = $existingimport->id;
            $DB->update_record('mtssdashboard_wep_import', $importrec);
        } else {
            $DB->insert_record('mtssdashboard_wep_import', $importrec);
        }
        $imported++;

        // Build / merge mtssdashboard_wep record.
        $existingwep = $DB->get_record(
            'mtssdashboard_wep',
            ['studentnumber' => $studentnumber]
        );

        if ($existingwep) {
            // Merge — only fill in fields that are currently empty.
            $merge = (object) ['id' => $existingwep->id];
            $merge->timemodified           = $lastmodts ?? $existingwep->timemodified;
            $merge->schoolyear             = $existingwep->schoolyear ?: '2025-2026';
            $merge->wep_review_date        = $existingwep->wep_review_date ?: $reviewts;
            $merge->identification_areas   = ($existingwep->identification_areas
                && $existingwep->identification_areas !== '[]')
                ? $existingwep->identification_areas : $idareasjson;
            $merge->service_setting        = ($existingwep->service_setting
                && $existingwep->service_setting !== '[]')
                ? $existingwep->service_setting : $svcjson;
            $merge->progress_measures      = ($existingwep->progress_measures
                && $existingwep->progress_measures !== '[]')
                ? $existingwep->progress_measures : $progressjson;
            $merge->personnel              = ($existingwep->personnel
                && $existingwep->personnel !== '[]')
                ? $existingwep->personnel : $personneljson;
            $merge->goal_reading_writing   = $existingwep->goal_reading_writing ?: $goalreading;
            $merge->goal_mathematics       = $existingwep->goal_mathematics ?: $goalmath;
            $merge->goal_science           = $existingwep->goal_science ?: $goalsci;
            $merge->goal_social_studies    = $existingwep->goal_social_studies ?: $goalss;
            $merge->midyear_reading        = $existingwep->midyear_reading ?: $midreading;
            $merge->midyear_mathematics    = $existingwep->midyear_mathematics ?: $midmath;
            $merge->midyear_science        = $existingwep->midyear_science ?: $midsci;
            $merge->midyear_social_studies = $existingwep->midyear_social_studies ?: $midss;
            $merge->endofyear_reading      = $existingwep->endofyear_reading ?: $endreading;
            $merge->endofyear_mathematics  = $existingwep->endofyear_mathematics ?: $endmath;
            $merge->endofyear_science      = $existingwep->endofyear_science ?: $endsci;
            $merge->endofyear_social_studies = $existingwep->endofyear_social_studies ?: $endss;
            $merge->parent_input           = $existingwep->parent_input ?: $parentinput;
            $merge->sig_parent             = $existingwep->sig_parent ?: $sigparent;
            $DB->update_record('mtssdashboard_wep', $merge);
            echo "  INFO row $rownum: student $studentnumber — merged with existing WEP.\n";
            $wepmerge++;
            continue;
        }

        // Insert new WEP record.
        $weprec = (object) [
            'studentnumber'            => $studentnumber,
            'schoollevel'              => $schoollevel,
            'schoolyear'               => '2025-2026',
            'wep_review_date'          => $reviewts,
            'identification_areas'     => $idareasjson,
            'service_setting'          => $svcjson,
            'progress_measures'        => $progressjson,
            'personnel'                => $personneljson,
            'goal_reading_writing'     => $goalreading,
            'goal_mathematics'         => $goalmath,
            'goal_science'             => $goalsci,
            'goal_social_studies'      => $goalss,
            'midyear_reading'          => $midreading,
            'midyear_mathematics'      => $midmath,
            'midyear_science'          => $midsci,
            'midyear_social_studies'   => $midss,
            'endofyear_reading'        => $endreading,
            'endofyear_mathematics'    => $endmath,
            'endofyear_science'        => $endsci,
            'endofyear_social_studies' => $endss,
            'parent_input'             => $parentinput,
            'sig_parent'               => $sigparent,
            'status'                   => wep_status($r['Plan Status'] ?? null),
            'timecreated'              => $now,
            'timemodified'             => $lastmodts ?? $now,
            'usermodified'             => 0,
        ];

        $DB->insert_record('mtssdashboard_wep', $weprec);
        $wepnew++;
        echo "  OK  row $rownum: student $studentnumber imported.\n";
    }

    fclose($fh);

    echo "\n--- $schoollevel summary ---\n";
    echo "  Staging rows imported/updated : $imported\n";
    echo "  WEP records created           : $wepnew\n";
    echo "  WEP records merged            : $wepmerge\n";
    echo "  Rows with errors              : $errors\n";
}

echo "\nDone.\n";
