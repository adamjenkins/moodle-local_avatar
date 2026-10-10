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
 * Web services of local_vravatar.
 *
 * @package    local_vravatar
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_vravatar_get_design' => [
        'classname' => \local_vravatar\external\get_design::class,
        'description' => 'Return the current user\'s VR avatar design.',
        'type' => 'read',
        'ajax' => true,
        'readonlysession' => true,
        'capabilities' => 'local/vravatar:edit',
    ],
    'local_vravatar_save_design' => [
        'classname' => \local_vravatar\external\save_design::class,
        'description' => 'Save the current user\'s VR avatar design and return it as stored.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/vravatar:edit',
    ],
];
