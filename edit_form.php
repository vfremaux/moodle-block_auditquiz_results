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
 * minimalistic edit form
 *
 * @package   block_auditquiz_results
 * @copyright 2015 Valery Fremaux (valery.fremaux@gmail.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class block_auditquiz_results_edit_form extends block_edit_form {

    /**
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform) {
        global $CFG, $DB, $COURSE;

        $globalconfig = get_config('block_auditquiz_results');

        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $label = get_string('configstudentcanseeown', 'block_auditquiz_results');
        $mform->addElement('advcheckbox', 'config_studentcanseeown', $label);
        $mform->setDefault('config_studentcanseeown', 0);

        $layoutopts[0] = get_string('publishinpage', 'block_auditquiz_results');
        $layoutopts[1] = get_string('publishinblock', 'block_auditquiz_results');
        $label = get_string('configlayout', 'block_auditquiz_results');
        $mform->addElement('select', 'config_inblocklayout', $label, $layoutopts);

        $quiztypestr = get_string('configquiztype', 'block_auditquiz_results');

        if (empty($globalconfig->modules)) {
            set_config('modules', 'quiz', 'block_auditquiz_results');
            $globalconfig->modules = 'quiz';
        }
        $quizmodules = explode(',', $globalconfig->modules);
        $options = array();
        foreach ($quizmodules as $qmname) {
            if ($quizmodule = $DB->get_record('modules', array('name' => $qmname))) {
               $options["$quizmodule->name"] = get_string('modulename', $qmname);
            }
        }

        $mform->addElement('select', 'config_quiztype', $quiztypestr, $options);

        $config = unserialize(base64_decode(@$this->block->instance->configdata));

        if (empty($config->quiztype) || is_numeric($config->quiztype)) {
            $config->quiztype = 'quiz';
        }

        if (empty($config->quiztype)) {
            if (empty($config)) {
                $config = new StdClass;
            }
            $config->quiztype = array_pop($quizmodules);
        }

        $quizidstr = get_string('configselectquiz', 'block_auditquiz_results');
        $quiztype = $DB->get_record('modules', array('name' => $config->quiztype));

        $quizzes = null;
        if (!empty($quiztype)) {
            $quizzes = $DB->get_records($quiztype->name, array('course' => $COURSE->id), '', 'id, name');
        }
        if (empty($quizzes)) {
            $label = get_string('confignoquizzesincourse', 'block_auditquiz_results');
            $mform->addElement('static', 'config_quizid_static', $quizidstr, $label);
            $mform->addElement('hidden', 'config_quizid', 0);
            $mform->setType('config_quizid', PARAM_INT);
        } else {
            $options = array();
            foreach($quizzes as $quiz) {
                $params = array('module' => $quiztype->id, 'instance' => $quiz->id);
                $cmidnumber = $DB->get_field('course_modules', 'idnumber', $params);
                $options[$quiz->id] = (empty($cmidnumber)) ? $quiz->name : $quiz->name.' ('.$cmidnumber.')';
            }
            $select = $mform->addElement('select', 'config_quizid', $quizidstr, $options);
            $select->setMultiple(true);
            $mform->addHelpButton('config_quizid', 'quizid', 'block_auditquiz_results');
        }

        $mform->addElement('text', 'config_width', get_string('width', 'block_auditquiz_results'), array('size' => '4'));
        $mform->setType('config_width', PARAM_INT);

        $mform->addElement('text', 'config_height', get_string('height', 'block_auditquiz_results'), array('size' => '4'));
        $mform->setType('config_height', PARAM_INT);

        $label = get_string('configenablecoursemapping', 'block_auditquiz_results');
        $mform->addElement('advcheckbox', 'config_enablecoursemapping', $label);
        $mform->setDefault('config_enablecoursemapping', 0);

        $label = get_string('configpassrate', 'block_auditquiz_results');
        $mform->addElement('text', 'config_passrate', $label, array('size' => '2'));
        $mform->setType('config_passrate', PARAM_INT);
        $mform->addHelpButton('config_passrate', 'configpassrate', 'block_auditquiz_results');
        $mform->disabledIf('config_passrate', 'config_enablecoursemapping', 'neq', 1);

        $label = get_string('configpassrate2', 'block_auditquiz_results');
        $mform->addElement('text', 'config_passrate2', $label, array('size' => '2'));
        $mform->setType('config_passrate2', PARAM_INT);
        $mform->addHelpButton('config_passrate2', 'configpassrate2', 'block_auditquiz_results');
        $mform->disabledIf('config_passrate2', 'config_enablecoursemapping', 'neq', 1);

        $label = get_string('configproposeenrolonsuccess', 'block_auditquiz_results');
        $mform->addElement('advcheckbox', 'config_proposeenrolonsuccess', $label);
        $mform->setDefault('config_proposeenrolonsuccess', 0);
        $mform->disabledIf('config_proposeenrolonsuccess', 'config_enablecoursemapping', 'neq', 1);
    }

    public function validation($data, $files = null) {
        $errors = array();

        if (!empty($data->config_passrate2) && ($data->config_passrate2 <= $data->config_passrate)) {
            $errors['config_passrate2'] = get_string('errorbadscale', 'block_auditquiz_results');
        }
    }
}