<?php
// =============================================================================
// LANG STRING ADDITIONS FOR v0.2.4
// Append these lines to the END of lang/en/mtssdashboard.php
// =============================================================================

// MTSS tab.
$string['tab_mtss']                    = 'MTSS';
$string['mtss_intro']                  = 'Select a student to create or edit their Reading Improvement and Monitoring Plan (RIMP). The table below shows the current RIMP status for all enrolled students.';
$string['mtss_select_student']         = 'Select a student';
$string['mtss_btn_go']                 = 'View';
$string['mtss_class_table_heading']    = 'Class RIMP Status';

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
$string['rimp_status_finalized']       = 'Finalized';
$string['rimp_status_on_track']        = 'On Track';
$string['rimp_status_not_on_track']    = 'Not On Track';

// RIMP buttons.
$string['rimp_btn_new']                = 'Create New RIMP';
$string['rimp_btn_edit']               = 'Edit RIMP';
$string['rimp_btn_print']              = 'Print RIMP';
$string['rimp_btn_print_now']          = 'Print / Save as PDF';
$string['rimp_btn_save_draft']         = 'Save as Draft';
$string['rimp_btn_finalize']           = 'Finalize RIMP';
$string['rimp_btn_cancel']             = 'Cancel';

// RIMP save confirmations.
$string['rimp_saved_draft']            = 'RIMP saved as draft.';
$string['rimp_finalized']              = 'RIMP has been finalized.';

// RIMP finalized warning.
$string['rimp_finalized_warning_title']= 'This RIMP is finalized.';
$string['rimp_finalized_warning_body'] = 'Any changes you save will revert it to Draft status until it is finalized again.';
$string['rimp_finalized_edit_confirm'] = 'This RIMP is currently Finalized. Saving changes will revert it to Draft status. Do you want to continue?';

// RIMP form sections.
$string['rimp_section_header']         = 'Plan Information';
$string['rimp_section_goals']          = 'Goals — This plan was created to improve:';
$string['rimp_section_supports']       = 'Instructional Supports';
$string['rimp_section_frequency']      = 'Services and Supports';
$string['rimp_section_monitoring']     = 'Plans for Monitoring Student Success';
$string['rimp_section_grade3']         = "Ohio's Third Grade Reading Guarantee";
$string['rimp_section_signatures']     = 'Signatures';
$string['rimp_section_assessment_data']= 'Assessment Data';

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
$string['invalidstudent']              = 'Invalid or unenrolled student.';
$string['rimp_not_found']              = 'No RIMP record found for this student.';

// ── Additional support options (v0.2.4 form update) ───────────────────────────
$string['rimp_sup_writing_found']    = 'Explicit Intervention in Writing (Foundational/Transcription)';
$string['rimp_sup_writing_comp']     = 'Explicit Intervention in Writing (Composition Skills)';
$string['rimp_sup_scaffolding']      = 'Small Group Scaffolding of Complex Text';
$string['rimp_sup_communication']    = 'Explicit Intervention in Communication/Language';
$string['rimp_sup_multimodal']       = 'Multi-Modal Approach to Structured Literacy';
$string['rimp_sup_other']            = 'Other';

// ── Monitored by — Other option ───────────────────────────────────────────────
$string['rimp_monitored_other']      = 'Other';

// ── MS monitoring assessment checkboxes ──────────────────────────────────────
$string['rimp_mon_uflr']             = 'UFLR- after every two lessons';
$string['rimp_mon_phonics']          = 'Phonics for Reading- a review at the end of each lesson and a check up at the end of each unit';
$string['rimp_mon_rewards']          = 'Rewards- a review after every two lessons and a check up at the end of each unit';

// ── Third Grade Guarantee — corrected options ─────────────────────────────────
$string['rimp_grade3_score700']      = 'The student reached the promotion score of 700 on the Ohio State Test for grade 3 English language arts.';
$string['rimp_grade3_alternative']   = 'The student reached the promotion score on an approved alternative reading assessment given by the district.';
$string['rimp_grade3_parentrequest'] = 'A parent/guardian, in consultation with the school, can request that their child be promoted to fourth grade even if they do not reach the promotion score. In this case, the student will continue to be supported with a RIMP and provided with intensive instruction, until they are able to read at grade level.';
