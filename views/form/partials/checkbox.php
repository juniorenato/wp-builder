<?php
$name = esc_attr($this->field['name']);
$id = 'field_' . esc_attr($this->field['name']);
$multiple = !empty($this->field['multiple']);
$options = $this->field['options'] ?? [];
$selectedValues = array_map('strval', (array) ($this->field['val'] ?? []));
?>
<?php if ($options === []) : ?>
    <input type="checkbox"
        name="<?= $name ?>"
        id="<?= $id ?>"
        value="1"
        <?= checked(!empty($this->field['val']), true, false) ?>
        <?= $this->field['attributes'] ?? '' ?>>
<?php else : ?>
    <fieldset id="<?= $id ?>" class="wpb-checkbox-group">
        <legend class="screen-reader-text"><?= esc_html($this->field['label']) ?></legend>
        <?php foreach ($options as $optionValue => $optionLabel) : ?>
            <?php
            $isChecked = $multiple
                ? in_array((string) $optionValue, $selectedValues, true)
                : (string) $optionValue === (string) ($this->field['val'] ?? '');
            $inputName = $multiple ? $name . '[]' : $name;
            ?>
            <label>
                <input type="checkbox"
                    name="<?= esc_attr($inputName) ?>"
                    value="<?= esc_attr($optionValue) ?>"
                    <?= $isChecked ? ' checked' : '' ?>
                    <?= $this->field['attributes'] ?? '' ?>>
                <?= esc_html($optionLabel) ?>
            </label><br>
        <?php endforeach; ?>
    </fieldset>
<?php endif; ?>
