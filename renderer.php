<?php
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

require_once($CFG->dirroot.'/local/vflibs/jqplotlib.php');
require_once($CFG->dirroot.'/blocks/auditquiz_results/lib.php');

class block_auditquiz_results_renderer extends plugin_renderer_base {

    /*
     * Assemble and renders jqplot dashboard for a user.
     */
    public function dashboard($theblock, $userid, $midheight = false) {

        $template = new StdClass;

        $context = context_block::instance($theblock->instance->id);
        if (block_auditquiz_results_supports_feature('graph/snapshot')) {
            if (has_capability('block/auditquiz_results:seeother', $context)) {
                // $template->cansnapshot = true; // Not yet ready html2canvas integration issues.
                $template->snapshoticon = $this->output->pix_icon('f/jpeg-128', '');
                $this->snapshotlist($template, $context, $userid, 'user'); // Pursuing we are an extended pro renderer.
                $template->arealink = $this->filearea_link('byuser', $userid, $theblock->instance->id);
                $template->snapshotstr = get_string('makesnapshot', 'block_auditquiz_results');
                $template->cansnapshot = true;
            }
        }

        $height = $theblock->config->height;
        if ($midheight) {
            $height = round($height / 2);
        }

        $properties = $theblock->graph_properties($theblock->seriecolors);
        $data = array($theblock->graphdata[$userid]);
        $template->blockid = $theblock->instance->id;
        $template->itemid = $userid;
        $template->type = 'user';
        $template->plot = local_vflibs_jqplot_print_graph('auditquiz-result-'.$theblock->instance->id.'-'.$userid, $properties, $data,
                                                $theblock->config->width, $height, $addstyle = '',
                                                true, $theblock->ticks);

        return $this->output->render_from_template('block_auditquiz_results/jqplot_dashboard', $template);
    }

    /*
     * Assemble and renders jqplot dashboard for a category.
     */
    public function dashboard_category($theblock, $catid) {

        $template = new StdClass;

        $context = context_block::instance($theblock->instance->id);
        if (block_auditquiz_results_supports_feature('graph/snapshot')) {
            if (has_capability('block/auditquiz_results:seeother', $context)) {
                // $template->cansnapshot = true; // Not yet ready html2canvas integration issues.
                $template->snapshoticon = $this->output->pix_icon('f/jpeg-128', '');
                $this->snapshotlist($template, $context, $catid, 'category'); // Pursuing we are an extended pro renderer.
                $template->arealink = $this->filearea_link('bycategory', $catid, $theblock->instance->id);
                $template->snapshotstr = get_string('makesnapshot', 'block_auditquiz_results');
                $template->cansnapshot = true;
            }
        }

        $properties = $theblock->graph_properties($theblock->seriecolors[$catid]);
        $data = array($theblock->graphdata[$catid]);
        $template->blockid = $theblock->instance->id;
        $template->itemid = $catid;
        $template->type = 'category';
        $template->plot = local_vflibs_jqplot_print_graph('auditquiz-category-result-'.$theblock->instance->id.'-'.$catid, $properties, $data,
                                                $theblock->config->width, $theblock->config->height, $addstyle = '',
                                                true, $theblock->ticks);

        return $this->output->render_from_template('block_auditquiz_results/jqplot_dashboard', $template);
    }

    public function userselector() {
        global $COURSE, $USER;

        $context = context_course::instance($COURSE->id);
        $users = get_enrolled_users($context);
        $userarr = array();
        foreach ($users as $uid => $u) {
            $userarr[$uid] = fullname($u);
        }

        // Fix the user lost for admins
        if (!array_key_exists($USER->id, $userarr)) {
            $userarr[$USER->id] = fullname($USER);
        }
        asort($userarr);

        $me = new moodle_url(me());
        $me->remove_params('userselect');
        $select = new single_select($me, 'userselect', $userarr, optional_param('userselect', $USER->id, PARAM_INT), null, $formid = 'form-user-select');
        return $this->output->render($select).'<br/>';
    }

