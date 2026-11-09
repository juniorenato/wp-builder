<?php
/**
 * File field. The button opens the WordPress media modal for any file.
 *
 * One file posts an attachment ID. Several files post a list of IDs.
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

$files = [];

foreach ($attachmentIds as $attachmentId) {
    $fileUrl = '';
    $fileName = '';

    if (function_exists('wp_get_attachment_url')) {
        $resolvedUrl = wp_get_attachment_url($attachmentId);
        $fileUrl = is_string($resolvedUrl) ? $resolvedUrl : '';
    }

    if (function_exists('get_the_title')) {
        $fileName = trim((string) get_the_title($attachmentId));
    }

    if ($fileName === '' && $fileUrl !== '') {
        $fileName = function_exists('wp_basename') ? (string) wp_basename($fileUrl) : $fileUrl;
    }

    $files[] = [
        'id'   => $attachmentId,
        'url'  => $fileUrl,
        'name' => $fileName,
    ];
}

$selectLabel = __($multiple ? 'Select files' : 'Select file', 'wpb');
$replaceLabel = __('Replace file', 'wpb');
$addLabel = __('Add files', 'wpb');
$removeLabel = __('Remove file', 'wpb');
$hasFile = $files !== [];
$requestName = (string) $this->field['name'] . ($multiple ? '[]' : '');
$buttonLabel = $multiple
    ? ($files === [] ? $selectLabel : $addLabel)
    : ($hasFile ? $replaceLabel : $selectLabel);
?>
<div
    <?php echo $this->field['attributes'] ?? ''; ?>
    data-multiple="<?php echo $multiple ? '1' : '0'; ?>"
    data-name="<?php echo esc_attr($requestName); ?>"
    data-select="<?php echo esc_attr($selectLabel); ?>"
    data-replace="<?php echo esc_attr($replaceLabel); ?>"
    data-add="<?php echo esc_attr($addLabel); ?>"
    data-remove="<?php echo esc_attr($removeLabel); ?>">
    <div class="wpb-file-field__preview">
        <?php if ($multiple) : ?>
            <?php foreach ($files as $file) : ?>
                <div class="wpb-file-field__item">
                    <?php if ($file['url'] !== '') : ?>
                        <a class="wpb-file-field__name" href="<?php echo esc_url($file['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($file['name'] !== '' ? $file['name'] : $file['url']); ?></a>
                    <?php elseif ($file['name'] !== '') : ?>
                        <span class="wpb-file-field__name"><?php echo esc_html($file['name']); ?></span>
                    <?php endif; ?>
                    <input type="hidden"
                        class="wpb-file-field__id"
                        name="<?php echo esc_attr($requestName); ?>"
                        value="<?php echo esc_attr((string) $file['id']); ?>">
                    <button type="button" class="button-link wpb-file-field__remove" aria-label="<?php echo esc_attr($removeLabel); ?>">
                        <?php echo esc_html($removeLabel); ?>
                    </button>
                </div>
            <?php endforeach; ?>
        <?php elseif ($files !== []) : ?>
            <?php if ($files[0]['url'] !== '') : ?>
                <a class="wpb-file-field__name" href="<?php echo esc_url($files[0]['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($files[0]['name'] !== '' ? $files[0]['name'] : $files[0]['url']); ?></a>
            <?php elseif ($files[0]['name'] !== '') : ?>
                <span class="wpb-file-field__name"><?php echo esc_html($files[0]['name']); ?></span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php if (!$multiple) : ?>
        <input type="hidden"
            class="wpb-file-field__id"
            name="<?php echo esc_attr((string) $this->field['name']); ?>"
            value="<?php echo esc_attr(isset($files[0]) ? (string) $files[0]['id'] : ''); ?>">
    <?php endif; ?>
    <p class="wpb-file-field__actions">
        <button type="button"
            class="button wpb-file-field__select"
            id="field_<?php echo esc_attr((string) $this->field['name']); ?>"
            <?php echo !empty($this->field['description']) ? 'aria-describedby="' . esc_attr($this->field['name'] . '-description') . '"' : ''; ?>>
            <?php echo esc_html($buttonLabel); ?>
        </button>
        <?php if (!$multiple) : ?>
            <button type="button" class="button-link wpb-file-field__remove"<?php echo $hasFile ? '' : ' hidden'; ?>>
                <?php echo esc_html($removeLabel); ?>
            </button>
        <?php endif; ?>
    </p>
</div>
