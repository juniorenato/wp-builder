<?php

/**
 * Appearance → Customize registration.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Customizer
 */

namespace WPB\Customizer;

use WPB\Builder;
use WPB\Notices;

/**
 * Registers panels, sections, and fields in the WordPress Customizer.
 *
 * A section without a panel appears at the root of Appearance → Customize,
 * on the same level as Site Identity. Values are theme mods when the library
 * runs as a theme, and options when it runs as a plugin. A section can store
 * its own fields as options with {@see Section::saveAsOption()}.
 *
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 *
 * @see https://developer.wordpress.org/reference/hooks/customize_register/
 * @see https://developer.wordpress.org/reference/functions/get_theme_mod/
 */
class Customizer
{
    /**
     * Capability required to edit the registered settings.
     */
    private const string CAPABILITY = 'edit_theme_options';

    /**
     * Panels declared on this instance.
     *
     * @var list<Panel>
     */
    private array $panels = [];

    /**
     * Sections declared on this instance.
     *
     * @var list<Section>
     */
    private array $sections = [];

    /**
     * Forced storage type, or an empty string to follow the library environment.
     */
    private string $storage = '';

    /**
     * Whether the current declarations were copied into the static registry.
     */
    private bool $collected = false;

    /**
     * Whether `customize_register` was hooked for this instance.
     */
    private bool $queued = false;

    /**
     * Whether this instance declared a repeater.
     */
    private bool $repeaterAssets = false;

    /**
     * Whether a declared repeater contains an image or a file.
     */
    private bool $repeaterMedia = false;

    /**
     * Whether a declared repeater contains a color.
     */
    private bool $repeaterColor = false;

    /**
     * Settings declared during this request, keyed by setting id.
     *
     * @var array<string, array{storage: string, default: mixed}>
     */
    private static array $settings = [];

    /**
     * Panels frozen when `register()` ran.
     *
     * @var list<array{id: string, title: string, description: string, priority: int}>
     */
    private array $panelConfig = [];

    /**
     * Sections frozen when `register()` ran.
     *
     * @var list<array{
     *     id: string,
     *     title: string,
     *     description: string,
     *     priority: int,
     *     panel: string,
     *     saveAsOption: bool,
     *     fields: list<array<string, mixed>>
     * }>
     */
    private array $sectionConfig = [];

    /**
     * Fields accepted for registration.
     *
     * @var list<array{
     *     section: string,
     *     field: array{
     *         id: string,
     *         type: string,
     *         label: string,
     *         description: string,
     *         default: mixed,
     *         choices: array<string, string>
     *     }
     * }>
     */
    private array $accepted = [];

    /**
     * Starts a panel that can contain sections.
     *
     * Declaring the same id again returns the existing panel. A different title
     * is ignored and reported.
     *
     * @param string $id    Panel id.
     * @param string $title Panel title.
     *
     * @return Panel Fluent panel.
     */
    public function panel(string $id, string $title): Panel
    {
        $panel = new Panel($id, $title);

        if (!$panel->isValid()) {
            return $panel;
        }

        foreach ($this->panels as $existing) {
            if ($existing->id() !== $panel->id()) {
                continue;
            }

            if ($existing->title() !== $title) {
                Notices::error(sprintf(
                    'Customizer panel "%s" is already registered. The first title was kept.',
                    $panel->id()
                ));
            }

            return $existing;
        }

        $this->panels[] = $panel;

        return $panel;
    }

    /**
     * Starts a section and returns it so fields can be chained.
     *
     * Declaring the same id again returns the existing section, so later fields
     * are added to it. A different title is ignored and reported.
     *
     * @param string $id    Section id.
     * @param string $title Section title.
     *
     * @return Section Fluent section.
     */
    public function section(string $id, string $title): Section
    {
        $section = new Section($id, $title);

        if (!$section->isValid()) {
            return $section;
        }

        foreach ($this->sections as $existing) {
            if ($existing->id() !== $section->id()) {
                continue;
            }

            if ($existing->title() !== $title) {
                Notices::error(sprintf(
                    'Customizer section "%s" is already registered. Additional fields were attached to the existing section.',
                    $section->id()
                ));
            }

            return $existing;
        }

        $this->sections[] = $section;

        return $section;
    }

