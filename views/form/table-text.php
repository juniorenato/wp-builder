<tr>
<th scope="row">
    <label for="field_<?= $this->field['name'] ?>"><?= $this->field['label'] ?></label>
</th>
<td>
    <input type="text"
        name="<?= $this->field['name'] ?>"
        id="field_<?= $this->field['name'] ?>"
        value="<?= $this->getValue($this->field['name']) ?>"
        <?= $this->field['attributes'] ?>>

    <?php if($this->field['description']) : ?>
        <p class="description" id="<?= $this->field['name'] ?>-description"><?= $this->field['description'] ?></p>
    <?php endif ?>

</td>
</tr>
