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
 * Main view page for mod_mtssdashboard.
 *
 * @package    mod_mtssdashboard
 * @copyright  2026 National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/mtssdashboard/lib.php');

// Parameters.
$id         = required_param('id', PARAM_INT);
$tab        = optional_param('tab', 'earlywarning', PARAM_ALPHANUMEXT);
$editmode   = optional_param('edit', 0, PARAM_INT);
$ewsort     = optional_param('ewsort', 'name', PARAM_ALPHA);
$year       = optional_param('year', '', PARAM_TEXT);
$studuserid = optional_param('studuserid', 0, PARAM_INT);

// Demographic filters.
$fgender = optional_param('f_gender', '', PARAM_TEXT);
$frace   = optional_param('f_race', '', PARAM_TEXT);
$fed     = optional_param('f_ed', '', PARAM_TEXT);
$fswd    = optional_param('f_swd', '', PARAM_TEXT);
$fel     = optional_param('f_el', '', PARAM_TEXT);
$f504    = optional_param('f_504', '', PARAM_TEXT);
$fiep    = optional_param('f_iep', '', PARAM_TEXT);
$fgifted = optional_param('f_gifted', '', PARAM_TEXT);

// Bootstrap.
$cm       = get_coursemodule_from_id('mtssdashboard', $id, 0, false, MUST_EXIST);
$course   = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('mtssdashboard', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/mtssdashboard:view', $context);

// Default to All years — teachers see full picture on first load.
// Instance default year only applies if explicitly set.
if ($year === '' && !empty($instance->defaultyear)) {
    $year = $instance->defaultyear;
}
// If still empty, stay empty — means All years.

// Log view.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Save handler: warning levels edit form.
// Runs before any output so we can redirect cleanly on success.
if (
    optional_param('action', '', PARAM_ALPHANUMEXT) === 'savewarning'
        && $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    require_sesskey();

    $schoollevel = stv_get_school_level_for_course($course);

    // Re-check edit permission on the server side. Never trust the client.
    if (!stv_can_edit_warning_levels($course, $schoollevel)) {
        throw new moodle_exception(
            'nopermissions',
            'error',
            '',
            get_string('warning_btn_edit', 'mod_mtssdashboard')
        );
    }

    $tmins = optional_param_array('tmin', [], PARAM_RAW_TRIMMED);
    $tmaxs = optional_param_array('tmax', [], PARAM_RAW_TRIMMED);

    // Fetch all rows for this school level so we can validate row IDs and
    // refuse updates to rows belonging to another school level.
    $existing = $DB->get_records(
        'mtssdashboard_warning_levels',
        ['schoollevel' => $schoollevel],
        '',
        'id, metric_key, color'
    );

    $now = time();

    foreach ($existing as $row) {
        $id = $row->id;

        // Only update rows we have inputs for.
        if (!array_key_exists($id, $tmins) && !array_key_exists($id, $tmaxs)) {
            continue;
        }

        $rawmin = isset($tmins[$id]) ? trim((string) $tmins[$id]) : '';
        $rawmax = isset($tmaxs[$id]) ? trim((string) $tmaxs[$id]) : '';

        // Empty string = NULL in the DB. Otherwise must be numeric.
        $newmin = ($rawmin === '') ? null : (is_numeric($rawmin) ? (float) $rawmin : false);
        $newmax = ($rawmax === '') ? null : (is_numeric($rawmax) ? (float) $rawmax : false);

        if ($newmin === false || $newmax === false) {
            throw new moodle_exception('warning_invalid_value', 'mod_mtssdashboard');
        }

        $update = (object) [
            'id'             => $id,
            'threshold_min'  => $newmin,
            'threshold_max'  => $newmax,
            'threshold_text' => stv_format_threshold_text($row->metric_key, $newmin, $newmax),
            'timemodified'   => $now,
            'usermodified'   => $USER->id,
        ];
        $DB->update_record('mtssdashboard_warning_levels', $update);
    }

    // IReady Lessons toggles: "by Grade" (lessonsoff_*) and "for RIMP Students"
    // (rimplessonsoff_*). Each is only processed when its card was on the
    // submitted form (hidden marker), so an unchecked box means "lessons on".
    $validgrades = stv_get_school_level_grades($schoollevel);
    foreach (['lessonsoff_', 'rimplessonsoff_'] as $prefix) {
        if (!optional_param($prefix . 'present', 0, PARAM_BOOL)) {
            continue;
        }
        foreach (['ela', 'math'] as $subject) {
            $picked = optional_param_array($prefix . $subject, [], PARAM_INT);
            $keep   = array_values(array_intersect($validgrades, array_map('intval', $picked)));
            sort($keep);
            set_config(
                $prefix . $schoollevel . '_' . $subject,
                implode(',', $keep),
                'mod_mtssdashboard'
            );
        }
    }

    // Redirect back to read-only view with a success notice.
    $redirecturl = new moodle_url('/mod/mtssdashboard/view.php', [
        'id'  => $cm->id,
        'tab' => 'warning',
    ]);
    redirect(
        $redirecturl,
        get_string('warning_saved', 'mod_mtssdashboard'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Page setup.
$pageurlparams = ['id' => $cm->id, 'tab' => $tab];
if ($year !== '') {
    $pageurlparams['year'] = $year;
}
if ($studuserid > 0) {
    $pageurlparams['studuserid'] = $studuserid;
}
foreach (['f_gender', 'f_race', 'f_ed', 'f_swd', 'f_el', 'f_504', 'f_iep', 'f_gifted'] as $fname) {
    if ($$fname !== '') {
        $pageurlparams[$fname] = $$fname;
    }
}
$PAGE->set_url('/mod/mtssdashboard/view.php', $pageurlparams);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
// Use Moodle's standard group selector — must come after $cm is defined.
$groupid = groups_get_activity_group($cm, true);
$PAGE->requires->js('/mod/mtssdashboard/chartjs.umd.min.js', true);
$PAGE->requires->js('/lib/amd/build/chartjs.min.js');

// Load data.
// Load level config and determine course level.
$levelcfg    = mtssdashboard_get_level_config();
$courselevel = mtssdashboard_get_course_level($course->category, $levelcfg);

// Set grade ranges for current and previous level.
if ($courselevel === 'hs') {
    $curmin  = $levelcfg->hs_min;
    $curmax  = $levelcfg->hs_max;
    $prevmin = $levelcfg->ms_min;
    $prevmax = $levelcfg->ms_max;
    $prevheading = get_string('history_hs_heading', 'mod_mtssdashboard');
} else if ($courselevel === 'ms') {
    $curmin  = $levelcfg->ms_min;
    $curmax  = $levelcfg->ms_max;
    $prevmin = $levelcfg->es_min;
    $prevmax = $levelcfg->es_max;
    $prevheading = get_string('history_ms_heading', 'mod_mtssdashboard');
} else {
    $curmin  = $levelcfg->es_min;
    $curmax  = $levelcfg->es_max;
    $prevmin = null;
    $prevmax = null;
    $prevheading = get_string('history_es_heading', 'mod_mtssdashboard');
}

// Load data.
$allstudents    = mtssdashboard_get_enrolled_students_with_ssid($course->id);
$yearoptions    = mtssdashboard_get_years();
$groups         = groups_get_all_groups($course->id);
$groupoptions   = [0 => get_string('filter_all', 'mod_mtssdashboard')];
foreach ($groups as $g) {
    $groupoptions[$g->id] = $g->name;
}

// Proficiency colour map.
$profcolors = [
    'Advanced'     => '#1a6e3c',
    'Accomplished' => '#2e86c1',
    'Proficient'   => '#1a8a1a',
    'Basic'        => '#d4a017',
    'Limited'      => '#c0392b',
    'Untested'     => '#adb5bd',
    'Not Reported' => '#6c757d',
];

// ----------------------------------------------------------------------
// HELPER FUNCTIONS.

/**
 * Filter student list by demographic parameters.
 *
 * @param array  $students Student records to filter.
 * @param string $fgender Gender filter ('' = any).
 * @param string $frace   Race filter ('' = any).
 * @param string $fed     Economically disadvantaged filter ('' = any).
 * @param string $fswd    Students with disabilities filter ('' = any).
 * @param string $fel     English learner filter ('' = any).
 * @param string $f504    504 plan filter ('' = any).
 * @param string $fiep    IEP filter ('' = any).
 * @param string $fgifted Gifted filter ('' = any).
 * @return array Students that match every filter.
 */
function stv_filter_students(
    $students,
    $fgender,
    $frace,
    $fed,
    $fswd,
    $fel,
    $f504,
    $fiep,
    $fgifted
) {
    $out = [];
    foreach ($students as $s) {
        if ($fgender !== '' && strcasecmp($s->gender, $fgender) !== 0) {
            continue;
        }
        if ($frace !== '' && strcasecmp($s->race_ethnicity, $frace) !== 0) {
            continue;
        }
        if ($fed !== '') {
            $ised = (stripos($s->ed_flag, 'free') !== false
                  || stripos($s->ed_flag, 'reduced') !== false) ? 'Y' : 'N';
            if ($ised !== $fed) {
                continue;
            }
        }
        if ($f504 !== '' && strcasecmp($s->status504, $f504) !== 0) {
            continue;
        }
        if ($fiep !== '' && strcasecmp($s->iep, $fiep) !== 0) {
            continue;
        }
        if ($fgifted !== '' && strcasecmp($s->gifted, $fgifted) !== 0) {
            continue;
        }
        $out[] = $s;
    }
    return $out;
}

/**
 * Fetch state test results for a set of SSIDs, optionally filtered by year and EL.
 * Returns most recent record per student per subject when $mostrecentonly = true.
 *
 * @param array  $ssids State student IDs.
 * @param string $year  School year to limit to ('' = all years).
 * @param string $fel   English learner filter ('' = any).
 * @return array Result records.
 */
function stv_get_results($ssids, $year = '', $fel = '') {
    global $DB, $CFG;
    if (empty($ssids)) {
        return [];
    }

    $statetable = $CFG->prefix . 'NTstatetesting';

    if ($year !== '') {
        // Specific year — straightforward query.
        [$insql, $params] = $DB->get_in_or_equal($ssids, SQL_PARAMS_NAMED, 'ssid');
        $where = "ssid $insql AND year = :year";
        $params['year'] = $year;
        if ($fel !== '') {
            $where .= ' AND el_code = :el';
            $params['el'] = $fel;
        }
        $where .= " AND proficiency_level IS NOT NULL AND proficiency_level != '' AND proficiency_level != 'Untested'";
        return $DB->get_records_sql(
            "SELECT * FROM {$statetable}
              WHERE $where
              ORDER BY ssid ASC, subject_code ASC",
            $params
        );
    } else {
        // No year — most recent per student per subject using subquery.
        [$insql1, $params1] = $DB->get_in_or_equal($ssids, SQL_PARAMS_NAMED, 'ssid1');
        [$insql2, $params2] = $DB->get_in_or_equal($ssids, SQL_PARAMS_NAMED, 'ssid2');
        [$insql3, $params3] = $DB->get_in_or_equal($ssids, SQL_PARAMS_NAMED, 'ssid3');
        $params = array_merge($params1, $params2, $params3);
        $elwhere = '';
        if ($fel !== '') {
            $elwhere = ' AND r.el_code = :el';
            $params['el'] = $fel;
        }
        return $DB->get_records_sql(
            "SELECT r.*
               FROM {$statetable} r
               INNER JOIN (
                   SELECT ssid, subject_code, MAX(year) AS maxyear
                     FROM {$statetable}
                    WHERE ssid $insql1
                    GROUP BY ssid, subject_code
               ) latest ON r.ssid = latest.ssid
                        AND r.subject_code = latest.subject_code
                        AND r.year = latest.maxyear
              WHERE r.ssid $insql2
                AND r.proficiency_level IS NOT NULL
                AND r.proficiency_level != ''
                {$elwhere}
              ORDER BY r.ssid ASC, r.subject_code ASC",
            $params
        );
    }
}

/**
 * Fetch ALL state test results for a set of SSIDs without most-recent-only filtering.
 * Used for History tab where we need all years to calculate grade-when-tested.
 *
 * @param array  $ssids State student IDs.
 * @param string $year  School year to limit to ('' = all years).
 * @param string $fel   English learner filter ('' = any).
 * @return array Result records.
 */
function stv_get_all_results($ssids, $year = '', $fel = '') {
    global $DB, $CFG;
    if (empty($ssids)) {
        return [];
    }
    $statetable = $CFG->prefix . 'NTstatetesting';
    [$insql, $params] = $DB->get_in_or_equal($ssids, SQL_PARAMS_NAMED, 'ssid');
    $where = "ssid $insql";
    if ($year !== '') {
        $where          .= ' AND year = :year';
        $params['year']  = $year;
    }
    if ($fel !== '') {
        $where          .= ' AND el_code = :el';
        $params['el']    = $fel;
    }
    $where .= " AND proficiency_level IS NOT NULL
                AND proficiency_level != ''
                AND proficiency_level != 'Untested'";

    return $DB->get_records_sql(
        "SELECT * FROM {$statetable}
          WHERE $where
          ORDER BY ssid ASC, subject_code ASC, year DESC",
        $params
    );
}

/**
 * From a result set, build per-subject data:
 * [ subject_description => [ 'scores' => [], 'proflevels' => [] ] ]
 *
 * @param array $results State test result records.
 * @return array Per-subject data keyed by subject description.
 */
function stv_by_subject($results) {
    $data = [];
    foreach ($results as $r) {
        $subj = $r->subject_description;
        if (!isset($data[$subj])) {
            $data[$subj] = ['scores' => [], 'proflevels' => [], 'code' => $r->subject_code];
        }
        $score = intval($r->assessment_score);
        if ($score > 0) {
            $data[$subj]['scores'][]     = $score;
            $data[$subj]['proflevels'][] = $r->proficiency_level ?? 'Not Reported';
        }
    }
    return $data;
}

/**
 * Compute proficiency distribution counts from results.
 *
 * @param array $results State test result records.
 * @return array Map of proficiency level => count (zero counts removed).
 */
function stv_prof_counts($results) {
    $levels = ['Advanced' => 0, 'Accomplished' => 0, 'Proficient' => 0,
               'Basic' => 0, 'Limited' => 0, 'Not Reported' => 0];
    foreach ($results as $r) {
        $pl = trim($r->proficiency_level ?? '');
        $key = isset($levels[$pl]) ? $pl : 'Not Reported';
        $levels[$key]++;
    }
    // Remove zero entries.
    return array_filter($levels);
}

/**
 * Render the filter form.
 *
 * @param stdClass $cm           Course module.
 * @param string   $tab          Current tab.
 * @param string   $year         Selected school year.
 * @param array    $yearoptions  School years to offer.
 * @param array    $allstudents  Students for the student selector.
 * @param int      $studuserid   Selected student user id (0 = none).
 * @param string   $fgender      Gender filter ('' = any).
 * @param string   $frace        Race filter ('' = any).
 * @param string   $fed          Economically disadvantaged filter ('' = any).
 * @param string   $fswd         Students with disabilities filter ('' = any).
 * @param string   $fel          English learner filter ('' = any).
 * @param string   $f504         504 plan filter ('' = any).
 * @param string   $fiep         IEP filter ('' = any).
 * @param string   $fgifted      Gifted filter ('' = any).
 * @return string Form HTML.
 */
function stv_render_filters(
    $cm,
    $tab,
    $year,
    $yearoptions,
    $allstudents,
    $studuserid,
    $fgender,
    $frace,
    $fed,
    $fswd,
    $fel,
    $f504,
    $fiep,
    $fgifted
) {

    $out = '';
    $out .= html_writer::start_tag('form', [
        'method' => 'get',
        'action' => (new moodle_url('/mod/mtssdashboard/view.php'))->out(false),
        'class'  => 'mtssdashboard-filters card card-body mb-3 p-2',
    ]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => $tab]);
    $out .= html_writer::start_div('row g-2 align-items-end');

    // Year — hidden on the student tab (trend chart always shows all years).
    if ($tab !== 'student') {
        $out .= stv_filter_select(
            'year',
            get_string('filter_year', 'mod_mtssdashboard'),
            ['' => get_string('filter_all', 'mod_mtssdashboard')] + $yearoptions,
            $year
        );
    }

    // Student (student tab only) — auto-submits on change, no buttons needed.
    if ($tab === 'student') {
        $stopts = [0 => '— ' . get_string('selectstudent', 'mod_mtssdashboard') . ' —'];
        foreach ($allstudents as $s) {
            $stopts[$s->userid] = $s->lastname . ', ' . $s->firstname;
        }
        $out .= html_writer::start_div('col-auto');
        $out .= html_writer::tag(
            'label',
            get_string('filter_student', 'mod_mtssdashboard'),
            ['for' => 'stv_studuserid', 'class' => 'form-label small mb-0']
        );
        $out .= html_writer::start_tag('select', [
            'name'     => 'studuserid',
            'id'       => 'stv_studuserid',
            'class'    => 'form-select form-select-sm',
            'onchange' => 'this.form.submit();',
        ]);
        foreach ($stopts as $val => $lbl) {
            $attrs = ['value' => $val];
            if ((string)$val === (string)$studuserid) {
                $attrs['selected'] = 'selected';
            }
            $out .= html_writer::tag('option', $lbl, $attrs);
        }
        $out .= html_writer::end_tag('select');
        $out .= html_writer::end_div();
    }

    // Demographic filters — hidden on the student tab (single student view).
    if ($tab !== 'student') {
        $ynopts = ['' => '—', 'Y' => 'Yes', 'N' => 'No'];
        $out .= stv_filter_select(
            'f_gender',
            get_string('filter_gender', 'mod_mtssdashboard'),
            ['' => '—', 'M' => 'Male', 'F' => 'Female'],
            $fgender
        );
        $out .= stv_filter_select('f_ed', get_string('filter_ed', 'mod_mtssdashboard'), $ynopts, $fed);
        $out .= stv_filter_select('f_swd', get_string('filter_swd', 'mod_mtssdashboard'), $ynopts, $fswd);
        $out .= stv_filter_select('f_iep', get_string('filter_iep', 'mod_mtssdashboard'), $ynopts, $fiep);
        $out .= stv_filter_select('f_504', get_string('filter_504', 'mod_mtssdashboard'), $ynopts, $f504);
        $out .= stv_filter_select('f_gifted', get_string('filter_gifted', 'mod_mtssdashboard'), $ynopts, $fgifted);
    }

    // Buttons — hidden on student tab (dropdown auto-submits).
    if ($tab !== 'student') {
        $out .= html_writer::start_div('col-auto');
        $out .= html_writer::tag(
            'button',
            get_string('applyfilters', 'mod_mtssdashboard'),
            ['type' => 'submit', 'class' => 'btn btn-primary btn-sm']
        );
        $out .= ' ';
        $out .= html_writer::link(
            new moodle_url('/mod/mtssdashboard/view.php', ['id' => $cm->id, 'tab' => $tab]),
            get_string('resetfilters', 'mod_mtssdashboard'),
            ['class' => 'btn btn-secondary btn-sm']
        );
        $out .= html_writer::end_div();
    }
    $out .= html_writer::end_div();
    $out .= html_writer::end_tag('form');
    return $out;
}

/**
 * Render a single filter select element.
 *
 * @param string $name     Form field name.
 * @param string $label    Label shown above the select.
 * @param array  $options  Map of value => label.
 * @param string $selected Currently selected value.
 * @return string Select HTML.
 */
function stv_filter_select($name, $label, $options, $selected) {
    $out  = html_writer::start_div('col-auto');
    $out .= html_writer::tag(
        'label',
        $label,
        ['for' => 'stv_' . $name, 'class' => 'form-label small mb-0']
    );
    $out .= html_writer::start_tag(
        'select',
        ['name' => $name, 'id' => 'stv_' . $name, 'class' => 'form-select form-select-sm']
    );
    foreach ($options as $val => $lbl) {
        $attrs = ['value' => $val];
        // Loose comparison intentional for int/string mixed keys.
        if ((string)$val === (string)$selected) {
            $attrs['selected'] = 'selected';
        }
        $out .= html_writer::tag('option', $lbl, $attrs);
    }
    $out .= html_writer::end_tag('select');
    $out .= html_writer::end_div();
    return $out;
}

/**
 * Render one subject doughnut chart (proficiency distribution for that subject).
 *
 * @param string $canvasid          HTML id for the chart canvas.
 * @param string $title             Chart title.
 * @param array  $resultsforsubject Result records for this subject.
 * @param array  $profcolors        Map of proficiency level => colour.
 * @param int    $totalsubjects     Number of charts in the row (sets the width).
 * @return array ['html' => string, 'js' => string].
 */
function stv_subject_chart($canvasid, $title, $resultsforsubject, $profcolors, $totalsubjects = 3) {
    // Count proficiency levels for this subject.
    $counts = [];
    foreach ($resultsforsubject as $r) {
        $pl = $r->proficiency_level ?? 'Not Reported';
        if (!isset($counts[$pl])) {
            $counts[$pl] = 0;
        }
        $counts[$pl]++;
    }

    if (empty($counts)) {
        return ['html' => '', 'js' => ''];
    }

    $labels = json_encode(array_keys($counts));
    $data   = json_encode(array_values($counts));
    $colors = json_encode(array_map(fn($k) => $profcolors[$k] ?? '#6c757d', array_keys($counts)));
    $total  = array_sum($counts);
    $chartid = 'chart_' . preg_replace('/[^a-z0-9]/i', '_', $canvasid);

    $colclass = ($totalsubjects <= 2) ? 'col-md-5' : 'col-md-4';
    // Cap canvas height so per-subject charts never grow larger than the
    // top "Proficiency Distribution" chart on wide screens.
    $canvasstyle = 'max-height:300px;';
    $html  = html_writer::start_div($colclass . ' mb-4');
    $html .= html_writer::tag('h5', htmlspecialchars($title), ['class' => 'text-center']);
    $html .= html_writer::tag('canvas', '', ['id' => $chartid, 'style' => $canvasstyle]);
    $html .= html_writer::end_div();

    // Match the Grades tab style: total in the center, per-slice counts on
    // each visible slice, no legend.
    $js = <<<JS
(function(){
    var ctx = document.getElementById('{$chartid}').getContext('2d');
    var datalabelsPlugin = {
        id: 'datalabels_{$chartid}',
        afterDatasetsDraw: function(chart) {
            var ctx2 = chart.ctx;
            chart.data.datasets.forEach(function(dataset, i) {
                var meta = chart.getDatasetMeta(i);
                meta.data.forEach(function(arc, index) {
                    var value = dataset.data[index];
                    var total = dataset.data.reduce(function(a,b){return a+b;},0);
                    var pct = Math.round(value/total*100);
                    if (pct < 5) return; // Skip tiny slices.
                    var pos = arc.tooltipPosition();
                    ctx2.save();
                    ctx2.fillStyle = '#fff';
                    ctx2.font = 'bold 12px sans-serif';
                    ctx2.textAlign = 'center';
                    ctx2.textBaseline = 'middle';
                    ctx2.fillText(value, pos.x, pos.y);
                    ctx2.restore();
                });
            });
        }
    };
    var centerPlugin = {
        id: 'cp_{$chartid}',
        afterDraw: function(chart) {
            var w = chart.width, h = chart.height, c = chart.ctx;
            c.restore();
            c.font = 'bold ' + Math.round(h/6) + 'px sans-serif';
            c.textBaseline = 'middle';
            c.fillStyle = '#333';
            var txt = '{$total}';
            c.fillText(txt, Math.round((w - c.measureText(txt).width)/2), h/2);
            c.save();
        }
    };
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: {$labels},
            datasets: [{ data: {$data}, backgroundColor: {$colors} }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                title: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                            var pct = Math.round(ctx.parsed/total*100);
                            return ctx.label+': '+ctx.parsed+' ('+pct+'%)';
                        }
                    }
                }
            }
        },
        plugins: [centerPlugin, datalabelsPlugin]
    });
})();
JS;
    return ['html' => $html, 'js' => $js];
}

