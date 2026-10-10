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

namespace local_vravatar\external;

use core_external\external_api;
use local_vravatar\local\design;

/**
 * Tests of the save and get design web services.
 *
 * @package    local_vravatar
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(save_design::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_design::class)]
final class services_test extends \advanced_testcase {
    /**
     * Save through the service.
     *
     * @param array $design Document.
     * @return array Cleaned return value.
     */
    private function save(array $design): array {
        return external_api::clean_returnvalue(
            save_design::execute_returns(),
            save_design::execute(json_encode($design))
        );
    }

    /**
     * Read through the service.
     *
     * @return array Cleaned return value.
     */
    private function get(): array {
        return external_api::clean_returnvalue(get_design::execute_returns(), get_design::execute());
    }

    /**
     * A user saves and reads back their own design; unknown values are dropped; nobody else's
     * design changes.
     */
    public function test_save_and_get_own(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        design::save((int)$other->id, ['color' => 'green']);
        $this->setUser($user);

        $before = $this->get();
        $this->assertFalse($before['hasdesign']);
        $this->assertSame(design::defaults(), $before['design']);
        $this->assertTrue($before['pictureallowed']);

        $result = $this->save(['body' => 'slim', 'color' => 'purple', 'hat' => 'crown', 'glasses' => true, 'picture' => true,
            'userid' => (int)$other->id]);
        $this->assertSame('slim', $result['design']['body']);
        $this->assertSame('purple', $result['design']['color']);
        $this->assertSame('none', $result['design']['hat']);
        $this->assertTrue($result['design']['glasses']);
        $this->assertTrue($result['design']['picture']);
        $this->assertTrue($result['pictureallowed']);

        $after = $this->get();
        $this->assertTrue($after['hasdesign']);
        $this->assertSame($result['design'], $after['design']);
        $this->assertSame($result['design'], design::load((int)$user->id));
        // The other user's design is untouched.
        $this->assertSame('green', design::load((int)$other->id)['color']);
        $this->assertSame('capsule', design::load((int)$other->id)['body']);
    }

    /**
     * The hair and head keys go through both services; an outfit other than none is dropped.
     */
    public function test_save_hair_and_head(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $chosen = ['skin' => 'deep', 'hair' => 'afro', 'haircolor' => 'pink', 'head' => 'oval', 'eyes' => 'sleepy',
            'brows' => 'thick', 'nose' => 'round', 'ears' => true, 'beard' => 'full'];
        $result = $this->save($chosen + ['outfit' => 'pirate']);
        foreach ($chosen as $key => $value) {
            $this->assertSame($value, $result['design'][$key], $key);
        }
        $this->assertSame('none', $result['design']['outfit']);
        $this->assertSame($result['design'], $this->get()['design']);
    }

    /**
     * A costume id (with hyphens) and the pirate hat go through both services.
     */
    public function test_save_costume(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $result = $this->save(['outfit' => 'mini-female-c', 'hat' => 'pirate']);
        $this->assertSame('mini-female-c', $result['design']['outfit']);
        $this->assertSame('pirate', $result['design']['hat']);
        $this->assertSame($result['design'], $this->get()['design']);
    }

    /**
     * Invalid JSON saves the default design.
     */
    public function test_save_invalid_json(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $result = external_api::clean_returnvalue(save_design::execute_returns(), save_design::execute('{"body":'));
        $this->assertSame(design::defaults(), $result['design']);
    }

    /**
     * While the site does not allow profile pictures, a save keeps the picture choice saved before.
     */
    public function test_picture_choice_kept_when_not_allowed(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->save(['picture' => true]);

        set_config('allowpicture', 0, 'local_vravatar');
        $result = $this->save(['picture' => false, 'body' => 'broad']);
        $this->assertFalse($result['pictureallowed']);
        $this->assertTrue($result['design']['picture']);
        $this->assertSame('broad', $result['design']['body']);
        $this->assertFalse($this->get()['pictureallowed']);

        $other = $this->getDataGenerator()->create_user();
        $this->setUser($other);
        $this->assertFalse($this->save(['picture' => true])['design']['picture']);
    }

    /**
     * Without the capability in their user context a user cannot save or read.
     */
    public function test_requires_capability(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $userrole = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability('local/vravatar:edit', CAP_PROHIBIT, $userrole, \context_system::instance()->id, true);
        $this->setUser($user);

        try {
            save_design::execute(json_encode(['body' => 'slim']));
            $this->fail('Saved without the capability');
        } catch (\required_capability_exception $e) {
            // The message names the capability by its title.
            $this->assertStringContainsString(get_capability_string('local/vravatar:edit'), $e->getMessage());
        }
        $this->assertNull(design::load((int)$user->id));

        $this->expectException(\required_capability_exception::class);
        get_design::execute();
    }

    /**
     * Guests cannot save.
     */
    public function test_guest_refused(): void {
        $this->resetAfterTest();
        $this->setGuestUser();
        $this->expectException(\moodle_exception::class);
        save_design::execute(json_encode(['body' => 'slim']));
    }

    /**
     * Without a user nothing is saved.
     */
    public function test_not_logged_in_refused(): void {
        $this->resetAfterTest();
        $this->setUser(0);
        $this->expectException(\moodle_exception::class);
        get_design::execute();
    }
}
