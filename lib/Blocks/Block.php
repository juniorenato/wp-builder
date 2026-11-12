<?php

/**
 * Server-rendered Gutenberg block factory.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Blocks
 */

namespace WPB\Blocks;

use WPB\Builder;
use WPB\Notices;

/**
 * Registers a server-rendered Gutenberg block without block.json.
 *
 * Requires WordPress 7.0+ (`supports.autoRegister`). Scalar blocks saved in
 * post content stay PHP-only. Repeaters and post meta storage load one shared
 * editor script.
 *
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
     * Block attribute that identifies one insertion when values live in post meta.
     */
    public const string INSTANCE_ATTRIBUTE = 'wpbId';

    /**
     * Block type name (`namespace/slug`).
     */
    protected string $name;

    /**
     * Human-readable block title.
     */
    protected string $title;

    /**
     * Block description.
     */
    protected string $description;

    /**
     * Inserter category.
     */
    protected string $category;

    /**
     * Dashicon slug, without the `dashicons-` prefix.
     */
    protected string $icon;

    /**
     * Inserter search keywords.
     *
     * @var list<string>
     */
    protected array $keywords;

    /**
     * Post types that may show this block in the inserter.
     *
     * An empty list means every editor.
     *
     * @var list<string>
     */
    private array $postTypes;

    /**
     * Whether the post type check is queued for `wp_loaded`.
     */
    private bool $postTypeRestrictionQueued;

    /**
     * Whether `allowed_block_types_all` is already filtering this block.
     */
    private bool $postTypeFilterAdded;

    /**
     * Block attribute schemas.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $attributes;

    /**
     * Block supports.
     *
     * @var array<string, mixed>
     */
    protected array $supports;

    /**
     * Server render callback.
     *
     * @var callable|null
     */
    protected mixed $renderCallback;

    /**
     * Stylesheets registered for the block.
     *
     * @var list<array{src: string, handle: string}>
     */
    protected array $stylesheets;

    /**
     * Style handles associated with the block.
     *
     * @var list<string>
     */
    protected array $styleHandles;

    /**
     * Arguments passed to `register_block_type()`.
     *
     * @var array<string, mixed>
     */
    protected array $args;

    /**
     * Whether field values are stored in the block comment instead of post meta.
     */
    protected bool $saveAsContent = false;

    /**
     * Root repeaters, keyed by field name.
     *
     * @var array<string, Repeater>
     */
    protected array $repeaters = [];

    /**
     * Top-level field names in declaration order.
     *
     * @var list<string>
     */
    protected array $fieldOrder = [];

    /**
     * Theme template slug passed to `get_template_part()`.
     *
     * An empty string means `{Builder::blockTemplates()}/{block name}`.
     * That directory defaults to `template-parts/blocks`.
     */
    private string $templateSlug;

    /**
     * Which convention assets to enqueue when the block renders.
     *
     * An empty string enqueues nothing. `style`, `script`, and `both` select the files.
     *
     * @var ''|'style'|'script'|'both'
     */
    private string $assetKind;

    /**
     * Block names registered by this library.
     *
     * @var array<string, true>
     */
    private static array $libraryBlocks = [];

    /**
     * Theme template paths found during this request.
     *
     * An empty string means the slug was looked up and the file is absent.
     *
     * @var array<string, string>
     */
    private static array $locatedTemplates = [];

    /**
     * Editor schemas keyed by block name.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $editorBlocks = [];

    /**
     * Meta keys included in post revisions.
     *
     * @var array<string, string>
     */
    private static array $revisionKeys = [];

    /**
     * Meta keys already passed to `register_meta()`.
     *
     * @var array<string, true>
     */
    private static array $registeredMeta = [];

    /**
     * Whether the editor script hook is registered.
     */
    private static bool $editorHooked = false;

    /**
     * Whether the editor script was attached to the page.
     */
    private static bool $editorScriptAdded = false;

    /**
     * Whether the revision meta filter is registered.
     */
    private static bool $revisionHooked = false;

    /**
     * Optionally registers a block immediately.
     *
     * @param string|null   $name   Block type name (`namespace/slug`).
     * @param string|null   $title  Human-readable title.
     * @param callable|null $render Render callback.
     */
    public function __construct(?string $name = null, ?string $title = null, mixed $render = null)
    {
        $this->init();
        self::hookCoreTemplates();

        if ($name && $title && is_callable($render)) {
            $this->register($name, $title, $render);
        }
    }

    /**
     * Resets the block configuration to defaults.
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
        $this->postTypes = [];
        $this->postTypeRestrictionQueued = false;
        $this->postTypeFilterAdded = false;
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
        $this->templateSlug = '';
        $this->assetKind = '';

        return $this;
    }

    /**
     * Sets the block type name.
     *
     * @param string $name Block type name (`namespace/slug`).
     *
     * @return static
     *
     * An invalid name is ignored and reported in the WordPress admin.
     *
     * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#name
     */
    public function name(string $name): static
    {
        if (!preg_match('/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/', $name)) {
            Notices::error(sprintf(
                'Invalid block name "%s". Use lowercase "namespace/slug".',
                $name
            ));

            return $this;
        }

        $this->name = $name;

        return $this;
    }

    /**
     * Sets the human-readable block title.
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
     * Limits the inserter to the given post types.
     *
     * Accepts one post type slug or a list of slugs. Further calls add to the
     * list. The check runs on `wp_loaded`, after post types registered on
     * `init` exist. A missing slug is ignored and reported in the WordPress
     * admin. When every slug is missing, the block stays available everywhere.
     *
     * The block remains registered. Content that already contains it still
     * renders. Editors without a post, such as the site editor, do not show it.
     *
     * @param string|list<string> $postTypes Post type slug or list of slugs.
     *
     * @return static
     *
     * A value that is not a string is ignored and reported in the WordPress admin.
     *
     * @see https://developer.wordpress.org/reference/hooks/allowed_block_types_all/
     */
    public function postTypes(string|array $postTypes): static
    {
        if (!is_array($postTypes)) {
            $postTypes = [$postTypes];
        }

        foreach ($postTypes as $postType) {
            if (!is_string($postType)) {
                Notices::error(sprintf(
                    'Block post type must be a string, %s given.',
                    get_debug_type($postType)
                ));

                continue;
            }

            $this->postTypes[] = $postType;
        }

        $this->postTypes = array_values(array_unique($this->postTypes));
        $this->queuePostTypeRestriction();

        return $this;
    }

    /**
     * Registers a block attribute that can generate an Inspector control.
     *
     * Accepted types: `string`, `integer`, `number`, `boolean`.
     * Use `string` + `enum` for a select control.
     *
     * @param string               $name   Attribute name.
     * @param array<string, mixed> $schema Attribute schema (`type`, `default`, `label`, `enum`).
     *
     * @return static
     *
     * An invalid attribute is ignored and reported in the WordPress admin.
     *
     * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-attributes/
     * @see https://make.wordpress.org/core/2026/03/03/php-only-block-registration/
     */
    public function attribute(string $name, array $schema): static
    {
        $nameOk = $this->assertAttributeName($name);
        $schemaOk = $this->assertAttributeSchema($name, $schema);
        $available = $this->assertFieldAvailable($name);

        if (!$nameOk || !$schemaOk || !$available) {
            return $this;
        }

        $this->attributes[$name] = $schema;
        $this->trackField($name);

        return $this;
    }

    /**
     * Adds a string attribute.
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
     * @param string        $name    Attribute name.
     * @param string        $label   Inspector label.
     * @param list<string>  $enum    Allowed values (used as select labels).
     * @param string|null   $default Default value, or the first enum value.
     *
     * @return static
     *
     * An empty enum is ignored and reported in the WordPress admin.
     *
     * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-attributes/
     * @see https://make.wordpress.org/core/2026/03/03/php-only-block-registration/
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
     * Sets the theme template that replaces the React mount point.
     *
     * `template-parts/blocks/hero` loads `template-parts/blocks/hero.php` from the child theme, then the parent.
     * A trailing `.php` is removed. An empty slug clears any previous slug and
     * the block uses `{Builder::blockTemplates()}/{block name}`. That directory
     * defaults to `template-parts/blocks`.
     *
     * @param string $slug Template slug, without `.php`.
     *
     * @return static
     */
    public function template(string $slug): static
    {
        $slug = trim($slug);

        if (str_ends_with($slug, '.php')) {
            $slug = substr($slug, 0, -4);
        }

        if ($slug === '') {
            Notices::error('Block template slug cannot be empty.');
            $this->templateSlug = '';

            return $this;
        }

        $this->templateSlug = $slug;

        return $this;
    }

    /**
     * Enqueues this block's CSS and JavaScript when the block renders.
     *
     * The files live in {@see Builder::blockAssets()}, which defaults to
     * `assets/public/blocks`. `pluria/carousel` loads `pluria-carousel.css` and
     * `pluria-carousel.js` from that directory, in the child theme, then the parent.
     * Pass `style` or `script` to load one file. `both` is the default.
     * A missing file is skipped. The script prints in the footer.
     *
     * @param 'style'|'script'|'both' $kind Files to enqueue.
     *
     * @return static
     *
     * An unknown kind is ignored and reported in the WordPress admin.
     *
     * @see https://developer.wordpress.org/reference/functions/wp_enqueue_style/
     * @see https://developer.wordpress.org/reference/functions/wp_enqueue_script/
     * @see https://developer.wordpress.org/reference/functions/get_theme_file_path/
     */
    public function assets(string $kind = 'both'): static
    {
        if (!in_array($kind, ['style', 'script', 'both'], true)) {
            Notices::error(sprintf(
                'Block assets must be "style", "script", or "both", "%s" given.',
                $kind
            ));

            return $this;
        }

        $this->assetKind = $kind;

        return $this;
    }

    /**
     * Registers a stylesheet for the block on the front end and in the editor.
     *
     * PHP-only blocks do not load `wp_enqueue_block_style()` in the editor, so
     * the same handle is also enqueued on `enqueue_block_editor_assets`.
     *
     * @param string $src    Stylesheet URL.
     * @param string $handle Style handle. Generated from the block name when empty.
     *
     * @return static
     *
     * An empty URL is ignored and reported in the WordPress admin.
     *
     * @see https://developer.wordpress.org/reference/functions/wp_register_style/
     * @see https://developer.wordpress.org/reference/hooks/enqueue_block_editor_assets/
     */
    public function stylesheet(string $src, string $handle = ''): static
    {
        if ($src === '') {
            Notices::error('Block stylesheet URL cannot be empty.');

            return $this;
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
     * Without this call, each insertion is stored in post meta under {@see metaKey()},
     * keyed by the `wpbId` saved in that block comment.
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
     * @param string $name  Field name.
     * @param string $label Inspector label.
     *
     * @return Repeater Repeater builder.
     *
     * An invalid name returns a repeater that is not registered on this block.
     * The message is reported in the WordPress admin.
     */
    public function repeater(string $name, string $label): Repeater
    {
        $nameOk = $this->assertAttributeName($name);
        $available = !isset($this->attributes[$name]) && !isset($this->repeaters[$name]);

        if (!$available) {
            Notices::error(sprintf(
                'Field name "%s" is already used on this block.',
                $name
            ));
        }

        if (!$nameOk || !$available) {
            return new Repeater($this, null, $name, $label);
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
     * Each insertion has its own entry in that value, keyed by `wpbId`.
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
     * A `render()` callback receives those values and owns the HTML. Image
     * fields in that array stay attachment IDs. Otherwise a theme template
     * owns the HTML. With no template, the block prints a React mount point.
     * `assets()` enqueues the block CSS and JavaScript before that HTML is built.
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
        $this->enqueueBlockAssets();
        $values = $this->valuesForRender($attributes, $block);

        if (is_callable($this->renderCallback)) {
            return (string) ($this->renderCallback)($values, $content, $block);
        }

        $slug = $this->templateSlug !== '' ? $this->templateSlug : Builder::blockTemplates() . '/' . $this->name;
        $rendered = self::captureTemplate($slug, [
            'attributes' => $this->propsForReact($values),
            'content'    => $content,
            'block'      => $block,
            'name'       => $this->name,
        ]);

        if (is_string($rendered)) {
            return $rendered;
        }

        if ($this->templateSlug !== '') {
            Notices::error(sprintf(
                'Block template "%s" was not found.',
                $slug
            ));
        }

        return $this->renderMount($this->propsForReact($values));
    }

    /**
     * Includes a theme template and returns its output.
     *
     * @param string               $slug Template slug, without `.php`.
     * @param array<string, mixed> $args Variables extracted into the template and available as `$args`.
     *
     * @return string|null Captured output when the file exists, or null when it does not.
     */
    private static function captureTemplate(string $slug, array $args): ?string
    {
        if ($slug === '') {
            return null;
        }

        if (!array_key_exists($slug, self::$locatedTemplates)) {
            $found = locate_template($slug . '.php', false, false);
            self::$locatedTemplates[$slug] = is_string($found) ? $found : '';
        }

        if (self::$locatedTemplates[$slug] === '') {
            return null;
        }

        ob_start();
        get_template_part($slug, null, $args);

        return (string) ob_get_clean();
    }

    /**
     * Registers the public render filter for core blocks.
     *
     * `new Block()` calls this. Call it alone when the theme replaces core
     * blocks and does not register a block of its own.
     *
     * @see https://developer.wordpress.org/reference/hooks/render_block/
     */
    public static function hookCoreTemplates(): void
    {
        static $hooked = false;

        if ($hooked || !function_exists('add_filter')) {
            return;
        }

        $hooked = true;
        add_filter('render_block', [self::class, 'filterCoreBlock'], 10, 3);
    }

    /**
     * Replaces a core block with a theme template when that file exists.
     *
     * Names registered by this library keep the HTML returned by `renderBlock()`.
     * Any other non-core name keeps the HTML WordPress passed in.
     *
     * @param string               $content  HTML WordPress rendered, including inner blocks.
     * @param array<string, mixed> $block    Parsed block.
     * @param \WP_Block            $instance Block instance.
     *
     * @return string Template output, or the original HTML.
     */
    public static function filterCoreBlock(string $content, array $block, \WP_Block $instance): string
    {
        $name = isset($block['blockName']) && is_string($block['blockName'])
            ? $block['blockName']
            : '';

        if (!str_starts_with($name, 'core/') || isset(self::$libraryBlocks[$name])) {
            return $content;
        }

        $rendered = self::captureTemplate(Builder::blockTemplates() . '/' . $name, [
            'attributes' => is_array($block['attrs'] ?? null) ? $block['attrs'] : [],
            'content'    => $content,
            'block'      => $instance,
            'name'       => $name,
        ]);

        return is_string($rendered) ? $rendered : $content;
    }

    /**
     * Copies field values and replaces image IDs with media objects.
     *
     * @param array<string, mixed> $values Normalized field values.
     *
     * @return array<string, mixed> Props shared by the theme template and `data-wpb-props`.
     */
    private function propsForReact(array $values): array
    {
        return $this->resolveImages($this->fieldSchemas(), $values);
    }

    /**
     * Replaces image fields in one level of values.
     *
     * @param list<array<string, mixed>> $fields Field schemas for this level.
     * @param array<string, mixed>       $values Values for this level.
     *
     * @return array<string, mixed> Values with image fields resolved.
     */
    private function resolveImages(array $fields, array $values): array
    {
        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');

            if ($name === '' || !array_key_exists($name, $values)) {
                continue;
            }

            $type = (string) ($field['type'] ?? '');

            if ($type === 'image') {
                $values[$name] = $this->imageProps($values[$name]);
                continue;
            }

            if ($type !== 'repeater' || !is_array($values[$name])) {
                continue;
            }

            $children = is_array($field['fields'] ?? null) ? $field['fields'] : [];
            $items = [];

            foreach ($values[$name] as $item) {
                $items[] = is_array($item)
                    ? $this->resolveImages($children, $item)
                    : $item;
            }

            $values[$name] = $items;
        }

        return $values;
    }

    /**
     * Builds the React image object for one attachment.
     *
     * @param mixed $value Stored attachment ID.
     *
     * @return array{id: int, url: string, alt: string, width: int, height: int, srcset: string}|null Null when the attachment has no URL.
     */
    private function imageProps(mixed $value): ?array
    {
        $id = (int) $value;

        if ($id <= 0) {
            return null;
        }

        $url = wp_get_attachment_image_url($id, 'large');

        if (!is_string($url) || $url === '') {
            return null;
        }

        $src = wp_get_attachment_image_src($id, 'large');
        $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
        $srcset = wp_get_attachment_image_srcset($id, 'large');

        return [
            'id'     => $id,
            'url'    => $url,
            'alt'    => is_string($alt) ? $alt : '',
            'width'  => is_array($src) ? (int) $src[1] : 0,
            'height' => is_array($src) ? (int) $src[2] : 0,
            'srcset' => is_string($srcset) ? $srcset : '',
        ];
    }

    /**
     * Prints the React mount point.
     *
     * An empty props array is encoded as `{}` so repeater lists inside a
     * non-empty payload stay JSON arrays. `JSON_FORCE_OBJECT` is not used.
     *
     * @param array<string, mixed> $props Props for `data-wpb-props`.
     *
     * @return string Mount element.
     */
    private function renderMount(array $props): string
    {
        if ($props === []) {
            $props = new \stdClass();
        }

        $json = wp_json_encode($props);

        if (!is_string($json)) {
            $json = '{}';
        }

        $inner = '';

        if ($this->isBlockRendererRequest()) {
            $inner = '<p class="wpb-block-placeholder">' . esc_html($this->title) . '</p>';
        }

        return sprintf(
            '<div %1$s data-wpb-block="%2$s" data-wpb-props="%3$s">%4$s</div>',
            get_block_wrapper_attributes([
                'class' => 'wpb-block',
            ]),
            esc_attr($this->name),
            esc_attr($json),
            $inner
        );
    }

    /**
     * Queues the block for registration on `init`.
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

        Builder::onInit([$this, 'registerBlock']);


        return true;
    }

    /**
     * Registers the block with WordPress.
     *
     * @see https://developer.wordpress.org/reference/functions/register_block_type/
     */
    public function registerBlock(): void
    {
        $this->registerStylesheets();
        $this->prepareStorage();
        $this->setArgs();

        self::$libraryBlocks[$this->name] = true;

        register_block_type($this->name, $this->args);
        $this->queuePostTypeRestriction();
    }

    /**
     * Checks post types after `init`, when custom post types exist.
     */
    private function queuePostTypeRestriction(): void
    {
        if ($this->postTypes === [] || !function_exists('add_action')) {
            return;
        }

        if (function_exists('did_action') && did_action('wp_loaded')) {
            $this->applyPostTypeRestriction();

            return;
        }

        if ($this->postTypeRestrictionQueued) {
            return;
        }

        $this->postTypeRestrictionQueued = true;

        add_action('wp_loaded', [$this, 'applyPostTypeRestriction'], PHP_INT_MAX);
    }

    /**
     * Drops unknown post types and hides the block outside the remaining ones.
     *
     * @see https://developer.wordpress.org/reference/functions/post_type_exists/
     * @see https://developer.wordpress.org/reference/hooks/allowed_block_types_all/
     */
    public function applyPostTypeRestriction(): void
    {
        $valid = [];

        foreach ($this->postTypes as $postType) {
            if ($postType === '' || !post_type_exists($postType)) {
                Notices::error(sprintf(
                    'Block "%s" cannot be limited to post type "%s" because that post type is not registered.',
                    $this->name,
                    $postType
                ));

                continue;
            }

            $valid[] = $postType;
        }

        $this->postTypes = $valid;

        if (
            $valid === []
            || $this->name === ''
            || !isset(self::$libraryBlocks[$this->name])
            || $this->postTypeFilterAdded
        ) {
            return;
        }

        $this->postTypeFilterAdded = true;

        add_filter('allowed_block_types_all', [$this, 'filterAllowedBlockTypes'], 10, 2);
    }

    /**
     * Removes this block from editors whose post type is not allowed.
     *
     * @param bool|list<string>        $allowed Block names, or true for every block.
     * @param \WP_Block_Editor_Context $context Editor context.
     *
     * @return bool|list<string> Allowed block names.
     *
     * @see https://developer.wordpress.org/reference/hooks/allowed_block_types_all/
     */
    public function filterAllowedBlockTypes(bool|array $allowed, \WP_Block_Editor_Context $context): bool|array
    {
        $postType = $context->post instanceof \WP_Post ? $context->post->post_type : '';

        if ($this->postTypes === [] || in_array($postType, $this->postTypes, true)) {
            return $allowed;
        }

        $names = $allowed === true
            ? array_keys(\WP_Block_Type_Registry::get_instance()->get_all_registered())
            : $allowed;

        return array_values(array_diff($names, [$this->name]));
    }

    /**
     * Enqueues block styles in the editor.
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
     * Enqueues the convention CSS and JavaScript for this render.
     *
     * @see https://developer.wordpress.org/reference/functions/get_theme_file_path/
     * @see https://developer.wordpress.org/reference/functions/get_theme_file_uri/
     */
    private function enqueueBlockAssets(): void
    {
        if ($this->assetKind === '' || $this->name === '') {
            return;
        }

        if (!function_exists('get_theme_file_path') || !function_exists('get_theme_file_uri')) {
            return;
        }

        $stem = Builder::blockAssets() . '/' . str_replace('/', '-', $this->name);
        $handle = 'wpb-' . str_replace('/', '-', $this->name);

        if ($this->assetKind === 'style' || $this->assetKind === 'both') {
            $this->enqueueThemeFile($stem . '.css', $handle, 'style');
        }

        if ($this->assetKind === 'script' || $this->assetKind === 'both') {
            $this->enqueueThemeFile($stem . '.js', $handle, 'script');
        }
    }

    /**
     * Registers and enqueues one theme file when it exists.
     *
     * @param string $relative Path relative to the theme.
     * @param string $handle   Style or script handle.
     * @param 'style'|'script' $kind Asset kind.
     */
    private function enqueueThemeFile(string $relative, string $handle, string $kind): void
    {
        $path = get_theme_file_path($relative);

        if (!is_string($path) || !is_readable($path)) {
            return;
        }

        $url = get_theme_file_uri($relative);

        if (!is_string($url) || $url === '') {
            return;
        }

        $mtime = filemtime($path);
        $version = $mtime === false ? false : (string) $mtime;

        if ($kind === 'style') {
            if (function_exists('wp_register_style')) {
                wp_register_style($handle, $url, [], $version);
            }

            if (function_exists('wp_enqueue_style')) {
                wp_enqueue_style($handle);
            }

            return;
        }

        if (function_exists('wp_register_script')) {
            wp_register_script($handle, $url, [], $version, true);
        }

        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script($handle);
        }
    }

    /**
     * Builds a style handle from the block name.
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
     * @param string $name Field name.
     *
     * @return bool False when the name is empty or reserved. The message is reported in the WordPress admin.
     */
    public static function assertFieldName(string $name): bool
    {
        if ($name === '') {
            Notices::error('Block attribute name cannot be empty.');

            return false;
        }

        if ($name === self::INSTANCE_ATTRIBUTE) {
            throw new InvalidArgumentException(
                'Field name "wpbId" is reserved. It identifies each insertion of a block stored in post meta.'
            );
        }

        if (in_array($name, self::RESERVED_ATTRIBUTE_NAMES, true)) {
            Notices::error(sprintf(
                'Attribute name "%s" is reserved by block supports. Reserved names: %s.',
                $name,
                implode(', ', self::RESERVED_ATTRIBUTE_NAMES)
            ));

            return false;
        }

        return true;
    }

    /**
     * Rejects reserved or empty attribute names.
     *
     * @param string $name Attribute name.
     *
     * @return bool False when the name is empty or reserved.
     */
    private function assertAttributeName(string $name): bool
    {
        return self::assertFieldName($name);
    }

    /**
     * Rejects attribute schemas that cannot generate Inspector controls.
     *
     * @param string               $name   Attribute name.
     * @param array<string, mixed> $schema Attribute schema.
     *
     * @return bool False when the schema is not supported. Each problem is reported in the WordPress admin.
     */
    private function assertAttributeSchema(string $name, array $schema): bool
    {
        $type = $schema['type'] ?? '';
        $valid = true;

        if (!in_array($type, self::ALLOWED_ATTRIBUTE_TYPES, true)) {
            Notices::error(sprintf(
                'Invalid type for attribute "%s". Allowed types: %s.',
                $name,
                implode(', ', self::ALLOWED_ATTRIBUTE_TYPES)
            ));
            $valid = false;
        }

        if (isset($schema['source'])) {
            Notices::error(sprintf(
                'Attribute "%s" cannot use a source. Choose post meta or post content with saveAsContent().',
                $name
            ));
            $valid = false;
        }

        if (($schema['role'] ?? '') === 'local') {
            Notices::error(sprintf(
                'Attribute "%s" uses role "local" and will not generate Inspector controls.',
                $name
            ));
            $valid = false;
        }

        if (isset($schema['enum']) && $type !== 'string') {
            Notices::error(sprintf(
                'Enum attribute "%s" must use type "string".',
                $name
            ));
            $valid = false;
        }

        if (isset($schema['enum']) && (!is_array($schema['enum']) || $schema['enum'] === [])) {
            Notices::error(sprintf(
                'Enum attribute "%s" requires a non-empty list of values.',
                $name
            ));
            $valid = false;
        }

        return $valid;
    }

    /**
     * Rejects a scalar name that belongs to a repeater.
     *
     * @param string $name Field name.
     *
     * @return bool False when the name is already a repeater. The message is reported in the WordPress admin.
     */
    private function assertFieldAvailable(string $name): bool
    {
        if (isset($this->repeaters[$name])) {
            Notices::error(sprintf(
                'Field name "%s" is already used on this block.',
                $name
            ));

            return false;
        }

        return true;
    }

    /**
     * Records a top-level field in declaration order.
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
                return FieldValue::normalizeStoredMeta($fields, $value);
            },
            'auth_callback'     => static function (mixed $allowed, mixed $metaKey, mixed $objectId): bool {
                return current_user_can('edit_post', (int) $objectId);
            },
            'show_in_rest'      => [
                'schema' => [
                    'type'                 => 'object',
                    'additionalProperties' => true,
                ],
            ],
        ]);
    }

    /**
     * Adds the block schema to the shared editor script.
     *
     * @see https://developer.wordpress.org/reference/hooks/enqueue_block_editor_assets/
     */
    private function queueEditorConfig(): void
    {
        self::$editorBlocks[$this->name] = [
            'storage'   => $this->saveAsContent ? 'content' : 'meta',
            'attribute' => $this->saveAsContent ? '' : $this->metaKey(),
            'instance'  => $this->saveAsContent ? '' : self::INSTANCE_ATTRIBUTE,
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
     * The handle depends on the block editor packages. When the file has a
     * public URL it is deferred with them, so the field UI runs after
     * `wp.blockEditor` exists. A library outside the content directory has no
     * URL. WordPress then treats the handle as an alias, and an alias cannot
     * use `defer`. The script is printed inline on that handle instead.
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
        $args = ['in_footer' => true];

        if ($url !== '') {
            $args['strategy'] = 'defer';
        }

        wp_register_script(
            'wpb-block-editor',
            $url !== '' ? $url : false,
            ['wp-hooks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-blocks', 'wp-block-editor', 'wp-data'],
            is_readable($path) ? (string) filemtime($path) : false,
            $args
        );
    }

    /**
     * Enqueues the shared block editor script.
     *
     * When the library lives outside the content directory, the file URL is
     * empty and the script is printed inline on the same handle.
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
     * Stores repeater arrays in the block comment, or the insertion id when values live in post meta.
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
        $this->args['attributes'][self::INSTANCE_ATTRIBUTE] = [
            'type'    => 'string',
            'default' => '',
        ];
    }

    /**
     * Resolves normalized field values for one insertion.
     *
     * Post meta stores every insertion under {@see metaKey()}. The block comment
     * contributes `wpbId`, and the render callback receives that insertion's fields.
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
        $instanceId = isset($attributes[self::INSTANCE_ATTRIBUTE])
            ? (string) $attributes[self::INSTANCE_ATTRIBUTE]
            : '';
        $stored = [];
        $postId = (int) ($block->context['postId'] ?? get_the_ID());

        if ($postId > 0) {
            $stored = FieldValue::bagForInstance(get_post_meta($postId, $key, true), $instanceId);
        }

        $normalized = FieldValue::normalizeBag($fields, $stored);

        foreach ($attributes as $name => $value) {
            if ($name === $key || $name === self::INSTANCE_ATTRIBUTE || array_key_exists($name, $normalized)) {
                continue;
            }

            $normalized[$name] = $value;
        }

        return $normalized;
    }

    /**
     * Determines whether the current request is the editor's server render preview.
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
