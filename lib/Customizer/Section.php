<?php

/**
 * Customizer section and its fields.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Customizer
 */

namespace WPB\Customizer;

use WPB\Notices;

/**
 * Declares one customizer section and the fields inside it.
 *
 * The section is an item in Appearance → Customize, on the same level as
 * Site Identity when it is not placed in a panel.
 *
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 *
 * @see https://developer.wordpress.org/reference/classes/wp_customize_manager/add_section/
 * @see https://developer.wordpress.org/reference/classes/wp_customize_manager/add_setting/
 * @see https://developer.wordpress.org/reference/classes/wp_customize_manager/add_control/
 */
class Section
{
    /**
     * Default position among customizer sections.
     */
    private const int DEFAULT_PRIORITY = 160;

    /**
     * Sanitized section id.
     */
    private string $id;

    /**
     * Section title shown in the customizer.
     */
    private string $title;

    /**
     * Text shown under the section title.
     */
    private string $description = '';

    /**
     * Sort order. Lower numbers appear first.
     */
    private int $priority = self::DEFAULT_PRIORITY;

    /**
     * Panel id that should contain this section.
     */
    private string $panel = '';

    /**
     * Whether this section's fields are stored as options.
     */
    private bool $saveAsOption = false;

    /**
     * Whether the section has an id and a title.
     */
    private bool $valid = true;

    /**
     * Fields declared on this section.
     *
     * A repeater is stored as an id stub and expanded in {@see Section::export()}.
     *
     * @var list<array<string, mixed>>
     */
    private array $fields = [];

    /**
     * Root repeaters, keyed by setting id.
     *
     * @var array<string, Repeater>
     */
    private array $repeaters = [];

    /**
     * Starts a section.
     *
     * @param string $id    Section id. It passes through `sanitize_key()`.
     * @param string $title Section title.
     */
    public function __construct(string $id, string $title)
    {
        $this->id    = self::key($id);
        $this->title = $title;

        if ($this->id === '' || $title === '') {
            $this->valid = false;
            Notices::error('A customizer section needs an id and a title.');
        }
    }

