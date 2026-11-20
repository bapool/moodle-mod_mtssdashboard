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
 * RIMP create / edit page for mod_mtssdashboard.
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
require_capability('mod/mtssdashboard:editrimp', $context);

$schoollevel = stv_get_school_level_for_course($course);
$recordtype  = 'rimp';

// Look up student.
$student = $DB->get_record_select(
    'user',
    "idnumber = :sn AND deleted = 0",
    ['sn' => $studentnumber]
);
if (!$student) {
    throw new moodle_exception('invalidstudent', 'mod_mtssdashboard');
}

// Fetch existing RIMP.
$rimp = $DB->get_record_select(
    'mtssdashboard_rimp',
    'studentnumber = :sn',
    ['sn' => $studentnumber]
);

// Handle POST save.
if ($action === 'saverimp' && confirm_sesskey()) {
    $finalize = optional_param('finalize', 0, PARAM_INT);
    $now      = time();

    // Concurrency safety net: if someone else saved this RIMP since this form
    // was loaded, don't silently clobber it — bail out and send the teacher
    // back to reopen it. In normal use the edit lock below prevents this
    // scenario entirely; this only catches the rare race where two people
    // load the form within the same instant.
    $locktimemodified = optional_param('lock_timemodified', 0, PARAM_INT);
    if ($rimp && (int) $rimp->timemodified !== $locktimemodified) {
        stv_release_edit_lock($studentnumber, $recordtype, $USER->id);
        $redirecturl = new moodle_url(
            '/mod/mtssdashboard/view.php',
            ['id' => $cm->id, 'tab' => 'rimp']
        );
        redirect(
            $redirecturl,
            get_string('rimp_edit_conflict', 'mod_mtssdashboard'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $goalsraw    = optional_param_array('goals', [], PARAM_ALPHANUMEXT);
    $supportsraw = optional_param_array('supports', [], PARAM_ALPHANUMEXT);

    // MS monitoring is checkboxes; ES monitoring is free text + select.
    if ($schoollevel === 'ms' || $schoollevel === 'hs') {
        $monchecks = optional_param_array('monitoring_checks', [], PARAM_TEXT);
        $monitoringassessment = implode("\n", array_map('trim', $monchecks));
        $monitoringfrequency  = null;
    } else {
        $monitoringassessment = optional_param('monitoring_assessment', '', PARAM_TEXT);
        $monitoringfrequency  = optional_param('monitoring_frequency', '', PARAM_TEXT);
    }

    $effdatestr = optional_param('rimp_effective_date', '', PARAM_TEXT);
    $effdatets  = !empty($effdatestr) ? strtotime($effdatestr) : null;

    $record = (object) [
        'studentnumber'         => $studentnumber,
        'schoollevel'           => $schoollevel,
        'schoolyear'            => optional_param('schoolyear', '', PARAM_TEXT),
        'rimp_effective_date'   => $effdatets ?: null,
        'student_score'         => optional_param('student_score', null, PARAM_INT),
        'assessment_status'     => optional_param('assessment_status', '', PARAM_TEXT),
        'goals'                 => json_encode(array_values($goalsraw)),
        'supports'              => json_encode(array_values($supportsraw)),
        'days_per_week'         => optional_param('days_per_week', '', PARAM_TEXT),
        'minutes_per_week'      => optional_param('minutes_per_week', null, PARAM_INT),
        'monitoring_assessment' => $monitoringassessment,
        'monitoring_frequency'  => $monitoringfrequency,
        'monitored_by'          => optional_param('monitored_by', '', PARAM_TEXT),
        'grade3_guarantee'      => optional_param('grade3_guarantee', '', PARAM_TEXT),
        'sig_parent'            => optional_param('sig_parent', '', PARAM_TEXT),
        'sig_teacher'           => optional_param('sig_teacher', '', PARAM_TEXT),
        'sig_ela_teacher'       => optional_param('sig_ela_teacher', '', PARAM_TEXT),
        'sig_intervention'      => optional_param('sig_intervention', '', PARAM_TEXT),
        'status'                => $finalize ? 1 : 0,
        'timemodified'          => $now,
        'usermodified'          => $USER->id,
    ];

    if ($rimp) {
        $record->id          = $rimp->id;
        $record->timecreated = $rimp->timecreated;
        $DB->update_record('mtssdashboard_rimp', $record);
    } else {
        $record->timecreated = $now;
        $DB->insert_record('mtssdashboard_rimp', $record);
    }

    stv_release_edit_lock($studentnumber, $recordtype, $USER->id);

    $redirecturl = new moodle_url(
        '/mod/mtssdashboard/view.php',
        ['id' => $cm->id, 'tab' => 'rimp']
    );
    $msg = $finalize
        ? get_string('rimp_finalized', 'mod_mtssdashboard')
        : get_string('rimp_saved_draft', 'mod_mtssdashboard');
    redirect($redirecturl, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
}

// Edit lock: block if someone else is already editing this RIMP.
$activelock = stv_get_edit_lock($studentnumber, $recordtype);
if ($activelock && (int) $activelock->userid !== (int) $USER->id) {
    $holder     = $DB->get_record('user', ['id' => $activelock->userid]);
    $holdername = $holder ? fullname($holder) : get_string('edit_lock_unknown_user', 'mod_mtssdashboard');
    $viewurl    = new moodle_url('/mod/mtssdashboard/view.php', ['id' => $id, 'tab' => 'rimp']);

    $PAGE->set_url('/mod/mtssdashboard/rimp_edit.php', ['id' => $id, 'sn' => $studentnumber]);
    $PAGE->set_title(get_string('rimp_lock_title', 'mod_mtssdashboard'));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->set_context($context);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('rimp_lock_title', 'mod_mtssdashboard'), 3);
    echo html_writer::start_tag('div', ['class' => 'alert alert-warning mt-3']);
    echo html_writer::tag('p', get_string('rimp_lock_message', 'mod_mtssdashboard', s($holdername)));
    echo html_writer::tag(
        'button',
        get_string('rimp_lock_ok', 'mod_mtssdashboard'),
        ['type' => 'button', 'class' => 'btn btn-primary', 'id' => 'rimp-lock-ok-btn']
    );
    echo html_writer::end_tag('div');

    echo html_writer::script("
    (function() {
        var btn = document.getElementById('rimp-lock-ok-btn');
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
$PAGE->set_url('/mod/mtssdashboard/rimp_edit.php', ['id' => $id, 'sn' => $studentnumber]);
$PAGE->set_title(get_string('rimp_edit_title', 'mod_mtssdashboard'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->navbar->add(
    format_string($instance->name),
    new moodle_url('/mod/mtssdashboard/view.php', ['id' => $id, 'tab' => 'rimp'])
);
$PAGE->navbar->add(get_string('rimp_edit_title', 'mod_mtssdashboard'));

/**
 * Decode a saved JSON array.
 *
 * @param string|null $json JSON text from the database.
 * @return array
 */
function rimp_decode_array($json) {
    if (empty($json)) {
        return [];
    }
    $d = json_decode($json, true);
    return is_array($d) ? $d : [];
}

$savedgoals    = $rimp ? rimp_decode_array($rimp->goals) : [];
$savedsupports = $rimp ? rimp_decode_array($rimp->supports) : [];

// Saved MS monitoring checkboxes.
$savedmonchecks = [];
if (
    $rimp && !empty($rimp->monitoring_assessment)
        && ($schoollevel === 'ms' || $schoollevel === 'hs')
) {
    $savedmonchecks = array_map('trim', explode("\n", $rimp->monitoring_assessment));
}

// Option definitions.
// Goals — same for all levels.
$goalsoptions = [
    'phonemic'      => 'Phonemic Awareness: Noticing, thinking about and working with the smallest units of spoken ' .
        'language, which are called phonemes. For example, young children hear "dog" but not "duh"-"aw"-"guh".',
    'phonics'       => 'Phonics: Knowing relationships between sounds (phonemes) and letters (graphemes). For ' .
        'example, when a child is taught the sounds for the letters t, p, a and c, they can start to build up words ' .
        'like: "tap", "pat", and "cat".',
    'vocabulary'    => 'Vocabulary: Understanding the meaning of words we speak, hear, read, and write.',
    'fluency'       => 'Reading Fluency: Reading connected text accurately, fluently, and for meaning.',
    'comprehension' => 'Reading Comprehension: Gaining meaning from text.',
];

// Supports — same for all levels.
$supportsoptions = [
    'writing_found'   => 'Explicit Intervention in Writing (Foundational/Transcription)',
    'writing_comp'    => 'Explicit Intervention in Writing (Composition Skills)',
    'phon_awareness'  => 'Explicit Intervention in Phonemic Awareness',
    'high_freq_words' => 'Explicit Intervention in High Frequency Word Recognition',
    'decoding'        => 'Explicit Intervention in Decoding',
    'comprehension'   => 'Explicit Intervention in Comprehension',
    'fluency'         => 'Explicit Intervention in Fluency',
    'vocabulary'      => 'Explicit Intervention in Vocabulary',
    'scaffolding'     => 'Small Group Scaffolding of Complex Text',
    'communication'   => 'Explicit Intervention in Communication/Language',
    'multimodal'      => 'Multi-Modal Approach to Structured Literacy',
];

$daysoptions = [
    '' => '— Select —',
    '1' => '1', '2' => '2', '3+' => '3+', '4+' => '4+', '5' => '5',
];

$freqoptionses = [
    ''          => '— Select —',
    'Weekly'    => 'Weekly',
    'Biweekly'  => 'Biweekly',
    'Monthly'   => 'Monthly',
    'Quarterly' => 'Quarterly',
];

// MS monitoring checkboxes.
$moncheckoptions = [
    'UFLR- after every two lessons'                                                              => 'UFLR- after ' .
        'every two lessons',
    'Phonics for Reading- a review at the end of each lesson and a check up at the end of each unit' => 'Phonics for ' .
        'Reading- a review at the end of each lesson and a check up at the end of each unit',
    'Rewards- a review after every two lessons and a check up at the end of each unit'           => 'Rewards- a ' .
        'review after every two lessons and a check up at the end of each unit',
];

$monitoredoptions = [
    ''                        => '— Select —',
    'Classroom Teacher'       => 'Classroom Teacher',
    'Title 1 Teacher'         => 'Title 1 Teacher',
    'Intervention Specialist' => 'Intervention Specialist',
    'Other'                   => 'Other',
];

$grade3options = [
    ''  => '— Select —',
    'Not applicable at this time'
        => 'Not applicable at this time',
    'The student reached the promotion score of 700 on the Ohio State Test for grade 3 English language arts.'
        => 'The student reached the promotion score of 700 on the Ohio State Test for grade 3 English language arts.',
    'The student reached the promotion score on an approved alternative reading assessment given by the district.'
        => 'The student reached the promotion score on an approved alternative reading assessment given by the ' .
            'district.',
    'A parent/guardian, in consultation with the school, can request that their child be promoted to fourth grade ' .
        'even if they do not reach the promotion score. In this case, the student will continue to be supported with ' .
        'a RIMP and provided with intensive instruction, until they are able to read at grade level.'
        => 'A parent/guardian, in consultation with the school, can request that their child be promoted to fourth ' .
            'grade...',
];

$isfinalized = $rimp && (int)$rimp->status === 1;
$statuslabel = $isfinalized
    ? get_string('rimp_status_finalized', 'mod_mtssdashboard')
    : get_string('rimp_status_draft', 'mod_mtssdashboard');

$effdatedisplay = ($rimp && !empty($rimp->rimp_effective_date))
    ? date('Y-m-d', $rimp->rimp_effective_date)
    : '';

echo $OUTPUT->header();
echo $OUTPUT->heading(fullname($student) . ' — ' .
    get_string('rimp_edit_title', 'mod_mtssdashboard'), 3);

$badgeclass = $isfinalized ? 'badge badge-success' : 'badge badge-warning';
echo html_writer::tag(
    'span',
    $statuslabel,
    ['class' => $badgeclass, 'style' => 'font-size:1em; margin-bottom:1rem; display:inline-block;']
);

// Rendering helpers.
$rinput = function ($name, $label, $value = '', $type = 'text', $extra = []) {
    $out  = html_writer::start_tag('div', ['class' => 'form-group row fitem']);
    $out .= html_writer::tag(
        'div',
        html_writer::tag(
            'label',
            $label,
            ['for' => 'rimp_' . $name, 'class' => 'col-form-label']
        ),
        ['class' => 'col-md-3']
    );
    $attrs = array_merge([
        'type'  => $type, 'name' => $name,
        'id'    => 'rimp_' . $name, 'value' => $value, 'class' => 'form-control',
    ], $extra);
    $out .= html_writer::tag(
        'div',
        html_writer::empty_tag('input', $attrs),
        ['class' => 'col-md-9']
    );
    $out .= html_writer::end_tag('div');
    return $out;
};

$rselect = function ($name, $label, $options, $selected = '') {
    $out  = html_writer::start_tag('div', ['class' => 'form-group row fitem']);
    $out .= html_writer::tag(
        'div',
        html_writer::tag(
            'label',
            $label,
            ['for' => 'rimp_' . $name, 'class' => 'col-form-label']
        ),
        ['class' => 'col-md-3']
    );
    $out .= html_writer::tag(
        'div',
        html_writer::select(
            $options,
            $name,
            $selected,
            false,
            ['id' => 'rimp_' . $name, 'class' => 'form-control custom-select']
        ),
        ['class' => 'col-md-9']
    );
    $out .= html_writer::end_tag('div');
    return $out;
};

$rcheckboxgroup = function ($name, $label, $options, $checked = []) {
    $out  = html_writer::start_tag('div', ['class' => 'form-group row fitem']);
    $out .= html_writer::tag(
        'div',
        html_writer::tag('span', $label, ['class' => 'col-form-label']),
        ['class' => 'col-md-3']
    );
    $cbhtml = '';
    foreach ($options as $val => $display) {
        $attrs = [
            'type'  => 'checkbox',
            'name'  => $name . '[]',
            'id'    => $name . '_' . preg_replace('/[^a-z0-9]/i', '_', $val),
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
            ['for'   => $name . '_' . preg_replace('/[^a-z0-9]/i', '_', $val),
            'class' => 'form-check-label ml-1']
        );
        $cbhtml .= html_writer::end_tag('div');
    }
    $out .= html_writer::tag('div', $cbhtml, ['class' => 'col-md-9']);
    $out .= html_writer::end_tag('div');
    return $out;
};

$rtextarea = function ($name, $label, $value = '', $rows = 3) {
    $out  = html_writer::start_tag('div', ['class' => 'form-group row fitem']);
    $out .= html_writer::tag(
        'div',
        html_writer::tag(
            'label',
            $label,
            ['for' => 'rimp_' . $name, 'class' => 'col-form-label']
        ),
        ['class' => 'col-md-3']
    );
    $out .= html_writer::tag(
        'div',
        html_writer::tag(
            'textarea',
            s($value),
            ['name' => $name, 'id' => 'rimp_' . $name,
            'rows' => $rows,
            'class' => 'form-control']
        ),
        ['class' => 'col-md-9']
    );
    $out .= html_writer::end_tag('div');
    return $out;
};

// Form.
$formurl = new moodle_url('/mod/mtssdashboard/rimp_edit.php');
echo html_writer::start_tag('form', [
    'method' => 'post', 'action' => $formurl->out(false),
    'id' => 'rimp-edit-form', 'class' => 'mform',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sn', 'value' => $studentnumber]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'saverimp']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'finalize', 'value' => '0',
    'id' => 'rimp-finalize-flag']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'lock_timemodified',
    'value' => $rimp->timemodified ?? 0]);

// Section: Plan Information.
echo html_writer::tag('h4', 'Plan Information', ['class' => 'mt-3 mb-2']);
echo $rinput(
    'schoolyear',
    'School Year',
    $rimp->schoolyear ?? ''
);
echo $rinput(
    'rimp_effective_date',
    'RIMP Effective Date',
    $effdatedisplay,
    'date'
);
echo $rinput(
    'student_score',
    'Student Score',
    $rimp->student_score ?? '',
    'number'
);

// Assessment status — radio buttons to match the original form.
$asstatus = $rimp->assessment_status ?? '';
echo html_writer::start_tag('div', ['class' => 'form-group row fitem']);
echo html_writer::tag(
    'div',
    html_writer::tag('span', 'Student Assessment Status', ['class' => 'col-form-label']),
    ['class' => 'col-md-3']
);
$radiohtml  = html_writer::start_tag('div', ['class' => 'form-check']);
$radiohtml .= html_writer::empty_tag('input', [
    'type' => 'radio', 'name' => 'assessment_status', 'id' => 'asstatus_ontrack',
    'value' => 'On Track', 'class' => 'mr-1',
    ($asstatus === 'On Track' ? 'checked' : '') => ($asstatus === 'On Track' ? 'checked' : ''),
]);
$radiohtml .= html_writer::tag(
    'label',
    'On Track',
    ['for' => 'asstatus_ontrack', 'class' => 'form-check-label ml-1']
);
$radiohtml .= html_writer::end_tag('div');
$radiohtml .= html_writer::start_tag('div', ['class' => 'form-check']);
$radiohtml .= html_writer::empty_tag('input', [
    'type' => 'radio', 'name' => 'assessment_status', 'id' => 'asstatus_nottrack',
    'value' => 'Not On Track', 'class' => 'mr-1',
    ($asstatus === 'Not On Track' ? 'checked' : '') => ($asstatus === 'Not On Track' ? 'checked' : ''),
]);
$radiohtml .= html_writer::tag(
    'label',
    'Not On Track',
    ['for' => 'asstatus_nottrack', 'class' => 'form-check-label ml-1']
);
$radiohtml .= html_writer::end_tag('div');
echo html_writer::tag('div', $radiohtml, ['class' => 'col-md-9']);
echo html_writer::end_tag('div');

// Section: Goals.
echo html_writer::tag(
    'h4',
    'This plan was created for this student with the goal of improving:',
    ['class' => 'mt-3 mb-2']
);
echo $rcheckboxgroup('goals', 'Goals', $goalsoptions, $savedgoals);

// Section: Instructional Supports.
echo html_writer::tag('h4', 'Instructional Supports:', ['class' => 'mt-3 mb-2']);
echo $rcheckboxgroup('supports', 'Supports', $supportsoptions, $savedsupports);

// Section: Services / Frequency.
echo html_writer::tag('h4', 'Services and Supports', ['class' => 'mt-3 mb-2']);
echo $rselect(
    'days_per_week',
    'Number of Days Per Week:',
    $daysoptions,
    $rimp->days_per_week ?? ''
);
echo $rinput(
    'minutes_per_week',
    'Number of Minutes Per Week:',
    $rimp->minutes_per_week ?? '',
    'number'
);

// Section: Monitoring.
echo html_writer::tag('h4', 'Plans for Monitoring Student Success', ['class' => 'mt-3 mb-2']);

if ($schoollevel === 'ms' || $schoollevel === 'hs') {
    echo $rcheckboxgroup(
        'monitoring_checks',
        'Assessment used for Progress Monitoring and Frequency:',
        $moncheckoptions,
        $savedmonchecks
    );
} else {
    echo $rtextarea(
        'monitoring_assessment',
        'Assessment used for Progress Monitoring:',
        $rimp->monitoring_assessment ?? ''
    );
    echo $rselect(
        'monitoring_frequency',
        'Frequency of Monitoring:',
        $freqoptionses,
        $rimp->monitoring_frequency ?? ''
    );
}

echo $rselect(
    'monitored_by',
    'Intervention Plan Monitored By:',
    $monitoredoptions,
    $rimp->monitored_by ?? ''
);

// Section: Third Grade Guarantee (ES only).
if ($schoollevel === 'es') {
    echo html_writer::tag(
        'h4',
        "Ohio's Third Grade Reading Guarantee (Third Grade Students Only)",
        ['class' => 'mt-3 mb-2']
    );
    echo html_writer::tag(
        'p',
        'The Third Grade Reading Guarantee requires Ohio\'s third grade students to demonstrate reading proficiency ' .
            'by the end of third grade.',
        ['class' => 'text-muted small mb-2']
    );

    // Render as checkboxes to match the original form layout.
    $g3val = $rimp->grade3_guarantee ?? '';
    $g3html = '';
    foreach ($grade3options as $val => $display) {
        if ($val === '') {
            continue;
        }
        $cbid = 'grade3_' . md5($val);
        $g3html .= html_writer::start_tag('div', ['class' => 'form-check mb-1']);
        $attrs = [
            'type'  => 'radio',
            'name'  => 'grade3_guarantee',
            'id'    => $cbid,
            'value' => $val,
            'class' => 'mr-1',
        ];
        if ($g3val === $val) {
            $attrs['checked'] = 'checked';
        }
        $g3html .= html_writer::empty_tag('input', $attrs);
        $g3html .= html_writer::tag(
            'label',
            s($display),
            ['for' => $cbid, 'class' => 'form-check-label ml-1']
        );
        $g3html .= html_writer::end_tag('div');
    }
    echo html_writer::start_tag('div', ['class' => 'form-group row fitem']);
    echo html_writer::tag(
        'div',
        html_writer::tag('span', 'Select One', ['class' => 'col-form-label']),
        ['class' => 'col-md-3']
    );
    echo html_writer::tag('div', $g3html, ['class' => 'col-md-9']);
    echo html_writer::end_tag('div');
}

// Section: Signatures.
echo html_writer::tag('h4', 'Signatures', ['class' => 'mt-3 mb-2']);
echo $rtextarea('sig_parent', 'Parent/Guardian:', $rimp->sig_parent ?? '', 2);

if ($schoollevel === 'es') {
    echo $rtextarea('sig_teacher', 'Current Teacher:', $rimp->sig_teacher ?? '', 2);
} else {
    echo $rtextarea(
        'sig_ela_teacher',
        'English language arts Teacher:',
        $rimp->sig_ela_teacher ?? '',
        2
    );
    echo $rtextarea(
        'sig_intervention',
        'Intervention/Title Teacher:',
        $rimp->sig_intervention ?? '',
        2
    );
}

// Buttons.
echo html_writer::start_tag('div', ['class' => 'form-group row mt-4']);
echo html_writer::tag('div', '', ['class' => 'col-md-3']);
echo html_writer::start_tag('div', ['class' => 'col-md-9 d-flex flex-wrap gap-2']);
echo html_writer::tag(
    'button',
    get_string('rimp_btn_save_draft', 'mod_mtssdashboard'),
    ['type' => 'submit', 'class' => 'btn btn-secondary mr-2', 'id' => 'rimp-btn-draft']
);
echo html_writer::tag(
    'button',
    get_string('rimp_btn_finalize', 'mod_mtssdashboard'),
    ['type' => 'button', 'class' => 'btn btn-primary mr-2', 'id' => 'rimp-btn-finalize']
);
if ($rimp) {
    $rimpprinturl = new moodle_url(
        '/mod/mtssdashboard/rimp_print.php',
        ['id' => $id, 'sn' => $studentnumber]
    );
    echo html_writer::tag(
        'a',
        get_string('rimp_btn_print', 'mod_mtssdashboard'),
        ['href' => $rimpprinturl->out(false), 'class' => 'btn btn-outline-secondary mr-2',
        'target' => '_blank']
    );

    $rimpdeleteurl = new moodle_url(
        '/mod/mtssdashboard/rimp_delete.php',
        ['id' => $id, 'sn' => $studentnumber, 'sesskey' => sesskey()]
    );
    echo html_writer::tag(
        'a',
        get_string('rimp_btn_delete', 'mod_mtssdashboard'),
        ['href' => $rimpdeleteurl->out(false), 'class' => 'btn btn-outline-danger mr-2',
         'onclick' => 'return confirm(' .
        json_encode(get_string('rimp_delete_confirm', 'mod_mtssdashboard')) . ');']
    );
}
$cancelurl = new moodle_url('/mod/mtssdashboard/view.php', ['id' => $id, 'tab' => 'rimp']);
echo html_writer::tag(
    'a',
    get_string('rimp_btn_cancel', 'mod_mtssdashboard'),
    ['href' => $cancelurl->out(false), 'class' => 'btn btn-link']
);
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

echo html_writer::end_tag('form');

// JavaScript.

$lockajaxurl = new moodle_url('/mod/mtssdashboard/edit_lock_ajax.php');
echo html_writer::script("
(function() {
    var form         = document.getElementById('rimp-edit-form');
    var finalizeFlag = document.getElementById('rimp-finalize-flag');
    var btnFinalize  = document.getElementById('rimp-btn-finalize');

    if (btnFinalize) {
        btnFinalize.addEventListener('click', function() {
            finalizeFlag.value = '1';
            form.submit();
        });
    }

    // ── Edit lock: heartbeat + release-on-leave ─────────────────────────────
    var lockParams = 'id=" . (int) $id . "&sn=" . (int) $studentnumber . "&type=rimp&sesskey=" . sesskey() . "';
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
                var msg = " . json_encode(get_string('rimp_lock_lost', 'mod_mtssdashboard', '%%HOLDER%%')) . ";
                window.alert(msg.replace('%%HOLDER%%', data.holdername || ''));
                window.location.href = '" .
                    (new moodle_url('/mod/mtssdashboard/view.php', ['id' => $id, 'tab' => 'rimp']))->out(false) .
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
