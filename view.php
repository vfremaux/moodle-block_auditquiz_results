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
 * More internal functions we may need
 * These functions are essentially direct use and data 
 * extration from the underlying DB model, that will not
 * use object instance context to proceed.
 *
 * @copyright 2015 Valery Fremaux (valery.fremaux@gmail.com)
 * @package block_auditquiz_results
 */

require('../../config.php');
require_once($CFG->dirroot.'/blocks/auditquiz_results/lib.php');

$courseid = required_param('id', PARAM_INT);
$blockid = required_param('blockid', PARAM_INT);
$foruser = optional_param('userselect', $USER->id, PARAM_INT);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}

require_login($course);

if (!$instance = $DB->get_record('block_instances', array('id' => $blockid))) {
    print_error('invalidblockid');
}

$theblock = block_instance('auditquiz_results', $instance);
$theblock->get_required_javascript();
$context = context_block::instance($theblock->instance->id);

if (!has_capability('block/auditquiz_results:seeother', $context)) {
    $foruser = $USER->id;
}

$PAGE->navbar->add(get_string('results', 'block_auditquiz_results'), null);

if (!empty($theBlock->config->title)) {
    $PAGE->navbar->add($theBlock->config->title, null);
}

$PAGE->set_url(new moodle_url('/blocks/auditquiz_results/view.php', array('id' => $courseid, 'blockid' => $blockid)));
$PAGE->set_title($SITE->shortname);
$PAGE->set_heading($SITE->shortname);

$renderer = block_auditquiz_results_get_renderer();

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('pluginname', 'block_auditquiz_results'));

echo $OUTPUT->box_start();

if (has_capability('block/auditquiz_results:seeother', $context)) {
    echo $renderer->userselector($theblock);
}

if (empty($theblock->config->quizid)) {
    echo $OUTPUT->box_start('error');
    echo get_string('errornoquizselected', 'block_auditquiz_results');
    echo $OUTPUT->box_end();
} else {

    $theblock->load_questions();
    $theblock->load_results();

    $theblock->build_graphdata();

    if (empty($theblock->categories)) {
        echo $OUTPUT->box($OUTPUT->notifications(get_string('errornocategories', 'block_auditquiz_results')));
    } else {
        echo $renderer->dashboard($theblock, $foruser);
    }
}

echo $OUTPUT->box_end();

echo $renderer->htmlreport($theblock);

echo '<br/>';
echo '<center>';
$options = array();
$options['id'] = $courseid;
$options['page'] = optional_param('page', '', PARAM_INT); // case of flexipage
echo $OUTPUT->single_button(new moodle_url('/course/view.php', $options), get_string('backtocourse', 'block_auditquiz_results'), 'get');
echo '</center>';
echo '<br/>';

echo $OUTPUT->footer($course);