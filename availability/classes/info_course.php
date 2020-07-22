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
 * Class handles conditional availability information for a course.
 *
 * @package core_availability
 * @copyright 2020 Alexandre Paes Rigão
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_availability;

defined('MOODLE_INTERNAL') || die();

/**
 * Class handles conditional availability information for a course.
 *
 * @package core_availability
 * @copyright 2020 Alexandre Paes Rigão
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class info_course extends info {
    protected $course;

    /**
     * Constructs with item details.
     *
     * @param \section_info $section Section object
     */
    public function __construct($course, $availability, $visible = false) {
        parent::__construct($course, $visible, $availability);
    }

    protected function get_thing_name() {
        // This may be used in error messages etc. You would probably use
        // the name of the thing you're controlling availability for.
        return 'Special thing within module';
    }

    public function get_context() {
        return \context_course::instance($this->get_course()->id);
    }

    protected function get_view_hidden_capability() {
        return 'moodle/course:ignoreavailabilityrestrictions';
    }

    protected function set_in_database($availability) {
        // This function should save the availability settings back to database.
        // It's needed if doing an update after restore, so you do need to
        // implement it.
    }

}
