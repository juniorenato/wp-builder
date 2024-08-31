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
    public string $td = 'default';

    public $prefix = '';

    public string $lang = 'en_US';

    public bool $male = true;

    /**
     * -------------------------------------------------------------------------
     * Configure the website's "text domain"
     * -------------------------------------------------------------------------
     *
     * @param string $text_domain
     * @return mixed
     */
    public function setTextDomain(string $text_domain): Builder
    {
        $this->td = $text_domain;

        return $this;
    }

    /**
     * -------------------------------------------------------------------------
     * Add prefix
     * -------------------------------------------------------------------------
     *
     * @param string|bool $prefix
     * @return mixed
     */
    public function setPrefix($prefix): Builder
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * -------------------------------------------------------------------------
     * Set language
     * -------------------------------------------------------------------------
     *
     * @param string $lang
     * @param bool $male
     * @return void
     */
    public function setLang(string $lang, bool $male = true): void
    {
        if(strpos($lang, '-')) {
            $lang = str_replace('-', '_', $lang);
        }

        if( 1 == 0
            || !preg_match("#[a-z]+#", $lang)
            || !preg_match("#[A-Z]+#", $lang)
        ) {
            $lang = explode('_', $lang);
            $lang[0] = strtolower($lang[0]);
            if(isset($lang[1])) $lang[1] = strtoupper($lang[1]);

            $lang = implode('_', $lang);
        }

        $this->lang = $lang;
        $this->male = $male;
    }

}
