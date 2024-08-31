<?php

namespace WPB\Taxonomy;

trait CustomFields
{
    private const FIELDS_PATH = __DIR__ .'../../../views/taxonomy/custom-fields/';

    protected array $customFields = [];

    public function addCustomField(string $type, string $name, string $label): void
    {
        $this->customFields[] = [
            'type' => $type,
            'name' => $name,
            'label' => $label,
        ];
    }

    protected function setCustomFields()
    {
        // Add fields to new term form
        add_action($this->prefix . $this->name .'_add_form_fields', function()
        {
            global $hswp;

            foreach($this->customFields as $hswp) {
                include self::FIELDS_PATH . $hswp['type'] .'.php';
            }
        });

        // Add fields to edit term form
        add_action($this->prefix . $this->name .'_edit_form_fields', function($term)
        {
            global $hswp;
            global $term_id;

            $term_id = $term->term_id;

            foreach($this->customFields as $hswp) {
                include self::FIELDS_PATH .'table/'. $hswp['type'] .'.php';
            }
        }, 10, 2);

        // Save at creation action
        add_action('created_'. $this->prefix . $this->name, function($term_id) {
            foreach($this->customFields as $field) {
                update_term_meta($term_id, $field['name'], $_POST[$field['name']]);
            }
        });

        // Save at edit action
        add_action('edited_'. $this->prefix . $this->name, function($term_id) {

            foreach($this->customFields as $field) {
                update_term_meta($term_id, $field['name'], $_POST[$field['name']]);
            }
        });
    }
}
