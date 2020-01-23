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
 * This screen can map courses to quesiton categories used in the tests so
 * that a list of self enrolment could be proposed in HTML results
 *
 * @copyright   2015 Valery Fremaux (valery.fremaux@gmail.com)
 * @package     block_auditquiz_results
 * @category    blocks
 */

require('../../config.php');
require_once($CFG->dirroot.'/blocks/auditquiz_results/classes/potential_courses_to_map_selector.php');
require_once($CFG->dirroot.'/blocks/auditquiz_results/classes/assigned_courses_to_map_selector.php');
require_once($CFG->dirroot.'/blocks/auditquiz_results/lib.php');

$blockid = required_param('id', PARAM_INT); // The Block ID.
$qcatid = required_param('qcatid', PARAM_INT);

if (!$instance = $DB->get_record('block_instances', array('id' => $blockid))) {
    print_error('invalidblockid');
}

$context = context::instance_by_id($instance->parentcontextid);
$courseid = $context->instanceid;

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}

// Security.

require_login($course);
require_capability('block/auditquiz_results:addinstance', $context);

$theblock = block_instance('auditquiz_results', $instance);
$theblock->get_required_javascript();
$theblock->load_questions();
$context = context_block::instance($theblock->instance->id);

$PAGE->navbar->add(get_string('addcourses', 'block_auditquiz_results'), null);

if (!empty($theBlock->config->title)) {
    $PAGE->navbar->add($theBlock->config->title, null);
}

$params = array('blockid' => $blockid, 'qcatid' => $qcatid);
$PAGE->set_url(new moodle_url('/blocks/auditquiz_results/mapcategory.php', $params));
$PAGE->set_title($SITE->shortname);
$PAGE->set_heading($SITE->shortname);

$renderer = $PAGE->get_renderer('block_auditquiz_results');

$options = array('blockid' => $blockid, 'qcatid' => $qcatid);
$potentialcoursesselector = new \block_auditquiz_results\selectors\potential_courses_to_map_selector('potcourses', $options);
$assignedcoursesselector = new \block_auditquiz_results\selectors\assigned_courses_to_map_selector('extcourses', $options);

// Process incoming role assignments.
$errors = array();
if (optional_param('add', false, PARAM_TEXT) && confirm_sesskey()) {
    $coursestoassign = $potentialcoursesselector->get_selected_courses();
    if (!empty($coursestoassign)) {

        foreach ($coursestoassign as $addcourse) {
            $allow = true;

            if ($allow) {
                block_auditquiz_add_mapping($addcourse->id, $blockid, $qcatid);
            }
        }

        $potentialcoursesselector->invalidate_selected_courses();
        $assignedcoursesselector->invalidate_selected_courses();
    }
}

// Process incoming role unassignments.
if (optional_param('remove', false, PARAM_TEXT) && confirm_sesskey()) {
    $coursestounassign = $assignedcoursesselector->get_selected_courses();
    if (!empty($coursestounassign)) {

        foreach ($coursestounassign as $removecourse) {
            block_auditquiz_remove_mapping($removecourse->id, $blockid, $qcatid);
        }

        $potentialcoursesselector->invalidate_selected_courses();
        $assignedcoursesselector->invalidate_selected_courses();

    }
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('addcourses', 'block_auditquiz_results'));

echo $OUTPUT->box(get_string('assignablecourses_desc', 'block_auditquiz_results'), 'assigncourses-instructions');

if ($qcatid) {
    // Show UI for assigning a particular courses to category.

    // Print the form.

    echo $renderer->assigncourseform($blockid, $qcatid, $assignedcoursesselector, $potentialcoursesselector);
}
$PAGE->requires->js_init_call('M.core_course.init_course_selector');

if (!empty($errors)) {
    $msg = '<p>';
    foreach ($errors as $e) {
        $msg .= $e.'<br />';
    }
    $msg .= '</p>';
    echo $OUTPUT->box_start();
    echo $OUTPUT->notification($msg);
    echo $OUTPUT->box_end();
}

echo '<center>';
echo $OUTPUT->single_button(new moodle_url('/blocks/auditquiz_results/mapping.php', array('id' => $blockid)), get_string('back', 'block_auditquiz_results'));
echo '</center>';

echo $OUTPUT->footer();