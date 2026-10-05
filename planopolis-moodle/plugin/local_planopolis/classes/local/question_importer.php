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
 * Imports validated questions into a quiz.
 *
 * The questions are converted to Moodle XML and imported with Moodle's own XML importer, so the
 * result is exactly the same as a question created by hand in the question bank (images and audio
 * are stored as normal question files and can be edited later in the web editor).
 * Every question is then added to the quiz as a fixed (non-random) slot with its own mark.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_importer {

    /**
     * Questions of the sheet that already exist in the quiz (same normalised text).
     *
     * @param question_sheet $sheet
     * @param stdClass $quiz
     * @return string[] warnings
     */
    public static function find_existing_duplicates(question_sheet $sheet, stdClass $quiz): array {
        $existing = [];
        $structure = \mod_quiz\question\bank\qbank_helper::get_question_structure($quiz->id, $quiz->context);
        foreach ($structure as $slot) {
            if (!empty($slot->questiontext)) {
                $textonly = preg_replace(['~<audio\b.*?</audio>~is', '~<img\b[^>]*>~i'], '', $slot->questiontext);
                $existing[helper::normalise_option(html_to_text($textonly, 0, false))] = $slot->slot;
            }
        }
        $warnings = [];
        foreach ($sheet->questions as $q) {
            $norm = helper::normalise_option($q->plaintext);
            if ($norm !== '' && isset($existing[$norm])) {
                $warnings[] = get_string('warn_alreadyinquiz', 'local_planopolis',
                    (object) ['row' => $q->rownum, 'slot' => $existing[$norm]]);
            }
        }
        return $warnings;
    }

    /**
     * Build the Moodle XML document.
     *
     * @param question_sheet $sheet
     * @return string
     */
    public static function build_xml(question_sheet $sheet): string {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<quiz>\n";
        foreach ($sheet->questions as $q) {
            $xml .= "<question type=\"multichoice\">\n";
            $xml .= '<name><text>' . self::esc($q->name) . "</text></name>\n";
            $xml .= '<questiontext format="html"><text>' . self::cdata($q->html) . '</text>'
                . self::files_xml(self::files_for($q->html, $q->files)) . "</questiontext>\n";
            $xml .= '<generalfeedback format="html"><text>' . self::cdata($q->feedback) . '</text>'
                . self::files_xml(self::files_for($q->feedback, $q->files)) . "</generalfeedback>\n";
            $xml .= '<defaultgrade>' . self::num($q->mark) . "</defaultgrade>\n";
            $xml .= "<penalty>0</penalty>\n<hidden>0</hidden>\n";
            $xml .= "<single>true</single>\n";
            $xml .= '<shuffleanswers>' . ($q->shuffle ? 'true' : 'false') . "</shuffleanswers>\n";
            $xml .= "<answernumbering>ABCD</answernumbering>\n<showstandardinstruction>0</showstandardinstruction>\n";
            foreach (['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'] as $fb) {
                $xml .= "<{$fb} format=\"html\"><text></text></{$fb}>\n";
            }
            foreach ($q->options as $letter => $option) {
                $fraction = $letter === $q->key ? 100 : ((float) $q->wrongfraction) * 100;
                $xml .= '<answer fraction="' . self::num($fraction) . '" format="html"><text>' . self::cdata($option->html)
                    . '</text>' . self::files_xml($option->files)
                    . "<feedback format=\"html\"><text></text></feedback></answer>\n";
            }
            if (trim($q->category) !== '') {
                $xml .= '<tags><tag><text>' . self::esc(trim($q->category)) . "</text></tag></tags>\n";
            }
            $xml .= "</question>\n";
        }
        return $xml . "</quiz>\n";
    }

    /**
     * Import the questions and add them to the quiz.
     *
     * @param question_sheet $sheet validated sheet without errors
     * @param stdClass $quiz quiz record with ->cmid and ->context
     * @param stdClass $course
     * @param bool $setmaxgrade make the quiz maximum grade equal to the total of all question marks
     * @return stdClass summary: imported, totalmarks, sumgrades, grade
     */
    public static function import(question_sheet $sheet, stdClass $quiz, stdClass $course, bool $setmaxgrade): stdClass {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/format/xml/format.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        if ($sheet->errors) {
            throw new \moodle_exception('err_fixfirst', 'local_planopolis');
        }
        if (quiz_has_attempts($quiz->id)) {
            throw new \moodle_exception('err_quizhasattempts', 'local_planopolis');
        }

        $category = question_get_default_category($quiz->context->id, true);
        $xmlfile = make_request_directory() . '/questions.xml';
        file_put_contents($xmlfile, self::build_xml($sheet));

        $format = new \qformat_xml();
        $format->setCategory($category);
        $format->setContexts([$quiz->context]);
        $format->setCourse($course);
        $format->setFilename($xmlfile);
        $format->setRealfilename('planopolis.xml');
        $format->setMatchgrades('error');
        $format->setCatfromfile(false);
        $format->setContextfromfile(false);
        $format->setStoponerror(true);
        $format->set_display_progress(false);

        ob_start();
        $ok = $format->importpreprocess() && $format->importprocess() && $format->importpostprocess();
        $output = ob_get_clean();
        if (!$ok || count($format->questionids) !== count($sheet->questions)) {
            throw new \moodle_exception('err_importfailed', 'local_planopolis', '', strip_tags($output));
        }

        $quizrecord = $DB->get_record('quiz', ['id' => $quiz->id], '*', MUST_EXIST);
        $quizrecord->cmid = $quiz->cmid;
        foreach (array_values($format->questionids) as $i => $questionid) {
            quiz_add_quiz_question($questionid, $quizrecord, 0, $sheet->questions[$i]->mark);
        }

        $quizobj = \mod_quiz\quiz_settings::create($quiz->id);
        $calculator = $quizobj->get_grade_calculator();
        $calculator->recompute_quiz_sumgrades();
        $sumgrades = (float) $DB->get_field('quiz', 'sumgrades', ['id' => $quiz->id]);
        if ($setmaxgrade && $sumgrades > 0) {
            $calculator->update_quiz_maximum_grade($sumgrades);
        }

        return (object) [
            'imported' => count($format->questionids),
            'totalmarks' => $sheet->total_marks(),
            'sumgrades' => $sumgrades,
            'grade' => (float) $DB->get_field('quiz', 'grade', ['id' => $quiz->id]),
        ];
    }

    /**
     * Files referenced from a piece of HTML.
     *
     * @param string $html
     * @param array $files filename => path
     * @return array
     */
    protected static function files_for(string $html, array $files): array {
        return array_filter($files, fn($name) => str_contains($html, '@@PLUGINFILE@@/' . rawurlencode($name)),
            ARRAY_FILTER_USE_KEY);
    }

    /**
     * XML for embedded files.
     *
     * @param array $files filename => path
     * @return string
     */
    protected static function files_xml(array $files): string {
        $xml = '';
        foreach ($files as $name => $path) {
            $xml .= '<file name="' . self::esc($name) . '" path="/" encoding="base64">'
                . base64_encode(file_get_contents($path)) . '</file>';
        }
        return $xml;
    }

    /**
     * Escape text for XML.
     *
     * @param string $text
     * @return string
     */
    protected static function esc(string $text): string {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Wrap HTML in CDATA.
     *
     * @param string $html
     * @return string
     */
    protected static function cdata(string $html): string {
        return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $html) . ']]>';
    }

    /**
     * Format a number without trailing zeros.
     *
     * @param float $n
     * @return string
     */
    protected static function num(float $n): string {
        return rtrim(rtrim(sprintf('%.7F', $n), '0'), '.');
    }
}
