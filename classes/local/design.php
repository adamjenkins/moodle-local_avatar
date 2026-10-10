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

namespace local_avatar\local;

/**
 * A user's avatar design: the "vrroom-avatar/1" document, its allowlist and its storage.
 *
 * The design is stored as one user preference holding a small JSON document. Every document is
 * cleaned against the allowlist on save and again on read: unknown keys and values are dropped and
 * missing ones take the default, so only names from the fixed lists below ever leave the server.
 *
 * @package    local_avatar
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class design {
    /** @var string Schema name of the document. */
    public const SCHEMA = 'vrroom-avatar/1';

    /** @var string The user preference holding the document. */
    public const PREFERENCE = 'local_avatar_design';

    /** @var int Largest document accepted (bytes of JSON). */
    public const MAXBYTES = 2048;

    /** @var int Size of the profile picture given to live rooms (px). */
    public const PICTURESIZE = 100;

    /** @var string[] The body and accent colours, in palette order. */
    public const COLOURS = [
        'red', 'orange', 'yellow', 'green', 'teal', 'cyan',
        'blue', 'indigo', 'purple', 'pink', 'brown', 'grey',
    ];

    /** @var string[] The hair colours (beards use the hair colour too). */
    public const HAIRCOLOURS = ['black', 'brown', 'blonde', 'auburn', 'grey', 'white', 'red', 'pink', 'blue', 'purple'];

    /**
     * The skin tones, light to deep. "match" gives the head and hands the body colour (the look of
     * avatars before skin tones existed); the others are natural skin colours chosen by the avatar
     * builder.
     *
     * @var string[]
     */
    public const SKINS = ['match', 'porcelain', 'fair', 'light', 'medium', 'tan', 'brown', 'dark', 'deep'];

    /** @var string[] The hair styles, in the order the designer shows them. */
    public const HAIRSTYLES = [
        'none', 'short', 'long', 'bun', 'curly', 'afro', 'ponytail', 'braids', 'buzz', 'bob', 'mohawk', 'sidepart',
        'pigtails', 'topknot',
    ];

    /**
     * Swatch colours for the designer form, by name. Only a visual cue next to the colour's name:
     * the 3D colours are chosen by vrroomruntime_builtin/live/avatarmodel, and the designer page
     * repaints the swatches from it when that module lists them.
     *
     * @var string[]
     */
    public const SWATCHES = [
        'red' => '#d32f2f',
        'orange' => '#f57c00',
        'yellow' => '#fbc02d',
        'green' => '#388e3c',
        'teal' => '#00897b',
        'cyan' => '#0097a7',
        'blue' => '#1976d2',
        'indigo' => '#3949ab',
        'purple' => '#7b1fa2',
        'pink' => '#d81b60',
        'brown' => '#6d4c41',
        'grey' => '#757575',
    ];

    /** @var string[] Swatch colours of the hair colours. */
    public const HAIRSWATCHES = [
        'black' => '#1c1c1c',
        'brown' => '#5d3a1a',
        'blonde' => '#e0c068',
        'auburn' => '#8d3b1f',
        'grey' => '#9e9e9e',
        'white' => '#eeeeee',
        'red' => '#b8452a',
        'pink' => '#e78fb3',
        'blue' => '#3f6fd8',
        'purple' => '#7b4bb5',
    ];

    /**
     * Swatch colours of the skin tones, a visual cue only: the designer page repaints them from the
     * avatar builder's own list when that module gives one. "match" has no swatch (it is the body
     * colour).
     *
     * @var string[]
     */
    public const SKINSWATCHES = [
        'porcelain' => '#f9e4d8',
        'fair' => '#f1cfb6',
        'light' => '#e3b48f',
        'medium' => '#c99266',
        'tan' => '#b07646',
        'brown' => '#8a5636',
        'dark' => '#683f25',
        'deep' => '#472a19',
    ];

    /** @var string[] Keys of the document that are true or false. */
    public const FLAGS = ['ears', 'glasses', 'picture'];

    /**
     * The allowed values of every key that takes a name.
     *
     * "outfit" is reserved for costumes: today its only value is "none", so any other value is
     * dropped and the key is always "none".
     *
     * @return array key => list of names
     */
    public static function choices(): array {
        return [
            'body' => ['capsule', 'slim', 'broad'],
            'head' => ['round', 'square', 'oval', 'heart'],
            'skin' => self::SKINS,
            'color' => self::COLOURS,
            'accent' => self::COLOURS,
            'hair' => self::HAIRSTYLES,
            'haircolor' => self::HAIRCOLOURS,
            'eyes' => ['dots', 'round', 'almond', 'sleepy'],
            'brows' => ['none', 'thin', 'thick', 'arched'],
            'nose' => ['none', 'small', 'round', 'pointed'],
            'beard' => ['none', 'stubble', 'moustache', 'goatee', 'full'],
            'hat' => ['none', 'cap', 'beanie', 'sunhat'],
            'outfit' => ['none'],
        ];
    }

    /**
     * The default document. Every key added after the first version defaults to the look the avatar
     * had before it, so designs saved earlier look the same.
     *
     * @return array
     */
    public static function defaults(): array {
        return [
            'schema' => self::SCHEMA,
            'body' => 'capsule',
            'head' => 'round',
            'skin' => 'match',
            'color' => 'blue',
            'accent' => 'grey',
            'hair' => 'none',
            'haircolor' => 'brown',
            'eyes' => 'dots',
            'brows' => 'none',
            'nose' => 'none',
            'ears' => false,
            'beard' => 'none',
            'glasses' => false,
            'hat' => 'none',
            'outfit' => 'none',
            // On by default (owner, 2026-10-10); the site setting allowpicture can turn it off.
            'picture' => true,
        ];
    }

    /**
     * Clean a document against the allowlist.
     *
     * Only the keys of the schema are kept, each only with an allowed value; anything else falls
     * back to the default. A document larger than MAXBYTES, a string that is not a JSON object or a
     * document of another schema gives the default document.
     *
     * @param array|string $input The document, as an array or as JSON.
     * @return array The clean document, every key set, in the schema's key order.
     */
    public static function clean(array|string $input): array {
        $clean = self::defaults();
        if (is_string($input)) {
            if (strlen($input) > self::MAXBYTES) {
                return $clean;
            }
            $input = json_decode($input, true, 4);
            if (!is_array($input)) {
                return $clean;
            }
        } else {
            $json = json_encode($input);
            if ($json === false || strlen($json) > self::MAXBYTES) {
                return $clean;
            }
        }
        if (array_key_exists('schema', $input) && $input['schema'] !== self::SCHEMA) {
            return $clean;
        }

        foreach (self::choices() as $key => $allowed) {
            if (isset($input[$key]) && is_string($input[$key]) && in_array($input[$key], $allowed, true)) {
                $clean[$key] = $input[$key];
            }
        }
        foreach (self::FLAGS as $key) {
            if (array_key_exists($key, $input)) {
                $flag = self::flag($input[$key]);
                if ($flag !== null) {
                    $clean[$key] = $flag;
                }
            }
        }
        return $clean;
    }

    /**
     * A true or false value, or null for anything else.
     *
     * @param mixed $value Value from the document.
     * @return bool|null
     */
    private static function flag(mixed $value): ?bool {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }
        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }
        return null;
    }

    /**
     * The user's saved design, cleaned again, or null when the user has none.
     *
     * @param int $userid User id.
     * @return array|null
     */
    public static function load(int $userid): ?array {
        $stored = get_user_preferences(self::PREFERENCE, null, $userid);
        if ($stored === null || $stored === '') {
            return null;
        }
        return self::clean((string)$stored);
    }

    /**
     * The saved designs of several users in one query, each cleaned again.
     *
     * @param int[] $userids User ids.
     * @return array userid => clean document, only for users with a design
     */
    public static function load_many(array $userids): array {
        global $DB;
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        if (!$userids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $params['name'] = self::PREFERENCE;
        $rows = $DB->get_records_select(
            'user_preferences',
            "name = :name AND userid {$insql}",
            $params,
            '',
            'id, userid, value'
        );
        $designs = [];
        foreach ($rows as $row) {
            if ((string)$row->value !== '') {
                $designs[(int)$row->userid] = self::clean((string)$row->value);
            }
        }
        return $designs;
    }

    /**
     * Clean and save a user's design.
     *
     * @param int $userid User id.
     * @param array $design The document.
     * @return array The clean document that was saved.
     */
    public static function save(int $userid, array $design): array {
        $clean = self::clean($design);
        set_user_preference(self::PREFERENCE, json_encode($clean), $userid);
        return $clean;
    }

    /**
     * Remove a user's design: the user has the standard look again.
     *
     * @param int $userid User id.
     * @return void
     */
    public static function remove(int $userid): void {
        unset_user_preference(self::PREFERENCE, $userid);
    }

    /**
     * Whether the site lets users show their profile picture on their avatar (on by default).
     *
     * @return bool
     */
    public static function picture_allowed(): bool {
        $setting = get_config('local_avatar', 'allowpicture');
        return $setting === false ? true : !empty($setting);
    }

    /**
     * Whether a user has a profile picture worth showing: an uploaded picture, or a Gravatar when
     * the site uses them. The standard grey silhouette is not shown on an avatar.
     *
     * @param \stdClass $user User record with the user picture fields and deleted.
     * @return bool
     */
    public static function has_picture(\stdClass $user): bool {
        global $CFG;
        if (!empty($user->deleted)) {
            return false;
        }
        return (int)($user->picture ?? 0) > 0 || !empty($CFG->enablegravatar);
    }

    /**
     * The address of a user's normal profile picture, the one Moodle shows next to their name.
     *
     * @param \stdClass $user User record with the user picture fields and deleted.
     * @param \moodle_page $page Page whose theme and renderer to use.
     * @return string The URL, or '' when the user has no picture worth showing.
     */
    public static function picture_url(\stdClass $user, \moodle_page $page): string {
        if (!self::has_picture($user)) {
            return '';
        }
        $picture = new \core\output\user_picture($user);
        $picture->size = self::PICTURESIZE;
        return $picture->get_url($page)->out(false);
    }

    /**
     * The user fields that picture_url() needs.
     *
     * @return string Comma separated list for a $DB call.
     */
    public static function picture_fields(): string {
        $fields = \core_user\fields::get_picture_fields();
        $fields[] = 'deleted';
        return implode(',', array_unique($fields));
    }
}
