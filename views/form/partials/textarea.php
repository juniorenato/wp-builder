<textarea
    name="<?= esc_attr($this->field['name']) ?>"
    id="field_<?= esc_attr($this->field['name']) ?>"
    <?= $this->field['attributes'] ?? '' ?>><?= esc_textarea($this->field['val'] ?? '') ?></textarea>
