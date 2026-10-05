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
 * Super Admin page: give or remove the "Admin Quiz" role.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_planopolis\local\helper;

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');

require_login(null, false);
if (!is_siteadmin()) {
    throw new required_capability_exception(core\context\system::instance(), 'moodle/site:config', 'nopermissions', '');
}
$syscontext = core\context\system::instance();
$PAGE->set_url(new core\url('/local/planopolis/admins.php'));
$PAGE->set_context($syscontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('manageadmins', 'local_planopolis'));
$PAGE->set_heading(get_string('panel', 'local_planopolis'));
$PAGE->navbar->add(get_string('panel', 'local_planopolis'), new core\url('/local/planopolis/index.php'));
$PAGE->navbar->add(get_string('manageadmins', 'local_planopolis'));

$role = $DB->get_record('role', ['shortname' => helper::ADMIN_ROLE]);
$action = optional_param('action', '', PARAM_ALPHA);
$messages = [];

if ($role && $action && confirm_sesskey()) {
    if ($action === 'add') {
        $username = core_text::strtolower(trim(required_param('username', PARAM_RAW_TRIMMED)));
        $user = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0]);
        $password = optional_param('password', '', PARAM_RAW);
        if (!$user) {
            $firstname = optional_param('firstname', '', PARAM_TEXT);
            $email = optional_param('email', '', PARAM_EMAIL);
            if (clean_param($username, PARAM_USERNAME) !== $username || $username === '') {
                $messages[] = [get_string('err_badusername', 'local_planopolis', s($username)), 'error'];
            } else if ($firstname === '' || $password === '') {
                $messages[] = [get_string('admins_neednew', 'local_planopolis'), 'error'];
            } else {
                $userid = user_create_user((object) [
                    'username' => $username, 'auth' => 'manual', 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id,
                    'firstname' => $firstname, 'lastname' => optional_param('lastname', '-', PARAM_TEXT) ?: '-',
                    'email' => $email ?: $username . '@admin.invalid', 'password' => $password,
                ], true, true);
                $user = $DB->get_record('user', ['id' => $userid]);
                $messages[] = [get_string('admins_created', 'local_planopolis', s($username)), 'success'];
            }
        }
        if ($user) {
            role_assign($role->id, $user->id, $syscontext->id);
            $messages[] = [get_string('admins_added', 'local_planopolis', s(fullname($user))), 'success'];
        }
    } else if ($action === 'remove') {
        $userid = required_param('userid', PARAM_INT);
        role_unassign($role->id, $userid, $syscontext->id);
        $messages[] = [get_string('admins_removed', 'local_planopolis'), 'success'];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageadmins', 'local_planopolis'));
if (!$role) {
    echo $OUTPUT->notification(get_string('admins_norole', 'local_planopolis'), 'error');
    echo $OUTPUT->footer();
    exit;
}
foreach ($messages as [$text, $type]) {
    echo $OUTPUT->notification($text, $type);
}
echo html_writer::tag('p', get_string('admins_intro', 'local_planopolis'));

$holders = get_role_users($role->id, $syscontext, false, 'u.id, u.username, u.firstname, u.lastname, u.email, u.lastaccess,
    u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename', 'u.username');
$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->head = [get_string('username'), get_string('fullname'), get_string('lastaccess'), ''];
foreach ($holders as $u) {
    $remove = html_writer::start_tag('form', ['method' => 'post', 'class' => 'd-inline']) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'remove']) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $u->id]) .
        html_writer::tag('button', get_string('admins_remove', 'local_planopolis'), ['class' => 'btn btn-sm btn-outline-danger',
            'onclick' => 'return confirm(' . json_encode(get_string('admins_confirmremove', 'local_planopolis')) . ')']) .
        html_writer::end_tag('form');
    $table->data[] = [s($u->username), s(fullname($u)),
        $u->lastaccess ? userdate($u->lastaccess) : get_string('never'), $remove];
}
if (!$holders) {
    $cell = new html_table_cell(get_string('admins_none', 'local_planopolis'));
    $cell->colspan = 4;
    $table->data[] = new html_table_row([$cell]);
}
echo html_writer::table($table);

echo $OUTPUT->heading(get_string('admins_add', 'local_planopolis'), 4);
echo html_writer::tag('p', get_string('admins_addhelp', 'local_planopolis'), ['class' => 'text-muted']);
echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'row g-2', 'style' => 'max-width: 720px']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'add']);
$fields = [
    ['username', get_string('username'), 'text', true],
    ['firstname', get_string('admins_newname', 'local_planopolis'), 'text', false],
    ['email', get_string('email'), 'email', false],
    ['password', get_string('admins_newpassword', 'local_planopolis'), 'text', false],
];
foreach ($fields as [$name, $label, $type, $required]) {
    echo html_writer::div(
        html_writer::label($label, 'pl-' . $name, true, ['class' => 'form-label']) .
        html_writer::empty_tag('input', ['type' => $type, 'name' => $name, 'id' => 'pl-' . $name, 'class' => 'form-control',
            'autocomplete' => 'off'] + ($required ? ['required' => 'required'] : [])),
        'col-md-6');
}
echo html_writer::div(html_writer::tag('button', get_string('admins_add', 'local_planopolis'),
    ['type' => 'submit', 'class' => 'btn btn-primary']), 'col-12');
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
