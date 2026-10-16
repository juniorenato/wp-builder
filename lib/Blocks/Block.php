<?php

/**
 * Server-rendered Gutenberg block factory.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Blocks
 */

namespace WPB\Blocks;

use InvalidArgumentException;
use WPB\Builder;

/**
 * Registers a server-rendered Gutenberg block without block.json.
 *
 * Requires WordPress 7.0+ (`supports.autoRegister`). Scalar blocks saved in
 * post content stay PHP-only. Repeaters and post meta storage load one shared
 * editor script.
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
     * Whether field values are stored in the block comment instead of post meta.
     *
     * @since 0.8.0
     */
    protected bool $saveAsContent = false;

    /**
     * Root repeaters, keyed by field name.
     *
     * @since 0.8.0
     *
     * @var array<string, Repeater>
     */
    protected array $repeaters = [];

    /**
     * Top-level field names in declaration order.
     *
     * @since 0.8.0
     *
     * @var list<string>
     */
    protected array $fieldOrder = [];

    /**
     * Editor schemas keyed by block name.
     *
     * @since 0.8.0
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $editorBlocks = [];

    /**
     * Meta keys included in post revisions.
     *
     * @since 0.8.0
     *
     * @var array<string, string>
     */
    private static array $revisionKeys = [];

    /**
     * Meta keys already passed to `register_meta()`.
     *
     * @since 0.8.0
     *
     * @var array<string, true>
     */
    private static array $registeredMeta = [];

    /**
     * Whether the editor script hook is registered.
     *
     * @since 0.8.0
     */
    private static bool $editorHooked = false;

    /**
     * Whether the editor script was attached to the page.
     *
     * @since 0.8.0
     */
    private static bool $editorScriptAdded = false;

    /**
     * Whether the revision meta filter is registered.
     *
     * @since 0.8.0
     */
    private static bool $revisionHooked = false;

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
        $this->saveAsContent = false;
        $this->repeaters = [];
        $this->fieldOrder = [];

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
        $this->assertFieldAvailable($name);

        $this->attributes[$name] = $schema;
        $this->trackField($name);

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
     * Stores field values in the block comment inside `post_content`.
     *
     * Without this call, values are stored in post meta under {@see metaKey()}.
     *
     * @since 0.8.0
     *
     * @return static
     */
    public function saveAsContent(): static
    {
        $this->saveAsContent = true;

        return $this;
    }

    /**
     * Opens a repeater.
     *
     * Nested repeaters use {@see Repeater::repeater()}. Call {@see Repeater::end()}
     * to return here. The repeater is registered immediately, so `end()` is only
     * needed to continue this chain.
     *
     * @since 0.8.0
     *
     * @param string $name  Field name.
     * @param string $label Inspector label.
     *
     * @return Repeater Repeater builder.
     *
     * @throws InvalidArgumentException If the name is reserved or already used.
     */
    public function repeater(string $name, string $label): Repeater
    {
        $this->assertAttributeName($name);

        if (isset($this->attributes[$name]) || isset($this->repeaters[$name])) {
            throw new InvalidArgumentException(sprintf(
                'Field name "%s" is already used on this block.',
                $name
            ));
        }

        $repeater = new Repeater($this, null, $name, $label);
        $this->repeaters[$name] = $repeater;
        $this->trackField($name);

        return $repeater;
    }

    /**
     * Returns the post meta key for this block.
     *
     * The block name `namespace/slug` is stored as `namespace_slug`.
     *
     * @since 0.8.0
     *
     * @return string Meta key.
     */
    public function metaKey(): string
    {
        return str_replace('/', '_', $this->name);
    }

    /**
     * Renders the block with normalized field values.
     *
     * A `render()` callback receives those values and owns the HTML. A block
     * with repeaters and no callback is rendered from `views/blocks`.
     *
     * @since 0.8.0
     *
     * @param array<string, mixed> $attributes Block attributes.
     * @param string               $content    Inner block HTML.
     * @param \WP_Block            $block      Block instance.
     *
     * @return string Rendered block.
     *
     * @see https://developer.wordpress.org/reference/functions/get_block_wrapper_attributes/
     */
    public function renderBlock(array $attributes, string $content, \WP_Block $block): string
    {
        $values = $this->valuesForRender($attributes, $block);

        if (is_callable($this->renderCallback)) {
            return (string) ($this->renderCallback)($values, $content, $block);
        }

        $html = '';

        foreach ($this->fieldSchemas() as $field) {
            if (($field['type'] ?? '') !== 'repeater') {
                continue;
            }

            $name = (string) $field['name'];
            $html .= $this->repeaters[$name]->renderItems($values[$name] ?? []);
        }

        if ($this->isBlockRendererRequest() && trim(wp_strip_all_tags($html)) === '') {
            $html .= '<p class="wpb-block-placeholder">' . esc_html($this->title) . '</p>';
        }

        return sprintf(
            '<div %1$s>%2$s</div>',
            get_block_wrapper_attributes([
                'class' => 'wpb-block',
            ]),
            $html
        );
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

        if (!$this->name || !$this->title) {
            return false;
        }

        if (!is_callable($this->renderCallback) && !$this->repeaters) {
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
        $this->prepareStorage();
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
        $this->applyStorageAttributes();
        $this->args['render_callback'] = [$this, 'renderBlock'];

        if ($this->needsEditorScript()) {
            self::registerSharedEditorScript();
            $handles = $this->args['editor_script'] ?? [];

            if (is_string($handles)) {
                $handles = [$handles];
            }

            $handles[] = 'wpb-block-editor';
            $this->args['editor_script'] = array_values(array_unique($handles));
        }

        self::registerSharedEditorStyle();
        $frameStyles = $this->args['style_handles'] ?? [];

        if (is_string($frameStyles)) {
            $frameStyles = [$frameStyles];
        }

        $frameStyles[] = 'wpb-block-editor';
        $this->args['style_handles'] = array_values(array_unique($frameStyles));

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
     * Rejects reserved or empty field names.
     *
     * @since 0.8.0
     *
     * @param string $name Field name.
     *
     * @throws InvalidArgumentException If the name is reserved or empty.
     */
    public static function assertFieldName(string $name): void
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
        self::assertFieldName($name);
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
                'Attribute "%s" cannot use a source. Choose post meta or post content with saveAsContent().',
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

    /**
     * Rejects a scalar name that belongs to a repeater.
     *
     * @since 0.8.0
     *
     * @param string $name Field name.
     *
     * @throws InvalidArgumentException If the name is already a repeater.
     */
    private function assertFieldAvailable(string $name): void
    {
        if (isset($this->repeaters[$name])) {
            throw new InvalidArgumentException(sprintf(
                'Field name "%s" is already used on this block.',
                $name
            ));
        }
    }

    /**
     * Records a top-level field in declaration order.
     *
     * @since 0.8.0
     *
     * @param string $name Field name.
     */
    private function trackField(string $name): void
    {
        if (!in_array($name, $this->fieldOrder, true)) {
            $this->fieldOrder[] = $name;
        }
    }

    /**
     * Returns top-level field schemas in declaration order.
     *
     * @since 0.8.0
     *
     * @return list<array<string, mixed>> Field schemas.
     */
    private function fieldSchemas(): array
    {
        $fields = [];

        foreach ($this->fieldOrder as $name) {
            if (isset($this->repeaters[$name])) {
                $fields[] = $this->repeaters[$name]->schema();
                continue;
            }

            if (isset($this->attributes[$name])) {
                $fields[] = $this->scalarSchema($name);
            }
        }

        return $fields;
    }

    /**
     * Returns root repeater schemas.
     *
     * @since 0.8.0
     *
     * @return list<array<string, mixed>> Repeater schemas.
     */
    private function repeaterSchemas(): array
    {
        $schemas = [];

        foreach ($this->fieldSchemas() as $field) {
            if (($field['type'] ?? '') === 'repeater') {
                $schemas[] = $field;
            }
        }

        return $schemas;
    }

    /**
     * Builds the editor schema for one scalar attribute.
     *
     * @since 0.8.0
     *
     * @param string $name Attribute name.
     *
     * @return array<string, mixed> Field schema.
     */
    private function scalarSchema(string $name): array
    {
        $schema = $this->attributes[$name];
        $type = (string) ($schema['type'] ?? 'string');

        if ($type === 'string' && !empty($schema['enum'])) {
            $type = 'enum';
        }

        $field = [
            'name'    => $name,
            'type'    => $type,
            'label'   => (string) ($schema['label'] ?? $name),
            'default' => $schema['default'] ?? $this->scalarDefault($type),
        ];

        if ($type === 'enum') {
            $field['enum'] = array_values($schema['enum'] ?? []);
        }

        return $field;
    }

    /**
     * Returns the empty value for a scalar field type.
     *
     * @since 0.8.0
     *
     * @param string $type Field type.
     *
     * @return mixed Empty value.
     */
    private function scalarDefault(string $type): mixed
    {
        return match ($type) {
            'integer', 'number', 'image' => 0,
            'boolean' => false,
            default => '',
        };
    }

    /**
     * Registers post meta and queues the editor script.
     *
     * @since 0.8.0
     */
    private function prepareStorage(): void
    {
        $fields = $this->fieldSchemas();

        if (!$this->saveAsContent && $fields !== []) {
            $this->registerMeta($fields);
        }

        if ($this->needsEditorScript()) {
            $this->queueEditorConfig();
        }
    }

    /**
     * Determines whether this block needs the shared editor script.
     *
     * @since 0.8.0
     *
     * @return bool True when the inspector must be drawn by the script.
     */
    private function needsEditorScript(): bool
    {
        if ($this->repeaters !== []) {
            return true;
        }

        return !$this->saveAsContent && $this->attributes !== [];
    }

    /**
     * Registers the block meta key for every post type.
     *
     * @since 0.8.0
     *
     * @param list<array<string, mixed>> $fields Field schemas.
     *
     * @see https://developer.wordpress.org/reference/functions/register_meta/
     * @see https://developer.wordpress.org/reference/hooks/wp_post_revision_meta_keys/
     */
    private function registerMeta(array $fields): void
    {
        $key = $this->metaKey();
        self::$revisionKeys[$key] = $key;

        if (!self::$revisionHooked) {
            self::$revisionHooked = true;
            add_filter('wp_post_revision_meta_keys', [self::class, 'revisionMetaKeys']);
        }

        if (isset(self::$registeredMeta[$key])) {
            return;
        }

        self::$registeredMeta[$key] = true;

        register_meta('post', $key, [
            'type'              => 'object',
            'single'            => true,
            'sanitize_callback' => static function (mixed $value) use ($fields): array {
                return FieldValue::normalizeBag($fields, wp_unslash($value));
            },
            'auth_callback'     => static function (mixed $allowed, mixed $metaKey, mixed $objectId): bool {
                return current_user_can('edit_post', (int) $objectId);
            },
            'show_in_rest'      => [
                'schema' => [
                    'type'                 => 'object',
                    'properties'           => FieldValue::restProperties($fields),
                    'additionalProperties' => false,
                ],
            ],
        ]);
    }

    /**
     * Adds the block schema to the shared editor script.
     *
     * @since 0.8.0
     *
     * @see https://developer.wordpress.org/reference/hooks/enqueue_block_editor_assets/
     */
    private function queueEditorConfig(): void
    {
        self::$editorBlocks[$this->name] = [
            'storage'   => $this->saveAsContent ? 'content' : 'meta',
            'attribute' => $this->saveAsContent ? '' : $this->metaKey(),
            'fields'    => $this->saveAsContent ? $this->repeaterSchemas() : $this->fieldSchemas(),
        ];

        if (!self::$editorHooked) {
            self::$editorHooked = true;
            add_action('enqueue_block_editor_assets', [self::class, 'enqueueEditorScript']);
            add_action('enqueue_block_assets', [self::class, 'enqueueEditorScript']);
        }
    }

    /**
     * Registers the shared editor script handle.
     *
     * The handle depends on the block editor packages and is deferred with
     * them, so the field UI runs only after `wp.blockEditor` exists.
     *
     * @since 0.8.0
     *
     * @see https://developer.wordpress.org/reference/functions/wp_register_script/
     */
    private static function registerSharedEditorScript(): void
    {
        if (wp_script_is('wpb-block-editor', 'registered')) {
            return;
        }

        $path = dirname(__DIR__, 2) . '/assets/js/block-editor.js';
        $url = Builder::url($path);

        wp_register_script(
            'wpb-block-editor',
            $url !== '' ? $url : false,
            ['wp-hooks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-blocks', 'wp-block-editor', 'wp-data'],
            is_readable($path) ? (string) filemtime($path) : false,
            [
                'in_footer' => true,
                'strategy'  => 'defer',
            ]
        );
    }

    /**
     * Enqueues the shared block editor script.
     *
     * When the library lives outside the content directory, the file URL is
     * empty and the script is printed inline on the same handle.
     *
     * @since 0.8.0
     *
     * @see https://developer.wordpress.org/reference/functions/wp_enqueue_script/
     * @see https://developer.wordpress.org/reference/functions/wp_add_inline_script/
     */
    public static function enqueueEditorScript(): void
    {
        if (self::$editorBlocks === [] || !is_admin()) {
            return;
        }

        self::registerSharedEditorStyle();
        wp_enqueue_style('wp-components');
        wp_enqueue_style('wpb-block-editor');

        if (self::$editorScriptAdded) {
            return;
        }

        self::registerSharedEditorScript();

        $config = wp_json_encode([
            'i18n'   => [
                'add'          => __('Add', 'wpb'),
                'remove'       => __('Remove', 'wpb'),
                'moveUp'       => __('Move up', 'wpb'),
                'moveDown'     => __('Move down', 'wpb'),
                'selectImage'  => __('Select image', 'wpb'),
                'replaceImage' => __('Replace image', 'wpb'),
                'removeImage'  => __('Remove image', 'wpb'),
            ],
            'blocks' => self::$editorBlocks,
        ]);

        if (!is_string($config)) {
            return;
        }

        global $wp_scripts;

        $src = $wp_scripts->registered['wpb-block-editor']->src ?? false;
        $inline = 'window.wpbBlockEditor = ' . $config . ';';

        if ($src === false || $src === '') {
            $path = dirname(__DIR__, 2) . '/assets/js/block-editor.js';
            $script = is_readable($path) ? file_get_contents($path) : false;

            if (!is_string($script)) {
                return;
            }

            $inline .= $script;
        }

        wp_add_inline_script('wpb-block-editor', $inline, ($src === false || $src === '') ? 'after' : 'before');
        wp_enqueue_script('wpb-block-editor');
        self::$editorScriptAdded = true;
    }

    /**
     * Registers the frame styles shared by the editor and the front end.
     *
     * @since 0.8.0
     *
     * @see https://developer.wordpress.org/reference/functions/wp_register_style/
     */
    private static function registerSharedEditorStyle(): void
    {
        if (wp_style_is('wpb-block-editor', 'registered')) {
            return;
        }

        $path = dirname(__DIR__, 2) . '/assets/css/block-editor.css';
        $url = is_readable($path) ? Builder::url($path) : '';

        wp_register_style(
            'wpb-block-editor',
            $url !== '' ? $url : false,
            [],
            is_readable($path) ? (string) filemtime($path) : false
        );

        if ($url !== '' || !is_readable($path)) {
            return;
        }

        $css = file_get_contents($path);

        if (is_string($css)) {
            wp_add_inline_style('wpb-block-editor', $css);
        }
    }

    /**
     * Adds block meta keys to post revisions.
     *
     * @since 0.8.0
     *
     * @param list<string> $keys Revision meta keys.
     *
     * @return list<string> Meta keys, including block storage keys.
     *
     * @see https://developer.wordpress.org/reference/hooks/wp_post_revision_meta_keys/
     */
    public static function revisionMetaKeys(array $keys): array
    {
        return array_values(array_unique(array_merge($keys, array_values(self::$revisionKeys))));
    }

    /**
     * Points block attributes at post meta or at repeater arrays in the block comment.
     *
     * @since 0.8.0
     */
    private function applyStorageAttributes(): void
    {
        if ($this->saveAsContent) {
            foreach (array_keys($this->repeaters) as $name) {
                $this->args['attributes'][$name] = [
                    'type'    => 'array',
                    'default' => [],
                ];
            }

            return;
        }

        foreach (array_keys($this->attributes) as $name) {
            unset($this->args['attributes'][$name]);
        }

        foreach (array_keys($this->repeaters) as $name) {
            unset($this->args['attributes'][$name]);
        }

        $fields = $this->fieldSchemas();

        if ($fields === []) {
            return;
        }

        $context = $this->args['uses_context'] ?? [];

        if (!in_array('postId', $context, true)) {
            $context[] = 'postId';
        }

        $this->args['uses_context'] = array_values($context);
        $this->args['attributes'][$this->metaKey()] = [
            'type'    => 'object',
            'source'  => 'meta',
            'meta'    => $this->metaKey(),
            'default' => FieldValue::defaults($fields),
        ];
    }

    /**
     * Resolves normalized field values for the render callback.
     *
     * @since 0.8.0
     *
     * @param array<string, mixed> $attributes Block attributes.
     * @param \WP_Block            $block      Block instance.
     *
     * @return array<string, mixed> Values passed to the render callback or the automatic view.
     *
     * @see https://developer.wordpress.org/reference/functions/get_post_meta/
     */
    private function valuesForRender(array $attributes, \WP_Block $block): array
    {
        $fields = $this->fieldSchemas();

        if ($this->saveAsContent) {
            $normalized = $attributes;

            foreach ($fields as $field) {
                $name = (string) $field['name'];
                $normalized[$name] = FieldValue::normalize(
                    $field,
                    $attributes[$name] ?? ($field['default'] ?? null)
                );
            }

            return $normalized;
        }

        $key = $this->metaKey();
        $stored = [];

        if ($this->isBlockRendererRequest() && isset($attributes[$key]) && (is_array($attributes[$key]) || $attributes[$key] instanceof \stdClass)) {
            $stored = (array) $attributes[$key];
        } else {
            $postId = (int) ($block->context['postId'] ?? get_the_ID());

            if ($postId > 0) {
                $meta = get_post_meta($postId, $key, true);

                if (is_array($meta) || $meta instanceof \stdClass) {
                    $stored = (array) $meta;
                }
            }
        }

        $normalized = FieldValue::normalizeBag($fields, $stored);

        foreach ($attributes as $name => $value) {
            if ($name === $key || array_key_exists($name, $normalized)) {
                continue;
            }

            $normalized[$name] = $value;
        }

        return $normalized;
    }

    /**
     * Determines whether the current request is the editor's server render preview.
     *
     * @since 0.8.0
     *
     * @return bool True on the block renderer REST route.
     */
    private function isBlockRendererRequest(): bool
    {
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return false;
        }

        $route = '';

        if (isset($GLOBALS['wp']) && is_object($GLOBALS['wp']) && isset($GLOBALS['wp']->query_vars['rest_route'])) {
            $route = (string) $GLOBALS['wp']->query_vars['rest_route'];
        }

        return str_contains($route, '/block-renderer/');
    }
}
