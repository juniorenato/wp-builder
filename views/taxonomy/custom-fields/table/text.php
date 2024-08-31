<?php
global $add;
global $hswp;
global $term_id;

$val = get_term_meta($term_id, $hswp['name'], true);
?>
<tr class="form-field term-<?= $hswp['name'] ?>-wrap">
    <th scope="row">
        <label for="field_<?= $hswp['name'] ?>"><?= $hswp['label'] ?></label>
    </th>
    <td>
        <input name="<?= $hswp['name'] ?>" id="field_<?= $hswp['name'] ?>" type="text" value="<?= $val ?>" size="40">
    </td>
</tr>
