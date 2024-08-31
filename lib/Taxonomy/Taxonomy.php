<?php

namespace WPB\Taxonomy;

use WPB\Builder;

/**
 * -----------------------------------------------------------------------------
 * Taxonomy creator
 * -----------------------------------------------------------------------------
 *
 * @see https://developer.wordpress.org/reference/functions/register_taxonomy/
 * @since 0.1.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 * @package hoststyle/hswp-theme-builder
 */
class Taxonomy extends Builder
{
    use CustomFields;
    use I18n;

    private string $name = '';

    protected string $singular = '';

    protected string $plural = '';

    private array $labels = [];

    private array $rewrite = [];

    private array $capabilities = [];

    private array $postTypes = [];

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
     * Adicionar a chave
     * -------------------------------------------------------------------------
     *
     * @param string $key
     * @return Taxonomy
     */
    public function setName(string $name): Taxonomy
    {
        $this->name = $name;

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
    }

    /**
     * -------------------------------------------------------------------------
     * Adicionar os nomes para exibição
     * -------------------------------------------------------------------------
     *
     * Opções:
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
    public function addLabels($labels, string $val, bool $ucfirst = true): Taxonomy
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
     * Configurar URLs
     * -------------------------------------------------------------------------
     *
     * Opções padão:
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
    public function addRewrite($rewrite, ?string $val = null): Taxonomy
    {
        if(is_bool($rewrite)) {
            $this->rewrite = [
                'slug'       => sanitize_title($this->singular),
                'with_front'    => true,
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
     * Configurar capacidades
     * -------------------------------------------------------------------------
     *
     * Opções padão:
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
    public function addCapabilities($capabilities, ?string $val = null): Taxonomy
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
    public function addPostTypes($post_types): Taxonomy
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
     * Configure arguments
     * -------------------------------------------------------------------------
     *
     * @param string|list<string> $args
     * @param string|bool|null $val
     * @return Taxonomy
     */
    public function addArgs($args, $val = null): Taxonomy
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
     * @param boolean $reset
     * @return void
     */
    public function register(?string $name = null, ?string $singular = null, ?string $plural = null, bool $reset = true): bool
    {
        // Set default prefix
        if($this->prefix === true) {
            $this->setPrefix('tax_');
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
            || !$this->postTypes
        ) { return false; }

        // Set selected language
        if($this->lang) {
            if(function_exists($this->{$this->lang}())) {
                $this->{$this->lang}();
            }
        }

        // Insert arguments
        $this->setArgs();

        // Insert custom fields
        if($this->customFields) $this->setCustomFields();

        register_taxonomy(
            $this->prefix . $this->name,
            $this->postTypes,
            $this->args
        );

        // Reset the class
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
     * Start Settings
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
            //'rest_base'             => true,
            //'rest_controller_class' => true,
            //'show_tagcloud'         => false,
            //'show_in_quick_edit'    => false,
            //'meta_box_cb'           => false,
            'show_admin_column'     => false,
            'description'           => get_option('_tax_'. $this->name .'_description'),
            'hierarchical'          => true,
            //'update_count_callback' => true,
            //'query_var'             => true,
            'rewrite'               => $this->rewrite,
            'capabilities'          => $this->capabilities,
            //'sort'                => false,
            '_builtin'              => false,
        ];

        $this->args = array_merge($args, $this->args);
    }
}
