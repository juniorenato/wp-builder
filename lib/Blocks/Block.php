<?php

/**
 * PHP-only Gutenberg block factory.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Blocks
 */

namespace WPB\Blocks;

use InvalidArgumentException;
use WPB\Builder;

/**
 * Registers a server-rendered Gutenberg block without JavaScript or block.json.
 *
 * Requires WordPress 7.0+ (`supports.autoRegister`).
 *
 * @since  0.7.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 * @see https://make.wordpress.org/core/2026/03/03/php-only-block-registration/
 */
class Block
{
    /**
     * Accepted `register_block_type()` argument keys.
     *
     * @since 0.7.0
     *
     * @var list<string>
     *
     * @see https://developer.wordpress.org/reference/classes/wp_block_type/__construct/
     * @see https://developer.wordpress.org/reference/functions/register_block_type/
     */
    protected const array ARGS = [
        'api_version',
        'title',
        'category',
        'parent',
        'ancestor',
        'allowed_blocks',
        'icon',
        'description',
        'keywords',
        'textdomain',
        'styles',
        'variations',
        'selectors',
        'supports',
        'example',
        'render_callback',
        'variation_callback',
        'attributes',
        'uses_context',
        'provides_context',
        'block_hooks',
        'editor_script_handles',
        'script_handles',
        'view_script_handles',
        'editor_style_handles',
        'style_handles',
        'view_style_handles',
    ];

    /**
     * Attribute types that generate Inspector controls.
     *
     * @since 0.7.0
     *
     * @var list<string>
     */
    private const array ALLOWED_ATTRIBUTE_TYPES = [
        'string',
        'integer',
        'number',
        'boolean',
    ];

    /**
     * Implicit attribute names reserved by block supports.
     *
     * @since 0.7.0
     *
     * @var list<string>
     */
    private const array RESERVED_ATTRIBUTE_NAMES = [
        'style',
        'className',
        'align',
        'anchor',
        'textColor',
        'backgroundColor',
        'fontSize',
        'fontFamily',
    ];

    /**
     * Block type name (`namespace/slug`).
     *
     * @since 0.7.0
     */
    protected string $name;

    /**
     * Human-readable block title.
     *
     * @since 0.7.0
     */
    protected string $title;

    /**
     * Block description.
     *
     * @since 0.7.0
     */
    protected string $description;

    /**
     * Inserter category.
     *
     * @since 0.7.0
     */
    protected string $category;

    /**
     * Dashicon slug, without the `dashicons-` prefix.
     *
     * @since 0.7.0
     */
    protected string $icon;

    /**
     * Inserter search keywords.
     *
     * @since 0.7.0
     *
     * @var list<string>
     */
    protected array $keywords;

    /**
     * Block attribute schemas.
     *
     * @since 0.7.0
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $attributes;

    /**
     * Block supports.
     *
     * @since 0.7.0
     *
     * @var array<string, mixed>
     */
    protected array $supports;

    /**
     * Server render callback.
     *
     * @since 0.7.0
     *
     * @var callable|null
     */
    protected mixed $renderCallback;

    /**
     * Stylesheets registered for the block.
     *
     * @since 0.7.0
     *
     * @var list<array{src: string, handle: string}>
     */
    protected array $stylesheets;

    /**
     * Style handles associated with the block.
     *
     * @since 0.7.0
     *
     * @var list<string>
     */
    protected array $styleHandles;

    /**
     * Arguments passed to `register_block_type()`.
     *
     * @since 0.7.0
     *
     * @var array<string, mixed>
     */
    protected array $args;

    /**
     * Optionally registers a block immediately.
     *
     * @since 0.7.0
     *
     * @param string|null   $name   Block type name (`namespace/slug`).
     * @param string|null   $title  Human-readable title.
     * @param callable|null $render Render callback.
     */
    public function __construct(?string $name = null, ?string $title = null, mixed $render = null)
    {
        $this->init();

        if ($name && $title && is_callable($render)) {
            $this->register($name, $title, $render);
        }
    }

    /**
     * Resets the block configuration to defaults.
     *
     * @since 0.7.0
     *
     * @return static
     */
    protected function init(): static
    {
        $this->name = '';
        $this->title = '';
        $this->description = '';
        $this->category = 'widgets';
        $this->icon = '';
        $this->keywords = [];
        $this->attributes = [];
        $this->supports = [
            'autoRegister' => true,
        ];
        $this->renderCallback = null;
        $this->stylesheets = [];
        $this->styleHandles = [];
        $this->args = [];

        return $this;
    }

