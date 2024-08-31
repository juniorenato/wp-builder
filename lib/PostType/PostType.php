<?php

namespace WPB\PostType;

use WPB\Builder;

/**
 * -----------------------------------------------------------------------------
 * Post Type creator
 * -----------------------------------------------------------------------------
 *
 * @see https://developer.wordpress.org/reference/functions/register_post_type/
 * @since 0.1.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 * @package hoststyle/hswp-theme-builder
 */
class PostType extends Builder
{
    use I18n;

    private string $name = '';

    protected string $singular = '';

    protected string $plural = '';

    private array $labels = [];

    private array $rewrite = [];

    private array $taxonomies = [];

    private array $supports = [
        'title',
        'editor',
    ];

    private array $args = [];

    public function __construct(?Builder $builder = null)
    {
        if($builder) {
            $this->td = $builder->td;
            $this->prefix = $builder->prefix;
            $this->lang = $builder->lang;
            $this->male = $builder->male;
        }
    }

    /**
     * -------------------------------------------------------------------------
     * add the key
     * -------------------------------------------------------------------------
     *
     * @param string $name
     * @return PostType
     */
    public function setName(string $name): PostType
    {
        $this->name = sanitize_title($name);

        return $this;
    }

    /**
     * -------------------------------------------------------------------------
     * Start names
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

        return $this;
    }

    /**
     * -------------------------------------------------------------------------
     * Add display names
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
     * - Default
     * - item_reverted_to_draft
     * - Default
     * - item_trashed
     * - item_scheduled
     * - item_updated
     * - item_link
     * - item_link_description
     *
     * @param string|list<string> $labels
     * @param string $val
     * @param boolean $ucfirst
     * @return PostType
     */
    public function addLabels($labels, string $val, bool $ucfirst = true): PostType
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
     * Add URL Settings
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
    public function addRewrite($rewrite, ?string $val = null): PostType
    {
        if(is_bool($rewrite)) {
            $this->rewrite = [
                'slug'       => sanitize_title($this->singular),
                'with_front' => true,
                'pages'      => true,
                'feeds'      => true,
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
     * Configure taxonomies
     * -------------------------------------------------------------------------
     *
     * @param string|list<string> $taxonomies
     * @return PostType
     */
    public function addTaxonomy($taxonomies): PostType
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
     * Launch supported features
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
    public function addSupports($supports, $val = null): PostType
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
     * Configure arguments
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
     * @param string|null $val
     * @return PostType
     */
    public function addArgs($config, ?string $val = null): PostType
    {
        if(!is_array($config) && $val) {
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
     * @return PostType
     *
     * @see https://developer.wordpress.org/resource/dashicons/
     */
    public function addIcon(string $icon): PostType
    {
        $this->args['menu_icon'] = $icon;

        return $this;
    }

    /**
     * -------------------------------------------------------------------------
     * Create custom post
     * -------------------------------------------------------------------------
     *
     * @param string @name
     * @param string @singular
     * @param string @plural
     * @param boolean $reset
     * @return void
     */
    public function register(?string $name = null, ?string $singular = null, ?string $plural = null, bool $reset = true): bool
    {
        // Set default prefix
        if($this->prefix === true) {
            $this->setPrefix('cpt_');
        }

        if($name && $singular && $plural) {
            $this->setName($name);
            $this->setLabels($singular, $plural);
        }

        // Return error if any configuration is missing
        if(1 == 0
            || !$this->name
            || !$this->singular
            || !$this->plural
        ) { return false; }

        // Set selected language
        if($this->lang) {
            if(function_exists($this->{$this->lang}())) {
                $this->{$this->lang}();
            }
        }

        // Set args
        $this->setArgs();

        // WordPress - Register Post Type
        register_post_type(
            $this->prefix . $this->name,
            $this->args
        );

        // Resetar a classe
        if($reset) $this->reset();

        return true;
    }

    /**
     * -------------------------------------------------------------------------
     * Reset the class
     * -------------------------------------------------------------------------
     *
     * @return void
     */
    public function reset(): void
    {
        $this->name = '';
        $this->singular = '';
        $this->plural = '';
        $this->labels = [];
        $this->rewrite = [];
        $this->taxonomies = [];
        $this->supports = [];
        $this->args = [];
    }

    /**
     * -------------------------------------------------------------------------
     * Start Settings
     * -------------------------------------------------------------------------
     *
     * @return void
     */
    private function setArgs(): void
    {
        $args = [
            'label'           => ucfirst($this->plural),
            'labels'          => $this->labels,
            'description'     => (string) get_option('_'. $this->name .'_description'),
            'public'          => true,
            'hierarchical'    => false,
            //'exclude_from_search',
            //'publicly_queryable',
            //'show_ui',
            //'show_in_menu',
            //'show_in_nav_menus',
            //'show_in_admin_bar',
            'show_in_rest'    => true,
            //'rest_base',
            //'rest_namespace',
            //'rest_controller_class',
            //'autosave_rest_controller_class',
            //'revisions_rest_controller_class',
            //'late_route_registration',
            'menu_position'   => 5,
            //'menu_icon'       => 'dashicons-admin-post',
            'capability_type' => 'post',
            //'capabilities' => [],
            //'map_meta_cap',
            'supports'        => $this->supports,
            //'register_meta_box_cb' => '',
            'taxonomies'      => $this->taxonomies,
            'has_archive'     => false,
            'rewrite'         => $this->rewrite,
            'query_var'       => $this->name,
            //'can_export',
            //'delete_with_user',
            //'template',
            //'template_lock',
            //'_builtin',
            //'_edit_link',
        ];

        $this->args = array_merge($args, $this->args);
    }
}
