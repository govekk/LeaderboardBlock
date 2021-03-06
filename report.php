<?php
// This file is part of leaderboard block for Moodle - http://moodle.org/
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
 * leaderboard block - report page
 *
 * @package    contrib
 * @subpackage block_ranking -> changed to block_leaderboard by Kiya Govek
 * @copyright  2015 Willian Mano http://willianmano.net
 * @authors    Willian Mano, edits by Kiya Govek
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->dirroot.'/blocks/leaderboard/lib.php');

define('DEFAULT_PAGE_SIZE', 100);

$courseid = required_param('courseid', PARAM_INT);
$perpage = optional_param('perpage', DEFAULT_PAGE_SIZE, PARAM_INT); // How many per page.
$group = optional_param('group', null, PARAM_INT);
$action = optional_param('action', null, PARAM_ALPHA);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

require_login($courseid);
$context = context_course::instance($courseid);

// Some stuff.
$url = new moodle_url('/blocks/leaderboard/report.php', array('courseid' => $courseid));
if ($action) {
    $url->param('action', $action);
}

// Page info.
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_title($course->fullname.': General leaderboard');
$PAGE->set_heading($COURSE->fullname);
$PAGE->set_url($url);

$userfields = user_picture::fields('u', array('username'));

$params['courseid'] = $COURSE->id;
$params['badgecourseid'] = $COURSE->id;

if ($group) {
    $from .= " INNER JOIN {groups_members} gm ON gm.userid = u.id AND gm.groupid = :groupid";
    $params['groupid'] = $group;
}

// Changed SQL query to count badges awarded - Kiya Govek 4/16

$sql = "SELECT $userfields,
		COUNT(badgetable.badgeid) as points
        FROM {user} u
        LEFT JOIN 
        (SELECT b.id AS badgeid, b.courseid AS courseid, bi.userid AS badgeuserid
        FROM {badge_issued} bi
        LEFT JOIN {badge} b ON b.id = bi.badgeid WHERE courseid = :badgecourseid) AS badgetable
        ON badgeuserid = u.id
        INNER JOIN {role_assignments} ra ON ra.userid = u.id
        INNER JOIN {context} c ON c.id = ra.contextid
        WHERE ra.roleid = 5 AND c.instanceid = :courseid AND c.contextlevel = 50
		GROUP BY u.id
		ORDER BY points DESC";

$students = array_values($DB->get_records_sql($sql, $params));

$strcoursereport = get_string('nostudents', 'block_leaderboard');;
if (count($students)) {
    $strcoursereport = get_string('report_head', 'block_leaderboard', count($students));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($strcoursereport);
$PAGE->set_title($strcoursereport);

// Output group selector if there are groups in the course.
echo $OUTPUT->container_start('leaderboard-report');

if (has_capability('moodle/site:accessallgroups', $context)) {
    $groups = groups_get_all_groups($course->id);
    if (!empty($groups)) {
        groups_print_course_menu($course, $PAGE->url);
    }
}

echo generate_table($students);

echo $OUTPUT->container_end();

echo $OUTPUT->footer();
