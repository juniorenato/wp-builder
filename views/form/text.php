
<?php if($this->fieldsType == 'box') : ?>

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

<?php elseif($this->fieldsType == 'term') : ?>

    <div class="form-field term-<?= $this->field['name'] ?>-wrap">

        <label for="field_<?= $this->field['name'] ?>"><?= $this->field['label'] ?></label>
        <input type="text"
            name="<?= $this->field['name'] ?>"
            id="field_<?= $this->field['name'] ?>"
            value="<?= $this->field['val'] ?? '' ?>"
            <?= $this->field['attributes'] ?>>

        <?php if($this->field['description']) : ?>
            <p class="description" id="<?= $this->field['name'] ?>-description"><?= $this->field['description'] ?></p>
        <?php endif ?>

    </div><!--/.form-field -->

<?php else : ?>

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

<?php endif ?>
