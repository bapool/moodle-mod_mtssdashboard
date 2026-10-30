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
 * Serves a student plan PDF from dataroot/NTimports/plans/.
 * Access is restricted to users who can view mod_mtssdashboard.
 *
 * @package    mod_mtssdashboard
 * @copyright  2026 National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Parameters.
$cmid = required_param('cmid', PARAM_INT);
$ssid = required_param('ssid', PARAM_INT);

// Load course module and context.
$cm      = get_coursemodule_from_id('mtssdashboard', $cmid, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

// Must be logged in and have view capability.
require_login($course, false, $cm);
require_capability('mod/mtssdashboard:view', $context);

// Build and validate the file path.
$ssid     = (int) $ssid;
$filepath = $CFG->dataroot . '/NTimports/plans/' . $ssid . '.pdf';

if ($ssid <= 0 || !file_exists($filepath) || !is_readable($filepath)) {
    throw new moodle_exception('filenotfound', 'error');
}

// Serve the PDF inline so the browser opens it in a new tab.
$filename = $ssid . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($filepath);
exit;
