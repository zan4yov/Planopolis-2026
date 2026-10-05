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
 * Reads and validates the question spreadsheet (optionally inside a ZIP together with images and audio).
 *
 * Nothing is written to the database here; the result is checked by the admin in a preview
 * and then passed to {@see question_importer}.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_sheet {

    /** @var string[] option letters supported by the template. */
    const LETTERS = ['A', 'B', 'C', 'D', 'E'];

    /** @var string[] allowed image extensions. */
    const IMAGE_EXT = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];

    /** @var string[] allowed audio extensions. */
    const AUDIO_EXT = ['mp3', 'wav', 'ogg', 'm4a'];

    /** @var stdClass[] valid parsed questions. */
    public array $questions = [];

    /** @var string[] blocking problems. */
    public array $errors = [];

    /** @var string[] non-blocking remarks. */
    public array $warnings = [];

    /** @var array lower-case basename => path on disk, for media found in the ZIP. */
    protected array $media = [];

    /** @var array lower-case basenames that were referenced. */
    protected array $mediaused = [];

    /** @var int number of example rows skipped. */
    public int $skippedexamples = 0;

    /** @var string name of the spreadsheet file that was read. */
    public string $sheetfilename = '';

    /**
     * Column header spellings accepted for each logical column (normalised by helper::normalise_header).
     *
     * @return array
     */
    public static function header_aliases(): array {
        $aliases = [
            'no' => ['no', 'nomor', 'nomer', 'number', 'nosoal'],
            'question' => ['soal', 'pertanyaan', 'question', 'tekssoal', 'teksoal'],
            'image' => ['gambarsoal', 'gambar', 'image', 'fotosoal', 'imagesoal'],
            'audio' => ['audiosoal', 'audio', 'suara', 'suarasoal'],
            'key' => ['kunci', 'kuncijawaban', 'jawabanbenar', 'answer', 'key', 'correct', 'correctanswer'],
            'mark' => ['nilaibenar', 'poinbenar', 'skorbenar', 'mark', 'bobot', 'nilai', 'poin'],
            'penalty' => ['nilaisalah', 'poinsalah', 'skorsalah', 'penalty', 'minus', 'nilaijawabansalah'],
            'category' => ['kategori', 'topik', 'category', 'topic', 'materi'],
            'feedback' => ['pembahasan', 'feedback', 'penjelasan'],
            'shuffle' => ['acakopsi', 'acak', 'shuffle', 'shuffleoptions', 'acakurutanopsi'],
        ];
        foreach (self::LETTERS as $letter) {
            $l = strtolower($letter);
            $aliases['opt' . $letter] = ["opsi{$l}", "pilihan{$l}", $l, "option{$l}", "jawaban{$l}"];
        }
        return $aliases;
    }

    /**
     * Load an uploaded .xlsx/.xls/.ods/.csv file or a .zip containing one of those plus media files.
     *
     * @param string $path path on disk
     * @param string $filename original filename
     * @param bool $defaultshuffle shuffle options unless the row says otherwise
     * @return self
     */
    public static function from_upload(string $path, string $filename, bool $defaultshuffle = true): self {
        $sheet = new self();
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === 'zip') {
            $dir = make_request_directory();
            $packer = get_file_packer('application/zip');
            $packer->extract_to_pathname($path, $dir);
            $spreadsheets = [];
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                /** @var \SplFileInfo $file */
                $name = $file->getFilename();
                if (str_contains($file->getPathname(), '__MACOSX') || str_starts_with($name, '.') || str_starts_with($name, '~$')) {
                    continue;
                }
                $fext = strtolower($file->getExtension());
                if (in_array($fext, ['xlsx', 'xls', 'ods', 'csv'])) {
                    $spreadsheets[] = $file->getPathname();
                } else if (in_array($fext, array_merge(self::IMAGE_EXT, self::AUDIO_EXT))) {
                    $key = \core_text::strtolower($name);
                    if (isset($sheet->media[$key])) {
                        $sheet->warnings[] = get_string('warn_duplicatemedia', 'local_planopolis', $name);
                    }
                    $sheet->media[$key] = $file->getPathname();
                }
            }
            if (count($spreadsheets) === 0) {
                $sheet->errors[] = get_string('err_zipnosheet', 'local_planopolis');
                return $sheet;
            }
            if (count($spreadsheets) > 1) {
                $sheet->errors[] = get_string('err_zipmanysheets', 'local_planopolis');
                return $sheet;
            }
            $path = $spreadsheets[0];
            $filename = basename($path);
        }
        $sheet->sheetfilename = $filename;
        try {
            $rows = helper::read_spreadsheet($path, $filename, 'Soal');
        } catch (\Throwable $e) {
            $sheet->errors[] = get_string('err_cannotread', 'local_planopolis', $e->getMessage());
            return $sheet;
        }
        $sheet->parse_rows($rows, $defaultshuffle);
        return $sheet;
    }

    /**
     * Parse and validate the rows.
     *
     * @param array $rows
     * @param bool $defaultshuffle
     */
    protected function parse_rows(array $rows, bool $defaultshuffle): void {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        [$headerrow, $map, $missing] = helper::map_headers($rows, self::header_aliases(), ['question', 'optA', 'optB', 'key']);
        if ($missing) {
            $this->errors[] = get_string('err_missingcolumns', 'local_planopolis', implode(', ', array_map(
                fn($k) => get_string('col_' . $k, 'local_planopolis'), $missing)));
            return;
        }

        $fractions = array_keys(\question_bank::fraction_options_full());
        $seentexts = [];

        foreach ($rows as $rownum => $row) {
            if ($rownum <= $headerrow) {
                continue;
            }
            $get = fn(string $key) => isset($map[$key]) ? ($row[$map[$key]] ?? '') : '';
            $no = $get('no');
            if (preg_match('/^(contoh|example)/i', $no)) {
                $this->skippedexamples++;
                continue;
            }
            $rawoptions = [];
            foreach (self::LETTERS as $letter) {
                $rawoptions[$letter] = $get('opt' . $letter);
            }
            $qtext = $get('question');
            $image = $get('image');
            $audio = $get('audio');
            $keyraw = $get('key');
            if ($qtext === '' && $image === '' && $audio === '' && $keyraw === '' && implode('', $rawoptions) === '') {
                // Pre-numbered but unused template row.
                continue;
            }

            $rowerrors = [];
            $rowwarnings = [];
            $label = get_string('rowlabel', 'local_planopolis', (object) ['row' => $rownum, 'no' => $no !== '' ? $no : '-']);

            // Question text and media.
            $files = [];
            $qhtml = $this->render_text($qtext, $files, $rowerrors);
            foreach ($this->split_list($image) as $img) {
                $qhtml .= '<p>' . $this->media_tag('img', $img, $files, $rowerrors) . '</p>';
            }
            foreach ($this->split_list($audio) as $aud) {
                $qhtml .= '<p>' . $this->media_tag('audio', $aud, $files, $rowerrors) . '</p>';
            }
            if ($qtext === '' && $image === '' && $audio === '') {
                $rowerrors[] = get_string('err_emptyquestion', 'local_planopolis');
            }

            // Options: must be contiguous from A, at least two, no duplicates.
            $options = [];
            $gap = false;
            $seenoptions = [];
            foreach (self::LETTERS as $letter) {
                $raw = $rawoptions[$letter];
                if ($raw === '') {
                    $gap = true;
                    continue;
                }
                if ($gap) {
                    $rowerrors[] = get_string('err_optiongap', 'local_planopolis', $letter);
                }
                $optfiles = [];
                $html = $this->render_text($raw, $optfiles, $rowerrors);
                $norm = helper::normalise_option(preg_replace_callback('/\[(img|gambar|audio)\s*:\s*([^\]]+)\]/iu',
                    fn($m) => ' [' . strtolower($m[1]) . ':' . \core_text::strtolower(trim($m[2])) . '] ', $raw));
                if (isset($seenoptions[$norm])) {
                    $rowerrors[] = get_string('err_duplicateoption', 'local_planopolis',
                        (object) ['a' => $seenoptions[$norm], 'b' => $letter, 'text' => s($raw)]);
                } else {
                    $seenoptions[$norm] = $letter;
                }
                $options[$letter] = (object) ['html' => $html, 'files' => $optfiles, 'raw' => $raw];
            }
            if (count($options) < 2) {
                $rowerrors[] = get_string('err_fewoptions', 'local_planopolis');
            }

            // Answer key: exactly one letter that has an option.
            $key = strtoupper(trim(preg_replace('/^(opsi|pilihan|option)\s*/i', '', $keyraw)));
            if ($key === '') {
                $rowerrors[] = get_string('err_nokey', 'local_planopolis');
            } else if (!in_array($key, self::LETTERS, true)) {
                $rowerrors[] = get_string('err_badkey', 'local_planopolis', s($keyraw));
            } else if (!isset($options[$key])) {
                $rowerrors[] = get_string('err_keyempty', 'local_planopolis', $key);
            }

            // Scoring.
            $markraw = str_replace(',', '.', $get('mark'));
            $mark = $markraw === '' ? 1.0 : (is_numeric($markraw) ? (float) $markraw : null);
            if ($mark === null || $mark <= 0 || $mark > 1000) {
                $rowerrors[] = get_string('err_badmark', 'local_planopolis', s($get('mark')));
                $mark = 1.0;
            }
            $penaltyraw = str_replace(',', '.', $get('penalty'));
            $penalty = $penaltyraw === '' ? 0.0 : (is_numeric($penaltyraw) ? (float) $penaltyraw : null);
            $wrongfraction = '0.0';
            if ($penalty === null || $penalty > 0) {
                $rowerrors[] = get_string('err_badpenalty', 'local_planopolis', s($get('penalty')));
            } else if ($penalty < 0) {
                $ratio = $penalty / $mark;
                $match = null;
                foreach ($fractions as $f) {
                    if (abs((float) $f - $ratio) < 0.00005) {
                        $match = $f;
                        break;
                    }
                }
                if ($match === null) {
                    $rowerrors[] = get_string('err_penaltyratio', 'local_planopolis',
                        (object) ['penalty' => $penaltyraw, 'mark' => $markraw === '' ? '1' : $markraw]);
                } else {
                    $wrongfraction = (string) $match;
                }
            }

            // Shuffle flag.
            $shuffle = $defaultshuffle;
            $shuffleraw = \core_text::strtolower($get('shuffle'));
            if ($shuffleraw !== '') {
                if (in_array($shuffleraw, ['y', 'ya', 'yes', 'true', '1'])) {
                    $shuffle = true;
                } else if (in_array($shuffleraw, ['n', 't', 'tidak', 'no', 'false', '0'])) {
                    $shuffle = false;
                } else {
                    $rowwarnings[] = get_string('warn_badshuffle', 'local_planopolis', s($get('shuffle')));
                }
            }

            // Same question twice in the file.
            $normtext = helper::normalise_option($qtext . ' ' . $image . ' ' . $audio);
            if (isset($seentexts[$normtext])) {
                $rowwarnings[] = get_string('warn_duplicatequestion', 'local_planopolis', $seentexts[$normtext]);
            } else {
                $seentexts[$normtext] = $rownum;
            }

            $feedbackhtml = $get('feedback') !== '' ? $this->render_text($get('feedback'), $files, $rowerrors) : '';

            foreach ($rowerrors as $msg) {
                $this->errors[] = $label . ': ' . $msg;
            }
            foreach ($rowwarnings as $msg) {
                $this->warnings[] = $label . ': ' . $msg;
            }
            if ($rowerrors) {
                continue;
            }

            $plain = trim(preg_replace('/\s+/u', ' ', $qtext));
            $namebase = $plain !== '' ? $plain : ($image !== '' ? $image : $audio);
            $this->questions[] = (object) [
                'rownum' => $rownum,
                'no' => $no,
                'name' => shorten_text(($no !== '' ? $no . '. ' : '') . $namebase, 200),
                'plaintext' => $plain,
                'normtext' => $normtext,
                'html' => $qhtml,
                'files' => $files,
                'options' => $options,
                'key' => $key,
                'mark' => $mark,
                'wrongfraction' => $wrongfraction,
                'penalty' => $penalty,
                'category' => $get('category'),
                'feedback' => $feedbackhtml,
                'shuffle' => $shuffle,
            ];
        }

        if (!$this->questions && !$this->errors) {
            $this->errors[] = get_string('err_noquestions', 'local_planopolis');
        }
        foreach (array_diff(array_keys($this->media), array_keys($this->mediaused)) as $unused) {
            $this->warnings[] = get_string('warn_unusedmedia', 'local_planopolis', basename($this->media[$unused]));
        }
    }

    /**
     * Convert cell text to safe HTML, replacing [img:file] and [audio:file] markers.
     *
     * @param string $text
     * @param array $files collects filename => path used
     * @param array $errors collects errors
     * @return string html
     */
    protected function render_text(string $text, array &$files, array &$errors): string {
        $parts = preg_split('/(\[(?:img|gambar|audio)\s*:\s*[^\]]+\])/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $html = '';
        foreach ($parts as $part) {
            if (preg_match('/^\[(img|gambar|audio)\s*:\s*([^\]]+)\]$/iu', $part, $m)) {
                $type = strtolower($m[1]) === 'audio' ? 'audio' : 'img';
                $html .= $this->media_tag($type, trim($m[2]), $files, $errors);
            } else if ($part !== '') {
                $html .= nl2br(s($part), false);
            }
        }
        return $html;
    }

    /**
     * Build an <img> or <audio> tag pointing at an embedded file (@@PLUGINFILE@@) and record the file.
     *
     * @param string $type img|audio
     * @param string $name filename as typed in the sheet
     * @param array $files
     * @param array $errors
     * @return string
     */
    protected function media_tag(string $type, string $name, array &$files, array &$errors): string {
        $key = \core_text::strtolower(basename(str_replace('\\', '/', $name)));
        $ext = pathinfo($key, PATHINFO_EXTENSION);
        $allowed = $type === 'img' ? self::IMAGE_EXT : self::AUDIO_EXT;
        if (!in_array($ext, $allowed, true)) {
            $errors[] = get_string($type === 'img' ? 'err_badimageext' : 'err_badaudioext', 'local_planopolis', s($name));
            return '';
        }
        if (!isset($this->media[$key])) {
            $errors[] = get_string('err_medianotfound', 'local_planopolis', s($name));
            return '';
        }
        $this->mediaused[$key] = true;
        $filename = clean_filename(basename($this->media[$key]));
        $files[$filename] = $this->media[$key];
        $url = '@@PLUGINFILE@@/' . rawurlencode($filename);
        if ($type === 'img') {
            return '<img src="' . $url . '" alt="' . s($filename) . '" class="img-fluid" style="max-width:100%;height:auto">';
        }
        $mime = ['mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4'][$ext];
        return '<audio controls="controls" controlsList="nodownload"><source src="' . $url . '" type="' . $mime . '">'
            . s($filename) . '</audio>';
    }

    /**
     * Split "a.png; b.png" style lists.
     *
     * @param string $value
     * @return string[]
     */
    protected function split_list(string $value): array {
        return array_values(array_filter(array_map('trim', preg_split('/[;,\n]/', $value)), fn($v) => $v !== ''));
    }

    /**
     * Total points of the valid questions.
     *
     * @return float
     */
    public function total_marks(): float {
        return array_sum(array_map(fn($q) => $q->mark, $this->questions));
    }
}
