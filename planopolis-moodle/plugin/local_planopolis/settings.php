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
 * Admin settings.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_planopolis_settings', get_string('pluginname', 'local_planopolis'));
    $settings->add(new admin_setting_configcheckbox('local_planopolis/protection',
        get_string('set_protection', 'local_planopolis'), get_string('set_protection_desc', 'local_planopolis'), 1));
    $settings->add(new admin_setting_configcheckbox('local_planopolis/watermark',
        get_string('set_watermark', 'local_planopolis'), get_string('set_watermark_desc', 'local_planopolis'), 1));
    $settings->add(new admin_setting_configtext('local_planopolis/courseid',
        get_string('set_courseid', 'local_planopolis'), get_string('set_courseid_desc', 'local_planopolis'), 0, PARAM_INT));
    $ADMIN->add('localplugins', $settings);
}

$ADMIN->add('localplugins', new admin_externalpage('local_planopolis_panel', get_string('panel', 'local_planopolis'),
    new core\url('/local/planopolis/index.php'), 'moodle/site:config'));
$ADMIN->add('localplugins', new admin_externalpage('local_planopolis_admins', get_string('manageadmins', 'local_planopolis'),
    new core\url('/local/planopolis/admins.php'), 'moodle/site:config'));
