<?php

/**
 * Bootstraps the WP Builder library.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB
 */

namespace WPB;

/**
 * Stores default library settings and loads translations.
 *
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class Builder
{
    /**
     * Theme environment identifier.
     */
    public const string THEME = 'theme';

    /**
     * Plugin environment identifier.
     */
    public const string PLUGIN = 'plugin';

    /**
     * Absolute paths used by the library.
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
     * Theme-relative directory for block CSS and JavaScript.
     */
    private static string $blockAssets = 'assets/public/blocks';

    /**
     * Theme-relative directory for public block PHP templates.
     */
    private static string $blockTemplates = 'template-parts/blocks';

    /**
     * Registers the library bootstrap for the given environment.
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

        self::removeCopiedTranslationFiles();
        self::onInit([$this, 'build'], 0);
    }

    /**
     * Runs a callback on `init`.
     *
     * The callback is not executed after `init` has finished. A call made while
     * `init` is running executes immediately, because that call is still inside
     * the hook. Constructors stay safe to call from a plugin or theme file.
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
     * WordPress warns when a text domain is translated before `after_setup_theme`.
     * The user locale is ready on `init`, which is also when post types are registered.
     *
     * @return bool True once `init` has started.
     *
     * @see https://developer.wordpress.org/reference/functions/did_action/
     * @see https://developer.wordpress.org/reference/hooks/init/
     * @see https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/
     */
    public static function canLoadTranslations(): bool
    {
        if (!function_exists('did_action')) {
            return false;
        }

        return did_action('init') > 0 || (function_exists('doing_action') && doing_action('init'));
    }

    /**
     * Determines whether the library is running as a plugin.
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
     * Sets or reads the theme directory that holds block CSS and JavaScript.
     *
     * Pass a path to change it. Call with no argument to read the current one.
     * `assets/public/blocks` is the default. `my-theme/cta` then uses
     * `assets/public/blocks/my-theme/cta.css` and `.js` when {@see \WPB\Blocks\Block::assets()}
     * is enabled. The child theme is checked first, then the parent.
     *
     * An empty path, or a path that leaves the theme with `..`, is ignored and
     * reported in the WordPress admin.
     *
     * @param string|null $directory Theme-relative directory, without a leading slash.
     *
     * @return string Directory in effect.
     */
    public static function blockAssets(?string $directory = null): string
    {
        if ($directory === null) {
            return self::$blockAssets;
        }

        $normalized = self::themeDirectory($directory, 'Block asset');

        if ($normalized !== null) {
            self::$blockAssets = $normalized;
        }

        return self::$blockAssets;
    }

    /**
     * Sets or reads the theme directory that holds public block PHP templates.
     *
     * Pass a path to change it. Call with no argument to read the current one.
     * `template-parts/blocks` is the default. `my-theme/team` then uses
     * `template-parts/blocks/my-theme/team.php` when the block has no `template()`
     * slug. Core block overrides use the same directory. The child theme is
     * checked first, then the parent.
     *
     * An empty path, or a path that leaves the theme with `..`, is ignored and
     * reported in the WordPress admin.
     *
     * @param string|null $directory Theme-relative directory, without a leading slash.
     *
     * @return string Directory in effect.
     *
     * @see https://developer.wordpress.org/reference/functions/locate_template/
     */
    public static function blockTemplates(?string $directory = null): string
    {
        if ($directory === null) {
            return self::$blockTemplates;
        }

        $normalized = self::themeDirectory($directory, 'Block template');

        if ($normalized !== null) {
            self::$blockTemplates = $normalized;
        }

        return self::$blockTemplates;
    }

    /**
     * Normalizes a theme-relative directory.
     *
     * @param string $directory Raw directory.
     * @param string $label     Name used in the admin notice.
     *
     * @return string|null Normalized directory, or null when the value is rejected.
     */
    private static function themeDirectory(string $directory, string $label): ?string
    {
        $directory = trim(str_replace('\\', '/', $directory), " \t\n\r\0\x0B/");

        if ($directory === '') {
            Notices::error($label . ' directory cannot be empty.');

            return null;
        }

        if (
            str_contains($directory, "\0")
            || str_contains($directory, '..')
            || preg_match('/^[A-Za-z]:/', $directory) === 1
        ) {
            Notices::error(sprintf(
                '%s directory must stay inside the theme and cannot contain "..".',
                $label
            ));

            return null;
        }

        return $directory;
    }

    /**
     * Converts an absolute path inside this install to a URL.
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
     * Loads translations on `init`.
     *
     * @return static
     */
    public function build(): static
    {
        $this->i18n();

        return $this;
    }

    /**
     * Loads the packaged text domain on `init`.
     *
     * The file stays in `lang/`. Copying it into `WP_LANG_DIR` makes WordPress
     * discover the `wpb` domain before `init` and emit the 6.7 notice.
     *
     * @see https://developer.wordpress.org/reference/functions/load_textdomain/
     * @see https://developer.wordpress.org/reference/functions/determine_locale/
     * @see https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/
     */
    private function i18n(): void
    {
        self::loadTranslations();
    }

    /**
     * Loads `lang/wpb-{locale}.mo` for the current locale.
     *
     * Safe to call more than once. A missing file leaves the source strings in place.
     */
    public static function loadTranslations(): void
    {
        static $loaded = false;

        if ($loaded || !function_exists('load_textdomain') || !function_exists('determine_locale')) {
            return;
        }

        $loaded = true;
        $locale = determine_locale();
        $mofile = self::PATH['LANG'] . '/wpb-' . $locale . '.mo';

        if (is_readable($mofile)) {
            load_textdomain('wpb', $mofile, $locale);
        }
    }

    /**
     * Deletes translations this library used to copy into `WP_LANG_DIR`.
     *
     * Those copies are found by just-in-time loading before `init`.
     */
    private static function removeCopiedTranslationFiles(): void
    {
        if (!defined('WP_LANG_DIR')) {
            return;
        }

        foreach (['themes', 'plugins'] as $type) {
            $directory = WP_LANG_DIR . '/' . $type;

            foreach (['mo', 'l10n.php'] as $extension) {
                foreach (glob($directory . '/wpb-*.' . $extension) ?: [] as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
        }
    }
}
