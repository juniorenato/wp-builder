<?php

namespace WPB;

/**
 * -----------------------------------------------------------------------------
 * Default plugin settings
 * -----------------------------------------------------------------------------
 *
 * @since v0.1.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 * @package juniorenato/wp-builder
 */
class Builder
{
    private $env = null;

    public function __construct(?string $env = null)
    {
        if($env) $this->env = $env;

        define('WPB_FIELD_PATH', __DIR__ .'/../views/form/');
        define('WPB_LANG_PATH', __DIR__ .'/../lang');
        define('WPB_PAGE_PATH', __DIR__ .'/../views/');

        add_action('init', [$this, 'build']);
    }

    public function build()
    {
        $this->i18n();
    }

    private function i18n()
    {
        $arr_lang = [
            'en_US',
            get_locale(),
        ];

        if(!$this->env || $this->env == 'theme') {
            $path = WP_CONTENT_DIR .'/languages/themes';
            if(!is_dir($path)) mkdir($path);

            foreach($arr_lang as $lang) {
                if(file_exists(WPB_LANG_PATH .'/'. $lang .'.mo') && !file_exists($path .'/wpb-'. $lang .'.mo')) {
                    copy(WPB_LANG_PATH .'/'. $lang .'.mo', $path .'/wpb-'. $lang .'.mo');
                }
            }

            load_theme_textdomain('wpb', WPB_LANG_PATH);
        }

        if(!$this->env || $this->env == 'plugin') {
            $path =  WP_CONTENT_DIR .'/languages/plugins';
            if(!is_dir($path)) mkdir($path);

            foreach($arr_lang as $lang) {
                if(file_exists(WPB_LANG_PATH .'/'. $lang .'.mo') && !file_exists($path .'/wpb-'. $lang .'.mo')) {
                    copy(WPB_LANG_PATH .'/'. $lang .'.mo', $path .'/wpb-'. $lang .'.mo');
                }
            }

            load_plugin_textdomain('wpb', false, WPB_LANG_PATH .'/lang');
        }
    }
}
