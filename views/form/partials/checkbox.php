<?php
/**
 * Checkbox field.
 *
 * @package WPB\Forms
 */

$name = esc_attr($this->field['name']);
$id = 'field_' . esc_attr($this->field['name']);
$multiple = !empty($this->field['multiple']);
$options = $this->field['options'] ?? [];
$selectedValues = array_map('strval', (array) ($this->field['val'] ?? []));
?>
<?php if ($options === []) : ?>
    <input type="checkbox"
        name="<?php echo $name; ?>"
        id="<?php echo $id; ?>"
        value="1"
        <?php echo checked(!empty($this->field['val']), true, false); ?>
        <?php echo $this->field['attributes'] ?? ''; ?>>
<?php else : ?>
    <fieldset id="<?php echo $id; ?>" class="wpb-checkbox-group">
        <legend class="screen-reader-text"><?php echo esc_html($this->field['label']); ?></legend>
        <?php foreach ($options as $optionValue => $optionLabel) : ?>
            <?php
            $isChecked = $multiple
                ? in_array((string) $optionValue, $selectedValues, true)
                : (string) $optionValue === (string) ($this->field['val'] ?? '');
            $inputName = $multiple ? $name . '[]' : $name;
            ?>
            <label>
                <input type="checkbox"
                    name="<?php echo esc_attr($inputName); ?>"
                    value="<?php echo esc_attr($optionValue); ?>"
                    <?php echo $isChecked ? ' checked' : ''; ?>
                    <?php echo $this->field['attributes'] ?? ''; ?>>
                <?php echo esc_html($optionLabel); ?>
            </label><br>
        <?php endforeach; ?>
    </fieldset>
<?php endif; ?>
