<?php

namespace WPB\PostTypes;

use WPB\Forms\AdminForm;
use WPB\Forms\PostTypeMetaBox;


/**
 * -----------------------------------------------------------------------------
 * Post Type
 * -----------------------------------------------------------------------------
 *
 * @since v0.2.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 * @package juniorenato/wp-builder
 */
class PostType
{
    use AdminForm;
    use PostTypeMetaBox;

    protected const ARGS = [
        'label',
        'labels',
        'description',
        'public',
        'hierarchical',
        'exclude_from_search',
        'publicly_queryable',
        'show_ui',
        'show_in_menu',
        'show_in_nav_menus',
        'show_in_admin_bar',
        'show_in_rest',
        'rest_base',
        'rest_namespace',
        'rest_controller_class',
        'autosave_rest_controller_class',
        'revisions_rest_controller_class',
        'late_route_registration',
        'menu_position',
        'menu_icon',
        'capability_type',
        'capabilities',
        'map_meta_cap',
        'supports',
        'register_meta_box_cb',
        'taxonomies',
        'has_archive',
        'rewrite',
        'query_var',
        'can_export',
        'delete_with_user',
        'template',
        'template_lock',
        '_builtin',
        '_edit_link',
    ];

    protected string $postType;
    protected string $singular;
    protected string $plural;
    protected bool $male;
    protected array $labels;
    protected array $rewrite;
    protected array $taxonomies;
    protected array $supports;
    protected array $args;

    /**
     * -------------------------------------------------------------------------
     * Add post type name
     * -------------------------------------------------------------------------
     *
     * @param string $postType
     * @return PostType
     */
    public function setPostType(string $postType): PostType
    {
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
     * @return PostType
     *
     * @see https://developer.wordpress.org/reference/functions/get_post_type_labels/
     */
    public function setLabels(string $singular, string $plural, bool $male = true): PostType
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
            'add_new'                  => ucfirst(sprintf(__('add %s %s', 'wpb'), $new, $this->singular)),
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

        $this->args('labels', $this->labels);

        return $this;
    }

    /**
     * -------------------------------------------------------------------------
     * Edit labels
     * -------------------------------------------------------------------------
     *
     * Opções:
     *
     * - menu_name
     * - name
     * - singular_name
     * - add_new
     * - add_new_item
     * - edit_item
     * - new_item
     * - view_item
     * - view_items
     * - search_items
     * - not_found
     * - not_found_in_trash
     * - parent_item_colon
     * - all_items
     * - archives
     * - attributes
     * - insert_into_item
     * - uploaded_to_this_item
     * - featured_image
     * - set_featured_image
     * - remove_featured_image
     * - use_featured_image
     * - menu_name
     * - filter_items_list
     * - filter_by_date
     * - items_list_navigation
     * - items_list
     * - item_published
     * - item_published_privately
     * - item_reverted_to_draft
     * - item_trashed
     * - item_scheduled
     * - item_updated
     * - item_link
     * - item_link_description
     *
     * @param string|list<string> $labels
     * @param string|null $val
     * @param boolean $ucfirst
     * @return PostType
     */
    public function labels($labels, string $val = null, bool $ucfirst = true): PostType
    {
        if(!is_array($labels) && $val) {
            $this->labels[$labels] = ($ucfirst) ? ucfirst($val) : $val;

            return $this;
        }

        else {
            foreach($labels as $key => $val) {
                $this->labels[$key] = ($ucfirst) ? ucfirst($val) : $val;
            }

            return $this;
        }

        return $this;
    }

    /**
     * -------------------------------------------------------------------------
     * Edit rewrite
     * -------------------------------------------------------------------------
     *
     * Opções:
     *
     * - slug
     * - with_front
     * - feeds
     * - pages
     * - ep_mask
     *
     * @param boolean|string|array $rewrite
     * @param string|null $val
     * @return PostType
     */
    public function rewrite($rewrite, ?string $val = null): PostType
    {
        if(is_bool($rewrite)) {
            $this->rewrite = [
                'slug' => sanitize_title($this->labels['name']) ?? $this->postType,
                'with_front' => true,
                'pages' => true,
                'feeds' => true,
            ];
        }

        elseif(is_array($rewrite)) {
            foreach($rewrite as $key => $val) $this->rewrite[$key] = $val;

            return $this;
        }

        else {
            $this->rewrite[$rewrite] = $val;

            if($rewrite == 'slug') $this->rewrite['with_front'] = true;

            return $this;
        }
    }

    /**
     * -------------------------------------------------------------------------
     * Edit supported features
     * -------------------------------------------------------------------------
     *
     * Opções padrão:
     *
     * - title
     * - editor
     * - author
     * - thumbnail
     * - excerpt
     * - trackbacks
     * - custom-fields
     * - comments
     * - revisions
     * - page-attributes
     * - post-formats
     *
     * @param string|array $supports
     * @param integer|string|bool|null $val
     * @return PostType
     */
    public function supports($supports, $val = null): PostType
    {
        if(is_array($supports) && $val) {
            foreach($supports as $k => $val) {
                $this->supports[$k] = $val;
            }
        }

        else {
            $this->supports[$supports] = $val;
        }

        return $this;
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
     * Edit arguments
     * -------------------------------------------------------------------------
     *
     * Options:
     *
     * - label
     * - labels
     * - description
     * - public
     * - hierarchical
     * - exclude_from_search
     * - publicly_queryable
     * - show_ui
     * - show_in_menu
     * - show_in_nav_menus
     * - show_in_admin_bar
     * - show_in_rest
     * - rest_base
     * - rest_namespace
     * - rest_controller_class
     * - autosave_rest_controller_class
     * - revisions_rest_controller_class
     * - late_route_registration
     * - menu_position
     * - menu_icon
     * - capability_type
     * - capabilities
     * - map_meta_cap
     * - supports
     * - register_meta_box_cb
     * - taxonomies
     * - has_archive
     * - rewrite
     * - query_var
     * - can_export
     * - delete_with_user
     * - template
     * - template_lock
     * - _builtin
     * - _edit_link
     *
     * @param string|list<string> $config
     * @param string|list<string>|null $val
     * @return PostType
     */
    public function args($config, $val = null): PostType
    {
        if(!is_array($config)) {
            $this->args[$config] = $val;

            return $this;
        }

        else {
            foreach($config as $key => $value) {
                $this->args[$key] = $value;
            }

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
}
