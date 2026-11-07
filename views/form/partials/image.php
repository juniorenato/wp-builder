<?php
/**
 * Image field. The button opens the WordPress media modal.
 *
 * One image posts an attachment ID. Several images post a list of IDs.
 *
 * @package WPB\Forms
 */

$this->enqueueAdminImage();

$multiple = !empty($this->field['multiple']);
$rawValue = $this->field['val'] ?? ($multiple ? [] : 0);
$attachmentIds = [];

if ($multiple) {
    $rawItems = is_array($rawValue) ? $rawValue : (($rawValue === '' || $rawValue === null || $rawValue === false) ? [] : [$rawValue]);

    foreach ($rawItems as $rawItem) {
        $attachmentId = absint(is_array($rawItem) ? 0 : $rawItem);

        if ($attachmentId > 0) {
            $attachmentIds[] = $attachmentId;
        }
    }
} else {
    $attachmentId = absint(is_array($rawValue) ? 0 : $rawValue);

    if ($attachmentId > 0) {
        $attachmentIds[] = $attachmentId;
    }
}

$images = [];

foreach ($attachmentIds as $attachmentId) {
    $imageUrl = '';
    $imageAlt = '';

    if (function_exists('wp_get_attachment_image_url')) {
        $resolvedUrl = wp_get_attachment_image_url($attachmentId, 'medium');
        $imageUrl = is_string($resolvedUrl) ? $resolvedUrl : '';
    }

    if (function_exists('get_post_meta')) {
        $imageAlt = (string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
    }

    $images[] = [
        'id'  => $attachmentId,
        'url' => $imageUrl,
        'alt' => $imageAlt,
    ];
}

$selectLabel = __($multiple ? 'Select images' : 'Select image', 'wpb');
$replaceLabel = __('Replace image', 'wpb');
$addLabel = __('Add images', 'wpb');
$removeLabel = __('Remove image', 'wpb');
$hasImage = $images !== [] && ($images[0]['url'] ?? '') !== '';
$requestName = (string) $this->field['name'] . ($multiple ? '[]' : '');
$buttonLabel = $multiple
    ? ($images === [] ? $selectLabel : $addLabel)
    : ($hasImage ? $replaceLabel : $selectLabel);
?>
<div
    <?php echo $this->field['attributes'] ?? ''; ?>
    data-multiple="<?php echo $multiple ? '1' : '0'; ?>"
    data-name="<?php echo esc_attr($requestName); ?>"
    data-select="<?php echo esc_attr($selectLabel); ?>"
    data-replace="<?php echo esc_attr($replaceLabel); ?>"
    data-add="<?php echo esc_attr($addLabel); ?>"
    data-remove="<?php echo esc_attr($removeLabel); ?>">
    <div class="wpb-image-field__preview">
        <?php if ($multiple) : ?>
            <?php foreach ($images as $image) : ?>
                <figure class="wpb-image-field__item">
                    <?php if ($image['url'] !== '') : ?>
                        <img src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt']); ?>">
                    <?php endif; ?>
                    <input type="hidden"
                        class="wpb-image-field__id"
                        name="<?php echo esc_attr($requestName); ?>"
                        value="<?php echo esc_attr((string) $image['id']); ?>">
                    <button type="button" class="button-link wpb-image-field__remove" aria-label="<?php echo esc_attr($removeLabel); ?>">
                        <?php echo esc_html($removeLabel); ?>
                    </button>
                </figure>
            <?php endforeach; ?>
        <?php elseif (($images[0]['url'] ?? '') !== '') : ?>
            <img src="<?php echo esc_url($images[0]['url']); ?>" alt="<?php echo esc_attr($images[0]['alt']); ?>">
        <?php endif; ?>
    </div>
    <?php if (!$multiple) : ?>
        <input type="hidden"
            class="wpb-image-field__id"
            name="<?php echo esc_attr((string) $this->field['name']); ?>"
            value="<?php echo esc_attr(isset($images[0]) ? (string) $images[0]['id'] : ''); ?>">
    <?php endif; ?>
    <p class="wpb-image-field__actions">
        <button type="button"
            class="button wpb-image-field__select"
            id="field_<?php echo esc_attr((string) $this->field['name']); ?>"
            <?php echo !empty($this->field['description']) ? 'aria-describedby="' . esc_attr($this->field['name'] . '-description') . '"' : ''; ?>>
            <?php echo esc_html($buttonLabel); ?>
        </button>
        <?php if (!$multiple) : ?>
            <button type="button" class="button-link wpb-image-field__remove"<?php echo $hasImage ? '' : ' hidden'; ?>>
                <?php echo esc_html($removeLabel); ?>
            </button>
        <?php endif; ?>
    </p>
</div>
