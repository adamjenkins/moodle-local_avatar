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

namespace local_avatar\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_avatar\local\design;

/**
 * Tests of the privacy provider.
 *
 * @package    local_avatar
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends provider_testcase {
    /**
     * The metadata names the preference and the live room participants.
     */
    public function test_get_metadata(): void {
        $items = provider::get_metadata(new collection('local_avatar'))->get_collection();
        $names = array_map(fn($item) => $item->get_name(), $items);
        $this->assertContains(design::PREFERENCE, $names);
        $this->assertContains('liveroomparticipants', $names);
    }

    /**
     * Contexts and users: the user context, only for users with a design.
     */
    public function test_contexts_and_users(): void {
        $this->resetAfterTest();
        $with = $this->getDataGenerator()->create_user();
        $without = $this->getDataGenerator()->create_user();
        design::save((int)$with->id, ['body' => 'slim']);
        $context = \context_user::instance($with->id);

        $contextids = provider::get_contexts_for_userid((int)$with->id)->get_contextids();
        $this->assertSame([(int)$context->id], array_map('intval', $contextids));
        $this->assertSame([], provider::get_contexts_for_userid((int)$without->id)->get_contextids());

        $userlist = new userlist($context, 'local_avatar');
        provider::get_users_in_context($userlist);
        $this->assertSame([(int)$with->id], array_map('intval', $userlist->get_userids()));

        $userlist = new userlist(\context_user::instance($without->id), 'local_avatar');
        provider::get_users_in_context($userlist);
        $this->assertSame([], $userlist->get_userids());

        $userlist = new userlist(\context_system::instance(), 'local_avatar');
        provider::get_users_in_context($userlist);
        $this->assertSame([], $userlist->get_userids());
    }

    /**
     * The design is exported as a user preference.
     */
    public function test_export_user_preferences(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $saved = design::save((int)$user->id, ['color' => 'orange', 'glasses' => true]);

        provider::export_user_preferences((int)$user->id);
        $writer = writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data());
        $prefs = $writer->get_user_preferences('local_avatar');
        $this->assertSame($saved, json_decode($prefs->{design::PREFERENCE}->value, true));
        $this->assertSame(get_string('privacy:preference:design', 'local_avatar'), $prefs->{design::PREFERENCE}->description);
    }

    /**
     * Nothing is exported for a user without a design.
     */
    public function test_export_nothing(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        provider::export_user_preferences((int)$user->id);
        $this->assertFalse(writer::with_context(\context_system::instance())->has_any_data());
    }

    /**
     * Deleting for one user, for all users of the user context and for a userlist removes the design.
     */
    public function test_delete(): void {
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $c = $this->getDataGenerator()->create_user();
        foreach ([$a, $b, $c] as $user) {
            design::save((int)$user->id, ['body' => 'broad']);
        }
        $contexta = \context_user::instance($a->id);
        $contextb = \context_user::instance($b->id);
        $contextc = \context_user::instance($c->id);

        // Another user's context in the list deletes nothing of theirs.
        provider::delete_data_for_user(new approved_contextlist($a, 'local_avatar', [$contexta->id, $contextb->id]));
        $this->assertNull(design::load((int)$a->id));
        $this->assertNotNull(design::load((int)$b->id));

        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertNotNull(design::load((int)$b->id));
        provider::delete_data_for_all_users_in_context($contextb);
        $this->assertNull(design::load((int)$b->id));

        provider::delete_data_for_users(new approved_userlist($contextc, 'local_avatar', [(int)$b->id]));
        $this->assertNotNull(design::load((int)$c->id));
        provider::delete_data_for_users(new approved_userlist($contextc, 'local_avatar', [(int)$c->id]));
        $this->assertNull(design::load((int)$c->id));
    }
}
