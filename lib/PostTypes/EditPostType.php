<?php

/**
 * Existing post type editor.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\PostTypes
 */

namespace WPB\PostTypes;

/**
 * Overrides arguments of an already registered post type.
 *
 * @since  0.4.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class EditPostType extends PostType
{
    /**
     * Optionally loads and updates a post type immediately.
     *
     * @since 0.4.0
     *
     * @param string|null              $postType Post type key.
     * @param array<string, mixed>|null $args    Arguments to merge.
     */
    public function __construct(?string $postType = null, ?array $args = null)
    {
        $this->valueType = 'post';

        $this->init();

        if ($postType) {
            $this->setPostType($postType);
        }

        if ($postType && $args) {
            $this->args($args);
            $this->edit();
        }
    }

    /**
     * Resets the editor state.
     *
     * @since 0.4.0
     *
     * @return static
     */
    protected function init(): static
    {
        $this->postType = '';
        $this->labels = [];
        $this->rewrite = [];
        $this->taxonomies = [];
        $this->supports = [];
        $this->args = [];

        return $this;
    }

    /**
     * Loads the current arguments of an existing post type.
     *
     * @since 0.4.0
     *
     * @param string $postType Post type key.
     *
     * @return static
     */
    #[\Override]
    public function setPostType(string $postType): static
    {
        $postType = (array) get_post_type_object($postType);

        if ($postType) {
            $this->postType = $postType['name'];
            $postType['labels'] = (array) $postType['labels'];

            foreach ($postType as $key => $val) {
                if (in_array($key, self::ARGS, true)) {
                    $this->args($key, $val);
                }
            }
        }

        return $this;
    }

    /**
     * Applies pending argument changes to the post type.
     *
     * @since 0.4.0
     *
     * @param string|null               $postType Post type key.
     * @param array<string, mixed>|null $args     Arguments to merge.
     */
    public function edit(?string $postType = null, ?array $args = null): void
    {
        if ($postType) {
            $this->setPostType($postType);
        }

        if ($args) {
            $this->args($args);
        }

        if ($this->metaBoxes || $this->metaFields) {
            $this->registerMetaBoxes();
        }

        add_filter('register_' . $this->postType . '_post_type_args', [$this, 'registerArgs']);
    }

    /**
     * Filters registered post type arguments.
     *
     * @since 0.4.0
     *
     * @param array<string, mixed> $args Current post type arguments.
     *
     * @return array<string, mixed> Merged arguments.
     */
    public function registerArgs(array $args): array
    {
        return array_merge($args, $this->args);
    }
}
