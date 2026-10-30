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
 * Post-install hook for mod_mtssdashboard.
 *
 * Seeds the mtssdashboard_warning_levels table with default threshold data.
 * This file runs ONLY on a fresh install (not on upgrades). Upgrade steps
 * in db/upgrade.php handle the same seeding for existing installations that
 * are upgrading from an earlier version.
 *
 * @package    mod_mtssdashboard
 * @copyright  2026 Brian Pool, National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Called by Moodle immediately after the tables in install.xml are created.
 */
function xmldb_mtssdashboard_install() {
    global $DB;

    $now = time();

    // ── Metric thresholds: HS, MS, ES ────────────────────────────────────────
    // Each row: [schoollevel, metric_key, color, threshold_min, threshold_max,
    //            threshold_text, sortorder]
    $defaults = [

        // ── High School (hs) ─────────────────────────────────────────────────

        ['hs', 'total_hours_missed',     'green',  null,  19.99, '< 20 hours',     1],
        ['hs', 'total_hours_missed',     'yellow', 20,    34,    '20-34 hours',    2],
        ['hs', 'total_hours_missed',     'orange', 35,    49,    '35-49 hours',    3],
        ['hs', 'total_hours_missed',     'red',    50,    null,  '50+ hours',      4],

        ['hs', 'unexcused_hours_missed', 'green',  null,  23.99, '< 24 hours',     1],
        ['hs', 'unexcused_hours_missed', 'yellow', 24,    35,    '24-35 hours',    2],
        ['hs', 'unexcused_hours_missed', 'orange', 36,    47,    '36-47 hours',    3],
        ['hs', 'unexcused_hours_missed', 'red',    48,    59,    '48-59 hours',    4],
        ['hs', 'unexcused_hours_missed', 'purple', 60,    null,  '60+ hours',      5],

        ['hs', 'office_incidents',       'green',  0,     0,     '0 incidents',    1],
        ['hs', 'office_incidents',       'yellow', 1,     2,     '1-2 incidents',  2],
        ['hs', 'office_incidents',       'orange', 3,     4,     '3-4 incidents',  3],
        ['hs', 'office_incidents',       'red',    5,     null,  '5+ incidents',   4],

        ['hs', 'course_failure_count',   'green',  0,     0,     '0 courses',      1],
        ['hs', 'course_failure_count',   'yellow', 1,     1,     '1 course',       2],
        ['hs', 'course_failure_count',   'orange', 2,     2,     '2 courses',      3],
        ['hs', 'course_failure_count',   'red',    3,     null,  '3+ courses',     4],

        ['hs', 'gpa',                    'green',  3.00,  null,  '>= 3.0',         1],
        ['hs', 'gpa',                    'yellow', 2.50,  2.99,  '2.50 - 2.99',    2],
        ['hs', 'gpa',                    'orange', 2.00,  2.49,  '2.00 - 2.49',    3],
        ['hs', 'gpa',                    'red',    null,  1.99,  '< 2.0',          4],

        ['hs', 'ost_performance',        'green',  0,     0,     '0 low scores',   1],
        ['hs', 'ost_performance',        'yellow', 1,     1,     '1 low score',    2],
        ['hs', 'ost_performance',        'red',    2,     null,  '2+ low scores',  3],

        // ── Middle School (ms) ───────────────────────────────────────────────

        ['ms', 'total_hours_missed',     'green',  null,  19.99, '< 20 hours',     1],
        ['ms', 'total_hours_missed',     'yellow', 20,    34,    '20-34 hours',    2],
        ['ms', 'total_hours_missed',     'orange', 35,    49,    '35-49 hours',    3],
        ['ms', 'total_hours_missed',     'red',    50,    null,  '50+ hours',      4],

        ['ms', 'unexcused_hours_missed', 'green',  null,  23.99, '< 24 hours',     1],
        ['ms', 'unexcused_hours_missed', 'yellow', 24,    35,    '24-35 hours',    2],
        ['ms', 'unexcused_hours_missed', 'orange', 36,    47,    '36-47 hours',    3],
        ['ms', 'unexcused_hours_missed', 'red',    48,    59,    '48-59 hours',    4],
        ['ms', 'unexcused_hours_missed', 'purple', 60,    null,  '60+ hours',      5],

        ['ms', 'office_incidents',       'green',  0,     0,     '0 incidents',    1],
        ['ms', 'office_incidents',       'yellow', 1,     2,     '1-2 incidents',  2],
        ['ms', 'office_incidents',       'orange', 3,     4,     '3-4 incidents',  3],
        ['ms', 'office_incidents',       'red',    5,     null,  '5+ incidents',   4],

        ['ms', 'course_failure_count',   'green',  0,     0,     '0 courses',      1],
        ['ms', 'course_failure_count',   'yellow', 1,     1,     '1 course',       2],
        ['ms', 'course_failure_count',   'orange', 2,     2,     '2 courses',      3],
        ['ms', 'course_failure_count',   'red',    3,     null,  '3+ courses',     4],

        ['ms', 'gpa',                    'green',  3.00,  null,  '>= 3.0',         1],
        ['ms', 'gpa',                    'yellow', 2.50,  2.99,  '2.50 - 2.99',    2],
        ['ms', 'gpa',                    'orange', 2.00,  2.49,  '2.00 - 2.49',    3],
        ['ms', 'gpa',                    'red',    null,  1.99,  '< 2.0',          4],

        ['ms', 'iready_ela_percentile',  'green',  41,    100,   '41-100%',        1],
        ['ms', 'iready_ela_percentile',  'yellow', 21,    40,    '21-40%',         2],
        ['ms', 'iready_ela_percentile',  'orange', 11,    20,    '11-20%',         3],
        ['ms', 'iready_ela_percentile',  'red',    0,     10,    '0-10%',          4],

        ['ms', 'iready_math_percentile', 'green',  41,    100,   '41-100%',        1],
        ['ms', 'iready_math_percentile', 'yellow', 21,    40,    '21-40%',         2],
        ['ms', 'iready_math_percentile', 'orange', 11,    20,    '11-20%',         3],
        ['ms', 'iready_math_percentile', 'red',    0,     10,    '0-10%',          4],

        ['ms', 'iready_ela_days',        'green',  0,     14,   '0-14 days',  1],
        ['ms', 'iready_ela_days',        'yellow', 15,    21,   '15-21 days', 2],
        ['ms', 'iready_ela_days',        'orange', 22,    29,   '22-29 days', 3],
        ['ms', 'iready_ela_days',        'red',    30,    null, '30+ days',   4],

        ['ms', 'iready_math_days',       'green',  0,     14,   '0-14 days',  1],
        ['ms', 'iready_math_days',       'yellow', 15,    21,   '15-21 days', 2],
        ['ms', 'iready_math_days',       'orange', 22,    29,   '22-29 days', 3],
        ['ms', 'iready_math_days',       'red',    30,    null, '30+ days',   4],

        ['ms', 'ost_performance',        'green',  0,     0,     '0 low scores',   1],
        ['ms', 'ost_performance',        'yellow', 1,     1,     '1 low score',    2],
        ['ms', 'ost_performance',        'red',    2,     null,  '2+ low scores',  3],

        // ── Elementary School (es) ───────────────────────────────────────────
        // ES uses a smaller metric set: no Office Incidents, no Course Failure
        // Count, no GPA. OST Performance applies only to grades 3 & 4
        // (handled in display logic, not here).

        ['es', 'total_hours_missed',     'green',  null,  19.99, '< 20 hours',     1],
        ['es', 'total_hours_missed',     'yellow', 20,    34,    '20-34 hours',    2],
        ['es', 'total_hours_missed',     'orange', 35,    49,    '35-49 hours',    3],
        ['es', 'total_hours_missed',     'red',    50,    null,  '50+ hours',      4],

        ['es', 'unexcused_hours_missed', 'green',  null,  23.99, '< 24 hours',     1],
        ['es', 'unexcused_hours_missed', 'yellow', 24,    35,    '24-35 hours',    2],
        ['es', 'unexcused_hours_missed', 'orange', 36,    47,    '36-47 hours',    3],
        ['es', 'unexcused_hours_missed', 'red',    48,    59,    '48-59 hours',    4],
        ['es', 'unexcused_hours_missed', 'purple', 60,    null,  '60+ hours',      5],

        ['es', 'iready_ela_percentile',  'green',  41,    100,   '41-100%',        1],
        ['es', 'iready_ela_percentile',  'yellow', 21,    40,    '21-40%',         2],
        ['es', 'iready_ela_percentile',  'orange', 11,    20,    '11-20%',         3],
        ['es', 'iready_ela_percentile',  'red',    0,     10,    '0-10%',          4],

        ['es', 'iready_math_percentile', 'green',  41,    100,   '41-100%',        1],
        ['es', 'iready_math_percentile', 'yellow', 21,    40,    '21-40%',         2],
        ['es', 'iready_math_percentile', 'orange', 11,    20,    '11-20%',         3],
        ['es', 'iready_math_percentile', 'red',    0,     10,    '0-10%',          4],

        ['es', 'ost_performance',        'green',  0,     0,     '0 low scores',   1],
        ['es', 'ost_performance',        'yellow', 1,     1,     '1 low score',    2],
        ['es', 'ost_performance',        'red',    2,     null,  '2+ low scores',  3],

        // ── HS OST passing score config row ─────────────────────────────────
        // Stored as a sentinel color='config' row. Only HS uses this.
        ['hs', 'ost_passing_score',      'config', 684,   null,  '684',            1],

        // ── Risk level thresholds (all levels) ───────────────────────────────
        // threshold_min = the flag count at which that risk level begins.
        ['hs', 'risk_threshold', 'low',      1, null, 'Flagged on 1 criterion',  1],
        ['hs', 'risk_threshold', 'moderate', 2, null, 'Flagged on 2-3 criteria', 2],
        ['hs', 'risk_threshold', 'extreme',  4, null, 'Flagged on 4+ criteria',  3],

        ['ms', 'risk_threshold', 'low',      1, null, 'Flagged on 1 criterion',  1],
        ['ms', 'risk_threshold', 'moderate', 2, null, 'Flagged on 2-3 criteria', 2],
        ['ms', 'risk_threshold', 'extreme',  4, null, 'Flagged on 4+ criteria',  3],

        ['es', 'risk_threshold', 'low',      1, null, 'Flagged on 1 criterion',  1],
        ['es', 'risk_threshold', 'moderate', 2, null, 'Flagged on 2-3 criteria', 2],
        ['es', 'risk_threshold', 'extreme',  4, null, 'Flagged on 4+ criteria',  3],
    ];

    foreach ($defaults as $row) {
        list($schoollevel, $mkey, $color, $tmin, $tmax, $ttext, $sort) = $row;
        // Idempotent: skip if the row already exists.
        $exists = $DB->record_exists('mtssdashboard_warning_levels', [
            'schoollevel' => $schoollevel,
            'metric_key'  => $mkey,
            'color'       => $color,
        ]);
        if (!$exists) {
            $DB->insert_record('mtssdashboard_warning_levels', (object) [
                'schoollevel'    => $schoollevel,
                'metric_key'     => $mkey,
                'color'          => $color,
                'threshold_min'  => $tmin,
                'threshold_max'  => $tmax,
                'threshold_text' => $ttext,
                'sortorder'      => $sort,
                'timemodified'   => $now,
                'usermodified'   => 0,
            ]);
        }
    }

    return true;
}
