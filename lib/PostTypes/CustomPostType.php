<?php

/**
 * Custom post type factory.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\PostTypes
 */

namespace WPB\PostTypes;

use WPB\Builder;

/**
 * Registers a custom post type with WordPress.
 *
 * @since  0.1.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 *
 * @see https://developer.wordpress.org/reference/functions/register_post_type/
 */
class CustomPostType extends PostType
{
    /**
     * Optionally registers a post type immediately.
     *
     * @since 0.1.0
     *
     * @param string|null $postType Post type key.
     * @param string|null $singular Singular label.
     * @param string|null $plural   Plural label.
     * @param bool        $male     Whether to use masculine translations.
     */
    public function __construct(?string $postType = null, ?string $singular = null, ?string $plural = null, bool $male = true)
    {
        $this->valueType = 'post';

        $this->init();

        if ($postType && $singular && $plural) {
            $this->register($postType, $singular, $plural, $male);
        }
    }

    /**
     * Resets the post type configuration to defaults.
     *
     * @since 0.1.0
     *
     * @return static
     */
    protected function init(): static
    {
        $this->postType = '';
        $this->singular = '';
        $this->plural = '';
        $this->male = true;
        $this->labels = [];
        $this->defaultLabelsBuilt = false;
        $this->rewrite = [];
        $this->taxonomies = [];
        $this->supports = [
            'title',
            'editor',
        ];
        $this->args = [];

        return $this;
    }

    /**
     * Merges default arguments with user-provided arguments.
     *
     * @since 0.1.0
     *
     * @see https://developer.wordpress.org/reference/functions/register_post_type/
     */
    protected function setArgs(): void
    {
        $args = [
            'label'           => ucfirst($this->plural),
            'labels'          => $this->labels,
            'description'     => (string) get_option('_' . $this->postType . '_description'),
            'public'          => true,
            'hierarchical'    => false,
            'show_in_rest'    => true,
            'menu_position'   => 5,
            'capability_type' => 'post',
            'supports'        => $this->supports,
            'taxonomies'      => $this->taxonomies,
            'has_archive'     => false,
            'rewrite'         => $this->rewrite,
            'query_var'       => sanitize_title($this->plural),
        ];

        $this->args = array_merge($args, $this->args);
    }

    /**
     * Queues the post type for registration on `init`.
     *
     * @since 0.1.0
     *
     * @param string|null $postType Post type key.
     * @param string|null $singular Singular label.
     * @param string|null $plural   Plural label.
     * @param bool        $male     Whether to use masculine translations.
     *
     * @return bool True when required configuration is present.
     *
     * @see https://developer.wordpress.org/reference/functions/register_post_type/
     * @see https://developer.wordpress.org/reference/hooks/init/
     */
    public function register(?string $postType = null, ?string $singular = null, ?string $plural = null, bool $male = true): bool
    {
        if ($postType && $singular && $plural) {
            $this->setPostType($postType);
            $this->setLabels($singular, $plural, $male);
        }

        if (!$this->postType || !$this->singular || !$this->plural) {
            return false;
        }

        Builder::onInit([$this, 'registerPostType']);

        return true;
    }

    /**
     * Registers the post type with WordPress.
     *
     * @since 0.1.0
     *
     * @see https://developer.wordpress.org/reference/functions/register_post_type/
     */
    public function registerPostType(): void
    {
        if (!$this->defaultLabelsBuilt) {
            $this->buildDefaultLabels();
        }

        $this->setArgs();

        if ($this->metaBoxes || $this->metaFields) {
            $this->registerMetaBoxes();
        }

        register_post_type($this->postType, $this->args);
    }
}
