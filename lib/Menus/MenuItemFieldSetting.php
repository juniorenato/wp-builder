<?php

/**
 * Customizer setting for one menu item field.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Menus
 */

namespace WPB\Menus;

/**
 * Stores a menu item field outside the nav menu item setting.
 *
 * WordPress drops unknown keys from that setting before it is saved. This
 * setting keeps the value and writes post meta after the menu item exists.
 *
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class MenuItemFieldSetting extends \WP_Customize_Setting
{
    /**
     * Setting type stored by this class instead of an option or theme mod.
     *
     * @var string
     */
    public $type = 'wpb_menu_item';

    /**
     * Shows the unsaved value in the Customizer preview.
     *
     * @return bool Whether the setting is previewed.
     */
    public function preview()
    {
        if ($this->is_previewed) {
            return true;
        }

        $this->is_previewed = true;
        Menu::previewCustomizerFields();

        return true;
    }

    /**
     * Queues the value until menu items have been saved.
     *
     * @param mixed $value Sanitized field value.
     */
    protected function update($value)
    {
        Menu::queueCustomizerValue($this->id, $value);
    }
}
