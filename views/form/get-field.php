<?php

use WPB\Builder;

$formPath = Builder::PATH['FORM'];
$fieldType = $this->field['type'] ?? 'text';
$partialPath = $this->fieldPartialPath($fieldType);

if ($fieldType === 'hidden') {
    require $partialPath;

    return;
}

if ($this->fieldsType === 'box') : ?>

    <p class="post-attributes-label-wrapper">
        <?php require $formPath . 'partials/label.php'; ?>
    </p>
    <?php require $partialPath; ?>
    <?php require $formPath . 'partials/description.php'; ?>

<?php elseif ($this->fieldsType === 'term') : ?>

    <div class="form-field term-<?= esc_attr($this->field['name']) ?>-wrap">
        <?php require $formPath . 'partials/label.php'; ?>
        <?php require $partialPath; ?>
        <?php require $formPath . 'partials/description.php'; ?>
    </div>

<?php else : ?>

    <tr>
        <th scope="row">
            <?php require $formPath . 'partials/label.php'; ?>
        </th>
        <td>
            <?php require $partialPath; ?>
            <?php require $formPath . 'partials/description.php'; ?>
        </td>
    </tr>

<?php endif; ?>
