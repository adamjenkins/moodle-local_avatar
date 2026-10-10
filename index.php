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
 * "My VR avatar": the current user designs their own avatar for the live rooms of VR room.
 *
 * With JavaScript the form saves through the local_vravatar_save_design web service; without it the
 * form posts here.
 *
 * @package    local_vravatar
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_vravatar\local\design;

require(__DIR__ . '/../../config.php');

require_login(null, false);
if (isguestuser()) {
    throw new \moodle_exception('noguest');
}
$context = \context_user::instance((int)$USER->id);
require_capability('local/vravatar:edit', $context);

$url = new \core\url('/local/vravatar/index.php');
$title = get_string('myavatar', 'local_vravatar');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title($title);
$PAGE->set_heading(fullname($USER));
$PAGE->navbar->add($title, $url);

$userid = (int)$USER->id;
$pictureallowed = design::picture_allowed();
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'remove') {
    require_sesskey();
    design::remove($userid);
    redirect($url, get_string('removed', 'local_vravatar'), null, \core\output\notification::NOTIFY_SUCCESS);
} else if ($action === 'save') {
    require_sesskey();
    $input = [];
    foreach (array_keys(design::choices()) as $key) {
        // Costume ids have hyphens and digits ("pirate-captain"); clean() keeps only allowed names.
        $input[$key] = optional_param($key, '', PARAM_ALPHANUMEXT);
    }
    $input['ears'] = optional_param('ears', 0, PARAM_BOOL);
    $input['glasses'] = optional_param('glasses', 0, PARAM_BOOL);
    $input['picture'] = $pictureallowed
        ? optional_param('picture', 0, PARAM_BOOL)
        : (bool)(design::load($userid)['picture'] ?? false);
    design::save($userid, $input);
    redirect($url, get_string('saved', 'local_vravatar'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$saved = design::load($userid);
$current = $saved ?? design::defaults();
$user = \core_user::get_user($userid, design::picture_fields(), MUST_EXIST);
$pictureurl = design::picture_url($user, $PAGE);

$designer = new \local_vravatar\output\designer($current, $saved !== null, $pictureallowed, $pictureurl !== '', $url);
$PAGE->requires->js_call_amd('local_vravatar/designer', 'init', [[
    'design' => $current,
    'defaults' => design::defaults(),
    'pictureurl' => $pictureurl,
    'pictureallowed' => $pictureallowed,
    'name' => fullname($USER),
    'seed' => 'local-vravatar-' . $userid,
]]);

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $OUTPUT->render($designer);
echo $OUTPUT->footer();
