<?php

/**
 * Comment helpers.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Comments
 */

namespace WPB\Comments;

/**
 * Disables WordPress comments in the admin and on the front end.
 *
 * @since  0.6.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 *
 * @see https://developer.wordpress.org/reference/hooks/comments_open/
 * @see https://developer.wordpress.org/reference/hooks/pings_open/
 */
class Comment
{
    /**
     * Disables comments, pings, and related admin UI.
     *
     * @since 0.6.0
     *
     * @see https://developer.wordpress.org/reference/hooks/comments_open/
     * @see https://developer.wordpress.org/reference/hooks/pings_open/
     * @see https://developer.wordpress.org/reference/hooks/admin_init/
     * @see https://developer.wordpress.org/reference/hooks/admin_menu/
     */
    public function disable(): void
    {
        add_action('admin_init', [$this, 'admin']);
        add_filter('comments_open', [$this, 'status']);
        add_filter('pings_open', [$this, 'status']);
        add_action('admin_menu', [$this, 'menu']);
    }

    /**
     * Removes comment support and the comments dashboard widget.
     *
     * @since 0.6.0
     *
     * @global string $pagenow Current admin page.
     *
     * @see https://developer.wordpress.org/reference/functions/remove_post_type_support/
     * @see https://developer.wordpress.org/reference/functions/remove_meta_box/
     * @see https://developer.wordpress.org/reference/functions/wp_safe_redirect/
     */
    public function admin(): void
    {
        global $pagenow;

        if ($pagenow === 'edit-comments.php') {
            wp_safe_redirect(admin_url());
            exit;
        }

        foreach (get_post_types() as $post_type) {
            if (post_type_supports($post_type, 'comments')) {
                remove_post_type_support($post_type, 'comments');
                remove_post_type_support($post_type, 'trackbacks');
            }
        }

        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
    }

    /**
     * Forces comment and ping status to closed.
     *
     * @since 0.6.0
     *
     * @return bool Always false.
     *
     * @see https://developer.wordpress.org/reference/hooks/comments_open/
     * @see https://developer.wordpress.org/reference/hooks/pings_open/
     */
    public function status(): bool
    {
        return false;
    }

    /**
     * Removes the comments screen from the admin menu.
     *
     * @since 0.6.0
     *
     * @see https://developer.wordpress.org/reference/functions/remove_menu_page/
     */
    public function menu(): void
    {
        remove_menu_page('edit-comments.php');
    }
}
