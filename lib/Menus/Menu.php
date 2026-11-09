<?php

/**
 * Navigation menu factory.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Menus
 */

namespace WPB\Menus;

use WPB\Builder;
use WPB\Forms\AdminForm;
use WPB\Notices;

/**
 * Registers theme menu locations and custom fields on menu items.
 *
 * Fields use the same helpers as a post type or a taxonomy. Each value is
 * stored as post meta on the menu item and copied onto the item object.
 *
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 *
 * @see https://developer.wordpress.org/reference/functions/register_nav_menu/
 * @see https://developer.wordpress.org/reference/hooks/wp_nav_menu_item_custom_fields/
 * @see https://developer.wordpress.org/reference/hooks/wp_update_nav_menu_item/
 * @see https://developer.wordpress.org/reference/hooks/wp_setup_nav_menu_item/
 */
class Menu
{
    use AdminForm;

    /**
     * Menu item properties WordPress already fills.
     *
     * A custom field with one of these names would replace the real value.
     *
     * @var list<string>
     */
    private const array RESERVED_FIELDS = [
        'ID',
        'db_id',
        'menu_item_parent',
        'object_id',
        'object',
        'type',
        'type_label',
        'title',
        'url',
        'target',
        'attr_title',
        'description',
        'classes',
        'xfn',
        'current',
        'current_item_ancestor',
        'current_item_parent',
    ];

    /**
     * Field names already attached to menu items during this request.
     *
     * @var array<string, true>
     */
    private static array $registeredFields = [];

    /**
     * Whether the menu item field styles have been printed.
     */
    private static bool $styled = false;

    /**
     * Theme locations, keyed by slug.
     *
     * @var array<string, string>
     */
    private array $locations = [];

    /**
     * Whether registration was queued on `init`.
     */
    private bool $queued = false;

    /**
     * Whether the menu item editor hooks are attached.
     */
    private bool $hooked = false;

    /**
     * Field names this instance has already claimed.
     *
     * @var array<string, true>
     */
    private array $claimed = [];

    /**
     * Optionally registers one location immediately.
     *
     * Fields added before `init` are still attached. Call `register()` last
     * when the location is set with `location()`.
     *
     * @param string|null $location    Location slug.
     * @param string|null $description Location description shown in the admin.
     */
    public function __construct(?string $location = null, ?string $description = null)
    {
        $this->init();

        if ($location !== null && $description !== null) {
            $this->register($location, $description);
        }
    }

    /**
     * Resets the menu configuration to defaults.
     */
    public function init(): void
    {
        $this->valueType = 'post';
        $this->fieldsType = 'menu';
        $this->id = 0;
        $this->fields = [];
        $this->metaFields = [];
        $this->locations = [];
        $this->queued = false;
        $this->hooked = false;
        $this->claimed = [];
    }

    /**
     * Adds a theme menu location.
     *
     * The slug passes through `sanitize_key()`.
     *
     * @param string $location    Location slug, such as `primary`.
     * @param string $description Location description shown in the admin.
     *
     * @return static
     *
     * @see https://developer.wordpress.org/reference/functions/register_nav_menu/
     */
    public function location(string $location, string $description): static
    {
        $slug = function_exists('sanitize_key') ? sanitize_key($location) : strtolower($location);

        if ($slug === '' || $description === '') {
            Notices::error('A menu location needs a slug and a description.');

            return $this;
        }

        $this->locations[$slug] = $description;

        return $this;
    }

    /**
     * Queues location registration and menu item fields on `init`.
     *
     * @param string|null $location    Location slug.
     * @param string|null $description Location description.
     *
     * @return static|false The menu instance, or false when nothing is configured.
     *
     * @see https://developer.wordpress.org/reference/functions/register_nav_menu/
     * @see https://developer.wordpress.org/reference/hooks/init/
     */
    public function register(?string $location = null, ?string $description = null): static|false
    {
        if ($location !== null || $description !== null) {
            if ($location === null || $description === null) {
                Notices::error('A menu location needs a slug and a description.');

                return false;
            }

            $this->location($location, $description);
        }

        if ($this->locations === [] && $this->fields === []) {
            return false;
        }

        if ($this->queued) {
            return $this;
        }

        $this->queued = true;
        Builder::onInit([$this, 'registerMenu']);

        return $this;
    }

    /**
     * Registers locations and hooks menu item fields.
     *
     * Labels and fields are finalized here so registration runs on `init`.
     *
     * @see https://developer.wordpress.org/reference/functions/register_nav_menu/
     */
    public function registerMenu(): void
    {
        if (function_exists('register_nav_menu')) {
            foreach ($this->locations as $location => $description) {
                register_nav_menu($location, $description);
            }
        }

        $this->prepareFields();

        if ($this->hooked || $this->fields === [] || !function_exists('add_action')) {
            return;
        }

        $this->hooked = true;
        add_action('admin_head', [$this, 'printMenuStyles']);
        add_action('wp_nav_menu_item_custom_fields', [$this, 'renderItemFields'], 10, 2);
        add_action('wp_update_nav_menu_item', [$this, 'saveItem'], 10, 2);
        add_filter('wp_setup_nav_menu_item', [$this, 'setupItem']);
    }

