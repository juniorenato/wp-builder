<?php

namespace WPB\Forms;

use InvalidArgumentException;
use WPB\Builder;

/**
 * -----------------------------------------------------------------------------
 * Admin Form Builder
 * -----------------------------------------------------------------------------
 *
 * @since v0.2.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 * @package juniorenato/wp-builder
 */
trait AdminForm
{
    private const ALLOWED_FIELD_TYPES = [
        'hidden',
        'text',
        'textarea',
        'rich-text',
        'number',
        'select',
        'radio',
        'checkbox',
    ];

    private const ALLOWED_VALUE_TYPES = [
        'option',
        'post',
        'taxonomy',
        'user',
    ];

    private const ALLOWED_FIELDS_TYPES = [
        'box',
        'term',
        'table',
    ];

    protected int $id = 0;

    protected array $field = [];

    public array $fields = [];
    public string $fieldsType = '';
    public array $metaFields = [];
    public string $valueType = '';

    /**
     * -------------------------------------------------------------------------
     * Add a WordPress noncename
     * -------------------------------------------------------------------------
     *
     * @return void
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

    public function savePost($post_id, $post)
    {
        // Check `noncename`
        if (1 == 0
            || !isset($_POST['_meta_noncename'])
            || !current_user_can('edit_post', $post_id)
            || (isset($_POST['_meta_noncename']) && !wp_verify_nonce($_POST['_meta_noncename'], __FILE__))
        ) { return $post_id; }

        if ($post->post_type == 'revision') {
            return $post_id;
        }

        // Create a meta variable
        $meta = [];
        foreach($this->metaFields as $field) {
            if($field !== '_meta_noncename') {
                $fieldConfig = $this->fields[$field] ?? ['type' => 'text', 'name' => $field];
                $meta[$field] = $this->sanitizeFieldValue(
                    $fieldConfig,
                    $this->getPostedValue($fieldConfig)
                );
            }
        }

        // Insert all metadata
        foreach($meta as $k => $v) {
            update_post_meta($post_id, $k, $v);
        }

        return $post_id;
    }
    /**
     * -------------------------------------------------------------------------
     * Set a field
     * -------------------------------------------------------------------------
     *
     * @param string $type
     * @param string $name
     * @param string $label
     * @param array $attr
     * @param array $options
     * @param string $description
     * @return void
     */
    public function formField(string $type, string $name, string $label = '', array $attributes = [], array $options = [], string $description = ''): void
    {
        $type = $this->assertFieldType($type);

        // Prepare description
        if($description) $attributes['aria-describedby'] = $name .'-description';

        $multiple = array_key_exists('multiple', $attributes);
        if($multiple) unset($attributes['multiple']);

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
     * -------------------------------------------------------------------------
     * Add a text field
     * -------------------------------------------------------------------------
     *
     * @param string $name
     * @param string $label
     * @param string $description
     * @param string $class
     * @param array $attrs
     * @return void
     */
    public function addTextField(string $name, string $label, string $description = '', string $class = '', array $attrs = []): void
    {
        $attributes['class'] = $this->adminFieldClass('regular-text', $class);
        $attributes = array_merge($attributes, $attrs);

        $this->formField('text', $name, $label, $attributes, [], $description);
    }

    /**
     * -------------------------------------------------------------------------
     * Add a select field
     * -------------------------------------------------------------------------
     *
     * @param string $name
     * @param string $label
     * @param array $options
     * @param bool $multiple
     * @param string $description
     * @param string $class
     * @param array $attrs
     * @return void
     */
    public function addSelectField(string $name, string $label, array $options, bool $multiple = false, string $description = '', string $class = '', array $attrs = []): void
    {
        $attributes['class'] = $this->adminFieldClass('regular-text', $class);
        if($multiple) $attributes['multiple'] = $multiple;
        $attrs = array_merge($attrs, $attributes);

        $this->formField('select', $name, $label, $attrs, $options, $description);
    }

    /**
     * -------------------------------------------------------------------------
     * Add a textarea field
     * -------------------------------------------------------------------------
     */
    public function addTextareaField(string $name, string $label, string $description = '', string $class = '', array $attrs = []): void
    {
        $class = $this->adminFieldClass('large-text', $class);
        $attributes['class'] = trim($class);
        $attributes = array_merge($attributes, $attrs);

        $this->formField('textarea', $name, $label, $attributes, [], $description);
    }

    /**
     * -------------------------------------------------------------------------
     * Add a number field
     * -------------------------------------------------------------------------
     */
    public function addNumberField(string $name, string $label, string $description = '', string $class = '', array $attrs = []): void
    {
        $class = $this->adminFieldClass('small-text', $class, false);
        $attributes['class'] = trim($class);
        $attributes = array_merge($attributes, $attrs);

        $this->formField('number', $name, $label, $attributes, [], $description);
    }

    /**
     * -------------------------------------------------------------------------
     * Add a radio field
     * -------------------------------------------------------------------------
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
     * -------------------------------------------------------------------------
     * Add a checkbox field
     * -------------------------------------------------------------------------
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
     * -------------------------------------------------------------------------
     * Add a rich text field
     * -------------------------------------------------------------------------
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
     * -------------------------------------------------------------------------
     * Get value by type
     * -------------------------------------------------------------------------
     *
     * @param string $key
     * @param string|null $type
     * @return mixed
     */
    protected function getValue(string $key, ?string $type = null)
    {
        if($type) {
            $this->valueType = $this->assertValueType($type);
        }

        if ($this->valueType !== '') {
            $this->assertValueType($this->valueType);
        }

        // Return empty string if is a new content
        if($this->valueType != 'option'
            && (!isset($this->id) || !$this->id)
        ) { return ''; }

        // Select value origin
        switch($this->valueType) {
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
     * -------------------------------------------------------------------------
     * Set value by type
     * -------------------------------------------------------------------------
     *
     * @param string $key
     * @param mixed $value
     * @return integer|bool
     */
    protected function setValue(string $key, $value)
    {
        if ($this->valueType !== '') {
            $this->assertValueType($this->valueType);
        }

        // Return empty string if is a new content
        if($this->valueType != 'option'
            && (!isset($this->id) || !$this->id)
        ) { return ''; }

        // Select value origin
        switch($this->valueType) {
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

    protected function assertFieldType(string $type): string
    {
        $type = preg_replace('/[^a-z0-9_-]/', '', $type);

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

    protected function assertFieldsType(string $type): string
    {
        $type = preg_replace('/[^a-z0-9_-]/', '', $type);

        if ($type === '' || !in_array($type, self::ALLOWED_FIELDS_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid fields type "%s". Allowed types: %s.',
                $type,
                implode(', ', self::ALLOWED_FIELDS_TYPES)
            ));
        }

        return $type;
    }

    protected function assertValueType(string $type): string
    {
        $type = preg_replace('/[^a-z0-9_-]/', '', $type);

        if ($type === '' || !in_array($type, self::ALLOWED_VALUE_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid value type "%s". Allowed types: %s.',
                $type,
                implode(', ', self::ALLOWED_VALUE_TYPES)
            ));
        }

        return $type;
    }

    protected function fieldPartialPath(string $type): string
    {
        $type = $this->assertFieldType($type);

        return Builder::PATH['FORM'] . 'partials/' . $type . '.php';
    }

    /**
     * -------------------------------------------------------------------------
     * Sanitize field value
     * -------------------------------------------------------------------------
     *
     * @param array $field
     * @param string|string[] $value
     * @return string|string[]
     */
    protected function sanitizeFieldValue(array $field, $value)
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
                    ? (string) (strpos((string) $value, '.') !== false ? (float) $value : (int) $value)
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

    protected function getPostedValue(array $field)
    {
        $name = $field['name'] ?? '';
        $type = $field['type'] ?? 'text';
        $multiple = !empty($field['multiple']);
        $hasOptions = !empty($field['options']);

        if ($type === 'checkbox' && !$hasOptions) {
            return isset($_POST[$name]) ? $_POST[$name] : '';
        }

        if (($type === 'checkbox' && $hasOptions) || ($type === 'select' && $multiple)) {
            return $_POST[$name] ?? [];
        }

        return $_POST[$name] ?? '';
    }

    private function adminFieldClass(string $baseClass, string $class = '', bool $addRegularText = true): string
    {
        $class.= ($this->valueType == 'post' && strpos($class, 'widefat') === false) ? ' widefat' : '';

        if ($addRegularText && strpos($class, 'widefat') === false && strpos($class, $baseClass) === false) {
            $class.= ' ' . $baseClass;
        }

        if (!$addRegularText && strpos($class, $baseClass) === false) {
            $class.= ' ' . $baseClass;
        }

        return trim($class);
    }

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

    protected function renderFields(string $fieldsType = ''): void
    {
        if ($fieldsType !== '') {
            $this->fieldsType = $this->assertFieldsType($fieldsType);
        }

        foreach ($this->fields as $field) {
            $this->renderField($field);
        }
    }

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

    private function formatAttributes(array $arr_attributes): string
    {
        $attributes = '';

        foreach($arr_attributes as $key => $val) {
            $key = preg_replace('/[^a-z0-9_:-]/i', '', (string) $key);

            if ($key === '') {
                continue;
            }

            if($val === true) {
                $attributes.= ' '. $key;
                continue;
            }

            $attributes.= ' '. $key .'="'. esc_attr((string) $val) .'"';
        }

        return $attributes;
    }

    protected function field()
    {
        $this->renderFields();
    }
}
