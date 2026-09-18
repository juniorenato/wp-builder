<?php

/**
 * Taxonomy factory.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Taxonomies
 */

namespace WPB\Taxonomies;

use WPB\Builder;
use WPB\Forms\AdminForm;
use WPB\Forms\TaxonomyCustomField;

/**
 * Registers a custom taxonomy with WordPress.
 *
 * @since  0.1.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 *
 * @see https://developer.wordpress.org/reference/functions/register_taxonomy/
 */
class Taxonomy
{
    use AdminForm;
    use TaxonomyCustomField;

    /**
     * Taxonomy slug.
     *
     * @since 0.1.0
     */
    private string $taxonomy;

    /**
     * Singular label.
     *
     * @since 0.1.0
     */
    protected string $singular;

    /**
     * Plural label.
     *
     * @since 0.1.0
     */
    protected string $plural;

    /**
     * Whether generated labels use the masculine form.
     *
     * @since 0.1.0
     */
    private bool $male;

    /**
     * Taxonomy labels.
     *
     * @since 0.1.0
     *
     * @var array<string, string>
     */
    private array $labels;

    /**
     * Whether default translated labels have been generated.
     *
     * @since 0.1.0
     */
    private bool $defaultLabelsBuilt = false;

    /**
     * Rewrite arguments.
     *
     * @since 0.1.0
     *
     * @var array<string, mixed>
     */
    private array $rewrite;

    /**
     * Taxonomy capabilities.
     *
     * @since 0.1.0
     *
     * @var array<string, string>
     */
    private array $capabilities;

    /**
     * Attached post type slugs.
     *
     * @since 0.1.0
     *
     * @var list<string>
     */
    private array $postTypes;

    /**
     * Arguments passed to `register_taxonomy()`.
     *
     * @since 0.1.0
     *
     * @var array<string, mixed>
     */
    private array $args;

    /**
     * Optionally registers a taxonomy immediately.
     *
     * @since 0.1.0
     *
     * @param string|null              $taxonomy Taxonomy slug.
     * @param string|list<string>|null $postType Post type slug or list of slugs.
     * @param string|null              $singular Singular label.
     * @param string|null              $plural   Plural label.
     * @param bool                     $male     Whether to use masculine translations.
     */
    public function __construct(
        ?string $taxonomy = null,
        string|array|null $postType = null,
        ?string $singular = null,
        ?string $plural = null,
        bool $male = true
    ) {
        $this->valueType = 'taxonomy';

        $this->init();

        if ($taxonomy && $postType && $singular && $plural) {
            $this->register($taxonomy, $postType, $singular, $plural, $male);
        }
    }

    /**
     * Resets the taxonomy configuration to defaults.
     *
     * @since 0.1.0
     */
    public function init(): void
    {
        $this->taxonomy = '';
        $this->plural = '';
        $this->singular = '';
        $this->male = true;
        $this->labels = [];
        $this->defaultLabelsBuilt = false;
        $this->rewrite = [];
        $this->capabilities = [];
        $this->postTypes = [];
        $this->args = [];
    }

    /**
     * Sets the taxonomy slug.
     *
     * @since 0.1.0
     *
     * @param string $taxonomy Taxonomy key.
     *
     * @return static
     */
    public function taxonomy(string $taxonomy): static
    {
        $this->taxonomy = $taxonomy;

        return $this;
    }

    /**
     * Stores names used to generate taxonomy labels.
     *
     * Translated strings are built on `init` so constructors can run from a
     * plugin or theme file without triggering the WordPress 6.7 notice.
     *
     * @since 0.1.0
     *
     * @param string $singular Singular name.
     * @param string $plural   Plural name.
     * @param bool   $male     Whether to use masculine translations.
     */
    public function setLabels(string $singular, string $plural, bool $male = true): void
    {
        $this->singular = $singular;
        $this->plural = $plural;
        $this->male = $male;
        $this->defaultLabelsBuilt = false;

        if (Builder::canLoadTranslations()) {
            $this->buildDefaultLabels();
        }
    }

