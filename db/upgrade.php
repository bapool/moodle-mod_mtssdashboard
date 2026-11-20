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
 * Upgrade steps for mod_mtssdashboard.
 *
 * @package    mod_mtssdashboard
 * @copyright  2026 Brian Pool, National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run the database upgrade steps.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_mtssdashboard_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026042500) {
        // Define table mtssdashboard_warning_levels to be created.
        $table = new xmldb_table('mtssdashboard_warning_levels');

        // Adding fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('schoollevel', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('metric_key', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('color', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, null);
        $table->add_field('threshold_min', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
        $table->add_field('threshold_max', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
        $table->add_field('threshold_text', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Adding indexes.
        $table->add_index('schoollevel_metric', XMLDB_INDEX_NOTUNIQUE, ['schoollevel', 'metric_key', 'sortorder']);

        // Conditionally launch create table.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Statetestview savepoint reached.
        upgrade_mod_savepoint(true, 2026042500, 'mtssdashboard');
    }
    if ($oldversion < 2026042501) {
        // Seed default warning level thresholds for HS and MS.
        // Idempotent: only inserts rows that don't already exist.
        $now = time();
        $defaults = [
            // HS.
            ['hs', 'total_hours_missed', 'green', null, 19.99, '< 20 hours', 1],
            ['hs', 'total_hours_missed', 'yellow', 20, 34, '20-34 hours', 2],
            ['hs', 'total_hours_missed', 'orange', 35, 49, '35-49 hours', 3],
            ['hs', 'total_hours_missed', 'red', 50, null, '50+ hours', 4],

            ['hs', 'unexcused_hours_missed', 'green', null, 23.99, '< 24 hours', 1],
            ['hs', 'unexcused_hours_missed', 'yellow', 24, 35, '24-35 hours', 2],
            ['hs', 'unexcused_hours_missed', 'orange', 36, 47, '36-47 hours', 3],
            ['hs', 'unexcused_hours_missed', 'red', 48, 59, '48-59 hours', 4],
            ['hs', 'unexcused_hours_missed', 'purple', 60, null, '60+ hours', 5],

            ['hs', 'office_incidents', 'green', 0, 0, '0 incidents', 1],
            ['hs', 'office_incidents', 'yellow', 1, 2, '1-2 incidents', 2],
            ['hs', 'office_incidents', 'orange', 3, 4, '3-4 incidents', 3],
            ['hs', 'office_incidents', 'red', 5, null, '5+ incidents', 4],

            ['hs', 'course_failure_count', 'green', 0, 0, '0 courses', 1],
            ['hs', 'course_failure_count', 'yellow', 1, 1, '1 course', 2],
            ['hs', 'course_failure_count', 'orange', 2, 2, '2 courses', 3],
            ['hs', 'course_failure_count', 'red', 3, null, '3+ courses', 4],

            ['hs', 'gpa', 'green', 3.00, null, '>= 3.0', 1],
            ['hs', 'gpa', 'yellow', 2.50, 2.99, '2.50 - 2.99', 2],
            ['hs', 'gpa', 'orange', 2.00, 2.49, '2.00 - 2.49', 3],
            ['hs', 'gpa', 'red', null, 1.99, '< 2.0', 4],

            ['hs', 'iready_ela_percentile', 'green', 41, 100, '41-100%', 1],
            ['hs', 'iready_ela_percentile', 'yellow', 21, 40, '21-40%', 2],
            ['hs', 'iready_ela_percentile', 'orange', 11, 20, '11-20%', 3],
            ['hs', 'iready_ela_percentile', 'red', 0, 10, '0-10%', 4],

            ['hs', 'iready_math_percentile', 'green', 41, 100, '41-100%', 1],
            ['hs', 'iready_math_percentile', 'yellow', 21, 40, '21-40%', 2],
            ['hs', 'iready_math_percentile', 'orange', 11, 20, '11-20%', 3],
            ['hs', 'iready_math_percentile', 'red', 0, 10, '0-10%', 4],

            ['hs', 'ost_performance', 'green', 0, 0, '0 low scores', 1],
            ['hs', 'ost_performance', 'yellow', 1, 1, '1 low score', 2],
            ['hs', 'ost_performance', 'red', 2, null, '2+ low scores', 3],

            // MS (same starting values; can be tweaked independently).
            ['ms', 'total_hours_missed', 'green', null, 19.99, '< 20 hours', 1],
            ['ms', 'total_hours_missed', 'yellow', 20, 34, '20-34 hours', 2],
            ['ms', 'total_hours_missed', 'orange', 35, 49, '35-49 hours', 3],
            ['ms', 'total_hours_missed', 'red', 50, null, '50+ hours', 4],

            ['ms', 'unexcused_hours_missed', 'green', null, 23.99, '< 24 hours', 1],
            ['ms', 'unexcused_hours_missed', 'yellow', 24, 35, '24-35 hours', 2],
            ['ms', 'unexcused_hours_missed', 'orange', 36, 47, '36-47 hours', 3],
            ['ms', 'unexcused_hours_missed', 'red', 48, 59, '48-59 hours', 4],
            ['ms', 'unexcused_hours_missed', 'purple', 60, null, '60+ hours', 5],

            ['ms', 'office_incidents', 'green', 0, 0, '0 incidents', 1],
            ['ms', 'office_incidents', 'yellow', 1, 2, '1-2 incidents', 2],
            ['ms', 'office_incidents', 'orange', 3, 4, '3-4 incidents', 3],
            ['ms', 'office_incidents', 'red', 5, null, '5+ incidents', 4],

            ['ms', 'course_failure_count', 'green', 0, 0, '0 courses', 1],
            ['ms', 'course_failure_count', 'yellow', 1, 1, '1 course', 2],
            ['ms', 'course_failure_count', 'orange', 2, 2, '2 courses', 3],
            ['ms', 'course_failure_count', 'red', 3, null, '3+ courses', 4],

            ['ms', 'gpa', 'green', 3.00, null, '>= 3.0', 1],
            ['ms', 'gpa', 'yellow', 2.50, 2.99, '2.50 - 2.99', 2],
            ['ms', 'gpa', 'orange', 2.00, 2.49, '2.00 - 2.49', 3],
            ['ms', 'gpa', 'red', null, 1.99, '< 2.0', 4],

            ['ms', 'iready_ela_percentile', 'green', 41, 100, '41-100%', 1],
            ['ms', 'iready_ela_percentile', 'yellow', 21, 40, '21-40%', 2],
            ['ms', 'iready_ela_percentile', 'orange', 11, 20, '11-20%', 3],
            ['ms', 'iready_ela_percentile', 'red', 0, 10, '0-10%', 4],

            ['ms', 'iready_math_percentile', 'green', 41, 100, '41-100%', 1],
            ['ms', 'iready_math_percentile', 'yellow', 21, 40, '21-40%', 2],
            ['ms', 'iready_math_percentile', 'orange', 11, 20, '11-20%', 3],
            ['ms', 'iready_math_percentile', 'red', 0, 10, '0-10%', 4],

            ['ms', 'ost_performance', 'green', 0, 0, '0 low scores', 1],
            ['ms', 'ost_performance', 'yellow', 1, 1, '1 low score', 2],
            ['ms', 'ost_performance', 'red', 2, null, '2+ low scores', 3],
        ];

        foreach ($defaults as $row) {
            [$schoollevel, $mkey, $color, $tmin, $tmax, $ttext, $sort] = $row;
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

        upgrade_mod_savepoint(true, 2026042501, 'mtssdashboard');
    }

    if ($oldversion < 2026042502) {
        // Seed default warning level thresholds for Elementary (ES).
        // ES uses a smaller metric set than HS/MS: no Office Incidents,
        // no Course Failure Count, no GPA. OST Performance applies only
        // to grades 3 & 4 (handled in display, not in the data).
        // Idempotent: only inserts rows that don't already exist.
        $now = time();
        $defaults = [
            ['es', 'total_hours_missed', 'green', null, 19.99, '< 20 hours', 1],
            ['es', 'total_hours_missed', 'yellow', 20, 34, '20-34 hours', 2],
            ['es', 'total_hours_missed', 'orange', 35, 49, '35-49 hours', 3],
            ['es', 'total_hours_missed', 'red', 50, null, '50+ hours', 4],

            ['es', 'unexcused_hours_missed', 'green', null, 23.99, '< 24 hours', 1],
            ['es', 'unexcused_hours_missed', 'yellow', 24, 35, '24-35 hours', 2],
            ['es', 'unexcused_hours_missed', 'orange', 36, 47, '36-47 hours', 3],
            ['es', 'unexcused_hours_missed', 'red', 48, 59, '48-59 hours', 4],
            ['es', 'unexcused_hours_missed', 'purple', 60, null, '60+ hours', 5],

            ['es', 'iready_ela_percentile', 'green', 41, 100, '41-100%', 1],
            ['es', 'iready_ela_percentile', 'yellow', 21, 40, '21-40%', 2],
            ['es', 'iready_ela_percentile', 'orange', 11, 20, '11-20%', 3],
            ['es', 'iready_ela_percentile', 'red', 0, 10, '0-10%', 4],

            ['es', 'iready_math_percentile', 'green', 41, 100, '41-100%', 1],
            ['es', 'iready_math_percentile', 'yellow', 21, 40, '21-40%', 2],
            ['es', 'iready_math_percentile', 'orange', 11, 20, '11-20%', 3],
            ['es', 'iready_math_percentile', 'red', 0, 10, '0-10%', 4],

            ['es', 'ost_performance', 'green', 0, 0, '0 low scores', 1],
            ['es', 'ost_performance', 'yellow', 1, 1, '1 low score', 2],
            ['es', 'ost_performance', 'red', 2, null, '2+ low scores', 3],
        ];

        foreach ($defaults as $row) {
            [$schoollevel, $mkey, $color, $tmin, $tmax, $ttext, $sort] = $row;
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

        upgrade_mod_savepoint(true, 2026042502, 'mtssdashboard');
    }

    if ($oldversion < 2026042503) {
        // Seed the HS OST passing score "config" row. We reuse the
        // mtssdashboard_warning_levels table by storing the score as a
        // sentinel-color row keyed by metric_key='ost_passing_score'.
        // Only HS uses this; MS and ES rows are not seeded.
        $now = time();

        $exists = $DB->record_exists('mtssdashboard_warning_levels', [
            'schoollevel' => 'hs',
            'metric_key'  => 'ost_passing_score',
            'color'       => 'config',
        ]);
        if (!$exists) {
            $DB->insert_record('mtssdashboard_warning_levels', (object) [
                'schoollevel'    => 'hs',
                'metric_key'     => 'ost_passing_score',
                'color'          => 'config',
                'threshold_min'  => 684,
                'threshold_max'  => null,
                'threshold_text' => '684',
                'sortorder'      => 1,
                'timemodified'   => $now,
                'usermodified'   => 0,
            ]);
        }

        upgrade_mod_savepoint(true, 2026042503, 'mtssdashboard');
    }

    if ($oldversion < 2026042504) {
        // Seed configurable risk level thresholds for HS, MS, and ES.
        // Stored as metric_key='risk_threshold', color='low'/'moderate'/'extreme'.
        // threshold_min = the flag count at which that level begins.
        // No 'none' row needed — none is anything below the low threshold.
        $now = time();
        $riskdefaults = [
            ['hs', 'risk_threshold', 'low', 1, null, 'Flagged on 1 criterion', 1],
            ['hs', 'risk_threshold', 'moderate', 2, null, 'Flagged on 2-3 criteria', 2],
            ['hs', 'risk_threshold', 'extreme', 4, null, 'Flagged on 4+ criteria', 3],
            ['ms', 'risk_threshold', 'low', 1, null, 'Flagged on 1 criterion', 1],
            ['ms', 'risk_threshold', 'moderate', 2, null, 'Flagged on 2-3 criteria', 2],
            ['ms', 'risk_threshold', 'extreme', 4, null, 'Flagged on 4+ criteria', 3],
            ['es', 'risk_threshold', 'low', 1, null, 'Flagged on 1 criterion', 1],
            ['es', 'risk_threshold', 'moderate', 2, null, 'Flagged on 2-3 criteria', 2],
            ['es', 'risk_threshold', 'extreme', 4, null, 'Flagged on 4+ criteria', 3],
        ];
        foreach ($riskdefaults as $row) {
            [$schoollevel, $mkey, $color, $tmin, $tmax, $ttext, $sort] = $row;
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

        upgrade_mod_savepoint(true, 2026042504, 'mtssdashboard');
    }

    if ($oldversion < 2026051901) {
        // Seed iReady lesson activity warning levels for MS.
        // iready_ela_days and iready_math_days track the number of weekdays
        // since a student last completed an iReady lesson.
        // Higher values = more days since last lesson = worse.
        $now = time();
        $defaults = [
            ['ms', 'iready_ela_days', 'green', 0, 14, '0-14 days', 1],
            ['ms', 'iready_ela_days', 'yellow', 15, 21, '15-21 days', 2],
            ['ms', 'iready_ela_days', 'orange', 22, 29, '22-29 days', 3],
            ['ms', 'iready_ela_days', 'red', 30, null, '30+ days', 4],

            ['ms', 'iready_math_days', 'green', 0, 14, '0-14 days', 1],
            ['ms', 'iready_math_days', 'yellow', 15, 21, '15-21 days', 2],
            ['ms', 'iready_math_days', 'orange', 22, 29, '22-29 days', 3],
            ['ms', 'iready_math_days', 'red', 30, null, '30+ days', 4],
        ];

        foreach ($defaults as $row) {
            [$schoollevel, $mkey, $color, $tmin, $tmax, $ttext, $sort] = $row;
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

        upgrade_mod_savepoint(true, 2026051901, 'mtssdashboard');
    }

    if ($oldversion < 2026051902) {
        // Remove iReady ELA and Math Percentile warning levels from HS.
        // These metrics are no longer shown on the HS early warning tab.
        $DB->delete_records_select(
            'mtssdashboard_warning_levels',
            "schoollevel = 'hs' AND metric_key IN ('iready_ela_percentile', 'iready_math_percentile')"
        );

        upgrade_mod_savepoint(true, 2026051902, 'mtssdashboard');
    }

    if ($oldversion < 2026052600) {
        // Table: mtssdashboard_rimp.
        // Stores one RIMP record per student.  A student can have at most one
        // active RIMP at a time; previous records are retained for audit.
        $table = new xmldb_table('mtssdashboard_rimp');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('studentnumber', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('schoollevel', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'es');
        $table->add_field('schoolyear', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('rimp_effective_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('student_score', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('assessment_status', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('goals', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('supports', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('days_per_week', XMLDB_TYPE_CHAR, '10', null, null, null, null);
        $table->add_field('minutes_per_week', XMLDB_TYPE_INTEGER, '5', null, null, null, null);
        $table->add_field('monitoring_assessment', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('monitoring_frequency', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('monitored_by', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('grade3_guarantee', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('sig_parent', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('sig_teacher', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('sig_ela_teacher', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('sig_intervention', XMLDB_TYPE_TEXT, null, null, null, null, null);
        // 0 = Draft, 1 = Finalized.
        $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        $table->add_index('idx_studentnumber', XMLDB_INDEX_NOTUNIQUE, ['studentnumber']);
        $table->add_index('idx_status', XMLDB_INDEX_NOTUNIQUE, ['studentnumber', 'status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Table: mtssdashboard_rimp_import.
        // Holds data imported from the district RIMP CSV exports.
        // Read-only reference / pre-fill source; not editable by teachers.
        $itable = new xmldb_table('mtssdashboard_rimp_import');

        $itable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $itable->add_field('studentnumber', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $itable->add_field('firstname', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $itable->add_field('lastname', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $itable->add_field('gender', XMLDB_TYPE_CHAR, '10', null, null, null, null);
        $itable->add_field('current_school', XMLDB_TYPE_CHAR, '200', null, null, null, null);
        $itable->add_field('current_grade', XMLDB_TYPE_INTEGER, '3', null, null, null, null);
        $itable->add_field('student_status', XMLDB_TYPE_CHAR, '10', null, null, null, null);
        $itable->add_field('plan_status', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $itable->add_field('last_modified', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $itable->add_field('student_score', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $itable->add_field('assessment_status', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $itable->add_field('effective_date', XMLDB_TYPE_CHAR, '30', null, null, null, null);
        $itable->add_field('goals', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('supports', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('days_per_week', XMLDB_TYPE_CHAR, '10', null, null, null, null);
        $itable->add_field('minutes_per_week', XMLDB_TYPE_INTEGER, '5', null, null, null, null);
        $itable->add_field('monitoring_freq', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('monitored_by', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $itable->add_field('grade3_select', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $itable->add_field('sig_parent', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('sig_teacher', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('schoollevel', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'es');
        $itable->add_field('timeimported', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $itable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $itable->add_index('idx_imp_studentnumber', XMLDB_INDEX_NOTUNIQUE, ['studentnumber']);
        $itable->add_index('idx_imp_level', XMLDB_INDEX_NOTUNIQUE, ['schoollevel', 'studentnumber']);

        if (!$dbman->table_exists($itable)) {
            $dbman->create_table($itable);
        }

        upgrade_mod_savepoint(true, 2026052600, 'mtssdashboard');
    }

    if ($oldversion < 2026052800) {
        // Mtssdashboard_wep.
        $table = new xmldb_table('mtssdashboard_wep');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('studentnumber', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('schoollevel', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('schoolyear', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('wep_review_date', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('identification_areas', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('service_setting', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('progress_measures', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('personnel', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('goal_reading_writing', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('goal_mathematics', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('goal_science', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('goal_social_studies', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('midyear_reading', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('midyear_mathematics', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('midyear_science', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('midyear_social_studies', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('endofyear_reading', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('endofyear_mathematics', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('endofyear_science', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('endofyear_social_studies', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('parent_input', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('sig_parent', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        $table->add_index('idx_wep_studentnumber', XMLDB_INDEX_NOTUNIQUE, ['studentnumber']);
        $table->add_index('idx_wep_status', XMLDB_INDEX_NOTUNIQUE, ['studentnumber', 'status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Mtssdashboard_wep_import.
        $itable = new xmldb_table('mtssdashboard_wep_import');

        $itable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $itable->add_field('studentnumber', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $itable->add_field('firstname', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $itable->add_field('lastname', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $itable->add_field('gender', XMLDB_TYPE_CHAR, '10', null, null, null, null);
        $itable->add_field('current_school', XMLDB_TYPE_CHAR, '200', null, null, null, null);
        $itable->add_field('current_grade', XMLDB_TYPE_INTEGER, '3', null, null, null, null);
        $itable->add_field('student_status', XMLDB_TYPE_CHAR, '10', null, null, null, null);
        $itable->add_field('plan_status', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $itable->add_field('last_modified', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $itable->add_field('review_date', XMLDB_TYPE_CHAR, '30', null, null, null, null);
        $itable->add_field('identification_areas', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('service_setting', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('progress_measures', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('personnel', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('goal_reading_writing', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('goal_mathematics', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('goal_science', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('goal_social_studies', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('midyear_reading', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('midyear_mathematics', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('midyear_science', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('midyear_social_studies', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('endofyear_reading', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('endofyear_mathematics', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('endofyear_science', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('endofyear_social_studies', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('parent_input', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('sig_parent', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $itable->add_field('schoollevel', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'es');
        $itable->add_field('timeimported', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $itable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $itable->add_index('idx_wep_imp_studentnumber', XMLDB_INDEX_NOTUNIQUE, ['studentnumber']);
        $itable->add_index('idx_wep_imp_level', XMLDB_INDEX_NOTUNIQUE, ['schoollevel', 'studentnumber']);

        if (!$dbman->table_exists($itable)) {
            $dbman->create_table($itable);
        }

        upgrade_mod_savepoint(true, 2026052800, 'mtssdashboard');
    }

    if ($oldversion < 2026052801) {
        // Re-register capabilities added in access.php (editwep, importwep).
        // No schema changes — savepoint only.
        upgrade_mod_savepoint(true, 2026052801, 'mtssdashboard');
    }

    if ($oldversion < 2026052802) {
        // Register editrimp capability added in access.php.
        upgrade_mod_savepoint(true, 2026052802, 'mtssdashboard');
    }

    if ($oldversion < 2026081400) {
        // Mtssdashboard_edit_sessions.
        // Tracks who currently has a RIMP/WEP record open for editing, so two
        // teachers can't silently overwrite each other's changes.
        $table = new xmldb_table('mtssdashboard_edit_sessions');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('studentnumber', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('recordtype', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('heartbeat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        $table->add_index('idx_edit_sessions_lock', XMLDB_INDEX_UNIQUE, ['studentnumber', 'recordtype']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026081400, 'mtssdashboard');
    }

    if ($oldversion < 2026090800) {
        // Widen grade3_guarantee — the four reading-guarantee options are
        // full sentences (up to 308 chars), not codes, and were truncating
        // against the old char(100) column, causing a DB write failure.
        $table = new xmldb_table('mtssdashboard_rimp');
        $field = new xmldb_field('grade3_guarantee', XMLDB_TYPE_TEXT, null, null, null, null, null, 'monitored_by');

        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        upgrade_mod_savepoint(true, 2026090800, 'mtssdashboard');
    }

    return true;
}