    /**
     * Renders the technical details of question data for analysis and debugging.
     * @param object $theblock the quditquiz_results block instance
     */
    public function data($theblock) {

        $template = new StdClass;
        $template->questions = $theblock->loadedquestions;

        foreach($theblock->catnames as $cid => $cname) {
            $cattpl = new StdClass;
            $cattpl->id = $cid;
            $cattpl->name = $name;
            $cattpl->score = $theblock->categories[$q->parentid][$q->categoryid];
            $template->categories[] = $cattpl;
        }

        return $this->output->render_from_template('block_auditquiz_results/data', $template);
    }

    /**
     * prints an html displayable table of all results with categories.
     */
    public function htmlreport(&$theblock) {

        if (empty($theblock->config->enablecoursemapping)) {
            return;
        }

        $template = new StdClass;

        $template->detailstr = get_string('detail', 'block_auditquiz_results');

        $catnamestr = get_string('catname', 'block_auditquiz_results');
        $scorestr = get_string('score', 'block_auditquiz_results');
        $maxscorestr = get_string('maxscore', 'block_auditquiz_results');
        $linkedcoursesstr = get_string('courses');

        $template->pass1str = get_string('pass', 'block_auditquiz_results', $theblock->config->passrate1);

        if (!empty($theblock->config->passrate2)) {
            $template->pass1str = get_string('lowpass', 'block_auditquiz_results', $theblock->config->passrate1);
            $template->pass2str = get_string('highpass', 'block_auditquiz_results', $theblock->config->passrate2);

            if (!empty($theblock->config->passrate3)) {
                $template->pass2str = get_string('midpass', 'block_auditquiz_results', $theblock->config->passrate2);
                $template->pass3str = get_string('highpass', 'block_auditquiz_results', $theblock->config->passrate3);
            }
        }

        if (!empty($theblock->categories)) {
            $i = 0;
            foreach ($theblock->categories as $pid => $subcats) {
                $categorytpl = new StdClass;
                $categorytpl->categoryname = $theblock->catnames[$pid];

                if (!empty($subcats)) {
                    $table = new html_table();
                    $table->head = array($catnamestr, $scorestr, $maxscorestr, '');
                    $table->align = array('left', 'left', 'left', 'center');
                    $table->size = array('40%', '20%', '20%', '20%');
                    $table->width = '100%';

                    if (!empty($theblock->config->enablecoursemapping)) {
                        $table->head[] = $linkedcoursesstr;
                        $table->align[] = 'left';
                        $table->size = array('30%', '10%', '10%', '10%','30%');
                    }

                    foreach ($subcats as $cid => $catdata) {

                        $result = '';
                        if (!array_key_exists($pid, $theblock->categoryresults)) {
                            // No results for this parent.
                            $table->data['r'.$i] = array($theblock->catnames[$cid], '', '', '', '');
                            $i++;
                            continue;
                        }
                        if (!array_key_exists($cid, $theblock->categoryresults[$pid])) {
                            // No results for this question category
                            $table->data['r'.$i] = array($theblock->catnames[$cid], '', '', '', '');
                            $i++;
                            continue;
                        }

                        $result = $theblock->categoryresults[$pid][$cid];

                        $passstate = '';
                        if ((($result * 100) / $catdata) >= $theblock->config->passrate) {
                            if (empty($theblock->config->passrate2)) {
                                // If the second rate is not used, just switch with rate 2
                                $passstate = 'success';
                                $icon = $this->output->pix_url('success', 'block_auditquiz_results');
                            } else if (empty($theblock->config->passrate2)) {
                                if ((($result * 100) / $catdata) >= $theblock->config->passrate2) {
                                    $passstate = 'success';
                                    $icon = $this->output->pix_url('success', 'block_auditquiz_results');
                                } else {
                                    $passstate = 'regular';
                                    $icon = $this->output->pix_url('regular', 'block_auditquiz_results');
                                }
                            } else {
                                if ((($result * 100) / $catdata) >= $theblock->config->passrate3) {
                                    $passstate = 'success';
                                    $icon = $this->output->pix_url('success', 'block_auditquiz_results');
                                } else if ((($result * 100) / $catdata) >= $theblock->config->passrate2) {
                                    $passstate = 'regular';
                                    $icon = $this->output->pix_url('regular', 'block_auditquiz_results');
                                } else {
                                    $passstate = 'insufficiant';
                                    $icon = $this->output->pix_url('insufficiant', 'block_auditquiz_results');
                                }
                            }
                        } else {
                            $passstate = 'failed';
                            $icon = $this->output->pix_url('failure', 'block_auditquiz_results');
                        }
                        $img = '<img src="'.$icon.'">';

                        $table->data['r'.$i] = array($theblock->catnames[$cid], $result, $catdata, $img);

                        if ($theblock->config->enablecoursemapping) {
                            $linkedcourses = $theblock->get_linked_courses($cid);
                            $linkedcoursesoutput = $this->format_linked_courses($theblock, $linkedcourses, $passstate);
                            $table->data['r'.$i][] = $linkedcoursesoutput;
                        }

                        $i++;
                    }
                    $categorytpl->subcategorytable = html_writer::table($table);
                    $template->categories[] = $categorytpl;
                }
            }
        }

        return $this->output->render_from_template('block_auditquiz_results/htmlreport', $template);
    }

