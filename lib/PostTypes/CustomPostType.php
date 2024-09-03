<?php

namespace WPB\PostTypes;

/**
 * -----------------------------------------------------------------------------
 * Post Type creator
 * -----------------------------------------------------------------------------
 *
 * @see https://developer.wordpress.org/reference/functions/register_post_type/
 * @since 0.1.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 * @package juniorenato/wp-builder
 */
class CustomPostType extends PostType
{
    protected string $singular;
    protected string $plural;
    protected bool $male;

    public function __construct()
    {
        $this->valueType = 'post';

        $this->init();
    }
    /**
     * -------------------------------------------------------------------------
     * Reset the class
     * -------------------------------------------------------------------------
     *
     * @return void
     */
    private function init(): CustomPostType
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
     * Add post type name
     * -------------------------------------------------------------------------
     *
     * @param string $postType
     * @return CustomPostType
     */
    public function setPostType(string $postType): CustomPostType
    {
        if($this->postType) $this->init();

        $this->postType = sanitize_title($postType);

        return $this;
    }

    /**
     * -------------------------------------------------------------------------
     * Set labels
     * -------------------------------------------------------------------------
     *
     * @param string $singular
     * @param string $plural
     * @param boolean $female
     * @return CustomPostType
     *
     * @see https://developer.wordpress.org/reference/functions/get_post_type_labels/
     */
    public function setLabels(string $singular, string $plural, bool $male = true): CustomPostType
    {
        $this->singular = mb_strtolower($singular);
        $this->plural = mb_strtolower($plural);
        $this->male = $male;

        $new       = ($this->male) ? __('new', 'wpb')       : __('female_new', 'wpb');
        $found     = ($this->male) ? __('found', 'wpb')     : __('female_found', 'wpb');
        $parent    = ($this->male) ? __('parent', 'wpb')    : __('female_parent', 'wpb');
        $all       = ($this->male) ? __('all', 'wpb')       : __('female_all', 'wpb');
        $item      = ($this->male) ? __('this', 'wpb')      : __('female_this', 'wpb');
        $published = ($this->male) ? __('published', 'wpb') : __('female_published', 'wpb');
        $scheduled = ($this->male) ? __('scheduled', 'wpb') : __('female_scheduled', 'wpb');
        $updated   = ($this->male) ? __('updated', 'wpb')   : __('female_updated', 'wpb');

        $this->labels = [
            'name'                     => ucfirst($this->plural),
            'singular_name'            => ucfirst($this->singular),
            'add_new'                  => ucfirst(sprintf(__('add %s', 'wpb'), $new)),
            'add_new_item'             => ucfirst(sprintf(__('add %s %s', 'wpb'), $new, $this->singular)),
            'edit_item'                => ucfirst(sprintf(__('edit %s', 'wpb'), $this->singular)),
            'new_item'                 => ucfirst($new .' '. $this->singular),
            'view_item'                => ucfirst(sprintf(__('view %s', 'wpb'), $this->singular)),
            'view_items'               => ucfirst(sprintf(__('view %s', 'wpb'), $this->plural)),
            'search_items'             => ucfirst(sprintf(__('search %s', 'wpb'), $this->plural)),
            'not_found'                => ucfirst(sprintf(__('%s not %s', 'wpb'), $this->plural, $found)),
            'not_found_in_trash'       => ucfirst(sprintf(__('%s not %s in trash', 'wpb'), $this->plural, $found)),
            'parent_item_colon'        => ucfirst($this->singular .' '. $parent),
            'all_items'                => ucfirst($all .' '. $this->plural),
            'archives'                 => ucfirst(sprintf(__('archives of %s', 'wpb'), $this->plural)),
            'attributes'               => ucfirst(__('attributes', 'wpb')),
            'insert_into_item'         => ucfirst(sprintf(__('insert into %s', 'wpb'), $this->plural)),
            'uploaded_to_this_item'    => ucfirst(sprintf(__('updated to %s %s', 'wpb'), $item ,$this->singular)),
            'menu_name'                => ucfirst($this->plural),
            'filter_items_list'        => ucfirst($this->plural),
            'items_list_navigation'    => ucfirst($this->plural),
            'items_list'               => ucfirst($this->plural),
            'item_published'           => ucfirst($this->singular . ' '. $published),
            'item_published_privately' => ucfirst(sprintf(__('%s %s privately', 'wpb'), $this->singular, $published)),
            'item_reverted_to_draft'   => ucfirst(__('%s reverted to draft', 'wpb', $this->singular)),
            'item_trashed'             => ucfirst(__('%s trashed', 'wpb')),
            'item_scheduled'           => ucfirst($this->singular .' '. $scheduled),
            'item_updated'             => ucfirst($this->singular .' '. $updated),
            'item_link'                => ucfirst(sprintf(__('link to %s', 'wpb'), $this->plural)),
            'item_link_description'    => ucfirst(sprintf(__('a link to %s', 'wpb'), $this->singular)),
        ];

        return $this;
    }

    /**
     * -------------------------------------------------------------------------
     * Set default arguments
     * -------------------------------------------------------------------------
     *
     * @return void
     */
    private function setArgs(): void
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

    /**
     * -------------------------------------------------------------------------
     * Add taxonomies
     * -------------------------------------------------------------------------
     *
     * @param string|list<string> $taxonomies
     * @return CustomPostType
     */
    public function taxonomies($taxonomies): CustomPostType
    {
        if(is_array($taxonomies)) {
            $this->taxonomies = array_merge($this->taxonomies, $taxonomies);
        }

        else {
            $this->taxonomies[] = $taxonomies;

            return $this;
        }
    }

    /**
     * -------------------------------------------------------------------------
     * Add icon
     * -------------------------------------------------------------------------
     *
     * @param string $icon
     * @return CustomPostType
     *
     * @see https://developer.wordpress.org/resource/dashicons/
     */
    public function icon(string $icon): CustomPostType
    {
        $this->args['menu_icon'] = $icon;

        return $this;
    }

    /**
     * -------------------------------------------------------------------------
     * Edit menu position
     * -------------------------------------------------------------------------
     *
     * @param string $position
     * @return CustomPostType
     *
     * @see https://developer.wordpress.org/resource/dashicons/
     */
    public function position(string $position): CustomPostType
    {
        $this->args['menu_position'] = $position;

        return $this;
    }

    /**
     * -------------------------------------------------------------------------
     * Register the post type
     * -------------------------------------------------------------------------
     *
     * @param string|boolean $postType
     * @param string $singular
     * @param string $plural
     * @param boolean $male
     * @param boolean $reset
     * @return CustomPostType
     */
    public function add(?string $postType = null, ?string $singular = null, ?string $plural = null, bool $male = true): bool
    {
        $this->init();

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

        return true;
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
