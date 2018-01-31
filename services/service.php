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

$blockid = required_context('id', PARAM_INT); // the course module id

if (!$DB->get_record('block_instances', array('id' => $blockid))) {
    print_error('badblock');
}

// Security.

$blockcontext = context_block::instance($id);
require_login();
require_capability('block/auditquiz_results:seeother', $blockcontext);

$action = required_param('what', PARAM_TEXT);

if ($action == 'storeimage') {
    /*
     * Stores a rastered image from html5 in user's browser
     */

    $imagedata = required_param('imagedata', PARAM_RAW);
    $blockid = required_param('id', PARAM_INT);
    $userid = required_param('userid', PARAM_INT);

    $filerec = new StdClass();
    $filerec->contextid = context_block::instance($blockid)->id;
    $filerec->component = 'block_auditquiz_results';
    $filerec->filerarea = 'resultgraph';
    $filerec->itemid = $userid;
    $filerec->filepath = '/';
    $filerec->filename = 'results.png';

    $fs = get_file_storage();
    $fs->delete_area_files($filerec->contextid, $filerec->component, $filerec->filerarea, $filerec->itemid);
    $fs->create_file_from_string($filerec, $imagedata);
}