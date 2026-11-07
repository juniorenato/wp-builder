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
use WPB\Notices;

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
        'image',
        'repeater',
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
     * An invalid field type is ignored and reported in the WordPress admin.
     */
    public function formField(
        string $type,
        string $name,
        string $label = '',
        array $attributes = [],
        array $options = [],
        string $description = ''
    ): void {
        $this->registerDefinedField($this->defineFormField(
            $type,
            $name,
            $label,
            $attributes,
            $options,
            $description
        ));
    }

    /**
     * Builds a field configuration without registering it.
     *
     * @since 0.9.0
     *
     * @param string               $type        Field type.
     * @param string               $name        Field name.
     * @param string               $label       Field label.
     * @param array<string, mixed> $attributes  HTML attributes.
     * @param array<mixed, mixed>  $options     Field options or editor settings.
     * @param string               $description Field description.
     *
     * @return array<string, mixed> Field configuration.
     *
     * @throws InvalidArgumentException If the field type is not allowed.
     */
    public function defineFormField(
        string $type,
        string $name,
        string $label = '',
        array $attributes = [],
        array $options = [],
        string $description = ''
    ): array {
        $type = $this->assertFieldType($type);

        if ($type === '') {
            return [
                'type'        => '',
                'name'        => '',
                'val'         => '',
                'attributes'  => '',
                'multiple'    => false,
                'options'     => [],
                'label'       => '',
                'description' => '',
            ];
        }

        if ($type === 'repeater') {
            throw new InvalidArgumentException('Use repeater() to add a repeater field.');
        }

        if ($description) {
            $attributes['aria-describedby'] = $name . '-description';
        }

        $multiple = array_key_exists('multiple', $attributes);
        if ($multiple) {
            unset($attributes['multiple']);
        }

        return [
            'type'        => $type,
            'name'        => $name,
            'val'         => '',
            'attributes'  => $this->formatAttributes($attributes),
            'multiple'    => $multiple,
            'options'     => $options,
            'label'       => $label,
            'description' => $description,
        ];
    }

    /**
     * Stores a field configuration.
     *
     * @since 0.9.0
     *
     * @param array<string, mixed> $field Field configuration.
     */
    protected function registerDefinedField(array $field): void
    {
        $name = (string) ($field['name'] ?? '');

        if ($name === '' || ($field['type'] ?? '') === '') {
            return;
        }

        $this->fields[$name] = $field;
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
        $this->registerDefinedField($this->defineTextField($name, $label, $description, $class, $attrs));
    }

    /**
     * Builds a text field configuration.
     *
     * @since 0.9.0
     *
     * @param string               $name        Field name.
     * @param string               $label       Field label.
     * @param string               $description Field description.
     * @param string               $class       Extra CSS classes.
     * @param array<string, mixed> $attrs       Extra HTML attributes.
     *
     * @return array<string, mixed> Field configuration.
     */
    public function defineTextField(string $name, string $label, string $description = '', string $class = '', array $attrs = []): array
    {
        $attributes['class'] = $this->adminFieldClass('regular-text', $class);
        $attributes = array_merge($attributes, $attrs);

        return $this->defineFormField('text', $name, $label, $attributes, [], $description);
    }

    /**
     * Adds a select field.
     *
     * @since 0.2.0
     *
     * @param string                   $name        Field name.
     * @param string                   $label       Field label.
     * @param array<string|int, mixed> $options     Select options.
     * @param bool                     $multiple    Whether multiple values are allowed.
     * @param string                   $description Field description.
     * @param string                   $class       Extra CSS classes.
     * @param array<string, mixed>     $attrs       Extra HTML attributes.
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
        $this->registerDefinedField($this->defineSelectField(
            $name,
            $label,
            $options,
            $multiple,
            $description,
            $class,
            $attrs
        ));
    }

    /**
     * Builds a select field configuration.
     *
     * @since 0.9.0
     *
     * @param string                   $name        Field name.
     * @param string                   $label       Field label.
     * @param array<string|int, mixed> $options     Select options.
     * @param bool                     $multiple    Whether multiple values are allowed.
     * @param string                   $description Field description.
     * @param string                   $class       Extra CSS classes.
     * @param array<string, mixed>     $attrs       Extra HTML attributes.
     *
     * @return array<string, mixed> Field configuration.
     */
    public function defineSelectField(
        string $name,
        string $label,
        array $options,
        bool $multiple = false,
        string $description = '',
        string $class = '',
        array $attrs = []
    ): array {
        $attributes['class'] = $this->adminFieldClass('regular-text', $class);
        if ($multiple) {
            $attributes['multiple'] = $multiple;
        }
        $attrs = array_merge($attrs, $attributes);

        return $this->defineFormField('select', $name, $label, $attrs, $options, $description);
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
        $this->registerDefinedField($this->defineTextareaField($name, $label, $description, $class, $attrs));
    }

    /**
     * Builds a textarea field configuration.
     *
     * @since 0.9.0
     *
     * @param string               $name        Field name.
     * @param string               $label       Field label.
     * @param string               $description Field description.
     * @param string               $class       Extra CSS classes.
     * @param array<string, mixed> $attrs       Extra HTML attributes.
     *
     * @return array<string, mixed> Field configuration.
     */
    public function defineTextareaField(string $name, string $label, string $description = '', string $class = '', array $attrs = []): array
    {
        $class = $this->adminFieldClass('large-text', $class);
        $attributes['class'] = trim($class);
        $attributes = array_merge($attributes, $attrs);

        return $this->defineFormField('textarea', $name, $label, $attributes, [], $description);
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
        $this->registerDefinedField($this->defineNumberField($name, $label, $description, $class, $attrs));
    }

    /**
     * Builds a number field configuration.
     *
     * @since 0.9.0
     *
     * @param string               $name        Field name.
     * @param string               $label       Field label.
     * @param string               $description Field description.
     * @param string               $class       Extra CSS classes.
     * @param array<string, mixed> $attrs       Extra HTML attributes.
     *
     * @return array<string, mixed> Field configuration.
     */
    public function defineNumberField(string $name, string $label, string $description = '', string $class = '', array $attrs = []): array
    {
        $class = $this->adminFieldClass('small-text', $class, false);
        $attributes['class'] = trim($class);
        $attributes = array_merge($attributes, $attrs);

        return $this->defineFormField('number', $name, $label, $attributes, [], $description);
    }

    /**
     * Adds an image field stored as a media library attachment ID.
     *
     * The control opens the WordPress media modal. One image is stored as an
     * attachment ID, or as an empty string when none is chosen. Several images
     * are stored as a list of attachment IDs.
     *
     * @since 0.9.0
     *
     * @param string               $name        Field name.
     * @param string               $label       Field label.
     * @param string               $description Field description.
     * @param string               $class       Extra CSS classes.
     * @param array<string, mixed> $attrs       Extra HTML attributes.
     * @param bool                 $multiple    Whether several images are allowed.
     */
    public function addImageField(
        string $name,
        string $label,
        string $description = '',
        string $class = '',
        array $attrs = [],
        bool $multiple = false
    ): void {
        $this->registerDefinedField($this->defineImageField(
            $name,
            $label,
            $description,
            $class,
            $attrs,
            $multiple
        ));
    }

    /**
     * Builds an image field configuration.
     *
     * @since 0.9.0
     *
     * @param string               $name        Field name.
     * @param string               $label       Field label.
     * @param string               $description Field description.
     * @param string               $class       Extra CSS classes.
     * @param array<string, mixed> $attrs       Extra HTML attributes.
     * @param bool                 $multiple    Whether several images are allowed.
     *
     * @return array<string, mixed> Field configuration.
     */
    public function defineImageField(
        string $name,
        string $label,
        string $description = '',
        string $class = '',
        array $attrs = [],
        bool $multiple = false
    ): array {
        $extraClass = trim($class . ' ' . (string) ($attrs['class'] ?? ''));
        unset($attrs['class']);

        if (array_key_exists('multiple', $attrs)) {
            $multiple = (bool) $attrs['multiple'];
            unset($attrs['multiple']);
        }

        $attributes = array_merge($attrs, [
            'class' => trim('wpb-image-field' . ($multiple ? ' wpb-image-field--multiple' : '') . ' ' . $extraClass),
        ]);

        if ($multiple) {
            $attributes['multiple'] = true;
        }

        return $this->defineFormField('image', $name, $label, $attributes, [], $description);
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
        $this->registerDefinedField($this->defineRadioField($name, $label, $options, $description, $class, $attrs));
    }

    /**
     * Builds a radio field configuration.
     *
     * @since 0.9.0
     *
     * @param string                   $name        Field name.
     * @param string                   $label       Field label.
     * @param array<string|int, mixed> $options     Radio options.
     * @param string                   $description Field description.
     * @param string                   $class       Extra CSS classes.
     * @param array<string, mixed>     $attrs       Extra HTML attributes.
     *
     * @return array<string, mixed> Field configuration.
     */
    public function defineRadioField(
        string $name,
        string $label,
        array $options,
        string $description = '',
        string $class = '',
        array $attrs = []
    ): array {
        $attributes = [];

        if ($class !== '') {
            $attributes['class'] = trim($class);
        }

        $attributes = array_merge($attributes, $attrs);

        return $this->defineFormField('radio', $name, $label, $attributes, $options, $description);
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
        $this->registerDefinedField($this->defineCheckboxField(
            $name,
            $label,
            $options,
            $multiple,
            $description,
            $class,
            $attrs
        ));
    }

    /**
     * Builds a checkbox field configuration.
     *
     * @since 0.9.0
     *
     * @param string                   $name        Field name.
     * @param string                   $label       Field label.
     * @param array<string|int, mixed> $options     Checkbox options.
     * @param bool                     $multiple    Whether multiple values are allowed.
     * @param string                   $description Field description.
     * @param string                   $class       Extra CSS classes.
     * @param array<string, mixed>     $attrs       Extra HTML attributes.
     *
     * @return array<string, mixed> Field configuration.
     */
    public function defineCheckboxField(
        string $name,
        string $label,
        array $options = [],
        bool $multiple = true,
        string $description = '',
        string $class = '',
        array $attrs = []
    ): array {
        $attributes = [];

        if ($class !== '') {
            $attributes['class'] = trim($class);
        }

        if ($options !== [] && $multiple) {
            $attributes['multiple'] = true;
        }

        $attributes = array_merge($attributes, $attrs);

        return $this->defineFormField('checkbox', $name, $label, $attributes, $options, $description);
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
        $this->registerDefinedField($this->defineRichTextField(
            $name,
            $label,
            $editorSettings,
            $description,
            $attrs
        ));
    }

    /**
     * Builds a rich text field configuration.
     *
     * @since 0.9.0
     *
     * @param string               $name           Field name.
     * @param string               $label          Field label.
     * @param array<string, mixed> $editorSettings `wp_editor()` settings.
     * @param string               $description    Field description.
     * @param array<string, mixed> $attrs          Extra HTML attributes.
     *
     * @return array<string, mixed> Field configuration.
     *
     * @see https://developer.wordpress.org/reference/functions/wp_editor/
     */
    public function defineRichTextField(
        string $name,
        string $label,
        array $editorSettings = [],
        string $description = '',
        array $attrs = []
    ): array {
        $attributes = array_merge($attrs, [
            'class' => 'wp-editor-wrap widefat',
        ]);

        return $this->defineFormField('rich-text', $name, $label, $attributes, $editorSettings, $description);
    }

    /**
     * Adds a repeater stored in one meta key or option.
     *
     * @since 0.9.0
     *
     * @param string $name        Field name.
     * @param string $label       Field label.
     * @param string $description Field description.
     *
     * @return Repeater Repeater builder.
     *
     * @throws InvalidArgumentException If the name is invalid or already used.
     */
    public function repeater(string $name, string $label, string $description = ''): Repeater
    {
        Repeater::assertName($name);

        if (isset($this->fields[$name])) {
            throw new InvalidArgumentException(sprintf(
                'Field name "%s" is already used.',
                $name
            ));
        }

        $repeater = new Repeater($this, null, $name, $label, $description);

        $this->fields[$name] = [
            'type'        => 'repeater',
            'name'        => $name,
            'val'         => '',
            'attributes'  => '',
            'multiple'    => false,
            'options'     => [],
            'label'       => $label,
            'description' => $description,
            'repeater'    => $repeater,
        ];
        $this->metaFields[] = $name;

        return $repeater;
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
     * An invalid value type is reported in the WordPress admin and this method returns an empty string.
     *
     * @see https://developer.wordpress.org/reference/functions/get_option/
     * @see https://developer.wordpress.org/reference/functions/get_post_meta/
     * @see https://developer.wordpress.org/reference/functions/get_term_meta/
     * @see https://developer.wordpress.org/reference/functions/get_user_meta/
     */
    protected function getValue(string $key, ?string $type = null): mixed
    {
        if ($type) {
            $type = $this->assertValueType($type);

            if ($type === '') {
                return '';
            }

            $this->valueType = $type;
        }

        if ($this->valueType !== '' && $this->assertValueType($this->valueType) === '') {
            return '';
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
     * An invalid value type is reported in the WordPress admin and this method returns an empty string.
     *
     * @see https://developer.wordpress.org/reference/functions/update_option/
     * @see https://developer.wordpress.org/reference/functions/update_post_meta/
     * @see https://developer.wordpress.org/reference/functions/update_term_meta/
     * @see https://developer.wordpress.org/reference/functions/update_user_meta/
     */
    protected function setValue(string $key, mixed $value): int|bool|string
    {
        if ($this->valueType !== '' && $this->assertValueType($this->valueType) === '') {
            return '';
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
     * @return string Sanitized field type, or an empty string when the type is rejected.
     *
     * A rejected type is reported in the WordPress admin.
     */
    protected function assertFieldType(string $type): string
    {
        $type = preg_replace('/[^a-z0-9_-]/', '', $type) ?? '';

        if ($type === '') {
            Notices::error(sprintf(
                'Invalid field type. Allowed types: %s.',
                implode(', ', self::ALLOWED_FIELD_TYPES)
            ));

            return '';
        }

        if (!in_array($type, self::ALLOWED_FIELD_TYPES, true)) {
            Notices::error(sprintf(
                'Invalid field type "%s". Allowed types: %s.',
                $type,
                implode(', ', self::ALLOWED_FIELD_TYPES)
            ));

            return '';
        }

        $partialPath = Builder::PATH['FORM'] . 'partials/' . $type . '.php';

        if (!file_exists($partialPath)) {
            Notices::error(sprintf(
                'Field type "%s" has no partial view at "%s".',
                $type,
                $partialPath
            ));

            return '';
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
     * @return string Sanitized layout type, or an empty string when the type is rejected.
     *
     * A rejected type is reported in the WordPress admin.
     */
    protected function assertFieldsType(string $type): string
    {
        $type = preg_replace('/[^a-z0-9_-]/', '', $type) ?? '';

        if ($type === '' || !in_array($type, self::ALLOWED_FIELDS_TYPES, true)) {
            Notices::error(sprintf(
                'Invalid fields type "%s". Allowed types: %s.',
                $type,
                implode(', ', self::ALLOWED_FIELDS_TYPES)
            ));

            return '';
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
     * @return string Sanitized value type, or an empty string when the type is rejected.
     *
     * A rejected type is reported in the WordPress admin.
     */
    protected function assertValueType(string $type): string
    {
        $type = preg_replace('/[^a-z0-9_-]/', '', $type) ?? '';

        if ($type === '' || !in_array($type, self::ALLOWED_VALUE_TYPES, true)) {
            Notices::error(sprintf(
                'Invalid value type "%s". Allowed types: %s.',
                $type,
                implode(', ', self::ALLOWED_VALUE_TYPES)
            ));

            return '';
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
     * @return string Absolute partial path, or an empty string when the field type is invalid.
     */
    protected function fieldPartialPath(string $type): string
    {
        $type = $this->assertFieldType($type);

        if ($type === '') {
            return '';
        }

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
     * @return string|array Sanitized value. Repeaters return a list of rows.
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

            case 'image':
                if (!empty($field['multiple'])) {
                    return $this->sanitizeImageList($value);
                }

                return $this->sanitizeImageValue($value);

            case 'repeater':
                return $this->sanitizeRepeater($this->repeaterSchema($field), $value);

            case 'hidden':
            case 'text':
            default:
                return sanitize_text_field((string) $value);
        }
    }

    /**
     * Sanitizes a repeater into a list of known fields.
     *
     * @since 0.9.0
     *
     * @param array<string, mixed> $field Repeater schema.
     * @param mixed                $value Submitted rows.
     *
     * @return list<array<string, mixed>> Sanitized rows.
     */
    private function sanitizeRepeater(array $field, mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $index => $row) {
            if ($index === '__present' || $index === '__i__' || !is_array($row)) {
                continue;
            }

            $clean = [];

            foreach ($field['fields'] ?? [] as $child) {
                if (!is_array($child) || !isset($child['name'])) {
                    continue;
                }

                $childName = (string) $child['name'];
                $submitted = $row[$childName] ?? $this->emptySubmittedValue($child);
                $clean[$childName] = $this->sanitizeFieldValue($child, $submitted);
            }

            $items[] = $clean;
        }

        $max = (int) ($field['max'] ?? 0);

        if ($max > 0) {
            $items = array_slice($items, 0, $max);
        }

        return array_values($items);
    }

    /**
     * Returns the live repeater schema when the builder is still attached.
     *
     * @since 0.9.0
     *
     * @param array<string, mixed> $field Field configuration.
     *
     * @return array<string, mixed> Repeater schema.
     */
    private function repeaterSchema(array $field): array
    {
        $repeater = $field['repeater'] ?? null;

        if ($repeater instanceof Repeater) {
            return $repeater->schema();
        }

        return $field;
    }

    /**
     * Returns the value used when a repeater subfield was not submitted.
     *
     * @since 0.9.0
     *
     * @param array<string, mixed> $field Field configuration.
     *
     * @return mixed Empty value for the field type.
     */
    private function emptySubmittedValue(array $field): mixed
    {
        $type = $field['type'] ?? 'text';

        if ($type === 'repeater') {
            return [];
        }

        if (
            ($type === 'checkbox' && !empty($field['options']))
            || ($type === 'select' && !empty($field['multiple']))
            || ($type === 'image' && !empty($field['multiple']))
        ) {
            return [];
        }

        return '';
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

        if ($type === 'repeater') {
            $value = wp_unslash($_POST[$name] ?? []);

            return is_array($value) ? $value : [];
        }

        if ($type === 'checkbox' && !$hasOptions) {
            $value = isset($_POST[$name]) ? wp_unslash($_POST[$name]) : '';
        } elseif (($type === 'checkbox' && $hasOptions) || ($type === 'select' && $multiple) || ($type === 'image' && $multiple)) {
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
     * @return string Absolute view path, or an empty string when the view or field type is missing.
     */
    protected function fieldViewPath(string $type): string
    {
        if ($this->assertFieldType($type) === '') {
            return '';
        }

        $path = Builder::PATH['FORM'] . 'get-field.php';

        if (!file_exists($path)) {
            Notices::error(sprintf(
                'Field view "%s" was not found.',
                $path
            ));

            return '';
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
     * An invalid field type is reported in the WordPress admin and skipped.
     */
    protected function renderField(array $field): void
    {
        $fieldType = $this->assertFieldType($field['type'] ?? 'text');
        $view = $fieldType === '' ? '' : $this->fieldViewPath($fieldType);

        if ($fieldType === '' || $view === '') {
            return;
        }

        $this->field = $field;
        $this->field['type'] = $fieldType;

        if (($field['val'] ?? '') === '') {
            $this->field['val'] = $this->getValue($this->field['name']);
        }

        require $view;
    }

    /**
     * Renders one repeater subfield.
     *
     * @since 0.9.0
     *
     * @param array<string, mixed> $field Field configuration.
     */
    protected function renderControl(array $field): void
    {
        $previous = $this->field;
        $this->field = $field;
        $partialPath = ($field['type'] ?? '') === 'repeater'
            ? ''
            : $this->fieldPartialPath((string) ($field['type'] ?? 'text'));

        require Builder::PATH['FORM'] . 'partials/control.php';

        $this->field = $previous;
    }

    /**
     * Renders one repeater row.
     *
     * @since 0.9.0
     *
     * @param Repeater                 $repeater Repeater level that owns the row.
     * @param array<string, mixed>     $schema   Repeater schema.
     * @param array<string, mixed>     $item     Saved row.
     * @param string                   $rowName  Request name for this row.
     */
    protected function renderRepeaterItem(Repeater $repeater, array $schema, array $item, string $rowName): void
    {
        require Builder::PATH['FORM'] . 'partials/repeater-item.php';
    }

    /**
     * Prints the repeater script and styles.
     *
     * Styles are printed inline when the admin header has already rendered.
     *
     * @since 0.9.0
     *
     * @see https://developer.wordpress.org/reference/functions/wp_enqueue_script/
     */
    protected function enqueueAdminRepeater(): void
    {
        static $done = false;

        if ($done || !function_exists('did_action') || !function_exists('wp_enqueue_script')) {
            return;
        }

        $done = true;

        $root = dirname(__DIR__, 2);
        $cssPath = $root . '/assets/css/admin-repeater.css';
        $jsPath = $root . '/assets/js/admin-repeater.js';
        $cssUrl = is_readable($cssPath) ? Builder::url($cssPath) : '';
        $jsUrl = is_readable($jsPath) ? Builder::url($jsPath) : '';

        if ($cssUrl !== '' && !did_action('admin_print_styles')) {
            wp_enqueue_style(
                'wpb-admin-repeater',
                $cssUrl,
                [],
                (string) filemtime($cssPath)
            );
        } elseif (is_readable($cssPath)) {
            $css = file_get_contents($cssPath);

            if (is_string($css)) {
                echo '<style id="wpb-admin-repeater-css">' . $css . '</style>';
            }
        }

        if (!wp_script_is('wpb-admin-repeater', 'registered')) {
            wp_register_script(
                'wpb-admin-repeater',
                $jsUrl !== '' ? $jsUrl : false,
                [],
                is_readable($jsPath) ? (string) filemtime($jsPath) : false,
                true
            );
        }

        if ($jsUrl === '' && is_readable($jsPath)) {
            $script = file_get_contents($jsPath);

            if (is_string($script)) {
                wp_add_inline_script('wpb-admin-repeater', $script);
            }
        }

        wp_enqueue_script('wpb-admin-repeater');
    }

    /**
     * Prints the media modal script and the image field styles.
     *
     * Styles are printed inline when the admin header has already rendered.
     *
     * @since 0.9.0
     *
     * @see https://developer.wordpress.org/reference/functions/wp_enqueue_media/
     */
    protected function enqueueAdminImage(): void
    {
        static $done = false;

        if ($done || !function_exists('did_action') || !function_exists('wp_enqueue_script')) {
            return;
        }

        $done = true;

        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }

        $root = dirname(__DIR__, 2);
        $cssPath = $root . '/assets/css/admin-image.css';
        $jsPath = $root . '/assets/js/admin-image.js';
        $cssUrl = is_readable($cssPath) ? Builder::url($cssPath) : '';
        $jsUrl = is_readable($jsPath) ? Builder::url($jsPath) : '';

        if ($cssUrl !== '' && !did_action('admin_print_styles')) {
            wp_enqueue_style(
                'wpb-admin-image',
                $cssUrl,
                [],
                (string) filemtime($cssPath)
            );
        } elseif (is_readable($cssPath)) {
            $css = file_get_contents($cssPath);

            if (is_string($css)) {
                echo '<style id="wpb-admin-image-css">' . $css . '</style>';
            }
        }

        if (!wp_script_is('wpb-admin-image', 'registered')) {
            wp_register_script(
                'wpb-admin-image',
                $jsUrl !== '' ? $jsUrl : false,
                ['media-editor'],
                is_readable($jsPath) ? (string) filemtime($jsPath) : false,
                true
            );
        }

        if ($jsUrl === '' && is_readable($jsPath)) {
            $script = file_get_contents($jsPath);

            if (is_string($script)) {
                wp_add_inline_script('wpb-admin-image', $script);
            }
        }

        wp_enqueue_script('wpb-admin-image');
    }

    /**
     * Keeps a positive image attachment ID and clears every other value.
     *
     * @since 0.9.0
     *
     * @param mixed $value Submitted value.
     *
     * @return string Attachment ID, or an empty string.
     */
    private function sanitizeImageValue(mixed $value): string
    {
        $id = absint(is_array($value) ? 0 : $value);

        if ($id < 1) {
            return '';
        }

        if (function_exists('wp_attachment_is_image') && !wp_attachment_is_image($id)) {
            return '';
        }

        return (string) $id;
    }

    /**
     * Keeps unique image attachment IDs in the submitted order.
     *
     * @since 0.9.0
     *
     * @param mixed $value Submitted value.
     *
     * @return list<string> Attachment IDs.
     */
    private function sanitizeImageList(mixed $value): array
    {
        if ($value === '' || $value === null || $value === false) {
            return [];
        }

        $items = is_array($value) ? $value : [$value];
        $ids = [];

        foreach ($items as $item) {
            $id = $this->sanitizeImageValue($item);

            if ($id === '' || in_array($id, $ids, true)) {
                continue;
            }

            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * Renders all registered fields.
     *
     * @since 0.2.0
     *
     * @param string $fieldsType Optional layout type override.
     *
     * An invalid layout type is reported in the WordPress admin and rendering stops.
     */
    protected function renderFields(string $fieldsType = ''): void
    {
        if ($fieldsType !== '') {
            $fieldsType = $this->assertFieldsType($fieldsType);

            if ($fieldsType === '') {
                return;
            }

            $this->fieldsType = $fieldsType;
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
