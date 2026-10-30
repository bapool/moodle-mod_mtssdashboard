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

// ── Bootstrap ─────────────────────────────────────────────────────────────────
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

// ── Config ────────────────────────────────────────────────────────────────────
$files = [
    'es'   => '/var/www/moodledata/NTimports/plans/RIMP WEP/student-District-CSVReport WEP.csv',
    'mshs' => '/var/www/moodledata/NTimports/plans/RIMP WEP/student-District-CSVReport WEP HS-MS.csv',
];

$now = time();

// ── Identification area text → JSON key mapping ───────────────────────────────
$id_area_map = [
    'Superior Cognitive'         => 'superior_cognitive',
    'Superior Creative'          => 'superior_creative',
    'Reading/Writing'            => 'reading_writing',
    'Mathematics'                => 'mathematics',
    'Science'                    => 'science',
    'Social Studies'             => 'social_studies',
];

// ── Service setting text → JSON key mapping ───────────────────────────────────
$svc_map_es = [
    'Cluster Grouping'           => 'cluster_es',
    'Other'                      => 'other',
];

$svc_map_mshs = [
    'Cluster Grouping'           => 'cluster_mshs',
    'Acceleration'               => 'acceleration',
    'College Credit Plus'        => 'college_credit',
    'Other'                      => 'other',
];

// ── Progress measures text → JSON key mapping ─────────────────────────────────
$progress_map = [
    'Observations'               => 'observations',
    'Checklists'                 => 'observations',
    'Projects'                   => 'projects',
    'Benchmarks'                 => 'benchmarks',
    'Presentations'              => 'presentations',
    'Classroom Assessments'      => 'assessments',
    'Classroom'                  => 'assessments',
    'Other'                      => 'other',
];

// ── Personnel text → JSON key mapping ─────────────────────────────────────────
$personnel_map = [
    'General Education'          => 'gen_ed',
];

// ── Helper: parse a comma-separated text column into JSON array of keys ───────
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

// ── Helper: safely get a trimmed string, null if empty/NaN ───────────────────
function wep_str($val) {
    if ($val === null || trim((string)$val) === '' || strtolower(trim((string)$val)) === 'nan') {
        return null;
    }
    return trim((string)$val);
}

// ── Helper: safely get an int, null if empty/NaN/zero ────────────────────────
function wep_int($val) {
    $s = wep_str($val);
    if ($s === null) { return null; }
    // Strip leading zeros from grade codes like "03".
    $i = intval($s);
    return ($i > 0) ? $i : null;
}

// ── Helper: parse date string to Unix timestamp ───────────────────────────────
function wep_date($val) {
    $s = wep_str($val);
    if ($s === null) { return null; }
    $ts = strtotime($s);
    return ($ts && $ts > 0) ? $ts : null;
}

// ── Helper: map Plan Status to status field (1 = Finalized, 0 = Draft) ────────
function wep_status($val) {
    return (wep_str($val) === 'Complete') ? 1 : 0;
}

