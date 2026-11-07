<?php

/**
 * Repeatable field group for an admin form.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Forms
 */

namespace WPB\Forms;

use InvalidArgumentException;

/**
 * Collects the fields of one repeater level on a meta box, term, or admin page.
 *
 * Create it from {@see AdminForm::repeater()} or {@see Repeater::repeater()}.
 * Call {@see Repeater::end()} to return to the parent.
 *
 * @since  0.9.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class Repeater
{
    /**
     * Form that stores the root repeater.
     *
     * @since 0.9.0
     */
    private object $owner;

    /**
     * Parent repeater, or null at the form root.
     *
     * @since 0.9.0
     */
    private ?Repeater $parent;

    /**
     * Field name.
     *
     * @since 0.9.0
     */
    private string $name;

    /**
     * Field label.
     *
     * @since 0.9.0
     */
    private string $label;

    /**
     * Field description.
     *
     * @since 0.9.0
     */
    private string $description;

    /**
     * Minimum item count shown in the form.
     *
     * @since 0.9.0
     */
    private int $min = 0;

    /**
     * Maximum item count accepted by the form. Zero means no limit.
     *
     * @since 0.9.0
     */
    private int $max = 0;

    /**
     * Scalar fields, keyed by name.
     *
     * @since 0.9.0
     *
     * @var array<string, array<string, mixed>>
     */
    private array $scalars = [];

    /**
     * Nested repeaters, keyed by name.
     *
     * @since 0.9.0
     *
     * @var array<string, Repeater>
     */
    private array $children = [];

    /**
     * Field names in declaration order.
     *
     * @since 0.9.0
     *
     * @var list<string>
     */
    private array $order = [];

    /**
     * Creates a repeater level.
     *
     * @since 0.9.0
     *
     * @param object        $owner       Form that owns the root repeater.
     * @param Repeater|null $parent      Parent repeater, or null at the root.
     * @param string        $name        Field name.
     * @param string        $label       Field label.
     * @param string        $description Field description.
     */
    public function __construct(
        object $owner,
        ?Repeater $parent,
        string $name,
        string $label,
        string $description = ''
    ) {
        $this->owner = $owner;
        $this->parent = $parent;
        $this->name = $name;
        $this->label = $label;
        $this->description = $description;
    }

    /**
     * Rejects a name that cannot be used in a request key.
     *
     * @since 0.9.0
     *
     * @param string $name Field name.
     *
     * @throws InvalidArgumentException If the name is empty or contains other characters.
     */
    public static function assertName(string $name): void
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
            throw new InvalidArgumentException(sprintf(
                'Field name "%s" must contain only letters, numbers, hyphens, and underscores.',
                $name
            ));
        }
    }

    /**
     * Returns the field name.
     *
     * @since 0.9.0
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Sets the minimum number of items the form shows.
     *
     * Saving fewer items is allowed. The next edit screen fills the list back up.
     *
     * @since 0.9.0
     *
     * @param int $min Minimum count.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the minimum is negative or greater than the maximum.
     */
    public function min(int $min): static
    {
        if ($min < 0) {
            throw new InvalidArgumentException(sprintf(
                'Repeater "%s" minimum cannot be negative.',
                $this->name
            ));
        }

        $this->min = $min;
        $this->assertBounds();

        return $this;
    }

    /**
     * Sets the maximum number of items the form accepts.
     *
     * Zero removes the limit. Extra submitted items are discarded.
     *
     * @since 0.9.0
     *
     * @param int $max Maximum count.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the maximum is negative or lower than the minimum.
     */
    public function max(int $max): static
    {
        if ($max < 0) {
            throw new InvalidArgumentException(sprintf(
                'Repeater "%s" maximum cannot be negative.',
                $this->name
            ));
        }

        $this->max = $max;
        $this->assertBounds();

        return $this;
    }

    /**
     * Adds a text field.
     *
     * @since 0.9.0
     *
     * @param string               $name        Field name.
     * @param string               $label       Field label.
     * @param string               $description Field description.
     * @param string               $class       Extra CSS classes.
     * @param array<string, mixed> $attrs       Extra HTML attributes.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the name is already used at this level.
     */
    public function addTextField(
        string $name,
        string $label,
        string $description = '',
        string $class = '',
        array $attrs = []
    ): static {
        return $this->addScalar($this->owner->defineTextField($name, $label, $description, $class, $attrs));
    }

    /**
     * Adds a textarea field.
     *
     * @since 0.9.0
     *
     * @param string               $name        Field name.
     * @param string               $label       Field label.
     * @param string               $description Field description.
     * @param string               $class       Extra CSS classes.
     * @param array<string, mixed> $attrs       Extra HTML attributes.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the name is already used at this level.
     */
    public function addTextareaField(
        string $name,
        string $label,
        string $description = '',
        string $class = '',
        array $attrs = []
    ): static {
        return $this->addScalar($this->owner->defineTextareaField($name, $label, $description, $class, $attrs));
    }

    /**
     * Adds a number field.
     *
     * @since 0.9.0
     *
     * @param string               $name        Field name.
     * @param string               $label       Field label.
     * @param string               $description Field description.
     * @param string               $class       Extra CSS classes.
     * @param array<string, mixed> $attrs       Extra HTML attributes.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the name is already used at this level.
     */
    public function addNumberField(
        string $name,
        string $label,
        string $description = '',
        string $class = '',
        array $attrs = []
    ): static {
        return $this->addScalar($this->owner->defineNumberField($name, $label, $description, $class, $attrs));
    }

    /**
     * Adds a select field.
     *
     * @since 0.9.0
     *
     * @param string                   $name        Field name.
     * @param string                   $label       Field label.
     * @param array<string|int, mixed> $options     Select options.
     * @param bool                     $multiple    Whether multiple values are allowed.
     * @param string                   $description Field description.
     * @param string                   $class       Extra CSS classes.
     * @param array<string, mixed>     $attrs       Extra HTML attributes.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the name is already used at this level.
     */
    public function addSelectField(
        string $name,
        string $label,
        array $options,
        bool $multiple = false,
        string $description = '',
        string $class = '',
        array $attrs = []
    ): static {
        return $this->addScalar($this->owner->defineSelectField(
            $name,
            $label,
            $options,
            $multiple,
            $description,
            $class,
            $attrs
        ));
    }

    /**
     * Adds a radio field.
     *
     * @since 0.9.0
     *
     * @param string                   $name        Field name.
     * @param string                   $label       Field label.
     * @param array<string|int, mixed> $options     Radio options.
     * @param string                   $description Field description.
     * @param string                   $class       Extra CSS classes.
     * @param array<string, mixed>     $attrs       Extra HTML attributes.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the name is already used at this level.
     */
    public function addRadioField(
        string $name,
        string $label,
        array $options,
        string $description = '',
        string $class = '',
        array $attrs = []
    ): static {
        return $this->addScalar($this->owner->defineRadioField(
            $name,
            $label,
            $options,
            $description,
            $class,
            $attrs
        ));
    }

    /**
     * Adds a checkbox field.
     *
     * @since 0.9.0
     *
     * @param string                   $name        Field name.
     * @param string                   $label       Field label.
     * @param array<string|int, mixed> $options     Checkbox options.
     * @param bool                     $multiple    Whether multiple values are allowed.
     * @param string                   $description Field description.
     * @param string                   $class       Extra CSS classes.
     * @param array<string, mixed>     $attrs       Extra HTML attributes.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the name is already used at this level.
     */
    public function addCheckboxField(
        string $name,
        string $label,
        array $options = [],
        bool $multiple = true,
        string $description = '',
        string $class = '',
        array $attrs = []
    ): static {
        return $this->addScalar($this->owner->defineCheckboxField(
            $name,
            $label,
            $options,
            $multiple,
            $description,
            $class,
            $attrs
        ));
    }

    /**
     * Adds an image field stored as a media library attachment ID.
     *
     * @since 0.9.0
     *
     * @param string               $name        Field name.
     * @param string               $label       Field label.
     * @param string               $description Field description.
     * @param string               $class       Extra CSS classes.
     * @param array<string, mixed> $attrs       Extra HTML attributes.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the name is already used at this level.
     */
    public function addImageField(
        string $name,
        string $label,
        string $description = '',
        string $class = '',
        array $attrs = []
    ): static {
        return $this->addScalar($this->owner->defineImageField($name, $label, $description, $class, $attrs));
    }

    /**
     * Rejects a rich text field.
     *
     * `wp_editor()` cannot be cloned into a new row.
     *
     * @since 0.9.0
     *
     * @param string               $name           Field name.
     * @param string               $label          Field label.
     * @param array<string, mixed> $editorSettings Unused editor settings.
     * @param string               $description    Unused description.
     * @param array<string, mixed> $attrs          Unused HTML attributes.
     *
     * @throws InvalidArgumentException Always.
     */
    public function addRichTextField(
        string $name,
        string $label,
        array $editorSettings = [],
        string $description = '',
        array $attrs = []
    ): static {
        unset($name, $label, $editorSettings, $description, $attrs);

        throw new InvalidArgumentException(sprintf(
            'Repeater "%s" cannot contain a rich text field. Use a textarea.',
            $this->name
        ));
    }

    /**
     * Opens a nested repeater.
     *
     * @since 0.9.0
     *
     * @param string $name        Field name.
     * @param string $label       Field label.
     * @param string $description Field description.
     *
     * @return Repeater Nested repeater.
     *
     * @throws InvalidArgumentException If the name is already used at this level.
     */
    public function repeater(string $name, string $label, string $description = ''): Repeater
    {
        $this->assertSiblingName($name);

        $child = new Repeater($this->owner, $this, $name, $label, $description);
        $this->children[$name] = $child;
        $this->order[] = $name;

        return $child;
    }

    /**
     * Returns to the parent repeater, or to the form at the root.
     *
     * @since 0.9.0
     *
     * @return object Parent repeater or the owning form.
     */
    public function end(): object
    {
        return $this->parent ?? $this->owner;
    }

    /**
     * Returns the schema used to render and sanitize this level.
     *
     * @since 0.9.0
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
     * Returns stored rows padded to the minimum shown in the form.
     *
     * @since 0.9.0
     *
     * @param mixed $stored Saved value.
     *
     * @return list<array<string, mixed>> Rows to render.
     */
    public function rowsForForm(mixed $stored): array
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

        while (count($items) < $this->min) {
            $items[] = [];
        }

        return $items;
    }

    /**
     * Returns the nested repeater with the given name.
     *
     * @since 0.9.0
     *
     * @param string $name Child field name.
     *
     * @return Repeater Nested repeater.
     *
     * @throws InvalidArgumentException If the child does not exist.
     */
    public function child(string $name): Repeater
    {
        if (!isset($this->children[$name])) {
            throw new InvalidArgumentException(sprintf(
                'Repeater "%s" has no nested repeater "%s".',
                $this->name,
                $name
            ));
        }

        return $this->children[$name];
    }

    /**
     * Returns the field schemas in declaration order.
     *
     * @since 0.9.0
     *
     * @return list<array<string, mixed>> Field schemas.
     */
    public function fieldSchemas(): array
    {
        $fields = [];

        foreach ($this->order as $name) {
            if (isset($this->children[$name])) {
                $fields[] = $this->children[$name]->schema();
                continue;
            }

            $fields[] = $this->scalars[$name];
        }

        return $fields;
    }

    /**
     * Stores a scalar field.
     *
     * @since 0.9.0
     *
     * @param array<string, mixed> $field Field configuration.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the name is already used at this level.
     */
    private function addScalar(array $field): static
    {
        $name = (string) ($field['name'] ?? '');
        $this->assertSiblingName($name);
        $this->scalars[$name] = $field;
        $this->order[] = $name;

        return $this;
    }

    /**
     * Rejects a field name that is invalid or already used on this level.
     *
     * @since 0.9.0
     *
     * @param string $name Field name.
     *
     * @throws InvalidArgumentException If the name cannot be used.
     */
    private function assertSiblingName(string $name): void
    {
        self::assertName($name);

        if (isset($this->scalars[$name]) || isset($this->children[$name])) {
            throw new InvalidArgumentException(sprintf(
                'Field name "%s" is already used on repeater "%s".',
                $name,
                $this->name
            ));
        }
    }

    /**
     * Rejects a minimum greater than a positive maximum.
     *
     * @since 0.9.0
     *
     * @throws InvalidArgumentException If the bounds are impossible.
     */
    private function assertBounds(): void
    {
        if ($this->max > 0 && $this->min > $this->max) {
            throw new InvalidArgumentException(sprintf(
                'Repeater "%s" minimum (%d) cannot be greater than maximum (%d).',
                $this->name,
                $this->min,
                $this->max
            ));
        }
    }
}
