<?php
/**
 * Textarea field.
 *
 * @package WPB\Forms
 */
?>
<textarea
    rows="4"
    name="<?php echo esc_attr($this->field['name']); ?>"
    id="field_<?php echo esc_attr($this->field['name']); ?>"
    <?php echo $this->field['attributes'] ?? ''; ?>><?php echo esc_textarea($this->field['val'] ?? ''); ?></textarea>
