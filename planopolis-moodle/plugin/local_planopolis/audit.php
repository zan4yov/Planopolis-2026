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
 * Pre-exam quiz check.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_planopolis\local\auditor;
use local_planopolis\local\helper;

require(__DIR__ . '/../../config.php');

$cmid = optional_param('cmid', 0, PARAM_INT);
$course = helper::setup_page('/local/planopolis/audit.php', 'audit', $cmid ? ['cmid' => $cmid] : []);
$quiz = helper::get_quiz($course, $cmid);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('audit', 'local_planopolis'));
if (!$quiz) {
    echo $OUTPUT->notification(get_string('noquizzes', 'local_planopolis'), 'error');
    echo $OUTPUT->footer();
    exit;
}
$menu = helper::quiz_menu($course);
if (count($menu) > 1) {
    echo $OUTPUT->single_select(new core\url('/local/planopolis/audit.php'), 'cmid', $menu, $quiz->cmid, null);
}
echo html_writer::tag('p', get_string('audit_intro', 'local_planopolis'));

$issues = auditor::run($quiz);
$errors = count(array_filter($issues, fn($i) => $i[0] === auditor::ERROR));
$warnings = count(array_filter($issues, fn($i) => $i[0] === auditor::WARNING));
$slots = $DB->count_records('quiz_slots', ['quizid' => $quiz->id]);

if (!$errors) {
    echo $OUTPUT->notification(get_string('audit_pass', 'local_planopolis', (object) ['slots' => $slots, 'warnings' => $warnings]),
        'success', false);
} else {
    echo $OUTPUT->notification(get_string('audit_fail', 'local_planopolis', (object) ['errors' => $errors,
        'warnings' => $warnings, 'slots' => $slots]), 'error', false);
}

if ($issues) {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [get_string('audit_severity', 'local_planopolis'), get_string('audit_where', 'local_planopolis'),
        get_string('audit_problem', 'local_planopolis'), ''];
    usort($issues, fn($a, $b) => ($a[0] === auditor::ERROR ? 0 : 1) <=> ($b[0] === auditor::ERROR ? 0 : 1));
    foreach ($issues as [$severity, $where, $message, $url]) {
        $table->data[] = [
            helper::badge(get_string('sev_' . $severity, 'local_planopolis'), $severity),
            s($where), $message,
            $url ? html_writer::link($url, get_string('edit')) : '',
        ];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->single_button(new core\url('/mod/quiz/startattempt.php', ['cmid' => $quiz->cmid, 'sesskey' => sesskey()]),
    get_string('previewquiz', 'local_planopolis'), 'post');
echo $OUTPUT->footer();
