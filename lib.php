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

/**
 * compile a set of results in the block instance.
 * @param object $blockinstance the block instance. Data is compiled within the instance.
 * @param array $targetusers an array of user records.
 * @param string $view the view for which to compile data. (byuser | bycategory)
 */
function block_auditquiz_results_compile($blockinstance, $targetusers, $view) {
    $funcname = 'block_auditquiz_results_compile_worker_'.$view;
    foreach ($targetusers as $u) {
        $funcname($blockinstance, $u);
    }
}

/**
 * Compile a single user in the instance.
 * @param object $blockinstance the block instance. Data is compiled within the instance.
 * @param mixed $userorid a user record or a userid.
 */
function block_auditquiz_results_compile_worker_byuser($blockinstance, $userorid) {
    global $DB;

    $context = context_block::instance($blockinstance->instance->id);

    if (is_numeric($userorid)) {
         $foruser = $userorid;
    } else {
        $foruser = $userorid->id;
    }

    $moduletable = $DB->get_field('modules', 'name', array('name' => $blockinstance->config->quiztype));

    if (empty($moduletable)) {
        return;
    }

    $allstates = array();

    // Scan all participating quizes.
    foreach ($blockinstance->config->quizid as $quizid) {

        // For each quiz we first get the last quiz attempt in its dedicated attempt table.
        $params = array('userid' => $foruser, 'quiz' => $quizid, 'state' => 'finished');
        $maxuserattemptdate = $DB->get_field($moduletable.'_attempts', 'MAX(timefinish)', $params);

        if (!$maxuserattemptdate) {
            continue;
        }

        /*
         * Question usage is the "unique attempt identifier" record, that binds a quiz module implementation
         * to a set of question_attempt_steps. We search for the last finished attempt in this quiz
         */
        $sql = "
            SELECT
                qua.id
            FROM
                {{$moduletable}}_attempts qa,
                {question_usages} qua
            WHERE
                qa.uniqueid = qua.id AND
                qa.timefinish > 0 AND
                qa.timefinish = ? AND
                qa.userid = ? AND
                qa.quiz = ? AND
                qa.state = 'finished'
        ";
        $questionusage = $DB->get_record_sql($sql, array($maxuserattemptdate, $foruser, $quizid));

        $sql = "
            SELECT
               qa.questionid,
               qa.minfraction,
               qa.maxfraction,
               qa.maxmark,
               qas.fraction,
               qc1.id as categoryid,
               qc1.name as category,
               qc2.id as parentid,
               qc2.name as parent
            FROM
                {question_attempt_steps} qas,
                {question_attempts} qa,
                {question} q,
                {question_categories} qc1
            LEFT JOIN
                {question_categories} qc2
            ON
                qc1.parent = qc2.id
            WHERE
                qas.questionattemptid = qa.id AND
                (qas.state = 'gradedright' OR qas.state = 'gradedpartial') AND
                qa.questionusageid = ? AND
                qa.questionid = q.id AND
                q.category = qc1.id AND
                qas.userid = ?
        ";
        $states = $DB->get_records_sql($sql, array($questionusage->id, $foruser));
        $allstates = $allstates + $states;
    }

    if ($allstates) {
        foreach ($allstates as $q) {

            // Create missing users.
            if (!array_key_exists($foruser, $blockinstance->results)) {
                $blockinstance->categoryresults[$foruser][$q->parentid][$q->categoryid] = 0;
                $blockinstance->parentresults[$foruser][$q->parentid] = 0;
                $blockinstance->categoryrealmax[$foruser][$q->parentid][$q->categoryid] = 0;
                $blockinstance->results[$foruser] = [];
            }

            // Aggregate in categories.
            if (!array_key_exists($q->parentid, $blockinstance->results[$foruser])) {
                $blockinstance->categoryresults[$foruser][$q->parentid][$q->categoryid] = 0;
                $blockinstance->parentresults[$foruser][$q->parentid] = 0;
                $blockinstance->categoryrealmax[$foruser][$q->parentid][$q->categoryid] = 0;
                $blockinstance->results[$foruser][$q->parentid] = [];
            }

            // Aggregate in categories.
            if (!array_key_exists($q->categoryid, $blockinstance->results[$foruser][$q->parentid])) {
            $blockinstance->categoryresults[$foruser][$q->parentid][$q->categoryid] = array();
                $blockinstance->categoryresults[$foruser][$q->parentid][$q->categoryid] = 0;
                $blockinstance->categoryrealmax[$foruser][$q->parentid][$q->categoryid] = 0;
            }

            // Gets the question score (real attempt).
            $qscore = $q->fraction * ($q->maxfraction - $q->minfraction) * $q->maxmark;
            $blockinstance->results[$foruser][$q->parentid][$q->categoryid][$q->questionid] = $qscore;

            /*
             * Aggregate real category max from this attempt. this might be slighly different from
             * the question_slots calculation, as question settings might have changed in the meanwhile.
             */
            $blockinstance->categoryrealmax[$foruser][$q->parentid][$q->categoryid] += $q->maxmark;

            if (!array_key_exists($q->categoryid, $blockinstance->categoryresults[$foruser][$q->parentid])) {
                $blockinstance->categoryresults[$foruser][$q->parentid][$q->categoryid] = $qscore;
            } else {
                $blockinstance->categoryresults[$foruser][$q->parentid][$q->categoryid] += $qscore;
            }
       }

        // Aggregate in parents.
        foreach ($blockinstance->categoryresults[$foruser] as $parentid => $parentsarr) {
            $blockinstance->parentresults[$foruser][$parentid] = array_sum($parentsarr);
        }
    }
}

