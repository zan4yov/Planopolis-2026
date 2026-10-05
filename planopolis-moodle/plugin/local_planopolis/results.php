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
 * Ranked results of a quiz, with Excel download.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_planopolis\local\helper;
use local_planopolis\local\results;

require(__DIR__ . '/../../config.php');

$cmid = optional_param('cmid', 0, PARAM_INT);
$download = optional_param('download', 0, PARAM_BOOL);
$course = helper::setup_page('/local/planopolis/results.php', 'results', $cmid ? ['cmid' => $cmid] : []);
$quiz = helper::get_quiz($course, $cmid);
if ($quiz) {
    require_capability('mod/quiz:viewreports', $quiz->context);
}

if ($download && $quiz) {
    require_sesskey();
    $rows = results::compute($quiz, $course);
    $filename = 'Nilai_' . clean_filename(format_string($quiz->name)) . '_' . date('Ymd_Hi');
    helper::download_xlsx($filename, results::columns(), $rows, format_string($quiz->name));
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('results', 'local_planopolis'));
if (!$quiz) {
    echo $OUTPUT->notification(get_string('noquizzes', 'local_planopolis'), 'error');
    echo $OUTPUT->footer();
    exit;
}

$menu = helper::quiz_menu($course);
if (count($menu) > 1) {
    echo $OUTPUT->single_select(new core\url('/local/planopolis/results.php'), 'cmid', $menu, $quiz->cmid, null);
}

$rows = results::compute($quiz, $course);
$finished = count(array_filter($rows, fn($r) => $r['rank'] !== ''));
echo html_writer::tag('p', get_string('results_summary', 'local_planopolis', (object) [
    'total' => count($rows), 'finished' => $finished, 'max' => format_float($quiz->grade, -1),
]));
echo html_writer::div(
    $OUTPUT->single_button(new core\url($PAGE->url, ['cmid' => $quiz->cmid, 'download' => 1, 'sesskey' => sesskey()]),
        get_string('downloadexcel', 'local_planopolis'), 'post', ['type' => 'primary']) . ' ' .
    $OUTPUT->single_button(new core\url('/mod/quiz/report.php', ['id' => $quiz->cmid, 'mode' => 'overview']),
        get_string('moodlereport', 'local_planopolis'), 'get'),
    'd-flex gap-2 mb-3');

$table = new html_table();
$table->attributes['class'] = 'generaltable table-sm';
$table->head = array_values(results::columns());
foreach ($rows as $row) {
    $cells = array_map(fn($v) => s((string) $v), array_values($row));
    $table->data[] = $cells;
}
echo html_writer::div(html_writer::table($table), 'table-responsive');
echo html_writer::tag('p', get_string('results_leftnote', 'local_planopolis'), ['class' => 'text-muted small']);
echo $OUTPUT->footer();
