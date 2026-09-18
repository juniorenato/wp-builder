<?php
/**
 * Textarea field.
 *
 * @package WPB\Forms
 */
?>
<textarea
    name="<?php echo esc_attr($this->field['name']); ?>"
    id="field_<?php echo esc_attr($this->field['name']); ?>"
    <?php echo $this->field['attributes'] ?? ''; ?>><?php echo esc_textarea($this->field['val'] ?? ''); ?></textarea>
