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
 * English strings for local_planopolis.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action_create'] = 'Create account';
$string['action_enrolonly'] = 'Already exists – enrol only';
$string['action_update'] = 'Update + reset password';
$string['addquiz'] = 'Add a quiz / round';
$string['admins_add'] = 'Give Admin Quiz access';
$string['admins_added'] = '{$a} now has Admin Quiz access.';
$string['admins_addhelp'] = 'Type the username of an existing account, or fill in all fields to create a new admin account.';
$string['admins_confirmremove'] = 'Remove Admin Quiz access from this user?';
$string['admins_created'] = 'Account {$a} created.';
$string['admins_intro'] = 'Admin Quiz users can register participants, import questions, check quizzes and download results. Only Super Admins (site administrators) can see this page.';
$string['admins_neednew'] = 'That username does not exist yet. To create a new admin account, fill in the name and password too.';
$string['admins_newname'] = 'Full name (new account only)';
$string['admins_newpassword'] = 'Password (new account only)';
$string['admins_none'] = 'No Admin Quiz users yet.';
$string['admins_norole'] = 'The Admin Quiz role does not exist. Run the setup script: php public/local/planopolis/cli/setup.php';
$string['admins_remove'] = 'Remove access';
$string['admins_removed'] = 'Access removed.';
$string['attemptsmade'] = 'Attempts';
$string['audit'] = 'Quiz check';
$string['audit_attempts'] = 'Participants are allowed more than one attempt. Competitions normally allow exactly 1.';
$string['audit_draft'] = 'The question is in Draft status and will NOT be shown to participants. Set it to Ready.';
$string['audit_duplicatequestion'] = 'Same question text as slot {$a}.';
$string['audit_emptyoption'] = 'Option {$a} is empty.';
$string['audit_errors'] = '{$a} problem(s)';
$string['audit_fail'] = 'Found {$a->errors} problem(s) and {$a->warnings} warning(s) in {$a->slots} question slot(s). Fix the problems before the exam.';
$string['audit_fewoptions'] = 'Fewer than two options.';
$string['audit_general'] = 'Quiz settings';
$string['audit_hidden'] = 'The question is hidden in the question bank.';
$string['audit_intro'] = 'This check looks at every question in the quiz, including questions typed in the web editor. It targets the 2024 problems: questions that did not appear and questions with duplicate answer options.';
$string['audit_manycorrect'] = '{$a} options are marked 100% correct. Usually this is a mistake.';
$string['audit_missing'] = 'The question in this slot no longer exists – participants would see an error. Remove the slot and add the question again.';
$string['audit_multipleanswers'] = 'The question allows multiple answers; the competition uses single-answer multiple choice.';
$string['audit_noclose'] = 'The quiz has no closing time. Set "Close the quiz" so late attempts are submitted automatically.';
$string['audit_nocorrect'] = 'No option is marked as correct.';
$string['audit_noquestions'] = 'The quiz has no questions.';
$string['audit_nosumgrades'] = 'The total mark of the quiz is 0.';
$string['audit_notmc'] = 'Question type is "{$a}", not multiple choice.';
$string['audit_ok'] = 'Ready';
$string['audit_pass'] = 'No problems found in {$a->slots} question slot(s) ({$a->warnings} warning(s)).';
$string['audit_problem'] = 'Problem';
$string['audit_randommixed'] = 'Random questions are mixed with fixed questions; a random slot could draw a question that is already a fixed slot.';
$string['audit_randomshort'] = 'These random slots need {$a->need} question(s) but only {$a->have} are available – some participants would get missing questions.';
$string['audit_reviewleak'] = 'Participants can see the right answers during or right after their attempt. Answers may leak to other participants.';
$string['audit_severity'] = 'Severity';
$string['audit_slot'] = 'Question {$a}';
$string['audit_slots'] = 'Random questions {$a}';
$string['audit_where'] = 'Where';
$string['audit_zeromark'] = 'The question is worth 0 points.';
$string['card_addquiz'] = 'Create another quiz, for example for a different round or session.';
$string['card_admins'] = 'Give or remove Admin Quiz access (Super Admin only).';
$string['card_audit'] = 'Run before every exam: finds missing questions, duplicate options and wrong answer keys.';
$string['card_participantlist'] = 'See, edit, suspend or reset passwords of enrolled participants.';
$string['card_participants'] = 'Create participant accounts from Excel. Participants cannot register themselves.';
$string['card_questions'] = 'Upload questions from the Excel template, with images and audio in a ZIP.';
$string['card_results'] = 'Ranked scores with correct / wrong / blank counts, downloadable as Excel.';
$string['checkfile'] = 'Check file';
$string['checklist'] = 'Before exam day';
$string['checklist_items'] = '<li>Register participants and hand out the downloaded credentials.</li><li>Import the questions (or type them in the web editor).</li><li>Set the quiz open and close time and the time limit in the quiz settings.</li><li>Run the Quiz check until it shows no problems.</li><li>Take the quiz once with a test participant account.</li><li>After the exam, download the results from Results.</li>';
$string['closes'] = 'Closes';
$string['col_audio'] = 'Audio Soal';
$string['col_category'] = 'Kategori';
$string['col_feedback'] = 'Pembahasan';
$string['col_image'] = 'Gambar Soal';
$string['col_key'] = 'Kunci';
$string['col_mark'] = 'Nilai Benar';
$string['col_no'] = 'No';
$string['col_optA'] = 'Opsi A';
$string['col_optB'] = 'Opsi B';
$string['col_penalty'] = 'Nilai Salah';
$string['col_question'] = 'Soal';
$string['confirmimport'] = 'Import {$a} question(s)';
$string['confirmparticipants'] = 'Register {$a} participant(s)';
$string['credentialsnote'] = 'Download the credentials now. For security, passwords are not stored in readable form and cannot be shown again after you leave this session.';
$string['downloadcredentials'] = 'Download credentials (Excel)';
$string['downloadexcel'] = 'Download Excel';
$string['downloadlastcredentials'] = 'Download credentials of the last registration';
$string['editquiz'] = 'Open quiz questions';
$string['err_badaudioext'] = 'Audio "{$a}" must be mp3, wav, ogg or m4a.';
$string['err_bademail'] = 'Invalid email "{$a}".';
$string['err_badimageext'] = 'Image "{$a}" must be png, jpg, jpeg, gif, webp or svg.';
$string['err_badkey'] = 'Answer key "{$a}" is not valid. Use one letter A–E.';
$string['err_badmark'] = 'Nilai Benar "{$a}" must be a number greater than 0.';
$string['err_badpenalty'] = 'Nilai Salah "{$a}" must be 0 or a negative number (for example -1).';
$string['err_badusername'] = 'Username "{$a}" may only use lowercase letters, numbers and . - _ @';
$string['err_cannotread'] = 'The file cannot be read: {$a}';
$string['err_duplicateoption'] = 'Options {$a->a} and {$a->b} are the same ("{$a->text}").';
$string['err_dupusername'] = 'Same username as row {$a}.';
$string['err_emptyquestion'] = 'Question text is empty.';
$string['err_fewoptions'] = 'At least options A and B are required.';
$string['err_fixfirst'] = 'Fix the errors in the file first.';
$string['err_importfailed'] = 'Import failed: {$a}';
$string['err_isadmin'] = 'This username belongs to a site administrator.';
$string['err_keyempty'] = 'The answer key is {$a} but option {$a} is empty.';
$string['err_medianotfound'] = 'File "{$a}" is not in the ZIP. Upload a ZIP containing the Excel file and all images/audio.';
$string['err_missingcolumns'] = 'Required column(s) not found: {$a}. Use the template.';
$string['err_nokey'] = 'Answer key (Kunci) is empty.';
$string['err_noname'] = 'Name is empty.';
$string['err_noparticipants'] = 'No participants found in the file.';
$string['err_noquestions'] = 'No questions found in the file.';
$string['err_nousername'] = 'Username is empty.';
$string['err_optiongap'] = 'Option {$a} is filled in but an earlier option is empty.';
$string['err_penaltyratio'] = 'Nilai Salah {$a->penalty} with Nilai Benar {$a->mark} is not supported by Moodle. Supported ratios are, for example, -1/4, -1/3, -1/2, -1/5, -1/10 or -1/1 of Nilai Benar.';
$string['err_passwordpolicy'] = 'Password does not meet the site password rules: {$a} Leave the Password cell empty to generate a valid password.';
$string['err_quizhasattempts'] = 'This quiz already has attempts. Questions cannot be added any more. Create a new quiz or delete the attempts.';
$string['err_zipmanysheets'] = 'The ZIP contains more than one spreadsheet. Put exactly one Excel file in the ZIP.';
$string['err_zipnosheet'] = 'The ZIP does not contain an Excel file.';
$string['importdone'] = '{$a->count} question(s) imported into "{$a->quiz}". Total points: {$a->sumgrades}. Maximum grade of the quiz: {$a->grade}.';
$string['importmore'] = 'Import more';
$string['importparticipants'] = 'Register participants';
$string['importparticipants_intro'] = '<p>Download the <a href="{$a->template}">participant template</a>, fill in one row per participant and upload it here. Leave <em>Password</em> empty to generate one automatically. Individual participants can also be <a href="{$a->manual}">added by hand</a>.</p>';
$string['importquestions'] = 'Import questions';
$string['importquestions_intro'] = '<p>Download the <a href="{$a->template}">question template</a>. Upload the Excel file alone, or – if questions use images or audio – a ZIP file containing the Excel file and all media files.</p><p>Nothing is saved until you have checked the preview and confirmed.</p>';
$string['manageadmins'] = 'Manage admins';
$string['maxgrade'] = 'Max grade';
$string['moodlereport'] = 'Detailed Moodle report';
$string['nocourseconfigured'] = 'The competition course is not configured. Run php public/local/planopolis/cli/setup.php';
$string['nofileuploaded'] = 'No file uploaded.';
$string['noquizzes'] = 'The course has no quiz yet.';
$string['open'] = 'Open';
$string['opens'] = 'Opens';
$string['panel'] = 'Planopolis panel';
$string['participantfile'] = 'Participant file (Excel or CSV)';
$string['participantfile_help'] = 'Columns: Username, Password (optional), Nama, Nama Belakang (optional), Email (optional), Instansi (optional), Kota (optional), Sesi (optional – participants with the same session are put in the same group).';
$string['participantlist'] = 'Participant list';
$string['participantsdone'] = '{$a} participant(s) registered and enrolled.';
$string['pcol_email'] = 'Email';
$string['pcol_firstname'] = 'Nama';
$string['pcol_group'] = 'Sesi';
$string['pcol_institution'] = 'Instansi';
$string['pcol_password'] = 'Password';
$string['pcol_username'] = 'Username';
$string['planopolis:bypassprotection'] = 'Not affected by the quiz copy protection';
$string['planopolis:manage'] = 'Use the Planopolis panel';
$string['pluginname'] = 'Planopolis quiz tools';
$string['previewquiz'] = 'Preview the quiz';
$string['privacy:metadata:local_planopolis_log'] = 'Integrity events during quiz attempts (leaving the quiz page, copy or screenshot attempts).';
$string['privacy:metadata:local_planopolis_log:attemptid'] = 'The quiz attempt.';
$string['privacy:metadata:local_planopolis_log:eventtype'] = 'The kind of event.';
$string['privacy:metadata:local_planopolis_log:timecreated'] = 'When the event happened.';
$string['privacy:metadata:local_planopolis_log:userid'] = 'The participant.';
$string['protect_blocked'] = 'Copying, printing and screenshots are not allowed during the quiz.';
$string['protect_left'] = 'You left the quiz page. This has been recorded. Click here to continue.';
$string['pv_action'] = 'Action';
$string['pv_autopassword'] = '(generated)';
$string['pv_enrolonly'] = 'Existing accounts to enrol';
$string['pv_errors'] = 'Errors';
$string['pv_file'] = 'File';
$string['pv_fixerrors'] = 'Fix these errors in the file and upload it again. Nothing has been saved.';
$string['pv_media'] = 'Media';
$string['pv_newusers'] = 'New accounts';
$string['pv_options'] = 'Options';
$string['pv_preview'] = 'Preview';
$string['pv_row'] = 'Row';
$string['pv_totalmarks'] = 'Total points';
$string['pv_updateusers'] = 'Accounts to update';
$string['pv_valid'] = 'Valid questions';
$string['pv_warnings'] = 'Warnings';
$string['questionfile'] = 'Question file (Excel or ZIP)';
$string['questionfile_help'] = 'Use the question template. For images or audio, put the Excel file and the media files in one ZIP and write the file names in the Gambar Soal / Audio Soal columns, or use [img:file.png] inside a question or option.';
$string['questions'] = 'Questions';
$string['quizzes'] = 'Quizzes';
$string['res_blank'] = 'Blank';
$string['res_correct'] = 'Correct';
$string['res_duration'] = 'Duration';
$string['res_finished'] = 'Finished';
$string['res_fullname'] = 'Name';
$string['res_group'] = 'Session';
$string['res_inprogress'] = 'In progress';
$string['res_institution'] = 'Institution';
$string['res_leftpage'] = 'Left page';
$string['res_notstarted'] = 'Not started';
$string['res_overdue'] = 'Overdue';
$string['res_rank'] = 'Rank';
$string['res_score'] = 'Score';
$string['res_started'] = 'Started';
$string['res_status'] = 'Status';
$string['res_username'] = 'Username';
$string['res_wrong'] = 'Wrong';
$string['results'] = 'Results';
$string['results_leftnote'] = '"Left page" counts how often the participant switched away from the quiz window (another tab, app or snipping tool). Ties are ranked by shorter duration.';
$string['results_summary'] = '{$a->finished} of {$a->total} participants finished. Maximum score: {$a->max}.';
$string['rowlabel'] = 'Row {$a->row} (No {$a->no})';
$string['runaudit'] = 'Run the quiz check';
$string['set_courseid'] = 'Competition course id';
$string['set_courseid_desc'] = 'Set automatically by the setup script.';
$string['set_protection'] = 'Quiz page protection';
$string['set_protection_desc'] = 'Block copying, printing and screenshot keys, hide questions when the participant leaves the window, and log these events.';
$string['set_watermark'] = 'Participant watermark';
$string['set_watermark_desc'] = 'Show the participant\'s name and username faintly across the quiz page, so leaked photos can be traced.';
$string['setmaxgrade'] = 'Use total points as the quiz maximum grade';
$string['setmaxgrade_help'] = 'If checked, the final score equals the sum of points (for example 40 questions × 4 = 160). If unchecked, Moodle rescales the score to the quiz maximum grade (for example 100).';
$string['sev_danger'] = 'Problem';
$string['sev_success'] = 'OK';
$string['sev_warning'] = 'Warning';
$string['shuffledefault'] = 'Shuffle options (unless the row says Acak Opsi = N)';
$string['status_created'] = 'Created';
$string['status_enrolonly'] = 'Enrolled (password unchanged)';
$string['status_updated'] = 'Updated, new password';
$string['targetquiz'] = 'Quiz';
$string['timelimit'] = 'Time limit';
$string['unsupportedfiletype'] = 'Unsupported file type: {$a}';
$string['updateexisting'] = 'Update existing accounts';
$string['updateexisting_help'] = 'If a username already exists: checked = update the name and set a new password; unchecked = keep the account as it is and only enrol it.';
$string['uploadagain'] = 'Upload again';
$string['warn_alreadyinquiz'] = 'Row {$a->row}: the same question is already question {$a->slot} of this quiz.';
$string['warn_badshuffle'] = 'Acak Opsi "{$a}" not understood (use Y or N); the default is used.';
$string['warn_duplicatemedia'] = 'The ZIP contains two files named {$a}; the last one is used.';
$string['warn_duplicatequestion'] = 'same question text as row {$a}.';
$string['warn_unusedmedia'] = 'File {$a} in the ZIP is not used by any question.';
$string['warn_userexists'] = 'account already exists; it will only be enrolled.';
