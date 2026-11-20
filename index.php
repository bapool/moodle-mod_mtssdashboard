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
 * Lists all State Test View instances in a course.
 *
 * @package    mod_mtssdashboard
 * @copyright  2026 National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/mtssdashboard/lib.php');

$id = required_param('id', PARAM_INT); // Course id.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

$context = context_course::instance($course->id);
require_capability('mod/mtssdashboard:view', $context);

// Log the course module list view event.
$event = \core\event\course_module_instance_list_viewed::create([
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->trigger();

// Set up the page.
$PAGE->set_url('/mod/mtssdashboard/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('modulenameplural', 'mod_mtssdashboard'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_mtssdashboard'));

// Get all instances in this course.
$instances = $DB->get_records_sql(
    "SELECT s.*, cm.id AS cmid
       FROM {mtssdashboard} s
       JOIN {course_modules} cm ON cm.instance = s.id
       JOIN {modules} m ON m.id = cm.module AND m.name = 'mtssdashboard'
      WHERE s.course = :courseid
      ORDER BY s.name ASC",
    ['courseid' => $course->id]
);

if (empty($instances)) {
    notice(
        get_string('nostudentdata', 'mod_mtssdashboard'),
        new moodle_url('/course/view.php', ['id' => $course->id])
    );
}

// Build the table.
$table = new html_table();
$table->head = [
    get_string('mtssdashboardname', 'mod_mtssdashboard'),
    get_string('filter_subject', 'mod_mtssdashboard'),
    get_string('filter_year', 'mod_mtssdashboard'),
];
$table->attributes['class'] = 'generaltable mod_index';

foreach ($instances as $instance) {
    $link = html_writer::link(
        new moodle_url('/mod/mtssdashboard/view.php', ['id' => $instance->cmid]),
        format_string($instance->name)
    );

    $subject = !empty($instance->defaultsubject)
        ? $instance->defaultsubject
        : get_string('showallsubjects', 'mod_mtssdashboard');

    $year = !empty($instance->defaultyear)
        ? $instance->defaultyear
        : get_string('filter_all', 'mod_mtssdashboard');

    $table->data[] = [$link, $subject, $year];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
