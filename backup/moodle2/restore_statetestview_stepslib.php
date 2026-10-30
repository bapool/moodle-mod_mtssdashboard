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
 * Restore steps for mod_mtssdashboard.
 *
 * Restores the main activity instance unconditionally.  Warning levels are
 * only inserted when the mtssdashboard_warning_levels table is completely
 * empty on the destination site — preserving any existing site configuration.
 *
 * @package    mod_mtssdashboard
 * @copyright  2026 National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines all the restore steps for the mtssdashboard activity.
 */
class restore_mtssdashboard_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define the XML paths that this step processes.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];

        // Main instance record.
        $paths[] = new restore_path_element(
            'mtssdashboard',
            '/activity/mtssdashboard'
        );

        // Site-wide warning level rows (nested in the backup XML).
        $paths[] = new restore_path_element(
            'mtssdashboard_warning_level',
            '/activity/mtssdashboard/warning_levels/warning_level'
        );

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the main mtssdashboard instance record.
     *
     * @param array|object $data Raw data from the backup XML.
     */
    protected function process_mtssdashboard($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        // Bind to the current course.
        $data->course = $this->get_courseid();
        $data->timecreated  = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insert the new instance then immediately apply it so Moodle
        // wires the course-module instance field to the new record ID.
        $newid = $DB->insert_record('mtssdashboard', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restore a warning level row — only if the table is currently empty.
     *
     * This preserves existing site-wide configuration on the destination
     * while ensuring a fresh install gets sensible defaults from the backup.
     *
     * @param array|object $data Raw data from the backup XML.
     */
    protected function process_mtssdashboard_warning_level($data) {
        global $DB;

        // Skip entirely if warning levels already exist on this site.
        if ($DB->count_records('mtssdashboard_warning_levels') > 0) {
            return;
        }

        $data = (object) $data;

        // Drop the backed-up primary key; the DB will assign a new one.
        unset($data->id);

        // Remap the usermodified reference to the current restoring user
        // if the original user does not exist on this site.
        $data->usermodified = $this->get_mappingid('user', $data->usermodified)
            ?: $this->task->get_userid();

        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('mtssdashboard_warning_levels', $data);
    }

    /**
     * After all records are restored, update the course-module reference
     * so Moodle knows which DB row this activity points to.
     */
    protected function after_execute() {
        $this->add_related_files('mod_mtssdashboard', 'intro', null);
    }
}