    /**
     * Chooses where this instance stores its settings.
     *
     * A section that calls {@see Section::saveAsOption()} stores its fields as
     * options even when this instance uses theme mods.
     *
     * @param string $type `theme_mod` or `option`.
     *
     * @return static
     */
    public function storage(string $type): static
    {
        if ($type !== 'theme_mod' && $type !== 'option') {
            Notices::error('Customizer storage must be "theme_mod" or "option".');

            return $this;
        }

        $this->storage = $type;

        return $this;
    }

    /**
     * Queues panels, sections, and fields on `customize_register`.
     *
     * Call this after the sections and fields are declared. The setting map
     * used by `get()` is filled here, including on requests that never open
     * the customizer.
     *
     * @return static|false The customizer instance, or false when nothing was declared.
     *
     * @see https://developer.wordpress.org/reference/hooks/customize_register/
     */
    public function register(): static|false
    {
        if ($this->panels === [] && $this->sections === []) {
            Notices::error('Customizer needs a section or a panel before it can be registered.');

            return false;
        }

        if (!$this->collected) {
            $this->collect();
            $this->collected = true;
        }

        if ($this->queued) {
            return $this;
        }

        $this->queued = true;

        if (!function_exists('add_action')) {
            return $this;
        }

        if (function_exists('did_action') && did_action('customize_register')) {
            $manager = $GLOBALS['wp_customize'] ?? null;

            if ($manager instanceof \WP_Customize_Manager) {
                $this->registerControls($manager);
                $this->hookRepeaterAssets();

                return $this;
            }
        }

        add_action('customize_register', [$this, 'registerControls']);
        $this->hookRepeaterAssets();

        return $this;
    }

    /**
     * Reads a setting saved by this library.
     *
     * The value is not escaped. Pass it through `esc_html()`, `esc_url()`, or
     * `wp_get_attachment_image()` in the template. An image field returns an
     * attachment ID.
     *
     * @param string $id      Setting id.
     * @param mixed  $default Fallback used when the caller passes one. Otherwise the declared default is used.
     *
     * @return mixed Saved value, or the fallback when the setting has not been saved.
     */
    public static function get(string $id, mixed $default = null): mixed
    {
        $key      = function_exists('sanitize_key') ? sanitize_key($id) : strtolower($id);
        $setting  = self::$settings[$key] ?? null;
        $fallback = func_num_args() > 1 ? $default : ($setting['default'] ?? null);

        if ($setting === null) {
            return $fallback;
        }

        if ($setting['storage'] === 'option' && function_exists('get_option')) {
            return get_option($key, $fallback);
        }

        if (function_exists('get_theme_mod')) {
            return get_theme_mod($key, $fallback);
        }

        return $fallback;
    }

    /**
     * Adds the frozen panels, sections, and controls to the customizer.
     *
     * @param \WP_Customize_Manager $manager Customizer manager.
     *
     * @see https://developer.wordpress.org/reference/hooks/customize_register/
     */
    public function registerControls(\WP_Customize_Manager $manager): void
    {
        foreach ($this->panelConfig as $panel) {
            if ($manager->get_panel($panel['id'])) {
                continue;
            }

            $manager->add_panel($panel['id'], [
                'title'       => $panel['title'],
                'description' => $panel['description'],
                'priority'    => $panel['priority'],
                'capability'  => self::CAPABILITY,
            ]);
        }

        foreach ($this->sectionConfig as $section) {
            if ($manager->get_section($section['id'])) {
                continue;
            }

            $args = [
                'title'       => $section['title'],
                'description' => $section['description'],
                'priority'    => $section['priority'],
                'capability'  => self::CAPABILITY,
            ];

            if ($section['panel'] !== '') {
                if ($manager->get_panel($section['panel'])) {
                    $args['panel'] = $section['panel'];
                } else {
                    Notices::error(sprintf(
                        'Customizer panel "%s" does not exist. Section "%s" was added at the top level.',
                        $section['panel'],
                        $section['id']
                    ));
                }
            }

            $manager->add_section($section['id'], $args);
        }

        $priorities = [];

        foreach ($this->accepted as $item) {
            $field = $item['field'];

            if ($manager->get_setting($field['id'])) {
                Notices::error(sprintf('Customizer setting "%s" is already registered.', $field['id']));

                continue;
            }

            $partial = ($field['type'] ?? '') === 'repeater' ? $this->repeaterPartial($field) : null;

            $manager->add_setting($field['id'], [
                'type'              => self::$settings[$field['id']]['storage'] ?? $this->resolvedStorage(),
                'default'           => $field['default'],
                'transport'         => $partial === null ? 'refresh' : 'postMessage',
                'capability'        => self::CAPABILITY,
                'sanitize_callback' => $this->sanitizeCallback($field),
            ]);

            $sectionId = $item['section'];
            $priorities[$sectionId] = ($priorities[$sectionId] ?? 0) + 10;

            if (($field['type'] ?? '') === 'repeater') {
                $this->addRepeaterControl($manager, $sectionId, $field, $priorities[$sectionId]);

                if ($partial !== null && isset($manager->selective_refresh)) {
                    $manager->selective_refresh->add_partial($field['id'], [
                        'selector'            => $partial['selector'],
                        'settings'            => [$field['id']],
                        'render_callback'     => $partial['callback'],
                        'container_inclusive' => true,
                    ]);
                }

                continue;
            }

            $this->addControl($manager, $sectionId, $field, $priorities[$sectionId]);
        }
    }

