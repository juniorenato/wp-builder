<input type="hidden"
    name="<?= esc_attr($this->field['name']) ?>"
    id="field_<?= esc_attr($this->field['name']) ?>"
    value="<?= esc_attr($this->field['val'] ?? '') ?>"<?= $this->field['attributes'] ?? '' ?>>