/**
 * Render collapsed data table.
 *
 * @param array $results  State test result records.
 * @param array $students Students keyed for name lookup.
 * @return string Table HTML.
 */
function stv_render_table($results, $students) {
    global $OUTPUT;
    $ssidmap = [];
    foreach ($students as $s) {
        $ssidmap[$s->ssid] = $s;
    }

    // Sort by student lastname, firstname, then subject, then year.
    usort($results, function ($a, $b) use ($ssidmap) {
        $sa = $ssidmap[$a->ssid] ?? null;
        $sb = $ssidmap[$b->ssid] ?? null;
        $namediff = strcmp(
            ($sa ? $sa->lastname . $sa->firstname : $a->ssid),
            ($sb ? $sb->lastname . $sb->firstname : $b->ssid)
        );
        if ($namediff !== 0) {
            return $namediff;
        }
        // Most recent year first.
        $yeardiff = strcmp($b->year, $a->year);
        if ($yeardiff !== 0) {
            return $yeardiff;
        }
        // Then alphabetical by subject.
        return strcmp($a->subject_description, $b->subject_description);
    });

    $table = new html_table();
    $table->head = [
        get_string('th_student', 'mod_mtssdashboard'),
        get_string('th_year', 'mod_mtssdashboard'),
        get_string('th_subject', 'mod_mtssdashboard'),
        get_string('th_grade', 'mod_mtssdashboard'),
        get_string('th_score', 'mod_mtssdashboard'),
        get_string('th_proficiency', 'mod_mtssdashboard'),
    ];
    $table->attributes['class'] = 'generaltable table-sm table-hover';

    $profcolors = [
        'Advanced'     => '#1a6e3c',
        'Accomplished' => '#2e86c1',
        'Proficient'   => '#1a8a1a',
        'Basic'        => '#d4a017',
        'Limited'      => '#c0392b',
    ];

    foreach ($results as $r) {
        $s    = $ssidmap[$r->ssid] ?? null;
        $name = $s ? ($s->lastname . ', ' . $s->firstname) : $r->ssid;
        $pl   = $r->proficiency_level ?? '—';
        $bg   = $profcolors[$pl] ?? '#6c757d';

        $profcell = new html_table_cell($pl);
        $profcell->attributes['style'] =
            "background:{$bg};color:#fff;font-weight:bold;text-align:center;";

        $table->data[] = new html_table_row([
            new html_table_cell($name),
            new html_table_cell($r->year),
            new html_table_cell($r->subject_description),
            new html_table_cell($r->grade_level),
            new html_table_cell($r->assessment_score),
            $profcell,
        ]);
    }

    $uid = uniqid('stv_tbl_');
    $out  = '<div class="mt-4">';
    $out .= '<button class="btn btn-outline-secondary btn-sm mb-2" type="button" ';
    $out .= 'onclick="var el=document.getElementById(\'' . $uid . '\');';
    $out .= 'el.style.display=(el.style.display===\'none\'||el.style.display===\'\')';
    $out .= '?\'block\':\'none\';">';
    $out .= '&#9660; Show / Hide Data Table</button>';
    $out .= '<div id="' . $uid . '" style="display:none;">';
    $out .= html_writer::table($table);
    $out .= '</div></div>';
    return $out;
}

// ----------------------------------------------------------------------
// OUTPUT.
if ($tab === 'group') {
    $tabstudents = $allstudents;
} else if ($tab === 'student') {
    $tabstudents = $allstudents;
} else if ($tab === 'grades') {
    if ($groupid > 0) {
        $gmids = array_keys(groups_get_members($groupid, 'u.id'));
        $tabstudents = array_filter(
            $allstudents,
            fn($s) => in_array($s->userid, $gmids)
        );
    } else {
        $tabstudents = $allstudents;
    }
} else {
    if ($groupid > 0) {
        $gmids = array_keys(groups_get_members($groupid, 'u.id'));
        $tabstudents = array_filter(
            $allstudents,
            fn($s) => in_array($s->userid, $gmids)
        );
    } else {
        $tabstudents = $allstudents;
    }
}

// CSV download handler: Early Warning tab.
if (
    optional_param('action', '', PARAM_ALPHANUMEXT) === 'downloadcsv'
        && $tab === 'earlywarning'
) {
    require_once($CFG->libdir . '/gradelib.php');
}
if (
    optional_param('action', '', PARAM_ALPHANUMEXT) === 'downloadcsv'
        && $tab === 'earlywarning'
) {
    require_sesskey();
    $schoollevel = stv_get_school_level_for_course($course);
    $ewstudents = stv_filter_students(
        $tabstudents,
        $fgender,
        $frace,
        $fed,
        $fswd,
        $fel,
        $f504,
        $fiep,
        $fgifted
    );
    $userids = [];
    $studentnumbers = [];
    $ssids = [];
    foreach ($ewstudents as $s) {
        $userids[] = (int) $s->userid;
        if (!empty($s->idnumber)) {
            $studentnumbers[] = (int) $s->idnumber;
        }
        if (!empty($s->ssid)) {
            $ssids[] = $s->ssid;
        }
    }
    $incidents  = stv_get_office_incidents_by_studentnumber($studentnumbers);
    $attendance = stv_get_attendance_by_studentnumber($studentnumbers);
    $failed     = stv_get_failed_courses_by_userid($userids);
    $gpas       = stv_get_gpa_by_userid($userids, $schoollevel);
    $lowost     = stv_get_low_ost_by_ssid($ssids);
    $ireadyela = stv_get_iready_ela_by_studentnumber($studentnumbers);
    $ireadymth = stv_get_iready_math_by_studentnumber($studentnumbers);
    $ireadydays = ($schoollevel === 'ms') ? stv_get_iready_days_since_lesson($studentnumbers) : [];
    $rimpdata   = stv_get_rimp_by_studentnumber($studentnumbers);
    if ($schoollevel === 'es') {
        $metrickeys = ['total_hours_missed', 'unexcused_hours_missed',
            'iready_ela_percentile', 'iready_math_percentile', 'ost_performance'];
    } else if ($schoollevel === 'ms') {
        $metrickeys = ['total_hours_missed', 'unexcused_hours_missed', 'office_incidents',
            'course_failure_count', 'gpa', 'iready_ela_percentile', 'iready_ela_days',
            'iready_math_percentile', 'iready_math_days', 'ost_performance'];
    } else {
        $metrickeys = ['total_hours_missed', 'unexcused_hours_missed', 'office_incidents',
            'course_failure_count', 'gpa', 'ost_performance'];
    }
    $colheaders = [
        'total_hours_missed' => 'Total Hours Missed', 'unexcused_hours_missed' => 'Unexcused Hours',
        'office_incidents' => 'Office Incidents', 'course_failure_count' => 'Failing Courses',
        'gpa' => 'Current GPA', 'iready_ela_percentile' => 'iReady ELA Percentile',
        'iready_math_percentile' => 'iReady Math Percentile', 'ost_performance' => 'Low OST Scores',
        'iready_ela_days' => 'Days Since ELA Lesson', 'iready_math_days' => 'Days Since Math Lesson',
    ];
    $rows = [];
    foreach ($ewstudents as $s) {
        $sn = $s->idnumber ? (int)$s->idnumber : 0;
        $uid = (int)$s->userid;
        $sid = $s->ssid ?? '';
        $row = (object)['userid' => $uid, 'studentnumber' => $sn, 'firstname' => $s->firstname,
            'lastname' => $s->lastname, 'gradelevel' => $s->gradelevel ?? '',
            'metrics' => ['total_hours_missed' => null, 'unexcused_hours_missed' => null,
                'office_incidents' => $incidents[$sn] ?? 0, 'course_failure_count' => $failed[$uid] ?? 0,
                'gpa' => $gpas[$uid] ?? null, 'iready_ela_percentile' => $ireadyela[$sn] ?? null,
                'iready_math_percentile' => $ireadymth[$sn] ?? null, 'ost_performance' => $lowost[$sid] ?? 0,
                'iready_ela_days' => $ireadydays[$sn]->ela_days ?? null,
                'iready_math_days' => $ireadydays[$sn]->math_days ?? null]];
        if (isset($attendance[$sn])) {
            $row->metrics['total_hours_missed'] = $attendance[$sn]->total_hours;
            $row->metrics['unexcused_hours_missed'] = $attendance[$sn]->unexcused_hours;
        }
        // Blank lesson-days for grades (or RIMP students in grades) that don't
        // take iReady lessons (Warning Levels toggles).
        $row->metrics = stv_apply_lessons_off(
            $row->metrics,
            $row->gradelevel,
            $schoollevel,
            !empty($rimpdata[$sn])
        );
        $rows[] = $row;
    }
    $ewsortcsv = optional_param('ewsort', 'name', PARAM_ALPHA);
    foreach ($rows as $row) {
        $flagcount = 0;
        foreach ($metrickeys as $mkey) {
            $value = $row->metrics[$mkey];
            if ($value === null) {
                continue;
            }
            $color = stv_get_band_color($schoollevel, $mkey, $value);
            if (stv_band_is_flagged($color)) {
                $flagcount++;
            }
        }
        $row->riskkey = stv_compute_risk_level($flagcount, $schoollevel);
        $row->flagcount = $flagcount;
    }
    if ($ewsortcsv === 'risk') {
        $riskrank = ['extreme' => 0, 'moderate' => 1, 'low' => 2, 'none' => 3];
        usort($rows, function ($a, $b) use ($riskrank) {
            $ra = $riskrank[$a->riskkey] ?? 99;
            $rb = $riskrank[$b->riskkey] ?? 99;
            if ($ra !== $rb) {
                return $ra - $rb;
            }
            $lc = strcasecmp($a->lastname, $b->lastname);
            return $lc !== 0 ? $lc : strcasecmp($a->firstname, $b->firstname);
        });
    } else {
        usort($rows, function ($a, $b) {
            $lc = strcasecmp($a->lastname, $b->lastname);
            return $lc !== 0 ? $lc : strcasecmp($a->firstname, $b->firstname);
        });
    }
    $risklabels = ['none' => 'No Risk', 'low' => 'Low Risk', 'moderate' => 'Moderate Risk', 'extreme' => 'Extreme Risk'];
    $filename = clean_filename($course->shortname . '_earlywarning_' . date('Ymd') . '.csv');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    $csvheader = ['Grade', 'Student ID', 'Last Name', 'First Name', 'Overall Risk'];
    foreach ($metrickeys as $mkey) {
        $csvheader[] = $colheaders[$mkey] ?? $mkey;
    }
    fputcsv($out, $csvheader);
    foreach ($rows as $row) {
        $csvrow = [$row->gradelevel, $row->studentnumber > 0 ? $row->studentnumber : '',
            $row->lastname, $row->firstname, $risklabels[$row->riskkey] ?? $row->riskkey];
        foreach ($metrickeys as $mkey) {
            $value = $row->metrics[$mkey];
            if ($value === null) {
                $csvrow[] = '';
            } else if (in_array($mkey, ['iready_ela_days', 'iready_math_days'], true) && (int)$value === 99) {
                $csvrow[] = 'Never';
            } else if ($mkey === 'gpa') {
                $csvrow[] = number_format((float)$value, 3);
            } else if (in_array($mkey, ['total_hours_missed', 'unexcused_hours_missed'], true)) {
                $csvrow[] = number_format((float)$value, 2);
            } else {
                $csvrow[] = (int)$value;
            }
        }
        fputcsv($out, $csvrow);
    }
    fclose($out);
    exit;
}

// Bulk print handler: Download RIMPs / Download WEPs.
// Builds one print-ready page holding every FINALIZED RIMP or WEP for the
// students currently shown on the tab (current group + applied demographic
// filters), so staff can Save as PDF and send them all through ParentSquare.
$bulkaction = optional_param('action', '', PARAM_ALPHANUMEXT);
$isbulkrimp = ($bulkaction === 'downloadrimps' && $tab === 'rimp');
$isbulkwep = ($bulkaction === 'downloadweps' && $tab === 'wep');
if ($isbulkrimp || $isbulkwep) {
    require_sesskey();
    $bulktype = $isbulkrimp ? 'rimp' : 'wep';
    require_capability('mod/mtssdashboard:edit' . $bulktype, $context);

    $bulkstudents = stv_filter_students(
        $tabstudents,
        $fgender,
        $frace,
        $fed,
        $fswd,
        $fel,
        $f504,
        $fiep,
        $fgifted
    );
    // Leave out CTC (J/NJ) and no-longer-attending (I/R) students.
    $bulkstudents = mtssdashboard_remove_skipped_students($bulkstudents);
    usort($bulkstudents, function ($a, $b) {
        $lc = strcasecmp($a->lastname, $b->lastname);
        return $lc !== 0 ? $lc : strcasecmp($a->firstname, $b->firstname);
    });

    $bulksnlist = [];
    foreach ($bulkstudents as $s) {
        if (!empty($s->idnumber)) {
            $bulksnlist[] = (int) $s->idnumber;
        }
    }

    // Keep only students whose record is finalized (status = 1), in name order.
    $bulkfinal = [];
    if (!empty($bulksnlist)) {
        [$insql, $inparams] = $DB->get_in_or_equal($bulksnlist, SQL_PARAMS_NAMED);
        $finalrecs = $DB->get_records_select(
            'mtssdashboard_' . $bulktype,
            "studentnumber $insql AND status = 1",
            $inparams,
            '',
            'id, studentnumber'
        );
        foreach ($finalrecs as $r) {
            $bulkfinal[(int) $r->studentnumber] = true;
        }
    }
    $bulkprint = array_values(array_filter($bulksnlist, fn ($sn) => isset($bulkfinal[$sn])));

    $bulktitle = get_string(
        $bulktype . '_bulk_title',
        'mod_mtssdashboard',
        format_string($course->shortname, true, ['context' => $context])
    );
    mtssdashboard_render_bulk_reports(
        $bulktype,
        $bulkprint,
        stv_get_school_level_for_course($course),
        $bulktitle
    );
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($instance->name));

if (!empty($instance->intro)) {
    echo $OUTPUT->box(
        format_module_intro('mtssdashboard', $instance, $cm->id),
        'generalbox mod_introbox'
    );
}

if (empty($allstudents)) {
    echo $OUTPUT->notification(get_string('nostudentlink', 'mod_mtssdashboard'), 'notifywarning');
    echo $OUTPUT->footer();
    exit;
}

// Tabs.
$baseurl = new moodle_url('/mod/mtssdashboard/view.php', ['id' => $cm->id]);
$tabs = [];

// Schoollevel is needed here for the RIMPS tab check. It is also set
// independently inside each tab's rendering block below.
if (!isset($schoollevel)) {
    $schoollevel = stv_get_school_level_for_course($course);
}

// Early Warning and common tabs shown at all levels.
$tabs[] = new tabobject(
    'earlywarning',
    new moodle_url($baseurl, ['tab' => 'earlywarning']),
    get_string('tab_earlywarning', 'mod_mtssdashboard')
);

// Student Info tab — shown second, right after Early Warning.
$tabs[] = new tabobject(
    'student',
    new moodle_url($baseurl, ['tab' => 'student']),
    get_string('tab_student', 'mod_mtssdashboard')
);

// Grades tab — HS and MS only.
if (in_array($schoollevel, ['hs', 'ms'])) {
    $tabs[] = new tabobject(
        'grades',
        new moodle_url($baseurl, ['tab' => 'grades']),
        get_string('tab_grades', 'mod_mtssdashboard')
    );
}

foreach (['class', 'subjecthistory'] as $t) {
    $tabs[] = new tabobject(
        $t,
        new moodle_url($baseurl, ['tab' => $t]),
        get_string('tab_' . $t, 'mod_mtssdashboard')
    );
}

// History tab — HS and MS only.
if (in_array($schoollevel, ['hs', 'ms'])) {
    $tabs[] = new tabobject(
        'history',
        new moodle_url($baseurl, ['tab' => 'history']),
        get_string('tab_history', 'mod_mtssdashboard')
    );
}

// Warning Levels tab — all levels, all teachers.
$tabs[] = new tabobject(
    'warning',
    new moodle_url($baseurl, ['tab' => 'warning']),
    get_string('tab_warning', 'mod_mtssdashboard')
);

// RIMP tab — any teacher in any course.
if (has_capability('mod/mtssdashboard:editrimp', $context)) {
    $tabs[] = new tabobject(
        'rimp',
        new moodle_url($baseurl, ['tab' => 'rimp']),
        get_string('tab_rimp', 'mod_mtssdashboard')
    );
}

// WEP tab — any teacher in any course.
if (has_capability('mod/mtssdashboard:editwep', $context)) {
    $tabs[] = new tabobject(
        'wep',
        new moodle_url($baseurl, ['tab' => 'wep']),
        get_string('tab_wep', 'mod_mtssdashboard')
    );
}

// Plans tab — all levels, all teachers.
$tabs[] = new tabobject(
    'plans',
    new moodle_url($baseurl, ['tab' => 'plans']),
    get_string('tab_plans', 'mod_mtssdashboard')
);

// Grade Updates tab — HS and MS only, managers only.
if (in_array($schoollevel, ['hs', 'ms']) && stv_can_edit_warning_levels($course, $schoollevel)) {
    $tabs[] = new tabobject(
        'gradeupdates',
        new moodle_url($baseurl, ['tab' => 'gradeupdates']),
        get_string('tab_gradeupdates', 'mod_mtssdashboard')
    );
}
echo $OUTPUT->tabtree($tabs, $tab);

// Filter form.
// Standard Moodle group selector above filters.
// Filters do not apply to the Warning Levels tab — those are school-wide thresholds.
// Student tab only shows Year and Student dropdowns — demographic filters don't apply
// to a single-student view.
if ($tab !== 'warning') {
    echo groups_print_activity_menu($cm, $PAGE->url, true);
    // For the student tab, filter the student dropdown to only show
    // students in the currently selected group.
    $dropdownstudents = $allstudents;
    if ($tab === 'student' && $groupid > 0) {
        $gmids = array_keys(groups_get_members($groupid, 'u.id'));
        $dropdownstudents = array_filter(
            $allstudents,
            fn($s) => in_array($s->userid, $gmids)
        );
    }
    echo stv_render_filters(
        $cm,
        $tab,
        $year,
        $yearoptions,
        $dropdownstudents,
        $studuserid,
        $fgender,
        $frace,
        $fed,
        $fswd,
        $fel,
        $f504,
        $fiep,
        $fgifted
    );
}

// Determine student set for this tab.
if ($tab === 'group') {
    $tabstudents = $allstudents; // Group tab — demographic filter applied inside tab block.
} else if ($tab === 'student') {
    $tabstudents = $allstudents; // Handled individually below.
} else if ($tab === 'grades') {
    if ($groupid > 0) {
        $gmids = array_keys(groups_get_members($groupid, 'u.id'));
        $tabstudents = array_filter(
            $allstudents,
            fn($s) => in_array($s->userid, $gmids)
        );
    } else {
        $tabstudents = $allstudents;
    }
} else {
    // Apply Moodle group filter to class view.
    if ($groupid > 0) {
        $gmids = array_keys(groups_get_members($groupid, 'u.id'));
        $tabstudents = array_filter(
            $allstudents,
            fn($s) => in_array($s->userid, $gmids)
        );
    } else {
        $tabstudents = $allstudents;
    }
}

