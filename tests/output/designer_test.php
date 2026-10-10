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
     * Every choice but the reserved outfit is shown exactly once, under the four headings, every
     * value with a lang string, and the current design is the one checked.
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
        $this->assertStringContainsString(get_string('showwhole', 'local_avatar'), $html);
    }
}
