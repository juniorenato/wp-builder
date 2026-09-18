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
     *     PAGE: string
     * }
     */
    public const array PATH = [
        'FORM' => __DIR__ . '/../views/form/',
        'LANG' => __DIR__ . '/../lang',
        'PAGE' => __DIR__ . '/../views/',
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
     * Runs a callback on `init`, or immediately when `init` has already started.
     *
     * WordPress 6.7+ forbids loading translations before `after_setup_theme`.
     * This keeps constructors safe to call from a plugin or theme bootstrap file.
     *
     * @since 0.1.0
     *
     * @param callable $callback Callback to run.
     * @param int      $priority Hook priority.
     *
     * @see https://developer.wordpress.org/reference/functions/add_action/
     * @see https://developer.wordpress.org/reference/functions/did_action/
     * @see https://developer.wordpress.org/reference/hooks/init/
     */
    public static function onInit(callable $callback, int $priority = 10): void
    {
        if (did_action('init')) {
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
     * Bootstraps library services hooked to `init`.
     *
     * @since 0.1.0
     *
     * @return static
     */
    public function build(): static
    {
        $this->i18n();

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