    public function format_linked_courses(&$theblock, $linkedcourses, $passstate) {

        if (empty($linkedcourses)) {
            return '';
        }

        $template = new StdClass;
        $template->applytostr = get_string('applyto'.$passstate.'_desc', 'block_auditquiz_results');

        foreach ($linkedcourses as $c) {

            if (empty($theblock->config->proposeenrolonsuccess) && ($passstate == 'success')) {
                // No matter to propose enrol.
                continue;
            }

            $coursetpl = new StdClass;
            $ctx = context_course::instance($c->id);
            if ($c->visible || has_capability('moodle/course:viewhiddencourses', $ctx)) {
                $coursetpl->class = ($c->visible) ? '' : 'shadow';
                $coursetpl->fullname = format_string($c->fullname);
                if (is_enrolled($ctx)) {
                    $coursetpl->courseurl = new moodle_url('/course/view.php', array('id' => $c->id));
                } else {
                    if (enrol_selfenrol_available($c->id)) {
                        $buttonurl = new moodle_url('/course/view.php', array('id' => $c->id));
                        $label = get_string('applyto'.$passstate, 'block_auditquiz_results');
                        $coursetpl->enrolbutton = $this->output->single_button($buttonurl, $label);
                    }
                }
            }

            $template->linkedcourses[] = $coursetpl;
        }

        return $this->output->render_from_template('block_auditquiz_results/linkedcourses', $template);
    }

