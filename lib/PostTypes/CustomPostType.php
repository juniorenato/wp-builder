<?php

namespace WPB\PostTypes;

/**
 * -----------------------------------------------------------------------------
 * Post Type creator
 * -----------------------------------------------------------------------------
 *
 * @see https://developer.wordpress.org/reference/functions/register_post_type/
 * @since v0.1.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 * @package juniorenato/wp-builder
 */
class CustomPostType extends PostType
{

    public function __construct(?string $postType = null, ?string $singular = null, ?string $plural = null, bool $male = true)
    {
        $this->valueType = 'post';

        $this->init();

        if(1 == 1
            && $postType
            && $singular
            && $plural
        ) { $this->register($postType, $singular, $plural, $male); }
    }

    /**
     * -------------------------------------------------------------------------
     * Reset the class
     * -------------------------------------------------------------------------
     *
     * @return CustomPostType
     */
    protected function init(): CustomPostType
    {
        $this->postType = '';
        $this->singular = '';
        $this->plural = '';
        $this->male = true;
        $this->labels = [];
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
     * -------------------------------------------------------------------------
     * Set default arguments
     * -------------------------------------------------------------------------
     *
     * @return void
     */
    protected function setArgs(): void
    {
        $args = [
            'label'           => ucfirst($this->plural),
            'labels'          => $this->labels,
            'description'     => (string) get_option('_'. $this->postType .'_description'),
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

    public function register(?string $postType = null, ?string $singular = null, ?string $plural = null, bool $male = true): bool
    {
        if(1 == 1
            && $postType
            && is_string($postType)
            && $singular
            && $plural
        ) {
            $this->setPostType($postType);
            $this->setLabels($singular, $plural, $male);
        }

        // Return error if any configuration is missing
        if(1 == 0
            || !$this->postType
            || !$this->singular
            || !$this->plural
        ) { return false; }

        // Set args
        $this->setArgs();

        if(1 == 0
            || $this->metaBoxes
            || $this->metaFields
        ) { $this->registerMetaBoxes(); }

        // WordPress - Register Post Type
        add_action('init', [$this, 'registerPostType']);

        return true;
    }

    /**
     * -------------------------------------------------------------------------
     * Register post type callback
     * -------------------------------------------------------------------------
     *
     * @return void
     */
    public function registerPostType(): void
    {
        register_post_type($this->postType, $this->args);
    }

}
