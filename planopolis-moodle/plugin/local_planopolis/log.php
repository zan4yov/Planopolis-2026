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
 * Receives integrity events (leaving the page, copy / screenshot attempts) from quiz attempt pages.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');

$attemptid = required_param('attempt', PARAM_INT);
$type = required_param('type', PARAM_ALPHA);

require_login(null, false, null, false, true);
require_sesskey();

if (!in_array($type, ['leave', 'copy', 'screenshot', 'shortcut', 'print'], true)) {
    throw new invalid_parameter_exception('type');
}
$attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], 'id, userid, state, preview');
if ($attempt && (int) $attempt->userid === (int) $USER->id && !$attempt->preview
        && $attempt->state === \mod_quiz\quiz_attempt::IN_PROGRESS) {
    $DB->insert_record('local_planopolis_log', (object) [
        'attemptid' => $attempt->id,
        'userid' => $USER->id,
        'eventtype' => $type,
        'timecreated' => time(),
    ]);
}
echo json_encode(['ok' => true]);
