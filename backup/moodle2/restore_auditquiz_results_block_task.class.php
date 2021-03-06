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
 * @package    block_auditquiz_results
 * @category   blocks
 * @subpackage backup-moodle2
 * @copyright  2003 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Specialised restore task for the auditquiz_results block
 * (has own DB structures to backup)
 *
 * TODO: Finish phpdocs
 */
class restore_auditquiz_results_block_task extends restore_block_task {

    protected function define_my_settings() {
    }

    protected function define_my_steps() {
    }

    public function get_fileareas() {
        return array(); // No associated fileareas.
    }

    public function get_configdata_encoded_attributes() {
        return array(); // No special handling of configdata.
    }

    static public function define_decode_contents() {
        return array();
    }

    static public function define_decode_rules() {
        return array();
    }

    // Each block should remap its binding to his configured quiz instances.
    public function after_restore() {
        global $DB;

        $courseid = $this->get_courseid();
        $blockid = $this->get_blockid();

        // These are fake blocks that can be cached in backup.
        if (!$blockid) return;

        $bi = $DB->get_record('block_instances', array('id' => $blockid));

        // Adjust the serialized configdata->quizid to the newly restored quizes.
        // Get the configdata.

        // Extract configdata.
        $config = unserialize(base64_decode($bi->configdata));

        if (!empty($config->quizid)) {
            $newquizes = array();
            foreach ($config->quizid as $qid) {
                $newquizes[] = $this->get_mappingid($config->quiztype, $qid);
            }
            $config->quizid = $newquizes;

            // Serialize back the configdata.
            $bi->configdata = base64_encode(serialize($config));

            // Store back in DB.
            $DB->update_record('block_instances', $bi);
        }
    }

    /**
     * Return the new id of a mapping for the given itemname
     *
     * @param string $itemname the type of item
     * @param int $oldid the item ID from the backup
     * @param mixed $ifnotfound what to return if $oldid wasnt found. Defaults to false
     */
    public function get_mappingid($itemname, $oldid, $ifnotfound = false) {
        $mapping = $this->get_mapping($itemname, $oldid);
        return $mapping ? $mapping->newitemid : $ifnotfound;
    }

    /**
     * Return the complete mapping from the given itemname, itemid
     */
    public function get_mapping($itemname, $oldid) {
        return restore_dbops::get_backup_ids_record($this->plan->get_restoreid(), $itemname, $oldid);
    }
}

