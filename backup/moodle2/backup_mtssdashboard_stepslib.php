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
 * Backup steps for mod_mtssdashboard.
 *
 * Backs up the main activity instance and, as supplemental site-wide
 * configuration, all rows from mtssdashboard_warning_levels.  The warning
 * levels are included so they are not lost in a backup, but they are only
 * restored when the destination site has no rows in that table.
 *
 * @package    mod_mtssdashboard
 * @copyright  2026 National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the complete mtssdashboard backup structure.
 */
class backup_mtssdashboard_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the backup structure tree.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        // ------------------------------------------------------------------
        // 1. Main instance element — fields from the mtssdashboard table.
        $mtssdashboard = new backup_nested_element('mtssdashboard', ['id'], [
            'name',
            'intro',
            'introformat',
            'defaultyear',
            'defaultsubject',
            'timecreated',
            'timemodified',
        ]);

        // ------------------------------------------------------------------
        // 2. Warning levels — site-wide config, included for preservation.
        // Nested under the activity element so they travel with the backup.
        $warninglevels = new backup_nested_element('warning_levels');

        $warninglevel = new backup_nested_element('warning_level', ['id'], [
            'schoollevel',
            'metric_key',
            'color',
            'threshold_min',
            'threshold_max',
            'threshold_text',
            'sortorder',
            'timemodified',
            'usermodified',
        ]);

        // Build the tree.
        $mtssdashboard->add_child($warninglevels);
        $warninglevels->add_child($warninglevel);

        // ------------------------------------------------------------------
        // 3. Data sources.

        // Main instance — use the standard per-activity WHERE clause.
        $mtssdashboard->set_source_table('mtssdashboard', ['id' => backup::VAR_ACTIVITYID]);

        // Warning levels — back up ALL rows (site-wide, not per-instance).
        $warninglevel->set_source_table('mtssdashboard_warning_levels', []);

        // ------------------------------------------------------------------
        // 4. No user-data annotations needed for this module.

        // Return the root of the structure wrapped in the standard envelope.
        return $this->prepare_activity_structure($mtssdashboard);
    }
}
