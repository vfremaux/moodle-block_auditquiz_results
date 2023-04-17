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

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/blocks/auditquiz_results/lib.php');

/**
 * Course list block settings
 *
 * @package    block_auditquiz_results
 * @copyright  2016 Valery Fremaux
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if ($ADMIN->fulltree) {
    $key = 'block_auditquiz_results/modules';
    $label = get_string('configblockquizmodules', 'block_auditquiz_results');
    $desc = get_string('configblockquizmodules_desc', 'block_auditquiz_results');
    $settings->add(new admin_setting_configtext($key, $label, $desc, 'quiz'));

    $key = 'block_auditquiz_results/disablesuspendedenrolments';
    $label = get_string('configdisablesuspendedenrolments', 'block_auditquiz_results');
    $desc = get_string('configdisablesuspendedenrolments_desc', 'block_auditquiz_results');
    $settings->add(new admin_setting_configcheckbox($key, $label, $desc, 0));

    if (block_auditquiz_results_supports_feature('emulate/community') == 'pro') {
        include_once($CFG->dirroot.'/blocks/auditquiz_results/pro/prolib.php');
        \block_auditquiz_results\pro_manager::add_settings($ADMIN, $settings);
    } else {
        $label = get_string('plugindist', 'block_auditquiz_results');
        $desc = get_string('plugindist_desc', 'block_auditquiz_results');
        $settings->add(new admin_setting_heading('plugindisthdr', $label, $desc));
    }
}