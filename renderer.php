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

class block_auditquiz_results_renderer extends plugin_renderer_base {

    public function dashboard($theblock, $userid) {

        $template = new StdClass;

        $context = context_block::instance($theblock->instance->id);
        if (has_capability('block/auditquiz_results:seeother', $context)) {
            $template->cansnapshot = true;
        }

        $template->snapshotstr = get_string('makesnapshot', 'block_auditquiz_results');
        $properties = $theblock->graph_properties($theblock->seriecolors);
        $data = array($theblock->graphdata);
        $template->blockid = $theblock->instance->id;
        $template->userid = $userid;
        $template->plot = local_vflibs_jqplot_print_graph('auditquiz-result-'.$theblock->instance->id, $properties, $data,
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

    public function data($theblock) {

        $str = '';
        $questions = '';
        $categories = '';

        foreach($theblock->loadedquestions as $q) {
            $questions .= "ID: $q->questionid<br/>Text: $q->name<br/>Cat: $q->parent/$q->category<br/><br/>";
        }

        foreach($theblock->catnames as $cid => $cname) {
            $categories .= "ID: $cid<br/>Text: $cname<br/>Score: ".$theblock->categories[$q->parentid][$q->categoryid].'<br/><br/>';
        }

        $str .= '<table width="100%">';
        $str .= '<tr valign="top"><td>Questions</td><td>Categories</td></tr>';
        $str .= '</tr><tr valign="top">';
        $str .= '<td>'.$questions.'</td><td>'.$categories.'</td></tr>';
        $str .= '</table>';

        return $str;
    }

    public function print_export_pdf_button(&$theblock, &$user, $format = 'pdf') {

        $context = context_block::instance($block->instance->id);
        if (!has_capability('block/auditquiz_results:export', $context)) {
            return;
        }

        $template = new StdClass;

        $template->formurl = new moodle_url('/blocks/auditquiz_results/export.php');
        $template->sesskey = sesskey();
        $template->blockid = $theblock->instance->id;
        $template->userid = $user->id;
        $template->label = get_string('exportpdfdetail', 'block_auditquiz_results') ;

        return $this->output->render_from_template('block_auditquiz_results/exportpdfbutton', $template);
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
        $template->passstr = get_string('pass', 'block_auditquiz_results', $theblock->config->passrate);
        $template->pass1str = get_string('lowpass', 'block_auditquiz_results', $theblock->config->passrate);
        $template->pass2str = get_string('highpass', 'block_auditquiz_results', $theblock->config->passrate2);

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
                            // No results for this parent
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
                        if ((($result * 100)/$catdata) >= $theblock->config->passrate) {
                            if (empty($theblock->config->passrate2)) {
                                // If the second rate is not used, just switch with rate 1
                                $passstate = 'success';
                                $icon = $this->output->pix_url('success', 'block_auditquiz_results');
                            } else {
                                if ((($result * 100)/$catdata) >= $theblock->config->passrate) {
                                    $passstate = 'success';
                                    $icon = $this->output->pix_url('success', 'block_auditquiz_results');
                                } else {
                                    $passstate = 'regular';
                                    $icon = $this->output->pix_url('regular', 'block_auditquiz_results');
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
        $template->enroliconurl = $this->output->pix_url('t/enrolusers');
        $template->deleteiconurl = $this->output->pix_url('t/delete');
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
        $template->raarowstr = get_string('remove').'&nbsp;'.$OUTPUT->rarrow();
        $template->titleremove = get_string('remove');
        $template->postcoursesstr = get_string('potcourses', 'block_auditquiz_results');
        $template->potentialcoursesselector = $potentialcoursesselector->display(true);

        return $this->output->render_from_template('block_auditquiz_results/assigncoursesform', $template);
    }
}