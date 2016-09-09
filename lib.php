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

defined('MOODLE_INTERNAL') || die();

/**
 * This screen can map courses to question categories used in the tests so
 * that a list of self enrolment could be proposed in HTML results
 *
 * @copyright 2015 Valery Fremaux (valery.fremaux@gmail.com)
 * @package block_auditquiz_results
 * @category blocks
 */

function block_auditquiz_add_mapping($courseid, $blockid, $qcatid) {
    global $DB;

    if (!$mapping = $DB->get_record('block_auditquiz_mappings', array('blockid' => $blockid, 'questioncategoryid' => $qcatid, 'courseid' => $courseid))) {
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

    $DB->delete_records('block_auditquiz_mappings', array('blockid' => $blockid, 'questioncategoryid' => $qcatid, 'courseid' => $courseid));
}

