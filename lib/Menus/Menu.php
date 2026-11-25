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
 * A field registered with locations is shown only on menus assigned to those
 * locations. Registering that name again grants the same field to the other
 * menu. The menu screen and the Customizer both save the value.
 *
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 *
 * @see https://developer.wordpress.org/reference/functions/register_nav_menu/
 * @see https://developer.wordpress.org/reference/hooks/wp_nav_menu_item_custom_fields/
 * @see https://developer.wordpress.org/reference/hooks/wp_nav_menu_item_custom_fields_customize_template/
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
     * Menu that first registered each field name.
     *
     * A later menu does not replace this definition. It is granted the field.
     *
     * @var array<string, self>
     */
    private static array $fieldOwners = [];

    /**
     * Customizer values waiting until menu items have real IDs.
     *
     * @var array<string, mixed>
     */
    private static array $pendingCustomizerValues = [];

    /**
     * Whether the Customizer hooks have been attached.
     */
    private static bool $customizerHooked = false;

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
     * Locations allowed to edit each field owned by this instance.
     *
     * `*` means every menu. Location slugs mean the menu assigned there.
     *
     * @var array<string, array<string, true>>
     */
    private array $grants = [];

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
        $this->grants = [];
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
        add_action('wp_nav_menu_item_custom_fields', [$this, 'renderItemFields'], 10, 5);
        add_action('wp_nav_menu_item_custom_fields_customize_template', [$this, 'renderCustomizerTemplate']);
        add_action('wp_update_nav_menu_item', [$this, 'saveItem'], 10, 2);
        add_filter('wp_setup_nav_menu_item', [$this, 'setupItem']);
        $this->hookCustomizer();
    }

    /**
     * Renders the custom fields inside one menu item in the editor.
     *
     * @param int|string $itemId   Menu item ID.
     * @param object     $menuItem Menu item being edited.
     * @param int        $depth    Unused. Accepted because the hook passes it.
     * @param mixed      $args     Unused. Accepted because the hook passes it.
     * @param int|string $menuId   Unused. The walker does not pass the menu term.
     *
     * @see https://developer.wordpress.org/reference/hooks/wp_nav_menu_item_custom_fields/
     */
    public function renderItemFields(
        int|string $itemId,
        object $menuItem,
        int $depth = 0,
        mixed $args = null,
        int|string $menuId = 0
    ): void {
        unset($menuItem, $depth, $args, $menuId);

        $itemId = (int) $itemId;

        if ($itemId < 1 || $this->fields === []) {
            return;
        }

        $currentMenu = $this->menuIdForItem($itemId);

        $this->id = $itemId;
        $this->printMenuStyles();

        foreach ($this->fields as $name => $field) {
            if (!is_array($field) || !$this->fieldApplies((string) $name, $currentMenu)) {
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
        $menuId = (int) $menuId;
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

            if (!$this->fieldApplies((string) $name, $menuId)) {
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

            if (isset(self::$fieldOwners[$name])) {
                self::$fieldOwners[$name]->grantField($name, array_keys($this->locations));
                unset($this->fields[$name]);
                continue;
            }

            self::$fieldOwners[$name] = $this;
            $this->claimed[$name] = true;
            $this->grantField($name, array_keys($this->locations));
        }
    }

    /**
     * Builds the field configuration posted for one menu item.
     *
     * The request name includes the item ID because the menu editor submits
     * every item in one form. The stored meta key stays the field name.
     *
     * @param int|string           $itemId Menu item ID, or a Customizer template token.
     * @param string               $name   Field name.
     * @param array<string, mixed> $field  Field configuration.
     *
     * @return array<string, mixed> Field configuration for this item.
     */
    private function fieldForItem(int|string $itemId, string $name, array $field): array
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
     * @param int|string $itemId Menu item ID, or a Customizer template token.
     * @param string     $name   Field name.
     *
     * @return string Request key.
     */
    private function requestName(int|string $itemId, string $name): string
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

    /**
     * Lets another menu edit a field this instance already defined.
     *
     * The stored key and the field controls stay with the first registration.
     * An empty location list grants the field to every menu.
     *
     * @param string       $name      Field name.
     * @param list<string> $locations Location slugs from the menu that asked again.
     */
    private function grantField(string $name, array $locations): void
    {
        if ($locations === [] || isset($this->grants[$name]['*'])) {
            $this->grants[$name] = ['*' => true];

            return;
        }

        foreach ($locations as $location) {
            if ($location !== '') {
                $this->grants[$name][$location] = true;
            }
        }
    }

    /**
     * Whether this menu should show and save the field.
     *
     * @param string $name   Field name.
     * @param int    $menuId Nav menu term ID being edited.
     */
    private function fieldApplies(string $name, int $menuId): bool
    {
        $grants = $this->grants[$name] ?? [];

        if ($grants === [] || isset($grants['*'])) {
            return true;
        }

        if ($menuId === 0) {
            return false;
        }

        foreach (array_keys($grants) as $location) {
            if ($this->menuOwnsLocation($location, $menuId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the nav menu that contains one item.
     *
     * @param int $itemId Menu item ID.
     */
    private function menuIdForItem(int $itemId): int
    {
        if ($itemId > 0 && function_exists('wp_get_post_terms')) {
            $terms = wp_get_post_terms($itemId, 'nav_menu', ['fields' => 'ids']);

            if (is_array($terms) && isset($terms[0])) {
                return (int) $terms[0];
            }
        }

        if (isset($_REQUEST['menu'])) {
            $menu = wp_unslash($_REQUEST['menu']);

            return absint(is_scalar($menu) ? $menu : 0);
        }

        return 0;
    }

    /**
     * Whether the menu being saved is assigned to one theme location.
     *
     * A new menu posts the location checkbox before its term ID exists, so the
     * submitted value is `0`. The checkbox still means this menu owns it.
     *
     * @param string $location Theme location slug.
     * @param int    $menuId   Nav menu term ID being saved or edited.
     */
    private function menuOwnsLocation(string $location, int $menuId): bool
    {
        if ($this->assignedMenuId($location) === $menuId) {
            return true;
        }

        if ($menuId < 1 || !$this->locationCheckedForCurrentMenu($location)) {
            return false;
        }

        return $this->assignedMenuId($location) === 0;
    }

    /**
     * Whether the menu editor included one location in this request.
     *
     * @param string $location Theme location slug.
     */
    private function locationCheckedForCurrentMenu(string $location): bool
    {
        if (
            !isset($_POST['update-nav-menu-nonce'], $_POST['menu-locations'])
            || !is_string($_POST['update-nav-menu-nonce'])
        ) {
            return false;
        }

        $posted = wp_unslash($_POST['menu-locations']);

        return is_array($posted) && array_key_exists($location, $posted);
    }

    /**
     * Returns the menu assigned to one theme location.
     *
     * The Customizer value wins while that screen is saving. The menu editor
     * form wins for a location included in the request. Otherwise the saved
     * assignment is used.
     *
     * @param string $location Theme location slug.
     */
    private function assignedMenuId(string $location): int
    {
        global $wp_customize;

        if ($wp_customize instanceof \WP_Customize_Manager) {
            $setting = $wp_customize->get_setting('nav_menu_locations[' . $location . ']');

            if ($setting instanceof \WP_Customize_Setting) {
                $posted = $setting->post_value(null);

                if (is_scalar($posted)) {
                    return (int) $posted;
                }

                return (int) $setting->value();
            }
        }

        if (
            isset($_POST['update-nav-menu-nonce'], $_POST['menu-locations'])
            && is_string($_POST['update-nav-menu-nonce'])
        ) {
            $posted = wp_unslash($_POST['menu-locations']);

            if (is_array($posted) && array_key_exists($location, $posted) && is_scalar($posted[$location])) {
                return absint($posted[$location]);
            }
        }

        if (!function_exists('get_nav_menu_locations')) {
            return 0;
        }

        $locations = get_nav_menu_locations();

        return (int) ($locations[$location] ?? 0);
    }

    /**
     * Attaches the Customizer setting hooks once per request.
     */
    private function hookCustomizer(): void
    {
        if (self::$customizerHooked || !function_exists('add_filter')) {
            return;
        }

        self::$customizerHooked = true;
        add_filter('customize_dynamic_setting_args', [self::class, 'customizerSettingArgs'], 10, 2);
        add_filter('customize_dynamic_setting_class', [self::class, 'customizerSettingClass'], 10, 3);
        add_action('customize_controls_enqueue_scripts', [self::class, 'enqueueCustomizer']);
        add_action('customize_save_after', [self::class, 'flushCustomizerValues']);
    }

    /**
     * Prints menu fields inside the Customizer menu item template.
     *
     * The template is shared by every item. Script decides which fields belong
     * to the menu open in the control. Repeater rows stay on the menu screen
     * because one template cannot rebuild saved rows. Rich text is a textarea.
     *
     * @see https://developer.wordpress.org/reference/hooks/wp_nav_menu_item_custom_fields_customize_template/
     */
    public function renderCustomizerTemplate(): void
    {
        $this->printMenuStyles();

        foreach ($this->fields as $name => $field) {
            if (!is_array($field)) {
                continue;
            }

            $type = (string) ($field['type'] ?? 'text');

            if ($type === 'repeater') {
                continue;
            }

            if ($type === 'rich-text') {
                $field['type'] = 'textarea';
            }

            $rendered = $this->fieldForItem('{{ data.menu_item_id }}', (string) $name, $field);
            unset($rendered['storage']);
            $rendered['template_item'] = '{{ data.menu_item_id }}';
            $this->renderField($rendered);
        }
    }

    /**
     * Accepts one menu-item field setting created from the Customizer.
     *
     * @param mixed  $args      Existing dynamic setting arguments.
     * @param string $settingId Setting ID.
     *
     * @return mixed Setting arguments, or the original value when the ID is unrelated.
     *
     * @see https://developer.wordpress.org/reference/hooks/customize_dynamic_setting_args/
     */
    public static function customizerSettingArgs(mixed $args, string $settingId): mixed
    {
        if (self::matchSettingId($settingId) === null) {
            return $args;
        }

        return [
            'type'              => 'wpb_menu_item',
            'transport'         => 'refresh',
            'capability'        => 'edit_theme_options',
            'sanitize_callback' => [self::class, 'sanitizeCustomizerValue'],
        ];
    }

    /**
     * Uses the menu item field setting class for recognized IDs.
     *
     * @param string               $class     Setting class selected so far.
     * @param string               $settingId Setting ID.
     * @param array<string, mixed> $args      Setting arguments.
     *
     * @see https://developer.wordpress.org/reference/hooks/customize_dynamic_setting_class/
     */
    public static function customizerSettingClass(string $class, string $settingId, array $args): string
    {
        unset($args);

        if (self::matchSettingId($settingId) === null) {
            return $class;
        }

        return MenuItemFieldSetting::class;
    }

    /**
     * Sanitizes a menu field submitted from the Customizer.
     *
     * @param mixed                 $value   Submitted value.
     * @param \WP_Customize_Setting $setting Setting being saved.
     *
     * @return string|array Sanitized value.
     */
    public static function sanitizeCustomizerValue(mixed $value, \WP_Customize_Setting $setting): string|array
    {
        $parsed = self::matchSettingId($setting->id);
        $owner = $parsed === null ? null : (self::$fieldOwners[$parsed['field']] ?? null);
        $field = ($owner instanceof self && $parsed !== null) ? ($owner->fields[$parsed['field']] ?? null) : null;

        if (!$owner instanceof self || !is_array($field)) {
            return '';
        }

        return $owner->sanitizeFieldValue($field, $value);
    }

    /**
     * Holds one Customizer value until every menu item has been saved.
     *
     * @param string $settingId Setting ID.
     * @param mixed  $value     Sanitized value.
     */
    public static function queueCustomizerValue(string $settingId, mixed $value): void
    {
        if (self::matchSettingId($settingId) === null) {
            return;
        }

        self::$pendingCustomizerValues[$settingId] = $value;
    }

    /**
     * Writes Customizer field values after menu items exist.
     *
     * Placeholder items receive a real post ID during the same save. Writing
     * here can see that ID.
     *
     * @param mixed $manager Unused Customizer manager.
     *
     * @see https://developer.wordpress.org/reference/hooks/customize_save_after/
     */
    public static function flushCustomizerValues(mixed $manager = null): void
    {
        unset($manager);

        $pending = self::$pendingCustomizerValues;
        self::$pendingCustomizerValues = [];

        foreach ($pending as $settingId => $value) {
            self::writeCustomizerValue((string) $settingId, $value);
        }
    }

    /**
     * Copies unsaved Customizer values onto menu items in the preview.
     */
    public static function previewCustomizerFields(): void
    {
        static $watching = false;

        if ($watching || !function_exists('add_filter')) {
            return;
        }

        $watching = true;
        add_filter('wp_setup_nav_menu_item', [self::class, 'previewMenuItem'], 20);
    }

    /**
     * Replaces stored field values with the Customizer changeset.
     *
     * @param object $item Menu item being prepared.
     *
     * @return object The same item.
     */
    public static function previewMenuItem(object $item): object
    {
        global $wp_customize;

        if (!$wp_customize instanceof \WP_Customize_Manager) {
            return $item;
        }

        $itemId = (int) ($item->ID ?? 0);

        if ($itemId === 0) {
            return $item;
        }

        foreach (array_keys(self::$fieldOwners) as $name) {
            $setting = $wp_customize->get_setting(self::settingId($itemId, (string) $name));

            if (!$setting instanceof \WP_Customize_Setting) {
                continue;
            }

            $value = $setting->post_value(null);

            if ($value === null) {
                continue;
            }

            $item->{$name} = $value;
        }

        return $item;
    }

    /**
     * Prints the Customizer control script and the values already stored.
     *
     * @see https://developer.wordpress.org/reference/hooks/customize_controls_enqueue_scripts/
     */
    public static function enqueueCustomizer(): void
    {
        $payload = self::customizerPayload();

        if ($payload['fields'] === []) {
            return;
        }

        foreach (self::$fieldOwners as $owner) {
            foreach ($owner->fields as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $type = (string) ($field['type'] ?? '');

                if ($type === 'image' || $type === 'file') {
                    $owner->enqueueAdminImage();
                }
            }
        }

        if (!function_exists('wp_enqueue_script') || !function_exists('wp_add_inline_script')) {
            return;
        }

        $path = dirname(__DIR__, 2) . '/assets/js/customize-menu-fields.js';
        $url = is_readable($path) ? Builder::url($path) : '';

        if ($url === '') {
            return;
        }

        wp_enqueue_script(
            'wpb-customize-menu-fields',
            $url,
            ['customize-controls'],
            (string) filemtime($path),
            true
        );

        wp_add_inline_script(
            'wpb-customize-menu-fields',
            'var wpbMenuFields = ' . wp_json_encode($payload) . ';',
            'before'
        );
    }

    /**
     * Stores one sanitized Customizer value on its menu item.
     *
     * @param string $settingId Setting ID.
     * @param mixed  $value     Sanitized value.
     */
    private static function writeCustomizerValue(string $settingId, mixed $value): void
    {
        $parsed = self::matchSettingId($settingId);
        $owner = $parsed === null ? null : (self::$fieldOwners[$parsed['field']] ?? null);

        if (
            $parsed === null
            || !$owner instanceof self
            || !function_exists('current_user_can')
            || !current_user_can('edit_theme_options')
            || !$owner->fieldApplies($parsed['field'], self::itemMenuId($parsed['item']))
        ) {
            return;
        }

        $itemId = self::resolveItemId($parsed['item']);

        if ($itemId < 1 || !function_exists('is_nav_menu_item') || !is_nav_menu_item($itemId)) {
            return;
        }

        update_post_meta($itemId, $parsed['field'], $value);
    }

    /**
     * Returns the menu that owns an item inside the Customizer.
     *
     * @param int $itemId Menu item ID from the setting, including a placeholder.
     */
    private static function itemMenuId(int $itemId): int
    {
        global $wp_customize;

        if ($wp_customize instanceof \WP_Customize_Manager) {
            $setting = $wp_customize->get_setting('nav_menu_item[' . $itemId . ']');

            if ($setting instanceof \WP_Customize_Setting) {
                $value = $setting->post_value(null);

                if (!is_array($value)) {
                    $stored = $setting->value();
                    $value = is_array($stored) ? $stored : null;
                }

                if (is_array($value) && isset($value['nav_menu_term_id'])) {
                    return (int) $value['nav_menu_term_id'];
                }
            }
        }

        $resolved = self::resolveItemId($itemId);

        if ($resolved > 0 && function_exists('wp_get_post_terms')) {
            $terms = wp_get_post_terms($resolved, 'nav_menu', ['fields' => 'ids']);

            if (is_array($terms) && isset($terms[0])) {
                return (int) $terms[0];
            }
        }

        return 0;
    }

    /**
     * Returns the saved post ID for a Customizer menu item.
     *
     * @param int $itemId Positive post ID, or the negative placeholder ID.
     */
    private static function resolveItemId(int $itemId): int
    {
        if ($itemId > 0) {
            return $itemId;
        }

        global $wp_customize;

        if (!$wp_customize instanceof \WP_Customize_Manager) {
            return 0;
        }

        $setting = $wp_customize->get_setting('nav_menu_item[' . $itemId . ']');

        if ($setting instanceof \WP_Customize_Nav_Menu_Item_Setting && (int) $setting->post_id > 0) {
            return (int) $setting->post_id;
        }

        return 0;
    }

    /**
     * Builds the field map passed to the Customizer script.
     *
     * @return array{
     *     fields: array<string, array{locations: list<string>}>,
     *     values: object|array<string, array<string, mixed>>,
     *     media: object|array<string, array{url: string, alt: string, name: string}>
     * }
     */
    private static function customizerPayload(): array
    {
        $fields = [];
        $types = [];

        foreach (self::$fieldOwners as $name => $owner) {
            $field = $owner->fields[$name] ?? null;
            $type = is_array($field) ? (string) ($field['type'] ?? 'text') : '';

            if (!is_array($field) || $type === 'repeater') {
                continue;
            }

            $grants = $owner->grants[$name] ?? [];
            $fields[$name] = [
                'locations' => isset($grants['*']) ? [] : array_keys($grants),
            ];
            $types[$name] = $type;
        }

        $values = [];
        $media = [];

        if ($fields !== [] && function_exists('get_posts') && function_exists('get_post_meta')) {
            $itemIds = get_posts([
                'post_type'      => 'nav_menu_item',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'orderby'        => 'ID',
                'order'          => 'ASC',
            ]);

            foreach ($itemIds as $itemId) {
                $itemId = (int) $itemId;
                $stored = [];

                foreach ($types as $name => $type) {
                    $stored[$name] = get_post_meta($itemId, $name, true);
                    self::collectMedia($media, $type, $stored[$name]);
                }

                $values[(string) $itemId] = $stored;
            }
        }

        return [
            'fields' => $fields,
            'values' => $values === [] ? new \stdClass() : $values,
            'media'  => $media === [] ? new \stdClass() : $media,
        ];
    }

    /**
     * Adds attachment display data used to fill image and file fields.
     *
     * @param array<string, array{url: string, alt: string, name: string}> $media Collected media.
     * @param string                                                        $type  Field type.
     * @param mixed                                                         $value Stored field value.
     */
    private static function collectMedia(array &$media, string $type, mixed $value): void
    {
        if (($type !== 'image' && $type !== 'file') || !function_exists('wp_get_attachment_url')) {
            return;
        }

        foreach (is_array($value) ? $value : [$value] as $id) {
            $id = (int) $id;

            if ($id < 1 || isset($media[(string) $id])) {
                continue;
            }

            $url = $type === 'image' && function_exists('wp_get_attachment_image_url')
                ? wp_get_attachment_image_url($id, 'medium')
                : wp_get_attachment_url($id);

            $media[(string) $id] = [
                'url'  => is_string($url) ? $url : '',
                'alt'  => $type === 'image' && function_exists('get_post_meta')
                    ? (string) get_post_meta($id, '_wp_attachment_image_alt', true)
                    : '',
                'name' => function_exists('get_the_title') ? (string) get_the_title($id) : '',
            ];
        }
    }

    /**
     * Returns the Customizer setting ID for one field on one item.
     *
     * @param int    $itemId Menu item ID.
     * @param string $name   Field name.
     */
    private static function settingId(int $itemId, string $name): string
    {
        return 'wpb_menu_item[' . $itemId . '][' . $name . ']';
    }

    /**
     * Reads the item ID and field name from a Customizer setting ID.
     *
     * @param string $settingId Setting ID.
     *
     * @return array{item: int, field: string}|null Parsed ID, or null when it is not a menu field.
     */
    private static function matchSettingId(string $settingId): ?array
    {
        if (!preg_match('/^wpb_menu_item\[(-?\d+)\]\[([A-Za-z0-9_-]+)\]$/', $settingId, $matches)) {
            return null;
        }

        return [
            'item'  => (int) $matches[1],
            'field' => $matches[2],
        ];
    }
}
