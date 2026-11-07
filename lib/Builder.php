<?php

/**
 * Bootstraps the WP Builder library.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB
 */

namespace WPB;

use WPB\Blocks\Block;

/**
 * Stores default library settings and loads translations.
 *
 * @since  0.1.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class Builder
{
    /**
     * Theme environment identifier.
     *
     * @since 0.1.0
     */
    public const string THEME = 'theme';

    /**
     * Plugin environment identifier.
     *
     * @since 0.1.0
     */
    public const string PLUGIN = 'plugin';

    /**
     * Absolute paths used by the library.
     *
     * @since 0.1.0
     *
     * @var array{
     *     FORM: string,
     *     LANG: string,
     *     PAGE: string,
     *     BLOCK: string
     * }
     */
    public const array PATH = [
        'FORM'  => __DIR__ . '/../views/form/',
        'LANG'  => __DIR__ . '/../lang',
        'PAGE'  => __DIR__ . '/../views/',
        'BLOCK' => __DIR__ . '/../views/blocks/',
    ];

    /**
     * Registers the library bootstrap for the given environment.
     *
     * @since 0.1.0
     *
     * @param string $env `theme` or `plugin`.
     *
     * @global string $wp_builder Current library environment.
     */
    public function __construct(string $env)
    {
        global $wp_builder;

        $env = strtolower($env);

        if ($env === self::THEME || $env === self::PLUGIN) {
            $wp_builder = $env;
        }

        self::onInit([$this, 'build'], 0);
    }

    /**
     * Runs a callback on `init`.
     *
     * The callback is not executed after `init` has finished. A call made while
     * `init` is running executes immediately, because that call is still inside
     * the hook. Constructors stay safe to call from a plugin or theme file.
     *
     * @since 0.1.0
     *
     * @param callable $callback Callback to run.
     * @param int      $priority Hook priority.
     *
     * @see https://developer.wordpress.org/reference/functions/add_action/
     * @see https://developer.wordpress.org/reference/functions/doing_action/
     * @see https://developer.wordpress.org/reference/hooks/init/
     */
    public static function onInit(callable $callback, int $priority = 10): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        if (function_exists('doing_action') && doing_action('init')) {
            $callback();

            return;
        }

        add_action('init', $callback, $priority);
    }

    /**
     * Determines whether translation functions may run without a 6.7 notice.
     *
     * @since 0.1.0
     *
     * @return bool True after `after_setup_theme` has started.
     *
     * @see https://developer.wordpress.org/reference/functions/did_action/
     * @see https://developer.wordpress.org/reference/hooks/after_setup_theme/
     * @see https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/
     */
    public static function canLoadTranslations(): bool
    {
        return (bool) did_action('after_setup_theme');
    }

    /**
     * Determines whether the library is running as a plugin.
     *
     * @since 0.1.0
     *
     * @global string $wp_builder Current library environment.
     *
     * @return bool True when the environment is a plugin.
     */
    public static function isPlugin(): bool
    {
        global $wp_builder;

        return self::PLUGIN === $wp_builder;
    }

    /**
     * Determines whether the library is running as a theme.
     *
     * @since 0.1.0
     *
     * @global string $wp_builder Current library environment.
     *
     * @return bool True when the environment is a theme.
     */
    public static function isTheme(): bool
    {
        global $wp_builder;

        return self::THEME === $wp_builder;
    }

    /**
     * Converts an absolute path inside this install to a URL.
     *
     * @since 0.8.0
     *
     * @param string $path Absolute filesystem path.
     *
     * @return string Public URL, or an empty string when the path is outside the install.
     *
     * @see https://developer.wordpress.org/reference/functions/content_url/
     * @see https://developer.wordpress.org/reference/functions/site_url/
     */
    public static function url(string $path): string
    {
        $path = wp_normalize_path($path);
        $resolved = realpath($path);
        $file = wp_normalize_path($resolved !== false ? $resolved : $path);

        foreach (self::urlBases() as $base) {
            $baseResolved = realpath($base['dir']);
            $dir = wp_normalize_path($baseResolved !== false ? $baseResolved : $base['dir']);

            if ($dir !== '' && str_starts_with($file, $dir)) {
                $relative = ltrim(substr($file, strlen($dir)), '/');

                return rtrim($base['url'], '/') . '/' . $relative;
            }
        }

        $contentDir = wp_normalize_path((string) (realpath(WP_CONTENT_DIR) ?: WP_CONTENT_DIR));

        if (str_starts_with($file, $contentDir)) {
            return content_url(substr($file, strlen($contentDir)));
        }

        $abspath = wp_normalize_path((string) (realpath(ABSPATH) ?: ABSPATH));

        if (str_starts_with($file, $abspath)) {
            return site_url(substr($file, strlen($abspath)));
        }

        return '';
    }

    /**
     * Returns theme and plugin directories that can host this library.
     *
     * Symlinked themes and plugins keep their public URL while PHP sees the
     * real path. Comparing those real paths is what makes the asset URL work.
     *
     * @since 0.8.0
     *
     * @return list<array{dir: string, url: string}> Directory and its public URL.
     *
     * @see https://developer.wordpress.org/reference/functions/get_stylesheet_directory/
     * @see https://developer.wordpress.org/reference/functions/plugins_url/
     */
    private static function urlBases(): array
    {
        $bases = [];

        if (function_exists('get_stylesheet_directory')) {
            $bases[] = [
                'dir' => get_stylesheet_directory(),
                'url' => get_stylesheet_directory_uri(),
            ];
        }

        if (
            function_exists('get_template_directory')
            && function_exists('get_stylesheet_directory')
            && get_template_directory() !== get_stylesheet_directory()
        ) {
            $bases[] = [
                'dir' => get_template_directory(),
                'url' => get_template_directory_uri(),
            ];
        }

        if (defined('WP_PLUGIN_DIR') && function_exists('plugins_url')) {
            foreach (glob(WP_PLUGIN_DIR . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                $bases[] = [
                    'dir' => $dir,
                    'url' => plugins_url('', $dir . '/plugin.php'),
                ];
            }
        }

        if (defined('WPMU_PLUGIN_DIR') && function_exists('plugins_url')) {
            foreach (glob(WPMU_PLUGIN_DIR . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                $bases[] = [
                    'dir' => $dir,
                    'url' => plugins_url('', $dir . '/plugin.php'),
                ];
            }
        }

        return $bases;
    }

    /**
     * Bootstraps library services hooked to `init`.
     *
     * @since 0.1.0
     *
     * @return static
     */
    public function build(): static
    {
        $this->i18n();
        Block::hookCoreTemplates();

        return $this;
    }

    /**
     * Loads the library text domain for the current environment.
     *
     * @since 0.1.0
     *
     * @see https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/
     * @see https://developer.wordpress.org/reference/functions/load_theme_textdomain/
     * @see https://developer.wordpress.org/reference/functions/load_plugin_textdomain/
     * @see https://developer.wordpress.org/reference/functions/determine_locale/
     */
    private function i18n(): void
    {
        $locales = array_unique([
            'en_US',
            determine_locale(),
        ]);

        if (!static::isPlugin()) {
            $this->installTranslationFiles('themes', $locales);
            load_theme_textdomain('wpb', self::PATH['LANG']);
        }

        if (!static::isTheme()) {
            $this->installTranslationFiles('plugins', $locales);
            load_plugin_textdomain('wpb', false, plugin_basename(self::PATH['LANG']));
        }
    }

    /**
     * Copies packaged translations into the WordPress language directory.
     *
     * JIT loading looks for `{domain}-{locale}.mo` and `{domain}-{locale}.l10n.php`
     * inside `WP_LANG_DIR/plugins` or `WP_LANG_DIR/themes`.
     *
     * @since 0.1.0
     *
     * @param 'plugins'|'themes' $type    Language subdirectory for the environment.
     * @param list<string>       $locales Locales to install.
     *
     * @see https://developer.wordpress.org/reference/functions/wp_mkdir_p/
     * @see https://developer.wordpress.org/reference/functions/load_plugin_textdomain/
     */
    private function installTranslationFiles(string $type, array $locales): void
    {
        $destinationDir = WP_LANG_DIR . '/' . $type;

        wp_mkdir_p($destinationDir);

        foreach ($locales as $locale) {
            foreach (['mo', 'l10n.php'] as $extension) {
                $destination = $destinationDir . '/wpb-' . $locale . '.' . $extension;
                $sources = [
                    self::PATH['LANG'] . '/wpb-' . $locale . '.' . $extension,
                    self::PATH['LANG'] . '/' . $locale . '.' . $extension,
                ];

                foreach ($sources as $source) {
                    if (!is_readable($source)) {
                        continue;
                    }

                    if (!file_exists($destination) || filemtime($source) > filemtime($destination)) {
                        copy($source, $destination);
                    }

                    break;
                }
            }
        }
    }
}