    /**
     * Copies the current panels, sections, and fields into the request registry.
     */
    private function collect(): void
    {
        $storage = $this->resolvedStorage();

        foreach ($this->panels as $panel) {
            if (!$panel->isValid()) {
                continue;
            }

            $this->panelConfig[] = $panel->export();
        }

        foreach ($this->sections as $section) {
            if (!$section->isValid()) {
                continue;
            }

            $exported = $section->export();
            $this->sectionConfig[] = $exported;
            $fieldStorage = $exported['saveAsOption'] ? 'option' : $storage;

            foreach ($exported['fields'] as $field) {
                if (isset(self::$settings[$field['id']])) {
                    Notices::error(sprintf('Customizer setting "%s" is already registered.', $field['id']));

                    continue;
                }

                self::$settings[$field['id']] = [
                    'storage' => $fieldStorage,
                    'default' => $field['default'],
                ];
                $this->accepted[] = [
                    'section' => $exported['id'],
                    'field'   => $field,
                ];

                if (($field['type'] ?? '') === 'repeater' && is_array($field['schema'] ?? null)) {
                    $this->noteRepeaterAssets($field['schema']);
                }
            }
        }
    }

    /**
     * Resolves `theme_mod` or `option` for this instance.
     *
     * @return string `theme_mod` or `option`.
     */
    private function resolvedStorage(): string
    {
        if ($this->storage !== '') {
            return $this->storage;
        }

        return Builder::isPlugin() ? 'option' : 'theme_mod';
    }

    /**
     * Builds the sanitize callback for one field.
     *
     * @param array{
     *     id: string,
     *     type: string,
     *     label: string,
     *     description: string,
     *     default: mixed,
     *     choices: array<string, string>
     * } $field Field configuration.
     *
     * @return callable(mixed): mixed Sanitize callback.
     */
    private function sanitizeCallback(array $field): callable
    {
        $default = $field['default'];
        $choices = $field['choices'];

        return match ($field['type']) {
            'repeater' => static function (mixed $value) use ($field): array {
                $schema = is_array($field['schema'] ?? null) ? $field['schema'] : [];

                return Repeater::sanitize($schema, $value);
            },
            'textarea' => static function (mixed $value): string {
                $text = is_scalar($value) ? (string) $value : '';

                return function_exists('sanitize_textarea_field') ? sanitize_textarea_field($text) : $text;
            },
            'number' => static function (mixed $value) use ($default): int|float {
                if (!is_numeric($value)) {
                    return $default;
                }

                return 0 + $value;
            },
            'checkbox' => static function (mixed $value): bool {
                if (function_exists('rest_sanitize_boolean')) {
                    return rest_sanitize_boolean($value);
                }

                return (bool) $value;
            },
            'select', 'radio' => static function (mixed $value) use ($choices, $default): string {
                $value = is_scalar($value) ? (string) $value : '';

                return array_key_exists($value, $choices) ? $value : (string) $default;
            },
            'color' => static function (mixed $value): string {
                return self::normalizeColor(is_scalar($value) ? (string) $value : '');
            },
            'image', 'file' => static function (mixed $value): int {
                return function_exists('absint') ? absint($value) : abs((int) $value);
            },
            default => static function (mixed $value): string {
                $text = is_scalar($value) ? (string) $value : '';

                return function_exists('sanitize_text_field') ? sanitize_text_field($text) : $text;
            },
        };
    }

