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
 * Language strings for mod_mtssdashboard.
 *
 * @package    mod_mtssdashboard
 * @copyright  2026 National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Core required strings.
$string['modulename']             = 'My Student Data';
$string['modulenameplural']       = 'My Student Data';
$string['modulename_help']        = 'My Student Data allows teachers to view student data for students enrolled in a course, including state testing results, trends over time, and demographic breakdowns.';
$string['pluginadministration']   = 'My Student Data administration';
$string['pluginname']             = 'My Student Data';

// Mod form settings.
$string['mtssdashboardname']      = 'Name';
$string['mtssdashboardname_help'] = 'Enter a name for this My Student Data instance.';
$string['defaultyear']            = 'Default year';
$string['defaultyear_help']       = 'Select the testing year to display by default when the resource is opened.';
$string['defaultsubject']         = 'Default subject';
$string['defaultsubject_help']    = 'Select the subject to display by default when the resource is opened.';
$string['showallsubjects']        = 'All subjects';

// Tabs.
$string['tab_warning']            = 'Warning Levels';
$string['tab_rimps']              = 'RIMPS';
$string['tab_gradeupdates']       = 'Grade Updates';
$string['gu_col_coursename']      = 'Course Name';
$string['gu_col_shortname']       = 'Short Name';
$string['gu_col_teachers']        = 'Teacher(s)';
$string['gu_col_lastupdate']      = 'Last Grade Update';
$string['gu_col_dayssince']       = 'Days Since Update';
$string['gu_no_courses']          = 'No gradebook activity found for any qualifying courses.';
$string['plan_view']              = 'View student plan (opens PDF in new tab)';
$string['rimps_intro']            = 'Check the box next to each student who has a Reading Improvement and Monitoring Plan (RIMP). This indicator will appear on the Early Warning tab for all teachers.';
$string['rimps_col_rimp']         = 'RIMP';
$string['ew_col_rimp']            = 'RIMP';
$string['tab_class']              = 'State Testing';
$string['tab_group']              = 'Testing History';
$string['tab_student']            = 'Student Info';
$string['tab_earlywarning']       = 'Early Warning';

// Filters.
$string['filter_year']            = 'Year';
$string['filter_subject']         = 'Subject';
$string['filter_group']           = 'Group';
$string['filter_student']         = 'Student';
$string['filter_gender']          = 'Gender';
$string['filter_race']            = 'Race/Ethnicity';
$string['filter_ed']              = 'Economically Disadvantaged';
$string['filter_swd']             = 'Students with Disabilities';
$string['filter_el']              = 'English Learners';
$string['filter_504']             = '504 Plan';
$string['filter_iep']             = 'IEP';
$string['filter_gifted']          = 'Gifted';
$string['filter_all']             = 'All';
$string['filter_yes']             = 'Yes';
$string['filter_no']              = 'No';
$string['applyfilters']           = 'Apply Filters';
$string['resetfilters']           = 'Reset Filters';

// Chart labels.
$string['chart_proficiency']      = 'Proficiency Distribution';
$string['chart_scores']           = 'Average Scores by Subject';
$string['chart_trend']            = 'Score Trend Over Time';
$string['chart_radar']            = 'Subject Proficiency Profile';
$string['chart_nodata']           = 'No data available for the selected filters.';

// Student Data tab — contact info / classes & grades panel.
$string['studentdata_contact_heading'] = 'Contact Information';
$string['studentdata_address']         = 'Address';
$string['studentdata_parentname']      = 'Parent/Guardian';
$string['studentdata_homephone']       = 'Home Phone';
$string['studentdata_email'] = 'Email';
$string['studentdata_lockercombo'] = 'Locker: {$a->locker} &nbsp;&nbsp; Combination: {$a->combination}';
$string['studentdata_classes_heading'] = 'Classes & Grades';
$string['studentdata_col_course']      = 'Class';
$string['studentdata_col_teacher']     = 'Teacher';
$string['studentdata_col_grade']       = 'Grade';
$string['studentdata_nogrades']        = 'No current class grades found for this student.';

// Proficiency levels.
$string['prof_advanced']          = 'Advanced';
$string['prof_accomplished']      = 'Accomplished';
$string['prof_proficient']        = 'Proficient';
$string['prof_basic']             = 'Basic';
$string['prof_limited']           = 'Limited';
$string['prof_suppressed']        = 'Score Not Reported';

// Table headings.
$string['th_student']             = 'Student';
$string['th_grade']               = 'Grade';
$string['th_subject']             = 'Subject';
$string['th_year']                = 'Year';
$string['th_score']               = 'Score';
$string['th_proficiency']         = 'Proficiency Level';
$string['th_gender']              = 'Gender';
$string['th_race']                = 'Race/Ethnicity';
$string['th_ed']                  = 'Econ. Disadvantaged';
$string['th_swd']                 = 'Disability';
$string['th_el']                  = 'English Learner';
$string['th_504']                 = '504';
$string['th_iep']                 = 'IEP';
$string['th_gifted']              = 'Gifted';

