<?php
/**
 * Radio field.
 *
 * @package WPB\Forms
 */

$name = esc_attr($this->field['name']);
$id = 'field_' . esc_attr($this->field['name']);
$value = (string) ($this->field['val'] ?? '');
?>
<fieldset id="<?php echo $id; ?>" class="wpb-radio-group">
    <legend class="screen-reader-text"><?php echo esc_html($this->field['label']); ?></legend>
    <?php foreach ($this->field['options'] as $optionValue => $optionLabel) : ?>
        <label>
            <input type="radio"
                name="<?php echo $name; ?>"
                value="<?php echo esc_attr($optionValue); ?>"
                <?php echo checked($value, (string) $optionValue, false); ?>
                <?php echo $this->field['attributes'] ?? ''; ?>>
            <?php echo esc_html($optionLabel); ?>
        </label><br>
    <?php endforeach; ?>
</fieldset>