/**
 * Compile a single user in the instance.
 * @param object $blockinstance the block instance. Data is compiled within the instance.
 * @param object $userorid a user record or a user id.
 */
function block_auditquiz_results_compile_worker_bycategory($blockinstance, $userorid) {
    global $DB;

    $context = context_block::instance($blockinstance->instance->id);

    if (is_numeric($userorid)) {
         $foruser = $userorid;
    } else {
        $foruser = $userorid->id;
    }

    $moduletable = $DB->get_field('modules', 'name', array('name' => $blockinstance->config->quiztype));

    if (empty($moduletable)) {
        return;
    }

    $allstates = array();

    // Scan all participating quizes.
    foreach ($blockinstance->config->quizid as $quizid) {

        // For each quiz we first get the last quiz attempt in its dedicated attempt table.
        $params = array('userid' => $foruser, 'quiz' => $quizid, 'state' => 'finished');
        $maxuserattemptdate = $DB->get_field($moduletable.'_attempts', 'MAX(timefinish)', $params);

        if (!$maxuserattemptdate) {
            continue;
        }

        /*
         * Question usage is the "unique attempt identifier" record, that binds a quiz module implementation
         * to a set of question_attempt_steps. We search for the last finished attempt in this quiz
         */
        $sql = "
            SELECT
                qua.id
            FROM
                {{$moduletable}}_attempts qa,
                {question_usages} qua
            WHERE
                qa.uniqueid = qua.id AND
                qa.timefinish > 0 AND
                qa.timefinish = ? AND
                qa.userid = ? AND
                qa.quiz = ? AND
                qa.state = 'finished'
        ";
        $questionusage = $DB->get_record_sql($sql, array($maxuserattemptdate, $foruser, $quizid));

        $sql = "
            SELECT
               qa.questionid,
               qa.minfraction,
               qa.maxfraction,
               qa.maxmark,
               qas.fraction,
               qc1.id as categoryid,
               qc1.name as category,
               qc2.id as parentid,
               qc2.name as parent
            FROM
                {question_attempt_steps} qas,
                {question_attempts} qa,
                {question} q,
                {question_categories} qc1
            LEFT JOIN
                {question_categories} qc2
            ON
                qc1.parent = qc2.id
            WHERE
                qas.questionattemptid = qa.id AND
                (qas.state = 'gradedright' OR qas.state = 'gradedpartial') AND
                qa.questionusageid = ? AND
                qa.questionid = q.id AND
                q.category = qc1.id AND
                qas.userid = ?
        ";
        $states = $DB->get_records_sql($sql, array($questionusage->id, $foruser));
        $allstates = $allstates + $states;
    }

    if ($allstates) {
        foreach ($allstates as $q) {

            // Aggregate in categories.
            if (!array_key_exists($q->parentid, $blockinstance->results)) {
                $blockinstance->categoryresults[$q->parentid][$q->categoryid][$foruser] = 0;
                $blockinstance->categoryrealmax[$q->parentid][$q->categoryid][$foruser] = 0;
                $blockinstance->parentresults[$q->parentid][$foruser] = 0;
                $blockinstance->results[$q->parentid] = [];
            }

            // Aggregate in categories.
            if (!array_key_exists($q->categoryid, $blockinstance->results[$q->parentid])) {
                $blockinstance->categoryresults[$q->parentid][$q->categoryid][$foruser] = 0;
                $blockinstance->categoryrealmax[$q->parentid][$q->categoryid][$foruser] = 0;
                $blockinstance->results[$q->parentid][$q->categoryid] = [];
            }

            // Gets the question score (real attempt).
            $qscore = $q->fraction * ($q->maxfraction - $q->minfraction) * $q->maxmark;
            $blockinstance->results[$q->parentid][$q->categoryid][$q->questionid][$foruser] = $qscore;

            /*
             * Aggregate real category max from this attempt. this might be slighly different from
             * the question_slots calculation, as question settings might have changed in the meanwhile.
             */
            if (!array_key_exists($foruser, $blockinstance->categoryrealmax[$q->parentid][$q->categoryid])) {
                $blockinstance->categoryrealmax[$q->parentid][$q->categoryid][$foruser] = 0;
            }
            $blockinstance->categoryrealmax[$q->parentid][$q->categoryid][$foruser] += $q->maxmark;

            if (!array_key_exists($foruser, $blockinstance->categoryresults[$q->parentid][$q->categoryid])) {
                $blockinstance->categoryresults[$q->parentid][$q->categoryid][$foruser] = 0;
            }
            $blockinstance->categoryresults[$q->parentid][$q->categoryid][$foruser] += $qscore;

            if (!array_key_exists($foruser, $blockinstance->parentresults[$q->parentid])) {
                $blockinstance->parentresults[$q->parentid][$foruser] = 0;
            }
            $blockinstance->parentresults[$q->parentid][$foruser] += $qscore;
       }

    }
}