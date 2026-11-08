<?php
/**
 * Field description.
 *
 * @package WPB\Forms
 */
?>
<?php if (!empty($this->field['description'])) : ?>
    <?php if ($this->fieldsType === 'menu') : ?>
        <span class="description" id="<?php echo esc_attr($this->field['name']); ?>-description"><?php echo esc_html($this->field['description']); ?></span>
    <?php else : ?>
        <p class="description" id="<?php echo esc_attr($this->field['name']); ?>-description"><?php echo esc_html($this->field['description']); ?></p>
    <?php endif; ?>
<?php endif; ?>
