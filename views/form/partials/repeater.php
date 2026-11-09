<?php
/**
 * Repeater field: saved rows, an inert template row, and the add button.
 *
 * @package WPB\Forms
 */

use WPB\Forms\Repeater;

$wpbRepeater = $this->field['repeater'] ?? null;

if (!$wpbRepeater instanceof Repeater) {
    return;
}

$this->enqueueAdminRepeater();

$wpbRepeaterSchema = $wpbRepeater->schema();
$wpbRepeaterName = (string) ($this->field['name'] ?? $wpbRepeaterSchema['name']);
$wpbRepeaterDataName = str_contains($wpbRepeaterName, '[')
    ? (string) $wpbRepeaterSchema['name']
    : $wpbRepeaterName;
$wpbRepeaterRows = $wpbRepeater->rowsForForm($this->field['val'] ?? []);
?>
<div
    class="wpb-repeater"
    data-name="<?php echo esc_attr($wpbRepeaterDataName); ?>"
    data-min="<?php echo esc_attr((string) $wpbRepeaterSchema['min']); ?>"
    data-max="<?php echo esc_attr((string) $wpbRepeaterSchema['max']); ?>">
    <div class="wpb-repeater__items">
        <?php foreach ($wpbRepeaterRows as $wpbRepeaterIndex => $wpbRepeaterRow) : ?>
            <?php $this->renderRepeaterItem(
                $wpbRepeater,
                $wpbRepeaterSchema,
                $wpbRepeaterRow,
                $wpbRepeaterName . '[' . $wpbRepeaterIndex . ']'
            ); ?>
        <?php endforeach; ?>
    </div>
    <template>
        <?php $this->renderRepeaterItem(
            $wpbRepeater,
            $wpbRepeaterSchema,
            [],
            $wpbRepeaterName . '[__i__]'
        ); ?>
    </template>
    <input type="hidden" class="wpb-repeater__present" name="<?php echo esc_attr($wpbRepeaterName); ?>[__present]" value="1">
    <p class="wpb-repeater__footer">
        <button type="button" class="button button-primary wpb-repeater__add">
            <?php echo esc_html(__('Add', 'wpb') . ' ' . (string) $wpbRepeaterSchema['label']); ?>
        </button>
    </p>
</div>
