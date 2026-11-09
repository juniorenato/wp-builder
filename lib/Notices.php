<?php

/**
 * Admin notices for developer mistakes.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB
 */

namespace WPB;

/**
 * Queues library errors and prints them in the WordPress admin.
 *
 * Invalid use of the fluent API reports here and returns control to the
 * caller. The request keeps running.
 *
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class Notices
{
    /**
     * Messages queued during this request.
     *
     * @var list<string>
     */
    private static array $errors = [];

    /**
     * Whether the admin notice hooks are registered.
     */
    private static bool $hooked = false;

    /**
     * Queues a developer error for the admin panel.
     *
     * Repeated messages are stored once.
     *
     * @param string $message Error message.
     */
    public static function error(string $message): void
    {
        if ($message === '' || in_array($message, self::$errors, true)) {
            return;
        }

        self::$errors[] = $message;
        self::hook();
    }

    /**
     * Prints the queued errors for administrators.
     *
     * @see https://developer.wordpress.org/reference/hooks/admin_notices/
     */
    public static function render(): void
    {
        if (
            self::$errors === []
            || !function_exists('current_user_can')
            || !current_user_can('manage_options')
        ) {
            return;
        }

        if (count(self::$errors) === 1) {
            printf(
                '<div class="notice notice-error"><p><strong>%s:</strong> %s</p></div>',
                esc_html(__('WP Builder', 'wpb')),
                esc_html(self::$errors[0])
            );

            return;
        }

        echo '<div class="notice notice-error"><p><strong>' . esc_html(__('WP Builder', 'wpb')) . '</strong></p><ul>';

        foreach (self::$errors as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }

        echo '</ul></div>';
    }

    /**
     * Registers the admin notice callbacks once.
     */
    private static function hook(): void
    {
        if (self::$hooked || !function_exists('add_action')) {
            return;
        }

        self::$hooked = true;

        add_action('admin_notices', [self::class, 'render']);
        add_action('network_admin_notices', [self::class, 'render']);
        add_action('user_admin_notices', [self::class, 'render']);
    }
}
