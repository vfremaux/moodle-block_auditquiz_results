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
 * Audit quiz results block.
 *
 * @package    block_auditquiz_results
 * @copyright  2015 Valery Fremaux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

class block_auditquiz_results extends block_base {

    public $loadedquestions;

    /**
     * A 3 dimensional array keyed by parentcategory, category and question id.
     */
    public $questions;

    /**
     * A 2 dimensional array keyed by parentcategory and category ids
     * Contains category average scores
     */
    public $categories;

    /**
     * an array of category names keyed by category id.
     */
    public $catnames;

    public $parents;

    public $results;
    public $categoryresults;
    public $categoryrealmax;
    public $parentresults;
    public $graphdata;
    public $ticks;

    /**
     * Series color holds all bar colors.
     * On a "per category" graph (byuser), color depends on wether category is parent or child.
     * $seriecolors = [<parent1color>, <child11color>, <child2color>, etc. ]
     * Seriecolors is identical for all users.
      * On a "per user basis" graph (per category), color depends on user score against thresholds.
     * $seriecolors = [catid1 => [<user1color>, <user2color>, <user3color>, etc. ], catid2 => ... ]
     * Each category has its own colorset, depending on user results in the category.
     */
    public $seriecolors;

    /**
     * A local cache of all targetted users.
     */
    public $users;

