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
 * Restore task for mod_mtssdashboard.
 *
 * @package    mod_mtssdashboard
 * @copyright  2026 National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/mtssdashboard/backup/moodle2/restore_mtssdashboard_stepslib.php');

/**
 * Provides the steps that make up the mtssdashboard restore task.
 */
class restore_mtssdashboard_activity_task extends restore_activity_task {
    /**
     * Define any settings specific to this module (none required).
     */
    protected function define_my_settings() {
        // No module-specific restore settings.
    }

    /**
     * Define the restore steps for this activity.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_mtssdashboard_activity_structure_step(
            'mtssdashboard_structure',
            'mtssdashboard.xml'
        ));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder.
     *
     * @return array
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content(
            'mtssdashboard',
            ['intro'],
            'mtssdashboard'
        );

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging to this activity.
     *
     * @return array
     */
    public static function define_decode_rules() {
        // This module has no internal links that need decoding.
        return [];
    }

    /**
     * Define the restore log rules that will be applied by the
     * {@see restore_logs_processor} when restoring logs.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        return [];
    }

    /**
     * Define the restore log rules that will be applied by the
     * {@see restore_logs_processor} when restoring course logs
     * for pre-2.7 backups.
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course() {
        return [];
    }
}
