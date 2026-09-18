<?php

/**
 * Admin page factory.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Pages
 */

namespace WPB\Pages;

use WPB\Builder;
use WPB\Forms\AdminForm;

/**
 * Registers a top-level or submenu admin page with settings fields.
 *
 * @since  0.2.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class AdminPage
{
    use AdminForm;

    /**
     * Parent menu slug.
     *
     * @since 0.2.0
     */
    private string $parentSlug;

    /**
     * Page title.
     *
     * @since 0.2.0
     */
    private string $pageTitle;

    /**
     * Menu title.
     *
     * @since 0.2.0
     */
    private string $menuTitle;

    /**
     * Required capability.
     *
     * @since 0.2.0
     */
    private string $capability;

    /**
     * Menu slug.
     *
     * @since 0.2.0
     */
    private string $menuSlug;

    /**
     * Menu icon URL or Dashicon class.
     *
     * @since 0.2.0
     */
    private string $iconUrl;

    /**
     * Menu position.
     *
     * @since 0.2.0
     */
    private int $position;

    /**
     * Page render callback.
     *
     * @since 0.2.0
     *
     * @var callable
     */
    private $callback;

    /**
     * Optionally registers an admin page immediately.
     *
     * @since 0.2.0
     *
     * @param string|null   $title    Page title.
     * @param callable|null $callback Render callback.
     * @param string|null   $parent   Parent menu slug.
     */
    public function __construct(
        ?string $title = null,
        mixed $callback = null,
        ?string $parent = null
    ) {
        $this->valueType = 'option';

        $this->init();

        if ($title && $callback) {
            $this->register($title, $callback, $parent);
        }
    }

    /**
     * Resets the page configuration to defaults.
     *
     * @since 0.2.0
     */
    public function init(): void
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

    /**
     * Sets the parent menu from a short slug.
     *
     * @since 0.2.0
     *
     * @param string $slug Parent slug without `.php`.
     *
     * @return static
     */
    public function parent(string $slug): static
    {
        return $this->parentSlug($slug);
    }

    /**
     * Sets the page title, menu title, and slug from a single value.
     *
     * @since 0.2.0
     *
     * @param string $title Page title.
     *
     * @return static
     */
    public function title(string $title): static
    {
        $this->pageTitle($title);
        $this->menuTitle($title);
        $this->menuSlug($title);

        return $this;
    }

    /**
     * Sets the menu slug.
     *
     * @since 0.2.0
     *
     * @param string $slug Menu slug.
     *
     * @return static
     */
    public function slug(string $slug): static
    {
        return $this->menuSlug($slug);
    }

    /**
     * Sets the menu icon.
     *
     * @since 0.2.0
     *
     * @param string $icon Dashicon class or icon URL.
     *
     * @return static
     */
    public function icon(string $icon): static
    {
        return $this->iconUrl($icon);
    }

    /**
     * Sets the parent menu slug, appending `.php` when needed.
     *
     * @since 0.2.0
     *
     * @param string $slug Parent slug.
     *
     * @return static
     */
    public function parentSlug(string $slug): static
    {
        $slug = str_replace('.php', '', $slug);

        $this->parentSlug = $slug . '.php';

        return $this;
    }

    /**
     * Sets the page title.
     *
     * @since 0.2.0
     *
     * @param string $title Page title.
     *
     * @return static
     */
    public function pageTitle(string $title): static
    {
        $this->pageTitle = ucfirst($title);

        return $this;
    }

    /**
     * Sets the menu title.
     *
     * @since 0.2.0
     *
     * @param string $title Menu title.
     *
     * @return static
     */
    public function menuTitle(string $title): static
    {
        $this->menuTitle = ucfirst($title);

        return $this;
    }

    /**
     * Sets the required capability.
     *
     * @since 0.2.0
     *
     * @param string $capability WordPress capability.
     *
     * @return static
     */
    public function capability(string $capability): static
    {
        $this->capability = $capability;

        return $this;
    }

    /**
     * Sets the sanitized menu slug.
     *
     * @since 0.2.0
     *
     * @param string $slug Unsanitized slug.
     *
     * @return static
     */
    public function menuSlug(string $slug): static
    {
        $this->menuSlug = sanitize_title($slug);

        return $this;
    }

    /**
     * Sets the menu icon URL or Dashicon class.
     *
     * @since 0.2.0
     *
     * @param string $icon Icon URL or Dashicon class.
     *
     * @return static
     */
    public function iconUrl(string $icon): static
    {
        $this->iconUrl = $icon;

        return $this;
    }

    /**
     * Sets the menu position.
     *
     * @since 0.2.0
     *
     * @param string $position Menu position.
     *
     * @return static
     */
    public function position(string $position): static
    {
        $this->position = (int) $position;

        return $this;
    }

    /**
     * Sets the page render callback.
     *
     * @since 0.2.0
     *
     * @param callable $callback Render callback.
     *
     * @return static
     */
    public function callback(mixed $callback): static
    {
        $this->callback = $callback;

        return $this;
    }

    /**
     * Queues the admin page for registration.
     *
     * @since 0.2.0
     *
     * @param string|null   $title    Page title.
     * @param callable|null $callback Render callback.
     * @param string|null   $parent   Parent menu slug.
     *
     * @return bool True when required configuration is present.
     */
    public function register(
        ?string $title = null,
        mixed $callback = null,
        ?string $parent = null
    ): bool {
        if ($title) {
            $this->title($title);
        }

        if ($parent) {
            $this->parent($parent);
        }

        if ($callback) {
            $this->callback($callback);
        }

        if (!$this->pageTitle || !$this->menuTitle || !$this->menuSlug) {
            return false;
        }

        add_action('admin_menu', [$this, 'addMenuPage']);

        return true;
    }

    /**
     * Registers the menu page or submenu page.
     *
     * @since 0.2.0
     */
    public function addMenuPage(): void
    {
        if (!$this->parentSlug) {
            add_menu_page(
                $this->pageTitle,
                $this->menuTitle,
                $this->capability,
                $this->menuSlug,
                $this->callback,
                $this->iconUrl,
                $this->position,
            );
        } else {
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

    /**
     * Registers settings for each page field.
     *
     * @since 0.2.0
     */
    public function registerSettings(): void
    {
        foreach ($this->fields as $field) {
            register_setting($this->menuSlug . '-group', $field['name'], [
                'sanitize_callback' => function ($value) use ($field) {
                    return $this->sanitizeFieldValue($field, $value);
                },
            ]);
        }
    }

    /**
     * Renders the admin page view.
     *
     * @since 0.2.0
     */
    public function buildPage(): void
    {
        require Builder::PATH['PAGE'] . 'page.php';
    }
}
