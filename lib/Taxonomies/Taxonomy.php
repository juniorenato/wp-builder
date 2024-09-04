<?php

namespace WPB\Taxonomies;

use WPB\Forms\AdminForm;
use WPB\Forms\TaxonomyCustomField;

/**
 * -----------------------------------------------------------------------------
 * Taxonomy builder
 * -----------------------------------------------------------------------------
 *
 * @see https://developer.wordpress.org/reference/functions/register_taxonomy/
 * @since v0.1.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 * @package juniorenato/wp-builder
 */
class Taxonomy
{
    use AdminForm;
    use TaxonomyCustomField;

    private string $taxonomy;
    protected string $singular;
    protected string $plural;
    private bool $male;
    private array $labels;
    private array $rewrite;
    private array $capabilities;
    private array $postTypes;
    private array $args;

    public function __construct()
    {
        $this->valueType = 'taxonomy';

        $this->init();
    }

    /**
     * -------------------------------------------------------------------------
     * Init the class
     * -------------------------------------------------------------------------
     *
     * @return void
     */
    public function init(): void
    {
        $this->taxonomy = '';
        $this->plural = '';
        $this->singular = '';
        $this->labels = [];
        $this->rewrite = [];
        $this->capabilities = [];
        $this->postTypes = [];
        $this->args = [];
    }

    /**
     * -------------------------------------------------------------------------
     * Add taxonomy name
     * -------------------------------------------------------------------------
     *
     * @param string $taxonomy
     * @return Taxonomy
     */
    public function taxonomy(string $taxonomy): Taxonomy
    {
        $this->taxonomy = $taxonomy;

        return $this;
    }

    /**
     * -------------------------------------------------------------------------
     * Inserir os nomes de exibição
     * -------------------------------------------------------------------------
     *
     * @param string $singular
     * @param string $plural
     * @param boolean $male
     * @return void
     */
    public function setLabels(string $singular, string $plural, bool $male = true): void
    {
        $this->singular = $singular;
        $this->plural = $plural;
        if($male) $this->male = $male;

        $new    = ($this->male) ? __('new', 'wpb') : __('female_new', 'wpb');
        $found  = ($this->male) ? __('found', 'wpb') : __('female_found', 'wpb');
        $parent = ($this->male) ? __('parent', 'wpb') : __('female_parent', 'wpb');
        $all    = ($this->male) ? __('all', 'wpb') : __('female_all', 'wpb');
        $used   = ($this->male) ? __('used', 'wpb') : __('female_used', 'wpb');

        $this->labels = [
            'name'                       => ucfirst(__($this->plural, 'wpb')),
            'singular_name'              => ucfirst(__($this->singular, 'wpb')),
            'search_items'               => ucfirst(sprintf(__('search %s','wpb'), $this->plural)),
            'popular_items'              => ucfirst(sprintf(__('popular %s','wpb'), $this->plural)),
            'all_items'                  => ucfirst($all .' '. $this->plural),
            'parent_item'                => ucfirst($this->singular .' '. $parent),
            'parent_item_colon'          => ucfirst($this->singular .' '. $parent .':'),
            'edit_item'                  => ucfirst(sprintf(__('edit %s','wpb'), $this->singular)),
            'view_item'                  => ucfirst(sprintf(__('view %s','wpb'), $this->singular)),
            'update_item'                => ucfirst(sprintf(__('update %s','wpb'), $this->singular)),
            'add_new_item'               => ucfirst(sprintf(__('add %s %s','wpb'), $new, $this->singular)),
            'new_item_name'              => ucfirst(sprintf(__('%s name','wpb'), $new)),
            'separate_items_with_commas' => ucfirst(sprintf(__('separate %s with commas','wpb'), $this->plural)),
            'add_or_remove_items'        => ucfirst(sprintf(__('add or remove %s','wpb'), $this->plural)),
            'not_found'                  => ucfirst(sprintf(__('%s not %s','wpb'), $this->singular, $found)),
            'no_terms'                   => ucfirst(sprintf(__('without %s','wpb'), $this->plural)),
            'filter_by_item'             => ucfirst(sprintf(__('filter by %s','wpb'), $this->singular)),
            'items_list_navigation'      => ucfirst(sprintf(__('%s list navigation','wpb'), $this->plural)),
            'items_list'                 => ucfirst(sprintf(__('%s list','wpb'), $this->plural)),
            'most_used'                  => ucfirst(sprintf(__('%s most %s', 'wpb'), $this->singular, $used)),
            'back_to_items'              => ucfirst(sprintf(__('back to %s', 'wpb'), $this->plural)),
            'item_link'                  => ucfirst(sprintf(__('link to %s.'), $this->plural)),
            'item_link_description'      => ucfirst(sprintf(__('a link to %s.'), $this->plural)),
        ];
    }

