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
 * Library of functions for mod_mtssdashboard.
 *
 * @package    mod_mtssdashboard
 * @copyright  2026 National Trail Local Schools
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Seconds of heartbeat silence before a RIMP/WEP edit lock is considered
 * abandoned (crashed tab, lost connection, etc.) and treated as available.
 */
define('MTSSDASHBOARD_EDIT_LOCK_TIMEOUT', 120);

/**
 * Add a new instance of mtssdashboard to a course.
 *
 * @param stdClass $data Form data from mod_form.php
 * @param mod_mtssdashboard_mod_form|null $mform The form object
 * @return int The new instance id
 */
function mtssdashboard_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated  = time();
    $data->timemodified = time();

    // Force visible groups mode on all new instances.
    $data->groupmode = VISIBLEGROUPS;

    if (empty($data->defaultyear)) {
        $data->defaultyear = '';
    }
    if (empty($data->defaultsubject)) {
        $data->defaultsubject = '';
    }

    $data->id = $DB->insert_record('mtssdashboard', $data);

    return $data->id;
}

/**
 * Update an existing instance of mtssdashboard.
 *
 * @param stdClass $data Form data from mod_form.php
 * @param mod_mtssdashboard_mod_form|null $mform The form object
 * @return bool True on success
 */
function mtssdashboard_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id           = $data->instance;

    if (empty($data->defaultyear)) {
        $data->defaultyear = '';
    }
    if (empty($data->defaultsubject)) {
        $data->defaultsubject = '';
    }

    return $DB->update_record('mtssdashboard', $data);
}

/**
 * Delete an instance of mtssdashboard.
 *
 * @param int $id The instance id
 * @return bool True on success
 */
function mtssdashboard_delete_instance($id) {
    global $DB;

    if (!$DB->record_exists('mtssdashboard', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('mtssdashboard', ['id' => $id]);

    return true;
}

/**
 * Returns the features this module supports.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if unknown
 */
function mtssdashboard_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_NO_VIEW_LINK:
            return false;
        default:
            return null;
    }
}

/**
 * Given a coursemodule object, this function returns any extra
 * information that may be needed when printing this activity in
 * a course listing.
 *
 * @param stdClass $coursemodule The coursemodule object
 * @return cached_cm_info|null Info to customise display in course
 */
function mtssdashboard_get_coursemodule_info($coursemodule) {
    global $DB;

    $record = $DB->get_record(
        'mtssdashboard',
        ['id' => $coursemodule->instance],
        'id, name, intro, introformat'
    );

    if (!$record) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $record->name;

    if ($coursemodule->showdescription && !empty($record->intro)) {
        $info->content = format_module_intro('mtssdashboard', $record, $coursemodule->id, false);
    }

    // In HS-category courses, append a direct Intervention link below the
    // activity name. Day-of-week routing: Mon/Tue/Wed → Home Room Attendance,
    // Thu/Fri → Intervention Attendance. No change for non-HS courses.
    $hscategoryid = (int) get_config('local_roomscheduler', 'hscategoryid');
    if (!empty($hscategoryid)) {
        $course = $DB->get_record('course', ['id' => $coursemodule->course], 'id, category');
        // Walk the category tree inline — cannot call local_roomscheduler functions
        // from mod_mtssdashboard as the plugin may not be loaded yet.
        $inhs = false;
        if ($course) {
            $catid   = (int)$course->category;
            $visited = [];
            while ($catid > 0) {
                if ($catid === (int)$hscategoryid) {
                    $inhs = true;
                    break;
                }
                if (isset($visited[$catid])) {
                    break;
                }
                $visited[$catid] = true;
                $cat = $DB->get_record('course_categories', ['id' => $catid], 'id, parent');
                if (!$cat) {
                    break;
                }
                $catid = (int)$cat->parent;
            }
        }
        if ($inhs) {
            $dow = (int)date('N');
            if ($dow <= 3) {
                $inturl = new moodle_url(
                    '/local/roomscheduler/hrattendance.php',
                    ['courseid' => $coursemodule->course]
                );
            } else {
                $inturl = new moodle_url(
                    '/local/roomscheduler/intattendance.php',
                    ['courseid' => $coursemodule->course]
                );
            }
            $info->content .= html_writer::div(
                html_writer::link(
                    $inturl,
                    '→ ' . get_string('gotointervention', 'local_roomscheduler'),
                    ['class' => 'small font-weight-bold text-primary']
                ),
                'mt-1'
            );
        }
    }

    return $info;
}

/**
 * Returns all instances of mtssdashboard in a given course.
 * Required for the index.php listing page.
 *
 * @param int $courseid The course id
 * @return array Array of mtssdashboard records
 */
function mtssdashboard_get_instances_in_course($courseid) {
    global $DB;
    return $DB->get_records('mtssdashboard', ['course' => $courseid]);
}

/**
 * Retrieve the list of distinct testing years from the state results table.
 * Used to populate year dropdowns in mod_form and view.php.
 *
 * @return array Associative array of year => year strings
 */
function mtssdashboard_get_years() {
    global $DB, $CFG;

    $table = $CFG->prefix . 'NTstatetesting';
    $years = $DB->get_fieldset_sql(
        "SELECT DISTINCT year FROM {$table} ORDER BY year DESC"
    );

    $options = [];
    foreach ($years as $year) {
        $options[$year] = $year;
    }

    return $options;
}

/**
 * Retrieve the list of distinct subjects from the state results table.
 * Used to populate subject dropdowns in mod_form and view.php.
 *
 * @return array Associative array of subject_code => subject_description
 */
function mtssdashboard_get_subjects() {
    global $DB, $CFG;

    $table = $CFG->prefix . 'NTstatetesting';
    $rows = $DB->get_records_sql(
        "SELECT DISTINCT subject_code, subject_description
           FROM {$table}
          ORDER BY subject_description ASC"
    );

    $options = [];
    foreach ($rows as $row) {
        $options[$row->subject_code] = $row->subject_description . ' (' . $row->subject_code . ')';
    }

    return $options;
}

/**
 * Get the SSIDs for all students enrolled in a course.
 * Joins mdl_user -> msb_contacts -> state testing results.
 *
 * @param int $courseid The course id
 * @return array Array of objects with userid, firstname, lastname, ssid
 */
function mtssdashboard_get_enrolled_students_with_ssid($courseid) {
    global $DB, $CFG;

    $contacts = $CFG->prefix . 'contacts';
    $sql = "SELECT u.id AS userid,
                   u.firstname,
                   u.lastname,
                   u.idnumber,
                   c.StateStudentID AS ssid,
                   c.GradeLevelCode AS gradelevel,
                   c.Gender AS gender,
                   c.StudentEthnicity AS race_ethnicity,
                   c.StudentFreeLunchStatus AS ed_flag,
                   c.Gifted AS gifted,
                   c.Status504 AS status504,
                   c.IEP AS iep,
                   c.StudentStatus AS studentstatus
              FROM {user} u
              JOIN {user_enrolments} ue ON ue.userid = u.id
              JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
              JOIN {role_assignments} ra ON ra.userid = u.id
              JOIN {context} ctx ON ctx.id = ra.contextid
                   AND ctx.contextlevel = 50
                   AND ctx.instanceid = :courseid2
              JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
              JOIN {$contacts} c ON CAST(u.idnumber AS UNSIGNED) = c.StudentNumber
             WHERE u.deleted = 0
               AND u.suspended = 0
               AND c.StateStudentID IS NOT NULL
               AND c.StateStudentID != ''
          ORDER BY u.lastname ASC, u.firstname ASC";

    return $DB->get_records_sql($sql, [
        'courseid'  => $courseid,
        'courseid2' => $courseid,
    ]);
}
/**
 * Remove students who should never appear on the RIMP or WEP tabs.
 *
 * RIMPs and WEPs are not written for students attending the career-tech
 * center (StudentStatus J or NJ) or for students no longer attending the
 * district (StudentStatus I or R), so those students are left out of the
 * RIMP/WEP tables, student dropdowns, and Download RIMPs/Download WEPs.
 *
 * @param array $students Student rows from mtssdashboard_get_enrolled_students_with_ssid().
 * @return array The same rows without the skipped statuses.
 */
function mtssdashboard_remove_skipped_students(array $students) {
    $skip = ['J', 'NJ', 'I', 'R'];
    return array_filter($students, function ($s) use ($skip) {
        $status = strtoupper(trim((string) ($s->studentstatus ?? '')));
        return !in_array($status, $skip, true);
    });
}

/**
 * Get the school level settings from admin config.
 *
 * @return stdClass Object with all level settings
 */
function mtssdashboard_get_level_config() {
    $cfg = new stdClass();
    $cfg->gradyear    = (int) get_config('mod_mtssdashboard', 'gradyear') ?: 2026;
    $cfg->hs_category = (int) get_config('mod_mtssdashboard', 'hs_category') ?: 2;
    $cfg->ms_category = (int) get_config('mod_mtssdashboard', 'ms_category') ?: 3;
    $cfg->es_category = (int) get_config('mod_mtssdashboard', 'es_category') ?: 28;
    $cfg->hs_min      = (int) get_config('mod_mtssdashboard', 'hs_grade_min') ?: 9;
    $cfg->hs_max      = (int) get_config('mod_mtssdashboard', 'hs_grade_max') ?: 12;
    $cfg->ms_min      = (int) get_config('mod_mtssdashboard', 'ms_grade_min') ?: 5;
    $cfg->ms_max      = (int) get_config('mod_mtssdashboard', 'ms_grade_max') ?: 8;
    $cfg->es_min      = (int) get_config('mod_mtssdashboard', 'es_grade_min') ?: 1;
    $cfg->es_max      = (int) get_config('mod_mtssdashboard', 'es_grade_max') ?: 4;
    return $cfg;
}

/**
 * Determine the school level for a course based on its category path.
 * Walks up the category tree to find a matching configured category.
 *
 * @param int $categoryid The course's immediate category id
 * @param stdClass $levelcfg From mtssdashboard_get_level_config()
 * @return string 'hs', 'ms', or 'es'
 */
function mtssdashboard_get_course_level($categoryid, $levelcfg) {
    global $DB;

    // Get the full path for this category.
    $cat = $DB->get_record('course_categories', ['id' => $categoryid], 'id, name, parent, path');
    if (!$cat) {
        return 'hs'; // Default.
    }

    // Path is like /2/10/45 — extract all ancestor IDs including self.
    $pathids = array_filter(explode('/', $cat->path));

    if (in_array((string)$levelcfg->es_category, $pathids)) {
        return 'es';
    }
    if (in_array((string)$levelcfg->ms_category, $pathids)) {
        return 'ms';
    }
    if (in_array((string)$levelcfg->hs_category, $pathids)) {
        return 'hs';
    }

    return 'hs'; // Default to high school.
}

/**
 * Convert a GradeLevelCode string to a numeric grade.
 *
 * @param string $code e.g. '09', '12', 'KG'
 * @return int|null Numeric grade or null if not applicable
 */
function mtssdashboard_grade_to_int($code) {
    $code = strtoupper(trim($code));
    if ($code === 'KG') {
        return 0;
    }
    if ($code === 'PS' || $code === '23') {
        return null;
    }
    $int = intval($code);
    return ($int >= 0 && $int <= 12) ? $int : null;
}

/**
 * Given a student's current grade code and the test year,
 * calculate what grade they were in during that test year.
 *
 * @param string $gradelevelcode Current grade from msb_contacts
 * @param string $testyear       e.g. '2023-24'
 * @param int    $gradyear       Current graduation year e.g. 2026
 * @return int|null Grade when tested, or null if can't calculate
 */
function mtssdashboard_grade_when_tested($gradelevelcode, $testyear, $gradyear) {
    $currentgrade = mtssdashboard_grade_to_int($gradelevelcode);
    if ($currentgrade === null) {
        return null;
    }
    // Extract the end year from the test year string e.g. '2023-24' → 2024.
    $parts = explode('-', $testyear);
    if (count($parts) !== 2) {
        return null;
    }
    $endyear = intval($parts[0]) + 1;
    $yearsdiff = $gradyear - $endyear;
    return $currentgrade - $yearsdiff;
}

/**
 * Filter results to only those matching the given grade range.
 * Uses grade-when-tested calculation.
 *
 * @param array    $results     From stv_get_results()
 * @param array    $students    Enrolled students with ssid/gradelevel
 * @param int      $grademin    Minimum grade (inclusive)
 * @param int      $grademax    Maximum grade (inclusive)
 * @param int      $gradyear    Current graduation year
 * @return array Filtered results
 */
function mtssdashboard_filter_by_grade_range($results, $students, $grademin, $grademax, $gradyear) {
    // Build ssid → gradelevelcode map.
    $grademap = [];
    foreach ($students as $s) {
        $grademap[$s->ssid] = $s->gradelevel;
    }

    $filtered = [];
    foreach ($results as $key => $r) {
        $glcode = $grademap[$r->ssid] ?? null;
        if ($glcode === null) {
            continue;
        }
        $gradewhen = mtssdashboard_grade_when_tested($glcode, $r->year, $gradyear);
        if ($gradewhen === null) {
            continue;
        }
        if ($gradewhen >= $grademin && $gradewhen <= $grademax) {
            $filtered[$key] = $r;
        }
    }
    return $filtered;
}
/**
 * Get grade data for the Grades tab.
 * Fetches finalgrade for all enrolled students across all their visible courses,
 * converts to letter grades using the management course scale for the school level,
 * and buckets courses into subject categories.
 *
 * @param array    $students    From mtssdashboard_get_enrolled_students_with_ssid()
 * @param int      $courseid    The current course id
 * @param string   $courselevel 'hs', 'ms', or 'es'
 * @param stdClass $levelcfg   From mtssdashboard_get_level_config()
 * @return stdClass Object with properties:
 *   ->scale         array of ['letter'=>, 'lowerboundary'=>] sorted high to low
 *   ->thiscourse    array of ['letter'=>, 'pct'=>, 'userid'=>] or empty if no grades
 *   ->categories    array of category_key => array of grade rows
 *   ->detail        array of all rows for the detail table
 *   ->hasthiscourse bool — whether this course has real grade data
 */
