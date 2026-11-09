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
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 *
 * @see https://developer.wordpress.org/reference/functions/add_meta_box/
 */
class MetaBox
{
    use AdminForm;

    /**
     * Meta box identifier.
     */
    public string $metaBox;

    /**
     * Meta box title.
     */
    public string $title;

    /**
     * Screens where the meta box is shown.
     *
     * @var list<mixed>
     */
    private array $screen;

    /**
     * Meta box context.
     */
    private string $context;

    /**
     * Meta box priority.
     */
    private string $priority;

    /**
     * Callback arguments passed to `add_meta_box()`.
     *
     * @var array<int|string, mixed>
     */
    private array $args;

    /**
     * Whether the meta box hooks have been added.
     */
    private bool $hooked = false;

    /**
     * Initializes a new meta box.
     */
    public function __construct()
    {
        $this->init();
        $this->valueType = 'post';
    }

    /**
     * Resets the meta box configuration to defaults.
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
     * @see https://developer.wordpress.org/reference/hooks/add_meta_boxes/
     */
    public function setMetaboxes(): void
    {
        if (!$this->metaBox) {
            $this->metaBox = sanitize_title($this->title);
        }

        if ($this->hooked) {
            return;
        }

        $this->hooked = true;

        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post', [$this, 'saveMetaBox'], 10, 2);
    }

    /**
     * Saves this meta box when the submitted post uses one of its screens.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object being saved.
     *
     * @see https://developer.wordpress.org/reference/hooks/save_post/
     */
    public function saveMetaBox($post_id, $post): void
    {
        if (!$post instanceof \WP_Post || !$this->servesPostType($post->post_type)) {
            return;
        }

        $this->id = (int) $post_id;
        $this->savePost($post_id, $post);
    }

    /**
     * Determines whether the meta box is registered for a post type.
     *
     * @param string $postType Post type key.
     *
     * @return bool True when the screen list includes the post type.
     */
    private function servesPostType(string $postType): bool
    {
        foreach ($this->screen as $screen) {
            if (is_string($screen) && $screen === $postType) {
                return true;
            }

            if (is_object($screen) && isset($screen->post_type) && $screen->post_type === $postType) {
                return true;
            }
        }

        return false;
    }

    /**
     * Registers the meta box with WordPress.
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
     * @global \WP_Post $post Current post object.
     *
     * @see https://developer.wordpress.org/reference/functions/add_meta_box/
     */
    public function registerMetabox(): void
    {
        global $post;

        $this->id = $post->ID ?? 0;
        $this->fieldsType = 'box';

        $this->addNonceName();
        $this->field();
    }
}