// ----------------------------------------------------------------------
// CLASS VIEW & GROUP VIEW.
if ($tab === 'class' || $tab === 'group') {
    $students = stv_filter_students(
        $tabstudents,
        $fgender,
        $frace,
        $fed,
        $fswd,
        $fel,
        $f504,
        $fiep,
        $fgifted
    );

    if (empty($students)) {
        echo $OUTPUT->notification(get_string('nostudentdata', 'mod_mtssdashboard'), 'notifywarning');
        echo $OUTPUT->footer();
        exit;
    }

    $ssids   = array_column($students, 'ssid');
    $allresults = stv_get_results($ssids, $year, $fel);

    // Filter to current school level only.
    $results = mtssdashboard_filter_by_grade_range(
        $allresults,
        $students,
        $curmin,
        $curmax,
        $levelcfg->gradyear
    );

    if (empty($results)) {
        echo $OUTPUT->notification(get_string('chart_nodata', 'mod_mtssdashboard'), 'notifyinfo');
        echo $OUTPUT->footer();
        exit;
    }

    // Build ssid → name map.
    $namemap = [];
    foreach ($students as $s) {
        $namemap[$s->ssid] = $s->lastname . ', ' . substr($s->firstname, 0, 1) . '.';
    }

    // Group results by subject.
    $bysubject = stv_by_subject($results);

    // Proficiency distribution across all subjects.
    $profcounts = stv_prof_counts($results);

    // Heading.
    $headlabel = ($tab === 'group' && $groupid > 0 && isset($groups[$groupid]))
        ? $groups[$groupid]->name
        : get_string('filter_all', 'mod_mtssdashboard');
    echo $OUTPUT->heading(
        get_string('tab_' . $tab, 'mod_mtssdashboard') . ' — ' .
        $headlabel . ' — ' . $year,
        4
    );

    // Charts.
    $alljs = '';

    // Overall proficiency distribution doughnut + totals table.
    echo html_writer::start_div('row mb-4 align-items-center');

    // Left col: doughnut chart fixed width.
    echo html_writer::start_div('col-md-6');
    echo html_writer::tag(
        'h5',
        get_string('chart_proficiency', 'mod_mtssdashboard'),
        ['class' => 'text-center']
    );
    echo html_writer::tag('canvas', '', ['id' => 'chartProfDist', 'style' => 'max-height:320px;']);
    echo html_writer::end_div();

    // Right col: totals table.
    echo html_writer::start_div('col-md-5');
    $studentcount = count(array_unique(array_column($students, 'ssid')));
    echo html_writer::tag('h5', get_string('prof_totals', 'mod_mtssdashboard'), ['class' => 'text-center']);
    echo html_writer::tag(
        'p',
        get_string('prof_totalstudents', 'mod_mtssdashboard') . ': ' . $studentcount,
        ['class' => 'text-center text-muted small mb-1']
    );
    $table = new html_table();
    $table->head = [
        get_string('th_proficiency', 'mod_mtssdashboard'),
        get_string('prof_testrecords', 'mod_mtssdashboard'),
        get_string('prof_pct', 'mod_mtssdashboard'),
    ];
    $table->attributes['class'] = 'generaltable table-sm table-bordered text-center';
    $total = array_sum($profcounts);
    foreach ($profcounts as $level => $cnt) {
        $pct = $total > 0 ? round(($cnt / $total) * 100, 1) : 0;
        $bgcolor = $profcolors[$level] ?? '#6c757d';
        $cell1 = new html_table_cell($level);
        $cell1->attributes['style'] = "background:{$bgcolor};color:#fff;font-weight:bold;";
        $cell2 = new html_table_cell($cnt);
        $cell3 = new html_table_cell($pct . '%');
        $table->data[] = new html_table_row([$cell1, $cell2, $cell3]);
    }
    // Total row.
    $cell1 = new html_table_cell(get_string('prof_total', 'mod_mtssdashboard'));
    $cell1->attributes['style'] = 'font-weight:bold;';
    $table->data[] = new html_table_row([$cell1,
        new html_table_cell($total),
        new html_table_cell('100%')]);
    echo html_writer::table($table);
    echo html_writer::end_div();

    echo html_writer::end_div(); // End .row.

    // Output the doughnut JS — center total + per-slice counts, no legend.
    $colors = json_encode(array_map(fn($k) => $profcolors[$k] ?? '#6c757d', array_keys($profcounts)));
    $labels = json_encode(array_keys($profcounts));
    $data   = json_encode(array_values($profcounts));
    $proftotal = array_sum($profcounts);
    $alljs .= <<<JS
(function(){
    var ctx = document.getElementById('chartProfDist').getContext('2d');
    var datalabelsPlugin = {
        id: 'datalabels_chartProfDist',
        afterDatasetsDraw: function(chart) {
            var ctx2 = chart.ctx;
            chart.data.datasets.forEach(function(dataset, i) {
                var meta = chart.getDatasetMeta(i);
                meta.data.forEach(function(arc, index) {
                    var value = dataset.data[index];
                    var total = dataset.data.reduce(function(a,b){return a+b;},0);
                    var pct = Math.round(value/total*100);
                    if (pct < 5) return;
                    var pos = arc.tooltipPosition();
                    ctx2.save();
                    ctx2.fillStyle = '#fff';
                    ctx2.font = 'bold 14px sans-serif';
                    ctx2.textAlign = 'center';
                    ctx2.textBaseline = 'middle';
                    ctx2.fillText(value, pos.x, pos.y);
                    ctx2.restore();
                });
            });
        }
    };
    var centerPlugin = {
        id: 'cp_chartProfDist',
        afterDraw: function(chart) {
            var ca = chart.chartArea;
            var cx = (ca.left + ca.right) / 2;
            var cy = (ca.top + ca.bottom) / 2;
            var c = chart.ctx;
            c.restore();
            c.font = 'bold ' + Math.round((ca.bottom - ca.top) / 5) + 'px sans-serif';
            c.textBaseline = 'middle';
            c.textAlign = 'center';
            c.fillStyle = '#333';
            c.fillText('{$proftotal}', cx, cy);
            c.save();
        }
    };
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: {$labels},
            datasets: [{ data: {$data}, backgroundColor: {$colors} }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'right',
                    labels: {
                        boxWidth: 14,
                        padding: 10,
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                            var pct = Math.round(ctx.parsed/total*100);
                            return ctx.label+': '+ctx.parsed+' ('+pct+'%)';
                        }
                    }
                }
            }
        },
        plugins: [centerPlugin, datalabelsPlugin]
    });
})();
JS;

    echo $OUTPUT->heading(get_string('prof_bysubject', 'mod_mtssdashboard'), 4);

    // One doughnut per subject, 3 per row.
    echo html_writer::start_div('row');

    $totalsubjects = count($bysubject);
    $si = 0;
    foreach ($bysubject as $subjdesc => $subjdata) {
        // Collect results for this subject only.
        $subjectresults = array_filter($results, fn($r) => $r->subject_description === $subjdesc);

        $canvasid = 'subj_' . $si;
        $chart = stv_subject_chart($canvasid, $subjdesc, $subjectresults, $profcolors, $totalsubjects);
        echo $chart['html'];
        $alljs .= $chart['js'];

        $si++;
        // New row every 3 charts (or 2 if only 2 subjects).
        $perrow = ($totalsubjects <= 2) ? 2 : 3;
        if ($si % $perrow === 0) {
            echo html_writer::end_div();
            echo html_writer::start_div('row');
        }
    }
    echo html_writer::end_div(); // End .row.

    // Proficiency legend was disabled; the old commented-out code is in git history.
    // Collapsed data table — current level.
    echo stv_render_table($results, $students);

    // Inject all Chart.js.
    echo '<script>' . $alljs . '</script>';
}

// ----------------------------------------------------------------------
// HISTORY TAB.
if ($tab === 'history') {
    $students = stv_filter_students(
        $tabstudents,
        $fgender,
        $frace,
        $fed,
        $fswd,
        $fel,
        $f504,
        $fiep,
        $fgifted
    );

    if ($courselevel === 'es') {
        echo $OUTPUT->notification(
            get_string('history_es_heading', 'mod_mtssdashboard'),
            'notifyinfo'
        );
        echo $OUTPUT->footer();
        exit;
    }

    if (empty($students)) {
        echo $OUTPUT->notification(get_string('nostudentdata', 'mod_mtssdashboard'), 'notifywarning');
        echo $OUTPUT->footer();
        exit;
    }

    $ssids      = array_column($students, 'ssid');
    // History needs ALL years not just most recent — pass a dummy year filter
    // by fetching all records without the most-recent-only subquery.
    $allresults = stv_get_all_results($ssids, $year, $fel);

    // Filter to previous level only.
    $results = mtssdashboard_filter_by_grade_range(
        $allresults,
        $students,
        $prevmin,
        $prevmax,
        $levelcfg->gradyear
    );

    echo $OUTPUT->heading($prevheading . ' — ' . $year, 4);

    if (empty($results)) {
        echo $OUTPUT->notification(get_string('history_no_data', 'mod_mtssdashboard'), 'notifyinfo');
        echo $OUTPUT->footer();
        exit;
    }

    $profcounts = stv_prof_counts($results);
    $bysubject  = stv_by_subject($results);
    $alljs      = '';

    // Overall proficiency doughnut + totals table.
    echo html_writer::start_div('row mb-4 align-items-center');
    echo html_writer::start_div('col-md-6');
    echo html_writer::tag(
        'h5',
        get_string('chart_proficiency', 'mod_mtssdashboard'),
        ['class' => 'text-center']
    );
    echo html_writer::tag('canvas', '', ['id' => 'chartProfDistHist', 'style' => 'max-height:320px;']);
    echo html_writer::end_div();

    // Totals table.
    echo html_writer::start_div('col-md-5');
    $studentcount = count(array_unique(array_column($students, 'ssid')));
    echo html_writer::tag(
        'h5',
        get_string('prof_totals', 'mod_mtssdashboard'),
        ['class' => 'text-center']
    );
    echo html_writer::tag('p', get_string('prof_totalstudents', 'mod_mtssdashboard') .
        ': ' . $studentcount, ['class' => 'text-center text-muted small mb-1']);
    $table = new html_table();
    $table->head = [
        get_string('th_proficiency', 'mod_mtssdashboard'),
        get_string('prof_testrecords', 'mod_mtssdashboard'),
        get_string('prof_pct', 'mod_mtssdashboard'),
    ];
    $table->attributes['class'] = 'generaltable table-sm table-bordered text-center';
    $total = array_sum($profcounts);

    // History tab uses teal color scheme to distinguish from State Testing tab.
    $histcolors = [
        'Advanced'     => '#0d6e5c',
        'Accomplished' => '#1a8a8a',
        'Proficient'   => '#2e9e6e',
        'Basic'        => '#b8860b',
        'Limited'      => '#8b0000',
        'Not Reported' => '#5a5a5a',
    ];

    foreach ($profcounts as $level => $cnt) {
        $pct    = $total > 0 ? round(($cnt / $total) * 100, 1) : 0;
        $bgcolor = $histcolors[$level] ?? '#5a5a5a';
        $cell1  = new html_table_cell($level);
        $cell1->attributes['style'] = "background:{$bgcolor};color:#fff;font-weight:bold;";
        $table->data[] = new html_table_row([
            $cell1,
            new html_table_cell($cnt),
            new html_table_cell($pct . '%'),
        ]);
    }
    $cell1 = new html_table_cell(get_string('prof_total', 'mod_mtssdashboard'));
    $cell1->attributes['style'] = 'font-weight:bold;';
    $table->data[] = new html_table_row([$cell1,
        new html_table_cell($total), new html_table_cell('100%')]);
    echo html_writer::table($table);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Overall doughnut JS.
    $hcolors = json_encode(array_map(
        fn($k) => $histcolors[$k] ?? '#5a5a5a',
        array_keys($profcounts)
    ));
    $hlabels = json_encode(array_keys($profcounts));
    $hdata   = json_encode(array_values($profcounts));
    $proftotalhist = array_sum($profcounts);
    $alljs  .= <<<JS
(function(){
    var ctx = document.getElementById('chartProfDistHist').getContext('2d');
    var datalabelsPluginH = {
        id: 'datalabels_chartProfDistHist',
        afterDatasetsDraw: function(chart) {
            var ctx2 = chart.ctx;
            chart.data.datasets.forEach(function(dataset, i) {
                var meta = chart.getDatasetMeta(i);
                meta.data.forEach(function(arc, index) {
                    var value = dataset.data[index];
                    var total = dataset.data.reduce(function(a,b){return a+b;},0);
                    var pct = Math.round(value/total*100);
                    if (pct < 5) return;
                    var pos = arc.tooltipPosition();
                    ctx2.save();
                    ctx2.fillStyle = '#fff';
                    ctx2.font = 'bold 14px sans-serif';
                    ctx2.textAlign = 'center';
                    ctx2.textBaseline = 'middle';
                    ctx2.fillText(value, pos.x, pos.y);
                    ctx2.restore();
                });
            });
        }
    };
    var centerPluginH = {
        id: 'cp_chartProfDistHist',
        afterDraw: function(chart) {
            var ca = chart.chartArea;
            var cx = (ca.left + ca.right) / 2;
            var cy = (ca.top + ca.bottom) / 2;
            var c = chart.ctx;
            c.restore();
            c.font = 'bold ' + Math.round((ca.bottom - ca.top) / 5) + 'px sans-serif';
            c.textBaseline = 'middle';
            c.textAlign = 'center';
            c.fillStyle = '#333';
            c.fillText('{$proftotalhist}', cx, cy);
            c.save();
        }
    };
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: {$hlabels},
            datasets: [{ data: {$hdata}, backgroundColor: {$hcolors} }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'right',
                    labels: {
                        boxWidth: 14,
                        padding: 10,
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                            var pct = Math.round(ctx.parsed/total*100);
                            return ctx.label+': '+ctx.parsed+' ('+pct+'%)';
                        }
                    }
                }
            }
        },
        plugins: [centerPluginH, datalabelsPluginH]
    });
})();
JS;

    // Per-subject doughnuts.
    echo $OUTPUT->heading(get_string('prof_bysubject', 'mod_mtssdashboard'), 4);
    echo html_writer::start_div('row');
    $totalsubjects = count($bysubject);
    $si = 0;
    foreach ($bysubject as $subjdesc => $subjdata) {
        $subjectresults = array_filter(
            $results,
            fn($r) => $r->subject_description === $subjdesc
        );
        $canvasid = 'hist_subj_' . $si;
        $chart = stv_subject_chart($canvasid, $subjdesc, $subjectresults, $histcolors, $totalsubjects);
        echo $chart['html'];
        $alljs .= $chart['js'];
        $si++;
        $perrow = ($totalsubjects <= 2) ? 2 : 3;
        if ($si % $perrow === 0) {
            echo html_writer::end_div();
            echo html_writer::start_div('row');
        }
    }
    echo html_writer::end_div();

    // Show data in history Tab for previous school.
    echo stv_render_table($results, $students);
    echo '<script>' . $alljs . '</script>';
}

// ----------------------------------------------------------------------
// TESTING HISTORY TAB.
// Shows year-over-year cohort line charts — one chart per subject family.
// X-axis  = test stops in grade-level progression order (oldest → newest).
// Y-axis  = average proficiency (1=Limited … 5=Advanced) for the cohort.
// Lines   = one per graduation cohort (derived from first 2 chars of username).
if ($tab === 'subjecthistory') {
    echo $OUTPUT->heading(get_string('tab_subjecthistory', 'mod_mtssdashboard'), 3);

    // Proficiency → numeric map.
    $proftonum = [
        'Limited'      => 1,
        'Basic'        => 2,
        'Proficient'   => 3,
        'Accomplished' => 4,
        'Advanced'     => 5,
    ];
    $numtoprof = array_flip($proftonum);

    // Subject family definitions.
    // Each family defines an ordered list of x-axis "stops".
    // A stop is matched by (subject_code IN codes) AND (grade_level = grade_level_value).
    // 'hs' grade_level means the grade_level column contains 'High School'.
    // Order within each family = left-to-right on the chart (oldest → newest).

    $families = [
        'ela' => [
            'title' => 'English Language Arts',
            'color_offset' => 0,
            'stops' => [
                ['label' => 'ELA 3rd', 'codes' => ['R', 'ELA'], 'grade' => 'Third Grade'],
                ['label' => 'ELA 4th', 'codes' => ['R', 'ELA'], 'grade' => 'Fourth Grade'],
                ['label' => 'ELA 5th', 'codes' => ['R', 'ELA'], 'grade' => 'Fifth Grade'],
                ['label' => 'ELA 6th', 'codes' => ['R', 'ELA'], 'grade' => 'Sixth Grade'],
                ['label' => 'ELA 7th', 'codes' => ['R', 'ELA'], 'grade' => 'Seventh Grade'],
                ['label' => 'ELA 8th', 'codes' => ['R', 'ELA'], 'grade' => 'Eighth Grade'],
                ['label' => 'ELA II', 'codes' => ['R', 'ELA2'], 'grade' => 'High School'],
            ],
        ],
        'math' => [
            'title' => 'Mathematics',
            'color_offset' => 0,
            'stops' => [
                ['label' => 'Math 3rd', 'codes' => ['M'], 'grade' => 'Third Grade'],
                ['label' => 'Math 4th', 'codes' => ['M'], 'grade' => 'Fourth Grade'],
                ['label' => 'Math 5th', 'codes' => ['M'], 'grade' => 'Fifth Grade'],
                ['label' => 'Math 6th', 'codes' => ['M'], 'grade' => 'Sixth Grade'],
                ['label' => 'Math 7th', 'codes' => ['M'], 'grade' => 'Seventh Grade'],
                ['label' => 'Math 8th', 'codes' => ['M'], 'grade' => 'Eighth Grade'],
                ['label' => 'Math I', 'codes' => ['MTH1'], 'grade' => 'High School'],
                ['label' => 'Algebra I', 'codes' => ['ALG1'], 'grade' => 'High School'],
                ['label' => 'Geometry', 'codes' => ['GEOM', 'M'], 'grade' => 'High School'],
            ],
        ],
        'science' => [
            'title' => 'Science',
            'color_offset' => 0,
            'stops' => [
                ['label' => 'Science 5th', 'codes' => ['S'], 'grade' => 'Fifth Grade'],
                ['label' => 'Science 8th', 'codes' => ['S'], 'grade' => 'Eighth Grade'],
                ['label' => 'Biology', 'codes' => ['S', 'BIOL'], 'grade' => 'High School'],
            ],
        ],
        'ss' => [
            'title' => 'Social Studies',
            'color_offset' => 0,
            'stops' => [
                ['label' => 'US History', 'codes' => ['C', 'HIST'], 'grade' => 'High School'],
                ['label' => 'Government', 'codes' => ['GOVM'], 'grade' => 'High School'],
            ],
        ],
    ];

    // Apply group + demographic filters.
    // $tabstudents already has the Moodle group filter applied (from the shared
    // tab routing block above). Apply demographic filters on top of that.
    $thbasestu = stv_filter_students(
        $tabstudents,
        $fgender,
        $frace,
        $fed,
        $fswd,
        $fel,
        $f504,
        $fiep,
        $fgifted
    );

    // Fetch all enrolled students WITH username (need it for grad year).
    // $allstudents from lib.php does not include username — re-query here.
    $thstudents = [];
    if (!empty($thbasestu)) {
        $userids = array_column($thbasestu, 'userid');
        [$uidsql, $uidparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $userrows = $DB->get_records_sql(
            "SELECT id, username FROM {user} WHERE id $uidsql",
            $uidparams
        );
        // Merge username back into student objects.
        foreach ($thbasestu as $s) {
            $clone = clone $s;
            $clone->username = isset($userrows[$s->userid])
                ? $userrows[$s->userid]->username : '';
            $thstudents[] = $clone;
        }
    }

    if (empty($thstudents)) {
        echo $OUTPUT->notification(
            get_string('nostudentlink', 'mod_mtssdashboard'),
            'notifywarning'
        );
        echo $OUTPUT->footer();
        exit;
    }

    // Derive graduation year from username.
    // Username starts with 2-digit grad year: "26bpool" → 2026.
    // Assumption: all years are 20xx (valid for 2021-2029 range in data).
    foreach ($thstudents as $s) {
        $prefix = intval(substr($s->username, 0, 2));
        $s->gradyear = 2000 + $prefix;
    }

    // Collect all SSIDs and fetch ALL their test records.
    $thssids = array_column($thstudents, 'ssid');
    $thssids = array_filter($thssids); // Remove empty.
    $thssids = array_values($thssids);

    if (empty($thssids)) {
        echo $OUTPUT->notification(
            get_string('nostudentlink', 'mod_mtssdashboard'),
            'notifywarning'
        );
        echo $OUTPUT->footer();
        exit;
    }

    [$thinsql, $thparams] = $DB->get_in_or_equal($thssids, SQL_PARAMS_NAMED, 'thssid');
    $statetable = $CFG->prefix . 'NTstatetesting';
    $thallresults = $DB->get_records_sql(
        "SELECT *
           FROM {$statetable}
          WHERE ssid $thinsql
            AND proficiency_level IS NOT NULL
            AND proficiency_level != ''
            AND proficiency_level != 'Untested'
          ORDER BY ssid ASC, year ASC",
        $thparams
    );

    if (empty($thallresults)) {
        echo $OUTPUT->notification(
            get_string('chart_nodata', 'mod_mtssdashboard'),
            'notifyinfo'
        );
        echo $OUTPUT->footer();
        exit;
    }

    // Build ssid → gradyear map.
    $ssidtogradyear = [];
    foreach ($thstudents as $s) {
        if (!empty($s->ssid)) {
            $ssidtogradyear[$s->ssid] = $s->gradyear;
        }
    }

    // Collect all distinct grad years present in our student set.
    $allgradyears = array_unique(array_values($ssidtogradyear));
    sort($allgradyears);

    // Cohort line colours (one per grad year, cycling).
    $cohortcolors = [
        '#4e73df', '#1cc88a', '#e74a3b', '#f6c23e',
        '#36b9cc', '#fd7e14', '#6f42c1', '#20c997',
    ];

    // Helper: match a result row to a stop definition.
    // Returns true if the result's subject_code is in stop codes AND
    // grade_level matches (case-insensitive).
    $matchstop = function ($r, $stop) {
        if (!in_array($r->subject_code, $stop['codes'], true)) {
            return false;
        }
        return strcasecmp(trim($r->grade_level), trim($stop['grade'])) === 0;
    };

    // Build chart data for each family.
    // For each family → each stop → each grad year → collect numeric prof scores
    // → average them → that is the data point.

    $alljs  = '';
    $chartn = 0;

    foreach ($families as $famkey => $family) {
        $stops      = $family['stops'];

        // Find the last stop index that has data for any cohort,
        // then show one stop beyond it as a peek ahead. Trim the rest.
        $laststopwithdata = -1;
        foreach ($allgradyears as $gradyear) {
            foreach ($stops as $si => $stop) {
                foreach ($thallresults as $r) {
                    $gy = $ssidtogradyear[$r->ssid] ?? null;
                    if ($gy !== $gradyear) {
                        continue;
                    }
                    if (!$matchstop($r, $stop)) {
                        continue;
                    }
                    if ($si > $laststopwithdata) {
                        $laststopwithdata = $si;
                    }
                }
            }
        }
        // Show one stop beyond the last data point (peek ahead), but no further.
        $maxstopindex = min($laststopwithdata + 1, count($stops) - 1);
        if ($laststopwithdata >= 0) {
            $stops = array_slice($stops, 0, $maxstopindex + 1);
        }

        $stoplabels = array_column($stops, 'label');

        // Datasets: one per grad year.
        $datasets = [];
        $hasanydata = false;

        foreach ($allgradyears as $gi => $gradyear) {
            // Collect data points for each stop.
            $datapoints  = [];
            $pointcounts = [];
            foreach ($stops as $stop) {
                $scores = [];
                foreach ($thallresults as $r) {
                    // Must belong to this grad year's cohort.
                    $gy = $ssidtogradyear[$r->ssid] ?? null;
                    if ($gy !== $gradyear) {
                        continue;
                    }
                    if (!$matchstop($r, $stop)) {
                        continue;
                    }
                    $num = $proftonum[trim($r->proficiency_level)] ?? null;
                    if ($num !== null) {
                        $scores[] = $num;
                    }
                }
                // Null = no data for this cohort at this stop (Chart.js spanGaps).
                $datapoints[]  = !empty($scores)
                    ? round(array_sum($scores) / count($scores), 2)
                    : null;
                $pointcounts[] = count($scores);
            }

            // Only add dataset if at least one stop has data.
            $haspoint = !empty(array_filter($datapoints, fn($v) => $v !== null));
            if (!$haspoint) {
                continue;
            }
            $hasanydata = true;

            $color = $cohortcolors[$gi % count($cohortcolors)];
            $datasets[] = [
                'label'           => 'Class of ' . $gradyear,
                'data'            => $datapoints,
                'pointCounts'     => $pointcounts,
                'borderColor'     => $color,
                'backgroundColor' => $color . '33',
                'pointBackgroundColor' => $color,
                'pointRadius'     => 6,
                'pointHoverRadius' => 8,
                'tension'         => 0.3,
                'fill'            => false,
                'spanGaps'        => true,
            ];
        }

        // Render chart HTML.
        $canvasid = 'th_chart_' . $famkey;
        echo html_writer::start_div('card mb-4');
        echo html_writer::start_div('card-header');
        echo html_writer::tag('h5', $family['title'], ['class' => 'mb-0']);
        echo html_writer::end_div();
        echo html_writer::start_div('card-body');

        if (!$hasanydata) {
            echo $OUTPUT->notification(
                get_string('chart_nodata', 'mod_mtssdashboard'),
                'notifyinfo'
            );
            echo html_writer::end_div();
            echo html_writer::end_div();
            continue;
        }

        echo html_writer::start_div('', ['style' => 'position:relative;height:60vh;min-height:320px;max-height:500px;']);
        echo html_writer::tag('canvas', '', ['id' => $canvasid]);
        echo html_writer::end_div();
        echo html_writer::end_div(); // Card-body.
        echo html_writer::end_div(); // Card.

        // Build Chart.js JS.
        $jslabels   = json_encode($stoplabels);
        $jsdatasets = json_encode($datasets);

        // Y-axis tick callback: map 1-5 back to proficiency label.
        // Passed as a literal JS function — cannot JSON-encode, build as string.
        $alljs .= <<<JS
(function(){
    var ctx = document.getElementById('{$canvasid}').getContext('2d');
    window.stvProfLabels = window.stvProfLabels || {
        1: 'Limited',
        2: 'Basic',
        3: 'Proficient',
        4: 'Accomplished',
        5: 'Advanced'
    };
    var profLabels = window.stvProfLabels;
    var countPlugin = {
        id: 'pointcountlabels',
        afterDatasetsDraw: function(chart) {
            var ctx2 = chart.ctx;
            chart.data.datasets.forEach(function(ds, i) {
                var meta = chart.getDatasetMeta(i);
                if (meta.hidden) return;
                meta.data.forEach(function(point, j) {
                    var cnt = ds.pointCounts ? ds.pointCounts[j] : 0;
                    if (!cnt) return;
                    ctx2.save();
                    ctx2.font = '9px sans-serif';
                    ctx2.fillStyle = 'rgba(80,80,80,0.7)';
                    ctx2.textAlign = 'center';
                    ctx2.fillText(cnt, point.x, point.y - 10);
                    ctx2.restore();
                });
            });
        }
    };
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: {$jslabels},
            datasets: {$jsdatasets}
        },
        plugins: [countPlugin],
        options: {
            responsive: true,
            spanGaps: true,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var val = ctx.parsed.y;
                            var lbl = profLabels[Math.round(val)] || val;
                            return ctx.dataset.label + ': ' + lbl +
                                   ' (' + val.toFixed(2) + ')';
                        }
                    }
                }
            },
            scales: {
                y: {
                    min: 0.5,
                    max: 5.5,
                    ticks: {
                        stepSize: 1,
                        font: { size: 11 },
                        callback: function(val) {
                            var labels = ['','Limited','Basic','Proficient','Accomplished','Advanced'];
                            return labels[Math.round(val)] || '';
                        }
                    },
                    title: { display: false }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Assessment'
                    }
                }
            }
        }
    });
})();
JS;
        $chartn++;
    }

    if ($chartn > 0) {
        echo stv_render_table(array_values($thallresults), $thstudents);
        echo '<script>' . $alljs . '</script>';
    }
}

