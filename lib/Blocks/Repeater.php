<?php

/**
 * Repeatable field group for a Gutenberg block.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Blocks
 */

namespace WPB\Blocks;

use InvalidArgumentException;
use WPB\Builder;

/**
 * Collects the fields of one repeater level and renders its items.
 *
 * Create it from {@see Block::repeater()} or {@see Repeater::repeater()}.
 *
 * @since  0.8.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class Repeater
{
    /**
     * Block that owns this repeater.
     *
     * @since 0.8.0
     */
    private Block $block;

    /**
     * Parent repeater, or null at the block root.
     *
     * @since 0.8.0
     */
    private ?Repeater $parent;

    /**
     * Field name.
     *
     * @since 0.8.0
     */
    private string $name;

    /**
     * Inspector label.
     *
     * @since 0.8.0
     */
    private string $label;

    /**
     * Minimum item count enforced in the editor.
     *
     * @since 0.8.0
     */
    private int $min = 0;

    /**
     * Maximum item count enforced in the editor. Zero means no limit.
     *
     * @since 0.8.0
     */
    private int $max = 0;

    /**
     * Scalar fields, keyed by name.
     *
     * @since 0.8.0
     *
     * @var array<string, array<string, mixed>>
     */
    private array $scalars = [];

    /**
     * Nested repeaters, keyed by name.
     *
     * @since 0.8.0
     *
     * @var array<string, Repeater>
     */
    private array $children = [];

    /**
     * Field names in declaration order.
     *
     * @since 0.8.0
     *
     * @var list<string>
     */
    private array $order = [];

    /**
     * Items currently being rendered.
     *
     * @since 0.8.0
     *
     * @var list<array<string, mixed>>
     */
    private array $items = [];

    /**
     * Field passed to a partial view.
     *
     * @since 0.8.0
     *
     * @var array<string, mixed>
     */
    private array $field = [];

    /**
     * Value passed to a partial view.
     *
     * @since 0.8.0
     */
    private mixed $value = null;

    /**
     * Creates a repeater level.
     *
     * @since 0.8.0
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
     *
     * @since 0.8.0
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Sets the minimum number of items the editor allows.
     *
     * @since 0.8.0
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
     * Sets the maximum number of items the editor allows.
     *
     * Zero removes the limit.
     *
     * @since 0.8.0
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
     * Adds a string field.
     *
     * @since 0.8.0
     *
     * @param string $name    Field name.
     * @param string $label   Inspector label.
     * @param string $default Default value.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the name is reserved or already used at this level.
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
     * @since 0.8.0
     *
     * @param string $name    Field name.
     * @param string $label   Inspector label.
     * @param int    $default Default value.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the name is reserved or already used at this level.
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
     * @since 0.8.0
     *
     * @param string $name    Field name.
     * @param string $label   Inspector label.
     * @param bool   $default Default value.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the name is reserved or already used at this level.
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
     * @since 0.8.0
     *
     * @param string       $name    Field name.
     * @param string       $label   Inspector label.
     * @param list<string> $enum    Allowed values.
     * @param string|null  $default Default value, or the first enum value.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the enum is empty or the name is already used.
     */
    public function addEnumAttribute(string $name, string $label, array $enum, ?string $default = null): static
    {
        $enum = array_values($enum);

        if ($enum === []) {
            throw new InvalidArgumentException(sprintf(
                'Enum attribute "%s" requires at least one value.',
                $name
            ));
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
     * @since 0.8.0
     *
     * @param string $name  Field name.
     * @param string $label Inspector label.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the name is reserved or already used at this level.
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
     * @since 0.8.0
     *
     * @param string $name  Field name.
     * @param string $label Inspector label.
     *
     * @return Repeater Nested repeater.
     *
     * @throws InvalidArgumentException If the name is reserved or already used at this level.
     */
    public function repeater(string $name, string $label): Repeater
    {
        $this->assertSiblingName($name);

        $child = new Repeater($this->block, $this, $name, $label);
        $this->children[$name] = $child;
        $this->order[] = $name;

        return $child;
    }

    /**
     * Returns to the parent repeater, or to the block at the root.
     *
     * @since 0.8.0
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
     * @since 0.8.0
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
     * @since 0.8.0
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
     * @since 0.8.0
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
     * @since 0.8.0
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
     * @since 0.8.0
     *
     * @param string               $name   Field name.
     * @param array<string, mixed> $schema Field schema.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the name is reserved or already used at this level.
     */
    private function addScalar(string $name, array $schema): static
    {
        $this->assertSiblingName($name);
        $this->scalars[$name] = $schema;
        $this->order[] = $name;

        return $this;
    }

    /**
     * Rejects a field name that is reserved or already used on this level.
     *
     * @since 0.8.0
     *
     * @param string $name Field name.
     *
     * @throws InvalidArgumentException If the name cannot be used.
     */
    private function assertSiblingName(string $name): void
    {
        Block::assertFieldName($name);

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
     * @since 0.8.0
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
