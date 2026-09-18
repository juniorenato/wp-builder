<?php
/**
 * Select field.
 *
 * @package WPB\Forms
 */

$name = esc_attr($this->field['name']);
$multiple = !empty($this->field['multiple']);
$id = 'field_' . esc_attr($this->field['name']);
$selectedValues = array_map('strval', (array) ($this->field['val'] ?? []));
?>
<select name="<?php echo $name . ($multiple ? '[]' : ''); ?>" id="<?php echo $id; ?>" <?php echo $this->field['attributes'] ?? ''; ?><?php echo $multiple ? ' multiple' : ''; ?>>
    <?php foreach ($this->field['options'] as $value => $label) : ?>
        <?php
        $isSelected = $multiple
            ? in_array((string) $value, $selectedValues, true)
            : (string) $value === (string) ($this->field['val'] ?? '');
        ?>
        <option value="<?php echo esc_attr($value); ?>"<?php echo $isSelected ? ' selected' : ''; ?>>
            <?php echo esc_html($label); ?>
        </option>
    <?php endforeach; ?>
</select>
