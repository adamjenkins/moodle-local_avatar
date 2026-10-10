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

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use local_avatar\local\design;

/**
 * The "My VR avatar" designer: the choices as labelled radio groups under four headings (head and
 * face, hair, body, extras), the preview and the buttons.
 *
 * @package    local_avatar
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class designer implements renderable, templatable {
    /**
     * The sections of the form, in page (and keyboard) order: lang string of the heading => keys.
     * A key in FLAGS is a checkbox, any other a radio group. The Extras section also holds the
     * glasses and profile picture checkboxes, drawn by the template. "outfit" is reserved and not
     * shown.
     *
     * @var array
     */
    private const SECTIONS = [
        'section_head' => ['head', 'skin', 'eyes', 'brows', 'nose', 'ears', 'beard'],
        'section_hair' => ['hair', 'haircolor'],
        'section_body' => ['body', 'color', 'accent'],
        'section_extras' => ['hat'],
    ];

    /** @var string[] Lang string of the legend of each group of choices. */
    private const LEGENDS = [
        'body' => 'body',
        'head' => 'head',
        'skin' => 'skin',
        'color' => 'color',
        'accent' => 'accent',
        'hair' => 'hair',
        'haircolor' => 'haircolor',
        'eyes' => 'eyes',
        'brows' => 'brows',
        'nose' => 'nose',
        'beard' => 'beard',
        'hat' => 'hat',
    ];

    /**
     * Constructor.
     *
     * @param array $current The clean design shown in the form.
     * @param bool $hasdesign Whether the user has saved a design.
     * @param bool $pictureallowed Whether the site allows profile pictures on avatars.
     * @param bool $haspicture Whether the user has a profile picture to show.
     * @param \core\url $actionurl Where the form posts without JavaScript.
     */
    public function __construct(
        /** @var array The clean design shown in the form. */
        protected array $current,
        /** @var bool Whether the user has saved a design. */
        protected bool $hasdesign,
        /** @var bool Whether the site allows profile pictures on avatars. */
        protected bool $pictureallowed,
        /** @var bool Whether the user has a profile picture to show. */
        protected bool $haspicture,
        /** @var \core\url Where the form posts without JavaScript. */
        protected \core\url $actionurl,
    ) {
    }

    /**
     * Data for the template.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $choices = design::choices();
        $sections = [];
        foreach (self::SECTIONS as $heading => $keys) {
            $items = [];
            foreach ($keys as $key) {
                if (in_array($key, design::FLAGS, true)) {
                    $items[] = ['check' => [
                        'name' => $key,
                        'id' => 'local-avatar-' . $key,
                        'groupid' => 'local-avatar-group-' . $key,
                        'legend' => get_string($key, 'local_avatar'),
                        'label' => get_string($key . '_show', 'local_avatar'),
                        'checked' => !empty($this->current[$key]),
                    ]];
                } else {
                    $items[] = ['radio' => $this->radio_group($key, $choices[$key])];
                }
            }
            $sections[] = [
                'id' => 'local-avatar-' . str_replace('_', '-', $heading),
                'heading' => get_string($heading, 'local_avatar'),
                'items' => $items,
                'isextras' => $heading === 'section_extras',
            ];
        }

        return [
            'actionurl' => $this->actionurl->out(false),
            'sesskey' => sesskey(),
            'sections' => $sections,
            'glasses' => !empty($this->current['glasses']),
            'picture' => !empty($this->current['picture']),
            'pictureallowed' => $this->pictureallowed,
            'haspicture' => $this->haspicture,
            'hasdesign' => $this->hasdesign,
        ];
    }

    /**
     * One group of radio buttons for the template.
     *
     * @param string $key Document key.
     * @param string[] $values Allowed values, in order.
     * @return array {name, id, legend, isswatch, palette, options: [{value, id, label, checked, swatch}]}
     */
    protected function radio_group(string $key, array $values): array {
        [$swatches, $palette] = match ($key) {
            'color', 'accent' => [design::SWATCHES, 'colour'],
            'haircolor' => [design::HAIRSWATCHES, 'hair'],
            'skin' => [design::SKINSWATCHES, 'skin'],
            default => [[], ''],
        };
        $options = [];
        foreach ($values as $value) {
            $options[] = [
                'value' => $value,
                'id' => 'local-avatar-' . $key . '-' . $value,
                'label' => get_string(self::option_string($key, $value), 'local_avatar'),
                'checked' => $this->current[$key] === $value,
                'swatch' => $swatches[$value] ?? '',
                'palette' => $palette,
            ];
        }
        return [
            'name' => $key,
            'id' => 'local-avatar-group-' . $key,
            'legend' => get_string(self::LEGENDS[$key], 'local_avatar'),
            'isswatch' => (bool)$swatches,
            'options' => $options,
        ];
    }

    /**
     * The lang string naming one value of one key.
     *
     * @param string $key Document key.
     * @param string $value Allowed value.
     * @return string String identifier.
     */
    public static function option_string(string $key, string $value): string {
        return match ($key) {
            'color', 'accent' => 'colour_' . $value,
            'haircolor' => 'haircolour_' . $value,
            'skin' => 'skin_' . $value,
            default => $key . '_' . $value,
        };
    }
}
