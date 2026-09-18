<?php

/**
 * Standalone meta box factory.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\MetaBoxes
 */

namespace WPB\MetaBoxes;

use WPB\Builder;
use WPB\Forms\AdminForm;

/**
 * Registers a meta box and its fields in the WordPress admin.
 *
 * @since  0.3.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 *
 * @see https://developer.wordpress.org/reference/functions/add_meta_box/
 */
class MetaBox
{
    use AdminForm;

    /**
     * Meta box identifier.
     *
     * @since 0.3.0
     */
    public string $metaBox;

    /**
     * Meta box title.
     *
     * @since 0.3.0
     */
    public string $title;

    /**
     * Screens where the meta box is shown.
     *
     * @since 0.3.0
     *
     * @var list<mixed>
     */
    private array $screen;

    /**
     * Meta box context.
     *
     * @since 0.3.0
     */
    private string $context;

    /**
     * Meta box priority.
     *
     * @since 0.3.0
     */
    private string $priority;

    /**
     * Callback arguments passed to `add_meta_box()`.
     *
     * @since 0.3.0
     *
     * @var array<int|string, mixed>
     */
    private array $args;

    /**
     * Initializes a new meta box.
     *
     * @since 0.3.0
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Resets the meta box configuration to defaults.
     *
     * @since 0.3.0
     */
    private function init(): void
    {
        $this->metaBox  = '';
        $this->title    = '';
        $this->screen   = [];
        $this->context  = 'normal';
        $this->priority = 'default';
        $this->args     = [
            '__back_compat_meta_box' => false,
        ];
        $this->fields = [];
    }

    /**
     * Sets the meta box identifier.
     *
     * @since 0.3.0
     *
     * @param string $metaBoxId Unsanitized identifier.
     *
     * @see https://developer.wordpress.org/reference/functions/add_meta_box/
     */
    public function metaBoxId(string $metaBoxId): void
    {
        $this->metaBox = sanitize_title($metaBoxId);
    }

    /**
     * Sets the meta box title and resets previous field state when reused.
     *
     * @since 0.3.0
     *
     * @param string $title Meta box title.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/functions/add_meta_box/
     */
    public function title(string $title): static
    {
        if ($this->title) {
            $this->init();
        }

        $this->title = ucfirst($title);

        return $this;
    }

    /**
     * Adds a screen where the meta box should appear.
     *
     * @since 0.3.0
     *
     * @param mixed $screen Post type slug, screen ID, or `WP_Screen`.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/functions/add_meta_box/
     */
    public function screen(mixed $screen): static
    {
        $this->screen[] = $screen;

        return $this;
    }

    /**
     * Sets the meta box context.
     *
     * @since 0.3.0
     *
     * @param string $context Context (`normal`, `side`, or `advanced`).
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/functions/add_meta_box/
     */
    public function context(string $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Sets the meta box priority.
     *
     * @since 0.3.0
     *
     * @param string $priority Priority (`high`, `core`, `default`, or `low`).
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/functions/add_meta_box/
     */
    public function priority(string $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Sets callback arguments passed to the meta box.
     *
     * @since 0.3.0
     *
     * @param mixed $callbackArgs Argument map or a single argument.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/functions/add_meta_box/
     */
    public function args(mixed $callbackArgs): static
    {
        if (is_array($callbackArgs)) {
            $this->args = array_merge($this->args, $callbackArgs);
        } else {
            $this->args[] = $callbackArgs;
        }

        return $this;
    }

    /**
     * Queues the meta box for registration.
     *
     * @since 0.3.0
     *
     * @param string|null $title  Meta box title.
     * @param mixed       $screen Screen where the meta box should appear.
     *
     * @return bool True when required configuration is present.
     *
     * @see https://developer.wordpress.org/reference/functions/add_meta_box/
     * @see https://developer.wordpress.org/reference/hooks/add_meta_boxes/
     */
    public function register(?string $title = null, mixed $screen = null): bool
    {
        if ($title && $screen) {
            $this->title($title);
            $this->screen($screen);
        }

        if (!$this->metaBox || !$this->title || !$this->screen) {
            return false;
        }

        Builder::onInit([$this, 'setMetaboxes']);

        return true;
    }

    /**
     * Hooks the meta box into `add_meta_boxes`.
     *
     * @since 0.3.0
     *
     * @see https://developer.wordpress.org/reference/hooks/add_meta_boxes/
     */
    public function setMetaboxes(): void
    {
        if (!$this->metaBox) {
            $this->metaBox = sanitize_title($this->title);
        }

        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
    }

    /**
     * Registers the meta box with WordPress.
     *
     * @since 0.3.0
     *
     * @see https://developer.wordpress.org/reference/functions/add_meta_box/
     */
    public function addMetaBoxes(): void
    {
        add_meta_box(
            $this->metaBox,
            $this->title,
            [$this, 'registerMetabox'],
            $this->screen,
            $this->context,
            $this->priority,
            $this->args
        );
    }

    /**
     * Renders the meta box fields for the current post.
     *
     * @since 0.3.0
     *
     * @global \WP_Post $post Current post object.
     *
     * @see https://developer.wordpress.org/reference/functions/add_meta_box/
     */
    public function registerMetabox(): void
    {
        global $post;

        $this->id = $post->ID ?? 0;
        $this->fieldsType = 'box';

        $this->field();
    }
}
