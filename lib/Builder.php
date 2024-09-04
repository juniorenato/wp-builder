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

    private $env = null;

    public function __construct(?string $env = null)
    {
        if($env) $this->env = $env;

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
                if(file_exists(self::WPB_LANG_PATH .'/'. $lang .'.mo') && !file_exists($path .'/wpb-'. $lang .'.mo')) {
                    copy(self::WPB_LANG_PATH .'/'. $lang .'.mo', $path .'/wpb-'. $lang .'.mo');
                }
            }

            load_theme_textdomain('wpb', self::WPB_LANG_PATH);
        }

        if(!$this->env || $this->env == 'plugin') {
            $path =  WP_CONTENT_DIR .'/languages/plugins';
            if(!is_dir($path)) mkdir($path);

            foreach($arr_lang as $lang) {
                if(file_exists(self::WPB_LANG_PATH .'/'. $lang .'.mo') && !file_exists($path .'/wpb-'. $lang .'.mo')) {
                    copy(self::WPB_LANG_PATH .'/'. $lang .'.mo', $path .'/wpb-'. $lang .'.mo');
                }
            }

            load_plugin_textdomain('wpb', false, self::WPB_LANG_PATH .'/lang');
        }
    }
}
