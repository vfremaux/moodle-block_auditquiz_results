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
require_once($CFG->dirroot.'/blocks/auditquiz_results/lib.php');

$blockid = required_param('blockid', PARAM_INT);

if (!$DB->get_record('block_instances', ['id' => $blockid])) {
    print_error('badblock');
}

// Security.

$blockcontext = context_block::instance($blockid);
require_login();
require_capability('block/auditquiz_results:seeother', $blockcontext);

$PAGE->set_context($blockcontext);
$PAGE->set_cacheable(false);
$renderer = block_auditquiz_results_get_renderer();

$action = required_param('what', PARAM_TEXT);

if ($action == 'unbind') {
    $qcatid = required_param('qcatid', PARAM_INT);
    $courseid = required_param('courseid', PARAM_INT);

    $params = ['blockid' => $blockid, 'questioncategoryid' => $qcatid, 'courseid' => $courseid];
    $DB->delete_records('block_auditquiz_mappings', $params);
    return;
}

if ($action == 'addsnapshot') {
    /*
     * Stores a rastered image from html5 in user's browser
     */

    $imagedata = required_param('imagedata', PARAM_RAW);
    $itemid = required_param('itemid', PARAM_INT);
    $type = required_param('snaptype', PARAM_TEXT);
    $timestamp = date('YmdHis', time());

    $imagedata = str_replace('data:image/png;base64,', '', $imagedata);
    $imagedata = base64_decode($imagedata);

    $filerec = new StdClass();
    $filerec->contextid = context_block::instance($blockid)->id;
    $filerec->component = 'block_auditquiz_results';
    $filerec->filearea = 'resultgraph_'.$type;
    $filerec->itemid = $itemid;
    $filerec->filepath = '/';
    $filerec->filename = 'results_'.$timestamp.'.png';

    $fs = get_file_storage();
    // $fs->delete_area_files($filerec->contextid, $filerec->component, $filerec->filearea, $filerec->itemid);
    $fs->create_file_from_string($filerec, $imagedata);

    $template = new StdClass;
    $template->cansnapshot = true;
    $template->itemid = $itemid;
    $template->blockid = $blockid;
    $template->snapshoticon = $OUTPUT->pix_icon('f/jpeg-128', '');
    $renderer->snapshotlist($template, $blockcontext, $itemid, $type);
    echo $OUTPUT->render_from_template('block_auditquiz_results/snapshotlist', $template);
}

if ($action == 'deletesnapshot') {

    $fileid = required_param('snapshotid', PARAM_INT);
    $itemid = required_param('itemid', PARAM_INT);
    $type = required_param('snaptype', PARAM_TEXT);
    $fs = get_file_storage();
    if ($storedfile = $fs->get_file_by_id($fileid)) {
        $storedfile->delete();
    }

    $template = new StdClass;
    $template->cansnapshot = true;
    $template->blockid = $blockid;
    $template->itemid = $itemid;
    $template->snapshoticon = $OUTPUT->pix_icon('f/jpeg-128', '');
    $renderer->snapshotlist($template, $blockcontext, $itemid, $type);
    echo $OUTPUT->render_from_template('block_auditquiz_results/snapshotlist', $template);
}
