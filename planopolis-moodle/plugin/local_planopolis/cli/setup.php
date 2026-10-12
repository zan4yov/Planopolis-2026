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
 * One-time (and safe to re-run) setup of a Moodle site as the Planopolis quiz platform.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'coursename' => 'Quiz Planopolis PWK 2026',
    'courseshort' => 'PLANOPOLIS2026',
    'quizname' => 'Quiz Planopolis PWK 2026',
    'timelimit' => 90,
    'lang' => 'id',
], ['h' => 'help']);

if ($options['help'] || $unrecognised) {
    echo "Configure this Moodle site as the Planopolis PWK 2026 quiz platform.

Options:
  --coursename=NAME   Full name of the competition course (default: Quiz Planopolis PWK 2026)
  --courseshort=CODE  Short name of the course (default: PLANOPOLIS2026)
  --quizname=NAME     Name of the first quiz (default: Quiz Planopolis PWK 2026)
  --timelimit=MIN     Time limit of the first quiz in minutes (default: 90)
  --lang=CODE         Site language to install and use, or 'en' (default: id)
  -h, --help          Show this help

Example:
  php public/local/planopolis/cli/setup.php --timelimit=120
";
    exit($unrecognised ? 1 : 0);
}

\core\session\manager::set_user(get_admin());
$syscontext = context_system::instance();

cli_heading('1. Site security and defaults');
$config = [
    'registerauth' => '',               // No self-registration: only admins create participant accounts.
    'guestloginbutton' => 0,
    'forcelogin' => 1,                  // Nothing is visible without logging in.
    'autologinguests' => 0,
    'messaging' => 0,                   // Participants cannot chat with each other.
    'enableblogs' => 0,
    'enablebadges' => 0,
    'enableanalytics' => 0,
    'defaulthomepage' => HOMEPAGE_MYCOURSES,
    'timezone' => 'Asia/Jakarta',
    'forcetimezone' => 'Asia/Jakarta',  // Everybody sees WIB times.
    'country' => 'ID',
    'hiddenuserfields' => 'description,email,city,country,webpage,icqnumber,skypeid,yahooid,aimid,msnid,lastip,' .
        'timezone,firstaccess,lastaccess,lastip,mycourses,groups,suspended',
];
foreach ($config as $name => $value) {
    set_config($name, $value);
    cli_writeln("  {$name} = " . (is_string($value) && $value === '' ? "''" : $value));
}

// Defaults for every new quiz created later (Add a quiz / round).
$quizdefaults = [
    'attempts' => 1,
    'grademethod' => QUIZ_GRADEHIGHEST,
    'questionsperpage' => 1,
    'navmethod' => QUIZ_NAVMETHOD_FREE,
    'shuffleanswers' => 1,
    'preferredbehaviour' => 'deferredfeedback',
    'overduehandling' => 'autosubmit',
    'timelimit' => ((int) $options['timelimit']) * 60,
    'reviewattempt' => 0x10000,           // During the attempt only.
    'reviewcorrectness' => 0,
    'reviewmaxmarks' => 0x10000 | 0x00010,
    'reviewmarks' => 0x00010,             // Marks visible only after the quiz closes.
    'reviewspecificfeedback' => 0,
    'reviewgeneralfeedback' => 0,
    'reviewrightanswer' => 0,
    'reviewoverallfeedback' => 0,
];
foreach ($quizdefaults as $name => $value) {
    set_config($name, $value, 'quiz');
}
cli_writeln('  quiz defaults: 1 attempt, 1 question per page, shuffled options, answers never revealed');

cli_heading('2. Roles');
// Participants: cannot change their registered name, cannot browse other participants.
$userrole = $DB->get_record('role', ['shortname' => 'user'], '*', MUST_EXIST);
assign_capability('moodle/user:editownprofile', CAP_PROHIBIT, $userrole->id, $syscontext->id, true);
cli_writeln('  Participants cannot edit their profile (name stays as registered)');

