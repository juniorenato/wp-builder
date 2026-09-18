<?php
/**
 * Rich text editor field.
 *
 * @package WPB\Forms
 */

$editorId = 'field_' . (preg_replace('/[^a-z0-9_]/i', '_', (string) $this->field['name']) ?? '');
$settings = is_array($this->field['options'] ?? null) ? $this->field['options'] : [];

wp_editor(
    (string) ($this->field['val'] ?? ''),
    $editorId,
    array_merge([
        'textarea_name' => $this->field['name'],
        'textarea_rows' => 8,
        'media_buttons' => true,
        'teeny'         => false,
        'quicktags'     => true,
        'editor_class'  => 'wp-editor-area widefat',
    ], $settings)
);
