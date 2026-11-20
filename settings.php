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
 * Admin settings for mod_mtssdashboard.
 *
 * @package    mod_mtssdashboard
 * @copyright  2026 National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // School Year.
    $settings->add(new admin_setting_heading(
        'mod_mtssdashboard/schoolyear_heading',
        get_string('settings_schoolyear_heading', 'mod_mtssdashboard'),
        get_string('settings_schoolyear_heading_desc', 'mod_mtssdashboard')
    ));

    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/gradyear',
        get_string('settings_gradyear', 'mod_mtssdashboard'),
        get_string('settings_gradyear_desc', 'mod_mtssdashboard'),
        '2026',
        PARAM_INT
    ));

    // Course Categories.
    $settings->add(new admin_setting_heading(
        'mod_mtssdashboard/categories_heading',
        get_string('settings_categories_heading', 'mod_mtssdashboard'),
        get_string('settings_categories_heading_desc', 'mod_mtssdashboard')
    ));

    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/hs_category',
        get_string('settings_hs_category', 'mod_mtssdashboard'),
        get_string('settings_hs_category_desc', 'mod_mtssdashboard'),
        '2',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/ms_category',
        get_string('settings_ms_category', 'mod_mtssdashboard'),
        get_string('settings_ms_category_desc', 'mod_mtssdashboard'),
        '3',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/es_category',
        get_string('settings_es_category', 'mod_mtssdashboard'),
        get_string('settings_es_category_desc', 'mod_mtssdashboard'),
        '28',
        PARAM_INT
    ));

    // Grade Ranges.
    $settings->add(new admin_setting_heading(
        'mod_mtssdashboard/graderanges_heading',
        get_string('settings_graderanges_heading', 'mod_mtssdashboard'),
        get_string('settings_graderanges_heading_desc', 'mod_mtssdashboard')
    ));

    // High School grade range.
    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/hs_grade_min',
        get_string('settings_hs_grade_min', 'mod_mtssdashboard'),
        get_string('settings_hs_grade_min_desc', 'mod_mtssdashboard'),
        '9',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/hs_grade_max',
        get_string('settings_hs_grade_max', 'mod_mtssdashboard'),
        get_string('settings_hs_grade_max_desc', 'mod_mtssdashboard'),
        '12',
        PARAM_INT
    ));

    // Middle School grade range.
    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/ms_grade_min',
        get_string('settings_ms_grade_min', 'mod_mtssdashboard'),
        get_string('settings_ms_grade_min_desc', 'mod_mtssdashboard'),
        '5',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/ms_grade_max',
        get_string('settings_ms_grade_max', 'mod_mtssdashboard'),
        get_string('settings_ms_grade_max_desc', 'mod_mtssdashboard'),
        '8',
        PARAM_INT
    ));

    // Elementary grade range.
    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/es_grade_min',
        get_string('settings_es_grade_min', 'mod_mtssdashboard'),
        get_string('settings_es_grade_min_desc', 'mod_mtssdashboard'),
        '1',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/es_grade_max',
        get_string('settings_es_grade_max', 'mod_mtssdashboard'),
        get_string('settings_es_grade_max_desc', 'mod_mtssdashboard'),
        '4',
        PARAM_INT
    ));
    // Gradebook Settings.
    $settings->add(new admin_setting_heading(
        'mod_mtssdashboard/grades_heading',
        get_string('settings_grades_heading', 'mod_mtssdashboard'),
        get_string('settings_grades_heading_desc', 'mod_mtssdashboard')
    ));

    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/hs_mgmt_course',
        get_string('settings_hs_mgmt_course', 'mod_mtssdashboard'),
        get_string('settings_hs_mgmt_course_desc', 'mod_mtssdashboard'),
        '53',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/ms_mgmt_course',
        get_string('settings_ms_mgmt_course', 'mod_mtssdashboard'),
        get_string('settings_ms_mgmt_course_desc', 'mod_mtssdashboard'),
        '54',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/es_mgmt_course',
        get_string('settings_es_mgmt_course', 'mod_mtssdashboard'),
        get_string('settings_es_mgmt_course_desc', 'mod_mtssdashboard'),
        '118',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/hs_cat_math',
        get_string('settings_hs_cat_math', 'mod_mtssdashboard'),
        get_string('settings_hs_cat_math_desc', 'mod_mtssdashboard'),
        '21',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/hs_cat_english',
        get_string('settings_hs_cat_english', 'mod_mtssdashboard'),
        get_string('settings_hs_cat_english_desc', 'mod_mtssdashboard'),
        '17',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/hs_cat_science',
        get_string('settings_hs_cat_science', 'mod_mtssdashboard'),
        get_string('settings_hs_cat_science_desc', 'mod_mtssdashboard'),
        '23',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/hs_cat_socialstudies',
        get_string('settings_hs_cat_socialstudies', 'mod_mtssdashboard'),
        get_string('settings_hs_cat_socialstudies_desc', 'mod_mtssdashboard'),
        '24',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/ms_cat_math',
        get_string('settings_ms_cat_math', 'mod_mtssdashboard'),
        get_string('settings_ms_cat_math_desc', 'mod_mtssdashboard'),
        '8',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/ms_cat_english',
        get_string('settings_ms_cat_english', 'mod_mtssdashboard'),
        get_string('settings_ms_cat_english_desc', 'mod_mtssdashboard'),
        '6',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/ms_cat_science',
        get_string('settings_ms_cat_science', 'mod_mtssdashboard'),
        get_string('settings_ms_cat_science_desc', 'mod_mtssdashboard'),
        '10',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_mtssdashboard/ms_cat_socialstudies',
        get_string('settings_ms_cat_socialstudies', 'mod_mtssdashboard'),
        get_string('settings_ms_cat_socialstudies_desc', 'mod_mtssdashboard'),
        '11',
        PARAM_TEXT
    ));
}