    /**
     * Renders the custom fields inside one menu item in the editor.
     *
     * @param int|string $itemId   Menu item ID.
     * @param object     $menuItem Menu item being edited.
     *
     * @see https://developer.wordpress.org/reference/hooks/wp_nav_menu_item_custom_fields/
     */
    public function renderItemFields(int|string $itemId, object $menuItem): void
    {
        unset($menuItem);

        $itemId = (int) $itemId;

        if ($itemId < 1 || $this->fields === []) {
            return;
        }

        $this->id = $itemId;
        $this->printMenuStyles();

        foreach ($this->fields as $name => $field) {
            if (!is_array($field)) {
                continue;
            }

            $this->renderField($this->fieldForItem($itemId, (string) $name, $field));
        }
    }

    /**
     * Saves custom fields posted with the menu editor.
     *
     * The menu screen nonce is required. Other callers of
     * `wp_update_nav_menu_item()`, such as the Customizer, leave the stored
     * values untouched.
     *
     * @param int|string $menuId Menu term ID.
     * @param int|string $itemId Menu item ID.
     *
     * @see https://developer.wordpress.org/reference/hooks/wp_update_nav_menu_item/
     * @see https://developer.wordpress.org/reference/functions/check_admin_referer/
     */
    public function saveItem(int|string $menuId, int|string $itemId): void
    {
        unset($menuId);

        $itemId = (int) $itemId;

        if (
            $itemId < 1
            || $this->fields === []
            || !isset($_POST['update-nav-menu-nonce'])
            || !is_string($_POST['update-nav-menu-nonce'])
            || !function_exists('is_nav_menu_item')
            || !is_nav_menu_item($itemId)
            || !current_user_can('edit_theme_options')
        ) {
            return;
        }

        check_admin_referer('update-nav_menu', 'update-nav-menu-nonce');

        $this->id = $itemId;

        foreach ($this->fields as $name => $field) {
            if (!is_array($field) || !isset($field['name'])) {
                continue;
            }

            $posted = $field;
            $posted['name'] = $this->requestName($itemId, (string) $name);

            $this->setValue(
                (string) $name,
                $this->sanitizeFieldValue($field, $this->getPostedValue($posted))
            );
        }
    }

    /**
     * Copies stored field values onto the menu item object.
     *
     * @param object $item Menu item being prepared for display.
     *
     * @return object The same item, with one property per custom field.
     *
     * @see https://developer.wordpress.org/reference/hooks/wp_setup_nav_menu_item/
     */
    public function setupItem(object $item): object
    {
        $itemId = (int) ($item->ID ?? 0);

        if ($itemId < 1) {
            return $item;
        }

        foreach (array_keys($this->fields) as $name) {
            $item->{$name} = get_post_meta($itemId, (string) $name, true);
        }

        return $item;
    }

    /**
     * Drops field names that would collide or cannot be posted per item.
     */
    private function prepareFields(): void
    {
        foreach (array_keys($this->fields) as $name) {
            $name = (string) $name;

            if (isset($this->claimed[$name])) {
                continue;
            }

            if (in_array($name, self::RESERVED_FIELDS, true)) {
                Notices::error(sprintf(
                    'Menu item field "%s" is reserved by WordPress.',
                    $name
                ));
                unset($this->fields[$name]);
                continue;
            }

            if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
                Notices::error(sprintf(
                    'Menu item field "%s" must contain only letters, numbers, hyphens, and underscores.',
                    $name
                ));
                unset($this->fields[$name]);
                continue;
            }

            if (isset(self::$registeredFields[$name])) {
                Notices::error(sprintf(
                    'Menu item field "%s" is already registered.',
                    $name
                ));
                unset($this->fields[$name]);
                continue;
            }

            self::$registeredFields[$name] = true;
            $this->claimed[$name] = true;
        }
    }

    /**
     * Builds the field configuration posted for one menu item.
     *
     * The request name includes the item ID because the menu editor submits
     * every item in one form. The stored meta key stays the field name.
     *
     * @param int                  $itemId Menu item ID.
     * @param string               $name   Field name.
     * @param array<string, mixed> $field  Field configuration.
     *
     * @return array<string, mixed> Field configuration for this item.
     */
    private function fieldForItem(int $itemId, string $name, array $field): array
    {
        $requestName = $this->requestName($itemId, $name);
        $attributes = (string) ($field['attributes'] ?? '');

        if ($attributes !== '' && str_contains($attributes, $name . '-description')) {
            $field['attributes'] = str_replace(
                $name . '-description',
                $requestName . '-description',
                $attributes
            );
        }

        $field['key'] = $name;
        $field['storage'] = $name;
        $field['name'] = $requestName;
        $field['val'] = '';

        return $field;
    }

    /**
     * Returns the request key for one field on one menu item.
     *
     * @param int    $itemId Menu item ID.
     * @param string $name   Field name.
     *
     * @return string Request key.
     */
    private function requestName(int $itemId, string $name): string
    {
        return 'wpb_menu_item_' . $itemId . '_' . $name;
    }

    /**
     * Prints the spacing used by fields inside the menu item editor.
     *
     * @see https://developer.wordpress.org/reference/hooks/admin_head/
     */
    public function printMenuStyles(): void
    {
        if (self::$styled) {
            return;
        }

        self::$styled = true;

        echo '<style>.menu-item-settings .wpb-menu-field{margin:0 0 12px}.menu-item-settings .wpb-menu-field>.description{display:block;margin-top:4px}</style>';
    }
}
