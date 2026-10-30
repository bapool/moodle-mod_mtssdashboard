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
 * Deletes a student's WEP record.
 *
 * @package    mod_mtssdashboard
 * @copyright  2026 National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/mtssdashboard/lib.php');

// ── Parameters ────────────────────────────────────────────────────────────────
$id            = required_param('id', PARAM_INT);
$studentnumber = required_param('sn', PARAM_INT);

// ── Bootstrap ─────────────────────────────────────────────────────────────────
$cm      = get_coursemodule_from_id('mtssdashboard', $id, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/mtssdashboard:editwep', $context);
require_sesskey();

$redirecturl = new moodle_url('/mod/mtssdashboard/view.php', ['id' => $cm->id, 'tab' => 'wep']);

// ── Delete ────────────────────────────────────────────────────────────────────
$wep = $DB->get_record_select('mtssdashboard_wep', 'studentnumber = :sn', ['sn' => $studentnumber]);
if ($wep) {
    $DB->delete_records('mtssdashboard_wep', ['id' => $wep->id]);
    stv_release_edit_lock($studentnumber, 'wep', $USER->id);
    redirect($redirecturl, get_string('wep_deleted', 'mod_mtssdashboard'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

redirect($redirecturl);