// Errors and notices.
$string['nostudentdata']          = 'No state testing data found for students in this course.';
$string['nostudentlink']          = 'Student accounts could not be linked to state testing records.';
$string['selectstudent']          = 'Please select a student to view their individual data.';
$string['selectgroup']            = 'Please select a group.';
$string['nostudentenrolled']      = 'No students with linked state testing records are enrolled in this course.';

// Capabilities.
$string['mtssdashboard:addinstance'] = 'Add a My Student Data instance';
$string['mtssdashboard:view']        = 'View My Student Data';
$string['defaultdisplay'] = 'Default Display Settings';
// Summary table.
$string['prof_totals']        = 'Proficiency Totals';
$string['prof_testrecords']   = 'Test Records';
$string['prof_totalstudents'] = 'Total Students';
$string['prof_total']         = 'Total';
$string['prof_pct']           = '%';
$string['prof_bylevel']       = 'Proficiency Distribution';
$string['prof_bysubject']     = 'Proficiency Distribution — By Subject';
$string['class_view_heading'] = 'Class View — {$a->group} — {$a->year}';
$string['group_view_heading'] = 'Group View — {$a->group} — {$a->year}';
// Admin settings.
$string['settings_schoolyear_heading']      = 'School Year';
$string['settings_schoolyear_heading_desc'] = 'Configure the current school year for grade level calculations.';
$string['settings_gradyear']                = 'Current graduation year';
$string['settings_gradyear_desc']           = 'The year current 12th graders will graduate. Used to calculate what grade a student was in when they took a state test. Update this each school year.';
$string['settings_categories_heading']      = 'Course Categories';
$string['settings_categories_heading_desc'] = 'Set the Moodle course category ID for each school level. Find category IDs at Site Administration > Courses > Manage course categories.';
$string['settings_hs_category']             = 'High School category ID';
$string['settings_hs_category_desc']        = 'Moodle course category ID for High School courses (default: 2).';
$string['settings_ms_category']             = 'Middle School category ID';
$string['settings_ms_category_desc']        = 'Moodle course category ID for Middle School courses (default: 3).';
$string['settings_es_category']             = 'Elementary category ID';
$string['settings_es_category_desc']        = 'Moodle course category ID for Elementary courses (default: 28).';
$string['settings_graderanges_heading']     = 'Grade Level Ranges';
$string['settings_graderanges_heading_desc'] = 'Set the grade ranges for each school level. Used to determine which state test results are shown for each course type.';
$string['settings_hs_grade_min']            = 'High School minimum grade';
$string['settings_hs_grade_min_desc']       = 'Lowest grade considered High School (default: 9).';
$string['settings_hs_grade_max']            = 'High School maximum grade';
$string['settings_hs_grade_max_desc']       = 'Highest grade considered High School (default: 12).';
$string['settings_ms_grade_min']            = 'Middle School minimum grade';
$string['settings_ms_grade_min_desc']       = 'Lowest grade considered Middle School (default: 5).';
$string['settings_ms_grade_max']            = 'Middle School maximum grade';
$string['settings_ms_grade_max_desc']       = 'Highest grade considered Middle School (default: 8).';
$string['settings_es_grade_min']            = 'Elementary minimum grade';
$string['settings_es_grade_min_desc']       = 'Lowest grade considered Elementary (default: 1).';
$string['settings_es_grade_max']            = 'Elementary maximum grade';
$string['settings_es_grade_max_desc']       = 'Highest grade considered Elementary (default: 4).';

// Tabs.
$string['tab_history']                      = 'History';
$string['tab_subjecthistory']               = 'Subject History';

// History tab.
$string['history_hs_heading']              = 'Middle School Testing Results';
$string['history_ms_heading']              = 'Elementary Testing Results';
$string['history_es_heading']              = 'No previous testing data available for this level.';
$string['history_no_data']                 = 'No previous level testing data found for enrolled students.';
// Grades tab.
$string['tab_grades']                          = 'Grades';
$string['grades_thiscourse']                   = 'This Course';
$string['grades_allcourses']                   = 'All Courses (no grade data for this course)';
$string['grades_bysubject']                    = 'Grades by Subject Category';
$string['grades_category_math']                = 'Math';
$string['grades_category_english']             = 'English / ELA';
$string['grades_category_science']             = 'Science';
$string['grades_category_socialstudies']       = 'Social Studies';
$string['grades_category_electives']           = 'Electives';
$string['grades_nodata']                       = 'No gradebook data found for enrolled students.';
$string['grades_studentcount']                 = 'Total Students';
$string['grades_coursecount']                  = 'Courses included';
$string['grades_detail_student']               = 'Student';
$string['grades_detail_course']                = 'Course';
$string['grades_detail_category']              = 'Category';
$string['grades_detail_letter']                = 'Grade';
$string['grades_detail_pct']                   = '% Score';
$string['grades_showdetail']                   = 'Show / Hide Student Grade Detail';

