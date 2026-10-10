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

/**
 * Library callbacks of local_vravatar.
 *
 * @package    local_vravatar
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add "My VR avatar" to the user's own profile page.
 *
 * Shown only on the user's own profile: nobody edits another person's avatar.
 *
 * @param \core_user\output\myprofile\tree $tree Profile tree.
 * @param stdClass $user The user whose profile is shown.
 * @param bool $iscurrentuser Whether it is the current user's own profile.
 * @param stdClass|null $course Course of a course profile, or null.
 * @return void
 */
function local_vravatar_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course): void {
    if (!$iscurrentuser || isguestuser($user) || !isloggedin()) {
        return;
    }
    if (!has_capability('local/vravatar:edit', \context_user::instance((int)$user->id))) {
        return;
    }
    $node = new \core_user\output\myprofile\node(
        'miscellaneous',
        'local_vravatar',
        get_string('myavatar', 'local_vravatar'),
        null,
        new \core\url('/local/vravatar/index.php')
    );
    $tree->add_node($node);
}
