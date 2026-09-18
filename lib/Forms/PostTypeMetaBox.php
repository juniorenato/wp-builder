<?php

/**
 * Post type meta box helpers.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Forms
 */

namespace WPB\Forms;

use WPB\Builder;
use WPB\MetaBoxes\MetaBox;

/**
 * Attaches meta boxes and save callbacks to a post type.
 *
 * @since  0.2.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
trait PostTypeMetaBox
{
    /**
     * Meta boxes attached to the post type.
     *
     * @since 0.2.0
     *
     * @var list<MetaBox>
     */
    protected array $metaBoxes = [];

    /**
     * Adds one or more meta boxes to the post type.
     *
     * @since 0.2.0
     *
     * @param MetaBox|list<MetaBox> $metaBox Meta box instance or list of instances.
     */
    public function metaBox(MetaBox|array $metaBox): void
    {
        if (is_array($metaBox)) {
            $this->metaBoxes = $metaBox;
        } else {
            $this->metaBoxes[] = $metaBox;
        }
    }

    /**
     * Registers attached meta boxes and their save hooks.
     *
     * @since 0.2.0
     */
    public function registerMetaBoxes(): void
    {
        if (!$this->metaBoxes) {
            $mb = new MetaBox();
            $mb->title($this->labels['attributes']);
            $mb->fields = $this->fields;
            $mb->metaFields = $this->metaFields;

            $this->metaBoxes[] = $mb;
        }

        foreach ($this->metaBoxes as $metaBox) {
            $metaBox->valueType = 'post';
            $metaBox->screen($this->postType);

            if (!$metaBox->metaBox) {
                $metaBox->metaBoxId($this->postType . '_' . $metaBox->title);
            }

            if (
                isset($this->args['register_meta_box_cb'])
                && !empty($this->args['register_meta_box_cb'])
            ) {
                $this->args['register_meta_box_cb'] = [
                    $this->args['register_meta_box_cb']
                ];
                $this->args['register_meta_box_cb'][] = [$metaBox, 'addMetaBoxes'];
            } else {
                $this->args['register_meta_box_cb'] = [$metaBox, 'addMetaBoxes'];
            }

            $this->metaFields = array_merge(
                $this->metaFields,
                $metaBox->metaFields
            );

            $metaBox->register();
        }

        Builder::onInit([$this, 'setPostTypeMetaBoxes']);
    }

    /**
     * Hooks nonce output and post-save handling for the post type.
     *
     * @since 0.2.0
     */
    public function setPostTypeMetaBoxes(): void
    {
        add_action('edit_form_after_title', [$this, 'addNonceName']);
        add_action('save_post_' . $this->postType, [$this, 'savePost'], 10, 2);
    }
}
