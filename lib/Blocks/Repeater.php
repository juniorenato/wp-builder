<?php

/**
 * Repeatable field group for a Gutenberg block.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Blocks
 */

namespace WPB\Blocks;

use WPB\Builder;
use WPB\Notices;

/**
 * Collects the fields of one repeater level and renders its items.
 *
 * Create it from {@see Block::repeater()} or {@see Repeater::repeater()}.
 *
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class Repeater
{
    /**
     * Block that owns this repeater.
     */
    private Block $block;

    /**
     * Parent repeater, or null at the block root.
     */
    private ?Repeater $parent;

    /**
     * Field name.
     */
    private string $name;

    /**
     * Inspector label.
     */
    private string $label;

    /**
     * Minimum item count enforced in the editor.
     */
    private int $min = 0;

    /**
     * Maximum item count enforced in the editor. Zero means no limit.
     */
    private int $max = 0;

    /**
     * Scalar fields, keyed by name.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $scalars = [];

    /**
     * Nested repeaters, keyed by name.
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
     * Items currently being rendered.
     *
     * @var list<array<string, mixed>>
     */
    private array $items = [];

    /**
     * Field passed to a partial view.
     *
     * @var array<string, mixed>
     */
    private array $field = [];

    /**
     * Value passed to a partial view.
     */
    private mixed $value = null;

    /**
     * Creates a repeater level.
     *
     * @param Block         $block  Owning block.
     * @param Repeater|null $parent Parent repeater, or null at the root.
     * @param string        $name   Field name.
     * @param string        $label  Inspector label.
     */
    public function __construct(Block $block, ?Repeater $parent, string $name, string $label)
    {
        $this->block = $block;
        $this->parent = $parent;
        $this->name = $name;
        $this->label = $label;
    }

    /**
     * Returns the field name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Sets the minimum number of items the editor allows.
     *
     * @param int $min Minimum count.
     *
     * @return static
     *
     * A negative minimum, or a minimum above the maximum, is ignored and reported in the WordPress admin.
     */
    public function min(int $min): static
    {
        if ($min < 0) {
            Notices::error(sprintf(
                'Repeater "%s" minimum cannot be negative.',
                $this->name
            ));

            return $this;
        }

        $previous = $this->min;
        $this->min = $min;

        if (!$this->assertBounds()) {
            $this->min = $previous;
        }

        return $this;
    }

    /**
     * Sets the maximum number of items the editor allows.
     *
     * Zero removes the limit.
     *
     * @param int $max Maximum count.
     *
     * @return static
     *
     * A negative maximum, or a maximum below the minimum, is ignored and reported in the WordPress admin.
     */
    public function max(int $max): static
    {
        if ($max < 0) {
            Notices::error(sprintf(
                'Repeater "%s" maximum cannot be negative.',
                $this->name
            ));

            return $this;
        }

        $previous = $this->max;
        $this->max = $max;

        if (!$this->assertBounds()) {
            $this->max = $previous;
        }

        return $this;
    }

    /**
     * Adds a string field.
     *
     * @param string $name    Field name.
     * @param string $label   Inspector label.
     * @param string $default Default value.
     *
     * @return static
     *
     * An unusable name is ignored and reported in the WordPress admin.
     */
    public function addStringAttribute(string $name, string $label, string $default = ''): static
    {
        return $this->addScalar($name, [
            'name'    => $name,
            'type'    => 'string',
            'label'   => $label,
            'default' => $default,
        ]);
    }

    /**
     * Adds an integer field.
     *
     * @param string $name    Field name.
     * @param string $label   Inspector label.
     * @param int    $default Default value.
     *
     * @return static
     *
     * An unusable name is ignored and reported in the WordPress admin.
     */
    public function addIntegerAttribute(string $name, string $label, int $default = 0): static
    {
        return $this->addScalar($name, [
            'name'    => $name,
            'type'    => 'integer',
            'label'   => $label,
            'default' => $default,
        ]);
    }

    /**
     * Adds a boolean field.
     *
     * A true value adds `wpb-repeater__item--{name}` to the item.
     *
     * @param string $name    Field name.
     * @param string $label   Inspector label.
     * @param bool   $default Default value.
     *
     * @return static
     *
     * An unusable name is ignored and reported in the WordPress admin.
     */
    public function addBooleanAttribute(string $name, string $label, bool $default = false): static
    {
        return $this->addScalar($name, [
            'name'    => $name,
            'type'    => 'boolean',
            'label'   => $label,
            'default' => $default,
        ]);
    }

    /**
     * Adds a string field with a fixed set of choices.
     *
     * @param string       $name    Field name.
     * @param string       $label   Inspector label.
     * @param list<string> $enum    Allowed values.
     * @param string|null  $default Default value, or the first enum value.
     *
     * @return static
     *
     * An empty enum or an unusable name is ignored and reported in the WordPress admin.
     */
    public function addEnumAttribute(string $name, string $label, array $enum, ?string $default = null): static
    {
        $enum = array_values($enum);

        if ($enum === []) {
            Notices::error(sprintf(
                'Enum attribute "%s" requires at least one value.',
                $name
            ));

            return $this;
        }

        return $this->addScalar($name, [
            'name'    => $name,
            'type'    => 'enum',
            'label'   => $label,
            'enum'    => $enum,
            'default' => $default ?? $enum[0],
        ]);
    }

    /**
     * Adds an image field stored as an attachment ID.
     *
     * An empty image is stored as `0`.
     *
     * @param string $name  Field name.
     * @param string $label Inspector label.
     *
     * @return static
     *
     * An unusable name is ignored and reported in the WordPress admin.
     */
    public function addImageAttribute(string $name, string $label): static
    {
        return $this->addScalar($name, [
            'name'    => $name,
            'type'    => 'image',
            'label'   => $label,
            'default' => 0,
        ]);
    }

    /**
     * Opens a nested repeater.
     *
     * @param string $name  Field name.
     * @param string $label Inspector label.
     *
     * @return Repeater Nested repeater.
     *
     * An invalid name returns a repeater that is not registered at this level.
     * The message is reported in the WordPress admin.
     */
    public function repeater(string $name, string $label): Repeater
    {
        if (!$this->assertSiblingName($name)) {
            return new Repeater($this->block, $this, $name, $label);
        }

        $child = new Repeater($this->block, $this, $name, $label);
        $this->children[$name] = $child;
        $this->order[] = $name;

        return $child;
    }

    /**
     * Returns to the parent repeater, or to the block at the root.
     *
     * @return Block|Repeater Parent builder.
     */
    public function end(): Block|Repeater
    {
        return $this->parent ?? $this->block;
    }

    /**
     * Returns the schema shared by the editor, the meta REST schema, and normalization.
     *
     * @return array<string, mixed> Repeater schema.
     */
    public function schema(): array
    {
        return [
            'name'   => $this->name,
            'type'   => 'repeater',
            'label'  => $this->label,
            'min'    => $this->min,
            'max'    => $this->max,
            'fields' => $this->fieldSchemas(),
        ];
    }

    /**
     * Renders the repeater items.
     *
     * @param list<array<string, mixed>> $items Normalized items.
     *
     * @return string Repeater HTML.
     */
    public function renderItems(array $items): string
    {
        $this->items = $items;

        ob_start();
        require Builder::PATH['BLOCK'] . 'repeater.php';

        return (string) ob_get_clean();
    }

    /**
     * Returns the nested repeater with the given name.
     *
     * @param string $name Child field name.
     *
     * @return Repeater Nested repeater.
     *
     * A missing child returns an unregistered repeater. The message is reported in the WordPress admin.
     */
    public function child(string $name): Repeater
    {
        if (!isset($this->children[$name])) {
            Notices::error(sprintf(
                'Repeater "%s" has no nested repeater "%s".',
                $this->name,
                $name
            ));

            return new Repeater($this->block, $this, $name, '');
        }

        return $this->children[$name];
    }

    /**
     * Returns the field schemas in declaration order.
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
     * @param string               $name   Field name.
     * @param array<string, mixed> $schema Field schema.
     *
     * @return static
     *
     * An unusable name is ignored and reported in the WordPress admin.
     */
    private function addScalar(string $name, array $schema): static
    {
        if (!$this->assertSiblingName($name)) {
            return $this;
        }

        $this->scalars[$name] = $schema;
        $this->order[] = $name;

        return $this;
    }

    /**
     * Rejects a field name that is reserved or already used on this level.
     *
     * @param string $name Field name.
     *
     * @return bool False when the name cannot be used. The message is reported in the WordPress admin.
     */
    private function assertSiblingName(string $name): bool
    {
        $valid = Block::assertFieldName($name);

        if (isset($this->scalars[$name]) || isset($this->children[$name])) {
            Notices::error(sprintf(
                'Field name "%s" is already used on repeater "%s".',
                $name,
                $this->name
            ));
            $valid = false;
        }

        return $valid;
    }

    /**
     * Rejects a minimum greater than a positive maximum.
     *
     * @return bool False when the bounds are impossible. The message is reported in the WordPress admin.
     */
    private function assertBounds(): bool
    {
        if ($this->max > 0 && $this->min > $this->max) {
            Notices::error(sprintf(
                'Repeater "%s" minimum (%d) cannot be greater than maximum (%d).',
                $this->name,
                $this->min,
                $this->max
            ));

            return false;
        }

        return true;
    }
}
