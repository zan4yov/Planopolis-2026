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

use core\context;
use core\url;
use stdClass;

/**
 * Shared helpers for the Planopolis tools.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /** @var string Short name of the custom admin role created by cli/setup.php. */
    const ADMIN_ROLE = 'adminquiz';

    /**
     * Get the competition course configured by cli/setup.php (or chosen by the user).
     *
     * @param int $courseid optional explicit course id.
     * @return stdClass course record
     */
    public static function get_course(int $courseid = 0): stdClass {
        if (!$courseid) {
            $courseid = (int) get_config('local_planopolis', 'courseid');
        }
        if (!$courseid) {
            throw new \moodle_exception('nocourseconfigured', 'local_planopolis');
        }
        return get_course($courseid);
    }

    /**
     * Require login and the manage capability in the competition course; set up $PAGE.
     *
     * @param string $path page path, e.g. '/local/planopolis/index.php'
     * @param string $titlestring language string id for the page title
     * @param array $params url params
     * @return stdClass the course
     */
    public static function setup_page(string $path, string $titlestring, array $params = []): stdClass {
        global $PAGE;
        $course = self::get_course();
        require_login($course, false);
        $context = context\course::instance($course->id);
        require_capability('local/planopolis:manage', $context);
        $PAGE->set_url(new url($path, $params));
        $PAGE->set_context($context);
        $PAGE->set_pagelayout('admin');
        $title = get_string($titlestring, 'local_planopolis');
        $PAGE->set_title($title);
        $PAGE->set_heading(get_string('panel', 'local_planopolis'));
        if ($path !== '/local/planopolis/index.php') {
            $PAGE->navbar->add(get_string('panel', 'local_planopolis'), new url('/local/planopolis/index.php'));
        }
        $PAGE->navbar->add($title);
        return $course;
    }

    /**
     * All quizzes in the course, keyed by course-module id.
     *
     * @param stdClass $course
     * @return array cmid => stdClass quiz record with ->cmid and ->context
     */
    public static function get_quizzes(stdClass $course): array {
        global $DB;
        $modinfo = get_fast_modinfo($course);
        $result = [];
        foreach ($modinfo->get_instances_of('quiz') as $cm) {
            $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
            $quiz->cmid = $cm->id;
            $quiz->coursemodule = $cm->id;
            $quiz->context = context\module::instance($cm->id);
            $result[$cm->id] = $quiz;
        }
        return $result;
    }

    /**
     * Get one quiz of the course (the first one if $cmid is empty).
     *
     * @param stdClass $course
     * @param int $cmid
     * @return stdClass|null
     */
    public static function get_quiz(stdClass $course, int $cmid = 0): ?stdClass {
        $quizzes = self::get_quizzes($course);
        if ($cmid && isset($quizzes[$cmid])) {
            return $quizzes[$cmid];
        }
        return $quizzes ? reset($quizzes) : null;
    }

    /**
     * Options for a quiz selector.
     *
     * @param stdClass $course
     * @return array cmid => name
     */
    public static function quiz_menu(stdClass $course): array {
        $menu = [];
        foreach (self::get_quizzes($course) as $cmid => $quiz) {
            $menu[$cmid] = format_string($quiz->name);
        }
        return $menu;
    }

    /**
     * Normalise a spreadsheet header so different spellings map to the same key.
     *
     * "Opsi A *", "opsi a (wajib)" and "OPSI_A" all become "opsia".
     *
     * @param string $header
     * @return string
     */
    public static function normalise_header(string $header): string {
        $header = preg_replace('/\(.*?\)/u', '', $header);
        $header = \core_text::strtolower($header);
        return preg_replace('/[^a-z0-9]/', '', $header);
    }

    /**
     * Normalise an answer option for duplicate detection.
     *
     * Case, repeated spaces, surrounding punctuation and a trailing full stop are ignored, so
     * "Ayam", " ayam." and "AYAM" are all duplicates of each other.
     *
     * @param string $text plain text of the option (with [img:...] markers kept)
     * @return string
     */
    public static function normalise_option(string $text): string {
        $text = \core_text::strtolower(trim($text));
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text, " \t\n\r\0\x0B.,;:");
    }

    /**
     * Read a spreadsheet (xlsx, xls, ods or csv) into an array of rows of trimmed strings.
     *
     * @param string $filepath path on disk
     * @param string $filename original file name (for the extension)
     * @param string|null $preferredsheet sheet title to read if it exists
     * @return array list of [excel row number => array of cell strings]
     */
    public static function read_spreadsheet(string $filepath, string $filename, ?string $preferredsheet = null): array {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $readertypes = ['xlsx' => 'Xlsx', 'xls' => 'Xls', 'ods' => 'Ods', 'csv' => 'Csv'];
        if (!isset($readertypes[$ext])) {
            throw new \moodle_exception('unsupportedfiletype', 'local_planopolis', '', $filename);
        }
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($readertypes[$ext]);
        if ($ext === 'csv') {
            // Auto-detect ; or , separators (Indonesian Excel often saves CSV with ;).
            $firstline = (string) fgets(fopen($filepath, 'r'));
            $reader->setDelimiter(substr_count($firstline, ';') > substr_count($firstline, ',') ? ';' : ',');
            $reader->setInputEncoding('UTF-8');
        }
        $spreadsheet = $reader->load($filepath);
        $sheet = null;
        if ($preferredsheet) {
            foreach ($spreadsheet->getAllSheets() as $candidate) {
                if (\core_text::strtolower(trim($candidate->getTitle())) === \core_text::strtolower($preferredsheet)) {
                    $sheet = $candidate;
                    break;
                }
            }
        }
        $sheet = $sheet ?? $spreadsheet->getSheet(0);

        $rows = [];
        $highestrow = $sheet->getHighestDataRow();
        $highestcol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        for ($r = 1; $r <= $highestrow; $r++) {
            $row = [];
            $hasvalue = false;
            for ($c = 1; $c <= $highestcol; $c++) {
                $cell = $sheet->getCell([$c, $r]);
                $value = $cell->getValue();
                if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $value = $value->getPlainText();
                } else if (is_string($value) && str_starts_with($value, '=')) {
                    $value = $cell->getCalculatedValue();
                }
                if (is_float($value) && floor($value) == $value && abs($value) < 1e15) {
                    $value = (string) (int) $value;
                } else if (is_bool($value)) {
                    $value = $value ? 'TRUE' : 'FALSE';
                }
                $value = trim((string) $value);
                // Remove zero-width and non-breaking spaces that sneak in from copy-paste.
                $value = trim(str_replace(["\u{00A0}", "\u{200B}", "\u{FEFF}"], [' ', '', ''], $value));
                $row[$c - 1] = $value;
                if ($value !== '') {
                    $hasvalue = true;
                }
            }
            if ($hasvalue) {
                $rows[$r] = $row;
            }
        }
        $spreadsheet->disconnectWorksheets();
        return $rows;
    }

    /**
     * Find the header row and map columns to logical keys.
     *
     * @param array $rows as returned by read_spreadsheet()
     * @param array $aliases logical key => list of normalised header spellings
     * @param array $required logical keys that must exist
     * @return array [header row number, [key => column index], list of missing required keys]
     */
    public static function map_headers(array $rows, array $aliases, array $required): array {
        $best = [0, [], $required];
        $checked = 0;
        foreach ($rows as $rownum => $row) {
            if (++$checked > 15) {
                break;
            }
            $map = [];
            foreach ($row as $col => $value) {
                $norm = self::normalise_header($value);
                foreach ($aliases as $key => $spellings) {
                    if (!isset($map[$key]) && in_array($norm, $spellings, true)) {
                        $map[$key] = $col;
                        break;
                    }
                }
            }
            $missing = array_values(array_diff($required, array_keys($map)));
            if (count($map) > count($best[1])) {
                $best = [$rownum, $map, $missing];
            }
            if (!$missing) {
                return [$rownum, $map, []];
            }
        }
        return $best;
    }

    /**
     * Save a file uploaded through a filepicker draft area to a temp dir.
     *
     * @param int $draftitemid
     * @return array [path on disk, original filename]
     */
    public static function draft_file_to_temp(int $draftitemid): array {
        global $USER;
        $fs = get_file_storage();
        $usercontext = context\user::instance($USER->id);
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id DESC', false);
        if (!$files) {
            throw new \moodle_exception('nofileuploaded', 'local_planopolis');
        }
        $file = reset($files);
        $dir = make_request_directory();
        $path = $dir . '/' . clean_filename($file->get_filename());
        $file->copy_content_to($path);
        return [$path, $file->get_filename()];
    }

    /**
     * Generate an easy-to-read random password that satisfies the default Moodle password policy
     * (upper case, lower case, digit and a special character), e.g. "Kpatre-4827".
     * No ambiguous characters such as 0/O or 1/l are used.
     *
     * @return string
     */
    public static function generate_password(): string {
        global $CFG;
        $upper = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        $lower = 'abcdefghjkmnpqrstuvwxyz';
        $digits = '23456789';
        $minlength = max(10, (int) ($CFG->minpasswordlength ?? 8));
        $password = $upper[random_int(0, strlen($upper) - 1)];
        while (strlen($password) < $minlength - 5) {
            $password .= $lower[random_int(0, strlen($lower) - 1)];
        }
        $password .= '-';
        for ($i = 0; $i < 4; $i++) {
            $password .= $digits[random_int(0, strlen($digits) - 1)];
        }
        return $password;
    }

    /**
     * Render a Bootstrap badge.
     *
     * @param string $text
     * @param string $type success|danger|warning|info|secondary
     * @return string
     */
    public static function badge(string $text, string $type): string {
        return \html_writer::span($text, "badge bg-{$type} text-bg-{$type}");
    }
    /**
     * Send a formatted Excel file (bold header, filter, frozen header, real numbers) and stop.
     *
     * @param string $filename without extension
     * @param array $columns key => heading
     * @param array $rows list of associative arrays keyed like $columns
     * @param string $sheettitle
     */
    public static function download_xlsx(string $filename, array $columns, array $rows, string $sheettitle): void {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $title = trim(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $sheettitle));
        $sheet->setTitle(\core_text::substr($title !== '' ? $title : 'Sheet1', 0, 31));
        $keys = array_keys($columns);
        foreach (array_values($columns) as $i => $heading) {
            $sheet->setCellValue([$i + 1, 1], $heading);
        }
        $r = 2;
        foreach ($rows as $row) {
            foreach ($keys as $i => $key) {
                $value = $row[$key] ?? '';
                if (is_int($value) || is_float($value)) {
                    $sheet->setCellValue([$i + 1, $r], $value);
                } else {
                    $sheet->setCellValueExplicit([$i + 1, $r], (string) $value,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
            $r++;
        }
        $lastcol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($keys));
        $header = $sheet->getStyle("A1:{$lastcol}1");
        $header->getFont()->setBold(true);
        $header->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('46BDC6');
        $sheet->getStyle("A1:{$lastcol}" . max(1, $r - 1))->getFont()->setName('Arial')->setSize(10);
        $sheet->setAutoFilter("A1:{$lastcol}" . max(1, $r - 1));
        $sheet->freezePane('A2');
        for ($i = 1; $i <= count($keys); $i++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
        $filename = clean_filename($filename) . '.xlsx';
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, must-revalidate, max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Full name without the "-" placeholder used for participants that have no last name.
     *
     * @param \stdClass $user
     * @return string
     */
    public static function display_name(\stdClass $user): string {
        return preg_replace('/\s+-$/u', '', fullname($user));
    }
}