// Grades admin settings.
$string['settings_grades_heading']             = 'Gradebook Settings';
$string['settings_grades_heading_desc']        = 'Configure the management courses whose grade letter scales are used for grade calculations, and the subject category mappings.';
$string['settings_hs_mgmt_course']             = 'High School management course ID';
$string['settings_hs_mgmt_course_desc']        = 'Course ID whose grade letter scale is used for all High School courses (default: 53).';
$string['settings_ms_mgmt_course']             = 'Middle School management course ID';
$string['settings_ms_mgmt_course_desc']        = 'Course ID whose grade letter scale is used for all Middle School courses (default: 54).';
$string['settings_es_mgmt_course']             = 'Elementary management course ID';
$string['settings_es_mgmt_course_desc']        = 'Course ID whose grade letter scale is used for all Elementary courses (default: 118).';
$string['settings_hs_cat_math']                = 'HS Math category IDs';
$string['settings_hs_cat_math_desc']           = 'Comma-separated Moodle category IDs that count as Math for High School (default: 21).';
$string['settings_hs_cat_english']             = 'HS English category IDs';
$string['settings_hs_cat_english_desc']        = 'Comma-separated Moodle category IDs that count as English for High School (default: 17).';
$string['settings_hs_cat_science']             = 'HS Science category IDs';
$string['settings_hs_cat_science_desc']        = 'Comma-separated Moodle category IDs that count as Science for High School (default: 23).';
$string['settings_hs_cat_socialstudies']       = 'HS Social Studies category IDs';
$string['settings_hs_cat_socialstudies_desc']  = 'Comma-separated Moodle category IDs that count as Social Studies for High School (default: 24).';
$string['settings_ms_cat_math']                = 'MS Math category IDs';
$string['settings_ms_cat_math_desc']           = 'Comma-separated Moodle category IDs that count as Math for Middle School (default: 8).';
$string['settings_ms_cat_english']             = 'MS English category IDs';
$string['settings_ms_cat_english_desc']        = 'Comma-separated Moodle category IDs that count as English for Middle School (default: 6).';
$string['settings_ms_cat_science']             = 'MS Science category IDs';
$string['settings_ms_cat_science_desc']        = 'Comma-separated Moodle category IDs that count as Science for Middle School (default: 10).';
$string['settings_ms_cat_socialstudies']       = 'MS Social Studies category IDs';
$string['settings_ms_cat_socialstudies_desc']  = 'Comma-separated Moodle category IDs that count as Social Studies for Middle School (default: 11).';
// Warning Levels tab.
$string['warning_heading_hs']            = 'High School Warning Levels';
$string['warning_heading_ms']            = 'Middle School Warning Levels';
$string['warning_heading_es']            = 'Elementary Warning Levels';
$string['warning_intro']                 = 'These thresholds are used to flag students at risk based on attendance, behavior, academic performance, and assessment data. Each metric is divided into colored bands.';
$string['warning_intro_editable']        = 'You are viewing the management course for this school level. Managers can edit these thresholds; changes apply everywhere this activity is used.';
$string['warning_intro_readonly']        = 'These thresholds are read-only here. They are maintained on the management course for this school level.';
$string['warning_es_placeholder']        = 'Elementary warning levels have not been configured yet.';
$string['warning_no_data']               = 'No warning levels are currently configured for this school level.';
$string['warning_btn_edit']              = 'Edit thresholds';
$string['warning_btn_cancel']            = 'Cancel editing';
$string['warning_btn_save']              = 'Save changes';
$string['warning_confirm_save']          = 'Are you sure you want to save these threshold changes? This will affect every course where this activity is used.';
$string['warning_saved']                 = 'Warning levels saved successfully.';
$string['warning_invalid_value']         = 'Invalid threshold value.';

// Metric labels.
$string['metric_total_hours_missed']     = 'Total Hours Missed';
$string['metric_unexcused_hours_missed'] = 'Unexcused Hours Missed';
$string['metric_office_incidents']       = 'Office Incidents';
$string['metric_course_failure_count']   = 'Course Failure Count';
$string['metric_gpa']                    = 'GPA';
$string['metric_iready_ela_percentile']  = 'iReady ELA Percentile';
$string['metric_iready_math_percentile'] = 'iReady Math Percentile';
$string['metric_iready_ela_days']        = 'Days Since ELA Lesson';
$string['metric_iready_math_days']       = 'Days Since Math Lesson';

// IReady Lessons by Grade toggle (Warning Levels tab, MS).
$string['lessonsoff_heading']       = 'iReady Lessons by Grade';
$string['lessonsoff_desc']          = 'Check a grade if those students take iReady diagnostics but not iReady lessons in that subject. Their "Days Since Lesson" column is left blank and does not count toward Overall Risk. Percentiles are not affected.';
$string['lessonsoff_subject_ela']   = 'Reading (ELA) lessons off';
$string['lessonsoff_subject_math']  = 'Math lessons off';
$string['lessonsoff_grade']         = 'Grade {$a}';
$string['lessonsoff_none']          = 'None (all grades use lessons)';
$string['lessonsoff_grades']        = 'Grade {$a}';
$string['rimplessonsoff_heading']   = 'iReady Lessons for RIMP Students';
$string['rimplessonsoff_desc']      = 'Check a grade if students in that grade who have a RIMP take iReady diagnostics but not iReady lessons in that subject. For those students, the "Days Since Lesson" column is left blank and does not count toward Overall Risk. Students without a RIMP and percentiles are not affected.';
$string['rimplessonsoff_none']      = 'None (RIMP students in all grades use lessons)';
$string['metric_ost_performance']        = 'OST Performance';
$string['metric_ost_passing_score']      = 'ELA/Math passing score';
$string['metric_ost_performance_es']     = 'Low OST Count';

