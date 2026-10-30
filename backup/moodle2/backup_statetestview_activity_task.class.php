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
 * Backup task for mod_mtssdashboard.
 *
 * @package    mod_mtssdashboard
 * @copyright  2026 National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/mtssdashboard/backup/moodle2/backup_mtssdashboard_stepslib.php');

/**
 * Provides the steps that make up the mtssdashboard backup task.
 */
class backup_mtssdashboard_activity_task extends backup_activity_task {

    /**
     * Define any settings specific to this module (none required).
     */
    protected function define_my_settings() {
        // No module-specific backup settings.
    }

    /**
     * Define the backup steps for this activity.
     *
     * Adds the single structure step that serialises the instance record and
     * the site-wide warning levels into the backup XML.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_mtssdashboard_activity_structure_step(
            'mtssdashboard_structure',
            'mtssdashboard.xml'
        ));
    }

    /**
     * Code the transformations any attribute/text in the backup file should
     * have (none for this module).
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        // This module has no internal links that need encoding.
        return $content;
    }
}
