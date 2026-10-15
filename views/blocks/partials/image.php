<?php

/**
 * Image field on the public block.
 *
 * @package WPB\Blocks
 */

$attachmentId = (int) $this->value;

if ($attachmentId <= 0) {
    return;
}

$html = wp_get_attachment_image($attachmentId, 'large');

if ($html === '') {
    return;
}

$name = sanitize_html_class((string) $this->field['name']);

?>
<figure class="wpb-field wpb-field--<?php echo esc_attr($name); ?> wpb-field--image"><?php echo $html; ?></figure>