// ----------------------------------------------------------------------
// STUDENT VIEW.
if ($tab === 'student') {
    if (empty($studuserid)) {
        echo $OUTPUT->notification(get_string('selectstudent', 'mod_mtssdashboard'), 'notifyinfo');
        echo $OUTPUT->footer();
        exit;
    }

    // Find student.
    $student = null;
    foreach ($allstudents as $s) {
        if ($s->userid == $studuserid) {
            $student = $s;
            break;
        }
    }

    if (!$student) {
        echo $OUTPUT->notification(get_string('nostudentdata', 'mod_mtssdashboard'), 'notifywarning');
        echo $OUTPUT->footer();
        exit;
    }

    // All years for trend (ignore year filter, and DO NOT collapse to most-recent
    // per subject — we want every test to appear as its own dot on the chart).
    $statetable = $CFG->prefix . 'NTstatetesting';
    $allresults = $DB->get_records_sql(
        "SELECT *
           FROM {$statetable}
          WHERE ssid = :ssid
            AND proficiency_level IS NOT NULL
            AND proficiency_level != ''
            AND proficiency_level != 'Untested'
          ORDER BY year ASC, subject_code ASC",
        ['ssid' => $student->ssid]
    );
    // Filtered results for table.
    $results    = stv_get_results([$student->ssid], $year, $fel);

    echo $OUTPUT->heading(
        $student->firstname . ' ' . $student->lastname . ' — ' .
        get_string('tab_student', 'mod_mtssdashboard'),
        3
    );

    // Student info card.
    // 504/IEP link to the same student plan PDF used by the Early Warning
    // tab's Student ID column. WEP/RIMP link to their own print views.
    $plansdir = $CFG->dataroot . '/NTimports/plans/';
    $planfile = $plansdir . $student->idnumber . '.pdf';
    $hasplan  = ($student->idnumber > 0 && file_exists($planfile) && is_readable($planfile));
    $planurl  = new moodle_url(
        '/mod/mtssdashboard/plan.php',
        ['cmid' => $cm->id, 'ssid' => $student->idnumber]
    );

    $rimpmap = stv_get_rimp_by_studentnumber([$student->idnumber]);
    $hasrimp = isset($rimpmap[(int) $student->idnumber]);
    $rimpurl = new moodle_url(
        '/mod/mtssdashboard/rimp_print.php',
        ['id' => $cm->id, 'sn' => $student->idnumber]
    );

    $hasweprecord = $DB->get_record_select(
        'mtssdashboard_wep',
        'studentnumber = :sn',
        ['sn' => $student->idnumber],
        'id'
    );
    $haswep  = !empty($hasweprecord);
    $wepurl  = new moodle_url(
        '/mod/mtssdashboard/wep_print.php',
        ['id' => $cm->id, 'sn' => $student->idnumber]
    );

    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-body py-2');
    echo html_writer::start_div('row row-cols-auto g-3');
    $badges = [
        'Grade'  => ['text' => $student->gradelevel],
        'Gender' => ['text' => $student->gender],
        '504'    => $student->status504 === 'Y'
            ? ['text' => '✔ Yes', 'link' => $hasplan ? $planurl : null]
            : ['text' => 'No'],
        'IEP'    => $student->iep === 'Y'
            ? ['text' => '✔ Yes', 'link' => $hasplan ? $planurl : null]
            : ['text' => 'No'],
        'WEP'    => $haswep
            ? ['text' => '✔ Yes', 'link' => $wepurl]
            : ['text' => 'No'],
        'RIMP'   => $hasrimp
            ? ['text' => '✔ Yes', 'link' => $rimpurl]
            : ['text' => 'No'],
        'Gifted' => ['text' => $student->gifted === 'Y' ? '✔ Yes' : 'No'],
    ];
    foreach ($badges as $lbl => $info) {
        echo html_writer::start_div('col');
        echo html_writer::tag('div', $lbl, ['class' => 'small text-muted']);
        if (!empty($info['link'])) {
            echo html_writer::link(
                $info['link']->out(false),
                $info['text'],
                ['class' => 'fw-bold', 'target' => '_blank',
                'title' => get_string(
                    'plan_view',
                    'mod_mtssdashboard'
                )]
            );
        } else {
            echo html_writer::tag('div', $info['text'], ['class' => 'fw-bold']);
        }
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Contact info + Classes & Grades (two-column section).
    $contactstable = $CFG->prefix . 'contacts';
    $contactinfo = $DB->get_record_sql(
        "SELECT Address AS address, Address2 AS address2, City AS city,
                State AS state, Zip AS zip, ParentName AS parentname,
                HomePhone AS homephone, Email AS email,
                Locker AS locker, Combination AS combination
           FROM {$contactstable}
          WHERE StudentNumber = :sn",
        ['sn' => $student->idnumber]
    );

    // Student picture — same pluginfile pattern used by rimp_print.php / wep_print.php.
    $picuser = $DB->get_record('user', ['id' => $student->userid], 'id, picture');
    $picurl = $CFG->wwwroot . '/pix/u/f1.png';
    if (!empty($picuser) && !empty($picuser->picture)) {
        $usercontext = context_user::instance($picuser->id);
        $picurl = $CFG->wwwroot . '/pluginfile.php/' . $usercontext->id
            . '/user/icon/boost/f1?rev=' . $picuser->picture;
    }

    // Build address display (Address, Address2, City State Zip — each on its own line).
    // Trim first so a whitespace-only DB value doesn't produce a blank line.
    $addressparts = [];
    $addr1 = trim((string) ($contactinfo->address ?? ''));
    if ($addr1 !== '') {
        $addressparts[] = $addr1;
    }
    $addr2 = trim((string) ($contactinfo->address2 ?? ''));
    if ($addr2 !== '') {
        $addressparts[] = $addr2;
    }
    $citystatezip = trim(($contactinfo->city ?? '') .
        (!empty($contactinfo->city) ? ', ' : '') .
        trim(($contactinfo->state ?? '') . ' ' . ($contactinfo->zip ?? '')));
    if ($citystatezip !== '') {
        $addressparts[] = $citystatezip;
    }
    $addressdisplay = !empty($addressparts)
        ? implode('<br>', array_map('s', $addressparts))
        : '—';

    $coursegrades = stv_get_student_course_grades($student->userid, $courselevel);

    $studentgradedigit = mtssdashboard_grade_to_int($student->gradelevel ?? '');
    $studentiselementary = ($studentgradedigit !== null && $studentgradedigit <= 4);

    echo html_writer::start_div('row mb-3');

    // Left column: contact information.
    echo html_writer::start_div('col-md-5 mb-3 mb-md-0');
    echo html_writer::start_div('card h-100');
    echo html_writer::start_div('card-body');
    echo html_writer::tag(
        'h5',
        get_string('studentdata_contact_heading', 'mod_mtssdashboard'),
        ['class' => 'card-title']
    );

    echo html_writer::start_div('d-flex align-items-start gap-3');
    echo html_writer::empty_tag('img', [
        'src'   => $picurl,
        'alt'   => '',
        'style' => 'width:64px;height:64px;border-radius:50%;object-fit:cover;flex-shrink:0;',
    ]);
    echo html_writer::start_div('flex-grow-1', ['style' => 'margin-left:0.75rem;']);

    $emaildisplay = '—';
    if (!empty(trim((string) $contactinfo->email))) {
        $emailclean   = trim($contactinfo->email);
        $emaildisplay = html_writer::link('mailto:' . $emailclean, s($emailclean));
    }

    $contactfields = [
        get_string('studentdata_address', 'mod_mtssdashboard')    => $addressdisplay,
        get_string('studentdata_parentname', 'mod_mtssdashboard') =>
            !empty($contactinfo->parentname) ? s($contactinfo->parentname) : '—',
        get_string('studentdata_homephone', 'mod_mtssdashboard') =>
            !empty($contactinfo->homephone) ? s($contactinfo->homephone) : '—',
        get_string('studentdata_email', 'mod_mtssdashboard') => $emaildisplay,
        '' => get_string('studentdata_lockercombo', 'mod_mtssdashboard', (object) [
            'locker' => !empty(trim((string) $contactinfo->locker)) ? s($contactinfo->locker) : '—',
            'combination' => !empty(trim((string) $contactinfo->combination)) ? s($contactinfo->combination) : '—',
        ]),
    ];
    // Render labels as real <th> cells in a table using the same classes as
    // the Classes & Grades table, so the header font/weight/color come from
    // the identical CSS rule rather than an approximated utility class.
    $ct = new html_table();
    $ct->attributes['class'] = 'generaltable table-sm';
    $ct->attributes['style'] = 'width:100%;';
    foreach ($contactfields as $flabel => $fval) {
        if ($flabel !== '') {
            $labelcell = new html_table_cell($flabel);
            $labelcell->header = true;
            $labelcell->attributes['style'] = 'border-top:none; padding-bottom:0;';
            $ct->data[] = new html_table_row([$labelcell]);
        }
        $valuecell = new html_table_cell($fval);
        $valuecell->attributes['style'] = 'border-top:none; padding-top:0;';
        $ct->data[] = new html_table_row([$valuecell]);
    }
    echo html_writer::table($ct);
    echo html_writer::end_div(); // Flex-grow-1.
    echo html_writer::end_div(); // D-flex.
    echo html_writer::end_div(); // Card-body.
    echo html_writer::end_div(); // Card.
    echo html_writer::end_div(); // Col-md-5.

    // Right column: classes & grades.
    echo html_writer::start_div('col-md-7');
    echo html_writer::start_div('card h-100');
    echo html_writer::start_div('card-body');
    echo html_writer::tag(
        'h5',
        get_string('studentdata_classes_heading', 'mod_mtssdashboard'),
        ['class' => 'card-title']
    );

    if ($studentiselementary) {
        $gradecardurl = new moodle_url('/blocks/parentgrades/viewgrades.php', ['userid' => $student->userid]);
        echo html_writer::div(
            html_writer::link(
                $gradecardurl->out(false),
                get_string('viewelementarygradecard', 'block_parentgrades'),
                ['class' => 'btn btn-secondary btn-sm mb-2', 'target' => '_blank']
            ),
            'mb-2'
        );
    }

    if (empty($coursegrades)) {
        echo html_writer::tag(
            'div',
            get_string('studentdata_nogrades', 'mod_mtssdashboard'),
            ['class' => 'text-muted small']
        );
    } else {
        $gt = new html_table();
        $gt->head = [
            get_string('studentdata_col_course', 'mod_mtssdashboard'),
            get_string('studentdata_col_teacher', 'mod_mtssdashboard'),
            get_string('studentdata_col_grade', 'mod_mtssdashboard'),
        ];
        $gt->attributes['class'] = 'generaltable table-sm';
        foreach ($coursegrades as $cg) {
            $singleviewurl = new moodle_url('/grade/report/singleview/index.php', [
                'id'     => $cg->courseid,
                'item'   => 'user',
                'itemid' => $student->userid,
            ]);
            $coursenamelink = html_writer::link(
                $singleviewurl->out(false),
                s($cg->coursename),
                ['target' => '_blank']
            );
            $gt->data[] = new html_table_row([
                new html_table_cell($coursenamelink),
                new html_table_cell(s($cg->teacher)),
                new html_table_cell($cg->pct . '% (' . s($cg->letter) . ')'),
            ]);
        }
        echo html_writer::table($gt);
    }
    echo html_writer::end_div(); // Card-body.
    echo html_writer::end_div(); // Card.
    echo html_writer::end_div(); // Col-md-7.

    echo html_writer::end_div(); // Row.

    if (!mtssdashboard_render_student_charts($student->ssid)) {
        echo $OUTPUT->footer();
        exit;
    }

    // Collapsed data table.
    if (!empty($results)) {
        echo stv_render_table($results, [$student]);
    }

    if (false) {
        // Proficiency-band cutoffs (approximate Ohio breakpoints).
        // Used to position dots within their band on the trend chart so two
        // students at the same proficiency level but different scores show at
        // visually distinguishable heights. The exact ranges vary by subject and
        // grade in Ohio's system; these approximations are good enough for an
        // at-a-glance visual.
        $bandranges = [
        'Limited'      => ['min' => 640, 'max' => 680],
        'Basic'        => ['min' => 680, 'max' => 700],
        'Proficient'   => ['min' => 700, 'max' => 725],
        'Accomplished' => ['min' => 725, 'max' => 745],
        'Advanced'     => ['min' => 745, 'max' => 805],
        ];

        // Maps proficiency_level to its 1-5 base Y value (band floor).
        $proftoyfloor = [
        'Limited'      => 1.0,
        'Basic'        => 2.0,
        'Proficient'   => 3.0,
        'Accomplished' => 4.0,
        'Advanced'     => 5.0,
        ];

        // Build trend datasets: one line per subject AREA (4 lines total).
        // X-axis = unique years (sorted). Y-axis = proficiency level (1-5).
        // Each dot's Y is fine-tuned within its band by where the score falls
        // between the band's min and max in $cutoffs.
        $codetoarea = [
        'R'    => 'ela', 'ELA'  => 'ela', 'ELA2' => 'ela',
        'M'    => 'math', 'MTH1' => 'math', 'ALG1' => 'math', 'GEOM' => 'math',
        'S'    => 'sci', 'BIOL' => 'sci',
        'C'    => 'ss', 'HIST' => 'ss', 'GOVM' => 'ss',
        ];
        $arealabels = [
        'ela'  => 'English Language Arts',
        'math' => 'Mathematics',
        'sci'  => 'Science',
        'ss'   => 'Social Studies',
        ];
        $areacolors = [
        'ela'  => '#4e73df', // Blue.
        'math' => '#1cc88a', // Green.
        'sci'  => '#f6c23e', // Yellow.
        'ss'   => '#e74a3b', // Red.
        ];

        // X-axis: list of unique years the student has any test in, chronological.
        $yearset = [];
        foreach ($allresults as $r) {
            $yearset[$r->year] = true;
        }
        ksort($yearset);
        $assessmentlabels = array_keys($yearset);

        // Compute (year-index, Y, score) for every test, grouped by subject area.
        // Using {x: yearindex, y: Y} object form lets two tests share a year.
        $areapoints = [];
        foreach ($arealabels as $areakey => $unused) {
            $areapoints[$areakey] = [];
        }
        foreach ($allresults as $r) {
            $area = $codetoarea[$r->subject_code] ?? null;
            if ($area === null) {
                continue;
            }
            $yearidx = array_search($r->year, $assessmentlabels, true);
            if ($yearidx === false) {
                continue;
            }
            $pl = $r->proficiency_level ?? null;
            $yfloor = $proftoyfloor[$pl] ?? null;
            if ($yfloor === null) {
                continue; // Unknown proficiency level — skip.
            }
            // Fine-tune Y within its band based on score position between
            // approximate Ohio breakpoints, so dots within the same band spread
            // vertically by score.
            $y = $yfloor;
            $band = $bandranges[$pl] ?? null;
            if ($band !== null && $band['max'] > $band['min']) {
                $score = (float) $r->assessment_score;
                $frac = ($score - $band['min']) / ($band['max'] - $band['min']);
                // Clamp to [0, 0.99] so dots stay inside their band visually.
                $frac = max(0.0, min(0.99, $frac));
                $y = $yfloor + $frac;
            }
            $areapoints[$area][] = [
            'x'        => (int) $yearidx,
            'y'        => round($y, 3),
            'score'    => (int) $r->assessment_score,
            'profname' => $pl,
            'subject'  => $r->subject_description,
            ];
        }

        // Sort each area's points by year-index so the line draws left-to-right.
        foreach ($areapoints as &$pts) {
            usort($pts, function ($a, $b) {
                return $a['x'] - $b['x'];
            });
        }
        unset($pts);

        // Build one Chart.js dataset per area that actually has any points.
        $trendsets = [];
        foreach ($arealabels as $areakey => $arealabel) {
            if (empty($areapoints[$areakey])) {
                continue;
            }
            $color = $areacolors[$areakey];
            $trendsets[] = [
            'label'                => $arealabel,
            'data'                 => $areapoints[$areakey],
            'borderColor'          => $color,
            'backgroundColor'      => $color . '22',
            'pointBackgroundColor' => $color,
            'pointBorderColor'     => $color,
            'pointRadius'          => 7,
            'pointHoverRadius'     => 9,
            'tension'              => 0.3,
            'fill'                 => false,
            'spanGaps'             => true,
            ];
        }

        // Radar: one polygon per year, axes = subject AREA.
        // Each vertex value is the average proficiency-Y of that year/area's tests
        // (same 1-5 mapping used by the trend chart). When a year is missing data
        // for an area, the previous year's value is carried forward so the
        // polygon stays whole.
        $radaraxes  = ['ela', 'math', 'sci', 'ss'];
        $radaraxislabels = [
        'ela'  => 'English Language Arts',
        'math' => 'Mathematics',
        'sci'  => 'Science',
        'ss'   => 'Social Studies',
        ];

        // Collect Y values per (year, area). Same code_to_area, prof_to_yfloor,
        // bandranges defined earlier in this tab.
        $radaryearareay = [];
        foreach ($allresults as $r) {
            $area = $codetoarea[$r->subject_code] ?? null;
            if ($area === null) {
                continue;
            }
            $pl = $r->proficiency_level ?? null;
            $yfloor = $proftoyfloor[$pl] ?? null;
            if ($yfloor === null) {
                continue;
            }
            $y = $yfloor;
            $band = $bandranges[$pl] ?? null;
            if ($band !== null && $band['max'] > $band['min']) {
                $score = (float) $r->assessment_score;
                $frac = ($score - $band['min']) / ($band['max'] - $band['min']);
                $frac = max(0.0, min(0.99, $frac));
                $y = $yfloor + $frac;
            }
            $radaryearareay[$r->year][$area][] = $y;
        }

        // Sort years chronologically (we already have $assessmentlabels = sorted years).
        $radaryears = $assessmentlabels;

        // Build per-year polygons. Carry-forward fills missing-area vertices.
        $radaryearcolors = [
        '#4e73df', '#1cc88a', '#f6c23e', '#e74a3b',
        '#36b9cc', '#858796', '#fd7e14',
        ];
        $radardatasets = [];
        $carry = ['ela' => null, 'math' => null, 'sci' => null, 'ss' => null];
        $ci = 0;
        foreach ($radaryears as $yr) {
            $vertex = [];
            foreach ($radaraxes as $area) {
                if (
                    isset($radaryearareay[$yr][$area])
                    && !empty($radaryearareay[$yr][$area])
                ) {
                    $vals = $radaryearareay[$yr][$area];
                    $avg = array_sum($vals) / count($vals);
                    $carry[$area] = $avg;
                    $vertex[] = round($avg, 3);
                } else if ($carry[$area] !== null) {
                    $vertex[] = round($carry[$area], 3);
                } else {
                    // No prior value to carry forward; place at axis origin.
                    $vertex[] = 0;
                }
            }
            $color = $radaryearcolors[$ci % count($radaryearcolors)];
            $radardatasets[] = [
            'label'                => $yr,
            'data'                 => $vertex,
            'backgroundColor'      => $color . '22',
            'borderColor'          => $color,
            'pointBackgroundColor' => $color,
            'pointRadius'          => 4,
            'fill'                 => true,
            ];
            $ci++;
        }

        // Variables consumed by the JS render block below.
        $radarlabelsarr = array_map(function ($a) use ($radaraxislabels) {
            return $radaraxislabels[$a];
        }, $radaraxes);

        // Charts.
        echo html_writer::start_div('row mb-3');

        // Trend line chart.
        echo html_writer::start_div('col-md-8');
        echo html_writer::tag(
            'h5',
            get_string('chart_trend', 'mod_mtssdashboard'),
            ['class' => 'text-center']
        );
        echo html_writer::start_div('', ['style' => 'position:relative; height:400px;']);
        echo html_writer::tag('canvas', '', ['id' => 'chartTrend']);
        echo html_writer::end_div();
        echo html_writer::end_div();

        // Radar chart.
        echo html_writer::start_div('col-md-4');
        echo html_writer::tag(
            'h5',
            get_string('chart_radar', 'mod_mtssdashboard'),
            ['class' => 'text-center']
        );
        echo html_writer::tag('canvas', '', ['id' => 'chartRadar']);
        echo html_writer::end_div();

        echo html_writer::end_div();

        // Proficiency colour legend was disabled; the old commented-out code is in git history.

        // Collapsed data table.
        if (!empty($results)) {
            echo stv_render_table($results, [$student]);
        }

        // Chart.js.
        $trendjson         = json_encode($trendsets);
        $yearlabels        = json_encode($assessmentlabels);
        $radarlabels       = json_encode($radarlabelsarr);
        $radardatasetsjson = json_encode($radardatasets);

        echo <<<JS
<script>
(function(){
    // Trend line chart.
    var ctx1 = document.getElementById('chartTrend').getContext('2d');
    var scorelabelPlugin = {
        id: 'studentscorelabels',
        afterDatasetsDraw: function(chart) {
            var ctx2 = chart.ctx;
            var chartRight = chart.chartArea.right;
            chart.data.datasets.forEach(function(ds, i) {
                var meta = chart.getDatasetMeta(i);
                if (meta.hidden) return;
                meta.data.forEach(function(point, j) {
                    var raw = ds.data[j];
                    if (!raw || raw.score === undefined) return;
                    ctx2.save();
                    ctx2.font = 'bold 11px sans-serif';
                    ctx2.fillStyle = '#333';
                    ctx2.textBaseline = 'middle';
                    var label = String(raw.score);
                    var labelWidth = ctx2.measureText(label).width;
                    // If label would overflow the right edge, draw it to the left of the dot.
                    if (point.x + 9 + labelWidth > chartRight) {
                        ctx2.textAlign = 'right';
                        ctx2.fillText(label, point.x - 9, point.y - 8);
                    } else {
                        ctx2.textAlign = 'left';
                        ctx2.fillText(label, point.x + 9, point.y - 8);
                    }
                    ctx2.restore();
                });
            });
        }
    };
    new Chart(ctx1, {
        type: 'line',
        data: { labels: {$yearlabels}, datasets: {$trendjson} },
        plugins: [scorelabelPlugin],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            spanGaps: true,
            parsing: false,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var d = ctx.raw || {};
                            return ctx.dataset.label + ' — ' +
                                   (d.subject || '') + ': ' +
                                   (d.profname || '') + ' (' + (d.score || ctx.parsed.y) + ')';
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'category',
                    title: { display: true, text: 'Year' }
                },
                y: {
                    min: 0.5,
                    max: 6.2,
                    ticks: {
                        stepSize: 1,
                        font: { size: 11 },
                        callback: function(val) {
                            var labels = ['','Limited','Basic','Proficient','Accomplished','Advanced'];
                            // Hide ticks above Advanced row (5).
                            var rounded = Math.round(val);
                            return labels[rounded] || '';
                        }
                    },
                    title: { display: false }
                }
            }
        }
    });

    // Radar chart — one polygon per year.
    var ctx2 = document.getElementById('chartRadar').getContext('2d');
    new Chart(ctx2, {
        type: 'radar',
        data: {
            labels: {$radarlabels},
            datasets: {$radardatasetsjson}
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var labels = ['','Limited','Basic','Proficient','Accomplished','Advanced'];
                            var lbl = labels[Math.round(ctx.parsed.r)] || '';
                            return ctx.dataset.label + ' — ' + ctx.label + ': ' + lbl;
                        }
                    }
                }
            },
            scales: {
                r: {
                    min: 0,
                    max: 6,
                    ticks: {
                        stepSize: 1,
                        backdropColor: 'transparent',
                        callback: function(val) {
                            var labels = ['','Limited','Basic','Proficient','Accomplished','Advanced'];
                            return labels[Math.round(val)] || '';
                        }
                    },
                    pointLabels: { font: { size: 11 } }
                }
            }
        }
    });
})();
</script>
JS;
    }
}