    /**
     * Sets the block type name.
     *
     * @since 0.7.0
     *
     * @param string $name Block type name (`namespace/slug`).
     *
     * @return static
     *
     * @throws InvalidArgumentException If the name is not `namespace/slug`.
     *
     * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#name
     */
    public function name(string $name): static
    {
        if (!preg_match('/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/', $name)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid block name "%s". Use lowercase "namespace/slug".',
                $name
            ));
        }

        $this->name = $name;

        return $this;
    }

    /**
     * Sets the human-readable block title.
     *
     * @since 0.7.0
     *
     * @param string $title Block title.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#title
     */
    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Sets the block description.
     *
     * @since 0.7.0
     *
     * @param string $description Block description.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#description
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Sets the inserter category.
     *
     * Core categories: `text`, `media`, `design`, `widgets`, `theme`, `embed`.
     *
     * @since 0.7.0
     *
     * @param string $category Category slug.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#category
     */
    public function category(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Sets the block icon.
     *
     * Dashicon slugs must omit the `dashicons-` prefix.
     *
     * @since 0.7.0
     *
     * @param string $icon Dashicon slug or SVG markup.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#icon
     * @see https://developer.wordpress.org/resource/dashicons/
     */
    public function icon(string $icon): static
    {
        $this->icon = str_starts_with($icon, 'dashicons-')
            ? substr($icon, strlen('dashicons-'))
            : $icon;

        return $this;
    }

    /**
     * Sets inserter search keywords.
     *
     * @since 0.7.0
     *
     * @param string|list<string> $keywords Keyword or list of keywords.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#keywords
     */
    public function keywords(string|array $keywords): static
    {
        if (is_array($keywords)) {
            $this->keywords = array_merge($this->keywords, array_values($keywords));
        } else {
            $this->keywords[] = $keywords;
        }

        return $this;
    }

    /**
     * Registers a block attribute that can generate an Inspector control.
     *
     * Accepted types: `string`, `integer`, `number`, `boolean`.
     * Use `string` + `enum` for a select control.
     *
     * @since 0.7.0
     *
     * @param string               $name   Attribute name.
     * @param array<string, mixed> $schema Attribute schema (`type`, `default`, `label`, `enum`).
     *
     * @return static
     *
     * @throws InvalidArgumentException If the attribute cannot generate a control.
     *
     * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-attributes/
     * @see https://make.wordpress.org/core/2026/03/03/php-only-block-registration/
     */
    public function attribute(string $name, array $schema): static
    {
        $this->assertAttributeName($name);
        $this->assertAttributeSchema($name, $schema);

        $this->attributes[$name] = $schema;

        return $this;
    }

    /**
     * Adds a string attribute.
     *
     * @since 0.7.0
     *
     * @param string $name    Attribute name.
     * @param string $label   Inspector label.
     * @param string $default Default value.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-attributes/
     */
    public function addStringAttribute(string $name, string $label, string $default = ''): static
    {
        return $this->attribute($name, [
            'type'    => 'string',
            'default' => $default,
            'label'   => $label,
        ]);
    }

    /**
     * Adds a boolean attribute.
     *
     * @since 0.7.0
     *
     * @param string $name    Attribute name.
     * @param string $label   Inspector label.
     * @param bool   $default Default value.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-attributes/
     */
    public function addBooleanAttribute(string $name, string $label, bool $default = false): static
    {
        return $this->attribute($name, [
            'type'    => 'boolean',
            'default' => $default,
            'label'   => $label,
        ]);
    }

    /**
     * Adds an integer attribute.
     *
     * @since 0.7.0
     *
     * @param string $name    Attribute name.
     * @param string $label   Inspector label.
     * @param int    $default Default value.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-attributes/
     */
    public function addIntegerAttribute(string $name, string $label, int $default = 0): static
    {
        return $this->attribute($name, [
            'type'    => 'integer',
            'default' => $default,
            'label'   => $label,
        ]);
    }

    /**
     * Adds a string attribute with a fixed set of choices.
     *
     * @since 0.7.0
     *
     * @param string        $name    Attribute name.
     * @param string        $label   Inspector label.
     * @param list<string>  $enum    Allowed values (used as select labels).
     * @param string|null   $default Default value, or the first enum value.
     *
     * @return static
     *
     * @throws InvalidArgumentException If `$enum` is empty.
     *
     * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-attributes/
     * @see https://make.wordpress.org/core/2026/03/03/php-only-block-registration/
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

        return $this->attribute($name, [
            'type'    => 'string',
            'enum'    => $enum,
            'default' => $default ?? $enum[0],
            'label'   => $label,
        ]);
    }

    /**
     * Merges block supports. `autoRegister` is always forced on.
     *
     * @since 0.7.0
     *
     * @param array<string, mixed> $supports Block supports.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/
     * @see https://make.wordpress.org/core/2026/03/03/php-only-block-registration/
     */
    public function supports(array $supports): static
    {
        $this->supports = array_replace_recursive($this->supports, $supports);
        $this->supports['autoRegister'] = true;

        return $this;
    }

    /**
     * Sets the server render callback.
     *
     * Signature: `function (array $attributes, string $content, \WP_Block $block): string`.
     * Use `get_block_wrapper_attributes()` in the callback so color, spacing,
     * and border supports appear on the front end.
     *
     * @since 0.7.0
     *
     * @param callable $callback Render callback.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/functions/get_block_wrapper_attributes/
     * @see https://developer.wordpress.org/block-editor/getting-started/fundamentals/static-dynamic-rendering/
     */
    public function render(callable $callback): static
    {
        $this->renderCallback = $callback;

        return $this;
    }

    /**
     * Registers a stylesheet for the block on the front end and in the editor.
     *
     * PHP-only blocks do not load `wp_enqueue_block_style()` in the editor, so
     * the same handle is also enqueued on `enqueue_block_editor_assets`.
     *
     * @since 0.7.0
     *
     * @param string $src    Stylesheet URL.
     * @param string $handle Style handle. Generated from the block name when empty.
     *
     * @return static
     *
     * @throws InvalidArgumentException If `$src` is empty.
     *
     * @see https://developer.wordpress.org/reference/functions/wp_register_style/
     * @see https://developer.wordpress.org/reference/hooks/enqueue_block_editor_assets/
     */
    public function stylesheet(string $src, string $handle = ''): static
    {
        if ($src === '') {
            throw new InvalidArgumentException('Block stylesheet URL cannot be empty.');
        }

        $this->stylesheets[] = [
            'src'    => $src,
            'handle' => $handle,
        ];

        return $this;
    }

    /**
     * Sets `register_block_type()` arguments.
     *
     * Options:
     *
     * - api_version
     * - title
     * - category
     * - parent
     * - ancestor
     * - allowed_blocks
     * - icon
     * - description
     * - keywords
     * - textdomain
     * - styles
     * - variations
     * - selectors
     * - supports
     * - example
     * - render_callback
     * - variation_callback
     * - attributes
     * - uses_context
     * - provides_context
     * - block_hooks
     * - editor_script_handles
     * - script_handles
     * - view_script_handles
     * - editor_style_handles
     * - style_handles
     * - view_style_handles
     *
     * @since 0.7.0
     *
     * @param string|array<string, mixed> $config Argument key or map of arguments.
     * @param mixed                       $val    Value when `$config` is a key.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/classes/wp_block_type/__construct/
     * @see https://developer.wordpress.org/reference/functions/register_block_type/
     */
    public function args(string|array $config, mixed $val = null): static
    {
        if (!is_array($config)) {
            $this->args[$config] = $val;

            return $this;
        }

        foreach ($config as $key => $value) {
            $this->args[$key] = $value;
        }

        return $this;
    }

    /**
     * Queues the block for registration on `init`.
     *
     * @since 0.7.0
     *
     * @param string|null   $name   Block type name (`namespace/slug`).
     * @param string|null   $title  Human-readable title.
     * @param callable|null $render Render callback.
     *
     * @return bool True when required configuration is present and WordPress is 7.0+.
     *
     * @see https://developer.wordpress.org/reference/functions/register_block_type/
     * @see https://developer.wordpress.org/block-editor/getting-started/fundamentals/registration-of-a-block/
     * @see https://make.wordpress.org/core/2026/03/03/php-only-block-registration/
     */
    public function register(?string $name = null, ?string $title = null, mixed $render = null): bool
    {
        if ($name) {
            $this->name($name);
        }

        if ($title) {
            $this->title($title);
        }

        if (is_callable($render)) {
            $this->render($render);
        }

        if (!$this->isWordPress7OrLater()) {
            _doing_it_wrong(
                __METHOD__,
                'PHP-only Gutenberg blocks require WordPress 7.0 or later.',
                '0.7.0'
            );

            return false;
        }

        if (!$this->name || !$this->title || !is_callable($this->renderCallback)) {
            return false;
        }

        Builder::onInit([$this, 'registerBlock']);

        return true;
    }

    /**
     * Registers the block with WordPress.
     *
     * @since 0.7.0
     *
     * @see https://developer.wordpress.org/reference/functions/register_block_type/
     */
    public function registerBlock(): void
    {
        $this->registerStylesheets();
        $this->setArgs();

        register_block_type($this->name, $this->args);
    }

    /**
     * Enqueues block styles in the editor.
     *
     * @since 0.7.0
     *
     * @see https://developer.wordpress.org/reference/functions/wp_enqueue_style/
     * @see https://developer.wordpress.org/reference/hooks/enqueue_block_editor_assets/
     */
    public function enqueueEditorStyles(): void
    {
        foreach ($this->styleHandles as $handle) {
            wp_enqueue_style($handle);
        }
    }

    /**
     * Merges default arguments with user-provided arguments.
     *
     * @since 0.7.0
     */
    protected function setArgs(): void
    {
        $args = [
            'title'           => $this->title,
            'category'        => $this->category,
            'attributes'      => $this->attributes,
            'supports'        => $this->supports,
            'render_callback' => $this->renderCallback,
        ];

        if ($this->description !== '') {
            $args['description'] = $this->description;
        }

        if ($this->icon !== '') {
            $args['icon'] = $this->icon;
        }

        if ($this->keywords) {
            $args['keywords'] = $this->keywords;
        }

        if ($this->styleHandles) {
            $args['style_handles'] = $this->styleHandles;
        }

        $this->args = array_merge($args, $this->args);

        $this->args['attributes'] = array_merge(
            $this->attributes,
            $this->args['attributes'] ?? []
        );
        $this->args['supports'] = array_replace_recursive(
            ['autoRegister' => true],
            $this->supports,
            $this->args['supports'] ?? []
        );
        $this->args['supports']['autoRegister'] = true;
        $this->args['render_callback'] = $this->renderCallback;

        if ($this->styleHandles) {
            $this->args['style_handles'] = array_values(array_unique(array_merge(
                $this->styleHandles,
                $this->args['style_handles'] ?? []
            )));
        }
    }

    /**
     * Registers stylesheets and hooks them into the block editor.
     *
     * @since 0.7.0
     *
     * @see https://developer.wordpress.org/reference/functions/wp_register_style/
     * @see https://developer.wordpress.org/reference/hooks/enqueue_block_editor_assets/
     */
    protected function registerStylesheets(): void
    {
        foreach ($this->stylesheets as $index => $stylesheet) {
            $handle = $stylesheet['handle'] !== ''
                ? $stylesheet['handle']
                : $this->defaultStyleHandle($index);

            wp_register_style($handle, $stylesheet['src']);

            $this->styleHandles[] = $handle;
        }

        if ($this->styleHandles) {
            add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorStyles']);
        }
    }

    /**
     * Builds a style handle from the block name.
     *
     * @since 0.7.0
     *
     * @param int $index Stylesheet index.
     */
    private function defaultStyleHandle(int $index): string
    {
        $handle = str_replace('/', '-', $this->name);

        return $index > 0 ? $handle . '-' . $index : $handle;
    }

    /**
     * Determines whether the running WordPress version supports autoRegister.
     *
     * @since 0.7.0
     *
     * @global string $wp_version WordPress version.
     *
     * @see https://make.wordpress.org/core/2026/03/03/php-only-block-registration/
     */
    private function isWordPress7OrLater(): bool
    {
        global $wp_version;

        return isset($wp_version) && version_compare((string) $wp_version, '7.0', '>=');
    }

    /**
     * Rejects reserved or empty attribute names.
     *
     * @since 0.7.0
     *
     * @param string $name Attribute name.
     *
     * @throws InvalidArgumentException If the name is reserved or empty.
     */
    private function assertAttributeName(string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Block attribute name cannot be empty.');
        }

        if (in_array($name, self::RESERVED_ATTRIBUTE_NAMES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Attribute name "%s" is reserved by block supports. Reserved names: %s.',
                $name,
                implode(', ', self::RESERVED_ATTRIBUTE_NAMES)
            ));
        }
    }

    /**
     * Rejects attribute schemas that cannot generate Inspector controls.
     *
     * @since 0.7.0
     *
     * @param string               $name   Attribute name.
     * @param array<string, mixed> $schema Attribute schema.
     *
     * @throws InvalidArgumentException If the schema is not supported.
     */
    private function assertAttributeSchema(string $name, array $schema): void
    {
        $type = $schema['type'] ?? '';

        if (!in_array($type, self::ALLOWED_ATTRIBUTE_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid type for attribute "%s". Allowed types: %s.',
                $name,
                implode(', ', self::ALLOWED_ATTRIBUTE_TYPES)
            ));
        }

        if (isset($schema['source'])) {
            throw new InvalidArgumentException(sprintf(
                'Attribute "%s" cannot use a source. PHP-only blocks store attributes in the block JSON.',
                $name
            ));
        }

        if (($schema['role'] ?? '') === 'local') {
            throw new InvalidArgumentException(sprintf(
                'Attribute "%s" uses role "local" and will not generate Inspector controls.',
                $name
            ));
        }

        if (isset($schema['enum']) && $type !== 'string') {
            throw new InvalidArgumentException(sprintf(
                'Enum attribute "%s" must use type "string".',
                $name
            ));
        }

        if (isset($schema['enum']) && (!is_array($schema['enum']) || $schema['enum'] === [])) {
            throw new InvalidArgumentException(sprintf(
                'Enum attribute "%s" requires a non-empty list of values.',
                $name
            ));
        }
    }
}
