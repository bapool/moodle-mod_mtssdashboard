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
 * Mod form for mod_mtssdashboard.
 *
 * @package    mod_mtssdashboard
 * @copyright  2026 National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/mtssdashboard/lib.php');

/**
 * Module instance settings form.
 */
class mod_mtssdashboard_mod_form extends moodleform_mod {
    /**
     * Define the form elements.
     */
    public function definition() {
        global $DB;

        $mform = $this->_form;

        // General section.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Name field (required).
        $mform->addElement(
            'text',
            'name',
            get_string('mtssdashboardname', 'mod_mtssdashboard'),
            ['size' => '64']
        );
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'mtssdashboardname', 'mod_mtssdashboard');
        $mform->setDefault('name', get_string('modulename', 'mod_mtssdashboard'));

        // Standard intro editor.
        $this->standard_intro_elements();

        // Default display settings.
        $mform->addElement(
            'header',
            'defaultdisplay',
            get_string('defaultdisplay', 'mod_mtssdashboard')
        );
        $mform->setExpanded('defaultdisplay');

        // Default year dropdown — populated from the database.
        $yearoptions = ['' => get_string('filter_all', 'mod_mtssdashboard')]
            + mtssdashboard_get_years();

        $mform->addElement(
            'select',
            'defaultyear',
            get_string('defaultyear', 'mod_mtssdashboard'),
            $yearoptions
        );
        $mform->setDefault('defaultyear', '');
        $mform->addHelpButton('defaultyear', 'defaultyear', 'mod_mtssdashboard');

        // Default subject dropdown — populated from the database.
        $subjectoptions = ['' => get_string('showallsubjects', 'mod_mtssdashboard')]
            + mtssdashboard_get_subjects();

        $mform->addElement(
            'select',
            'defaultsubject',
            get_string('defaultsubject', 'mod_mtssdashboard'),
            $subjectoptions
        );
        $mform->setDefault('defaultsubject', '');
        $mform->addHelpButton('defaultsubject', 'defaultsubject', 'mod_mtssdashboard');

        // Standard Moodle form elements (grading, etc.).
        $this->standard_coursemodule_elements();

        // Action buttons.
        $this->add_action_buttons();
    }

    /**
     * Custom validation.
     *
     * @param array $data  Submitted form data
     * @param array $files Submitted files
     * @return array Errors array (empty if valid)
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty(trim($data['name']))) {
            $errors['name'] = get_string('required');
        }

        return $errors;
    }
}
