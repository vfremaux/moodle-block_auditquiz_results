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

require('../../../config.php');

$blockid = required_param('blockid', PARAM_INT);

if (!$DB->get_record('block_instances', array('id' => $blockid))) {
    print_error('badblock');
}

// Security.

$blockcontext = context_block::instance($blockid);
require_login();
require_capability('block/auditquiz_results:seeother', $blockcontext);

$action = required_param('what', PARAM_TEXT);

if ($action == 'unbind') {
    $qcatid = required_param('qcatid', PARAM_INT);
    $courseid = required_param('courseid', PARAM_INT);

    $params = array('blockid' => $blockid, 'questioncategoryid' => $qcatid, 'courseid' => $courseid);
    $DB->delete_records('block_auditquiz_mappings', $params);
    return;
}

if ($action == 'storeimage') {
    /*
     * Stores a rastered image from html5 in user's browser
     */

    $imagedata = required_param('imagedata', PARAM_RAW);
    $userid = required_param('userid', PARAM_INT);
    $timestamp = date('YmdHis', time());

    $filerec = new StdClass();
    $filerec->contextid = context_block::instance($blockid)->id;
    $filerec->component = 'block_auditquiz_results';
    $filerec->filerarea = 'resultgraph';
    $filerec->itemid = $userid;
    $filerec->filepath = '/';
    $filerec->filename = 'results_'.$timestamp.'.png';

    $fs = get_file_storage();
    $fs->delete_area_files($filerec->contextid, $filerec->component, $filerec->filerarea, $filerec->itemid);
    $fs->create_file_from_string($filerec, $imagedata);
}