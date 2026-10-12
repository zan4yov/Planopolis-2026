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
 * Loads Moodle's config.php from any supported directory layout.
 *
 * Counting "../" by hand breaks whenever Moodle moves things. Before 5.1 a plugin sat in
 * <dirroot>/local/; from 5.1 it sits in <dirroot>/public/local/ while config.php stays at
 * <dirroot>. The pages of this plugin and its CLI script also sit at different depths, so
 * every file needed a different count and one wrong count failed in a way that named the
 * wrong line: the include itself succeeded, $CFG was never set, and the next line reported
 * "Undefined variable $CFG".
 *
 * So this walks up from the including file and takes the first config.php that actually
 * bootstraps Moodle, judged by the result rather than by its path. A placeholder that
 * parses but leaves $CFG unset is therefore skipped rather than accepted.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Deliberately not inside a function: config.php must be required at global scope.
$planoconfigtried = [];
for ($planodir = __DIR__, $planodepth = 0;
        $planodepth < 6 && $planodir !== dirname($planodir);
        $planodir = dirname($planodir), $planodepth++) {

    $planocandidate = $planodir . '/config.php';
    if (!is_readable($planocandidate)) {
        continue;
    }
    $planoconfigtried[] = $planocandidate;
    require($planocandidate);

    // The only reliable test is whether Moodle came up.
    if (isset($CFG) && !empty($CFG->libdir)) {
        break;
    }
}

if (!isset($CFG) || empty($CFG->libdir)) {
    $planomessage = "local_planopolis: could not load Moodle's config.php.\n"
        . '  searched upwards from: ' . __DIR__ . "\n"
        . '  config.php files read: '
        . ($planoconfigtried ? implode(', ', $planoconfigtried) : 'none found') . "\n"
        . "  none of them defined \$CFG->libdir.\n";
    if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
        fwrite(STDERR, $planomessage);
    } else {
        header('Content-Type: text/plain; charset=utf-8', true, 500);
        echo $planomessage;
    }
    exit(1);
}

unset($planoconfigtried, $planodir, $planodepth, $planocandidate, $planomessage);
