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

namespace local_vravatar\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use local_vravatar\local\costumes;
use local_vravatar\local\design;

/**
 * The "My VR avatar" designer: the choices as labelled radio groups under five headings (head and
 * face, hair, body, costume, extras), the preview and the buttons.
 *
 * @package    local_vravatar
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class designer implements renderable, templatable {
    /**
     * The sections of the form, in page (and keyboard) order: lang string of the heading => keys.
     * A key in FLAGS is a checkbox, "outfit" the costume picker (thumbnails by category), any other
     * a radio group. The Extras section also holds the glasses and profile picture checkboxes, drawn
     * by the template. The costume comes after the body: a costume replaces the body (a device the
     * head too), and some costumes take the accent colour.
     *
     * @var array
     */
    private const SECTIONS = [
        'section_head' => ['head', 'skin', 'eyes', 'brows', 'nose', 'ears', 'beard'],
        'section_hair' => ['hair', 'haircolor'],
        'section_body' => ['body', 'color', 'accent'],
        'section_costume' => ['outfit'],
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
                if ($key === 'outfit') {
                    $items[] = ['costume' => $this->costume_group($output, $choices['outfit'])];
                } else if (in_array($key, design::FLAGS, true)) {
                    $items[] = ['check' => [
                        'name' => $key,
                        'id' => 'local-vravatar-' . $key,
                        'groupid' => 'local-vravatar-group-' . $key,
                        'legend' => get_string($key, 'local_vravatar'),
                        'label' => get_string($key . '_show', 'local_vravatar'),
                        'checked' => !empty($this->current[$key]),
                    ]];
                } else {
                    $items[] = ['radio' => $this->radio_group($key, $choices[$key])];
                }
            }
            $sections[] = [
                'id' => 'local-vravatar-' . str_replace('_', '-', $heading),
                'heading' => get_string($heading, 'local_vravatar'),
                'items' => $items,
                'isextras' => $heading === 'section_extras',
            ];
        }

        $credits = $this->credits();
        return [
            'actionurl' => $this->actionurl->out(false),
            'sesskey' => sesskey(),
            'sections' => $sections,
            'glasses' => !empty($this->current['glasses']),
            'picture' => !empty($this->current['picture']),
            'pictureallowed' => $this->pictureallowed,
            'haspicture' => $this->haspicture,
            'hasdesign' => $this->hasdesign,
            'credits' => $credits,
            'hascredits' => (bool)$credits,
        ];
    }

    /**
     * The costume picker: "No costume", then one group of thumbnails per category.
     *
     * Each costume is a radio button whose label holds its thumbnail, its name and, when its licence
     * asks for it, the author's credit. data-model gives the designer's preview the model's address.
     *
     * @param renderer_base $output Renderer.
     * @param string[] $allowed Allowed values of "outfit": "none" and the costume ids.
     * @return array {name, id, legend, none: option, categories: [{id, legend, options}]}
     */
    protected function costume_group(renderer_base $output, array $allowed): array {
        $bycategory = array_fill_keys(costumes::CATEGORIES, []);
        foreach ($allowed as $id) {
            $entry = costumes::get($id);
            if ($entry === null) {
                continue;
            }
            $bycategory[$entry['category']][] = [
                'value' => $id,
                'id' => 'local-vravatar-outfit-' . $id,
                'label' => get_string(costumes::name_string($id), 'local_vravatar'),
                'checked' => $this->current['outfit'] === $id,
                'thumb' => $output->image_url('costumes/' . $id, 'local_vravatar')->out(false),
                'model' => costumes::model_url($id)->out(false),
                'credit' => self::credit_line($entry),
                'kind' => $entry['kind'],
            ];
        }
        $categories = [];
        foreach ($bycategory as $category => $options) {
            if ($options) {
                $categories[] = [
                    'id' => 'local-vravatar-costumes-' . $category,
                    'legend' => get_string('costumecat_' . $category, 'local_vravatar'),
                    'options' => $options,
                ];
            }
        }
        return [
            'name' => 'outfit',
            'id' => 'local-vravatar-group-outfit',
            'legend' => get_string('outfit', 'local_vravatar'),
            'none' => [
                'value' => 'none',
                'id' => 'local-vravatar-outfit-none',
                'label' => get_string('outfit_none', 'local_vravatar'),
                'checked' => $this->current['outfit'] === 'none',
            ],
            'categories' => $categories,
        ];
    }

    /**
     * The small credit line of a costume whose licence asks for one ("by <author>, CC BY 3.0").
     *
     * @param array $entry Manifest entry.
     * @return string The line, or '' when the licence does not ask for a credit.
     */
    protected static function credit_line(array $entry): string {
        if (!costumes::needs_attribution($entry)) {
            return '';
        }
        return get_string('costumecredit', 'local_vravatar', (object)[
            'author' => $entry['author'],
            'licence' => costumes::licence_label($entry['licence']),
        ]);
    }

    /**
     * The credits of every costume model, for the list under the costume picker.
     *
     * Every costume is listed, whatever its licence: CC0 asks for no credit, but the authors get it.
     *
     * @return array list of {name, title, credit, source}
     */
    protected function credits(): array {
        $credits = [];
        foreach (costumes::all() as $id => $entry) {
            $credits[] = [
                'name' => get_string(costumes::name_string($id), 'local_vravatar'),
                'title' => $entry['title'],
                'credit' => get_string('costumecredit', 'local_vravatar', (object)[
                    'author' => $entry['author'],
                    'licence' => costumes::licence_label($entry['licence']),
                ]),
                'source' => $entry['source'],
            ];
        }
        return $credits;
    }

    /**
     * One group of radio buttons for the template.
     *
     * @param string $key Document key.
     * @param string[] $values Allowed values, in order.
     * @return array {name, id, legend, isswatch, options: [{value, id, label, checked, swatch, palette, model,
     *     credit}]}
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
            // A hat that is a costume model: the preview loads it, and its credit is shown.
            $hatcostume = $key === 'hat' && isset(costumes::HATS[$value]) ? costumes::get(costumes::HATS[$value]) : null;
            $options[] = [
                'value' => $value,
                'id' => 'local-vravatar-' . $key . '-' . $value,
                'label' => get_string(self::option_string($key, $value), 'local_vravatar'),
                'checked' => $this->current[$key] === $value,
                'swatch' => $swatches[$value] ?? '',
                'palette' => $palette,
                'model' => $hatcostume ? costumes::model_url($hatcostume['id'])->out(false) : '',
                'credit' => $hatcostume ? self::credit_line($hatcostume) : '',
            ];
        }
        return [
            'name' => $key,
            'id' => 'local-vravatar-group-' . $key,
            'legend' => get_string(self::LEGENDS[$key], 'local_vravatar'),
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