function mtssdashboard_get_grades_data($students, $courseid, $courselevel, $levelcfg) {
    global $DB;

    $result = new stdClass();
    $result->scale         = [];
    $result->thiscourse    = [];
    $result->categories    = [
        'math'         => [],
        'english'      => [],
        'science'      => [],
        'socialstudies' => [],
        'electives'    => [],
    ];
    $result->detail        = [];
    $result->hasthiscourse = false;

    // Use the (group-filtered) student list passed in by the caller.
    // Re-key by userid and fetch firstname/lastname from {user} so downstream
    // code can rely on the same object shape regardless of caller.
    if (empty($students)) {
        return $result;
    }
    $userids = [];
    foreach ($students as $s) {
        if (!empty($s->userid)) {
            $userids[(int) $s->userid] = (int) $s->userid;
        }
    }
    if (empty($userids)) {
        return $result;
    }
    [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
    $students = $DB->get_records_sql(
        "SELECT u.id AS userid, u.firstname, u.lastname
           FROM {user} u
          WHERE u.id $insql
            AND u.deleted = 0
            AND u.suspended = 0",
        $inparams
    );

    if (empty($students)) {
        return $result;
    }

    // Load grade letter scale from management course.
    $mgmtcourse = ($courselevel === 'ms')
        ? ((int) get_config('mod_mtssdashboard', 'ms_mgmt_course') ?: 54)
        : ((int) get_config('mod_mtssdashboard', 'hs_mgmt_course') ?: 53);

    $scale = $DB->get_records_sql(
        "SELECT gl.lowerboundary, gl.letter
           FROM {grade_letters} gl
           JOIN {context} ctx ON ctx.id = gl.contextid
          WHERE ctx.contextlevel = 50
            AND ctx.instanceid   = :courseid
          ORDER BY gl.lowerboundary DESC",
        ['courseid' => $mgmtcourse]
    );
    $result->scale = array_values($scale);

    // Fall back to site-level scale if management course has none.
    if (empty($result->scale)) {
        $scale = $DB->get_records_sql(
            "SELECT lowerboundary, letter
               FROM {grade_letters}
              WHERE contextid = 1
              ORDER BY lowerboundary DESC"
        );
        $result->scale = array_values($scale);
    }

    // Build subject category ID sets from admin config.
    $prefix = ($courselevel === 'ms') ? 'ms' : 'hs';

    $catids = [];
    foreach (['math', 'english', 'science', 'socialstudies'] as $subj) {
        $raw = get_config('mod_mtssdashboard', $prefix . '_cat_' . $subj);
        $ids = [];
        foreach (explode(',', $raw ?: '') as $id) {
            $id = (int) trim($id);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $catids[$subj] = $ids;
    }

    // Helper: convert percentage to letter using scale.
    $pcttoletter = function ($pct) use (&$result) {
        foreach ($result->scale as $row) {
            if ($pct >= (float) $row->lowerboundary) {
                return $row->letter;
            }
        }
        return 'F';
    };

    // Helper: classify a course by its category path.
    $classifycourse = function ($categoryid) use ($catids, $DB) {
        static $catcache = [];
        if (isset($catcache[$categoryid])) {
            return $catcache[$categoryid];
        }
        $cat = $DB->get_record(
            'course_categories',
            ['id' => $categoryid],
            'id, path'
        );
        if (!$cat) {
            $catcache[$categoryid] = 'electives';
            return 'electives';
        }
        $path = $cat->path;
        foreach (['math', 'english', 'science', 'socialstudies'] as $subj) {
            foreach ($catids[$subj] as $cid) {
                if (
                    strpos($path, '/' . $cid . '/') !== false
                        || substr($path, -(strlen((string)$cid) + 1)) === '/' . $cid
                ) {
                    $catcache[$categoryid] = $subj;
                    return $subj;
                }
            }
        }
        $catcache[$categoryid] = 'electives';
        return 'electives';
    };

    // Build userid → name map.
    $namemap = [];
    foreach ($students as $s) {
        $namemap[$s->userid] = $s->lastname . ', ' . $s->firstname;
    }
    // Fetch grades for all students enrolled in this course.
    // Use passed $students (respects group filter) or fall back to all enrolled.
    if (!empty($students)) {
        $enrolledstudents = [];
        foreach ($students as $key => $s) {
            $obj = new stdClass();
            $obj->userid    = isset($s->userid) ? $s->userid : $key;
            $obj->firstname = $s->firstname;
            $obj->lastname  = $s->lastname;
            $enrolledstudents[$obj->userid] = $obj;
        }
    } else {
        $studentsql = "SELECT DISTINCT u.id AS userid, u.firstname, u.lastname
                         FROM {user} u
                         JOIN {user_enrolments} ue ON ue.userid = u.id
                         JOIN {enrol} e ON e.id = ue.enrolid
                         JOIN {context} cx ON cx.instanceid = e.courseid
                              AND cx.contextlevel = 50
                         JOIN {role_assignments} ra ON ra.userid = u.id
                              AND ra.contextid = cx.id
                         JOIN {role} r ON r.id = ra.roleid
                              AND r.shortname = 'student'
                        WHERE e.courseid = :cid
                          AND u.deleted = 0 AND u.suspended = 0 AND ue.status = 0
                     ORDER BY u.lastname ASC, u.firstname ASC";
        $enrolledstudents = $DB->get_records_sql($studentsql, ['cid' => $courseid]);
    }

    $gradesql = "SELECT c.id AS courseid,
                        c.fullname AS coursename,
                        c.category AS categoryid,
                        gi.grademax,
                        gg.finalgrade
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid
                   JOIN {course} c ON c.id = e.courseid AND c.visible = 1
              LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
              LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = ue.userid
                  WHERE ue.userid = :userid
                    AND gg.finalgrade IS NOT NULL
               ORDER BY c.fullname ASC";

    $rows = [];
    foreach ($enrolledstudents as $es) {
        $namemap[$es->userid] = $es->lastname . ', ' . $es->firstname;
        $grades = $DB->get_records_sql($gradesql, ['userid' => $es->userid]);
        foreach ($grades as $g) {
            $g->userid = $es->userid;
            $rows[] = $g;
        }
    }
        // Split into this-course vs other courses.
    $thiscourserows = [];
    $otherrows      = [];

    foreach ($rows as $row) {
        if ((int)$row->courseid === (int)$courseid) {
            $thiscourserows[] = $row;
        } else {
            $otherrows[] = $row;
        }
    }

    // Process this course.
    if (!empty($thiscourserows)) {
        $result->hasthiscourse = true;
        foreach ($thiscourserows as $row) {
            $grademax = (float)$row->grademax ?: 100;
            $pct      = round(((float)$row->finalgrade / $grademax) * 100, 2);
            $letter   = $pcttoletter($pct);
            $result->thiscourse[] = [
                'userid'     => $row->userid,
                'name'       => $namemap[$row->userid] ?? '—',
                'coursename' => $row->coursename,
                'pct'        => $pct,
                'letter'     => $letter,
                'category'   => 'thiscourse',
            ];
        }
    }

    // Process other courses.
    foreach ($otherrows as $row) {
        $grademax = (float)$row->grademax ?: 100;
        $pct      = round(((float)$row->finalgrade / $grademax) * 100, 2);
        $letter   = $pcttoletter($pct);
        $subj     = $classifycourse($row->categoryid);

        $entry = [
            'userid'     => $row->userid,
            'name'       => $namemap[$row->userid] ?? '—',
            'coursename' => $row->coursename,
            'pct'        => $pct,
            'letter'     => $letter,
            'category'   => $subj,
        ];

        $result->categories[$subj][] = $entry;
        $result->detail[]            = $entry;
    }

    // If this course had no grades, merge thiscourse rows into detail too
    // so the "all courses" summary includes everything.
    if (!$result->hasthiscourse) {
        foreach ($thiscourserows as $row) {
            $grademax = (float)$row->grademax ?: 100;
            $pct      = round(((float)$row->finalgrade / $grademax) * 100, 2);
            $letter   = $pcttoletter($pct);
            $subj     = $classifycourse($row->categoryid);
            $entry = [
                'userid'     => $row->userid,
                'name'       => $namemap[$row->userid] ?? '—',
                'coursename' => $row->coursename,
                'pct'        => $pct,
                'letter'     => $letter,
                'category'   => $subj,
            ];
            $result->categories[$subj][] = $entry;
            $result->detail[]            = $entry;
        }
    } else {
        // Add this course rows to detail table as well.
        foreach ($result->thiscourse as $entry) {
            $result->detail[] = $entry;
        }
    }

    return $result;
}

/**
 * Count letter grade distribution from an array of grade entries.
 * Returns array of letter => count, sorted by scale order.
 *
 * @param array $entries  Array of grade entry arrays with 'letter' key
 * @param array $scale    Scale rows with ->letter property, high to low
 * @return array letter => count
 */
function mtssdashboard_count_letters($entries, $scale) {
    $counts = [];
    // Seed all letters from scale so order is preserved.
    foreach ($scale as $row) {
        $counts[$row->letter] = 0;
    }
    foreach ($entries as $e) {
        $l = $e['letter'];
        if (!isset($counts[$l])) {
            $counts[$l] = 0;
        }
        $counts[$l]++;
    }
    // Remove zero entries.
    return array_filter($counts);
}

/**
 * Determine the school level ('hs', 'ms', or 'es') for a given course based
 * on its course category, using the category IDs configured in plugin settings.
 *
 * @param stdClass $course Moodle course record (must have ->category).
 * @return string School level: 'hs', 'ms', or 'es'. Defaults to 'hs' if no match.
 */
function stv_get_school_level_for_course($course) {
    global $DB;

    $hscat = (int) get_config('mod_mtssdashboard', 'hs_category');
    $mscat = (int) get_config('mod_mtssdashboard', 'ms_category');
    $escat = (int) get_config('mod_mtssdashboard', 'es_category');

    $catid = (int) $course->category;

    // Walk up the category tree so courses in subcategories are matched too.
    while ($catid > 0) {
        if ($catid === $hscat) {
            return 'hs';
        }
        if ($catid === $mscat) {
            return 'ms';
        }
        if ($catid === $escat) {
            return 'es';
        }
        $parent = $DB->get_field('course_categories', 'parent', ['id' => $catid]);
        $catid = (int) $parent;
    }

    // Default fallback.
    return 'hs';
}

/**
 * Check whether the given course is the management course for the given
 * school level. Edits to warning levels are only permitted on the
 * management course (and only for users with the manager role there).
 *
 * @param int    $courseid    The course ID being viewed.
 * @param string $schoollevel 'hs', 'ms', or 'es'.
 * @return bool True if this course is the configured management course for that level.
 */
function stv_is_management_course($courseid, $schoollevel) {
    $cfgkey = $schoollevel . '_mgmt_course';
    $mgmtcourseid = (int) get_config('mod_mtssdashboard', $cfgkey);

    if ($mgmtcourseid <= 0) {
        return false;
    }

    return ((int) $courseid === $mgmtcourseid);
}

/**
 * Check whether the current user is allowed to edit warning level thresholds
 * on the given course. Edits are only permitted when:
 *   1. The course is the configured management course for its school level, AND
 *   2. The user holds the 'manager' role on that course's context.
 *
 * Site admins are always allowed (Moodle convention).
 *
 * @param stdClass $course      Moodle course record.
 * @param string   $schoollevel 'hs', 'ms', or 'es'.
 * @return bool True if the current user may save warning level edits here.
 */
function stv_can_edit_warning_levels($course, $schoollevel) {
    global $USER;

    // Edits must always happen on the configured management course for the
    // school level — even site admins are constrained to this, so all changes
    // funnel through the canonical course.
    if (!stv_is_management_course($course->id, $schoollevel)) {
        return false;
    }

    // Site admins always pass once we're on the management course.
    if (is_siteadmin($USER)) {
        return true;
    }

    // Otherwise the user must hold the manager role on this course's context.
    $context = context_course::instance($course->id);
    $roles = get_user_roles($context, $USER->id, true);

    foreach ($roles as $role) {
        if ($role->shortname === 'manager') {
            return true;
        }
    }

    return false;
}

/**
 * Build the human-readable threshold text from numeric min/max bounds for
 * a given metric. Example: ('total_hours_missed', 20, 34) → '20-34 hours'.
 *
 * Used to keep the displayed band labels in sync with the underlying numbers
 * after a manager edits them, without requiring manual text entry.
 *
 * @param string     $metrickey  Metric identifier, e.g. 'gpa'.
 * @param float|null $min        Inclusive lower bound, or null for no lower bound.
 * @param float|null $max        Inclusive upper bound, or null for no upper bound.
 * @return string Human-readable threshold label.
 */
function stv_format_threshold_text($metrickey, $min, $max) {

    // Strip trailing zeros from numeric values for cleaner display.
    $fmt = function ($n) {
        if ($n === null) {
            return null;
        }
        $s = (string) (float) $n;
        // A (float) cast drops unneeded trailing zeros automatically; cast then
        // round to 2 places to avoid float drift like 20.000000001.
        $s = number_format((float) $n, 2, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return ($s === '' || $s === '-') ? '0' : $s;
    };

    $minf = $fmt($min);
    $maxf = $fmt($max);

    // Per-metric unit suffix and pluralization rules.
    $units = [
        'total_hours_missed'     => ['hours', 'hours'],
        'unexcused_hours_missed' => ['hours', 'hours'],
        'office_incidents'       => ['incident', 'incidents'],
        'course_failure_count'   => ['course', 'courses'],
        'gpa'                    => ['', ''],
        'iready_ela_percentile'  => ['%', '%'],
        'iready_math_percentile' => ['%', '%'],
        'ela_fall_score'         => ['', ''],
        'ela_fall_pct'           => ['%ile', '%ile'],
        'ela_winter_score'       => ['', ''],
        'ela_winter_pct'         => ['%ile', '%ile'],
        'ela_spring_score'       => ['', ''],
        'ela_spring_pct'         => ['%ile', '%ile'],
        'math_fall_score'        => ['', ''],
        'math_fall_pct'          => ['%ile', '%ile'],
        'math_winter_score'      => ['', ''],
        'math_winter_pct'        => ['%ile', '%ile'],
        'math_spring_score'      => ['', ''],
        'math_spring_pct'        => ['%ile', '%ile'],
        'ost_performance'        => ['low score', 'low scores'],
    ];
    [$singular, $plural] = $units[$metrickey] ?? ['', ''];

    // Helper to attach the right unit form to a single number.
    $withunit = function ($n, $isrange = false) use ($singular, $plural) {
        if ($singular === '' && $plural === '') {
            return $n;
        }
        // Range values always get plural unit. Single value uses singular if "1".
        if (!$isrange && $n === '1') {
            return $n . ' ' . $singular;
        }
        return $n . ($plural === '%' ? '%' : ' ' . $plural);
    };

    if ($minf !== null && $maxf !== null) {
        if ($minf === $maxf) {
            // Single-point band, e.g. office_incidents 0-0 → "0 incidents".
            return $withunit($minf);
        }
        // True range, e.g. 20-34 → "20-34 hours" or "21-40%".
        if ($plural === '%') {
            return $minf . '-' . $maxf . '%';
        }
        $unitsuffix = ($plural !== '') ? ' ' . $plural : '';
        return $minf . '-' . $maxf . $unitsuffix;
    }

    if ($minf !== null && $maxf === null) {
        // Open-ended upper bound, e.g. ">= 3.0" or "50+ hours" or "5+ incidents".
        if ($metrickey === 'gpa') {
            return '>= ' . $minf;
        }
        if ($plural === '%') {
            return $minf . '+%';
        }
        $unitsuffix = ($plural !== '') ? ' ' . $plural : '';
        return $minf . '+' . $unitsuffix;
    }

    if ($minf === null && $maxf !== null) {
        // Open-ended lower bound, e.g. "< 20 hours" or "< 2.0".
        if ($metrickey === 'gpa') {
            // Display as "< (max+0.01)" rounded — for GPA we typically want "< 2.0".
            $displaymax = $fmt($max + 0.01);
            return '< ' . $displaymax;
        }
        if ($plural === '%') {
            return '< ' . $fmt($max + 1) . '%';
        }
        $unitsuffix = ($plural !== '') ? ' ' . $plural : '';
        return '< ' . $fmt($max + 1) . $unitsuffix;
    }

    return '—';
}

/**
 * Bulk-fetch office incident counts from msb_NTdiscipline for a set of
 * school StudentNumbers. Returns a keyed map so callers can do
 * "$counts[$studentnumber] ?? 0" without a query per student.
 *
 * Each row in msb_NTdiscipline represents a single discipline incident,
 * so we COUNT(*) per StudentId.
 *
 * @param array $studentnumbers Array of school student numbers (the ones
 *                              that match msb_contacts.StudentNumber and
 *                              msb_NTdiscipline.StudentId).
 * @return array Map of [studentnumber => incident_count]; missing students
 *               are simply absent from the map (treat as 0 in the caller).
 */
function stv_get_office_incidents_by_studentnumber(array $studentnumbers) {
    global $DB, $CFG;

    if (empty($studentnumbers)) {
        return [];
    }

    // Sanitize to integers — these are the school's numeric student IDs.
    $clean = [];
    foreach ($studentnumbers as $sn) {
        $sn = (int) $sn;
        if ($sn > 0) {
            $clean[$sn] = $sn;
        }
    }
    if (empty($clean)) {
        return [];
    }

    [$insql, $inparams] = $DB->get_in_or_equal($clean, SQL_PARAMS_NAMED, 'sn');

    // NTdiscipline is externally managed (not registered in Moodle's XMLDB),
    // so reference it via the configured DB prefix rather than a {placeholder}.
    $table = $CFG->prefix . 'NTdiscipline';

    // Only count incidents for the current school year.
    // SchoolYear in NTdiscipline stores the fall year (e.g. 2026 for 2026-27).
    // gradyear is the spring year, so fall year = gradyear - 1.
    $gradyear   = (int) get_config('mod_mtssdashboard', 'gradyear');
    $schoolyear = ($gradyear > 0) ? ($gradyear - 1) : (int) date('Y');
    $inparams['schoolyear'] = (string) $schoolyear;

    $sql = "SELECT StudentId AS studentnumber, COUNT(*) AS incidents
              FROM {$table}
             WHERE StudentId $insql
               AND SchoolYear = :schoolyear
          GROUP BY StudentId";

    $rows = $DB->get_records_sql($sql, $inparams);

    $map = [];
    foreach ($rows as $r) {
        $map[(int) $r->studentnumber] = (int) $r->incidents;
    }
    return $map;
}

/**
 * Bulk-fetch attendance hour totals from msb_NTattendance for a set of
 * school StudentNumbers. Returns both:
 *   - total_hours_missed = HoursAbsentExcused + HoursAbsentUnexcused
 *   - unexcused_hours_missed = HoursAbsentUnexcused
 *
 * If a student has multiple rows (e.g. one per term), the values are
 * summed across rows so the total covers the whole school year.
 *
 * @param array $studentnumbers Array of school student numbers.
 * @return array Map of [studentnumber => (object){total_hours, unexcused_hours}].
 *               Missing students are absent from the map; caller defaults
 *               to nulls (treated as "no data" by the display).
 */
function stv_get_attendance_by_studentnumber(array $studentnumbers) {
    global $DB;

    if (empty($studentnumbers)) {
        return [];
    }

    $clean = [];
    foreach ($studentnumbers as $sn) {
        $sn = (int) $sn;
        if ($sn > 0) {
            $clean[$sn] = $sn;
        }
    }
    if (empty($clean)) {
        return [];
    }

    [$insql, $inparams] = $DB->get_in_or_equal($clean, SQL_PARAMS_NAMED, 'sn');

    // NTattendance is externally managed; reference via the configured prefix.
    global $CFG;
    $table = $CFG->prefix . 'NTattendance';

    // Sum across rows in case a student has multiple records (e.g. per term).
    $sql = "SELECT StudentId AS studentnumber,
                   SUM(COALESCE(HoursAbsentExcused, 0)
                     + COALESCE(HoursAbsentUnexcused, 0)) AS total_hours,
                   SUM(COALESCE(HoursAbsentUnexcused, 0)) AS unexcused_hours
              FROM {$table}
             WHERE StudentId $insql
          GROUP BY StudentId";

    $rows = $DB->get_records_sql($sql, $inparams);

    $map = [];
    foreach ($rows as $r) {
        $map[(int) $r->studentnumber] = (object) [
            'total_hours'     => (float) $r->total_hours,
            'unexcused_hours' => (float) $r->unexcused_hours,
        ];
    }
    return $map;
}

/**
 * Fetch grade currency data for all qualifying courses — visible courses
 * whose shortname matches the district convention ^[0-9]{4}[A-Z]{2}$.
 * Returns one row per course with the most recent grade_grades timemodified,
 * the teacher list, and the school-day count since that update.
 *
 * @param string $schoollevel 'ms' or 'hs' — selects the course category.
 * @param array  $userids     Only courses where these users are enrolled (empty = all courses).
 * @return array Array of stdClass objects sorted descending by days_since.
 */
function stv_get_grade_currency($schoollevel, array $userids = []) {
    global $DB;

    $levelcfg   = mtssdashboard_get_level_config();
    $categoryid = ($schoollevel === 'ms') ? $levelcfg->ms_category : $levelcfg->hs_category;

    // Get all category IDs that are children of the school category.
    $allcats = $DB->get_records('course_categories', null, '', 'id, path');
    $catids  = [];
    foreach ($allcats as $cat) {
        $pathids = array_filter(explode('/', $cat->path));
        if (in_array((string)$categoryid, $pathids)) {
            $catids[] = (int)$cat->id;
        }
    }
    if (empty($catids)) {
        return [];
    }

    [$catsql, $catparams] = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED, 'cat');

    // If a group is selected, limit to courses where at least one group member is enrolled.
    $groupjoin  = '';
    $groupwhere = '';
    $groupparams = [];
    if (!empty($userids)) {
        $clean = array_map('intval', $userids);
        [$usersql, $groupparams] = $DB->get_in_or_equal($clean, SQL_PARAMS_NAMED, 'usr');
        $groupjoin  = "JOIN {user_enrolments} ue ON ue.userid $usersql
              JOIN {enrol} en             ON en.id = ue.enrolid
                                         AND en.courseid = c.id";
        $groupwhere = "AND ue.userid $usersql";
        // Need params twice — once for join, once for where.
        [$usersql2, $groupparams2] = $DB->get_in_or_equal($clean, SQL_PARAMS_NAMED, 'usr2');
        $groupjoin  = "JOIN {user_enrolments} ue ON ue.userid $usersql
              JOIN {enrol} en             ON en.id = ue.enrolid
                                         AND en.courseid = c.id";
        $groupwhere = "AND ue.userid $usersql2";
        $groupparams = array_merge($groupparams, $groupparams2);
    }

    $sql = "SELECT gi.courseid,
                   c.fullname,
                   c.shortname,
                   MAX(gg.timemodified) AS lastmodified
              FROM {grade_grades} gg
              JOIN {grade_items} gi ON gi.id = gg.itemid
                                    AND gi.itemtype = 'course'
              JOIN {course} c        ON c.id = gi.courseid
                                    AND c.visible = 1
                                    AND c.shortname REGEXP '^[0-9]{4}[A-Z]{2}\$'
                                    AND c.category $catsql
              $groupjoin
             WHERE gg.timemodified IS NOT NULL
               $groupwhere
          GROUP BY gi.courseid, c.fullname, c.shortname
          ORDER BY lastmodified ASC";

    $rows = $DB->get_records_sql($sql, array_merge($catparams, $groupparams));

    if (empty($rows)) {
        return [];
    }

    // Fetch teachers for each course in one pass.
    // Role shortname 'editingteacher' is the standard Moodle teacher role.
    $courseids = array_keys($rows);
    [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');

    $teachersql = "SELECT ctx.instanceid AS courseid,
                          u.firstname,
                          u.lastname
                     FROM {role_assignments} ra
                     JOIN {user} u         ON u.id = ra.userid
                     JOIN {role} r         ON r.id = ra.roleid
                                          AND r.shortname = 'editingteacher'
                     JOIN {context} ctx    ON ctx.id = ra.contextid
                                          AND ctx.contextlevel = 50
                    WHERE ctx.instanceid $insql
                 ORDER BY u.lastname, u.firstname";

    $teacherrows = $DB->get_records_sql($teachersql, $inparams);

    // Group teachers by courseid.
    $teachermap = [];
    foreach ($teacherrows as $tr) {
        $cid = (int) $tr->courseid;
        $teachermap[$cid][] = $tr->lastname . ', ' . $tr->firstname;
    }

    $today = new DateTime('today');
    $results = [];

    foreach ($rows as $row) {
        $cid      = (int) $row->courseid;
        $lastdate = new DateTime('@' . (int) $row->lastmodified);
        $lastdate->setTimezone(new DateTimeZone(date_default_timezone_get()));

        $obj               = new stdClass();
        $obj->courseid     = $cid;
        $obj->fullname     = $row->fullname;
        $obj->shortname    = $row->shortname;
        $obj->lastupdate   = $lastdate->format('Y-m-d');
        $obj->days_since   = stv_count_weekdays($lastdate, $today);
        $obj->teachers     = isset($teachermap[$cid])
                             ? implode('; ', $teachermap[$cid])
                             : '—';
        $results[] = $obj;
    }

    // Sort descending by days_since (stale courses first).
    usort($results, fn($a, $b) => $b->days_since - $a->days_since);

    return $results;
}

/**
 * Bulk-fetch the count of letter-F course final grades for a set of
 * Moodle user IDs. "F" is determined by joining the user's final grade
 * value against the course's grade_letters scale, falling back to the
 * site default scale where the course has none.
 *
 * Only courses whose shortname matches ^[0-9]{4}[A-Z]{2}$ and are not
 * hidden are counted (per project naming convention for "real" classes).
 *
 * @param array $userids Array of Moodle user IDs.
 * @return array Map of [userid => failed_count]. Missing users absent from map.
 */
function stv_get_failed_courses_by_userid(array $userids) {
    global $DB;

    if (empty($userids)) {
        return [];
    }

    $clean = [];
    foreach ($userids as $uid) {
        $uid = (int) $uid;
        if ($uid > 0) {
            $clean[$uid] = $uid;
        }
    }
    if (empty($clean)) {
        return [];
    }

    [$insql, $inparams] = $DB->get_in_or_equal($clean, SQL_PARAMS_NAMED, 'uid');

    // Pull every "real" course final grade for these users, with each course's
    // own letter scale (or the site default fallback). We resolve the letter
    // in PHP rather than SQL because letter scales differ per course context.
    $sql = "SELECT gg.id AS gid,
                   gg.userid,
                   gg.finalgrade,
                   gg.rawgrademax,
                   gi.courseid,
                   c.shortname
              FROM {grade_grades} gg
              JOIN {grade_items} gi ON gi.id = gg.itemid
                                    AND gi.itemtype = 'course'
              JOIN {course} c ON c.id = gi.courseid
                              AND c.visible = 1
                              AND c.shortname REGEXP '^[0-9]{4}[A-Z]{2}\$'
              JOIN {enrol} e ON e.courseid = gi.courseid
              JOIN {user_enrolments} ue ON ue.enrolid = e.id
                                       AND ue.userid = gg.userid
                                       AND ue.status = 0
             WHERE gg.userid $insql
               AND gg.finalgrade IS NOT NULL
               AND gg.rawgrademax IS NOT NULL
               AND gg.rawgrademax > 0";

    $rows = $DB->get_records_sql($sql, $inparams);

    if (empty($rows)) {
        return [];
    }

    // Cache letter scales per course context to avoid repeated lookups.
    $letterscache = [];

    $counts = [];
    foreach ($rows as $r) {
        $courseid = (int) $r->courseid;

        // Get the applicable letter boundaries for this course context.
        if (!isset($letterscache[$courseid])) {
            $context = context_course::instance($courseid);
            $letterscache[$courseid] = grade_get_letters($context);
        }
        $letters = $letterscache[$courseid];

        // Compute percentage and resolve to letter.
        $pct = ((float) $r->finalgrade) / ((float) $r->rawgrademax) * 100.0;
        $letter = stv_pct_to_letter($pct, $letters);

        if ($letter === 'F') {
            $uid = (int) $r->userid;
            $counts[$uid] = ($counts[$uid] ?? 0) + 1;
        }
    }

    return $counts;
}

/**
 * Resolve a percentage score to a letter grade using a Moodle letter
 * boundary array. The boundary array is keyed by lower-bound percentages
 * (e.g. [93=>'A', 90=>'A-', 87=>'B+', ..., 0=>'F']).
 *
 * @param float $pct     Percentage score (0-100).
 * @param array $letters Map from lower-bound percentage => letter.
 * @return string The letter grade, or 'F' if no boundary matched.
 */
function stv_pct_to_letter($pct, array $letters) {
    if (empty($letters)) {
        return 'F';
    }

    // Sort descending by lower-bound so the highest matching band wins.
    $boundaries = $letters;
    krsort($boundaries, SORT_NUMERIC);

    foreach ($boundaries as $boundary => $letter) {
        if ($pct >= (float) $boundary) {
            return $letter;
        }
    }
    return 'F';
}

/**
 * Bulk-fetch GPA values for a set of Moodle user IDs, using the
 * management course's grade letter scale to convert letters to GPA points.
 *
 * GPA is computed as the unweighted average of letter→points conversions
 * across all real courses (shortname matches ^[0-9]{4}[A-Z]{2}$, not hidden)
 * the student has a final grade in.
 *
 * @param array  $userids     Array of Moodle user IDs.
 * @param string $schoollevel 'hs', 'ms', or 'es' — picks which mgmt course
 *                            grade-letter scale provides the points table.
 * @return array Map of [userid => gpa_float]. Students with zero qualifying
 *               grades are absent from the map (treated as no-data).
 */
function stv_get_gpa_by_userid(array $userids, $schoollevel) {
    global $DB;

    if (empty($userids)) {
        return [];
    }

    $clean = [];
    foreach ($userids as $uid) {
        $uid = (int) $uid;
        if ($uid > 0) {
            $clean[$uid] = $uid;
        }
    }
    if (empty($clean)) {
        return [];
    }

    // Resolve management course id and build a letter -> points map from it.
    $mgmtcfg = $schoollevel . '_mgmt_course';
    $mgmtcourseid = (int) get_config('mod_mtssdashboard', $mgmtcfg);
    if ($mgmtcourseid <= 0) {
        return [];
    }
    $points = stv_get_letter_points_for_course($mgmtcourseid);
    if (empty($points)) {
        return [];
    }

    [$insql, $inparams] = $DB->get_in_or_equal($clean, SQL_PARAMS_NAMED, 'uid');

    $sql = "SELECT gg.id AS gid,
                   gg.userid,
                   gg.finalgrade,
                   gg.rawgrademax,
                   gi.courseid
              FROM {grade_grades} gg
              JOIN {grade_items} gi ON gi.id = gg.itemid
                                    AND gi.itemtype = 'course'
              JOIN {course} c ON c.id = gi.courseid
                              AND c.visible = 1
                              AND c.shortname REGEXP '^[0-9]{4}[A-Z]{2}\$'
              JOIN {enrol} e ON e.courseid = gi.courseid
              JOIN {user_enrolments} ue ON ue.enrolid = e.id
                                       AND ue.userid = gg.userid
                                       AND ue.status = 0
             WHERE gg.userid $insql
               AND gg.finalgrade IS NOT NULL
               AND gg.rawgrademax IS NOT NULL
               AND gg.rawgrademax > 0";

    $rows = $DB->get_records_sql($sql, $inparams);

    if (empty($rows)) {
        return [];
    }

    // Resolve letter per row using each course's own letter boundaries,
    // then convert that letter to GPA points using the mgmt course scale.
    $letterscache = [];

    $sums = [];
    foreach ($rows as $r) {
        $courseid = (int) $r->courseid;

        if (!isset($letterscache[$courseid])) {
            $context = context_course::instance($courseid);
            $letterscache[$courseid] = grade_get_letters($context);
        }
        $letters = $letterscache[$courseid];

        $pct = ((float) $r->finalgrade) / ((float) $r->rawgrademax) * 100.0;
        $letter = stv_pct_to_letter($pct, $letters);

        // Look up GPA points for this letter from the mgmt course scale.
        if (!array_key_exists($letter, $points)) {
            // Letter not on the mgmt course scale — skip rather than fudge.
            continue;
        }

        $uid = (int) $r->userid;
        if (!isset($sums[$uid])) {
            $sums[$uid] = ['total' => 0.0, 'count' => 0];
        }
        $sums[$uid]['total'] += $points[$letter];
        $sums[$uid]['count']++;
    }

    $gpas = [];
    foreach ($sums as $uid => $s) {
        if ($s['count'] > 0) {
            $gpas[$uid] = round($s['total'] / $s['count'], 3);
        }
    }
    return $gpas;
}

/**
 * Build a letter → GPA points map from the grade letter scale of a given
 * course context. The "points" come from the percentage lower-bound that
 * each letter sits on, normalized to a 4.0 scale (e.g. 93% → 4.0, 90% → 3.7).
 *
 * Conventional U.S. letter→GPA mapping is hardcoded; this matches your
 * earlier instruction that A=4.0, A-=3.7, etc. The letters that *exist*
 * are determined by the course's actual grade_letters scale, so a school
 * that doesn't use B- gets no B- entry.
 *
 * @param int $courseid Course whose letter scale defines which letters apply.
 * @return array Map of [letter => gpa_points], e.g. ['A' => 4.0, 'A-' => 3.7, ...].
 */
function stv_get_letter_points_for_course($courseid) {
    $context = context_course::instance($courseid);
    $letters = grade_get_letters($context);
    if (empty($letters)) {
        return [];
    }

    // Conventional U.S. 4.0 GPA scale.
    $standard = [
        'A+' => 4.0, 'A'  => 4.0, 'A-' => 3.7,
        'B+' => 3.3, 'B'  => 3.0, 'B-' => 2.7,
        'C+' => 2.3, 'C'  => 2.0, 'C-' => 1.7,
        'D+' => 1.3, 'D'  => 1.0, 'D-' => 0.7,
        'F'  => 0.0,
    ];

    // Keep only the letters this course actually uses.
    $points = [];
    foreach ($letters as $boundary => $letter) {
        $key = trim((string) $letter);
        if (isset($standard[$key])) {
            $points[$key] = $standard[$key];
        }
    }
    return $points;
}

/**
 * Bulk-fetch the count of "low" state test results (proficiency_level
 * Basic or Limited) for a set of SSIDs, restricted to the current
 * school year per plugin settings.
 *
 * "Current school year" is derived from the gradyear plugin setting
 * (the year current 12th graders graduate). Test result year is matched
 * directly — so if gradyear=2026 the current school year is 2026.
 *
 * @param array $ssids Array of state student IDs (msb_contacts.StateStudentID).
 * @return array Map of [ssid => low_score_count]. Missing students absent from map.
 */
function stv_get_low_ost_by_ssid(array $ssids) {
    global $DB;

    if (empty($ssids)) {
        return [];
    }

    $clean = [];
    foreach ($ssids as $sid) {
        $sid = trim((string) $sid);
        if ($sid !== '') {
            $clean[$sid] = $sid;
        }
    }
    if (empty($clean)) {
        return [];
    }

    // Subject-area grouping mirrors the State Testing chart families.
    // Only used for the non-HS "most recent per area" rule below.
    $codetoarea = [
        'R'    => 'ela', 'ELA'  => 'ela', 'ELA2' => 'ela',
        'M'    => 'math', 'MTH1' => 'math', 'ALG1' => 'math', 'GEOM' => 'math',
        'S'    => 'sci', 'BIOL' => 'sci',
        'C'    => 'ss', 'HIST' => 'ss', 'GOVM' => 'ss',
    ];

    $currentyear = (int) get_config('mod_mtssdashboard', 'gradyear');
    if ($currentyear <= 0) {
        return [];
    }
    $minyear = $currentyear - 2;

    // Read the HS OST passing score from the warning_levels config row.
    // A "Basic" with score >= passing_score is treated as passed (not low).
    $passingrow = $DB->get_record('mtssdashboard_warning_levels', [
        'schoollevel' => 'hs',
        'metric_key'  => 'ost_passing_score',
        'color'       => 'config',
    ]);
    $hspassscore = $passingrow ? (float) $passingrow->threshold_min : null;

    [$insql, $inparams] = $DB->get_in_or_equal($clean, SQL_PARAMS_NAMED, 'sid');

    // Pull all results — no year filter at the SQL level. We apply the right
    // rule per row in PHP based on grade_level:
    // - HS results: always counted (no time limit, no most-recent filter),
    // but a "Basic" with score >= hs passing score is excluded.
    // - non-HS results: only the most recent within the 2-year window per
    // subject area is counted.
    global $CFG;
    $statetable = $CFG->prefix . 'NTstatetesting';
    $sql = "SELECT id, ssid, subject_code, year, proficiency_level, grade_level,
                   assessment_score AS score
              FROM {$statetable}
             WHERE ssid $insql
          ORDER BY ssid ASC, year DESC";

    $rows = $DB->get_records_sql($sql, $inparams);

    if (empty($rows)) {
        return [];
    }

    $keptnonhs = [];
    $map = [];

    foreach ($rows as $r) {
        $islow = ($r->proficiency_level === 'Basic' || $r->proficiency_level === 'Limited');

        if ($r->grade_level === 'High School') {
            // HS rule: every Basic/Limited counts UNLESS the test is one of
            // the graduation competency assessments (Algebra I, Integrated
            // Math I, or ELA II) AND the score meets the HS passing score —
            // those subjects use the score as the "passed for graduation"
            // threshold even when the proficiency label is "Basic".
            $iscompetencytest = in_array($r->subject_code, ['ALG1', 'MTH1', 'ELA2'], true);
            if (
                $islow && $iscompetencytest && $hspassscore !== null
                    && $r->proficiency_level === 'Basic'
                    && (float) $r->score >= $hspassscore
            ) {
                continue; // Passed for graduation despite the Basic label.
            }
            if ($islow) {
                $map[$r->ssid] = ($map[$r->ssid] ?? 0) + 1;
            }
            continue;
        }

        // Non-HS rule: most recent per subject area within the 2-year window.
        if ((int) $r->year < $minyear) {
            continue;
        }
        $area = $codetoarea[$r->subject_code] ?? null;
        if ($area === null) {
            continue;
        }
        $key = $r->ssid . '|' . $area;
        if (isset($keptnonhs[$key])) {
            continue;
        }
        $keptnonhs[$key] = true;
        if ($islow) {
            $map[$r->ssid] = ($map[$r->ssid] ?? 0) + 1;
        }
    }

    return $map;
}

/**
 * Look up which warning level color band a given numeric value falls into,
 * for the given school level and metric.
 *
 * Reads from {mtssdashboard_warning_levels} which holds (schoollevel, metric_key,
 * color, threshold_min, threshold_max) rows configured by school managers.
 * The first matching band wins (rows shouldn't overlap, but we prefer determinism).
 *
 * @param string     $schoollevel 'hs', 'ms', or 'es'.
 * @param string     $metrickey   e.g. 'gpa', 'office_incidents'.
 * @param float|null $value       The numeric metric value. NULL means no data.
 * @return string|null Color name ('green', 'yellow', 'orange', 'red', 'purple')
 *                     or NULL if no band matched (or value was NULL).
 */
function stv_get_band_color($schoollevel, $metrickey, $value) {
    static $cache = [];

    if ($value === null) {
        return null;
    }

    // The ES iReady diagnostic percentile columns (ela_fall_pct, ela_winter_pct,
    // ela_spring_pct, math_fall_pct, math_winter_pct, math_spring_pct) share
    // the same warning thresholds as iready_ela_percentile / iready_math_percentile.
    // Score columns (ela_fall_score etc.) are display-only — no color banding.
    $aliasmap = [
        'ela_fall_pct'    => 'iready_ela_percentile',
        'ela_winter_pct'  => 'iready_ela_percentile',
        'ela_spring_pct'  => 'iready_ela_percentile',
        'math_fall_pct'   => 'iready_math_percentile',
        'math_winter_pct' => 'iready_math_percentile',
        'math_spring_pct' => 'iready_math_percentile',
    ];
    if (isset($aliasmap[$metrickey])) {
        $metrickey = $aliasmap[$metrickey];
    }

    // Cache the band rows for each (schoollevel, metric_key) pair so a table
    // with hundreds of cells doesn't hit the DB hundreds of times.
    $cachekey = $schoollevel . '|' . $metrickey;
    if (!isset($cache[$cachekey])) {
        global $DB;
        $cache[$cachekey] = $DB->get_records(
            'mtssdashboard_warning_levels',
            ['schoollevel' => $schoollevel, 'metric_key' => $metrickey],
            'sortorder ASC'
        );
    }
    $bands = $cache[$cachekey];
    if (empty($bands)) {
        return null;
    }

    $val = (float) $value;

    foreach ($bands as $band) {
        $min = ($band->threshold_min !== null) ? (float) $band->threshold_min : null;
        $max = ($band->threshold_max !== null) ? (float) $band->threshold_max : null;

        // Bound check: NULL min = no lower bound, NULL max = no upper bound.
        $passesmin = ($min === null) || ($val >= $min);
        $passesmax = ($max === null) || ($val <= $max);

        if ($passesmin && $passesmax) {
            return $band->color;
        }
    }

    return null;
}

/**
 * Decide whether a band color counts as "flagged" (i.e. contributes to the
 * Overall Risk Level count). Conventionally green and yellow are not
 * flagged; orange, red, and purple are.
 *
 * @param string|null $color Result of stv_get_band_color(); NULL is not flagged.
 * @return bool True if this color contributes to the risk count.
 */
function stv_band_is_flagged($color) {
    return in_array($color, ['orange', 'red', 'purple'], true);
}

/**
 * Compute the Overall Risk Level given a count of flagged metrics.
 * Mirrors the conventional "At Risk Levels" buckets.
 *
 * @param int $flagcount Number of metrics in flagged bands for the student.
 * @return string Risk key: 'none', 'low', 'moderate', or 'extreme'.
 */
/**
 * Compute the Overall Risk Level given a count of flagged metrics.
 * Thresholds are read from the warning_levels table (metric_key='risk_threshold')
 * so each school level can configure their own breakpoints.
 * Falls back to hardcoded defaults if no DB rows exist.
 *
 * @param int    $flagcount   Number of flagged metrics for this student.
 * @param string $schoollevel 'hs', 'ms', or 'es'.
 * @return string Risk key: 'none', 'low', 'moderate', or 'extreme'.
 */
function stv_compute_risk_level($flagcount, $schoollevel = 'hs') {
    static $cache = [];

    if (!isset($cache[$schoollevel])) {
        global $DB;
        $rows = $DB->get_records('mtssdashboard_warning_levels', [
            'schoollevel' => $schoollevel,
            'metric_key'  => 'risk_threshold',
        ], 'sortorder ASC');

        // Build threshold map: color => min flag count.
        $thresholds = ['low' => 1, 'moderate' => 2, 'extreme' => 4]; // Defaults.
        foreach ($rows as $r) {
            if (in_array($r->color, ['low', 'moderate', 'extreme'], true)) {
                $thresholds[$r->color] = (int) $r->threshold_min;
            }
        }
        $cache[$schoollevel] = $thresholds;
    }

    $t = $cache[$schoollevel];

    if ($flagcount <= 0) {
        return 'none';
    }
    if ($flagcount >= $t['extreme']) {
        return 'extreme';
    }
    if ($flagcount >= $t['moderate']) {
        return 'moderate';
    }
    if ($flagcount >= $t['low']) {
        return 'low';
    }
    return 'none';
}

/**
 * Bulk-fetch the most recent iReady ELA percentile for a set of school
 * StudentNumbers, considering only tests completed within the last 365 days.
 *
 * @param array $studentnumbers Array of school student numbers.
 * @return array Map of [studentnumber => percentile_int]. Missing students
 *               or those with no recent test are absent from the map.
 */
function stv_get_iready_ela_by_studentnumber(array $studentnumbers) {
    return stv_get_iready_percentile_by_studentnumber($studentnumbers, 'NTireadyela');
}

/**
 * Bulk-fetch the most recent iReady Math percentile for a set of school
 * StudentNumbers, considering only tests completed within the last 365 days.
 *
 * @param array $studentnumbers Array of school student numbers.
 * @return array Map of [studentnumber => percentile_int].
 */
function stv_get_iready_math_by_studentnumber(array $studentnumbers) {
    return stv_get_iready_percentile_by_studentnumber($studentnumbers, 'NTireadymath');
}

/**
 * Shared implementation for iReady ELA and Math percentile lookups.
 *
 * Both tables share the same shape: StudentID + Percentile + CompletionDate.
 * They are externally managed (not registered in Moodle's XMLDB), so we
 * reference them via the configured prefix.
 *
 * Strategy: pull every (StudentID, Percentile, CompletionDate) row in the
 * 365-day window for the requested students, then in PHP keep the row with
 * the latest CompletionDate per StudentID. Doing the "most recent" filter
 * in SQL would require a correlated subquery or window function; the PHP
 * approach is simpler and the row count is bounded (at most a few rows
 * per student per year).
 *
 * @param array  $studentnumbers Array of school student numbers.
 * @param string $tablebase      Bare table name (no prefix), e.g. 'NTireadyela'.
 * @return array Map of [studentnumber => percentile_int].
 */
function stv_get_iready_percentile_by_studentnumber(array $studentnumbers, $tablebase) {
    global $DB, $CFG;

    if (empty($studentnumbers)) {
        return [];
    }

    $clean = [];
    foreach ($studentnumbers as $sn) {
        $sn = (int) $sn;
        if ($sn > 0) {
            $clean[$sn] = $sn;
        }
    }
    if (empty($clean)) {
        return [];
    }

    [$insql, $inparams] = $DB->get_in_or_equal($clean, SQL_PARAMS_NAMED, 'sn');

    // Current school year only. The iReady tables store AcademicYear as
    // "YYYY-YYYY" (e.g. "2025-2026"), so we build that string from the
    // gradyear setting. gradyear is the year current 12th graders graduate,
    // which by convention equals the spring half of the current school year.
    $gradyear = (int) get_config('mod_mtssdashboard', 'gradyear');
    if ($gradyear <= 0) {
        return [];
    }
    $academicyear = ($gradyear - 1) . '-' . $gradyear;
    $inparams['ay'] = $academicyear;

    // Externally managed table; reference via the configured prefix.
    $table = $CFG->prefix . $tablebase;

    $sql = "SELECT StudentID AS studentnumber, Percentile AS percentile,
                   CompletionDate AS completiondate
              FROM {$table}
             WHERE StudentID $insql
               AND AcademicYear = :ay
               AND Percentile IS NOT NULL
          ORDER BY StudentID ASC, CompletionDate DESC";

    // The iReady tables don't have a Moodle 'id' primary key; they have
    // no unique single-column key at all in the schema you provided.
    // get_records_sql requires a unique first column. Use get_recordset_sql
    // instead so duplicate-key errors don't bite us.
    $rs = $DB->get_recordset_sql($sql, $inparams);

    $map = [];
    foreach ($rs as $r) {
        $sn = (int) $r->studentnumber;
        // Because we ORDER BY CompletionDate DESC, the FIRST row we see
        // per StudentID is the most recent. Skip subsequent rows.
        if (isset($map[$sn])) {
            continue;
        }
        $map[$sn] = (int) $r->percentile;
    }
    $rs->close();

    return $map;
}

/**
 * Bulk-fetch iReady ELA and Math diagnostic scores and percentiles for
 * Fall, Winter, and Spring norming windows for a set of school StudentNumbers.
 *
 * Used by the Elementary School early warning tab to display all three
 * diagnostic windows side by side.
 *
 * Returns a map keyed by studentnumber. Each value is an object with:
 *   ->ela_fall_score, ->ela_fall_pct
 *   ->ela_winter_score, ->ela_winter_pct
 *   ->ela_spring_score, ->ela_spring_pct
 *   ->math_fall_score, ->math_fall_pct
 *   ->math_winter_score, ->math_winter_pct
 *   ->math_spring_score, ->math_spring_pct
 *
 * All values are integers or null if no test exists for that window.
 *
 * @param array $studentnumbers Array of school student numbers.
 * @return array Map of [studentnumber => stdClass].
 */
function stv_get_iready_diagnostics_by_studentnumber(array $studentnumbers) {
    global $DB, $CFG;

    if (empty($studentnumbers)) {
        return [];
    }

    $clean = [];
    foreach ($studentnumbers as $sn) {
        $sn = (int) $sn;
        if ($sn > 0) {
            $clean[$sn] = $sn;
        }
    }
    if (empty($clean)) {
        return [];
    }

    $gradyear = (int) get_config('mod_mtssdashboard', 'gradyear');
    if ($gradyear <= 0) {
        return [];
    }
    $academicyear = ($gradyear - 1) . '-' . $gradyear;

    [$insql, $inparams] = $DB->get_in_or_equal($clean, SQL_PARAMS_NAMED, 'sn');
    $inparams['ay'] = $academicyear;

    // Initialise result map with null values for all students.
    $map = [];
    foreach ($clean as $sn) {
        $map[$sn] = (object) [
            'ela_fall_score'    => null, 'ela_fall_pct'    => null,
            'ela_winter_score'  => null, 'ela_winter_pct'  => null,
            'ela_spring_score'  => null, 'ela_spring_pct'  => null,
            'math_fall_score'   => null, 'math_fall_pct'   => null,
            'math_winter_score' => null, 'math_winter_pct' => null,
            'math_spring_score' => null, 'math_spring_pct' => null,
        ];
    }

    // Helper to query one subject table and populate the map.
    $fetch = function ($tablebase, $prefix) use ($DB, $CFG, $insql, $inparams, &$map) {
        $table = $CFG->prefix . $tablebase;
        $sql = "SELECT StudentID AS studentnumber,
                       NormingWindow AS normingwindow,
                       OverallScaleScore AS scalescore,
                       Percentile AS percentile,
                       CompletionDate AS completiondate
                  FROM {$table}
                 WHERE StudentID $insql
                   AND AcademicYear = :ay
                   AND CompletionDate IS NOT NULL
              ORDER BY StudentID ASC, CompletionDate ASC";

        $rs = $DB->get_recordset_sql($sql, $inparams);
        foreach ($rs as $r) {
            $sn = (int) $r->studentnumber;
            if (!isset($map[$sn])) {
                continue;
            }
            $nw = strtolower($r->normingwindow ?? '');
            if (strpos($nw, 'fall') !== false) {
                $window = 'fall';
            } else if (strpos($nw, 'winter') !== false) {
                $window = 'winter';
            } else if (strpos($nw, 'spring') !== false) {
                $window = 'spring';
            } else {
                continue; // Skip Rush Flag rows and blanks.
            }
            // ORDER BY CompletionDate ASC means later rows overwrite earlier
            // ones within the same window -- keeps the most recent per window.
            $map[$sn]->{$prefix . '_' . $window . '_score'} =
                $r->scalescore !== null ? (int) $r->scalescore : null;
            $map[$sn]->{$prefix . '_' . $window . '_pct'} =
                $r->percentile !== null ? (int) $r->percentile : null;
        }
        $rs->close();
    };

    $fetch('NTireadyela', 'ela');
    $fetch('NTireadymath', 'math');

    return $map;
}

/**
 * Bulk-fetch RIMP status for a set of student numbers.
 * Returns a map of [studentnumber => 1] for students who have any record
 * in mtssdashboard_rimp (draft or finalized).
 *
 * @param array $studentnumbers Array of school student numbers.
 * @return array Map of [studentnumber => 1].
 */
function stv_get_rimp_by_studentnumber(array $studentnumbers) {
    global $DB;

    if (empty($studentnumbers)) {
        return [];
    }

    $clean = [];
    foreach ($studentnumbers as $sn) {
        $sn = (int) $sn;
        if ($sn > 0) {
            $clean[$sn] = $sn;
        }
    }
    if (empty($clean)) {
        return [];
    }

    [$insql, $inparams] = $DB->get_in_or_equal($clean, SQL_PARAMS_NAMED, 'sn');
    $rs = $DB->get_recordset_select(
        'mtssdashboard_rimp',
        "studentnumber $insql",
        $inparams,
        '',
        'studentnumber, status'
    );
    $map = [];
    foreach ($rs as $r) {
        $map[(int)$r->studentnumber] = 1;
    }
    $rs->close();

    return $map;
}

/**
 * Fetch a single student's current courses with grade percentage, letter,
 * and teacher name(s), for display on the Student Data tab's contact/grades
 * panel. Mirrors the scale-lookup logic in mtssdashboard_get_grades_data()
 * but scoped to one student and enriched with the course teacher(s).
 *
 * @param int    $userid      Moodle user id of the student.
 * @param string $courselevel 'es', 'ms', or 'hs' — selects the grade letter
 *                             scale from the matching management course.
 * @return array List of stdClass rows: coursename, teacher, pct, letter.
 */
function stv_get_student_course_grades($userid, $courselevel) {
    global $DB;

    $result = [];
    if (empty($userid)) {
        return $result;
    }

    // Grade letter scale from management course (same source as Grades tab).
    $mgmtcourse = ($courselevel === 'ms')
        ? ((int) get_config('mod_mtssdashboard', 'ms_mgmt_course') ?: 54)
        : ((int) get_config('mod_mtssdashboard', 'hs_mgmt_course') ?: 53);

    $scale = array_values($DB->get_records_sql(
        "SELECT gl.lowerboundary, gl.letter
           FROM {grade_letters} gl
           JOIN {context} ctx ON ctx.id = gl.contextid
          WHERE ctx.contextlevel = 50
            AND ctx.instanceid   = :courseid
       ORDER BY gl.lowerboundary DESC",
        ['courseid' => $mgmtcourse]
    ));

    // Fall back to site-level scale if the management course has none.
    if (empty($scale)) {
        $scale = array_values($DB->get_records_sql(
            "SELECT lowerboundary, letter
               FROM {grade_letters}
              WHERE contextid = 1
           ORDER BY lowerboundary DESC"
        ));
    }

    $pcttoletter = function ($pct) use ($scale) {
        foreach ($scale as $row) {
            if ($pct >= (float) $row->lowerboundary) {
                return $row->letter;
            }
        }
        return 'F';
    };

    // Course + final grade for every course this student is enrolled in.
    // and has a final grade recorded for.
    $gradesql = "SELECT c.id AS courseid,
                        c.fullname AS coursename,
                        gi.grademax,
                        gg.finalgrade
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid
                   JOIN {course} c ON c.id = e.courseid AND c.visible = 1
              LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
              LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = ue.userid
                  WHERE ue.userid = :userid
                    AND gg.finalgrade IS NOT NULL
               ORDER BY c.fullname ASC";
    $rows = $DB->get_records_sql($gradesql, ['userid' => $userid]);

    foreach ($rows as $row) {
        $grademax = (float) $row->grademax ?: 100;
        $pct      = round(((float) $row->finalgrade / $grademax) * 100, 2);
        $letter   = $pcttoletter($pct);

        // Teacher for this course — editingteacher role at course context.
        // Only one is shown: whoever was assigned to the role first
        // (earliest role_assignments.timemodified, id as a tiebreaker).
        $teacher = $DB->get_record_sql(
            "SELECT u.id, u.firstname, u.lastname
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid
                    AND ctx.contextlevel = 50 AND ctx.instanceid = :courseid
               JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'editingteacher'
               JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
           ORDER BY ra.timemodified ASC, ra.id ASC
              LIMIT 1",
            ['courseid' => $row->courseid]
        );
        $teachername = !empty($teacher) ? ($teacher->firstname . ' ' . $teacher->lastname) : '—';

        $result[] = (object) [
            'courseid'   => $row->courseid,
            'coursename' => $row->coursename,
            'teacher'    => $teachername,
            'pct'        => $pct,
            'letter'     => $letter,
        ];
    }

    return $result;
}

/**
 * Calculate the number of weekdays (Mon-Fri) between two dates.
 * Returns the count of weekdays from $from up to but not including $to.
 *
 * @param DateTime $from  Earlier date.
 * @param DateTime $to    Later date (defaults to today).
 * @return int Number of weekdays.
 */
function stv_count_weekdays(DateTime $from, DateTime $to) {

    // Build set of school-break dates to skip.
    // We exclude the same windows for every year that falls within the range.
    $excluded = [];

    $startyear = (int) $from->format('Y');
    $endyear   = (int) $to->format('Y');

    for ($yr = $startyear; $yr <= $endyear; $yr++) {
        // Last two weeks of December: Dec 18 – Dec 31.
        $d = new DateTime($yr . '-12-18');
        $last = new DateTime($yr . '-12-31');
        while ($d <= $last) {
            $excluded[$d->format('Y-m-d')] = true;
            $d->modify('+1 day');
        }

        // First week of January: Jan 1 – Jan 5.
        $d = new DateTime($yr . '-01-01');
        $last = new DateTime($yr . '-01-05');
        while ($d <= $last) {
            $excluded[$d->format('Y-m-d')] = true;
            $d->modify('+1 day');
        }

        // Week of Thanksgiving: find the 4th Thursday of November,
        // then exclude the full Mon–Fri of that week.
        $nov1    = new DateTime($yr . '-11-01');
        $thurnum = 0;
        $d       = clone $nov1;
        while ($thurnum < 4) {
            if ((int) $d->format('N') === 4) { // 4 = Thursday
                $thurnum++;
            }
            if ($thurnum < 4) {
                $d->modify('+1 day');
            }
        }
        // At this point $d is the 4th Thursday — step back to Monday of that week.
        $monday = clone $d;
        $monday->modify('monday this week');
        $friday = clone $monday;
        $friday->modify('+4 days');
        $w = clone $monday;
        while ($w <= $friday) {
            $excluded[$w->format('Y-m-d')] = true;
            $w->modify('+1 day');
        }
    }
    // Count weekdays, skipping excluded dates.
    $days = 0;
    $current = clone $from;
    // Normalise both to midnight so day comparison is clean.
    $current->setTime(0, 0, 0);
    $end = clone $to;
    $end->setTime(0, 0, 0);

    while ($current < $end) {
        $dow    = (int) $current->format('N'); // 1=Mon … 7=Sun
        $datestr = $current->format('Y-m-d');
        if ($dow <= 5 && !isset($excluded[$datestr])) {
            $days++;
        }
        $current->modify('+1 day');
    }
    return $days;
}

/**
 * Bulk-fetch the number of weekdays since each student's most recent
 * iReady lesson completion for ELA and Math separately.
 *
 * Checks both the regular progress tables (K-5) and the Pro tables (6-8)
 * and uses whichever has the most recent completion date per student.
 *
 * Returns 99 for students who have never completed a lesson AND the
 * current date is on or after October 1 of the current school year.
 * Returns null before October 1 (so the column shows — instead of 99).
 *
 * @param array $studentnumbers Array of school student numbers.
 * @return array Map of [studentnumber => stdClass] with ->ela_days and ->math_days.
 *               Values are int (weekdays) or null (too early to flag).
 */
function stv_get_iready_days_since_lesson(array $studentnumbers) {
    global $DB, $CFG;

    if (empty($studentnumbers)) {
        return [];
    }

    $clean = [];
    foreach ($studentnumbers as $sn) {
        $sn = (int) $sn;
        if ($sn > 0) {
            $clean[$sn] = $sn;
        }
    }
    if (empty($clean)) {
        return [];
    }

    $gradyear = (int) get_config('mod_mtssdashboard', 'gradyear');
    if ($gradyear <= 0) {
        return [];
    }
    $academicyear = ($gradyear - 1) . '-' . $gradyear;

    // Oct 1 of the school year start (fall year = gradyear - 1).
    $oct1 = new DateTime((($gradyear - 1)) . '-10-01');
    $today = new DateTime('today');
    $afteroct1 = ($today >= $oct1);

    [$insql, $inparams] = $DB->get_in_or_equal($clean, SQL_PARAMS_NAMED, 'sn');
    $inparams['ay'] = $academicyear;

    // Initialise map — null means "not yet flaggable".
    $map = [];
    foreach ($clean as $sn) {
        $map[$sn] = (object) ['ela_days' => null, 'math_days' => null];
    }

    // Track the most recent date found per student per subject across all tables.
    $latest = [];
    foreach ($clean as $sn) {
        $latest[$sn] = (object) ['ela' => null, 'math' => null];
    }

    // Helper: fetch most recent CompletionDate per student from one table
    // and update $latest if newer than what we already have.
    $fetch = function ($tablebase, $subject) use ($DB, $CFG, $insql, $inparams, &$latest) {
        $table = $CFG->prefix . $tablebase;
        $sql = "SELECT StudentID AS studentnumber,
                       MAX(CompletionDate) AS lastdate
                  FROM {$table}
                 WHERE StudentID $insql
                   AND AcademicYear = :ay
                   AND CompletionDate IS NOT NULL
              GROUP BY StudentID";

        $rs = $DB->get_recordset_sql($sql, $inparams);
        foreach ($rs as $r) {
            $sn = (int) $r->studentnumber;
            if (!isset($latest[$sn])) {
                continue;
            }
            $thisdate = new DateTime($r->lastdate);
            if ($latest[$sn]->{$subject} === null || $thisdate > $latest[$sn]->{$subject}) {
                $latest[$sn]->{$subject} = $thisdate;
            }
        }
        $rs->close();
    };

    // Query all four tables.
    $fetch('NTireadyelaprogress', 'ela');
    $fetch('NTireadyproelaprogress', 'ela');
    $fetch('NTireadymathprogress', 'math');
    $fetch('NTireadypromathprogress', 'math');

    // Now compute weekdays from latest date to today.
    foreach ($clean as $sn) {
        foreach (['ela', 'math'] as $subject) {
            $field = $subject . '_days';
            if ($latest[$sn]->{$subject} !== null) {
                $map[$sn]->{$field} = stv_count_weekdays($latest[$sn]->{$subject}, $today);
            } else {
                $map[$sn]->{$field} = $afteroct1 ? 99 : null;
            }
        }
    }

    return $map;
}

/**
 * Grade levels served by each school level, taken from the plugin's
 * grade range settings (e.g. ms_grade_min / ms_grade_max). Used by the
 * "iReady Lessons by Grade" toggle on the Warning Levels tab.
 *
 * @param string $schoollevel 'es', 'ms', or 'hs'.
 * @return int[] Numeric grades.
 */
function stv_get_school_level_grades($schoollevel) {
    $cfg = mtssdashboard_get_level_config();
    $min = $cfg->{$schoollevel . '_min'} ?? null;
    $max = $cfg->{$schoollevel . '_max'} ?? null;
    if ($min === null || $max === null || $min > $max) {
        return [];
    }
    return range($min, $max);
}

/**
 * Return the grades that do NOT take iReady lessons for a subject.
 *
 * Stored as plugin config "lessonsoff_{schoollevel}_{subject}" holding a
 * comma-separated list of numeric grades (e.g. "7"). Set from the
 * "iReady Lessons by Grade" card on the Warning Levels tab.
 *
 * When $rimponly is true, reads "rimplessonsoff_{schoollevel}_{subject}"
 * instead: grades where students WITH A RIMP do not take iReady lessons.
 * Set from the "iReady Lessons for RIMP Students" card.
 *
 * @param string $schoollevel 'es', 'ms', or 'hs'.
 * @param string $subject     'ela' or 'math'.
 * @param bool   $rimponly    True to read the RIMP-students setting.
 * @return array Map of [grade => grade] for quick isset() checks.
 */
function stv_get_lessons_off_grades($schoollevel, $subject, $rimponly = false) {
    $prefix = $rimponly ? 'rimplessonsoff_' : 'lessonsoff_';
    $raw = (string) get_config('mod_mtssdashboard', $prefix . $schoollevel . '_' . $subject);
    $grades = [];
    foreach (explode(',', $raw) as $g) {
        $g = trim($g);
        if ($g !== '' && is_numeric($g)) {
            $grades[(int) $g] = (int) $g;
        }
    }
    return $grades;
}

/**
 * Blank the "Days Since ELA/Math Lesson" metrics for a student who does not
 * take iReady lessons in that subject. A NULL metric displays as "—" and is
 * skipped by the Overall Risk flag count.
 *
 * A subject is turned off when either:
 *  - the student's grade is checked on the "iReady Lessons by Grade" card, or
 *  - the student has a RIMP and the grade is checked on the
 *    "iReady Lessons for RIMP Students" card.
 *
 * @param array  $metrics     Early Warning metrics for one student.
 * @param string $gradelevel  GradeLevelCode, e.g. '07'.
 * @param string $schoollevel 'es', 'ms', or 'hs'.
 * @param bool   $hasrimp     True if the student has any RIMP record.
 * @return array The metrics with lesson-day values blanked where turned off.
 */
function stv_apply_lessons_off(array $metrics, $gradelevel, $schoollevel, $hasrimp = false) {
    $grade = mtssdashboard_grade_to_int((string) $gradelevel);
    if ($grade === null) {
        return $metrics;
    }
    foreach (['ela' => 'iready_ela_days', 'math' => 'iready_math_days'] as $subject => $key) {
        if (!array_key_exists($key, $metrics)) {
            continue;
        }
        $off = stv_get_lessons_off_grades($schoollevel, $subject);
        if (isset($off[$grade])) {
            $metrics[$key] = null;
            continue;
        }
        if ($hasrimp) {
            $rimpoff = stv_get_lessons_off_grades($schoollevel, $subject, true);
            if (isset($rimpoff[$grade])) {
                $metrics[$key] = null;
            }
        }
    }
    return $metrics;
}

/**
 * Work out the school name to print on a RIMP or WEP from the student's
 * CURRENT grade (contacts.GradeLevelCode), using the grade ranges in the
 * plugin settings. The import tables hold the school from when the plan
 * was imported, which goes stale once a student moves up a building.
 *
 * @param string $gradecode Current GradeLevelCode, e.g. '09', 'KG'.
 * @param string $fallback  Name to use when the grade is unknown.
 * @return string School name.
 */
function mtssdashboard_school_name_for_grade($gradecode, $fallback) {
    $grade = mtssdashboard_grade_to_int((string) $gradecode);
    if ($grade === null) {
        return $fallback;
    }
    $levelcfg = mtssdashboard_get_level_config();
    if ($grade >= $levelcfg->hs_min) {
        return get_string('school_name_hs', 'mod_mtssdashboard');
    }
    if ($grade >= $levelcfg->ms_min) {
        return get_string('school_name_ms', 'mod_mtssdashboard');
    }
    return get_string('school_name_es', 'mod_mtssdashboard');
}

/**
 * Clean up a school name string for display on the RIMP report (strip a
 * leading "NT " prefix, title-case an all-caps value).
 *
 * @param string $raw Raw school name from the import table.
 * @return string Cleaned school name.
 */
function mtssdashboard_rimp_clean_school($raw) {
    $name = trim((string)$raw);
    if (stripos($name, 'NT ') === 0) {
        $name = substr($name, 3);
    }
    if ($name === strtoupper($name)) {
        $name = ucwords(strtolower($name));
    }
    return $name;
}

/**
 * Render a single checked-item <li> for the RIMP report, or an empty
 * string if that item isn't checked.
 *
 * @param bool   $checked Whether this item is checked.
 * @param string $label   Item label.
 * @param string $desc    Optional description shown after the label.
 * @return string HTML <li> markup, or '' if not checked.
 */
function mtssdashboard_rimp_cb($checked, $label, $desc = '') {
    if (!$checked) {
        return '';
    }
    $out = '<li><strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong>';
    if ($desc) {
        $out .= ': ' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
    }
    $out .= '</li>';
    return $out;
}

/**
 * Render the full RIMP report body (HTML) for a given student and echo it
 * directly, exactly as rimp_print.php used to do inline.
 *
 * Extracted out of rimp_print.php so both the staff-facing script and a
 * parent-facing script (block_parentgrades) can reuse the exact same
 * report-building logic after each does its own separate
 * authentication/authorization check.
 *
 * @param int    $studentnumber The student's StudentNumber (contacts.StudentNumber / user.idnumber).
 * @param string $schoollevel   'hs', 'ms', or 'es'.
 * @return void
 */
function mtssdashboard_render_rimp_report($studentnumber, $schoollevel) {
    global $DB, $CFG;

    $student = $DB->get_record_select(
        'user',
        "idnumber = :sn AND deleted = 0",
        ['sn' => $studentnumber],
        'id, firstname, middlename, lastname, picture, imagealt, email'
    );
    if (!$student) {
        throw new moodle_exception('invalidstudent', 'mod_mtssdashboard');
    }

    $rimp = $DB->get_record_select(
        'mtssdashboard_rimp',
        'studentnumber = :sn',
        ['sn' => $studentnumber]
    );
    if (!$rimp) {
        throw new moodle_exception('rimp_not_found', 'mod_mtssdashboard');
    }

    $contactstable = $CFG->prefix . 'contacts';
    $contact = $DB->get_record_sql(
        "SELECT c.GradeLevelCode AS gradelevel, c.Gender AS gender,
                c.Birthdate AS birthdate, c.StateStudentID AS ssid
           FROM {$contactstable} c WHERE c.StudentNumber = :sn",
        ['sn' => $studentnumber]
    );

    $import = $DB->get_record(
        'mtssdashboard_rimp_import',
        ['studentnumber' => $studentnumber, 'schoollevel' => $schoollevel]
    );

    // Decode JSON.
    $goals    = !empty($rimp->goals) ? json_decode($rimp->goals, true) : [];
    $supports = !empty($rimp->supports) ? json_decode($rimp->supports, true) : [];
    if (!is_array($goals)) {
        $goals    = [];
    }
    if (!is_array($supports)) {
        $supports = [];
    }

    $schoolname = mtssdashboard_school_name_for_grade(
        $contact->gradelevel ?? '',
        mtssdashboard_rimp_clean_school(
            !empty($import->current_school) ? $import->current_school : 'National Trail Local Schools'
        )
    );

    $currentgrade = !empty($contact->gradelevel) ? $contact->gradelevel
        : (!empty($import->current_grade) ? $import->current_grade : '');

    $genderraw    = !empty($import->gender) ? $import->gender
        : (!empty($contact->gender) ? $contact->gender : '');
    $genderdisplay = ($genderraw === 'M' || strtolower($genderraw) === 'male') ? 'Male'
        : (($genderraw === 'F' || strtolower($genderraw) === 'female') ? 'Female' : $genderraw);

    $birthdate    = !empty($contact->birthdate) ? $contact->birthdate : '';
    $effdatestr = !empty($rimp->rimp_effective_date) ? date('m/d/Y', $rimp->rimp_effective_date) : '';

    $statustext  = ((int)$rimp->status === 0) ? 'DRAFT' : '';
    $statuscolor = '#856404';
    $statusbg    = '#fff3cd';

    $subtitle = ($schoollevel === 'ms' || $schoollevel === 'hs')
        ? 'Ohio (RIMP) Reading Improvement Monitoring Plan — MS/HS'
        : 'Ohio (RIMP) Reading Improvement Monitoring Plan';

    $usercontext = context_user::instance($student->id);
    $picurl = !empty($student->picture)
        ? $CFG->wwwroot . '/pluginfile.php/' . $usercontext->id
            . '/user/icon/boost/f1?rev=' . $student->picture
        : $CFG->wwwroot . '/pix/u/f1.png';

    // Goal definitions — same for all levels.
    $goaldefs = [
        'phonemic'      => ['label' => 'Phonemic Awareness',
            'desc' => 'Noticing, thinking about and working with the smallest units of spoken language, which are ' .
                'called phonemes. For example, young children hear "dog" but not "duh"-"aw"-"guh".'],
        'phonics'       => ['label' => 'Phonics',
            'desc' => 'Knowing relationships between sounds (phonemes) and letters (graphemes). For example, when a ' .
                'child is taught the sounds for the letters t, p, a and c, they can start to build up words like: ' .
                '"tap", "pat", and "cat".'],
        'vocabulary'    => ['label' => 'Vocabulary',
            'desc' => 'Understanding the meaning of words we speak, hear, read, and write.'],
        'fluency'       => ['label' => 'Reading Fluency',
            'desc' => 'Reading connected text accurately, fluently, and for meaning.'],
        'comprehension' => ['label' => 'Reading Comprehension',
            'desc' => 'Gaining meaning from text.'],
    ];

    // Support definitions — same for all levels.
    $supportdefs = [
        'writing_found'   => 'Explicit Intervention in Writing (Foundational/Transcription)',
        'writing_comp'    => 'Explicit Intervention in Writing (Composition Skills)',
        'phon_awareness'  => 'Explicit Intervention in Phonemic Awareness',
        'high_freq_words' => 'Explicit Intervention in High Frequency Word Recognition',
        'decoding'        => 'Explicit Intervention in Decoding',
        'comprehension'   => 'Explicit Intervention in Comprehension',
        'fluency'         => 'Explicit Intervention in Fluency',
        'vocabulary'      => 'Explicit Intervention in Vocabulary',
        'scaffolding'     => 'Small Group Scaffolding of Complex Text',
        'communication'   => 'Explicit Intervention in Communication/Language',
        'multimodal'      => 'Multi-Modal Approach to Structured Literacy',
    ];

    $monitoredoptions = ['Classroom Teacher', 'Title 1 Teacher', 'Intervention Specialist', 'Other'];

    // MS monitoring checkboxes.
    $moncheckoptions = [
        'UFLR- after every two lessons',
        'Phonics for Reading- a review at the end of each lesson and a check up at the end of each unit',
        'Rewards- a review after every two lessons and a check up at the end of each unit',
    ];
    $savedmonchecks = [];
    if (
        !empty($rimp->monitoring_assessment)
            && ($schoollevel === 'ms' || $schoollevel === 'hs')
    ) {
        $savedmonchecks = array_map('trim', explode("\n", $rimp->monitoring_assessment));
    }

    $grade3options = [
        'Not applicable at this time',
        'The student reached the promotion score of 700 on the Ohio State Test for grade 3 English language arts.',
        'The student reached the promotion score on an approved alternative reading assessment given by the district.',
        'A parent/guardian, in consultation with the school, can request that their child be promoted to fourth grade ' .
            'even if they do not reach the promotion score. In this case, the student will continue to be supported ' .
            'with a RIMP and provided with intensive instruction, until they are able to read at grade level.',
    ];

    header('Content-Type: text/html; charset=UTF-8');

    echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Ohio RIMP &mdash; ';
    echo htmlspecialchars(fullname($student), ENT_QUOTES, 'UTF-8');
    echo '</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 10.5pt; color: #000;
         background: #fff; padding: 1.5cm 2cm; max-width: 22cm; margin: 0 auto; }
  h1 { font-size: 13pt; text-align: center; font-weight: bold; margin-bottom: 0.2em; }
  h2 { font-size: 11pt; text-align: center; font-weight: bold; margin-bottom: 0.8em; }
  h3 { font-size: 10.5pt; font-weight: bold; margin: 0.8em 0 0.3em; }
  .print-btn { display: block; text-align: center; margin: 0 auto 1em;
               padding: 0.5em 2em; background: #0f6cbf; color: #fff;
               border: none; cursor: pointer; font-size: 11pt; border-radius: 4px; }
  .student-header { display: flex; align-items: center; gap: 1em;
                    border: 1px solid #000; padding: 0.5em; margin-bottom: 0.5em; }
  .student-header img { width: 70px; height: 70px; object-fit: cover; border: 1px solid #ccc; }
  .student-header .name  { font-size: 12pt; font-weight: bold; }
  .student-header .school { font-size: 10pt; }
  .status-badge { margin-left: auto; padding: 0.2em 0.8em; border: 1px solid #000;
                  font-weight: bold; font-size: 10pt;
                  background: ';
    echo $statusbg;
    echo '; color: ';
    echo $statuscolor;
    echo '; }
  .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 0.5em; }
  .meta-table th, .meta-table td { padding: 0.2em 0.5em; font-size: 10pt; }
  .meta-table th { font-weight: bold; width: 25%; }
  .section { border-top: 1.5px solid #000; margin-top: 0.7em; padding-top: 0.4em; }
  .intro-text { font-size: 9.5pt; margin: 0.4em 0; line-height: 1.4; }
  .assessment-box { background: #f0f0f0; font-size: 10.5pt; font-weight: bold;
                    padding: 0.3em 0.5em; margin: 0.4em 0; }
  .bench-table { width: 100%; font-size: 9.5pt; border-collapse: collapse; margin: 0.3em 0; }
  .bench-table td { padding: 0.1em 0.4em; }
  .bench-table td:first-child { font-weight: bold; width: 35%; }
  .cb-table { width: 100%; border-collapse: collapse; margin: 0.3em 0; }
  .cb-table td { padding: 0.15em 0.3em; vertical-align: top; font-size: 10pt; }
  .cb-cell { width: 1.5em; text-align: center; font-size: 12pt; }
  .sig-wrap { display: flex; gap: 1em; margin-top: 0.5em; }
  .sig-block { flex: 1; }
  .sig-label { font-weight: bold; font-size: 10pt; }
  .sig-line { border-bottom: 1px solid #000; min-height: 2.5em;
              padding: 0.2em 0.2em 0; font-size: 10pt; white-space: pre-wrap; margin-top: 0.2em; }
  .doc-footer { margin-top: 1em; font-size: 8.5pt; color: #555;
                border-top: 1px solid #ccc; padding-top: 0.3em; text-align: center; }
  @media print { .print-btn { display: none; } body { padding: 0.5cm 1cm; } }
</style>
</head>
<body>

<button class="print-btn" onclick="window.print()">Print / Save as PDF</button>

<h1>';
    echo htmlspecialchars($schoolname, ENT_QUOTES, 'UTF-8');
    echo '</h1>
<h2>';
    echo htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8');
    echo '</h2>

<div class="student-header">
  <img src="';
    echo s($picurl);
    echo '" alt="';
    echo s(fullname($student));
    echo '"
       onerror="this.src=\'';
    echo s($CFG->wwwroot . '/pix/u/f1.png');
    echo '\'">
  <div>
    <div class="name">';
    echo s(fullname($student));
    echo '</div>
    <div class="school">';
    echo s($schoolname);
    echo '</div>
  </div>
  ';
    if ($statustext !== '') :
        echo '  <div class="status-badge">';
        echo s($statustext);
        echo '</div>
  ';
    endif;
    echo '</div>

<table class="meta-table">
  <tr>
    <th>Student ID</th>   <td>';
    echo s((string)$studentnumber);
    echo '</td>
    <th>Date of Birth</th><td>';
    echo s($birthdate);
    echo '</td>
  </tr>
  <tr>
    <th>Current Grade</th><td>';
    echo s((string)$currentgrade);
    echo '</td>
    <th>Gender</th>        <td>';
    echo s($genderdisplay);
    echo '</td>
  </tr>
</table>

<div class="section">
  <h3>What is a Reading Improvement and Monitoring Plan?</h3>
  <p class="intro-text">This plan allows us as teachers and parents to work together to understand the ' .
        'student&rsquo;s reading difficulties and outline reading instruction and support. The instructional services ' .
        'selected for a student rely on the student&rsquo;s data, and ideas and resources from the student&rsquo;s ' .
        'teacher and parent or guardian.</p>
  <p class="intro-text">Research shows that a child&rsquo;s reading abilities in the early grades is a strong ' .
        'predictor of reading comprehension in later grades (Cunningham &amp; Stanovich, 1997; Duncan et al., 2007; ' .
        'Stanley et al., 2018; Storch &amp; Whitehurst, 2002) and that children with early reading difficulties may ' .
        'continue to experience increasing difficulties over time if not addressed (Morgan et al., 2008; Morgan et ' .
        'al., 2011; Partanen &amp; Siegel, 2014).</p>
  <p class="intro-text">Under Ohio law (ORC 3313.608), districts and schools must create a Reading Improvement and ' .
        'Monitoring Plan (RIMP) for a student who is not on-track (reading below grade level) within 60 days of ' .
        'receiving the reading diagnostic results. The district must involve the student&rsquo;s parent or guardian ' .
        'and the classroom teacher in developing the plan.</p>
  <p class="intro-text">For students in grade 4 and beyond, districts and schools are required to develop and ' .
        'implement RIMPs for the following students:</p>
  <p class="intro-text">1) Students who did not meet the promotion score on Ohio&rsquo;s State Test for grade 3 ' .
        'English language arts but the student&rsquo;s parent or guardian, in consultation with the student&rsquo;s ' .
        'reading teacher and principal, requested the student be promoted to grade 4. These same students must ' .
        'receive 90 minutes of daily reading instruction that includes intensive intervention.</p>
  ';
    if ($schoollevel === 'es') :
        echo '  <p class="intro-text">2) RIMPs must continue for students who had a RIMP in grade 3 and still are not ' .
            'reading at grade level (700 or higher on Ohio&rsquo;s State Test for grade 3 English language arts).</p>
  ';
    else :
        echo '  <p class="intro-text">2) RIMPs must continue for students who are not reading at grade level (700 or ' .
            'higher on Ohio&rsquo;s State Test for grade 3 and above English language arts).</p>
  ';
    endif;
    echo '  <p class="intro-text">The RIMP continues throughout the student&rsquo;s K-12 academic career until the ' .
        'student is reading on grade level (proficient on Ohio&rsquo;s State Test for English language arts).</p>
</div>

<div class="section">
  ';
    if ($schoollevel === 'es') :
        echo '  <div class="assessment-box">Assessment Data: RIMP placement in K-3 is determined by their performance ' .
            'on beginning of the year iReady diagnostic assessment. In grades 4-6 is determined by their performance ' .
            'on the ELA Ohio State Test at the end of the previous school year.</div>
  <table class="bench-table">
    <tr><td>Kindergarten:</td><td>';
        echo get_string('rimp_k_ontrack', 'mtssdashboard');
        echo '</td></tr>
    <tr><td>Grade 1:</td>     <td>';
        echo get_string('rimp_grade1_ontrack', 'mtssdashboard');
        echo '</td></tr>
    <tr><td>Grade 2:</td>     <td>';
        echo get_string('rimp_grade2_ontrack', 'mtssdashboard');
        echo '</td></tr>
    <tr><td>Grade 3:</td>     <td>';
        echo get_string('rimp_grade3_ontrack', 'mtssdashboard');
        echo '</td></tr>
    <tr><td>Grade 4:</td>     <td>Third Grade OST Reading Scale Score below 700</td></tr>
    <tr><td>Grade 5:</td>     <td>Fourth Grade OST Reading Scale Score below 700</td></tr>
    <tr><td>Grade 6:</td>     <td>Fifth Grade OST Reading Scale Score below 700</td></tr>
  </table>
  ';
    else :
        echo '  <div class="assessment-box">Assessment Data: In grades 4-6 is determined by their performance on the ' .
            'ELA Ohio State Test at the end of the previous school year.</div>
  <table class="bench-table">
    <tr><td>Grade 4:</td><td>Third Grade OST Reading Scale Score below 700</td></tr>
    <tr><td>Grade 5:</td><td>Fourth Grade OST Reading Scale Score below 700</td></tr>
    <tr><td>Grade 6:</td><td>Fifth Grade OST Reading Scale Score below 700</td></tr>
    <tr><td>Grade 7:</td><td>Sixth Grade OST Reading Scale Score below 700</td></tr>
    <tr><td>Grade 8:</td><td>Seventh Grade OST Reading Scale Score below 700</td></tr>
  </table>
  ';
    endif;
    echo '  <table style="width:100%; border-collapse:collapse; margin-top:0.4em; font-size:10pt;">
    <tr>
      <td style="white-space:nowrap; font-weight:bold; padding:0.2em 0.5em 0.2em 0; width:1%;">Student Score:</td>
      <td style="padding:0.2em 1.5em 0.2em 0;">';
    echo !is_null($rimp->student_score) ? s((string)$rimp->student_score) : '';
    echo '</td>
      <td style="white-space:nowrap; font-weight:bold; padding:0.2em 0.5em 0.2em 0; width:1%;">Student Assessment ' .
        'Status:</td>
      <td style="padding:0.2em 1.5em 0.2em 0;">';
    echo s($rimp->assessment_status ?? '');
    echo '</td>
      <td style="white-space:nowrap; font-weight:bold; padding:0.2em 0.5em 0.2em 0; width:1%;">RIMP Effective ' .
        'Date:</td>
      <td style="padding:0.2em 0;">';
    echo s($effdatestr);
    echo '</td>
    </tr>
  </table>
</div>

<div class="section">
  <h3>Services and Supports</h3>
  <p class="intro-text">Specific interventions and resources provided to help a student improve their reading skills. ' .
        'This aligns with the high dosage tutoring requirements from the state of Ohio.</p>

  <h3>This plan was created for this student with the goal of improving:</h3>
  <ul style="margin:0.3em 0 0.3em 1.5em; font-size:10pt;">
    ';
    foreach ($goaldefs as $key => $g) :
        echo '      ';
        echo mtssdashboard_rimp_cb(in_array($key, $goals), $g['label'], $g['desc']);
        echo '    ';
    endforeach;
    echo '  </ul>

  <h3>Instructional Supports:</h3>
  <ul style="margin:0.3em 0 0.3em 1.5em; font-size:10pt;">
    ';
    foreach ($supportdefs as $key => $label) :
        echo '      ';
        echo mtssdashboard_rimp_cb(in_array($key, $supports), $label);
        echo '    ';
    endforeach;
    echo '  </ul>

  <table style="width:100%; border-collapse:collapse; margin-top:0.4em; font-size:10pt;">
    <tr>
      <td style="white-space:nowrap; font-weight:bold; padding:0.2em 0.5em 0.2em 0; width:1%;">Number of Days Per ' .
        'Week:</td>
      <td style="padding:0.2em 2em 0.2em 0;">';
    echo s($rimp->days_per_week ?? '');
    echo '</td>
      <td style="white-space:nowrap; font-weight:bold; padding:0.2em 0.5em 0.2em 0; width:1%;">Number of Minutes Per ' .
        'Week:</td>
      <td style="padding:0.2em 0;">';
    echo s(!is_null($rimp->minutes_per_week) ? (string)$rimp->minutes_per_week : '');
    echo '</td>
    </tr>
  </table>
</div>

<div class="section">
  <h3>Plans for Monitoring Student Success</h3>
  ';
    if ($schoollevel === 'es') :
        echo '  <table class="meta-table">
    <tr><th>Assessment used for Progress Monitoring</th>
        <td>';
        echo nl2br(s($rimp->monitoring_assessment ?? ''));
        echo '</td></tr>
    <tr><th>Frequency of Monitoring:</th>
        <td>';
        echo s($rimp->monitoring_frequency ?? '');
        echo '</td></tr>
  </table>
  ';
    else :
        echo '  <h3>Assessment used for Progress Monitoring and Frequency:</h3>
  <ul style="margin:0.3em 0 0.3em 1.5em; font-size:10pt;">
    ';
        foreach ($moncheckoptions as $opt) :
            echo '      ';
            echo mtssdashboard_rimp_cb(in_array($opt, $savedmonchecks), $opt);
            echo '    ';
        endforeach;
        echo '  </ul>
  ';
    endif;
    echo '
  <p style="font-size:10pt; margin:0.3em 0;"><br><strong>Intervention Plan Monitored By:</strong>
  ';
    echo s($rimp->monitored_by ?? '');
    echo '</p>
</div>

';
    if ($schoollevel === 'es') :
        echo '<div class="section">
  <h3>Ohio&rsquo;s Third Grade Reading Guarantee (Third Grade Students Only)</h3>
  <p class="intro-text">The Third Grade Reading Guarantee requires Ohio&rsquo;s third grade students to demonstrate ' .
            'reading proficiency by the end of third grade.</p>
  <ul style="margin:0.3em 0 0.3em 1.5em; font-size:10pt;">
    ';
        foreach ($grade3options as $opt) :
            echo '      ';
            echo mtssdashboard_rimp_cb($rimp->grade3_guarantee === $opt, $opt);
            echo '    ';
        endforeach;
        echo '  </ul>
</div>
';
    endif;
    echo '
<div class="section">
  <h3>Signatures</h3>
  ';
    if ($schoollevel === 'es') :
        echo '  <div class="sig-wrap">
    <div class="sig-block">
      <div class="sig-label">Parent/Guardian: ___________________________</div>
      <div class="sig-line">';
        echo nl2br(s($rimp->sig_parent ?? ''));
        echo '</div>
    </div>
    <div class="sig-block">
      <div class="sig-label">Current Teacher: ___________________________</div>
      <div class="sig-line">';
        echo nl2br(s($rimp->sig_teacher ?? ''));
        echo '</div>
    </div>
  </div>
  ';
    else :
        echo '  <div class="sig-wrap">
    <div class="sig-block">
      <div class="sig-label">Parent/Guardian: ___________________________</div>
      <div class="sig-line">';
        echo nl2br(s($rimp->sig_parent ?? ''));
        echo '</div>
    </div>
  </div>
  <div class="sig-wrap" style="margin-top:0.5em;">
    <div class="sig-block">
      <div class="sig-label">English language arts Teacher: ___________________________</div>
      <div class="sig-line">';
        echo nl2br(s($rimp->sig_ela_teacher ?? ''));
        echo '</div>
    </div>
    <div class="sig-block">
      <div class="sig-label">Intervention/Title Teacher: ___________________________</div>
      <div class="sig-line">';
        echo nl2br(s($rimp->sig_intervention ?? ''));
        echo '</div>
    </div>
  </div>
  ';
    endif;
    echo '</div>

<div class="doc-footer">
  ';
    echo s(fullname($student));
    echo ' (';
    echo s((string)$studentnumber);
    echo ')
  &mdash; Generated ';
    echo date('m/d/Y');
    echo '  ';
    if ($statustext !== '') :
        echo '&mdash; ';
        echo s($statustext);
    endif;
    echo '</div>

<button class="print-btn" onclick="window.print()" style="margin-top:1em;">Print / Save as PDF</button>

</body>
</html>
';
}

/**
 * Decode a JSON-encoded array field from the WEP table, returning an
 * empty array for empty/invalid input.
 *
 * @param string|null $json Raw JSON string.
 * @return array Decoded array, or [] if empty/invalid.
 */
function mtssdashboard_wep_decode($json) {
    if (empty($json)) {
        return [];
    }
    $d = json_decode($json, true);
    return is_array($d) ? $d : [];
}

/**
 * Clean up a school name string for display on the WEP report (strip a
 * leading "NT " prefix, title-case an all-caps value).
 *
 * @param string $raw Raw school name from the import table.
 * @return string Cleaned school name.
 */
function mtssdashboard_wep_clean_school($raw) {
    $name = trim((string)$raw);
    if ($name === '') {
        return 'National Trail Local Schools';
    }
    if (stripos($name, 'NT ') === 0) {
        $name = substr($name, 3);
    }
    if ($name === strtoupper($name)) {
        $name = ucwords(strtolower($name));
    }
    return $name;
}

/**
 * Render only the checked items from a label set as WEP checkbox table rows.
 *
 * @param array $alllabels   Map of key => label for every possible option.
 * @param array $checkedkeys Keys that are actually checked.
 * @return string HTML <tr> rows, or a "None selected" row if none checked.
 */
function mtssdashboard_wep_checked_list($alllabels, $checkedkeys) {
    $out = '';
    foreach ($alllabels as $key => $label) {
        if (!in_array($key, $checkedkeys, true)) {
            continue;
        }
        $out .= '<tr><td class="cb-cell">&#9746;</td>'
              . '<td>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }
    return $out ?: '<tr><td colspan="2" style="color:#999;font-style:italic;">None selected</td></tr>';
}

/**
 * Render a labeled text field block for the WEP report.
 *
 * @param string $label Field label.
 * @param string $value Field value (may be empty).
 * @param int    $lines Approximate number of lines to reserve for the field.
 * @return string HTML markup for the field block.
 */
function mtssdashboard_wep_field($label, $value, $lines = 4) {
    $h   = ($lines * 1.5) . 'em';
    $out  = '<div class="field-block">';
    $out .= '<div class="field-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>';
    $out .= '<div class="field-value" style="min-height:' . $h . ';">';
    $out .= ($value !== '' && $value !== null)
        ? nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'))
        : '&nbsp;';
    $out .= '</div>';
    $out .= '<div class="field-line"></div>';
    $out .= '</div>';
    return $out;
}

/**
 * Render the full WEP report body (HTML) for a given student and echo it
 * directly, exactly as wep_print.php used to do inline.
 *
 * Extracted out of wep_print.php so both the staff-facing script and a
 * parent-facing script (block_parentgrades) can reuse the exact same
 * report-building logic after each does its own separate
 * authentication/authorization check.
 *
 * @param int    $studentnumber The student's StudentNumber (contacts.StudentNumber / user.idnumber).
 * @param string $schoollevel   'hs', 'ms', or 'es'.
 * @return void
 */
function mtssdashboard_render_wep_report($studentnumber, $schoollevel) {
    global $DB, $CFG;

    $student = $DB->get_record_select(
        'user',
        "idnumber = :sn AND deleted = 0",
        ['sn' => $studentnumber],
        'id, firstname, middlename, lastname, picture, imagealt, email'
    );
    if (!$student) {
        throw new moodle_exception('invalidstudent', 'mod_mtssdashboard');
    }

    $wep = $DB->get_record_select(
        'mtssdashboard_wep',
        'studentnumber = :sn',
        ['sn' => $studentnumber]
    );
    if (!$wep) {
        throw new moodle_exception('wep_not_found', 'mod_mtssdashboard');
    }

    // Fetch contact / import info.
    $contactstable = $CFG->prefix . 'contacts';
    $contact = $DB->get_record_sql(
        "SELECT c.GradeLevelCode AS gradelevel, c.Gender AS gender,
                c.Birthdate AS birthdate, c.StateStudentID AS ssid
           FROM {$contactstable} c WHERE c.StudentNumber = :sn",
        ['sn' => $studentnumber]
    );

    // Normalize schoollevel for import table lookup (stored as 'mshs' for ms and hs).
    $importlevel = ($schoollevel === 'es') ? 'es' : 'mshs';

    $import = $DB->get_record(
        'mtssdashboard_wep_import',
        ['studentnumber' => $studentnumber, 'schoollevel' => $importlevel]
    );

    // Decode JSON arrays.
    $idareas      = mtssdashboard_wep_decode($wep->identification_areas);
    $svcsetting   = mtssdashboard_wep_decode($wep->service_setting);
    $progmeasures = mtssdashboard_wep_decode($wep->progress_measures);
    $personnel     = mtssdashboard_wep_decode($wep->personnel);

    // Convenience: is an identification area checked?
    $idchecked = function ($key) use ($idareas) {
        return in_array($key, $idareas, true);
    };

    // Superior Cognitive / Creative → goals in ALL academic areas.
    $allareas = $idchecked('superior_cognitive') || $idchecked('superior_creative');
    $showreading  = $allareas || $idchecked('reading_writing');
    $showmath     = $allareas || $idchecked('mathematics');
    $showscience  = ($schoollevel !== 'es') && ($allareas || $idchecked('science'));
    $showss       = ($schoollevel !== 'es') && ($allareas || $idchecked('social_studies'));

    $schoolname = mtssdashboard_school_name_for_grade(
        $contact->gradelevel ?? '',
        mtssdashboard_wep_clean_school(
            !empty($import->current_school) ? $import->current_school : 'National Trail Local Schools'
        )
    );

    $currentgrade = !empty($contact->gradelevel) ? $contact->gradelevel
        : (!empty($import->current_grade) ? $import->current_grade : '');

    $genderraw     = !empty($import->gender) ? $import->gender
        : (!empty($contact->gender) ? $contact->gender : '');
    $genderdisplay = ($genderraw === 'M' || strtolower($genderraw) === 'male') ? 'Male'
        : (($genderraw === 'F' || strtolower($genderraw) === 'female') ? 'Female' : $genderraw);

    $birthdate       = !empty($contact->birthdate) ? $contact->birthdate : '';
    $reviewdatestr = !empty($wep->wep_review_date) ? date('m/d/Y', $wep->wep_review_date) : '';

    $statustext  = ((int)$wep->status === 0) ? 'DRAFT' : '';
    $statuscolor = '#856404';
    $statusbg    = '#fff3cd';

    $pagetitle = 'Written Education Plan';

    // Student photo URL.
    $usercontext = context_user::instance($student->id);
    $picurl = !empty($student->picture)
        ? $CFG->wwwroot . '/pluginfile.php/' . $usercontext->id
            . '/user/icon/boost/f1?rev=' . $student->picture
        : $CFG->wwwroot . '/pix/u/f1.png';

    // Option label maps.
    $idarealabels = [
        'superior_cognitive' => 'Superior Cognitive Ability',
        'superior_creative'  => 'Superior Creative Thinking Ability',
        'reading_writing'    => 'Reading/Writing',
        'mathematics'        => 'Mathematics',
        'science'            => 'Science',
        'social_studies'     => 'Social Studies',
    ];

    if ($schoollevel === 'es') {
        $svclabels = [
            'cluster_es' => 'Cluster Grouping (HIVE time)',
            'other'      => 'Other',
        ];
    } else {
        $svclabels = [
            'cluster_mshs'   => 'Cluster Grouping (205062)',
            'acceleration'   => 'Acceleration (205052)',
            'college_credit' => 'College Credit Plus (205070)',
            'other'          => 'Other',
        ];
    }

    $progresslabels = [
        'observations'  => 'Observations/ Checklists',
        'projects'      => 'Projects',
        'benchmarks'    => 'Benchmarks',
        'presentations' => 'Presentations',
        'assessments'   => 'Classroom Assessments',
        'other'         => 'Other',
    ];

    $personnellabels = [
        'gen_ed' => 'General Education Teacher',
    ];

    header('Content-Type: text/html; charset=UTF-8');

    echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>';
    echo htmlspecialchars($pagetitle, ENT_QUOTES, 'UTF-8');
    echo ' &mdash;
       ';
    echo htmlspecialchars(fullname($student), ENT_QUOTES, 'UTF-8');
    echo '</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 10.5pt; color: #000;
         background: #fff; padding: 1.5cm 2cm; max-width: 22cm; margin: 0 auto; }
  h1  { font-size: 13pt; text-align: center; font-weight: bold; margin-bottom: 0.2em; }
  h2  { font-size: 11pt; text-align: center; font-weight: bold; margin-bottom: 0.8em; }
  h3  { font-size: 10.5pt; font-weight: bold; margin: 0.8em 0 0.3em; }
  .print-btn { display: block; text-align: center; margin: 0 auto 1em;
               padding: 0.5em 2em; background: #0f6cbf; color: #fff;
               border: none; cursor: pointer; font-size: 11pt; border-radius: 4px; }
  /* Student header — matches RIMP style */
  .student-header { display: flex; align-items: center; gap: 1em;
                    border: 1px solid #000; padding: 0.5em; margin-bottom: 0.5em; }
  .student-header img { width: 70px; height: 70px; object-fit: cover; border: 1px solid #ccc; }
  .student-header .name   { font-size: 12pt; font-weight: bold; }
  .student-header .school { font-size: 10pt; }
  .status-badge { margin-left: auto; padding: 0.2em 0.8em; border: 1px solid #000;
                  font-weight: bold; font-size: 10pt;
                  background: ';
    echo $statusbg;
    echo '; color: ';
    echo $statuscolor;
    echo '; }
  .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 0.5em; font-size: 10pt; }
  .meta-table th { font-weight: bold; text-align: left; white-space: nowrap;
                   padding: 0.2em 0.4em; vertical-align: top; }
  .meta-table td { white-space: nowrap; padding: 0.2em 0.8em 0.2em 0.2em; vertical-align: top; }
  .meta-table th.label-wrap { white-space: normal; width: 11em; }
  /* Sections */
  .section { border-top: 1.5px solid #000; margin-top: 0.7em; padding-top: 0.5em; }
  .section-title { font-weight: bold; font-size: 10.5pt; margin-bottom: 0.3em; }
  /* Intro text — red matching original WEP */
  .intro-text   { font-size: 9.5pt; line-height: 1.45; margin: 0.4em 0; color: #b22222; }
  .intro-footer { font-size: 9.5pt; font-style: italic; margin: 0.4em 0; }
  /* Checkbox table — checked items only */
  .cb-table { width: 100%; border-collapse: collapse; margin: 0.2em 0; font-size: 10pt; }
  .cb-table td { padding: 0.15em 0.3em; vertical-align: middle; }
  .cb-cell { width: 1.6em; text-align: center; font-size: 13pt; }
  /* Subject heading */
  .subj-heading { font-weight: bold; font-size: 10.5pt; margin: 0.8em 0 0.2em;
                  color: #1a5276; border-bottom: 1px solid #aed6f1; padding-bottom: 0.1em; }
  /* Goal / progress text fields */
  .field-block  { margin: 0.4em 0; }
  .field-label  { font-weight: bold; font-size: 10pt; margin-bottom: 0.15em; }
  .field-value  { font-size: 10pt; padding: 0.15em 0; white-space: pre-wrap; line-height: 1.4; }
  .field-line   { border-bottom: 1px solid #aaa; margin-top: 0.1em; }
  /* Signatures */
  .sig-label { font-weight: bold; font-size: 10pt; }
  .sig-line  { border-bottom: 1px solid #000; min-height: 2.2em;
               padding: 0.2em 0.2em 0; font-size: 10pt;
               white-space: pre-wrap; margin-top: 0.2em; }
  /* Footer */
  .doc-footer { margin-top: 1em; font-size: 8.5pt; color: #555;
                border-top: 1px solid #ccc; padding-top: 0.3em; text-align: center; }
  @media print { .print-btn { display: none; } body { padding: 0.5cm 1cm; } }
</style>
</head>
<body>

<button class="print-btn" onclick="window.print()">Print / Save as PDF</button>

<!-- RIMP-style header: school name + subtitle -->
<h1>';
    echo htmlspecialchars($schoolname, ENT_QUOTES, 'UTF-8');
    echo '</h1>
<h2>';
    echo htmlspecialchars($pagetitle, ENT_QUOTES, 'UTF-8');
    echo '</h2>

<!-- Student card matching RIMP layout -->
<div class="student-header">
  <img src="';
    echo s($picurl);
    echo '"
       alt="';
    echo s(fullname($student));
    echo '"
       onerror="this.src=\'';
    echo s($CFG->wwwroot . '/pix/u/f1.png');
    echo '\'">
  <div>
    <div class="name">';
    echo htmlspecialchars(fullname($student), ENT_QUOTES, 'UTF-8');
    echo '</div>
    <div class="school">';
    echo htmlspecialchars($schoolname, ENT_QUOTES, 'UTF-8');
    echo '</div>
  </div>
  ';
    if ($statustext !== '') :
        echo '  <div class="status-badge">';
        echo htmlspecialchars($statustext, ENT_QUOTES, 'UTF-8');
        echo '</div>
  ';
    endif;
    echo '</div>

<table class="meta-table" style="margin-bottom:0.3em;">
  <tr>
    <th class="label-wrap">Student ID</th>
    <td>';
    echo htmlspecialchars((string)$studentnumber, ENT_QUOTES, 'UTF-8');
    echo '</td>
    <th>Date of Birth</th>
    <td>';
    echo htmlspecialchars($birthdate, ENT_QUOTES, 'UTF-8');
    echo '</td>
    <th>Current Grade</th>
    <td>';
    echo htmlspecialchars((string)$currentgrade, ENT_QUOTES, 'UTF-8');
    echo '</td>
    <th>Gender</th>
    <td>';
    echo htmlspecialchars($genderdisplay, ENT_QUOTES, 'UTF-8');
    echo '</td>
  </tr>
  <tr>
    <th class="label-wrap">';
    echo htmlspecialchars(get_string('wep_schoolyear', 'mod_mtssdashboard'), ENT_QUOTES, 'UTF-8');
    echo '</th>
    <td>';
    echo htmlspecialchars($wep->schoolyear ?? '', ENT_QUOTES, 'UTF-8');
    echo '</td>
    <th>';
    echo htmlspecialchars(get_string('wep_review_date', 'mod_mtssdashboard'), ENT_QUOTES, 'UTF-8');
    echo '</th>
    <td>';
    echo htmlspecialchars($reviewdatestr, ENT_QUOTES, 'UTF-8');
    echo '</td>
    <td colspan="4"></td>
  </tr>
</table>

<!-- Area(s) of Identification — checked items only -->
<div class="section">
  <div class="section-title">';
    echo htmlspecialchars(get_string('wep_section_identification', 'mod_mtssdashboard'), ENT_QUOTES, 'UTF-8');
    echo '</div>
  <table class="cb-table">
    ';
    echo mtssdashboard_wep_checked_list($idarealabels, $idareas);
    echo '  </table>
</div>

<!-- Intro text -->
<div class="section" style="border-top:none; margin-top:0.5em;">
  <p class="intro-text">';
    echo htmlspecialchars(get_string('wep_section_intro_text', 'mod_mtssdashboard'), ENT_QUOTES, 'UTF-8');
    echo '</p>
  <p class="intro-footer">';
    echo htmlspecialchars(get_string('wep_section_intro_footer', 'mod_mtssdashboard'), ENT_QUOTES, 'UTF-8');
    echo '</p>
</div>

<!-- Service Setting — checked items only -->
<div class="section">
  <div class="section-title">';
    echo htmlspecialchars(get_string('wep_section_service', 'mod_mtssdashboard'), ENT_QUOTES, 'UTF-8');
    echo '</div>
  <table class="cb-table">
    ';
    echo mtssdashboard_wep_checked_list($svclabels, $svcsetting);
    echo '  </table>
</div>

<!-- Student Progress Measures — checked items only -->
<div class="section">
  <div class="section-title">';
    echo htmlspecialchars(get_string('wep_section_progress_measures', 'mod_mtssdashboard'), ENT_QUOTES, 'UTF-8');
    echo '</div>
  <table class="cb-table">
    ';
    echo mtssdashboard_wep_checked_list($progresslabels, $progmeasures);
    echo '  </table>
</div>

<!-- Personnel — checked items only -->
<div class="section">
  <div class="section-title">';
    echo htmlspecialchars(get_string('wep_section_personnel', 'mod_mtssdashboard'), ENT_QUOTES, 'UTF-8');
    echo '</div>
  <table class="cb-table">
    ';
    echo mtssdashboard_wep_checked_list($personnellabels, $personnel);
    echo '  </table>
</div>

<!-- Goals and Progress — grouped by subject, shown only if area is checked -->
<div class="section">

  ';
    if ($showreading) :
        echo '    <div class="subj-heading">';
        echo htmlspecialchars(get_string('wep_id_reading_writing', 'mod_mtssdashboard'), ENT_QUOTES, 'UTF-8');
        echo '</div>
    ';
        echo mtssdashboard_wep_field(get_string('wep_goal_reading_writing', 'mod_mtssdashboard'), $wep->goal_reading_writing ?? '');
        echo '    ';
        echo mtssdashboard_wep_field(get_string('wep_midyear_reading', 'mod_mtssdashboard'), $wep->midyear_reading ?? '', 3);
        echo '    ';
        echo mtssdashboard_wep_field(get_string('wep_endofyear_reading', 'mod_mtssdashboard'), $wep->endofyear_reading ?? '', 3);
        echo '  ';
    endif;
    echo '
  ';
    if ($showmath) :
        echo '    <div class="subj-heading">';
        echo htmlspecialchars(get_string('wep_id_mathematics', 'mod_mtssdashboard'), ENT_QUOTES, 'UTF-8');
        echo '</div>
    ';
        echo mtssdashboard_wep_field(get_string('wep_goal_mathematics', 'mod_mtssdashboard'), $wep->goal_mathematics ?? '');
        echo '    ';
        echo mtssdashboard_wep_field(
            get_string('wep_midyear_mathematics', 'mod_mtssdashboard'),
            $wep->midyear_mathematics ?? '',
            3
        );
        echo '    ';
        echo mtssdashboard_wep_field(
            get_string('wep_endofyear_mathematics', 'mod_mtssdashboard'),
            $wep->endofyear_mathematics ?? '',
            3
        );
        echo '  ';
    endif;
    echo '
  ';
    if ($showscience) :
        echo '    <div class="subj-heading">';
        echo htmlspecialchars(get_string('wep_id_science', 'mod_mtssdashboard'), ENT_QUOTES, 'UTF-8');
        echo '</div>
    ';
        echo mtssdashboard_wep_field(get_string('wep_goal_science', 'mod_mtssdashboard'), $wep->goal_science ?? '');
        echo '    ';
        echo mtssdashboard_wep_field(get_string('wep_midyear_science', 'mod_mtssdashboard'), $wep->midyear_science ?? '', 3);
        echo '    ';
        echo mtssdashboard_wep_field(get_string('wep_endofyear_science', 'mod_mtssdashboard'), $wep->endofyear_science ?? '', 3);
        echo '  ';
    endif;
    echo '
  ';
    if ($showss) :
        echo '    <div class="subj-heading">';
        echo htmlspecialchars(get_string('wep_id_social_studies', 'mod_mtssdashboard'), ENT_QUOTES, 'UTF-8');
        echo '</div>
    ';
        echo mtssdashboard_wep_field(get_string('wep_goal_social_studies', 'mod_mtssdashboard'), $wep->goal_social_studies ?? '');
        echo '    ';
        echo mtssdashboard_wep_field(
            get_string('wep_midyear_social_studies', 'mod_mtssdashboard'),
            $wep->midyear_social_studies ?? '',
            3
        );
        echo '    ';
        echo mtssdashboard_wep_field(
            get_string('wep_endofyear_social_studies', 'mod_mtssdashboard'),
            $wep->endofyear_social_studies ?? '',
            3
        );
        echo '  ';
    endif;
    echo '
</div>

<!-- Parent Input -->
<div class="section">
  ';
    echo mtssdashboard_wep_field(get_string('wep_parent_input', 'mod_mtssdashboard'), $wep->parent_input ?? '', 4);
    echo '</div>

<!-- Signatures -->
<div class="section">
  <div class="section-title">';
    echo htmlspecialchars(get_string('wep_section_signatures', 'mod_mtssdashboard'), ENT_QUOTES, 'UTF-8');
    echo '</div>
  <div class="sig-label">';
    echo htmlspecialchars(get_string('wep_sig_parent', 'mod_mtssdashboard'), ENT_QUOTES, 'UTF-8');
    echo ': ___________________________</div>
  <div class="sig-line">';
    echo nl2br(htmlspecialchars($wep->sig_parent ?? '', ENT_QUOTES, 'UTF-8'));
    echo '</div>
</div>

<p style="font-size:9pt; color:#555; margin-top:1em;">
  ';
    echo htmlspecialchars(get_string('wep_sig_footer', 'mod_mtssdashboard'), ENT_QUOTES, 'UTF-8');
    echo '</p>

<div class="doc-footer">
  ';
    echo htmlspecialchars(fullname($student), ENT_QUOTES, 'UTF-8');
    echo '  (';
    echo htmlspecialchars((string)$studentnumber, ENT_QUOTES, 'UTF-8');
    echo ')
  &mdash; Generated ';
    echo date('m/d/Y');
    echo '  ';
    if ($statustext !== '') :
        echo '&mdash; ';
        echo htmlspecialchars($statustext, ENT_QUOTES, 'UTF-8');
    endif;
    echo '</div>

<button class="print-btn" onclick="window.print()" style="margin-top:1em;">Print / Save as PDF</button>

</body>
</html>
';
}

/**
 * Render the Score Trend Over Time and Subject Proficiency Profile charts
 * for a single student, given their StateStudentID (ssid), and echo them
 * directly along with the Chart.js <script> block that draws them.
 *
 * Extracted out of the mtssdashboard "Student Info" tab in view.php so both
 * the staff-facing tab and a parent-facing page (block_parentgrades) can
 * reuse the exact same chart-building logic. Callers must have already
 * loaded Chart.js via:
 *   $PAGE->requires->js('/mod/mtssdashboard/chartjs.umd.min.js', true);
 *   $PAGE->requires->js('/lib/amd/build/chartjs.min.js');
 *
 * @param int $ssid The student's StateStudentID (contacts.StateStudentID).
 * @return bool True if chart data was found and rendered, false if there
 *              was no test data (in which case only a "no data" notice
 *              was echoed — callers that want the original staff-tab
 *              behavior of stopping the page early on empty data should
 *              call $OUTPUT->footer() and exit themselves when this
 *              returns false).
 */
function mtssdashboard_render_student_charts($ssid) {
    global $DB, $CFG, $OUTPUT;

    // All years for trend (ignore any year filter, and DO NOT collapse to
    // most-recent per subject — every test appears as its own dot).
    $statetable = $CFG->prefix . 'NTstatetesting';
    $allresults = $DB->get_records_sql(
        "SELECT *
           FROM {$statetable}
          WHERE ssid = :ssid
            AND proficiency_level IS NOT NULL
            AND proficiency_level != ''
            AND proficiency_level != 'Untested'
          ORDER BY year ASC, subject_code ASC",
        ['ssid' => $ssid]
    );

    if (empty($allresults)) {
        echo $OUTPUT->notification(get_string('chart_nodata', 'mod_mtssdashboard'), 'notifyinfo');
        return false;
    }

    // Proficiency-band cutoffs (approximate Ohio breakpoints).
    // Used to position dots within their band on the trend chart so two
    // students at the same proficiency level but different scores show at
    // visually distinguishable heights. The exact ranges vary by subject and
    // grade in Ohio's system; these approximations are good enough for an
    // at-a-glance visual.
    $bandranges = [
        'Limited'      => ['min' => 640, 'max' => 680],
        'Basic'        => ['min' => 680, 'max' => 700],
        'Proficient'   => ['min' => 700, 'max' => 725],
        'Accomplished' => ['min' => 725, 'max' => 745],
        'Advanced'     => ['min' => 745, 'max' => 805],
    ];

    // Maps proficiency_level to its 1-5 base Y value (band floor).
    $proftoyfloor = [
        'Limited'      => 1.0,
        'Basic'        => 2.0,
        'Proficient'   => 3.0,
        'Accomplished' => 4.0,
        'Advanced'     => 5.0,
    ];

    // Build trend datasets: one line per subject AREA (4 lines total).
    // X-axis = unique years (sorted). Y-axis = proficiency level (1-5).
    // Each dot's Y is fine-tuned within its band by where the score falls
    // between the band's min and max in $cutoffs.
    $codetoarea = [
        'R'    => 'ela', 'ELA'  => 'ela', 'ELA2' => 'ela',
        'M'    => 'math', 'MTH1' => 'math', 'ALG1' => 'math', 'GEOM' => 'math',
        'S'    => 'sci', 'BIOL' => 'sci',
        'C'    => 'ss', 'HIST' => 'ss', 'GOVM' => 'ss',
    ];
    $arealabels = [
        'ela'  => 'English Language Arts',
        'math' => 'Mathematics',
        'sci'  => 'Science',
        'ss'   => 'Social Studies',
    ];
    $areacolors = [
        'ela'  => '#4e73df', // Blue.
        'math' => '#1cc88a', // Green.
        'sci'  => '#f6c23e', // Yellow.
        'ss'   => '#e74a3b', // Red.
    ];

    // X-axis: list of unique years the student has any test in, chronological.
    $yearset = [];
    foreach ($allresults as $r) {
        $yearset[$r->year] = true;
    }
    ksort($yearset);
    $assessmentlabels = array_keys($yearset);

    // Compute (year-index, Y, score) for every test, grouped by subject area.
    // Using {x: yearindex, y: Y} object form lets two tests share a year.
    $areapoints = [];
    foreach ($arealabels as $areakey => $unused) {
        $areapoints[$areakey] = [];
    }
    foreach ($allresults as $r) {
        $area = $codetoarea[$r->subject_code] ?? null;
        if ($area === null) {
            continue;
        }
        $yearidx = array_search($r->year, $assessmentlabels, true);
        if ($yearidx === false) {
            continue;
        }
        $pl = $r->proficiency_level ?? null;
        $yfloor = $proftoyfloor[$pl] ?? null;
        if ($yfloor === null) {
            continue; // Unknown proficiency level — skip.
        }
        // Fine-tune Y within its band based on score position between
        // approximate Ohio breakpoints, so dots within the same band spread
        // vertically by score.
        $y = $yfloor;
        $band = $bandranges[$pl] ?? null;
        if ($band !== null && $band['max'] > $band['min']) {
            $score = (float) $r->assessment_score;
            $frac = ($score - $band['min']) / ($band['max'] - $band['min']);
            // Clamp to [0, 0.99] so dots stay inside their band visually.
            $frac = max(0.0, min(0.99, $frac));
            $y = $yfloor + $frac;
        }
        $areapoints[$area][] = [
            'x'        => (int) $yearidx,
            'y'        => round($y, 3),
            'score'    => (int) $r->assessment_score,
            'profname' => $pl,
            'subject'  => $r->subject_description,
        ];
    }

    // Sort each area's points by year-index so the line draws left-to-right.
    foreach ($areapoints as &$pts) {
        usort($pts, function ($a, $b) {
            return $a['x'] - $b['x'];
        });
    }
    unset($pts);

    // Build one Chart.js dataset per area that actually has any points.
    $trendsets = [];
    foreach ($arealabels as $areakey => $arealabel) {
        if (empty($areapoints[$areakey])) {
            continue;
        }
        $color = $areacolors[$areakey];
        $trendsets[] = [
            'label'                => $arealabel,
            'data'                 => $areapoints[$areakey],
            'borderColor'          => $color,
            'backgroundColor'      => $color . '22',
            'pointBackgroundColor' => $color,
            'pointBorderColor'     => $color,
            'pointRadius'          => 7,
            'pointHoverRadius'     => 9,
            'tension'              => 0.3,
            'fill'                 => false,
            'spanGaps'             => true,
        ];
    }

    // Radar: one polygon per year, axes = subject AREA.
    // Each vertex value is the average proficiency-Y of that year/area's tests
    // (same 1-5 mapping used by the trend chart). When a year is missing data
    // for an area, the previous year's value is carried forward so the
    // polygon stays whole.
    $radaraxes  = ['ela', 'math', 'sci', 'ss'];
    $radaraxislabels = [
        'ela'  => 'English Language Arts',
        'math' => 'Mathematics',
        'sci'  => 'Science',
        'ss'   => 'Social Studies',
    ];

    // Collect Y values per (year, area). Same code_to_area, prof_to_yfloor,
    // bandranges defined earlier in this function.
    $radaryearareay = [];
    foreach ($allresults as $r) {
        $area = $codetoarea[$r->subject_code] ?? null;
        if ($area === null) {
            continue;
        }
        $pl = $r->proficiency_level ?? null;
        $yfloor = $proftoyfloor[$pl] ?? null;
        if ($yfloor === null) {
            continue;
        }
        $y = $yfloor;
        $band = $bandranges[$pl] ?? null;
        if ($band !== null && $band['max'] > $band['min']) {
            $score = (float) $r->assessment_score;
            $frac = ($score - $band['min']) / ($band['max'] - $band['min']);
            $frac = max(0.0, min(0.99, $frac));
            $y = $yfloor + $frac;
        }
        $radaryearareay[$r->year][$area][] = $y;
    }

    // Sort years chronologically (we already have $assessmentlabels = sorted years).
    $radaryears = $assessmentlabels;

    // Build per-year polygons. Carry-forward fills missing-area vertices.
    $radaryearcolors = [
        '#4e73df', '#1cc88a', '#f6c23e', '#e74a3b',
        '#36b9cc', '#858796', '#fd7e14',
    ];
    $radardatasets = [];
    $carry = ['ela' => null, 'math' => null, 'sci' => null, 'ss' => null];
    $ci = 0;
    foreach ($radaryears as $yr) {
        $vertex = [];
        foreach ($radaraxes as $area) {
            if (
                isset($radaryearareay[$yr][$area])
                    && !empty($radaryearareay[$yr][$area])
            ) {
                $vals = $radaryearareay[$yr][$area];
                $avg = array_sum($vals) / count($vals);
                $carry[$area] = $avg;
                $vertex[] = round($avg, 3);
            } else if ($carry[$area] !== null) {
                $vertex[] = round($carry[$area], 3);
            } else {
                // No prior value to carry forward; place at axis origin.
                $vertex[] = 0;
            }
        }
        $color = $radaryearcolors[$ci % count($radaryearcolors)];
        $radardatasets[] = [
            'label'                => $yr,
            'data'                 => $vertex,
            'backgroundColor'      => $color . '22',
            'borderColor'          => $color,
            'pointBackgroundColor' => $color,
            'pointRadius'          => 4,
            'fill'                 => true,
        ];
        $ci++;
    }

    // Variables consumed by the JS render block below.
    $radarlabelsarr = array_map(function ($a) use ($radaraxislabels) {
        return $radaraxislabels[$a];
    }, $radaraxes);

    // Charts.
    echo html_writer::start_div('row mb-3');

    // Trend line chart.
    echo html_writer::start_div('col-md-8');
    echo html_writer::tag(
        'h5',
        get_string('chart_trend', 'mod_mtssdashboard'),
        ['class' => 'text-center']
    );
    echo html_writer::start_div('', ['style' => 'position:relative; height:400px;']);
    echo html_writer::tag('canvas', '', ['id' => 'chartTrend']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Radar chart.
    echo html_writer::start_div('col-md-4');
    echo html_writer::tag(
        'h5',
        get_string('chart_radar', 'mod_mtssdashboard'),
        ['class' => 'text-center']
    );
    echo html_writer::tag('canvas', '', ['id' => 'chartRadar']);
    echo html_writer::end_div();

    echo html_writer::end_div();

    // Chart.js.
    $trendjson         = json_encode($trendsets);
    $yearlabels        = json_encode($assessmentlabels);
    $radarlabels       = json_encode($radarlabelsarr);
    $radardatasetsjson = json_encode($radardatasets);

    echo <<<JS
<script>
(function(){
    // Trend line chart.
    var ctx1 = document.getElementById('chartTrend').getContext('2d');
    var scorelabelPlugin = {
        id: 'studentscorelabels',
        afterDatasetsDraw: function(chart) {
            var ctx2 = chart.ctx;
            var chartRight = chart.chartArea.right;
            chart.data.datasets.forEach(function(ds, i) {
                var meta = chart.getDatasetMeta(i);
                if (meta.hidden) return;
                meta.data.forEach(function(point, j) {
                    var raw = ds.data[j];
                    if (!raw || raw.score === undefined) return;
                    ctx2.save();
                    ctx2.font = 'bold 11px sans-serif';
                    ctx2.fillStyle = '#333';
                    ctx2.textBaseline = 'middle';
                    var label = String(raw.score);
                    var labelWidth = ctx2.measureText(label).width;
                    // If label would overflow the right edge, draw it to the left of the dot.
                    if (point.x + 9 + labelWidth > chartRight) {
                        ctx2.textAlign = 'right';
                        ctx2.fillText(label, point.x - 9, point.y - 8);
                    } else {
                        ctx2.textAlign = 'left';
                        ctx2.fillText(label, point.x + 9, point.y - 8);
                    }
                    ctx2.restore();
                });
            });
        }
    };
    new Chart(ctx1, {
        type: 'line',
        data: { labels: {$yearlabels}, datasets: {$trendjson} },
        plugins: [scorelabelPlugin],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            spanGaps: true,
            parsing: false,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var d = ctx.raw || {};
                            return ctx.dataset.label + ' — ' +
                                   (d.subject || '') + ': ' +
                                   (d.profname || '') + ' (' + (d.score || ctx.parsed.y) + ')';
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'category',
                    title: { display: true, text: 'Year' }
                },
                y: {
                    min: 0.5,
                    max: 6.2,
                    ticks: {
                        stepSize: 1,
                        font: { size: 11 },
                        callback: function(val) {
                            var labels = ['','Limited','Basic','Proficient','Accomplished','Advanced'];
                            // Hide ticks above Advanced row (5).
                            var rounded = Math.round(val);
                            return labels[rounded] || '';
                        }
                    },
                    title: { display: false }
                }
            }
        }
    });

    // Radar chart — one polygon per year.
    var ctx2 = document.getElementById('chartRadar').getContext('2d');
    new Chart(ctx2, {
        type: 'radar',
        data: {
            labels: {$radarlabels},
            datasets: {$radardatasetsjson}
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var labels = ['','Limited','Basic','Proficient','Accomplished','Advanced'];
                            var lbl = labels[Math.round(ctx.parsed.r)] || '';
                            return ctx.dataset.label + ' — ' + ctx.label + ': ' + lbl;
                        }
                    }
                }
            },
            scales: {
                r: {
                    min: 0,
                    max: 6,
                    ticks: {
                        stepSize: 1,
                        backdropColor: 'transparent',
                        callback: function(val) {
                            var labels = ['','Limited','Basic','Proficient','Accomplished','Advanced'];
                            return labels[Math.round(val)] || '';
                        }
                    },
                    pointLabels: { font: { size: 11 } }
                }
            }
        }
    });
})();
</script>
JS;

    return true;
}

/**
 * Get the currently active edit lock for a student's RIMP or WEP record, if any.
 *
 * A lock is "active" if its heartbeat is within {@see MTSSDASHBOARD_EDIT_LOCK_TIMEOUT}
 * seconds. Expired locks are cleaned up (deleted) as a side effect of calling this,
 * so callers never need to worry about stale rows from crashed tabs.
 *
 * @param int $studentnumber District student ID (idnumber from mdl_user).
 * @param string $recordtype 'rimp' or 'wep'.
 * @return stdClass|false The lock record (id, studentnumber, recordtype, userid,
 *                         heartbeat, timecreated) if actively held, or false if free.
 */
function stv_get_edit_lock($studentnumber, $recordtype) {
    global $DB;

    $lock = $DB->get_record(
        'mtssdashboard_edit_sessions',
        ['studentnumber' => $studentnumber, 'recordtype' => $recordtype]
    );

    if (!$lock) {
        return false;
    }

    if ($lock->heartbeat < (time() - MTSSDASHBOARD_EDIT_LOCK_TIMEOUT)) {
        // Abandoned lock (crashed tab, lost connection, etc.) — clear it out.
        $DB->delete_records('mtssdashboard_edit_sessions', ['id' => $lock->id]);
        return false;
    }

    return $lock;
}

/**
 * Acquire (or refresh) the edit lock for a student's RIMP or WEP record.
 *
 * Only succeeds if the record is unlocked or already locked by this same user.
 * Refreshing an already-held lock is what keeps it alive across a heartbeat ping.
 *
 * @param int $studentnumber District student ID.
 * @param string $recordtype 'rimp' or 'wep'.
 * @param int $userid The user requesting the lock.
 * @return bool True if the lock is held by $userid after this call, false if it's
 *              actively held by someone else.
 */
function stv_acquire_edit_lock($studentnumber, $recordtype, $userid) {
    global $DB;

    $existing = stv_get_edit_lock($studentnumber, $recordtype);
    $now = time();

    if ($existing) {
        if ((int) $existing->userid !== (int) $userid) {
            // Actively held by someone else — cannot acquire.
            return false;
        }
        // Already ours — just refresh the heartbeat.
        $existing->heartbeat = $now;
        $DB->update_record('mtssdashboard_edit_sessions', $existing);
        return true;
    }

    $record = (object) [
        'studentnumber' => $studentnumber,
        'recordtype'    => $recordtype,
        'userid'        => $userid,
        'heartbeat'     => $now,
        'timecreated'   => $now,
    ];
    $DB->insert_record('mtssdashboard_edit_sessions', $record);
    return true;
}

/**
 * Release the edit lock for a student's RIMP or WEP record, if held by this user.
 *
 * Safe to call even if no lock exists or it's held by someone else — in either
 * case nothing is deleted.
 *
 * @param int $studentnumber District student ID.
 * @param string $recordtype 'rimp' or 'wep'.
 * @param int $userid The user releasing the lock.
 * @return void
 */
function stv_release_edit_lock($studentnumber, $recordtype, $userid) {
    global $DB;

    $DB->delete_records('mtssdashboard_edit_sessions', [
        'studentnumber' => $studentnumber,
        'recordtype'    => $recordtype,
        'userid'        => $userid,
    ]);
}

/**
 * Render many finalized RIMP or WEP reports as one print-ready HTML page.
 *
 * Each report is built by the existing single-student renderer
 * (mtssdashboard_render_rimp_report() / mtssdashboard_render_wep_report())
 * so the bulk output always matches the single-student printout. The
 * renderer output is captured, its stylesheet is kept once, and each
 * report body is placed in its own block that starts on a new printed
 * page. The browser's print dialog opens on load so staff can choose
 * "Save as PDF" and upload the single file to ParentSquare.
 *
 * Students whose report cannot be built (no user account, no record) are
 * skipped silently so one bad record does not stop the whole batch.
 *
 * @param string $type           'rimp' or 'wep'.
 * @param array  $studentnumbers Student numbers to include, in print order.
 * @param string $schoollevel    'hs', 'ms', or 'es'.
 * @param string $doctitle       Document title (browser default PDF file name).
 * @return void
 */
function mtssdashboard_render_bulk_reports($type, array $studentnumbers, $schoollevel, $doctitle) {
    $renderer = ($type === 'rimp') ? 'mtssdashboard_render_rimp_report'
                                   : 'mtssdashboard_render_wep_report';

    $style  = '';
    $bodies = [];
    foreach ($studentnumbers as $sn) {
        ob_start();
        try {
            $renderer((int) $sn, $schoollevel);
        } catch (moodle_exception $e) {
            ob_end_clean();
            continue;
        }
        $html = ob_get_clean();

        if ($style === '' && preg_match('~<style>(.*?)</style>~s', $html, $m)) {
            $style = $m[1];
        }
        if (preg_match('~<body>(.*)</body>~s', $html, $m)) {
            // Drop the per-report Print buttons; the bulk page has its own.
            $bodies[] = preg_replace('~<button class="print-btn"[^>]*>.*?</button>~s', '', $m[1]);
        }
    }

    $count = count($bodies);
    $a = (object) ['count' => $count];

    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html>' . "\n";
    echo '<html lang="en">' . "\n";
    echo '<head>' . "\n";
    echo '<meta charset="UTF-8">' . "\n";
    echo '<title>' . s($doctitle) . '</title>' . "\n";
    echo '<style>' . $style . "\n";
    echo '  .bulk-toolbar { text-align: center; margin: 0 auto 1.5em; }' . "\n";
    echo '  .bulk-toolbar p { margin-bottom: 0.5em; font-size: 11pt; }' . "\n";
    echo '  .bulk-report + .bulk-report { break-before: page; page-break-before: always; }' . "\n";
    echo '  @media screen { .bulk-report + .bulk-report { border-top: 3px dashed #999;' .
         ' margin-top: 2em; padding-top: 2em; } }' . "\n";
    echo '  @media print { .bulk-toolbar { display: none; } }' . "\n";
    echo '</style>' . "\n";
    echo '</head>' . "\n";
    echo '<body>' . "\n";

    echo '<div class="bulk-toolbar">';
    if ($count === 0) {
        echo '<p>' . s(get_string($type . '_bulk_none', 'mod_mtssdashboard')) . '</p>';
    } else {
        echo '<p>' . s(get_string($type . '_bulk_count', 'mod_mtssdashboard', $a)) . '</p>';
        echo '<button class="print-btn" type="button" onclick="window.print()">' .
             s(get_string('bulk_btn_print', 'mod_mtssdashboard')) . '</button>';
    }
    echo '</div>' . "\n";

    foreach ($bodies as $body) {
        echo '<div class="bulk-report">' . $body . '</div>' . "\n";
    }

    if ($count > 0) {
        // Open the print dialog once every student photo has loaded.
        echo '<script>window.addEventListener("load", function() { window.print(); });</script>' . "\n";
    }
    echo '</body>' . "\n";
    echo '</html>' . "\n";
}
