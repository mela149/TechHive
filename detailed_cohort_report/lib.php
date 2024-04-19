<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * 
 *
 * @package     local_cohortreport
 * @copyright   2023 Talha Mela <talhamela20000@hotmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
* Insert a link to index.php on the site front page navigation menu.
*
* @param navigation_node $frontpage Node representing the front page in the navigation tree.
*/
function local_detailed_cohort_report_extend_navigation_frontpage(navigation_node $frontpage) {
    if (has_capability('moodle/analytics:managemodels', context_system::instance())) { // moodle/analytics:managemodels permission used to determine if report link will be on homepage of user
        $frontpage->add(
            get_string('pluginname', 'local_detailed_cohort_report'),
            new moodle_url('/local/detailed_cohort_report/index.php')
        );
    }
}