    /**
     * Builds the default translated taxonomy labels.
     *
     * @since 0.1.0
     */
    private function buildDefaultLabels(): void
    {
        $new    = $this->male ? __('new', 'wpb') : __('female_new', 'wpb');
        $found  = $this->male ? __('found', 'wpb') : __('female_found', 'wpb');
        $parent = $this->male ? __('parent', 'wpb') : __('female_parent', 'wpb');
        $all    = $this->male ? __('all', 'wpb') : __('female_all', 'wpb');
        $used   = $this->male ? __('used', 'wpb') : __('female_used', 'wpb');

        $this->labels = array_merge([
            'name'                       => ucfirst($this->plural),
            'singular_name'              => ucfirst($this->singular),
            'search_items'               => ucfirst(sprintf(__('search %s', 'wpb'), $this->plural)),
            'popular_items'              => ucfirst(sprintf(__('popular %s', 'wpb'), $this->plural)),
            'all_items'                  => ucfirst($all . ' ' . $this->plural),
            'parent_item'                => ucfirst($this->singular . ' ' . $parent),
            'parent_item_colon'          => ucfirst($this->singular . ' ' . $parent . ':'),
            'edit_item'                  => ucfirst(sprintf(__('edit %s', 'wpb'), $this->singular)),
            'view_item'                  => ucfirst(sprintf(__('view %s', 'wpb'), $this->singular)),
            'update_item'                => ucfirst(sprintf(__('update %s', 'wpb'), $this->singular)),
            'add_new_item'               => ucfirst(sprintf(__('add %s %s', 'wpb'), $new, $this->singular)),
            'new_item_name'              => ucfirst(sprintf(__('%s name', 'wpb'), $new)),
            'separate_items_with_commas' => ucfirst(sprintf(__('separate %s with commas', 'wpb'), $this->plural)),
            'add_or_remove_items'        => ucfirst(sprintf(__('add or remove %s', 'wpb'), $this->plural)),
            'not_found'                  => ucfirst(sprintf(__('%s not %s', 'wpb'), $this->singular, $found)),
            'no_terms'                   => ucfirst(sprintf(__('without %s', 'wpb'), $this->plural)),
            'filter_by_item'             => ucfirst(sprintf(__('filter by %s', 'wpb'), $this->singular)),
            'items_list_navigation'      => ucfirst(sprintf(__('%s list navigation', 'wpb'), $this->plural)),
            'items_list'                 => ucfirst(sprintf(__('%s list', 'wpb'), $this->plural)),
            'most_used'                  => ucfirst(sprintf(__('%s most %s', 'wpb'), $this->singular, $used)),
            'back_to_items'              => ucfirst(sprintf(__('back to %s', 'wpb'), $this->plural)),
            'item_link'                  => ucfirst(sprintf(__('link to %s.', 'wpb'), $this->plural)),
            'item_link_description'      => ucfirst(sprintf(__('a link to %s.', 'wpb'), $this->plural)),
        ], $this->labels);

        $this->defaultLabelsBuilt = true;
    }

    /**
     * Merges default arguments with user-provided arguments.
     *
     * @since 0.1.0
     */
    private function setArgs(): void
    {
        $args = [
            'label'              => ucfirst($this->plural),
            'labels'             => $this->labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_nav_menus'  => true,
            'show_in_rest'       => true,
            'show_admin_column'  => false,
            'description'        => get_option('_tax_' . $this->taxonomy . '_description'),
            'hierarchical'       => true,
            'rewrite'            => $this->rewrite,
            'capabilities'       => $this->capabilities,
            '_builtin'           => false,
        ];

        $this->args = array_merge($args, $this->args);
    }

    /**
     * Overrides one or more taxonomy labels.
     *
     * Accepted keys:
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
     * @since 0.1.0
     *
     * @param string|array<string, string> $labels  Label key or a map of labels.
     * @param string                       $val     Label value when `$labels` is a key.
     * @param bool                         $ucfirst Whether to uppercase the first character.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/functions/get_taxonomy_labels/
     */
    public function labels(string|array $labels, string $val, bool $ucfirst = true): static
    {
        if (!is_array($labels) && $val) {
            $this->labels[$labels] = $ucfirst ? ucfirst($val) : $val;

            return $this;
        }

        foreach ($labels as $key => $label) {
            $this->labels[$key] = $ucfirst ? ucfirst($label) : $label;
        }

        return $this;
    }