// Color labels.
$string['color_green']                   = 'Green';
$string['color_yellow']                  = 'Yellow';
$string['color_orange']                  = 'Orange';
$string['color_red']                     = 'Red';
$string['color_purple']                  = 'Purple';
// Early Warning tab.
$string['ew_heading']                = 'Early Warning Indicators';
$string['ew_intro']                  = 'Please filter before sorting by risk. Note: Cell colors are based on the thresholds configured under "Warning Levels" for this school level.  Highlighted IDs are links to student plans';
$string['ew_no_students']            = 'No students with linked records are enrolled in this course.';
$string['ew_col_grade']              = 'Grade';
$string['ew_col_studentid']          = 'Student ID';
$string['ew_col_name']               = 'Student';
$string['ew_col_overall']            = 'Overall Risk';
$string['ew_col_total_hours']        = 'Total Hours Missed';
$string['ew_col_unexcused_hours']    = 'Unexcused Hours';
$string['ew_col_office']             = 'Office Incidents';
$string['ew_col_failed']             = 'Failing Courses';
$string['ew_col_gpa']                = 'Current GPA';
$string['ew_col_iready_ela']         = 'iReady ELA Percentile';
$string['ew_col_iready_math']        = 'iReady Math Percentile';
$string['ew_col_iready_ela_days']    = 'Days Since ELA Lesson';
$string['ew_col_iready_math_days']   = 'Days Since Math Lesson';
$string['ew_never']                  = 'Never';
$string['ew_col_ela_fall_score']     = 'Fall ELA Score';
$string['ew_col_ela_fall_pct']       = 'Fall ELA %';
$string['ew_col_ela_winter_score']   = 'Winter ELA Score';
$string['ew_col_ela_winter_pct']     = 'Winter ELA %';
$string['ew_col_ela_spring_score']   = 'Spring ELA Score';
$string['ew_col_ela_spring_pct']     = 'Spring ELA %';
$string['ew_col_math_fall_score']    = 'Fall Math Score';
$string['ew_col_math_fall_pct']      = 'Fall Math %';
$string['ew_col_math_winter_score']  = 'Winter Math Score';
$string['ew_col_math_winter_pct']    = 'Winter Math %';
$string['ew_col_math_spring_score']  = 'Spring Math Score';
$string['ew_col_math_spring_pct']    = 'Spring Math %';
$string['ew_col_low_ost']            = 'Low OST Count';
$string['ew_risk_none']              = 'No Risk';
$string['ew_risk_low']               = 'Low Risk';
$string['ew_risk_moderate']          = 'Moderate Risk';
$string['ew_risk_extreme']           = 'Extreme Risk';
$string['ew_pending_data']           = 'Coming soon';
$string['ew_no_data']                = '—';
$string['ew_btn_sort_risk']          = 'Sort by Risk';
$string['ew_btn_sort_name']          = 'Sort A–Z';
// ----------------------------------------------------------------------
// LANG STRING ADDITIONS FOR v0.2.4
// Append these lines to the END of lang/en/mtssdashboard.php.

// MTSS tab.
$string['tab_rimp']                   = 'RIMP';
$string['rimp_tab_intro']                  = 'Select a student to create or edit their Reading Improvement and Monitoring Plan (RIMP). The table below shows the current RIMP status for all enrolled students.';
$string['rimp_select_student']         = 'Select a student';
$string['mtss_select_student']         = 'Select a student';
$string['school_name_es']              = 'National Trail Elementary School';
$string['school_name_ms']              = 'National Trail Middle School';
$string['school_name_hs']              = 'National Trail High School';
$string['rimp_btn_go']                 = 'View';
$string['rimp_btn_download']           = 'Download RIMPs';
$string['rimp_btn_download_help']      = 'Open every finalized RIMP for the students currently shown (current group and filters) as one document to save as a PDF.';
$string['rimp_bulk_title']             = 'RIMPs - {$a}';
$string['rimp_bulk_count']             = '{$a->count} finalized RIMP(s). In the print window choose "Save as PDF".';
$string['rimp_bulk_none']              = 'There are no finalized RIMPs for the students currently shown.';
$string['rimp_class_table_heading']    = 'Class RIMP Status';

