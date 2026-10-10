<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_vravatar\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_vravatar\local\design;

/**
 * Privacy provider for local_vravatar.
 *
 * Personal data: one user preference, the user's avatar design. The other participants of a live
 * room of VR room receive the design and, when the user chose it and the site allows it, the address
 * of the user's profile picture (described as an external location). The design is deleted with the
 * user's data in their user context.
 *
 * @package    local_vravatar
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Describe the personal data.
     *
     * @param collection $collection the collection to add to
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(design::PREFERENCE, 'privacy:metadata:preference:design');
        $collection->add_external_location_link('liveroomparticipants', [
            'appearance' => 'privacy:metadata:liveroomparticipants:appearance',
            'picture' => 'privacy:metadata:liveroomparticipants:picture',
        ], 'privacy:metadata:liveroomparticipants');
        return $collection;
    }

    /**
     * The user's own user context, when the user has a saved design.
     *
     * @param int $userid user id
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {user_preferences} up
                  JOIN {context} ctx ON ctx.instanceid = up.userid AND ctx.contextlevel = :contextlevel
                 WHERE up.userid = :userid AND up.name = :name";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_USER,
            'userid' => $userid,
            'name' => design::PREFERENCE,
        ]);
        return $contextlist;
    }

    /**
     * The user of a user context, when that user has a saved design.
     *
     * @param userlist $userlist the userlist to add to
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_user) {
            return;
        }
        $sql = "SELECT userid
                  FROM {user_preferences}
                 WHERE userid = :userid AND name = :name";
        $userlist->add_from_sql('userid', $sql, ['userid' => $context->instanceid, 'name' => design::PREFERENCE]);
    }

    /**
     * Nothing to export per context: the design is exported as a user preference.
     *
     * @param approved_contextlist $contextlist the approved contexts
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
    }

    /**
     * Export the user's avatar design.
     *
     * @param int $userid user id
     * @return void
     */
    public static function export_user_preferences(int $userid): void {
        $saved = design::load($userid);
        if ($saved === null) {
            return;
        }
        writer::export_user_preference(
            'local_vravatar',
            design::PREFERENCE,
            json_encode($saved),
            get_string('privacy:preference:design', 'local_vravatar')
        );
    }

    /**
     * Delete the design of the user of a user context.
     *
     * @param \context $context the context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context instanceof \context_user) {
            design::remove((int)$context->instanceid);
        }
    }

    /**
     * Delete the user's design.
     *
     * @param approved_contextlist $contextlist the approved contexts
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_user && (int)$context->instanceid === $userid) {
                design::remove($userid);
            }
        }
    }

    /**
     * Delete the design of the approved users of a user context.
     *
     * @param approved_userlist $userlist the approved users
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_user) {
            return;
        }
        if (in_array((int)$context->instanceid, array_map('intval', $userlist->get_userids()), true)) {
            design::remove((int)$context->instanceid);
        }
    }
}
