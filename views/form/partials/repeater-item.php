<?php
/**
 * One repeater row.
 *
 * @package WPB\Forms
 */
?>
<div class="wpb-repeater__item">
    <div class="wpb-repeater__fields">
        <?php foreach ($schema['fields'] as $child) : ?>
            <?php
            if (!is_array($child) || !isset($child['name'])) {
                continue;
            }

            $control = $child;
            $control['key'] = (string) $child['name'];
            $control['name'] = $rowName . '[' . $child['name'] . ']';

            if (($child['type'] ?? '') === 'repeater') {
                $control['repeater'] = $repeater->child((string) $child['name']);
                $nested = $item[$child['name']] ?? [];
                $control['val'] = is_array($nested) ? $nested : [];
            } else {
                $control['val'] = $item[$child['name']] ?? '';
            }

            if (!empty($control['description'])) {
                $describedBy = preg_replace(
                    '/\saria-describedby="[^"]*"/',
                    '',
                    (string) ($control['attributes'] ?? '')
                );
                $control['attributes'] = (is_string($describedBy) ? $describedBy : '')
                    . ' aria-describedby="' . esc_attr($control['name'] . '-description') . '"';
            }

            $this->renderControl($control);
            ?>
        <?php endforeach; ?>
    </div>
    <p class="wpb-repeater__toolbar">
        <button type="button" class="button wpb-repeater__up"><?php esc_html_e('Move up', 'wpb'); ?></button>
        <button type="button" class="button wpb-repeater__down"><?php esc_html_e('Move down', 'wpb'); ?></button>
        <button type="button" class="button wpb-repeater__remove"><?php esc_html_e('Remove', 'wpb'); ?></button>
    </p>
</div>
