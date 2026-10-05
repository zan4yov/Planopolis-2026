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
 * Planopolis control panel.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_planopolis\local\auditor;
use local_planopolis\local\helper;

require(__DIR__ . '/../../config.php');

$course = helper::setup_page('/local/planopolis/index.php', 'panel');
$coursecontext = core\context\course::instance($course->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($course->fullname));

$card = function(string $title, string $text, core\url $url, string $button, bool $primary = false): string {
    return html_writer::div(html_writer::div(
        html_writer::tag('h5', $title, ['class' => 'card-title']) .
        html_writer::tag('p', $text, ['class' => 'card-text small text-muted']) .
        html_writer::link($url, $button, ['class' => 'btn ' . ($primary ? 'btn-primary' : 'btn-outline-primary') . ' btn-sm']),
        'card-body'), 'card h-100');
};

$cards = [
    $card(get_string('importparticipants', 'local_planopolis'), get_string('card_participants', 'local_planopolis'),
        new core\url('/local/planopolis/import_participants.php'), get_string('open', 'local_planopolis'), true),
    $card(get_string('importquestions', 'local_planopolis'), get_string('card_questions', 'local_planopolis'),
        new core\url('/local/planopolis/import_questions.php'), get_string('open', 'local_planopolis'), true),
    $card(get_string('audit', 'local_planopolis'), get_string('card_audit', 'local_planopolis'),
        new core\url('/local/planopolis/audit.php'), get_string('open', 'local_planopolis'), true),
    $card(get_string('results', 'local_planopolis'), get_string('card_results', 'local_planopolis'),
        new core\url('/local/planopolis/results.php'), get_string('open', 'local_planopolis'), true),
    $card(get_string('participantlist', 'local_planopolis'), get_string('card_participantlist', 'local_planopolis'),
        new core\url('/user/index.php', ['id' => $course->id]), get_string('open', 'local_planopolis')),
    $card(get_string('addquiz', 'local_planopolis'), get_string('card_addquiz', 'local_planopolis'),
        new core\url('/course/modedit.php', ['add' => 'quiz', 'course' => $course->id, 'section' => 0]),
        get_string('open', 'local_planopolis')),
];
if (is_siteadmin()) {
    $cards[] = $card(get_string('manageadmins', 'local_planopolis'), get_string('card_admins', 'local_planopolis'),
        new core\url('/local/planopolis/admins.php'), get_string('open', 'local_planopolis'));
}
echo html_writer::div(implode('', array_map(fn($c) => html_writer::div($c, 'col'), $cards)),
    'row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3 mb-4');

echo $OUTPUT->heading(get_string('quizzes', 'local_planopolis'), 3);
$quizzes = helper::get_quizzes($course);
if (!$quizzes) {
    echo $OUTPUT->notification(get_string('noquizzes', 'local_planopolis'), 'warning');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [get_string('name'), get_string('questions', 'local_planopolis'), get_string('maxgrade', 'local_planopolis'),
        get_string('opens', 'local_planopolis'), get_string('closes', 'local_planopolis'), get_string('timelimit', 'local_planopolis'),
        get_string('attemptsmade', 'local_planopolis'), get_string('audit', 'local_planopolis'), ''];
    foreach ($quizzes as $quiz) {
        $issues = auditor::run($quiz);
        $errors = count(array_filter($issues, fn($i) => $i[0] === auditor::ERROR));
        $status = $errors ? helper::badge(get_string('audit_errors', 'local_planopolis', $errors), 'danger')
            : helper::badge(get_string('audit_ok', 'local_planopolis'), 'success');
        $links = [
            html_writer::link(new core\url('/course/modedit.php', ['update' => $quiz->cmid]), get_string('settings')),
            html_writer::link(new core\url('/mod/quiz/edit.php', ['cmid' => $quiz->cmid]), get_string('questions', 'local_planopolis')),
            html_writer::link(new core\url('/local/planopolis/audit.php', ['cmid' => $quiz->cmid]), get_string('audit', 'local_planopolis')),
            html_writer::link(new core\url('/local/planopolis/results.php', ['cmid' => $quiz->cmid]),
                get_string('results', 'local_planopolis')),
        ];
        $table->data[] = [
            html_writer::link(new core\url('/mod/quiz/view.php', ['id' => $quiz->cmid]), format_string($quiz->name)),
            $DB->count_records('quiz_slots', ['quizid' => $quiz->id]),
            format_float($quiz->grade, -1),
            $quiz->timeopen ? userdate($quiz->timeopen, get_string('strftimedatetimeshort', 'langconfig')) : '-',
            $quiz->timeclose ? userdate($quiz->timeclose, get_string('strftimedatetimeshort', 'langconfig')) : '-',
            $quiz->timelimit ? format_time($quiz->timelimit) : '-',
            $DB->count_records('quiz_attempts', ['quiz' => $quiz->id, 'preview' => 0]),
            $status,
            implode(' · ', $links),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'table-responsive');
}

echo $OUTPUT->heading(get_string('checklist', 'local_planopolis'), 3);
echo html_writer::tag('ol', get_string('checklist_items', 'local_planopolis'));
echo $OUTPUT->footer();
