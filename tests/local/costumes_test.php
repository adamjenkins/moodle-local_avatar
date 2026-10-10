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
 * Tests of the costume manifest and the costume models it lists.
 *
 * @package    local_avatar
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(costumes::class)]
final class costumes_test extends \advanced_testcase {
    /** @var string[] The costumes the owner chose (2026-10-10), in manifest order. */
    private const IDS = [
        'pirate-captain', 'pirate-woman', 'hat-pirate', 'business-dude', 'suit-tie', 'sunflower', 'cactus',
        'alien-green', 'alien-pink', 'robot-screen', 'lalobot', 'device-laptop', 'device-monitor', 'device-tv',
        'device-computer',
        'blocky-a', 'blocky-b', 'blocky-c', 'blocky-d', 'blocky-e', 'blocky-f', 'blocky-g', 'blocky-h', 'blocky-i',
        'blocky-j', 'blocky-k', 'blocky-l', 'blocky-m', 'blocky-n', 'blocky-o', 'blocky-p', 'blocky-q', 'blocky-r',
        'mini-male-a', 'mini-male-b', 'mini-male-c', 'mini-male-d', 'mini-male-e', 'mini-male-f',
        'mini-female-a', 'mini-female-b', 'mini-female-c', 'mini-female-d', 'mini-female-e', 'mini-female-f',
    ];

    /**
     * Forget the manifest between tests.
     */
    protected function tearDown(): void {
        costumes::reset_cache();
        parent::tearDown();
    }

    /**
     * The manifest is valid JSON, every row of it survives the checks, and it lists the chosen costumes.
     */
    public function test_manifest_lists_every_costume(): void {
        $dir = \core_component::get_component_directory('local_avatar');
        $rows = json_decode(file_get_contents($dir . '/' . costumes::MANIFEST), true);
        $this->assertIsArray($rows);
        $this->assertSame(self::IDS, array_column($rows, 'id'));
        // No row is dropped by the checks.
        $this->assertSame(self::IDS, array_keys(costumes::all()));
    }

    /**
     * Every costume has its model and thumbnail file, a name, a category with a name, a licence, a
     * credit naming the author and an https source.
     */
    public function test_every_costume_complete(): void {
        $dir = \core_component::get_component_directory('local_avatar');
        $strings = get_string_manager();
        foreach (costumes::all() as $id => $entry) {
            $this->assertFileExists($dir . '/costumes/' . $id . '.glb', $id);
            $this->assertStringEndsWith('/local/avatar/costumes/' . $id . '.glb', costumes::model_url($id)->out(false));
            $this->assertSame('pix/costumes/' . $id . '.png', $entry['thumb'], $id);
            $this->assertFileExists($dir . '/' . $entry['thumb'], $id);
            $this->assertTrue($strings->string_exists(costumes::name_string($id), 'local_avatar'), $id);
            $this->assertTrue($strings->string_exists('costumecat_' . $entry['category'], 'local_avatar'), $id);
            $this->assertContains($entry['licence'], ['CC0-1.0', 'CC-BY-3.0', 'CC-BY-4.0'], $id);
            $this->assertNotSame('', trim($entry['credit']), $id);
            $this->assertStringContainsString($entry['author'], $entry['credit'], $id);
            $this->assertStringStartsWith('https://', $entry['source'], $id);
            $this->assertStringContainsString($entry['source'], $entry['credit'], $id);
            $this->assertIsBool($entry['tint'], $id);
        }
    }

    /**
     * Every category of the page has a name, and the CC BY models are the three that need a credit.
     */
    public function test_categories_and_attribution(): void {
        foreach (costumes::CATEGORIES as $category) {
            $this->assertTrue(get_string_manager()->string_exists('costumecat_' . $category, 'local_avatar'), $category);
        }
        $credited = array_keys(array_filter(costumes::all(), [costumes::class, 'needs_attribution']));
        $this->assertSame(['hat-pirate', 'suit-tie', 'device-computer'], $credited);
        $this->assertSame('CC BY 3.0', costumes::licence_label('CC-BY-3.0'));
    }

    /**
     * Every model is credited in thirdpartylibs.xml with its licence and source.
     */
    public function test_thirdpartylibs_lists_every_model(): void {
        $dir = \core_component::get_component_directory('local_avatar');
        $xml = simplexml_load_file($dir . '/thirdpartylibs.xml');
        $this->assertNotFalse($xml);
        $libraries = [];
        foreach ($xml->library as $library) {
            $libraries[(string)$library->location] = $library;
        }
        foreach (costumes::all() as $id => $entry) {
            $location = 'costumes/' . $id . '.glb';
            $this->assertArrayHasKey($location, $libraries, $id);
            $this->assertSame($entry['source'], (string)$libraries[$location]->repository, $id);
            $licence = (string)$libraries[$location]->license . '-' . (string)$libraries[$location]->licenseversion;
            $this->assertSame($entry['licence'], $licence, $id);
        }
    }

    /**
     * The outfits are the costumes of kind body or device; the pirate hat is an accessory, not an outfit.
     */
    public function test_outfit_ids_from_manifest(): void {
        $ids = costumes::outfit_ids();
        $this->assertCount(44, $ids);
        $this->assertNotContains('hat-pirate', $ids);
        $this->assertSame('accessory', costumes::get('hat-pirate')['kind']);
        foreach (['device-laptop', 'device-monitor', 'device-tv', 'device-computer', 'robot-screen'] as $id) {
            $this->assertSame('device', costumes::get($id)['kind'], $id);
            $this->assertContains($id, $ids);
        }
        $this->assertSame(array_merge(['none'], $ids), design::choices()['outfit']);
        $this->assertSame('hat-pirate', costumes::HATS['pirate']);
        $this->assertContains('pirate', design::choices()['hat']);
        $this->assertNull(costumes::get('no-such-costume'));
    }

    /**
     * A design gives the addresses of the models it names, and nothing for the drawn parts.
     */
    public function test_assets_for(): void {
        $this->assertSame([], costumes::assets_for(design::defaults()));
        $this->assertSame([], costumes::assets_for(design::clean(['hat' => 'cap'])));

        $assets = costumes::assets_for(design::clean(['outfit' => 'device-laptop', 'hat' => 'pirate']));
        $this->assertSame(['outfit', 'hat'], array_keys($assets));
        $this->assertSame((new \moodle_url('/local/avatar/costumes/device-laptop.glb'))->out(false), $assets['outfit']);
        $this->assertSame((new \moodle_url('/local/avatar/costumes/hat-pirate.glb'))->out(false), $assets['hat']);

        // A raw, uncleaned value never becomes a path.
        $this->assertSame([], costumes::assets_for(['outfit' => '../../config', 'hat' => 'hat-pirate']));
    }
}