    /**
     * Sets the text shown inside the section.
     *
     * @param string $description Section description.
     *
     * @return static
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Sets the section sort order.
     *
     * @param int $priority Sort order. Lower numbers appear first.
     *
     * @return static
     */
    public function priority(int $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Places the section inside a panel.
     *
     * @param string $id Panel id.
     *
     * @return static
     */
    public function panel(string $id): static
    {
        $panel = self::key($id);

        if ($panel === '') {
            Notices::error('A customizer panel id cannot be empty.');

            return $this;
        }

        $this->panel = $panel;

        return $this;
    }

    /**
     * Stores this section's fields as options.
     *
     * Fields in a section without this call follow the customizer storage:
     * theme mods in a theme, options in a plugin, or the type passed to
     * {@see Customizer::storage()}.
     *
     * @return static
     */
    public function saveAsOption(): static
    {
        $this->saveAsOption = true;

        return $this;
    }

    /**
     * Starts a repeater and returns it so its fields can be chained.
     *
     * `end()` returns this section. The repeater is one setting. Its id is the
     * sanitized name, and the saved value is a list.
     *
     * @param string $name        Field name. It becomes the setting id.
     * @param string $label       Field label.
     * @param string $description Help text under the field.
     *
     * @return Repeater Fluent repeater.
     */
    public function repeater(string $name, string $label, string $description = ''): Repeater
    {
        if (!$this->valid) {
            return Repeater::inactive($this);
        }

        $repeater = new Repeater($this, true, $name, $label, $description);

        if (!$repeater->isValid()) {
            return $repeater;
        }

        if ($this->hasField($repeater->name())) {
            Notices::error(sprintf('Customizer setting "%s" is already registered.', $repeater->name()));

            return Repeater::inactive($this);
        }

        $this->repeaters[$repeater->name()] = $repeater;
        $this->fields[] = [
            'id'   => $repeater->name(),
            'type' => 'repeater',
        ];

        return $repeater;
    }

    /**
     * Adds a single-line text field.
     *
     * @param string $name        Field name. It becomes the setting id.
     * @param string $label       Field label.
     * @param string $description Help text under the field.
     * @param string $default     Value used until the setting is saved.
     *
     * @return static
     */
    public function addTextField(string $name, string $label, string $description = '', string $default = ''): static
    {
        return $this->addField('text', $name, $label, $description, $default);
    }

    /**
     * Adds a multiline text field.
     *
     * @param string $name        Field name. It becomes the setting id.
     * @param string $label       Field label.
     * @param string $description Help text under the field.
     * @param string $default     Value used until the setting is saved.
     *
     * @return static
     */
    public function addTextareaField(string $name, string $label, string $description = '', string $default = ''): static
    {
        return $this->addField('textarea', $name, $label, $description, $default);
    }

    /**
     * Adds a multiline field that keeps the HTML allowed in post content.
     *
     * @param string $name        Field name. It becomes the setting id.
     * @param string $label       Field label.
     * @param string $description Help text under the field.
     * @param string $default     Value used until the setting is saved.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/functions/wp_kses_post/
     */
    public function addHtmlField(string $name, string $label, string $description = '', string $default = ''): static
    {
        return $this->addField('html', $name, $label, $description, $default);
    }

    /**
     * Adds a numeric field.
     *
     * @param string    $name        Field name. It becomes the setting id.
     * @param string    $label       Field label.
     * @param string    $description Help text under the field.
     * @param int|float $default     Value used until the setting is saved.
     *
     * @return static
     */
    public function addNumberField(string $name, string $label, string $description = '', int|float $default = 0): static
    {
        return $this->addField('number', $name, $label, $description, $default);
    }

    /**
     * Adds a checkbox. The saved value is a boolean.
     *
     * @param string $name        Field name. It becomes the setting id.
     * @param string $label       Field label.
     * @param string $description Help text under the field.
     * @param bool   $default     Value used until the setting is saved.
     *
     * @return static
     */
    public function addCheckboxField(string $name, string $label, string $description = '', bool $default = false): static
    {
        return $this->addField('checkbox', $name, $label, $description, $default);
    }

    /**
     * Adds a select field. Only a key of `$options` can be saved.
     *
     * @param string                    $name        Field name. It becomes the setting id.
     * @param string                    $label       Field label.
     * @param array<int|string, mixed>  $options     Choices, with the stored value as the key.
     * @param string                    $description Help text under the field.
     * @param string                    $default     Value used until the setting is saved.
     *
     * @return static
     */
    public function addSelectField(
        string $name,
        string $label,
        array $options,
        string $description = '',
        string $default = ''
    ): static {
        return $this->addChoiceField('select', $name, $label, $options, $description, $default);
    }

    /**
     * Adds a radio field. Only a key of `$options` can be saved.
     *
     * @param string                    $name        Field name. It becomes the setting id.
     * @param string                    $label       Field label.
     * @param array<int|string, mixed>  $options     Choices, with the stored value as the key.
     * @param string                    $description Help text under the field.
     * @param string                    $default     Value used until the setting is saved.
     *
     * @return static
     */
    public function addRadioField(
        string $name,
        string $label,
        array $options,
        string $description = '',
        string $default = ''
    ): static {
        return $this->addChoiceField('radio', $name, $label, $options, $description, $default);
    }

    /**
     * Adds a color field. The saved value is a hex color.
     *
     * @param string $name        Field name. It becomes the setting id.
     * @param string $label       Field label.
     * @param string $description Help text under the field.
     * @param string $default     Hex color used until the setting is saved.
     *
     * @return static
     */
    public function addColorField(string $name, string $label, string $description = '', string $default = ''): static
    {
        return $this->addField('color', $name, $label, $description, $default);
    }

    /**
     * Adds an image field. The saved value is an attachment ID.
     *
     * @param string $name        Field name. It becomes the setting id.
     * @param string $label       Field label.
     * @param string $description Help text under the field.
     * @param int    $default     Attachment ID used until the setting is saved.
     *
     * @return static
     */
    public function addImageField(string $name, string $label, string $description = '', int $default = 0): static
    {
        return $this->addField('image', $name, $label, $description, max(0, $default));
    }

    /**
     * Adds a file field. The saved value is an attachment ID.
     *
     * @param string $name        Field name. It becomes the setting id.
     * @param string $label       Field label.
     * @param string $description Help text under the field.
     * @param int    $default     Attachment ID used until the setting is saved.
     *
     * @return static
     */
    public function addFileField(string $name, string $label, string $description = '', int $default = 0): static
    {
        return $this->addField('file', $name, $label, $description, max(0, $default));
    }

    /**
     * Returns the sanitized section id.
     *
     * @return string Section id.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Returns the section title.
     *
     * @return string Section title.
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * Reports whether the section can be registered.
     *
     * @return bool True when the id and the title are usable.
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Returns the section arguments and fields stored for registration.
     *
     * @return array{
     *     id: string,
     *     title: string,
     *     description: string,
     *     priority: int,
     *     panel: string,
     *     saveAsOption: bool,
     *     fields: list<array<string, mixed>>
     * } Section arguments.
     */
    public function export(): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'description'  => $this->description,
            'priority'     => $this->priority,
            'panel'        => $this->panel,
            'saveAsOption' => $this->saveAsOption,
            'fields'       => $this->exportedFields(),
        ];
    }

    /**
     * Returns the fields in declaration order, with repeater schemas filled in.
     *
     * @return list<array<string, mixed>> Fields ready for registration.
     */
    private function exportedFields(): array
    {
        $fields = [];

        foreach ($this->fields as $field) {
            if (($field['type'] ?? '') !== 'repeater') {
                $fields[] = $field;

                continue;
            }

            $repeater = $this->repeaters[$field['id']] ?? null;

            if ($repeater instanceof Repeater) {
                $fields[] = $repeater->field();
            }
        }

        return $fields;
    }

    /**
     * Reports whether a setting id is already used in this section.
     *
     * @param string $id Sanitized setting id.
     *
     * @return bool True when a field or a repeater uses the id.
     */
    private function hasField(string $id): bool
    {
        foreach ($this->fields as $field) {
            if (($field['id'] ?? '') === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stores a select or radio field.
     *
     * @param string                   $type        `select` or `radio`.
     * @param string                   $name        Field name.
     * @param string                   $label       Field label.
     * @param array<int|string, mixed> $options     Choices, with the stored value as the key.
     * @param string                   $description Help text under the field.
     * @param string                   $default     Value used until the setting is saved.
     *
     * @return static
     */
    private function addChoiceField(
        string $type,
        string $name,
        string $label,
        array $options,
        string $description,
        string $default
    ): static {
        $choices = [];

        foreach ($options as $value => $optionLabel) {
            $choices[(string) $value] = is_scalar($optionLabel) ? (string) $optionLabel : '';
        }

        $id = self::key($name);

        if ($choices === []) {
            Notices::error(sprintf('Customizer setting "%s" needs at least one choice.', $id === '' ? $name : $id));

            return $this;
        }

        if ($default !== '' && !array_key_exists($default, $choices)) {
            Notices::error(sprintf('Customizer setting "%s" has a default outside its choices.', $id === '' ? $name : $id));
            $default = '';
        }

        return $this->addField($type, $name, $label, $description, $default, $choices);
    }

    /**
     * Stores a field when its name and label can be registered.
     *
     * @param string                $type        Control type.
     * @param string                $name        Field name.
     * @param string                $label       Field label.
     * @param string                $description Help text under the field.
     * @param mixed                 $default     Value used until the setting is saved.
     * @param array<string, string> $choices     Select or radio choices.
     *
     * @return static
     */
    private function addField(
        string $type,
        string $name,
        string $label,
        string $description,
        mixed $default,
        array $choices = []
    ): static {
        if (!$this->valid) {
            return $this;
        }

        $id = self::key($name);

        if ($id === '' || $label === '') {
            Notices::error('A customizer field needs a name and a label.');

            return $this;
        }

        if ($this->hasField($id)) {
            Notices::error(sprintf('Customizer setting "%s" is already registered.', $id));

            return $this;
        }

        if ($type === 'color' && $default !== '') {
            $color = self::normalizeColor((string) $default);

            if ($color === '') {
                Notices::error(sprintf('Customizer setting "%s" has an invalid color default.', $id));
            }

            $default = $color;
        }

        $this->fields[] = [
            'id'          => $id,
            'type'        => $type,
            'label'       => $label,
            'description' => $description,
            'default'     => $default,
            'choices'     => $choices,
        ];

        return $this;
    }

    /**
     * Normalizes a hex color to `#rgb` or `#rrggbb`.
     *
     * @param string $value Raw color.
     *
     * @return string Hex color, or an empty string when the value is not a color.
     */
    private static function normalizeColor(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if ($value[0] !== '#') {
            $value = '#' . $value;
        }

        if (function_exists('sanitize_hex_color')) {
            $color = sanitize_hex_color($value);

            return is_string($color) ? $color : '';
        }

        if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) !== 1) {
            return '';
        }

        return strtolower($value);
    }

    /**
     * Sanitizes an id the same way WordPress sanitizes a key.
     *
     * @param string $value Raw id.
     *
     * @return string Sanitized id.
     */
    private static function key(string $value): string
    {
        if (function_exists('sanitize_key')) {
            return sanitize_key($value);
        }

        return strtolower($value);
    }
}
