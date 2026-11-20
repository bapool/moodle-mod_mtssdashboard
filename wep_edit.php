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
 * WEP create / edit page for mod_mtssdashboard.
 *
 * @package    mod_mtssdashboard
 * @copyright  2026 National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/mtssdashboard/lib.php');

// Parameters.
$id            = required_param('id', PARAM_INT);
$studentnumber = required_param('sn', PARAM_INT);
$action        = optional_param('action', '', PARAM_ALPHANUMEXT);

// Bootstrap.
$cm       = get_coursemodule_from_id('mtssdashboard', $id, 0, false, MUST_EXIST);
$course   = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('mtssdashboard', ['id' => $cm->instance], '*', MUST_EXIST);
$context  = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/mtssdashboard:editwep', $context);

$schoollevel = stv_get_school_level_for_course($course);
$recordtype  = 'wep';

// Look up student.
$student = $DB->get_record_select(
    'user',
    "idnumber = :sn AND deleted = 0",
    ['sn' => $studentnumber]
);
if (!$student) {
    throw new moodle_exception('invalidstudent', 'mod_mtssdashboard');
}

// Fetch existing WEP.
$wep = $DB->get_record_select(
    'mtssdashboard_wep',
    'studentnumber = :sn',
    ['sn' => $studentnumber]
);

