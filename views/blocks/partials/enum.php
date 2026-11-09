<?php

/**
 * Enum field on the public block.
 *
 * @package WPB\Blocks
 */

if (!is_scalar($this->value) || (string) $this->value === '') {
    return;
}

$name = sanitize_html_class((string) $this->field['name']);

?>
<div class="wpb-field wpb-field--<?php echo esc_attr($name); ?> wpb-field--enum"><?php echo esc_html((string) $this->value); ?></div>
