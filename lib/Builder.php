<?php

namespace WPB;

/**
 * -----------------------------------------------------------------------------
 * Default plugin settings
 * -----------------------------------------------------------------------------
 *
 * @param string $env `theme | plugin`
 *
 * @since v0.1.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 * @package juniorenato/wp-builder
 */
class Builder
{
    public const THEME = 'theme';
    public const PLUGIN = 'plugin';
    public const PATH = [
        'FORM' => __DIR__ .'/../views/form/',
        'LANG' => __DIR__ .'/../lang',
        'PAGE' => __DIR__ .'/../views/',
    ];

    public function __construct(string $env)
    {
        global $wp_builder;

        if(strtolower($env) === 'theme' || strtolower($env) === 'plugin') {
            $wp_builder = strtolower($env);
        }

        add_action('init', [$this, 'build']);
    }

    /**
     * -------------------------------------------------------------------------
     * Check if it's a plugin
     * -------------------------------------------------------------------------
     *
     * @return boolean
     */
    public static function isPlugin(): bool
    {
        global $wp_builder;

        return (self::PLUGIN === $wp_builder);
    }

    /**
     * -------------------------------------------------------------------------
     * Check if it's a theme
     * -------------------------------------------------------------------------
     *
     * @return boolean
     */
    public static function isTheme(): bool
    {
        global $wp_builder;

        return (self::THEME === $wp_builder);
    }

    public function build(): Builder
    {
        $this->i18n();

        return $this;
    }

    private function i18n()
    {
        $arr_lang = [
            'en_US',
            get_locale(),
        ];

        if(!static::isPlugin()) {
            $path = WP_CONTENT_DIR .'/languages/themes';
            if(!is_dir($path)) mkdir($path);

            foreach($arr_lang as $lang) {
                if(file_exists(self::PATH['LANG'] .'/'. $lang .'.mo') && !file_exists($path .'/wpb-'. $lang .'.mo')) {
                    copy(self::PATH['LANG'] .'/'. $lang .'.mo', $path .'/wpb-'. $lang .'.mo');
                }
            }

            load_theme_textdomain('wpb', self::PATH['LANG']);
        }

        if(!static::isTheme()) {
            $path = WP_CONTENT_DIR .'/languages/plugins';
            if(!is_dir($path)) mkdir($path);

            foreach($arr_lang as $lang) {
                if(file_exists(self::PATH['LANG'] .'/'. $lang .'.mo') && !file_exists($path .'/wpb-'. $lang .'.mo')) {
                    copy(self::PATH['LANG'] .'/'. $lang .'.mo', $path .'/wpb-'. $lang .'.mo');
                }
            }

            load_plugin_textdomain('wpb', false, self::PATH['LANG'] .'/lang');
        }
    }
}
