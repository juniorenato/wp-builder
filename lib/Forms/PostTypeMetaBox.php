<?php

namespace WPB\Forms;

use WPB\MetaBoxes\MetaBox;

trait PostTypeMetaBox
{
    protected array $metaBoxes = [];

    /**
     * -------------------------------------------------------------------------
     * Add a Meta Box to Post Type
     * -------------------------------------------------------------------------
     *
     * @param MetaBox $metaBox
     * @return void
     */
    public function metaBox($metaBox): void
    {
        if(is_array($metaBox)) {
            $this->metaBoxes = $metaBox;
        }

        else {
            $this->metaBoxes[] = $metaBox;
        }
    }

    public function registerMetaBoxes(): void
    {
        if(!$this->metaBoxes) {
            $mb = new MetaBox();
            $mb->title($this->labels['attributes']);
            $mb->fields = $this->fields;

            $this->metaBoxes[] = $mb;
        }

        foreach($this->metaBoxes as $metaBox) {

            $metaBox->valueType = 'post';
            $metaBox->screen($this->postType);
            if(!$metaBox->metaBox) $metaBox->metaBoxId($metaBox->title);
            $this->args['register_meta_box_cb'][] = $metaBox->metaBox;

            $this->metaFields = array_merge(
                $this->metaFields,
                $metaBox->metaFields
            );

            $metaBox->register();
        }

        add_action('init', [$this, 'setPostTypeMetaBoxes']);
    }

    public function setPostTypeMetaBoxes(): void
    {
        // Add noncename field
        add_action('edit_form_after_title', [$this, 'addNonceName']);

        // Add save actions
        add_action('save_post_'. $this->postType, [$this, 'savePost'], 10 , 2);
    }
}
