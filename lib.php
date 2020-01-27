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
 * @copyright 2015 Valery Fremaux (valery.fremaux@gmail.com)
 * @package block_auditquiz_results
 * @category blocks
 */
defined('MOODLE_INTERNAL') || die();

/**
 * This function is not implemented in this plugin, but is needed to mark
 * the vf documentation custom volume availability.
 */
function block_auditquiz_results_supports_feature($feature) {
    global $CFG;
    static $supports;

    $config = get_config('block_auditquiz_results');

    if (!isset($supports)) {
        $supports = array(
            'pro' => array(
                'graph' => array('snapshot'),
                'export' => array('pdf'),
            ),
            'community' => array(
            ),
        );
    }

    // Check existance of the 'pro' dir in plugin.
    if (is_dir(__DIR__.'/pro')) {
        if ($feature == 'emulate/community') {
            return 'pro';
        }
        if (empty($config->emulatecommunity)) {
            $versionkey = 'pro';
        } else {
            $versionkey = 'community';
        }
    } else {
        $versionkey = 'community';
    }

    list($feat, $subfeat) = explode('/', $feature);

    if (!array_key_exists($feat, $supports[$versionkey])) {
        return false;
    }

    if (!in_array($subfeat, $supports[$versionkey][$feat])) {
        return false;
    }

    return $versionkey;
}

function block_auditquiz_results_get_renderer() {
    global $PAGE, $CFG;

    if (block_auditquiz_results_supports_feature('emulate/community') == 'pro') {
        include($CFG->dirroot.'/blocks/auditquiz_results/pro/renderer.php');
        $renderer = new block_auditquiz_results_renderer_extended($PAGE, '');
        return $renderer;
    } else {
        return $PAGE->get_renderer('block_auditquiz_results');
    }
}

function block_auditquiz_add_mapping($courseid, $blockid, $qcatid) {
    global $DB;

    $params = array('blockid' => $blockid, 'questioncategoryid' => $qcatid, 'courseid' => $courseid);
    if (!$mapping = $DB->get_record('block_auditquiz_mappings', $params)) {
        $record = new StdClass;
        $record->courseid = $courseid;
        $record->blockid = $blockid;
        $record->questioncategoryid = $qcatid;
        $record->description = '';
        $DB->insert_record('block_auditquiz_mappings', $record);
    }
}

function block_auditquiz_remove_mapping($courseid, $blockid, $qcatid) {
    global $DB;

    $params = array('blockid' => $blockid, 'questioncategoryid' => $qcatid, 'courseid' => $courseid);
    $DB->delete_records('block_auditquiz_mappings', $params);
}

function block_auditquiz_results_pluginfile($course, $birecord_or_cm, $context, $filearea, $args, $forcedownload) {

    if ($context->contextlevel != CONTEXT_BLOCK) {
        send_file_not_found();
    }

    require_course_login($course);

    if ($filearea !== 'resultgraph') {
        send_file_not_found();
    }

    $fs = get_file_storage();

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/'.implode('/', $args).'/' : '/';

    if ((!$file = $fs->get_file($context->id, 'block_auditquiz_results', 'resultgraph', $itemid, $filepath, $filename)) or $file->is_directory()) {
        send_file_not_found();
    }

    // Weird, there should be parent context, better force dowload then.
    $forcedownload = true;

    \core\session\manager::write_close();
    send_stored_file($file, 60 * 60, 0, $forcedownload);
}
