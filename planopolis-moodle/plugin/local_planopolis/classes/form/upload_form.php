<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_planopolis\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Upload form used by the question and participant import pages.
 *
 * Custom data: 'mode' => 'questions'|'participants', 'quizzes' => cmid => name (questions only).
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $mode = $this->_customdata['mode'];

        if ($mode === 'questions') {
            $mform->addElement('select', 'cmid', get_string('targetquiz', 'local_planopolis'), $this->_customdata['quizzes']);
            $mform->addElement('filepicker', 'importfile', get_string('questionfile', 'local_planopolis'), null,
                ['accepted_types' => ['.xlsx', '.xls', '.ods', '.zip']]);
            $mform->addHelpButton('importfile', 'questionfile', 'local_planopolis');
            $mform->addElement('advcheckbox', 'shuffle', get_string('shuffledefault', 'local_planopolis'));
            $mform->setDefault('shuffle', 1);
            $mform->addElement('advcheckbox', 'setmaxgrade', get_string('setmaxgrade', 'local_planopolis'));
            $mform->addHelpButton('setmaxgrade', 'setmaxgrade', 'local_planopolis');
            $mform->setDefault('setmaxgrade', 1);
        } else {
            $mform->addElement('filepicker', 'importfile', get_string('participantfile', 'local_planopolis'), null,
                ['accepted_types' => ['.xlsx', '.xls', '.ods', '.csv']]);
            $mform->addHelpButton('importfile', 'participantfile', 'local_planopolis');
            $mform->addElement('advcheckbox', 'updateexisting', get_string('updateexisting', 'local_planopolis'));
            $mform->addHelpButton('updateexisting', 'updateexisting', 'local_planopolis');
        }
        $mform->addRule('importfile', null, 'required');
        $this->add_action_buttons(false, get_string('checkfile', 'local_planopolis'));
    }
}
