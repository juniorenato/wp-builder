<?php

/**
 * Repeatable list inside a customizer section.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Customizer
 */

namespace WPB\Customizer;

use WPB\Notices;

/**
 * Declares the rows of one customizer setting.
 *
 * Create it from {@see Section::repeater()} or {@see Repeater::repeater()}.
 * Call {@see Repeater::end()} to return to the parent. The saved value is one
 * list on the root repeater. Nested lists live inside that list.
 *
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class Repeater
{
    /**
     * Section or parent repeater returned by `end()`.
     */
    private Section|Repeater $owner;

    /**
     * Whether this repeater is stored as its own setting.
     */
    private bool $root;

    /**
     * Sanitized field name.
     */
    private string $name;

    /**
     * Field label.
     */
    private string $label;

    /**
     * Help text under the field.
     */
    private string $description;

    /**
     * Minimum item count shown in the control.
     */
    private int $min = 0;

    /**
     * Maximum item count accepted when saving. Zero means no limit.
     */
    private int $max = 0;

    /**
     * Whether the repeater has a name and a label.
     */
    private bool $valid = true;

    /**
     * Whether `preview()` was called on this repeater.
     */
    private bool $previewRequested = false;

    /**
     * CSS selector replaced in the preview.
     */
    private string $previewSelector = '';

    /**
     * Callback that prints the preview region.
     *
     * @var string|array<int|string, mixed>|callable|null
     */
    private mixed $previewCallback = null;

    /**
     * Scalar fields, keyed by sanitized name.
     *
     * @var array<string, array{
     *     name: string,
     *     type: string,
     *     label: string,
     *     description: string,
     *     default: mixed,
     *     choices: array<string, string>
     * }>
     */
    private array $scalars = [];

    /**
     * Nested repeaters, keyed by sanitized name.
     *
     * @var array<string, Repeater>
     */
    private array $children = [];

    /**
     * Field names in declaration order.
     *
     * @var list<string>
     */
    private array $order = [];

    /**
     * Starts a repeater level.
     *
     * @param Section|Repeater $owner       Parent returned by `end()`.
     * @param bool             $root        True when this level is the saved setting.
     * @param string           $name        Field name.
     * @param string           $label       Field label.
     * @param string           $description Help text.
     */
    public function __construct(
        Section|Repeater $owner,
        bool $root,
        string $name,
        string $label,
        string $description = ''
    ) {
        $this->owner = $owner;
        $this->root = $root;
        $this->name = self::key($name);
        $this->label = $label;
        $this->description = $description;

        if ($this->name === '' || $label === '') {
            $this->valid = false;
            Notices::error('A customizer repeater needs a name and a label.');
        }
    }

    /**
     * Returns a repeater that ignores later declarations.
     *
     * @param Section|Repeater $owner Parent returned by `end()`.
     *
     * @return self Inactive repeater.
     */
    public static function inactive(Section|Repeater $owner): self
    {
        $repeater = new self($owner, $owner instanceof Section, 'inactive', 'inactive');
        $repeater->valid = false;
        $repeater->name = '';

        return $repeater;
    }

    /**
     * Returns the sanitized name.
     *
     * @return string Field name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Reports whether the repeater can be registered.
     *
     * @return bool True when the name and the label are usable.
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Sets how many empty rows the control shows.
     *
     * Saving fewer rows is allowed. A negative minimum, or a minimum above the
     * maximum, leaves the previous minimum in place.
     *
     * @param int $min Minimum count.
     *
     * @return static
     */
    public function min(int $min): static
    {
        if (!$this->valid) {
            return $this;
        }

        if ($min < 0) {
            Notices::error(sprintf('Repeater "%s" minimum cannot be negative.', $this->name));

            return $this;
        }

        if ($this->max > 0 && $min > $this->max) {
            Notices::error(sprintf('Repeater "%s" minimum cannot be greater than its maximum.', $this->name));

            return $this;
        }

        $this->min = $min;

        return $this;
    }

    /**
     * Sets how many rows can be saved.
     *
     * Zero removes the limit. A negative maximum, or a maximum below the
     * minimum, leaves the previous maximum in place.
     *
     * @param int $max Maximum count.
     *
     * @return static
     */
    public function max(int $max): static
    {
        if (!$this->valid) {
            return $this;
        }

        if ($max < 0) {
            Notices::error(sprintf('Repeater "%s" maximum cannot be negative.', $this->name));

            return $this;
        }

        if ($max > 0 && $max < $this->min) {
            Notices::error(sprintf('Repeater "%s" minimum cannot be greater than its maximum.', $this->name));

            return $this;
        }

        $this->max = $max;

        return $this;
    }

    /**
     * Adds a single-line text field.
     *
     * @param string $name        Field name.
     * @param string $label       Field label.
     * @param string $description Help text under the field.
     * @param string $default     Value used for a new row.
     *
     * @return static
     */
    public function addTextField(string $name, string $label, string $description = '', string $default = ''): static
    {
        return $this->addScalar('text', $name, $label, $description, $default);
    }

    /**
     * Adds a multiline text field.
     *
     * @param string $name        Field name.
     * @param string $label       Field label.
     * @param string $description Help text under the field.
     * @param string $default     Value used for a new row.
     *
     * @return static
     */
    public function addTextareaField(string $name, string $label, string $description = '', string $default = ''): static
    {
        return $this->addScalar('textarea', $name, $label, $description, $default);
    }

    /**
     * Adds a numeric field.
     *
     * @param string    $name        Field name.
     * @param string    $label       Field label.
     * @param string    $description Help text under the field.
     * @param int|float $default     Value used when the submitted number is not numeric.
     *
     * @return static
     */
    public function addNumberField(string $name, string $label, string $description = '', int|float $default = 0): static
    {
        return $this->addScalar('number', $name, $label, $description, $default);
    }

    /**
     * Adds a checkbox. The saved value is a boolean.
     *
     * @param string $name        Field name.
     * @param string $label       Field label.
     * @param string $description Help text under the field.
     * @param bool   $default     Value used for a new row.
     *
     * @return static
     */
    public function addCheckboxField(string $name, string $label, string $description = '', bool $default = false): static
    {
        return $this->addScalar('checkbox', $name, $label, $description, $default);
    }

    /**
     * Adds a select field. Only a key of `$options` can be saved.
     *
     * @param string                   $name        Field name.
     * @param string                   $label       Field label.
     * @param array<int|string, mixed> $options     Choices, with the stored value as the key.
     * @param string                   $description Help text under the field.
     * @param string                   $default     Value used for a new row.
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
     * @param string                   $name        Field name.
     * @param string                   $label       Field label.
     * @param array<int|string, mixed> $options     Choices, with the stored value as the key.
     * @param string                   $description Help text under the field.
     * @param string                   $default     Value used for a new row.
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
     * @param string $name        Field name.
     * @param string $label       Field label.
     * @param string $description Help text under the field.
     * @param string $default     Hex color used for a new row.
     *
     * @return static
     */
    public function addColorField(string $name, string $label, string $description = '', string $default = ''): static
    {
        return $this->addScalar('color', $name, $label, $description, $default);
    }

    /**
     * Adds an image field. The saved value is an attachment ID.
     *
     * @param string $name        Field name.
     * @param string $label       Field label.
     * @param string $description Help text under the field.
     * @param int    $default     Attachment ID used for a new row.
     *
     * @return static
     */
    public function addImageField(string $name, string $label, string $description = '', int $default = 0): static
    {
        return $this->addScalar('image', $name, $label, $description, max(0, $default));
    }

    /**
     * Adds a file field. The saved value is an attachment ID.
     *
     * @param string $name        Field name.
     * @param string $label       Field label.
     * @param string $description Help text under the field.
     * @param int    $default     Attachment ID used for a new row.
     *
     * @return static
     */
    public function addFileField(string $name, string $label, string $description = '', int $default = 0): static
    {
        return $this->addScalar('file', $name, $label, $description, max(0, $default));
    }

    /**
     * Refuses a rich text field.
     *
     * The editor cannot be cloned into a new row. The declaration continues
     * and the field is not added.
     *
     * @param string               $name           Unused field name.
     * @param string               $label          Unused field label.
     * @param array<string, mixed> $editorSettings Unused editor settings.
     * @param string               $description    Unused description.
     * @param array<string, mixed> $attrs          Unused HTML attributes.
     *
     * @return static
     */
    public function addRichTextField(
        string $name,
        string $label,
        array $editorSettings = [],
        string $description = '',
        array $attrs = []
    ): static {
        unset($name, $label, $editorSettings, $description, $attrs);

        Notices::error(sprintf(
            'Repeater "%s" cannot contain a rich text field. Use a textarea.',
            $this->name === '' ? 'repeater' : $this->name
        ));

        return $this;
    }

    /**
     * Opens a nested repeater.
     *
     * @param string $name        Field name.
     * @param string $label       Field label.
     * @param string $description Help text.
     *
     * @return Repeater Nested repeater. `end()` returns this repeater.
     */
    public function repeater(string $name, string $label, string $description = ''): Repeater
    {
        $child = new Repeater($this, false, $name, $label, $description);

        if (!$this->valid || !$child->isValid()) {
            return $child;
        }

        if ($this->hasName($child->name())) {
            Notices::error(sprintf('Repeater "%s" already has a field "%s".', $this->name, $child->name()));

            return self::inactive($this);
        }

        $this->children[$child->name()] = $child;
        $this->order[] = $child->name();

        return $child;
    }

    /**
     * Marks the preview region printed by `$callback`.
     *
     * The callback must print the element matched by `$selector`. Nested
     * repeaters are saved inside the root list, so preview is ignored there.
     * An empty selector or a missing callback is reported when the customizer
     * registers and the preview reloads the whole frame.
     *
     * @param string                            $selector CSS selector in the preview.
     * @param string|array<int|string, mixed>|callable $callback  Render callback.
     *
     * @return static
     */
    public function preview(string $selector, string|array|callable $callback): static
    {
        if (!$this->valid) {
            return $this;
        }

        if (!$this->root) {
            Notices::error(sprintf(
                'Repeater "%s" preview applies to the saved list. Call it on the root repeater.',
                $this->name
            ));

            return $this;
        }

        $this->previewRequested = true;
        $this->previewSelector = $selector;
        $this->previewCallback = $callback;

        return $this;
    }

    /**
     * Returns to the section or the parent repeater.
     *
     * @return Section|Repeater Parent that declared this repeater.
     */
    public function end(): Section|Repeater
    {
        return $this->owner;
    }

    /**
     * Returns the setting field stored by the section.
     *
     * @return array{
     *     id: string,
     *     type: string,
     *     label: string,
     *     description: string,
     *     default: array{},
     *     choices: array{},
     *     schema: array<string, mixed>,
     *     preview: array{requested: bool, selector: string, callback: mixed}
     * } Field configuration.
     */
    public function field(): array
    {
        return [
            'id'          => $this->name,
            'type'        => 'repeater',
            'label'       => $this->label,
            'description' => $this->description,
            'default'     => [],
            'choices'     => [],
            'schema'      => $this->schema(),
            'preview'     => [
                'requested' => $this->previewRequested,
                'selector'  => $this->previewSelector,
                'callback'  => $this->previewCallback,
            ],
        ];
    }

    /**
     * Returns the schema used to render and sanitize this level.
     *
     * @return array<string, mixed> Repeater schema.
     */
    public function schema(): array
    {
        return [
            'name'        => $this->name,
            'type'        => 'repeater',
            'label'       => $this->label,
            'description' => $this->description,
            'min'         => $this->min,
            'max'         => $this->max,
            'fields'      => $this->fieldSchemas(),
        ];
    }

    /**
     * Returns stored rows padded to the minimum shown in the control.
     *
     * @param array<string, mixed> $schema Repeater schema.
     * @param mixed                $stored Saved value.
     *
     * @return list<array<string, mixed>> Rows to render.
     */
    public static function rows(array $schema, mixed $stored): array
    {
        $items = [];

        if (is_array($stored)) {
            foreach ($stored as $index => $row) {
                if ($index === '__present' || $index === '__i__' || !is_array($row)) {
                    continue;
                }

                $items[] = $row;
            }
        }

        $min = max(0, (int) ($schema['min'] ?? 0));

        while (count($items) < $min) {
            $items[] = [];
        }

        return $items;
    }

    /**
     * Sanitizes a submitted list into the declared fields.
     *
     * @param array<string, mixed> $schema Repeater schema.
     * @param mixed                $value  Submitted rows, or a JSON string.
     *
     * @return list<array<string, mixed>> Sanitized rows.
     */
    public static function sanitize(array $schema, mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $index => $row) {
            if ($index === '__present' || $index === '__i__' || !is_array($row)) {
                continue;
            }

            $clean = [];

            foreach ($schema['fields'] ?? [] as $child) {
                if (!is_array($child) || !isset($child['name'])) {
                    continue;
                }

                $childName = (string) $child['name'];
                $submitted = array_key_exists($childName, $row) ? $row[$childName] : self::emptyValue($child);
                $clean[$childName] = self::sanitizeValue($child, $submitted);
            }

            $items[] = $clean;
        }

        $max = (int) ($schema['max'] ?? 0);

        if ($max > 0) {
            $items = array_slice($items, 0, $max);
        }

        return array_values($items);
    }

    /**
     * Stores a select or radio field.
     *
     * @param string                   $type        `select` or `radio`.
     * @param string                   $name        Field name.
     * @param string                   $label       Field label.
     * @param array<int|string, mixed> $options     Choices, with the stored value as the key.
     * @param string                   $description Help text under the field.
     * @param string                   $default     Value used for a new row.
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

        return $this->addScalar($type, $name, $label, $description, $default, $choices);
    }

    /**
     * Stores a scalar field when its name and label can be used.
     *
     * @param string                $type        Field type.
     * @param string                $name        Field name.
     * @param string                $label       Field label.
     * @param string                $description Help text under the field.
     * @param mixed                 $default     Value used for a new row.
     * @param array<string, string> $choices     Select or radio choices.
     *
     * @return static
     */
    private function addScalar(
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

        if ($this->hasName($id)) {
            Notices::error(sprintf('Repeater "%s" already has a field "%s".', $this->name, $id));

            return $this;
        }

        if (($type === 'select' || $type === 'radio') && $choices === []) {
            Notices::error(sprintf('Repeater "%s" field "%s" needs at least one choice.', $this->name, $id));

            return $this;
        }

        if (($type === 'select' || $type === 'radio') && $default !== '' && !array_key_exists($default, $choices)) {
            Notices::error(sprintf('Repeater "%s" field "%s" has a default outside its choices.', $this->name, $id));
            $default = '';
        }

        if ($type === 'color' && is_string($default) && $default !== '') {
            $color = self::normalizeColor($default);

            if ($color === '') {
                Notices::error(sprintf('Repeater "%s" field "%s" has an invalid color default.', $this->name, $id));
            }

            $default = $color;
        }

        $this->scalars[$id] = [
            'name'        => $id,
            'type'        => $type,
            'label'       => $label,
            'description' => $description,
            'default'     => $default,
            'choices'     => $choices,
        ];
        $this->order[] = $id;

        return $this;
    }

    /**
     * Reports whether a name is already used at this level.
     *
     * @param string $name Sanitized field name.
     *
     * @return bool True when a scalar or a nested repeater uses the name.
     */
    private function hasName(string $name): bool
    {
        return isset($this->scalars[$name]) || isset($this->children[$name]);
    }

    /**
     * Returns the field schemas in declaration order.
     *
     * @return list<array<string, mixed>> Field schemas.
     */
    private function fieldSchemas(): array
    {
        $fields = [];

        foreach ($this->order as $name) {
            if (isset($this->children[$name])) {
                $fields[] = $this->children[$name]->schema();

                continue;
            }

            if (isset($this->scalars[$name])) {
                $fields[] = $this->scalars[$name];
            }
        }

        return $fields;
    }

    /**
     * Returns the value used when a row omits a field.
     *
     * @param array<string, mixed> $field Field schema.
     *
     * @return mixed Empty value for the field type.
     */
    private static function emptyValue(array $field): mixed
    {
        return match ($field['type'] ?? 'text') {
            'number', 'image', 'file' => 0,
            'checkbox' => false,
            'repeater' => [],
            default => '',
        };
    }

    /**
     * Sanitizes one cell.
     *
     * @param array<string, mixed> $field Field schema.
     * @param mixed                $value Submitted value.
     *
     * @return mixed Sanitized value.
     */
    private static function sanitizeValue(array $field, mixed $value): mixed
    {
        $type = (string) ($field['type'] ?? 'text');

        return match ($type) {
            'textarea' => self::text($value, true),
            'number' => self::number($value, $field['default'] ?? 0),
            'checkbox' => self::boolean($value),
            'select', 'radio' => self::choice($value, $field),
            'color' => self::normalizeColor(is_scalar($value) ? (string) $value : ''),
            'image', 'file' => self::attachment($value),
            'repeater' => self::sanitize($field, $value),
            default => self::text($value, false),
        };
    }

    /**
     * Sanitizes a text cell.
     *
     * @param mixed $value    Submitted value.
     * @param bool  $textarea True when newlines are kept.
     *
     * @return string Sanitized text.
     */
    private static function text(mixed $value, bool $textarea): string
    {
        $text = is_scalar($value) ? (string) $value : '';

        if ($textarea && function_exists('sanitize_textarea_field')) {
            return sanitize_textarea_field($text);
        }

        if (!$textarea && function_exists('sanitize_text_field')) {
            return sanitize_text_field($text);
        }

        $text = strip_tags($text);

        if (!$textarea) {
            $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        }

        return trim($text);
    }

    /**
     * Sanitizes a number cell.
     *
     * A missing number arrives as `0`. Any other non-numeric value returns the
     * field default.
     *
     * @param mixed $value   Submitted value.
     * @param mixed $default Declared default.
     *
     * @return int|float Sanitized number.
     */
    private static function number(mixed $value, mixed $default): int|float
    {
        $fallback = is_int($default) || is_float($default) ? $default : 0;

        if ($value === 0 || $value === 0.0) {
            return 0 + $value;
        }

        if (!is_numeric($value)) {
            return $fallback;
        }

        return 0 + $value;
    }

    /**
     * Sanitizes a checkbox cell.
     *
     * @param mixed $value Submitted value.
     *
     * @return bool Sanitized boolean.
     */
    private static function boolean(mixed $value): bool
    {
        if (function_exists('rest_sanitize_boolean')) {
            return rest_sanitize_boolean($value);
        }

        if (is_bool($value)) {
            return $value;
        }

        $text = is_scalar($value) ? strtolower((string) $value) : '';

        return in_array($text, ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * Keeps a choice that belongs to the field.
     *
     * @param mixed                $value Submitted value.
     * @param array<string, mixed> $field Field schema.
     *
     * @return string Stored choice, or the default when the value is outside the list.
     */
    private static function choice(mixed $value, array $field): string
    {
        $choices = is_array($field['choices'] ?? null) ? $field['choices'] : [];
        $selected = is_scalar($value) ? (string) $value : '';
        $default = is_scalar($field['default'] ?? null) ? (string) $field['default'] : '';

        return array_key_exists($selected, $choices) ? $selected : $default;
    }

    /**
     * Reduces an attachment cell to an ID.
     *
     * @param mixed $value Submitted value.
     *
     * @return int Attachment ID, or zero when the cell is empty.
     */
    private static function attachment(mixed $value): int
    {
        if (is_array($value)) {
            return 0;
        }

        return function_exists('absint') ? absint($value) : max(0, (int) $value);
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