// Admin Quiz role.
$role = $DB->get_record('role', ['shortname' => 'adminquiz']);
if (!$role) {
    $roleid = create_role('Admin Quiz', 'adminquiz',
        'Planopolis committee: registers participants, imports questions, checks quizzes and downloads results.', 'manager');
    $role = $DB->get_record('role', ['id' => $roleid]);
    cli_writeln('  Created role Admin Quiz');
} else {
    cli_writeln('  Role Admin Quiz already exists – refreshing its permissions');
}
set_role_contextlevels($role->id, [CONTEXT_SYSTEM]);
// Start from the manager defaults, then take away everything that lets an admin create other admins
// or influence the exam in ways that should belong to the Super Admin only.
$DB->delete_records('role_capabilities', ['roleid' => $role->id, 'contextid' => $syscontext->id]);
foreach (get_default_capabilities('manager') as $cap => $permission) {
    assign_capability($cap, $permission, $role->id, $syscontext->id, true);
}
$prohibit = [
    'moodle/role:manage', 'moodle/role:override', 'moodle/role:safeoverride', 'moodle/role:switchroles',
    'moodle/user:loginas', 'moodle/course:delete', 'moodle/category:manage', 'moodle/backup:backupcourse',
    'moodle/restore:restorecourse', 'moodle/site:uploadusers', 'moodle/cohort:manage',
];
foreach ($prohibit as $cap) {
    if (get_capability_info($cap)) {
        assign_capability($cap, CAP_PROHIBIT, $role->id, $syscontext->id, true);
    }
}
foreach (['local/planopolis:manage', 'local/planopolis:bypassprotection', 'moodle/user:create', 'moodle/user:update',
        'moodle/user:viewdetails', 'moodle/user:viewalldetails', 'enrol/manual:enrol', 'enrol/manual:manage'] as $cap) {
    assign_capability($cap, CAP_ALLOW, $role->id, $syscontext->id, true);
}
// Admin Quiz may only hand out the student role (enrolling participants); never manager or admin roles.
$DB->delete_records('role_allow_assign', ['roleid' => $role->id]);
$DB->delete_records('role_allow_override', ['roleid' => $role->id]);
$DB->delete_records('role_allow_switch', ['roleid' => $role->id]);
$student = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
core_role_set_assign_allowed($role->id, $student->id);
foreach (['student', 'teacher', 'editingteacher', 'adminquiz'] as $viewable) {
    $target = $DB->get_record('role', ['shortname' => $viewable]);
    if ($target && !$DB->record_exists('role_allow_view', ['roleid' => $role->id, 'allowview' => $target->id])) {
        core_role_set_view_allowed($role->id, $target->id);
    }
}
accesslib_clear_all_caches(true);
cli_writeln('  Admin Quiz can: register participants, manage quizzes and questions, see results');
cli_writeln('  Admin Quiz cannot: give admin access, log in as participants, delete courses');

cli_heading('3. Competition course and quiz');
$category = $DB->get_record('course_categories', ['idnumber' => 'planopolis']);
if (!$category) {
    $category = core_course_category::create(['name' => 'Planopolis PWK 2026', 'idnumber' => 'planopolis']);
    $category = $DB->get_record('course_categories', ['id' => $category->id]);
}
$course = $DB->get_record('course', ['shortname' => $options['courseshort']]);
if (!$course) {
    $course = create_course((object) [
        'fullname' => $options['coursename'],
        'shortname' => $options['courseshort'],
        'category' => $category->id,
        'format' => 'topics',
        'numsections' => 0,
        'visible' => 1,
        'enablecompletion' => 0,
        'showgrades' => 0,
        'newsitems' => 0,
        'summary' => '',
        'summaryformat' => FORMAT_HTML,
    ]);
    cli_writeln("  Created course {$course->fullname} (id {$course->id})");
} else {
    cli_writeln("  Course {$course->fullname} already exists (id {$course->id})");
}
$coursecontext = context_course::instance($course->id);
// Participants must not browse the participant list or other participants' profiles.
foreach (['moodle/course:viewparticipants', 'moodle/user:viewdetails', 'moodle/site:viewparticipants'] as $cap) {
    if (get_capability_info($cap)) {
        role_change_permission($student->id, $coursecontext, $cap, CAP_PROHIBIT);
    }
}
set_config('courseid', $course->id, 'local_planopolis');
set_config('protection', 1, 'local_planopolis');
set_config('watermark', 1, 'local_planopolis');

