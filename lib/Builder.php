<?php

namespace WPB;

/**
 * -----------------------------------------------------------------------------
 * Default plugin settings
 * -----------------------------------------------------------------------------
 *
 * @since 0.1.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 * @package juniorenato/wp-builder
 */
class Builder
{
    protected const WPB_LANG_PATH = __DIR__ .'/../lang';

    public function __construct()
    {
        add_action('init', [$this, 'i18n']);
    }

    public function i18n()
    {
        $arr_lang = [
            'en_US',
            get_locale(),
        ];

        $theme_path =  WP_CONTENT_DIR .'/languages/themes';
        if(!is_dir($theme_path)) mkdir($theme_path);

        $plugin_path =  WP_CONTENT_DIR .'/languages/plugins';
        if(!is_dir($plugin_path)) mkdir($plugin_path);

        foreach($arr_lang as $lang) {
            if(file_exists(self::WPB_LANG_PATH .'/'. $lang .'.mo') && !file_exists($theme_path .'/wpb-'. $lang .'.mo')) {
                copy(self::WPB_LANG_PATH .'/'. $lang .'.mo', $theme_path .'/wpb-'. $lang .'.mo');
            }

            if(file_exists(self::WPB_LANG_PATH .'/'. $lang .'.mo') && !file_exists($plugin_path .'/wpb-'. $lang .'.mo')) {
                copy(self::WPB_LANG_PATH .'/'. $lang .'.mo', $plugin_path .'/wpb-'. $lang .'.mo');
            }
        }

        load_theme_textdomain('wpb', self::WPB_LANG_PATH);
        load_plugin_textdomain('wpb', false, self::WPB_LANG_PATH .'/lang');
    }
}
