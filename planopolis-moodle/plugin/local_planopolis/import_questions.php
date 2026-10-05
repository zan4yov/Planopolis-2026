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
 * Import questions from Excel (optionally zipped together with images and audio).
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_planopolis\local\helper;
use local_planopolis\local\question_importer;
use local_planopolis\local\question_sheet;

require(__DIR__ . '/../../config.php');

$course = helper::setup_page('/local/planopolis/import_questions.php', 'importquestions');
$quizzes = helper::quiz_menu($course);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importquestions', 'local_planopolis'));

if (!$quizzes) {
    echo $OUTPUT->notification(get_string('noquizzes', 'local_planopolis'), 'error');
    echo $OUTPUT->footer();
    exit;
}

if ($confirm) {
    require_sesskey();
    $draftid = required_param('draftid', PARAM_INT);
    $cmid = required_param('cmid', PARAM_INT);
    $shuffle = required_param('shuffle', PARAM_BOOL);
    $setmaxgrade = required_param('setmaxgrade', PARAM_BOOL);
    $quiz = helper::get_quiz($course, $cmid);
    [$path, $filename] = helper::draft_file_to_temp($draftid);
    $sheet = question_sheet::from_upload($path, $filename, $shuffle);
    try {
        $summary = question_importer::import($sheet, $quiz, $course, $setmaxgrade);
        echo $OUTPUT->notification(get_string('importdone', 'local_planopolis', (object) [
            'count' => $summary->imported,
            'quiz' => format_string($quiz->name),
            'sumgrades' => format_float($summary->sumgrades, -1),
            'grade' => format_float($summary->grade, -1),
        ]), 'success');
        echo html_writer::div(
            $OUTPUT->single_button(new core\url('/local/planopolis/audit.php', ['cmid' => $quiz->cmid]),
                get_string('runaudit', 'local_planopolis'), 'get', ['type' => 'primary']) . ' ' .
            $OUTPUT->single_button(new core\url('/mod/quiz/edit.php', ['cmid' => $quiz->cmid]),
                get_string('editquiz', 'local_planopolis'), 'get') . ' ' .
            $OUTPUT->single_button(new core\url('/local/planopolis/import_questions.php'),
                get_string('importmore', 'local_planopolis'), 'get'),
            'd-flex gap-2 flex-wrap');
    } catch (moodle_exception $e) {
        echo $OUTPUT->notification($e->getMessage(), 'error');
        echo $OUTPUT->single_button(new core\url('/local/planopolis/import_questions.php'), get_string('back'), 'get');
    }
    echo $OUTPUT->footer();
    exit;
}

$form = new \local_planopolis\form\upload_form(null, ['mode' => 'questions', 'quizzes' => $quizzes]);

if ($data = $form->get_data()) {
    $quiz = helper::get_quiz($course, (int) $data->cmid);
    [$path, $filename] = helper::draft_file_to_temp((int) $data->importfile);
    $sheet = question_sheet::from_upload($path, $filename, (bool) $data->shuffle);
    if (!$sheet->errors) {
        $sheet->warnings = array_merge($sheet->warnings, question_importer::find_existing_duplicates($sheet, $quiz));
    }

    // Summary.
    $summary = [
        get_string('pv_file', 'local_planopolis') => s($filename),
        get_string('targetquiz', 'local_planopolis') => format_string($quiz->name),
        get_string('pv_valid', 'local_planopolis') => count($sheet->questions),
        get_string('pv_totalmarks', 'local_planopolis') => format_float($sheet->total_marks(), -1),
        get_string('pv_errors', 'local_planopolis') => helper::badge(count($sheet->errors), $sheet->errors ? 'danger' : 'success'),
        get_string('pv_warnings', 'local_planopolis') => helper::badge(count($sheet->warnings),
            $sheet->warnings ? 'warning' : 'success'),
    ];
    $table = new html_table();
    $table->attributes['class'] = 'generaltable w-auto';
    foreach ($summary as $k => $v) {
        $table->data[] = [html_writer::tag('strong', $k), $v];
    }
    echo html_writer::table($table);

    if ($sheet->errors) {
        echo $OUTPUT->notification(get_string('pv_fixerrors', 'local_planopolis'), 'error', false);
        echo html_writer::alist(array_map('s', $sheet->errors), ['class' => 'text-danger']);
    }
    if ($sheet->warnings) {
        echo $OUTPUT->heading(get_string('pv_warnings', 'local_planopolis'), 4);
        echo html_writer::alist(array_map('s', $sheet->warnings), ['class' => 'text-warning-emphasis']);
    }

    if ($sheet->questions) {
        echo $OUTPUT->heading(get_string('pv_preview', 'local_planopolis'), 4);
        $pt = new html_table();
        $pt->attributes['class'] = 'generaltable table-sm';
        $pt->head = [get_string('pv_row', 'local_planopolis'), get_string('col_no', 'local_planopolis'),
            get_string('col_question', 'local_planopolis'), get_string('pv_options', 'local_planopolis'),
            get_string('col_key', 'local_planopolis'), get_string('col_mark', 'local_planopolis'),
            get_string('col_penalty', 'local_planopolis'), get_string('pv_media', 'local_planopolis')];
        foreach ($sheet->questions as $q) {
            $options = [];
            foreach ($q->options as $letter => $opt) {
                $text = s(shorten_text($opt->raw, 40));
                $options[] = $letter === $q->key ? html_writer::tag('strong', "{$letter}. {$text} ✓", ['class' => 'text-success'])
                    : "{$letter}. {$text}";
            }
            $media = [];
            foreach ($q->files as $name => $unused) {
                $media[] = s($name);
            }
            $pt->data[] = [$q->rownum, s($q->no), s(shorten_text($q->plaintext, 120)), implode('<br>', $options),
                $q->key, format_float($q->mark, -1), format_float($q->penalty, -1), implode('<br>', $media)];
        }
        echo html_writer::div(html_writer::table($pt), 'table-responsive');
    }

    if (!$sheet->errors) {
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
        foreach (['sesskey' => sesskey(), 'confirm' => 1, 'draftid' => (int) $data->importfile, 'cmid' => $quiz->cmid,
                'shuffle' => (int) $data->shuffle, 'setmaxgrade' => (int) $data->setmaxgrade] as $name => $value) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        echo html_writer::tag('button', get_string('confirmimport', 'local_planopolis', count($sheet->questions)),
            ['type' => 'submit', 'class' => 'btn btn-primary']);
        echo ' ' . html_writer::link($PAGE->url, get_string('cancel'), ['class' => 'btn btn-secondary']);
        echo html_writer::end_tag('form');
    } else {
        echo $OUTPUT->single_button($PAGE->url, get_string('uploadagain', 'local_planopolis'), 'get');
    }
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->box(get_string('importquestions_intro', 'local_planopolis', (object) [
    'template' => (new core\url('/local/planopolis/templates/Template_Soal_Planopolis.xlsx'))->out(),
]), 'generalbox');
$form->display();
echo $OUTPUT->footer();