    /**
     * Sets rewrite arguments.
     *
     * Accepted keys:
     *
     * - slug
     * - with_front
     * - hierarchical
     * - ep_mask
     *
     * @since 0.1.0
     *
     * @param bool|string|array<string, mixed> $rewrite Rewrite flag, key, or map of values.
     * @param string|null                      $val     Value when `$rewrite` is a key.
     *
     * @return static
     */
    public function rewrite(bool|string|array $rewrite, ?string $val = null): static
    {
        if (is_bool($rewrite)) {
            $this->rewrite = [
                'slug' => sanitize_title($this->singular),
                'with_front' => true,
            ];
        } elseif (is_array($rewrite)) {
            foreach ($rewrite as $key => $value) {
                $this->rewrite[$key] = $value;
            }
        } else {
            $this->rewrite[$rewrite] = $val;

            if ($rewrite === 'slug') {
                $this->rewrite['with_front'] = true;
            }
        }

        return $this;
    }

    /**
     * Sets taxonomy capabilities.
     *
     * Accepted keys:
     *
     * - manage_terms
     * - edit_terms
     * - delete_terms
     * - assign_terms
     *
     * @since 0.1.0
     *
     * @param string|array<string, string> $capabilities Capability key or map of capabilities.
     * @param string|null                  $val          Capability value when `$capabilities` is a key.
     *
     * @return static
     */
    public function capabilities(string|array $capabilities, ?string $val = null): static
    {
        if (is_array($capabilities)) {
            foreach ($capabilities as $key => $value) {
                $this->capabilities[$key] = $value;
            }

            return $this;
        }

        $this->capabilities[$capabilities] = $val;

        return $this;
    }

    /**
     * Attaches post types to the taxonomy.
     *
     * @since 0.1.0
     *
     * @param string|list<string> $post_types Post type slug or list of slugs.
     *
     * @return static
     */
    public function postTypes(string|array $post_types): static
    {
        if (is_array($post_types)) {
            $this->postTypes = array_merge($this->postTypes, $post_types);
        } else {
            $this->postTypes[] = $post_types;
        }

        return $this;
    }

    /**
     * Sets `register_taxonomy()` arguments.
     *
     * Options:
     *
     * - label
     * - labels
     * - public
     * - publicly_queryable
     * - show_ui
     * - show_in_menu
     * - show_in_nav_menus
     * - show_in_rest
     * - rest_base
     * - rest_controller_class
     * - show_tagcloud
     * - show_in_quick_edit
     * - meta_box_cb
     * - show_admin_column
     * - description
     * - hierarchical
     * - update_count_callback
     * - query_var
     * - rewrite
     * - capabilities
     * - sort
     * - _builtin
     *
     * @since 0.1.0
     *
     * @param string|array<string, mixed> $args Argument key or map of arguments.
     * @param mixed                       $val  Value when `$args` is a key.
     *
     * @return static
     */
    public function args(string|array $args, mixed $val = null): static
    {
        if (!is_array($args) && $val !== null) {
            $this->args[$args] = $val;

            return $this;
        }

        foreach ($args as $key => $value) {
            $this->args[$key] = $value;
        }

        return $this;
    }

    /**
     * Queues the taxonomy for registration on `init`.
     *
     * @since 0.1.0
     *
     * @param string|null              $taxonomy Taxonomy slug.
     * @param string|list<string>|null $postType Post type slug or list of slugs.
     * @param string|null              $singular Singular label.
     * @param string|null              $plural   Plural label.
     * @param bool                     $male     Whether to use masculine translations.
     *
     * @return static|false The taxonomy instance, or false when configuration is incomplete.
     */
    public function register(
        ?string $taxonomy = null,
        string|array|null $postType = null,
        ?string $singular = null,
        ?string $plural = null,
        bool $male = true
    ): static|false {
        if ($taxonomy && $postType && $singular && $plural) {
            $this->taxonomy($taxonomy);
            $this->postTypes($postType);
            $this->setLabels($singular, $plural, $male);
        }

        if (!$this->taxonomy || !$this->postTypes || !$this->singular || !$this->plural) {
            return false;
        }

        Builder::onInit([$this, 'registerTaxonomy']);

        return $this;
    }

    /**
     * Registers the taxonomy with WordPress.
     *
     * Labels and arguments are finalized here so `__()` runs on `init`.
     *
     * @since 0.1.0
     */
    public function registerTaxonomy(): void
    {
        if (!$this->defaultLabelsBuilt) {
            $this->buildDefaultLabels();
        }

        $this->setArgs();

        if ($this->fields) {
            $this->setTermFields();
        }

        register_taxonomy(
            $this->taxonomy,
            $this->postTypes,
            $this->args
        );
    }
}