// ----------------------------------------------------------------------
// GRADES TAB.
if ($tab === 'grades') {
    $gradecolors = [
        'A'  => '#1a6e3c',
        'A-' => '#1a7a45',
        'B+' => '#2471a3',
        'B'  => '#2e86c1',
        'B-' => '#3498db',
        'C+' => '#148f77',
        'C'  => '#1a8a1a',
        'C-' => '#27ae60',
        'D+' => '#b7950b',
        'D'  => '#d4a017',
        'F'  => '#c0392b',
    ];
    $defaultcolor = '#adb5bd';

    // Apply demographic filters before fetching grade data.
    $gradestudents = stv_filter_students(
        $tabstudents,
        $fgender,
        $frace,
        $fed,
        $fswd,
        $fel,
        $f504,
        $fiep,
        $fgifted
    );

    $gradesdata = mtssdashboard_get_grades_data(
        $gradestudents,
        $course->id,
        $courselevel,
        $levelcfg
    );

    if (empty($gradesdata->scale)) {
        echo $OUTPUT->notification(get_string('grades_nodata', 'mod_mtssdashboard'), 'notifywarning');
        echo $OUTPUT->footer();
        exit;
    }

    if (empty($gradesdata->detail)) {
        echo $OUTPUT->notification(get_string('grades_nodata', 'mod_mtssdashboard'), 'notifyinfo');
        echo $OUTPUT->footer();
        exit;
    }

    $alljs = '';

    // Helper: build grade counts table HTML.
    $rendercountstable = function ($counts, $gradecolors, $defaultcolor, $scale) {
        $total = array_sum($counts);
        $t = new html_table();
        $t->head = ['Grade', 'Students', '%'];
        $t->attributes['class'] = 'generaltable table-sm table-bordered text-center mt-2';
        $t->attributes['style'] = 'font-size:0.85em;';
        foreach ($counts as $letter => $cnt) {
            $pct  = $total > 0 ? round(($cnt / $total) * 100, 1) : 0;
            $bg   = $gradecolors[$letter] ?? $defaultcolor;
            $cell = new html_table_cell($letter);
            $cell->attributes['style'] = "background:{$bg};color:#fff;font-weight:bold;";
            $t->data[] = new html_table_row([
                $cell,
                new html_table_cell($cnt),
                new html_table_cell($pct . '%'),
            ]);
        }
        $tc = new html_table_cell('Total');
        $tc->attributes['style'] = 'font-weight:bold;';
        $t->data[] = new html_table_row([$tc,
            new html_table_cell($total),
            new html_table_cell('100%')]);
        return html_writer::table($t);
    };

    // Helper: build legend HTML from scale.
    $renderlegend = function ($scale, $gradecolors, $defaultcolor) {
        $out = html_writer::start_div('d-flex flex-wrap gap-2 mt-2');
        foreach ($scale as $row) {
            $color = $gradecolors[$row->letter] ?? $defaultcolor;
            $out .= html_writer::tag(
                'span',
                html_writer::tag('span', '', [
                    'style' => "display:inline-block;width:13px;height:13px;" .
                               "background:{$color};border-radius:3px;margin-right:3px;",
                ]) . htmlspecialchars($row->letter),
                ['class' => 'badge text-dark border', 'style' => 'font-size:0.82em;']
            );
        }
        $out .= html_writer::end_div();
        return $out;
    };

    // Helper: render a doughnut canvas + JS only (no table).
    $renderdonutjs = function (
        $canvasid,
        $entries,
        $scale,
        $gradecolors,
        $defaultcolor,
        $withtable = false
    ) use (
        &$alljs,
        $rendercountstable
) {
        $counts  = mtssdashboard_count_letters($entries, $scale);
        if (empty($counts)) {
            return ['html' => '', 'counts' => []];
        }
        $total   = array_sum($counts);
        $labels  = json_encode(array_keys($counts));
        $data    = json_encode(array_values($counts));
        $colors  = json_encode(array_map(
            fn($l) => $gradecolors[$l] ?? $defaultcolor,
            array_keys($counts)
        ));
        $chartid = 'gc_' . preg_replace('/[^a-z0-9]/i', '_', $canvasid);

        $html = html_writer::tag(
            'canvas',
            '',
            ['id' => $chartid, 'style' => 'max-height:240px;']
        );

        if ($withtable) {
            $html .= $rendercountstable($counts, $gradecolors, $defaultcolor, $scale);
        }

        $alljs .= <<<JS
(function(){
    var ctx = document.getElementById('{$chartid}').getContext('2d');
    var datalabelsPlugin = {
        id: 'datalabels_{$chartid}',
        afterDatasetsDraw: function(chart) {
            var ctx2 = chart.ctx;
            chart.data.datasets.forEach(function(dataset, i) {
                var meta = chart.getDatasetMeta(i);
                meta.data.forEach(function(arc, index) {
                    var value = dataset.data[index];
                    var total = dataset.data.reduce(function(a,b){return a+b;},0);
                    var pct = Math.round(value/total*100);
                    if (pct < 5) return; // Skip tiny slices.
                    var pos = arc.tooltipPosition();
                    ctx2.save();
                    ctx2.fillStyle = '#fff';
                    ctx2.font = 'bold 12px sans-serif';
                    ctx2.textAlign = 'center';
                    ctx2.textBaseline = 'middle';
                    ctx2.fillText(value, pos.x, pos.y);
                    ctx2.restore();
                });
            });
        }
    };
    var centerPlugin = {
        id: 'cp_{$chartid}',
        afterDraw: function(chart) {
            var w = chart.width, h = chart.height, c = chart.ctx;
            c.restore();
            c.font = 'bold ' + Math.round(h/6) + 'px sans-serif';
            c.textBaseline = 'middle';
            c.fillStyle = '#333';
            var txt = '{$total}';
            c.fillText(txt, Math.round((w - c.measureText(txt).width)/2), h/2);
            c.save();
        }
    };
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: {$labels},
            datasets: [{ data: {$data}, backgroundColor: {$colors} }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                            var pct = Math.round(ctx.parsed/total*100);
                            return ctx.label+': '+ctx.parsed+' ('+pct+'%)';
                        }
                    }
                }
            }
        },
        plugins: [centerPlugin, datalabelsPlugin]
    });
})();
JS;
        return ['html' => $html, 'counts' => $counts];
    };

    // Section 1: This Course (or All Courses if no grade data).
    $entries1 = $gradesdata->hasthiscourse ? $gradesdata->thiscourse : $gradesdata->detail;
    $heading1 = $gradesdata->hasthiscourse
        ? get_string('grades_thiscourse', 'mod_mtssdashboard') . ' — ' . format_string($course->fullname)
        : get_string('grades_allcourses', 'mod_mtssdashboard');

    echo $OUTPUT->heading($heading1, 4);
    echo html_writer::start_div('row mb-4 align-items-start');

    // Left col: donut.
    echo html_writer::start_div('col-md-4');
    $r1 = $renderdonutjs(
        'maincourse',
        $entries1,
        $gradesdata->scale,
        $gradecolors,
        $defaultcolor,
        false
    );
    echo $r1['html'];
    echo html_writer::end_div();

    // Right col: table + legend.
    echo html_writer::start_div('col-md-4');
    if (!empty($r1['counts'])) {
        echo $rendercountstable($r1['counts'], $gradecolors, $defaultcolor, $gradesdata->scale);
        echo $renderlegend($gradesdata->scale, $gradecolors, $defaultcolor);
    }
    echo html_writer::end_div();

    echo html_writer::end_div(); // End .row.

    // Section 2: By Subject Category.
    $catkeys = [
        'math'          => get_string('grades_category_math', 'mod_mtssdashboard'),
        'english'       => get_string('grades_category_english', 'mod_mtssdashboard'),
        'science'       => get_string('grades_category_science', 'mod_mtssdashboard'),
        'socialstudies' => get_string('grades_category_socialstudies', 'mod_mtssdashboard'),
        'electives'     => get_string('grades_category_electives', 'mod_mtssdashboard'),
    ];

    $activecats = array_filter($gradesdata->categories, fn($e) => !empty($e));

    if (!empty($activecats)) {
        echo $OUTPUT->heading(get_string('grades_bysubject', 'mod_mtssdashboard'), 4);
        echo html_writer::start_div('row');
        $ci = 0;
        foreach ($catkeys as $key => $label) {
            if (empty($gradesdata->categories[$key])) {
                continue;
            }
            echo html_writer::start_div('col-md-4 mb-4 text-center');
            echo html_writer::tag(
                'h5',
                htmlspecialchars($label),
                ['class' => 'text-center text-primary']
            );
            $r = $renderdonutjs(
                'cat_' . $key,
                $gradesdata->categories[$key],
                $gradesdata->scale,
                $gradecolors,
                $defaultcolor,
                false
            );
            echo $r['html'];
            echo html_writer::end_div();
            $ci++;
            if ($ci % 3 === 0) {
                echo html_writer::end_div();
                echo html_writer::start_div('row');
            }
        }
        echo html_writer::end_div();
    }

    // Collapsed detail table.
    if (!empty($gradesdata->detail)) {
        usort($gradesdata->detail, fn($a, $b) =>
            strcmp($a['name'], $b['name']) ?: strcmp($a['coursename'], $b['coursename']));

        $uid = uniqid('stv_gtbl_');
        echo '<div class="mt-4">';
        echo '<button class="btn btn-outline-secondary btn-sm mb-2" type="button" ';
        echo 'onclick="var el=document.getElementById(\'' . $uid . '\');';
        echo 'el.style.display=(el.style.display===\'none\'||el.style.display===\'\')';
        echo '?\'block\':\'none\';">';
        echo '&#9660; ' . get_string('grades_showdetail', 'mod_mtssdashboard') . '</button>';
        echo '<div id="' . $uid . '" style="display:none;">';

        $table = new html_table();
        $table->head = [
            get_string('grades_detail_student', 'mod_mtssdashboard'),
            get_string('grades_detail_course', 'mod_mtssdashboard'),
            get_string('grades_detail_category', 'mod_mtssdashboard'),
            get_string('grades_detail_letter', 'mod_mtssdashboard'),
            get_string('grades_detail_pct', 'mod_mtssdashboard'),
        ];
        $table->attributes['class'] = 'generaltable table-sm table-hover';
        $catlabels = $catkeys + ['thiscourse' => format_string($course->fullname)];

        foreach ($gradesdata->detail as $e) {
            $bg    = $gradecolors[$e['letter']] ?? $defaultcolor;
            $lcell = new html_table_cell($e['letter']);
            $lcell->attributes['style'] =
                "background:{$bg};color:#fff;font-weight:bold;text-align:center;";
            $table->data[] = new html_table_row([
                new html_table_cell($e['name']),
                new html_table_cell($e['coursename']),
                new html_table_cell($catlabels[$e['category']] ?? $e['category']),
                $lcell,
                new html_table_cell($e['pct'] . '%'),
            ]);
        }
        echo html_writer::table($table);
        echo '</div></div>';
    }

    echo '<script>' . $alljs . '</script>';
}


// CSV download handler moved — see before $OUTPUT->header() above.
// placeholder removed.
if (
    optional_param('action', '', PARAM_ALPHANUMEXT) === 'downloadcsv'
        && $tab === 'earlywarning'
) {
    require_sesskey();

    $schoollevel = stv_get_school_level_for_course($course);

    $ewstudents = stv_filter_students(
        $tabstudents,
        $fgender,
        $frace,
        $fed,
        $fswd,
        $fel,
        $f504,
        $fiep,
        $fgifted
    );

    $userids        = [];
    $studentnumbers = [];
    $ssids          = [];
    foreach ($ewstudents as $s) {
        $userids[] = (int) $s->userid;
        if (!empty($s->idnumber)) {
            $studentnumbers[] = (int) $s->idnumber;
        }
        if (!empty($s->ssid)) {
            $ssids[] = $s->ssid;
        }
    }

    $incidents  = stv_get_office_incidents_by_studentnumber($studentnumbers);
    $attendance = stv_get_attendance_by_studentnumber($studentnumbers);
    $failed     = stv_get_failed_courses_by_userid($userids);
    $gpas       = stv_get_gpa_by_userid($userids, $schoollevel);
    $lowost     = stv_get_low_ost_by_ssid($ssids);
    $ireadyela = stv_get_iready_ela_by_studentnumber($studentnumbers);
    $ireadymth = stv_get_iready_math_by_studentnumber($studentnumbers);

    if ($schoollevel === 'es') {
        $metrickeys = [
            'total_hours_missed', 'unexcused_hours_missed',
            'iready_ela_percentile', 'iready_math_percentile', 'ost_performance',
        ];
    } else if ($schoollevel === 'ms') {
        $metrickeys = [
            'total_hours_missed', 'unexcused_hours_missed', 'office_incidents',
            'course_failure_count', 'gpa', 'iready_ela_percentile', 'iready_ela_days',
            'iready_math_percentile', 'iready_math_days', 'ost_performance',
        ];
    } else {
        $metrickeys = [
            'total_hours_missed', 'unexcused_hours_missed', 'office_incidents',
            'course_failure_count', 'gpa', 'ost_performance',
        ];
    }

    $colheaders = [
        'total_hours_missed'     => 'Total Hours Missed',
        'unexcused_hours_missed' => 'Unexcused Hours',
        'office_incidents'       => 'Office Incidents',
        'course_failure_count'   => 'Failing Courses',
        'gpa'                    => 'Current GPA',
        'iready_ela_percentile'  => 'iReady ELA Percentile',
        'iready_math_percentile' => 'iReady Math Percentile',
        'ost_performance'        => 'Low OST Scores',
    ];

    $rows = [];
    foreach ($ewstudents as $s) {
        $sn  = !empty($s->idnumber) ? (int) $s->idnumber : 0;
        $uid = (int) $s->userid;
        $sid = !empty($s->ssid) ? $s->ssid : '';
        $row = (object) [
            'userid'        => $uid,
            'studentnumber' => $sn,
            'firstname'     => $s->firstname,
            'lastname'      => $s->lastname,
            'gradelevel'    => $s->gradelevel ?? '',
            'metrics'       => [
                'total_hours_missed'     => null,
                'unexcused_hours_missed' => null,
                'office_incidents'       => $incidents[$sn] ?? 0,
                'course_failure_count'   => $failed[$uid] ?? 0,
                'gpa'                    => $gpas[$uid] ?? null,
                'iready_ela_percentile'  => $ireadyela[$sn] ?? null,
                'iready_math_percentile' => $ireadymth[$sn] ?? null,
                'ost_performance'        => $lowost[$sid] ?? 0,
            ],
        ];
        if (isset($attendance[$sn])) {
            $row->metrics['total_hours_missed']     = $attendance[$sn]->total_hours;
            $row->metrics['unexcused_hours_missed'] = $attendance[$sn]->unexcused_hours;
        }
        $rows[] = $row;
    }

    $ewsortcsv = optional_param('ewsort', 'name', PARAM_ALPHA);
    foreach ($rows as $row) {
        $flagcount = 0;
        foreach ($metrickeys as $mkey) {
            $value = $row->metrics[$mkey];
            if ($value === null) {
                continue;
            }
            $color = stv_get_band_color($schoollevel, $mkey, $value);
            if (stv_band_is_flagged($color)) {
                $flagcount++;
            }
        }
        $row->riskkey   = stv_compute_risk_level($flagcount, $schoollevel);
        $row->flagcount = $flagcount;
    }

    if ($ewsortcsv === 'risk') {
        $riskrank = ['extreme' => 0, 'moderate' => 1, 'low' => 2, 'none' => 3];
        usort($rows, function ($a, $b) use ($riskrank) {
            $ra = $riskrank[$a->riskkey] ?? 99;
            $rb = $riskrank[$b->riskkey] ?? 99;
            if ($ra !== $rb) {
                return $ra - $rb;
            }
            $lc = strcasecmp($a->lastname, $b->lastname);
            return $lc !== 0 ? $lc : strcasecmp($a->firstname, $b->firstname);
        });
    } else {
        usort($rows, function ($a, $b) {
            $lc = strcasecmp($a->lastname, $b->lastname);
            return $lc !== 0 ? $lc : strcasecmp($a->firstname, $b->firstname);
        });
    }

    $risklabels = [
        'none'     => 'No Risk',
        'low'      => 'Low Risk',
        'moderate' => 'Moderate Risk',
        'extreme'  => 'Extreme Risk',
    ];

    $filename = clean_filename($course->shortname . '_earlywarning_' . date('Ymd') . '.csv');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");

    $csvheader = ['Grade', 'Student ID', 'Last Name', 'First Name', 'Overall Risk'];
    foreach ($metrickeys as $mkey) {
        $csvheader[] = $colheaders[$mkey] ?? $mkey;
    }
    fputcsv($out, $csvheader);

    foreach ($rows as $row) {
        $csvrow = [
            $row->gradelevel,
            $row->studentnumber > 0 ? $row->studentnumber : '',
            $row->lastname,
            $row->firstname,
            $risklabels[$row->riskkey] ?? $row->riskkey,
        ];
        foreach ($metrickeys as $mkey) {
            $value = $row->metrics[$mkey];
            if ($value === null) {
                $csvrow[] = '';
            } else if ($mkey === 'gpa') {
                $csvrow[] = number_format((float) $value, 3);
            } else if (in_array($mkey, ['total_hours_missed', 'unexcused_hours_missed'], true)) {
                $csvrow[] = number_format((float) $value, 2);
            } else {
                $csvrow[] = (int) $value;
            }
        }
        fputcsv($out, $csvrow);
    }

    fclose($out);
    exit;
}

