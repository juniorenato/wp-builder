<?php
/**
 * Color field. The saved value is a hex color.
 *
 * @package WPB\Forms
 */

$this->enqueueAdminColor();
?>
<input type="text"
    name="<?php echo esc_attr($this->field['name']); ?>"
    id="field_<?php echo esc_attr($this->field['name']); ?>"
    value="<?php echo esc_attr($this->field['val'] ?? ''); ?>"
    data-default-color="<?php echo esc_attr($this->field['val'] ?? ''); ?>"
    <?php echo $this->field['attributes'] ?? ''; ?>>
