<?php

/**
 * Repeater list. Included from Repeater::renderItems().
 *
 * @package WPB\Blocks
 */

use WPB\Builder;

$blockPath = Builder::PATH['BLOCK'];

?>
<div class="wpb-repeater wpb-repeater--<?php echo esc_attr(sanitize_html_class($this->name())); ?>">
<?php foreach ($this->items as $item) : ?>
    <?php
    $classes = ['wpb-repeater__item'];

    foreach ($this->fieldSchemas() as $schema) {
        if (($schema['type'] ?? '') !== 'boolean' || empty($item[$schema['name']])) {
            continue;
        }

        $classes[] = 'wpb-repeater__item--' . sanitize_html_class((string) $schema['name']);
    }
    ?>
    <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
        <?php foreach ($this->fieldSchemas() as $schema) : ?>
            <?php
            $type = (string) ($schema['type'] ?? 'string');

            if ($type === 'boolean') {
                $this->field = $schema;
                $this->value = $item[$schema['name']] ?? false;
                require $blockPath . 'partials/boolean.php';
                continue;
            }

            if ($type === 'repeater') {
                echo $this->child((string) $schema['name'])->renderItems($item[$schema['name']] ?? []);
                continue;
            }

            $this->field = $schema;
            $this->value = $item[$schema['name']] ?? null;
            require $blockPath . 'partials/' . $type . '.php';
            ?>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
</div>
