<?php if (!empty($this->field['description'])) : ?>
    <p class="description" id="<?= esc_attr($this->field['name']) ?>-description"><?= esc_html($this->field['description']) ?></p>
<?php endif; ?>
