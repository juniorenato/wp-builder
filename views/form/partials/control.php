<?php
/**
 * Stacked label, control, and description for one repeater subfield.
 *
 * @package WPB\Forms
 */

$formPath = \WPB\Builder::PATH['FORM'];
$fieldKey = (string) ($this->field['key'] ?? $this->field['name'] ?? '');
$multiple = !empty($this->field['multiple']) ? '1' : '0';
?>
<div class="wpb-repeater__field" data-key="<?php echo esc_attr($fieldKey); ?>" data-multiple="<?php echo esc_attr($multiple); ?>">
    <?php if (($this->field['type'] ?? '') === 'repeater') : ?>
        <p class="wpb-repeater__label"><strong><?php echo esc_html($this->field['label'] ?? ''); ?></strong></p>
        <?php require $formPath . 'partials/repeater.php'; ?>
        <?php require $formPath . 'partials/description.php'; ?>
    <?php else : ?>
        <?php require $formPath . 'partials/label.php'; ?>
        <?php require $partialPath; ?>
        <?php require $formPath . 'partials/description.php'; ?>
    <?php endif; ?>
</div>
