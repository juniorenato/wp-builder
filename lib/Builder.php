<?php

namespace WPB;

/**
 * -----------------------------------------------------------------------------
 * Default plugin settings
 * -----------------------------------------------------------------------------
 *
 * @since 0.1.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 * @package hoststyle/hswp-theme-builder
 */
class Builder
{
    protected const WPB_PATH = __DIR__ .'/../';
    protected const WPB_TAXONOMY_FORM_FIELDS = __DIR__ .'/../views/taxonomy/custom-fields/';

    public function __construct()
    {
        add_action('init', [$this, 'initialization']);
    }

    public function initialization()
    {
        load_plugin_textdomain('wpb', false, self::WPB_PATH .'/lang/');
        load_theme_textdomain('wpb', false, self::WPB_PATH .'/lang/');
    }
}
