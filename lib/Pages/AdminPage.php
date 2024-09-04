<?php

namespace WPB\Pages;

use WPB\Forms\AdminForm;

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
    private $callback;

    public function __construct(
        ?string $title = null,
        $callback = null,
        ?string $parent = null
    ) {
        $this->valueType = 'option';

        $this->init();

        if(1 == 1
            && $title
            && $callback
        ) { $this->register($title, $callback, $parent); }

    }

    public function init()
    {
        $this->parentSlug = '';
        $this->pageTitle = '';
        $this->menuTitle = '';
        $this->capability = 'manage_options';
        $this->menuSlug = '';
        $this->iconUrl = 'dashicons-laptop';
        $this->position = 2;
        $this->callback = [
            $this,
            'buildPage'
        ];
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
        $this->pageTitle = ucfirst($title);

        return $this;
    }

    public function menuTitle(string $title): AdminPage
    {
        $this->menuTitle = ucfirst($title);

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

    public function callback($callback): AdminPage
    {
        $this->callback = $callback;

        return $this;
    }

    /**
     * -------------------------------------------------------------------------
     * Register Admin Page
     * -------------------------------------------------------------------------
     *
     * @param string|null $title
     * @param string|list<string>|null $callback
     * @param string|null $parent
     * @return boolean
     */
    public function register(
        ?string $title = null,
        $callback = null,
        ?string $parent = null
    ): bool {
        if($title) $this->title($title);
        if($parent) $this->parent($parent);
        if($callback) $this->callback($callback);

        if(1 == 0
            || !$this->pageTitle
            || !$this->menuTitle
            || !$this->menuSlug
        ) { return false; }

        add_action('admin_menu', [$this, 'addMenuPage']);

        return true;
    }

    public function addMenuPage()
    {
        if(!$this->parentSlug) {
            add_menu_page(
                $this->pageTitle,
                $this->menuTitle,
                $this->capability,
                $this->menuSlug,
                $this->callback,
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
                $this->callback,
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

        require WPB_PAGE_PATH .'page.php';
    }
}