// RIMP edit page.
$string['rimp_edit_title']             = 'RIMP — Reading Improvement and Monitoring Plan';
$string['rimp_none_exists']            = 'No RIMP has been created for this student yet.';
$string['rimp_none_short']             = 'None';
$string['rimp_status_label']           = 'Status:';
$string['rimp_last_modified']          = 'Last modified:';
$string['rimp_col_action']             = 'Action';
$string['rimp_col_studentid']          = 'Student ID';
$string['rimp_col_dob']                = 'Date of Birth';
$string['rimp_col_grade']              = 'Current Grade';
$string['rimp_col_gender']             = 'Gender';

// RIMP status badges.
$string['rimp_status_draft']           = 'Draft';
$string['rimp_status_finalized']       = '';
$string['rimp_status_on_track']        = 'On Track';
$string['rimp_status_not_on_track']    = 'Not On Track';

$string['rimp_k_ontrack']      = 'On Track 320';
$string['rimp_grade1_ontrack'] = 'On Track 377';
$string['rimp_grade2_ontrack'] = 'On Track 425';
$string['rimp_grade3_ontrack'] = 'On Track 469';

// RIMP buttons.
$string['rimp_btn_new']                = 'Create New RIMP';
$string['rimp_btn_edit']               = 'Edit RIMP';
$string['rimp_btn_print']              = 'Print RIMP';
$string['rimp_btn_print_now']          = 'Print / Save as PDF';
$string['rimp_btn_save_draft']         = 'Save as Draft';
$string['rimp_btn_finalize']           = 'Save RIMP';
$string['rimp_btn_cancel']             = 'Cancel';
$string['rimp_btn_delete']             = 'Delete RIMP';
$string['rimp_delete_confirm']         = 'Are you sure you want to delete this RIMP? This cannot be undone.';
$string['rimp_deleted']                = 'RIMP deleted.';

// RIMP save confirmations.
$string['rimp_saved_draft']            = 'RIMP saved as draft.';
$string['rimp_finalized']              = 'RIMP has been finalized.';

// RIMP finalized warning.
$string['rimp_finalized_warning_title'] = 'This RIMP is finalized.';
$string['rimp_finalized_warning_body'] = 'Any changes you save will revert it to Draft status until it is finalized again.';
$string['rimp_finalized_edit_confirm'] = 'This RIMP is currently Finalized. Saving changes will revert it to Draft status. Do you want to continue?';
$string['rimp_lock_title']             = 'This RIMP is being edited';
$string['rimp_lock_message']           = 'This RIMP is currently being edited by {$a}. Please close this window and try again later.';
$string['rimp_lock_ok']                = 'OK';
$string['rimp_edit_conflict']          = 'This RIMP was changed by someone else while you were editing it, so your changes were not saved. Please reopen it and try again.';
$string['rimp_lock_lost']              = 'This RIMP is now being edited by {$a}. You will be returned to the dashboard without saving.';

// RIMP form sections.
$string['rimp_section_header']         = 'Plan Information';
$string['rimp_section_goals']          = 'Goals — This plan was created to improve:';
$string['rimp_section_supports']       = 'Instructional Supports';
$string['rimp_section_frequency']      = 'Services and Supports';
$string['rimp_section_monitoring']     = 'Plans for Monitoring Student Success';
$string['rimp_section_grade3']         = "Ohio's Third Grade Reading Guarantee";
$string['rimp_section_signatures']     = 'Signatures';
$string['rimp_section_assessment_data'] = 'Assessment Data';

// RIMP form field labels.
$string['rimp_schoolyear']             = 'School Year';
$string['rimp_effective_date']         = 'RIMP Effective Date';
$string['rimp_student_score']          = 'Student Score';
$string['rimp_assessment_status']      = 'Student Assessment Status';
$string['rimp_goals_label']            = 'Goals';
$string['rimp_supports_label']         = 'Instructional Supports';
$string['rimp_days_per_week']          = 'Number of Days Per Week';
$string['rimp_minutes_per_week']       = 'Number of Minutes Per Week';
$string['rimp_monitoring_assessment']  = 'Assessment Used for Progress Monitoring';
$string['rimp_monitoring_frequency']   = 'Frequency of Monitoring';
$string['rimp_monitored_by']           = 'Intervention Plan Monitored By';
$string['rimp_grade3_label']           = "Third Grade Reading Guarantee";
$string['rimp_sig_parent']             = 'Parent/Guardian';
$string['rimp_sig_teacher']            = 'Current Teacher';
$string['rimp_sig_ela_teacher']        = 'English Language Arts Teacher';
$string['rimp_sig_intervention']       = 'Intervention/Title Teacher';
$string['rimp_select']                 = 'Select...';

// Goal options.
$string['rimp_goal_phonemic']          = 'Phonemic Awareness';
$string['rimp_goal_phonics']           = 'Phonics';
$string['rimp_goal_fluency']           = 'Reading Fluency';
$string['rimp_goal_vocabulary']        = 'Vocabulary';
$string['rimp_goal_comprehension']     = 'Reading Comprehension';

