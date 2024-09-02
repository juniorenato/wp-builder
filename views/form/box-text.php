<p class="post-attributes-label-wrapper">
    <label for="field_<?= $this->field['name'] ?>"><?= $this->field['label'] ?></label>
</p>
<input type="text"
    name="<?= $this->field['name'] ?>"
    id="field_<?= $this->field['name'] ?>"
    value="<?= $this->field['val'] ?? '' ?>"
    <?= $this->field['attributes'] ?>>

<?php if($this->field['description']) : ?>
    <p class="description" id="<?= $this->field['name'] ?>-description"><?= $this->field['description'] ?></p>
<?php endif ?>


