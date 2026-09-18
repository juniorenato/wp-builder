<?php
$name = esc_attr($this->field['name']);
$multiple = !empty($this->field['multiple']);
$id = 'field_' . esc_attr($this->field['name']);
$selectedValues = array_map('strval', (array) ($this->field['val'] ?? []));
?>
<select name="<?= $name . ($multiple ? '[]' : '') ?>" id="<?= $id ?>" <?= $this->field['attributes'] ?? '' ?><?= $multiple ? ' multiple' : '' ?>>
    <?php foreach ($this->field['options'] as $value => $label) : ?>
        <?php
        $isSelected = $multiple
            ? in_array((string) $value, $selectedValues, true)
            : (string) $value === (string) ($this->field['val'] ?? '');
        ?>
        <option value="<?= esc_attr($value) ?>"<?= $isSelected ? ' selected' : '' ?>>
            <?= esc_html($label) ?>
        </option>
    <?php endforeach; ?>
</select>
