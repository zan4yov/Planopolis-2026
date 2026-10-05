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

use core_question\local\bank\question_version_status;
use stdClass;

/**
 * Pre-exam checks for a quiz, targeting the two problems reported in the 2024 evaluation:
 * questions that did not appear, and questions with duplicate answer options.
 *
 * Works on every question in the quiz, including questions created by hand in the web editor.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auditor {

    /** @var string severity: blocks a fair exam. */
    const ERROR = 'danger';
    /** @var string severity: should be looked at. */
    const WARNING = 'warning';
    /** @var string severity: fine. */
    const OK = 'success';

    /**
     * Run all checks.
     *
     * @param stdClass $quiz quiz record with ->cmid and ->context
     * @return array list of [severity, slot label, message, edit url|null]
     */
    public static function run(stdClass $quiz): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $issues = [];
        $structure = \mod_quiz\question\bank\qbank_helper::get_question_structure($quiz->id, $quiz->context);
        $general = get_string('audit_general', 'local_planopolis');

        if (!$structure) {
            $issues[] = [self::ERROR, $general, get_string('audit_noquestions', 'local_planopolis'), null];
            return $issues;
        }

        // Random slots: enough questions available for every random slot with the same filter?
        $randomneeds = [];
        foreach ($structure as $slot) {
            if (!empty($slot->random)) {
                $key = json_encode($slot->filtercondition['filter'] ?? []);
                $randomneeds[$key]['count'] = ($randomneeds[$key]['count'] ?? 0) + 1;
                $randomneeds[$key]['filter'] = $slot->filtercondition['filter'] ?? [];
                $randomneeds[$key]['slots'][] = $slot->slot;
            }
        }
        if ($randomneeds) {
            $fixedids = array_filter(array_map(fn($s) => empty($s->random) ? $s->questionbankentryid : null, $structure));
            $loader = new \core_question\local\bank\random_question_loader(new \qubaid_list([]));
            foreach ($randomneeds as $need) {
                $available = $loader->count_filtered_questions($need['filter']);
                $label = get_string('audit_slots', 'local_planopolis', implode(', ', $need['slots']));
                if ($available < $need['count']) {
                    $issues[] = [self::ERROR, $label, get_string('audit_randomshort', 'local_planopolis',
                        (object) ['need' => $need['count'], 'have' => $available]), null];
                } else if ($fixedids) {
                    $issues[] = [self::WARNING, $label, get_string('audit_randommixed', 'local_planopolis'), null];
                }
            }
        }

        // Fixed slots.
        $seentext = [];
        foreach ($structure as $slot) {
            if (!empty($slot->random)) {
                continue;
            }
            $label = get_string('audit_slot', 'local_planopolis', $slot->slot);
            $editurl = !empty($slot->questionid) && is_numeric($slot->questionid)
                ? new \core\url('/question/bank/editquestion/question.php',
                    ['id' => $slot->questionid, 'cmid' => $quiz->cmid, 'returnurl' => '/local/planopolis/audit.php'])
                : null;

            if ($slot->qtype === 'missingtype') {
                $issues[] = [self::ERROR, $label, get_string('audit_missing', 'local_planopolis'), null];
                continue;
            }
            if ($slot->status === question_version_status::QUESTION_STATUS_DRAFT) {
                $issues[] = [self::ERROR, $label, get_string('audit_draft', 'local_planopolis'), $editurl];
            } else if ($slot->status === question_version_status::QUESTION_STATUS_HIDDEN) {
                $issues[] = [self::WARNING, $label, get_string('audit_hidden', 'local_planopolis'), $editurl];
            }
            if ((float) $slot->maxmark <= 0) {
                $issues[] = [self::ERROR, $label, get_string('audit_zeromark', 'local_planopolis'), $editurl];
            }
            if ($slot->qtype !== 'multichoice') {
                $issues[] = [self::WARNING, $label, get_string('audit_notmc', 'local_planopolis', $slot->qtype), $editurl];
            }

            $text = helper::normalise_option(html_to_text($slot->questiontext ?? '', 0, false));
            $imgs = self::media_signature($slot->questiontext ?? '');
            $sig = $text . '|' . $imgs;
            if ($text !== '' && isset($seentext[$sig])) {
                $issues[] = [self::WARNING, $label, get_string('audit_duplicatequestion', 'local_planopolis', $seentext[$sig]),
                    $editurl];
            } else {
                $seentext[$sig] = $slot->slot;
            }

            if ($slot->qtype === 'multichoice') {
                foreach (self::check_multichoice((int) $slot->questionid) as [$severity, $message]) {
                    $issues[] = [$severity, $label, $message, $editurl];
                }
            }
        }

        // Settings that caused trouble or leak answers.
        $reviewduring = (int) $quiz->reviewrightanswer & \mod_quiz\question\display_options::DURING;
        $reviewimmediately = (int) $quiz->reviewrightanswer & \mod_quiz\question\display_options::IMMEDIATELY_AFTER;
        if ($reviewduring || $reviewimmediately) {
            $issues[] = [self::WARNING, $general, get_string('audit_reviewleak', 'local_planopolis'), null];
        }
        if ((int) $quiz->attempts !== 1) {
            $issues[] = [self::WARNING, $general, get_string('audit_attempts', 'local_planopolis'), null];
        }
        if (empty($quiz->timeclose)) {
            $issues[] = [self::WARNING, $general, get_string('audit_noclose', 'local_planopolis'), null];
        }
        if ((float) $quiz->sumgrades <= 0) {
            $issues[] = [self::ERROR, $general, get_string('audit_nosumgrades', 'local_planopolis'), null];
        }
        return $issues;
    }

    /**
     * Checks on a single multiple-choice question.
     *
     * @param int $questionid
     * @return array list of [severity, message]
     */
    public static function check_multichoice(int $questionid): array {
        global $DB;
        $issues = [];
        $answers = $DB->get_records('question_answers', ['question' => $questionid], 'id');
        $options = $DB->get_record('qtype_multichoice_options', ['questionid' => $questionid]);
        $letters = range('A', 'Z');

        if (count($answers) < 2) {
            $issues[] = [self::ERROR, get_string('audit_fewoptions', 'local_planopolis')];
        }
        $seen = [];
        $full = 0;
        $maxfraction = -INF;
        $i = 0;
        foreach ($answers as $answer) {
            $letter = $letters[$i++] ?? '?';
            $plain = helper::normalise_option(html_to_text($answer->answer, 0, false));
            $norm = $plain . '|' . self::media_signature($answer->answer);
            if ($plain === '' && self::media_signature($answer->answer) === '') {
                $issues[] = [self::ERROR, get_string('audit_emptyoption', 'local_planopolis', $letter)];
                continue;
            }
            if (isset($seen[$norm])) {
                $issues[] = [self::ERROR, get_string('err_duplicateoption', 'local_planopolis',
                    (object) ['a' => $seen[$norm], 'b' => $letter, 'text' => s(trim(html_to_text($answer->answer, 0, false)))])];
            } else {
                $seen[$norm] = $letter;
            }
            if ((float) $answer->fraction >= 0.9999999) {
                $full++;
            }
            $maxfraction = max($maxfraction, (float) $answer->fraction);
        }
        if ($options && (int) $options->single === 1) {
            if ($full === 0) {
                $issues[] = [self::ERROR, get_string('audit_nocorrect', 'local_planopolis')];
            } else if ($full > 1) {
                $issues[] = [self::WARNING, get_string('audit_manycorrect', 'local_planopolis', $full)];
            }
        } else if ($options) {
            $issues[] = [self::WARNING, get_string('audit_multipleanswers', 'local_planopolis')];
        }
        if ($maxfraction < 0.9999999 && $maxfraction > -INF && $full === 0 && !($options && (int) $options->single === 1)) {
            $issues[] = [self::ERROR, get_string('audit_nocorrect', 'local_planopolis')];
        }
        return $issues;
    }

    /**
     * A signature of the images/audio used in some HTML, so image-only options are compared properly.
     *
     * @param string $html
     * @return string
     */
    protected static function media_signature(string $html): string {
        preg_match_all('/<(?:img|source|audio)[^>]+src="([^"]+)"/i', $html, $m);
        $names = array_map(fn($src) => \core_text::strtolower(basename(rawurldecode(parse_url($src, PHP_URL_PATH) ?? ''))), $m[1]);
        sort($names);
        return implode(',', $names);
    }
}