// Support options.
$string['rimp_sup_phon_awareness']     = 'Explicit Intervention in Phonemic Awareness';
$string['rimp_sup_high_freq_words']    = 'Explicit Intervention in High Frequency Word Recognition';
$string['rimp_sup_decoding']           = 'Explicit Intervention in Decoding';
$string['rimp_sup_fluency']            = 'Explicit Intervention in Fluency';
$string['rimp_sup_comprehension']      = 'Explicit Intervention in Comprehension';
$string['rimp_sup_vocabulary']         = 'Explicit Intervention in Vocabulary';

// Frequency options.
$string['rimp_freq_weekly']            = 'Weekly';
$string['rimp_freq_biweekly']          = 'Biweekly';
$string['rimp_freq_monthly']           = 'Monthly';
$string['rimp_freq_quarterly']         = 'Quarterly';

// Monitored-by options.
$string['rimp_monitored_intervention'] = 'Intervention Specialist';
$string['rimp_monitored_title1']       = 'Title 1 Teacher';
$string['rimp_monitored_classroom']    = 'Classroom Teacher';

// Third-grade guarantee options.
$string['rimp_grade3_na']              = 'Not applicable at this time';
$string['rimp_grade3_retain']          = 'Student will be retained in grade 3';
$string['rimp_grade3_promote']         = 'Parent/guardian requested promotion to grade 4';
$string['rimp_grade3_met']             = 'Student scored 700 or above — no guarantee required';

// Print page.
$string['rimp_print_title']            = 'Ohio RIMP — Reading Improvement and Monitoring Plan';
$string['rimp_print_subtitle']         = 'Ohio (RIMP) Reading Improvement and Monitoring Plan';
$string['rimp_print_footer']           = '{$a->student} ({$a->sn}) — Generated from Moodle';
$string['rimp_school_default']         = 'National Trail Local Schools';

// Error strings.
$string['last_edited_by']              = 'Last Edited By';
$string['invalidstudent']              = 'Invalid or unenrolled student.';
$string['invalidrecordtype']           = 'Invalid record type.';
$string['invalidlockaction']           = 'Invalid lock action.';
$string['edit_lock_unknown_user']      = 'another user';
$string['rimp_not_found']              = 'No RIMP record found for this student.';
$string['wep_not_found']               = 'No WEP record found for this student.';
// ----------------------------------------------------------------------
// LANG STRING ADDITIONS FOR v0.2.4
// Append these lines to the END of lang/en/mtssdashboard.php.

// MTSS tab.
$string['tab_mtss']                    = 'MTSS';
$string['mtss_intro']                  = 'Select a student to create or edit their Reading Improvement and Monitoring Plan (RIMP). The table below shows the current RIMP status for all enrolled students.';
$string['mtss_btn_go']                 = 'View';
$string['mtss_class_table_heading']    = 'Class RIMP Status';

// RIMP edit page.

// RIMP status badges.

// RIMP buttons.

// RIMP save confirmations.

// RIMP finalized warning.

// RIMP form sections.

// RIMP form field labels.

// Goal options.

// Support options.

// Frequency options.

// Monitored-by options.

// Third-grade guarantee options.

// Print page.

// Error strings.

// Additional support options (v0.2.4 form update).
$string['rimp_sup_writing_found']    = 'Explicit Intervention in Writing (Foundational/Transcription)';
$string['rimp_sup_writing_comp']     = 'Explicit Intervention in Writing (Composition Skills)';
$string['rimp_sup_scaffolding']      = 'Small Group Scaffolding of Complex Text';
$string['rimp_sup_communication']    = 'Explicit Intervention in Communication/Language';
$string['rimp_sup_multimodal']       = 'Multi-Modal Approach to Structured Literacy';
$string['rimp_sup_other']            = 'Other';

// Monitored by — Other option.
$string['rimp_monitored_other']      = 'Other';

// MS monitoring assessment checkboxes.
$string['rimp_mon_uflr']             = 'UFLR- after every two lessons';
$string['rimp_mon_phonics']          = 'Phonics for Reading- a review at the end of each lesson and a check up at the end of each unit';
$string['rimp_mon_rewards']          = 'Rewards- a review after every two lessons and a check up at the end of each unit';

// Third Grade Guarantee — corrected options.
$string['rimp_grade3_score700']      = 'The student reached the promotion score of 700 on the Ohio State Test for grade 3 English language arts.';
$string['rimp_grade3_alternative']   = 'The student reached the promotion score on an approved alternative reading assessment given by the district.';
$string['rimp_grade3_parentrequest'] = 'A parent/guardian, in consultation with the school, can request that their child be promoted to fourth grade even if they do not reach the promotion score. In this case, the student will continue to be supported with a RIMP and provided with intensive instruction, until they are able to read at grade level.';
$string['tab_plans']        = 'Plans';
$string['plans_intro']      = 'Student plan status for enrolled students. Click the date to open the plan PDF.';
$string['plans_col_status'] = 'Status';
$string['plans_col_plan']   = 'Plan Date';
$string['plans_no_plan']    = 'No Plan';

// ----------------------------------------------------------------------
// WEP (Written Education Plan) strings — v0.2.5.

