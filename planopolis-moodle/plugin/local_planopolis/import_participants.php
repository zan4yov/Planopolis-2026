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

/**
 * Register participants from Excel/CSV: create accounts and enrol them in the competition course.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_planopolis\local\helper;
use local_planopolis\local\participant_importer;

require(__DIR__ . '/../../config.php');

$course = helper::setup_page('/local/planopolis/import_participants.php', 'importparticipants');
require_capability('moodle/user:create', core\context\system::instance());
require_capability('enrol/manual:enrol', core\context\course::instance($course->id));

$download = optional_param('download', 0, PARAM_BOOL);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$credcolumns = [
    'username' => get_string('pcol_username', 'local_planopolis'),
    'password' => get_string('pcol_password', 'local_planopolis'),
    'name' => get_string('pcol_firstname', 'local_planopolis'),
    'institution' => get_string('pcol_institution', 'local_planopolis'),
    'group' => get_string('pcol_group', 'local_planopolis'),
    'status' => get_string('res_status', 'local_planopolis'),
];

if ($download) {
    require_sesskey();
    $creds = $SESSION->local_planopolis_creds ?? [];
    helper::download_xlsx('Akun_Peserta_' . date('Ymd_Hi'), $credcolumns, $creds, 'Akun Peserta');
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importparticipants', 'local_planopolis'));

if ($confirm) {
    require_sesskey();
    $draftid = required_param('draftid', PARAM_INT);
    $updateexisting = required_param('updateexisting', PARAM_BOOL);
    [$path, $filename] = helper::draft_file_to_temp($draftid);
    $imp = participant_importer::from_upload($path, $filename, $updateexisting);
    if ($imp->errors) {
        echo $OUTPUT->notification(get_string('pv_fixerrors', 'local_planopolis'), 'error');
    } else {
        $creds = $imp->process($course);
        $SESSION->local_planopolis_creds = $creds;
        echo $OUTPUT->notification(get_string('participantsdone', 'local_planopolis', count($creds)), 'success');
        echo $OUTPUT->notification(get_string('credentialsnote', 'local_planopolis'), 'info', false);
        echo $OUTPUT->single_button(new core\url($PAGE->url, ['download' => 1, 'sesskey' => sesskey()]),
            get_string('downloadcredentials', 'local_planopolis'), 'post', ['type' => 'primary']);
        $table = new html_table();
        $table->attributes['class'] = 'generaltable table-sm';
        $table->head = array_values($credcolumns);
        foreach ($creds as $cred) {
            $table->data[] = array_map('s', array_values($cred));
        }
        echo html_writer::div(html_writer::table($table), 'table-responsive');
    }
    echo $OUTPUT->footer();
    exit;
}

$form = new \local_planopolis\form\upload_form(null, ['mode' => 'participants']);

if ($data = $form->get_data()) {
    [$path, $filename] = helper::draft_file_to_temp((int) $data->importfile);
    $imp = participant_importer::from_upload($path, $filename, (bool) $data->updateexisting);

    $counts = ['create' => 0, 'update' => 0, 'enrolonly' => 0];
    foreach ($imp->rows as $row) {
        $counts[$row->action]++;
    }
    echo html_writer::alist([
        get_string('pv_file', 'local_planopolis') . ': ' . s($filename),
        get_string('pv_newusers', 'local_planopolis') . ': ' . $counts['create'],
        get_string('pv_updateusers', 'local_planopolis') . ': ' . $counts['update'],
        get_string('pv_enrolonly', 'local_planopolis') . ': ' . $counts['enrolonly'],
        get_string('pv_errors', 'local_planopolis') . ': ' . helper::badge(count($imp->errors), $imp->errors ? 'danger' : 'success'),
    ]);
    if ($imp->errors) {
        echo $OUTPUT->notification(get_string('pv_fixerrors', 'local_planopolis'), 'error', false);
        echo html_writer::alist(array_map('s', $imp->errors), ['class' => 'text-danger']);
    }
    if ($imp->warnings) {
        echo html_writer::alist(array_map('s', $imp->warnings), ['class' => 'text-warning-emphasis']);
    }
    if ($imp->rows) {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable table-sm';
        $table->head = [get_string('pv_row', 'local_planopolis'), get_string('pcol_username', 'local_planopolis'),
            get_string('pcol_firstname', 'local_planopolis'), get_string('pcol_institution', 'local_planopolis'),
            get_string('pcol_group', 'local_planopolis'), get_string('pcol_password', 'local_planopolis'),
            get_string('pv_action', 'local_planopolis')];
        foreach ($imp->rows as $row) {
            $table->data[] = [$row->rownum, s($row->username), s($row->firstname . ' ' . $row->lastname), s($row->institution),
                s($row->group), $row->password !== '' ? '••••••' : get_string('pv_autopassword', 'local_planopolis'),
                get_string('action_' . $row->action, 'local_planopolis')];
        }
        echo html_writer::div(html_writer::table($table), 'table-responsive');
    }
    if (!$imp->errors) {
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
        foreach (['sesskey' => sesskey(), 'confirm' => 1, 'draftid' => (int) $data->importfile,
                'updateexisting' => (int) $data->updateexisting] as $name => $value) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        echo html_writer::tag('button', get_string('confirmparticipants', 'local_planopolis', count($imp->rows)),
            ['type' => 'submit', 'class' => 'btn btn-primary']);
        echo ' ' . html_writer::link($PAGE->url, get_string('cancel'), ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('form');
    } else {
        echo $OUTPUT->single_button($PAGE->url, get_string('uploadagain', 'local_planopolis'), 'get');
    }
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->box(get_string('importparticipants_intro', 'local_planopolis', (object) [
    'template' => (new core\url('/local/planopolis/templates/Template_Peserta_Planopolis.xlsx'))->out(),
    'manual' => (new core\url('/user/editadvanced.php', ['id' => -1]))->out(),
]), 'generalbox');
$form->display();
if (!empty($SESSION->local_planopolis_creds)) {
    echo $OUTPUT->single_button(new core\url($PAGE->url, ['download' => 1, 'sesskey' => sesskey()]),
        get_string('downloadlastcredentials', 'local_planopolis'), 'post');
}
echo $OUTPUT->footer();