// Early Warning tab.
if ($tab === 'earlywarning') {
    $schoollevel = stv_get_school_level_for_course($course);

    echo $OUTPUT->heading(get_string('ew_heading', 'mod_mtssdashboard'), 3);
    echo html_writer::tag(
        'p',
        get_string('ew_intro', 'mod_mtssdashboard'),
        ['class' => 'text-muted']
    );

    // Sort toggle button — flips between alphabetical and by-risk ordering.
    // Preserve all active filter values so sorting doesn't clear the filter.
    $togglesort = ($ewsort === 'risk') ? 'name' : 'risk';
    $togglekey  = ($ewsort === 'risk') ? 'ew_btn_sort_name' : 'ew_btn_sort_risk';
    $sorturlparams = [
        'id'     => $cm->id,
        'tab'    => 'earlywarning',
        'ewsort' => $togglesort,
    ];
    // Carry forward any active demographic filters.
    if ($fgender !== '') {
        $sorturlparams['f_gender'] = $fgender;
    }
    if ($frace !== '') {
        $sorturlparams['f_race']   = $frace;
    }
    if ($fed !== '') {
        $sorturlparams['f_ed']     = $fed;
    }
    if ($fswd !== '') {
        $sorturlparams['f_swd']    = $fswd;
    }
    if ($fel !== '') {
        $sorturlparams['f_el']     = $fel;
    }
    if ($f504 !== '') {
        $sorturlparams['f_504']    = $f504;
    }
    if ($fiep !== '') {
        $sorturlparams['f_iep']    = $fiep;
    }
    if ($fgifted !== '') {
        $sorturlparams['f_gifted'] = $fgifted;
    }
    // Carry forward group selection.
    if ($groupid > 0) {
        $sorturlparams['group'] = $groupid;
    }
    $sorturl = new moodle_url('/mod/mtssdashboard/view.php', $sorturlparams);

    // CSV download URL — carries all the same filters plus current sort order.
    $csvurlparams = [
        'id'      => $cm->id,
        'tab'     => 'earlywarning',
        'action'  => 'downloadcsv',
        'ewsort'  => $ewsort,
        'sesskey' => sesskey(),
    ];
    if ($fgender !== '') {
        $csvurlparams['f_gender'] = $fgender;
    }
    if ($frace !== '') {
        $csvurlparams['f_race']   = $frace;
    }
    if ($fed !== '') {
        $csvurlparams['f_ed']     = $fed;
    }
    if ($fswd !== '') {
        $csvurlparams['f_swd']    = $fswd;
    }
    if ($fel !== '') {
        $csvurlparams['f_el']     = $fel;
    }
    if ($f504 !== '') {
        $csvurlparams['f_504']    = $f504;
    }
    if ($fiep !== '') {
        $csvurlparams['f_iep']    = $fiep;
    }
    if ($fgifted !== '') {
        $csvurlparams['f_gifted'] = $fgifted;
    }
    if ($groupid > 0) {
        $csvurlparams['group']    = $groupid;
    }
    $csvurl = new moodle_url('/mod/mtssdashboard/view.php', $csvurlparams);

    echo html_writer::tag(
        'p',
        html_writer::link(
            $sorturl,
            get_string($togglekey, 'mod_mtssdashboard'),
            ['class' => 'btn btn-outline-primary btn-sm']
        ) .
        ' ' .
        html_writer::link(
            $csvurl,
            '⬇ Download CSV',
            ['class' => 'btn btn-outline-secondary btn-sm']
        ),
        ['style' => 'margin-bottom:1rem;']
    );

    // Apply demographic filters on top of group-filtered $tabstudents.
    $ewstudents = stv_filter_students(
        $tabstudents,
        $fgender,
        $frace,
        $fed,
        $fswd,
        $fel,
        $f504,
        $fiep,
        $fgifted
    );

    if (empty($ewstudents)) {
        echo $OUTPUT->notification(
            get_string('ew_no_students', 'mod_mtssdashboard'),
            'notifymessage'
        );
    } else {
        // Collect ID arrays for bulk queries.
        $userids        = [];
        $studentnumbers = [];
        $ssids          = [];
        foreach ($ewstudents as $s) {
            $userids[] = (int) $s->userid;
            // S->idnumber holds the school's StudentNumber (per existing pattern).
            if (!empty($s->idnumber)) {
                $studentnumbers[] = (int) $s->idnumber;
            }
            if (!empty($s->ssid)) {
                $ssids[] = $s->ssid;
            }
        }

        // Bulk fetch all metric data in parallel.
        $incidents  = stv_get_office_incidents_by_studentnumber($studentnumbers);
        $attendance = stv_get_attendance_by_studentnumber($studentnumbers);
        $failed     = stv_get_failed_courses_by_userid($userids);
        $gpas       = stv_get_gpa_by_userid($userids, $schoollevel);
        $lowost     = stv_get_low_ost_by_ssid($ssids);
        $ireadyela = stv_get_iready_ela_by_studentnumber($studentnumbers);
        $ireadymth = stv_get_iready_math_by_studentnumber($studentnumbers);
        $ireadydiag = stv_get_iready_diagnostics_by_studentnumber($studentnumbers);
        $rimpdata    = stv_get_rimp_by_studentnumber($studentnumbers);
        $ireadydays = ($schoollevel === 'ms')
            ? stv_get_iready_days_since_lesson($studentnumbers)
            : [];

        // Assemble per-student rows for rendering.
        $rows = [];
        foreach ($ewstudents as $s) {
            $sn   = !empty($s->idnumber) ? (int) $s->idnumber : 0;
            $uid  = (int) $s->userid;
            $sid  = !empty($s->ssid) ? $s->ssid : '';
            $diag = $ireadydiag[$sn] ?? null;

            $row = (object) [
                'userid'         => $uid,
                'studentnumber'  => $sn,
                'firstname'      => $s->firstname,
                'lastname'       => $s->lastname,
                'gradelevel'     => $s->gradelevel ?? '',
                'metrics'        => [
                    'total_hours_missed'     => null, // Coming soon — stays null.
                    'unexcused_hours_missed' => null, // Coming soon — stays null.
                    'office_incidents'       => $incidents[$sn] ?? 0,
                    'course_failure_count'   => $failed[$uid] ?? 0,
                    'gpa'                    => $gpas[$uid] ?? null,
                    'iready_ela_percentile'  => $ireadyela[$sn] ?? null,
                    'iready_math_percentile' => $ireadymth[$sn] ?? null,
                    'ela_fall_score'         => $diag->ela_fall_score ?? null,
                    'ela_fall_pct'           => $diag->ela_fall_pct ?? null,
                    'ela_winter_score'       => $diag->ela_winter_score ?? null,
                    'ela_winter_pct'         => $diag->ela_winter_pct ?? null,
                    'ela_spring_score'       => $diag->ela_spring_score ?? null,
                    'ela_spring_pct'         => $diag->ela_spring_pct ?? null,
                    'math_fall_score'        => $diag->math_fall_score ?? null,
                    'math_fall_pct'          => $diag->math_fall_pct ?? null,
                    'math_winter_score'      => $diag->math_winter_score ?? null,
                    'math_winter_pct'        => $diag->math_winter_pct ?? null,
                    'math_spring_score'      => $diag->math_spring_score ?? null,
                    'math_spring_pct'        => $diag->math_spring_pct ?? null,
                    'ost_performance'        => $lowost[$sid] ?? 0,
                    'rimp'                   => $rimpdata[$sn] ?? 0,
                    'iready_ela_days'        => $ireadydays[$sn]->ela_days ?? null,
                    'iready_math_days'       => $ireadydays[$sn]->math_days ?? null,
                ],
            ];

            // If attendance data is present, fill in the hours metrics.
            if (isset($attendance[$sn])) {
                $row->metrics['total_hours_missed']     = $attendance[$sn]->total_hours;
                $row->metrics['unexcused_hours_missed'] = $attendance[$sn]->unexcused_hours;
            }

            // Blank lesson-days for grades that don't take iReady lessons, and
            // for RIMP students in grades set on the "for RIMP Students" card
            // (Warning Levels tab). Blank = "—" and not counted in risk.
            $row->metrics = stv_apply_lessons_off(
                $row->metrics,
                $row->gradelevel,
                $schoollevel,
                !empty($rimpdata[$sn])
            );

            $rows[] = $row;
        }

        // Sort: alphabetical by default, or by risk if requested.
        // Risk sort runs AFTER risk computation, so we just record which sort
        // to apply and do it after the foreach loop that assigns ->riskkey.
        $applysortbyrisk = ($ewsort === 'risk');

        // Alphabetical sort runs immediately (it doesn't depend on riskkey).
        if (!$applysortbyrisk) {
            usort($rows, function ($a, $b) {
                $lc = strcasecmp($a->lastname, $b->lastname);
                return $lc !== 0 ? $lc : strcasecmp($a->firstname, $b->firstname);
            });
        }

        // Define which columns to show per school level.
        // ES gets the smaller metric set (no GPA, no Failed Courses, no Office).
        // HS and MS get all 8.
        if ($schoollevel === 'es') {
            $metrickeys = [
                'total_hours_missed',
                // Unexcused hours column hidden per ES request 2026-05-20; re-add 'unexcused_hours_missed' here to restore.
                'ela_fall_score',
                'ela_fall_pct',
                'ela_winter_score',
                'ela_winter_pct',
                'ela_spring_score',
                'ela_spring_pct',
                'math_fall_score',
                'math_fall_pct',
                'math_winter_score',
                'math_winter_pct',
                'math_spring_score',
                'math_spring_pct',
                'ost_performance',
                'rimp',
            ];
        } else if ($schoollevel === 'ms') {
            $metrickeys = [
                'total_hours_missed',
                'unexcused_hours_missed',
                'office_incidents',
                'course_failure_count',
                'gpa',
                'iready_ela_percentile',
                'iready_ela_days',
                'iready_math_percentile',
                'iready_math_days',
                'ost_performance',
                'rimp',
            ];
        } else {
            $metrickeys = [
                'total_hours_missed',
                'unexcused_hours_missed',
                'office_incidents',
                'course_failure_count',
                'gpa',
                'ost_performance',
                'rimp',
            ];
        }

        // Map metric_key → display column lang string key.
        $colkey = [
            'total_hours_missed'     => 'ew_col_total_hours',
            'unexcused_hours_missed' => 'ew_col_unexcused_hours',
            'office_incidents'       => 'ew_col_office',
            'course_failure_count'   => 'ew_col_failed',
            'gpa'                    => 'ew_col_gpa',
            'iready_ela_percentile'  => 'ew_col_iready_ela',
            'iready_math_percentile' => 'ew_col_iready_math',
            'iready_ela_days'        => 'ew_col_iready_ela_days',
            'iready_math_days'       => 'ew_col_iready_math_days',
            'ela_fall_score'         => 'ew_col_ela_fall_score',
            'ela_fall_pct'           => 'ew_col_ela_fall_pct',
            'ela_winter_score'       => 'ew_col_ela_winter_score',
            'ela_winter_pct'         => 'ew_col_ela_winter_pct',
            'ela_spring_score'       => 'ew_col_ela_spring_score',
            'ela_spring_pct'         => 'ew_col_ela_spring_pct',
            'math_fall_score'        => 'ew_col_math_fall_score',
            'math_fall_pct'          => 'ew_col_math_fall_pct',
            'math_winter_score'      => 'ew_col_math_winter_score',
            'math_winter_pct'        => 'ew_col_math_winter_pct',
            'math_spring_score'      => 'ew_col_math_spring_score',
            'math_spring_pct'        => 'ew_col_math_spring_pct',
            'ost_performance'        => 'ew_col_low_ost',
            'rimp'                   => 'ew_col_rimp',
        ];

        // Color → CSS background hex.
        $bandbg = [
            'green'  => '#d1e7dd',
            'yellow' => '#fff3cd',
            'orange' => '#fde0c4',
            'red'    => '#f8d7da',
            'purple' => '#e2d4f0',
        ];

        // Risk level → CSS background hex (header row of Overall Risk column).
        $riskbg = [
            'none'     => '#d1e7dd',
            'low'      => '#fff3cd',
            'moderate' => '#fde0c4',
            'extreme'  => '#f8d7da',
        ];

        // Compute Overall Risk for each student row.
        foreach ($rows as $row) {
            $flagcount = 0;
            foreach ($metrickeys as $mkey) {
                $value = $row->metrics[$mkey];
                if ($value === null) {
                    continue;
                }
                $color = stv_get_band_color($schoollevel, $mkey, $value);
                if (stv_band_is_flagged($color)) {
                    $flagcount++;
                }
            }
            $row->riskkey = stv_compute_risk_level($flagcount, $schoollevel);
            $row->flagcount = $flagcount;
        }

        // If sorting by risk, do it now that every row has a riskkey assigned.
        if ($applysortbyrisk) {
            $riskrank = [
                'extreme'  => 0,
                'moderate' => 1,
                'low'      => 2,
                'none'     => 3,
            ];
            usort($rows, function ($a, $b) use ($riskrank) {
                $ra = $riskrank[$a->riskkey] ?? 99;
                $rb = $riskrank[$b->riskkey] ?? 99;
                if ($ra !== $rb) {
                    return $ra - $rb;
                }
                $lc = strcasecmp($a->lastname, $b->lastname);
                return $lc !== 0 ? $lc : strcasecmp($a->firstname, $b->firstname);
            });
        }

        // Build the table.
        echo html_writer::start_tag('div', ['style' => 'overflow-x:auto; overflow-y:auto; max-height:70vh;']);
        echo html_writer::start_tag('table', [
            'class' => 'generaltable stv-earlywarning-table',
            'style' => 'width:100%; border-collapse:collapse; font-size:0.9em;',
        ]);

        // Header row.
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr', ['style' => 'background:#f1f3f5;']);
        $thstyle = 'padding:0.5rem 0.3rem; border:1px solid #dee2e6; text-align:center; font-weight:600; ' .
            'position:sticky; top:0; z-index:2; background:#f1f3f5; min-width:70px;';
        $thstickystyle = $thstyle . ' position:sticky; left:0; top:0; z-index:4; background:#f1f3f5; text-align:left; ' .
            'min-width:160px;';
        echo html_writer::tag('th', get_string('ew_col_name', 'mod_mtssdashboard'), ['style' => $thstickystyle]);
        echo html_writer::tag('th', get_string('ew_col_grade', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::tag('th', get_string('ew_col_studentid', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::tag('th', get_string('ew_col_overall', 'mod_mtssdashboard'), ['style' => $thstyle]);
        foreach ($metrickeys as $mkey) {
            // Use ES-specific OST label if appropriate.
            $strkey = $colkey[$mkey];
            if ($mkey === 'ost_performance' && $schoollevel === 'es') {
                // Reuse the Warning Levels metric label override.
                $manager = get_string_manager();
                if ($manager->string_exists('metric_ost_performance_es', 'mod_mtssdashboard')) {
                    echo html_writer::tag(
                        'th',
                        get_string('metric_ost_performance_es', 'mod_mtssdashboard'),
                        ['style' => $thstyle]
                    );
                    continue;
                }
            }
            echo html_writer::tag(
                'th',
                get_string($strkey, 'mod_mtssdashboard'),
                ['style' => $thstyle]
            );
        }
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');

        // Body rows.
        echo html_writer::start_tag('tbody');
        $tdstyle = 'padding:0.4rem 0.3rem; border:1px solid #dee2e6; vertical-align:middle; text-align:center;';
        $coming  = get_string('ew_pending_data', 'mod_mtssdashboard');
        $nodata  = get_string('ew_no_data', 'mod_mtssdashboard');

        // Pre-load SSIDs that have a plan PDF — avoids per-row file_exists() calls.
        $plansdir  = $CFG->dataroot . '/NTimports/plans/';
        $planfiles = [];
        if (is_dir($plansdir)) {
            foreach (glob($plansdir . '*.pdf') as $f) {
                $planfiles[(int) pathinfo($f, PATHINFO_FILENAME)] = true;
            }
        }
        $planbaseurl = new moodle_url('/mod/mtssdashboard/plan.php', ['cmid' => $cm->id]);
        echo html_writer::tag('style', '.mtssdash-plan-link, .mtssdash-plan-link:visited { color:#e07b00; ' .
            'font-weight:600; }');

        foreach ($rows as $row) {
            echo html_writer::start_tag('tr');

            // Student name (last, first) — sticky left column.
            $tdstickystyle = $tdstyle . ' position:sticky; left:0; z-index:1; background:#fff; text-align:left;';
            echo html_writer::tag(
                'td',
                s($row->lastname . ', ' . $row->firstname),
                ['style' => $tdstickystyle]
            );

            // Grade.
            echo html_writer::tag('td', s($row->gradelevel), ['style' => $tdstyle]);

            // Student ID — link to plan PDF if one exists for this student.
            if ($row->studentnumber > 0 && isset($planfiles[$row->studentnumber])) {
                $planurl = new moodle_url($planbaseurl, ['ssid' => $row->studentnumber]);
                $snlabel = html_writer::link(
                    $planurl->out(false),
                    (string) $row->studentnumber,
                    ['target' => '_blank', 'title' => get_string('plan_view', 'mod_mtssdashboard'),
                    'class' => 'mtssdash-plan-link']
                );
            } else {
                $snlabel = $row->studentnumber > 0 ? (string) $row->studentnumber : $nodata;
            }
            echo html_writer::tag('td', $snlabel, ['style' => $tdstyle]);

            // Overall Risk Level — colored cell with risk label.
            $riskcolor = $riskbg[$row->riskkey] ?? '#fff';
            $risklabel = get_string('ew_risk_' . $row->riskkey, 'mod_mtssdashboard');
            echo html_writer::tag(
                'td',
                s($risklabel),
                ['style' => $tdstyle . " background:$riskcolor; font-weight:600;"]
            );

            // Metric cells.
            foreach ($metrickeys as $mkey) {
                $value = $row->metrics[$mkey];

                // RIMP column — link to print view if student has a RIMP.
                if ($mkey === 'rimp') {
                    if (!empty($value)) {
                        $printurl = new moodle_url(
                            '/mod/mtssdashboard/rimp_print.php',
                            ['id' => $cm->id, 'sn' => $row->studentnumber]
                        );
                        $cell = html_writer::tag(
                            'a',
                            '&#10003;',
                            ['href'   => $printurl->out(false),
                             'target' => '_blank',
                             'title'  => get_string('rimp_btn_print', 'mod_mtssdashboard'),
                            'style'  => 'font-size:1.2em; font-weight:bold; color:#155724;']
                        );
                        $cellbg = ' background:#d4edda;';
                    } else {
                        $cell   = '—';
                        $cellbg = '';
                    }
                    echo html_writer::tag(
                        'td',
                        $cell,
                        ['style' => $tdstyle . ' text-align:center;' . $cellbg]
                    );
                    continue;
                }

                // Note: iReady columns now have live data; the "coming soon"
                // stub treatment has been removed.

                if ($value === null) {
                    echo html_writer::tag(
                        'td',
                        s($nodata),
                        ['style' => $tdstyle . ' color:#adb5bd;']
                    );
                    continue;
                }

                // Look up band color and pick the matching background.
                $color = stv_get_band_color($schoollevel, $mkey, $value);
                $bg    = $color !== null ? ($bandbg[$color] ?? '#fff') : '#fff';

                // Display formatting per metric type.
                if ($mkey === 'gpa') {
                    $display = number_format((float) $value, 3);
                } else if (in_array($mkey, ['total_hours_missed', 'unexcused_hours_missed'], true)) {
                    $display = number_format((float) $value, 2);
                } else if (in_array($mkey, ['iready_ela_days', 'iready_math_days'], true)) {
                    $display = ($value == 99) ? get_string('ew_never', 'mod_mtssdashboard')
                                              : (string)(int)$value;
                } else {
                    $display = (string) (int) $value;
                }

                echo html_writer::tag(
                    'td',
                    s($display),
                    ['style' => $tdstyle . " background:$bg;"]
                );
            }

            echo html_writer::end_tag('tr');
        }
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
        echo html_writer::end_tag('div');

        // Show row count footer.
        echo html_writer::tag(
            'p',
            count($rows) . ' student' . (count($rows) === 1 ? '' : 's'),
            ['class' => 'text-muted', 'style' => 'margin-top:0.5rem; font-size:0.85em;']
        );
    }
}

// Warning Levels tab.
if ($tab === 'warning') {
    // Determine school level for this course from the course category.
    $schoollevel = stv_get_school_level_for_course($course);

    // Build heading for this school level.
    $headingkey = 'warning_heading_' . $schoollevel;
    echo $OUTPUT->heading(get_string($headingkey, 'mod_mtssdashboard'), 3);

    // Determine whether this is the management course for this school level.
    $ismgmtcourse = stv_is_management_course($course->id, $schoollevel);

    // Determine whether the current user may edit thresholds on this course.
    $caneditwarnings = stv_can_edit_warning_levels($course, $schoollevel);

    // Force read-only mode if user lacks edit permission, even if ?edit=1.
    if (!$caneditwarnings) {
        $editmode = 0;
    }

    // Show the appropriate intro text.
    if ($ismgmtcourse) {
        echo $OUTPUT->notification(
            get_string('warning_intro_editable', 'mod_mtssdashboard'),
            'notifymessage'
        );
    } else {
        echo html_writer::tag(
            'p',
            get_string('warning_intro_readonly', 'mod_mtssdashboard'),
            ['class' => 'text-muted']
        );
    }

    echo html_writer::tag(
        'p',
        get_string('warning_intro', 'mod_mtssdashboard')
    );

    // Edit toggle button — visible only to users who can actually save edits.
    if ($caneditwarnings) {
        $editurl = new moodle_url('/mod/mtssdashboard/view.php', [
            'id'   => $cm->id,
            'tab'  => 'warning',
            'edit' => $editmode ? 0 : 1,
        ]);
        $btnlabelkey = $editmode ? 'warning_btn_cancel' : 'warning_btn_edit';
        $btnclass    = $editmode ? 'btn btn-secondary' : 'btn btn-primary';

        echo html_writer::tag(
            'p',
            html_writer::link(
                $editurl,
                get_string($btnlabelkey, 'mod_mtssdashboard'),
                ['class' => $btnclass]
            ),
            ['style' => 'margin-bottom:1rem;']
        );
    }

    // Fetch warning level rows for this school level.
    $rows = $DB->get_records(
        'mtssdashboard_warning_levels',
        ['schoollevel' => $schoollevel],
        'metric_key ASC, sortorder ASC'
    );

    if (empty($rows)) {
        // ES has no rows yet; HS/MS show this only if all rows were deleted.
        $msgkey = ($schoollevel === 'es')
            ? 'warning_es_placeholder'
            : 'warning_no_data';
        echo $OUTPUT->notification(
            get_string($msgkey, 'mod_mtssdashboard'),
            'notifymessage'
        );
    } else {
        // Group rows by metric_key, preserving sortorder within each group.
        $bymetric = [];
        foreach ($rows as $r) {
            $bymetric[$r->metric_key][] = $r;
        }

        // Color → CSS hex for the band swatches.
        $colorhex = [
            'green'  => '#198754',
            'yellow' => '#ffc107',
            'orange' => '#fd7e14',
            'red'    => '#dc3545',
            'purple' => '#6f42c1',
        ];

        // If editing, wrap everything in a form. Sesskey protects against CSRF.
        if ($editmode) {
            $formaction = new moodle_url('/mod/mtssdashboard/view.php');
            echo html_writer::start_tag('form', [
                'method' => 'post',
                'action' => $formaction->out(false),
                'id'     => 'stv-warning-form',
                'onsubmit' => 'return confirm('
                    . json_encode(get_string('warning_confirm_save', 'mod_mtssdashboard'))
                    . ');',
            ]);

            // Hidden context fields.
            echo html_writer::empty_tag('input', [
                'type' => 'hidden', 'name' => 'id', 'value' => $cm->id,
            ]);
            echo html_writer::empty_tag('input', [
                'type' => 'hidden', 'name' => 'tab', 'value' => 'warning',
            ]);
            echo html_writer::empty_tag('input', [
                'type' => 'hidden', 'name' => 'action', 'value' => 'savewarning',
            ]);
            echo html_writer::empty_tag('input', [
                'type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey(),
            ]);
        }

        // Render a responsive grid of metric cards.
        echo html_writer::start_tag('div', [
            'class' => 'stv-warning-grid',
            'style' => 'display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); '
                     . 'gap:1rem; margin-top:1rem;',
        ]);

        // Separate config rows from regular band rows before rendering.
        $configrows   = []; // Metric_key => rows with color='config'.
        $riskrows     = []; // Risk_threshold rows.
        $bandmetrics  = []; // Normal threshold band rows.

        foreach ($bymetric as $mkey => $bands) {
            if ($mkey === 'risk_threshold') {
                $riskrows = $bands;
            } else {
                $regularbands = [];
                foreach ($bands as $band) {
                    if ($band->color === 'config') {
                        $configrows[$mkey][] = $band;
                    } else {
                        $regularbands[] = $band;
                    }
                }
                if (!empty($regularbands)) {
                    $bandmetrics[$mkey] = $regularbands;
                }
            }
        }

        // Regular threshold band cards.
        foreach ($bandmetrics as $mkey => $bands) {
            $eslabelkey = 'metric_' . $mkey . '_' . $schoollevel;
            $manager = get_string_manager();
            if ($manager->string_exists($eslabelkey, 'mod_mtssdashboard')) {
                $metriclabel = get_string($eslabelkey, 'mod_mtssdashboard');
            } else {
                $metriclabel = get_string('metric_' . $mkey, 'mod_mtssdashboard');
            }

            echo html_writer::start_tag('div', [
                'class' => 'card stv-warning-card',
                'style' => 'border:1px solid #dee2e6; border-radius:0.375rem; '
                         . 'padding:1rem; background:#fff;',
            ]);
            echo html_writer::tag('h5', s($metriclabel), [
                'style' => 'margin:0 0 0.75rem 0; font-weight:600;',
            ]);
            echo html_writer::start_tag('ul', [
                'style' => 'list-style:none; padding:0; margin:0;',
            ]);

            foreach ($bands as $band) {
                $hex        = $colorhex[$band->color] ?? '#6c757d';
                $colorlabel = get_string('color_' . $band->color, 'mod_mtssdashboard');
                $swatch     = html_writer::tag('span', '', [
                    'style'       => "display:inline-block; width:0.85em; height:0.85em; "
                                   . "border-radius:50%; background:$hex; margin-right:0.5em; "
                                   . "vertical-align:middle;",
                    'aria-hidden' => 'true',
                ]);
                $colorpart = html_writer::tag('strong', s($colorlabel) . ':', [
                    'style' => 'margin-right:0.35em;',
                ]);

                if ($editmode) {
                    $minval = ($band->threshold_min !== null)
                        ? rtrim(rtrim($band->threshold_min, '0'), '.') : '';
                    $maxval = ($band->threshold_max !== null)
                        ? rtrim(rtrim($band->threshold_max, '0'), '.') : '';
                    $mininput = html_writer::empty_tag('input', [
                        'type' => 'number', 'step' => '0.01',
                        'name' => 'tmin[' . $band->id . ']', 'value' => $minval,
                        'placeholder' => '—', 'style' => 'width:5em; margin-right:0.25em;',
                    ]);
                    $maxinput = html_writer::empty_tag('input', [
                        'type' => 'number', 'step' => '0.01',
                        'name' => 'tmax[' . $band->id . ']', 'value' => $maxval,
                        'placeholder' => '—', 'style' => 'width:5em;',
                    ]);
                    $editbits = $mininput
                        . html_writer::tag('span', ' to ', ['style' => 'margin:0 0.25em;'])
                        . $maxinput;
                    echo html_writer::tag(
                        'li',
                        $swatch . $colorpart . $editbits,
                        ['style' => 'padding:0.3rem 0;']
                    );
                } else {
                    $textpart = s($band->threshold_text ?? '');
                    echo html_writer::tag(
                        'li',
                        $swatch . $colorpart . $textpart,
                        ['style' => 'padding:0.2rem 0;']
                    );
                }
            }

            echo html_writer::end_tag('ul');
            echo html_writer::end_tag('div');
        }

        // Risk Level Thresholds card.
        if (!empty($riskrows)) {
            echo html_writer::start_tag('div', [
                'class' => 'card stv-warning-card',
                'style' => 'border:2px solid #4e73df; border-radius:0.375rem; '
                         . 'padding:1rem; background:#fff;',
            ]);
            echo html_writer::tag('h5', 'Risk Level Thresholds', [
                'style' => 'margin:0 0 0.75rem 0; font-weight:600; color:#4e73df;',
            ]);
            echo html_writer::tag(
                'p',
                'Number of flagged criteria required to reach each risk level.',
                ['style' => 'font-size:0.85em; color:#6c757d; margin-bottom:0.5rem;']
            );
            echo html_writer::start_tag('ul', [
                'style' => 'list-style:none; padding:0; margin:0;',
            ]);

            $riskcolorhex = [
                'low'      => '#ffc107',
                'moderate' => '#fd7e14',
                'extreme'  => '#dc3545',
            ];
            $risklabels = [
                'low'      => 'Low Risk',
                'moderate' => 'Moderate Risk',
                'extreme'  => 'Extreme Risk',
            ];

            foreach ($riskrows as $band) {
                $hex       = $riskcolorhex[$band->color] ?? '#6c757d';
                $risklabel = $risklabels[$band->color] ?? $band->color;
                $swatch    = html_writer::tag('span', '', [
                    'style'       => "display:inline-block; width:0.85em; height:0.85em; "
                                   . "border-radius:50%; background:$hex; margin-right:0.5em; "
                                   . "vertical-align:middle;",
                    'aria-hidden' => 'true',
                ]);
                $colorpart = html_writer::tag('strong', s($risklabel) . ':', [
                    'style' => 'margin-right:0.35em;',
                ]);

                if ($editmode) {
                    $minval = ($band->threshold_min !== null)
                        ? rtrim(rtrim($band->threshold_min, '0'), '.') : '';
                    $mininput = html_writer::empty_tag('input', [
                        'type'        => 'number',
                        'step'        => '1',
                        'min'         => '1',
                        'name'        => 'tmin[' . $band->id . ']',
                        'value'       => $minval,
                        'placeholder' => '—',
                        'style'       => 'width:4em;',
                    ]);
                    // Hidden max field (not used for risk thresholds).
                    echo html_writer::empty_tag('input', [
                        'type' => 'hidden',
                        'name' => 'tmax[' . $band->id . ']',
                        'value' => '',
                    ]);
                    $suffix = html_writer::tag(
                        'span',
                        ' or more flags',
                        ['style' => 'margin-left:0.35em; color:#6c757d;']
                    );
                    echo html_writer::tag(
                        'li',
                        $swatch . $colorpart . $mininput . $suffix,
                        ['style' => 'padding:0.3rem 0;']
                    );
                } else {
                    $textpart = s($band->threshold_text ?? '');
                    echo html_writer::tag(
                        'li',
                        $swatch . $colorpart . $textpart,
                        ['style' => 'padding:0.2rem 0;']
                    );
                }
            }

            // No Risk is implicit — always add as a note.
            $swatch = html_writer::tag('span', '', [
                'style'       => "display:inline-block; width:0.85em; height:0.85em; "
                               . "border-radius:50%; background:#198754; margin-right:0.5em; "
                               . "vertical-align:middle;",
                'aria-hidden' => 'true',
            ]);
            echo html_writer::tag(
                'li',
                $swatch .
                html_writer::tag('strong', 'No Risk:', ['style' => 'margin-right:0.35em;']) .
                'Below Low Risk threshold',
                ['style' => 'padding:0.2rem 0; color:#6c757d; font-style:italic;']
            );

            echo html_writer::end_tag('ul');
            echo html_writer::end_tag('div');
        }

        // Config cards (e.g. ELA/Math passing score).
        foreach ($configrows as $mkey => $bands) {
            $eslabelkey = 'metric_' . $mkey . '_' . $schoollevel;
            $manager = get_string_manager();
            if ($manager->string_exists($eslabelkey, 'mod_mtssdashboard')) {
                $metriclabel = get_string($eslabelkey, 'mod_mtssdashboard');
            } else {
                $strkey = 'metric_' . $mkey;
                $manager2 = get_string_manager();
                $metriclabel = $manager2->string_exists($strkey, 'mod_mtssdashboard')
                    ? get_string($strkey, 'mod_mtssdashboard')
                    : s($mkey);
            }

            echo html_writer::start_tag('div', [
                'class' => 'card stv-warning-card',
                'style' => 'border:1px solid #dee2e6; border-radius:0.375rem; '
                         . 'padding:1rem; background:#fff;',
            ]);
            echo html_writer::tag('h5', s($metriclabel), [
                'style' => 'margin:0 0 0.75rem 0; font-weight:600;',
            ]);
            echo html_writer::start_tag('ul', [
                'style' => 'list-style:none; padding:0; margin:0;',
            ]);

            foreach ($bands as $band) {
                $label = html_writer::tag('strong', 'Minimum Score:', [
                    'style' => 'margin-right:0.35em;',
                ]);

                if ($editmode) {
                    $minval = ($band->threshold_min !== null)
                        ? rtrim(rtrim($band->threshold_min, '0'), '.') : '';
                    $mininput = html_writer::empty_tag('input', [
                        'type'        => 'number',
                        'step'        => '1',
                        'name'        => 'tmin[' . $band->id . ']',
                        'value'       => $minval,
                        'placeholder' => '—',
                        'style'       => 'width:5em;',
                    ]);
                    echo html_writer::empty_tag('input', [
                        'type' => 'hidden',
                        'name' => 'tmax[' . $band->id . ']',
                        'value' => '',
                    ]);
                    echo html_writer::tag(
                        'li',
                        $label . $mininput,
                        ['style' => 'padding:0.3rem 0;']
                    );
                } else {
                    $val = $band->threshold_min !== null
                        ? rtrim(rtrim($band->threshold_min, '0'), '.') : '—';
                    echo html_writer::tag(
                        'li',
                        $label . s($val),
                        ['style' => 'padding:0.2rem 0;']
                    );
                }
            }

            echo html_writer::end_tag('ul');
            echo html_writer::end_tag('div');
        }

        // IReady Lessons cards (MS only).
        // "by Grade": turn off "Days Since Lesson" for grades that take iReady
        // diagnostics but not iReady lessons.
        // "for RIMP Students": same, but only for students who have a RIMP.
        if ($schoollevel === 'ms') {
            $lessongrades   = stv_get_school_level_grades($schoollevel);
            $lessonsubjects = [
                'ela'  => get_string('lessonsoff_subject_ela', 'mod_mtssdashboard'),
                'math' => get_string('lessonsoff_subject_math', 'mod_mtssdashboard'),
            ];
            $lessoncards = [
                'lessonsoff_'     => false,
                'rimplessonsoff_' => true,
            ];

            foreach ($lessoncards as $prefix => $rimponly) {
                echo html_writer::start_tag('div', [
                    'class' => 'card stv-warning-card',
                    'style' => 'border:1px solid #dee2e6; border-radius:0.375rem; '
                             . 'padding:1rem; background:#fff;',
                ]);
                echo html_writer::tag('h5', get_string($prefix . 'heading', 'mod_mtssdashboard'), [
                    'style' => 'margin:0 0 0.75rem 0; font-weight:600;',
                ]);
                echo html_writer::tag(
                    'p',
                    get_string($prefix . 'desc', 'mod_mtssdashboard'),
                    ['style' => 'font-size:0.85em; color:#6c757d; margin-bottom:0.5rem;']
                );

                if ($editmode) {
                    echo html_writer::empty_tag('input', [
                        'type' => 'hidden', 'name' => $prefix . 'present', 'value' => 1,
                    ]);
                }

                echo html_writer::start_tag('ul', [
                    'style' => 'list-style:none; padding:0; margin:0;',
                ]);

                foreach ($lessonsubjects as $subject => $subjectlabel) {
                    $off   = stv_get_lessons_off_grades($schoollevel, $subject, $rimponly);
                    $label = html_writer::tag('strong', s($subjectlabel) . ':', [
                        'style' => 'margin-right:0.35em;',
                    ]);

                    if ($editmode) {
                        $boxes = '';
                        foreach ($lessongrades as $g) {
                            $attrs = [
                                'type'  => 'checkbox',
                                'name'  => $prefix . $subject . '[]',
                                'value' => $g,
                                'style' => 'margin-right:0.25em;',
                            ];
                            if (isset($off[$g])) {
                                $attrs['checked'] = 'checked';
                            }
                            $boxes .= html_writer::tag(
                                'label',
                                html_writer::empty_tag('input', $attrs)
                                . get_string('lessonsoff_grade', 'mod_mtssdashboard', $g),
                                ['style' => 'margin-right:0.75em; font-weight:normal;']
                            );
                        }
                        echo html_writer::tag(
                            'li',
                            $label . html_writer::empty_tag('br') . $boxes,
                            ['style' => 'padding:0.3rem 0;']
                        );
                    } else {
                        $text = empty($off)
                            ? get_string($prefix . 'none', 'mod_mtssdashboard')
                            : get_string('lessonsoff_grades', 'mod_mtssdashboard', implode(', ', $off));
                        echo html_writer::tag(
                            'li',
                            $label . s($text),
                            ['style' => 'padding:0.2rem 0;']
                        );
                    }
                }

                echo html_writer::end_tag('ul');
                echo html_writer::end_tag('div');
            }
        }

        // Save button when editing.
        if ($editmode) {
            $cancelurl = new moodle_url('/mod/mtssdashboard/view.php', [
                'id'   => $cm->id,
                'tab'  => 'warning',
            ]);

            echo html_writer::start_tag('div', [
                'style' => 'margin-top:1rem; display:flex; gap:0.5rem; align-items:flex-start;',
            ]);
            echo html_writer::empty_tag('input', [
                'type'  => 'submit',
                'value' => get_string('warning_btn_save', 'mod_mtssdashboard'),
                'class' => 'btn btn-primary',
            ]);
            echo html_writer::link(
                $cancelurl,
                get_string('warning_btn_cancel', 'mod_mtssdashboard'),
                ['class' => 'btn btn-secondary', 'style' => 'align-self:flex-start;']
            );
            echo html_writer::end_tag('div');

            echo html_writer::end_tag('form');
        }
    }
}


// Grade Updates tab.
if (
    $tab === 'gradeupdates'
        && in_array($schoollevel, ['hs', 'ms'])
        && stv_can_edit_warning_levels($course, $schoollevel)
) {
    echo html_writer::tag(
        'h3',
        get_string('tab_gradeupdates', 'mod_mtssdashboard'),
        ['style' => 'margin-bottom:0.5rem;']
    );

    $gmids = ($groupid > 0) ? array_keys(groups_get_members($groupid, 'u.id')) : [];
    $gudata = stv_get_grade_currency($schoollevel, $gmids);

    if (empty($gudata)) {
        echo $OUTPUT->notification(get_string('gu_no_courses', 'mod_mtssdashboard'), 'notifywarning');
    } else {
        $thstyle  = 'padding:0.5rem 0.3rem; border:1px solid #dee2e6; text-align:center;'
                  . ' font-weight:600; position:sticky; top:0; z-index:2; background:#f1f3f5;';
        $thlstyle = $thstyle . ' text-align:left;';
        $tdstyle  = 'padding:0.4rem 0.3rem; border:1px solid #dee2e6;'
                  . ' vertical-align:middle; text-align:center;';
        $tdlstyle = $tdstyle . ' text-align:left;';

        echo html_writer::start_tag('div', ['style' => 'overflow-x:auto; overflow-y:auto; max-height:70vh;']);
        echo html_writer::start_tag('table', ['style' => 'border-collapse:collapse; width:100%;']);

        // Header row.
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr', ['style' => 'background:#f1f3f5;']);
        echo html_writer::tag('th', get_string('gu_col_coursename', 'mod_mtssdashboard'), ['style' => $thlstyle . ' ' .
            'min-width:200px;']);
        echo html_writer::tag('th', get_string('gu_col_shortname', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::tag('th', get_string('gu_col_teachers', 'mod_mtssdashboard'), ['style' => $thlstyle . ' ' .
            'min-width:160px;']);
        echo html_writer::tag('th', get_string('gu_col_lastupdate', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::tag('th', get_string('gu_col_dayssince', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');

        // Data rows.
        echo html_writer::start_tag('tbody');
        foreach ($gudata as $row) {
            // Highlight rows by days since last grade update.
            if ($row->days_since > 45) {
                $rowbg = 'background:#f5c293;'; // Orange — very stale.
            } else if ($row->days_since > 21) {
                $rowbg = 'background:#ffeeba;'; // Yellow — getting stale.
            } else {
                $rowbg = '';
            }
            echo html_writer::start_tag('tr', ['style' => $rowbg]);
            echo html_writer::tag('td', s($row->fullname), ['style' => $tdlstyle]);
            echo html_writer::tag('td', s($row->shortname), ['style' => $tdstyle]);
            echo html_writer::tag('td', s($row->teachers), ['style' => $tdlstyle]);
            echo html_writer::tag('td', s($row->lastupdate), ['style' => $tdstyle]);
            echo html_writer::tag('td', (string) $row->days_since, ['style' => $tdstyle]);
            echo html_writer::end_tag('tr');
        }
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
        echo html_writer::end_tag('div');
    }
}

// MTSS tab.
if ($tab === 'rimp' && has_capability('mod/mtssdashboard:editrimp', $context)) {
    if (!isset($schoollevel)) {
        $schoollevel = stv_get_school_level_for_course($course);
    }

    echo html_writer::tag(
        'h3',
        get_string('tab_rimp', 'mod_mtssdashboard'),
        ['style' => 'margin-bottom:0.5rem;']
    );
    echo html_writer::tag(
        'p',
        get_string('rimp_tab_intro', 'mod_mtssdashboard'),
        ['style' => 'margin-bottom:1rem; color:#555;']
    );

    // Student selector.
    $selectedsn = optional_param('mtss_sn', 0, PARAM_INT);

    // Use $tabstudents (already group-filtered by the shared block above),
    // then apply the top demographic filters so the filter bar works here too.
    $mtssstudents = stv_filter_students(
        $tabstudents,
        $fgender,
        $frace,
        $fed,
        $fswd,
        $fel,
        $f504,
        $fiep,
        $fgifted
    );
    // Leave out CTC (J/NJ) and no-longer-attending (I/R) students.
    $mtssstudents = mtssdashboard_remove_skipped_students($mtssstudents);
    usort($mtssstudents, function ($a, $b) {
        $lc = strcasecmp($a->lastname, $b->lastname);
        return $lc !== 0 ? $lc : strcasecmp($a->firstname, $b->firstname);
    });

    // Build student number list for bulk RIMP fetch.
    $mtsssnlist = [];
    foreach ($mtssstudents as $s) {
        if (!empty($s->idnumber)) {
            $mtsssnlist[] = (int) $s->idnumber;
        }
    }

    // Bulk fetch existing RIMPs for these students.
    $mtssrimpmap = [];
    if (!empty($mtsssnlist)) {
        [$insql, $inparams] = $DB->get_in_or_equal($mtsssnlist, SQL_PARAMS_NAMED);
        $mtssrimps = $DB->get_records_select(
            'mtssdashboard_rimp',
            "studentnumber $insql",
            $inparams,
            'timemodified DESC'
        );
        // Key by studentnumber — keep the most recent per student.
        foreach ($mtssrimps as $r) {
            if (!isset($mtssrimpmap[$r->studentnumber])) {
                $mtssrimpmap[$r->studentnumber] = $r;
            }
        }
    }

    // Student selector form.
    $selectorurl = new moodle_url(
        '/mod/mtssdashboard/view.php',
        ['id' => $cm->id, 'tab' => 'rimp']
    );

    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => $selectorurl->out(false),
        'class'  => 'form-inline mb-3',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => 'rimp']);
    // Keep the applied demographic filters when View is pressed.
    foreach (['f_gender', 'f_race', 'f_ed', 'f_swd', 'f_el', 'f_504', 'f_iep', 'f_gifted'] as $fname) {
        if ($$fname !== '') {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $fname, 'value' => $$fname]);
        }
    }

    $stuopts = [0 => '— ' . get_string('rimp_select_student', 'mod_mtssdashboard') . ' —'];
    foreach ($mtssstudents as $s) {
        $sn = !empty($s->idnumber) ? (int) $s->idnumber : 0;
        $stuopts[$sn] = s($s->lastname . ', ' . $s->firstname)
            . ($sn > 0 ? ' (' . $sn . ')' : '');
    }
    echo html_writer::tag(
        'label',
        get_string('mtss_select_student', 'mod_mtssdashboard'),
        ['for' => 'mtss-student-select', 'class' => 'mr-2 font-weight-bold']
    );
    echo html_writer::select(
        $stuopts,
        'mtss_sn',
        $selectedsn,
        false,
        ['id' => 'mtss-student-select', 'class' => 'form-control mr-2']
    );
    echo html_writer::tag(
        'button',
        get_string('rimp_btn_go', 'mod_mtssdashboard'),
        ['type' => 'submit', 'class' => 'btn btn-secondary']
    );
    // Download RIMPs: every finalized RIMP for the students currently shown.
    $rimpbulkurl = new moodle_url($PAGE->url, ['action' => 'downloadrimps', 'sesskey' => sesskey()]);
    echo html_writer::link(
        $rimpbulkurl,
        get_string('rimp_btn_download', 'mod_mtssdashboard'),
        [
            'class' => 'btn btn-primary ml-2',
            'target' => '_blank',
            'title' => get_string('rimp_btn_download_help', 'mod_mtssdashboard'),
        ]
    );
    echo html_writer::end_tag('form');

    // Selected student action panel.
    if ($selectedsn > 0) {
        $selrimp    = $mtssrimpmap[$selectedsn] ?? null;
        $selstudent = null;
        foreach ($mtssstudents as $s) {
            if ((int)$s->idnumber === $selectedsn) {
                $selstudent = $s;
                break;
            }
        }

        if ($selstudent) {
            $editurl = new moodle_url(
                '/mod/mtssdashboard/rimp_edit.php',
                ['id' => $cm->id, 'sn' => $selectedsn]
            );

            echo html_writer::start_tag('div', ['class' => 'card mb-3']);
            echo html_writer::start_tag('div', ['class' => 'card-body']);

            echo html_writer::tag(
                'h5',
                s($selstudent->lastname . ', ' . $selstudent->firstname) .
                ' (' . $selectedsn . ')',
                ['class' => 'card-title']
            );

            if ($selrimp) {
                $statuslabel = ((int)$selrimp->status === 1)
                    ? html_writer::tag(
                        'span',
                        get_string('rimp_status_finalized', 'mod_mtssdashboard'),
                        ['class' => 'badge badge-success']
                    )
                    : html_writer::tag(
                        'span',
                        get_string('rimp_status_draft', 'mod_mtssdashboard'),
                        ['class' => 'badge badge-warning']
                    );

                $modified = userdate(
                    $selrimp->timemodified,
                    get_string('strftimedatetime', 'langconfig')
                );

                echo html_writer::tag(
                    'p',
                    get_string('rimp_status_label', 'mod_mtssdashboard') . ' ' .
                    $statuslabel . ' &nbsp; ' .
                    get_string('rimp_last_modified', 'mod_mtssdashboard') . ' ' .
                    s($modified),
                    ['class' => 'card-text']
                );

                if (!empty($selrimp->rimp_effective_date)) {
                    echo html_writer::tag(
                        'p',
                        get_string('rimp_effective_date', 'mod_mtssdashboard') . ': ' .
                        userdate(
                            $selrimp->rimp_effective_date,
                            get_string('strftimedate', 'langconfig')
                        ),
                        ['class' => 'card-text']
                    );
                }

                echo html_writer::tag(
                    'a',
                    get_string('rimp_btn_edit', 'mod_mtssdashboard'),
                    ['href' => $editurl->out(false), 'class' => 'btn btn-primary mr-2']
                );

                $printurl = new moodle_url(
                    '/mod/mtssdashboard/rimp_print.php',
                    ['id' => $cm->id, 'sn' => $selectedsn]
                );
                echo html_writer::tag(
                    'a',
                    get_string('rimp_btn_print', 'mod_mtssdashboard'),
                    ['href' => $printurl->out(false), 'class' => 'btn btn-outline-secondary',
                    'target' => '_blank']
                );
            } else {
                echo html_writer::tag(
                    'p',
                    get_string('rimp_none_exists', 'mod_mtssdashboard'),
                    ['class' => 'card-text text-muted']
                );
                echo html_writer::tag(
                    'a',
                    get_string('rimp_btn_new', 'mod_mtssdashboard'),
                    ['href' => $editurl->out(false), 'class' => 'btn btn-success']
                );
            }

            echo html_writer::end_tag('div'); // Card-body.
            echo html_writer::end_tag('div'); // Card.
        }
    }

    // Class RIMP status table.
    echo html_writer::tag(
        'h4',
        get_string('rimp_class_table_heading', 'mod_mtssdashboard'),
        ['class' => 'mt-3 mb-2']
    );

    if (empty($mtssstudents)) {
        echo $OUTPUT->notification(get_string('ew_no_students', 'mod_mtssdashboard'), 'notifywarning');
    } else {
        $thstyle = 'padding:0.5rem; border:1px solid #dee2e6; text-align:left;'
                 . ' font-weight:600; background:#f1f3f5;';
        $tdstyle = 'padding:0.4rem 0.5rem; border:1px solid #dee2e6; vertical-align:middle;';

        echo html_writer::start_tag('div', ['style' => 'overflow-x:auto;']);
        echo html_writer::start_tag('table', [
            'class' => 'generaltable',
            'style' => 'width:100%; border-collapse:collapse; font-size:0.9em;',
        ]);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', get_string('ew_col_name', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::tag('th', get_string('ew_col_studentid', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::tag('th', get_string('rimp_status_label', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::tag('th', get_string('rimp_last_modified', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::tag('th', get_string('last_edited_by', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::tag('th', get_string('rimp_col_action', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');

        // Pre-fetch usermodified display names for all RIMP records.
        $rimpeditorids = array_filter(array_unique(array_column(
            array_values((array)$mtssrimpmap),
            'usermodified'
        )));
        $rimpeditormap = [];
        if (!empty($rimpeditorids)) {
            [$einsql, $einparams] = $DB->get_in_or_equal($rimpeditorids, SQL_PARAMS_NAMED);
            $rimpeditors = $DB->get_records_select(
                'user',
                "id $einsql",
                $einparams,
                '',
                'id, firstname, lastname'
            );
            foreach ($rimpeditors as $eu) {
                $rimpeditormap[$eu->id] = fullname($eu);
            }
        }

        foreach ($mtssstudents as $s) {
            $sn       = !empty($s->idnumber) ? (int) $s->idnumber : 0;
            $rowrimp = $mtssrimpmap[$sn] ?? null;

            if ($rowrimp) {
                $rstatus = ((int)$rowrimp->status === 1)
                    ? html_writer::tag(
                        'span',
                        get_string('rimp_status_finalized', 'mod_mtssdashboard'),
                        ['class' => 'badge badge-success']
                    )
                    : html_writer::tag(
                        'span',
                        get_string('rimp_status_draft', 'mod_mtssdashboard'),
                        ['class' => 'badge badge-warning']
                    );
                $rmod    = userdate(
                    $rowrimp->timemodified,
                    get_string('strftimedate', 'langconfig')
                );
                $reditor = !empty($rowrimp->usermodified)
                    ? ($rimpeditormap[$rowrimp->usermodified] ?? '—') : '—';
                $rowbg   = ((int)$rowrimp->status === 1)
                    ? 'background:#d4edda;'
                    : 'background:#fff3cd;';
            } else {
                $rstatus = html_writer::tag(
                    'span',
                    get_string('rimp_none_short', 'mod_mtssdashboard'),
                    ['class' => 'badge badge-secondary']
                );
                $rmod    = '—';
                $reditor = '—';
                $rowbg   = '';
            }

            $editurl = new moodle_url(
                '/mod/mtssdashboard/rimp_edit.php',
                ['id' => $cm->id, 'sn' => $sn]
            );
            $actionlink = $rowrimp
                ? html_writer::tag(
                    'a',
                    get_string('rimp_btn_edit', 'mod_mtssdashboard'),
                    ['href' => $editurl->out(false), 'class' => 'btn btn-sm btn-primary']
                )
                : html_writer::tag(
                    'a',
                    get_string('rimp_btn_new', 'mod_mtssdashboard'),
                    ['href' => $editurl->out(false), 'class' => 'btn btn-sm btn-success']
                );

            echo html_writer::start_tag('tr', ['style' => $rowbg]);
            echo html_writer::tag('td', s($s->lastname . ', ' . $s->firstname), ['style' => $tdstyle]);
            echo html_writer::tag('td', $sn > 0 ? (string) $sn : '—', ['style' => $tdstyle]);
            echo html_writer::tag('td', $rstatus, ['style' => $tdstyle]);
            echo html_writer::tag('td', $rmod, ['style' => $tdstyle]);
            echo html_writer::tag('td', s($reditor), ['style' => $tdstyle]);
            echo html_writer::tag('td', $actionlink, ['style' => $tdstyle]);
            echo html_writer::end_tag('tr');
        }

        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
        echo html_writer::end_tag('div');
    }
}
// END MTSS tab.

// Plans tab.
if ($tab === 'plans') {
    echo html_writer::tag(
        'h3',
        get_string('tab_plans', 'mod_mtssdashboard'),
        ['style' => 'margin-bottom:0.5rem;']
    );
    echo html_writer::tag(
        'p',
        get_string('plans_intro', 'mod_mtssdashboard'),
        ['style' => 'margin-bottom:1rem; color:#555;']
    );

    // Group filter.
    echo groups_print_activity_menu(
        $cm,
        new moodle_url('/mod/mtssdashboard/view.php', ['id' => $cm->id, 'tab' => 'plans']),
        true
    );

    // Apply group and demographic filters using the same $allstudents list.
    $planstudents = stv_filter_students(
        $allstudents,
        $fgender,
        $frace,
        $fed,
        $fswd,
        $fel,
        $f504,
        $fiep,
        $fgifted
    );

    if ($groupid > 0) {
        $gmids = array_keys(groups_get_members($groupid, 'u.id'));
        $planstudents = array_filter($planstudents, function ($s) use ($gmids) {
            return in_array($s->userid, $gmids);
        });
    }

    usort($planstudents, function ($a, $b) {
        $lc = strcasecmp($a->lastname, $b->lastname);
        return $lc !== 0 ? $lc : strcasecmp($a->firstname, $b->firstname);
    });

    // Pre-load plan files — keyed by student number.
    $plansdir  = $CFG->dataroot . '/NTimports/plans/';
    $planfiles = [];
    if (is_dir($plansdir)) {
        foreach (glob($plansdir . '*.pdf') as $f) {
            $sn = (int) pathinfo($f, PATHINFO_FILENAME);
            $planfiles[$sn] = filemtime($f);
        }
    }
    $planbaseurl = new moodle_url('/mod/mtssdashboard/plan.php', ['cmid' => $cm->id]);

    if (empty($planstudents)) {
        echo $OUTPUT->notification(get_string('ew_no_students', 'mod_mtssdashboard'), 'notifywarning');
    } else {
        $thstyle = 'padding:0.5rem 0.4rem; border:1px solid #dee2e6; text-align:left;'
                 . ' font-weight:600; background:#f1f3f5; white-space:nowrap;';
        $tdstyle = 'padding:0.4rem 0.5rem; border:1px solid #dee2e6; vertical-align:middle;';

        echo html_writer::start_tag('div', ['style' => 'overflow-x:auto;']);
        echo html_writer::start_tag('table', [
            'class' => 'generaltable',
            'style' => 'width:100%; border-collapse:collapse; font-size:0.9em;',
        ]);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', get_string('ew_col_name', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::tag('th', get_string('ew_col_grade', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::tag('th', get_string('ew_col_studentid', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::tag('th', get_string('plans_col_status', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::tag(
            'th',
            html_writer::tag(
                'a',
                get_string('plans_col_plan', 'mod_mtssdashboard') . ' &#x25B4;&#x25BE;',
                ['href' => '#', 'id' => 'plans-sort-btn',
                'style' => 'text-decoration:none; color:inherit; white-space:nowrap;']
            ),
            ['style' => $thstyle]
        );
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');

        foreach ($planstudents as $s) {
            $sn = !empty($s->idnumber) ? (int) $s->idnumber : 0;

            // Status badges.
            $badges = '';
            if (!empty($s->iep) && strtoupper($s->iep) === 'Y') {
                $badges .= html_writer::tag(
                    'span',
                    'IEP',
                    ['class' => 'badge badge-danger mr-1',
                    'style' => 'font-size:0.85em;']
                );
            }
            if (!empty($s->status504) && strtoupper($s->status504) === 'Y') {
                $badges .= html_writer::tag(
                    'span',
                    '504',
                    ['class' => 'badge badge-warning mr-1',
                    'style' => 'font-size:0.85em;']
                );
            }
            if (!empty($s->gifted) && strtoupper($s->gifted) === 'Y') {
                $badges .= html_writer::tag(
                    'span',
                    'Gifted',
                    ['class' => 'badge badge-info mr-1',
                    'style' => 'font-size:0.85em;']
                );
            }
            if (empty($badges)) {
                $badges = html_writer::tag(
                    'span',
                    '—',
                    ['style' => 'color:#999;']
                );
            }

            // Plan link.
            $plantimestamp = 0;
            $plancellbg     = '';
            if ($sn > 0 && isset($planfiles[$sn])) {
                $plantimestamp = $planfiles[$sn];
                $planurl  = new moodle_url($planbaseurl, ['ssid' => $sn]);
                $filedate = userdate($plantimestamp, get_string('strftimedate', 'langconfig'));
                $planlink = html_writer::tag(
                    'a',
                    $filedate,
                    ['href'   => $planurl->out(false),
                     'target' => '_blank',
                     'style'  => 'font-weight:600; color:#0f6cbf;',
                    'title'  => get_string(
                        'plan_view',
                        'mod_mtssdashboard'
                    )]
                );
                if ($plantimestamp < (time() - 365 * 24 * 60 * 60)) {
                    $plancellbg = ' background:#ffe0b2;';
                }
            } else {
                $planlink = html_writer::tag(
                    'span',
                    get_string('plans_no_plan', 'mod_mtssdashboard'),
                    ['style' => 'color:#999;']
                );
            }

            echo html_writer::start_tag('tr', ['data-plandate' => $plantimestamp]);
            echo html_writer::tag(
                'td',
                s($s->lastname . ', ' . $s->firstname),
                ['style' => $tdstyle]
            );
            echo html_writer::tag(
                'td',
                s($s->gradelevel ?? ''),
                ['style' => $tdstyle . ' text-align:center;']
            );
            echo html_writer::tag(
                'td',
                $sn > 0 ? (string)$sn : '—',
                ['style' => $tdstyle . ' text-align:center;']
            );
            echo html_writer::tag('td', $badges, ['style' => $tdstyle]);
            echo html_writer::tag(
                'td',
                $planlink,
                ['style' => $tdstyle . $plancellbg]
            );
            echo html_writer::end_tag('tr');
        }

        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
        echo html_writer::end_tag('div');
    }

    echo html_writer::script("
(function() {
    var btn   = document.getElementById('plans-sort-btn');
    if (!btn) { return; }
    var asc   = true;
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var tbody = btn.closest('table').querySelector('tbody');
        var rows  = Array.from(tbody.querySelectorAll('tr'));
        rows.sort(function(a, b) {
            var da = parseInt(a.getAttribute('data-plandate'), 10) || 0;
            var db = parseInt(b.getAttribute('data-plandate'), 10) || 0;
            return asc ? da - db : db - da;
        });
        asc = !asc;
        rows.forEach(function(r) { tbody.appendChild(r); });
    });
})();
");
}
// END Plans tab.

// WEP tab.
if ($tab === 'wep' && has_capability('mod/mtssdashboard:editwep', $context)) {
    if (!isset($schoollevel)) {
        $schoollevel = stv_get_school_level_for_course($course);
    }

    echo html_writer::tag(
        'h3',
        get_string('tab_wep', 'mod_mtssdashboard'),
        ['style' => 'margin-bottom:0.5rem;']
    );
    echo html_writer::tag(
        'p',
        get_string('wep_tab_intro', 'mod_mtssdashboard'),
        ['style' => 'margin-bottom:1rem; color:#555;']
    );

    // Student selector.
    $selectedwepsn = optional_param('wep_sn', 0, PARAM_INT);

    // Use $tabstudents (already group-filtered by the shared block above),
    // then apply the top demographic filters so the filter bar works here too.
    $wepstudents = stv_filter_students(
        $tabstudents,
        $fgender,
        $frace,
        $fed,
        $fswd,
        $fel,
        $f504,
        $fiep,
        $fgifted
    );
    // Leave out CTC (J/NJ) and no-longer-attending (I/R) students.
    $wepstudents = mtssdashboard_remove_skipped_students($wepstudents);
    usort($wepstudents, function ($a, $b) {
        $lc = strcasecmp($a->lastname, $b->lastname);
        return $lc !== 0 ? $lc : strcasecmp($a->firstname, $b->firstname);
    });

    // Build student number list for bulk WEP fetch.
    $wepsnlist = [];
    foreach ($wepstudents as $s) {
        if (!empty($s->idnumber)) {
            $wepsnlist[] = (int) $s->idnumber;
        }
    }

    // Bulk fetch most-recent WEP per student.
    $wepmap = [];
    if (!empty($wepsnlist)) {
        [$insql, $inparams] = $DB->get_in_or_equal($wepsnlist, SQL_PARAMS_NAMED);
        $weprecords = $DB->get_records_select(
            'mtssdashboard_wep',
            "studentnumber $insql",
            $inparams,
            'timemodified DESC'
        );
        foreach ($weprecords as $w) {
            if (!isset($wepmap[$w->studentnumber])) {
                $wepmap[$w->studentnumber] = $w;
            }
        }
    }

    // Student selector form.
    $wepselectorurl = new moodle_url(
        '/mod/mtssdashboard/view.php',
        ['id' => $cm->id, 'tab' => 'wep']
    );

    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => $wepselectorurl->out(false),
        'class'  => 'form-inline mb-3',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => 'wep']);
    // Keep the applied demographic filters when View is pressed.
    foreach (['f_gender', 'f_race', 'f_ed', 'f_swd', 'f_el', 'f_504', 'f_iep', 'f_gifted'] as $fname) {
        if ($$fname !== '') {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $fname, 'value' => $$fname]);
        }
    }

    $wepstuopts = [0 => '— ' . get_string('wep_select_student', 'mod_mtssdashboard') . ' —'];
    foreach ($wepstudents as $s) {
        $sn = !empty($s->idnumber) ? (int) $s->idnumber : 0;
        $wepstuopts[$sn] = s($s->lastname . ', ' . $s->firstname)
            . ($sn > 0 ? ' (' . $sn . ')' : '');
    }
    echo html_writer::tag(
        'label',
        get_string('wep_select_student', 'mod_mtssdashboard'),
        ['for' => 'wep-student-select', 'class' => 'mr-2 font-weight-bold']
    );
    echo html_writer::select(
        $wepstuopts,
        'wep_sn',
        $selectedwepsn,
        false,
        ['id' => 'wep-student-select', 'class' => 'form-control mr-2']
    );
    echo html_writer::tag(
        'button',
        get_string('wep_btn_go', 'mod_mtssdashboard'),
        ['type' => 'submit', 'class' => 'btn btn-secondary']
    );
    // Download WEPs: every finalized WEP for the students currently shown.
    $wepbulkurl = new moodle_url($PAGE->url, ['action' => 'downloadweps', 'sesskey' => sesskey()]);
    echo html_writer::link(
        $wepbulkurl,
        get_string('wep_btn_download', 'mod_mtssdashboard'),
        [
            'class' => 'btn btn-primary ml-2',
            'target' => '_blank',
            'title' => get_string('wep_btn_download_help', 'mod_mtssdashboard'),
        ]
    );
    echo html_writer::end_tag('form');

    // Selected student action panel.
    if ($selectedwepsn > 0) {
        $selwep     = $wepmap[$selectedwepsn] ?? null;
        $selstudent = null;
        foreach ($wepstudents as $s) {
            if ((int)$s->idnumber === $selectedwepsn) {
                $selstudent = $s;
                break;
            }
        }

        if ($selstudent) {
            $wepediturl = new moodle_url(
                '/mod/mtssdashboard/wep_edit.php',
                ['id' => $cm->id, 'sn' => $selectedwepsn]
            );

            echo html_writer::start_tag('div', ['class' => 'card mb-3']);
            echo html_writer::start_tag('div', ['class' => 'card-body']);

            echo html_writer::tag(
                'h5',
                s($selstudent->lastname . ', ' . $selstudent->firstname) .
                ' (' . $selectedwepsn . ')',
                ['class' => 'card-title']
            );

            if ($selwep) {
                $wepstatuslabel = ((int)$selwep->status === 1)
                    ? html_writer::tag(
                        'span',
                        get_string('wep_status_finalized', 'mod_mtssdashboard'),
                        ['class' => 'badge badge-success']
                    )
                    : html_writer::tag(
                        'span',
                        get_string('wep_status_draft', 'mod_mtssdashboard'),
                        ['class' => 'badge badge-warning']
                    );

                $wepmodified = userdate(
                    $selwep->timemodified,
                    get_string('strftimedatetime', 'langconfig')
                );

                echo html_writer::tag(
                    'p',
                    get_string('wep_status_label', 'mod_mtssdashboard') . ' ' .
                    $wepstatuslabel . ' &nbsp; ' .
                    get_string('wep_last_modified', 'mod_mtssdashboard') . ' ' .
                    s($wepmodified),
                    ['class' => 'card-text']
                );

                echo html_writer::tag(
                    'a',
                    get_string('wep_btn_edit', 'mod_mtssdashboard'),
                    ['href' => $wepediturl->out(false), 'class' => 'btn btn-primary mr-2']
                );

                $wepprinturl = new moodle_url(
                    '/mod/mtssdashboard/wep_print.php',
                    ['id' => $cm->id, 'sn' => $selectedwepsn]
                );
                echo html_writer::tag(
                    'a',
                    get_string('wep_btn_print', 'mod_mtssdashboard'),
                    ['href' => $wepprinturl->out(false), 'class' => 'btn btn-outline-secondary',
                    'target' => '_blank']
                );
            } else {
                echo html_writer::tag(
                    'p',
                    get_string('wep_none_exists', 'mod_mtssdashboard'),
                    ['class' => 'card-text text-muted']
                );
                echo html_writer::tag(
                    'a',
                    get_string('wep_btn_new', 'mod_mtssdashboard'),
                    ['href' => $wepediturl->out(false), 'class' => 'btn btn-success']
                );
            }

            echo html_writer::end_tag('div'); // Card-body.
            echo html_writer::end_tag('div'); // Card.
        }
    }

    // Class WEP status table.
    echo html_writer::tag(
        'h4',
        get_string('wep_class_table_heading', 'mod_mtssdashboard'),
        ['class' => 'mt-3 mb-2']
    );

    if (empty($wepstudents)) {
        echo $OUTPUT->notification(get_string('ew_no_students', 'mod_mtssdashboard'), 'notifywarning');
    } else {
        $thstyle = 'padding:0.5rem; border:1px solid #dee2e6; text-align:left;'
                 . ' font-weight:600; background:#f1f3f5;';
        $tdstyle = 'padding:0.4rem 0.5rem; border:1px solid #dee2e6; vertical-align:middle;';

        echo html_writer::start_tag('div', ['style' => 'overflow-x:auto;']);
        echo html_writer::start_tag('table', [
            'class' => 'generaltable',
            'style' => 'width:100%; border-collapse:collapse; font-size:0.9em;',
        ]);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', get_string('ew_col_name', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::tag('th', get_string('ew_col_studentid', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::tag('th', get_string('wep_status_label', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::tag('th', get_string('wep_last_modified', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::tag('th', get_string('last_edited_by', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::tag('th', get_string('wep_col_action', 'mod_mtssdashboard'), ['style' => $thstyle]);
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');

        // Pre-fetch usermodified display names for all WEP records.
        $wepeditorids = array_filter(array_unique(array_column(
            array_values((array)$wepmap),
            'usermodified'
        )));
        $wepeditormap = [];
        if (!empty($wepeditorids)) {
            [$weinsql, $weinparams] = $DB->get_in_or_equal($wepeditorids, SQL_PARAMS_NAMED);
            $wepeditors = $DB->get_records_select(
                'user',
                "id $weinsql",
                $weinparams,
                '',
                'id, firstname, lastname'
            );
            foreach ($wepeditors as $eu) {
                $wepeditormap[$eu->id] = fullname($eu);
            }
        }

        foreach ($wepstudents as $s) {
            $sn      = !empty($s->idnumber) ? (int) $s->idnumber : 0;
            $rowwep = $wepmap[$sn] ?? null;

            if ($rowwep) {
                $wstatus = ((int)$rowwep->status === 1)
                    ? html_writer::tag(
                        'span',
                        get_string('wep_status_finalized', 'mod_mtssdashboard'),
                        ['class' => 'badge badge-success']
                    )
                    : html_writer::tag(
                        'span',
                        get_string('wep_status_draft', 'mod_mtssdashboard'),
                        ['class' => 'badge badge-warning']
                    );
                $wmod    = userdate(
                    $rowwep->timemodified,
                    get_string('strftimedate', 'langconfig')
                );
                $weditor = !empty($rowwep->usermodified)
                    ? ($wepeditormap[$rowwep->usermodified] ?? '—') : '—';
                $rowbg   = ((int)$rowwep->status === 1)
                    ? 'background:#d4edda;'
                    : 'background:#fff3cd;';
            } else {
                $wstatus = html_writer::tag(
                    'span',
                    get_string('wep_none_short', 'mod_mtssdashboard'),
                    ['class' => 'badge badge-secondary']
                );
                $wmod    = '—';
                $weditor = '—';
                $rowbg   = '';
            }

            $wepediturl = new moodle_url(
                '/mod/mtssdashboard/wep_edit.php',
                ['id' => $cm->id, 'sn' => $sn]
            );
            $wepactionlink = $rowwep
                ? html_writer::tag(
                    'a',
                    get_string('wep_btn_edit', 'mod_mtssdashboard'),
                    ['href' => $wepediturl->out(false), 'class' => 'btn btn-sm btn-primary']
                )
                : html_writer::tag(
                    'a',
                    get_string('wep_btn_new', 'mod_mtssdashboard'),
                    ['href' => $wepediturl->out(false), 'class' => 'btn btn-sm btn-success']
                );

            echo html_writer::start_tag('tr', ['style' => $rowbg]);
            echo html_writer::tag('td', s($s->lastname . ', ' . $s->firstname), ['style' => $tdstyle]);
            echo html_writer::tag('td', $sn > 0 ? (string) $sn : '—', ['style' => $tdstyle]);
            echo html_writer::tag('td', $wstatus, ['style' => $tdstyle]);
            echo html_writer::tag('td', $wmod, ['style' => $tdstyle]);
            echo html_writer::tag('td', s($weditor), ['style' => $tdstyle]);
            echo html_writer::tag('td', $wepactionlink, ['style' => $tdstyle]);
            echo html_writer::end_tag('tr');
        }

        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
        echo html_writer::end_tag('div');
    }
}
// END WEP tab.

echo $OUTPUT->footer();
