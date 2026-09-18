<?php
/**
 * Field description.
 *
 * @package WPB\Forms
 */
?>
<?php if (!empty($this->field['description'])) : ?>
    <p class="description" id="<?php echo esc_attr($this->field['name']); ?>-description"><?php echo esc_html($this->field['description']); ?></p>
<?php endif; ?>
