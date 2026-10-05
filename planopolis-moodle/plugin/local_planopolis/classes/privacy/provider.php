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

namespace local_planopolis\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: the integrity event log.
 *
 * @package    local_planopolis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Describe stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_planopolis_log', [
            'attemptid' => 'privacy:metadata:local_planopolis_log:attemptid',
            'userid' => 'privacy:metadata:local_planopolis_log:userid',
            'eventtype' => 'privacy:metadata:local_planopolis_log:eventtype',
            'timecreated' => 'privacy:metadata:local_planopolis_log:timecreated',
        ], 'privacy:metadata:local_planopolis_log');
        return $collection;
    }

    /**
     * Quiz contexts in which the user has log entries.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {local_planopolis_log} l
                  JOIN {quiz_attempts} qa ON qa.id = l.attemptid
                  JOIN {course_modules} cm ON cm.instance = qa.quiz
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :level
                 WHERE l.userid = :userid";
        $list = new contextlist();
        $list->add_from_sql($sql, ['level' => CONTEXT_MODULE, 'userid' => $userid]);
        return $list;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $sql = "SELECT l.userid
                  FROM {local_planopolis_log} l
                  JOIN {quiz_attempts} qa ON qa.id = l.attemptid
                  JOIN {course_modules} cm ON cm.instance = qa.quiz AND cm.id = :cmid
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
    }

    /**
     * Log entries of a quiz context.
     *
     * @param \context $context
     * @param int|null $userid
     * @return array
     */
    protected static function records(\context $context, ?int $userid = null): array {
        global $DB;
        if ($context->contextlevel != CONTEXT_MODULE) {
            return [];
        }
        $sql = "SELECT l.*
                  FROM {local_planopolis_log} l
                  JOIN {quiz_attempts} qa ON qa.id = l.attemptid
                  JOIN {course_modules} cm ON cm.instance = qa.quiz AND cm.id = :cmid
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'";
        $params = ['cmid' => $context->instanceid];
        if ($userid) {
            $sql .= " WHERE l.userid = :userid";
            $params['userid'] = $userid;
        }
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Export.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $data = array_values(array_map(fn($r) => [
                'attemptid' => $r->attemptid,
                'eventtype' => $r->eventtype,
                'timecreated' => \core_privacy\local\request\transform::datetime($r->timecreated),
            ], self::records($context, $userid)));
            if ($data) {
                writer::with_context($context)->export_data([get_string('pluginname', 'local_planopolis')],
                    (object) ['events' => $data]);
            }
        }
    }

    /**
     * Delete all data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        $ids = array_keys(self::records($context));
        if ($ids) {
            $DB->delete_records_list('local_planopolis_log', 'id', $ids);
        }
    }

    /**
     * Delete data of one user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            $ids = array_keys(self::records($context, $contextlist->get_user()->id));
            if ($ids) {
                $DB->delete_records_list('local_planopolis_log', 'id', $ids);
            }
        }
    }

    /**
     * Delete data of several users.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        foreach ($userlist->get_userids() as $userid) {
            $ids = array_keys(self::records($userlist->get_context(), $userid));
            if ($ids) {
                $DB->delete_records_list('local_planopolis_log', 'id', $ids);
            }
        }
    }
}
