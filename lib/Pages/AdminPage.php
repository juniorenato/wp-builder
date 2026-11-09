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
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 *
 * @see https://developer.wordpress.org/reference/functions/add_menu_page/
 * @see https://developer.wordpress.org/reference/functions/add_submenu_page/
 */
class AdminPage
{
    use AdminForm;

    /**
     * Parent menu slug.
     */
    private string $parentSlug;

    /**
     * Page title.
     */
    protected string $pageTitle;

    /**
     * Menu title.
     */
    private string $menuTitle;

    /**
     * Required capability.
     */
    private string $capability;

    /**
     * Menu slug.
     */
    protected string $menuSlug;

    /**
     * Menu icon URL or Dashicon class.
     */
    private string $iconUrl;

    /**
     * Menu position.
     */
    private int $position;

    /**
     * Page render callback.
     *
     * @var callable
     */
    private $callback;

    /**
     * Optionally registers an admin page immediately.
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
     * @param string $slug Parent slug without `.php`.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/functions/add_submenu_page/
     */
    public function parent(string $slug): static
    {
        return $this->parentSlug($slug);
    }

    /**
     * Sets the page title, menu title, and slug from a single value.
     *
     * @param string $title Page title.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/functions/add_menu_page/
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
     * @param string $slug Menu slug.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/functions/add_menu_page/
     */
    public function slug(string $slug): static
    {
        return $this->menuSlug($slug);
    }

    /**
     * Sets the menu icon.
     *
     * @param string $icon Dashicon class or icon URL.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/resource/dashicons/
     * @see https://developer.wordpress.org/reference/functions/add_menu_page/
     */
    public function icon(string $icon): static
    {
        return $this->iconUrl($icon);
    }

    /**
     * Sets the parent menu slug, appending `.php` when needed.
     *
     * @param string $slug Parent slug.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/functions/add_submenu_page/
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
     * @param string $title Page title.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/functions/add_menu_page/
     */
    public function pageTitle(string $title): static
    {
        $this->pageTitle = ucfirst($title);

        return $this;
    }

    /**
     * Sets the menu title.
     *
     * @param string $title Menu title.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/functions/add_menu_page/
     */
    public function menuTitle(string $title): static
    {
        $this->menuTitle = ucfirst($title);

        return $this;
    }

    /**
     * Sets the required capability.
     *
     * @param string $capability WordPress capability.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/plugins/users/roles-and-capabilities/
     * @see https://developer.wordpress.org/reference/functions/add_menu_page/
     */
    public function capability(string $capability): static
    {
        $this->capability = $capability;

        return $this;
    }

    /**
     * Sets the sanitized menu slug.
     *
     * @param string $slug Unsanitized slug.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/functions/add_menu_page/
     */
    public function menuSlug(string $slug): static
    {
        $this->menuSlug = sanitize_title($slug);

        return $this;
    }

    /**
     * Sets the menu icon URL or Dashicon class.
     *
     * @param string $icon Icon URL or Dashicon class.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/resource/dashicons/
     * @see https://developer.wordpress.org/reference/functions/add_menu_page/
     */
    public function iconUrl(string $icon): static
    {
        $this->iconUrl = $icon;

        return $this;
    }

    /**
     * Sets the menu position.
     *
     * @param string $position Menu position.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/functions/add_menu_page/
     */
    public function position(string $position): static
    {
        $this->position = (int) $position;

        return $this;
    }

    /**
     * Sets the page render callback.
     *
     * @param callable $callback Render callback.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/functions/add_menu_page/
     */
    public function callback(mixed $callback): static
    {
        $this->callback = $callback;

        return $this;
    }

    /**
     * Queues the admin page for registration.
     *
     * @param string|null   $title    Page title.
     * @param callable|null $callback Render callback.
     * @param string|null   $parent   Parent menu slug.
     *
     * @return bool True when required configuration is present.
     *
     * @see https://developer.wordpress.org/reference/hooks/admin_menu/
     * @see https://developer.wordpress.org/reference/functions/add_menu_page/
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
     * @see https://developer.wordpress.org/reference/functions/add_menu_page/
     * @see https://developer.wordpress.org/reference/functions/add_submenu_page/
     * @see https://developer.wordpress.org/reference/hooks/admin_init/
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
     * @see https://developer.wordpress.org/reference/functions/register_setting/
     * @see https://developer.wordpress.org/plugins/settings/settings-api/
     */
    public function registerSettings(): void
    {
        foreach ($this->fields as $field) {
            $args = [
                'sanitize_callback' => function ($value) use ($field) {
                    return $this->sanitizeFieldValue($field, $value);
                },
            ];

            if (($field['type'] ?? '') === 'repeater') {
                $args['type'] = 'array';
            }

            register_setting($this->menuSlug . '-group', $field['name'], $args);
        }
    }

    /**
     * Renders the admin page view.
     *
     * @see https://developer.wordpress.org/plugins/settings/settings-api/
     * @see https://developer.wordpress.org/reference/functions/settings_fields/
     */
    public function buildPage(): void
    {
        require Builder::PATH['PAGE'] . 'page.php';
    }
}