// Handle POST save.
if ($action === 'savewep' && confirm_sesskey()) {
    $finalize = optional_param('finalize', 0, PARAM_INT);
    $now      = time();

    // Concurrency safety net: if someone else saved this WEP since this form
    // was loaded, don't silently clobber it. In normal use the edit lock below
    // prevents this scenario entirely; this only catches the rare race where
    // two people load the form within the same instant.
    $locktimemodified = optional_param('lock_timemodified', 0, PARAM_INT);
    if ($wep && (int) $wep->timemodified !== $locktimemodified) {
        stv_release_edit_lock($studentnumber, $recordtype, $USER->id);
        $redirecturl = new moodle_url(
            '/mod/mtssdashboard/view.php',
            ['id' => $cm->id, 'tab' => 'wep']
        );
        redirect(
            $redirecturl,
            get_string('wep_edit_conflict', 'mod_mtssdashboard'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $idareasraw    = optional_param_array('identification_areas', [], PARAM_ALPHANUMEXT);
    $svcraw         = optional_param_array('service_setting', [], PARAM_ALPHANUMEXT);
    $progressraw    = optional_param_array('progress_measures', [], PARAM_ALPHANUMEXT);
    $personnelraw   = optional_param_array('personnel', [], PARAM_ALPHANUMEXT);

    $reviewstr = optional_param('wep_review_date', '', PARAM_TEXT);
    $reviewts  = !empty($reviewstr) ? strtotime($reviewstr) : null;

    $record = (object) [
        'studentnumber'            => $studentnumber,
        'schoollevel'              => $schoollevel,
        'schoolyear'               => optional_param('schoolyear', '', PARAM_TEXT),
        'wep_review_date'          => $reviewts ?: null,
        'identification_areas'     => json_encode(array_values($idareasraw)),
        'service_setting'          => json_encode(array_values($svcraw)),
        'progress_measures'        => json_encode(array_values($progressraw)),
        'personnel'                => json_encode(array_values($personnelraw)),
        'goal_reading_writing'     => optional_param('goal_reading_writing', '', PARAM_TEXT),
        'goal_mathematics'         => optional_param('goal_mathematics', '', PARAM_TEXT),
        'goal_science'             => optional_param('goal_science', '', PARAM_TEXT),
        'goal_social_studies'      => optional_param('goal_social_studies', '', PARAM_TEXT),
        'midyear_reading'          => optional_param('midyear_reading', '', PARAM_TEXT),
        'midyear_mathematics'      => optional_param('midyear_mathematics', '', PARAM_TEXT),
        'midyear_science'          => optional_param('midyear_science', '', PARAM_TEXT),
        'midyear_social_studies'   => optional_param('midyear_social_studies', '', PARAM_TEXT),
        'endofyear_reading'        => optional_param('endofyear_reading', '', PARAM_TEXT),
        'endofyear_mathematics'    => optional_param('endofyear_mathematics', '', PARAM_TEXT),
        'endofyear_science'        => optional_param('endofyear_science', '', PARAM_TEXT),
        'endofyear_social_studies' => optional_param('endofyear_social_studies', '', PARAM_TEXT),
        'parent_input'             => optional_param('parent_input', '', PARAM_TEXT),
        'sig_parent'               => optional_param('sig_parent', '', PARAM_TEXT),
        'status'                   => $finalize ? 1 : 0,
        'timemodified'             => $now,
        'usermodified'             => $USER->id,
    ];

    if ($wep) {
        $record->id          = $wep->id;
        $record->timecreated = $wep->timecreated;
        $DB->update_record('mtssdashboard_wep', $record);
    } else {
        $record->timecreated = $now;
        $DB->insert_record('mtssdashboard_wep', $record);
    }

    stv_release_edit_lock($studentnumber, $recordtype, $USER->id);

    $redirecturl = new moodle_url(
        '/mod/mtssdashboard/view.php',
        ['id' => $cm->id, 'tab' => 'wep']
    );
    $msg = $finalize
        ? get_string('wep_finalized', 'mod_mtssdashboard')
        : get_string('wep_saved_draft', 'mod_mtssdashboard');
    redirect($redirecturl, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
}

// Edit lock: block if someone else is already editing this WEP.
$activelock = stv_get_edit_lock($studentnumber, $recordtype);
if ($activelock && (int) $activelock->userid !== (int) $USER->id) {
    $holder     = $DB->get_record('user', ['id' => $activelock->userid]);
    $holdername = $holder ? fullname($holder) : get_string('edit_lock_unknown_user', 'mod_mtssdashboard');
    $viewurl    = new moodle_url('/mod/mtssdashboard/view.php', ['id' => $id, 'tab' => 'wep']);

    $PAGE->set_url('/mod/mtssdashboard/wep_edit.php', ['id' => $id, 'sn' => $studentnumber]);
    $PAGE->set_title(get_string('wep_lock_title', 'mod_mtssdashboard'));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->set_context($context);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('wep_lock_title', 'mod_mtssdashboard'), 3);
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning mt-3']);
    echo html_writer::tag('p', get_string('wep_lock_message', 'mod_mtssdashboard', s($holdername)));
    echo html_writer::tag(
        'button',
        get_string('wep_lock_ok', 'mod_mtssdashboard'),
        ['type' => 'button', 'class' => 'btn btn-primary', 'id' => 'wep-lock-ok-btn']
    );
    echo html_writer::end_tag('div');

    echo html_writer::script("
    (function() {
        var btn = document.getElementById('wep-lock-ok-btn');
        if (btn) {
            btn.addEventListener('click', function() {
                window.close();
                setTimeout(function() {
                    window.location.href = " . json_encode($viewurl->out(false)) . ";
                }, 250);
            });
        }
    })();
    ");

    echo $OUTPUT->footer();
    die();
}

// No one else is editing — claim (or refresh) the lock for this user.
stv_acquire_edit_lock($studentnumber, $recordtype, $USER->id);

// Page setup.
$PAGE->set_url('/mod/mtssdashboard/wep_edit.php', ['id' => $id, 'sn' => $studentnumber]);
$PAGE->set_title(get_string('wep_edit_title', 'mod_mtssdashboard'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->navbar->add(
    format_string($instance->name),
    new moodle_url('/mod/mtssdashboard/view.php', ['id' => $id, 'tab' => 'wep'])
);
$PAGE->navbar->add(get_string('wep_edit_title', 'mod_mtssdashboard'));

/**
 * Decode a saved JSON array.
 *
 * @param string|null $json JSON text from the database.
 * @return array
 */
function wep_decode_array($json) {
    if (empty($json)) {
        return [];
    }
    $d = json_decode($json, true);
    return is_array($d) ? $d : [];
}

$savedidareas  = $wep ? wep_decode_array($wep->identification_areas) : [];
$savedsvc       = $wep ? wep_decode_array($wep->service_setting) : [];
$savedprogress  = $wep ? wep_decode_array($wep->progress_measures) : [];
$savedpersonnel = $wep ? wep_decode_array($wep->personnel) : [];

// Checkbox option definitions.

// Identification areas — same for both ES and MS/HS.
$idareaoptions = [
    'superior_cognitive' => get_string('wep_id_superior_cognitive', 'mod_mtssdashboard'),
    'superior_creative'  => get_string('wep_id_superior_creative', 'mod_mtssdashboard'),
    'reading_writing'    => get_string('wep_id_reading_writing', 'mod_mtssdashboard'),
    'mathematics'        => get_string('wep_id_mathematics', 'mod_mtssdashboard'),
    'science'            => get_string('wep_id_science', 'mod_mtssdashboard'),
    'social_studies'     => get_string('wep_id_social_studies', 'mod_mtssdashboard'),
];

// Service settings differ by school level.
if ($schoollevel === 'es') {
    $svcoptions = [
        'cluster_es' => get_string('wep_svc_cluster_es', 'mod_mtssdashboard'),
        'other'      => get_string('wep_svc_other', 'mod_mtssdashboard'),
    ];
} else {
    $svcoptions = [
        'cluster_mshs'  => get_string('wep_svc_cluster_mshs', 'mod_mtssdashboard'),
        'acceleration'  => get_string('wep_svc_acceleration', 'mod_mtssdashboard'),
        'college_credit' => get_string('wep_svc_college_credit', 'mod_mtssdashboard'),
        'other'         => get_string('wep_svc_other', 'mod_mtssdashboard'),
    ];
}

// Progress measures — same for both levels.
$progressoptions = [
    'observations'  => get_string('wep_pm_observations', 'mod_mtssdashboard'),
    'projects'      => get_string('wep_pm_projects', 'mod_mtssdashboard'),
    'benchmarks'    => get_string('wep_pm_benchmarks', 'mod_mtssdashboard'),
    'presentations' => get_string('wep_pm_presentations', 'mod_mtssdashboard'),
    'assessments'   => get_string('wep_pm_assessments', 'mod_mtssdashboard'),
    'other'         => get_string('wep_pm_other', 'mod_mtssdashboard'),
];

// Personnel — same for both levels.
$personneloptions = [
    'gen_ed' => get_string('wep_per_gen_ed', 'mod_mtssdashboard'),
];

$isfinalized = $wep && (int)$wep->status === 1;
$statuslabel = $isfinalized
    ? get_string('wep_status_finalized', 'mod_mtssdashboard')
    : get_string('wep_status_draft', 'mod_mtssdashboard');

$reviewdatedisplay = ($wep && !empty($wep->wep_review_date))
    ? date('Y-m-d', $wep->wep_review_date)
    : '';

// Current school year (Jul–Jun) and today's date, used by the Reset WEP action.
$resetyearstart   = ((int) date('n') >= 7) ? (int) date('Y') : (int) date('Y') - 1;
$currentschoolyear = $resetyearstart . '-' . ($resetyearstart + 1);
$todayiso          = date('Y-m-d');

echo $OUTPUT->header();
echo $OUTPUT->heading(fullname($student) . ' — ' .
    get_string('wep_edit_title', 'mod_mtssdashboard'), 3);

$badgeclass = $isfinalized ? 'badge badge-success' : 'badge badge-warning';
echo html_writer::tag(
    'span',
    $statuslabel,
    ['class' => $badgeclass, 'style' => 'font-size:1em; margin-bottom:1rem; display:inline-block;']
);

// Rendering helpers — same pattern as rimp_edit.php.
$winput = function ($name, $label, $value = '', $type = 'text', $extra = []) {
    $out  = html_writer::start_tag('div', ['class' => 'form-group row fitem']);
    $out .= html_writer::tag(
        'div',
        html_writer::tag(
            'label',
            $label,
            ['for' => 'wep_' . $name, 'class' => 'col-form-label']
        ),
        ['class' => 'col-md-3']
    );
    $attrs = array_merge([
        'type'  => $type, 'name' => $name,
        'id'    => 'wep_' . $name, 'value' => $value, 'class' => 'form-control',
    ], $extra);
    $out .= html_writer::tag(
        'div',
        html_writer::empty_tag('input', $attrs),
        ['class' => 'col-md-9']
    );
    $out .= html_writer::end_tag('div');
    return $out;
};

$wcheckboxgroup = function ($name, $label, $options, $checked = []) {
    $out  = html_writer::start_tag('div', ['class' => 'form-group row fitem']);
    $out .= html_writer::tag(
        'div',
        html_writer::tag('span', $label, ['class' => 'col-form-label']),
        ['class' => 'col-md-3']
    );
    $cbhtml = '';
    foreach ($options as $val => $display) {
        $cbid  = 'wep_' . $name . '_' . preg_replace('/[^a-z0-9]/i', '_', $val);
        $attrs = [
            'type'  => 'checkbox',
            'name'  => $name . '[]',
            'id'    => $cbid,
            'value' => $val,
            'class' => 'mr-1',
        ];
        if (in_array($val, $checked, true)) {
            $attrs['checked'] = 'checked';
        }
        $cbhtml .= html_writer::start_tag('div', ['class' => 'form-check']);
        $cbhtml .= html_writer::empty_tag('input', $attrs);
        $cbhtml .= html_writer::tag(
            'label',
            s($display),
            ['for' => $cbid, 'class' => 'form-check-label ml-1']
        );
        $cbhtml .= html_writer::end_tag('div');
    }
    $out .= html_writer::tag('div', $cbhtml, ['class' => 'col-md-9']);
    $out .= html_writer::end_tag('div');
    return $out;
};

$wtextarea = function ($name, $label, $value = '', $rows = 4) {
    $out  = html_writer::start_tag('div', ['class' => 'form-group row fitem']);
    $out .= html_writer::tag(
        'div',
        html_writer::tag(
            'label',
            $label,
            ['for' => 'wep_' . $name, 'class' => 'col-form-label']
        ),
        ['class' => 'col-md-3']
    );
    $out .= html_writer::tag(
        'div',
        html_writer::tag(
            'textarea',
            s($value),
            ['name' => $name, 'id' => 'wep_' . $name,
            'rows' => $rows,
            'class' => 'form-control']
        ),
        ['class' => 'col-md-9']
    );
    $out .= html_writer::end_tag('div');
    return $out;
};

// Form.
$formurl = new moodle_url('/mod/mtssdashboard/wep_edit.php');
echo html_writer::start_tag('form', [
    'method' => 'post', 'action' => $formurl->out(false),
    'id' => 'wep-edit-form', 'class' => 'mform',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sn', 'value' => $studentnumber]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'savewep']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'finalize', 'value' => '0',
    'id' => 'wep-finalize-flag']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'lock_timemodified',
    'value' => $wep->timemodified ?? 0]);

// Section: Plan Header.
$titlestr = 'Written Education Plan';
echo html_writer::tag('h4', $titlestr, ['class' => 'mt-3 mb-2']);

echo $winput(
    'schoolyear',
    get_string('wep_schoolyear', 'mod_mtssdashboard'),
    $wep->schoolyear ?? '2025-2026'
);
echo $winput(
    'wep_review_date',
    get_string('wep_review_date', 'mod_mtssdashboard'),
    $reviewdatedisplay,
    'date'
);

// Section: Area(s) of Identification.
echo html_writer::tag(
    'h4',
    get_string('wep_section_identification', 'mod_mtssdashboard'),
    ['class' => 'mt-3 mb-2']
);
echo $wcheckboxgroup(
    'identification_areas',
    get_string('wep_section_identification', 'mod_mtssdashboard'),
    $idareaoptions,
    $savedidareas
);

// Descriptive intro text.
echo html_writer::tag(
    'p',
    get_string('wep_section_intro_text', 'mod_mtssdashboard'),
    ['class' => 'text-muted small my-3',
    'style' => 'border-left:4px solid #dee2e6; padding-left:1rem;']
);
echo html_writer::tag(
    'p',
    html_writer::tag('em', get_string('wep_section_intro_footer', 'mod_mtssdashboard')),
    ['class' => 'small mb-3']
);

// Section: Service Setting / EMIS Code.
echo html_writer::tag(
    'h4',
    get_string('wep_section_service', 'mod_mtssdashboard'),
    ['class' => 'mt-3 mb-2']
);
echo $wcheckboxgroup(
    'service_setting',
    get_string('wep_section_service', 'mod_mtssdashboard'),
    $svcoptions,
    $savedsvc
);

// Section: Student Progress Measures.
echo html_writer::tag(
    'h4',
    get_string('wep_section_progress_measures', 'mod_mtssdashboard'),
    ['class' => 'mt-3 mb-2']
);
echo $wcheckboxgroup(
    'progress_measures',
    get_string('wep_section_progress_measures', 'mod_mtssdashboard'),
    $progressoptions,
    $savedprogress
);

// Section: Personnel Responsible.
echo html_writer::tag(
    'h4',
    get_string('wep_section_personnel', 'mod_mtssdashboard'),
    ['class' => 'mt-3 mb-2']
);
echo $wcheckboxgroup(
    'personnel',
    get_string('wep_section_personnel', 'mod_mtssdashboard'),
    $personneloptions,
    $savedpersonnel
);

// Section: Goals and Progress (grouped by subject).
echo html_writer::tag(
    'h4',
    get_string('wep_section_goals', 'mod_mtssdashboard'),
    ['class' => 'mt-3 mb-2']
);

// Reading/Writing — all levels.
echo html_writer::tag(
    'h5',
    get_string('wep_id_reading_writing', 'mod_mtssdashboard'),
    ['class' => 'mt-3 mb-1 text-primary']
);
echo $wtextarea(
    'goal_reading_writing',
    get_string('wep_goal_reading_writing', 'mod_mtssdashboard'),
    $wep->goal_reading_writing ?? ''
);
echo $wtextarea(
    'midyear_reading',
    get_string('wep_midyear_reading', 'mod_mtssdashboard'),
    $wep->midyear_reading ?? '',
    3
);
echo $wtextarea(
    'endofyear_reading',
    get_string('wep_endofyear_reading', 'mod_mtssdashboard'),
    $wep->endofyear_reading ?? '',
    3
);

// Mathematics — all levels.
echo html_writer::tag(
    'h5',
    get_string('wep_id_mathematics', 'mod_mtssdashboard'),
    ['class' => 'mt-3 mb-1 text-primary']
);
echo $wtextarea(
    'goal_mathematics',
    get_string('wep_goal_mathematics', 'mod_mtssdashboard'),
    $wep->goal_mathematics ?? ''
);
echo $wtextarea(
    'midyear_mathematics',
    get_string('wep_midyear_mathematics', 'mod_mtssdashboard'),
    $wep->midyear_mathematics ?? '',
    3
);
echo $wtextarea(
    'endofyear_mathematics',
    get_string('wep_endofyear_mathematics', 'mod_mtssdashboard'),
    $wep->endofyear_mathematics ?? '',
    3
);

// Science and Social Studies — MS/HS only.
if ($schoollevel !== 'es') {
    echo html_writer::tag(
        'h5',
        get_string('wep_id_science', 'mod_mtssdashboard'),
        ['class' => 'mt-3 mb-1 text-primary']
    );
    echo $wtextarea(
        'goal_science',
        get_string('wep_goal_science', 'mod_mtssdashboard'),
        $wep->goal_science ?? ''
    );
    echo $wtextarea(
        'midyear_science',
        get_string('wep_midyear_science', 'mod_mtssdashboard'),
        $wep->midyear_science ?? '',
        3
    );
    echo $wtextarea(
        'endofyear_science',
        get_string('wep_endofyear_science', 'mod_mtssdashboard'),
        $wep->endofyear_science ?? '',
        3
    );

    echo html_writer::tag(
        'h5',
        get_string('wep_id_social_studies', 'mod_mtssdashboard'),
        ['class' => 'mt-3 mb-1 text-primary']
    );
    echo $wtextarea(
        'goal_social_studies',
        get_string('wep_goal_social_studies', 'mod_mtssdashboard'),
        $wep->goal_social_studies ?? ''
    );
    echo $wtextarea(
        'midyear_social_studies',
        get_string('wep_midyear_social_studies', 'mod_mtssdashboard'),
        $wep->midyear_social_studies ?? '',
        3
    );
    echo $wtextarea(
        'endofyear_social_studies',
        get_string('wep_endofyear_social_studies', 'mod_mtssdashboard'),
        $wep->endofyear_social_studies ?? '',
        3
    );
}

// Section: Parent Input.
echo html_writer::tag(
    'h4',
    get_string('wep_section_parent_input', 'mod_mtssdashboard'),
    ['class' => 'mt-3 mb-2']
);
echo $wtextarea(
    'parent_input',
    get_string('wep_parent_input', 'mod_mtssdashboard'),
    $wep->parent_input ?? '',
    4
);

// Section: Signatures.
echo html_writer::tag(
    'h4',
    get_string('wep_section_signatures', 'mod_mtssdashboard'),
    ['class' => 'mt-3 mb-2']
);
echo $wtextarea(
    'sig_parent',
    get_string('wep_sig_parent', 'mod_mtssdashboard'),
    $wep->sig_parent ?? '',
    2
);
echo html_writer::tag(
    'p',
    get_string('wep_sig_footer', 'mod_mtssdashboard'),
    ['class' => 'text-muted small mt-2']
);

// Buttons.
echo html_writer::start_tag('div', ['class' => 'form-group row mt-4']);
echo html_writer::tag('div', '', ['class' => 'col-md-3']);
echo html_writer::start_tag('div', ['class' => 'col-md-9 d-flex flex-wrap gap-2']);
echo html_writer::tag(
    'button',
    get_string('wep_btn_save_draft', 'mod_mtssdashboard'),
    ['type' => 'submit', 'class' => 'btn btn-secondary mr-2', 'id' => 'wep-btn-draft']
);
echo html_writer::tag(
    'button',
    get_string('wep_btn_finalize', 'mod_mtssdashboard'),
    ['type' => 'button', 'class' => 'btn btn-primary mr-2', 'id' => 'wep-btn-finalize']
);
if ($wep) {
    $wepprinturl = new moodle_url(
        '/mod/mtssdashboard/wep_print.php',
        ['id' => $id, 'sn' => $studentnumber]
    );
    echo html_writer::tag(
        'a',
        get_string('wep_btn_print', 'mod_mtssdashboard'),
        ['href' => $wepprinturl->out(false), 'class' => 'btn btn-outline-secondary mr-2',
        'target' => '_blank']
    );

    echo html_writer::tag(
        'button',
        get_string('wep_btn_reset', 'mod_mtssdashboard'),
        ['type' => 'button', 'class' => 'btn btn-outline-warning mr-2', 'id' => 'wep-btn-reset']
    );

    $wepdeleteurl = new moodle_url(
        '/mod/mtssdashboard/wep_delete.php',
        ['id' => $id, 'sn' => $studentnumber, 'sesskey' => sesskey()]
    );
    echo html_writer::tag(
        'a',
        get_string('wep_btn_delete', 'mod_mtssdashboard'),
        ['href' => $wepdeleteurl->out(false), 'class' => 'btn btn-outline-danger mr-2',
         'onclick' => 'return confirm(' .
        json_encode(get_string('wep_delete_confirm', 'mod_mtssdashboard')) . ');']
    );
}
$cancelurl = new moodle_url('/mod/mtssdashboard/view.php', ['id' => $id, 'tab' => 'wep']);
echo html_writer::tag(
    'a',
    get_string('wep_btn_cancel', 'mod_mtssdashboard'),
    ['href' => $cancelurl->out(false), 'class' => 'btn btn-link']
);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

echo html_writer::end_tag('form');

// JavaScript.

$lockajaxurl = new moodle_url('/mod/mtssdashboard/edit_lock_ajax.php');
echo html_writer::script("
(function() {
    var form         = document.getElementById('wep-edit-form');
    var finalizeFlag = document.getElementById('wep-finalize-flag');
    var btnFinalize  = document.getElementById('wep-btn-finalize');

    if (btnFinalize) {
        btnFinalize.addEventListener('click', function() {
            finalizeFlag.value = '1';
            form.submit();
        });
    }

    var btnReset = document.getElementById('wep-btn-reset');
    if (btnReset) {
        btnReset.addEventListener('click', function() {
            var resetMsg = " . json_encode(get_string('wep_reset_confirm', 'mod_mtssdashboard', $currentschoolyear)) . ";
            if (!window.confirm(resetMsg)) {
                return;
            }
            var schoolYearInput = document.getElementById('wep_schoolyear');
            if (schoolYearInput) {
                schoolYearInput.value = " . json_encode($currentschoolyear) . ";
            }
            var reviewDateInput = document.getElementById('wep_wep_review_date');
            if (reviewDateInput) {
                reviewDateInput.value = " . json_encode($todayiso) . ";
            }
            form.querySelectorAll('textarea').forEach(function(ta) {
                ta.value = '';
            });
        });
    }

    // ── Edit lock: heartbeat + release-on-leave ─────────────────────────────
    var lockParams = 'id=" . (int) $id . "&sn=" . (int) $studentnumber . "&type=wep&sesskey=" . sesskey() . "';
    var lockUrl     = '" . $lockajaxurl->out(false) . "';
    var submitting  = false;

    if (form) {
        form.addEventListener('submit', function() { submitting = true; });
    }

    var heartbeatTimer = setInterval(function() {
        fetch(lockUrl, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: lockParams + '&lockaction=heartbeat'
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data && data.status === 'lost') {
                clearInterval(heartbeatTimer);
                var msg = " . json_encode(get_string('wep_lock_lost', 'mod_mtssdashboard', '%%HOLDER%%')) . ";
                window.alert(msg.replace('%%HOLDER%%', data.holdername || ''));
                window.location.href = '" .
                    (new moodle_url('/mod/mtssdashboard/view.php', ['id' => $id, 'tab' => 'wep']))->out(false) .
                    "';
            }
        }).catch(function() { /* network hiccup — next heartbeat will retry */ });
    }, 45000);

    var releaseLock = function() {
        if (submitting) { return; }
        var blob = new Blob([lockParams + '&lockaction=release'],
            { type: 'application/x-www-form-urlencoded' });
        navigator.sendBeacon(lockUrl, blob);
    };
    window.addEventListener('pagehide', releaseLock);
    window.addEventListener('beforeunload', releaseLock);
})();
");

echo $OUTPUT->footer();
