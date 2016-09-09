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
 * minimalistic edit form
 *
 * @package   block_auditquiz_results
 * @copyright 2015 Valery Fremaux (valery.fremaux@gmail.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir.'/formslib.php');

class block_auditquiz_results_edit_form extends block_edit_form {
    /**
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform) {
        global $CFG, $DB, $COURSE;

        $globalconfig = get_config('block_auditquiz_results');

        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('checkbox', 'config_studentcanseeown', get_string('configstudentcanseeown', 'block_auditquiz_results'));
        $mform->setDefault('config_studentcanseeown', 1);

        $layoutopts[0] = get_string('publishinpage', 'block_auditquiz_results');
        $layoutopts[1] = get_string('publishinblock', 'block_auditquiz_results');
        $mform->addElement('select', 'config_inblocklayout', get_string('configlayout', 'block_auditquiz_results'), $layoutopts);

        $quiztypestr = get_string('configquiztype', 'block_auditquiz_results');

        if (empty($globalconfig->modules)) {
            set_config('modules', 'quiz', 'block_auditquiz_results');
            $globalconfig->modules = 'quiz';
        }
        $quizmodules = explode(',', $globalconfig->modules);
        $options = array();
        foreach ($quizmodules as $qmname) {
            if ($quizmodule = $DB->get_record('modules', array('name' => $qmname))) {
               $options["$quizmodule->id"] = get_string('modulename', $qmname);
            }
        }

        $mform->addElement('select', 'config_quiztype', $quiztypestr, $options);

        $config = unserialize(base64_decode(@$this->block->instance->configdata));

        if (empty($config->quiztype)) {
            if (empty($config)) {
                $config = new StdClass;
            }
            $config->quiztype = $DB->get_field('modules', 'id', array('name' => array_pop($quizmodules)));
        }

        $quizidstr = get_string('configselectquiz', 'block_auditquiz_results');
        $quiztype = $DB->get_record('modules', array('id' => $config->quiztype));

        $quizzes = null;
        if (!empty($quiztype)) {
            $quizzes = $DB->get_records($quiztype->name, array('course' => $COURSE->id), '', 'id, name');
        }
        if (empty($quizzes)) {
            $mform->addElement('static', 'config_quizid_static', $quizidstr, get_string('config_no_quizzes_in_course', 'block_auditquiz_results'));
            $mform->addElement('hidden', 'config_quizid', 0);
            $mform->setType('config_quizid', PARAM_INT);
        } else {
            $options = array();
            foreach($quizzes as $quiz) {
                $cmidnumber = $DB->get_field('course_modules', 'idnumber', array('module' => $quiztype->id, 'instance' => $quiz->id));
                $options[$quiz->id] = (empty($cmidnumber)) ? $quiz->name : $quiz->name.' ('.$cmidnumber.')' ;
            }
            $select = $mform->addElement('select', 'config_quizid', $quizidstr, $options);
            $select->setMultiple(true);
            $mform->addHelpButton('config_quizid', 'quizid', 'block_auditquiz_results');
        }

        /**
        $graphtypestr = get_string('configgraphsize', 'block_auditquiz_results');
        $options = array('bar' => get_string('bar', 'block_auditquiz_results'), 'time' => get_string('time', 'block_auditquiz_results'));
        $mform->addElement('select', 'config_graphtype', $graphtypestr, $options);
        */

        $mform->addElement('text', 'config_width', get_string('width', 'block_auditquiz_results'), array('size' => '4'));
        $mform->setType('config_width', PARAM_INT);

        $mform->addElement('text', 'config_height', get_string('height', 'block_auditquiz_results'), array('size' => '4'));
        $mform->setType('config_height', PARAM_INT);

        $mform->addElement('checkbox', 'config_enablecoursemapping', get_string('configenablecoursemapping', 'block_auditquiz_results'));
        $mform->setDefault('config_enablecoursemapping', 1);

        $mform->addElement('text', 'config_passrate', get_string('configpassrate', 'block_auditquiz_results'), array('size' => '2'));
        $mform->setType('config_passrate', PARAM_INT);
        $mform->addHelpButton('config_passrate', 'configpassrate', 'block_auditquiz_results');

        $mform->addElement('text', 'config_passrate2', get_string('configpassrate2', 'block_auditquiz_results'), array('size' => '2'));
        $mform->setType('config_passrate2', PARAM_INT);
        $mform->addHelpButton('config_passrate2', 'configpassrate2', 'block_auditquiz_results');

        $mform->addElement('checkbox', 'config_proposeenrolonsuccess', get_string('configproposeenrolonsuccess', 'block_auditquiz_results'));
        $mform->setDefault('config_proposeenrolonsuccess', 1);
    }

    function validation($data, $files = null) {
        $errors = array();

        if (!empty($data->config_passrate2) && ($data->config_passrate2 <= $data->config_passrate)) {
            $errors['config_passrate2'] = get_string('errorbadscale', 'block_auditquiz_results');
        }
    }
}