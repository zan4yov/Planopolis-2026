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

use mod_quiz\quiz_attempt;
use stdClass;

/**
 * Builds the ranked result list of a quiz.
 *
 * One row per enrolled participant: the best finished attempt (highest score, then earliest finish),
 * with the number of correct, wrong and unanswered questions and the number of integrity events.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class results {

    /**
     * Column definitions for display and Excel export.
     *
     * @return array key => heading
     */
    public static function columns(): array {
        $keys = ['rank', 'username', 'fullname', 'institution', 'group', 'score', 'correct', 'wrong', 'blank',
            'started', 'finished', 'duration', 'leftpage', 'status'];
        $cols = [];
        foreach ($keys as $key) {
            $cols[$key] = get_string('res_' . $key, 'local_planopolis');
        }
        return $cols;
    }

    /**
     * Compute the rows.
     *
     * @param stdClass $quiz quiz record with ->cmid
     * @param stdClass $course
     * @return array list of associative arrays keyed like columns()
     */
    public static function compute(stdClass $quiz, stdClass $course): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/group/lib.php');

        $context = \core\context\course::instance($course->id);
        $students = get_role_users($DB->get_field('role', 'id', ['shortname' => 'student']), $context, false,
            'u.id, u.username, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename,
             u.alternatename, u.institution', 'u.username');

        $attempts = $DB->get_records('quiz_attempts', ['quiz' => $quiz->id, 'preview' => 0], 'userid, attempt');
        $byuser = [];
        foreach ($attempts as $attempt) {
            $byuser[$attempt->userid][] = $attempt;
        }

        $logcounts = $DB->get_records_sql_menu("
                SELECT l.attemptid, COUNT(1)
                  FROM {local_planopolis_log} l
                  JOIN {quiz_attempts} qa ON qa.id = l.attemptid
                 WHERE qa.quiz = ? AND l.eventtype = 'leave'
              GROUP BY l.attemptid", [$quiz->id]);

        $groups = [];
        foreach (groups_get_all_groups($course->id, 0, 0, 'g.*', true) as $group) {
            foreach ($group->members ?? [] as $memberid) {
                $groups[$memberid][] = $group->name;
            }
        }

        $maxgrade = (float) $quiz->grade;
        $sumgrades = (float) $quiz->sumgrades;
        $rows = [];
        foreach ($students as $user) {
            $row = [
                'rank' => '',
                'username' => $user->username,
                'fullname' => helper::display_name($user),
                'institution' => $user->institution,
                'group' => implode(', ', $groups[$user->id] ?? []),
                'score' => null,
                'correct' => '', 'wrong' => '', 'blank' => '',
                'started' => '', 'finished' => '', 'duration' => '',
                'leftpage' => '',
                'status' => get_string('res_notstarted', 'local_planopolis'),
                '_sort' => [-INF, PHP_INT_MAX],
            ];
            $userattempts = $byuser[$user->id] ?? [];
            $best = null;
            $inprogress = null;
            foreach ($userattempts as $attempt) {
                if ($attempt->state === quiz_attempt::FINISHED) {
                    if (!$best || $attempt->sumgrades > $best->sumgrades
                            || ($attempt->sumgrades == $best->sumgrades && $attempt->timefinish < $best->timefinish)) {
                        $best = $attempt;
                    }
                } else {
                    $inprogress = $attempt;
                }
            }
            $shown = $best ?? $inprogress;
            if ($shown) {
                $row['started'] = userdate($shown->timestart, get_string('strftimedatetimeshort', 'langconfig'));
                $row['leftpage'] = (int) ($logcounts[$shown->id] ?? 0);
            }
            if ($best) {
                $score = $sumgrades > 0 ? quiz_rescale_grade($best->sumgrades, $quiz, false) : 0.0;
                $counts = self::count_answers($best->uniqueid);
                $row['score'] = round((float) $score, 2);
                $row['correct'] = $counts['correct'];
                $row['wrong'] = $counts['wrong'];
                $row['blank'] = $counts['blank'];
                $row['finished'] = userdate($best->timefinish, get_string('strftimedatetimeshort', 'langconfig'));
                $row['duration'] = format_time(max(0, $best->timefinish - $best->timestart));
                $row['status'] = get_string('res_finished', 'local_planopolis');
                $row['_sort'] = [(float) $best->sumgrades, $best->timefinish - $best->timestart];
            } else if ($inprogress) {
                $row['status'] = get_string($inprogress->state === quiz_attempt::OVERDUE ? 'res_overdue' : 'res_inprogress',
                    'local_planopolis');
            }
            $rows[] = $row;
        }

        // Highest score first; ties broken by shorter duration.
        usort($rows, function($a, $b) {
            return [$b['_sort'][0], $a['_sort'][1]] <=> [$a['_sort'][0], $b['_sort'][1]];
        });
        $rank = 0;
        $previous = null;
        foreach ($rows as $i => &$row) {
            if ($row['score'] !== null) {
                if ($previous === null || $row['_sort'] !== $previous) {
                    $rank = $i + 1;
                }
                $row['rank'] = $rank;
                $previous = $row['_sort'];
            }
            if ($row['score'] === null) {
                $row['score'] = '';
            }
            unset($row['_sort']);
        }
        unset($row);
        return $rows;
    }

    /**
     * Count correct, wrong and blank questions of one attempt.
     *
     * @param int $uniqueid question usage id
     * @return array
     */
    public static function count_answers(int $uniqueid): array {
        $quba = \question_engine::load_questions_usage_by_activity($uniqueid);
        $counts = ['correct' => 0, 'wrong' => 0, 'blank' => 0];
        foreach ($quba->get_slots() as $slot) {
            $qa = $quba->get_question_attempt($slot);
            $fraction = $qa->get_fraction();
            if ($qa->get_response_summary() === null || $fraction === null) {
                $counts['blank']++;
            } else if ($fraction >= 0.9999999) {
                $counts['correct']++;
            } else {
                $counts['wrong']++;
            }
        }
        return $counts;
    }
}
