<?php

namespace WPB\Forms;

/**
 * -----------------------------------------------------------------------------
 * Taxonomy Custom Fields Builder
 * -----------------------------------------------------------------------------
 *
 * @since v0.2.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 * @package juniorenato/wp-builder
 */
trait TaxonomyCustomField
{
    /**
     * -------------------------------------------------------------------------
     * Set fields in the forms
     * -------------------------------------------------------------------------
     *
     * Actions:
     * - Add fields to a new term form
     * - Add fields to edit a term form
     * - Save at create action
     * - Save at edit action
     *
     * @return void
     */
    protected function setTermFields(): void
    {
        // Add fields to new term form
        add_action($this->taxonomy .'_add_form_fields', [$this, 'createTermFormFields']);

        // Add fields to edit term form
        add_action($this->taxonomy .'_edit_form_fields', [$this, 'editTermFormFields'], 10, 2);

        // Save at creation action
        add_action('created_'. $this->taxonomy, [$this, 'createTerm'], 10, 3);

        // Save at edit action
        add_action('edited_'. $this->taxonomy, [$this, 'editTerm'], 10, 2);
    }

    public function createTermFormFields()
    {
        $this->fieldsType = 'term';

        $this->field();
    }

    public function editTermFormFields($term)
    {
        $this->id = $term->term_id;
        $this->fieldsType = 'table';

        $this->field();
    }

    public function createTerm($term_id)
    {
        if (!current_user_can('manage_terms', $this->taxonomy)) {
            return;
        }

        check_admin_referer('add-' . $this->taxonomy, '_wpnonce');

        $this->id = $term_id;
        $this->saveFieldsFromPost();
    }

    public function editTerm($term_id)
    {
        if (!current_user_can('edit_term', $term_id)) {
            return;
        }

        check_admin_referer('update-tag_' . $term_id);

        $this->id = $term_id;
        $this->saveFieldsFromPost();
    }
}