    /**
     * -------------------------------------------------------------------------
     * Set arguments
     * -------------------------------------------------------------------------
     *
     * @return void
     */
    private function setArgs(): void
    {
        $args = [
            'label'                 => ucfirst($this->plural),
            'labels'                => $this->labels,
            'public'                => true,
            'publicly_queryable'    => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'show_in_nav_menus'     => true,
            'show_in_rest'          => true,
            'show_admin_column'     => false,
            'description'           => get_option('_tax_'. $this->taxonomy .'_description'),
            'hierarchical'          => true,
            'rewrite'               => $this->rewrite,
            'capabilities'          => $this->capabilities,
            '_builtin'              => false,
        ];

        $this->args = array_merge($args, $this->args);
    }
    /**
     * -------------------------------------------------------------------------
     * Edit labels
     * -------------------------------------------------------------------------
     *
     * - menu_name
     * - name
     * - singular_name
     * - search_items
     * - popular_items
     * - all_items
     * - parent_item
     * - parent_item_colon
     * - name_field_description
     * - slug_field_description
     * - parent_field_description
     * - desc_field_description
     * - edit_item
     * - view_item
     * - update_item
     * - add_new_item
     * - new_item_name
     * - separate_items_with_commas
     * - add_or_remove_items
     * - choose_from_most_used
     * - not_found
     * - no_terms
     * - filter_by_item
     * - items_list_navigation
     * - items_list
     * - most_used
     * - back_to_items
     * - item_link
     * - item_link_description
     *
     * @param string|list<string> $labels
     * @param string $val
     * @param boolean $ucfirst
     * @return Taxonomy
     *
     * @see https://developer.wordpress.org/reference/functions/get_taxonomy_labels/
     */
    public function labels($labels, string $val, bool $ucfirst = true): Taxonomy
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
     * - slug
     * - with_front
     * - hierarchical
     * - ep_mask
     *
     * @param boolean|string|list<string> $rewrite
     * @param string|null $val
     * @return Taxonomy
     */
    public function rewrite($rewrite, ?string $val = null): Taxonomy
    {
        if(is_bool($rewrite)) {
            $this->rewrite = [
                'slug' => sanitize_title($this->singular),
                'with_front' => true,
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
     * Edit capacidades
     * -------------------------------------------------------------------------
     *
     * - manage_terms
     * - edit_terms
     * - delete_terms
     * - assign_terms
     *
     * @param string|list<string> $capabilities
     * @param string|null $val
     * @return Taxonomy
     */
    public function capabilities($capabilities, ?string $val = null): Taxonomy
    {
        if(is_array($capabilities)) {
            foreach($capabilities as $key => $val) $this->capabilities[$key] = $val;

            return $this;
        }

        else {
            $this->capabilities[$capabilities] = $val;

            return $this;
        }
    }

    /**
     * -------------------------------------------------------------------------
     * Add post types
     * -------------------------------------------------------------------------
     *
     * @param string|list<string> $post_types
     * @param string|null $val
     * @return Taxonomy
     */
    public function postTypes($post_types): Taxonomy
    {
        if(is_array($post_types)) {
            $this->postTypes = array_merge($this->postTypes, $post_types);
        }

        else {
            $this->postTypes[] = $post_types;
        }

        return $this;
    }

    /**
     * -------------------------------------------------------------------------
     * Edit arguments
     * -------------------------------------------------------------------------
     *
     * label
     * labels
     * public
     * publicly_queryable
     * show_ui
     * show_in_menu
     * show_in_nav_menus
     * show_in_rest
     * rest_base
     * rest_controller_class
     * show_tagcloud
     * show_in_quick_edit
     * meta_box_cb
     * show_admin_column
     * description
     * hierarchical
     * update_count_callback
     * query_var
     * rewrite
     * capabilities
     * sort
     * _builtin
     *
     * @param string|list<string> $args
     * @param string|bool|null $val
     * @return Taxonomy
     */
    public function args($args, $val = null): Taxonomy
    {
        if(!is_array($args) && $val !== null) {
            $this->args[$args] = $val;

            return $this;
        }

        else {
            foreach($args as $key => $value) {
                $this->args[$key] = $value;
            }

            return $this;
        }
    }

    /**
     * -------------------------------------------------------------------------
     * Create taxonomy
     * -------------------------------------------------------------------------
     *
     * @param string $taxonomy
     * @param string|list<string> $postType
     * @param string $singular
     * @param string $plural
     * @param boolean $male
     * @return Taxonomy
     */
    public function register(?string $taxonomy = null, $postType = null, ?string $singular = null, ?string $plural = null, bool $male = true): Taxonomy
    {
        if(1 == 1
            && $taxonomy
            && $postType
            && $singular
            && $plural
        ) {
            $this->taxonomy($taxonomy);
            $this->postTypes($postType);
            $this->setLabels($singular, $plural, $male);
        }

        // Return error if any configuration is missing
        if(1 == 0
            || !$this->taxonomy
            || !$this->postTypes
            || !$this->singular
            || !$this->plural
        ) { return false; }

        // Set args
        $this->setArgs();

        // WordPress - Register Taxonomy
        add_action('init', [$this, 'registerTaxonomy']);

        return $this;
    }

    /**
     * -------------------------------------------------------------------------
     * Register taxonomy callback
     * -------------------------------------------------------------------------
     *
     * @return void
     */
    public function registerTaxonomy()
    {
        if($this->fields) $this->setTermFields();

        register_taxonomy(
            $this->taxonomy,
            $this->postTypes,
            $this->args
        );
    }
}
