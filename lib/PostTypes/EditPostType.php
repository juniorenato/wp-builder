<?php

namespace WPB\PostTypes;

/**
 * -----------------------------------------------------------------------------
 * Edit Post Type
 * -----------------------------------------------------------------------------
 *
 * @since v0.4.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 * @package juniorenato/wp-builder
 */
class EditPostType extends PostType
{
    public function __construct(?string $postType = null, ?array $args = null)
    {
        $this->valueType = 'post';

        $this->init();

        if($postType) $this->setPostType($postType);

        if($postType && $args) {
            $this->args((array) $args);
            $this->edit();
        }

        return $this;
    }

    /**
     * -------------------------------------------------------------------------
     * Reset the class
     * -------------------------------------------------------------------------
     *
     * @return PostType
     */
    protected function init(): PostType
    {
        $this->postType = '';
        $this->labels = [];
        $this->rewrite = [];
        $this->taxonomies = [];
        $this->supports = [];
        $this->args = [];

        return $this;
    }

    public function setPostType(string $postType): EditPostType
    {
        $postType = (array) get_post_type_object($postType);

        if($postType) {
            $this->postType = $postType['name'];
            $postType['labels'] = (array) $postType['labels'];

            foreach($postType as $k => $val) {
                if(in_array($k, self::ARGS)) $this->args($k, $val);
            }
        }

        return $this;
    }

    public function edit(?string $postType = null, ?array $args = null)
    {
        if($postType) $this->setPostType($postType);
        if($args) $this->args($args);

        if(1 == 0
            || $this->metaBoxes
            || $this->metaFields
        ) { $this->registerMetaBoxes(); }

        // WordPress - Register Post Type Args
        add_filter('register_'. $this->postType .'_post_type_args', [$this, 'registerArgs']);
    }

    public function registerArgs($args)
    {
        $args = array_merge($args, $this->args);

        return $args;
    }
}
