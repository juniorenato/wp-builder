<?php
/**
 * Hidden input field.
 *
 * @package WPB\Forms
 */
?>
<input type="hidden"
    name="<?php echo esc_attr($this->field['name']); ?>"
    id="field_<?php echo esc_attr($this->field['name']); ?>"
    value="<?php echo esc_attr($this->field['val'] ?? ''); ?>"<?php echo $this->field['attributes'] ?? ''; ?>>