    /**
     * Called by the course to queston categories mapping backoffice.
     * @param object $theblock the block instance
     * @param arrayref $mappings the mapping table
     */
    public function categories_mapping(&$theblock, &$mappings) {
        global $DB, $CFG;

        $template = new StdClass;

        $template->deletestr = get_string('unlinkcourse', 'block_auditquiz_results');
        $template->enrolmethodsstr = get_string('enrolmethods', 'block_auditquiz_results');
        $template->enroliconurl = $this->output->pix_icon('t/enrolusers', get_string('enrolusers', 'block_auditquiz_results'), 'core');
        $template->deleteicon = $this->output->pix_icon('t/delete', get_string('delete'), 'core');
        $template->nocourses = $this->output->notification(get_string('nocourses', 'block_auditquiz_results'));
        $template->blockid = $theblock->instance->id;

        foreach ($theblock->categories as $parentid => $children) {
            $categorytpl = new StdClass;
            $categorytpl->categoryname = $theblock->catnames[$parentid];

            foreach ($children as $catid => $foo) {
                $childtpl = new StdClass;
                $childtpl->childname = $theblock->catnames[$catid];

                $url = new moodle_url('/blocks/auditquiz_results/mapcategory.php', array('id' => $theblock->instance->id, 'qcatid' => $catid));
                $childtpl->addcoursesbutton = $this->output->single_button($url, get_string('addcourses', 'block_auditquiz_results'));
                $childtpl->catid = $catid;

                if (array_key_exists($catid, $mappings)) {
                    foreach ($mappings[$catid] as $courseid) {
                        $mappingtpl = new StdClass;
                        $mappingtpl->courseid = $courseid;
                        $course = $DB->get_record('course', array('id' => $courseid), 'id,shortname,fullname,visible,category');
                        $category = $DB->get_field('course_categories', 'name', array('id' => $course->category));
                        $coursecontext = context_course::instance($course->id);
                        $selfenrollable = false;
                        $selfenrol = $DB->get_record('enrol', array('courseid' => $courseid, 'enrol' => 'self'));
                        if (!$selfenrol) {
                            continue;
                        }
                        $selfenrollable = $selfenrol->status == 0;
                        $mappingtpl->class = (!$course->visible || !$selfenrollable) ? 'shadow' : '';
                        $mappingtpl->coursefullname = $category.' / ['.$course->shortname.'] '.$course->fullname;

                        if (has_capability('moodle/course:enrolconfig', $coursecontext)) {
                            $mappingtpl->enrolurl = new moodle_url('/enrol/instances.php', array('id' => $courseid));
                        }

                        $childtpl->mappings[] = $mappingtpl;
                    }
                }
                $categorytpl->children[] = $childtpl;
            }
            $template->categories[] = $categorytpl;
        }

        return $this->output->render_from_template('block_auditquiz_results/categoriesmapping', $template);
    }

    public function assigncourseform($blockid, $qcatid, $assignedcoursesselector, $potentialcoursesselector) {
        global $PAGE, $OUTPUT;

        $template = new StdClass;
        $template->formurl = new moodle_url($PAGE->url, array('blockid' => $blockid, 'qcatid' => $qcatid));
        $template->sesskey = sesskey();
        $template->blockid = $blockid;
        $template->qcatid = $qcatid;
        $template->extcoursesstr = get_string('extcourses', 'block_auditquiz_results');
        $template->assignedcoursesselector = $assignedcoursesselector->display(true);
        $template->larrowstr = $OUTPUT->larrow().'&nbsp;'.get_string('add');
        $template->titleadd = get_string('add');
        $template->rarrowstr = get_string('remove').'&nbsp;'.$OUTPUT->rarrow();
        $template->titleremove = get_string('remove');
        $template->postcoursesstr = get_string('potcourses', 'block_auditquiz_results');
        $template->potentialcoursesselector = $potentialcoursesselector->display(true);

        return $this->output->render_from_template('block_auditquiz_results/assigncoursesform', $template);
    }

    public function snapshotlist($template, $context, $userid) {
        // Pro version provides only.
        assert(1);
    }

    public function filearea_link($view, $itemid, $blockid) {
        // Pro version provides only.
        assert(1);
    }

