<?php

namespace WPB\PostTypes;

use WPB\Forms\AdminForm;
use WPB\Forms\PostTypeMetaBox;

class PostType
{
    use AdminForm;
    use PostTypeMetaBox;

    protected string $postType;
    protected array $labels;
    protected array $rewrite;
    protected array $taxonomies;
    protected array $supports;
    protected array $args;

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
     * @param string $val
     * @param boolean $ucfirst
     * @return PostType
     */
    public function labels($labels, string $val, bool $ucfirst = true): PostType
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
     * @param string|null $val
     * @return PostType
     */
    public function args($config, ?string $val = null): PostType
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

}
