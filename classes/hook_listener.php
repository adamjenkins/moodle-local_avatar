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

namespace local_avatar;

use local_avatar\local\design;

/**
 * Hook listeners of local_avatar.
 *
 * @package    local_avatar
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_listener {
    /**
     * Give each person in a live room of VR room their saved avatar design.
     *
     * mod_vrroom dispatches the hook while it builds the list of the people in one live room, so
     * the designs (and profile picture addresses) reach only the others in that room. People without
     * a saved design are left alone and keep the standard look. The profile picture is given only when
     * the site allows it, the user chose it and the user has a picture worth showing.
     *
     * @param \mod_vrroom\hook\avatar_appearance $hook The hook.
     * @return void
     */
    public static function avatar_appearance(\mod_vrroom\hook\avatar_appearance $hook): void {
        global $DB, $PAGE;

        $peers = $hook->get_peers();
        if (!$peers) {
            return;
        }
        $designs = design::load_many(array_column($peers, 'userid'));
        if (!$designs) {
            return;
        }

        $pictures = [];
        if (design::picture_allowed()) {
            $wanted = array_keys(array_filter($designs, fn(array $d): bool => !empty($d['picture'])));
            if ($wanted) {
                $users = $DB->get_records_list('user', 'id', $wanted, '', design::picture_fields());
                foreach ($users as $user) {
                    $pictures[(int)$user->id] = design::picture_url($user, $PAGE);
                }
            }
        }

        foreach ($peers as $peer) {
            $userid = (int)($peer['userid'] ?? 0);
            if (!isset($designs[$userid])) {
                continue;
            }
            $url = $pictures[$userid] ?? '';
            $appearance = $designs[$userid];
            // The badge is shown only with a picture to show.
            $appearance['picture'] = $url !== '';
            $hook->set_appearance((string)$peer['peerid'], $appearance, $url);
        }
    }
}