    public function init() {
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

    public function has_config() {
        return true;
    }

    public function instance_allow_multiple() {
        return true;
    }

    public function instance_allow_config() {
        return true;
    }

    public function instance_create() {
        $this->config = new StdClass;
        $this->config->studentcanseeown = 1;
        $this->config->enablecoursemapping = 1;
        $this->config->proposeenrolonsuccess = 1;
        $this->config->width = 400;
        $this->config->height = 300;
        $this->config->passrate = 50;
        $this->config->passrate2 = 80;

        $this->instance_config_save($this->config);
    }

    public function get_content() {
        global $COURSE, $PAGE, $OUTPUT, $USER, $DB;

        if ($this->content !== null) {
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

        if (@$this->config->inblocklayout >= 1) {
            // Render in block.
            $this->load_questions();
            $this->load_results();

            if (has_capability('block/auditquiz_results:seeother', $context)) {
                $this->content->text .= $renderer->userselector();
            }

            if (empty($this->categories)) {
                $this->content->text .= $OUTPUT->notification(get_string('errornocategories', 'block_auditquiz_results'));
            } else {
                $foruser = optional_param('userselect', $USER->id, PARAM_INT);
                if (!has_capability('block/auditquiz_results:seeother', $context)) {
                    $foruser = $USER->id;
                }
                $this->users = [$foruser => $DB->get_record('user', ['id' => $foruser])];
                $this->build_graphdata($foruser);
                $this->content->text .= $renderer->dashboard($this, $foruser);
            }

            if ($this->config->inblocklayout == 2) {
                $this->content->text .= $renderer->htmlreport($theblock);
            }
        } else {
            // Render in external page.
            $viewdashboardstr = get_string('viewresults', 'block_auditquiz_results');
            $dashboardviewurl = new moodle_url('/blocks/auditquiz_results/view.php', array('id' => $COURSE->id, 'blockid' => $this->instance->id));
            $this->content->text = '<a href="'.$dashboardviewurl.'">'.$viewdashboardstr.'</a>';
        }

        $this->content->footer = '';
        if (has_capability('block/auditquiz_results:seeother', $context)) {
            $footerelms[] = $renderer->course_report_link($this->instance->id, 'aslink', 'smalltext');
        }

        if (has_capability('block/auditquiz_results:addinstance', $context)) {
            $mapurl = new moodle_url('/blocks/auditquiz_results/mapping.php', array('id' => $this->instance->id));
            $footerelms[] = '<a class="smalltext" href="'.$mapurl.'">'.get_string('mapcategories', 'block_auditquiz_results').'</a>';
        }
        if (!empty($footerelms)) {
            $this->content->footer = implode(' - ', $footerelms);
        }

        return $this->content;
    }

    public function load_questions() {
        global $DB;

        if (empty($this->config->quizid)) {
            return;
        };

        list($insql, $inparams) = $DB->get_in_or_equal($this->config->quizid);

        $sql = "
            SELECT DISTINCT
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
            foreach ($this->loadedquestions as $q) {
                // If this is a standard straight question. 
                // We collect immediate category as topic, and parent as domain.
                // If the question is random, the top pickup category will be the topic and the parent the domain.
                $this->questions[$q->parentid][$q->categoryid][$q->questionid] = $q;

                // Cache category names for future rendering.
                if (!array_key_exists($q->parentid, $this->catnames)) {
                    $this->catnames[$q->parentid] = $q->parent;
                }
                if (!array_key_exists($q->categoryid, $this->catnames)) {
                    $this->catnames[$q->categoryid] = $q->category;
                }

                // Aggregate in categories.
                if (!array_key_exists($q->parentid, $this->categories)) {
                    $this->categories[$q->parentid] = array();
                }
                if (!array_key_exists($q->categoryid, $this->categories[$q->parentid])) {
                    $this->categories[$q->parentid][$q->categoryid] = $q->maxmark;
                } else {
                    $this->categories[$q->parentid][$q->categoryid] += $q->maxmark;
                }
           }

            foreach ($this->categories as $parentid => $parentsarr) {
                $this->parents[$parentid] = array_sum($parentsarr);
            }
        }
    }

    /**
     * Load all results (question graderight state) of the last attempt and distribute*
     * fractions over categories
     */
    public function load_results() {
        global $USER;

        $context = context_block::instance($this->instance->id);

        $foruser = optional_param('userselect', $USER->id, PARAM_INT);
        if (!has_capability('block/auditquiz_results:seeother', $context)) {
            $foruser = $USER->id;
        }

        block_auditquiz_results_compile_worker_byuser($this, $foruser);
    }

    /**
     * Build data striucture for user graph.
     */
    public function build_graphdata($userid = null) {
        global $USER;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        if (empty($this->categories)) {
            return;
        }

        $debug = optional_param('debug', false, PARAM_BOOL);

        if ($debug && is_siteadmin()) {
            print_object($this->catnames);
            echo "Categories";
            print_object($this->categories);
            echo "Categories real maxes";
            print_object($this->categoryrealmax);
            echo "Results (per question)";
            print_object($this->results[$userid]);
            echo "Cat results";
            print_object($this->categoryresults[$userid]);
            echo "Parent cat results";
            print_object($this->parentresults[$userid]);
        }

        $CATNAMECACHE = array();

        foreach ($this->categories as $parentid => $cats) {
            $catmaxscore = 0;
            $catuserscore = 0;
            $catgraphdata = array();
            $this->seriecolors[] = '#244282';
            $this->ticks[] = strtoupper(str_replace("'", " ", $this->catnames[$parentid]));
            foreach ($cats as $catid => $maxscore) {

                $userscore = 0 + @$this->categoryresults[$userid][$parentid][$catid];

                $catgraphdata[$catid] = $userscore / $maxscore * 100;

                $this->seriecolors[] = '#4BB2C5';
                $this->ticks[] = str_replace("'", "\\'", $this->catnames[$catid]);
                $catmaxscore += $maxscore;
                $catuserscore += $userscore;
            }
            $this->graphdata[$userid][] = array(strtoupper(str_replace("'", "\\'", $this->catnames[$parentid])), ($catmaxscore) ? $catuserscore / $catmaxscore * 100 : 0);

            // Add all cats.
            foreach ($catgraphdata as $catid => $data) {
                $catname = $this->catnames[$catid];
                while (in_array($catname, $CATNAMECACHE)) {
                    $catname .= ' ';
                }
                $CATNAMECACHE[] = $catname;
                $this->graphdata[$userid][] = array(str_replace("'", "\\'", $catname), $data);
            }
        }
    }

    /**
     * Build data structure for question category graph.
     */
    public function build_category_graphdata($catid, $isparent = false, $perpage = 35, $userpage) {
        global $DB;

        if (empty($this->categories)) {
            return;
        }

        $CATNAMECACHE = array();

        if ($isparent) {
            if (array_key_exists($catid, $this->parentresults))  {
                $resultsource = $this->parentresults[$catid];
            } else {
                $resultsource = [];
            }
        } else {
            $parentid = $DB->get_field('question_categories', 'parent', ['id' => $catid]);
            $resultsource = @$this->categoryresults[$parentid][$catid];
            if (empty($resultsource)) {
                $resultsource = [];
            }
        }

        $this->ticks = [];

        $maxscore = 0; // max score among users in the category.
        foreach ($resultsource as $userid => $result) {
            $userscore = 0 + @$this->categoryresults[$parentid][$catid][$userid];
            if ($userscore > $maxscore) {
                $maxscore = $userscore;
            }
        }

        $this->graphdata[$catid] = [];
        foreach ($this->users as $userid => $user) {
            $usermaxscore = 0;
            $catuserscore = 0;
            $catgraphdata = array();
            $userscore = 0 + @$this->categoryresults[$parentid][$catid][$userid];
            $userscoreratio = ($maxscore) ? $userscore / $maxscore * 100 : 0;

            $this->ticks[] = fullname($this->users[$userid]);
            $this->graphdata[$catid][] = array(fullname($user), $userscoreratio);

        }
        $sort = optional_param('sort', 'byname', PARAM_TEXT);
        usort($this->graphdata[$catid], 'block_auditquiz_results::sort_'.$sort);

        $this->graphdata[$catid] = array_slice($this->graphdata[$catid], $perpage * $userpage, $perpage, false);

        // Post resolve colors.
        foreach ($this->graphdata[$catid] as $userscore) {
            $this->seriecolors[$catid][] = $this->resolve_user_color($userscore[1]);
        }
    }

    /**
     * build a graph descriptor, taking some defaults decisions
     *
     */
    public function graph_properties($seriecolors) {

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

        $PAGE->requires->js_call_amd('block_auditquiz_results/auditquiz_results', 'init');
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
    static public function get_enrollable_courses($userid = null) {
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

        // Collect unique list of possible courses.
        $courses = array();
        if (!empty($possibles)) {
            $courseids = array();
            foreach ($possibles as $e) {
                if (!in_array($e->cid, $courseids)) {
                    $fields = 'id,shortname,fullname,visible,summary,sortorder,category';
                    $courses[$e->cid] = $DB->get_record('course', array('id' => $e->cid), $fields);
                    $params = array('id' => $courses[$e->cid]->category);
                    $courses[$e->cid]->ccsortorder = $DB->get_field('course_categories', 'sortorder', $params);
                    $courseids[] = $e->cid;
                }
            }
        }

        return $courses;
    }

    /**
     * Get the mapping information for the current bloc instance.
     */
    public function get_mappings($categoryid = 0) {
        global $DB;

        $map = array();
        if ($categoryid) {
            $params = array('blockid' => $this->instance->id, 'questioncategoryid' => $categoryid);
            $mappings = $DB->get_records('block_auditquiz_mappings', $params);
        } else {
            $params = array('blockid' => $this->instance->id);
            $mappings = $DB->get_records('block_auditquiz_mappings', $params);
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
    public function get_linked_courses($categoryid) {
        global $DB;

        $map = $this->get_mappings();
        $mappedcourses = array();
        if (!empty($map)) {
            if (array_key_exists($categoryid, $map)) {
                foreach ($map[$categoryid] as $cid) {
                    $fields = 'id, shortname, fullname, visible';
                    $mappedcourses[$cid] = $DB->get_record('course', array('id' => $cid), $fields);
                }
            }
        }

        return $mappedcourses;
    }

    /**
     * Resolves the user color depending on:
     * user result (ratio)
     * number of thresholds (block instance config)
     * global setting color list if defined, overriding defaults.
     * @param int $resultratio the user's result ratio in a category.
     */
    public function resolve_user_color($resultratio) {

        $config = get_config('block_auditquiz_results');
        if (!empty($config->alternatelevelcolors)) {
            $alternativecolors = explode(',', $config->alternatelevelcolors);
        }

        // One threshold.
        $defaultcolors = [
            1 => [
                '#F03030'
            ],

            2 => [
                '#F03030',
                '#40A040',
            ],

            3 => [
                '#F03030',
                '#F0A000',
                '#40A040',
            ],

            4 => [
                '#F03030',
                '#F0A000',
                '#D0D000',
                '#40A040',
            ]
        ];

        if (!empty($this->config->passrate3) && ($this->config->passrate3 > 0)) {
            $level = 4;
        } else if (!empty($this->config->passrate2) && ($this->config->passrate2 > 0)) {
            $level = 3;
        } else if (!empty($this->config->passrate1) && ($this->config->passrate1 > 0)) {
            $level = 2;
        } else {
            $level = 1;
        }

        if (!empty($this->config->passrate1) && ($resultratio < $this->config->passrate1)) {
            $resultlevel = 0;
        } else if (!empty($this->config->passrate2) && ($resultratio < $this->config->passrate2)) {
            $resultlevel = 1;
        } else if (!empty($this->config->passrate3) && ($resultratio < $this->config->passrate3)) {
            $resultlevel = 2;
        } else {
            $resultlevel = 3;
        }

        if ($resultlevel > $level - 1) {
            // Normalize and cap value against max available levels.
            $resultlevel = $level - 1;
        }

        if (isset($alternativecolors[$resultlevel])) {
            return $alternativecolors[$resultlevel];
        }
        return $defaultcolors[$level][$resultlevel];
    }

    public function sort_byname($a, $b) {
        if ($a[0] > $b[0]) {
            return 1;
        }
        if ($a[0] < $b[0]) {
            return -1;
        }
        return 0;
    }

    public function sort_byscore($a, $b) {
        if ($a[1] > $b[1]) {
            return -1;
        }
        if ($a[1] < $b[1]) {
            return 1;
        }
        return 0;
    }
}
