<?php

/**
 * Hides an existing post type from the admin and public queries.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\PostTypes
 */

namespace WPB\PostTypes;

/**
 * Removes a post type from menus, search, and public queries.
 *
 * @since  0.4.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class RemovePostType extends EditPostType
{
    /**
     * Optionally removes a post type immediately.
     *
     * @since 0.4.0
     *
     * @param string|null $postType Post type key.
     */
    public function __construct(?string $postType)
    {
        parent::__construct();

        if ($postType) {
            $this->remove($postType);
        }
    }

    /**
     * Hides the given post type without unregistering it.
     *
     * @since 0.4.0
     *
     * @param string $postType Post type key.
     */
    public function remove(string $postType): void
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

        add_filter('register_' . $this->postType . '_post_type_args', [$this, 'registerArgs']);
    }
}