// Tab label.
$string['tab_wep']                          = 'WEP';

// WEP tab intro / class table.
$string['wep_tab_intro']                    = 'Select a student to create or edit their Written Education Plan (WEP). The table below shows the current WEP status for all enrolled students.';
$string['wep_class_table_heading']          = 'Class WEP Status';
$string['wep_select_student']               = 'Select a student';
$string['wep_btn_go']                       = 'View';
$string['wep_btn_download']                 = 'Download WEPs';
$string['wep_btn_download_help']            = 'Open every finalized WEP for the students currently shown (current group and filters) as one document to save as a PDF.';
$string['wep_bulk_title']                   = 'WEPs - {$a}';
$string['wep_bulk_count']                   = '{$a->count} finalized WEP(s). In the print window choose "Save as PDF".';
$string['wep_bulk_none']                    = 'There are no finalized WEPs for the students currently shown.';
$string['bulk_btn_print']                   = 'Print / Save as PDF';

// WEP edit page — page title.
$string['wep_edit_title']                   = 'WEP — Written Education Plan';
$string['wep_edit_title_es']                = 'Written Education Plan';
$string['wep_edit_title_mshs']              = 'Written Education Plan';

// WEP status.
$string['wep_none_exists']                  = 'No WEP has been created for this student yet.';
$string['wep_none_short']                   = 'None';
$string['wep_status_label']                 = 'Status:';
$string['wep_last_modified']                = 'Last modified:';
$string['wep_col_action']                   = 'Action';
$string['wep_col_studentid']                = 'Student ID';
$string['wep_col_dob']                      = 'Date of Birth';
$string['wep_col_grade']                    = 'Current Grade';
$string['wep_col_gender']                   = 'Gender';

// WEP status badges.
$string['wep_status_draft']                 = 'Draft';
$string['wep_status_finalized']             = '';

// WEP buttons.
$string['wep_btn_new']                      = 'Create New WEP';
$string['wep_btn_edit']                     = 'Edit WEP';
$string['wep_btn_print']                    = 'Print WEP';
$string['wep_btn_print_now']                = 'Print / Save as PDF';
$string['wep_btn_save_draft']               = 'Save as Draft';
$string['wep_btn_finalize']                 = 'Save WEP';
$string['wep_btn_cancel']                   = 'Cancel';
$string['wep_btn_reset']                    = 'Reset WEP';
$string['wep_reset_confirm']                = 'Reset this WEP for the {$a} school year? This will set the school year to {$a} and the review date to today, keep all checked boxes as they are, and clear every goal, progress, parent input, and signature text field. This cannot be undone once saved.';
$string['wep_btn_delete']                   = 'Delete WEP';
$string['wep_delete_confirm']               = 'Are you sure you want to delete this WEP? This cannot be undone.';
$string['wep_deleted']                      = 'WEP deleted.';

// WEP save confirmations.
$string['wep_saved_draft']                  = 'WEP saved as draft.';
$string['wep_finalized']                    = 'WEP has been finalized.';

// WEP finalized warning.
$string['wep_finalized_warning_title']      = 'This WEP is finalized.';
$string['wep_finalized_warning_body']       = 'Any changes you save will revert it to Draft status until it is finalized again.';
$string['wep_finalized_edit_confirm']       = 'This WEP is currently Finalized. Saving changes will revert it to Draft status. Do you want to continue?';
$string['wep_lock_title']                   = 'This WEP is being edited';
$string['wep_lock_message']                 = 'This WEP is currently being edited by {$a}. Please close this window and try again later.';
$string['wep_lock_ok']                      = 'OK';
$string['wep_edit_conflict']                = 'This WEP was changed by someone else while you were editing it, so your changes were not saved. Please reopen it and try again.';
$string['wep_lock_lost']                    = 'This WEP is now being edited by {$a}. You will be returned to the dashboard without saving.';

// WEP form sections.
$string['wep_section_header']               = 'Plan Information';
$string['wep_section_identification']       = 'Area(s) of Identification';
$string['wep_section_intro_text']           = 'The Written Education Plan (WEP) is defined in the Ohio Administrative Code 3301-51-15 and adheres to district policies on privacy and records. Information on appeals, withdrawals, and service requirements are available upon request through the district central office or on the district website. Parents/Guardians will be notified of the student\'s progress at the middle and end of the school year, or as appropriate for the length of the course. A Written Education Plan (WEP) is a document that outlines the goals of the service for students who are identified as gifted. These goals are aligned to Ohio State Learning Standards and designed to meet the needs of students and optimize growth. It is a collaborative communication tool for the parents/guardians, gifted service provider, and the student. We value your partnership in supporting your child\'s learning. If you would like to provide input or suggestions regarding your child\'s goals, please contact the Gifted Supervisor. Your feedback will help ensure that the plan reflects your child\'s strengths, interests, and needs.';
$string['wep_section_intro_footer']         = 'Your child will receive goals below in their identified areas. Identifications in Superior Cognitive or Creative Thinking Ability will receive goals in all academic areas.';
$string['wep_section_service']              = 'Service Setting / EMIS Code';
$string['wep_section_progress_measures']    = 'Student Progress Measures';
$string['wep_section_personnel']            = 'Personnel Responsible for Service';
$string['wep_section_goals']                = 'Goals';
$string['wep_section_progress']             = 'Progress';
$string['wep_section_parent_input']         = 'Parent Input';
$string['wep_section_signatures']           = 'WEP Signatures';

