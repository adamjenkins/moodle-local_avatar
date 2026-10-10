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
 * Tests of the avatar design allowlist and its storage.
 *
 * @package    local_avatar
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(design::class)]
final class design_test extends \advanced_testcase {
    /** @var array A design with every key set to a value other than the default. */
    private const FULL = [
        'schema' => 'vrroom-avatar/1',
        'body' => 'broad',
        'head' => 'heart',
        'skin' => 'tan',
        'color' => 'teal',
        'accent' => 'pink',
        'hair' => 'braids',
        'haircolor' => 'purple',
        'eyes' => 'almond',
        'brows' => 'arched',
        'nose' => 'pointed',
        'ears' => true,
        'beard' => 'goatee',
        'glasses' => true,
        'hat' => 'sunhat',
        'outfit' => 'none',
        'picture' => true,
    ];

    /**
     * The defaults are the contract's defaults, and an empty document gives them.
     */
    public function test_defaults(): void {
        $expected = [
            'schema' => 'vrroom-avatar/1',
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
            'picture' => true,
        ];
        $this->assertSame($expected, design::defaults());
        $this->assertSame($expected, design::clean([]));
        $this->assertSame($expected, design::clean('{}'));
    }

    /**
     * A design of allowed values is kept as it is, from an array or from JSON.
     */
    public function test_allowed_values_kept(): void {
        $this->assertSame(self::FULL, design::clean(self::FULL));
        $this->assertSame(self::FULL, design::clean(json_encode(self::FULL)));
        $withoutschema = self::FULL;
        unset($withoutschema['schema']);
        $this->assertSame(self::FULL, design::clean($withoutschema));
    }

    /**
     * Every allowed name of every key is accepted.
     */
    public function test_every_choice_accepted(): void {
        foreach (design::choices() as $key => $values) {
            foreach ($values as $value) {
                $this->assertSame($value, design::clean([$key => $value])[$key], "{$key}={$value}");
            }
        }
        $this->assertCount(12, design::COLOURS);
        $this->assertCount(10, design::HAIRCOLOURS);
        $this->assertCount(9, design::SKINS);
        $this->assertCount(14, design::HAIRSTYLES);
        $this->assertSame(design::COLOURS, array_keys(design::SWATCHES));
        $this->assertSame(design::HAIRCOLOURS, array_keys(design::HAIRSWATCHES));
        // Every skin tone but "match" (the body colour) has a swatch.
        $this->assertSame(array_values(array_diff(design::SKINS, ['match'])), array_keys(design::SKINSWATCHES));
    }

    /**
     * The new keys have the contract's lists, in order, and the defaults of the look before them.
     */
    public function test_hair_and_head_lists(): void {
        $choices = design::choices();
        $this->assertSame(['none', 'short', 'long', 'bun', 'curly', 'afro', 'ponytail', 'braids', 'buzz', 'bob',
            'mohawk', 'sidepart', 'pigtails', 'topknot'], $choices['hair']);
        $this->assertSame(
            ['black', 'brown', 'blonde', 'auburn', 'grey', 'white', 'red', 'pink', 'blue', 'purple'],
            $choices['haircolor']
        );
        $this->assertSame(['round', 'square', 'oval', 'heart'], $choices['head']);
        $this->assertSame(
            ['match', 'porcelain', 'fair', 'light', 'medium', 'tan', 'brown', 'dark', 'deep'],
            $choices['skin']
        );
        $this->assertSame(['dots', 'round', 'almond', 'sleepy'], $choices['eyes']);
        $this->assertSame(['none', 'thin', 'thick', 'arched'], $choices['brows']);
        $this->assertSame(['none', 'small', 'round', 'pointed'], $choices['nose']);
        $this->assertSame(['none', 'stubble', 'moustache', 'goatee', 'full'], $choices['beard']);
        $this->assertSame(['none'], $choices['outfit']);
        $this->assertContains('ears', design::FLAGS);
    }

    /**
     * A design saved before the new keys existed reads back with today's look for each of them.
     */
    public function test_old_design_keeps_its_look(): void {
        $old = [
            'schema' => 'vrroom-avatar/1',
            'body' => 'slim',
            'head' => 'square',
            'color' => 'red',
            'accent' => 'yellow',
            'hair' => 'long',
            'haircolor' => 'blonde',
            'glasses' => true,
            'hat' => 'cap',
            'picture' => false,
        ];
        $clean = design::clean(json_encode($old));
        foreach ($old as $key => $value) {
            $this->assertSame($value, $clean[$key], $key);
        }
        $this->assertSame('match', $clean['skin']);
        $this->assertSame('dots', $clean['eyes']);
        $this->assertSame('none', $clean['brows']);
        $this->assertSame('none', $clean['nose']);
        $this->assertFalse($clean['ears']);
        $this->assertSame('none', $clean['beard']);
        $this->assertSame('none', $clean['outfit']);
    }

    /**
     * "outfit" is reserved for costumes: only "none" is kept, anything else is dropped.
     */
    public function test_outfit_reserved(): void {
        $this->assertSame('none', design::clean(['outfit' => 'none'])['outfit']);
        foreach (['pirate', 'sailor', 'suit', '', 'None', 1, true, ['none']] as $value) {
            $this->assertSame('none', design::clean(['outfit' => $value])['outfit'], var_export($value, true));
        }
    }

