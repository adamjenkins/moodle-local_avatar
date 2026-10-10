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

namespace local_avatar\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_avatar\local\design;

/**
 * Save the current user's avatar design.
 *
 * There is no user id parameter: a user saves only their own design.
 *
 * @package    local_avatar
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_design extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'design' => new external_value(PARAM_RAW, 'The vrroom-avatar/1 document as JSON; cleaned against the allowlist'),
        ]);
    }

    /**
     * Save the design.
     *
     * While the site does not allow profile pictures on avatars the picture choice is not changed:
     * the choice saved before is kept for when the site allows it again.
     *
     * @param string $design The document as JSON.
     * @return array
     */
    public static function execute(string $design): array {
        $params = self::validate_parameters(self::execute_parameters(), ['design' => $design]);
        $userid = self::require_own_avatar();

        $clean = design::clean((string)$params['design']);
        $allowed = design::picture_allowed();
        if (!$allowed) {
            $clean['picture'] = (bool)(design::load($userid)['picture'] ?? false);
        }
        $saved = design::save($userid, $clean);
        return ['design' => $saved, 'pictureallowed' => $allowed];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'design' => self::design_structure(),
            'pictureallowed' => new external_value(PARAM_BOOL, 'Whether the site lets users show their profile picture'),
        ]);
    }

    /**
     * Check that the current user may design their own avatar.
     *
     * @return int The current user's id.
     */
    public static function require_own_avatar(): int {
        global $USER;
        if (!isloggedin() || isguestuser()) {
            throw new \moodle_exception('noguest');
        }
        $context = \context_user::instance((int)$USER->id);
        self::validate_context($context);
        require_capability('local/avatar:edit', $context);
        return (int)$USER->id;
    }

    /**
     * The vrroom-avatar/1 document.
     *
     * @return external_single_structure
     */
    public static function design_structure(): external_single_structure {
        return new external_single_structure([
            'schema' => new external_value(PARAM_RAW, 'Schema name, vrroom-avatar/1'),
            'body' => new external_value(PARAM_ALPHA, 'Body shape'),
            'head' => new external_value(PARAM_ALPHA, 'Head shape'),
            'color' => new external_value(PARAM_ALPHA, 'Body colour name'),
            'accent' => new external_value(PARAM_ALPHA, 'Accent colour name'),
            'hair' => new external_value(PARAM_ALPHA, 'Hair style'),
            'haircolor' => new external_value(PARAM_ALPHA, 'Hair colour name'),
            'glasses' => new external_value(PARAM_BOOL, 'Glasses'),
            'hat' => new external_value(PARAM_ALPHA, 'Hat'),
            'picture' => new external_value(PARAM_BOOL, 'Show the profile picture on the avatar'),
        ]);
    }
}
