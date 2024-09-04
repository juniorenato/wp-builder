<?php

namespace WPB\PostTypes;

class RemovePostType extends EditPostType
{
    public function __construct(?string $postType)
    {
        if($postType) $this->remove($postType);
    }

    public function remove(string $postType)
    {
        $this->setPostType($postType);

        $this->args([
            'capability_type'     => 'block',
            'public'              => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'show_ui'             => false,
            'show_in_menu'        => false,
            'show_in_nav_menus'   => false,
            'show_in_admin_bar'   => false,
            'can_export'          => false,
        ]);

        // WordPress - Register Post Type Args
        add_filter('register_'. $this->postType .'_post_type_args', [$this, 'registerArgs']);
    }
}
