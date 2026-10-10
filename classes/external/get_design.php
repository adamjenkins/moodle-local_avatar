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
 * Return the current user's avatar design.
 *
 * @package    local_avatar
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_design extends external_api {
    /**
     * Parameters: none, a user reads only their own design.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Read the design.
     *
     * @return array
     */
    public static function execute(): array {
        self::validate_parameters(self::execute_parameters(), []);
        $userid = save_design::require_own_avatar();
        $saved = design::load($userid);
        return [
            'hasdesign' => $saved !== null,
            'design' => $saved ?? design::defaults(),
            'pictureallowed' => design::picture_allowed(),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'hasdesign' => new external_value(PARAM_BOOL, 'Whether the user has saved a design (false: the standard look)'),
            'design' => save_design::design_structure(),
            'pictureallowed' => new external_value(PARAM_BOOL, 'Whether the site lets users show their profile picture'),
        ]);
    }
}
