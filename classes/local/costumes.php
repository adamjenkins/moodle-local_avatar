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

namespace local_vravatar\local;

/**
 * The costumes this plugin ships: the manifest costumes/costumes.json and the models next to it.
 *
 * Each costume is a glTF binary model, costumes/<id>.glb, served by the web server as a static file
 * (like the plugin's pix), with a thumbnail pix/costumes/<id>.png. The manifest lists for each one
 * its kind ("body": it replaces the body, "device": it replaces head and body and shows the face on
 * its screen, "accessory": a hat), its category on the designer page, its credit and licence.
 *
 * @package    local_vravatar
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class costumes {
    /** @var string The manifest, relative to the plugin directory. */
    public const MANIFEST = 'costumes/costumes.json';

    /** @var string[] The kinds a user can wear as their outfit (design key "outfit"). */
    public const OUTFIT_KINDS = ['body', 'device'];

    /** @var string[] The categories of the designer page, in page order. */
    public const CATEGORIES = ['pirate', 'suit', 'plant', 'alien', 'robot', 'pc', 'characters'];

    /**
     * The hats that are costume models: value of the design key "hat" => costume id (kind accessory).
     * The other hats are drawn by the avatar builder itself.
     *
     * @var string[]
     */
    public const HATS = ['pirate' => 'hat-pirate'];

    /** @var string[] Licences that need the author's credit shown. */
    public const ATTRIBUTION_LICENCES = ['CC-BY-3.0', 'CC-BY-4.0'];

    /** @var array|null The manifest once read, id => entry. */
    private static ?array $manifest = null;

    /**
     * All costumes of the manifest, in its order.
     *
     * An entry is kept only with an id of lower-case letters, digits and hyphens, a known kind and
     * category, a credit, a licence and an https source.
     *
     * @return array id => ['id', 'kind', 'category', 'title', 'author', 'credit', 'licence', 'source',
     *     'tint' (bool), 'thumb']
     */
    public static function all(): array {
        if (self::$manifest !== null) {
            return self::$manifest;
        }
        $manifest = [];
        $file = \core_component::get_component_directory('local_vravatar') . '/' . self::MANIFEST;
        $rows = is_readable($file) ? json_decode((string)file_get_contents($file), true) : null;
        foreach (is_array($rows) ? $rows : [] as $row) {
            $entry = self::clean_entry($row);
            if ($entry !== null) {
                $manifest[$entry['id']] = $entry;
            }
        }
        self::$manifest = $manifest;
        return $manifest;
    }

    /**
     * One manifest row, checked, or null when it is not usable.
     *
     * @param mixed $row Row of the manifest.
     * @return array|null
     */
    private static function clean_entry(mixed $row): ?array {
        if (!is_array($row)) {
            return null;
        }
        $id = $row['id'] ?? null;
        if (!is_string($id) || !preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $id)) {
            return null;
        }
        $kind = $row['kind'] ?? null;
        if (!in_array($kind, ['body', 'device', 'accessory'], true)) {
            return null;
        }
        $category = $row['category'] ?? null;
        if (!in_array($category, self::CATEGORIES, true)) {
            return null;
        }
        foreach (['title', 'author', 'credit', 'licence', 'source'] as $key) {
            if (!isset($row[$key]) || !is_string($row[$key]) || trim($row[$key]) === '') {
                return null;
            }
        }
        if (!preg_match('~^https://~', $row['source'])) {
            return null;
        }
        return [
            'id' => $id,
            'kind' => $kind,
            'category' => $category,
            'title' => $row['title'],
            'author' => $row['author'],
            'credit' => $row['credit'],
            'licence' => $row['licence'],
            'source' => $row['source'],
            'tint' => !empty($row['tint']),
            'thumb' => 'pix/costumes/' . $id . '.png',
        ];
    }

    /**
     * Forget the manifest read so far (for tests).
     *
     * @return void
     */
    public static function reset_cache(): void {
        self::$manifest = null;
    }

    /**
     * One costume.
     *
     * @param string $id Costume id.
     * @return array|null The entry, or null when there is no such costume.
     */
    public static function get(string $id): ?array {
        return self::all()[$id] ?? null;
    }

    /**
     * The ids a user can choose as their outfit (kinds body and device), in manifest order.
     *
     * @return string[]
     */
    public static function outfit_ids(): array {
        $ids = [];
        foreach (self::all() as $id => $entry) {
            if (in_array($entry['kind'], self::OUTFIT_KINDS, true)) {
                $ids[] = (string)$id;
            }
        }
        return $ids;
    }

    /**
     * The address of a costume's model.
     *
     * @param string $id Costume id.
     * @return \moodle_url
     */
    public static function model_url(string $id): \moodle_url {
        return new \moodle_url('/local/vravatar/costumes/' . $id . '.glb');
    }

    /**
     * The costume models a design names, for the avatar builder: 'outfit' => URL when an outfit is
     * worn, 'hat' => URL when the hat is a costume model.
     *
     * @param array $design A clean design.
     * @return array<string, string> asset name => URL
     */
    public static function assets_for(array $design): array {
        $assets = [];
        $outfit = (string)($design['outfit'] ?? 'none');
        if ($outfit !== 'none' && in_array($outfit, self::outfit_ids(), true)) {
            $assets['outfit'] = self::model_url($outfit)->out(false);
        }
        $hat = (string)($design['hat'] ?? 'none');
        if (isset(self::HATS[$hat]) && self::get(self::HATS[$hat]) !== null) {
            $assets['hat'] = self::model_url(self::HATS[$hat])->out(false);
        }
        return $assets;
    }

    /**
     * The lang string naming a costume.
     *
     * @param string $id Costume id.
     * @return string String identifier.
     */
    public static function name_string(string $id): string {
        return 'costume_' . str_replace('-', '_', $id);
    }

    /**
     * Whether a costume's licence asks for the author's credit to be shown.
     *
     * @param array $entry Manifest entry.
     * @return bool
     */
    public static function needs_attribution(array $entry): bool {
        return in_array($entry['licence'], self::ATTRIBUTION_LICENCES, true);
    }

    /**
     * A licence id as people read it ("CC-BY-3.0" => "CC BY 3.0", "CC0-1.0" => "CC0 1.0").
     *
     * @param string $licence SPDX licence id.
     * @return string
     */
    public static function licence_label(string $licence): string {
        return str_replace('-', ' ', $licence);
    }
}