$modinfo = get_fast_modinfo($course);
if (!$modinfo->get_instances_of('quiz')) {
    $moduleinfo = (object) [
        'modulename' => 'quiz',
        'module' => $DB->get_field('modules', 'id', ['name' => 'quiz']),
        'course' => $course->id,
        'section' => 0,
        'visible' => 1,
        'name' => $options['quizname'],
        'introeditor' => ['text' => '<p>Baca setiap soal dengan teliti. Pilih satu jawaban. Jawaban tersimpan otomatis ' .
            'saat berpindah soal. Klik <strong>Selesai</strong> lalu <strong>Kumpulkan semua dan selesai</strong> ' .
            'di akhir.</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
        'showdescription' => 0,
        'timeopen' => 0,
        'timeclose' => 0,
        'timelimit' => ((int) $options['timelimit']) * 60,
        'overduehandling' => 'autosubmit',
        'graceperiod' => 0,
        'preferredbehaviour' => 'deferredfeedback',
        'canredoquestions' => 0,
        'attempts' => 1,
        'attemptonlast' => 0,
        'grademethod' => QUIZ_GRADEHIGHEST,
        'decimalpoints' => 2,
        'questiondecimalpoints' => -1,
        'questionsperpage' => 1,
        'navmethod' => QUIZ_NAVMETHOD_FREE,
        'shuffleanswers' => 1,
        'sumgrades' => 0,
        'grade' => 100,
        'quizpassword' => '',
        'subnet' => '',
        'browsersecurity' => '-',
        'delay1' => 0,
        'delay2' => 0,
        'showuserpicture' => 0,
        'showblocks' => 0,
        'completionattemptsexhausted' => 0,
        'completionminattempts' => 0,
        'allowofflineattempts' => 0,
        'groupmode' => 0,
        'cmidnumber' => '',
        // Review: participants see their own answers while working, nothing afterwards except the
        // mark once the quiz is closed, so right answers cannot leak to people who have not finished.
        'attemptduring' => 1, 'correctnessduring' => 0, 'maxmarksduring' => 1, 'marksduring' => 0,
        'specificfeedbackduring' => 0, 'generalfeedbackduring' => 0, 'rightanswerduring' => 0, 'overallfeedbackduring' => 0,
        'attemptimmediately' => 0, 'correctnessimmediately' => 0, 'maxmarksimmediately' => 0, 'marksimmediately' => 0,
        'specificfeedbackimmediately' => 0, 'generalfeedbackimmediately' => 0, 'rightanswerimmediately' => 0,
        'overallfeedbackimmediately' => 0,
        'attemptopen' => 0, 'correctnessopen' => 0, 'maxmarksopen' => 0, 'marksopen' => 0, 'specificfeedbackopen' => 0,
        'generalfeedbackopen' => 0, 'rightansweropen' => 0, 'overallfeedbackopen' => 0,
        'attemptclosed' => 0, 'correctnessclosed' => 0, 'maxmarksclosed' => 1, 'marksclosed' => 1,
        'specificfeedbackclosed' => 0, 'generalfeedbackclosed' => 0, 'rightanswerclosed' => 0, 'overallfeedbackclosed' => 0,
    ];
    $moduleinfo = create_module($moduleinfo);
    // Shuffle the question order for every participant.
    $DB->set_field('quiz_sections', 'shufflequestions', 1, ['quizid' => $moduleinfo->instance]);
    cli_writeln("  Created quiz {$options['quizname']} (cmid {$moduleinfo->coursemodule}): " .
        "{$options['timelimit']} min, 1 attempt, shuffled questions and options");
} else {
    cli_writeln('  Quiz already exists');
}

cli_heading('4. Language');
if ($options['lang'] !== 'en') {
    $installed = get_string_manager()->get_list_of_translations(true);
    if (!isset($installed[$options['lang']])) {
        try {
            $controller = new \tool_langimport\controller();
            $controller->install_languagepacks($options['lang']);
            get_string_manager()->reset_caches();
            $installed = get_string_manager()->get_list_of_translations(true);
        } catch (\Throwable $e) {
            cli_writeln('  Could not download the language pack: ' . $e->getMessage());
        }
    }
    if (isset($installed[$options['lang']])) {
        set_config('lang', $options['lang']);
        cli_writeln("  Site language: {$options['lang']}");
    } else {
        cli_writeln("  Language pack '{$options['lang']}' not available; the site stays in English. " .
            'Install it later in Site administration > Language > Language packs.');
    }
}

cli_heading('5. Branding (Planopolis 2026)');
// Exact flat colours read from the official brand assets (planopolis26.png wordmark and
// PC.png frame), not sampled from compressed social images. The identity builds a "P" out
// of a park canopy (green), a road with lane markings (blue) and a building (yellow).
// Readability wins over exuberance here: participants read questions under time pressure,
// so the brand colours carry the chrome and the question area stays text on white cards.
set_config('theme', 'boost');
set_config('brandcolor', '#006cd8', 'theme_boost');

// Injected before Bootstrap, so these override its !default variables.
$scsspre = <<<'SCSS'
// Planopolis 2026 palette. $primary comes from the theme's own "brandcolor"
// setting (#006cd8, the road blue), so it is deliberately not repeated here.
// That blue is 5.1:1 on white, above WCAG AA for body text, so links need no
// separate colour and the brand blue is used throughout.
$plano-green:  #3bb79b;   // park canopy
$plano-orange: #feb322;   // building block
$plano-cream:  #f3fede;   // the outline cream of the wordmark

// Dark text on these reaches 6.2:1 and 8.6:1, so Bootstrap's buttons and badges
// stay readable.
$success: $plano-green;
$warning: $plano-orange;

// $danger keeps Bootstrap's own red on purpose. The brand's #f5512c is a signage
// accent, not an error colour, and it manages only 4.5:1 against dark text and
// 3.4:1 against white - below AA for the import errors and failed logins that
// $danger actually renders.

$body-bg: $plano-cream;
$border-radius:    .75rem;
$border-radius-lg: 1rem;
SCSS;
set_config('scsspre', $scsspre, 'theme_boost');

// Injected after Bootstrap. Kept to two stable selectors: deep overrides of Boost's
// markup tend to break on the next Moodle upgrade.
$scss = <<<'SCSS'
// Accent strip under the navigation, echoing the road markings in the logo.
.navbar.fixed-top { border-bottom: 4px solid #feb322; }

// White cards on the cream page, like the campaign's sticker-on-photo layout.
#page-content .card,
.pagelayout-login .card { border: 0; box-shadow: 0 2px 0 rgba(0, 0, 0, .08); }
SCSS;
set_config('scss', $scss, 'theme_boost');

cli_writeln('  theme boost, brand colour #006cd8, Planopolis 2026 palette applied');

// Brand assets shipped in the plugin's pix/ folder, installed into the file areas that
// Moodle's own appearance settings read. Each one is guarded: a missing or unreadable
// asset must not abort an otherwise healthy setup run.
$fs = get_file_storage();
$pixdir = __DIR__ . '/../pix/';
$assets = [
    // file, component, file area + setting name
    ['logo.png', 'core_admin', 'logo'],
    ['logomark.png', 'core_admin', 'logocompact'],
    ['favicon.png', 'core_admin', 'favicon'],
    ['loginbackground.png', 'theme_boost', 'loginbackgroundimage'],
];
foreach ($assets as [$file, $component, $area]) {
    $source = $pixdir . $file;
    if (!is_readable($source)) {
        cli_writeln("  {$area}: {$file} not found, skipped");
        continue;
    }
    try {
        $fs->delete_area_files($syscontext->id, $component, $area);
        $fs->create_file_from_pathname([
            'contextid' => $syscontext->id,
            'component' => $component,
            'filearea' => $area,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $file,
        ], $source);
        set_config($area, '/' . $file, $component);
        cli_writeln("  {$area}: {$file}");
    } catch (Throwable $e) {
        cli_writeln("  {$area}: could not install {$file} - " . $e->getMessage());
    }
}

purge_all_caches();
cli_heading('Done');
cli_writeln('Log in as the site administrator (Super Admin) and open ' . $CFG->wwwroot . '/local/planopolis/index.php');
