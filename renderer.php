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

    public function dashboard($theblock) {

        $str = '';

        $str .= "
        <style type=\"text/css\" media=\"screen\">
        .jqplot-axis {
            font-size: 0.85em;
        }
        .jqplot-point-label {
            border: 1.5px solid #aaaaaa;
            padding: 1px 3px;
            background-color: #eeccdd;
        }
        </style>";

        $properties = $theblock->graph_properties($theblock->seriecolors);
        $data = array($theblock->graphdata);
        $str .= html_writer::start_div('auditquiz-results-graph');
        $str .= local_vflibs_jqplot_print_graph('auditquiz-result-'.$theblock->instance->id, $properties, $data,
                                                $theblock->config->width, $theblock->config->height, $addstyle = '',
                                                true, $theblock->ticks);
        $str .= html_writer::end_div();

        return $str;
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

        $str = '';

        $formurl = new moodle_url('/blocks/auditquiz_results/export.php');
        $str .= '<form style="display: inline;" action="'.$formurl.'" method="get" target="_blank">';
        $str .= '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
        $str .= '<input type="hidden" name="id" value="'.$theblock->instance->id.'" />';
        $str .= '<input type="hidden" name="user" value="'.$user->id.'" />';

        $label = get_string('exportpdfdetail', 'block_auditquiz_results') ;
        $str .= ' <input type="submit" name="export" value="'.$label.'" />';
        $str .= '</form>';

        return $str;
    }

    /**
     * prints an html displayable table of all results with categories.
     */
    public function htmlreport(&$theblock) {

        if (empty($theblock->config->enablecoursemapping)) {
            return;
        }

        $str = '';

        $str .= $this->output->heading(get_string('detail', 'block_auditquiz_results'));

        $catnamestr = get_string('catname', 'block_auditquiz_results');
        $scorestr = get_string('score', 'block_auditquiz_results');
        $maxscorestr = get_string('maxscore', 'block_auditquiz_results');
        $linkedcoursesstr = get_string('courses');
        $passstr = get_string('pass', 'block_auditquiz_results', $theblock->config->passrate);
        $pass1str = get_string('lowpass', 'block_auditquiz_results', $theblock->config->passrate);
        $pass2str = get_string('highpass', 'block_auditquiz_results', $theblock->config->passrate2);

        if ($theblock->config->passrate) {
            $str .= '<div class="auditquiz-html-output-passrates">';
            if ($theblock->config->passrate2) {
                $str .= '<div class="auditquiz-html-output-lowpass pull-left">';
                $str .= $pass1str;
                $str .= '</div>';
                $str .= '<div class="auditquiz-html-output-highpass pull-right">';
                $str .= $pass2str;
                $str .= '</div>';
            } else {
                $str .= '<div class="auditquiz-html-output-pass">';
                $str .= $passstr;
                $str .= '</div>';
            }
            $str .= '</div>';
        }

        $str .= '<div class="auditquiz-html-output">';
        if (!empty($theblock->categories)) {
            $i = 0;
            foreach ($theblock->categories as $pid => $subcats) {
                $str .= '<div class="auditquiz-parenttopic">';
                $str .= $theblock->catnames[$pid];
                $str .= '</div>';

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
                    $str .= html_writer::table($table);
                }
            }
        }

        $str .= '</div>';
        return $str;
    }

    public function format_linked_courses(&$theblock, $linkedcourses, $passstate) {

        if (empty($linkedcourses)) {
            return '';
        }

        $str = '';
        foreach ($linkedcourses as $c) {

            if (empty($theblock->config->proposeenrolonsuccess) && ($passstate == 'success')) {
                // No matter to propose enrol.
                continue;
            }

            $ctx = context_course::instance($c->id);
            if ($c->visible || has_capability('moodle/course:viewhiddencourses', $ctx)) {
                $class = ($c->visible) ? '' : 'shadow';
                if (is_enrolled($ctx)) {
                    $courseurl = new moodle_url('/course/view.php', array('id' => $c->id));
                    $str .= '<div class="auditquiz-courseblock '.$class.'">';
                    $str .= '<div class="auditquiz-coursename '.$class.'"><a href="'.$courseurl.'">'.format_string($c->fullname).'</a></div>';
                    $str .= '</div>';
                } else {
                    if (enrol_selfenrol_available($c->id)) {
                        $str .= '<div class="auditquiz-courseblock '.$class.'">';
                        $str .= '<div class="auditquiz-coursename">'.format_string($c->fullname).'</div>';
                        $str .= $this->output->single_button(new moodle_url('/course/view.php', array('id' => $c->id)), get_string('applyto'.$passstate, 'block_auditquiz_results'));
                        $str .= '<div class="auditquiz-instr">';
                        $str .= get_string('applyto'.$passstate.'_desc', 'block_auditquiz_results');
                        $str .= '</div>';
                        $str .= '</div>';
                    } else {
                        // Do NOT show courses you cannot self enrol in.
                        /*
                        $str .= '<div class="auditquiz-courseblock '.$class.'">';
                        $str .= '<div class="auditquiz-coursename '.$class.'">'.format_string($c->fullname).'</div>';
                        $str .= '</div>';
                        */
                    }
                }
            }
        }

        return $str;
    }

    /**
     * Called by the course to queston categories mapping backoffice.
     * @param object $theblock the block instance
     * @param arrayref $mappings the mapping table
     */
    public function categories_mapping(&$theblock, &$mappings) {
        global $DB, $CFG;

        $str = '';

        $str .= '<div class="auditquiz-mapper-catlist">';

        foreach ($theblock->categories as $parentid => $children) {
            $str .= '<div class="parent-category">';
            $str .= '<div class="parent-name">';
            $str .= '<h2>'.$theblock->catnames[$parentid].'</h2>';
            $str .= '</div>';

            foreach ($children as $catid => $foo) {
                $str .= '<div class="cat-mapping">';
                $str .= '<div class="cat-name">';
                $str .= '<h3>'.$theblock->catnames[$catid].'</h3>';
                $str .= '</div>';

                $url = new moodle_url('/blocks/auditquiz_results/mapcategory.php', array('id' => $theblock->instance->id, 'qcatid' => $catid));
                $str .= '<div class="auditquiz-results add-courses" style="float:right">';
                $str .= $this->output->single_button($url, get_string('addcourses', 'block_auditquiz_results'));
                $str .= '</div>';

                $deletestr = get_string('unlinkcourse', 'block_auditquiz_results');
                $enrolmethodsstr = get_string('enrolmethods', 'block_auditquiz_results');

                if (array_key_exists($catid, $mappings)) {
                    foreach ($mappings[$catid] as $courseid) {
                        $course = $DB->get_record('course', array('id' => $courseid), 'id,shortname,fullname,visible,category');
                        $category = $DB->get_field('course_categories', 'name', array('id' => $course->category));
                        $coursecontext = context_course::instance($course->id);
                        $selfenrollable = false;
                        $selfenrol = $DB->get_record('enrol', array('courseid' => $courseid, 'enrol' => 'self'));
                        if (!$selfenrol) {
                            continue;
                        }
                        $selfenrollable = $selfenrol->status == 0;
                        $class = (!$course->visible || !$selfenrollable) ? 'shadow' : '';
                        $str .= '<div class="course-name '.$class.'" id="coursebinding'.$catid.'_'.$courseid.'">';
                        $str .= $category.' / ['.$course->shortname.'] '.$course->fullname;
                        $str .= '<div class="course-commands">';

                        if (has_capability('moodle/course:enrolconfig', $coursecontext)) {
                            $img = '<img src="'.$this->output->pix_url('t/enrolusers').'">';
                            $enrolurl = new moodle_url('/enrol/instances.php', array('id' => $courseid));
                            $str .= '&nbsp;&nbsp;<a href="'.$enrolurl.'" title="'.$enrolmethodsstr.'">'.$img.'</a>';
                        }

                        $img = '<img src="'.$this->output->pix_url('t/delete').'">';
                        $str .= '&nbsp;<a href="javascript:ajax_unbind_course(\''.$theblock->instance->id.'\', \''.$catid.'\', \''.$course->id.'\', \''.$CFG->wwwroot.'\')" title="'.$deletestr.'">'.$img.'</a>';
                        $str .= '</div>';
                        $str .= '</div>';
                    }
                } else {
                        $str .= '<div class="no-courses">';
                        $str .= $this->output->notification(get_string('nocourses', 'block_auditquiz_results'));
                        $str .= '</div>';

                }

                $str .= '</div>';
            }
        }

        $str .= '</div>';

        return $str;
    }

}