// ── Process each file ─────────────────────────────────────────────────────────
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
    $raw_headers = fgetcsv($fh);
    if (!$raw_headers) {
        echo "ERROR: Empty file $filepath\n";
        fclose($fh);
        continue;
    }
    $headers = array_map(function($h) {
        return trim(str_replace("\xEF\xBB\xBF", '', $h));
    }, $raw_headers);

    // Use the correct service map for this level.
    $svc_map = ($schoollevel === 'es') ? $svc_map_es : $svc_map_mshs;

    $imported  = 0;
    $wep_new   = 0;
    $wep_merge = 0;
    $errors    = 0;
    $rownum    = 1;

    while (($row = fgetcsv($fh)) !== false) {
        $rownum++;

        if (count($row) < count($headers)) {
            // Pad short rows rather than skip — some trailing columns may be empty.
            $row = array_pad($row, count($headers), '');
        }

        $r = array_combine($headers, array_slice($row, 0, count($headers)));

        // ── Student number ────────────────────────────────────────────────────
        $studentnumber = wep_int($r['Student ID'] ?? null);
        if (!$studentnumber) {
            echo "  WARN row $rownum: no Student ID, skipping.\n";
            $errors++;
            continue;
        }

        // ── Parse multi-value checkbox columns ────────────────────────────────
        $id_areas_json   = wep_parse_list($r['Area(s) of Identification']          ?? '', $id_area_map);
        $svc_json        = wep_parse_list($r['Service Setting / EMIS Code']        ?? '', $svc_map);
        $progress_json   = wep_parse_list($r['Student Progress Measures ']         ?? '', $progress_map);
        $personnel_json  = wep_parse_list($r['Personnel Responsible for Service:'] ?? '', $personnel_map);

        // ── Parse text fields — ES vs MS/HS differ in column layout ──────────
        if ($schoollevel === 'es') {
            // ES: single mid/end year progress fields (not per-subject).
            $goal_reading   = wep_str($r['WEP Measurable Annual Reading/Writing Goal'] ?? null);
            $goal_math      = wep_str($r['WEP Measurable Annual Mathematics Goal']     ?? null);
            $goal_sci       = null;
            $goal_ss        = null;
            $mid_reading    = wep_str($r['Mid Year Progress']                           ?? null);
            $end_reading    = wep_str($r['End of Year Progress']                        ?? null);
            $mid_math       = null;
            $end_math       = null;
            $mid_sci        = null;
            $end_sci        = null;
            $mid_ss         = null;
            $end_ss         = null;
            $parent_input   = wep_str($r['Parent Input']                                ?? null);
            $sig_parent     = wep_str($r['Parent/Guardian Representative:']             ?? null);
        } else {
            // MS/HS: per-subject mid/end year progress.
            $goal_reading   = wep_str($r['WEP Measurable Annual Reading/Writing Goal'] ?? null);
            $goal_math      = wep_str($r['WEP Measurable Annual Mathematics Goal']     ?? null);
            $goal_sci       = wep_str($r['WEP Measurable Annual Science Goal']         ?? null);
            $goal_ss        = wep_str($r['WEP Measurable Annual Social Studies Goal']  ?? null);
            $mid_reading    = wep_str($r['Mid Year Progress - Reading']                 ?? null);
            $end_reading    = wep_str($r['End of Year Progress - Reading']              ?? null);
            $mid_math       = wep_str($r['Mid Year Progress - Mathematics']             ?? null);
            $end_math       = wep_str($r['End of Year Progress - Mathematics']          ?? null);
            $mid_sci        = wep_str($r['Mid Year Progress - Science']                 ?? null);
            $end_sci        = wep_str($r['End of Year Progress - Science']              ?? null);
            $mid_ss         = wep_str($r['Mid Year Progress - Social Studies']          ?? null);
            $end_ss         = wep_str($r['End of Year Progress - Social Studies']       ?? null);
            $parent_input   = wep_str($r['Parent Input']                                ?? null);
            $sig_parent     = wep_str($r['Parent/Guardian Representative:']             ?? null);
        }

        $review_ts    = wep_date($r['2025-2026 School Year - Reviewed Annually'] ?? null);
        $last_mod_ts  = wep_date($r['Last Modified'] ?? null);

        // ── Upsert staging table ──────────────────────────────────────────────
        $import_rec = (object) [
            'studentnumber'            => $studentnumber,
            'firstname'                => wep_str($r['First Name']      ?? null),
            'lastname'                 => wep_str($r['Last Name']       ?? null),
            'gender'                   => wep_str($r['Gender']          ?? null),
            'current_school'           => wep_str($r['Current School']  ?? null),
            'current_grade'            => wep_int($r['Current Grade']   ?? null),
            'student_status'           => wep_str($r['Student Status']  ?? null),
            'plan_status'              => wep_str($r['Plan Status']     ?? null),
            'last_modified'            => wep_str($r['Last Modified']   ?? null),
            'review_date'              => wep_str($r['2025-2026 School Year - Reviewed Annually'] ?? null),
            'identification_areas'     => $id_areas_json,
            'service_setting'          => $svc_json,
            'progress_measures'        => $progress_json,
            'personnel'                => $personnel_json,
            'goal_reading_writing'     => $goal_reading,
            'goal_mathematics'         => $goal_math,
            'goal_science'             => $goal_sci,
            'goal_social_studies'      => $goal_ss,
            'midyear_reading'          => $mid_reading,
            'midyear_mathematics'      => $mid_math,
            'midyear_science'          => $mid_sci,
            'midyear_social_studies'   => $mid_ss,
            'endofyear_reading'        => $end_reading,
            'endofyear_mathematics'    => $end_math,
            'endofyear_science'        => $end_sci,
            'endofyear_social_studies' => $end_ss,
            'parent_input'             => $parent_input,
            'sig_parent'               => $sig_parent,
            'schoollevel'              => $schoollevel,
            'timeimported'             => $now,
        ];

        $existing_import = $DB->get_record('mtssdashboard_wep_import',
            ['studentnumber' => $studentnumber, 'schoollevel' => $schoollevel]);
        if ($existing_import) {
            $import_rec->id = $existing_import->id;
            $DB->update_record('mtssdashboard_wep_import', $import_rec);
        } else {
            $DB->insert_record('mtssdashboard_wep_import', $import_rec);
        }
        $imported++;

        // ── Build / merge mtssdashboard_wep record ────────────────────────────
        $existing_wep = $DB->get_record('mtssdashboard_wep',
            ['studentnumber' => $studentnumber]);

        if ($existing_wep) {
            // Merge — only fill in fields that are currently empty.
            $merge = (object) ['id' => $existing_wep->id];
            $merge->timemodified           = $last_mod_ts ?? $existing_wep->timemodified;
            $merge->schoolyear             = $existing_wep->schoolyear             ?: '2025-2026';
            $merge->wep_review_date        = $existing_wep->wep_review_date        ?: $review_ts;
            $merge->identification_areas   = ($existing_wep->identification_areas
                && $existing_wep->identification_areas !== '[]')
                ? $existing_wep->identification_areas : $id_areas_json;
            $merge->service_setting        = ($existing_wep->service_setting
                && $existing_wep->service_setting !== '[]')
                ? $existing_wep->service_setting : $svc_json;
            $merge->progress_measures      = ($existing_wep->progress_measures
                && $existing_wep->progress_measures !== '[]')
                ? $existing_wep->progress_measures : $progress_json;
            $merge->personnel              = ($existing_wep->personnel
                && $existing_wep->personnel !== '[]')
                ? $existing_wep->personnel : $personnel_json;
            $merge->goal_reading_writing   = $existing_wep->goal_reading_writing   ?: $goal_reading;
            $merge->goal_mathematics       = $existing_wep->goal_mathematics       ?: $goal_math;
            $merge->goal_science           = $existing_wep->goal_science           ?: $goal_sci;
            $merge->goal_social_studies    = $existing_wep->goal_social_studies    ?: $goal_ss;
            $merge->midyear_reading        = $existing_wep->midyear_reading        ?: $mid_reading;
            $merge->midyear_mathematics    = $existing_wep->midyear_mathematics    ?: $mid_math;
            $merge->midyear_science        = $existing_wep->midyear_science        ?: $mid_sci;
            $merge->midyear_social_studies = $existing_wep->midyear_social_studies ?: $mid_ss;
            $merge->endofyear_reading      = $existing_wep->endofyear_reading      ?: $end_reading;
            $merge->endofyear_mathematics  = $existing_wep->endofyear_mathematics  ?: $end_math;
            $merge->endofyear_science      = $existing_wep->endofyear_science      ?: $end_sci;
            $merge->endofyear_social_studies = $existing_wep->endofyear_social_studies ?: $end_ss;
            $merge->parent_input           = $existing_wep->parent_input           ?: $parent_input;
            $merge->sig_parent             = $existing_wep->sig_parent             ?: $sig_parent;
            $DB->update_record('mtssdashboard_wep', $merge);
            echo "  INFO row $rownum: student $studentnumber — merged with existing WEP.\n";
            $wep_merge++;
            continue;
        }

        // ── Insert new WEP record ─────────────────────────────────────────────
        $wep_rec = (object) [
            'studentnumber'            => $studentnumber,
            'schoollevel'              => $schoollevel,
            'schoolyear'               => '2025-2026',
            'wep_review_date'          => $review_ts,
            'identification_areas'     => $id_areas_json,
            'service_setting'          => $svc_json,
            'progress_measures'        => $progress_json,
            'personnel'                => $personnel_json,
            'goal_reading_writing'     => $goal_reading,
            'goal_mathematics'         => $goal_math,
            'goal_science'             => $goal_sci,
            'goal_social_studies'      => $goal_ss,
            'midyear_reading'          => $mid_reading,
            'midyear_mathematics'      => $mid_math,
            'midyear_science'          => $mid_sci,
            'midyear_social_studies'   => $mid_ss,
            'endofyear_reading'        => $end_reading,
            'endofyear_mathematics'    => $end_math,
            'endofyear_science'        => $end_sci,
            'endofyear_social_studies' => $end_ss,
            'parent_input'             => $parent_input,
            'sig_parent'               => $sig_parent,
            'status'                   => wep_status($r['Plan Status'] ?? null),
            'timecreated'              => $now,
            'timemodified'             => $last_mod_ts ?? $now,
            'usermodified'             => 0,
        ];

        $DB->insert_record('mtssdashboard_wep', $wep_rec);
        $wep_new++;
        echo "  OK  row $rownum: student $studentnumber imported.\n";
    }

    fclose($fh);

    echo "\n--- $schoollevel summary ---\n";
    echo "  Staging rows imported/updated : $imported\n";
    echo "  WEP records created           : $wep_new\n";
    echo "  WEP records merged            : $wep_merge\n";
    echo "  Rows with errors              : $errors\n";
}

echo "\nDone.\n";
