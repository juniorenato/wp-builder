<?php

namespace WPB\Pages;

use WPB\Forms\AdminForm;
use WPB\Builder;

/**
 * -----------------------------------------------------------------------------
 * Admin Custom Page Builder
 * -----------------------------------------------------------------------------
 *
 * @since v0.2.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 * @package juniorenato/wp-builder
 */
class AdminPage
{
    use AdminForm;

    private string $parentSlug;
    private string $pageTitle;
    private string $menuTitle;
    private string $capability;
    private string $menuSlug;
    private string $iconUrl;
    private int $position;

    public function __construct()
    {
        $this->valueType = 'option';

        $this->init();
    }

    public function init()
    {
        $this->parentSlug = '';
        $this->pageTitle = '';
        $this->menuTitle = '';
        $this->capability = 'manage_options';
        $this->menuSlug = '';
        $this->iconUrl = 'dashicons-admin-post';
        $this->position = 2;
    }

    public function parent(string $slug): AdminPage
    {
        return $this->parentSlug($slug);
    }

    public function title(string $title): AdminPage
    {
        $this->pageTitle($title);
        $this->menuTitle($title);
        $this->menuSlug($title);

        return $this;
    }

    public function slug(string $slug): AdminPage
    {
        return $this->menuSlug($slug);
    }

    public function icon(string $icon): AdminPage
    {
        return $this->iconUrl($icon);
    }

    public function parentSlug(string $slug): AdminPage
    {
        $slug = str_replace('.php', '', $slug);

        $this->parentSlug = $slug .'.php';

        return $this;
    }

    public function pageTitle(string $title): AdminPage
    {
        $this->pageTitle = $title;

        return $this;
    }

    public function menuTitle(string $title): AdminPage
    {
        $this->menuTitle = $title;

        return $this;
    }

    public function capability(string $capability): AdminPage
    {
        $this->capability = $capability;

        return $this;
    }

    public function menuSlug(string $slug): AdminPage
    {
        $this->menuSlug = sanitize_title($slug);

        return $this;
    }

    public function iconUrl(string $icon): AdminPage
    {
        $this->iconUrl = $icon;

        return $this;
    }

    public function position(string $position): AdminPage
    {
        $this->position = $position;

        return $this;
    }

    public function register(?string $title = null, ?string $parent = null, ?string $icon = null, ?int $position = null)
    {
        if($parent) $this->parent($parent);
        if($title) $this->title($title);
        if($icon) $this->icon($icon);
        if($position) $this->position($position);

        add_action('admin_menu', [$this, 'addMenuPage']);
    }

    public function addMenuPage()
    {
        if(!$this->parentSlug) {
            add_menu_page(
                $this->pageTitle,
                $this->menuTitle,
                $this->capability,
                $this->menuSlug,
                [$this, 'buildPage'],
                $this->iconUrl,
                $this->position,
            );
        }

        else {
            add_submenu_page(
                $this->parentSlug,
                $this->pageTitle,
                $this->menuTitle,
                $this->capability,
                $this->menuSlug,
                [$this, 'buildPage'],
                $this->position,
            );
        }

        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function registerSettings()
    {
        foreach($this->fields as $field) {
            register_setting($this->menuSlug .'-group', $field['name']);
        }
    }

    public function buildPage()
    {
        wp_nonce_field($this->menuSlug .'-settings');

        require self::PAGE_PATH .'page.php';
    }
}
