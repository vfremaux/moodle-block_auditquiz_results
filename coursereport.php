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
 * this screen provides a course/group range report of audit quiz results
 * for teachers.
 *
 * @copyright 2015 Valery Fremaux (valery.fremaux@gmail.com)
 * @package block_auditquiz_results
 */

require('../../config.php');
require_once($CFG->dirroot.'/blocks/auditquiz_results/lib.php');

$courseid = required_param('id', PARAM_INT);
$blockid = required_param('blockid', PARAM_INT);
$groupid = optional_param('group', 0, PARAM_INT);
$view = optional_param('view', 'byuser', PARAM_ALPHA);
$PAGE->requires->jquery_plugin('jqplotjquery', 'local_vflibs');
$PAGE->requires->jquery_plugin('jqplot', 'local_vflibs');
$PAGE->requires->css('/local/vflibs/jquery/jqplot/jquery.jqplot.css');
$PAGE->requires->js_call_amd('block_auditquiz_results/auditquiz_results', 'init');

$params = ['id' => $courseid, 'blockid' => $blockid, 'group' => $groupid];
$url = new moodle_url('/blocks/auditquiz_results/coursereport.php');

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}

if (!$instance = $DB->get_record('block_instances', array('id' => $blockid))) {
    print_error('invalidblockid');
}
$theblock = block_instance('auditquiz_results', $instance);

// Security.
$context = context_course::instance($courseid);
require_course_login($course, null);
require_capability('block/auditquiz_results:seeother', $context);

$PAGE->set_url($url, $params);
$PAGE->set_context($context);
$PAGE->set_heading(get_string('coursereport', 'block_auditquiz_results'));

$reportdata = new StdClass;
$reportdata->groupmenu = groups_print_course_menu($course, $url, true);

$config = get_config('block_auditquiz_results');
if (empty($config->disablesuspendedenrolments)) {
    $config->disablesuspendedenrolments = false;
    set_config('disablesuspendedenrolments', false, 'block_auditquiz_results');
}

if ($groupid) {
    $targetusers = get_enrolled_users($context, '', $groupid, 'u.*', 'u.lastname,u.firstname', 0, 0, $config->disablesuspendedenrolments);
} else {
    $targetusers = get_enrolled_users($context, '', 0, 'u.*', 'u.lastname,u.firstname', 0, 0, $config->disablesuspendedenrolments);
}

/*
 * Load the auditquiz structure with all used questions and categories.
 */
$theblock->load_questions();
$theblock->users = $targetusers;

/*
 * Compile quiz results, building result data in the block instance with
 * a suitable organisation for the required view.
 */
block_auditquiz_results_compile($theblock, $targetusers, $view);

if ($view == 'byuser') {
    foreach (array_keys($targetusers) as $uid) {
        $theblock->build_graphdata($uid);
    }
} else {
    foreach ($theblock->categories as $parentid => $parentcats) {
        $theblock->build_category_graphdata($parentid, true /* is parent */);
        foreach (array_keys($parentcats) as $catid) {
            $theblock->build_category_graphdata($catid, false);
        }
    }
}
$reportdata->blockinstance = $theblock;
$reportdata->view = $view;

$renderer = block_auditquiz_results_get_renderer();

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('coursereport', 'block_auditquiz_results'));

echo $renderer->course_report_tabs($blockid, $view);

if ($view == 'bycategory') {
    echo '<div id="course-report-filters">';
    echo $renderer->sort_users($blockid);
    echo '</div>';
}

echo $renderer->course_report($reportdata);

echo "<center>";
$options = array();
$options['id'] = $courseid;
$options['page'] = optional_param('page', '', PARAM_INT); // Case of flexipage.
echo $OUTPUT->single_button(new moodle_url('/course/view.php', $options), get_string('backtocourse', 'block_auditquiz_results'), 'get');
echo "</center>";

echo $OUTPUT->footer();