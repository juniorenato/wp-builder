<?php

/**
 * Taxonomy custom field helpers.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Forms
 */

namespace WPB\Forms;

/**
 * Adds and saves custom fields on taxonomy term screens.
 *
 * @since  0.2.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 *
 * @see https://developer.wordpress.org/reference/hooks/taxonomy_add_form_fields/
 * @see https://developer.wordpress.org/reference/hooks/taxonomy_edit_form_fields/
 */
trait TaxonomyCustomField
{
    /**
     * Hooks term form rendering and save callbacks.
     *
     * @since 0.2.0
     *
     * @see https://developer.wordpress.org/reference/hooks/taxonomy_add_form_fields/
     * @see https://developer.wordpress.org/reference/hooks/taxonomy_edit_form_fields/
     * @see https://developer.wordpress.org/reference/hooks/created_taxonomy/
     * @see https://developer.wordpress.org/reference/hooks/edited_taxonomy/
     */
    protected function setTermFields(): void
    {
        add_action($this->taxonomy . '_add_form_fields', [$this, 'createTermFormFields']);
        add_action($this->taxonomy . '_edit_form_fields', [$this, 'editTermFormFields'], 10, 2);
        add_action('created_' . $this->taxonomy, [$this, 'createTerm'], 10, 3);
        add_action('edited_' . $this->taxonomy, [$this, 'editTerm'], 10, 2);
    }

    /**
     * Renders fields on the add-term form.
     *
     * @since 0.2.0
     *
     * @see https://developer.wordpress.org/reference/hooks/taxonomy_add_form_fields/
     */
    public function createTermFormFields(): void
    {
        $this->fieldsType = 'term';

        $this->field();
    }

    /**
     * Renders fields on the edit-term form.
     *
     * @since 0.2.0
     *
     * @param \WP_Term $term Term being edited.
     *
     * @see https://developer.wordpress.org/reference/hooks/taxonomy_edit_form_fields/
     */
    public function editTermFormFields($term): void
    {
        $this->id = $term->term_id;
        $this->fieldsType = 'table';

        $this->field();
    }

    /**
     * Saves custom fields when a term is created.
     *
     * @since 0.2.0
     *
     * @param int $term_id Created term ID.
     *
     * @see https://developer.wordpress.org/reference/hooks/created_taxonomy/
     * @see https://developer.wordpress.org/reference/functions/current_user_can/
     * @see https://developer.wordpress.org/reference/functions/check_admin_referer/
     */
    public function createTerm($term_id): void
    {
        if (!current_user_can('manage_terms', $this->taxonomy)) {
            return;
        }

        check_admin_referer('add-' . $this->taxonomy, '_wpnonce');

        $this->id = $term_id;
        $this->saveFieldsFromPost();
    }

    /**
     * Saves custom fields when a term is updated.
     *
     * @since 0.2.0
     *
     * @param int $term_id Updated term ID.
     *
     * @see https://developer.wordpress.org/reference/hooks/edited_taxonomy/
     * @see https://developer.wordpress.org/reference/functions/current_user_can/
     * @see https://developer.wordpress.org/reference/functions/check_admin_referer/
     */
    public function editTerm($term_id): void
    {
        if (!current_user_can('edit_term', $term_id)) {
            return;
        }

        check_admin_referer('update-tag_' . $term_id);

        $this->id = $term_id;
        $this->saveFieldsFromPost();
    }
}
