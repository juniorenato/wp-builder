<?php

namespace WPB\Comments;

class Comment
{
    /**
     * -------------------------------------------------------------------------
     * Disable WordPress comments
     * -------------------------------------------------------------------------
     *
     * @return void
     */
    public function disable(): void
    {
        add_action('admin_init', [$this, 'admin']);
        add_filter('comments_open', [$this, 'status']);
        add_filter('pings_open', [$this, 'status']);
        add_action('admin_menu', [$this, 'menu']);
    }

    /**
     * -------------------------------------------------------------------------
     * Remove from WP Admin
     * -------------------------------------------------------------------------
     *
     * @return void
     */
    public function admin(): void
    {
        global $pagenow;

        // Redirect from comments page
        if($pagenow === 'edit-comments.php') {
            wp_redirect(admin_url()); exit;
        }
        // Remove comments for all post types
        foreach(get_post_types() as $post_type) {
            if(post_type_supports($post_type, 'comments')) {
                remove_post_type_support($post_type, 'comments');
                remove_post_type_support($post_type, 'trackbacks');
            }
        }

        // Remove comments metabox from dasboard
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
    }

    /**
     * -------------------------------------------------------------------------
     * Force comment status to false
     * -------------------------------------------------------------------------
     *
     * @return boolean
     */
    public function status(): bool
    {
        return false;
    }

    /**
     * -------------------------------------------------------------------------
     * Remove from admin menu
     * -------------------------------------------------------------------------
     *
     * @return void
     */
    public function menu(): void
    {
        remove_menu_page('edit-comments.php');
    }
}
