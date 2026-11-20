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
 * AJAX endpoint used by rimp_edit.php / wep_edit.php to keep an edit lock
 * alive (heartbeat) or release it early (on save/cancel/tab close).
 *
 * @package    mod_mtssdashboard
 * @copyright  2026 National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/mtssdashboard/lib.php');

// Parameters.
$id            = required_param('id', PARAM_INT);
$studentnumber = required_param('sn', PARAM_INT);
$recordtype    = required_param('type', PARAM_ALPHA);
$action        = required_param('lockaction', PARAM_ALPHA);

// Bootstrap.
$cm      = get_coursemodule_from_id('mtssdashboard', $id, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);

if (!in_array($recordtype, ['rimp', 'wep'], true)) {
    throw new moodle_exception('invalidrecordtype', 'mod_mtssdashboard');
}
if (!in_array($action, ['heartbeat', 'release'], true)) {
    throw new moodle_exception('invalidlockaction', 'mod_mtssdashboard');
}

require_capability('mod/mtssdashboard:edit' . $recordtype, $context);
require_sesskey();

header('Content-Type: application/json');

if ($action === 'release') {
    stv_release_edit_lock($studentnumber, $recordtype, $USER->id);
    echo json_encode(['status' => 'released']);
    die();
}

// Action === 'heartbeat'.
$acquired = stv_acquire_edit_lock($studentnumber, $recordtype, $USER->id);
if (!$acquired) {
    // Someone else has taken the lock since this page loaded (rare race).
    $lock = stv_get_edit_lock($studentnumber, $recordtype);
    $holder = $lock ? $DB->get_record('user', ['id' => $lock->userid]) : null;
    echo json_encode([
        'status'     => 'lost',
        'holdername' => $holder ? fullname($holder) : get_string('edit_lock_unknown_user', 'mod_mtssdashboard'),
    ]);
    die();
}

echo json_encode(['status' => 'ok']);
die();