    /**
     * Main course report rendering.
     */
    public function course_report($reportdata) {
        $mustache = 'block_auditquiz_results/coursereport_'.$reportdata->view;

        $template = new StdClass;

        if ($reportdata->view == 'byuser') {
            // Maps internal result data for template.
            if (!empty($reportdata->blockinstance->users)) {
                foreach ($reportdata->blockinstance->users as $uid => $usertpl) {
                    $usertpl->graph = $this->dashboard($reportdata->blockinstance, $uid);
                    $template->users[] = $usertpl;
                }
            } else {
                $template->nodatanotification = $this->output->notification(get_string('nousers', 'block_auditquiz_results'));
            }
        } else if ($reportdata->view == 'bycategory') {
            if (!empty($reportdata->blockinstance->categories)) {
                foreach ($reportdata->blockinstance->categories as $parentid => $parentcats) {
                    $cattpl = new StdClass;
                    $cattpl->catid = $parentid;
                    $cattpl->name = $reportdata->blockinstance->catnames[$parentid];
                    $cattpl->graph = $this->dashboard_category($reportdata->blockinstance, $parentid);
                    $cattpl->isparent = true;

                    $template->categories[] = $cattpl;

                    foreach (array_keys($parentcats) as $catid) {
                        $cattpl = new StdClass;
                        $cattpl->catid = $catid;
                        $cattpl->name = $reportdata->blockinstance->catnames[$catid];
                        $cattpl->graph = $this->dashboard_category($reportdata->blockinstance, $catid);
                        $cattpl->isparent = false;
                        $template->categories[] = $cattpl;
                    }
                }
            } else {
                $template->nodatanotification = $this->output->notification(get_string('noquestions', 'block_auditquiz_results'));
            }
        } else {
            echo $OUTPUT->notification("Unsupported report range", 'error');
        }

        return $this->output->render_from_template($mustache, $template);
    }

    public function course_report_tabs($blockid, $view) {
        global $COURSE;

        $tabrows = [];

        $tabname = get_string('bycategory', 'block_auditquiz_results');
        $params = array('view' => 'bycategory', 'id' => $COURSE->id, 'blockid' => $blockid);
        $taburl = new moodle_url('/blocks/auditquiz_results/coursereport.php', $params);
        $row[] = new tabobject('bycategory', $taburl, $tabname);

        $tabname = get_string('byuser', 'block_auditquiz_results');
        $params = array('view' => 'byuser', 'id' => $COURSE->id, 'blockid' => $blockid);
        $taburl = new moodle_url('/blocks/auditquiz_results/coursereport.php', $params);
        $row[] = new tabobject('byuser', $taburl, $tabname);

        $tabrows[0] = $row;

        return print_tabs($tabrows, $view, null, [], true);
    }

    public function course_report_link($blockid, $mode = 'asbutton', $extraclasses = '') {
        global $COURSE;

        $template = new StdClass;

        $options = array();
        $options['id'] = $COURSE->id;
        $options['page'] = optional_param('page', '', PARAM_INT); // In case of course page format.
        $options['blockid'] = $blockid; // Block id.
        $options['view'] = 'byuser'; // Default view.
        $template->formurl = new moodle_url('/blocks/auditquiz_results/coursereport.php', $options);
        $template->sesskey = sesskey();
        $template->classes = $extraclasses;
        $template->aslink = $mode == 'aslink';

        return $this->output->render_from_template('block_auditquiz_results/coursereportlink', $template);
    }

    /**
     * Renders the sort filter choice on "per category" report.
     */
    public function sort_users($blockid) {
        global $COURSE;

        $sortoptions = [
            'byname',
            'byscore',
        ];

        $template = new StdClass;
        $defaultfilteroption = optional_param('sort', 'byname', PARAM_TEXT);
        foreach ($sortoptions as $option) {
            $opttpl = new StdClass;
            $opttpl->value = $option;
            if ($opttpl->value != '*') {
                $opttpl->optionlabelstr = get_string($option, 'block_auditquiz_results');
            } else {
                $opttpl->optionlabelstr = get_string('everything', 'block_auditquiz_results');
            }
            $opttpl->active = $defaultfilteroption == $option; // At the moment, not bound to user preferences. Next step.
            $opttpl->optionarialabelstr = get_string('ariaviewfilteroption', 'block_auditquiz_results', $opttpl->optionlabelstr);
            $template->sortoptions[] = $opttpl;
        }
        $template->value = optional_param('sort', 'byname', PARAM_TEXT);
        $template->blockid = $blockid;
        $template->id = $COURSE->id;
        $template->view = 'bycategory';

        return $this->output->render_from_template('block_auditquiz_results/sort', $template);
    }
}