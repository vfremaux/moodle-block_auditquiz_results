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
 * @copyright 2015 Valery Fremaux (valery.fremaux@gmail.com)
 * @package block_auditquiz_results
 */

require('../../config.php');

$blockid = required_param('id', PARAM_INT);

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

$PAGE->navbar->add(get_string('results', 'block_auditquiz_results'), null);

if (!empty($theBlock->config->title)) {
    $PAGE->navbar->add($theBlock->config->title, null);
}

$PAGE->set_url(new moodle_url('/blocks/auditquiz_results/view.php', array('id' => $courseid, 'blockid' => $blockid)));
$PAGE->set_title($SITE->shortname);
$PAGE->set_heading($SITE->shortname);
$PAGE->requires->js_call_amd('block_auditquiz_results/auditquiz_results', 'init');

$renderer = $PAGE->get_renderer('block_auditquiz_results');

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('coursebindings', 'block_auditquiz_results'));

if (empty($theblock->categories)) {
    echo $OUTPUT->box(get_string('noquestions', 'block_auditquiz_results'));
    echo $OUTPUT->footer();
    die;
}

$mappings = $theblock->get_mappings(0);
echo $OUTPUT->box_start();
echo $renderer->categories_mapping($theblock, $mappings);
echo $OUTPUT->box_end();
echo '<br/>';
echo '<center>';
$buttonurl = new moodle_url('/course/view.php', array('id' => $courseid));
echo $OUTPUT->single_button($buttonurl, get_string('backtocourse', 'block_auditquiz_results'));
echo '</center>';

echo $OUTPUT->footer();