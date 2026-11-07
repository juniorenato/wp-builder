<?php
/**
 * Image field. The button opens the WordPress media modal.
 *
 * @package WPB\Forms
 */

$this->enqueueAdminImage();

$attachmentId = absint($this->field['val'] ?? 0);
$imageUrl = '';
$imageAlt = '';

if ($attachmentId > 0 && function_exists('wp_get_attachment_image_url')) {
    $resolvedUrl = wp_get_attachment_image_url($attachmentId, 'medium');
    $imageUrl = is_string($resolvedUrl) ? $resolvedUrl : '';
}

if ($attachmentId > 0 && function_exists('get_post_meta')) {
    $imageAlt = (string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
}

$selectLabel = __('Select image', 'wpb');
$replaceLabel = __('Replace image', 'wpb');
$buttonLabel = $imageUrl !== '' ? $replaceLabel : $selectLabel;
?>
<div
    <?php echo $this->field['attributes'] ?? ''; ?>
    data-select="<?php echo esc_attr($selectLabel); ?>"
    data-replace="<?php echo esc_attr($replaceLabel); ?>">
    <div class="wpb-image-field__preview">
        <?php if ($imageUrl !== '') : ?>
            <img src="<?php echo esc_url($imageUrl); ?>" alt="<?php echo esc_attr($imageAlt); ?>">
        <?php endif; ?>
    </div>
    <input type="hidden"
        class="wpb-image-field__id"
        name="<?php echo esc_attr($this->field['name']); ?>"
        value="<?php echo esc_attr($attachmentId > 0 ? (string) $attachmentId : ''); ?>">
    <p class="wpb-image-field__actions">
        <button type="button"
            class="button wpb-image-field__select"
            id="field_<?php echo esc_attr($this->field['name']); ?>"
            <?php echo !empty($this->field['description']) ? 'aria-describedby="' . esc_attr($this->field['name'] . '-description') . '"' : ''; ?>>
            <?php echo esc_html($buttonLabel); ?>
        </button>
        <button type="button" class="button-link wpb-image-field__remove"<?php echo $imageUrl === '' ? ' hidden' : ''; ?>>
            <?php esc_html_e('Remove image', 'wpb'); ?>
        </button>
    </p>
</div>
