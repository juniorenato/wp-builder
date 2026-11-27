<?php
/**
 * HTML field. The control is a textarea; saved markup is filtered with wp_kses_post().
 *
 * @package WPB\Forms
 */
?>
<textarea
    rows="6"
    name="<?php echo esc_attr($this->field['name']); ?>"
    id="field_<?php echo esc_attr($this->field['name']); ?>"
    <?php echo $this->field['attributes'] ?? ''; ?>><?php echo esc_textarea($this->field['val'] ?? ''); ?></textarea>
