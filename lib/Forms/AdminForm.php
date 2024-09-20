<?php

namespace WPB\Forms;

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
        $this->field = [
            'type' => 'hidden',
            'name' => '_meta_noncename',
            'val' => wp_create_nonce(__FILE__),
        ];

        require Builder::PATH['FORM'] . 'hidden.php';
    }

    public function savePost($post_id, $post)
    {
        // Check `noncename`
        if (1 == 0
            || !isset($_POST['_meta_noncename'])
            || !current_user_can('edit_post', $post_id)
            || (isset($_POST['_meta_noncename']) && !wp_verify_nonce($_POST['_meta_noncename'], __FILE__))
        ) { return $post_id; }

        // Create a meta variable
        $meta = [];
        foreach($this->metaFields as $field) {
            if(1 == 1
                && $field !== '_meta_noncename'
            ) { $meta[$field] = $_POST[$field] ?? ''; }
        }

        // Insert all metadata
        foreach($meta as $k => $v) {
            // Don't save data twice
            if($post->post_type == 'revision') return;

            if(get_post_meta($post_id, $k, true)) {
                update_post_meta($post_id, $k, $v);
            } else {
                add_post_meta($post_id, $k, $v);
            }
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
        // Prepare description
        if($description) $attributes['aria-describedby'] = $name .'-description';

        $this->fields[$name] = [
            'type'        => $type,
            'name'        => $name,
            'val'         => '',
            'attributes'  => $this->formatAttributes($attributes),
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
     * @return void
     */
    public function addTextField(string $name, string $label, string $description = '', string $class = ''): void
    {
        $class.= ($this->valueType == 'post' && strpos($class, 'widefat') === false) ? ' widefat' : '';
        $class.= (strpos($class, 'widefat') === false) ? ' regular-text' : '';

        $attributes['class'] = trim($class);

        $this->formField('text', $name, $label, $attributes, [], $description);
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
        if($type) $this->valueType = $type;

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

    private function formatAttributes(array $arr_attributes): string
    {
        $attributes = '';
        foreach($arr_attributes as $key => $val) {
            if($val === true) {
                $attributes.= ' '. $key;
                continue;
            }

            $attributes.= ' '. $key .'="'. $val .'"';
        }

        return $attributes;
    }

    protected function field()
    {
        foreach($this->fields as $name => $this->field) {
            $this->field['val'] = $this->getValue($name);
            require Builder::PATH['FIELD'] . $this->field['type'] .'.php';
        }
    }
}