    /**
     * Unknown keys are dropped and unknown or mistyped values take the default.
     */
    public function test_unknown_dropped(): void {
        $clean = design::clean([
            'body' => 'giant',
            'head' => ['round'],
            'color' => 'Blue',
            'accent' => '#ff0000',
            'hair' => '<script>alert(1)</script>',
            'haircolor' => 3,
            'glasses' => 'yes',
            'hat' => null,
            'picture' => 'https://example.org/me.png',
            'skin' => '#c98e64',
            'eyes' => 'laser',
            'brows' => 'Thick',
            'nose' => ['small'],
            'ears' => 'pointy',
            'beard' => 'full ',
            'outfit' => 'pirate',
            'name' => 'Someone else',
            'html' => '<b>bold</b>',
        ]);
        $this->assertSame(design::defaults(), $clean);
        $this->assertSame(array_keys(design::defaults()), array_keys($clean));
    }

    /**
     * The flags take true or false in the usual spellings.
     */
    public function test_flags(): void {
        foreach ([true, 1, '1', 'true'] as $yes) {
            $this->assertTrue(design::clean(['glasses' => $yes])['glasses']);
            $this->assertTrue(design::clean(['ears' => $yes])['ears']);
        }
        foreach ([false, 0, '0', 'false'] as $no) {
            $this->assertFalse(design::clean(['glasses' => $no, 'picture' => $no])['glasses']);
            $this->assertFalse(design::clean(['picture' => $no])['picture']);
        }
        // Anything else is the default, which shows the picture.
        $this->assertTrue(design::clean(['picture' => 2])['picture']);
    }

    /**
     * Documents larger than the cap, invalid JSON, a JSON scalar and another schema give the default.
     */
    public function test_rejected_documents(): void {
        $big = self::FULL + ['padding' => str_repeat('x', design::MAXBYTES)];
        $this->assertSame(design::defaults(), design::clean($big));
        $this->assertSame(design::defaults(), design::clean(json_encode($big)));
        $this->assertSame(design::defaults(), design::clean('not json'));
        $this->assertSame(design::defaults(), design::clean('"capsule"'));
        $this->assertSame(design::defaults(), design::clean(['schema' => 'vrroom-avatar/2'] + self::FULL));
        // Just under the cap is fine.
        $fits = self::FULL + ['padding' => str_repeat('x', design::MAXBYTES - strlen(json_encode(self::FULL)) - 20)];
        $this->assertLessThanOrEqual(design::MAXBYTES, strlen(json_encode($fits)));
        $this->assertSame(self::FULL, design::clean($fits));
    }

    /**
     * Save cleans and stores one preference; load cleans again; load_many reads several users.
     */
    public function test_save_load(): void {
        global $DB;
        $this->resetAfterTest();
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $c = $this->getDataGenerator()->create_user();

        $this->assertNull(design::load((int)$a->id));
        $saved = design::save((int)$a->id, ['body' => 'slim', 'evil' => 'x']);
        $this->assertSame('slim', $saved['body']);
        $this->assertArrayNotHasKey('evil', $saved);
        $this->assertSame($saved, design::load((int)$a->id));
        $stored = $DB->get_field('user_preferences', 'value', ['userid' => $a->id, 'name' => design::PREFERENCE]);
        $this->assertSame($saved, json_decode($stored, true));

        // A tampered preference is cleaned on read.
        $DB->set_field('user_preferences', 'value', '{"body":"giant","color":"red","x":"<b>"}', [
            'userid' => $a->id,
            'name' => design::PREFERENCE,
        ]);
        // The same document, whatever the key order.
        $this->assertEquals(['color' => 'red'] + design::defaults(), design::load((int)$a->id));

        design::save((int)$b->id, self::FULL);
        $many = design::load_many([(int)$a->id, (int)$b->id, (int)$c->id, (int)$b->id, 0]);
        $this->assertEqualsCanonicalizing([(int)$a->id, (int)$b->id], array_keys($many));
        $this->assertSame(self::FULL, $many[(int)$b->id]);
        $this->assertSame('red', $many[(int)$a->id]['color']);
        $this->assertArrayNotHasKey((int)$c->id, $many);
        $this->assertSame([], design::load_many([]));

        design::remove((int)$a->id);
        $this->assertNull(design::load((int)$a->id));
    }

    /**
     * The profile picture setting is on by default and can be turned off.
     */
    public function test_picture_allowed(): void {
        $this->resetAfterTest();
        $this->assertTrue(design::picture_allowed());
        unset_config('allowpicture', 'local_avatar');
        $this->assertTrue(design::picture_allowed());
        set_config('allowpicture', 0, 'local_avatar');
        $this->assertFalse(design::picture_allowed());
    }

    /**
     * Only a real profile picture (or a Gravatar) gives a picture address.
     */
    public function test_picture_url(): void {
        global $CFG, $DB, $PAGE;
        $this->resetAfterTest();
        // As in a live room: a logged-in user asks (with forced login, others get the default picture).
        $this->setUser($this->getDataGenerator()->create_user());
        $CFG->enablegravatar = 0;
        $without = $this->getDataGenerator()->create_user();
        $with = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'picture', 42, ['id' => $with->id]);

        $fields = design::picture_fields();
        $userwithout = $DB->get_record('user', ['id' => $without->id], $fields);
        $userwith = $DB->get_record('user', ['id' => $with->id], $fields);
        $this->assertSame('', design::picture_url($userwithout, $PAGE));
        $url = design::picture_url($userwith, $PAGE);
        $this->assertStringContainsString('/user/icon/', $url);
        $this->assertStringContainsString('rev=42', $url);

        $userwith->deleted = 1;
        $this->assertSame('', design::picture_url($userwith, $PAGE));

        $CFG->enablegravatar = 1;
        $this->assertStringContainsString('gravatar.com/avatar/', design::picture_url($userwithout, $PAGE));
    }
}
