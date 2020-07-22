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
 * This file contains the availability criteria type class and any
 * supporting functions it may require.
 *
 * @package core_completion
 * @category completion
 * @copyright 2020 Alexandre Paes Rigão
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Course completion critieria - completion on availability
 *
 * @package core_completion
 * @category completion
 * @copyright 2020 Alexandre Paes Rigão
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_criteria_availability extends completion_criteria {

    /* @var int Criteria [COMPLETION_CRITERIA_TYPE_AVAILABILITY] */
    public $criteriatype = COMPLETION_CRITERIA_TYPE_AVAILABILITY;

    /**
     * Finds and returns a data_object instance based on params.
     *
     * @param array $params associative arrays varname=>value
     * @return completion_criteria_availability data_object instance or false if none found.
     */
    public static function fetch($params) {
        $params['criteriatype'] = COMPLETION_CRITERIA_TYPE_AVAILABILITY;
        return self::fetch_helper('course_completion_criteria', __CLASS__, $params);
    }

    /**
     * Add appropriate form elements to the critieria form
     *
     * @param moodleform $mform  Moodle forms object
     * @param stdClass $data details of various modules
     */
    public function config_form_display(&$mform, $data = null) {
        $mform->addElement('textarea', 'availabilityconditionsjson', get_string('accessrestrictions', 'availability'));

        if ($this->id) {
            $mform->setDefault('availabilityconditionsjson', $this->availability);
        }

        \core_availability\frontend::include_all_javascript($data->course);
    }

    /**
     * Update the criteria information stored in the database
     *
     * @param stdClass $data Form data
     */
    public function update_config(&$data) {
        $errors = [];
        \core_availability\frontend::report_validation_errors((array)$data, $errors);

        if ('{"op":"&","c":[],"showc":[]}' == $data->availabilityconditionsjson) {
            $data->availabilityconditionsjson = null;
        }

        if (empty($errors) && !empty($data->availabilityconditionsjson)) {
            $this->course = $data->id;
            $this->availability = $data->availabilityconditionsjson;
            $this->insert();
        }
    }

    /**
     * Review this criteria and decide if the user has completed
     *
     * @param completion_completion $completion     The user's completion record
     * @param bool $mark Optionally set false to not save changes to database
     * @return bool
     */
    public function review($completion, $mark = true) {
        $info_coruse = new \core_availability\info_course($completion->course);
        $info = '';
        $is_available = $x->is_available($info, false, $completion->userid);

        if ($is_available) {
            if ($mark) {
                $completion->mark_complete();
            }

            return true;
        }

        return false;
    }

    /**
     * Return criteria title for display in reports
     *
     * @return string
     */
    public function get_title() {
        return get_string('accessrestrictions', 'availability');
    }

    /**
     * Return a more detailed criteria title for display in reports
     *
     * @return  string
     */
    public function get_title_detailed() {
        $course = (object)['id' => $this->course];

        $info_course = new \core_availability\info_course($course, $this->availability);

        return \core_availability\info_course::format_info($info_course->get_full_information(), $course);
    }

    /**
     * Return criteria type title for display in reports
     *
     * @return  string
     */
    public function get_type_title() {
        return get_string('restrictaccess', 'availability');
    }

    /**
     * Find users who have completed this criteria and mark them accordingly
     */
    public function cron() {
        global $DB;

        // Get all users who meet this criteria
        $sql = '
            SELECT DISTINCT
                cr.course AS course,
                cr.id AS criteriaid
            FROM
                {course_completion_criteria} cr
            INNER JOIN
                {course} c
             ON cr.course = c.id
            INNER JOIN
                {context} con
             ON con.instanceid = c.id
            INNER JOIN
                {role_assignments} ra
              ON ra.contextid = con.id
            LEFT JOIN
                {course_completion_crit_compl} cc
             ON cc.criteriaid = cr.id
            AND cc.userid = ra.userid
            WHERE
                cr.criteriatype = '.COMPLETION_CRITERIA_TYPE_AVAILABILITY.'
            AND con.contextlevel = '.CONTEXT_COURSE.'
            AND c.enablecompletion = 1
            AND cc.id IS NULL
        ';

        // Loop through completions, and mark as complete
        $rs = $DB->get_recordset_sql($sql);
        foreach ($rs as $record) {
            $course = new \stdClass();
            $course->id = $record->course;

            $context = get_context_instance(CONTEXT_COURSE, $course->id);
            $users = get_enrolled_users($context, '', 0, 'u.id');
            $info = new \core_availability\info_course($course, $this->availability);
            $filtered = $info->filter_user_list($users);

            foreach ($filtered as $user) {
                $completion = new completion_criteria_completion([
                    'userid' => $user->id,
                    'course' => $course->id,
                    'criteriaid' => $record->criteriaid,
                ], DATA_OBJECT_FETCH_BY_KEY);

                $completion->mark_complete(time() - 3000);
            }
        }
        $rs->close();
    }

    /**
     * Return criteria progress details for display in reports
     *
     * @param completion_completion $completion The user's completion record
     * @return array An array with the following keys:
     *     type, criteria, requirement, status
     */
    public function get_details($completion) {
        // Get completion info
        $modinfo = get_fast_modinfo($completion->course);
        $cm = $modinfo->get_cm($this->moduleinstance);

        $details = array();
        $details['type'] = $this->get_title();
        if ($cm->has_view()) {
            $details['criteria'] = html_writer::link($cm->url, $cm->get_formatted_name());
        } else {
            $details['criteria'] = $cm->get_formatted_name();
        }

        // Build requirements
        $details['requirement'] = array();

        if ($cm->completion == COMPLETION_TRACKING_MANUAL) {
            $details['requirement'][] = get_string('markingyourselfcomplete', 'completion');
        } elseif ($cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
            if ($cm->completionview) {
                $modulename = core_text::strtolower(get_string('modulename', $this->module));
                $details['requirement'][] = get_string('viewingactivity', 'completion', $modulename);
            }

            if (!is_null($cm->completiongradeitemnumber)) {
                $details['requirement'][] = get_string('achievinggrade', 'completion');
            }
        }

        $details['requirement'] = implode(', ', $details['requirement']);

        $details['status'] = '';

        return $details;
    }

    /**
     * Return pix_icon for display in reports.
     *
     * @param string $alt The alt text to use for the icon
     * @param array $attributes html attributes
     * @return pix_icon
     */
    public function get_icon($alt, array $attributes = null) {
        return new pix_icon('icon', $alt, 'mod_'.$this->module, $attributes);
    }
}