    /**
     * Adds the native control for one field.
     *
     * @param \WP_Customize_Manager $manager   Customizer manager.
     * @param string                $sectionId Section id.
     * @param array{
     *     id: string,
     *     type: string,
     *     label: string,
     *     description: string,
     *     default: mixed,
     *     choices: array<string, string>
     * } $field Field configuration.
     * @param int $priority Control sort order.
     */
    private function addControl(\WP_Customize_Manager $manager, string $sectionId, array $field, int $priority): void
    {
        $args = [
            'label'       => $field['label'],
            'description' => $field['description'],
            'section'     => $sectionId,
            'priority'    => $priority,
        ];

        if ($field['type'] === 'color') {
            $manager->add_control(new \WP_Customize_Color_Control($manager, $field['id'], $args));

            return;
        }

        if ($field['type'] === 'image' || $field['type'] === 'file') {
            if ($field['type'] === 'image') {
                $args['mime_type'] = 'image';
            }

            $manager->add_control(new \WP_Customize_Media_Control($manager, $field['id'], $args));

            return;
        }

        $args['type'] = $field['type'];

        if ($field['type'] === 'select' || $field['type'] === 'radio') {
            $args['choices'] = $field['choices'];
        }

        $manager->add_control($field['id'], $args);
    }

    /**
     * Normalizes a hex color to `#rgb` or `#rrggbb`.
     *
     * @param string $value Raw color.
     *
     * @return string Hex color, or an empty string when the value is not a color.
     */
    private static function normalizeColor(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if ($value[0] !== '#') {
            $value = '#' . $value;
        }

        if (function_exists('sanitize_hex_color')) {
            $color = sanitize_hex_color($value);

            return is_string($color) ? $color : '';
        }

        if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) !== 1) {
            return '';
        }

        return strtolower($value);
    }

    /**
     * Records which repeater assets this schema needs.
     *
     * The repeater script loads when a repeater is declared. The media script
     * loads when an image or a file is declared. The color picker loads when a
     * color is declared. Nested repeaters are included.
     *
     * @param array<string, mixed> $schema Repeater schema.
     */
    private function noteRepeaterAssets(array $schema): void
    {
        $this->repeaterAssets = true;

        foreach ($schema['fields'] ?? [] as $field) {
            if (!is_array($field)) {
                continue;
            }

            $type = (string) ($field['type'] ?? '');

            if ($type === 'image' || $type === 'file') {
                $this->repeaterMedia = true;
            }

            if ($type === 'color') {
                $this->repeaterColor = true;
            }

            if ($type === 'repeater') {
                $this->noteRepeaterAssets($field);
            }
        }
    }

    /**
     * Hooks repeater assets on `customize_controls_enqueue_scripts`.
     *
     * The controls screen rebuilds the script queue after `customize_register`,
     * so assets enqueued earlier never reach the page. This hook is the one
     * that publishes them.
     *
     * @see https://developer.wordpress.org/reference/hooks/customize_controls_enqueue_scripts/
     */
    private function hookRepeaterAssets(): void
    {
        if (!$this->repeaterAssets || !function_exists('add_action')) {
            return;
        }

        if (function_exists('did_action') && did_action('customize_controls_enqueue_scripts')) {
            $this->enqueueRepeaterAssets();

            return;
        }

        add_action('customize_controls_enqueue_scripts', [$this, 'enqueueRepeaterAssets']);
    }

    /**
     * Returns the selective refresh partial for a repeater, when one can be registered.
     *
     * A requested preview without a selector or a callable callback is reported
     * and the setting keeps the full preview refresh.
     *
     * @param array<string, mixed> $field Repeater field.
     *
     * @return array{selector: string, callback: callable|string|array<int|string, mixed>}|null Partial arguments.
     */
    private function repeaterPartial(array $field): ?array
    {
        $preview = is_array($field['preview'] ?? null) ? $field['preview'] : [];

        if (empty($preview['requested'])) {
            return null;
        }

        $selector = trim((string) ($preview['selector'] ?? ''));
        $callback = $preview['callback'] ?? null;
        $ready = is_string($callback) ? function_exists($callback) : is_callable($callback);

        if ($selector === '' || !$ready) {
            Notices::error(sprintf(
                'Customizer setting "%s" preview needs a selector and a render callback.',
                (string) ($field['id'] ?? '')
            ));

            return null;
        }

        return [
            'selector' => $selector,
            'callback' => $callback,
        ];
    }

    /**
     * Adds the repeater control for one setting.
     *
     * @param \WP_Customize_Manager $manager   Customizer manager.
     * @param string                $sectionId Section id.
     * @param array<string, mixed>  $field     Repeater field.
     * @param int                   $priority  Control sort order.
     */
    private function addRepeaterControl(
        \WP_Customize_Manager $manager,
        string $sectionId,
        array $field,
        int $priority
    ): void {
        $manager->add_control(new RepeaterControl($manager, (string) $field['id'], [
            'label'       => (string) ($field['label'] ?? ''),
            'description' => (string) ($field['description'] ?? ''),
            'section'     => $sectionId,
            'priority'    => $priority,
            'settings'    => (string) $field['id'],
            'schema'      => is_array($field['schema'] ?? null) ? $field['schema'] : [],
        ]));
    }

    /**
     * Prints the scripts and styles used by repeater controls.
     *
     * A package installed in `vendor/` is outside the theme and plugin
     * directories, so {@see Builder::url()} has no public address for it.
     * Those files are printed inline and the add button still has a handler.
     *
     * @see https://developer.wordpress.org/reference/hooks/customize_controls_enqueue_scripts/
     */
    public function enqueueRepeaterAssets(): void
    {
        if (!$this->repeaterAssets || !function_exists('wp_enqueue_script')) {
            return;
        }

        $root = dirname(__DIR__, 2);
        $bridge = ['customize-controls', 'wpb-admin-repeater'];

        $this->enqueueAsset(
            'wpb-admin-repeater',
            $root . '/assets/css/admin-repeater.css',
            $root . '/assets/js/admin-repeater.js',
            []
        );

        if ($this->repeaterMedia) {
            if (function_exists('wp_enqueue_media')) {
                wp_enqueue_media();
            }

            $this->enqueueAsset(
                'wpb-admin-image',
                $root . '/assets/css/admin-image.css',
                $root . '/assets/js/admin-image.js',
                ['media-editor']
            );
            $bridge[] = 'wpb-admin-image';
        }

        if ($this->repeaterColor) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            $bridge[] = 'jquery';
            $bridge[] = 'wp-color-picker';
        }

        $this->enqueueAsset(
            'wpb-customize-repeater',
            $root . '/assets/css/customize-repeater.css',
            $root . '/assets/js/customize-repeater.js',
            $bridge
        );
    }

    /**
     * Enqueues one stylesheet and one footer script.
     *
     * A readable file without a public URL is printed inline. That is the
     * case when this library lives in `vendor/`.
     *
     * @param string       $handle Style and script handle.
     * @param string       $style  Absolute CSS path.
     * @param string       $script Absolute JavaScript path.
     * @param list<string> $deps   Script dependencies.
     */
    private function enqueueAsset(string $handle, string $style, string $script, array $deps): void
    {
        if (function_exists('wp_script_is') && wp_script_is($handle, 'enqueued')) {
            return;
        }

        $styleUrl = is_readable($style) ? Builder::url($style) : '';
        $scriptUrl = is_readable($script) ? Builder::url($script) : '';

        if ($styleUrl !== '') {
            wp_enqueue_style($handle, $styleUrl, [], (string) filemtime($style));
        } elseif (is_readable($style)) {
            $this->inlineStyle($handle, $style);
        }

        if (!is_readable($script)) {
            return;
        }

        if ($scriptUrl !== '') {
            wp_enqueue_script(
                $handle,
                $scriptUrl,
                $deps,
                (string) filemtime($script),
                true
            );

            return;
        }

        $this->inlineScript($handle, $script, $deps);
    }

    /**
     * Prints one stylesheet that has no public URL.
     *
     * @param string $handle Style handle.
     * @param string $path   Absolute CSS path.
     */
    private function inlineStyle(string $handle, string $path): void
    {
        if (!function_exists('wp_register_style') || !function_exists('wp_add_inline_style')) {
            return;
        }

        $css = file_get_contents($path);

        if (!is_string($css)) {
            return;
        }

        wp_register_style($handle, false, [], (string) filemtime($path));
        wp_enqueue_style($handle);
        wp_add_inline_style($handle, $css);
    }

    /**
     * Prints one footer script that has no public URL.
     *
     * @param string       $handle Script handle.
     * @param string       $path   Absolute JavaScript path.
     * @param list<string> $deps   Script dependencies.
     */
    private function inlineScript(string $handle, string $path, array $deps): void
    {
        if (!function_exists('wp_register_script') || !function_exists('wp_add_inline_script')) {
            return;
        }

        $source = file_get_contents($path);

        if (!is_string($source)) {
            return;
        }

        wp_register_script(
            $handle,
            false,
            $deps,
            (string) filemtime($path),
            true
        );
        wp_add_inline_script($handle, $source);
        wp_enqueue_script($handle);
    }
}
