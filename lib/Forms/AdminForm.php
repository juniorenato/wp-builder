<?php

/**
 * Admin form field helpers.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Forms
 */

namespace WPB\Forms;

use InvalidArgumentException;
use WPB\Builder;

/**
 * Builds, renders, sanitizes, and saves admin form fields.
 *
 * @since  0.2.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
trait AdminForm
{
    /**
     * Allowed field partial types.
     *
     * @since 0.2.0
     *
     * @var list<string>
     */
    private const array ALLOWED_FIELD_TYPES = [
        'hidden',
        'text',
        'textarea',
        'rich-text',
        'number',
        'select',
        'radio',
        'checkbox',
    ];

    /**
     * Allowed value storage types.
     *
     * @since 0.2.0
     *
     * @var list<string>
     */
    private const array ALLOWED_VALUE_TYPES = [
        'option',
        'post',
        'taxonomy',
        'user',
    ];

    /**
     * Allowed field layout types.
     *
     * @since 0.2.0
     *
     * @var list<string>
     */
    private const array ALLOWED_FIELDS_TYPES = [
        'box',
        'term',
        'table',
    ];

    /**
     * Current object ID used when reading or writing values.
     *
     * @since 0.2.0
     */
    protected int $id = 0;

    /**
     * Field currently being rendered.
     *
     * @since 0.2.0
     *
     * @var array<string, mixed>
     */
    protected array $field = [];

    /**
     * Registered fields.
     *
     * @since 0.2.0
     *
     * @var array<string, array<string, mixed>>
     */
    public array $fields = [];

    /**
     * Current field layout type.
     *
     * @since 0.2.0
     */
    public string $fieldsType = '';

    /**
     * Field names persisted as post meta.
     *
     * @since 0.2.0
     *
     * @var list<string>
     */
    public array $metaFields = [];

    /**
     * Value storage type.
     *
     * @since 0.2.0
     */
    public string $valueType = '';

    /**
     * Renders the WordPress nonce field used by meta boxes.
     *
     * @since 0.2.0
     *
     * @see https://developer.wordpress.org/reference/functions/wp_create_nonce/
     * @see https://developer.wordpress.org/reference/functions/wp_nonce_field/
     */
    public function addNonceName(): void
    {
        $this->renderField([
            'type'        => 'hidden',
            'name'        => '_meta_noncename',
            'val'         => wp_create_nonce(__FILE__),
            'attributes'  => '',
            'label'       => '',
            'description' => '',
        ]);
    }

    /**
     * Saves registered meta fields for a post.
     *
     * @since 0.2.0
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object being saved.
     *
     * @return int Post ID.
     *
     * @see https://developer.wordpress.org/reference/hooks/save_post/
     * @see https://developer.wordpress.org/reference/functions/wp_verify_nonce/
     * @see https://developer.wordpress.org/reference/functions/update_post_meta/
     * @see https://developer.wordpress.org/reference/functions/current_user_can/
     */
    public function savePost($post_id, $post): int
    {
        if (
            !isset($_POST['_meta_noncename'])
            || !current_user_can('edit_post', $post_id)
            || (isset($_POST['_meta_noncename']) && !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['_meta_noncename'])),
                __FILE__
            ))
        ) {
            return $post_id;
        }

        if ($post->post_type == 'revision') {
            return $post_id;
        }

        $meta = [];
        foreach ($this->metaFields as $field) {
            if ($field !== '_meta_noncename') {
                $fieldConfig = $this->fields[$field] ?? ['type' => 'text', 'name' => $field];
                $meta[$field] = $this->sanitizeFieldValue(
                    $fieldConfig,
                    $this->getPostedValue($fieldConfig)
                );
            }
        }

        foreach ($meta as $k => $v) {
            update_post_meta($post_id, $k, $v);
        }

        return $post_id;
    }

    /**
     * Registers a form field.
     *
     * @since 0.2.0
     *
     * @param string               $type        Field type.
     * @param string               $name        Field name.
     * @param string               $label       Field label.
     * @param array<string, mixed> $attributes  HTML attributes.
     * @param array<mixed, mixed>  $options     Field options or editor settings.
     * @param string               $description Field description.
     *
     * @throws InvalidArgumentException If the field type is not allowed.
     */
    public function formField(
        string $type,
        string $name,
        string $label = '',
        array $attributes = [],
        array $options = [],
        string $description = ''
    ): void {
        $type = $this->assertFieldType($type);

        if ($description) {
            $attributes['aria-describedby'] = $name . '-description';
        }

        $multiple = array_key_exists('multiple', $attributes);
        if ($multiple) {
            unset($attributes['multiple']);
        }

        $this->fields[$name] = [
            'type'        => $type,
            'name'        => $name,
            'val'         => '',
            'attributes'  => $this->formatAttributes($attributes),
            'multiple'    => $multiple,
            'options'     => $options,
            'label'       => $label,
            'description' => $description,
        ];

        $this->metaFields[] = $name;
    }

    /**
     * Adds a text field.
     *
     * @since 0.2.0
     *
     * @param string               $name        Field name.
     * @param string               $label       Field label.
     * @param string               $description Field description.
     * @param string               $class       Extra CSS classes.
     * @param array<string, mixed> $attrs       Extra HTML attributes.
     */
    public function addTextField(string $name, string $label, string $description = '', string $class = '', array $attrs = []): void
    {
        $attributes['class'] = $this->adminFieldClass('regular-text', $class);
        $attributes = array_merge($attributes, $attrs);

        $this->formField('text', $name, $label, $attributes, [], $description);
    }

    /**
     * Adds a select field.
     *
     * @since 0.2.0
     *
     * @param string                $name        Field name.
     * @param string                $label       Field label.
     * @param array<string|int, mixed> $options  Select options.
     * @param bool                  $multiple    Whether multiple values are allowed.
     * @param string                $description Field description.
     * @param string                $class       Extra CSS classes.
     * @param array<string, mixed>  $attrs       Extra HTML attributes.
     */
    public function addSelectField(
        string $name,
        string $label,
        array $options,
        bool $multiple = false,
        string $description = '',
        string $class = '',
        array $attrs = []
    ): void {
        $attributes['class'] = $this->adminFieldClass('regular-text', $class);
        if ($multiple) {
            $attributes['multiple'] = $multiple;
        }
        $attrs = array_merge($attrs, $attributes);

        $this->formField('select', $name, $label, $attrs, $options, $description);
    }

    /**
     * Adds a textarea field.
     *
     * @since 0.2.0
     *
     * @param string               $name        Field name.
     * @param string               $label       Field label.
     * @param string               $description Field description.
     * @param string               $class       Extra CSS classes.
     * @param array<string, mixed> $attrs       Extra HTML attributes.
     */
    public function addTextareaField(string $name, string $label, string $description = '', string $class = '', array $attrs = []): void
    {
        $class = $this->adminFieldClass('large-text', $class);
        $attributes['class'] = trim($class);
        $attributes = array_merge($attributes, $attrs);

        $this->formField('textarea', $name, $label, $attributes, [], $description);
    }

    /**
     * Adds a number field.
     *
     * @since 0.2.0
     *
     * @param string               $name        Field name.
     * @param string               $label       Field label.
     * @param string               $description Field description.
     * @param string               $class       Extra CSS classes.
     * @param array<string, mixed> $attrs       Extra HTML attributes.
     */
    public function addNumberField(string $name, string $label, string $description = '', string $class = '', array $attrs = []): void
    {
        $class = $this->adminFieldClass('small-text', $class, false);
        $attributes['class'] = trim($class);
        $attributes = array_merge($attributes, $attrs);

        $this->formField('number', $name, $label, $attributes, [], $description);
    }

    /**
     * Adds a radio field.
     *
     * @since 0.2.0
     *
     * @param string                   $name        Field name.
     * @param string                   $label       Field label.
     * @param array<string|int, mixed> $options     Radio options.
     * @param string                   $description Field description.
     * @param string                   $class       Extra CSS classes.
     * @param array<string, mixed>     $attrs       Extra HTML attributes.
     */
    public function addRadioField(string $name, string $label, array $options, string $description = '', string $class = '', array $attrs = []): void
    {
        $attributes = [];

        if ($class !== '') {
            $attributes['class'] = trim($class);
        }

        $attributes = array_merge($attributes, $attrs);

        $this->formField('radio', $name, $label, $attributes, $options, $description);
    }

    /**
     * Adds a checkbox field.
     *
     * @since 0.2.0
     *
     * @param string                   $name        Field name.
     * @param string                   $label       Field label.
     * @param array<string|int, mixed> $options     Checkbox options.
     * @param bool                     $multiple    Whether multiple values are allowed.
     * @param string                   $description Field description.
     * @param string                   $class       Extra CSS classes.
     * @param array<string, mixed>     $attrs       Extra HTML attributes.
     */
    public function addCheckboxField(
        string $name,
        string $label,
        array $options = [],
        bool $multiple = true,
        string $description = '',
        string $class = '',
        array $attrs = []
    ): void {
        $attributes = [];

        if ($class !== '') {
            $attributes['class'] = trim($class);
        }

        if ($options !== [] && $multiple) {
            $attributes['multiple'] = true;
        }

        $attributes = array_merge($attributes, $attrs);

        $this->formField('checkbox', $name, $label, $attributes, $options, $description);
    }

    /**
     * Adds a rich text field.
     *
     * @since 0.2.0
     *
     * @param string               $name           Field name.
     * @param string               $label          Field label.
     * @param array<string, mixed> $editorSettings `wp_editor()` settings.
     * @param string               $description    Field description.
     * @param array<string, mixed> $attrs          Extra HTML attributes.
     *
     * @see https://developer.wordpress.org/reference/functions/wp_editor/
     */
    public function addRichTextField(
        string $name,
        string $label,
        array $editorSettings = [],
        string $description = '',
        array $attrs = []
    ): void {
        $attributes = array_merge($attrs, [
            'class' => 'wp-editor-wrap widefat',
        ]);

        $this->formField('rich-text', $name, $label, $attributes, $editorSettings, $description);
    }

    /**
     * Reads a stored value by the current value type.
     *
     * @since 0.2.0
     *
     * @param string      $key  Meta or option key.
     * @param string|null $type Optional value type override.
     *
     * @return mixed Stored value, or an empty string when unavailable.
     *
     * @throws InvalidArgumentException If `$type` is not allowed.
     *
     * @see https://developer.wordpress.org/reference/functions/get_option/
     * @see https://developer.wordpress.org/reference/functions/get_post_meta/
     * @see https://developer.wordpress.org/reference/functions/get_term_meta/
     * @see https://developer.wordpress.org/reference/functions/get_user_meta/
     */
    protected function getValue(string $key, ?string $type = null): mixed
    {
        if ($type) {
            $this->valueType = $this->assertValueType($type);
        }

        if ($this->valueType !== '') {
            $this->assertValueType($this->valueType);
        }

        if ($this->valueType != 'option'
            && (!isset($this->id) || !$this->id)
        ) {
            return '';
        }

        switch ($this->valueType) {
            case 'option':
                $val = get_option($key);
                break;

            case 'post':
                $val = get_post_meta($this->id, $key, true);
                break;

            case 'taxonomy':
                $val = get_term_meta($this->id, $key, true);
                break;

            case 'user':
                $val = get_user_meta($this->id, $key, true);
                break;

            default:
                $val = '';
                break;
        }

        return $val;
    }

    /**
     * Writes a value by the current value type.
     *
     * @since 0.2.0
     *
     * @param string $key   Meta or option key.
     * @param mixed  $value Value to store.
     *
     * @return int|bool|string Write result, or an empty string when unavailable.
     *
     * @throws InvalidArgumentException If the current value type is not allowed.
     *
     * @see https://developer.wordpress.org/reference/functions/update_option/
     * @see https://developer.wordpress.org/reference/functions/update_post_meta/
     * @see https://developer.wordpress.org/reference/functions/update_term_meta/
     * @see https://developer.wordpress.org/reference/functions/update_user_meta/
     */
    protected function setValue(string $key, mixed $value): int|bool|string
    {
        if ($this->valueType !== '') {
            $this->assertValueType($this->valueType);
        }

        if ($this->valueType != 'option'
            && (!isset($this->id) || !$this->id)
        ) {
            return '';
        }

        switch ($this->valueType) {
            case 'option':
                $meta_id = update_option($key, $value);
                break;

            case 'post':
                $meta_id = update_post_meta($this->id, $key, $value);
                break;

            case 'taxonomy':
                $meta_id = update_term_meta($this->id, $key, $value);
                break;

            case 'user':
                $meta_id = update_user_meta($this->id, $key, $value);
                break;

            default:
                $meta_id = '';
                break;
        }

        return $meta_id;
    }

    /**
     * Validates a field type and ensures its partial exists.
     *
     * @since 0.2.0
     *
     * @param string $type Field type.
     *
     * @return string Sanitized field type.
     *
     * @throws InvalidArgumentException If the type is invalid or the partial is missing.
     */
    protected function assertFieldType(string $type): string
    {
        $type = preg_replace('/[^a-z0-9_-]/', '', $type) ?? '';

        if ($type === '') {
            throw new InvalidArgumentException(sprintf(
                'Invalid field type. Allowed types: %s.',
                implode(', ', self::ALLOWED_FIELD_TYPES)
            ));
        }

        if (!in_array($type, self::ALLOWED_FIELD_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid field type "%s". Allowed types: %s.',
                $type,
                implode(', ', self::ALLOWED_FIELD_TYPES)
            ));
        }

        $partialPath = Builder::PATH['FORM'] . 'partials/' . $type . '.php';

        if (!file_exists($partialPath)) {
            throw new InvalidArgumentException(sprintf(
                'Field type "%s" has no partial view at "%s".',
                $type,
                $partialPath
            ));
        }

        return $type;
    }

    /**
     * Validates a field layout type.
     *
     * @since 0.2.0
     *
     * @param string $type Layout type.
     *
     * @return string Sanitized layout type.
     *
     * @throws InvalidArgumentException If the type is not allowed.
     */
    protected function assertFieldsType(string $type): string
    {
        $type = preg_replace('/[^a-z0-9_-]/', '', $type) ?? '';

        if ($type === '' || !in_array($type, self::ALLOWED_FIELDS_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid fields type "%s". Allowed types: %s.',
                $type,
                implode(', ', self::ALLOWED_FIELDS_TYPES)
            ));
        }

        return $type;
    }

    /**
     * Validates a value storage type.
     *
     * @since 0.2.0
     *
     * @param string $type Value type.
     *
     * @return string Sanitized value type.
     *
     * @throws InvalidArgumentException If the type is not allowed.
     */
    protected function assertValueType(string $type): string
    {
        $type = preg_replace('/[^a-z0-9_-]/', '', $type) ?? '';

        if ($type === '' || !in_array($type, self::ALLOWED_VALUE_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid value type "%s". Allowed types: %s.',
                $type,
                implode(', ', self::ALLOWED_VALUE_TYPES)
            ));
        }

        return $type;
    }

    /**
     * Returns the absolute path of a field partial.
     *
     * @since 0.2.0
     *
     * @param string $type Field type.
     *
     * @return string Absolute partial path.
     *
     * @throws InvalidArgumentException If the field type is invalid.
     */
    protected function fieldPartialPath(string $type): string
    {
        $type = $this->assertFieldType($type);

        return Builder::PATH['FORM'] . 'partials/' . $type . '.php';
    }

    /**
     * Sanitizes a submitted field value.
     *
     * @since 0.2.0
     *
     * @param array<string, mixed> $field Field configuration.
     * @param mixed                $value Submitted value.
     *
     * @return string|list<string> Sanitized value.
     *
     * @see https://developer.wordpress.org/reference/functions/sanitize_text_field/
     * @see https://developer.wordpress.org/reference/functions/sanitize_textarea_field/
     * @see https://developer.wordpress.org/reference/functions/wp_kses_post/
     * @see https://developer.wordpress.org/reference/functions/rest_sanitize_boolean/
     */
    protected function sanitizeFieldValue(array $field, mixed $value): string|array
    {
        switch ($field['type'] ?? 'text') {
            case 'textarea':
                return sanitize_textarea_field((string) $value);

            case 'rich-text':
                return wp_kses_post((string) $value);

            case 'number':
                if ($value === '' || $value === null) {
                    return '';
                }

                return is_numeric($value)
                    ? (string) (str_contains((string) $value, '.') ? (float) $value : (int) $value)
                    : '';

            case 'select':
            case 'radio':
                if (is_array($value)) {
                    return array_map('sanitize_text_field', $value);
                }

                return sanitize_text_field((string) $value);

            case 'checkbox':
                if (is_array($value)) {
                    return array_map('sanitize_text_field', $value);
                }

                return (string) (int) rest_sanitize_boolean($value);

            case 'hidden':
            case 'text':
            default:
                return sanitize_text_field((string) $value);
        }
    }

    /**
     * Reads a field value from the current request.
     *
     * @since 0.2.0
     *
     * @param array<string, mixed> $field Field configuration.
     *
     * @return mixed Unslashed submitted value.
     *
     * @see https://developer.wordpress.org/reference/functions/wp_unslash/
     */
    protected function getPostedValue(array $field): mixed
    {
        $name = $field['name'] ?? '';
        $type = $field['type'] ?? 'text';
        $multiple = !empty($field['multiple']);
        $hasOptions = !empty($field['options']);

        if ($type === 'checkbox' && !$hasOptions) {
            $value = isset($_POST[$name]) ? wp_unslash($_POST[$name]) : '';
        } elseif (($type === 'checkbox' && $hasOptions) || ($type === 'select' && $multiple)) {
            $value = wp_unslash($_POST[$name] ?? []);
        } else {
            $value = wp_unslash($_POST[$name] ?? '');
        }

        return $value;
    }

    /**
     * Builds the CSS class list for an admin field.
     *
     * @since 0.2.0
     *
     * @param string $baseClass       Default WordPress admin class.
     * @param string $class           Extra classes.
     * @param bool   $addRegularText  Whether to append `$baseClass` when `widefat` is absent.
     *
     * @return string Normalized class list.
     */
    private function adminFieldClass(string $baseClass, string $class = '', bool $addRegularText = true): string
    {
        $class .= ($this->valueType == 'post' && !str_contains($class, 'widefat')) ? ' widefat' : '';

        if ($addRegularText && !str_contains($class, 'widefat') && !str_contains($class, $baseClass)) {
            $class .= ' ' . $baseClass;
        }

        if (!$addRegularText && !str_contains($class, $baseClass)) {
            $class .= ' ' . $baseClass;
        }

        return trim($class);
    }

    /**
     * Returns the absolute path of the field wrapper view.
     *
     * @since 0.2.0
     *
     * @param string $type Field type.
     *
     * @return string Absolute view path.
     *
     * @throws InvalidArgumentException If the view file is missing.
     */
    protected function fieldViewPath(string $type): string
    {
        $this->assertFieldType($type);

        $path = Builder::PATH['FORM'] . 'get-field.php';

        if (!file_exists($path)) {
            throw new InvalidArgumentException(sprintf(
                'Field view "%s" was not found.',
                $path
            ));
        }

        return $path;
    }

    /**
     * Renders a single field.
     *
     * @since 0.2.0
     *
     * @param array<string, mixed> $field Field configuration.
     *
     * @throws InvalidArgumentException If the field type is invalid.
     */
    protected function renderField(array $field): void
    {
        $fieldType = $this->assertFieldType($field['type'] ?? 'text');

        $this->field = $field;
        $this->field['type'] = $fieldType;

        if (($field['val'] ?? '') === '') {
            $this->field['val'] = $this->getValue($this->field['name']);
        }

        require $this->fieldViewPath($fieldType);
    }

    /**
     * Renders all registered fields.
     *
     * @since 0.2.0
     *
     * @param string $fieldsType Optional layout type override.
     *
     * @throws InvalidArgumentException If `$fieldsType` is invalid.
     */
    protected function renderFields(string $fieldsType = ''): void
    {
        if ($fieldsType !== '') {
            $this->fieldsType = $this->assertFieldsType($fieldsType);
        }

        foreach ($this->fields as $field) {
            $this->renderField($field);
        }
    }

    /**
     * Saves all registered fields from the current request.
     *
     * @since 0.2.0
     */
    protected function saveFieldsFromPost(): void
    {
        foreach ($this->fields as $name => $field) {
            if (!isset($field['name'])) {
                continue;
            }

            $this->setValue(
                $name,
                $this->sanitizeFieldValue($field, $this->getPostedValue($field))
            );
        }
    }

    /**
     * Converts an attribute map into an HTML attribute string.
     *
     * @since 0.2.0
     *
     * @param array<string, mixed> $arr_attributes Attribute map.
     *
     * @return string Escaped attribute string.
     *
     * @see https://developer.wordpress.org/reference/functions/esc_attr/
     */
    private function formatAttributes(array $arr_attributes): string
    {
        $attributes = '';

        foreach ($arr_attributes as $key => $val) {
            $key = preg_replace('/[^a-z0-9_:-]/i', '', (string) $key) ?? '';

            if ($key === '') {
                continue;
            }

            if ($val === true) {
                $attributes .= ' ' . $key;
                continue;
            }

            $attributes .= ' ' . $key . '="' . esc_attr((string) $val) . '"';
        }

        return $attributes;
    }

    /**
     * Renders the registered fields using the current layout.
     *
     * @since 0.2.0
     */
    protected function field(): void
    {
        $this->renderFields();
    }
}
