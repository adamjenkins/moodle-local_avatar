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

namespace local_avatar\output;

use local_avatar\local\costumes;
use local_avatar\local\design;

/**
 * Tests of the designer page's template data.
 *
 * @package    local_avatar
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(designer::class)]
final class designer_test extends \advanced_testcase {
    /**
     * Every choice but the costume is shown exactly once as a radio group or checkbox, under the five
     * headings, every value with a lang string, and the current design is the one checked.
     */
    public function test_sections(): void {
        global $PAGE;
        $this->resetAfterTest();
        $current = design::clean(['skin' => 'dark', 'hair' => 'mohawk', 'ears' => true, 'beard' => 'stubble']);
        $designer = new designer($current, true, true, false, new \core\url('/local/avatar/index.php'));
        $data = $designer->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame(
            [
                get_string('section_head', 'local_avatar'),
                get_string('section_hair', 'local_avatar'),
                get_string('section_body', 'local_avatar'),
                get_string('section_costume', 'local_avatar'),
                get_string('section_extras', 'local_avatar'),
            ],
            array_column($data['sections'], 'heading')
        );

        $radios = [];
        $checks = [];
        foreach ($data['sections'] as $section) {
            foreach ($section['items'] as $item) {
                if (isset($item['radio'])) {
                    $radios[$item['radio']['name']] = $item['radio'];
                } else if (isset($item['costume'])) {
                    continue;
                } else {
                    $checks[$item['check']['name']] = $item['check'];
                }
            }
        }
        $expected = array_diff(array_keys(design::choices()), ['outfit']);
        $this->assertEqualsCanonicalizing($expected, array_keys($radios));
        $this->assertSame(['ears'], array_keys($checks));
        $this->assertTrue($checks['ears']['checked']);

        foreach ($radios as $key => $group) {
            $this->assertSame(design::choices()[$key], array_column($group['options'], 'value'), $key);
            $checked = array_column(array_filter($group['options'], fn($o) => $o['checked']), 'value');
            $this->assertSame([$current[$key]], $checked, $key);
            foreach ($group['options'] as $option) {
                $this->assertNotSame('', $option['label']);
                $this->assertStringNotContainsString('[[', $option['label'], "{$key}={$option['value']}");
            }
        }
        // Skin tones are named swatches; "match" has no swatch of its own.
        $this->assertTrue($radios['skin']['isswatch']);
        $this->assertSame('', $radios['skin']['options'][0]['swatch']);
        $this->assertSame(get_string('skin_match', 'local_avatar'), $radios['skin']['options'][0]['label']);
        $this->assertSame('skin', $radios['skin']['options'][1]['palette']);

        // The pirate hat is a model, with its CC BY credit; the drawn hats are not.
        $hats = array_column($radios['hat']['options'], null, 'value');
        $this->assertStringEndsWith('/local/avatar/costumes/hat-pirate.glb', $hats['pirate']['model']);
        $this->assertStringContainsString('Poly by Google', $hats['pirate']['credit']);
        $this->assertStringContainsString('CC BY 3.0', $hats['pirate']['credit']);
        $this->assertSame('', $hats['cap']['model']);
        $this->assertSame('', $hats['cap']['credit']);
    }

    /**
     * The costume picker: "No costume", then every outfit once, grouped by category in page order,
     * each with its name, thumbnail, model and (only for CC BY models) credit; the current one checked.
     */
    public function test_costume_picker(): void {
        global $PAGE;
        $this->resetAfterTest();
        $current = design::clean(['outfit' => 'suit-tie']);
        $designer = new designer($current, true, true, false, new \core\url('/local/avatar/index.php'));
        $data = $designer->export_for_template($PAGE->get_renderer('core'));

        $section = $data['sections'][3];
        $this->assertSame('local-avatar-section-costume', $section['id']);
        $picker = $section['items'][0]['costume'];
        $this->assertSame('outfit', $picker['name']);
        $this->assertSame('none', $picker['none']['value']);
        $this->assertFalse($picker['none']['checked']);

        $categories = array_map(fn($c) => substr($c['id'], strlen('local-avatar-costumes-')), $picker['categories']);
        $this->assertSame(costumes::CATEGORIES, $categories);

        $options = [];
        foreach ($picker['categories'] as $category) {
            $this->assertStringNotContainsString('[[', $category['legend']);
            foreach ($category['options'] as $option) {
                $options[$option['value']] = $option;
            }
        }
        $this->assertSame(costumes::outfit_ids(), array_keys($options));
        foreach ($options as $id => $option) {
            $this->assertSame(get_string(costumes::name_string($id), 'local_avatar'), $option['label']);
            $this->assertStringContainsString('costumes/' . $id, $option['thumb']);
            $this->assertStringEndsWith('/local/avatar/costumes/' . $id . '.glb', $option['model']);
            $this->assertSame($id === 'suit-tie', $option['checked'], $id);
            $this->assertSame(costumes::needs_attribution(costumes::get($id)), $option['credit'] !== '', $id);
        }
        $this->assertSame('by Ian MacGillivray, CC BY 3.0', $options['suit-tie']['credit']);
        $this->assertSame('device', $options['device-laptop']['kind']);

        // Every model, the hat too, is in the credits list.
        $this->assertTrue($data['hascredits']);
        $this->assertCount(count(costumes::all()), $data['credits']);
    }

    /**
     * The page renders without its template docblock leaking out, posts without JavaScript, and
     * has the zoom and camera buttons hidden until the preview starts.
     */
    public function test_template_renders(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->set_url(new \core\url('/local/avatar/index.php'));
        $output = $PAGE->get_renderer('core');
        $designer = new designer(design::clean([]), true, true, false, new \core\url('/local/avatar/index.php'));
        $html = $output->render_from_template('local_avatar/designer', $designer->export_for_template($output));

        $this->assertStringNotContainsString('Example context', $html);
        $this->assertStringNotContainsString('@template', $html);
        $this->assertMatchesRegularExpression('~<form method="post" action="[^"]*/local/avatar/index\.php"~', $html);
        $this->assertMatchesRegularExpression('~data-action="zoom"[^>]*\shidden>~', $html);
        $this->assertMatchesRegularExpression('~data-action="zoom"[^>]*aria-pressed="false"~', $html);
        $this->assertStringContainsString(get_string('showwhole', 'local_avatar'), $html);
    }

    /**
     * The costume picker renders as labelled radio buttons named outfit, each with a decorative
     * thumbnail and its model address, the CC BY credits are shown, and the credits list links the
     * sources.
     */
    public function test_template_renders_costumes(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->set_url(new \core\url('/local/avatar/index.php'));
        $output = $PAGE->get_renderer('core');
        $designer = new designer(
            design::clean(['outfit' => 'device-laptop', 'hat' => 'pirate']),
            true,
            true,
            false,
            new \core\url('/local/avatar/index.php')
        );
        $html = $output->render_from_template('local_avatar/designer', $designer->export_for_template($output));

        $this->assertStringContainsString(get_string('section_costume', 'local_avatar'), $html);
        $this->assertMatchesRegularExpression(
            '~<input class="form-check-input" type="radio" name="outfit" value="none" id="local-avatar-outfit-none"~',
            $html
        );
        foreach (costumes::outfit_ids() as $id) {
            $this->assertMatchesRegularExpression(
                '~<input class="local-avatar-costume-input" type="radio" name="outfit"\s+value="' . preg_quote($id, '~') .
                    '" id="local-avatar-outfit-' . preg_quote($id, '~') . '" data-model="[^"]*/local/avatar/costumes/' .
                    preg_quote($id, '~') . '\.glb"~',
                $html,
                $id
            );
            $this->assertStringContainsString(
                '<label class="local-avatar-costume-label" for="local-avatar-outfit-' . $id . '">',
                $html
            );
        }
        $this->assertMatchesRegularExpression('~id="local-avatar-outfit-device-laptop"[^>]*\schecked>~', $html);
        $this->assertMatchesRegularExpression('~<img class="local-avatar-costume-thumb" src="[^"]+" alt=""~', $html);
        $this->assertMatchesRegularExpression(
            '~id="local-avatar-hat-pirate"\s+data-model="[^"]*hat-pirate\.glb"\s+checked>~',
            $html
        );
        $this->assertStringContainsString('by Ian MacGillivray, CC BY 3.0', $html);
        $this->assertStringContainsString('by Robert Schlyter, CC BY 3.0', $html);
        $this->assertStringContainsString('by Poly by Google, CC BY 3.0', $html);
        $this->assertStringContainsString(get_string('costumecredits', 'local_avatar'), $html);
        $this->assertStringContainsString('href="https://kenney.nl/assets/blocky-characters"', $html);
        $this->assertStringContainsString('aria-describedby="local-avatar-outfit-help"', $html);
    }
}
