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

namespace local_planopolis\local;

use stdClass;

/**
 * Registers participants from a spreadsheet: creates manual accounts and enrols them as students.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participant_importer {

    /** @var stdClass[] valid rows. */
    public array $rows = [];

    /** @var string[] blocking problems. */
    public array $errors = [];

    /** @var string[] remarks. */
    public array $warnings = [];

    /**
     * Accepted header spellings.
     *
     * @return array
     */
    public static function header_aliases(): array {
        return [
            'username' => ['username', 'usernamepeserta', 'idpeserta', 'nopeserta', 'nomorpeserta', 'user'],
            'password' => ['password', 'katasandi', 'sandi', 'pass'],
            'firstname' => ['namadepan', 'firstname', 'nama', 'namalengkap', 'namapeserta', 'namatim'],
            'lastname' => ['namabelakang', 'lastname', 'surname'],
            'email' => ['email', 'surel', 'emailpeserta'],
            'institution' => ['instansi', 'sekolah', 'asalsekolah', 'universitas', 'institution', 'kampus', 'asalinstansi'],
            'city' => ['kota', 'city', 'asalkota', 'kabupatenkota'],
            'group' => ['grup', 'group', 'sesi', 'kelompok', 'ruang'],
        ];
    }

    /**
     * Read and validate a file.
     *
     * @param string $path
     * @param string $filename
     * @param bool $updateexisting reset password/details of usernames that already exist
     * @return self
     */
    public static function from_upload(string $path, string $filename, bool $updateexisting): self {
        global $DB;
        $imp = new self();
        try {
            $rows = helper::read_spreadsheet($path, $filename, 'Peserta');
        } catch (\Throwable $e) {
            $imp->errors[] = get_string('err_cannotread', 'local_planopolis', $e->getMessage());
            return $imp;
        }
        [$headerrow, $map, $missing] = helper::map_headers($rows, self::header_aliases(), ['username', 'firstname']);
        if ($missing) {
            $imp->errors[] = get_string('err_missingcolumns', 'local_planopolis', implode(', ', array_map(
                fn($k) => get_string('pcol_' . $k, 'local_planopolis'), $missing)));
            return $imp;
        }
        $seen = [];
        foreach ($rows as $rownum => $row) {
            if ($rownum <= $headerrow) {
                continue;
            }
            $get = fn(string $key) => isset($map[$key]) ? ($row[$map[$key]] ?? '') : '';
            $rawusername = $get('username');
            if (preg_match('/^(contoh|example)/i', $rawusername)) {
                continue;
            }
            $label = get_string('rowlabel', 'local_planopolis', (object) ['row' => $rownum, 'no' => s($rawusername)]);
            $username = \core_text::strtolower(trim($rawusername));
            $rowerrors = [];
            if ($username === '') {
                $rowerrors[] = get_string('err_nousername', 'local_planopolis');
            } else if (clean_param($username, PARAM_USERNAME) !== $username) {
                $rowerrors[] = get_string('err_badusername', 'local_planopolis', s($rawusername));
            } else if (isset($seen[$username])) {
                $rowerrors[] = get_string('err_dupusername', 'local_planopolis', $seen[$username]);
            }
            $firstname = $get('firstname');
            if ($firstname === '') {
                $rowerrors[] = get_string('err_noname', 'local_planopolis');
            }
            $email = trim($get('email'));
            if ($email !== '' && !validate_email($email)) {
                $rowerrors[] = get_string('err_bademail', 'local_planopolis', s($email));
            }
            $password = $get('password');
            $policyerror = '';
            if ($password !== '' && !check_password_policy($password, $policyerror)) {
                $rowerrors[] = get_string('err_passwordpolicy', 'local_planopolis', html_to_text($policyerror, 0, false));
            }
            $existing = $username !== '' ? $DB->get_record('user',
                ['username' => $username, 'mnethostid' => $GLOBALS['CFG']->mnet_localhost_id, 'deleted' => 0]) : false;
            if ($existing && is_siteadmin($existing)) {
                $rowerrors[] = get_string('err_isadmin', 'local_planopolis');
            }
            foreach ($rowerrors as $msg) {
                $imp->errors[] = $label . ': ' . $msg;
            }
            if ($rowerrors) {
                continue;
            }
            $seen[$username] = $rownum;
            // Moodle requires a last name. If only one name column is filled, split "Tim Alpha" into
            // "Tim" + "Alpha" so the full name still reads naturally; a single word gets "-".
            $lastname = $get('lastname');
            if ($lastname === '') {
                $parts = preg_split('/\s+/u', trim($firstname));
                if (count($parts) > 1) {
                    $lastname = array_pop($parts);
                    $firstname = implode(' ', $parts);
                } else {
                    $lastname = '-';
                }
            }
            $action = 'create';
            if ($existing) {
                $action = $updateexisting ? 'update' : 'enrolonly';
                if (!$updateexisting) {
                    $imp->warnings[] = $label . ': ' . get_string('warn_userexists', 'local_planopolis');
                }
            }
            $imp->rows[] = (object) [
                'rownum' => $rownum,
                'username' => $username,
                'password' => $password,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => $email,
                'institution' => $get('institution'),
                'city' => $get('city'),
                'group' => $get('group'),
                'action' => $action,
                'existingid' => $existing ? (int) $existing->id : 0,
            ];
        }
        if (!$imp->rows && !$imp->errors) {
            $imp->errors[] = get_string('err_noparticipants', 'local_planopolis');
        }
        return $imp;
    }

    /**
     * Create/update accounts and enrol them.
     *
     * @param stdClass $course
     * @return array credentials rows (username, password, name, institution, status)
     */
    public function process(stdClass $course): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->libdir . '/enrollib.php');

        $enrol = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
        if (!$instance) {
            $instanceid = $enrol->add_default_instance($course);
            $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        }
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

        $credentials = [];
        foreach ($this->rows as $row) {
            $transaction = $DB->start_delegated_transaction();
            $password = $row->password;
            $showpassword = '';
            if ($row->action === 'create') {
                if ($password === '') {
                    $password = helper::generate_password();
                }
                $user = (object) [
                    'username' => $row->username,
                    'auth' => 'manual',
                    'confirmed' => 1,
                    'mnethostid' => $CFG->mnet_localhost_id,
                    'firstname' => $row->firstname,
                    'lastname' => $row->lastname,
                    'email' => $row->email !== '' ? $row->email : $row->username . '@peserta.invalid',
                    'emailstop' => $row->email === '' ? 1 : 0,
                    'institution' => $row->institution,
                    'city' => $row->city,
                    'country' => 'ID',
                    'timezone' => '99',
                    'password' => $password,
                ];
                $userid = user_create_user($user, true, true);
                $showpassword = $password;
                $status = get_string('status_created', 'local_planopolis');
            } else {
                $userid = $row->existingid;
                if ($row->action === 'update') {
                    $update = (object) [
                        'id' => $userid,
                        'firstname' => $row->firstname,
                        'lastname' => $row->lastname,
                        'institution' => $row->institution,
                        'city' => $row->city,
                        'suspended' => 0,
                    ];
                    if ($row->email !== '') {
                        $update->email = $row->email;
                    }
                    user_update_user($update, false, true);
                    if ($password === '') {
                        $password = helper::generate_password();
                    }
                    update_internal_user_password($DB->get_record('user', ['id' => $userid]), $password);
                    $showpassword = $password;
                    $status = get_string('status_updated', 'local_planopolis');
                } else {
                    $status = get_string('status_enrolonly', 'local_planopolis');
                }
            }
            $enrol->enrol_user($instance, $userid, $studentrole->id);

            if ($row->group !== '') {
                $groupid = groups_get_group_by_name($course->id, $row->group);
                if (!$groupid) {
                    $groupid = groups_create_group((object) ['courseid' => $course->id, 'name' => $row->group]);
                }
                groups_add_member($groupid, $userid);
            }
            $transaction->allow_commit();

            $credentials[] = [
                'username' => $row->username,
                'password' => $showpassword,
                'name' => trim($row->firstname . ' ' . ($row->lastname === '-' ? '' : $row->lastname)),
                'institution' => $row->institution,
                'group' => $row->group,
                'status' => $status,
            ];
        }
        return $credentials;
    }
}