// WEP form field labels — header.
$string['wep_schoolyear']                   = 'School Year - Reviewed Annually';
$string['wep_review_date']                  = 'Review Date';

// Identification area checkboxes.
$string['wep_id_superior_cognitive']        = 'Superior Cognitive Ability';
$string['wep_id_superior_creative']         = 'Superior Creative Thinking Ability';
$string['wep_id_reading_writing']           = 'Reading/Writing';
$string['wep_id_mathematics']               = 'Mathematics';
$string['wep_id_science']                   = 'Science';
$string['wep_id_social_studies']            = 'Social Studies';

// Service setting checkboxes — ES.
$string['wep_svc_cluster_es']               = 'Cluster Grouping (HIVE time)';
$string['wep_svc_other']                    = 'Other';

// Service setting checkboxes — MS/HS.
$string['wep_svc_cluster_mshs']             = 'Cluster Grouping (205062)';
$string['wep_svc_acceleration']             = 'Acceleration (205052)';
$string['wep_svc_college_credit']           = 'College Credit Plus (205070)';

// Progress measure checkboxes.
$string['wep_pm_observations']              = 'Observations/ Checklists';
$string['wep_pm_projects']                  = 'Projects';
$string['wep_pm_benchmarks']                = 'Benchmarks';
$string['wep_pm_presentations']             = 'Presentations';
$string['wep_pm_assessments']               = 'Classroom Assessments';
$string['wep_pm_other']                     = 'Other';

// Personnel checkboxes.
$string['wep_per_gen_ed']                   = 'General Education Teacher';

// Goal field labels.
$string['wep_goal_reading_writing']         = 'WEP Measurable Annual Reading/Writing Goal';
$string['wep_goal_mathematics']             = 'WEP Measurable Annual Mathematics Goal';
$string['wep_goal_science']                 = 'WEP Measurable Annual Science Goal';
$string['wep_goal_social_studies']          = 'WEP Measurable Annual Social Studies Goal';

// Progress field labels.
$string['wep_midyear_reading']              = 'Mid Year Progress - Reading';
$string['wep_endofyear_reading']            = 'End of Year Progress - Reading';
$string['wep_midyear_mathematics']          = 'Mid Year Progress - Mathematics';
$string['wep_endofyear_mathematics']        = 'End of Year Progress - Mathematics';
$string['wep_midyear_science']              = 'Mid Year Progress - Science';
$string['wep_endofyear_science']            = 'End of Year Progress - Science';
$string['wep_midyear_social_studies']       = 'Mid Year Progress - Social Studies';
$string['wep_endofyear_social_studies']     = 'End of Year Progress - Social Studies';

// Parent input / signatures.
$string['wep_parent_input']                 = 'Parent Input';
$string['wep_sig_parent']                   = 'Parent/Guardian Representative';
$string['wep_sig_footer']                   = 'Distribute copies of this document to: student\'s building principal(s), current teacher, and parent(s) or legal guardian(s).';

// Print page.
$string['wep_print_title_es']               = 'Written Education Plan';
$string['wep_print_title_mshs']             = 'Written Education Plan';
$string['wep_print_footer']                 = '{$a->student} ({$a->sn}) — Generated from Moodle';
$string['wep_school_default']               = 'National Trail Local Schools';

// Import page.
$string['wep_import_title']                 = 'Import WEP Data from CSV';
$string['wep_import_intro']                 = 'Upload the district WEP CSV export to import historical records into the staging table. Choose the school level that matches the CSV file.';
$string['wep_import_label_level']           = 'School Level';
$string['wep_import_label_file']            = 'CSV File';
$string['wep_import_btn_submit']            = 'Import CSV';
$string['wep_import_success']               = 'Import complete. {$a} records loaded into the staging table.';
$string['wep_import_error_file']            = 'Could not read the uploaded file.';
$string['wep_import_error_cols']            = 'CSV column headers do not match the expected format for the selected school level.';
$string['wep_import_promote_title']         = 'Promote Staged Records to Live WEPs';
$string['wep_import_promote_intro']         = 'The staging table contains {$a} records. Click Promote to copy them into the live WEP table (existing records for the same student will be updated).';
$string['wep_import_btn_promote']           = 'Promote to Live WEPs';
$string['wep_import_promoted']              = 'Promotion complete. {$a} WEP records created or updated.';

// Capability strings.
$string['mtssdashboard:editrimp']           = 'Create and edit RIMP records';
$string['mtssdashboard:editwep']            = 'Create and edit WEP records';
$string['mtssdashboard:importwep']          = 'Import WEP data from CSV';
