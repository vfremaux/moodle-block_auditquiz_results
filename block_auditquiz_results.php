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
 * Audit quiz results block.
 *
 * @package    block_auditquiz_results
 * @copyright  2015 Valery Fremaux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_auditquiz_results extends block_base {

    public $loadedquestions;
    public $questions;
    public $categories;
    public $catnames;
    public $parents;
    public $results;
    public $categoryresults;
    public $categoryrealmax;
    public $parentresults;
    public $graphdata;
    public $ticks;
    public $seriecolors;

    function init() {
        $this->title = get_string('pluginname', 'block_auditquiz_results');
        $this->questions = array();
        $this->categories = array();
        $this->catnames = array();
        $this->parents = array();
        $this->results = array();
        $this->categoryresults = array();
        $this->categoryrealmax = array();
        $this->parentresults = array();
        $this->graphdata = array();
        $this->ticks = array();
    }

    function has_config() {
        return true;
    }

    function instance_allow_multiple() {
        return true;
    }

    function instance_allow_config() {
        return true;
    }

    function get_content() {
        global $COURSE, $PAGE, $OUTPUT;

        if($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        $renderer = $PAGE->get_renderer('block_auditquiz_results');
        $context = context_block::instance($this->instance->id);

        $this->content->text = '';

        if (empty($this->config->quizid)) {
            $this->content->text .= '<span class="error">'.get_string('errornoquizselected', 'block_auditquiz_results'). '</span>';
            $this->content->footer = '';
            return $this->content;
        }

        if (@$this->config->inblocklayout) {
            $this->load_questions();
            $this->load_results();

            if (has_capability('block/auditquiz_results:seeother', $context)) {
                $this->content->text .= $renderer->userselector();
            }

            if (empty($this->categories)) {
                $this->content->text .= $OUTPUT->notification(get_string('errornocategories', 'block_auditquiz_results'));
            } else {
                $this->build_graphdata();
                $this->content->text .= $renderer->dashboard($this);
            }
        } else {
            $viewdashboardstr = get_string('viewresults', 'block_auditquiz_results');
            $dashboardviewurl = new moodle_url('/blocks/auditquiz_results/view.php', array('id' => $COURSE->id, 'blockid' => $this->instance->id));
            $this->content->text = '<a href="'.$dashboardviewurl.'">'.$viewdashboardstr.'</a>';
        }

        $this->content->footer = '';

        if (has_capability('block/auditquiz_results:addinstance', $context)) {
            $mapurl = new moodle_url('/blocks/auditquiz_results/mapping.php', array('id' => $this->instance->id));
            $this->content->footer = '<a class="smalltext" href="'.$mapurl.'">'.get_string('mapcategories', 'block_auditquiz_results').'</a>';
        }

        return $this->content;
    }

    function load_questions() {
        global $DB;

        if (empty($this->config->quizid)) {
            return;
        };

        list($insql, $inparams) = $DB->get_in_or_equal($this->config->quizid);

        $sql = "
            SELECT
                qs.questionid,
                qs.maxmark,
                q.name,
                qc1.id as categoryid,
                qc1.name as category,
                qc2.id as parentid,
                qc2.name as parent
            FROM
                {quiz_slots} qs,
                {question} q,
                {question_categories} qc1
            LEFT JOIN
                {question_categories} qc2
            ON
                qc2.id = qc1.parent
            WHERE
                qs.questionid = q.id AND
                q.category = qc1.id AND
                qs.quizid $insql
            ORDER BY
                qc2.sortorder,qc1.sortorder, qs.slot
        ";

        if ($this->loadedquestions = $DB->get_records_sql($sql, $inparams)) {
            foreach($this->loadedquestions as $q) {
                $this->questions[$q->parentid][$q->categoryid][$q->questionid] = $q;

                // Cache category names for future rendering
                if (!array_key_exists($q->parentid, $this->catnames)) {
                    $this->catnames[$q->parentid] = $q->parent;
                }
                if (!array_key_exists($q->categoryid, $this->catnames)) {
                    $this->catnames[$q->categoryid] = $q->category;
                }

                // aggregate in categories
                if (!array_key_exists($q->parentid, $this->categories)) {
                    $this->categories[$q->parentid] = array();
                }
                if (!array_key_exists($q->categoryid, $this->categories[$q->parentid])) {
                    $this->categories[$q->parentid][$q->categoryid] = $q->maxmark;
                } else {
                    $this->categories[$q->parentid][$q->categoryid] += $q->maxmark;
                }
           }

            foreach($this->categories as $parentid => $parentsarr) {
                $this->parents[$parentid] = array_sum($parentsarr);
            }
        }
    }

    /**
     * Load all results (question graderight state) of the last attempt and distribute*
     * fractions over categories
     */
    function load_results() {
        global $USER, $DB;

        $context = context_block::instance($this->instance->id);

        $foruser = optional_param('userselect', $USER->id, PARAM_INT);
        if (!has_capability('block/auditquiz_results:seeother', $context)) {
            $foruser = $USER->id;
        }

        if (empty($this->config->quiztype)) {
            $this->config->quiztype = $DB->get_field('modules', 'id', array('name' => 'quiz'));
        }

        $moduletable = $DB->get_field('modules', 'name', array('id' => $this->config->quiztype));

        if (empty($moduletable)) return;

        $allstates = array();

        // Scan all participating quizes
        foreach ($this->config->quizid as $quizid) {

            // for each quiz we first get the last quiz attempt in its dedicated attempt table.
            $maxuserattemptdate = $DB->get_field($moduletable.'_attempts', 'MAX(timefinish)', array('userid' => $foruser, 'quiz' => $quizid, 'state' => 'finished'));

            if (!$maxuserattemptdate) {
                continue;
            }

            // Question usage is the "unique attempt identifier" record, that binds a quiz module implementation
            // to a set of question_attempt_steps. We search for the last finished attempt in this quiz
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
            foreach($allstates as $q) {

                // Aggregate real category max from this attempt. this might be slighly different from
                // the question_slots calculation, as question settings might have changed in the meanwhile.
                @$this->categoryrealmax[$q->parentid][$q->categoryid] += $q->maxmark;

                // Gets the question score (real attempt).
                $qscore = $q->fraction * ($q->maxfraction - $q->minfraction) * $q->maxmark;
                $this->results[$q->parentid][$q->categoryid][$q->questionid] = $qscore;

                // Aggregate in categories.
                if (!array_key_exists($q->parentid, $this->categoryresults)) {
                    $this->categoryresults[$q->parentid] = array();
                }
                if (!array_key_exists($q->categoryid, $this->categoryresults[$q->parentid])) {
                    $this->categoryresults[$q->parentid][$q->categoryid] = $qscore;
                } else {
                    $this->categoryresults[$q->parentid][$q->categoryid] += $qscore;
                }

           }

            // Aggregate in parents
            foreach ($this->categoryresults as $parentid => $parentsarr) {
                $this->parentresults[$parentid] = array_sum($parentsarr);
            }
        }
    }

    function build_graphdata() {
        if (empty($this->categories)) return;

        $debug = optional_param('debug', false, PARAM_BOOL);

        if ($debug && is_siteadmin()) {
            print_object($this->catnames);
            echo "Categories";
            print_object($this->categories);
            echo "Categories real maxes";
            print_object($this->categoryrealmax);
            echo "Results (per question)";
            print_object($this->results);
            echo "Cat results";
            print_object($this->categoryresults);
            echo "Parent cat results";
            print_object($this->parentresults);
        }

        $CATNAMECACHE = array();

        foreach($this->categories as $parentid => $cats) {
            $catmaxscore = 0;
            $catuserscore = 0;
            $catgraphdata = array();
            $this->seriecolors[] = '#244282';
            $this->ticks[] = strtoupper(str_replace("'", " ", $this->catnames[$parentid]));
            foreach ($cats as $catid => $maxscore) {

                /*
                if (isset($this->categoryrealmax[$parentid][$catid])) {
                    // Override with real attempts if any.
                    $maxscore = $this->categoryrealmax[$parentid][$catid];
                }
                */

                $userscore = 0 + @$this->categoryresults[$parentid][$catid];

                $catgraphdata[$catid] = $userscore / $maxscore * 100;

                $this->seriecolors[] = '#4BB2C5';
                $this->ticks[] = str_replace("'", "\\'", $this->catnames[$catid]);
                $catmaxscore += $maxscore;
                $catuserscore += $userscore;
            }
            // $this->graphdata[] = array('TOT : '.strtoupper(str_replace("'", "\\'", $this->catnames[$parentid])), ($catmaxscore) ? $catuserscore / $catmaxscore * 100 : 0);
            $this->graphdata[] = array(strtoupper(str_replace("'", "\\'", $this->catnames[$parentid])), ($catmaxscore) ? $catuserscore / $catmaxscore * 100 : 0);
            // Add all cats
            foreach ($catgraphdata as $catid => $data) {
                // $this->graphdata[] = array(str_replace("'", "\\'", $this->catnames[$catid]), $data);
                $catname = $this->catnames[$catid];
                while (in_array($catname, $CATNAMECACHE)) {
                    $catname .= ' ';
                }
                $CATNAMECACHE[] = $catname;
                $this->graphdata[] = array(str_replace("'", "\\'", $catname), $data);
            }
        }
    }

    /**
     * build a graph descriptor, taking some defaults decisions
     *
     */
    function graph_properties($seriecolors) {

        $jqplot = array();

        $labelarray = array(array('label' => get_string('pluginname', 'block_auditquiz_results')));

        $jqplot = array(

             'seriesDefaults' => array(
                 'renderer' => '$.jqplot.BarRenderer',
                 'rendererOptions' => array(
                     'varyBarColor' => true
                 ),
             ),
             'series' => $labelarray,
             'seriesColors' => $seriecolors,
             'noDataIndicator' => array(
                'show' => true,
             ),
             'animate' => '!$.jqplot.use_excanvas',
             'axes' => array(
                 'xaxis' => array(
                     'tickRenderer' => '$.jqplot.CanvasAxisTickRenderer',
                     'tickOptions' => array(
                         'angle' => '45'
                     ),
                    'renderer' => '$.jqplot.CategoryAxisRenderer',
                    'label' => '',
//                    'ticks' => '$$.ticks',
                 ),
                 'yaxis' => array(
                     'autoscale' => true,
                     'padMax' => 5,
                    'label' => get_string('rate', 'block_auditquiz_results'),
                    'rendererOptions' => array('forceTickAt0' => true),
                     'tickOptions' => array('formatString' => '%2d'),
                    'labelRenderer' => '$.jqplot.CanvasAxisLabelRenderer',
                    'labelOptions' => array('angle' => 90),
                 ),
             ),
        );

        $jqplot['axes']['yaxis']['min'] = 0;
        $jqplot['axes']['yaxis']['autoscale'] = false;

        $jqplot['axes']['yaxis']['max'] = 100;
        $jqplot['axes']['yaxis']['autoscale'] = false;

        if (!empty($this->config->tickspacing)) {
            $jqplot['axes']['yaxis']['tickInterval'] = 10;
        }

        return $jqplot;
    }

    /**
     * Fetch javascript and extra libs necessary for rendering this block
     */
    public function get_required_javascript() {
        global $CFG, $PAGE;

        parent::get_required_javascript();

        $PAGE->requires->jquery_plugin('jqplotjquery', 'local_vflibs');
        $PAGE->requires->jquery_plugin('jqplot', 'local_vflibs');
        $PAGE->requires->css('/local/vflibs/jquery/jqplot/jquery.jqplot.css');
    }

    /**
     * Gets all enrolllable courses for the students : 
     * - course is visible
     * - course has a self enrolment method or a profilefield enrolment method
     * all mapped courses at setup time may not be actually presented to user depending
     * his own enrolment capabilities at time the test will be performed.
     * @see local/my/lib.php local_get_enrollable_courses() for similar query
     */
    static function get_enrollable_courses($userid = null) {
        global $DB, $USER;

        if (!$userid) $userid = $USER->id;
        
        $sql = "
            SELECT
                e.id,
                e.enrol,
                e.courseid as cid
            FROM
                {enrol} e
            LEFT JOIN
                {user_enrolments} ue
            ON
                ue.userid = ? AND
                ue.enrolid = e.id
            WHERE
                e.status = 0 AND
                ue.id IS NULL AND
                (e.enrol = 'self' OR e.enrol = 'profilefield')
        ";
        $possibles = $DB->get_records_sql($sql, array($USER->id));

        // Collect unique list of possible courses
        $courses = array();
        if (!empty($possibles)) {
            $courseids = array();
            foreach ($possibles as $e) {
                if (!in_array($e->cid, $courseids)) {
                    $courses[$e->cid] = $DB->get_record('course', array('id' => $e->cid), 'id,shortname,fullname,visible,summary,sortorder,category');
                    $courses[$e->cid]->ccsortorder = $DB->get_field('course_categories', 'sortorder', array('id' => $courses[$e->cid]->category));
                    $courseids[] = $e->cid;
                }
            }
        }

        return $courses;
    }

    /**
     * Get the mapping information for the current bloc instance.
     */
    function get_mappings($categoryid = 0) {
        global $DB;

        $map = array();
        if ($categoryid) {
            $mappings = $DB->get_records('block_auditquiz_mappings', array('blockid' => $this->instance->id, 'questioncategoryid' => $categoryid));
        } else {
            $mappings = $DB->get_records('block_auditquiz_mappings', array('blockid' => $this->instance->id));
        }
        if ($mappings) {
            foreach($mappings as $mapping) {
                $map[$mapping->questioncategoryid][] = $mapping->courseid;
            }
        }

        return $map;
    }

    /**
     * Get the mapping information for the current bloc instance.
     */
    function get_linked_courses($categoryid) {
        global $DB;

        $map = $this->get_mappings();
        $mappedcourses = array();
        if (!empty($map)) {
            if (array_key_exists($categoryid, $map)) {
                foreach ($map[$categoryid] as $cid) {
                    $mappedcourses[$cid] = $DB->get_record('course', array('id' => $cid), 'id, shortname, fullname, visible');
                }
            }
        }

        return $mappedcourses;
    }
}


