<?php
$name = esc_attr($this->field['name']);
$id = 'field_' . esc_attr($this->field['name']);
$value = (string) ($this->field['val'] ?? '');
?>
<fieldset id="<?= $id ?>" class="wpb-radio-group">
    <legend class="screen-reader-text"><?= esc_html($this->field['label']) ?></legend>
    <?php foreach ($this->field['options'] as $optionValue => $optionLabel) : ?>
        <label>
            <input type="radio"
                name="<?= $name ?>"
                value="<?= esc_attr($optionValue) ?>"
                <?= checked($value, (string) $optionValue, false) ?>
                <?= $this->field['attributes'] ?? '' ?>>
            <?= esc_html($optionLabel) ?>
        </label><br>
    <?php endforeach; ?>
</fieldset>
